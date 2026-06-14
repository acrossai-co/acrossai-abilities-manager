# Phase 1 Data Model: Remove Allowed Servers + Add Extensibility Hooks

This feature primarily **removes** data model surface. The "new" data is the shape of values passed through the extension hooks at runtime — not persisted entities.

## Persistent storage changes

### Modified: `{prefix}_acrossai_abilities` table

**Before**:

| Column | Type | Notes |
|---|---|---|
| ... | ... | (existing columns unchanged) |
| `mcp_servers` | `longtext` NULL | JSON-encoded `string[]` of MCP server IDs, OR `NULL` (semantic: "all servers allowed"). Empty array collapsed to `NULL` on save (per Feature 016). |
| ... | ... | (existing columns unchanged) |

**After**:

| Column | Type | Notes |
|---|---|---|
| ... | ... | (existing columns unchanged — no other changes) |

The `mcp_servers` column is removed from the schema definition. All other columns retain their definitions.

### No upgrade migration shipped

Per FR-011 / FR-012, this feature ships NO migration code. The plugin has not been launched yet, so there are no production installs to upgrade.

- **Fresh install**: BerlinDB creates the abilities table from the updated `AcrossAI_Abilities_Schema`; the `mcp_servers` column is never created.
- **Dev install with stale data**: the developer manually drops the abilities table (`wp db query "DROP TABLE {prefix}_acrossai_abilities"`) and reactivates the plugin, which triggers BerlinDB to recreate the table without the column.
- **No `ALTER TABLE`, no `__110()` version-bump method, no `$version` constant change.**

## Removed in-memory data shapes

### React Redux store

`OVERRIDABLE_FIELDS` in `src/js/abilities/store/index.js`:

```js
// BEFORE
const OVERRIDABLE_FIELDS = [
  'label',
  /* ...other fields... */
  'mcp_servers',  // ← removed
  /* ...other fields... */
];
```

The dirty-check (`JSON.stringify(currentDraft) !== JSON.stringify(savedDraft)` over `OVERRIDABLE_FIELDS`) continues to work for the remaining fields unchanged.

### React component state

Removed from `AbilityForm.jsx` (lines 252–255):
- `mcpServers` (the saved list of allowed server IDs)
- `mcpAdapterAvailable` (boolean — did the MCP server-list fetch succeed)
- `mcpServersError` (error message string for fetch failures)

Removed derived values (lines 621–630):
- `mcpSavedIds`, `mcpFetchedIds`, `mcpStaleIds`, `mcpAllItems`

### PHP

- `AcrossAI_Abilities_Row::$mcp_servers` property: removed.
- `AcrossAI_Sanitizer::MAX_MCP_SERVERS` constant: removed.
- `AcrossAI_Sanitizer::MAX_SERVER_ID_LENGTH` constant: removed.
- `AcrossAI_Sanitizer::sanitize_mcp_servers_array()` method: removed.
- `AcrossAI_Abilities_Sanitizer::sanitize_mcp_servers()` method + its calls in `sanitize_create_request()` / `sanitize_update_request()`: removed.

## Runtime data shapes (hook payloads — public contract per FR-010)

These are NOT persisted but ARE part of the public extension contract. Extensions read these shapes.

### `acrossai_abilities.form.extra_sections` — filter context

Second argument to `applyFilters`, third argument to `addFilter` callbacks:

```typescript
interface FormContext {
  abilityId: string | null;   // ability identifier or null for new ability
  slug: string | null;        // ability slug or null
  draft: Record<string, any>; // current in-form draft state (entire form draft)
  isNonDb: boolean;           // true if ability is registered in code, false if database-backed
}
```

Filter signature: `(sections: ReactNode[], context: FormContext) => ReactNode[]`. Initial value: `[]`.

### `acrossai_abilities.form.draft_changed` — action payload

Sole argument passed to subscriber:

```typescript
type DraftPayload = Record<string, any>;  // entire current draft state
```

### `acrossai_abilities.form.save_payload` — filter context

Second argument is the base payload (the object about to be POSTed); third argument is a context object:

```typescript
interface SaveContext {
  abilityId: string | null;
  slug: string | null;
  isNonDb: boolean;
}
// Filter signature: (payload: Record<string, any>, context: SaveContext) => Record<string, any>
```

### `acrossai_abilities_admin_localize_data` — PHP filter

Single argument: the `$data` array about to be JSON-encoded into `window.acrossaiAbilitiesManager`:

```php
// apply_filters( 'acrossai_abilities_admin_localize_data', array $data ): array
```

### `acrossai_abilities_form_settings_registered` — PHP action

No arguments. Fires once per admin page load, after the abilities admin script bundle is enqueued and the localize data is injected.

## Entity relationship summary

No new entities. No new relationships. The Ability entity loses one field (`mcp_servers`); all other fields and relationships unchanged. The hook payloads above are transient runtime structures, not entities.

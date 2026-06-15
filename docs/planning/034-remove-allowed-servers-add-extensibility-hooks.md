# Planning: Remove Allowed Servers + Add Extensibility Hooks (Feature 034)

Remove the per-ability **Allowed Servers** setting (added in feature 016) from
`acrossai-abilities-manager` entirely — PHP, JS/React, REST schema, sanitizer,
formatter, tests, and the underlying `mcp_servers` database column.

Replace the deleted UI block with a tiny, MCP-agnostic extension-point surface
(`@wordpress/hooks` `applyFilters` + `doAction` on the React side; `do_action`
+ `apply_filters` on the PHP side) so that a future `acrossai-mcp-manager`
plugin can inject its own server-mapping UI into the ability form when active,
without this plugin ever knowing that MCP servers exist.

This is the architectural inversion we agreed on in discussion: the abilities
plugin becomes a pure ability registry. Server ↔ ability mapping is owned by
the MCP manager plugin (greenfield, separate work) and reaches into the
abilities form only via the new hooks.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "034-remove-allowed-servers-add-extensibility-hooks"

# 2. Specify
/speckit.specify "Remove the per-ability 'Allowed Servers' setting from acrossai-abilities-manager
entirely (mcp_servers column + sanitizer + REST schema + formatter + React block + store field + tests).
Add a small React + PHP extension-point surface so a future acrossai-mcp-manager plugin can inject its
own server-mapping UI into the ability form without this plugin knowing about MCP servers.
Drop the mcp_servers column via a versioned migration. Six changes total:
(1) PHP — remove mcp_servers from schema, row, sanitizer, formatter, REST controllers;
(2) JS — remove allowed servers block from AbilityForm and mcp_servers from OVERRIDABLE_FIELDS in the store;
(3) DB — add migration to drop the mcp_servers column on upgrade;
(4) NEW React hooks — applyFilters 'acrossai_abilities.form.extra_sections', doAction
    'acrossai_abilities.form.draft_changed', filter 'acrossai_abilities.form.save_payload';
(5) NEW PHP hooks — do_action 'acrossai_abilities_form_settings_registered',
    apply_filters 'acrossai_abilities_admin_localize_data';
(6) Tests — delete mcp-servers-checkbox.test.js + any PHPUnit assertions on mcp_servers,
    add a short smoke-test MU plugin snippet for hook verification."
```

---

## Background — what is already in place; do NOT confuse with new work

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | Feature 016 created the Allowed Servers checkbox list — UI, REST, sanitizer, formatter, DB column. This feature **undoes all of that.** | read `docs/planning/016-allowed-servers-checkbox-list.md` |
| B-2 | DB column is `mcp_servers` (longtext, nullable, JSON-encoded array). Semantics: `null` = all servers; empty array is collapsed to null on save; populated array = specific server IDs. | `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` lines 186–191 |
| B-3 | The React form block uses live state (`mcpServers`, `mcpAdapterAvailable`, `mcpServersError`) and renders **154 lines** of UI between lines 1268–1422. | `src/js/abilities/components/AbilityForm.jsx` |
| B-4 | The Redux store includes `mcp_servers` in `OVERRIDABLE_FIELDS` for dirty-check / override tracking. | `src/js/abilities/store/index.js` line 48 |
| B-5 | Existing PHP hook naming uses `acrossai_abilities_*` (snake_case); existing JS hook naming is not yet established because no JS hooks exist today — this feature establishes `acrossai_abilities.form.*` as the dot-notation convention. | `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` lines 268, 286, 331, 343, 363 |
| B-6 | `@wordpress/hooks` is already a dependency of the abilities admin bundle (via `@wordpress/scripts`). No new package needed. | `package.json` — `@wordpress/hooks` resolves transitively; verify with `grep "@wordpress/hooks" package-lock.json` |
| B-7 | No `acrossai-mcp-manager` plugin exists yet. This feature creates the **seams** for a future plugin, not for an existing one. The hooks must work with zero subscribers. | `ls wp-content/plugins/` |
| B-8 | Existing `mcp_servers` row data is **discarded** on upgrade. A greenfield migration into the future MCP manager is out of scope for this feature and may never be needed. | confirmed in discussion |
| B-9 | The plugin uses BerlinDB-style schema management (per recent feature 028 "berlindb-upgrade"). The migration runner pattern lives in the existing Abilities Database module. | `includes/Modules/Abilities/Database/` — locate schema version constant and upgrade routine |

---

## Code Inventory — every `mcp_servers` reference

### PHP — Schema & Row

| File | Lines | Action |
|------|-------|--------|
| `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` | 186–191 | Delete column definition. |
| `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php` | 175–180, 267, 300–304 | Delete property, remove from JSON-decode list, remove from constructor decode. |

### PHP — Sanitization & Validation

| File | Lines | Action |
|------|-------|--------|
| `includes/Utilities/AcrossAI_Sanitizer.php` | 135–168 | Delete `MAX_MCP_SERVERS`, `MAX_SERVER_ID_LENGTH`, `sanitize_mcp_servers_array()` entirely. |
| `includes/Utilities/AcrossAI_Abilities_Sanitizer.php` | 239–241, 306–308 | Delete `sanitize_mcp_servers()`; remove calls from `sanitize_create_request()` and `sanitize_update_request()`. |

### PHP — REST

| File | Lines | Action |
|------|-------|--------|
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` | 103–113, 172–182 | Delete `mcp_servers` REST arg schema on both create + update routes. |
| same | 306–308, 526 | Delete `mcp_servers` extraction from sanitized request and from non-db override save path. |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php` | (`grep mcp_servers`) | Remove from any read response shaping. |

### PHP — Response Formatting

| File | Lines | Action |
|------|-------|--------|
| `includes/Utilities/AcrossAI_Abilities_Formatter.php` | 58, 98, 150, 192 | Remove `mcp_servers` from `format_for_response()`, `format_for_exposure()`, `format_merged_ability()`, `build_registry_args()`. |

### JS / React

| File | Lines | Action |
|------|-------|--------|
| `src/js/abilities/components/AbilityForm.jsx` | 252–255 | Delete state: `mcpServers`, `mcpAdapterAvailable`, `mcpServersError`. |
| same | 526 | Delete `mcp_servers` from save payload. |
| same | 604–616 | Delete `handleServerToggle`, `handleAllServersToggle`. |
| same | 621–630 | Delete derived: `mcpSavedIds`, `mcpFetchedIds`, `mcpStaleIds`, `mcpAllItems`. |
| same | 1268–1422 | Delete entire UI render block (label, loading/error/adapter-unavailable, checkbox list, stale indicator, plugin-declared hint). |
| same | (`grep` for server fetcher) | Delete the `useEffect` / REST fetch that populates `mcpServers`. |
| `src/js/abilities/store/index.js` | 48 | Remove `mcp_servers` from `OVERRIDABLE_FIELDS`. |

### Tests

| File | Action |
|------|--------|
| `tests/jest/abilities/mcp-servers-checkbox.test.js` | Delete entire file. |
| any PHPUnit test asserting `mcp_servers` in response/row | Locate via `grep -r mcp_servers tests/` and delete. |

---

## CHANGE-1 — PHP: remove `mcp_servers` from schema, row, sanitizer, formatter, REST

Strip every reference listed in the PHP inventory above. The field disappears
completely from the codebase. Specifically:

- Delete column definition and JSON-decode list entry.
- Delete sanitizer constants (`MAX_MCP_SERVERS`, `MAX_SERVER_ID_LENGTH`) and method.
- Delete sanitize callbacks and REST arg schema on both write routes.
- Delete extraction in the write controller flow and the non-db override path.
- Remove from all four formatter methods.
- Remove from read controller response shaping.

**Rules**
- Do NOT leave deprecation shims, `@deprecated` comments, or hidden internal
  fields "for later use." The plugin must read clean.
- The constants are MCP-specific and have no other consumer — delete them, do
  not generalize.
- Do not rename remaining sanitizer methods or constants.

---

## CHANGE-2 — JS/React: remove the Allowed Servers block from `AbilityForm.jsx`

Delete the state, handlers, derived calculations, UI block, save-payload field,
and server-list fetcher per the JS inventory above. Remove `mcp_servers` from
`OVERRIDABLE_FIELDS` in `src/js/abilities/store/index.js`.

Delete the Jest file `tests/jest/abilities/mcp-servers-checkbox.test.js`.

**Rules**
- After removal, `AbilityForm.jsx` must render with **zero console errors,
  zero React warnings, zero broken state references**.
- `npm run build` must succeed with no warnings about unused imports.
- The dirty-check via JSON.stringify must continue to work for the remaining
  overridable fields (label, readonly, callback_type, etc.).
- Do not leave commented-out code or `// TODO: re-add this` markers.

---

## CHANGE-3 — DB migration: drop `mcp_servers` column

Locate the schema version constant and the existing upgrade routine (BerlinDB
flow added in feature 028). Add a new migration step that executes:

```sql
ALTER TABLE {prefix}_acrossai_abilities DROP COLUMN mcp_servers;
```

Bump the schema version constant so the migration fires on next plugin load for
existing installs. Fresh installs simply never create the column (CHANGE-1
removed it from the schema definition; `dbDelta` will not re-add it).

**Rules**
- The migration must be idempotent — re-running it on a DB where the column is
  already gone must not error (use `SHOW COLUMNS LIKE 'mcp_servers'` guard).
- Wrap the `$wpdb->query()` call in the existing patterns (with `phpcs:ignore
  WordPress.DB.DirectDatabaseQuery.SchemaChange` if needed — schema changes are
  acceptable in migration code).
- No data preservation. Existing `mcp_servers` JSON values are discarded.

---

## CHANGE-4 — NEW: React extension points in `AbilityForm.jsx`

Where the deleted Allowed Servers block used to live, render an extensibility
slot that downstream plugins fill via `applyFilters`:

```jsx
import { applyFilters, doAction } from '@wordpress/hooks';
import { Fragment } from '@wordpress/element';

// Inside AbilityForm render, where the old Allowed Servers block was:
{ applyFilters(
    'acrossai_abilities.form.extra_sections',
    [],
    { abilityId, slug, draft, isNonDb }
).map( ( node, i ) => (
    <Fragment key={ i }>{ node }</Fragment>
) ) }
```

Fire a draft-observation action whenever the draft updates (so external plugins
can read current form state without poking into internals):

```jsx
useEffect( () => {
    doAction( 'acrossai_abilities.form.draft_changed', draft );
}, [ draft ] );
```

Wrap the save payload with a filter immediately before the REST POST so external
plugins can piggy-back data on the save call (optional — most extensions will
make their own REST calls, but this seam exists for the single-save use case):

```jsx
const payload = applyFilters(
    'acrossai_abilities.form.save_payload',
    basePayload,
    { abilityId, slug, isNonDb }
);
```

**Rules**
- All three hooks must produce correct, identical-to-baseline behavior with
  **zero subscribers** (defaults preserve current semantics).
- **No `mcp_*` naming anywhere** in the new code — the abilities plugin must
  remain MCP-agnostic. A reviewer who does not know about MCP must not be able
  to infer it from these hooks.
- Hook names use dot-notation (`acrossai_abilities.form.*`) — this is the new
  JS-side convention established by this feature.
- The slot accepts React nodes (elements, fragments, arrays of elements), not
  raw HTML strings.

---

## CHANGE-5 — NEW: PHP extension points

Add two hooks alongside the existing `acrossai_abilities_*` family:

```php
// In the abilities admin settings page bootstrap, after enqueueing the
// abilities admin React bundle:
do_action( 'acrossai_abilities_form_settings_registered' );

// In the wp_localize_script setup for the abilities admin bundle:
$data = apply_filters( 'acrossai_abilities_admin_localize_data', $data );
wp_localize_script( $handle, 'acrossaiAbilitiesData', $data );
```

These let external plugins:
- Enqueue their own React extension bundle at exactly the right moment, so the
  bundle loads **after** the abilities bundle and can call `addFilter` before
  the form mounts.
- Inject extra data into the localized JS object the form reads on mount (the
  future MCP manager will pass its server list this way, without the abilities
  plugin knowing it exists).

**Rules**
- Both hooks must be safe with zero subscribers — no required return value,
  no side-effects from the abilities plugin itself.
- Place the `do_action` after the `wp_enqueue_script` for the abilities admin
  bundle so extension plugins can rely on the bundle handle already being
  registered.
- Do NOT add any storage, option, or table for "extension data." Extensions
  manage their own persistence.

---

## CHANGE-6 — Tests + smoke-test reference

- Delete `tests/jest/abilities/mcp-servers-checkbox.test.js`.
- Delete any PHPUnit tests asserting `mcp_servers` in REST responses, row
  decoding, or formatter output (locate via `grep -r mcp_servers tests/`).
- Add a short MU-plugin snippet to `docs/planning/034-...md` (this file) or to
  a new `docs/dev/hooks-smoke-test.md` showing how to verify each new hook
  manually. Snippet content is shown in the verification checklist below.

**No new automated test is required** for "an extension renders here" — the
hooks are a thin pass-through, and adding a Jest test that mocks
`@wordpress/hooks` is more ceremony than value. Manual smoke verification is
sufficient.

---

## What must NOT change

- Do not add any `mcp_*` named hook, option, table, function, or constant in
  this plugin. The plugin must read as if MCP servers never existed here.
- Do not preserve `mcp_servers` row data on upgrade. Discard.
- Do not introduce a composer dependency on `acrossai-mcp-manager` or any
  third-party package.
- Do not add a `register_setting` or option storage for "extension data."
- Do not modify other abilities settings (label, readonly, callback_type,
  input/output schema, etc.).
- Do not change the abilities REST URL or response shape beyond removing
  `mcp_servers`.
- Do not change unrelated tests or refactor unrelated files for "cleanup."
- Do not add the new hooks anywhere outside `AbilityForm.jsx` and the admin
  settings page bootstrap. Resist scope creep — keep the extension surface
  minimal so future evolution stays cheap.

---

## CONSTRAINTS

- Approximately **10 files change**. New: 0 (the new code lives inside
  existing files). Deleted: 1 Jest test file. Modified: 9.
- After CHANGE-2, `npm run build` succeeds with zero warnings about unused
  imports, missing references, or unused variables.
- PHPStan level 8 passes with zero errors after CHANGE-1 and CHANGE-3.
- PHPCS passes with zero errors after all PHP changes.
- The abilities plugin must work standalone with **zero subscribers** to any
  new hook — default behavior identical to "Allowed Servers" never having
  existed.
- Hook names (`acrossai_abilities.form.extra_sections`,
  `acrossai_abilities.form.draft_changed`,
  `acrossai_abilities.form.save_payload`,
  `acrossai_abilities_form_settings_registered`,
  `acrossai_abilities_admin_localize_data`) are finalized in this feature and
  treated as a **public contract** — renaming after this PR ships requires a
  deprecation cycle.
- DB migration must be idempotent (safe to re-run; safe if column already gone).

---

## Spec-kit Commands

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer run phpcs
composer run phpstan
npm run build
npm run test

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual Verification Checklist

### CHANGE-1 — PHP removal

- [ ] `grep -rn "mcp_servers\|MCP_SERVERS\|sanitize_mcp_servers\|MAX_MCP_SERVERS\|MAX_SERVER_ID_LENGTH" includes/` returns zero results.
- [ ] `composer run phpstan` — zero errors.
- [ ] `composer run phpcs` — zero errors.
- [ ] `POST /wp-json/acrossai-abilities/v1/abilities` with `mcp_servers: [...]` in the body returns 2xx and the field is silently ignored (not persisted, not in response).

### CHANGE-2 — JS removal

- [ ] `grep -rn "mcp_servers\|mcpServers\|mcpAdapterAvailable\|handleServerToggle\|handleAllServersToggle" src/` returns zero results.
- [ ] `npm run build` succeeds with zero warnings.
- [ ] `npm run test` passes (after deleting `mcp-servers-checkbox.test.js`).
- [ ] Open an ability edit page in the WP admin — form renders, no "Allowed Servers" block, no console errors, no React warnings.

### CHANGE-3 — DB migration

- [ ] On a fresh install, `DESCRIBE {prefix}_acrossai_abilities;` shows no `mcp_servers` column.
- [ ] On an upgraded install (existing DB row had `mcp_servers` column populated), the column is dropped on next plugin load — verify via `SHOW COLUMNS FROM {prefix}_acrossai_abilities LIKE 'mcp_servers';` (empty result).
- [ ] Schema version constant has been bumped.
- [ ] No `wpdb->last_error` after activation / upgrade — check with WP_DEBUG_LOG on.
- [ ] Running the migration a second time does not error (idempotency).

### CHANGE-4 — React hooks

Drop the following MU plugin at
`wp-content/mu-plugins/test-abilities-hooks.php`:

```php
<?php
add_action( 'admin_enqueue_scripts', function () {
    if ( ! wp_script_is( 'acrossai-abilities-admin', 'enqueued' ) ) {
        return;
    }
    wp_add_inline_script( 'acrossai-abilities-admin', <<<'JS'
( function() {
    if ( ! window.wp || ! wp.hooks ) { return; }
    wp.hooks.addFilter(
        'acrossai_abilities.form.extra_sections',
        'test/extra',
        function ( sections, context ) {
            return [
                ...sections,
                wp.element.createElement(
                    'div',
                    { id: 'mcp-test', style: { padding: '8px', background: '#ffeecc' } },
                    'HELLO MCP — ability slug: ' + ( context.slug || '(new)' )
                ),
            ];
        }
    );
    wp.hooks.addAction(
        'acrossai_abilities.form.draft_changed',
        'test/draft',
        function ( draft ) {
            console.log( '[draft_changed]', draft );
        }
    );
    wp.hooks.addFilter(
        'acrossai_abilities.form.save_payload',
        'test/payload',
        function ( payload ) {
            return { ...payload, _probe: true };
        }
    );
}() );
JS
    );
} );
```

- [ ] `<div id="mcp-test">HELLO MCP — ability slug: …</div>` renders where the old block used to be.
- [ ] Typing in any form field logs `[draft_changed] {…}` to the console on each change.
- [ ] Saving the form sends `_probe: true` in the request body (verify in Network tab).
- [ ] Disable the MU plugin — the slot disappears, no errors, form continues to work.

### CHANGE-5 — PHP hooks

- [ ] After loading the abilities settings page, `did_action( 'acrossai_abilities_form_settings_registered' ) > 0`.
- [ ] An MU plugin hooking `acrossai_abilities_admin_localize_data` and adding a key sees that key in `window.acrossaiAbilitiesData` in the browser console.
- [ ] With zero subscribers, the abilities admin page renders identically to before this feature.

### Standalone behavior

- [ ] With NO external plugin subscribers, the abilities form renders, saves, edits, and round-trips identically to the post-removal baseline.
- [ ] Deactivating any third-party plugin causes no errors and no broken UI.
- [ ] `wp_register_ability()` in code continues to work for `mcp_servers`-free abilities (the registry no longer reads or stores that field).

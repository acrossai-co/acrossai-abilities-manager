# Plan: Fix Override Layer Bugs + AbilityForm UI Improvements (Feature 015)

**Feature Branch**: `015-fix-override-layer-bugs`  
**Spec**: `specs/015-fix-override-layer-bugs/spec.md`  
**Memory Synthesis**: `specs/015-fix-override-layer-bugs/memory-synthesis.md`  
**Reference Prototype**: `code-done-via-claude` @ `84324a3` (reference only — speckit implementation is authoritative)

---

## Architecture Summary

Six discrete bug fixes across three layers:

```
PHP Layer
├── AcrossAI_Ability_Merger.php     — normalize_registry() MCP paths + schema getters
├── AcrossAI_Abilities_Schema.php   — callback_type / status allow_null
├── AcrossAI_Abilities_Table.php    — set_schema() SQL DEFAULT NULL
└── AcrossAI_Abilities_Query.php    — save_override() return type + cache bypass
    AcrossAI_Abilities_Write_Controller.php — use Row return value directly

JS / React Layer
├── src/js/abilities/store/index.js — SET_SAVED seeds from _override
└── src/js/abilities/components/AbilityForm.jsx
    ├── Plugin declares hints from _registry
    └── Section reorder + sect-num + hide Status

Manual Step
└── ALTER TABLE wp_acrossai_abilities (developer, WP-CLI or phpMyAdmin)
```

**Constraint compliance:**
- `AcrossAI_Ability_Override_Processor` is **not touched** (CON-001)
- REST response shape (`_registry`, `_override`, top-level merged) is **not changed** (CON-002)
- `save_override()` remains slug-oriented at all call-sites (CON-003)
- `prepare_fields_for_write()` is **not changed** (CON-004)
- SEC-002 source-injection guard is **preserved** in `save_override()` (DEC-DB-WRITE-BOUNDARY-GUARD)

---

## Soft Conflicts Resolved

1. **CON-009 visibility relaxed**: `get_ability_by_id()` stays `public` (it is already legitimately used in `create_ability()`). The override stale-cache bug is resolved by `save_override()` returning the row directly — no separate `get_override_by_slug()` re-query in the non-db Write path. Add `@internal` annotation to `get_ability_by_id()` to signal its cache-bypass role.
2. **Error code change**: Write controller non-db save path changes from `'rest_update_failed'` to `'save_override_failed'` per FR-019 / SC-015-C3. No production consumers.

---

## Implementation Tasks

### T001 — Bug 1: Fix `normalize_registry()` in `AcrossAI_Ability_Merger.php`

**File**: `includes/Utilities/AcrossAI_Ability_Merger.php`

**Changes (in order):**

1. After reading `$annotations`, add `is_array()` guard:
   ```php
   $annotations = $ability->get_meta_item( 'annotations', array() );
   if ( ! is_array( $annotations ) ) {
       $annotations = array();
   }
   ```

2. Read `$mcp_meta` from nested `meta['mcp']` (before the `return array(...)` block):
   ```php
   $mcp_meta = $ability->get_meta_item( 'mcp', null );
   if ( ! is_array( $mcp_meta ) ) {
       $mcp_meta = array();
   }
   ```

3. In the `return array(...)`:
   - Replace `'input_schema' => $ability->get_meta_item( 'input_schema', null )` with:
     ```php
     'input_schema' => ( static function() use ($ability) {
         $s = $ability->get_input_schema();
         return ( is_array( $s ) && empty( $s ) ) ? null : $s;
     } )(),
     ```
   - Replace `'output_schema'` similarly using `get_output_schema()`.
   - Replace `'mcp_type' => $ann_or_meta( 'mcp_type' )` with:
     ```php
     'mcp_type' => $mcp_meta['type'] ?? $ann_or_meta( 'mcp_type' ),
     ```
   - Replace `'mcp_servers' => $ann_or_meta( 'mcp_servers' )` with:
     ```php
     'mcp_servers' => $mcp_meta['servers'] ?? $ann_or_meta( 'mcp_servers' ),
     ```
   - Add `'mcp_public' => $mcp_meta['public'] ?? null,` as a registry-only field (surfaced in `_registry`, NOT overridable — SC-015-C5).
     > Note: `mcp_public` is added to `normalize_registry()` output but NOT to `$overridable_fields` in the Merger. It appears only in `_registry`.

**Verification**: Third-party ability with nested `meta['mcp']` data surfaces correct `mcp_type`, `mcp_servers`, `mcp_public` in `_registry`. Schema fields show registered values.

---

### T002 — Bug 5 (part A): Update DB Schema

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`

Locate the `status` column definition (~line 84) and update:
- Add `'allow_null' => true`
- Change `'default' => 'draft'` → `'default' => null`

Locate the `callback_type` column definition (~line 123) and update:
- Add `'allow_null' => true`
- Change `'default' => 'noop'` → `'default' => null`

---

### T003 — Bug 5 (part B): Update Table SQL

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php`

In `set_schema()` SQL string:
- Change: `` `status` varchar(20) NOT NULL DEFAULT 'draft' `` → `` `status` varchar(20) DEFAULT NULL ``
- Change: `` `callback_type` varchar(50) NOT NULL DEFAULT 'noop' `` → `` `callback_type` varchar(50) DEFAULT NULL ``

---

### T004 — Bug 5 (part C) + Bug 4: Update `save_override()` in `AcrossAI_Abilities_Query.php`

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`

**Changes:**

1. **PHPDoc**: Update return type annotation from `@return bool` to `@return AcrossAI_Abilities_Row|false`.

2. **Method signature**: Change `): bool` → `): AcrossAI_Abilities_Row|false`

3. **INSERT path** — after `$result = $this->add_item( $fields );`:
   ```php
   // Explicitly null out fields not meaningful for override rows (Bug 5).
   if ( ! array_key_exists( 'callback_type', $fields ) ) {
       $fields['callback_type'] = null;
   }
   if ( ! array_key_exists( 'status', $fields ) ) {
       $fields['status'] = null;
   }
   ```
   Then replace:
   ```php
   return false !== $result && (int) $result > 0;
   ```
   With:
   ```php
   if ( false === $result || (int) $result <= 0 ) {
       return false;
   }
   // Bug 4: bypass BerlinDB slug-cache by re-reading via ID (not slug).
   return $this->get_ability_by_id( (int) $result );
   ```

4. **UPDATE path** — replace:
   ```php
   $result = $this->update_item( $existing->id, $fields );
   return false !== $result;
   ```
   With:
   ```php
   $result = $this->update_item( $existing->id, $fields );
   if ( false === $result ) {
       return false;
   }
   // Bug 4: bypass BerlinDB slug-cache by re-reading via ID (not slug).
   return $this->get_ability_by_id( $existing->id );
   ```

5. **`get_ability_by_id()` annotation**: Add `@internal` tag to its PHPDoc noting it is the canonical post-insert cache-bypass read and must not be called from the override save path in controllers (use the row returned by `save_override()` instead).

> Note: the `callback_type = null` / `status = null` assignment must happen **before** `$this->add_item( $fields )`, not after. Move the null-assignment block to before `add_item()` in the INSERT path (after `$fields['updated_at'] = $now`).

---

### T005 — Bug 4: Update Write Controller non-db path

**File**: `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`

In `update_ability()`, in the non-db upsert path (after `strip_protected_fields_for_non_db`):

Replace:
```php
$saved = $this->db_query->save_override( $slug, $fields );
if ( ! $saved ) {
    return new \WP_Error( 'rest_update_failed', __( 'Failed to save override.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
}

// SEC-GUARDRAIL-01: bust_cache() only — do not call other methods on Override Processor.
AcrossAI_Ability_Override_Processor::bust_cache();

$override_row = $this->db_query->get_override_by_slug( $slug );
$registry     = AcrossAI_Ability_Merger::normalize_registry( $registry_raw );
$merged       = AcrossAI_Ability_Merger::merge( $registry, $override_row );
return rest_ensure_response( AcrossAI_Abilities_Formatter::format_merged_ability( $merged ) );
```

With:
```php
$override_row = $this->db_query->save_override( $slug, $fields );
if ( false === $override_row ) {
    return new \WP_Error(
        'save_override_failed',
        __( 'Failed to confirm saved override.', 'acrossai-abilities-manager' ),
        array( 'status' => 500 )
    );
}

// SEC-GUARDRAIL-01: bust_cache() only — do not call other methods on Override Processor.
AcrossAI_Ability_Override_Processor::bust_cache();

$registry = AcrossAI_Ability_Merger::normalize_registry( $registry_raw );
$merged   = AcrossAI_Ability_Merger::merge( $registry, $override_row );
return rest_ensure_response( AcrossAI_Abilities_Formatter::format_merged_ability( $merged ) );
```

---

### T006 — Bug 3: Fix `SET_SAVED` reducer in `store/index.js`

**File**: `src/js/abilities/store/index.js`

At the top of the file (or near the `SET_SAVED` constant), add the overridable fields list:
```js
const OVERRIDABLE_FIELDS = [
  'label', 'description', 'category', 'callback_type', 'callback_config',
  'site_allowed', 'readonly', 'destructive', 'idempotent', 'show_in_rest',
  'show_in_mcp', 'mcp_type', 'mcp_servers',
];
```

In the `SET_SAVED` case, replace:
```js
draftAbility: saved ? { ...saved } : {},
```

With:
```js
draftAbility: (() => {
  if ( ! saved ) return {};
  const draft = { ...saved };
  if ( saved._override ) {
    OVERRIDABLE_FIELDS.forEach( ( field ) => {
      draft[ field ] = saved._override[ field ] ?? null;
    } );
  }
  return draft;
})(),
```

---

### T007 — Bug 2: Fix "Plugin declares:" hints in `AbilityForm.jsx`

**File**: `src/js/abilities/components/AbilityForm.jsx`

> **CRITICAL**: Read the exact tab depth of each target element before editing (BUG-ABILITYFORM-JSX-MIXED-DEPTHS). Do not assume uniform indentation.

**Changes:**
1. All 6 TriChip "Plugin declares:" hints — change source from `savedAbility.{field}` to `savedAbility?._registry?.{field}`:
   - `show_in_mcp`, `mcp_type`, `readonly`, `destructive`, `idempotent`, `show_in_rest`
2. Remove null guards on these hints. Pattern:
   - Before: `{ null !== savedAbility?.readonly && <HintRow label="Plugin declares:" value={...} /> }`
   - After: `<HintRow label="Plugin declares:" value={ savedAbility?._registry?.readonly ?? 'not set' } />`
3. Text field hints (Label, Category, Description) — same pattern; read from `savedAbility?._registry?.{field}` and render unconditionally with `'not set'` fallback.

---

### T008 — Bug 6: Reorder AbilityForm sections and fix sect-num

**File**: `src/js/abilities/components/AbilityForm.jsx`

> **CRITICAL**: Read actual tab depths before each section-level str_replace (BUG-ABILITYFORM-JSX-MIXED-DEPTHS).

**Desired section order:**

| # | Section | isNonDb | source=db |
|---|---------|---------|-----------|
| 1 | Identity | sect-num="1" | sect-num="1" |
| 2 | Site Permission | sect-num="2" | hidden |
| 3 | MCP Exposure | sect-num="3" | sect-num="2" |
| 4 | Annotation Overrides | sect-num="4" | sect-num="3" |
| 5 | Callback | sect-num="5" | sect-num="4" |
| 6 | Schema | sect-num="6" | sect-num="5" |

**Changes:**
1. Reorder the JSX section blocks to match the table above.
2. Update `sect-num` attributes for each section/variant.
3. Add `{ isNonDb && ... }` wrapper around the Status toggle row JSX to hide it for non-db.

---

### T009 — Manual: ALTER TABLE

**Executor**: Developer (WP-CLI or phpMyAdmin) — runs once on dev DB. No code task required.

```sql
ALTER TABLE wp_acrossai_abilities
  MODIFY COLUMN `status` varchar(20) DEFAULT NULL,
  MODIFY COLUMN `callback_type` varchar(50) DEFAULT NULL;
```

---

### T010 — Quality gates

```bash
composer run phpcs
composer run phpstan
npm run lint:js
npm run build
```

All four must pass with zero errors before the feature is considered complete.

---

## Task Dependency Order

```
T002 (Schema) → T003 (Table SQL) → T009 (ALTER TABLE)
T001 (Merger)                                           — independent
T004 (Query save_override)                              — independent
T005 (Write Controller) → depends on T004               — after T004
T006 (store SET_SAVED)                                  — independent
T007 (AbilityForm hints) ─┐
T008 (AbilityForm sections)┘ — both in same file; do in one pass
T010 (QA gates) → last, after all T001–T009
```

Recommended execution order: `T001 → T002 → T003 → T004 → T005 → T006 → T007+T008 → T009 → T010`

---

## Risk Register

| Risk | Mitigation |
|---|---|
| AbilityForm.jsx str_replace fails due to inconsistent tab depth | Read raw tab depth with `read_file` before each edit (BUG-ABILITYFORM-JSX-MIXED-DEPTHS) |
| Python str_replace on PHP files uses spaces not tabs | Use `\t` in all Python str_replace scripts for PHP (BUG-PHPCBF-TABS) |
| PHPStan L8 fails on `AcrossAI_Abilities_Row\|false` callers | Run PHPStan immediately after T004; check all callers of `save_override()` |
| `save_override()` return-type change silently breaks other callers | Grep all callers before implementing T004 |
| `mcp_public` accidentally added to `$overridable_fields` | Verify `AcrossAI_Ability_Merger::$overridable_fields` array is not changed |
| ALTER TABLE missing → new inserts still default to 'noop'/'draft' | T009 must be run before integration test SC-004 |

---

## Open Items for `/speckit.architecture-guard.governed-plan`

- Confirm `get_input_schema()` / `get_output_schema()` exist as public methods on `WP_Ability` in WP 6.9+.
- Confirm no other callers of `save_override()` outside the Write Controller.

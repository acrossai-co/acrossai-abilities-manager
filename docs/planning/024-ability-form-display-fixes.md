# Planning: Ability Form and List Display Fixes (Feature 024)

Fix five bugs surfaced by the `core/get-environment-info` ability: wrong source attribution
for core abilities, missing Type badge in the list, missing or wrong "Plugin declares" hints
in the form's Identity, MCP Exposure, and Annotations sections, the Callback section being
interactive instead of read-only for non-db abilities, and `inject_override_args()` not
injecting `label`, `description`, or `category` overrides into the live WP registry.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "024-ability-form-display-fixes"

# 2. Specify
/speckit.specify "Fix five bugs surfaced by the core/get-environment-info ability:

1. Source attribution: normalize_registry() in includes/Utilities/AcrossAI_Ability_Merger.php line 183
   always defaults source to 'plugin' via (string) cast — change the default to null so
   AcrossAI_Ability_Registry_Query.php line 79's empty() guard can invoke
   AcrossAI_Ability_Source_Detector::detect() for core abilities.

2. Type badge: TypeCell in src/js/abilities/components/AbilitiesList.jsx lines 90-94 returns — for
   non-db abilities because it only reads item.callback_type; add item._registry?.callback_type
   as a fallback.

3. Plugin declares hints: In src/js/abilities/components/AbilityForm.jsx —
   (a) Identity section (label ~line 839, description ~line 940, category ~line 884) has no
       registry-value hints at all — add <div className='desc'> hints below each input;
   (b) MCP Exposure hints (show_in_mcp ~line 1178, mcp_type ~line 1206) check savedAbility?.field
       (merged/overridden value) instead of savedAbility?._registry?.field (registry value) — fix
       the reference path;
   (c) Annotations hints (readonly ~line 1428, destructive ~line 1467, idempotent ~line 1508,
       show_in_rest ~line 1548) have the same wrong merged-value reference — fix all four.

4. Callback read-only: AbilityForm.jsx Callback section (isNonDb branch ~line 1622) shows
   interactive CALLBACK_CHIPS and CallbackConfigField — replace with a static read-only display
   mirroring how the Schema section handles isNonDb (badge for type, <pre> for config,
   'Not defined' fallback). Variant A (db) editable chips are unchanged.

5. Override injection: AcrossAI_Ability_Override_Processor::inject_override_args() line 290 in
   includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php injects site_allowed and
   meta fields but skips label, description, category — add injection of these three top-level
   WP Abilities API args after the site_allowed block (line 298) when the DB override row has a
   non-null, non-empty value.

Full root-cause analysis and exact before/after code in
docs/planning/024-ability-form-display-fixes.md."

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
vendor/bin/phpstan analyse --level=8
npm run build

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Background — what is already done; do NOT redo it

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | `AcrossAI_Ability_Registry_Query.php` already has the `empty($merged['source'])` guard at line 79 that calls `AcrossAI_Ability_Source_Detector::detect()` — the guard works; only the upstream default in `normalize_registry()` is wrong | `sed -n '75,85p' includes/Utilities/AcrossAI_Ability_Registry_Query.php` |
| B-2 | `AcrossAI_Ability_Source_Detector::detect()` already handles `'core'` provider correctly — it returns `'core'` when provider is `'wordpress-core'` or `'core'`; no changes needed there | `grep -n "wordpress-core\|'core'" includes/Utilities/AcrossAI_Ability_Source_Detector.php` |
| B-3 | `normalize_registry()` already derives `$provider = 'core'` from the slug prefix at line 159 for `core/get-environment-info` — provider detection is correct; only source default is wrong | `sed -n '155,165p' includes/Utilities/AcrossAI_Ability_Merger.php` |
| B-4 | `format_merged_ability()` already includes `_registry` in every list item (via `format_merged_collection()` → `format_merged_ability()`), so `item._registry` and `item._registry.callback_type` are available in the list view and form | `grep -n "_registry" includes/Utilities/AcrossAI_Ability_Merger.php \| head -20` |
| B-5 | `$overridable_fields` in `AcrossAI_Ability_Merger.php` (lines 27–40) already lists `'label'`, `'description'`, `'category'` — the merger applies them to REST responses; only the live-registry injection via `inject_override_args()` is missing | `sed -n '25,45p' includes/Utilities/AcrossAI_Ability_Merger.php` |
| B-6 | `AcrossAI_Abilities_Row` already exposes `$row->label`, `$row->description`, `$row->category` as typed properties — no schema or model changes needed | `grep -n "public \$label\|public \$description\|public \$category" includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php` |
| B-7 | DB save pipeline for label/description/category already works: `sanitize_update_request()` accepts all three, `save_override()` stores them, and the merger reads them back for REST responses | `grep -n "label\|description\|category" includes/Modules/Abilities/AcrossAI_Abilities_REST_Controller.php \| head -20` |
| B-8 | `inject_override_args()` is hooked on `wp_register_ability_args` at priority 100000 (PATH B only); the hook registration and PATH A / PATH B split require no changes | `grep -n "inject_override_args\|wp_register_ability_args" includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` |
| B-9 | `isNonDb` is already defined at `AbilityForm.jsx:625` as `const isNonDb = !!(savedAbility && 'db' !== savedAbility.source)` — the variable is available throughout the form; no new variable needed | `grep -n "isNonDb" src/js/abilities/components/AbilityForm.jsx \| head -5` |
| B-10 | `TYPE_MAP` in `AbilitiesList.jsx` already has all needed type entries — no new map entries are required for CHANGE-2 | `grep -n "TYPE_MAP" src/js/abilities/components/AbilitiesList.jsx \| head -10` |

---

## Root Cause Summary

| # | Symptom | Root cause | File : line |
|---|---------|------------|-------------|
| 1 | `core/get-environment-info` shows Source = "Plugin" | `normalize_registry()` uses `(string)` cast with `'plugin'` default — always non-empty, so `empty()` guard in Registry Query never fires | `AcrossAI_Ability_Merger.php:183` |
| 2 | Type badge shows `—` in list for non-db abilities | `TypeCell` only reads `item.callback_type`; WP core abilities have no top-level `callback_type`, only `_registry.callback_type` | `AbilitiesList.jsx:91` |
| 3a | MCP Exposure and Annotations hints show wrong (merged) value | Hints check `savedAbility?.field` (merged/overridden) instead of `savedAbility?._registry?.field` (registry-declared) | `AbilityForm.jsx:1178, 1206, 1428, 1467, 1508, 1548` |
| 3b | Identity (label/description/category) shows no "Plugin declares" hint | Identity section has no hint markup at all for `isNonDb` abilities | `AbilityForm.jsx:839, 884, 940` |
| 4 | Callback section is interactive for non-db abilities | `isNonDb` branch renders `CALLBACK_CHIPS` and `CallbackConfigField` instead of a read-only display | `AbilityForm.jsx:~1622` |
| 5 | Label/description/category overrides not applied to live WP_Ability | `inject_override_args()` injects `site_allowed` and meta fields but never sets `$args['label']`, `$args['description']`, `$args['category']` | `AcrossAI_Ability_Override_Processor.php:290–363` |

---

## Scope Rules

### In scope

- `includes/Utilities/AcrossAI_Ability_Merger.php` — one character change to `normalize_registry()` source default (line 183)
- `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` — three new `if` blocks inside `inject_override_args()` after the `site_allowed` injection (line 298)
- `src/js/abilities/components/AbilitiesList.jsx` — `TypeCell` fallback (lines 90–94)
- `src/js/abilities/components/AbilityForm.jsx`:
  - Identity section: add `<div className="desc">` hints after label, description, category inputs
  - MCP Exposure section: fix `show_in_mcp` and `mcp_type` hint references to use `._registry.`
  - Annotations section: fix `readonly`, `destructive`, `idempotent`, `show_in_rest` hint references to use `._registry.`
  - Callback section: replace `isNonDb` branch with read-only display

### Out of scope

- REST endpoint paths or response shapes
- Database schema or override save pipeline
- `AcrossAI_Ability_Source_Detector::detect()` — already correct
- `CallbackConfigField.jsx` — no changes
- Any form section other than Identity, MCP Exposure, Annotations, Callback
- `TYPE_MAP` entries — no new types
- `CALLBACK_CHIPS` or `CallbackTypeChips` for Variant A (db abilities)

---

## CHANGE-1 — Fix Source Attribution for Core Abilities

**File**: `includes/Utilities/AcrossAI_Ability_Merger.php` — line 183

### Root cause (detailed)

`normalize_registry()` (defined at line 148) returns a non-null `source` on every call:

```php
// Line 183 — current:
'source' => (string) $ability->get_meta_item( 'source', 'plugin' ),
```

The `(string)` cast combined with the `'plugin'` fallback means the returned array always
has `source = 'plugin'` for any ability that has no explicit `source` meta item. All WordPress
core abilities (`core/get-environment-info` and others) have no `source` meta item, so they
all get `source = 'plugin'`.

In `AcrossAI_Ability_Registry_Query.php` (lines 79–80):

```php
if ( empty( $merged['source'] ) ) {
    $merged['source'] = AcrossAI_Ability_Source_Detector::detect( $ability_data );
}
```

Because `$merged['source']` is never empty (always `'plugin'`), the detector is never invoked.

`AcrossAI_Ability_Source_Detector::detect()` checks the `provider` field and returns `'core'`
when provider is `'wordpress-core'` or `'core'`. For `core/get-environment-info`,
`normalize_registry()` already derives `$provider = 'core'` from the slug prefix at line 159:

```php
// Line 159:
$provider = false !== $slash_pos ? substr( $name, 0, $slash_pos ) : '';
```

The detector WOULD correctly return `'core'` — but it is never called because the guard at
line 79 is never reached.

### Fix

Remove the `(string)` cast and change the fallback from `'plugin'` to `null`:

```php
// Before (line 183):
'source' => (string) $ability->get_meta_item( 'source', 'plugin' ),

// After:
'source' => $ability->get_meta_item( 'source', null ),
```

When an ability explicitly registers with `source = 'plugin'` in its meta, that string value
is preserved unchanged. When no source is declared (all WP core abilities), `null` is returned,
`empty( null )` is `true`, and the detector runs and returns `'core'`.

### No other files need changing

`AcrossAI_Ability_Source_Detector::detect()` already handles `'core'`. The
`empty($merged['source'])` guard in `AcrossAI_Ability_Registry_Query.php` already handles
`null`. This is a one-token fix.

---

## CHANGE-2 — Show Type Badge in List for Non-DB Abilities

**File**: `src/js/abilities/components/AbilitiesList.jsx` — `TypeCell` function (lines 90–94)

### Root cause (detailed)

`normalize_registry()` reads `callback_type` via:

```php
'callback_type' => $ann_or_meta( 'callback_type' ),
```

WordPress core abilities do not set `callback_type` in their `annotations` array or as a
top-level meta item, so this returns `null`. The merger propagates `null` to the REST response
top-level. `TypeCell` reads only `item.callback_type`, which is `null`, and renders `—`.

The ability's registry data is available as `item._registry` on every list row (included by
`format_merged_collection()` → `format_merged_ability()`). `item._registry.callback_type`
holds the correct value when the plugin registered the ability with a callback type.

### Fix

```jsx
// Before (lines 90–94):
function TypeCell( { item } ) {
	if ( ! item.callback_type ) return <span>—</span>;
	const { cls, label } = TYPE_MAP[ item.callback_type ] || TYPE_MAP.noop;
	return <span className={ `tbadge ${ cls }` }>{ label }</span>;
}

// After:
function TypeCell( { item } ) {
	const type = item.callback_type || item._registry?.callback_type;
	if ( ! type ) return <span>—</span>;
	const { cls, label } = TYPE_MAP[ type ] || TYPE_MAP.noop;
	return <span className={ `tbadge ${ cls }` }>{ label }</span>;
}
```

### Key rules

- Do NOT add new `TYPE_MAP` entries — out of scope.
- The fallback chain is `item.callback_type` (merged/overridden) first, then
  `item._registry?.callback_type` (registry-declared). Merged value always wins.

---

## CHANGE-3 — Show Correct "Plugin declares" Hints in Identity, MCP Exposure, Annotations

**File**: `src/js/abilities/components/AbilityForm.jsx`

### Sub-change 3a — Fix hint references in MCP Exposure (lines ~1178, ~1206)

Both hints currently read the merged/potentially-overridden value instead of the
registry-declared value.

**`show_in_mcp` hint** (line ~1178):

```jsx
// Before — reads merged value (savedAbility.show_in_mcp):
hint={
    isNonDb && null !== savedAbility?.show_in_mcp
        ? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${true === savedAbility.show_in_mcp ? 'yes' : 'no'}`
        : null
}

// After — reads registry value (savedAbility._registry.show_in_mcp):
hint={
    isNonDb && null !== savedAbility?._registry?.show_in_mcp
        ? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${true === savedAbility._registry.show_in_mcp ? 'yes' : 'no'}`
        : null
}
```

**`mcp_type` hint** (line ~1206):

```jsx
// Before — reads merged value (savedAbility.mcp_type):
hint={
    isNonDb && savedAbility?.mcp_type
        ? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${savedAbility.mcp_type}`
        : null
}

// After — reads registry value (savedAbility._registry.mcp_type):
hint={
    isNonDb && savedAbility?._registry?.mcp_type
        ? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${savedAbility._registry.mcp_type}`
        : null
}
```

### Sub-change 3b — Fix hint references in Annotations (lines ~1428, ~1467, ~1508, ~1548)

All four Annotations hints have the same merged-value bug. Apply the `._registry.` fix to each.

**`readonly` hint** (line ~1428) — change `savedAbility?.readonly` → `savedAbility?._registry?.readonly` and `savedAbility.readonly` → `savedAbility._registry.readonly`:

```jsx
// Before:
hint={
    isNonDb && null !== savedAbility?.readonly
        ? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${true === savedAbility.readonly ? 'yes' : 'no'}`
        : __('Does this ability mutate state?', 'acrossai-abilities-manager')
}

// After:
hint={
    isNonDb && null !== savedAbility?._registry?.readonly
        ? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${true === savedAbility._registry.readonly ? 'yes' : 'no'}`
        : __('Does this ability mutate state?', 'acrossai-abilities-manager')
}
```

Apply the same `._registry.` correction to **`destructive`** (~line 1467),
**`idempotent`** (~line 1508), and **`show_in_rest`** (~line 1548) using the same pattern.

### Sub-change 3c — Add "Plugin declares" hints to Identity section (lines ~839, ~884, ~940)

The Identity section currently shows no registry-declared values for `isNonDb` abilities.
Add a `<div className="desc">` hint below each editable Identity field.

Fields and hint sources:

| Field | Input location | Hint source |
|-------|---------------|-------------|
| Label | `AbilityForm.jsx:~839` | `savedAbility?._registry?.label` |
| Category | `AbilityForm.jsx:~884` | `savedAbility?._registry?.category` |
| Description | `AbilityForm.jsx:~940` | `savedAbility?._registry?.description` |

Pattern (matching existing `<div className="desc">` style used throughout the form):

```jsx
{isNonDb && savedAbility?._registry?.label && (
    <div className="desc">
        {__('Plugin declares:', 'acrossai-abilities-manager')}{' '}
        {savedAbility._registry.label}
    </div>
)}
```

Apply the same pattern for `category` and `description`, each placed immediately after their
respective input element.

**Do not add hints to Slug or Status** — Slug is always read-only for non-db and Status is
shown as static text, not an input, for non-db abilities.

Only show the hint when `isNonDb && savedAbility?._registry?.{field}` is truthy — never
render "Plugin declares: null" or "Plugin declares: undefined".

---

## CHANGE-4 — Make Callback Section Read-Only for Non-DB Abilities

**File**: `src/js/abilities/components/AbilityForm.jsx` — Section 6 (Callback), `isNonDb` branch (~line 1622)

### Current behaviour

For `isNonDb` abilities, the Callback section currently renders:

- `CALLBACK_CHIPS` chip-buttons allowing type selection (interactive — BUG)
- `CallbackConfigField` for editing config (interactive — BUG)
- A `<div className="desc">` hint "Registered type: X" beneath the chips

This is incorrect. Non-db abilities execute via the plugin's registered callback, which cannot
be overridden by the admin. Showing editable controls implies the user can change execution
behaviour, which they cannot.

### Target behaviour

Mirror exactly how Section 7 (Schema) handles `isNonDb`: render a static, read-only display
of the registry-declared values. No chip-buttons, no `CallbackConfigField`.

Pattern from Schema section (existing — reference only, do not import):

```jsx
// Schema does this for isNonDb:
{regInput !== null ? (
    <pre className="rt code readonly-schema">
        {JSON.stringify(regInput, null, 2)}
    </pre>
) : (
    <span className="desc">{__('Not defined', 'acrossai-abilities-manager')}</span>
)}
```

### Replacement markup for Callback `isNonDb` branch

```jsx
// Type row — replaces the entire current isNonDb ? (chips...) block:
{isNonDb ? (
    <div className="ff">
        {savedAbility?._registry?.callback_type ? (
            <span className="tbadge">
                {savedAbility._registry.callback_type}
            </span>
        ) : (
            <span className="desc">
                {__('Not defined', 'acrossai-abilities-manager')}
            </span>
        )}
    </div>
) : (
    /* existing Variant A editable chips — UNCHANGED */
)}

// Config row — rendered only when callback_type is present (add below the type row):
{isNonDb && savedAbility?._registry?.callback_type && (
    <div className="fr">
        <label className="fl">
            {__('Config', 'acrossai-abilities-manager')}
        </label>
        <div className="ff">
            {savedAbility._registry.callback_config ? (
                <pre className="rt code readonly-schema">
                    {JSON.stringify(
                        savedAbility._registry.callback_config,
                        null,
                        2
                    )}
                </pre>
            ) : (
                <span className="desc">
                    {__('Not defined', 'acrossai-abilities-manager')}
                </span>
            )}
        </div>
    </div>
)}
```

### Key rules

- The existing "Registered type: X" `<div className="desc">` hint that currently appears
  below the chips is **replaced entirely** by this read-only display — do not keep both.
- Variant A (db abilities, `!isNonDb` branch) chip-buttons and `CallbackConfigField` remain
  **completely unchanged**.
- `CallbackConfigField.jsx` itself is **not modified**.

---

## CHANGE-5 — Inject label/description/category Overrides into Live WP Registry

**File**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` — `inject_override_args()` (line 290)

### Root cause (detailed)

`inject_override_args()` is registered on `wp_register_ability_args` at priority 100000
(PATH B only). Its field path map (docblock lines 262–288) documents:

```
site_allowed              → $args['site_allowed']
readonly/destructive/
  idempotent              → $args['meta']['annotations']['<key>']
show_in_rest              → $args['meta']['show_in_rest']
show_in_mcp               → $args['meta']['mcp']['public']
mcp_type                  → $args['meta']['mcp']['type']
mcp_servers               → $args['meta']['mcp']['servers']
permission_callback       → $args['permission_callback']
```

`label`, `description`, and `category` are absent from this map. They are top-level keys in
the `wp_register_ability()` args signature (same level as `site_allowed`). The DB save works
correctly — `sanitize_update_request()` accepts all three, `save_override()` stores them, and
the merger reads them for REST responses via `$overridable_fields`. But the live `WP_Ability`
object (used outside REST, e.g. by other plugins, MCP adapters, or direct WP API calls) never
receives the overridden values because `inject_override_args()` never sets `$args['label']`,
`$args['description']`, or `$args['category']`.

### Fix

Inside the `if ( isset( self::$overrides_cache[ $slug ] ) )` block, immediately after the
`site_allowed` injection (line 298), add:

```php
// Before (line 298, existing site_allowed block):
// Top-level field — skip null to preserve Inherit semantics (FR-006).
if ( null !== $row->site_allowed ) {
    $args['site_allowed'] = $row->site_allowed;
}

// After — change comment, then add three new blocks:
// Top-level fields — skip null/empty to preserve Inherit semantics (FR-006).
if ( null !== $row->site_allowed ) {
    $args['site_allowed'] = $row->site_allowed;
}
if ( null !== $row->label && '' !== $row->label ) {
    $args['label'] = $row->label;
}
if ( null !== $row->description && '' !== $row->description ) {
    $args['description'] = $row->description;
}
if ( null !== $row->category && '' !== $row->category ) {
    $args['category'] = $row->category;
}
```

Both `null` and empty-string are treated as "not set" (Inherit) — consistent with the
semantics of `site_allowed` (which uses `null !== $row->site_allowed` only, but label/
description/category can be saved as empty string to mean "cleared", so both guards are
needed here).

### Update the docblock field map

Add the three new fields to the existing `inject_override_args()` docblock (lines 262–288):

```
label                     → $args['label']           (top-level WP Abilities API field)
description               → $args['description']     (top-level WP Abilities API field)
category                  → $args['category']        (top-level WP Abilities API field)
```

### No other files need changing

`AcrossAI_Abilities_Row` already exposes `$row->label`, `$row->description`, `$row->category`.
`AcrossAI_Ability_Merger::$overridable_fields` already lists all three (lines 28–30).
No DB schema changes. No REST changes.

---

## What Must NOT Change

- REST endpoint paths, response field names, or response shapes
- Database schema or override save pipeline
- Behaviour of Variant A (source=db) — all `isNonDb`-gated changes affect only non-db abilities
- `CALLBACK_CHIPS` or `CallbackTypeChips` for Variant A (db abilities) — unchanged
- `AcrossAI_Ability_Source_Detector::detect()` — already correct, no changes
- Any form section other than Identity, MCP Exposure, Annotations, and Callback
- `CallbackConfigField.jsx` — no changes needed
- `TYPE_MAP` entries in `AbilitiesList.jsx` — no new types
- PATH A / PATH B logic in `AcrossAI_Ability_Override_Processor` — only `inject_override_args()` body changes
- `AcrossAI_Ability_Registry_Query.php` — already correct, no changes

---

## CONSTRAINTS

- Exactly 4 files change in source (before build):
  `includes/Utilities/AcrossAI_Ability_Merger.php`,
  `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`,
  `src/js/abilities/components/AbilitiesList.jsx`,
  `src/js/abilities/components/AbilityForm.jsx`.
  Plus 2 rebuilt artifacts: `build/js/abilities.js`, `build/js/abilities.asset.php`.
- `composer run phpcs` must exit 0 after PHP changes.
- `vendor/bin/phpstan analyse --level=8` must exit 0 after PHP changes.
- `npm run build` must succeed with no webpack errors after JS changes.
- All `isNonDb`-gated JS changes must be conditional — Variant A behaviour is identical before and after.
- The `null` → `null` default change in `normalize_registry()` is a one-token fix; do not refactor surrounding code.
- `inject_override_args()` docblock must be updated to list the three new fields — PHPStan L8 enforces accurate doc types.

---

## Expected Files Changed

```text
includes/Utilities/AcrossAI_Ability_Merger.php               (CHANGE-1)
includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php  (CHANGE-5)
src/js/abilities/components/AbilitiesList.jsx                (CHANGE-2)
src/js/abilities/components/AbilityForm.jsx                  (CHANGE-3, CHANGE-4)
build/js/abilities.js                                        (rebuilt artifact)
build/js/abilities.asset.php                                 (rebuilt artifact)
```

---

## Manual Verification Checklist

### CHANGE-1 — Source attribution

- [ ] `grep -n "get_meta_item.*source" includes/Utilities/AcrossAI_Ability_Merger.php` — result must NOT contain `(string)` or `'plugin'` as default.
- [ ] Open Abilities list in admin; filter by Source = "Core". `core/get-environment-info` (and all other `core/*` abilities) shows badge "Core", not "Plugin".
- [ ] Filter by Source = "Plugin". `core/get-environment-info` does NOT appear.
- [ ] Plugin abilities that explicitly register with `source = 'plugin'` in their meta continue to show "Plugin".
- [ ] `composer run phpcs` — zero errors in `AcrossAI_Ability_Merger.php`.
- [ ] `vendor/bin/phpstan analyse --level=8` — zero errors in `AcrossAI_Ability_Merger.php`.

### CHANGE-2 — Type column

- [ ] `grep -n "item.callback_type\|_registry" src/js/abilities/components/AbilitiesList.jsx` — `TypeCell` now references `item._registry?.callback_type`.
- [ ] `core/get-environment-info` row shows a Type badge (not `—`) when the ability has a `callback_type` in its `_registry` data.
- [ ] Plugin-sourced abilities with a top-level `callback_type` continue to show the correct badge (merged value still wins).
- [ ] DB abilities with a `callback_type` override continue to show the correct badge.

### CHANGE-3 — "Plugin declares" hints

- [ ] `grep -n "_registry" src/js/abilities/components/AbilityForm.jsx | grep "hint"` — all hint props that reference `Plugin declares` now use `._registry.` path, not the merged-value path.
- [ ] Open edit form for a non-db ability (e.g. `core/get-environment-info`). Label, Description, and Category fields each show a `Plugin declares: ...` hint below the input.
- [ ] MCP Exposure `Show in MCP` hint shows the registry-declared value, not the possibly-overridden merged value.
- [ ] MCP Exposure `MCP Type` hint shows the registry-declared value.
- [ ] Annotations `Readonly`, `Destructive`, `Idempotent`, `Show in REST` hints each show the registry-declared value.
- [ ] When no registry value is defined for a field, the hint is absent — never renders "Plugin declares: null" or "Plugin declares: undefined".
- [ ] Variant A (db ability) form is unchanged — no new hints appear.

### CHANGE-4 — Callback read-only

- [ ] `grep -n "CALLBACK_CHIPS\|CallbackConfigField" src/js/abilities/components/AbilityForm.jsx` — neither appears inside an `isNonDb` truthy branch.
- [ ] Callback section for a non-db ability shows the registered `callback_type` as a static `<span className="tbadge">`, no chip-buttons.
- [ ] When `callback_config` is defined on the registry, it appears as a `<pre className="rt code readonly-schema">` read-only block.
- [ ] When `callback_type` is null/undefined, the Callback section shows the "Not defined" span.
- [ ] The old "Registered type: X" `<div className="desc">` hint below the chips is gone — not duplicated.
- [ ] Variant A (db ability) Callback section is completely unchanged — chips and `CallbackConfigField` still present and editable.
- [ ] `npm run build` succeeds with no webpack errors.

### CHANGE-5 — Override injection (label/description/category)

- [ ] `grep -n "row->label\|row->description\|row->category" includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` — all three appear in `inject_override_args()`.
- [ ] Docblock for `inject_override_args()` lists `label → $args['label']`, `description → $args['description']`, `category → $args['category']`.
- [ ] Save a label override for a non-db ability via the admin form; confirm `get_registered_ability('slug')->get_label()` returns the overridden value (not the plugin-declared default).
- [ ] Save a description override; confirm it is reflected on subsequent reads outside REST.
- [ ] Clearing a label override (blank the field, save) restores the plugin-declared value — null/empty-string is not injected.
- [ ] Variant A (db ability) is unaffected — its label/description/category come from the DB row directly.
- [ ] `composer run phpcs` — zero errors in `AcrossAI_Ability_Override_Processor.php`.
- [ ] `vendor/bin/phpstan analyse --level=8` — zero errors in `AcrossAI_Ability_Override_Processor.php`.

### Quality gates (all changes)

- [ ] `composer run phpcs` exits 0 across all changed PHP files.
- [ ] `vendor/bin/phpstan analyse --level=8` exits 0 across all changed PHP files.
- [ ] `npm run build` exits 0, no webpack errors or warnings.

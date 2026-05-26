# Planning: Fix Override Layer Bugs + AbilityForm UI Improvements (Feature 015)

This document drives the formal Spec-Kit workflow for a set of ad-hoc fixes that were
prototyped in the `code-done-via-claude` branch. Running them through the speckit flow
ensures they are properly specified, planned, tested, and memory-captured before being
merged to the main development branch.

**Reference branch:** `code-done-via-claude` (commit `84324a3`)
**Scope:** 6 discrete fixes spanning the PHP override layer, BerlinDB query layer,
DB schema, Redux store, and AbilityForm JSX.

---

## Phase 1: Setup & Specification

```markdown
# 1. Create a numbered feature branch
/speckit.git.feature "fix-override-layer-bugs"

# 2. Specify the requirements
# Use the detailed prompt below for the description
/speckit.specify "Fix six bugs in the non-db ability override layer: registry normalization, Plugin declares hints, draftAbility seeding, first-save stale cache, DB schema defaults, and AbilityForm section order."
```

### Detailed Description for `/speckit.specify`

> **GOAL:** Harden the non-db ability override edit flow. Six discrete bugs were
> identified and prototyped in `code-done-via-claude`. This feature formalises them
> as a specced, planned, and tested implementation.
>
> ---
>
> **BUG 1 — `normalize_registry()` reads wrong paths for MCP fields and schema**
>
> File: `includes/Utilities/AcrossAI_Ability_Merger.php`
>
> - External plugins (mcp-adapter convention) register MCP fields under
>   `meta['mcp']['type' | 'public' | 'servers']` (nested). `normalize_registry()`
>   was reading flat `meta.mcp_type` / `meta.mcp_servers` via `get_meta_item()`,
>   which always returned null for these plugins.
> - `input_schema` and `output_schema` are first-class `WP_Ability` properties
>   accessible via `get_input_schema()` / `get_output_schema()`. They were being
>   read via `get_meta_item()` which always returns null.
> - `$annotations` was not guarded as array before use.
>
> **Fix requirements:**
> - Read `$mcp_meta = get_meta_item('mcp', null)` and access `$mcp_meta['type']`,
>   `$mcp_meta['public']`, `$mcp_meta['servers']` with fallback to flat
>   `$ann_or_meta()` for plugins that use flat keys.
> - Use `get_input_schema()` / `get_output_schema()` for schema fields; normalise
>   empty array `[]` → `null`.
> - Add `is_array()` guard on `$annotations`.
>
> ---
>
> **BUG 2 — "Plugin declares:" hints show wrong values and hide when null**
>
> File: `src/js/abilities/components/AbilityForm.jsx`
>
> The form shows a "Plugin declares: X" hint below overridable fields for non-db
> abilities. Two bugs:
> - Hints were reading `savedAbility.readonly` (merged/effective value) instead of
>   `savedAbility._registry.readonly` (pure WP registration value). For a plugin
>   that sets `readonly: true`, the merged value was `true` but the hint should
>   show the raw declared value from `_registry`.
> - Guards like `null !== savedAbility?.field` prevented the hint from rendering
>   when the registry value is null/false, even though "not set" should always be shown.
>
> **Fix requirements:**
> - All 6 TriChip hints (show_in_mcp, mcp_type, readonly, destructive, idempotent,
>   show_in_rest) must read from `savedAbility._registry.*` not top-level.
> - Remove null guards — hints always render with `'not set'` fallback when the
>   registry value is null/undefined.
> - Same pattern for Label, Category, Description "Plugin declares:" text hints.
>
> ---
>
> **BUG 3 — `SET_SAVED` reducer seeds `draftAbility` from merged values**
>
> File: `src/js/abilities/store/index.js`
>
> When `SET_SAVED` is dispatched (on ability load or after save), the draft was
> seeded with `{ ...saved }` — the merged/effective top-level values. For non-db
> abilities, `saved.readonly = true` (inherited from registry) seeded the TriChip
> as "yes" instead of the correct "default/inherit" (null).
>
> **Fix requirements:**
> - When `saved._override` is present, patch all overridable fields in `draftAbility`
>   from `_override[field]` (not merged top-level).
> - A null value in `_override` means "not set / inherit" → TriChip must show
>   "default".
> - Overridable fields: `label`, `description`, `category`, `callback_type`,
>   `callback_config`, `site_allowed`, `readonly`, `destructive`, `idempotent`,
>   `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`.
>
> ---
>
> **BUG 4 — First save of a non-db override returns `has_override: false`**
>
> Files: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`,
> `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`
>
> On the very first save of an override for a non-db ability:
> 1. `save_override()` calls `get_override_by_slug()` — BerlinDB caches a null
>    result for this slug (no row exists yet).
> 2. `add_item()` inserts the new row.
> 3. The Write controller calls `get_override_by_slug()` again — stale cache
>    returns null → `has_override: false` in the response.
> 4. The frontend `SET_SAVED` reducer resets all overridable fields back to null
>    (treating it as "no override") → UI reverts to "default" even though save succeeded.
>
> On page reload the correct data appears (cache cleared between requests).
>
> **Fix requirements:**
> - Change `save_override()` return type from `bool` to `AcrossAI_Abilities_Row|false`.
> - INSERT path: after `add_item()` returns the new integer ID, re-read the row using
>   an ID-based query (`query(['id' => $new_id, 'number' => 1])`) **inside
>   `save_override()` only** — this bypasses BerlinDB's stale slug query cache.
>   This is an internal implementation detail of `save_override()`; all callers remain
>   slug-based and are unaware of this mechanism.
> - UPDATE path: after `update_item()`, re-read the row using the same ID-based query
>   (`query(['id' => $existing->id, 'number' => 1])`).
> - The helper method `get_ability_by_id( int $id )` encapsulates this ID-based query.
>   It is only ever called from within `save_override()` — never from controllers or
>   external code. The slug remains the only external identifier throughout the system.
> - Write controller: use the returned row directly — remove the separate
>   `get_override_by_slug()` re-query.
>
> **Clarification (SC-015-C1):** `get_ability_by_id()` already exists in
> `AcrossAI_Abilities_Query` (added as part of this fix). No new method needs to be
> created. Its scope is strictly internal to `save_override()` — it is a cache-bypass
> mechanism, not a new public API pattern. All public-facing and controller-level
> lookups continue to use slug as the identifier.
>
> ---
>
> **BUG 5 — DB defaults `callback_type='noop'` and `status='draft'` on override INSERT**
>
> Files: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`,
> `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`,
> `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php`
>
> When saving an override for a non-db ability, the payload does not include
> `callback_type` or `status` (not meaningful for override rows). The DB schema
> defined both as `NOT NULL DEFAULT 'noop'` / `NOT NULL DEFAULT 'draft'`, so MySQL
> silently applied these defaults on INSERT. The DB ended up with
> `callback_type='noop'` and `status='draft'` for every new non-db override row.
>
> **Fix requirements:**
> - In `save_override()` INSERT path: explicitly set `callback_type = null` and
>   `status = null` when those keys are absent from `$fields`.
> - Update `AcrossAI_Abilities_Schema.php`: both columns to `allow_null: true,
>   default: null`.
> - Update `AcrossAI_Abilities_Table.php` `set_schema()` SQL: both columns to
>   `DEFAULT NULL` (remove `NOT NULL`).
> - Run `ALTER TABLE wp_acrossai_abilities` to apply to the live DB (this is a new
>   plugin — no migration script needed; direct ALTER is acceptable).
>
> ---
>
> **BUG 6 — AbilityForm section order and numbering**
>
> File: `src/js/abilities/components/AbilityForm.jsx`
>
> The form sections were in an illogical order (Identity → Callback → Schema →
> MCP Exposure → Site Permission → Annotations). The desired order groups related
> concerns and puts governance controls (Site Permission, MCP, Annotations) before
> implementation details (Callback, Schema).
>
> **Fix requirements:**
> - Reorder sections to: Identity → Site Permission → MCP Exposure →
>   Annotation Overrides → Callback → Schema.
> - Update `sect-num` values for both variants:
>   - Variant B (isNonDb): 1 / 2 / 3 / 4 / 5 / 6
>   - Variant A (source=db): 1 / — / 2 / 3 / 4 / 5
> - Hide the Status toggle row entirely for isNonDb (override rows have no lifecycle
>   status — it's always null in the DB).
>
> ---
>
> **CONSTRAINTS:**
> - Do NOT modify `AcrossAI_Ability_Override_Processor` — it is PATH A / PATH B
>   split logic, unchanged by these fixes.
> - Do NOT change the REST response shape — `_registry`, `_override`, and
>   top-level merged fields must remain as-is.
> - `save_override()` must remain slug-oriented at the call-site. The ID-based
>   cache bypass is an internal implementation detail of `save_override()` only.
> - `prepare_fields_for_write()` enum guards already pass null values unchanged —
>   no changes needed there.
> - PHPStan level 8 must pass after the return-type change on `save_override()`.

---

## Phase 2: Planning & Validation

```markdown
# 3. Generate technical plan with memory context
/speckit.memory-md.plan-with-memory

# 4. Validate plan against Constitution and prior architecture decisions
/speckit.architecture-guard.governed-plan

# 5. Review plan for security gaps
/speckit.security-review.plan
```

---

## Phase 3: Task Generation

```markdown
# 6. Generate implementation tasks
/speckit.tasks

# 7. Validate tasks for architecture drift
/speckit.architecture-guard.governed-tasks

# 8. Review task sequencing for security
/speckit.security-review.tasks
```

---

## Phase 4: Implementation & Verification

```markdown
# 9. Execute tasks with governance and security review
/speckit.architecture-guard.governed-implement

# 10. Quality checks
npm run build
composer run phpcs
composer run phpstan
npm run lint:js
```

> **Note:** The reference implementation already exists in `code-done-via-claude`.
> During `/speckit.implement`, compare generated code against that branch for
> correctness. The speckit implementation is the authoritative version — do not
> blindly copy from the prototype branch.

---

## Phase 5: Review, Memory & Commit

```markdown
# 11. Cross-artifact consistency check
/speckit.analyze

# 12. Post-implementation architecture drift analysis
/speckit.architecture-guard.architecture-review

# 13. Final security audit of staged changes
/speckit.security-review.staged

# 14. Extract durable knowledge to project memory
/speckit.memory-md.capture-from-diff

# 15. Commit with structured message
/speckit.git.commit
```

---

## Manual Verification Checklist

### PHP layer
- [ ] `normalize_registry()` returns correct `show_in_mcp`, `mcp_type`, `mcp_servers`
      for abilities registered with nested `meta['mcp']`.
- [ ] `normalize_registry()` returns correct `input_schema` / `output_schema` via getters.
- [ ] First save of a non-db override returns `has_override: true` in the REST response
      (no page reload required).
- [ ] Subsequent saves return the updated `_override` values correctly.
- [ ] New override rows in `wp_acrossai_abilities` have `callback_type = NULL` and
      `status = NULL` (not `'noop'` / `'draft'`).
- [ ] PHPStan level 8 passes with zero errors.
- [ ] PHPCS passes with zero errors.

### JS/React layer
- [ ] Opening a non-db ability: all TriChips show "default" when `_override[field]` is null.
- [ ] "Plugin declares:" hints show for ALL overridable fields (even when registry
      value is null/false — shows "not set").
- [ ] "Plugin declares:" values match `savedAbility._registry.*`, not merged top-level.
- [ ] After first save: TriChips reflect the saved `_override` values without page reload.
- [ ] AbilityForm section order: Identity → Site Permission → MCP Exposure →
      Annotation Overrides → Callback → Schema.
- [ ] Section numbers correct for both isNonDb (1–6) and source=db (1, 2–5) variants.
- [ ] ESLint passes with zero errors.
- [ ] Webpack build is clean (`npm run build`).

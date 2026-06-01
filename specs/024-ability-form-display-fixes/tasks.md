# Tasks: Ability Form and List Display Fixes

**Input**: Design documents from `specs/024-ability-form-display-fixes/`
**Prerequisites**: [plan.md](plan.md) ✅ | [spec.md](spec.md) ✅ | [memory-synthesis.md](memory-synthesis.md) ✅ | [security-review-plan.md](security-review-plan.md) ✅
**Branch**: `024-ability-form-display-fixes`
**Date**: 2026-05-31

---

## Phase 0 — Pre-flight Verification

**Purpose**: Confirm codebase baseline matches planning doc facts before any edits. Must complete before Phase 1.

- [x] T001 Run pre-flight baseline checks (Node version, grep CHANGE-1/2/5 baselines per plan.md Phase 0):
  ```bash
  node --version   # Must be v20.x.x
  grep -n "get_meta_item.*source" includes/Utilities/AcrossAI_Ability_Merger.php
  grep -n "TypeCell" src/js/abilities/components/AbilitiesList.jsx | head -3
  grep -n "inject_override_args\|row->label" includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php | head -10
  ```

- [x] T002 Confirm ADVISORY-1 (security-review-plan.md): verify `callback_type` / `callback_config` are NOT in `$overridable_fields`
  ```bash
  grep -n "callback_type\|callback_config" includes/Utilities/AcrossAI_Ability_Merger.php | grep -i "overridable"
  ```
  Expected: no results → ADVISORY-1 resolved; UI-only read-only is sufficient.

- [x] T003 Confirm ADVISORY-2 (security-review-plan.md): verify PATH A guard in Override Processor
  ```bash
  grep -n "PATH_A\|path_a\|IS_MANAGER\|is_manager_request\|rest_namespace\|is_manager" \
    includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php | head -10
  ```
  Expected: static flag or constant guards `inject_override_args` from running on Manager REST requests.

**Checkpoint**: Pre-flight complete — all baselines confirmed, both advisories resolved.

---

## Phase 1 — PHP Changes (US1 + US5)

**Purpose**: Fix server-side source attribution (US1) and override injection (US5). Must complete and pass quality gates before Phase 2.

### User Story 1 — Correct Source Badge for Core Abilities

- [x] T004 [US1] Apply CHANGE-1: in `includes/Utilities/AcrossAI_Ability_Merger.php` line ~183, change:
  ```php
  // Before:
  'source' => (string) $ability->get_meta_item( 'source', 'plugin' ),
  // After:
  'source' => $ability->get_meta_item( 'source', null ),
  ```
  Do NOT refactor surrounding code. One-token fix only. Preserve file header (`@package`, `@subpackage`, `@since`).

- [x] T005 [US1] Run quality gates after CHANGE-1:
  ```bash
  composer run phpcs
  vendor/bin/phpstan analyse --level=8
  ```
  Both must exit 0 before continuing.

### User Story 5 — Override Injection for Label/Description/Category

- [x] T006 [US5] Apply CHANGE-5: in `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`, inside `inject_override_args()`, immediately after the `site_allowed` block (~line 298), add three new `if` blocks using `\t` indentation (BUG-PHPCBF-TABS):
  ```php
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
  Use top-level `$args['label']` — NOT `$args['meta']['label']` (BUG-FLAT-ARGS-PATH).

- [x] T007 [US5] Update `inject_override_args()` docblock field map — add three new entries after the `site_allowed` line. Long description must start with capital letter (BUG-PHPCS-DOCBLOCK-CAPITAL):
  ```
   * label                     → $args['label']           (top-level WP Abilities API field)
   * description               → $args['description']     (top-level WP Abilities API field)
   * category                  → $args['category']        (top-level WP Abilities API field)
  ```

- [x] T008 [US5] Run quality gates after CHANGE-5:
  ```bash
  composer run phpcs
  vendor/bin/phpstan analyse --level=8
  ```
  Both must exit 0 before continuing.

**Checkpoint**: PHP changes complete, PHPCS + PHPStan pass. US1 and US5 server-side changes done.

---

## Phase 2 — JavaScript Changes (US2, US3, US4)

**Purpose**: Fix list Type badge (US2), form hints (US3), and Callback read-only (US4). All JS edits require pre-reading surrounding context for exact tab depth (BUG-ABILITYFORM-JSX-MIXED-DEPTHS).

### User Story 2 — Correct Type Badge for Non-DB Abilities

- [x] T009 [US2] Pre-read `AbilitiesList.jsx` TypeCell context:
  ```bash
  sed -n '88,96p' src/js/abilities/components/AbilitiesList.jsx
  ```
  Confirm exact current content and tab depth.

- [x] T010 [US2] Apply CHANGE-2: in `src/js/abilities/components/AbilitiesList.jsx` `TypeCell` function (~lines 90–94), replace with fallback chain:
  ```jsx
  function TypeCell( { item } ) {
  	const type = item.callback_type || item._registry?.callback_type;
  	if ( ! type ) return <span>—</span>;
  	const { cls, label } = TYPE_MAP[ type ] || TYPE_MAP.noop;
  	return <span className={ `tbadge ${ cls }` }>{ label }</span>;
  }
  ```
  Do NOT add new `TYPE_MAP` entries.

### User Story 3 — "Plugin declares" Hints in Edit Form

- [x] T011 [US3] Pre-read `AbilityForm.jsx` Identity section context before label input (~line 835):
  ```bash
  sed -n '833,845p' src/js/abilities/components/AbilityForm.jsx
  ```

- [x] T012 [US3] Apply CHANGE-3c (label hint): insert after the label input (~line 839), using exact surrounding tab depth:
  ```jsx
  {isNonDb && savedAbility?._registry?.label && (
      <div className="desc">
          {__('Plugin declares:', 'acrossai-abilities-manager')}{' '}
          {savedAbility._registry.label}
      </div>
  )}
  ```

- [x] T013 [US3] Pre-read category input context (~line 880):
  ```bash
  sed -n '880,892p' src/js/abilities/components/AbilityForm.jsx
  ```

- [x] T014 [US3] Apply CHANGE-3c (category hint): insert after the category input (~line 884), same pattern.

- [x] T015 [US3] Pre-read description input context (~line 936):
  ```bash
  sed -n '936,948p' src/js/abilities/components/AbilityForm.jsx
  ```

- [x] T016 [US3] Apply CHANGE-3c (description hint): insert after the description input (~line 940), same pattern.

- [x] T017 [US3] Pre-read `show_in_mcp` hint context (~line 1174):
  ```bash
  sed -n '1172,1185p' src/js/abilities/components/AbilityForm.jsx
  ```

- [x] T018 [US3] Apply CHANGE-3a (`show_in_mcp` hint fix, ~line 1178): replace `savedAbility?.show_in_mcp` → `savedAbility?._registry?.show_in_mcp` and `savedAbility.show_in_mcp` → `savedAbility._registry.show_in_mcp`.

- [x] T019 [US3] Pre-read `mcp_type` hint context (~line 1202):
  ```bash
  sed -n '1202,1214p' src/js/abilities/components/AbilityForm.jsx
  ```

- [x] T020 [US3] Apply CHANGE-3a (`mcp_type` hint fix, ~line 1206): replace `savedAbility?.mcp_type` → `savedAbility?._registry?.mcp_type` and `savedAbility.mcp_type` → `savedAbility._registry.mcp_type`.

- [x] T021 [US3] Pre-read `readonly` hint context (~line 1424):
  ```bash
  sed -n '1424,1436p' src/js/abilities/components/AbilityForm.jsx
  ```

- [x] T022 [US3] Apply CHANGE-3b (`readonly` hint fix, ~line 1428): replace `savedAbility?.readonly` → `savedAbility?._registry?.readonly` and `savedAbility.readonly` → `savedAbility._registry.readonly`.

- [x] T023 [US3] Apply CHANGE-3b (`destructive` hint fix, ~line 1467): same `._registry.` substitution pattern. Pre-read context first.

- [x] T024 [US3] Apply CHANGE-3b (`idempotent` hint fix, ~line 1508): same `._registry.` substitution pattern. Pre-read context first.

- [x] T025 [US3] Apply CHANGE-3b (`show_in_rest` hint fix, ~line 1548): same `._registry.` substitution pattern. Pre-read context first.

### User Story 4 — Read-Only Callback Section for Non-DB Abilities

- [x] T026 [US4] Pre-read Callback section `isNonDb` branch (~line 1615):
  ```bash
  sed -n '1615,1680p' src/js/abilities/components/AbilityForm.jsx
  ```
  Locate the full extent of `isNonDb ? (chips...) : (db-branch)` block.

- [x] T027 [US4] Apply CHANGE-4: replace the entire `isNonDb` branch in the Callback type row with static read-only display (badge or "Not defined" span). Variant A (`!isNonDb`) chips remain completely unchanged.

- [x] T028 [US4] Add Config row below the type row (conditional on `isNonDb && savedAbility?._registry?.callback_type`): `<pre className="rt code readonly-schema">` for config, "Not defined" span fallback.

- [x] T029 [US4] Remove the old "Registered type: X" `<div className="desc">` hint — confirm it is not duplicated alongside the new read-only display.

### Build

- [x] T030 [US2+US3+US4] Build JS artifacts:
  ```bash
  node --version   # Confirm ≥ 20
  npm run build    # Must exit 0 with no webpack errors
  ```
  Confirm `build/js/abilities.js` and `build/js/abilities.asset.php` are updated.

**Checkpoint**: All JS changes applied, build succeeds. US2, US3, US4 changes complete.

---

## Phase 3 — Automated Tests (SC-006)

**Purpose**: Required Variant A regression tests per SC-006 clarification. Must pass before quality gates sign-off.

### User Story 4 — Variant A Callback Regression (Jest)

- [x] T031 [US4] Create `tests/jest/abilities/AbilityFormCallbackVariantATest.test.js`:
  - Mock all `@wordpress/*` imports (PATTERN-NAMED-EXPORT-JEST)
  - Assert: for `source='db'` (Variant A), Callback section renders `CALLBACK_CHIPS` and `CallbackConfigField`
  - Assert: for `source='core'` (isNonDb), Callback section renders static badge, no chip-buttons (PATTERN-JEST-SECTION-SCOPE)
  - Follow `ABSPATH`-before-autoloader bootstrap if applicable

### User Story 5 — Variant A Override Injection Regression (PHPUnit)

- [x] T032 [US5] Create `tests/phpunit/abilities/AbilityOverrideInjectVariantATest.php`:
  - Bootstrap: `define('ABSPATH', ...)` before `require_once vendor/autoload.php` (ARCH-PHPUNIT-BOOTSTRAP)
  - Assert: `inject_override_args()` does NOT inject `label`/`description`/`category` when values are `null`
  - Assert: `inject_override_args()` does NOT inject when values are empty string `''` (Inherit semantics)
  - Assert: `inject_override_args()` DOES inject when values are non-null, non-empty strings
  - Assert: CHANGE-1 regression — ability with explicit `source = 'plugin'` meta item still returns `'plugin'` (not null, not `'core'`) — `get_meta_item()` returns stored value; `empty()` guard is not reached
  - If test transitively loads BerlinDB Table subclasses: exclude from `phpunit.xml.dist` and document exclusion

- [x] T033 [US4+US5] Run test suites:
  ```bash
  npm run test:unit      # Jest — T031 must pass
  vendor/bin/phpunit     # PHPUnit — T032 must pass
  ```

**Checkpoint**: All automated tests pass. Variant A regression coverage confirmed.

---

## Phase 4 — Quality Gates

**Purpose**: All six gates must pass before feature is complete (SC-006 §VII).

- [x] T034 Run all quality gates in sequence:
  ```bash
  composer run phpcs                          # 0 errors, 0 warnings
  vendor/bin/phpstan analyse --level=8        # silent exit 0 = pass
  npm run build                               # 0 webpack errors (already done in T030 — re-run to confirm)
  npm run test:unit                           # Jest Variant A tests pass
  vendor/bin/phpunit                          # PHPUnit Variant A tests pass
  npm run validate-packages                   # Package hierarchy check
  ```

- [ ] T035 Run manual verification smoke tests (8 items from plan.md Manual Verification Checklist):
  1. Abilities list: `core/get-environment-info` shows "Core" badge, not "Plugin"
  2. Abilities list: Type column shows badge (not `—`) for abilities with declared `callback_type`
  3. Edit form (non-db): Label, Description, Category show "Plugin declares: …" hint
  4. Edit form (non-db): MCP Exposure and Annotations hints show registry-declared value
  5. Edit form (non-db): Callback section shows static badge, no chip-buttons
  6. Edit form (db): Callback section fully interactive — chips and config field unchanged
  7. Save label override for non-db ability → `get_registered_ability('slug')->get_label()` returns overridden value on next request
  8. Clear label override → original plugin-declared value restored on next request

**Checkpoint**: All 6 quality gates pass, all 8 smoke tests pass. Feature complete.

---

## Summary

| Phase | Tasks | Blocking |
|-------|-------|---------|
| Phase 0 — Pre-flight | T001–T003 | Yes — must complete before Phase 1 |
| Phase 1 — PHP (US1, US5) | T004–T008 | Yes — must pass PHPCS/PHPStan before Phase 2 |
| Phase 2 — JS (US2, US3, US4) | T009–T030 | Yes — must build before Phase 3 |
| Phase 3 — Tests (SC-006) | T031–T033 | Yes — must pass before Phase 4 sign-off |
| Phase 4 — Quality gates | T034–T035 | Yes — feature not complete until all pass |

**Total tasks**: 35 | **Source files changed**: 4 (+2 build artifacts +2 new test files)

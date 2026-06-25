# Implementation Plan: Library Tab Group

**Branch**: `037-library-tab-group` | **Date**: 2026-06-25 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/037-library-tab-group/spec.md`

**Note**: This plan was generated inline by `/speckit-architecture-guard-governed-plan` because the orchestrator runs in the same agent context.

## Summary

Add an optional `tab_group` field to the `Ability_Definition` return-value contract. The field flows through the existing `acrossai_abilities_api_init` collection filter, is validated and sanitized at the Registry boundary using the existing `AcrossAI_Ability_Library_Config::sanitize_key_field()` helper (the same one already used for `category`, `slug`, and `sub_group`), reaches the React Library page via the existing `window.acrossaiAbilityLibraryData` injection channel, and drives a new in-SPA tab bar above the existing category cards. A built-in "All" tab is always present and always selected by default; one additional tab is rendered per unique `tab_group`, sorted alphabetically by sanitized identifier (FR-013). When no add-on declares a `tab_group`, the tab bar is omitted and the page renders identically to Feature 036's layout.

**Technical approach**: Two small PHP edits (one to `Ability_Definition::push_definition()` mirroring the existing `sub_group` block, one to the Registry allowlist + validator), one React change in `LibraryPage.js` (thread `tab_group` through `groupDefinitions()` + add tab-bar render with active-tab filter), and a minimal SCSS block for the tab bar. No new files, no new PHP classes, no new hooks outside Main.php, no REST changes, no saved-config schema changes, no DB migration. The pattern is a direct mirror of Feature 033's `sub_group` plumbing, lifted one display layer up.

## Technical Context

**Language/Version**: PHP 8.1+ (per CONSTITUTION v1.4.6), JavaScript ES2022 via `@wordpress/scripts` Babel toolchain.
**Primary Dependencies**: `@wordpress/components` (TabPanel — new import in `LibraryPage.js`; CheckboxControl/RadioControl/ToggleControl already imported), `@wordpress/element` (useState/useMemo — already imported), `@wordpress/i18n` (already imported). No new package added to `package.json`; `TabPanel` ships in the existing `@wordpress/components` dependency.
**Storage**: N/A — saved-config schema (`enabled`, `mode`, `sub_keys` per category) unchanged. `tab_group` is never written to disk.
**Testing**: Jest via `@wordpress/scripts test-unit-js` for React, PHPUnit for PHP (extend the existing Registry test that covers `sub_group` pass-through), `composer run phpstan` (level 8) and `composer run phpcs` for static analysis.
**Target Platform**: WordPress 6.6+ admin (`wp-admin/admin.php?page=acrossai-abilities-library`), modern evergreen browsers (TabPanel is keyboard-accessible by default).
**Project Type**: WordPress plugin — single project.
**Performance Goals**: No regression. Tab filtering operates on already-loaded definition data in-memory; no extra HTTP, no extra render passes outside React's normal reconciliation. Page renders under one second on a fresh admin load with up to a few hundred abilities and a low double-digit count of `tab_group` values.
**Constraints**: `tab_group` is capped to 100 chars + `sanitize_key()` charset by the existing `sanitize_key_field()` helper. Tab bar must hide entirely when no `tab_group` is declared (FR-006) to preserve the no-regression contract from User Story 3.
**Scale/Scope**: ~4 files touched (Ability_Definition.php, Registry.php, LibraryPage.js, admin.scss), ~30 lines of PHP added, ~40 lines of JSX added, ~15 lines of SCSS added. Plus two test files extended.

## Constitution Check

*GATE: Must pass before implementation. Re-checked at end of plan.*

| Principle (CONSTITUTION.md) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Boot Flow Rule (Main.php is single source of hook registration) | No new hooks | ✅ Pass | Existing `acrossai_abilities_api_init` filter and `init` P99 collect hook are unchanged. No new `add_action`/`add_filter` outside Main.php. |
| §I Admin Partials Rule (admin/Partials/ for menu/render/enqueue) | No changes | ✅ Pass | `LibraryMenu.php` and `admin/Main.php` untouched. Data injection path inherited unchanged. |
| §I REST Controller Pattern (orchestrator + sub-controllers, ≤400 lines) | No REST changes | ✅ Pass | `AcrossAI_Ability_Library_Rest_Controller` and `AcrossAI_Ability_Library_Config_Controller` untouched. |
| §I REST `permission_callback` return type | No REST changes | ✅ Pass | N/A — no controller edits. |
| §I Module Contract (singleton pattern, private ctor, no cross-module deps) | No new modules | ✅ Pass | N/A. |
| §II PHP Standards (`acrossai_` prefix, nonces, capability checks, sanitize/escape) | Sanitize on input applies | ✅ Pass | `tab_group` sanitized at Registry boundary via `AcrossAI_Ability_Library_Config::sanitize_key_field()` (same helper used for category/slug/sub_group). React renders the value as a text node — implicit XSS-safe via JSX's default escaping. |
| §II Quality Gate (PHPCS, PHPStan L8, ESLint clean; tests pass; DataForm/DataViews mandated) | Partial | ⚠️ Documented Deviation (pre-existing) | Library page is custom React per `DEC-DESIGN-OVERRIDES-DATAVIEWS`. Feature 037 introduces NO new form/list pattern — it adds a `TabPanel` above existing custom cards. No new deviation. |
| §III UI Contract (`DataForm`/`DataViews` for forms and lists) | See above | ⚠️ Pre-existing | Tab navigation is not a form or list — it's a page-level filter. `TabPanel` is the canonical `@wordpress/components` primitive for this; no DataViews equivalent exists for tabs. |
| §III Database (use `$wpdb->prepare()`; prefer options/meta) | No DB changes | ✅ Pass | N/A. |
| §III Integration Resilience | N/A | ✅ Pass | No new integrations. |
| **Code Quality & Workflow** — `npm run validate-packages` before commit | Yes | 🔲 Verify at implement time | Run during `/speckit-implement`. |
| **Code Quality & Workflow** — never modify `.agents/tools/` | N/A | ✅ Pass | Untouched. |

**Constitution Gate**: **PASS** with one pre-existing UI-pattern deviation already documented in memory (`DEC-DESIGN-OVERRIDES-DATAVIEWS`). No new violations or deviations introduced.

## Memory Synthesis Findings

Synthesized in `memory-synthesis.md` (this directory). Highlights applied to this plan:

- **DEC-LIBRARY-CATEGORY-SLUG-REBRAND** — `Ability_Definition` keeps a single abstract `ability()` method. `tab_group` is added strictly as an optional `args` key — external subclasses that don't set it stay compatible. No method signature changes.
- **DEC-DESCRIPTION-VALIDATION-PATTERN** — Display-only `args` fields are validated/sanitized at the Registry boundary. `tab_group` follows the same precedent.
- **DEC-NODE-20-BUILD-REQUIRED** — Pre-flight: confirm `node -v` ≥ 20 before `npm run build`.
- **AC-ENQUEUE-ADMIN** — Data injection stays in `admin/Main::enqueue_scripts()`. The plan adds zero PHP outside `includes/Modules/Library/`.
- **AC-HOOKS-MAIN** — Hook wiring stays in `includes/Main.php`. No new add_action/add_filter outside it.
- **PATTERN-ADDON-FILTER-LATE-INIT** — Add-on filters fire at init P99 — unchanged. `tab_group` participates in the same late-init collection.
- **PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH** — *Critical.* Registry currently key-allowlists `args` but does NOT value-sanitize. This plan closes that gap for `tab_group` specifically by sanitizing at the Registry boundary via `sanitize_key_field()`. After sanitization, only alphanumeric/hyphen/underscore characters (sanitize_key charset) remain — XSS-class injection is precluded and React can render the value as a plain text node without explicit escaping.
- **PATTERN-NAMED-EXPORT-JEST** — Both `groupDefinitions()` (existing) and any new helper extracted for the tab-filter logic MUST be named exports so they can be unit-tested without rendering. Mirror the pattern Feature 036 used.
- **BUG-JEST-MOCK-LIST-STALENESS** — Adding `TabPanel` to the `@wordpress/components` import in `LibraryPage.js` requires updating the corresponding `jest.mock()` allowlist in the test file in the same commit. Without that, helper-import Jest specs will silently regress.
- **Feature 033 (sub_group display) precedent** — `tab_group` plumbing mirrors `sub_group` step-for-step. Reuse the same `OPTIONAL_FIELDS` allowlist, the same `sanitize_key_field()` call, the same display-only contract. Do NOT diverge.

## Project Structure

### Documentation (this feature)

```text
specs/037-library-tab-group/
├── spec.md                  # Feature spec (created by /speckit-specify)
├── memory-synthesis.md      # Memory synthesis (created by /speckit-memory-md-plan-with-memory)
├── plan.md                  # This file (created by /speckit-architecture-guard-governed-plan)
├── security-constraints.md  # Security review output (created by /speckit-security-review-plan, inline below)
├── checklists/
│   └── requirements.md      # Spec quality checklist (16/16 pass)
└── tasks.md                 # To be created by /speckit-tasks
```

`research.md`, `data-model.md`, `quickstart.md`, and `contracts/` are intentionally omitted — this feature has no new tech to research, no data model changes, no REST contracts, and the verification path is short enough to live inside `plan.md` (see "Verification" below).

### Source Code (repository root)

WordPress plugin (single project). Touched files:

```text
includes/Modules/Library/
├── Ability_Definition.php                       # Add tab_group extraction (~6 lines mirroring sub_group block)
└── AcrossAI_Ability_Library_Registry.php        # Add 'tab_group' to ALLOWED_ARGS_FIELDS + OPTIONAL_FIELDS;
                                                 # add sanitize+pass-through in validate_and_normalize()

src/js/ability-library/
└── components/
    └── LibraryPage.js                           # Thread tab_group through groupDefinitions();
                                                 # add tab bar (TabPanel) + active-tab filter

src/scss/ability-library/
└── admin.scss                                   # Minimal tab-bar styling block

# Tests extended (no new files):
tests/ (PHP)                                     # Extend existing Registry test covering sub_group pass-through
src/js/ability-library/components/__tests__/    # Extend existing groupDefinitions test
```

**Structure Decision**: Library module conventions are already established (Modules/Library/ for PHP, src/js/ability-library/ for React, src/scss/ability-library/ for styles). No new directories.

## Implementation Phases

### Phase 1 — PHP boundary (Ability_Definition + Registry)

1. **`Ability_Definition::push_definition()`** — alongside the existing `$sub_group` extraction (around line 67):
   - Read `$tab_group = isset($args['tab_group']) ? (string) $args['tab_group'] : '';`
   - When non-empty, set `$row['tab_group'] = $tab_group;`
   - Update the class docblock to list `args['tab_group']` next to `args['sub_group']` as the second optional display-only key.

2. **`AcrossAI_Ability_Library_Registry`**:
   - Append `'tab_group'` to `ALLOWED_ARGS_FIELDS` (so it survives the `array_intersect_key` strip at line 188).
   - Append `'tab_group'` to `OPTIONAL_FIELDS` (parity with `sub_group`).
   - In `validate_and_normalize()`, immediately after the `sub_group` block (lines 212–222), add a parallel `tab_group` block:
     ```php
     if ( isset( $item['tab_group'] ) && '' !== $item['tab_group'] ) {
         $clean_tab = AcrossAI_Ability_Library_Config::sanitize_key_field( (string) $item['tab_group'] );
         if ( '' !== $clean_tab ) {
             $entry['tab_group'] = $clean_tab;
         }
     }
     ```
   - Update the inline docblock above `apply_filters( 'acrossai_abilities_api_init', … )` (lines 121–139) to mention `tab_group` next to `sub_group`.

### Phase 2 — React (LibraryPage.js)

1. **`groupDefinitions()`** — destructure `tab_group: tabGroup` from each definition alongside the existing `sub_group` destructure; push `tabGroup: tabGroup || ''` into each slug record.

2. **New named export `filterItemsByTabGroup(items, activeTab)`** — pure helper:
   - When `activeTab === '__all__'`, return `items` unchanged.
   - Otherwise, map each item to a copy whose `slugs` array is filtered to entries where `slug.tabGroup === activeTab`; drop items with empty `slugs`.
   - Named export per `PATTERN-NAMED-EXPORT-JEST` for direct Jest testability.

3. **New named export `collectTabGroups(items)`** — pure helper:
   - Iterate all `items[].slugs`, collect unique non-empty `tabGroup` values into a Set, return them as a sorted array (case-insensitive, per FR-013).
   - Named export for testability.

4. **Tab-bar render in `LibraryPage`**:
   - `const tabGroups = useMemo(() => collectTabGroups(items), [items]);`
   - `const [activeTab, setActiveTab] = useState('__all__');`
   - `const visibleItems = useMemo(() => filterItemsByTabGroup(items, activeTab), [items, activeTab]);`
   - When `tabGroups.length === 0`, render the existing cards unchanged (no tab bar) — preserves no-regression contract (FR-006, US3).
   - When `tabGroups.length >= 1`, render `<TabPanel>` from `@wordpress/components` above the cards with one tab per `__all__` + sorted tab groups. Label format: `'All'` for `__all__`; otherwise `titleCase(tabGroup.replace(/-/g, ' '))` to mirror the PHP `ucwords(str_replace('-', ' ', …))` rule.

### Phase 3 — Styling

Add a small block in `src/scss/ability-library/admin.scss` after the `.acrossai-library-page__empty` rule (~line 35). Target `.components-tab-panel__tabs` inside `.acrossai-library-page` for spacing/margin; rely on `@wordpress/components` `TabPanel` defaults for everything else (font, border, focus ring). Keep it minimal — goal is "feels native to wp-admin".

### Phase 4 — Tests

1. **PHP** — extend the existing Registry unit test covering `sub_group` pass-through to also cover `tab_group`: present → carried through; missing → absent; bogus chars → sanitized via `sanitize_key_field()`.
2. **JS** — extend the existing `groupDefinitions()` Jest test (the file updated in Feature 036 for `description`) to assert each slug now carries `tabGroup`. Add new Jest tests for `filterItemsByTabGroup` (three cases: `__all__` unchanged, matching tab returns trimmed slugs, non-matching tab drops empty cards) and `collectTabGroups` (sort order, dedup, case-insensitivity).
3. **Jest mock allowlist** — when adding `TabPanel` to the `@wordpress/components` import, update the `jest.mock('@wordpress/components', …)` allowlist in the LibraryPage test file in the same commit (per `BUG-JEST-MOCK-LIST-STALENESS`).

## Verification

End-to-end smoke (Local site `wordpress-7-0`):

1. **Pre-flight** — `node -v` ≥ 20 (per `DEC-NODE-20-BUILD-REQUIRED`); branch is `037-library-tab-group`; `git status` clean.
2. **Static + unit** — `composer phpstan`, `composer phpcs`, `npm run lint:js`, `composer test`, `npm test` — all green.
3. **Build** — `npm run build` succeeds.
4. **Smoke with throwaway mu-plugin** — drop a mu-plugin that subclasses `Ability_Definition` and declares two abilities with `args['tab_group'] => 'sales'` and one with `args['tab_group'] => 'support'`; keep one untagged ability from any existing add-on.
5. **Browser checks** — visit `wp-admin/admin.php?page=acrossai-abilities-library`:
   - Tab bar shows **All**, **Sales**, **Support** (alphabetical).
   - **All** → every ability visible.
   - **Sales** → only the two sales abilities; cards with no matching ability are hidden; untagged ability hidden.
   - **Support** → only the one support ability.
   - Toggle a card under a non-All tab — existing save/load via REST still works (no regression in `LibraryCard` behaviour).
6. **No-add-on regression** — deactivate the mu-plugin; tab bar disappears; page renders identically to Feature 036's layout.

## Complexity Tracking

No Constitution violations to justify — the table below remains empty.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| (none) | — | — |

## Re-check at end of plan

Re-running the Constitution Check: **PASS**. No items moved status during plan elaboration. The single pre-existing UI-pattern deviation (`DEC-DESIGN-OVERRIDES-DATAVIEWS`) was already noted on entry and remains the only deviation.

## Next steps

- `/speckit-security-review-plan` — formal security review of this plan (run inline below by the governed-plan orchestrator).
- `/speckit-architecture-guard-violation-detection` — architectural drift scan (run inline below).
- `/speckit-tasks` — generate the dependency-ordered task list once both validations pass.

# Implementation Plan: Library Page — Tab-Scoped Bulk Enable/Disable + URL-Synced Tabs

**Branch**: `052-library-bulk-toggle-and-url-synced-tabs` | **Date**: 2026-07-13 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/052-library-bulk-toggle-and-url-synced-tabs/spec.md`
**Memory synthesis**: [memory-synthesis.md](./memory-synthesis.md)
**Source planning artifact**: [../../docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md](../../docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md) — authoritative for CHANGE-N-level file/function detail.

## Summary

Add two administrator-facing UX affordances to the Ability Library admin page (`?page=acrossai-abilities-library`):

1. **Two side-by-side bulk-action `<Button>`s** in a new right-aligned header row above the TabPanel: primary `Enable All` and secondary `Disable All`. Both actions target ONLY the categories in the currently active tab (or every registered category on the default `All` tab). Both preserve each in-scope category's `mode` and `sub_keys` — the only mutation is the `enabled` boolean. Out-of-scope categories pass through byte-for-byte. Persistence uses the EXISTING `POST /acrossai-abilities-library/v1/abilities/config` REST route — no new endpoints.
2. **URL-synced tabs** via `?tab=<slug>`. The TabPanel migrates from uncontrolled to controlled state driven by a new `useLibraryTabSync` React hook that mirrors the three-effect pattern of `src/js/abilities/hooks/useUrlViewSync.js` (mount / change / popstate). Query-arg absence keeps the canonical default URL clean.

Three new public static helpers land on the existing `Ability_Definition` base class (`is_all_enabled`, `is_all_disabled`, `bulk_toggle_state`) plus one private helper (`registered_category_slugs`). The tri-state hint (`'all' | 'none' | 'mixed'`) is localized into `window.acrossaiAbilityLibraryData.bulkToggleState` via the existing `admin/Main.php::enqueue_scripts()` block, so first-paint state matches the default `All` tab without a REST round-trip. After first paint, tab-scoped state is derived entirely client-side.

Clarified behavior from `/speckit.clarify` (Session 2026-07-13): both buttons ALWAYS render as fully active controls — no `disabled` attribute, no `aria-disabled`, no visual dimming. Redundant clicks are silent no-ops (FR-008).

## Technical Context

**Language/Version**: PHP 8.1+ (Constitution §II floor); JavaScript per `@wordpress/scripts` (Node ≥20 build environment — DEC-NODE-20-BUILD-REQUIRED)
**Primary Dependencies**: `@wordpress/components` (`Button`, `TabPanel`), `@wordpress/element`, `@wordpress/i18n`, `@wordpress/url` (`addQueryArgs` / `getQueryArg` / `removeQueryArgs`), `@wordpress/api-fetch`. No new npm dependencies. No new Composer packages.
**Storage**: Existing `acrossai_library_config` site-option, sparse-storage semantics unchanged. Schema unchanged.
**Testing**: PHPUnit 10 (existing `tests/phpunit/Modules/Library/Test_Ability_Definition.php` extended); Jest via `@wordpress/scripts` (four new files under `tests/jest/ability-library/`, one extended file).
**Target Platform**: WordPress 6.9+ admin (multisite-compatible via existing `get_site_option()` / `update_site_option()` in the storage model).
**Project Type**: WordPress plugin — admin React surface + PHP backend static helpers.
**Performance Goals**: SC-007 — no measurable Library page regressions vs release 0.0.6 under the same fixture. Bulk save = single REST request.
**Constraints**: No new REST endpoints (FR-006 uses existing `POST /abilities/config`). No schema changes to `acrossai_library_config`. No changes to the tab-group data flow. No PHP-side `?tab=` parsing (client-only).
**Scale/Scope**: ≈17 tab groups, ≈176 abilities across all groups (post-Feature-046 absorbed set). Bulk `All`-tab payload is one config object with ≈17 entries. Well below any option-size concern.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Constitution version: **1.4.8**. Checked against each Core Principle:

| Principle | Gate | Verdict | Notes |
|---|---|---|---|
| §I Modular Architecture | Every changed file is inside the existing `Library` module or the `admin/Main.php` orchestrator. No new module. No cross-module code duplication. | ✅ PASS | `Ability_Definition.php` is the existing Library base class; new static helpers stay module-local. |
| §II WPCS Compliance | PHPCS strict, PHPStan L8, ESLint zero, Plugin Check clean; PHP 8.1+; WP 6.9+. | ✅ PASS (planned) | Enforced by `composer phpstan`, `composer phpcs`, `npm run lint:js`, wp-env plugin-check job as quality gates before commit. New helpers use `array_keys()` / `foreach` — no new SQL, no forbidden functions. |
| §III User-Centric Design (NON-NEGOTIABLE) | All forms use `DataForm`; all lists use DataViews. | ⚠️ ACCEPTED DEVIATION | Library page is the pre-approved deviation surface — **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Active). The header row is a command-control surface (two `<Button>`s), not a form or list. DataForm doesn't apply. Recorded here for governance traceability. |
| §IV Security First (NON-NEGOTIABLE) | Sanitize/escape/nonce/capability at every boundary. | ✅ PASS | No new user-input surface. The bulk save routes through the EXISTING `AcrossAI_Ability_Library_Config_Controller` which is `manage_options` + `wp_verify_nonce('wp_rest')` gated (verified pre-Feature-052). PHP helpers are read-only over stored config. Localization values are strings from a fixed enum `'all' \| 'none' \| 'mixed'` — no untrusted input. Per user memory rule, no permission_callback audit is added to the plan artifacts. |
| §V Extensibility Without Core Modification | Additions via hooks / extension points / new modules; no editing of external plugins. | ✅ PASS | No modification to companion or vendor packages. The `Ability_Definition` base class extension is purely additive. |
| §VI DRY & @wordpress-first | Reuse `@wordpress/*` packages; run `npm run validate-packages`. | ✅ PASS | `useLibraryTabSync` mirrors the three-effect structure of the existing `useUrlViewSync` — same helpers (`addQueryArgs` / `getQueryArg` / `removeQueryArgs`). No new deps. Existing `saveConfig()` REST wrapper is reused. |
| §VII Definition of Done | PHPCS / PHPStan / ESLint / DoD checks. | ✅ PLANNED | Quality-gate audits enumerated in the planning artifact CHANGE-8 section. All new files include the standard `@package AcrossAI_Abilities_Manager` header (`AC-FILE-HEADER-PATTERN`). |

**Boot Flow Rule**: Feature 052 adds ZERO new PHP hooks. `admin/Main.php::enqueue_scripts()` is edited to add ONE new key to the existing `window.acrossaiAbilityLibraryData` localization — this stays inside the already-hooked admin-enqueue path. `AC-HOOKS-MAIN` and `AC-ENQUEUE-ADMIN` both remain satisfied.

**REST `permission_callback` Return Type**: No new REST routes. No changes to any existing `check_permission()`. Per user memory rule (`Skip permission_callback REST audit`), no line-by-line audit is performed on the existing controller; its `manage_options` + nonce gate is treated as an unchanged baseline.

**Overall Constitution verdict**: PASS with one pre-approved accepted deviation (§III, tracked as `DEC-DESIGN-OVERRIDES-DATAVIEWS`). No new violations introduced.

## Project Structure

### Documentation (this feature)

```text
specs/052-library-bulk-toggle-and-url-synced-tabs/
├── plan.md                    # THIS FILE
├── spec.md                    # Feature specification (produced by /speckit-specify)
├── memory-synthesis.md        # Memory-first index selection (produced by /speckit-memory-md-plan-with-memory)
├── security-constraints.md    # Plan-level security review (produced by /speckit-security-review-plan)
├── architecture-review.md     # Architecture violation detection (produced by /speckit-architecture-guard-violation-detection)
├── checklists/
│   └── requirements.md        # Spec-quality checklist (all passing)
└── tasks.md                   # Phase 2 output — NOT produced by this command
```

### Source Code (repository root — WordPress plugin layout)

```text
acrossai-abilities-manager/
├── admin/
│   └── Main.php                                         # MODIFIED — add 'bulkToggleState' key to localized data
├── includes/
│   └── Modules/
│       └── Library/
│           ├── Ability_Definition.php                    # MODIFIED — +3 public static helpers, +1 private helper
│           ├── AcrossAI_Ability_Library_Config.php       # UNCHANGED — storage model unchanged
│           ├── AcrossAI_Ability_Library_Registry.php     # UNCHANGED — read-only reference by new helper
│           └── Rest/
│               └── AcrossAI_Ability_Library_Config_Controller.php  # UNCHANGED — no new routes
├── src/
│   ├── js/
│   │   └── ability-library/
│   │       ├── components/
│   │       │   └── LibraryPage.js                         # MODIFIED — controlled TabPanel + header row + 3 helpers + handlers
│   │       ├── hooks/
│   │       │   └── useLibraryTabSync.js                   # NEW — URL-sync hook (mirrors useUrlViewSync)
│   │       └── api.js                                     # UNCHANGED
│   └── scss/
│       └── ability-library/
│           └── admin.scss                                 # MODIFIED — .acrossai-library-page__header block
├── tests/
│   ├── phpunit/
│   │   └── Modules/Library/
│   │       └── Test_Ability_Definition.php                # MODIFIED — 6 new tests
│   └── jest/
│       └── ability-library/
│           ├── LibraryPage.test.js                        # MODIFIED — integration + tab-scope + no-op cases
│           ├── useLibraryTabSync.test.js                  # NEW
│           ├── collectInScopeCategories.test.js           # NEW
│           ├── buildBulkPatch.test.js                     # NEW
│           └── computeInScopeBulkState.test.js            # NEW
└── build/                                                 # REGENERATED via `npm run build`
    ├── js/ability-library.js
    ├── js/ability-library.asset.php
    ├── css/ability-library.css
    └── css/ability-library-rtl.css
```

**Structure Decision**: Standard WordPress plugin layout (admin partials, includes/Modules/Library, src/js + src/scss, tests/phpunit + tests/jest) — same layout used by every Library-touching feature since 027. No new top-level directories.

## Phase 0 — Research (compressed)

Nearly all research is captured in `docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md` (CHANGE-1 through CHANGE-8) and doesn't need duplication. Key research findings summarised:

- **URL-sync reference implementation**: `src/js/abilities/hooks/useUrlViewSync.js` — three-effect pattern (mount / change / popstate) is directly transplantable. Same `@wordpress/url` helpers, same `pushState` mechanics.
- **Config write path**: `saveConfig(config)` at `src/js/ability-library/api.js:23` already POSTs the full config object; no shape change needed.
- **Sparse-storage semantics**: `AcrossAI_Ability_Library_Config::save_config()` strips entries at the all-default state (`enabled:true`, `mode:'all'`, `sub_keys:[]`). Post `Enable All` on `All` tab, the option row is emptied — verified against the storage code.
- **Registry API**: `AcrossAI_Ability_Library_Registry::instance()->get_definitions()` returns the normalized list; `def['category']` is the slug the new helpers key on.
- **Existing Jest conventions**: `PATTERN-NAMED-EXPORT-JEST` used by all sibling `*.test.js` files. Mock allowlist for `@wordpress/components` and `@wordpress/element` established in `collectTabGroups.test.js` — reused verbatim.
- **Historical bugs to guard against** (from memory-synthesis.md):
  - `BUG-WP-LOCALIZE-SCRIPT-RENDER` → `bulkToggleState` localization stays inside `enqueue_scripts()` via `wp_add_inline_script('before')`. Never from a render callback.
  - `BUG-CLASS-EXISTS-AUTOLOAD-FALSE-SILENT` → `registered_category_slugs()` uses `class_exists()` with DEFAULT `autoload=on`. Never pass `false` as the second arg.
  - `BUG-JEST-MOCK-LIST-STALENESS` → `LibraryPage.test.js` mock allowlist must be updated for new `Button` import (if not already) and for the new `useLibraryTabSync` module.

No `[NEEDS CLARIFICATION]` remains; the one Session-2026-07-13 clarification (button non-actionable state → Option C) is already integrated into the spec.

## Phase 1 — Design

Design detail lives in the planning artifact CHANGE-N sections. Highlights:

### Data model (client-side derived — no PHP data model changes)

The client-side computation for each render:

```
items                    = groupDefinitions(data.definitions)     // existing
tabGroups                = collectTabGroups(items)                 // existing
activeTab                = state (URL-driven via useLibraryTabSync)
inScopeCategories        = collectInScopeCategories(items, activeTab, ALL_TABS_KEY)    // NEW helper
inScopeBulkState         = computeInScopeBulkState(config, inScopeCategories)          // NEW helper — returns 'all'|'none'|'mixed'
```

The two handlers each call `buildBulkPatch(config, inScopeCategories, enabled)` and then `saveConfig(next)`.

### Contracts

- **New JS module** `useLibraryTabSync.js` — three named exports (`parseTabFromUrl`, `buildUrlFromTab`, default hook). Signatures per planning artifact CHANGE-3.
- **New JS helpers exported from `LibraryPage.js`** — `collectInScopeCategories(items, activeTab, allTabsKey)`, `buildBulkPatch(currentConfig, inScopeCategories, enabled)`, `computeInScopeBulkState(currentConfig, inScopeCategories)`. Signatures per CHANGE-4c.
- **New PHP static helpers on `Ability_Definition`** — `is_all_enabled(): bool`, `is_all_disabled(): bool`, `bulk_toggle_state(): string`, `registered_category_slugs(): array` (private). Signatures per CHANGE-1.
- **Localized data key** — `window.acrossaiAbilityLibraryData.bulkToggleState : 'all' | 'none' | 'mixed'`. Added inside the existing `wp_add_inline_script` block in `admin/Main.php::enqueue_scripts()`.
- **REST contract** — UNCHANGED. Feature 052 issues the same `POST /acrossai-abilities-library/v1/abilities/config` request the per-card save already uses.

### Quickstart (from planning artifact "Manual verification")

The planning artifact's 16-step wp-env quickstart is the authoritative manual-verification runbook. Highlights:

1. Verify the header row shows two separate `<Button>`s (no split-button).
2. On `All` tab: `Enable All` empties the option; `Disable All` writes an entry per registered category with `enabled=0`.
3. On `Core` tab: `Disable All` flips ONLY Core-tab categories; every non-Core category stays byte-for-byte identical. Cross-check via DB query.
4. URL-sync: click tabs → `?tab=<slug>` appears; back button re-syncs; direct-link `&tab=themes` opens Themes tab; invalid `&tab=nonexistent` silently falls back to `All`.

### Complexity Tracking

None. No Constitution violations require justification — the one accepted deviation (§III DataForm/DataViews for the Library page) predates this feature and is Active per `DEC-DESIGN-OVERRIDES-DATAVIEWS`. No new complexity is introduced.

## Post-design Constitution re-check

Re-checking against the completed Phase 1 design:

- §I Modular Architecture: still PASS. All edits stay inside the Library module + the admin/Main.php orchestrator.
- §II WPCS: still PASS-planned. Quality gates unchanged.
- §III User-Centric Design: still ACCEPTED DEVIATION via `DEC-DESIGN-OVERRIDES-DATAVIEWS`. No new deviation added.
- §IV Security First: still PASS. Design confirms no new user-input surface, no new REST route, no new capability boundary.
- §V Extensibility: still PASS.
- §VI DRY: reinforced — three named-export helpers each single-purpose, `useLibraryTabSync` mirrors `useUrlViewSync`.
- §VII DoD: quality gates enumerated; all planned.

No re-check regressions. Proceed to `/speckit-tasks`.

## References

- Spec: [spec.md](./spec.md)
- Memory Synthesis: [memory-synthesis.md](./memory-synthesis.md)
- Planning artifact (implementation-level): [../../docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md](../../docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md)
- URL-sync reference implementation: `src/js/abilities/hooks/useUrlViewSync.js`
- Existing Library page: `src/js/ability-library/components/LibraryPage.js`
- Existing config storage: `includes/Modules/Library/AcrossAI_Ability_Library_Config.php`
- Existing REST controller: `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php`

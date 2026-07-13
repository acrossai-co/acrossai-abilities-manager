# Memory Synthesis

## Current Scope

Feature 052 adds two UX affordances to the Library admin page (`?page=acrossai-abilities-library`): (A) two side-by-side bulk-action buttons `Enable All` / `Disable All`, scoped to the currently active tab, preserving each category's `mode` and `sub_keys` and flipping only the `enabled` boolean; (B) URL-synced tabs via `?tab=<slug>`. Touches: `src/js/ability-library/components/LibraryPage.js` (controlled tab state, header row, 4 named-export helpers), new hook `src/js/ability-library/hooks/useLibraryTabSync.js`, `src/scss/ability-library/*.scss` (header row), `includes/Modules/Library/Ability_Definition.php` (3 public + 1 private static helper), `admin/Main.php::enqueue_scripts()` (localize `bulkToggleState`), Jest + PHPUnit tests, `npm run build` artifacts. No REST route changes, no schema changes.

## Relevant Decisions

- **DEC-META-ACROSSAI-NAMESPACE** (Reason: tab_group is read from `$args['meta']['acrossai']['tab_group']` in `push_definition()`; Feature 052 scopes bulk actions off the JS-side `tabGroup` derived from that path. Status: Active, Source: DECISIONS.md)
- **DEC-ABSORBED-CODE-INCLUDES-TIER** (Reason: all 176 absorbed classes extend `Ability_Definition`, so the 3 new static helpers benefit every consumer without per-class edits. Status: Active, Source: DECISIONS.md)
- **DEC-LIBRARY-CATEGORY-SLUG-REBRAND** (Reason: `Ability_Definition` is the single canonical base class Feature 052 augments; simplified `ability()` contract and derivation flow stay untouched. Status: Active, Source: DECISIONS.md)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: the header row uses `@wordpress/components` `<Button>` primitives, not DataForm; the pre-existing Library-page deviation covers this. Status: Active-Accepted-Deviation, Source: DECISIONS.md)
- **DEC-NODE-20-BUILD-REQUIRED** (Reason: bundle regeneration in CHANGE-8 must run under Node ≥ 20 or `toSorted` and related transforms fail silently. Status: Active, Source: DECISIONS.md)

## Active Architecture Constraints

- **AC-ENQUEUE-ADMIN** (Reason: `bulkToggleState` MUST be emitted via `wp_add_inline_script('before')` inside `Admin\Main::enqueue_scripts()` — the SAME localization block that ships `definitions`, `restBase`, `nonce`, `addonsUrl`. Source: CONSTITUTION.md §I)
- **AC-HOOKS-MAIN** (Reason: Feature 052 adds NO new PHP hooks; still worth stating since the temptation on any admin feature is to add a filter. Source: CONSTITUTION.md §I)
- **AC-FILE-HEADER-PATTERN** (Reason: new `useLibraryTabSync.js` needs the JSDoc file header block; new test files need the standard `@since 0.1.0` metadata where applicable. Source: ARCHITECTURE.md)
- **PATTERN-NAMED-EXPORT-JEST** (Reason: all four new helpers — `collectInScopeCategories`, `buildBulkPatch`, `computeInScopeBulkState`, plus `parseTabFromUrl`/`buildUrlFromTab` in the hook — MUST be named exports so Jest unit-tests them without rendering. Source: ARCHITECTURE.md)
- **PATTERN-PROTECTED-SLUGS-JS-LOCALIZE** (Reason: `bulkToggleState` follows the same PHP→JS channel used by `definitions` and other localized keys — no ad-hoc REST round-trip on first paint. Source: ARCHITECTURE.md)

## Accepted Deviations

- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: the Library page is the pre-approved React-not-DataForm surface; the split-button/two-button header row is inside that surface. Status: Accepted-Deviation, Source: DECISIONS.md)
- **DEC-ABSORBED-CODE-INCLUDES-TIER** (Reason: `Ability_Definition` lives at `includes/Modules/Library/` — the tier the absorbed code deliberately uses; new helpers respect the pending Constitution PATCH tracked in spec 047. Status: Accepted-Deviation, Source: DECISIONS.md)

## Relevant Security Constraints

- Existing baseline holds: `POST /acrossai-abilities-library/v1/abilities/config` is `manage_options` + nonce-gated (`AcrossAI_Ability_Library_Config_Controller`). Feature 052 issues that same call with a bulk-assembled payload — no new attack surface.
- **User rule (memory)**: permission_callback compliance auditing is explicitly skipped for this spec/plan; not enumerating a security lens here.

## Related Historical Lessons

- **BUG-WP-LOCALIZE-SCRIPT-RENDER** (Reason: `bulkToggleState` MUST be emitted via `wp_add_inline_script('before')` in `enqueue_scripts()`. Calling from a render callback fires too late and yields a blank page — same class of bug as Feature 030's Library blank page. Source: BUGS.md)
- **BUG-CLASS-EXISTS-AUTOLOAD-FALSE-SILENT** (Reason: the planned `registered_category_slugs()` uses a `class_exists()` guard on `AcrossAI_Ability_Library_Registry`; it MUST use the default `autoload=on`. Passing `false` as the second arg silently no-ops when nothing else has referenced the class yet — the identical Feature 046 Bootstrap failure mode. Source: BUGS.md)
- **BUG-JEST-MOCK-LIST-STALENESS** (Reason: `LibraryPage.js` will gain new imports — `Button` from `@wordpress/components` if not already imported, plus the new `useLibraryTabSync` module. Existing `LibraryPage.test.js` and the sibling helper-test mocks MUST be updated to whitelist those imports, or Jest fails at `require()` of the JSX module with a misleading stack trace. Source: BUGS.md)

## Conflict Warnings

- None. The spec/plan respect every active decision and constraint listed above. The Library page's use of standard React over DataForm is pre-approved (DEC-DESIGN-OVERRIDES-DATAVIEWS), so the two-button header row does NOT trigger a Constitution §III violation.

## Retrieval Notes

- Index entries considered: ~20 (out of ~140). Selected: 5 decisions, 3 architecture constraints (plus 2 explicit patterns folded into the AC list), 2 accepted deviations, 3 bug patterns, 0 worklog items (Feature 037 tab_group and Feature 046 absorption are the direct predecessors, but their context is already captured in the decisions above — no additional worklog rows needed).
- Skipped entirely: all BerlinDB decisions/bugs, Abilities REST, override merger, MCP, Access Control, Freemius, addons-page, main-menu 0.0.x — none touch the Library page or `Ability_Definition` in scope for this feature.
- Source sections read: 4 short entries from BUGS.md, 2 from ARCHITECTURE.md. Full durable-memory reads: NOT performed. Budget status: within limits (≈650 words, under the 900-word cap).
- Optimizer: not enabled — `.specify/extensions/memory-md/config.yml` is absent; markdown-only index-first retrieval used.

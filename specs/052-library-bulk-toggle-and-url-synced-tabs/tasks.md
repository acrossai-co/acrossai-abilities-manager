---

description: "Task list for Feature 052 — Library Page: Tab-Scoped Bulk Enable/Disable + URL-Synced Tabs"
---

# Tasks: Library Page — Tab-Scoped Bulk Enable/Disable + URL-Synced Tabs

**Input**: Design documents from `/specs/052-library-bulk-toggle-and-url-synced-tabs/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [memory-synthesis.md](./memory-synthesis.md), [security-constraints.md](./security-constraints.md), [architecture-review.md](./architecture-review.md)
**Implementation reference**: [../../docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md](../../docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup

- [x] T001 Verify Node ≥ 20 (Node 22.22.0) + branch `052-library-bulk-toggle-and-url-synced-tabs` + composer/npm installed.

---

## Phase 2: Foundational (Blocking Prerequisites)

- [x] T002 Add `is_all_enabled()`, `is_all_disabled()`, `bulk_toggle_state()`, `registered_category_slugs()` to `includes/Modules/Library/Ability_Definition.php` with explicit `use` statements for Config + Registry. SEC-052-I-001: `class_exists()` uses default `autoload=on`.
- [x] T003 [P] Add 6 PHPUnit test methods for the new helpers in `tests/phpunit/Modules/Library/Test_Ability_Definition.php` — 16 total tests, 39 assertions, all pass.
- [x] T004 Add `'bulkToggleState' => Ability_Definition::bulk_toggle_state()` to `window.acrossaiAbilityLibraryData` in `admin/Main.php::enqueue_scripts()`. SEC-052-I-002: same `wp_add_inline_script('before')` block.
- [x] T005 [P] Migrate `<TabPanel>` in `src/js/ability-library/components/LibraryPage.js` to controlled `activeTab` state (`useState(ALL_TABS_KEY)` + `onSelect={setActiveTab}` + `key={activeTab}`).

---

## Phase 3: User Story 1 — Tab-Scoped Bulk Enable/Disable (Priority: P1) 🎯 MVP

### Tests

- [x] T006 [P] [US1] `tests/jest/ability-library/collectInScopeCategories.test.js` — 5 cases, all pass.
- [x] T007 [P] [US1] `tests/jest/ability-library/buildBulkPatch.test.js` — 5 cases including US3 round-trip, all pass.
- [x] T008 [P] [US1] `tests/jest/ability-library/computeInScopeBulkState.test.js` — 6 cases, all pass.
- [x] T009 [P] [US1] Update `tests/jest/ability-library/LibraryPage.test.js` mock allowlist for new `Button` import + `useLibraryTabSync` module (BUG-JEST-MOCK-LIST-STALENESS). Also update the four sibling helper tests (collectTabGroups / filterItemsByTabGroup / titleCaseTabLabel / groupDefinitions) with same allowlist + missing `@wordpress/icons` mock.

### Implementation

- [x] T010 [US1] Add three named-export helpers to `src/js/ability-library/components/LibraryPage.js`: `collectInScopeCategories`, `buildBulkPatch`, `computeInScopeBulkState`.
- [x] T011 [US1] Add `handleEnableAll` / `handleDisableAll` handlers + shared `runBulkPatch(enabled)` in `LibraryPage.js` with FR-008 silent-no-op short-circuit.
- [x] T012 [US1] Add header row with two `<Button>`s (primary Enable All, secondary destructive Disable All) above the TabPanel in `LibraryPage.js`. Memoize `inScopeCategories` + `inScopeBulkState`.
- [x] T013 [P] [US1] Add `.acrossai-library-page__header` block to `src/scss/ability-library/admin.scss` (flex, right-aligned, gap:8px, bottom border).

---

## Phase 4: User Story 2 — URL-Synced Tabs (Priority: P2)

### Tests

- [x] T014 [P] [US2] `tests/jest/ability-library/useLibraryTabSync.test.js` — `parseTabFromUrl` (4 cases incl. SEC-052-I-003 sentinel-fallback) + `buildUrlFromTab` (3 cases). Virtual `@wordpress/url` mock. All pass.

### Implementation

- [x] T015 [P] [US2] Create `src/js/ability-library/hooks/useLibraryTabSync.js` with `parseTabFromUrl`, `buildUrlFromTab`, and default-export three-effect hook mirroring `useUrlViewSync`.
- [x] T016 [US2] Wire `useLibraryTabSync(activeTab, setActiveTab, tabGroups)` into `LibraryPage.js` (import at top, call after `tabGroups` memoization).

---

## Phase 5: User Story 3 — Lossless Round-Trip (Priority: P3)

- [x] T018 [P] [US3] Round-trip test folded into `buildBulkPatch.test.js` (T007) — asserts disable→enable restores mode + sub_keys byte-for-byte.

---

## Phase 6: Polish & Cross-Cutting

- [x] T019 Added Feature 052 disabled-card DOM-identity assertion block to `tests/jest/ability-library/LibraryCard.test.js` — 4 new cases covering per-card + bulk-all-mode + bulk-specific-mode disable paths + predicate parity between the two paths. FR-017 / SEC-052-I-004.
- [x] T020 [P] `npm run build` — clean compile; `ability-library.js` grew 16.5 KiB → 25.4 KiB.
- [x] T021 [P] `composer phpstan` — zero errors at level 8.
- [x] T022 [P] `composer phpcs -- includes/Modules/Library/Ability_Definition.php admin/Main.php` — zero errors.
- [x] T023 [P] `npx wp-scripts test-unit-js tests/jest/ability-library/` — 11 suites, 76 tests, all pass.
- [x] T024 [P] `composer test` (full PHPUnit) — 129 tests, 314 assertions, all pass. 6 pre-existing warnings in `AcrossAI_Ability_Merger.php` unrelated to Feature 052.
- [x] T025 [P] `npm run validate-packages` — clean, no direct React imports, no duplicates.
- [ ] T026 **Manual wp-env verification** — requires running site; deferred to user.

---

## Summary

- **Setup**: 1/1 ✅
- **Foundational**: 4/4 ✅
- **US1 (MVP)**: 8/8 ✅
- **US2**: 3/3 ✅
- **US3**: 1/1 ✅
- **Polish**: 7/8 ✅ (T026 requires running wp-env — user gate)

**Quality gates**: PHPStan L8 ✅ • PHPCS ✅ • PHPUnit 129/129 ✅ • Jest 80/80 ✅ • validate-packages ✅ • build ✅

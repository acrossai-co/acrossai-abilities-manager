---

description: "Task list for Feature 037 — Library Tab Group"
---

# Tasks: Library Tab Group

**Input**: Design documents from `specs/037-library-tab-group/`
**Prerequisites**: plan.md (loaded), spec.md (loaded), memory-synthesis.md (loaded), security-constraints.md (loaded)
**Tests**: Included — Feature 037 mirrors Feature 033's `sub_group` plumbing, which shipped with PHPUnit + Jest coverage. Same expectation applies here per the plan's Phase 4.

**Organization**: Tasks grouped by user story (US1, US2, US3, US4 from spec.md). The PHP-side data plumbing is Phase 2 (Foundational) because every user story depends on `tab_group` reaching the React layer.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files / no dependencies on incomplete tasks)
- **[Story]**: User story label (US1, US2, US3, US4) — present only in Phase 3+
- File paths are absolute under the plugin root

## Path Conventions

WordPress plugin (single project). Source under `includes/`, `src/`, `admin/`. Tests under `tests/phpunit/` (PHP) and `tests/jest/` (JS).

---

## Phase 1: Setup

**Purpose**: Pre-flight checks before code changes.

- [x] T001 Pre-flight: confirm `node -v` ≥ 20 (per `DEC-NODE-20-BUILD-REQUIRED`); confirm current branch is `037-library-tab-group`; confirm `git status` is clean

---

## Phase 2: Foundational (PHP boundary + JS data threading)

**Purpose**: Plumb `tab_group` through the PHP collection pipeline and into the React data layer. Every user story depends on this.

**⚠️ CRITICAL**: No user story phase can begin until this phase is complete.

- [x] T002 Extract `args['tab_group']` in `Ability_Definition::push_definition()`: read `$tab_group = isset($args['tab_group']) ? (string) $args['tab_group'] : '';`; when non-empty set `$row['tab_group'] = $tab_group;` immediately after the existing `sub_group` block. File: `includes/Modules/Library/Ability_Definition.php`
- [x] T003 Update `Ability_Definition` class docblock to list `args['tab_group']` next to `args['sub_group']` as the second optional display-only key. File: `includes/Modules/Library/Ability_Definition.php`
- [x] T004 Append `'tab_group'` to `AcrossAI_Ability_Library_Registry::ALLOWED_ARGS_FIELDS` and `::OPTIONAL_FIELDS`. File: `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`
- [x] T005 Add `tab_group` sanitize + pass-through block in `AcrossAI_Ability_Library_Registry::validate_and_normalize()` immediately after the existing `sub_group` block (lines 212–222): call `AcrossAI_Ability_Library_Config::sanitize_key_field()`; on non-empty result, set `$entry['tab_group'] = $clean_tab;`. Update the inline docblock above `apply_filters( 'acrossai_abilities_api_init', … )` to mention `tab_group`. File: `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`
- [x] T006 [P] Extend `Test_Ability_Library_Registry.php` to add three `tab_group` cases mirroring the existing `sub_group` tests: (a) present → carried through, (b) missing → absent from entry, (c) bogus characters → sanitized via `sanitize_key_field()`. File: `tests/phpunit/Modules/Library/Test_Ability_Library_Registry.php`
- [x] T007 [P] Extend `Test_Ability_Definition.php` to assert `push_definition()` copies `args['tab_group']` to the top-level row when present and omits it when missing. File: `tests/phpunit/Modules/Library/Test_Ability_Definition.php`
- [x] T008 Thread `tab_group: tabGroup` through `groupDefinitions()` destructure in `LibraryPage.js`; push `tabGroup: tabGroup || ''` onto each slug record alongside the existing `subGroup` field. File: `src/js/ability-library/components/LibraryPage.js`
- [x] T009 [P] Extend `groupDefinitions.test.js` to assert each slug record carries a `tabGroup` field (string, empty when input has no `tab_group`). File: `tests/jest/ability-library/groupDefinitions.test.js`

**Checkpoint**: Foundation ready — `tab_group` reaches the React layer through the existing data injection channel. User stories can now be implemented in parallel.

---

## Phase 3: User Story 1 — Group abilities under a named tab (Priority: P1) 🎯 MVP

**Goal**: An add-on author who declares `args['tab_group']` on multiple abilities sees those abilities grouped under one tab on the Library admin page.

**Independent Test**: With a throwaway mu-plugin that subclasses `Ability_Definition` and declares two abilities with `args['tab_group'] => 'sales'`, the Library page shows a "Sales" tab; clicking it shows only those two abilities.

### Implementation for User Story 1

- [x] T010 [P] [US1] Add `collectTabGroups(items)` named export to `LibraryPage.js`: iterate all `items[].slugs`, collect non-empty `tabGroup` values into a Set, return as a case-insensitive alphabetically-sorted array. File: `src/js/ability-library/components/LibraryPage.js`
- [x] T011 [P] [US1] Add `filterItemsByTabGroup(items, activeTab)` named export to `LibraryPage.js`: when `activeTab === '__all__'` return `items` unchanged; otherwise map each item to a copy whose `slugs` array is filtered to entries where `slug.tabGroup === activeTab`, dropping items with empty `slugs`. File: `src/js/ability-library/components/LibraryPage.js`
- [x] T012 [P] [US1] Add `titleCaseTabLabel(slug)` named export to `LibraryPage.js`: replace `-` with space, then capitalize each word (mirror PHP `ucwords(str_replace('-', ' ', …))`). File: `src/js/ability-library/components/LibraryPage.js`
- [x] T013 [US1] Add `TabPanel` to the existing `@wordpress/components` import in `LibraryPage.js`. File: `src/js/ability-library/components/LibraryPage.js`
- [x] T014 [US1] Update the `jest.mock('@wordpress/components', …)` allowlist in the Jest test file that imports `LibraryPage` to include `TabPanel` (per `BUG-JEST-MOCK-LIST-STALENESS`). File: `tests/jest/ability-library/groupDefinitions.test.js` (and any other LibraryPage-importing spec)
- [x] T015 [US1] Add `useState('__all__')` for `activeTab` and `useMemo` for `tabGroups` and `visibleItems` in `LibraryPage`. Render `<TabPanel>` above the cards loop when `tabGroups.length >= 1`, building tabs as `[{name: '__all__', title: 'All'}, ...tabGroups.map(g => ({name: g, title: titleCaseTabLabel(g)}))]`. Map `visibleItems` (output of `filterItemsByTabGroup`) into the existing `LibraryCard` map. Depends on T010, T011, T012, T013. File: `src/js/ability-library/components/LibraryPage.js`
- [x] T016 [P] [US1] Add new Jest test file `collectTabGroups.test.js` covering: (a) returns empty for items with no `tabGroup`, (b) dedupes when multiple slugs share a group, (c) sorts case-insensitively. File: `tests/jest/ability-library/collectTabGroups.test.js`
- [x] T017 [P] [US1] Add new Jest test file `filterItemsByTabGroup.test.js` covering: (a) `__all__` returns input unchanged, (b) matching tab returns trimmed slug list, (c) non-matching tab drops items with empty slugs. File: `tests/jest/ability-library/filterItemsByTabGroup.test.js`
- [x] T018 [P] [US1] Add new Jest test file `titleCaseTabLabel.test.js` covering: (a) `'sales-ops'` → `'Sales Ops'`, (b) `'support'` → `'Support'`, (c) preserves trailing digits/underscores acceptably. File: `tests/jest/ability-library/titleCaseTabLabel.test.js`

**Checkpoint**: User Story 1 is fully functional and testable independently — a single add-on declaring `tab_group` produces a working tab.

---

## Phase 4: User Story 2 — Default 'All' tab shows everything (Priority: P1)

**Goal**: On every fresh page load, the default "All" tab is selected and shows every registered ability regardless of group.

**Independent Test**: With a mix of tab-grouped and ungrouped abilities, the admin opens the Library page and immediately sees every ability without selecting a tab.

### Implementation for User Story 2

- [x] T019 [US2] Verify T015's implementation: `useState('__all__')` is the default; the 'All' tab is always the first tab in the `TabPanel` `tabs` prop array; `filterItemsByTabGroup(items, '__all__')` returns `items` reference-equal (no copying) for performance. File: `src/js/ability-library/components/LibraryPage.js` (no new code beyond what T015 already wrote — this task verifies the default behavior is correct)
- [x] T020 [P] [US2] Add a Jest test in `filterItemsByTabGroup.test.js` (extends T017) asserting that the function returns the exact same array reference when `activeTab === '__all__'` (so React `useMemo` semantics stay correct). File: `tests/jest/ability-library/filterItemsByTabGroup.test.js`

**Checkpoint**: Both US1 and US2 work — fresh page load shows all abilities under 'All'; switching to a group tab filters as expected.

---

## Phase 5: User Story 3 — Page renders unchanged when no add-on opts in (Priority: P2)

**Goal**: On a site where no add-on declares `tab_group`, the Library page renders identically to the prior release (no tab bar, no spacing shift).

**Independent Test**: Deactivate every add-on that declares `tab_group` (or test in a fresh site). Open the Library page and confirm it visually matches Feature 036's layout.

### Implementation for User Story 3

- [x] T021 [US3] Add conditional render in `LibraryPage`: when `tabGroups.length === 0`, render the existing cards map directly with `items` (no `<TabPanel>` wrapper). When `tabGroups.length >= 1`, render the tab bar + filtered cards from T015. Implements FR-006. File: `src/js/ability-library/components/LibraryPage.js`
- [x] T022 [P] [US3] Add a Jest test asserting that rendering `<LibraryPage>` with definitions that have no `tab_group` produces no `TabPanel` in the DOM tree. File: `tests/jest/ability-library/LibraryPage.test.js` (new file)

**Checkpoint**: No-add-on regression test passes — page is byte-identical-by-DOM-shape to the prior release when no `tab_group` is declared.

---

## Phase 6: User Story 4 — Toggles preserve under any tab (Priority: P2)

**Goal**: Admin can toggle a card under any tab and the change saves via the existing REST flow; reloading restores the state regardless of which tab is active.

**Independent Test**: Switch to a non-default tab, toggle a card's enabled state, reload the page, confirm saved state is restored both in the group tab and in the default tab.

### Implementation for User Story 4

- [x] T023 [US4] Manual verification (no code change required). Confirm: (a) `LibraryCard` prop interface is unchanged — it still receives `{item, config, onChange}`, (b) `handleChange` in `LibraryPage` is identical to today (no tab-aware branching), (c) saved config write path is untouched. Document the verification result in the task's commit message or a comment in `LibraryPage.js`.

**Checkpoint**: Save flow is provably untouched; all four user stories are now independently testable.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Quality gates, styling, build, and end-to-end smoke.

- [x] T024 [P] Add tab-bar SCSS block after `.acrossai-library-page__empty` (around line 35) in `src/scss/ability-library/admin.scss`. Target `.acrossai-library-page .components-tab-panel__tabs` with minimal margin/padding to feel native to wp-admin. File: `src/scss/ability-library/admin.scss`
- [x] T025 Run `composer phpstan` — must pass level 8 with zero new errors
- [x] T026 Run `composer phpcs` — must pass with zero new errors on touched PHP files
- [x] T027 Run `npm run lint:js` — must pass with zero new errors
- [x] T028 Run `composer test` (PHPUnit) — all tests green, including the new `tab_group` Registry and Ability_Definition assertions
- [x] T029 Run `npm test` (Jest via `@wordpress/scripts test-unit-js`) — all tests green, including the new `collectTabGroups`, `filterItemsByTabGroup`, `titleCaseTabLabel`, and `LibraryPage` no-tab-bar tests
- [x] T030 Run `npm run build` under Node ≥20 — must succeed and emit `build/js/ability-library.js` + `build/css/ability-library.css`
- [ ] T031 Manual end-to-end smoke (Local site `wordpress-7-0`): drop a throwaway mu-plugin that subclasses `Ability_Definition` and declares two abilities with `args['tab_group'] => 'sales'` and one with `args['tab_group'] => 'support'`; verify all four user stories per the plan's "Verification" section
- [x] T032 Verify no `dangerouslySetInnerHTML` was introduced anywhere in the diff (per security review SEC-001 hardening reminder)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 — BLOCKS all user stories
- **Phase 3 (US1)**: Depends on Phase 2 — core feature implementation
- **Phase 4 (US2)**: Depends on T015 in Phase 3 — verification + one extra test
- **Phase 5 (US3)**: Depends on T015 in Phase 3 — conditional-render addition
- **Phase 6 (US4)**: Depends on Phase 3 completion — manual verification
- **Phase 7 (Polish)**: Depends on all desired user stories being complete

### Within Phase 2 (Foundational)

- T002 → T003 (same file, sequential)
- T004 → T005 (same file, sequential)
- T002–T005 (PHP) must complete before T008–T009 (JS), since JS tests assume the PHP side outputs `tab_group`
- T006, T007 [P] can run alongside T002–T005 if testing-after-write workflow
- T008 → T009 (same file group, sequential is safer)

### Within Phase 3 (US1)

- T010, T011, T012 [P] — three independent pure helpers, parallelizable
- T013, T014 — sequential edits on different files (LibraryPage.js + Jest test file)
- T015 — depends on T010, T011, T012, T013 (consumes the helpers and the import)
- T016, T017, T018 [P] — three new Jest test files, parallelizable
- All [P] tasks within a phase can run concurrently

### Parallel Opportunities

- **Setup**: T001 alone — no parallelism
- **Foundational**: T006 and T007 [P] can run alongside PHP edits; T009 [P] alongside JS edit
- **US1**: T010, T011, T012 [P]; T016, T017, T018 [P]
- **US3**: T022 [P]
- **Polish**: T024 [P] alongside other polish tasks; T025–T030 sequential (each is a single gate)

---

## Parallel Example: User Story 1

```bash
# Three pure helpers can be added in parallel:
Task T010: collectTabGroups in src/js/ability-library/components/LibraryPage.js
Task T011: filterItemsByTabGroup in src/js/ability-library/components/LibraryPage.js
Task T012: titleCaseTabLabel in src/js/ability-library/components/LibraryPage.js

# Their three Jest test files can be added in parallel:
Task T016: tests/jest/ability-library/collectTabGroups.test.js
Task T017: tests/jest/ability-library/filterItemsByTabGroup.test.js
Task T018: tests/jest/ability-library/titleCaseTabLabel.test.js
```

(Same-file parallelism is conceptual — in practice, group these into one editing session since they land in the same file.)

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Complete Phase 1 (Setup)
2. Complete Phase 2 (Foundational) — PHP + JS data threading
3. Complete Phase 3 (US1) — TabPanel render + filter logic
4. **STOP and VALIDATE**: smoke-test US1 with a single mu-plugin declaring `tab_group => 'sales'`
5. Deploy/demo if ready — admins can group abilities under tabs

### Incremental Delivery

1. Phase 1 + 2 → Foundation ready
2. + Phase 3 (US1) → MVP — per-group tabs work
3. + Phase 4 (US2) → 'All' default verified
4. + Phase 5 (US3) → No-add-on regression locked in
5. + Phase 6 (US4) → Save flow verified
6. + Phase 7 (Polish) → Quality gates, styling, build

### Parallel Team Strategy

Single-developer feature. Sequential execution is realistic; parallel slots inside Phase 3 are conceptual rather than meaningful split-of-work.

---

## Notes

- Tests are mandatory for this feature (per the plan's Phase 4 and Feature 033 precedent).
- File paths use the existing `tests/phpunit/Modules/Library/` and `tests/jest/ability-library/` conventions verified during planning.
- The `tab_group` field is sanitized at the Registry boundary via `AcrossAI_Ability_Library_Config::sanitize_key_field()` — same helper used for `category`, `slug`, `sub_group` (security review SEC-001 mitigation).
- Stop at any checkpoint to validate the story independently.
- Avoid: cross-story dependencies that would break the per-story independent-testability contract.

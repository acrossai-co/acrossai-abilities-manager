---

description: "Task list for Feature 067 — Elementor Ability Suite (88 abilities)"
---

# Tasks: Elementor Ability Suite

**Input**: Design documents from `/specs/067-elementor-abilities/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/abilities.md](./contracts/abilities.md), [quickstart.md](./quickstart.md)

**Tests**: Included — one source-inspection test per ability + integration coverage for the 4 workhorse abilities. Follows the Feature 066 test convention.

**Organization**: Tasks grouped by user story (US1–US10). US1–US4 = P1 (blocking parity), US5–US8 = P2 (broad coverage), US9–US10 = P3 (design audits + Pro).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Parallelisable (different files, no dependencies on incomplete tasks)
- **[Story]**: US1–US10 (setup + foundational + polish have no story label)
- File paths under `includes/Abilities/Elementor/` unless otherwise noted

## Path Conventions

WordPress plugin single-project layout (from plan.md § Project Structure):
- **Source**: `includes/Abilities/Elementor/` (ability classes), `includes/Abilities/Utilities/Elementor/` (shared helpers)
- **Tests**: `tests/phpunit/abilities/`

---

## Phase 1: Setup

- [ ] T001 Verify current branch is `067-elementor-abilities` and working tree is clean
- [ ] T002 Verify baseline: `composer test`, `composer phpstan`, `composer phpcs` all pass on the branch before adding code

---

## Phase 2: Foundational (blocking — no user story can begin until complete)

**Purpose**: Build the 6 shared utility classes + Category_Registrar + bootstrap conditional gate. Every ability depends on this.

- [ ] T003 [P] Create `includes/Abilities/Elementor/Category_Registrar.php` mirroring `includes/Abilities/Block/Category_Registrar.php` — registers category `acrossai-abilities-manager-elementor` on `wp_abilities_api_categories_init`, guarded on `class_exists( '\Elementor\Plugin' )`
- [ ] T004 [P] Create `includes/Abilities/Utilities/Elementor/Document_Repository.php` — load `_elementor_data` from post, decode/normalise, save with `wp_slash()` + cache invalidation, tree helpers (find/insert/remove/reorder by ID, reassign subtree IDs). Port from source plugin's `document-repository.php`
- [ ] T005 [P] Create `includes/Abilities/Utilities/Elementor/Template_Query.php` — `WP_Query` wrappers for `elementor_library` CPT with tax filters + `score_pattern_match()`. Port from source plugin's `template-query.php`
- [ ] T006 [P] Create `includes/Abilities/Utilities/Elementor/Widget_Controls.php` — `get_type($name)`, `summarize($controls, $search?)` per data-model.md § Control entity
- [ ] T007 [P] Create `includes/Abilities/Utilities/Elementor/Guidance_Catalog.php` — static Elementor.com pattern & layout guidance data. Port from source plugin's `guidance.php`
- [ ] T008 [P] Create `includes/Abilities/Utilities/Elementor/Design_Audit_Runner.php` — orchestrator for `evaluate-design` + `suggest-design-fixes`. Port from source plugin's `design-audit-runner.php`
- [ ] T009 Modify `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — add `$loader->add_action( 'wp_abilities_api_categories_init', Elementor\Category_Registrar::instance(), 'register' )` next to the existing `Block\Category_Registrar` action
- [ ] T010 Modify `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` — add the two conditional blocks: `if ( class_exists( '\Elementor\Plugin' ) ) { … 80 free abilities … if ( class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' ) ) { … 8 Pro abilities … } }`. Individual `new Elementor\<Class>()` lines added as each ability class is created in subsequent phases.
- [ ] T011 [P] Create `tests/phpunit/abilities/Test_Elementor_Document_Repository.php` — source-inspection + unit tests for load/save/tree ops
- [ ] T012 [P] Create `tests/phpunit/abilities/Test_Elementor_Template_Query.php` — unit tests for query builder + pattern scoring
- [ ] T013 [P] Create `tests/phpunit/abilities/Test_Elementor_Widget_Controls.php` + `Test_Elementor_Guidance_Catalog.php` + `Test_Elementor_Design_Audit_Runner.php` — three separate test files
- [ ] T014 Register all 5 utility test files in `phpunit.xml.dist`
- [ ] T015 Run `vendor/bin/phpcs -n` and `vendor/bin/phpstan analyse` against `includes/Abilities/Elementor/Category_Registrar.php` + all 6 utility files → 0 errors
- [ ] T016 Run `vendor/bin/phpunit --filter='Test_Elementor_(Document_Repository|Template_Query|Widget_Controls|Guidance_Catalog|Design_Audit_Runner)'` → all pass

**Checkpoint**: Utilities + category + bootstrap gate ready. User story implementation can now begin in parallel.

---

## Phase 3: US1 — Widget-schema discovery (P1) 🎯 MVP

**Goal**: `elementor/get-widget-controls` returns any registered widget's control schema at runtime.

**Independent Test**: On a site with Elementor, request the schema for `heading`. Verify `count > 0` and `controls` includes `title`, `header_size`, `align`, `title_color`.

- [ ] T017 [P] [US1] Create `includes/Abilities/Elementor/Get_Widget_Controls.php` per contracts/abilities.md § 3.1 — extends `Ability_Definition`, category `acrossai-abilities-manager-elementor`, uses `Widget_Controls::get_type` + `summarize`
- [ ] T018 [P] [US1] Create `tests/phpunit/abilities/Test_Elementor_Get_Widget_Controls.php` — assert registration, slug `elementor/get-widget-controls`, category, schema shape, error branches
- [ ] T019 [US1] Register `Get_Widget_Controls` in bootstrap (T010's free-Elementor block)

**Checkpoint**: US1 fully functional.

---

## Phase 4: US2 — Read document tree + find/get element (P1)

**Goal**: `get-data`, `find-elements`, `get-element` — the read primitives every write depends on.

- [ ] T020 [P] [US2] Create `includes/Abilities/Elementor/Get_Data.php` per contracts § 1.1
- [ ] T021 [P] [US2] Create `includes/Abilities/Elementor/Get_Element.php` per contracts § 1.5
- [ ] T022 [P] [US2] Create `includes/Abilities/Elementor/Find_Elements.php` per contracts § 1.6
- [ ] T023 [P] [US2] Create test files `Test_Elementor_Get_Data.php`, `Test_Elementor_Get_Element.php`, `Test_Elementor_Find_Elements.php` (one per ability)
- [ ] T024 [US2] Register all 3 abilities in bootstrap

---

## Phase 5: US3 — Update / merge / delete / remove element (P1)

**Goal**: Element-scoped write primitives with force-guards.

- [ ] T025 [P] [US3] Create `includes/Abilities/Elementor/Update_Element.php` per contracts § 1.7 (with `force_replace` guard)
- [ ] T026 [P] [US3] Create `includes/Abilities/Elementor/Merge_Element_Settings.php` per contracts § 1.8 (deep-merge, no guard needed — idempotent)
- [ ] T027 [P] [US3] Create `includes/Abilities/Elementor/Delete_Element.php` per contracts § 1.9 (with `force_delete` guard)
- [ ] T028 [P] [US3] Create `includes/Abilities/Elementor/Remove_Element.php` per contracts § 1.10 (safer alias)
- [ ] T029 [P] [US3] Create 4 test files (one per ability)
- [ ] T030 [US3] Register all 4 abilities in bootstrap

---

## Phase 6: US4 — Compose a page (create + add + shortcuts) (P1)

**Goal**: `create-page`, `add-container`, `add-widget`, `update-page-settings` + 5 widget shortcuts.

- [ ] T031 [P] [US4] Create `includes/Abilities/Elementor/Create_Page.php` per contracts § 1.17
- [ ] T032 [P] [US4] Create `includes/Abilities/Elementor/Add_Container.php` per contracts § 1.14
- [ ] T033 [P] [US4] Create `includes/Abilities/Elementor/Add_Widget.php` per contracts § 1.15 (validates `widget_type` via `Widget_Controls::get_type`)
- [ ] T034 [P] [US4] Create `includes/Abilities/Elementor/Update_Page_Settings.php` per contracts § 1.16
- [ ] T035 [P] [US4] Create 5 widget shortcut classes: `Add_Heading.php`, `Add_Text_Editor.php`, `Add_Image.php`, `Add_Button.php`, `Add_Post_Tabs.php` per contracts § 2.1-2.5 (each internally delegates to `Add_Widget::execute` with type-specific schema)
- [ ] T036 [P] [US4] Create test files for all 9 abilities in this phase
- [ ] T037 [US4] Register all 9 abilities in bootstrap

**Checkpoint**: US1–US4 complete. Client can discover, read, edit, and compose Elementor pages end-to-end.

---

## Phase 7: US5 — Reorganise (move / duplicate / reorder) + patch-data + clone-data (P2)

**Goal**: Tree-mutation primitives beyond insert/update/delete.

- [ ] T038 [P] [US5] Create `includes/Abilities/Elementor/Move_Element.php` per contracts § 1.11 (with descendant-guard)
- [ ] T039 [P] [US5] Create `includes/Abilities/Elementor/Duplicate_Element.php` per contracts § 1.12 (uses `Document_Repository::reassign_subtree_ids`)
- [ ] T040 [P] [US5] Create `includes/Abilities/Elementor/Reorder_Elements.php` per contracts § 1.13
- [ ] T041 [P] [US5] Create `includes/Abilities/Elementor/Patch_Data.php` per contracts § 1.3
- [ ] T042 [P] [US5] Create `includes/Abilities/Elementor/Clone_Data.php` per contracts § 1.4
- [ ] T043 [P] [US5] Create `includes/Abilities/Elementor/Update_Data.php` per contracts § 1.2
- [ ] T044 [P] [US5] Create 6 test files (one per ability)
- [ ] T045 [US5] Register all 6 abilities in bootstrap

---

## Phase 8: US6 — Templates (11 abilities) (P2)

**Goal**: Full CRUD on Elementor templates + pattern search + import/export.

- [ ] T046 [P] [US6] Create `includes/Abilities/Elementor/List_Templates.php` per contracts § 4.1
- [ ] T047 [P] [US6] Create `includes/Abilities/Elementor/Get_Template.php` per contracts § 4.2
- [ ] T048 [P] [US6] Create `includes/Abilities/Elementor/Create_Template.php` per contracts § 4.3
- [ ] T049 [P] [US6] Create `includes/Abilities/Elementor/Update_Template.php` per contracts § 4.4
- [ ] T050 [P] [US6] Create `includes/Abilities/Elementor/Delete_Template.php` per contracts § 4.5
- [ ] T051 [P] [US6] Create `includes/Abilities/Elementor/Restore_Template.php` per contracts § 4.6
- [ ] T052 [P] [US6] Create `includes/Abilities/Elementor/Duplicate_Template.php` per contracts § 4.7
- [ ] T053 [P] [US6] Create `includes/Abilities/Elementor/Empty_Trash.php` per contracts § 4.8
- [ ] T054 [P] [US6] Create `includes/Abilities/Elementor/Export_Template.php` per contracts § 4.9
- [ ] T055 [P] [US6] Create `includes/Abilities/Elementor/Import_Template.php` per contracts § 4.10
- [ ] T056 [P] [US6] Create `includes/Abilities/Elementor/Find_Template_For_Pattern.php` per contracts § 4.11 (uses `Template_Query::score_pattern_match`)
- [ ] T057 [P] [US6] Create 11 test files (one per ability)
- [ ] T058 [US6] Register all 11 abilities in bootstrap

---

## Phase 9: US7 — Kits & site settings (P2)

- [ ] T059 [P] [US7] Create `includes/Abilities/Elementor/List_Kits.php` per contracts § 5.1
- [ ] T060 [P] [US7] Create `includes/Abilities/Elementor/Get_Kit_Settings.php` per contracts § 5.2
- [ ] T061 [P] [US7] Create `includes/Abilities/Elementor/Update_Kit_Settings.php` per contracts § 5.3
- [ ] T062 [P] [US7] Create `includes/Abilities/Elementor/Set_Active_Kit.php` per contracts § 5.4
- [ ] T063 [P] [US7] Create `includes/Abilities/Elementor/List_Global_Widgets.php` per contracts § 5.5
- [ ] T064 [P] [US7] Create `includes/Abilities/Elementor/List_Experiments.php` per contracts § 5.6
- [ ] T065 [P] [US7] Create `includes/Abilities/Elementor/Update_Experiment.php` per contracts § 5.7
- [ ] T066 [P] [US7] Create 7 test files (one per ability)
- [ ] T067 [US7] Register all 7 abilities in bootstrap

---

## Phase 10: US8 — Theme Builder conditions (P2)

- [ ] T068 [P] [US8] Create `includes/Abilities/Elementor/Get_Theme_Builder_Conditions.php` per contracts § 6.1
- [ ] T069 [P] [US8] Create `includes/Abilities/Elementor/Update_Theme_Builder_Conditions.php` per contracts § 6.2
- [ ] T070 [P] [US8] Create 2 test files
- [ ] T071 [US8] Register both abilities in bootstrap

---

## Phase 11: Discovery, guidance, system/maintenance (rest of P2)

**Not tied to a single spec user story — these are the remaining P2/P3 abilities from Groups 3 + 7.**

- [ ] T072 [P] Create `includes/Abilities/Elementor/Get_Official_Widget_Catalog.php` per contracts § 3.2 (12-hour transient over `elementor.com/widgets` fetch)
- [ ] T073 [P] Create `includes/Abilities/Elementor/Get_Official_Pattern_Guidance.php` per contracts § 3.3 (uses `Guidance_Catalog`)
- [ ] T074 [P] Create `includes/Abilities/Elementor/Get_Theme_Context.php` per contracts § 3.4
- [ ] T075 [P] Create `includes/Abilities/Elementor/Get_Style_Guide.php` per contracts § 3.5
- [ ] T076 [P] Create `includes/Abilities/Elementor/Evaluate_Render_Context.php` per contracts § 3.6
- [ ] T077 [P] Create `includes/Abilities/Elementor/Clear_Cache.php` per contracts § 7.1
- [ ] T078 [P] Create `includes/Abilities/Elementor/Replace_Urls.php` per contracts § 7.2
- [ ] T079 [P] Create `includes/Abilities/Elementor/Get_Maintenance_Mode.php` per contracts § 7.3
- [ ] T080 [P] Create `includes/Abilities/Elementor/Update_Maintenance_Mode.php` per contracts § 7.4
- [ ] T081 [P] Create 9 test files (one per ability above)
- [ ] T082 Register all 9 abilities in bootstrap

---

## Phase 12: US9 — Design audits (28 abilities) (P3)

**Goal**: Composition/rhythm/emphasis audits + normalisation/fix subtree ops + copy/sync/convert helpers.

**Aggregators + core scorers (4):**
- [ ] T083 [P] [US9] Create `includes/Abilities/Elementor/Evaluate_Design.php` per contracts § 8 aggregators (uses `Design_Audit_Runner`)
- [ ] T084 [P] [US9] Create `includes/Abilities/Elementor/Suggest_Design_Fixes.php` per contracts § 8 aggregators
- [ ] T085 [P] [US9] Create `includes/Abilities/Elementor/Score_Distinctiveness.php`
- [ ] T086 [P] [US9] Create `includes/Abilities/Elementor/Extract_Design_Tokens.php`

**Individual audits (14):**
- [ ] T087 [P] [US9] Create `Audit_Column_Alignment_Rhythm.php`
- [ ] T088 [P] [US9] Create `Audit_Column_Balance.php`
- [ ] T089 [P] [US9] Create `Audit_Column_Dominance.php`
- [ ] T090 [P] [US9] Create `Audit_Column_Necessity.php`
- [ ] T091 [P] [US9] Create `Audit_Column_Patterns.php`
- [ ] T092 [P] [US9] Create `Audit_Composition_Rhythm.php`
- [ ] T093 [P] [US9] Create `Audit_Emphasis_Drift.php`
- [ ] T094 [P] [US9] Create `Audit_Generic_Component_Repetition.php`
- [ ] T095 [P] [US9] Create `Audit_Generic_Layout_Patterns.php`
- [ ] T096 [P] [US9] Create `Audit_Layout_Mechanism_Fit.php`
- [ ] T097 [P] [US9] Create `Audit_Native_Widget_Opportunities.php`
- [ ] T098 [P] [US9] Create `Audit_Section_Rivalry.php`
- [ ] T099 [P] [US9] Create `Audit_Separator_Discipline.php`
- [ ] T100 [P] [US9] Create `Audit_Surface_Overuse.php`

**Normalise / fix / apply subtree operations (7):**
- [ ] T101 [P] [US9] Create `Apply_Text_Hierarchy.php`
- [ ] T102 [P] [US9] Create `Enforce_Boundary_Coherence.php`
- [ ] T103 [P] [US9] Create `Fix_Visible_Gap_Rhythm.php`
- [ ] T104 [P] [US9] Create `Normalize_Responsive_Values.php`
- [ ] T105 [P] [US9] Create `Normalize_Section_Spacing_Rhythm.php`
- [ ] T106 [P] [US9] Create `Reset_Negative_Margins_Subtree.php`
- [ ] T107 [P] [US9] Create `Zero_Container_Padding_Subtree.php`

**Copy / sync / convert helpers (4):**
- [ ] T108 [P] [US9] Create `Copy_Lane_Settings.php`
- [ ] T109 [P] [US9] Create `Copy_Row_Balance.php`
- [ ] T110 [P] [US9] Create `Image_Widget_To_Background_Container.php`
- [ ] T111 [P] [US9] Create `Sync_Component_Variant.php`

**Tests + registration:**
- [ ] T112 [P] [US9] Create 28 test files (one per audit ability)
- [ ] T113 [US9] Register all 28 abilities in bootstrap

---

## Phase 13: US10 — Elementor Pro (8 abilities) (P3)

**Goal**: Pro-only abilities gated on Elementor Pro presence.

**Custom Code (5):**
- [ ] T114 [P] [US10] Create `includes/Abilities/Elementor/List_Custom_Code.php` per contracts § 9.1
- [ ] T115 [P] [US10] Create `Get_Custom_Code.php`
- [ ] T116 [P] [US10] Create `Create_Custom_Code.php`
- [ ] T117 [P] [US10] Create `Update_Custom_Code.php`
- [ ] T118 [P] [US10] Create `Delete_Custom_Code.php`

**Form Submissions (3):**
- [ ] T119 [P] [US10] Create `List_Form_Submissions.php`
- [ ] T120 [P] [US10] Create `Get_Form_Submission.php`
- [ ] T121 [P] [US10] Create `Delete_Form_Submission.php`

**Each Pro ability's `execute()` MUST first check `class_exists( '\ElementorPro\Plugin' )` and return `error_code: elementor_pro_missing` if absent.**

- [ ] T122 [P] [US10] Create 8 test files
- [ ] T123 [US10] Register all 8 abilities in bootstrap's Pro-conditional block

---

## Phase 14: Polish & release

- [ ] T124 Add all 88 new ability-class test files + 5 utility test files to `phpunit.xml.dist` (all 88 test files, one per ability)
- [ ] T125 Run `composer test` — expect 577 baseline + 88 ability tests + 5 utility tests = 670+ tests, 0 failures
- [ ] T126 Run `composer phpcs` — 0 errors across all new files
- [ ] T127 Run `composer phpstan` — 0 errors at level 8
- [ ] T128 Absence-gating verification: deactivate Elementor, run `wp ability list --category=acrossai-abilities-manager-elementor` — expect 0 results; reactivate — expect 80 (or 88 with Pro)
- [ ] T129 Runtime-gating verification: invoke any ability, deactivate Elementor mid-session, invoke again — expect `error_code: elementor_missing`, no fatal
- [ ] T130 Pro-gating verification: deactivate Elementor Pro, invoke any Pro ability — expect `error_code: elementor_pro_missing`; reactivate — succeeds
- [ ] T131 Execute quickstart.md end-to-end on a live Elementor site (17-step walkthrough)
- [ ] T132 Bump version in `acrossai-abilities-manager.php` header: `0.0.24` → `0.0.25`
- [ ] T133 Bump `Stable tag: 0.0.24` → `0.0.25` in `readme.txt` (or `README.txt` — same file on macOS)
- [ ] T134 Add `= 0.0.25 =` changelog entry summarising all 88 new abilities + 6 new utilities + Elementor gating + Pro sub-gate

---

## Dependencies & Execution Order

### Phase dependencies

- **Phase 1 (Setup)** — no deps
- **Phase 2 (Foundational)** — depends on Phase 1; **BLOCKS all user stories** because every ability consumes the 6 utilities
- **Phase 3-13 (User Stories)** — each depends only on Phase 2. All 11 story/group phases can run in parallel after Phase 2 completes.
- **Phase 14 (Polish)** — depends on all Phases 3-13

### Within each user story

- Ability class files can be created in parallel (different files)
- Test files can be created in parallel with their ability classes (different files)
- Bootstrap registration (T009/T010 modifications) is inherently serialised — same file
- Run each story's test filter as the final task: `composer test -- --filter=Test_Elementor_<AbilityGroup>`

### Parallel opportunities

- **Phase 2**: T003-T008 + T011-T013 all mark [P] (different files)
- **Phases 3-13**: **All 11 user-story phases can run in parallel** once Phase 2 completes — each ability class touches its own file
- **File-conflict serialisation**: only `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` is shared — bootstrap-registration tasks (T009/T010, T019, T024, T030, T037, T045, T058, T067, T071, T082, T113, T123) must be applied sequentially or merged into a single "register all 88" task if implementing in one pass. Recommended: merge all bootstrap registrations into one commit after all ability classes exist.

---

## Parallel example: kicking off US4 (compose a page)

```bash
# After Phase 2 checkpoint, in parallel:
Task T031: Create Create_Page.php
Task T032: Create Add_Container.php
Task T033: Create Add_Widget.php (validates widget_type via Widget_Controls)
Task T034: Create Update_Page_Settings.php
Task T035: Create 5 widget shortcut classes (Add_Heading, Add_Text_Editor, Add_Image, Add_Button, Add_Post_Tabs)
Task T036: Create test files for all 9 abilities
# Then serialise:
Task T037: Register all 9 abilities in bootstrap
```

---

## Implementation strategy

### MVP scope (recommended first checkpoint)

Complete Phases 1-6 (Setup + Foundational + US1-US4). This delivers:
- Widget schema discovery (`get-widget-controls`)
- Document read + element read/find/get (`get-data`, `find-elements`, `get-element`)
- Element update + delete + merge (`update-element`, `merge-element-settings`, `delete-element`, `remove-element`)
- Page composition (`create-page`, `add-container`, `add-widget`, 5 widget shortcuts, `update-page-settings`)

= 22 abilities. Enough to author full Elementor pages end-to-end. **STOP + VALIDATE** here on a live site before continuing.

### Incremental delivery (recommended)

1. Setup + Foundational (Phase 1-2) → 6 utilities + category + bootstrap gate
2. Add US1-US4 (Phase 3-6) → 22 abilities → **MVP checkpoint**
3. Add US5-US8 + Phase 11 (Phase 7-11) → 30 more abilities → templates + kits + system → 52 total
4. Add US9 design audits (Phase 12) → 28 more → 80 total (matches free-Elementor complete count)
5. Add US10 Pro (Phase 13) → 8 more → 88 total (full feature)
6. Polish (Phase 14) → tests + phpcs + phpstan + version bump + PR + release

### Full-parallel team strategy

- 1-2 devs on Phase 1-2 (foundational)
- After Phase 2: fan out 10 user-story phases across 5-8 devs
- Convergence for Phase 14 polish

---

## Notes

- [P] = different file, no dependency on incomplete tasks
- [US#] maps every user-story-phase task to its spec story for traceability
- Bootstrap file (`AcrossAI_Core_Abilities_Bootstrap.php`) is the one serialisation point — merge all 88 `new Elementor\<Class>()` registrations at the end of the run
- Every ability class MUST include the runtime `class_exists( '\Elementor\Plugin' )` guard as the first check in `execute()` per R1 (defense-in-depth)
- Every Pro ability class MUST additionally check `class_exists( '\ElementorPro\Plugin' )` as the second check in `execute()`
- Every `_elementor_data` write MUST go through `Document_Repository::save_data()` which wraps in `wp_slash()` and invalidates cache
- Test convention: source-inspection (`file_get_contents` + `assertStringContainsString`) matching `Test_Block_Tree.php`
- Commit after each story phase; the `after_*` extension hooks in `.specify/extensions.yml` will offer auto-commits
- Avoid: multi-ability changes in a single task, cross-story dependencies that break independence, custom SQL (use WP APIs only), skipping the `wp_slash` wrap on `_elementor_data` writes

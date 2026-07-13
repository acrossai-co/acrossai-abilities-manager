---
description: "Task list for Feature 046 — Absorb Core Abilities Companion Into Manager"
---

# Tasks: Absorb Core Abilities Companion Into Manager

**Input**: Design documents from `/specs/046-absorb-core-abilities-into-manager/`
**Prerequisites**: [plan.md](./plan.md) (required), [spec.md](./spec.md) (required for user stories), [memory-synthesis.md](./memory-synthesis.md), [security-review-plan.md](./security-review-plan.md), [violation-detection.md](./violation-detection.md), [docs/planning/046-absorb-core-abilities-into-manager.md](../../docs/planning/046-absorb-core-abilities-into-manager.md)

**Tests**: INCLUDED. Constitution §VII (Definition of Done) mandates unit tests. Security review (`security-review-plan.md` §Action Plan) specifies five verification tasks (`TASK-SEC-046-01` through `TASK-SEC-046-05`) — mapped inline below.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 / US2 / US3 / US4 per spec.md user stories
- Include exact file paths in descriptions

## Path Conventions

Repository root: `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-abilities-manager/`
Source companion (READ-ONLY): `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-core-abilities/`
All manager-side paths below are repo-root relative.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prepare target directory tree and bulk-rewrite tooling before any file moves.

- [ ] T001 Create the empty target directory tree `includes/Abilities/{Block,Cache,Comments,Content,Cron,Database,FileManager,Fonts,Media,Menus,Options,Plugins,Settings,SiteHealth,Taxonomies,Themes,Users,Utilities/{Block_Style_Variations,Global_Styles,Pattern,Template,Template_Part}}` under the manager plugin root
- [ ] T002 [P] Verify `admin/Partials/` exists (should already, per current manager layout) — no action if present
- [ ] T003 [P] Author a helper shell script `scripts/046-rewrite-matrix.sh` that runs the 9-step CHANGE-5 bulk rewrites in the mandated order (5a namespace → 5b use → 5c text-domain → 5d constants → 5e label text → 5f category slugs → 5g identifier names → 5h singleton property → 5i docblock) per BUG-PYTHON-STRREPLACE-PARTIAL-WRITE (use `sed`/`perl` with synchronous per-file writes)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Copy the raw source files into the target tree so subsequent user-story phases can operate on them.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T004 Copy every PHP file from `../acrossai-core-abilities/includes/Abilities/` into `includes/Abilities/` preserving the 17 category subfolder structure (CHANGE-1). Verify count: **201 PHP files total** across the 17 category folders (17 Category_Registrar + 176 ability classes + 8 helper classes such as Formatters, Routes, Moderation).
- [ ] T005 Copy every PHP file from `../acrossai-core-abilities/includes/Utilities/` into `includes/Abilities/Utilities/` preserving the 5 sub-folder structure (CHANGE-2). Verify count: 9 top-level + all files under Block_Style_Variations/, Global_Styles/, Pattern/, Template/, Template_Part/
- [ ] T006 Copy `../acrossai-core-abilities/includes/Admin/Partials/Core_Settings_Menu.php` to `admin/Partials/Core_Settings_Menu.php` (CHANGE-3)
- [ ] T007 Run `scripts/046-rewrite-matrix.sh` from T003 across `includes/Abilities/` and `admin/Partials/Core_Settings_Menu.php` — applies CHANGE-5a through 5i in order; commit before running any quality gates
- [ ] T008 Run `phpcbf` over the moved tree to normalize whitespace after the rewrites; commit
- [ ] T009 Run the 4 grep-audit merge gates (plan §7 audits 1–4) — all must return zero matches: `grep -R "Acrossai_Core_Abilities" …`, `grep -R "'acrossai-core-abilities'" …`, `grep -R "acrossai-core-abilities-" …`, `grep -R "ACROSSAI_CORE_ABILITIES_" …`. Fail the phase if any hit lands outside `specs/`
- [ ] T010 Run the Constitution §II forbidden-function grep gate (plan §7 audit #5): `grep -RnE '\beval\(|extract\(|shell_exec\(|passthru\(|exec\(|popen\(|proc_open\(|system\(' includes/Abilities` — must return zero hits (SEC-046-03)
- [ ] T011 Run `composer dump-autoload`; confirm no missing-file warnings; open a fresh admin page and confirm no "Class not found" fatals for any moved class

**Checkpoint**: Foundation ready — moved tree exists, rewrites applied, autoload passes, forbidden-function audit clean. User story implementation can now begin.

---

## Phase 3: User Story 1 — Single-plugin ability registration (Priority: P1) 🎯 MVP

**Goal**: With only the manager plugin active, all 17 rebranded categories and all 176 abilities appear in the WP Abilities API enumeration.

**Independent Test**: Fresh WP site, only manager active, no companion on disk. Run `wp ability category list --format=json | jq '. | length'` → 17. Enumerate `wp ability list` → 176 abilities. Invoke one ability per category → same payload shape as pre-migration.

### Tests for User Story 1

- [ ] T012 [P] [US1] Add PHPUnit case `tests/phpunit/abilities/Absorbed/Bootstrap_Registers_Categories_Test.php` — asserts `AcrossAI_Core_Abilities_Bootstrap::instance()->register_categories()` registers exactly 17 categories with the `acrossai-abilities-manager-` prefix
- [ ] T013 [P] [US1] Add PHPUnit case `tests/phpunit/abilities/Absorbed/Bootstrap_Registers_Abilities_Test.php` — asserts `register_abilities()` instantiates exactly 176 ability classes without PHP notices/warnings
- [ ] T014 [P] [US1] Add PHPUnit case `tests/phpunit/abilities/Absorbed/Legacy_Slugs_Absent_Test.php` — asserts zero `acrossai-core-abilities-*` category slugs are registered post-migration (SEC-046-01 verification support)

### Implementation for User Story 1

- [ ] T015 [US1] **Verification-only** — grep-confirm every `Category_Registrar.php` under `includes/Abilities/*/` already has an empty constructor and a synchronous `register()` method (this is the actual pattern shipped by the companion — no refactor needed after the T007 rewrite matrix rebranded slugs/labels and normalized `$_instance` → `$instance`). Run `grep -RnE 'add_action|add_filter' includes/Abilities/*/Category_Registrar.php` and confirm zero hits. If any hit appears, halt and re-plan
- [ ] T016 [US1] **Verification-only** — grep-confirm the 176 ability class files (out of 201 total PHP files under the 17 category folders — the remaining 25 = 17 Category_Registrar + 8 helper classes) contain no `add_action`/`add_filter` calls in their bodies (they extend `Ability_Definition` which handles registration via its inherited constructor). Run `grep -RnE 'add_action|add_filter' includes/Abilities/{Block,Cache,Comments,Content,Cron,Database,FileManager,Fonts,Media,Menus,Options,Plugins,Settings,SiteHealth,Taxonomies,Themes,Users}/*.php --include='*.php' --exclude='Category_Registrar.php'` and confirm zero hits. Ancillary static registration calls in a few files (e.g., `Media/Upload_Media.php` may reference `CHUNK_SWEEP_HOOK`) are wired centrally from the Bootstrap in T017 — they don't need to be relocated
- [ ] T017 [US1] Create `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — singleton with `$instance` property (PSR2 per DEC-SINGLETON-PSR2-PROPERTY), private constructor, and two public methods: `register_category_callbacks( AcrossAI_Loader $loader )` that calls `$loader->add_action( 'wp_abilities_api_categories_init', <Cat>\Category_Registrar::instance(), 'register' )` 17 times (one per category, in the companion's original order); `register_abilities()` that (a) short-circuits via `class_exists('Ability_Definition')` (default autoload ON — passing `false` silently no-ops when the class isn't referenced yet at `plugins_loaded @ P20`), (b) executes **176** `new <Cat>\<Ability_Class>()` statements in the companion's original order, and (c) invokes the three extras the companion Main.php also ran: `Cron_Helpers::register_filter()`, `add_action( Media\Upload_Media::CHUNK_SWEEP_HOOK, [Media\Upload_Media::class, 'sweep_chunk_sessions'] )`, and `Media\Upload_Media::register_sweep_cron()`. Match the class list against companion Main.php lines ~330-505 verbatim (176 `new` statements).
- [ ] T018 [US1] Modify `includes/Main.php::define_public_hooks()` to instantiate the Bootstrap and wire it via the Loader following variable-first AC-HOOKS-MAIN: `$core_abilities_bootstrap = AcrossAI_Core_Abilities_Bootstrap::instance(); $core_abilities_bootstrap->register_category_callbacks( $this->loader ); $this->loader->add_action( 'plugins_loaded', $core_abilities_bootstrap, 'register_abilities', 20 );` (CHANGE-6 public block)
- [ ] T019 [US1] Run `composer phpstan` on `includes/Abilities/` chunked per category; fix any typing errors surfaced by PHPStan against the moved code
- [ ] T020 [US1] Run `composer phpcs` on `includes/Abilities/`; fix any coding-standard violations not auto-corrected by T008 phpcbf
- [ ] T021 [US1] Manual verification against a wp-env fixture: activate the manager only, open wp-admin, confirm no PHP fatals; run `wp ability category list` and confirm 17 rebranded category slugs; run `wp ability list --category=acrossai-abilities-manager-plugins` and confirm the plugin-management abilities are enumerated

**Checkpoint**: US1 complete. Manager alone provides all 193 rebranded registrations (17 categories + 176 abilities). Ship-able MVP.

---

## Phase 4: User Story 3 — Core settings inside the Abilities tab (Priority: P2)

**Goal**: The absorbed extra-MIME-types field renders inside the existing Abilities tab (URL `…?tab=abilities`) — no separate Core tab. A single "Delete data on uninstall" checkbox governs both manager and absorbed data. Companion option keys migrate at activation and are cleaned up.

**Independent Test**: On a wp-env fixture with only the manager active, open `…?page=acrossai-settings&tab=abilities` and confirm: extra-MIME-types field visible, exactly one uninstall checkbox, no reachable `?tab=core`. Save a MIME type, reload, confirm the value persists under `acrossai_abilities_manager_extra_mimes`.

### Tests for User Story 3

- [ ] T022 [P] [US3] Add PHPUnit case `tests/phpunit/abilities/Absorbed/Activation_Migration_Idempotency_Test.php` — asserts the OR-monotonic and idempotent activation-migration semantics (TASK-SEC-046-02): seed both legacy keys, activate twice, verify only-one-copy semantics; separately verify manager `= 1` cannot be demoted by legacy `= 0`; verify legacy `= 1` promotes manager `false → true`
- [ ] T023 [P] [US3] Add PHPUnit case `tests/phpunit/abilities/Absorbed/Uninstall_Gate_Test.php` — asserts `uninstall.php` deletes `acrossai_abilities_manager_extra_mimes` only when `acrossai_abilities_uninstall_delete_data` is truthy (TASK-SEC-046-04). Verifies PATTERN-UNINSTALL-DATA-GATE and BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE compliance
- [ ] T024 [P] [US3] Add PHPUnit case `tests/phpunit/abilities/Absorbed/Settings_Field_Registers_On_Abilities_Tab_Test.php` — asserts `Core_Settings_Menu::register_settings()` registers the MIME-types field into the manager's existing Abilities-tab section, and does NOT register a Core tab via `acrossai_settings_tabs`

### Implementation for User Story 3

- [ ] T025 [US3] Refactor `admin/Partials/Core_Settings_Menu.php`: drop any `register_tab()` method and any `acrossai_settings_tabs` filter subscription; keep `register_settings()` but rewire it to `add_settings_field()` into the manager's existing Abilities-tab section under the shared `option_group`; drop the uninstall-opt-in field entirely (the manager's existing single opt-in covers it); confirm the class uses `$instance` (PSR2)
- [ ] T026 [US3] Modify `includes/Main.php::define_admin_hooks()` to add `$this->loader->add_action( 'admin_init', $core_settings_menu, 'register_settings' )` following variable-first AC-HOOKS-MAIN. Do NOT add an `acrossai_settings_tabs` filter (CHANGE-6 admin block)
- [ ] T027 [US3] Modify `includes/AcrossAI_Activator.php::activate()` to run the activation-time option migration (CHANGE-7): copy `acrossai_core_abilities_extra_mimes` → `acrossai_abilities_manager_extra_mimes` only if the manager-branded key is null; OR the legacy uninstall-opt-in value into the manager's existing opt-in with monotonic semantics; unconditionally delete both legacy keys after the copy/OR
- [ ] T028 [US3] Modify `uninstall.php`: inside the existing `$acrossai_delete_data` gate, add `delete_option( 'acrossai_abilities_manager_extra_mimes' );` (CHANGE-8). Verify the delete is not placed outside the gate (BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE)
- [ ] T029 [US3] Run `composer phpstan` and `composer phpcs` on the modified files (`Core_Settings_Menu.php`, `Main.php`, `AcrossAI_Activator.php`, `uninstall.php`); fix zero errors
- [ ] T030 [US3] Manual verification of upgrade path: seed a wp-env DB with `acrossai_core_abilities_extra_mimes = "text/x-foo"` and `acrossai_core_abilities_uninstall_delete_data = 1`; activate the manager; verify the value migrates to `acrossai_abilities_manager_extra_mimes`, the manager's existing `acrossai_abilities_uninstall_delete_data` becomes 1, and both legacy rows are deleted from `wp_options`
- [ ] T031 [US3] Manual verification of steady state: open `?page=acrossai-settings&tab=abilities`, confirm the extra-MIME-types field is visible, confirm exactly one uninstall checkbox exists, save a MIME type, reload, confirm the value persists; navigate to `?tab=core` and confirm the URL returns to the default tab

**Checkpoint**: US3 complete. Absorbed settings surface merged into the Abilities tab; upgrade path preserves admin config.

---

## Phase 5: User Story 2 — Downstream integrator verification & coordination (Priority: P2)

**Goal**: Downstream integrators (MCP servers, REST callers, WP-CLI scripts) that reference category slugs receive a documented, coordinated breaking change; internal AcrossAI-org plugins update their references in lockstep.

**Independent Test**: Enumerate categories via the WP Abilities API after the migration; confirm every category slug uses `acrossai-abilities-manager-` prefix; confirm zero legacy `acrossai-core-abilities-` slugs; confirm ability payload shapes are unchanged.

### Tests for User Story 2

- [ ] T032 [P] [US2] Add PHPUnit case `tests/phpunit/abilities/Absorbed/Ability_Payload_Shape_Unchanged_Test.php` — asserts payload shape parity for a sample of high-traffic abilities (e.g., `Plugin_List`, `Content\Get_Post`, `Users\User_Get`): payload keys, types, and top-level structure match the pre-migration source (fixture)

### Implementation for User Story 2

- [ ] T033 [US2] Coordinate with any first-party AcrossAI-org plugin that references the legacy `acrossai-core-abilities-<domain>` category slugs — audit `acrossai-mcp-manager` (if present) and any other sibling plugin for slug references; open coordinated PRs for each. (TASK-SEC-046-01 — LOW severity, downstream mitigation)
- [ ] T034 [US2] Add a release-notes entry in `docs/planning/` (or the release-notes file this project uses) that explicitly lists the 17 category slug renames (before → after) for public consumption. Reference `spec.md` FR-001 and US2

**Checkpoint**: US2 complete. Downstream integrators have a documented rename map; verification test confirms zero legacy slugs registered.

---

## Phase 6: User Story 4 — Single quality gate over the absorbed inventory (Priority: P3)

**Goal**: The manager's existing quality gate (`composer phpstan`, `composer phpcs`, `composer test`, `npm run validate-packages`) passes over the entire migrated tree without regressions.

**Independent Test**: On the feature branch after Phases 1–5, run all quality gates. All must exit zero-errors. Existing tests in `Modules/Abilities/` and `Modules/Library/` must not be silently skipped or broken.

### Implementation for User Story 4

- [ ] T035 [P] [US4] Run `composer phpstan` (level 8) over the whole plugin; assert zero errors. Investigate and fix any errors traced to the absorbed tree
- [ ] T036 [P] [US4] Run `composer phpcs` over the whole plugin; assert zero errors. If phpcbf can auto-fix, run and commit; otherwise fix manually
- [ ] T037 [P] [US4] Run `composer test` (full PHPUnit suite); assert green. In particular, confirm the pre-existing `Modules/Abilities/` and `Modules/Library/` tests are neither silently skipped nor broken by the migration
- [ ] T038 [P] [US4] Run `npm run validate-packages`; assert green (no changes expected — no JS moved)
- [ ] T039 [US4] Re-run the four grep merge gates from T009 (`Acrossai_Core_Abilities`, `'acrossai-core-abilities'`, `acrossai-core-abilities-`, `ACROSSAI_CORE_ABILITIES_`) and the forbidden-function gate from T010; assert zero hits (belt-and-suspenders — should still be green from the foundational phase)
- [ ] T040 [US4] (Optional) Run Plugin Check on the production plugin surface via `.github/workflows/plugin-check.yml` locally; assert no new errors

**Checkpoint**: US4 complete. Definition-of-Done constitution gates pass.

---

## Phase 7: Security-Review Verification (from `security-review-plan.md`)

**Purpose**: Explicit tasks mapped from the plan-phase security review's Action Plan. Most already inlined above; this phase gathers the remaining verification.

- [ ] T041 [P] Add PHPUnit case `tests/phpunit/abilities/Absorbed/Permission_Gate_Sample_Test.php` — samples 3 ability classes across high-risk categories (`FileManager\File_Delete`, `Database\Db_Delete`, `Plugins\Plugin_Deactivate`) and asserts each still carries a `manage_options`-scoped permission check after the migration. Note: the Path-A constructor refactor described in earlier plan revisions was **retracted** on 2026-07-13 after a source spot-check; no refactor occurred, so this test is a straight assertion that the moved classes' `ability()` return arrays continue to embed the permission_callback they always had (TASK-SEC-046-05 — sample check per project memory feedback)

**Checkpoint**: All security-review-plan.md Action Plan tasks accounted for.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Follow-up work and durable-memory captures identified during planning.

- [ ] T042 [P] Run `/speckit-memory-md-capture` (invoked manually — per project memory `feedback_user_runs_speckit_commands.md`) with the four candidate captures surfaced in the governed-plan summary: `DEC-ABSORBED-CODE-INCLUDES-TIER`, `PATTERN-BULK-REWRITE-MATRIX`, `PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC`, `PATTERN-CONSTRUCTOR-HOOK-REFACTOR-PATH-A`
- [ ] T043 [P] Append the memory-hub INDEX row for the security review to `docs/memory/INDEX.md` under the "Security Reviews" table: `| specs/046-absorb-core-abilities-into-manager/security-review-plan.md | plan | 2026-07-13 | INFORMATIONAL | C:0 H:0 M:0 L:1 I:4 | A03,A05,A08 |`
- [ ] T044 Draft a follow-up spec stub `specs/047-constitution-include-abilities-tier/spec.md` (skeleton only) to enumerate `includes/Abilities/` in Constitution §I Directory Layout via a PATCH bump — resolves plan.md V-02 as a governance task
- [ ] T045 [P] Log a follow-up spec stub `specs/048-dry-audit-abilities-utilities/spec.md` (skeleton only) to audit `includes/Abilities/Utilities/` versus `includes/Utilities/` for DRY overlaps — resolves plan.md V-03
- [ ] T046 [P] Log a follow-up spec stub `specs/049-regenerate-pot/spec.md` (skeleton only) to regenerate `languages/acrossai-abilities-manager.pot` to include the newly absorbed strings
- [ ] T047 Update `docs/memory/WORKLOG.md` with the feature-046 milestone entry once implementation is committed
- [ ] T048 Final `git status` review — ensure no unintended files were touched in the companion plugin folder (`../acrossai-core-abilities/` MUST remain untouched per spec FR-014)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies. Can start immediately.
- **Phase 2 (Foundational)**: Depends on Phase 1. **BLOCKS all user stories.**
- **Phase 3 (US1 — MVP)**: Depends on Phase 2. Ship-able MVP after this phase.
- **Phase 4 (US3)**: Depends on Phase 2. Independent of Phase 3 in principle, but easier to run after US1 lands because Main.php touch-points overlap.
- **Phase 5 (US2)**: Depends on Phase 3 (US1 delivers the actual slug rebrand; US2 verifies it).
- **Phase 6 (US4)**: Depends on Phase 3, 4, 5. Runs the constitutional quality gate.
- **Phase 7 (Security)**: Runs any time after Phase 3; ideally after Phase 4 so all migration surface is exercised.
- **Phase 8 (Polish)**: Runs last.

### User Story Dependencies

- **US1 (P1) — MVP**: Depends on Foundational (Phase 2). No dependencies on other stories.
- **US2 (P2)**: Depends on US1 (verifies the slug rebrand US1 delivers). Cannot be started before US1's Bootstrap wiring is committed.
- **US3 (P2)**: Depends on Foundational (Phase 2). Can be developed in parallel with US1 by a second developer; independent code paths in `Main.php::define_admin_hooks()` and `AcrossAI_Activator`.
- **US4 (P3)**: Depends on US1, US2, US3 — quality gates over the combined state.

### Within Each User Story

- Tests written and failing → implementation → verify passing (Constitution §VII).
- Rewrite matrix (Phase 2) → constructor refactor (US1) → Bootstrap wiring (US1).
- Core_Settings_Menu refactor (US3) → Main.php admin wiring (US3) → activation migration (US3) → uninstall gate (US3).
- Chunk `composer phpstan` and `composer phpcs` per category folder inside US1 T016 to isolate failures.

### Parallel Opportunities

- **T012, T013, T014** (US1 tests) parallel — different test files.
- **T015 (17 Category_Registrar files) can be applied concurrently by different reviewers**, one per category — but T015 as a task is atomic in this list.
- **T022, T023, T024** (US3 tests) parallel — different test files.
- **T032** (US2 payload test) parallel with US2 T033 coordination work.
- **T035, T036, T037, T038** (US4 quality gates) parallel — no shared state between commands.
- **T042, T043, T045, T046** (Polish) parallel — different files / different commands.

---

## Parallel Example: User Story 1

```bash
# Launch all US1 tests together (write these BEFORE implementation per Constitution §VII):
Task: "Add PHPUnit case tests/phpunit/abilities/Absorbed/Bootstrap_Registers_Categories_Test.php"
Task: "Add PHPUnit case tests/phpunit/abilities/Absorbed/Bootstrap_Registers_Abilities_Test.php"
Task: "Add PHPUnit case tests/phpunit/abilities/Absorbed/Legacy_Slugs_Absent_Test.php"

# Refactor category-registrar files and ability files can be split by category:
#   Developer A: Block/, Cache/, Comments/
#   Developer B: Content/, Cron/, Database/
#   Developer C: FileManager/, Fonts/, Media/
#   Developer D: Menus/, Options/, Plugins/
#   Developer E: Settings/, SiteHealth/, Taxonomies/
#   Developer F: Themes/, Users/
# But T015 and T016 are represented as atomic tasks here; parallelization is at review-batch granularity.
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup — target tree + rewrite tooling ready.
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories). Files copied, bulk rewrites applied, autoload passes, forbidden-function grep clean.
3. Complete Phase 3: US1 — Bootstrap wires 17 category callbacks + instantiates 176 ability classes from Main.php. (The originally-planned Path A refactor across 193 classes was retracted; see plan.md V-01.)
4. **STOP and VALIDATE**: On wp-env, activate manager only, enumerate 17 rebranded categories and 176 abilities. This is the shippable MVP.

### Incremental Delivery

1. Ship US1 as the MVP.
2. Add US3 (Core settings inside Abilities tab + activation migration + uninstall gate). Ship. Admins get the merged settings UI + safe upgrade path.
3. Add US2 verification + downstream coordination + release notes. Ship. Downstream integrators get a documented rename map.
4. Add US4 (quality gates confirmation). Ship. Definition of Done satisfied.
5. Phase 7 security verification + Phase 8 polish and follow-up spec stubs. Ship.

### Parallel Team Strategy

- Two developers: US1 (Phase 3) and US3 (Phase 4) can proceed in parallel after Phase 2.
- Reviewer batches: split T015 and T016 across category folders so PHPStan/PHPCS chunked runs give tight feedback loops.

---

## Notes

- `[P]` tasks operate on different files with no dependencies on incomplete tasks.
- `[Story]` label maps every user-story-phase task back to its spec.md user story.
- Each user story is independently testable and ship-able as an increment.
- **Tests written and failing** before implementation, per Constitution §VII and the template guidance.
- **Commit** after each task or logical group (per project convention — Spec Kit `after_*` git hooks are already wired).
- Every task specifies exact file paths for LLM-executable clarity.
- Avoid: touching `../acrossai-core-abilities/` files (spec FR-014); creating a `?tab=core` URL (spec FR-004); creating a second uninstall-opt-in checkbox on the Abilities tab (spec Q4).

---

## Security Task Cross-Reference

| Security Review ID | Task ID(s) | Verification |
|---|---|---|
| TASK-SEC-046-01 | T033, T034 | Downstream slug coordination + release notes |
| TASK-SEC-046-02 | T022 | PHPUnit — activation migration idempotency + OR-monotonic |
| TASK-SEC-046-03 | T010, T039 | Forbidden-function grep merge gate |
| TASK-SEC-046-04 | T023 | PHPUnit — uninstall gate honors opt-in |
| TASK-SEC-046-05 | T041 | PHPUnit — sample permission-gate spot-check post-refactor |

---

## Task Count Summary

| Phase | Count | Notes |
|---|---|---|
| 1 — Setup | 3 | T001–T003 |
| 2 — Foundational | 8 | T004–T011 |
| 3 — US1 (MVP) | 10 | T012–T021 (3 tests + 7 impl) |
| 4 — US3 | 10 | T022–T031 (3 tests + 7 impl) |
| 5 — US2 | 3 | T032–T034 (1 test + 2 coordination) |
| 6 — US4 | 6 | T035–T040 (all quality gates) |
| 7 — Security | 1 | T041 (sample permission-gate PHPUnit) |
| 8 — Polish | 7 | T042–T048 |
| **Total** | **48 tasks** | 8 test-only, 34 implementation-focused, 6 quality-gate/verification |

Suggested MVP scope: **Phase 1 + Phase 2 + Phase 3 (US1)** = T001–T021 (21 tasks). Ship-able state where the manager alone provides all 193 rebranded registrations (17 categories + 176 abilities).

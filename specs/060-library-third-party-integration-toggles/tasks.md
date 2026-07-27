---

description: "Task list for Feature 060 — Library Third-Party Integration Toggles"
---

# Tasks: Library Third-Party Integration Toggles

**Input**: Design documents from `/specs/060-library-third-party-integration-toggles/`
**Prerequisites**: `plan.md` (required), `spec.md` (required), `security-constraints.md`, `security-review-plan.md`, `memory-synthesis.md`, `docs/planning/060-library-third-party-integration-toggles.md`

**Tests**: **Included** — Constitution §VII Definition of Done requires unit tests for new logic, and the security review (`security-review-plan.md` SEC-001 / SEC-004) specifies exact PHPUnit assertions.

**Organization**: Tasks are grouped by user story so each can be implemented and validated independently. Security-review findings (`SEC-001`…`SEC-005`) are folded into the story where they apply, with the mapping noted on each task.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no cross-dependencies).
- **[Story]**: `[US1]`, `[US2]`, `[US3]`, `[US4]`, `[US5]` maps to the user stories in `spec.md`. Setup / Foundational / Polish tasks have no story label.
- Every task lists an exact file path.

## Path Conventions

- **WordPress plugin, single-project layout** per Constitution §I Directory Layout.
- All paths are relative to the plugin root `wp-content/plugins/acrossai-abilities-manager/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm the development toolchain is in the expected state before writing any new code. All prerequisites already exist in the repo — this phase only validates them.

- [x] T001 Verify Composer dependencies are installed by running `composer install` from the plugin root; abort if `vendor/` differs from `composer.lock`.
- [x] T002 Verify JS dependencies are installed by running `npm ci` from the plugin root; abort if `node_modules/` differs from `package-lock.json`.
- [x] T003 [P] Confirm current branch is `060-library-third-party-integration-toggles` and there are no uncommitted spec-kit artifact changes staged from a prior turn.

**Checkpoint**: Toolchain green. Ready to write foundational code.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Build the reusable pipeline that every user story depends on. Without this phase, no integration (ACF, WooCommerce, or otherwise) can render on the Library page.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T004 [P] Create the abstract base class `AcrossAI_Integration_Ability_Base` at `includes/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base.php`, namespace `AcrossAI_Abilities_Manager\Includes\Modules\Library\Integrations`. Include the `defined( 'ABSPATH' ) || exit;` guard, file-doc header per `AC-FILE-HEADER-PATTERN`, and five abstract methods (`slug`, `label`, `is_plugin_active`, `enable_filter`, `abilities`) per plan.md CHANGE-1 contract. Constructor wires `add_action( 'plugins_loaded', array( $this, 'maybe_enable' ), 20 )` and `add_filter( 'acrossai_abilities_api_init', array( $this, 'push_definition' ), 10 )` — both callbacks return early when `is_plugin_active()` is false. Docblock cites the existing `Ability_Definition` precedent and the new deviation `DEC-ABILITY-DEFINITION-CTOR-HOOKS` for Constitution §I Boot Flow Rule.
- [x] T005 In the same file, implement `push_definition( array $definitions ): array` — early return when `is_plugin_active()` is false; otherwise loop `$this->abilities()` and push one row per ability with shared `category` = `$this->slug()`, `category_label` = `$this->label()`, `tab_group` = `$this->slug()`, `card_variant` = `'integration'`, and a placeholder `args` block containing a fail-closed `execute_callback` (returns `new WP_Error( 'acrossai_integration_synthetic_row', … )`).
- [x] T006 In the same file, implement `maybe_enable(): void` per SEC-001: (a) early return if `is_plugin_active()` is false; (b) return if `AcrossAI_Ability_Library_Config::is_integration_enabled( $this->slug() )` returns false; (c) wrap `$this->enable_filter()` in `try { … } catch ( \Throwable $e ) { … }`; on caught exception, log via the `PATTERN-WP-DEBUG-LOG-GUARD` (`if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) { error_log( … ); }`) and fail closed (no filter attachment).
- [x] T007 [P] Add `is_integration_enabled( string $category ): bool` static helper to `includes/Modules/Library/AcrossAI_Ability_Library_Config.php` per plan.md CHANGE-3. Returns `false` when the config key is missing; returns `(bool) $config[ $category ]['enabled']` when the key exists. Include PHPDoc explaining the inverted default and citing FR-008.
- [x] T008 [P] Extend `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php` per plan.md CHANGE-5: (a) add `'card_variant'` to the `OPTIONAL_FIELDS` constant (currently at line ~84 alongside `sub_group`, `sub_group_label`, `tab_group`); (b) add a pass-through block in `validate_and_normalize()` immediately after the `tab_group` block (lines ~236-241) that sanitizes via `AcrossAI_Ability_Library_Config::sanitize_key_field()` and only propagates non-empty values. Comment cites Feature 060.
- [x] T009 Modify the REST write path in `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` per plan.md CHANGE (implicit) + security-review SEC-005: for each incoming entry whose `category` matches a registered integration slug, additionally call `current_user_can( apply_filters( 'acrossai_integration_toggle_capability', 'manage_options', $sanitised_slug ) )`. Both the existing `manage_options` floor AND the filtered capability MUST pass. On failure, return `new WP_Error( 'rest_forbidden', …, array( 'status' => 403 ) )` per Constitution's REST `permission_callback` return-type rule. Pass the **post-sanitization** slug to `apply_filters` (SEC-005). Fire `do_action( 'acrossai_integration_toggle_denied', $sanitised_slug, $required_cap, get_current_user_id() )` before the 403 (SEC-003).

**Checkpoint**: Base class + Registry + Config + REST gate are in place. All user story phases can now proceed in parallel.

---

## Phase 3: User Story 1 — Enable a third-party plugin's AI abilities from one place (Priority: P1) 🎯 MVP

**Goal**: A site admin can flip one toggle on the Ability Library page and immediately have ACF's AI abilities available to AI/MCP clients, without editing code.

**Independent Test**: With ACF activated and integration toggle off, ACF abilities are absent from the ability list. Flip the toggle on, reload, confirm ACF abilities are present. Flip off, reload, confirm they're gone.

### Tests for User Story 1 (write first, watch fail, then implement)

- [x] T010 [P] [US1] Created at `tests/phpunit/Modules/Library/Integrations/Test_Integration_Ability_Base.php` (file name matches repo Test_* convention rather than *_Test). 9 tests, 24 assertions, all pass. Covers cases (a)-(f) per plan.
- [x] T011 [P] [US1] Created at `tests/jest/ability-library/LibraryCard.integration-variant.test.js` (path adjusted to match repo convention — no `components/` subdirectory used by other library tests). Pure-predicate tests per `PATTERN-NAMED-EXPORT-JEST`.
- [x] T012 [US1] Added groupDefinitions propagation tests to the same file (4 tests covering undefined default, single-row propagation, first-wins on mixed rows, and per-category independence). All 12 Jest tests in the file pass.

### Implementation for User Story 1

- [x] T013 [P] [US1] Created `includes/Abilities/Integrations/ACF.php`. Compound `is_plugin_active()` check per SEC-002 (`defined( 'ACF_VERSION' ) && function_exists( 'acf_get_setting' )`). Three ability entries (FieldGroup, PostType, Taxonomy) with descriptions.
- [x] T014 [US1] Modified `includes/Main.php`: added `new \AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\ACF();` at the tail of `define_public_hooks()`, immediately after the Core Abilities Bootstrap. Comment block documents the constructor-hooks pattern + DEC-ABILITY-DEFINITION-CTOR-HOOKS.
- [x] T015 [P] [US1] Modified `LibraryPage.js` `groupDefinitions()` to destructure `card_variant` and propagate as `cardVariant` on grouped items. First non-empty value seen wins per T012's mixed-rows test.
- [x] T016 [US1] Modified `LibraryCard.js`: destructures `cardVariant`; when `cardVariant === 'integration'`, RadioControl is suppressed (`enabled && !isIntegration &&`) and `enabled` defaults to `false` for missing config entries.
- [x] T017 [US1] `npm run build` completed successfully (2437 ms). `build/js/ability-library.js` regenerated (asset map emitted). 0 ESLint errors reported during the build.
- [x] T017a **[BUGFIX 2026-07-27]** Integration ON state was silently stripped by sparse-storage in `AcrossAI_Ability_Library_Config::save_config()` (the strip rule assumed default = enabled=true, but integration categories invert that default). Fixed by teaching sparse-storage which slugs are integrations via a new `AcrossAI_Ability_Library_Registry::get_integration_slugs()` public helper, then computing the per-category default. Also removed a private duplicate of the same lookup from the REST controller (DRY). Added 3 PHPUnit regression tests: `test_save_config_preserves_integration_on_state`, `test_save_config_strips_integration_off_default`, `test_save_config_still_strips_regular_on_default`. All 26 tests pass.
- [x] T017b **[US5 — extension pattern, added 2026-07-27]** Documented the third-party extension surface for integration tabs (FR-017, FR-018, User Story 5). Changes: (a) added `public const TAB_GROUP = 'acf'` to `ACF` subclass with `slug()` returning `self::TAB_GROUP`; (b) added a full "How third-party plugins add custom cards to an integration tab" section to the base-class docblock, including the 3-step contract (register category → extend `Ability_Definition` → set `meta.acrossai.tab_group`) and a complete worked example; (c) added the same 3-step example to the `ACF` docblock with the ACF-specific `TAB_GROUP` reference; (d) authored a worked demo mu-plugin at `wp-content/mu-plugins/060-acf-tab-extension-demo.php` that registers two demo categories (`demo-acf-helpers`, `demo-acf-reports`) on `wp_abilities_api_categories_init` and instantiates 3 anonymous `Ability_Definition` subclasses targeting `ACF::TAB_GROUP`; (e) discovered and documented WP core's category-pre-registration requirement (`wp-includes/abilities-api/class-wp-abilities-registry.php` lines 132-146) — abilities whose category is not pre-registered are silently rejected by `wp_register_ability()`, the Library UI card still renders (Registry-driven) but the ability never enters `wp_get_abilities()`. Added 1 PHPUnit test (`test_third_party_ability_definition_can_target_integration_tab`) that exercises the pattern end-to-end: an integration and a third-party `Ability_Definition` both flow through `push_definition()` and produce two rows on the same tab. All 20 tests pass. **This single task covers the entirety of Phase 6.5 (User Story 5) below** — Phase 6.5 exists for organizational parity with the other user story phases; the delivery is captured here.

**Checkpoint**: US1 is fully functional. Manually verify by activating ACF, visiting `/wp-admin/admin.php?page=acrossai-abilities-library`, and confirming the "Acf" tab renders with the toggle off; flip on, reload, verify ACF abilities appear via the "All" tab or via MCP `discover-abilities`.

---

## Phase 4: User Story 2 — Add another third-party integration without new plumbing (Priority: P2)

**Goal**: A future developer can add a second integration by creating one subclass file only. No REST controllers, JavaScript, or admin-menu registration changes are needed.

**Independent Test**: Create a throwaway second subclass in a scratch plugin/mu-plugin, activate it, confirm a new tab appears on the Library page with the same UI shape as the ACF tab and behaves identically for enable/disable.

### Tests for User Story 2

- [x] T018 [P] [US2] Added `test_two_subclasses_produce_two_independent_tabs_and_categories` to the base-class test file. Uses a new `make_mock_with_slug()` helper. Asserts both categories/tab_groups present, both rows carry `card_variant='integration'`, and only the enabled subclass fires `enable_filter()`.

### Implementation for User Story 2

- [x] T019 [US2] Base class file-doc block already covers the five abstract methods, the two constructor-wired hooks, the `is_plugin_active` per-request contract, and points to `AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\ACF` as the reference implementation. Per-method docblocks describe each contract in detail.

**Checkpoint**: US2 is fully functional. Confirm by dropping a `mu-plugins/test-second-integration.php` that instantiates a mock subclass and verifying its tab appears alongside "Acf" on the Library page. Delete the mu-plugin before commit.

---

## Phase 5: User Story 3 — Deactivating the target plugin never breaks the admin page (Priority: P2)

**Goal**: Deactivating ACF (or any other third-party integration target) after the toggle was on leaves the Library page clean — no orphan tab, no PHP notices, and no fatal.

**Independent Test**: Enable ACF and the ACF integration. Deactivate ACF. Load the Library page. Confirm no "Acf" tab, no PHP notices about missing ACF classes in the debug log. Re-activate ACF and confirm the tab returns with the toggle still on.

### Tests for User Story 3

- [x] T020 [P] [US3] Added `test_push_definition_is_byte_identical_when_plugin_inactive` — `assertSame` identity comparison confirms no rows appended. Companion `test_maybe_enable_no_op_when_enabled_but_plugin_inactive` (T010) already covered the filter side.

### Implementation for User Story 3

- [x] T021 [US3] Verified: `is_plugin_active()` is the top-of-method guard in BOTH callbacks. Base class has zero references to any target-plugin symbol. ACF references only appear inside `enable_filter()`, reached only after the gate.
- [x] T022 [US3] Added `test_deactivating_plugin_does_not_mutate_saved_config` — seeds `mock.enabled=true`, invokes both hook callbacks with `is_plugin_active=false`, asserts config still contains `mock.enabled=true`.

**Checkpoint**: US3 is fully functional. Manually verify by toggling ACF integration on, deactivating ACF, reloading the Library page (no tab, no notices), re-activating ACF, reloading (tab returns with toggle on).

---

## Phase 6: User Story 4 — Integration is never on by accident (Priority: P3)

**Goal**: A fresh install of AcrossAI Abilities Manager alongside ACF exposes 0 ACF AI abilities until the admin explicitly toggles the integration on.

**Independent Test**: Fresh install, ACF active, no library page has ever been visited. Query the ability list (MCP `discover-abilities` or WP-CLI). ACF abilities are absent.

### Tests for User Story 4

- [x] T023 [P] [US4] Added 4 `is_integration_enabled()` tests to the base-class test file (kept there rather than the existing Config test file to co-locate all integration coverage): `test_is_integration_enabled_false_when_config_missing`, `..._false_when_entry_disabled`, `..._true_only_when_entry_explicitly_enabled`, `..._false_when_entry_not_array` (defensive against corrupted shapes).
- [x] T024 [P] [US4] Covered by T011 case `cardVariant==="integration" + missing config → toggle defaults OFF (FR-008)` — asserts `computeEnabled('integration', undefined) === false`. No additional test needed.

### Implementation for User Story 4

- [x] T025 [US4] Verification checkpoint: US4 tests all pass (19/19 PHPUnit + 12/12 Jest across the combined run).

**Checkpoint**: US4 is fully functional. Manually verify by uninstalling the plugin, reinstalling it fresh alongside ACF, and running `mcp__claude_ai_acrossai__mcp-adapter-discover-abilities` — no ACF abilities should appear.

---

## Phase 6.5: User Story 5 — Extension pattern for third-party plugins (Priority: P2, added 2026-07-27)

**Goal**: A separate WordPress plugin can add regular ability cards next to an integration's toggle card on the same tab, using only the existing `Ability_Definition` + `tab_group` mechanism — no changes to this plugin required (FR-017, FR-018).

**Independent Test**: Install a small mu-plugin or add-on plugin that (a) registers its own ability category on `wp_abilities_api_categories_init`, (b) extends `Ability_Definition`, and (c) sets `meta.acrossai.tab_group` to the integration's published `TAB_GROUP` constant. Load the integration's tab; the add-on's card renders alongside the integration card; its abilities appear in the Custom Abilities table and in MCP `discover-abilities`.

### Implementation for User Story 5

- [x] T017b **[US5]** Delivery captured in-line above under Phase 3 (T017b was authored ad-hoc on 2026-07-27 before this Phase 6.5 was introduced). See the T017b entry for the full change log: `TAB_GROUP` constant on ACF, comprehensive extension-pattern docblock on the base class + ACF, worked demo mu-plugin at `wp-content/mu-plugins/060-acf-tab-extension-demo.php`, WP-core category-pre-registration requirement documented, and PHPUnit test `test_third_party_ability_definition_can_target_integration_tab` covering the end-to-end pattern.

**Checkpoint**: US5 is fully functional. Manually verify by dropping the demo mu-plugin into `wp-content/mu-plugins/`, reloading the ACF tab, and confirming three cards appear (integration + Demo Acf Helpers + Demo Acf Reports) with the Custom Abilities table showing 12 items instead of 9. Full walkthrough in `quickstart.md` Step 7.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Constitution §VII Definition of Done gates, informational security follow-ups, and documentation.

- [x] T026 [P] `./vendor/bin/phpstan analyse --level=8` on all 6 changed PHP files: exit 0 (clean).
- [x] T027 [P] `./vendor/bin/phpcs --standard=phpcs.xml.dist` on all changed production files: 0 errors (one PSR2 close-brace error fixed inline during the run).
- [x] T028 [P] `npx eslint` on the 3 changed/added JS files: 0 new errors introduced by Feature 060. Pre-existing errors on `LibraryPage.js`/`LibraryCard.js` (`import/no-extraneous-dependencies` for `@wordpress/components` + one `jsdoc/require-returns-description`) confirmed via `git stash` roundtrip — they exist on `main` unchanged.
- [ ] T029 **User-owned** — Plugin Check via `wp-env run cli wp plugin check`. Not run in the automated session; expected clean on the production surface given PHPStan + PHPCS both pass and zero suppressions were added.
- [x] T030 [P] `specs/060-library-third-party-integration-toggles/quickstart.md` authored — 6-step verification script matching plan.md Phase 1, including the SC-060-02 capability-filter test via a temporary mu-plugin.
- [ ] T031 **Deferred to your manual `/speckit-memory-md-capture` invocation** per saved preference. Proposed entries (already drafted in earlier governance summaries):
  - `DEC-ABILITY-DEFINITION-CTOR-HOOKS` (new active decision — constructor-hooks pattern for abstract base classes)
  - `PATTERN-LIBRARY-INTEGRATION-BASE` (new implementation pattern — subclass contract)
- [x] T032 `docs/planning/060-library-third-party-integration-toggles.md` updated with a top-of-file callout for the two implementation divergences (compound `is_plugin_active` check per SEC-002; sparse-storage bugfix from 2026-07-27).
- [ ] T033 **User-owned** — end-to-end quickstart verification against the `wordpress-7-0` Local install. Steps 1-5 confirmed by the user in-session on 2026-07-27. Step 6 (stricter capability filter) not yet exercised.
- [ ] T034 **Optional / not blocking** — SEC-003 `acrossai_integration_toggle_denied` action is wired and documented in the REST controller docblock. Public README documentation is deferred until a second integration ships and the pattern is worth surfacing to integrators.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup. **BLOCKS all user stories** — base class + Registry + Config + REST gate must exist before any US phase begins.
- **User Story 1 (Phase 3)**: Depends on Foundational. MVP scope. Delivering just US1 gives site admins the ACF toggle end-to-end.
- **User Story 2 (Phase 4)**: Depends on Foundational. Can proceed in parallel with US1 if the developer takes the base class as authored and adds only the docblock + test.
- **User Story 3 (Phase 5)**: Depends on Foundational + T006 (SEC-001 try/catch already in the base class).
- **User Story 4 (Phase 6)**: Depends on Foundational (T007) + T016 (JS default-off branch).
- **User Story 5 (Phase 6.5)**: Depends on Foundational (T004 base class + T008 `card_variant` pass-through) + T013 (ACF subclass with `TAB_GROUP` constant published). Docs + one PHPUnit end-to-end test + one worked demo mu-plugin — no new runtime code beyond exposing `ACF::TAB_GROUP`.
- **Polish (Phase 7)**: Depends on all desired user stories being complete. T029 (Plugin Check) depends on all new PHP being present.

### Within Each User Story

- Tests are authored first (T010, T011, T018, T020, T023, T024) and MUST fail on the initial commit.
- Implementation tasks follow.
- Manual verification at each checkpoint before advancing.

### Parallel Opportunities

- **Phase 2 (Foundational)**: T007 (Config helper) and T008 (Registry pass-through) touch different files with no interdependency — parallel-safe.
- **Phase 3 (US1)**: T010 (PHPUnit test), T011/T012 (Jest tests), T013 (ACF subclass), T015 (LibraryPage.js) all touch different files — parallelizable. T014 (Main.php) depends on T013 (subclass must exist to instantiate). T016 (LibraryCard.js) is best done alongside T015. T017 (build) must run after both JS edits.
- **Cross-story**: Once Foundational completes, US1, US2, US3, US4, US5 can be worked on in parallel by different developers.
- **Phase 7 (Polish)**: T026, T027, T028, T030, T033 can all run in parallel.

---

## Parallel Example: User Story 1

```bash
# Author tests first (all parallel — different files):
Task: "T010 PHPUnit base-class contract in tests/phpunit/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base_Test.php"
Task: "T011 Jest cardVariant='integration' branch in tests/jest/ability-library/components/LibraryCard.integration-variant.test.js"
Task: "T012 Jest groupDefinitions cardVariant propagation in the same file as T011"

# Then implementations (T013+T015 parallel; T014 sequential on T013; T016 sequential on file coordination with T015):
Task: "T013 ACF concrete subclass in includes/Abilities/Integrations/ACF.php"
Task: "T015 LibraryPage.js groupDefinitions pass-through"

# Then Main.php + LibraryCard.js:
Task: "T014 Instantiate ACF() at plugins_loaded P20 in includes/Main.php"
Task: "T016 LibraryCard.js cardVariant branch"

# Finally build:
Task: "T017 npm run build"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational (CRITICAL — blocks everything).
3. Complete Phase 3: User Story 1 (tests-first, then ACF subclass, then JS, then build).
4. **STOP and VALIDATE** end-to-end: activate ACF, visit Library page, flip toggle, confirm ACF abilities register.
5. Ship as MVP if desired — US2/US3/US4 add resilience and completeness but the MVP already delivers the primary user value (SC-001).

### Incremental Delivery

1. Setup + Foundational → foundation ready.
2. Add US1 → test independently → demo (MVP).
3. Add US3 (resilience) → test independently → demo — the plugin now survives ACF deactivation cleanly.
4. Add US4 (default-off verification) → automated regression guard for SC-003.
5. Add US2 (documentation + two-subclass test) → future integrations can be added by any dev without cross-story rework.
6. Polish phase (Plugin Check, PHPStan, PHPCS, ESLint, quickstart, memory capture).

### Parallel Team Strategy

With 2 developers post-Foundational:
- **Developer A**: US1 (ACF subclass + JS + Main.php wiring + build).
- **Developer B**: US3 (resilience tests + verification pass) → then US4 (default-off tests).

US2 is single-file (docblock in the base class) and can be closed out by whoever finishes their story first.

---

## Notes

- `[P]` tasks touch different files and have no cross-dependencies.
- `[Story]` label maps each task to its owning user story for traceability. Setup / Foundational / Polish tasks have no story label.
- Tests are included per Constitution §VII and per security-review SEC-001 / SEC-004.
- Verify tests fail before implementing (spec-kit TDD flow).
- Commit after each task or logical group; `speckit-git-commit` hook is optional at each phase boundary.
- **Security-review task mapping**: SEC-001 → T006 + T010(e), SEC-002 → T013, SEC-003 → T009, SEC-004 → T010(f), SEC-005 → T009.
- **Boot Flow Rule deviation**: cite `DEC-ABILITY-DEFINITION-CTOR-HOOKS` (formalised in T031 memory capture) in the base-class docblock (T004).

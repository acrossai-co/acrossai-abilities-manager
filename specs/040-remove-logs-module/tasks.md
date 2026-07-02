---
description: "Task list for Feature 040 — Remove the Logger module"
---

# Tasks: Remove the Logger module

**Input**: Design documents from `/specs/040-remove-logs-module/`
**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [memory-synthesis.md](./memory-synthesis.md), [security-review-plan.md](./security-review-plan.md), [security-constraints.md](./security-constraints.md), [`docs/planning/040-remove-logs-module.md`](../../docs/planning/040-remove-logs-module.md)

**Tests**: No new test tasks — this is a module-removal feature with no new business logic. Five existing PHPUnit test files are deleted (per TASK-7). The test count drops from ~103 to ~85 with all remaining tests passing.

**Organization**: Tasks grouped by user story from spec.md so each story delivers an independently verifiable increment.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: US1 / US2 / US3 mapping to spec.md user stories
- **[SEC-###]** in-task annotation: cross-reference to a security-review-plan.md finding
- **[TASK-#]** in-task annotation: cross-reference to `docs/planning/040-remove-logs-module.md`'s implementation breakdown
- Every task includes exact file paths

## Path Conventions

Single-project WordPress plugin. Source at repo root: `includes/`, `admin/`, `src/`, `uninstall.php`, `webpack.config.js`. Tests at `tests/phpunit/`. Memory at `docs/memory/`. Constitution at `.specify/memory/CONSTITUTION.md`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm the working tree is in a clean, resumable state before touching any code.

- [x] T001 Verify git state: on branch `040-remove-logs-module`, working tree clean or only editor/cache artifacts dirty; run `git status -sb` and confirm branch is based on `main` post-#53 merge (Feature 039 landed).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish baseline metrics + run the Phase 0 grep audit that would catch `BUG-INVENTORY-GREP-MISS` (Feature 034's mid-implementation grep miss). Without a clean grep at Phase 2, later deletions can leave orphaned references that PHPStan L8 catches too late.

**⚠️ CRITICAL**: If T003 or T004 surface an unexpected Logger consumer outside `includes/Modules/Logger/**`, `includes/Utilities/AcrossAI_Logger_*`, `includes/Main.php`, `includes/AcrossAI_Activator.php`, `admin/Main.php`, `admin/Partials/LogsMenu.php`, `admin/Partials/SettingsMenu.php`, `src/js/index.js`, `src/js/components/LogsTable.js`, `src/scss/logs-table.scss`, `webpack.config.js`, `uninstall.php`, or `tests/phpunit/Modules/Logger/**` + `tests/phpunit/Utilities/AcrossAI_Logger_Source_Detector_Test.php`, **halt the feature** and reassess scope.

- [x] T002 Baseline quality gate: `composer phpstan` (level 8) and `composer phpcs` — both MUST be zero-error before Phase 3 starts. Also record baseline `composer test` count (expected ~103) so Phase 6 can verify the reduced-count assertion.
- [x] T003 [P] Pre-deletion inventory grep — per `BUG-INVENTORY-GREP-MISS` counter (Feature 034 precedent): `grep -rEn 'AcrossAI_Ability_Logger|AcrossAI_Ability_Logs_Table|AcrossAI_Ability_Logs_Query|AcrossAI_Ability_Logs_Schema|AcrossAI_Ability_Logs_Row|AcrossAI_Logger_Controller|AcrossAI_Logger_Logs_Controller|LogsMenu|is_logs_page|logger_asset_file' --include='*.php' --include='*.js' --include='*.jsx' includes/ admin/ src/ tests/ acrossai-abilities-manager.php uninstall.php`. Confirm matches ONLY inside the expected file set above.
- [x] T004 [P] Utility-scoped grep for `PATTERN-HELPER-DELETION-GREP-FIRST`: `grep -rEn 'AcrossAI_Logger_Formatter|AcrossAI_Logger_Source_Detector' --include='*.php' --include='*.js' includes/ admin/ src/ tests/ acrossai-abilities-manager.php uninstall.php`. Confirm matches ONLY inside `includes/Modules/Logger/**`, `includes/Utilities/AcrossAI_Logger_*.php`, and their PHPUnit tests. If any cross-module consumer surfaces, KEEP the utility and lift Logger-boundary lessons into its docblock instead of deleting.

**Checkpoint**: Baseline PHPStan L8 + PHPCS green; grep audits confirm the deletion scope matches the spec's assumption of "no cross-module consumers." User story phases can start.

---

## Phase 3: User Story 1 — Admins keep using the plugin after Logger removal (Priority: P1) 🎯 MVP

**Goal**: After the site owner deploys the Feature 040 release, every non-Logger admin feature continues to work — Abilities list + edit + Access Control panel + Library + Add-ons + Settings (two remaining fields). No PHP fatals, no PHP notices, no JavaScript console errors. The Logs submenu and the Log Retention Days Settings field are gone by design.

**Independent Test**: On a WordPress install running the previous plugin version with the Logger active and configured (`_per_page=25`, `_log_retention_days=90`, `_uninstall_delete_data=1`), upgrade to a Feature 040 release build. Walk through the six non-Logger admin surfaces (Abilities, ability edit + save, Access Control panel + save, Library, Add-ons, Settings). All six succeed within five minutes of clicking, with zero PHP notices and zero JavaScript console errors. The two preserved settings return their pre-upgrade values.

### Implementation for User Story 1

- [x] T005 [P] [US1] [TASK-1] Edit `includes/Main.php` lines 300-303 (delete Logs menu registration) AND lines ~412 (delete `AcrossAI_Logger_Controller::instance()` + `add_action('rest_api_init', ...)` pair). Delete file-top `use` imports for `AcrossAI_Logger_Controller` and `LogsMenu` classes. Do not touch lines 400-411 yet — TASK-3 handles those (T007). Edit `admin/Main.php`: delete `$logger_asset_file` property (~lines 80-86), the `require ... build/js/logger.asset.php` block (~lines 122-124), the `is_logs_page`-guarded CSS enqueue (~lines 152-166), the `is_logs_page`-guarded JS enqueue + `wp_localize_script` (~lines 214-235), the `is_logs_page()` helper (~lines 332-334), and the Logs entry in plugin action links (~lines 377-401). Delete `admin/Partials/LogsMenu.php` entirely.
- [x] T006 [P] [US1] [TASK-2] Edit `admin/Partials/SettingsMenu.php`: delete the three related calls (`register_setting` for `acrossai_abilities_log_retention_days` ~line 113; `add_settings_section` for the Log section ~line 165; `add_settings_field` for the Log Retention Days field ~lines 239 + 241). Delete `sanitize_log_retention_days()` and `render_log_retention_days_field()` methods. Keep per-page + uninstall-delete-data fields fully intact.
- [x] T007 [US1] [TASK-3] Edit `includes/Main.php` lines 400-412 (the "Ability Execution Logger" block) — delete atomically. Removes: `AcrossAI_Ability_Logs_Table::instance();`, `$logger = AcrossAI_Ability_Logger::instance();`, and all 6 hook registrations (`mcp_adapter_pre_tool_call` P5, `wp_before_execute_ability` P10, `wp_after_execute_ability` P10, `wp_register_ability_args` P100001, `acrossai_ability_logger_cleanup` P10, `plugins_loaded` schedule_cleanup P20). Delete the file-top `use` imports for `AcrossAI_Ability_Logger` and `AcrossAI_Ability_Logs_Table`.
- [x] T008 [US1] [TASK-5-partial] Edit `includes/AcrossAI_Activator.php`: remove line 14 `use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Table;` and line 43 `( new AcrossAI_Ability_Logs_Table() )->maybe_upgrade();`. Update the docblock at lines 34-35 to drop the `{prefix}acrossai_ability_logs` mention. Two sibling table-create lines remain (`AcrossAI_Abilities_Table`, `RuleTable( AcrossAI_Abilities_Access_Control::TABLE_SLUG )`). **Note**: uninstall.php additions land in T013 (US2 phase).
- [x] T009 [US1] [SEC-001] Post-TASK-3 verification: `grep -rn 'wrap_permission_callback\|VARIADIC-CALLBACK-WRAP\|BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS\|BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE' includes/ admin/ src/` — references MUST be inside memory docblocks only (never inside runtime code). Manually verify on a test install: save an Access Control rule that denies a subscriber → attempt to execute the ability as that subscriber → confirm the AC denial still fires. This is the SEC-001 verification from the plan-time security review.
- [x] T010 [US1] Quality gate for T005-T008: `composer phpstan` (level 8) + `composer phpcs` — zero errors. PHPStan is the strong signal that no dangling `use` statements or class references remain in the edited files. Do not batch with Phase 4/6 gates — verify individually per Constitution §VII.
- [ ] T011 [US1] Manual verification A — non-Logger admin surfaces: navigate through WP-Admin → AcrossAI → Abilities (list renders), Library (list renders), Add-ons (Freemius bootstraps, submenu renders), Settings (exactly two fields: per-page + delete-data — no Log Retention Days). Confirm no PHP fatals + no PHP notices + no JavaScript console errors. Confirm WP-Admin sidebar shows exactly four AcrossAI submenus (Abilities, Library, Add-ons, Settings — no Logs). On the upgraded test install (pre-Feature-040 build had `_per_page=25` + `_uninstall_delete_data=1`), confirm both Settings fields display the pre-upgrade values after upgrade — verifies FR-008 + SC-003 (preserved values retained across the release). Matches spec US1 acceptance scenario 2 + acceptance scenario 1 (preserved settings).
- [ ] T012 [US1] Manual verification B — Abilities module regression check: on a test install, open an ability's edit page. Save an override (persists). Open the Access Control panel, confirm the "Who can access" dropdown lists the full provider list (Feature 039's slug fix is still in effect), save an AC rule (persists via `/wpb-ac/v1/abilities/rules/...`). Pre-upgrade, capture (a) `wp db query "SHOW CREATE TABLE {prefix}acrossai_abilities\G"` + `SHOW CREATE TABLE {prefix}abilities_access_control\G`, (b) `wp option get acrossai_abilities_db_version` + the schema-version marker for AC, (c) legacy row count via `wp db query "SELECT COUNT(*) FROM {prefix}acrossai_ability_logs"`. Post-upgrade, re-capture the same three values. Preserved-table schemas + version markers MUST be byte-identical; legacy row count MUST show zero delta. Verifies FR-006 + FR-009 + SC-004 (upgrade does not read, write, or touch data). Matches spec US1 acceptance scenario 3.

**Checkpoint**: US1 is fully functional as an MVP release. The Logger's admin-visible consumer surface is gone; non-Logger features continue unchanged. Fresh installs still get an orphaned Logger boot code path in the vendor autoload classmap (until T019 physically deletes the module), but no runtime execution — a shippable MVP state.

---

## Phase 4: User Story 2 — Fresh installs never see the Logger surface (Priority: P2)

**Goal**: On a clean WordPress install, activating the Feature 040 release provisions exactly two custom tables (`{prefix}acrossai_abilities`, `{prefix}abilities_access_control`). No `{prefix}acrossai_ability_logs`. No Logs submenu. No log-retention Settings field. No logger JS bundle. The plugin is functionally identical to what it would look like if the Logger had never existed.

**Independent Test**: On a clean WordPress install (never had this plugin), install and activate the Feature 040 release. Inspect: (a) `wp db query "SHOW TABLES LIKE '%ability_logs%'"` returns zero rows; (b) `wp option get acrossai_ability_logs_db_version` returns empty; (c) WP-Admin sidebar shows AcrossAI → Abilities, Library, Add-ons, Settings only. Set an AC rule on the first ability created → confirm persists. Additionally, opt into delete-data at uninstall → confirm the removal is clean.

### Implementation for User Story 2

- [x] T013 [US2] [TASK-5-partial] Edit `uninstall.php` — inside the existing `if ( $acrossai_delete_data ) { ... }` gate, immediately after the existing `_abilities_access_control` table drop from Feature 039, add three new lines: (a) `$acrossai_logs_table = $wpdb->prefix . 'acrossai_ability_logs';`, (b) `$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_logs_table ) );` with the standard `phpcs:ignore` comment for the direct query line, (c) `\delete_option( 'acrossai_ability_logs_db_version' );`. Then add the Action Scheduler cleanup block: `if ( function_exists( 'as_unschedule_all_actions' ) ) { as_unschedule_all_actions( 'acrossai_ability_logger_cleanup' ); }`. Keep the existing `\delete_option( 'acrossai_abilities_log_retention_days' );` line unchanged (already inside the gate).
- [x] T014 [US2] [SEC-002] Diff verification: `git diff HEAD~1 -- uninstall.php` — all additions MUST appear between the opening `if ( $acrossai_delete_data )` brace and its matching closing brace. Any addition outside the gate is a `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE` regression. This is the SEC-002 verification from the plan-time security review.
- [ ] T015 [US2] Manual verification A — fresh activation: on a clean WP install (no prior version of this plugin), activate the Feature 040 release. Immediately run `wp db query "SHOW TABLES LIKE '%ability_logs%'"` — MUST return zero rows. Run `wp option get acrossai_ability_logs_db_version` — MUST return empty. Confirm WP-Admin sidebar shows exactly four AcrossAI submenus. Additionally hit the removed REST endpoint with `wp eval 'echo wp_remote_retrieve_response_code( wp_remote_get( rest_url( "acrossai-abilities-log/v1/logger/logs" ) ) );'` — MUST return `404`. Verifies FR-004 runtime behavior beyond the T017 grep. Matches spec US2 acceptance scenario 1 + FR-001 + FR-004.
- [ ] T016 [US2] Manual verification B — opt-in uninstall on an upgraded install with pre-existing logs data: on a test install that has the pre-Feature-040 Logger's data (mock: manually re-create `{prefix}acrossai_ability_logs` with a row + set `acrossai_ability_logs_db_version = '20260520'` + set `acrossai_abilities_log_retention_days = 90` + schedule an AS action on `acrossai_ability_logger_cleanup`), then activate Feature 040 with `_uninstall_delete_data = 1`, then deactivate + delete the plugin via WP-Admin. Confirm: (a) `SHOW TABLES LIKE '%ability_logs%'` returns zero rows; (b) all three options are deleted; (c) `wp action-scheduler list --hook=acrossai_ability_logger_cleanup` returns zero pending actions. Matches SC-005.

**Checkpoint**: US2 is fully functional. Fresh installs get zero Logger artifacts. Opt-in uninstall drops legacy Logger data cleanly from upgraded installs.

---

## Phase 5: User Story 3 — Companion plugin can consume ability-execution hooks (Priority: P3)

**Goal**: Confirm that after Feature 040, this plugin registers zero callbacks on the four upstream ability-execution hooks (`wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `mcp_adapter_pre_tool_call`). Any future companion plugin (or any third-party consumer) can hook these events at any priority without interference from this plugin.

**Independent Test**: Verification-only — no code produced. Grep-based static verification + one live-hook test.

### Implementation for User Story 3

**No new code needed.** US3 is delivered by the hook-registration removals in T007 (TASK-3). This phase is verification-only.

- [x] T017 [P] [US3] [FR-005] Grep verification: `grep -rEn 'wp_before_execute_ability|wp_after_execute_ability|wp_register_ability_args|mcp_adapter_pre_tool_call' --include='*.php' includes/ admin/ acrossai-abilities-manager.php uninstall.php` — MUST return zero matches. This proves the abilities-manager after Feature 040 does not intercept, filter, or wrap any of the four upstream ability-execution hooks. Matches spec US3 acceptance scenario 2 + FR-005 + FR-010.
- [ ] T018 [P] [US3] [FR-010] Live-hook test: on a test WordPress install, register a minimal callback: `add_action( 'wp_after_execute_ability', function( $ability, $args, $result ) { update_option( 'acrossai_test_040_us3', 'fired' ); }, 10, 3 );` (place in a mu-plugin or test file). Execute any ability. Confirm `wp option get acrossai_test_040_us3` returns `fired` — the abilities-manager did not intercept the callback. Matches spec US3 acceptance scenario 1.

**Checkpoint**: US3 verified. Future companion plugin authors have the extensibility contract they need — no plugin-side coordination required.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Delete the now-orphaned Logger module files (all consumers unwired in Phase 3), delete the front-end assets, clean up tests + `phpunit.xml.dist`, update release notes + memory + Constitution + housekeeping, run final quality gates.

- [x] T019 [TASK-4] Grep confirmation (T004 result stands) → delete `includes/Modules/Logger/` directory in its entirety (8 PHP files: `AcrossAI_Ability_Logger.php`, `Database/AcrossAI_Ability_Logs_Table.php`, `Database/AcrossAI_Ability_Logs_Schema.php`, `Database/AcrossAI_Ability_Logs_Row.php`, `Database/AcrossAI_Ability_Logs_Query.php`, `Rest/AcrossAI_Logger_Controller.php`, `Rest/AcrossAI_Logger_Logs_Controller.php`; plus the `Database/`, `Rest/`, and `Logger/` directories themselves). Delete `includes/Utilities/AcrossAI_Logger_Formatter.php` + `includes/Utilities/AcrossAI_Logger_Source_Detector.php`. Run `composer dump-autoload` — regenerated PSR-4 classmap MUST drop all Logger entries.
- [x] T020 [TASK-6] Delete `src/js/index.js`, `src/js/components/LogsTable.js`, `src/scss/logs-table.scss`. Edit `webpack.config.js` (lines 79-81 area) — remove `'js/logger': ...` and `'css/logger': ...` entries. Run `npm run build` — succeeds. Verify: `find build/ -iname 'logger*'` — MUST return zero results. If any stale files remain (webpack doesn't auto-delete removed entries), manually delete: `build/js/logger.js`, `build/js/logger.asset.php`, `build/js/logger.css`, `build/js/logger-rtl.css`, `build/css/logger.css`, `build/css/logger.asset.php`, `build/css/logger-rtl.css`.
- [x] T021 [TASK-7] Delete 5 PHPUnit test files: `tests/phpunit/Modules/Logger/AcrossAI_Ability_Logger_Test.php`, `tests/phpunit/Modules/Logger/Database/AcrossAI_Ability_Logs_Table_Test.php`, `tests/phpunit/Modules/Logger/Database/AcrossAI_Logger_Query_Test.php`, `tests/phpunit/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller_Test.php`, `tests/phpunit/Utilities/AcrossAI_Logger_Source_Detector_Test.php`. Delete `tests/phpunit/Modules/Logger/` directory + subdirs. Edit `phpunit.xml.dist`: remove every `<file>` entry that references one of those paths (per `BUG-PHPUNIT-AUTODISCOVERY-PREFIX` — stale entries hard-error). Do NOT rename or remove the surviving suites (abilities-unit / library-unit / includes-unit).
- [x] T022 [P] [TASK-8a] [SEC-003] [SEC-004] Edit `README.txt`: extend the Unreleased changelog with a bullet announcing the Logger removal. Add a corresponding Upgrade Notice line. MUST include: (a) manual cleanup SQL (`DROP TABLE {prefix}acrossai_ability_logs; DELETE FROM {prefix}options WHERE option_name IN ('acrossai_abilities_log_retention_days', 'acrossai_ability_logs_db_version');`); (b) explicit mention that external integrations polling `/wp-json/acrossai-abilities-log/v1/logger/logs` will receive 404; (c) explicit mention that bookmarks to `wp-admin/admin.php?page=acrossai-abilities-logs` will receive the standard "page not found" admin response; (d) per SEC-003 recommendation, an explicit "Ability execution denials (previously logged to the Logs page) are no longer recorded by this plugin. If you rely on this signal, install a compatible logging plugin or hook `wp_after_execute_ability` directly." line.
- [x] T023 [P] [TASK-8b-e] Memory hygiene (per `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`): (a) In `docs/memory/DECISIONS.md`, mark `DEC-HOOK-PARAM-EXTRACTION`, `DEC-DURATION-CALC-TIMESTAMPS`, `DEC-VARIADIC-CALLBACK-WRAP`, `DEC-LOGGER-NAMESPACE-MIGRATION` as **Superseded (Feature 040)** with entry bodies kept intact. (b) In `docs/memory/ARCHITECTURE.md`, annotate `PATTERN-LOGGER-OPTION-FEED-FILTER`, `PATTERN-STAGE-NAMING`, `PATTERN-FEATURE-ASSET-SEPARATION` with forward-pointer notes ("Original Logger consumer removed in Feature 040; the pattern still applies to..."). Also extend `ARCH-ADV-001`'s existing "Logger boot() removed in Feature 017" annotation to note the full-module removal in Feature 040 (deviation stays Active — still applies to Override Processor). (c) Add a Feature 040 WORKLOG milestone to `docs/memory/WORKLOG.md` following the Feature 039 format (Why durable / Future mistake prevented / Evidence / Where to look) — highlight the durable lesson: "module decommission without replacement — Feature 040 is the canonical example of the 'no consumers outside boot wiring' case, distinct from Feature 012 which had a same-plugin replacement." (d) Update `docs/memory/INDEX.md`: change Status column to `Superseded (Feature 040)` for the four decisions above; add a new 2026-07-01 WORKLOG row for Feature 040; add rows for the four Feature 040 spec/plan/tasks/security-review artifacts.
- [x] T024 [P] [TASK-8f] Constitution amendment (per §Governance PATCH bump v1.4.7 → v1.4.8): edit `.specify/memory/CONSTITUTION.md`: (a) add a SYNC IMPACT REPORT HTML comment block at the top per `PATTERN-CONSTITUTION-SYNC-REPORT`, describing version change, modified sections, rationale, and templates-reviewed checklist. (b) In §I Modular Architecture, change "six active feature areas (Per-User Access Control, MCP Server Management, Custom Ability Registration, WebMCP Integration, Ability Execution Logging, AbilityAPI Registration Management)" to "five active feature areas (Per-User Access Control, MCP Server Management, Custom Ability Registration, WebMCP Integration, AbilityAPI Registration Management)". (c) In Architecture & UI Standards → Directory Layout, remove `Logger/` from the `Modules/` enumeration. (d) Update the top-of-file `**Version**` line to `1.4.8`. (e) Update the `**Last Amended**` line to `2026-07-01`.
- [x] T025 [P] [TASK-8g] Update `CLAUDE.md`: change the SPECKIT plan reference inside the `<!-- SPECKIT START --> ... <!-- SPECKIT END -->` markers from `specs/039-composer-package-updates/plan.md` to `specs/040-remove-logs-module/plan.md`.
- [x] T026 Final full-repo grep audit — **merge blocker** (per `BUG-INVENTORY-GREP-MISS` counter): `grep -rEn 'Logger|acrossai_ability_logs|acrossai-abilities-log|acrossai_ability_logger|LogsTable|LogsMenu|is_logs_page|logger_asset_file|log_retention' --include='*.php' --include='*.js' --include='*.jsx' --include='*.json' includes/ admin/ src/ tests/ acrossai-abilities-manager.php uninstall.php webpack.config.js`. MUST return **zero matches**. Non-zero indicates incomplete decommission — inspect and clean before merge. (Grepping `docs/memory/**` and `specs/**` is expected to still show references — those are documentation of the removal, not runtime code, and are out of scope for this audit.)
- [x] T027 [P] Plugin-wide PHPStan L8: `composer phpstan` — zero errors across the entire plugin. Also run `git diff main..HEAD -- composer.json` and confirm the `require` block shows zero net additions and zero net removals (comments/formatting-only changes are acceptable). Verifies SC-007.
- [x] T028 [P] Plugin-wide PHPCS: `composer phpcs` — zero errors and zero warnings across the entire plugin. File count MUST drop from 56 to approximately 46 (~10 files deleted).
- [x] T029 [P] JS package validator + build: `npm run validate-packages` — clean. `npm run build` — succeeds. Confirms Constitution §VII gating.
- [x] T030 [P] Full PHPUnit suite: `composer test` — all remaining tests pass. Test count MUST drop from ~103 to ~85 (~18 tests removed across the 5 deleted test files) with zero failures.
- [ ] T031 [P] [SC-006] Missing-vendor fail-open regression check: on a test install, rename `vendor/` to `vendor.bak/`, visit `wp-admin/` as a `manage_options` user. Confirm the Feature 038 boot-resilience admin notice ("AcrossAI Abilities Manager: Composer dependencies not loaded…") fires and no PHP fatal reaches the browser within one page load. Restore `vendor/`. This is a one-request check — no new code. Preserves the `DEC-FAIL-OPEN-NOTICE` guarantee that Feature 040 introduced no regression to the missing-vendor path.

**Final Checkpoint**: All quality gates green; full-repo grep audit returns zero matches; memory + Constitution + release notes all updated. Feature is Definition-of-Done complete per Constitution §VII and ready for PR to `main`.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Phase 1. **BLOCKS all user stories** (T003/T004 grep audits MUST pass before any deletion; T002 baseline PHPStan/PHPCS establishes the pre-deletion truth).
- **User Story 1 (Phase 3)**: Depends on Phase 2 completion. **MVP scope.** After Phase 3, admins see the Logger's admin-visible surface gone but the module files still exist on disk (physically deleted in T019).
- **User Story 2 (Phase 4)**: Depends on Phase 2 completion. T013 (uninstall additions) is independent of Phase 3 — could technically run in parallel with US1, but sequential ordering is cleaner for reviewability.
- **User Story 3 (Phase 5)**: Depends on Phase 3 completion (needs T007 to have removed the hook registrations). Verification-only.
- **Polish (Phase 6)**: T019/T020/T021 depend on Phase 3-4 code being done. T022/T023/T024/T025 have no code dependencies (parallel). T026 is the final grep audit merge blocker. T027-T030 quality gates run after all code phases complete.

### User Story Dependencies

- **US1 (P1)**: Requires Phase 2. Delivers the primary upgrade path. **MVP-shippable in isolation** (though ideally paired with T019 for physical file deletion; without T019, the module files are dead code sitting on disk).
- **US2 (P2)**: Requires Phase 2. Delivers fresh-install correctness. Independent of US1's manual verifications.
- **US3 (P3)**: Requires T007 (US1's hook-registration removal). Verification-only.

### Within Each User Story

- **US1**: T005 + T006 are parallel (different files). T007 must follow T005 in `includes/Main.php` because they touch the same file (mergeable but cleaner sequentially). T008 modifies a different file, parallel with T005-T007. T009 (SEC-001 verify) depends on T007 being complete. T010 quality gate depends on T005-T008. T011 + T012 manual verifications depend on T010 succeeding.
- **US2**: T013 sequential (one file). T014 depends on T013. T015 + T016 manual, order flexible.
- **US3**: T017 + T018 independent, parallel.

### Parallel Opportunities

- **Phase 2**: T003 + T004 (two independent greps) can run in parallel after T002.
- **Phase 3**: T005 + T006 + T008 (three different files) can run in parallel; T007 modifies the same file as T005 so sequential.
- **Phase 5**: T017 + T018 fully parallel.
- **Phase 6**: T022 + T023 + T024 + T025 (four independent files/subsystems: README.txt, docs/memory/*, CONSTITUTION.md, CLAUDE.md) can all run in parallel. T027 + T028 + T029 + T030 (four quality gates against fully-implemented code) can all run in parallel.

---

## Parallel Example: User Story 1

```bash
# After T004 checkpoint (Phase 2 complete), launch three US1 implementation tasks in parallel:
Task T005: "Edit includes/Main.php Logs menu + REST controller registration; edit admin/Main.php logger asset wiring; delete admin/Partials/LogsMenu.php"
Task T006: "Edit admin/Partials/SettingsMenu.php — remove log_retention_days field"
Task T008: "Edit includes/AcrossAI_Activator.php — remove Logger table create"
# T007 (Main.php Logger boot block) must run after T005 because they touch the same file.
```

## Parallel Example: Phase 6 Polish

```bash
# After code phases complete, launch four housekeeping updates in parallel:
Task T022: "README.txt Unreleased changelog + Upgrade Notice"
Task T023: "Memory hygiene — Superseded decisions + annotated patterns + WORKLOG + INDEX rows"
Task T024: "Constitution amendment — PATCH bump v1.4.7 → v1.4.8"
Task T025: "CLAUDE.md SPECKIT plan reference update"

# Then run four quality gates in parallel:
Task T027: "composer phpstan (plugin-wide)"
Task T028: "composer phpcs (plugin-wide)"
Task T029: "npm run validate-packages + npm run build"
Task T030: "composer test (PHPUnit)"
```

---

## Implementation Strategy

### MVP First (User Story 1 + T019 Only)

1. Complete Phase 1: Setup (T001).
2. Complete Phase 2: Foundational (T002 baseline + T003/T004 grep audits). **Do not skip** — they are the `BUG-INVENTORY-GREP-MISS` counter and merge-blocker foundation.
3. Complete Phase 3: User Story 1 (T005-T012).
4. Run T019 (physical file deletion) — this is technically Phase 6 but paired with US1 makes the MVP more complete: the Logger's admin surface disappears (US1) AND the module files leave the disk (T019).
5. Run T027 (PHPStan) + T028 (PHPCS) to confirm the reduced-file state compiles cleanly.
6. **STOP and VALIDATE**: T011 + T012 manual walkthrough proves the primary post-removal experience works.
7. Deploy the MVP release. Fresh installs still get an orphaned uninstall.php (won't drop the legacy Logger table if the admin didn't upgrade a pre-Feature-040 install — but that's OK for MVP; the release-note SQL is the fallback).

### Incremental Delivery

1. **Setup + Foundational** → gate cleared for all downstream work.
2. **US1 + T019 + T027/T028** → ship the MVP release. Admin surface change is complete.
3. **US2 (T013-T016)** → ship uninstall cleanup. Fresh installs are fully correct.
4. **US3 (T017-T018)** → verification only. No release action.
5. **T020 (JS/webpack) + T021 (tests) + T022-T025 (docs/memory/Constitution) + T026 (grep audit) + T029/T030 (validate + PHPUnit)** → final polish. Ship the complete release.

### Solo-Developer Strategy

Sequential path: **Phase 1 → Phase 2 → US1 → US2 → US3 → Polish**. Within US1, do T005 → T007 (same file, sequential) → T006 + T008 in parallel tabs → T009 verification → T010 quality gate → T011/T012 in a browser session. Within Polish, run T019 → T020 → T021 → T022/T023/T024/T025 in one editing pass → T026 grep → T027-T030 all at once in the terminal.

---

## Task Count Summary

| Phase | Tasks | Parallel | User Story | Focus |
|---|---|---|---|---|
| 1: Setup | 1 (T001) | 0 | — | Working-tree + branch check |
| 2: Foundational | 3 (T002-T004) | 2 (T003, T004) | — | Baseline metrics + Phase 0 grep audit |
| 3: US1 (MVP) | 8 (T005-T012) | 2 (T005+T006+T008 tri-parallel; T007 sequential on same file as T005) | US1 | Consumer-side removal + SEC-001 verify + admin walkthrough |
| 4: US2 | 4 (T013-T016) | 0 (sequential due to shared uninstall.php + verification ordering) | US2 | Uninstall additions + SEC-002 diff + fresh-install verify + opt-in uninstall verify |
| 5: US3 | 2 (T017-T018) | 2 (T017 + T018) | US3 | FR-005 grep + FR-010 live-hook test |
| 6: Polish | 13 (T019-T031) | 9 (T022+T023+T024+T025 doc updates; T027+T028+T029+T030 quality gates; T031 fail-open check) | — | Module + JS + tests deletion; release notes; memory; Constitution amendment; grep audit; quality gates; fail-open regression check |
| **Total** | **31** | **15 parallelizable** | — | — |

### Security-Review Traceability

| SEC Finding | Blocking? | Tasks |
|---|---|---|
| SEC-001 (INFO — P100001 wrapper removal doesn't re-expose historical permission-bypass bugs) | Advisory | T009 |
| SEC-002 (INFO — TASK-5 uninstall additions stay inside opt-in gate) | Advisory | T014 |
| SEC-003 (INFO — observability reduction disclosure in release notes) | Advisory | T022 |
| SEC-004 (INFO — external REST clients + admin bookmarks 404 disclosure) | Advisory | T022 |

### Planning-Doc Traceability

| TASK from `docs/planning/040-remove-logs-module.md` | Tasks.md IDs |
|---|---|
| TASK-1 (REST + Logs admin surface) | T005 |
| TASK-2 (Settings API field removal) | T006 |
| TASK-3 (Main.php boot wiring removal) | T007 |
| TASK-4 (Logger module + utility deletion) | T019 (grep + delete, grep-first was T004) |
| TASK-5 (Activator + uninstall cleanup) | T008 (activator part) + T013 (uninstall part) |
| TASK-6 (Front-end JS/SCSS/build cleanup) | T020 |
| TASK-7 (Tests + xml.dist cleanup) | T021 |
| TASK-8 (Release notes + memory hygiene + Constitution amendment) | T022 (release notes) + T023 (memory) + T024 (Constitution) + T025 (CLAUDE.md) |

---

## Notes

- No new test tasks generated — Feature 040 removes 5 test files without adding any. T030 verifies the reduced test count.
- Constitution amendment (T024) is not a deviation but a mandatory PATCH-level correction — the codebase and Constitution stay in sync per §Governance amendment procedure.
- Every code-editing task specifies exact file paths + line ranges. Every verification task specifies exact commands or navigation paths.
- Constitution §VII per-task gating is enforced by T010 (US1), T027-T030 (Polish). Do NOT batch quality gates until the individually-scoped US1 gate passes.
- The MVP release is safely shippable after Phase 3 + T019 alone; Phases 4-6 harden and clean up without changing the removal-behavior admins observe.
- T023's memory hygiene edits 4 memory files in one editing pass — safe to batch since memory files don't affect PHPStan/PHPCS. If future memory-md tooling formalizes per-entry approval, T023 splits into sub-tasks; for now, it's one editing session.
- T026 is a **hard merge blocker**. Non-zero result MUST halt the merge and trigger cleanup.

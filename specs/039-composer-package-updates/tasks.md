---
description: "Task list for Feature 039 — Composer Package Updates (wpb-access-control v2 + main-menu absorbs addons-page)"
---

# Tasks: Composer Package Updates — wpb-access-control v2 + main-menu absorbs addons-page

**Input**: Design documents from `/specs/039-composer-package-updates/`
**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [memory-synthesis.md](./memory-synthesis.md), [security-review-plan.md](./security-review-plan.md), [`docs/planning/039-composer-package-updates.md`](../../docs/planning/039-composer-package-updates.md)

**Tests**: No new test tasks — this is a dependency-refresh feature with no new business logic. One existing PHPUnit test (`AccessControlBootstrapTest.php`) needs a signature update; that update is a US1 task, not a new test.

**Organization**: Tasks are grouped by user story from spec.md so each story can be implemented and demoed independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: US1 / US2 / US3 mapping to spec.md user stories
- **[SEC-###]** in-task annotation: cross-reference to a security-review-plan.md finding
- Every task includes exact file paths

## Path Conventions

Single-project WordPress plugin. Source at repo root: `composer.json`, `includes/`, `admin/`, `uninstall.php`. Tests at `tests/phpunit/`. Vendor at `vendor/` (never edited by hand).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm the working tree is in a clean, resumable state before touching composer or code.

- [x] T001 Verify git state: on branch `039-composer-package-updates`, working tree clean or only `.claude/settings.local.json` + `.phpunit.cache/` dirty; run `git status -sb` and confirm before proceeding

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Update the composer manifest, regenerate the lockfile, and run the five-point post-upgrade security verification gate. Without this phase, TASK-3's manager constructor throws (empty `$table_slug` fails the `^[a-z0-9_]{1,32}$` validation) and TASK-2's AddonsPage constructor mismatches — no user story can start.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete. If any of T004/T005/T006 reports an upstream regression, **halt the feature** and coordinate with upstream before proceeding.

- [x] T002 Update `composer.json` `require`: bump `wpboilerplate/wpb-access-control` from `^1.6.0` to `^2.0.0`; bump `acrossai-co/main-menu` from `^0.0.4` to `^0.0.7`; remove the `acrossai-co/addons-page: ^0.0.19` line entirely. Leave the `repositories` VCS entry for wpb-access-control untouched.
- [x] T003 Regenerate lockfile: run `composer update --with-all-dependencies wpboilerplate/wpb-access-control acrossai-co/main-menu acrossai-co/addons-page` from repo root. Confirm lockfile: (a) wpb-access-control at `v2.0.0`, (b) main-menu at `v0.0.7`, (c) addons-page absent from `packages`, (d) `freemius/wordpress-sdk ^2.0` present transitively. Commit `composer.json` + `composer.lock` together.
- [x] T004 [P] [SEC-002] Multisite-isolation verification: `grep -nE '\\$global\\s*=' vendor/wpboilerplate/wpb-access-control/src/Database/Rule/RuleTable.php` — MUST return zero matches OR only `$global = false`. If `$global = true` appears, HALT and file an upstream issue.
- [x] T005 [P] [SEC-003] Strict-comparison verification: `grep -nE '(user_has_access|access_control_key|access_control_value).*[^=!]==[^=]' vendor/wpboilerplate/wpb-access-control/src/AccessControlManager.php` — MUST return zero matches (any loose comparison in the access hierarchy is a blocker per SEC-04 + BUG-LOOSE-COMPARISON-BYPASS).
- [x] T006 [P] [SEC-004] REST permission_callback return-type audit: `grep -rnE 'return .*new WP_REST_Response' vendor/wpboilerplate/wpb-access-control/src/Rest/` — MUST return zero matches (any `WP_REST_Response` returned from a permission_callback is BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE regression).
- [x] T007 [P] Autoloader-classmap verification: `grep -n 'AcrossAI_Addon.AddonsPage' vendor/composer/jetpack_autoload_classmap.php` — MUST map to a path under `vendor/acrossai-co/main-menu/src/Addons/`, NOT `vendor/acrossai-co/addons-page/`.
- [x] T008 Baseline quality gate: `composer phpstan` (level 8) and `composer phpcs` — both MUST be zero-error before Phase 3 starts. This baseline lets Phase 3 tasks detect regressions they introduce.

**Checkpoint**: composer.lock reflects the three package changes; four vendor-code verification greps all pass; PHPStan L8 + PHPCS baseline are green. User stories can start.

---

## Phase 3: User Story 1 — Admins keep using the plugin after the dependency update (Priority: P1) 🎯 MVP

**Goal**: After deploying the release built from this branch, WP-Admin → AcrossAI → Add-ons renders, the per-ability Access Control React panel loads, and saving a rule persists — all without PHP fatals, PHP notices, or JavaScript console errors.

**Independent Test**: On a WordPress install running the previous plugin version, replace the plugin folder with a build from HEAD after this phase completes. Visit the three surfaces (Add-ons submenu, one ability's edit page, save an AC rule). All three succeed on first attempt.

### Implementation for User Story 1

- [x] T009 [US1] Edit `includes/Main.php` lines 316-348: drop the first positional `'acrossai'` argument from `new \AcrossAI_Addon\AddonsPage(...)` at line 324. Keep the `class_exists( \AcrossAI_Addon\AddonsPage::class )` guard, the `try`/`catch ( \Throwable $e )` wrapper, and the entire `admin_notices` fallback closure (lines 322-347) byte-for-byte unchanged except for the constructor arg list. Update the leading comment at lines 316-321 to reference Feature 039 and cite that AddonsPage now ships from `acrossai-co/main-menu` (class name preserved by upstream). Continue to cite `DEC-EXTERNAL-PACKAGE-HOOK-CTOR`.
- [x] T010 [P] [US1] Edit `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php`: add `const TABLE_SLUG = 'abilities';` immediately after `const PROVIDERS_FILTER = ...;` (around line 36). In `boot_manager()` (line 74) change `new AccessControlManager( self::PROVIDERS_FILTER )` to `new AccessControlManager( self::PROVIDERS_FILTER, self::TABLE_SLUG )`. Do not modify the singleton pattern, the `is_available()` guard, `get_manager()`, `register_rest_api()`, or `maybe_show_library_notice`.
- [x] T011 [US1] [SEC-001] Byte-level diff verification: `git diff HEAD~1 -- includes/Main.php | awk '/^@@/{range=$0} /^[+-]/ && !/^[+-][+-][+-]/{print range, $0}'` — the only in-range changes for lines 322-347 MUST be inside the argument list of `new \AcrossAI_Addon\AddonsPage(...)`. If any other line changed inside the closure body (capability gate, `esc_html`, closure captures), revert and redo T009 without touching the closure. This is the SEC-001 mitigation from the plan-time security review.
- [x] T012 [P] [US1] Update `tests/phpunit/sitewide/AccessControlBootstrapTest.php` if it asserts the single-argument `AccessControlManager` constructor form. Assert both positional args now (`self::PROVIDERS_FILTER, self::TABLE_SLUG`). Add a one-line assertion that `AcrossAI_Abilities_Access_Control::TABLE_SLUG` matches `^[a-z0-9_]{1,32}$` (SEC-review REC-03 optional harden).
- [x] T013 [US1] Quality gate for T009 + T010 + T012: `composer phpstan` (level 8) + `composer phpcs` — zero errors on `includes/Main.php`, `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php`, and `tests/phpunit/sitewide/AccessControlBootstrapTest.php`. Do not batch with Phase 4/6 gates — verify individually per Constitution §VII.
- [ ] T014 [US1] Manual verification A — Add-ons submenu: navigate to WP-Admin → AcrossAI → Add-ons. Confirm the page renders, Freemius bootstraps (no error notices), the "Add-ons" submenu appears in the sidebar under the AcrossAI parent menu (matches spec US1 acceptance scenario 1).
- [ ] T015 [US1] Manual verification B — per-ability Access Control panel: navigate to any ability's edit page. Confirm the Access Control panel mounts, the "Who can access" dropdown lists the available providers (WP Role, WP User, WP Capability), and a save round-trips through `/wp-json/wpb-ac/v1/abilities/rules/...` (verify in browser DevTools Network tab) — no console errors. Matches spec US1 acceptance scenarios 2 + 3.

**Checkpoint**: US1 is fully functional. The plugin can be shipped as an MVP release covering the primary upgrade scenario. All post-upgrade surfaces that admins interact with are verified working.

---

## Phase 4: User Story 2 — Fresh install creates the per-consumer table on activation (Priority: P2)

**Goal**: On a clean WordPress install with no prior version of this plugin, activating the plugin provisions the plugin's dedicated access-control storage in the same step as its other custom tables — within seconds, without requiring a subsequent admin request to warm anything up.

**Independent Test**: On a WordPress install with the plugin never previously activated, click Activate. Within five seconds, `wp db query "SHOW TABLES LIKE '%abilities_access_control%'"` returns exactly one row (the new per-consumer table). Setting a rule on the first ability created succeeds on the first attempt.

### Implementation for User Story 2

- [x] T016 [US2] Edit `includes/AcrossAI_Activator.php`: add `use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control;` alongside the existing imports (around line 14). Replace the line-43 call `( new RuleTable() )->maybe_upgrade();` with `( new RuleTable( AcrossAI_Abilities_Access_Control::TABLE_SLUG ) )->maybe_upgrade();` — the constant lives in one place (US1's T010). Update the docblock at lines 34-35 to say `{prefix}abilities_access_control` instead of `{prefix}wpb_access_control`. Do not call `dbDelta()` directly; do not add legacy-table cleanup.
- [x] T017 [US2] Quality gate for T016: `composer phpstan` (level 8) + `composer phpcs` — zero errors on `includes/AcrossAI_Activator.php`.
- [ ] T018 [US2] Manual verification A — fresh activation: on a clean WP install (no prior version of this plugin), activate the plugin. Immediately run `wp db query "SHOW TABLES LIKE '%abilities_access_control%'"` — MUST return one row: `{prefix}abilities_access_control`. Matches spec US2 acceptance scenario 1.
- [ ] T019 [US2] Manual verification B — schema-version option: after T018, run `wp option get wpb_ac_abilities_db_version` — MUST return the schema version string (e.g. `202605120001`). This confirms BerlinDB's `maybe_upgrade()` completed synchronously during activation, not deferred to `admin_init`. Also try setting an AC rule on a fresh ability — MUST succeed on first attempt (matches spec US2 acceptance scenario 2).

**Checkpoint**: US2 is fully functional. Fresh installs get the correct storage layout without any post-activation warm-up window.

---

## Phase 5: User Story 3 — Multi-plugin coexistence: storage isolation is provable (Priority: P3)

**Goal**: Confirm that after the upgrade, this plugin's access-control storage embeds the plugin-specific slug (`abilities`) in every observable identifier — table name, REST namespace, option key, cache group. A hypothetical sibling AcrossAI plugin using a different slug would land in a distinct, non-overlapping storage area.

**Independent Test**: Direct database and REST inspection. No second consumer plugin required — the isolation is proven by the fact that every generated identifier is slug-parameterized.

### Implementation for User Story 3

**No new code needed.** US3 is delivered entirely by the slug propagation implemented in T010 (manager) + T016 (activator). This phase is verification-only.

- [ ] T020 [US3] Database-level isolation verification: run `wp db query "SHOW TABLES LIKE '%_access_control'"`. MUST show `{prefix}abilities_access_control` (and possibly the orphaned legacy `{prefix}wpb_access_control` on existing installs — that's expected). MUST NOT show any table with the exact name `{prefix}wpb_access_control` being *created by this plugin*. Matches spec US3 acceptance scenario 1.
- [ ] T021 [US3] REST-level isolation verification: `curl -H "X-WP-Nonce: <nonce>" 'https://<site>/wp-json/wpb-ac/v1/abilities/providers'` MUST return 200 with the provider list JSON. `curl -H "X-WP-Nonce: <nonce>" 'https://<site>/wp-json/wpb-ac/v1/providers'` (no slug segment) MUST return 404. This proves the REST namespace is slug-parameterized and legacy non-slug routes are no longer registered by v2. Matches spec US3 acceptance scenario 2.

**Checkpoint**: US3 is verified. A future second AcrossAI consumer plugin embedding wpb-access-control v2 with a different slug will land in a wholly distinct set of identifiers. Storage isolation is provable.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Uninstall cleanup path (FR-006), release-note communication (SEC-005), plugin-wide quality gates, and housekeeping updates.

- [x] T022 Edit `uninstall.php` lines 31-37 inside the existing `if ( $acrossai_delete_data ) { ... }` block: change `$acrossai_access_control_table = $wpdb->prefix . 'wpb_access_control';` to `... . 'abilities_access_control';`. Change `\delete_option( 'wpb_access_control_db_version' );` to `\delete_option( 'wpb_ac_abilities_db_version' );`. Do NOT add cleanup for the legacy `wpb_access_control` table or its old option key — per spec FR-005 they are left orphaned intentionally. Uphold `PATTERN-UNINSTALL-DATA-GATE` and guard against `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`.
- [x] T023 [P] [SEC-005] Draft release-note copy for the upgrade. MUST cover three bullets from SEC-005 mitigation: (1) pre-upgrade rules on `{prefix}wpb_access_control` are no longer read, (2) admins with critical AC rules should audit every ability's Access Control panel after upgrade and reconfigure, (3) the legacy table is left on disk and can be dropped manually. Add the copy to `README.txt` changelog or a separate `docs/release-notes/039.md` at the maintainer's discretion.
- [x] T024 [P] Plugin-wide PHPStan L8: `composer phpstan` — zero errors across the entire plugin.
- [x] T025 [P] Plugin-wide PHPCS: `composer phpcs` — zero errors and zero warnings across the entire plugin.
- [x] T026 [P] JS package validator: `npm run validate-packages` — passes (no changes to JS deps expected, but the gate is Constitution §VII mandatory).
- [x] T027 [P] Full PHPUnit suite: `composer test` — all tests pass, including `tests/phpunit/sitewide/AccessControlBootstrapTest.php` (updated in T012).
- [ ] T028 Manual verification — uninstall cleanup: on a test install with `acrossai_abilities_uninstall_delete_data = 1` and at least one AC rule set, deactivate + delete the plugin via WP-Admin. Run `wp db query "SHOW TABLES LIKE '%abilities_access_control%'"` — MUST return zero rows. Run `wp option get wpb_ac_abilities_db_version` — MUST return empty. Confirm `{prefix}wpb_access_control` and `wpb_access_control_db_version` remain (orphaned on purpose).
- [x] T029 [P] Update `CLAUDE.md`: change the plan reference inside the `<!-- SPECKIT START --> ... <!-- SPECKIT END -->` markers from `specs/038-acrossai-main-menu-integration/plan.md` to `specs/039-composer-package-updates/plan.md`.
- [x] T030 [P] Update `docs/memory/INDEX.md` "Security Reviews" table: append the row `| specs/039-composer-package-updates/security-review-plan.md | plan | 2026-07-01 | LOW | C:0 H:0 M:0 L:1 I:4 | A01,A04,A05,A09 |` per the SEC-review INDEX row directive.

**Final Checkpoint**: All quality gates green; uninstall path verified; release-note communication drafted; memory index updated. Feature is Definition-of-Done complete per Constitution §VII.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Phase 1. **BLOCKS all user stories.** T003 (composer update) must complete before T004/T005/T006/T007 can inspect the new vendor code. T008 (PHPStan baseline) must be last in Phase 2 because it may reveal issues introduced by the composer bump itself.
- **User Story 1 (Phase 3)**: Depends on Phase 2 completion. **MVP scope.**
- **User Story 2 (Phase 4)**: Depends on Phase 2 completion + T010 (needs `TABLE_SLUG` constant from US1). Can start once T010 lands, in parallel with T011–T015.
- **User Story 3 (Phase 5)**: Depends on Phase 4 completion (needs the table to exist in a live install to verify). Verification-only — no code.
- **Polish (Phase 6)**: T022, T028 depend on Phase 4 completion. T024/T025/T026/T027 quality gates depend on all code phases complete. T023/T029/T030 have no code dependencies.

### User Story Dependencies

- **US1 (P1)**: Requires Phase 2. Delivers the primary upgrade path. **MVP-shippable in isolation.**
- **US2 (P2)**: Requires Phase 2 + T010 (US1's TABLE_SLUG constant). Delivers fresh-install correctness.
- **US3 (P3)**: Requires US2 completion (needs an activated install with the new table to verify).

### Within Each User Story

- **US1**: T009 and T010 are parallel (different files). T011 gates T009 specifically. T012 is parallel with T009/T010 (different file). T013 must succeed before T014/T015 manual verification.
- **US2**: T016 is single-file. T017 must succeed before T018/T019 manual verification.
- **US3**: T020 and T021 are independent verifications; can run in parallel.

### Parallel Opportunities

- **Phase 2**: T004 + T005 + T006 + T007 (four independent vendor greps) can run in parallel after T003.
- **Phase 3**: T009 (Main.php) + T010 (AC_Access_Control.php) + T012 (test file) — three different files, no shared imports; run in parallel.
- **Phase 6**: T023 + T029 + T030 (docs/index updates) + T024 + T025 + T026 + T027 (quality gates against fully-implemented code) — all seven can run in parallel.

---

## Parallel Example: User Story 1

```bash
# After T008 checkpoint, launch three US1 implementation tasks in parallel:
Task T009: "Edit includes/Main.php:316-348 — drop $menu_slug arg from AddonsPage constructor"
Task T010: "Edit includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php — add TABLE_SLUG constant + pass to AccessControlManager"
Task T012: "Update tests/phpunit/sitewide/AccessControlBootstrapTest.php for the new 2-arg constructor"
```

## Parallel Example: Phase 2 Verification

```bash
# After T003 (composer update):
Task T004: "grep RuleTable.php for $global"
Task T005: "grep AccessControlManager for loose comparison"
Task T006: "grep Rest/* for WP_REST_Response in permission_callback"
Task T007: "grep autoload_classmap for AddonsPage → main-menu"
```

## Parallel Example: Phase 6 Quality Gates

```bash
# After all code phases complete:
Task T024: "composer phpstan (plugin-wide)"
Task T025: "composer phpcs (plugin-wide)"
Task T026: "npm run validate-packages"
Task T027: "composer test (PHPUnit)"
Task T023: "Draft release-note copy"
Task T029: "Update CLAUDE.md SPECKIT plan reference"
Task T030: "Append INDEX.md Security Reviews row"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001).
2. Complete Phase 2: Foundational (T002–T008). **Do not skip the four verification greps** — they are the SEC-002/003/004 blocking gates.
3. Complete Phase 3: User Story 1 (T009–T015).
4. **STOP and VALIDATE**: T014 + T015 manual admin walkthrough proves the primary upgrade path works.
5. Deploy the MVP release. Fresh installs still get an empty AC table on first admin request (BerlinDB `admin_init` fallback) — not ideal, but not broken. US2 hardens that.

### Incremental Delivery

1. **Setup + Foundational** → gate cleared for all downstream work.
2. **US1** → ship the MVP release. Existing admins keep using the plugin. Fresh-install storage arrives on first admin request (acceptable but not optimal).
3. **US2** → fresh-install correctness hardened. Ship a follow-up patch release.
4. **US3** → verification only. No release action; capture the isolation proof for future multi-plugin scenarios.
5. **Polish (Phase 6)** → uninstall cleanup, release notes, quality gates green. Ship the full release.

### Solo-Developer Strategy

With one developer, the natural sequence is: **Phase 1 → Phase 2 → US1 → US2 → US3 → Polish**. Within US1, do T009 and T010 sequentially (single tab context makes parallel less useful for one person), then T011 (verify T009 didn't regress the closure), then T012, then T013, then T014/T015 in a browser session. Within Polish, run the quality gates (T024–T027) all together at the terminal.

---

## Task Count Summary

| Phase | Tasks | Parallel | User Story | Focus |
|---|---|---|---|---|
| 1: Setup | 1 (T001) | 0 | — | Working-tree check |
| 2: Foundational | 7 (T002–T008) | 4 (T004–T007) | — | Composer update + SEC-002/003/004 gates + baseline PHPStan |
| 3: US1 (MVP) | 7 (T009–T015) | 2 (T010, T012) | US1 | AddonsPage constructor + TABLE_SLUG + admin verification |
| 4: US2 | 4 (T016–T019) | 0 | US2 | Activator table-create + fresh-install verification |
| 5: US3 | 2 (T020–T021) | 1 (T021) | US3 | Storage-isolation verification |
| 6: Polish | 9 (T022–T030) | 7 (T023–T027, T029, T030) | — | Uninstall + release notes + quality gates + housekeeping |
| **Total** | **30** | **14 parallelizable** | — | — |

### Security-Review Traceability

| SEC Finding | Blocking? | Tasks |
|---|---|---|
| SEC-001 (LOW — closure preservation) | Advisory | T011 |
| SEC-002 (INFO — multisite `$global`) | **BLOCKING** | T004 |
| SEC-003 (INFO — strict comparison) | **BLOCKING** | T005 |
| SEC-004 (INFO — permission_callback return type) | **BLOCKING** | T006 |
| SEC-005 (INFO — release-note communication) | Advisory | T023 |

---

## Notes

- No new test tasks generated — the feature adds no new business logic. T012 updates an existing test.
- The five TASKs from `docs/planning/039-composer-package-updates.md` map to code tasks as: TASK-1 → T002+T003; TASK-2 → T009; TASK-3 → T010; TASK-4 → T016; TASK-5 → T022.
- Every code-editing task specifies exact file paths and line ranges. Every verification task specifies exact commands or navigation paths.
- Constitution §VII per-task gating is enforced by T013 (US1), T017 (US2), and T024–T027 (Polish). Do NOT batch quality gates until the individually-scoped ones pass.
- The MVP release is safely shippable after Phase 3 alone; Phases 4–6 harden and clean up without changing the upgrade-path behavior admins observe.

---

description: "Task list for Feature 043 — WordPress core rollback via WP.org Core API"
---

# Tasks: Feature 043

**Input**: Design documents from `/specs/043-wp-core-rollback-via-wporg-api/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [memory-synthesis.md](./memory-synthesis.md), [security-constraints.md](./security-constraints.md), [architecture-review.md](./architecture-review.md)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Parallelizable (different files, no dependencies)
- **[Story]**: US1 (WP core rollback through Abilities API)

---

## Phase 1: Setup

- [x] T001 Read Andy Fragen's `core-rollback` plugin (`wp-content/plugins/core-rollback/src/Core.php` + `Settings.php`) to understand the WP.org Core API 1.7 endpoint contract, per-locale caching posture, and the `Core_Upgrader::upgrade($offer)` observation (upgrader doesn't care about version direction).
- [x] T002 Cut branch `043-wp-core-rollback-via-wporg-api` from `main` at `53f8763` (release 0.0.11).

---

## Phase 2: US1 — WP core rollback (Priority: P1)

- [x] T003 [US1] Create `includes/Abilities/Core/Wp_Core_Rollback.php` — extends `Ability_Definition`. Requires BOTH `manage_options` AND `update_core`. Guards on `File_Mods_Guard::blocked_response('install')` + multisite guard + non-downgrade guard (`version_compare($v, get_bloginfo('version'), '>=')`). Fetches offers from `https://api.wordpress.org/core/version-check/1.7/?locale={locale}` via `wp_remote_get`; caches per-locale in `acrossai_abilities_manager_core_offers_{locale}` site transient with `DAY_IN_SECONDS` TTL (filter guarded only by upstream WP behaviour; not exposing our own filter yet). Forces `$offer->response = 'upgrade'`; hands offer directly to `Core_Upgrader::upgrade()`. Result-interpretation ladder identical to `Wp_Core_Update` / `Plugin_Update` / `Theme_Update`.
- [x] T004 [US1] Add `new Core\Wp_Core_Rollback();` to `AcrossAI_Core_Abilities_Bootstrap::register_abilities()` next to the two Feature 042 abilities.

---

## Phase 3: Tests

- [x] T005 Create `tests/phpunit/abilities/Test_Feature_043_Core_Rollback.php` — 10 source-inspection tests: (1) shape + full args, (2) both-cap gate `manage_options`+`update_core`, (3) File_Mods_Guard + multisite guard, (4) non-downgrade refusal via `version_compare('>=' )`, (5) uses Core_Upgrader + WP_Ajax_Upgrader_Skin + `->upgrade($offer)`, (6) forces `$offer->response = 'upgrade'`, (7) fetches from `api.wordpress.org/core/version-check/1.7/` via `wp_remote_get`, (8) per-locale site transient cache with `DAY_IN_SECONDS`, (9) `destructive => true` annotation, (10) bootstrap wires the ability.
- [x] T006 Register `feature-043-unit` testsuite in `phpunit.xml.dist`.

---

## Phase 4: Quality gates

- [x] T007 `composer run phpstan` (level 8) — zero errors
- [x] T008 `composer run phpcs` — zero errors
- [x] T009 `composer test` — feature-043-unit passes (10 tests / 27 assertions); full suite green (target: 163 tests / 531 assertions)

---

## Phase 5: Spec-kit artifacts

- [x] T010 Author `specs/043-wp-core-rollback-via-wporg-api/` — 7 files (spec / plan / tasks / checklists/requirements / security-constraints / memory-synthesis / architecture-review).

---

## Phase 6: Ship 0.0.12

- [ ] T011 Open feature PR (043 branch → main). Merge.
- [ ] T012 Cut `release-0.0.12` branch with version bump across `acrossai-abilities-manager.php`, `includes/Main.php`, `README.txt`. Add Changelog + Upgrade Notice entries for 0.0.12.
- [ ] T013 Open release PR (release-0.0.12 → main). Merge.
- [ ] T014 Tag `0.0.12`, cut GitHub release.
- [ ] T015 Revise the pending memory-capture entries (from the 0.0.10 → 0.0.11 range that was paused when Feature 043 kicked off): add the rollback technique to `PATTERN-WP-CORE-UPGRADER-ABILITY`, add a new `PATTERN-WP-CORE-ROLLBACK-VIA-API-OFFER`, and annotate the Feature 042 WORKLOG entry with a "follow-up: Feature 043" pointer. Get approval, then write to durable memory.

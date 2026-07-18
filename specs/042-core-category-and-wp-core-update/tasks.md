---

description: "Task list for Feature 042 — Core category (WP core update) + backup filename scheme change"
---

# Tasks: Feature 042

**Input**: Design documents from `/specs/042-core-category-and-wp-core-update/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [memory-synthesis.md](./memory-synthesis.md), [security-constraints.md](./security-constraints.md), [architecture-review.md](./architecture-review.md)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Parallelizable (different files, no dependencies)
- **[Story]**: US1 (WP core update through Abilities API) / US2 (time-sortable backup filenames)

---

## Phase 1: Setup

- [x] T001 Cut branch `042-core-category-and-wp-core-update` from `main` at `0e8e03b` (release 0.0.10).
- [x] T002 Pattern-parity read of `Plugins/Update_Check.php`, `Plugins/Plugin_Update.php`, `Themes/Theme_Update.php`, `FileManager/Category_Registrar.php` before writing any new ability code.

---

## Phase 2: US2 — Backup filename scheme (Priority: P2, no runtime dependencies)

- [x] T003 [US2] Rewrite `Backups_Storage::random_backup_filename()` — drop the `backup-` prefix seed and the 12-char `wp_generate_password(12, false)` random suffix; emit `{slug}-{unix-timestamp}-{ms}.zip` where `{slug}` comes from a private `filename_slug_segment()` helper (target → target_type → literal `backup`) and `{unix}` + `{ms}` come from a private `filename_time_segments()` helper reading `microtime(true)`.

---

## Phase 3: US1 — WP core update abilities (Priority: P1)

- [x] T004 [US1] Create `includes/Abilities/Core/Category_Registrar.php` mirroring `FileManager/Category_Registrar.php`. Slug `acrossai-abilities-manager-core`, label preserves mojibake `â` character to match the 17 existing Category_Registrar labels.
- [x] T005 [US1] Create `includes/Abilities/Core/Wp_Core_Update_Check.php` — read-only, `manage_options`-gated, wraps `get_core_updates()`. Output flattens the first offer into JSON-friendly fields.
- [x] T006 [US1] Create `includes/Abilities/Core/Wp_Core_Update.php` — wraps `Core_Upgrader::upgrade()`. Requires BOTH `manage_options` AND `update_core`. Guards on `File_Mods_Guard::blocked_response('install')` + multisite guard. Optional `version` + `locale` inputs.

---

## Phase 4: Bootstrap wiring

- [x] T007 Wire `Core\Category_Registrar::instance()` into `AcrossAI_Core_Abilities_Bootstrap::register_category_callbacks()`.
- [x] T008 Add `new Core\Wp_Core_Update_Check();` and `new Core\Wp_Core_Update();` to `AcrossAI_Core_Abilities_Bootstrap::register_abilities()` next to the existing SiteHealth abilities.

---

## Phase 5: Tests

- [x] T009 Create `tests/phpunit/abilities/Test_Feature_042_Core_Update.php` — 9 source-inspection tests: filename scheme drops old prefix + suffix, uses slug+unix+ms, has ultimate fallback; Core Category_Registrar shape; Wp_Core_Update_Check shape + read-only annotation; Wp_Core_Update guards (Core_Upgrader / File_Mods_Guard / multisite guard / WP_Ajax_Upgrader_Skin); permission_callback ANDs manage_options with update_core; optional version + locale accepted; bootstrap wires category + both abilities.
- [x] T010 Register `feature-042-unit` testsuite in `phpunit.xml.dist`.

---

## Phase 6: Quality gates

- [ ] T011 `composer run phpstan` (level 8) — zero errors
- [ ] T012 `composer run phpcs` — zero errors
- [ ] T013 `composer test` — full suite green (target: 153 tests / 504 assertions after 9 new 042 tests)

---

## Phase 7: Spec-kit artifacts

- [x] T014 Backfill Feature 041 spec-kit folder at `specs/041-backup-restore-abilities-and-updates/` (7 files: spec / plan / tasks / checklists/requirements / security-constraints / memory-synthesis / architecture-review). Fixes section in spec + tasks documents the 0.0.10 include_hidden fix.
- [x] T015 Author Feature 042 spec-kit folder at `specs/042-core-category-and-wp-core-update/` (7 files).

---

## Phase 8: Ship 0.0.11

- [ ] T016 Open feature PR (042 branch → main). Merge.
- [ ] T017 Cut `release-0.0.11` branch with version bump across `acrossai-abilities-manager.php`, `includes/Main.php`, `README.txt`. Add Changelog + Upgrade Notice entries.
- [ ] T018 Open release PR (release-0.0.11 → main). Merge.
- [ ] T019 Tag `0.0.11`, cut GitHub release.
- [ ] T020 Run `/speckit.memory-md.capture-from-diff` against the shipped diff. Confirm the proposed memory entries; save the confirmed ones.

---

description: "Task list for Feature 041 — Backup/restore abilities + plugin/theme update"
---

# Tasks: Feature 041

**Input**: Design documents from `/specs/041-backup-restore-abilities-and-updates/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [memory-synthesis.md](./memory-synthesis.md), [security-constraints.md](./security-constraints.md), [architecture-review.md](./architecture-review.md)
**Backfilled**: All tasks completed at implementation time; this file is the post-implementation record.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Parallelizable (different files, no dependencies)
- **[Story]**: US1 (cross-site backup/restore) / US2 (plugin/theme update) / US3 (backup dir hygiene)

---

## Phase 1: Setup

- [x] T001 Cut feature branch `041-backup-restore-abilities-and-updates` from `main` at `6f69b57`.

---

## Phase 2: Shared utilities (unblocks every ability)

- [x] T002 Create `includes/Abilities/Utilities/Backups_Storage.php` — bootstraps `acrossai-backups/` + `acrossai-staging/` with hardening files, generates random filenames (`backup-{type}-{slug}-{12-random-chars}.zip`), exposes `resolve_managed_path()` / `list_entries()` / `sha256_of()` / `url_for()` / `to_abspath_relative()`. `.htaccess` blocks PHP execution but allows `.zip` downloads.
- [x] T003 Create `includes/Abilities/Utilities/Zip_Target_Resolver.php` — 5 supported types (`TYPE_PLUGIN`, `TYPE_THEME`, `TYPE_UPLOADS`, `TYPE_MU_PLUGINS`, `TYPE_PATH`). Plugin resolution via `Plugin_Helpers`; theme via `Theme_Helpers`; `path` type applies the same `realpath()`-inside-ABSPATH check File_Read.php uses.

---

## Phase 3: US1 — Cross-site backup / restore (Priority: P1)

- [x] T004 [US1] Create `includes/Abilities/FileManager/Zip_Create.php` — `ZipArchive::CREATE|OVERWRITE`, `RecursiveIteratorIterator` + SELF_FIRST, size-cap filter (default 512 MB). Returns `file_path`, `file_url`, `size`, `sha256`, `message`.
- [x] T005 [US1] Create `includes/Abilities/FileManager/Zip_Upload.php` — three input modes (base64 / URL / chunked). Chunked mode reuses `Upload_Media`'s staging + TTL cleanup shape but finalizes into `acrossai-backups/`. Magic-byte check on finalize. Own sweeper cron `acrossai_abilities_manager_zip_upload_sweep_chunks`.
- [x] T006 [US1] Create `includes/Abilities/FileManager/Zip_Extract.php` — audits every zip entry for `..`, absolute paths, backslashes, null bytes BEFORE extraction. Uses `unzip_file()` by default; `overwrite=true` drops to `ZipArchive::extractTo`. Guards on `File_Mods_Guard::blocked_response('install')`.

---

## Phase 4: US3 — Backup dir hygiene (Priority: P3)

- [x] T007 [US3] Create `includes/Abilities/FileManager/Zip_Download.php` — read-only, returns URL + `size` + `sha256` + `created_at` for any file inside the managed dirs.
- [x] T008 [US3] Create `includes/Abilities/FileManager/Zip_List.php` — paginated (`limit` 1–200, default 50; `offset` ≥ 0), newest first. Selectable `dir ∈ {backups, staging}`.
- [x] T009 [US3] Create `includes/Abilities/FileManager/Zip_Delete.php` — idempotent (missing file → success with note). Guards on `File_Mods_Guard`.

---

## Phase 5: US2 — Plugin / theme update (Priority: P2)

- [x] T010 [US2] Create `includes/Abilities/Plugins/Plugin_Update.php` — wraps `Plugin_Upgrader::bulk_upgrade()`. Accepts plugin files or bare slugs (resolved via `Plugin_Helpers`). Additional cap `update_plugins`. Guards on `File_Mods_Guard`.
- [x] T011 [US2] Create `includes/Abilities/Themes/Theme_Update.php` — wraps `Theme_Upgrader::bulk_upgrade()`. Accepts stylesheets or theme names (resolved via `Theme_Helpers`). Additional cap `update_themes`. Guards on `File_Mods_Guard`.

---

## Phase 6: Bootstrap wiring + tests

- [x] T012 Wire eight new abilities into `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap::register_abilities()`. Add Zip_Upload sweeper cron next to the existing Upload_Media sweeper.
- [x] T013 Create `tests/phpunit/abilities/Test_Feature_041_Backup_Abilities.php` — 14 source-inspection tests covering Ability_Definition extends, full args shape, rebranded slugs/categories, permission gates (manage_options everywhere; update_plugins / update_themes extras on the two update abilities), `File_Mods_Guard` on every mutating ability, Zip_Extract zip-slip audit, Zip_Upload magic-byte check, bootstrap instantiation of every new class + sweeper registration, and the utility surface (Backups_Storage constants + helpers, hardening allows `.zip` downloads, Zip_Target_Resolver enumerates the five types with an ABSPATH boundary check).
- [x] T014 Register new suite in `phpunit.xml.dist` as `feature-041-unit`.

**Commit 1**: `acf5551 Feature 041 — Backup/restore abilities + plugin/theme update` (13 files, +3610)

---

## Phase 7: Quality gates

- [x] T015 `composer run phpstan` (level 8) — zero errors
- [x] T016 `composer run phpcs` — zero errors
- [x] T017 `composer test` — 143 tests, 466 assertions, all pass

---

## Phase 8: Ship 0.0.9

- [x] T018 Open PR #71 (feature branch → main). Merged as `dc49548`.
- [x] T019 Cut `release-0.0.9` branch with version bump across `acrossai-abilities-manager.php` `Version:` header, `includes/Main.php` `ACROSSAI_ABILITIES_MANAGER_VERSION` constant, `README.txt` `Stable tag`. Add Changelog + Upgrade Notice entries.
- [x] T020 Open PR #72 (release-0.0.9 → main). Merged as `b0a21d5`.
- [x] T021 Tag `0.0.9`, cut GitHub release.

**Commit 2**: `1bef861 Release 0.0.9 — bump version + changelog for Feature 041`

---

## Fixes

### F1 — 0.0.10 fix for `include_hidden=false` recursive descent (2026-07-18)

**Bug**: `RecursiveIteratorIterator::SELF_FIRST` does not stop descent when the outer `foreach` runs `continue` on a hidden entry — so files INSIDE a hidden directory (`.git/objects/xxx`) still landed in the archive because their basenames don't start with `.`. Only the top-level hidden directory entry was skipped.

**Fix pattern**: Study reference plugin `download-plugin/app/Plugins/Base.php:282` which checks every path segment. Apply the same per-segment check.

- [x] F1a Fix `Zip_Create::append_dir_to_zip()` and `estimate_tree_size()` — check EVERY segment of the entry's forward-slashed relative path.
- [x] F1b Extract two small helpers: `normalize_relative()` (absolute pathname → forward-slashed relative path) and `has_hidden_segment()` (per-segment `.`-prefix check).
- [x] F1c Add regression test `test_zip_create_skips_hidden_at_every_segment` to `Test_Feature_041_Backup_Abilities.php` (15 tests / 154 assertions).
- [x] F1d Quality gates: phpstan L8 zero, phpcs zero, phpunit 144 tests / 468 assertions.
- [x] F1e Open PR #73 (fix branch → main). Merged as `0e1a177`.
- [x] F1f Cut `release-0.0.10` branch with version bump + 0.0.10 Changelog + Upgrade Notice entries.
- [x] F1g Open PR #74 (release-0.0.10 → main). Merged as `0e8e03b`.
- [x] F1h Tag `0.0.10`, cut GitHub release.

**Commit 3**: `910d38c Feature 041 fix — Zip_Create honours include_hidden=false recursively`
**Commit 4**: `f1f0d68 Release 0.0.10 — bump version + changelog for Feature 041 fix`

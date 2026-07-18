# Implementation Plan: Feature 041

**Feature Branch**: `041-backup-restore-abilities-and-updates` (merged as PR #71)
**Follow-up fix**: `fix-041-zip-create-hidden-descent` (merged as PR #73)
**Release**: `release-0.0.9` (PR #72) + `release-0.0.10` (PR #74)
**Backfilled**: 2026-07-18

## Approach

Adopt existing patterns rather than invent new abstractions:

- **8 new abilities** live under existing `Category` folders (`FileManager/`, `Plugins/`, `Themes/`) — Constitution §I locks the module list at five; no new module.
- **2 utilities** under `includes/Abilities/Utilities/` (Constitution §VI: shared logic → Utilities before second use).
- **Auto-registration**: each ability's `Ability_Definition::__construct` hooks the `acrossai_abilities_api_init` filter. Bootstrap wiring is only 8 `new …()` lines in `AcrossAI_Core_Abilities_Bootstrap::register_abilities()`.
- **Tests**: source-inspection style already established by Feature 046's Absorbed suite — no full WP runtime dependency.

## New files

### Abilities

- `includes/Abilities/FileManager/Zip_Create.php` — creates a zip of a plugin/theme/uploads/mu-plugins/path into `wp-content/uploads/acrossai-backups/{random}.zip`. Uses PHP `ZipArchive` (guaranteed on PHP 8.1). Size-cap filterable via `acrossai_abilities_manager_zip_max_bytes` (default 512 MB).
- `includes/Abilities/FileManager/Zip_Upload.php` — chunked / base64 / URL upload with the same staging pattern as `Media/Upload_Media.php` (staging under `wp-content/uploads/acrossai-staging/` with a TTL cleanup cron), but finalizes into `acrossai-backups/` and skips the media library. Validates PK magic on finalize.
- `includes/Abilities/FileManager/Zip_Extract.php` — reads a zip from a local path or fetches via `download_url()`. Uses `ZipArchive::open(CHECKCONS)` for the audit pass, then `unzip_file()` (or `ZipArchive::extractTo` when `overwrite=true`).
- `includes/Abilities/FileManager/Zip_Download.php` — returns fresh URL + metadata for any zip inside the managed dirs.
- `includes/Abilities/FileManager/Zip_List.php` — paginated listing (`limit` 1–200, `offset` ≥ 0), newest first.
- `includes/Abilities/FileManager/Zip_Delete.php` — idempotent delete (deleting a missing file returns success with a note).
- `includes/Abilities/Plugins/Plugin_Update.php` — wraps `Plugin_Upgrader::bulk_upgrade()`. Additional cap `update_plugins`.
- `includes/Abilities/Themes/Theme_Update.php` — wraps `Theme_Upgrader::bulk_upgrade()`. Additional cap `update_themes`.

### Utilities

- `includes/Abilities/Utilities/Backups_Storage.php` — bootstraps + hardens the two managed dirs, resolves managed paths with boundary checks, generates random filenames (see Feature 042 for the follow-up scheme change), computes SHA-256, lists entries with metadata.
- `includes/Abilities/Utilities/Zip_Target_Resolver.php` — maps `(target_type, target)` to an absolute path via `Plugin_Helpers` / `Theme_Helpers` / `wp_get_upload_dir()` / `WPMU_PLUGIN_DIR` / ABSPATH-realpath-boundary check.

### Tests

- `tests/phpunit/abilities/Test_Feature_041_Backup_Abilities.php` — 15 tests (14 initial + 1 added in the 0.0.10 fix), 154 assertions. Source-inspection style.

## Modified files

- `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — 8 `new …()` lines added in `register_abilities()` next to the existing FileManager / Plugins / Themes instantiations; new sweeper hook + registration for Zip_Upload's chunk cron mirrors the Upload_Media sweeper.
- `phpunit.xml.dist` — new `feature-041-unit` testsuite.

## Reusables consumed

- `Ability_Definition` base class (`includes/Modules/Library/Ability_Definition.php`) — subclasses only implement `ability()`; the constructor hooks the filter.
- `File_Mods_Guard` (`includes/Abilities/Utilities/File_Mods_Guard.php`) — DISALLOW_FILE_MODS short-circuit on every mutating ability.
- `Plugin_Helpers` / `Theme_Helpers` — fuzzy resolution for plugin slugs / theme stylesheets.
- `Upload_Media` chunked pattern (`includes/Abilities/Media/Upload_Media.php`) — Zip_Upload lifts the staging + TTL + chunk-assembly shape verbatim.
- WP core: `download_url()`, `unzip_file()`, `WP_Filesystem`, `Plugin_Upgrader`, `Theme_Upgrader`, `WP_Ajax_Upgrader_Skin`, `wp_get_upload_dir()`. No third-party libs.

## Security constraints

See [security-constraints.md](./security-constraints.md) for the full checklist.

## Verification

- `composer run phpstan` (level 8) — zero errors
- `composer run phpcs` — zero errors
- `composer test` — 143→144 tests, 466→468 assertions across the 0.0.9 / 0.0.10 hop
- Manual e2e in Local: Zip_Create → Zip_List → Zip_Download → Zip_Extract → verify files land
- Zip-slip archive rejected; DISALLOW_FILE_MODS short-circuits five mutating abilities; idempotent no-op behaviour on the update abilities

## Post-implementation follow-up

The 0.0.10 fix (`fix-041-zip-create-hidden-descent`) landed on top of the initial Feature 041 ship. See [tasks.md](./tasks.md) `Fixes` section for the tracked task list.

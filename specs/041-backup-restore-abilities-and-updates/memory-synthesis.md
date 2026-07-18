# Memory Synthesis: Feature 041

**Feature**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)
**Backfilled**: 2026-07-18

## Current Scope

Adds 8 new abilities to `acrossai-abilities-manager`: 6 FileManager Zip abilities (`zip-create`, `zip-upload`, `zip-extract`, `zip-download`, `zip-list`, `zip-delete`) for cross-site backup / restore workflows, and 2 lifecycle abilities (`plugin-update`, `theme-update`) that finally close the "apply an update through the Abilities API" gap. Adds 2 shared utilities (`Backups_Storage`, `Zip_Target_Resolver`) under `includes/Abilities/Utilities/` per Constitution §VI. Adds a chunk-sweeper cron (`acrossai_abilities_manager_zip_upload_sweep_chunks`) mirroring the existing `Upload_Media` sweeper. Bootstrap wiring in `AcrossAI_Core_Abilities_Bootstrap`. No new modules (Constitution §I preserved). No new REST endpoints, no new database tables, no composer additions. Released as 0.0.9 (PRs #71 + #72) and patched as 0.0.10 (PRs #73 + #74).

## Relevant Decisions

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** (Reason: `Ability_Definition::__construct` hooks `acrossai_abilities_api_init` — every ability class self-registers on instantiation. Feature 041 adds 8 subclasses; bootstrap wiring is just 8 `new …()` lines. Status: Active, Source: DECISIONS.md)
- **DEC-UTILITIES-BEFORE-SECOND-USE** (Reason: `Backups_Storage` and `Zip_Target_Resolver` were introduced when they hit two callers each — `Backups_Storage` used by all six Zip_* abilities; `Zip_Target_Resolver` used by both `Zip_Create` and `Zip_Extract`. Status: Active, Source: CONSTITUTION.md §VI)
- **DEC-BACKUP-URL-RETURN** (New: Chose "return `file_url` from a hardened uploads dir" over "server-to-server push" for the cross-site transfer model. Reason: URL-return keeps the ability surface small (no outbound HTTP + credentials handling), fits AI/MCP flows where the model can download and re-upload, and reuses WP core's existing uploads dir. The `.htaccess` blocks PHP execution while leaving `.zip` reachable — the crucial trade-off that makes the URL flow work. Status: Active, Source: this spec)
- **DEC-SOURCE-INSPECTION-TESTS** (Reason: Every Feature 041 test uses source-inspection (`file_get_contents` + regex/substring assertions) rather than runtime instantiation — matches the Feature 046 Absorbed test suite precedent. The plugin's stub bootstrap can't safely construct the full plugin. Status: Active, Source: Test_Feature_046_Contracts.php precedent)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason: All 8 new abilities auto-register via the filter on `Ability_Definition::__construct`. Bootstrap wiring in `AcrossAI_Core_Abilities_Bootstrap::register_abilities()` is called from `Main.php::define_public_hooks()`, preserving Boot Flow Rule literalism. Source: CONSTITUTION.md §I)
- **AC-MODULE-ENUMERATION-LOCKED** (Reason: No new module. `FileManager`, `Plugins`, `Themes` are existing Category folders inside the existing Abilities module. Source: CONSTITUTION.md §I)
- **AC-CAPABILITY-BASELINE** (Reason: Every ability enforces at least `manage_options`. `Plugin_Update` and `Theme_Update` add `update_plugins` / `update_themes` — matching WP core's own admin gate for those actions. Source: CONSTITUTION.md §IV)
- **AC-FILE-MODS-GUARD** (Reason: Every mutating ability short-circuits via `File_Mods_Guard::blocked_response('install')`. Verified by `test_mutating_abilities_call_file_mods_guard`. Source: CONSTITUTION.md §IV)
- **AC-UTILITIES-DRY** (Reason: Reused `Plugin_Helpers`, `Theme_Helpers`, `File_Mods_Guard` — did not re-implement. Placed new utilities in `includes/Abilities/Utilities/` on first cross-file need. Source: CONSTITUTION.md §VI)

## Accepted Deviations

- **DEV-041-ZIP-STAGING-PARALLEL** (Reason: `Zip_Upload` duplicates the shape of `Media/Upload_Media`'s chunked staging protocol rather than extracting a shared helper. Two callers only, one of which routes to the media library and one of which does not — the abstraction that would unify them would need to be parameterized on "finalize target dir" and "media_handle_sideload vs plain move" and would obscure both call sites. Accepted for now; revisit if a third caller appears. Status: Accepted-Deviation, Source: this spec)

## Relevant Security Constraints

See [security-constraints.md](./security-constraints.md). The eleven constraints in force are:

- C-041-SEC-01 through C-041-SEC-10 for the 0.0.9 baseline (zip-slip audit, ABSPATH boundary, backups dir hardening, random filenames, File_Mods_Guard, capability gates, size caps, magic-byte validation, managed-path boundary, no shell).
- C-041-SEC-11 for the 0.0.10 fix (per-segment hidden-path check).

Per `feedback_skip_permission_callback_audit` in memory, a full REST-layer permission_callback audit is out of scope; each ability's `permission_callback` is inspected by the source-scan test suite.

## Related Historical Lessons

- **BUG-041-01 — Global Deny in .htaccess breaks download URL** (Reason: Initial `Backups_Storage::resolve_dir()` wrote `Deny from all` which would have made `zip-create`'s returned `file_url` unreachable. Caught during self-review before the first commit; changed to `FilesMatch` blocking only PHP-family extensions + `Options -Indexes`. The test `test_backups_storage_hardening_allows_zip_downloads` guards against regression. Source: this spec + Test_Feature_041 source-inspection assertion)
- **BUG-041-02 — SELF_FIRST does not stop descent on `continue`** (Reason: 0.0.9 `Zip_Create::append_dir_to_zip()` used `if ($name[0] === '.') continue;` on the current entry's basename. `RecursiveIteratorIterator::SELF_FIRST` visits the directory first (skipped) but then descends into every file below it (basenames don't start with `.` so they're added). Result: `include_hidden=false` archives silently included `.git/objects/xxx`. Fix in 0.0.10: check EVERY segment of the entry's forward-slashed relative path via `has_hidden_segment()` — same pattern the reference `download-plugin/app/Plugins/Base.php` uses. Source: this spec + Test_Feature_041 regression test)
- **BUG-041-03 — sub-second filename collisions** (Not exercised in 0.0.9/0.0.10; addressed in Feature 042 where the filename scheme changes to `{slug}-{unix}-{ms}.zip` and the `ms` suffix prevents same-second overwrites. See Feature 042 memory-synthesis. Source: Feature 042 spec)

## Conflict Warnings

None. All Feature 041 changes are compatible with active decisions and constraints. Feature 042 supersedes `DEC-BACKUP-URL-RETURN`'s enumeration-defense corollary (12-char random filename suffix) by switching to a time-based scheme — the URL-return contract itself is unchanged.

## Retrieval Notes

- Optimizer not enabled — markdown-only, index-first retrieval used.
- Read references at planning time: reference plugin `download-plugin/app/Plugins/Base.php` (adopted per-segment hidden-check pattern in the 0.0.10 fix); WP core `Plugin_Upgrader`, `Theme_Upgrader`, `unzip_file()`, `download_url()`; existing utilities `Plugin_Helpers`, `Theme_Helpers`, `File_Mods_Guard`; existing ability shape `Media/Upload_Media` (chunked upload pattern).
- Full durable-memory reads: NOT performed. Budget status: within limits (~800 words, under the 900-word cap).

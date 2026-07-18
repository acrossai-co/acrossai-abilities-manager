# Memory Synthesis: Feature 042

**Feature**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)

## Current Scope

Adds a new `Core` Category folder under `includes/Abilities/Core/` with a `Category_Registrar.php` (slug `acrossai-abilities-manager-core`) and two abilities: `wp-core-update-check` (read-only availability report) and `wp-core-update` (applies the update via `Core_Upgrader::upgrade()`). Rewrites `Backups_Storage::random_backup_filename()` to emit `{slug}-{unix-timestamp}-{ms}.zip` instead of the 0.0.9 `backup-{type}-{slug}-{random}.zip` scheme. Wires the new Category + abilities into `AcrossAI_Core_Abilities_Bootstrap`. Adds `Test_Feature_042_Core_Update.php` (9 source-inspection tests). No new REST endpoints, no new database tables, no composer changes. Target release: 0.0.11.

## Relevant Decisions

- **DEC-042-CORE-CATEGORY-NEW-FOLDER** (New: A new Category folder is added under `includes/Abilities/` — the 18th, joining Plugins / Themes / FileManager / Cache / Database / Users / Block / Settings / Fonts / Content / Taxonomies / Media / Comments / Menus / Options / Cron / SiteHealth. This is NOT a new module — Constitution §I locks the module list at 5; Category folders are the sub-partition of the existing Custom Ability Registration module. Justification: the WP core surface is a natural grouping and gives future core-scoped abilities a home. Status: Active, Source: this spec)
- **DEC-042-FILENAME-TIME-BASED** (New: Backup filenames switch from random-suffix to time-based (`{slug}-{unix}-{ms}.zip`). Trade-off: gains human-readability + lexicographic-sort = chronological-sort; loses enumeration-by-guessing defense. Mitigations retained: directory listing disabled, PHP execution blocked, `manage_options` gate on list/download. User-approved after explicit clarification. Status: Active, Source: this spec)
- **DEC-042-WP-CORE-ONLY** (New: The `wp-core-update` ability wraps `Core_Upgrader::upgrade()` exclusively — no custom HTTP fetch, no custom integrity verification, no bundled updater code. All upgrade infrastructure is WP core's responsibility. Motivated by user directive: "make sure to use the wordpress functions only for the core". Status: Active, Source: this spec)
- **DEC-042-FIX-SPEC-KIT-BACKFILL** (New: Feature 041 gets a full spec-kit folder backfilled in the same round as Feature 042 (rather than a fix-only spec folder or forward-only). Matches the Feature 053 backfill precedent already in `git log`. The 0.0.10 fix is documented as a `Fixes` section inside Feature 041's spec + tasks, not a separate spec folder. Status: Active, Source: this spec Clarifications)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason: Both new abilities auto-register via `Ability_Definition::__construct` on the `acrossai_abilities_api_init` filter. Bootstrap wiring is in `AcrossAI_Core_Abilities_Bootstrap::register_abilities()` — called from `Main.php::define_public_hooks()`. Boot Flow Rule literalism preserved. Source: CONSTITUTION.md §I)
- **AC-MODULE-ENUMERATION-LOCKED** (Reason: No new module. `Core` is a new Category folder inside the existing Abilities module — same sub-partitioning strategy used for the other 17 Category folders. Source: CONSTITUTION.md §I)
- **AC-CAPABILITY-BASELINE** (Reason: `Wp_Core_Update` enforces both `manage_options` AND `update_core` — matches WP core's own admin gate for core upgrades. Source: CONSTITUTION.md §IV)
- **AC-FILE-MODS-GUARD** (Reason: `Wp_Core_Update` short-circuits via `File_Mods_Guard::blocked_response('install')` before any upgrade work. Source: CONSTITUTION.md §IV)
- **AC-PATTERN-PARITY** (Reason: New ability classes match the exact shape used by `Plugins/Update_Check.php` / `Plugins/Plugin_Update.php` / `Themes/Theme_Update.php` — no creative shape drift, per explicit user directive. Source: this spec)

## Accepted Deviations

- **DEV-042-MOJIBAKE-LABEL** (Reason: The new `Core/Category_Registrar.php` label preserves the mojibake `â` character (should be `–` en-dash). All 17 existing Category_Registrar labels have the same encoding artifact — matching the pattern is more valuable than fixing this one instance. If fixed anywhere, must be fixed everywhere as a separate feature. Status: Accepted-Deviation, Source: this spec)

## Relevant Security Constraints

See [security-constraints.md](./security-constraints.md). Nine constraints in force:

- C-042-SEC-01 through C-042-SEC-09 (both-cap gate, File_Mods_Guard, multisite guard, no custom HTTP, read-only check ability, sanitized version+locale inputs, filename trade-off acceptance, no shell, filename slug sanitization).

Per `feedback_skip_permission_callback_audit` in memory, a full REST-layer permission_callback audit is out of scope; each ability's `permission_callback` is inspected by the source-scan test suite.

## Related Historical Lessons

- **BUG-041-02 — SELF_FIRST does not stop descent on `continue`** (Reason: Not exercised by Feature 042. `Wp_Core_Update` does not iterate directories. The 0.0.10 fix stays in force and documented in Feature 041's memory-synthesis. Source: Feature 041 memory-synthesis)
- **BUG-041-01 — Global Deny in .htaccess breaks download URL** (Reason: Not exercised by Feature 042 — the `.htaccess` in `Backups_Storage::resolve_dir()` is unchanged. The `Options -Indexes` + PHP-family FilesMatch pattern remains the mitigation for the filename-scheme trade-off. Source: Feature 041 memory-synthesis)
- **NEW BUG-042-01 — Sub-second filename collision on concurrent zip-create** (Reason: The 0.0.9 filename scheme used `wp_generate_password(12, false)` which is collision-resistant enough that concurrent same-target calls produced distinct filenames. The Feature 042 time-based scheme could collide if two calls hit within the same second — mitigated by the 3-digit `ms` suffix from `microtime(true)`. Test `test_filename_scheme_uses_slug_unix_ms` asserts the `ms` suffix is present. Source: this spec)

## Conflict Warnings

None. `DEC-042-FILENAME-TIME-BASED` supersedes the enumeration-defense corollary of Feature 041's `DEC-BACKUP-URL-RETURN`; the URL-return contract itself is unchanged.

## Retrieval Notes

- Optimizer not enabled — markdown-only, index-first retrieval used.
- Read references at planning time: reference `Plugins/Update_Check.php` for `get_core_updates()` shape; `Plugins/Plugin_Update.php` + `Themes/Theme_Update.php` for the result-interpretation ladder; `FileManager/Category_Registrar.php` for the Category_Registrar template; WP core `Core_Upgrader`, `WP_Ajax_Upgrader_Skin`; existing utilities `Backups_Storage`, `File_Mods_Guard`.
- Full durable-memory reads: NOT performed. Budget status: within limits (~750 words, under the 900-word cap).

# Feature Specification: Backup / restore abilities + plugin/theme update abilities

**Feature Branch**: `041-backup-restore-abilities-and-updates`
**Created**: 2026-07-17
**Status**: Draft (backfilled post-implementation — reflects shipped code across PRs #71 / #72 / #73 / #74; released as 0.0.9 + 0.0.10)
**Input**: User description: (multi-turn) — "create a abilites that can create zip on the server or in the local then upload it and then unzip it back so if some one want to take a backup of some folder , plugin, themes in local or in live site it can be done" → "will be mention when running the abilities also create for plugin, themes, upload, mu-plugins etc use this 'file-manager'" → "add this plugin-update or theme-update ability as well this will be under plugins" → "do we have upload and download zip abilities?" → (during code review) "check /Users/…/download-plugin — udpate the Backups abialites form that and if all is good then leave it"

## Clarifications

### Session 2026-07-17 (planning)

- Q: What should the abilities be able to zip/restore? → A: plugin, theme, arbitrary folder, uploads / mu-plugins — all four, with the target passed at run time via one generic ability pair rather than N target-specific abilities.
- Q: How should the zip be transferred once created? → A: Return download URL only (Recommended). Zip lands in `wp-content/uploads/acrossai-backups/`, caller pulls it via URL, uses a separate `zip-extract` on the destination site.
- Q: Where should backup zips be stored on the server? → A: `wp-content/uploads/acrossai-backups/` + return URL. Hardened `.htaccess` blocks PHP execution but permits `.zip` downloads (required so the returned URL is reachable).
- Q: Should I ask you to run /speckit-specify + /speckit-plan to formalize this? → A: Draft inline ability plan only. Spec-kit artifacts get backfilled later (see Feature 042 plan).
- Q: Do you want a dedicated Zip_Upload ability? → A: Yes, plus Zip_Download, Zip_List, Zip_Delete — total six FileManager Zip abilities.

### Session 2026-07-17 (during release-eve review)

- Q: The reference `download-plugin` skips hidden files by checking every segment of the relative path; Zip_Create only checks the current entry basename. Fix? → A: Apply the per-segment check. Shipped as 0.0.10 (PR #73 + release PR #74).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Cross-site backup / restore of a plugin (Priority: P1)

An administrator running local WordPress site A wants to duplicate a plugin's state (or roll back a plugin) onto live WordPress site B. Through an AI / MCP client they call `zip-create` on site A with `target_type=plugin, target=hello-dolly`; the response returns a `file_url` under `wp-content/uploads/acrossai-backups/`. The AI downloads the archive, uploads it to site B via `zip-upload` (chunked, base64, or by URL), and calls `zip-extract` on site B pointing at the uploaded file, with `target_type=plugin, target=hello-dolly`. Site B's `wp-content/plugins/hello-dolly/` now mirrors site A's.

**Why this priority**: Cross-site plugin/theme mobility is the headline story that motivates the whole feature. Everything else builds on this flow.

**Independent Test**: Zip Hello Dolly on Local site 1 → download via returned URL → upload via Zip_Upload on Local site 2 → run Zip_Extract → verify the extracted directory tree matches site 1's byte-for-byte (excluding hidden files when `include_hidden=false`).

**Acceptance Scenarios**:

1. **Given** Hello Dolly is installed on site A, **When** `zip-create {target_type:"plugin", target:"hello-dolly"}` is called, **Then** the response includes `success=true`, `file_path` under `wp-content/uploads/acrossai-backups/`, a fetchable `file_url`, and a non-empty `sha256`.
2. **Given** the returned `file_url` is downloaded, **When** `zip-upload {url:"…"}` is called on site B, **Then** the response includes `finalized=true` and a new `file_path` under acrossai-backups on site B.
3. **Given** the uploaded `file_path` on site B, **When** `zip-extract {source:{path:"…"}, target_type:"plugin", target:"hello-dolly"}` is called, **Then** the response reports `files_count > 0` and every file lands under `wp-content/plugins/hello-dolly/`.

### User Story 2 — In-place plugin / theme update through the Abilities API (Priority: P2)

Same admin wants to apply pending plugin / theme updates via the Abilities API rather than the WP dashboard. `plugin-update {slugs:["hello-dolly/hello.php"]}` upgrades that specific plugin using WP core's `Plugin_Upgrader::bulk_upgrade()`. `theme-update` does the same for themes. Both are idempotent — re-running on a plugin/theme with no available update returns success + `updated_count: 0`.

**Why this priority**: `Update_Check` was already reporting availability across core + plugins + themes, but no ability could actually *apply* an update. This closes an obvious loop.

**Independent Test**: Pin a bundled plugin to an older version → confirm `update-check` shows the update → run `plugin-update` → verify version bumps.

### User Story 3 — Backup dir hygiene (Priority: P3)

The AI needs to list previously-created backups, download an older one, or delete stale ones. `zip-list`, `zip-download`, and `zip-delete` cover this loop without needing to leave the abilities surface.

**Independent Test**: Create 3 backups → `zip-list` returns all three newest-first → `zip-delete` on one → `zip-list` returns two → `zip-download` on a listed `file_path` returns a fresh URL + matching `sha256`.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-041-001**: `zip-create` MUST accept one of `target_type ∈ {plugin, theme, uploads, mu-plugins, path}` and MUST write the archive to `wp-content/uploads/acrossai-backups/` with a filename that does not reveal predictable content. (Filename scheme rebalanced in Feature 042.)
- **FR-041-002**: `zip-create` MUST enforce a configurable maximum archive size (default 512 MB) via `acrossai_abilities_manager_zip_max_bytes` and MUST reject over-size input before writing bytes to disk.
- **FR-041-003**: `zip-create` MUST accept an `include_hidden` flag; when `false`, files under any hidden path segment (`.git/objects/…`, `.svn/…`, etc.) MUST NOT appear in the archive. **(Regression fixed in 0.0.10 — original 0.0.9 implementation only skipped the top-level hidden entry, not the descent.)**
- **FR-041-004**: `zip-upload` MUST accept base64, remote URL, or chunked (session/index/is_final) modes, MUST validate the finalized bytes start with `PK\x03\x04` / `PK\x05\x06` / `PK\x07\x08`, and MUST reject non-zip payloads.
- **FR-041-005**: `zip-extract` MUST audit every zip entry for `..` segments, absolute paths, backslashes, and null bytes BEFORE extraction; any offending entry MUST fail the operation without writing files.
- **FR-041-006**: `zip-download`, `zip-list`, `zip-delete` MUST resolve their `file_path` input inside `wp-content/uploads/acrossai-backups/` or `wp-content/uploads/acrossai-staging/`; any path outside those two directories MUST be rejected with a clean error.
- **FR-041-007**: `plugin-update` MUST wrap `Plugin_Upgrader::bulk_upgrade()`; `theme-update` MUST wrap `Theme_Upgrader::bulk_upgrade()`. Neither ability MAY implement its own HTTP fetch or integrity verification.
- **FR-041-008**: All mutating abilities (`zip-upload`, `zip-extract`, `zip-delete`, `plugin-update`, `theme-update`) MUST short-circuit via `File_Mods_Guard::blocked_response('install')` when `DISALLOW_FILE_MODS` is set.
- **FR-041-009**: `plugin-update` MUST require `update_plugins` in addition to `manage_options`; `theme-update` MUST require `update_themes` in addition to `manage_options`. Read-only Zip abilities require `manage_options` alone.
- **FR-041-010**: The `acrossai-backups/` directory MUST be created on first use with an `.htaccess` that blocks execution of PHP-family extensions (`.php`, `.phtml`, `.phar`, `.pl`, `.py`, `.jsp`, `.asp`, `.htm`, `.html`, `.shtml`) but MUST NOT globally deny access (`.zip` downloads must remain reachable so `zip-create`'s returned `file_url` works).

### Non-Functional Requirements

- **NFR-041-001**: Every new ability MUST match the existing Ability_Definition shape (namespace, `use` ordering, docblock style, `meta.acrossai` block layout).
- **NFR-041-002**: Every new class MUST pass PHPStan L8 and PHPCS strict (WPCS) with zero errors.
- **NFR-041-003**: Tests MUST use the existing source-inspection pattern (mirrors Feature 046's Absorbed test suite) — no full WP runtime dependency.

## Success Criteria

- **SC-041-001**: 8 new abilities visible in the Abilities Library (6 FileManager Zip_* + Plugin_Update + Theme_Update) under the correct tab groups.
- **SC-041-002**: `composer run phpstan` zero errors at L8; `composer run phpcs` zero errors; `composer test` all pass (144 tests / 468 assertions post-0.0.10).
- **SC-041-003**: A hand-crafted zip-slip archive (`../evil.php`) fails `zip-extract` with a clean error message and NO files written outside the target directory.
- **SC-041-004**: `DISALLOW_FILE_MODS=true` in wp-config short-circuits all 5 mutating abilities with the guard's `{success:false, message}` envelope.
- **SC-041-005**: A plugin dir containing `.git/objects/xxx` yields an archive with ZERO `.git/` entries when `include_hidden=false` (Regression fixed in 0.0.10).

## Out of Scope

- WordPress core update (deferred to Feature 042).
- Restore of an entire site (backup surface is per-target, not whole-site).
- Bundled `Base_Backup_Adapter` abstraction — download-plugin's per-context Base classes are inspiration, not a template we adopt.
- Server-to-server push (only URL-based transfer + local extract are shipped; that model is documented as a "possible future addition" in memory-synthesis).

## Fixes

### 2026-07-18 (0.0.10 — PRs #73 + #74)

- **Bug**: `Zip_Create`'s `include_hidden=false` branch only checked the current entry's basename against the leading-dot rule. `RecursiveIteratorIterator::SELF_FIRST` does not stop descent when the outer `foreach` runs `continue`, so files INSIDE a hidden directory still got added because their basenames don't start with `.`.
- **Fix**: Check every segment of the entry's forward-slashed relative path. Applied to both `append_dir_to_zip()` and `estimate_tree_size()`. Two small helpers extracted (`normalize_relative()`, `has_hidden_segment()`), mirroring the pattern used by the reference `download-plugin/app/Plugins/Base.php`.
- **Migration**: Users who called `zip-create` with `include_hidden=false` on 0.0.9 against source trees containing hidden directories should regenerate those archives.

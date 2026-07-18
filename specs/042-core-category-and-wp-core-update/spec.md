# Feature Specification: Core category (WP core update abilities) + backup filename scheme

**Feature Branch**: `042-core-category-and-wp-core-update`
**Created**: 2026-07-18
**Status**: Draft
**Input**: User description: "when zip is getting create make sure to name the same as slug-strtotime.zip and also creat a new abilitest what can check for wordpress update and if possible udpate wordpress abilites too make sure to use the wordpress functions only for the core create a new tab call Core and also a new caterioures with new folder name as core here /Users/…/includes/Abilities"

## Clarifications

### Session 2026-07-18

- Q: Two backups of the same target created in the same second would collide with a pure `{slug}-{unix-timestamp}.zip` scheme (second overwrites first) — how to guard? → A: Append microtime — `{slug}-{unix}-{ms}.zip`. Keeps the timestamp-based naming; adds three digits of milliseconds so back-to-back calls produce distinct filenames.
- Q: What update flow should the new WP core update ability follow? → A: Both — default to latest, allow version override. Optional `version` (+ optional `locale`) input; when omitted, upgrades to the first `response=upgrade` offer from `get_core_updates()`.
- Q: Spec-kit backfill scope for Feature 041 + the 0.0.10 fix? → A: Full backfill: create `specs/041-backup-restore-abilities-and-updates/` with the whole artifact set; the 0.0.10 fix becomes a `Fixes` section inside 041's spec + tasks.
- Q: Feature number for this new work? → A: 042 — next sequential (fills the gap; 041 gets its backfilled folder in the same round).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — WordPress core update through the Abilities API (Priority: P1)

An administrator (or an AI / MCP client the admin has authorized) wants to apply a pending WordPress core update without leaving the abilities surface. `wp-core-update-check` reports whether an upgrade offer exists. `wp-core-update` applies the offer via WP core's `Core_Upgrader::upgrade()` — the same class the built-in WP dashboard uses. No `version` argument = update to latest offer; supplying `version` (+ optional `locale`) pins the upgrade to a specific offer via `find_core_update()`.

**Why this priority**: The absorbed inventory has no ability that actually **applies** a core update. `Plugins/Update_Check` reports availability across core + plugins + themes but stops at "here's what's available". This closes the loop.

**Independent Test**: On a site pinned to an older WP version, `wp-core-update-check` returns `available=true` with a populated `new_version` + `download` URL. Calling `wp-core-update` (no args) upgrades the site; `get_bloginfo('version')` after the call reflects the new version. Re-calling `wp-core-update` on the now-upgraded site returns `updated=false` with a clean "no core update available" envelope.

**Acceptance Scenarios**:

1. **Given** a site running WordPress N-1, **When** `wp-core-update-check {}` is called, **Then** the response includes `success=true`, `available=true`, `current_version` (N-1), `new_version` (N), and non-empty `download` URL.
2. **Given** the same site, **When** `wp-core-update {}` is called, **Then** WordPress upgrades to N, `updated=true`, `from_version=N-1`, `to_version=N`.
3. **Given** the site is now on the latest version, **When** `wp-core-update {}` is re-called, **Then** the response is `{success:true, updated:false, from_version:N, to_version:N, message:"No core update available."}`.
4. **Given** `wp-core-update {"version": "99.99.99"}` (nonexistent) is called, **Then** the response is a clean error naming the requested version, no partial update occurs.
5. **Given** `DISALLOW_FILE_MODS=true` in wp-config, **When** `wp-core-update {}` is called, **Then** the response is the standard `File_Mods_Guard` envelope (`success=false, message="File modifications are disabled…"`).

### User Story 2 — Time-sortable / human-readable backup filenames (Priority: P2)

The admin browsing `wp-content/uploads/acrossai-backups/` sees filenames like `hello-dolly-1721260800-517.zip` instead of `backup-plugin-hello-dolly-abcXYZ12dEfG.zip`. They can:

- Read what target the backup was of at a glance (`hello-dolly`, `uploads`, `mu-plugins`).
- Sort by filename lexicographically to sort by creation time.
- Know that two back-to-back calls in the same second don't collide (the `-517` millisecond suffix guarantees uniqueness within a second per target).

**Why this priority**: Feature 041 chose `{random-12-chars}` for enumeration defense. The tradeoff is worth revisiting: directory listing is disabled by `.htaccess` on the backups dir, so enumeration-by-listing is already blocked. Enumeration-by-guessing remains a theoretical concern, accepted per the user request.

**Independent Test**: Call `zip-create {target_type:"plugin", target:"hello-dolly"}` twice back-to-back; verify (a) both files exist, (b) filenames match the shape `hello-dolly-\d{10}-\d{3}\.zip`, (c) both filenames differ (different `ms` suffix).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-042-001**: A new Category folder `includes/Abilities/Core/` MUST exist, with a `Category_Registrar.php` registering the slug `acrossai-abilities-manager-core` and label `Acrossai Abilities Manager – Core` (mojibake `â` character preserved to match the existing 17 Category_Registrar labels).
- **FR-042-002**: A new ability `acrossai-abilities-manager/wp-core-update-check` MUST exist under the Core category, tab_group `core`, sub_group `lifecycle`. Read-only (`annotations.readonly=true`). Empty input schema. Output MUST include `current_version`, `available`, `new_version`, `locale`, `response`, `partial_version`, `download`, `php_version`, `mysql_version`, `message`.
- **FR-042-003**: A new ability `acrossai-abilities-manager/wp-core-update` MUST exist under the Core category. Input schema accepts optional `version` (string) and optional `locale` (string); both defaulted (locale → `get_locale()`; version → first `response=upgrade` offer).
- **FR-042-004**: `wp-core-update` MUST wrap `Core_Upgrader::upgrade()` — no bundled updater code, no custom HTTP fetch, no custom integrity verification.
- **FR-042-005**: `wp-core-update`'s `permission_callback` MUST require BOTH `manage_options` AND `update_core`. Its `execute()` MUST additionally short-circuit via `File_Mods_Guard::blocked_response('install')` and MUST include a multisite guard (`is_multisite() && !current_user_can('update_core')` → clean error).
- **FR-042-006**: `wp-core-update` MUST report `from_version` (before the upgrade) and `to_version` (after) using `get_bloginfo('version')`; re-running on a site with no available update MUST return `updated=false` with a clean success envelope (idempotent).
- **FR-042-007**: `Backups_Storage::random_backup_filename()` MUST emit the filename `{slug}-{unix-timestamp}-{ms}.zip` where `{slug}` is `sanitize_key()` of `$target` (falling back to `$target_type`, ultimate fallback `backup`), `{unix-timestamp}` is `(int) floor(microtime(true))`, and `{ms}` is `(int) floor(($now - $unix) * 1000)` padded to 3 digits.
- **FR-042-008**: Both new abilities MUST be instantiated in `AcrossAI_Core_Abilities_Bootstrap::register_abilities()` next to the existing SiteHealth abilities; the Core Category_Registrar MUST be registered in `register_category_callbacks()`.

### Non-Functional Requirements

- **NFR-042-001**: Both new abilities MUST match the exact class shape used by `Plugins/Update_Check` and `Plugins/Plugin_Update` (namespace, `use` ordering, `defined( 'ABSPATH' ) || exit;` line, class docblock, `ability()` array key order, `meta.acrossai` block layout). No creative shape drift.
- **NFR-042-002**: All new code MUST pass PHPStan L8 zero errors and PHPCS strict WPCS zero errors.
- **NFR-042-003**: Test coverage MUST use the source-inspection pattern established by Feature 046 / Feature 041 — no full WP runtime.

## Success Criteria

- **SC-042-001**: A new "Core" tab appears on the Ability Library page with both abilities visible under Core → Lifecycle.
- **SC-042-002**: `composer run phpstan` zero errors at L8, `composer run phpcs` zero errors, `composer test` all pass (target: 153 tests / 504 assertions after adding 9 new 042 tests).
- **SC-042-003**: `zip-create` against `hello-dolly` returns a `file_path` matching `wp-content/uploads/acrossai-backups/hello-dolly-\d{10}-\d{3}\.zip` with NO `backup-plugin-` prefix and NO 12-char random alphanumeric block.
- **SC-042-004**: Two back-to-back `zip-create` calls against the same target produce two distinct filenames.
- **SC-042-005**: `wp-core-update` on a site pinned to an older WP version upgrades to the latest and reports `updated=true` with `from_version` != `to_version`.
- **SC-042-006**: `DISALLOW_FILE_MODS=true` short-circuits `wp-core-update` with the `File_Mods_Guard` envelope; `wp-core-update-check` still works (read-only).

## Out of Scope

- WP core downgrade path (upgrader class only supports upgrades; no `WP_Downgrader` in core).
- Multisite bulk-network-upgrade path (`Core_Upgrader::upgrade()` runs on the current site; network upgrades stay a separate future ability).
- Backup filename change to Zip_Upload's `filename_hint`-derived slugs (piggy-backs on the same helper; already covered because the helper change is internal).
- Full-site backup ability (still per-target; deferred to a future feature).

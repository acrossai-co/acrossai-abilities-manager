# Security Constraints: Feature 042

**Feature**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)

## Threat Model Summary

Feature 042 adds a WordPress-core-upgrade path through the Abilities API and changes the backup filename scheme. Primary threats: (1) unauthorized core upgrade / downgrade / lateral pin to a vulnerable version, (2) core upgrade during `DISALLOW_FILE_MODS` lockdown, (3) upgrade attempted on multisite without network privileges, (4) predictable backup filenames enabling enumeration-by-guessing.

## Constraints (enforced by shipped code)

### C-042-SEC-01 — Both-capability gate on `Wp_Core_Update`

`permission_callback` requires `current_user_can('manage_options') && current_user_can('update_core')`. Both must return true; `manage_options` alone is insufficient. Matches WP core's own admin gate for the "Update WordPress" screen.

### C-042-SEC-02 — File_Mods_Guard on `Wp_Core_Update`

`Wp_Core_Update::execute()` calls `File_Mods_Guard::blocked_response('install')` before any upgrade work. When `DISALLOW_FILE_MODS` is true, the ability returns the standard `{success:false, message:"…"}` envelope and does NOT invoke `Core_Upgrader`.

### C-042-SEC-03 — Multisite guard

`Wp_Core_Update::execute()` bails cleanly with `is_multisite() && ! current_user_can('update_core')` — matches WP core's `admin_page_access_denied()` path in wp-admin/update-core.php. Prevents non-super-admins from triggering a network-wide core upgrade.

### C-042-SEC-04 — No custom HTTP fetch, no custom integrity checks

`Wp_Core_Update` calls `Core_Upgrader::upgrade($update)` exclusively. The `$update` object comes from `get_core_updates()` / `find_core_update()`; both are WP core functions that read from the existing `update_core` transient (populated by WP's own update-check cron, which fetches from `api.wordpress.org` over HTTPS with core's built-in integrity verification). Feature 042 adds ZERO custom HTTP requests and ZERO custom cryptographic verification code.

### C-042-SEC-05 — `Wp_Core_Update_Check` is read-only

`Wp_Core_Update_Check::execute()` does not write to disk, does not modify options, does not touch the plugin/theme registries. Requires only `manage_options` (no `update_core`). Safe to call from any authenticated admin context.

### C-042-SEC-06 — Version + locale inputs sanitized

Both `version` and `locale` inputs pass through `sanitize_text_field()` before use. `find_core_update()` itself validates the version string against the offers WP core received from its update check; garbage input returns `null` and the ability returns a clean "No core update offer found for version …" envelope.

### C-042-SEC-07 — Filename scheme trade-off explicitly accepted

`{slug}-{unix}-{ms}.zip` filenames are predictable given a target + rough time of creation. This removes the enumeration-by-guessing defense the 12-char random suffix provided. Mitigations still in force:

- `Options -Indexes` in `Backups_Storage`'s `.htaccess` — directory listing is blocked.
- `FilesMatch` deny for PHP-family extensions — even if a filename is guessed and the file downloaded, it cannot execute code server-side.
- `manage_options` capability gate on `zip-list` and `zip-download` — programmatic enumeration through the abilities API requires an admin session.

Accepted per user direction (see [Clarifications in spec.md](./spec.md#clarifications)).

### C-042-SEC-08 — No shell execution

Feature 042 uses no `shell_exec` / `exec` / `system` / `proc_*` / backtick operators. All work is WP core PHP APIs.

### C-042-SEC-09 — Filename slug segment sanitized

`Backups_Storage::filename_slug_segment()` runs both `str_replace(['/','\\','.'], '-', $target)` AND `sanitize_key()` before returning. No path-separator smuggling through the slug. Falls back through `target_type` → literal `'backup'` so the filename never becomes just `-{ts}.zip`.

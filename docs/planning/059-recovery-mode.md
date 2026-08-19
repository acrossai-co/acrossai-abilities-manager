# Feature 059 — Recovery Mode / Safe Mode / fatal-error abilities

**Status**: input brief for `/speckit-specify`. Written 2026-07-25.

## Problem

WordPress **Recovery Mode** (WP 5.2+) is the safety net that engages on fatal errors. It pauses the offending plugin/theme and shows a recovery banner in `wp-admin`. Recovery mode's admin UI is discoverable only when someone happens to be logged in mid-fatal — an AI agent driving the site over REST/MCP has no way to see or act on it.

The plugin today has:

- No visibility into recovery-mode state.
- No way to list which extensions WP has paused.
- No way to clear a paused entry.
- No filter to surface fatal errors specifically in `debug.log` (existing `read-debug-log` returns raw text).

## Proposed abilities

7 new abilities under a new `Recovery` category (`includes/Abilities/Recovery/`). Slug convention per `DEC-SLUG-CONVENTION-VERB-FIRST` (Feature 058): `acrossai/<verb>-<subject>`.

### Reads (4)

| Slug | Purpose | Core API |
|---|---|---|
| `recovery/get-recovery-mode-status` | Detect if the site is currently in recovery mode + summary counters. | `wp_is_recovery_mode()`, `wp_paused_plugins()->get_all()`, `wp_paused_themes()->get_all()`, `wp_is_fatal_error_handler_enabled()` |
| `recovery/list-paused-plugins` | Enumerate every paused plugin with its captured error details. | `wp_paused_plugins()->get_all()` + enrichment via `Plugin_Helpers::resolve_plugin()` for the human plugin name |
| `recovery/list-paused-themes` | Symmetric for themes. | `wp_paused_themes()->get_all()` + `Theme_Helpers::resolve_theme()` |
| `recovery/get-recovery-exit-url` | Return the admin-clickable URL that exits recovery mode. Cannot programmatically exit (nonce + cookie guarded); this is the closest we can ship. | `wp_nonce_url( admin_url(), WP_Recovery_Mode::EXIT_ACTION )` — same pattern as `wp_recovery_mode_nag()` in `wp-admin/includes/update.php:1019` |

### Writes (2)

| Slug | Purpose | Core API | Guards |
|---|---|---|---|
| `recovery/unpause-plugin` | Clear the paused-storage entry so WP retries loading the plugin next request. Distinct from `deactivate-plugin` (which only flips `active_plugins`). | `wp_paused_plugins()->delete($slug)` + `Plugin_Helpers::resolve_plugin()` for fuzzy match | `manage_options` + `File_Mods_Guard::blocked_response()` |
| `recovery/unpause-theme` | Symmetric for themes. | `wp_paused_themes()->delete($slug)` + `Theme_Helpers::resolve_theme()` | same |

Annotations: `destructive: true, idempotent: true` (unpausing an already-unpaused extension is a no-op).

### Read (fatal-error filter) (1)

| Slug | Purpose | Notes |
|---|---|---|
| `recovery/list-recent-fatal-errors` | Parse `debug.log` and return only PHP Fatal / Parse / Compile errors, grouped by signature (`type + file + line + message`), with `since_days` and `limit` inputs. | Streams the file line-by-line (don't `file_get_contents` — logs can be huge). Reuses log-path + `WP_DEBUG_LOG`-enabled guard from `Read_Debug_Log.php:90-101`. |

Inputs:
- `since_days` (int, default 7, max 90)
- `limit` (int, default 20, max 200)

Output shape:
```json
[
  {
    "first_seen": "2026-07-20T14:32:11+00:00",
    "last_seen":  "2026-07-24T09:15:03+00:00",
    "count":      7,
    "type":       "Fatal Error",
    "message":    "Uncaught Error: Call to undefined function foo()",
    "file":       "/path/to/plugin/thing.php",
    "line":       42,
    "sample_stack": ["#0 /path/to/plugin/other.php(15): thing_do()", "..."]
  }
]
```

Sorted by `last_seen` desc.

## Documentation-only tweaks (no code change)

Append one sentence to the `description` of four existing abilities so agents know they work in recovery mode:

- `plugins/activate-plugin`, `plugins/deactivate-plugin`
- `themes/activate-theme`, `acrossai/deactivate-theme`

Text: *"Works in recovery mode; only updates the active-plugins/themes option, does not load the extension file."*

## Explicitly dropped from scope

- **"Trigger recovery mode programmatically"** — WP core has NO public API. The recovery-mode handler only fires when a real fatal error hits a protected endpoint (admin / login / protected AJAX). Cannot be manually triggered from REST.
- **"Exit recovery mode programmatically"** — the exit action is guarded by both a cookie (from the recovery-mode entry link) and a nonce. A normal admin REST call can't satisfy those. Replaced by `get-recovery-exit-url` above; an admin (or an agent driving a browser) clicks that URL to exit.

## Existing utilities to reuse

| Utility | Path | Reuse for |
|---|---|---|
| `Ability_Definition` base class | `includes/Modules/Library/Ability_Definition.php` | Every new ability extends this |
| `Plugin_Helpers::resolve_plugin()` | `includes/Abilities/Utilities/Plugin_Helpers.php:100` | Fuzzy plugin-slug resolution for `Unpause_Plugin` + name enrichment in `List_Paused_Plugins` |
| `Theme_Helpers::resolve_theme()` | `includes/Abilities/Utilities/Theme_Helpers.php:93` | Symmetric for themes |
| `File_Mods_Guard::blocked_response()` | (used in `Clear_Debug_Log.php:82`) | Guards `Unpause_Plugin` + `Unpause_Theme` — same convention WP core uses on its paused-extension admin actions |
| Log-path + `WP_DEBUG_LOG` guard | `Read_Debug_Log.php:90-101` | Copy the guard shape into `List_Recent_Fatal_Errors` (single re-user; no shared helper needed) |
| `meta.acrossai` metadata pattern | Feature 041 convention | New abilities set `sub_group: 'recovery'` under a `tab_group: 'core'` (or new `tab_group: 'recovery'` — TBD in the spec) |

## Bootstrap wiring

Two additions to `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php`:

- One `$loader->add_action( 'wp_abilities_api_categories_init', Recovery\Category_Registrar::instance(), 'register' );` line (matching the 17 existing per-category calls).
- Seven `new Recovery\<Class>();` lines in `register_abilities()` matching the existing per-category block pattern (e.g. `Settings` block at lines 118-124).

No changes to `Main.php`, `AcrossAI_Activator.php`, or the REST-controller stack — abilities auto-register through the existing bootstrap.

## Testing

- PHPUnit — one test class per ability under `tests/phpunit/abilities/Recovery/`. Cover: happy path, no-paused-extensions case, unknown-slug case for unpause, `DISALLOW_FILE_MODS` blocked case, empty-debug-log case for the fatal filter.
- Manual verification (recovery mode requires an actual fatal on a protected endpoint — can't unit-test): force a fatal in a test plugin, trigger recovery via any admin page load, then exercise the 7 abilities in order (`get-recovery-mode-status` → `list-paused-plugins` → `unpause-plugin` → `list-recent-fatal-errors`).

## Rollout

- Ships as v0.0.17.
- No breaking change; no data migration; no version bumps outside version constant + README stable-tag + plugin header.
- Optional durable memory capture: append `DEC-RECOVERY-MODE-EXPOSURE` to `docs/memory/DECISIONS.md` recording (a) "trigger recovery mode" is intentionally not exposed because WP core has no public API, and (b) "exit" is exposed only as a URL, not as a direct action, because of nonce+cookie constraints.

## References

- WP core `wp-includes/error-protection.php` — public accessor functions (`wp_recovery_mode`, `wp_paused_plugins`, `wp_paused_themes`, `wp_is_recovery_mode`, `wp_is_fatal_error_handler_enabled`).
- WP core `wp-includes/class-wp-recovery-mode.php` — main `WP_Recovery_Mode` class.
- WP core `wp-includes/class-wp-paused-extensions-storage.php` — `WP_Paused_Extensions_Storage` (methods: `set`, `delete`, `get`, `get_all`, `delete_all`).
- WP core `wp-admin/includes/update.php:1019` — `wp_recovery_mode_nag()` (reference for the exit-URL construction).
- WP core `wp-admin/includes/plugin.php:2555` — `paused_plugins_notice()` (reference for the admin-side capability check pattern).
- Approved plan for this feature: `~/.claude/plans/can-you-check-the-synchronous-pixel.md`.

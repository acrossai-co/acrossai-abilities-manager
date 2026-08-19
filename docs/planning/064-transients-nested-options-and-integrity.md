# Feature 064 — Transient CRUD, nested option access, plugin lifecycle & checksum integrity

**Status**: input brief for `/speckit-specify`. Written 2026-08-11.

## Problem

Three shape-completeness gaps in the current ability surface:

1. **Transients** — we expose `flush-transients` (bulk delete every transient) but no way to read, list, delete-one-by-name, or delete-expired-only. Any workflow that inspects or surgically manages transient state today has to fall back to raw SQL via `run-db-select-query`.
2. **Nested option access** — `get-option` returns the whole serialized blob for large options (e.g. WooCommerce settings, membership rulesets), forcing the client to deserialize server-side outputs client-side. There is no way to read or mutate a single nested key.
3. **Post-meta append semantics** — `update-post-meta` replaces every row for the key; `delete-post-meta` removes rows. There is no ability corresponding to WordPress core `add_post_meta()` (append a new row without touching existing rows) — required for post-meta fields that legitimately store multiple values per key.
4. **Plugin lifecycle & integrity** — no WP.org plugin directory search, no plugin uninstall (only deactivate), no core or plugin file-integrity check against the official WordPress.org checksums API. These are common ops for site auditing and lifecycle management.

This feature adds 11 new abilities filling all four gaps. `manage_options` remains the sole access gate.

## Proposed abilities

Slug convention per `DEC-SLUG-CONVENTION-VERB-FIRST` (Feature 058): `acrossai/<verb>-<subject>`.

### Transient CRUD (4) — under `Cache/` category

| Slug | Purpose | Core API | Notes |
|---|---|---|---|
| `cache/get-transient` | Return one transient value + expiry. Distinguishes "unset" from `value === false`. | `get_transient( $key )` or `get_site_transient( $key )` when `site: true` | Input: `{ key: string, site?: bool = false }`. Output: `{ success, exists: bool, value, expires_at?: int }`. |
| `cache/list-transients` | Enumerate every transient (or site-transient) with expiry. Capped at 100 rows by default. | `SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'` — join in expiry via companion `_transient_timeout_<key>` rows. | Input: `{ search?: string, limit?: int = 100 (max 500), offset?: int = 0, site_only?: bool = false, include_expired?: bool = true }`. Output: `{ success, transients: [{ name, expires_at?, is_site, is_expired }], count, total }`. |
| `cache/delete-transient` | Delete one transient by name. `destructive: true, idempotent: true`. | `delete_transient( $key )` or `delete_site_transient` when `site: true` | Input: `{ key: string, site?: bool = false }`. |
| `cache/delete-expired-transients` | Purge every expired transient in a single pass. `destructive: true`. | `delete_expired_transients()` (WP core function) | No input. Output: `{ success, deleted: int, message }`. |

### Nested option access (2) — under `Options/` category

| Slug | Purpose | Core API | Notes |
|---|---|---|---|
| `options/get-nested-option-value` | Read one nested key inside a serialized-array option without returning the whole blob. | `get_option( $option )` + array/object path walk. | Input: `{ option: string, path: string[] }`. Output: `{ success, exists: bool, value }`. `readonly: true`. |
| `options/patch-option-value` | Mutate one nested key inside a serialized option (insert / update / delete). | `get_option()` → walk to parent → mutate → `update_option()`. | Input: `{ option: string, operation: 'insert'\|'update'\|'delete', path: string[], value?: mixed }`. Guard: reject any option in the existing block-list used by `update-option` (reuse the `Options` category's blocked-options constant if one exists; otherwise mirror `Update_Option.php`'s guardrails). `destructive: true`. |

### Post-meta append (1) — under `Content/` category

| Slug | Purpose | Core API | Notes |
|---|---|---|---|
| `content/add-post-meta` | WordPress `add_post_meta()` semantics — append a new meta row (does not touch existing rows for the same key). Complements the existing replace/delete verbs. | `add_post_meta( $post_id, $key, $value, $unique )` | Input mirrors `Update_Post_Meta.php` (`post_id`, `key`/`meta_key`, `value`/`meta_value`) plus `unique?: bool = false`. Output: `{ success, meta_id: int\|false, message }`. Not `destructive: true` — additive semantics. |

### Plugin lifecycle & integrity (4) — under `Plugins/` (3) and `Core/` (1)

| Slug | Category | Purpose | Core API |
|---|---|---|---|
| `plugins/search-wp-plugin-directory` | `Plugins` | Search the WordPress.org plugin directory. | `plugins_api( 'query_plugins', ['search' => $q, 'per_page' => $n, 'page' => $p, 'fields' => ['slug','name','short_description','rating','downloaded','active_installs','homepage','download_link']] )` — must `require_once ABSPATH . 'wp-admin/includes/plugin-install.php';` first (REST context does not preload it). Output: `{ success, plugins: [...], info: { page, pages, results } }`. Read-only. |
| `plugins/uninstall-plugin` | `Plugins` | Uninstall a plugin (fires uninstall hook + deletes files). Distinct from `delete-plugin` (files-only) — this triggers the plugin's own cleanup. | `uninstall_plugin( $plugin )` from `wp-admin/includes/plugin.php`. Requires `Plugin_Helpers::resolve_plugin()` for fuzzy slug/file matching. Input: `{ plugin: string (slug or file), delete_data?: bool = true }`. Guards: (a) refuse if plugin is currently active (return actionable message pointing to `plugins/deactivate-plugin`); (b) honor `DISALLOW_FILE_MODS` via `File_Mods_Guard::blocked_response()` (same pattern used by `Install_Plugin.php`). `destructive: true`. |
| `plugins/verify-plugin-checksums` | `Plugins` | Verify plugin files against official WordPress.org checksums. | Fetch `https://api.wordpress.org/plugins/checksums/1.0/?plugin=<slug>&version=<v>` via `wp_remote_get()`; walk plugin dir and hash every file; diff. Input: `{ plugin: string, strict?: bool = false }`. Output: `{ success, plugin, version, results: [{ file, expected, actual, status: 'ok'\|'modified'\|'missing'\|'added' }], summary: { total, ok, modified, missing, added } }`. `strict: true` treats "added" files as failures too. Read-only. |
| `core/verify-core-checksums` | `Core` | Same as above for WordPress core files. | `get_core_checksums( $version, $locale )` from `wp-admin/includes/update.php` (already used by WP's own checksums command). Walk `ABSPATH` and diff. Input: `{ version?: string (defaults to installed), locale?: string (defaults to 'en_US'), include_root?: bool = false, exclude?: string[] }`. Output shape same as `verify-plugin-checksums`. Read-only. |

## Reused utilities (do not reinvent)

- **`Ability_Definition`** parent class.
- **`Plugin_Helpers::resolve_plugin()`** — fuzzy plugin slug/file resolution; already used by Recovery abilities (`includes/Abilities/Recovery/Unpause_Plugin.php`).
- **`File_Mods_Guard`** — `DISALLOW_FILE_MODS` gate; already used by `Install_Plugin.php`, `Delete_Theme.php`.
- **`Update_Option.php`** — mirror its `BLOCKED_OPTIONS` allowlist / block-list for `patch-option-value`.
- **`Update_Post_Meta.php`** input schema shape — mirror verbatim for `add-post-meta` (same `key`/`meta_key` and `value`/`meta_value` aliases pattern).

## Common shape (all 11)

- Correct namespace per category directory.
- `permission_callback => static function (): bool { return current_user_can( 'manage_options' ); }` — LITERAL, verbatim, per the plugin's established convention (identical to all 219 existing abilities).
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.
- `meta.acrossai.sub_group` — `'cache'` (4), `'options'` (2), `'posts'` (1), `'plugins'` (3), `'core'` (1).
- All string inputs sanitized with `sanitize_text_field()`.
- All returned messages wrapped in `__( '...', 'acrossai-abilities-manager' )`.
- `readonly: true` for the two verify-checksums abilities, the two transient reads, the nested-option read, and `search-wp-plugin-directory`.
- `destructive: true` for `delete-transient`, `delete-expired-transients`, `patch-option-value`, `uninstall-plugin`.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` only (no new category registrar):

- 4 new `new Cache\<Class>();` lines inside the existing Cache block (currently lines 140–142).
- 2 new `new Options\<Class>();` lines inside the existing Options block (currently lines 273–277).
- 1 new `new Content\Add_Post_Meta();` line inside the existing Content block, adjacent to `Update_Post_Meta` and the recently added `Delete_Post_Meta` (currently line 215–216).
- 3 new `new Plugins\<Class>();` lines inside the existing Plugins block (currently lines 111–116 and 156–158).
- 1 new `new Core\Verify_Core_Checksums();` line inside the existing Core block (currently lines 295–298).

## Testing

Under `tests/phpunit/abilities/`, one test file per ability. Golden-path + guardrail tests:
- Transients: seed a transient via `set_transient()` in `setUp()`; `get-transient` returns the exact value + `exists: true`; `delete-transient` clears it; `list-transients` returns paginated results including seeded rows.
- Nested options: seed a complex option via `update_option( 'test_opt', ['a' => ['b' => 'c']] )`; `get-nested-option-value` with `path: ['a', 'b']` returns `'c'`; `patch-option-value` with `operation: update, path: ['a', 'b'], value: 'd'` mutates only the target key.
- `add-post-meta` with `unique: true` twice → second call returns `meta_id: false` (append blocked).
- `uninstall-plugin` against an active plugin → rejected with actionable message.
- `verify-core-checksums` mocks the HTTP fetch via `add_filter('pre_http_request', ...)` and verifies expected diff output.

For plugin/core-checksums, HTTP layer mocked via `pre_http_request` filter (same pattern used in `tests/phpunit/abilities/Test_Feature_042_Core_Update.php`).

Target: ~11 golden-path tests + ~10 guardrail tests.

## Delivery

Feature branch off `main`, no version bump — will be rolled into a single `release-0.0.23` alongside features 062 and 063. See `/Users/raftaar1191/.claude/plans/prepare-a-plan-for-refactored-fern.md` for the unified release plan.

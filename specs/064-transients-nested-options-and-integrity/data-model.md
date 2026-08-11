# Phase 1 Data Model: Transient CRUD, nested option access, plugin lifecycle & checksum integrity

**Status**: No new persistent entities. Every write mutates an existing WordPress-managed store.

## Entities (all existing, WordPress-managed)

### Transient

- **Storage**: Blog-scope in `wp_options` (rows `_transient_<key>` / `_transient_timeout_<key>`); site-scope in `wp_sitemeta` (on multisite) or `wp_options` (single-site) (`_site_transient_<key>` / `_site_transient_timeout_<key>`). May also be served from an external object cache without touching SQL.
- **Attributes**: name, value, expiry (unix timestamp), scope (blog vs site).
- **Validation**:
  - Name MUST be a non-empty string.
  - Scope is caller-supplied via `site: bool` on read/delete; auto-detected by `list-transients` output (each row carries `is_site`).
- **Mutations from this feature**:
  - Read: `get-transient` (single), `list-transients` (paginated enumeration).
  - Delete: `delete-transient` (single by name), `delete-expired-transients` (bulk by expiry).

### Serialized Option

- **Storage**: `wp_options.option_value`.
- **Attributes**: name, value (PHP-serialized).
- **Validation**:
  - Name MUST NOT be in the block-list (mirrors `Update_Option::BLOCKED_OPTIONS`).
  - `path` MUST be a non-empty array of strings.
  - Every intermediate value along `path` MUST be array-like (array or object) — mutations refuse when traversing into scalars.
- **Mutations from this feature**:
  - Read: `get-nested-option-value` (walks path, returns leaf value + exists flag).
  - Write: `patch-option-value` (insert / update / delete one nested value).

### Post Meta Row

- **Storage**: `wp_postmeta`.
- **Attributes**: `post_id`, `meta_key`, `meta_value` (may repeat with same key).
- **Validation**:
  - `post_id` MUST reference an existing post.
  - When `unique: true` and any row already exists for the `(post_id, key)` pair, the append is refused (`meta_id: false` per WP core `add_post_meta( ..., true )`).
- **Mutations from this feature**:
  - `add-post-meta` appends one row.

### Plugin Directory Listing (ephemeral)

- **Storage**: none — data comes from wordpress.org over HTTP per call.
- **Attributes**: slug, name, short_description (HTML), rating (0–100), active_installs, homepage, download_link, pagination metadata.
- **Validation**: none on the caller side; input is a search query string.
- **Mutations from this feature**: none — read-only.

### Plugin File (existing on disk)

- **Storage**: filesystem, under `WP_PLUGIN_DIR`.
- **Attributes**: file path (relative to plugin dir), content, md5 hash (computed).
- **Validation**: identity resolved via `Plugin_Helpers::resolve_plugin()`.
- **Mutations from this feature**:
  - `uninstall-plugin` deletes all files after firing the uninstall hook.
  - `verify-plugin-checksums` reads and hashes only.

### Core File (existing on disk)

- **Storage**: filesystem, under `ABSPATH`.
- **Attributes**: file path (relative to ABSPATH), content, md5 hash.
- **Mutations from this feature**:
  - `verify-core-checksums` reads and hashes only.

### Checksum Result (ephemeral)

- **Storage**: none — computed per invocation.
- **Attributes**: per-file `{ file, expected, actual, status: 'ok'|'modified'|'missing'|'added' }` + summary counters.
- **Mutations**: none — pure read.

## State transitions

None of the write abilities introduce state machines.

## Cross-entity invariants

1. **Transient expiry timestamp MUST NOT exceed the value stored in the companion `_transient_timeout_<key>` row.** WP core enforces this; the abilities do not maintain their own invariants.
2. **A patched option value MUST round-trip through `get_option()` after `update_option()` byte-for-byte identical to the intended structure.** Verified by an integration-style test in `Test_Patch_Option_Value.php`.
3. **`uninstall-plugin` MUST NOT run on a plugin whose `active_plugins` option contains its file path.** Enforced by `is_plugin_active()` guard.

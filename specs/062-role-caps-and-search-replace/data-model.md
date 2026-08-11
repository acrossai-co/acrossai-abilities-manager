# Phase 1 Data Model: Role & capability CRUD + site-wide DB search-replace

**Status**: No new persistent entities. Every entity below is either an existing WordPress core entity that the new abilities read/write in-place, or an ephemeral request/response shape (defined in `contracts/abilities.md`).

## Entities (all existing, WordPress-managed)

### Role

- **Storage**: Serialized `wp_user_roles` option in the site's `wp_options` table. Managed by WordPress core (`add_role()`, `remove_role()`, `WP_Role::add_cap()`, `WP_Role::remove_cap()`).
- **Identity**: Role slug (`administrator`, `editor`, `author`, `contributor`, `subscriber`, or any caller-supplied string).
- **Attributes**:
  - `display_name`: human-readable label.
  - `capabilities`: `{ [cap_name: string]: bool }` — mapping of capability name to grant-boolean.
- **Validation**:
  - Slug MUST be a non-empty string.
  - Slugs in `DEFAULT_ROLES` MUST NOT be deleted (FR-011); MAY be reset (FR-012). Slugs outside `DEFAULT_ROLES` MUST be delete-eligible but MUST NOT be reset-eligible (FR-012).
  - `administrator` role MUST retain every capability in `CORE_ADMIN_CAPS` (FR-013).
- **Mutations from this feature**:
  - `add-role-capability`: adds one `(cap_name → true)` entry.
  - `remove-role-capability`: removes one entry (subject to `CORE_ADMIN_CAPS` guard when role is `administrator`).
  - `create-role`: creates a new entry with a fresh capability set (empty or cloned from another role).
  - `delete-role`: removes an entry entirely.
  - `reset-role`: removes the entry, then re-invokes WP core `populate_roles()` to re-seed the WP-core defaults for that slug.

### Capability

- **Storage**: WordPress-core-defined caps live inside `Role.capabilities`; plugin-defined caps live wherever their owning plugin puts them (typically also inside role capability arrays).
- **Identity**: String name (`edit_posts`, `manage_options`, `custom_plugin_cap`, etc.).
- **Attributes**: None beyond the name — capabilities are pure identifiers.
- **Validation**:
  - Name MUST be a non-empty string.
  - No character restrictions (WordPress core accepts any string).
- **Mutations from this feature**: Handled through the Role and User entities — capabilities themselves are not first-class persistent objects.

### User

- **Storage**: WordPress core `wp_users` table + `wp_usermeta` for per-user capability overrides. Managed by WP core (`WP_User::add_cap()`, `WP_User::remove_cap()`).
- **Identity**: Numeric user ID (accepted also by login name or email for input convenience via existing WP core helpers).
- **Attributes**:
  - `ID`: numeric identifier.
  - `roles`: array of role slugs.
  - `allcaps`: aggregated capability grants (role-derived + per-user overrides).
- **Validation**:
  - User MUST exist (probe via `get_userdata()`).
  - If the target user is the last remaining administrator AND the capability being revoked is in `CORE_ADMIN_CAPS`, the revoke MUST be refused (FR-014).
- **Mutations from this feature**:
  - `add-user-capability`: adds one per-user capability override.
  - `remove-user-capability`: removes one per-user capability override (subject to last-admin guard).

### Search-Replace Operation (ephemeral)

- **Storage**: None — this is a one-shot request/response, not a persisted entity.
- **Identity**: Not identified; each invocation is independent.
- **Attributes** (input):
  - `old`: non-empty source string.
  - `new`: target string (may be empty).
  - `tables`: optional list of table names to scope the operation to.
  - `skip_tables`: optional list of table names to exclude.
  - `skip_columns`: optional list of column names to exclude from every table.
  - `include_guids`: boolean (default `false`) — opt-in to including `wp_posts.guid`.
  - `all_tables`: boolean (default `false`) — when `true`, includes non-prefixed tables.
  - `dry_run`: boolean (default `true`) — safe-by-default.
- **Attributes** (output):
  - `success`: boolean.
  - `dry_run`: boolean (echoes input, so callers can confirm which mode ran).
  - `results`: array of `{ table, column, matches, replaced }` per (table, column) that was scanned.
  - `summary`: `{ tables_scanned, rows_matched, rows_replaced }`.
  - `message`: human-readable summary.
- **Validation**:
  - `old` MUST be non-empty (FR-008 is meaningful only for a non-empty source pattern).
  - Every entry in `tables` MUST exist per `$wpdb->get_col('SHOW TABLES')` (FR-016).
  - When `include_guids` is `false` (default), `wp_posts.guid` MUST NOT be modified regardless of the value of `old` (FR-018).
  - When `dry_run` is `true` (default), no `$wpdb->update()` calls are executed (FR-015).

## State transitions

None of the new abilities introduce state machines. Every mutation is a single-transaction WP-core call.

## Cross-entity invariants

1. **Every role slug in `Role.capabilities.keys()` MUST be a valid WordPress capability name** — but no runtime validation is required because WP core accepts any string; the abilities do not restrict caller-supplied capability names beyond the safety block-list (`CORE_ADMIN_CAPS` guard on the administrator role only).
2. **The site MUST always have at least one administrator user with the full `CORE_ADMIN_CAPS` set** — enforced by the last-admin guard in `Remove_User_Capability` (FR-014).
3. **The site's five built-in roles MUST always exist** — enforced by the `DEFAULT_ROLES` refuse-delete guard (FR-011).

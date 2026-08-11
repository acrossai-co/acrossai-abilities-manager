# Feature 062 — Role & capability CRUD + DB search-replace

**Status**: input brief for `/speckit-specify`. Written 2026-08-11.

## Problem

The plugin currently exposes 219 abilities but has two significant gaps in the site-management surface that WordPress core REST does not cover either:

1. **Role & capability CRUD is read-only.** We can `list-user-roles` and `get-role-capabilities`, but there is no way to grant/revoke a capability on a role, create or delete a custom role, reset a WP-core role to defaults, or grant/revoke a capability directly on a user. Every real-world site administration workflow that involves adjusting permissions requires a role-editor plugin today; an agent driving the site has no way to make these edits through the ability surface.
2. **No site-wide DB string replacement.** Migrations, domain changes, and bulk metadata rewrites all require the WP-CLI `search-replace` operation (serialized-data-safe, table-scoped, dry-run-able). We expose `update-db-rows` and `delete-db-rows` for targeted single-table writes but nothing that walks the whole database.

This feature adds 8 new abilities that fill both gaps end-to-end. Every ability is `destructive: true` and carries input guardrails; `manage_options` remains the sole access gate (per the plugin's established convention — same permission callback used by all 219 existing abilities).

## Proposed abilities

Slug convention per `DEC-SLUG-CONVENTION-VERB-FIRST` (Feature 058): `acrossai/<verb>-<subject>`.

### Role & capability CRUD (7) — under `Users/` category

| Slug | Purpose | Core API | Guards |
|---|---|---|---|
| `acrossai/add-role-capability` | Grant one capability to a role. | `WP_Role::add_cap()` via `get_role( $role )` | Role must exist. |
| `acrossai/remove-role-capability` | Revoke one capability from a role. | `WP_Role::remove_cap()` | Refuse if `role === 'administrator'` AND `capability` is in the WP-core administrator baseline (hardcode the well-known ~52-cap list from `wp-admin/includes/schema.php::populate_roles_270()` as `const CORE_ADMIN_CAPS`). Prevents accidentally locking the site owner out. |
| `acrossai/create-role` | Create a new custom role, optionally seeded from an existing one. | `add_role( $slug, $display_name, $caps )` — when `clone_from` is set, seed caps from `get_role($clone_from)->capabilities`. | Slug must not already exist. |
| `acrossai/delete-role` | Remove a role. | `remove_role( $slug )` | (a) Refuse if slug is in `DEFAULT_ROLES` (`administrator/editor/author/contributor/subscriber`); (b) refuse if any user currently holds the role (probe via `get_users(['role' => $slug, 'number' => 1, 'fields' => 'ID'])`). |
| `acrossai/reset-role` | Restore a WP-core role's default capabilities. | `remove_role()` + `populate_roles()` (target-scoped by removing the role first, then re-populating; a single-role `populate_roles_<version>()` helper is not exposed by core, so the "remove-then-re-add via populate_roles()" pattern is the safe approach). | Only allowed for slugs in `DEFAULT_ROLES`. Reject otherwise. |
| `acrossai/add-user-capability` | Grant one capability directly to a user. | `WP_User::add_cap()` | User must exist. |
| `acrossai/remove-user-capability` | Revoke one capability directly from a user. | `WP_User::remove_cap()` | If capability is in `CORE_ADMIN_CAPS` AND the target user is the last remaining administrator, reject. Count via `get_users(['role' => 'administrator', 'fields' => 'ID'])`. |

Annotations for all seven: `destructive: true`. `idempotent: true` for `add-*-capability` / `remove-*-capability` (WP core `add_cap`/`remove_cap` are idempotent); `false` for `create-role` / `delete-role` / `reset-role`.

### DB search-replace (1) — under `Database/` category

| Slug | Purpose | Core API | Guards |
|---|---|---|---|
| `acrossai/search-replace` | Site-wide serialized-data-safe string replacement across the database. Mirrors the input surface of the well-known WP-CLI operation. | `$wpdb->get_results()` per table + `maybe_unserialize()` recursive walk + `maybe_serialize()` + `$wpdb->update()`. | `dry_run: bool = true` (defaults to true — must be explicitly set to false to execute). Table allowlist via `$wpdb->get_col('SHOW TABLES')` mirroring `Update_Db_Rows.php:156–166`. `--skip-columns` / `--skip-tables` honored. `include_guids` defaults to false (safer default than WP-CLI). |

**Input schema:**
```json
{
  "old":            "string (required, non-empty)",
  "new":            "string (required — may be empty)",
  "tables":         "string[] (optional, defaults to every table with wp prefix)",
  "skip_tables":    "string[] (optional)",
  "skip_columns":   "string[] (optional)",
  "include_guids":  "boolean (default false)",
  "all_tables":     "boolean (default false — when true, includes non-prefixed tables)",
  "dry_run":        "boolean (default true)"
}
```

**Output shape:**
```json
{
  "success": true,
  "dry_run": true,
  "results": [
    { "table": "wp_posts", "column": "post_content", "matches": 42, "replaced": 42 },
    { "table": "wp_postmeta", "column": "meta_value", "matches": 3, "replaced": 3 }
  ],
  "summary": { "tables_scanned": 12, "rows_matched": 128, "rows_replaced": 128 }
}
```

Annotations: `destructive: true`, `idempotent: true` when `dry_run === true`, `false` otherwise.

## Reused utilities (do not reinvent)

- **`Ability_Definition`** parent class + auto-hook constructor — every new class extends it and implements `ability(): array` + `execute( array ): array`.
- **`$wpdb->get_col('SHOW TABLES')`** table-existence check — mirror from `includes/Abilities/Database/Update_Db_Rows.php:156–166`.
- **`get_role()`, `wp_roles()`, `WP_User::add_cap/remove_cap`** — already touched in `includes/Abilities/Users/Get_Role_Capabilities.php`, `List_User_Roles.php`, `Update_User.php`. Coding style should match those files.
- **PHPUnit fixtures via `$this->factory->user->create()`** — matches existing test setup.

## Common shape (all 8)

- Namespace `AcrossAI_Abilities_Manager\Includes\Abilities\Users` (7) / `\Database` (1).
- `permission_callback => static function (): bool { return current_user_can( 'manage_options' ); }` — LITERAL, verbatim, no variation.
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.
- `meta.acrossai.sub_group = 'users'` (7) or `'db'` (1).
- All string inputs sanitized with `sanitize_text_field()`.
- All returned messages wrapped in `__( '...', 'acrossai-abilities-manager' )`.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`:
- Add 7 `new Users\<Class>();` lines inside the existing Users block (currently lines 132–139).
- Add 1 `new Database\Search_Replace();` line inside the existing Database block (currently lines 143–151).

No new category registrar needed (both Users and Database already exist).

## Testing

Under `tests/phpunit/abilities/`, one test file per ability (`Test_Add_Role_Capability.php`, etc.). Each extends `WP_UnitTestCase`. Golden-path test + guardrail tests (role-delete-with-active-users → rejected; remove-admin-cap-from-last-admin → rejected; search-replace-with-blocked-table → rejected; search-replace-dry-run does not mutate DB → rows unchanged after execute). Fixtures via `$this->factory->user->create()` and direct `add_role()` calls. Target: ~24 test methods (8 golden-path + ~16 guardrail).

## Delivery

Feature branch off `main`, no version bump in this spec — will be rolled into a single `release-0.0.23` alongside features 063 and 064. See `/Users/raftaar1191/.claude/plans/prepare-a-plan-for-refactored-fern.md` for the unified release plan.

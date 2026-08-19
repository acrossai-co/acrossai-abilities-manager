# Quickstart: Role & capability CRUD + site-wide DB search-replace

Steps to verify the feature end-to-end on the local WP install (`wordpress-7-0`) once implementation is complete.

## Prerequisites

- Local WordPress site at `http://wordpress-7-0.local` with the plugin installed and active.
- Administrator user credentials for the site.
- An application password issued for the administrator user (WP admin → Users → your profile → Application Passwords), used as the REST auth token.

## Manual verification

### 1. Add / remove a role capability

```bash
# Grant manage_options to the editor role
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"role":"editor","capability":"manage_options"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/add-role-capability/run

# Expected: { "success": true, "role": "editor", "capability": "manage_options", ... }

# Verify by hitting the existing read ability
curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/get-role-capabilities/run?role=editor \
  | jq '.capabilities.manage_options'
# Expected: true

# Revoke it
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"role":"editor","capability":"manage_options"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/remove-role-capability/run

# Attempt to revoke a WP-core admin cap FROM the administrator role — must be refused
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"role":"administrator","capability":"manage_options"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/remove-role-capability/run

# Expected: { "success": false, "blocked_reason": "core_admin_cap", ... }
```

### 2. Create / delete a role

```bash
# Create a "support_agent" role cloning from "editor"
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"role":"support_agent","display_name":"Support Agent","clone_from":"editor"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/create-role/run

# Verify in the admin UI: WP admin → Users → Add New → role dropdown should include "Support Agent"

# Assign one user to the role (via existing update-user ability), then attempt to delete — must be refused
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"role":"support_agent"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/delete-role/run

# Expected: { "success": false, "blocked_reason": "role_has_users", "user_count": 1, ... }

# Reassign the user, then delete — should succeed
```

### 3. Reset a WP-core role

```bash
# Deliberately break "editor" first
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"role":"editor","capability":"edit_posts"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/remove-role-capability/run

# Reset
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"role":"editor"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/reset-role/run

# Verify edit_posts is back
curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/get-role-capabilities/run?role=editor \
  | jq '.capabilities.edit_posts'
# Expected: true
```

### 4. Add / remove a per-user capability

```bash
# Grant upload_files to user #2 (a subscriber, normally lacks it)
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"user_id":2,"capability":"upload_files"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/add-user-capability/run

# Log in as user #2 in a separate browser session; the Upload Media button should now be visible.

# Revoke
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"user_id":2,"capability":"upload_files"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/remove-user-capability/run

# Attempt to revoke a WP-core admin cap from the ONLY admin user (there must be exactly one admin on the site for this test)
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"user_id":1,"capability":"manage_options"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/users/remove-user-capability/run

# Expected: { "success": false, "blocked_reason": "last_admin_core_cap", ... }
```

### 5. Search-replace (dry-run then apply)

```bash
# Seed a test string in a post
wp post create --post_title="Test SR" --post_content="Visit http://old-domain.com for details" --porcelain
# → returns the new post ID, remember it as $POSTID

# Dry-run (default)
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"old":"old-domain.com","new":"new-domain.com"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/database/search-replace/run

# Expected:
#   {
#     "success": true,
#     "dry_run": true,
#     "results": [ { "table": "wp_posts", "column": "post_content", "matches": 1, "replaced": 0 }, ... ],
#     "summary": { ..., "rows_matched": ≥1, "rows_replaced": 0 }
#   }

# Verify no row was mutated (dry-run guarantee)
wp post get $POSTID --field=post_content
# Expected output STILL contains "old-domain.com"

# Apply
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"old":"old-domain.com","new":"new-domain.com","dry_run":false}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/database/search-replace/run

# Verify
wp post get $POSTID --field=post_content
# Expected: contains "new-domain.com", no trace of "old-domain.com"

# Attempt against a non-existent table — must be refused
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"old":"foo","new":"bar","tables":["wp_nonexistent"],"dry_run":false}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/database/search-replace/run

# Expected: { "success": false, "blocked_reason": "unknown_table", ... }
```

### 6. Admin UI sanity

- Load `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-abilities-library`.
- Confirm all 8 new abilities appear in the Custom Abilities table:
  - Under sub-group `Users`: 7 role/cap CRUD entries.
  - Under sub-group `Database`: `search-replace`.
- Every row shows `manage_options` as the permission and `destructive: true` in the annotations column.

### 7. Automated tests

From the plugin root:

```bash
composer install
composer run test    # ~16 new PHPUnit methods pass alongside existing suite
composer run phpcs   # zero errors, zero warnings
composer run phpstan # zero errors at level 8
```

CI will run the same three commands across PHP 8.1 through 8.5 automatically on the PR.

# Quickstart: Transient CRUD, nested option access, plugin lifecycle & checksum integrity

## Prerequisites

- Local WordPress site (`http://wordpress-7-0.local`), plugin active.
- Administrator credentials + application password.
- WP-CLI available on the host (used to seed transients / options).

## Manual verification

### 1. Transient CRUD

```bash
# Seed a transient (via WP-CLI)
wp transient set demo_transient "hello" 3600
wp transient set demo_expired "gone" 1
sleep 2

# Read
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"key":"demo_transient"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/cache/get-transient/run
# Expected: { "success": true, "exists": true, "value": "hello", "expires_at": <unix> }

# List (filtered)
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"search":"demo_"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/cache/list-transients/run \
  | jq '.transients[].name'

# Purge expired
curl -u admin:APP_PASSWORD -X POST \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/cache/delete-expired-transients/run
# Expected: { "success": true, "deleted": >=1 }

# Delete one by name
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"key":"demo_transient"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/cache/delete-transient/run
```

### 2. Nested-option access

```bash
# Seed a nested option (via WP-CLI)
wp option update demo_settings '{"a":{"b":"c"}}' --format=json

# Read one nested key
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"option":"demo_settings","path":["a","b"]}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/options/get-nested-option-value/run
# Expected: { "success": true, "exists": true, "value": "c" }

# Update one nested key
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"option":"demo_settings","operation":"update","path":["a","b"],"value":"d"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/options/patch-option-value/run

# Verify
wp option get demo_settings --format=json
# Expected: {"a":{"b":"d"}}

# Attempt to patch a blocked option (must be refused)
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"option":"siteurl","operation":"update","path":["scheme"],"value":"https"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/options/patch-option-value/run
# Expected: { "success": false, "blocked_reason": "blocked_option" }

wp option delete demo_settings
```

### 3. Post-meta append

```bash
POSTID=$(wp post create --post_title="Meta Test" --porcelain)

# First append
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d "{\"post_id\":$POSTID,\"key\":\"tags_v2\",\"value\":\"first\"}" \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/content/add-post-meta/run
# Expected: { "success": true, "meta_id": <int> }

# Second append (same key)
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d "{\"post_id\":$POSTID,\"key\":\"tags_v2\",\"value\":\"second\"}" \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/content/add-post-meta/run

# Verify both rows persist (update-post-meta would have replaced)
wp post meta list $POSTID | grep tags_v2

# Unique-flag test: third append with unique:true (expected to be refused)
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d "{\"post_id\":$POSTID,\"key\":\"tags_v2\",\"value\":\"third\",\"unique\":true}" \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/content/add-post-meta/run
# Expected: { "success": true, "meta_id": false }

wp post delete $POSTID --force
```

### 4. Plugin discovery

```bash
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"query":"jetpack","per_page":3}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/plugins/search-wp-plugin-directory/run \
  | jq '.plugins[].slug'
# Expected: array of 3 slugs, first is "jetpack" or similar
```

### 5. Plugin uninstall

```bash
# Install a test plugin
wp plugin install akismet --version=5.3 && wp plugin deactivate akismet

# Uninstall via ability
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"plugin":"akismet"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/plugins/uninstall-plugin/run
# Expected: { "success": true, "uninstalled": true }

wp plugin list | grep akismet    # Expected: no output — plugin gone

# Attempt on an active plugin (must be refused)
wp plugin install classic-editor --activate
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"plugin":"classic-editor"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/plugins/uninstall-plugin/run
# Expected: { "success": false, "blocked_reason": "plugin_active" }

wp plugin deactivate classic-editor && wp plugin uninstall classic-editor
```

### 6. Checksum verification

```bash
# Verify plugin (using a fresh wp.org-hosted plugin)
wp plugin install classic-editor
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"plugin":"classic-editor"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/plugins/verify-plugin-checksums/run \
  | jq '.summary'
# Expected: { "total": <N>, "ok": <N>, "modified": 0, "missing": 0, "added": 0 }

# Deliberately modify a file, re-verify
echo "// added line" >> "$(wp plugin path)/classic-editor/classic-editor.php"

curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"plugin":"classic-editor"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/plugins/verify-plugin-checksums/run \
  | jq '.results[] | select(.status=="modified")'
# Expected: one entry for classic-editor.php with status: "modified"

# Core verify
curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/core/verify-core-checksums/run \
  | jq '.summary'
# Expected: modified: 0 on a vanilla install
```

### 7. Admin UI sanity

- Load `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-abilities-library`.
- Confirm the 11 new abilities appear under Cache (4), Options (2), Content (1), Plugins (3), Core (1).
- Every ability shows `manage_options` as the permission; destructive/readonly annotations match the spec.

### 8. Automated tests

```bash
composer install
composer run test    # ~21 new PHPUnit methods pass alongside existing suite
composer run phpcs
composer run phpstan
```

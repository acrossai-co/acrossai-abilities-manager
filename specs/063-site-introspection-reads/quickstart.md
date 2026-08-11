# Quickstart: Site introspection read endpoints

Steps to verify the feature end-to-end on the local WP install (`wordpress-7-0`).

## Prerequisites

- Local WordPress site at `http://wordpress-7-0.local` with the plugin installed and active.
- Administrator credentials + an application password (WP admin → Users → your profile → Application Passwords).

## Manual verification

### 1. Small facts

```bash
# WordPress version
curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/get-wp-version/run
# Expected: { "success": true, "version": "7.0", "is_multisite": false, ... }

# Database prefix
curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/get-db-prefix/run
# Expected: { "success": true, "prefix": "wp_", "base_prefix": "wp_", ... }

# Defined wp-config constant
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"constant":"WP_DEBUG"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/get-wp-config-constant/run
# Expected: { "success": true, "defined": true, "value": true, ... }

# Blocked constant — must be refused
curl -u admin:APP_PASSWORD -X POST \
  -H 'Content-Type: application/json' \
  -d '{"constant":"AUTH_KEY"}' \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/get-wp-config-constant/run
# Expected: { "success": false, "blocked_reason": "sensitive_constant", ... }
```

### 2. Themes, rewrite rules, widgets, sidebars

```bash
curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/list-theme-mods/run
# Expected: { "success": true, "theme": "twentytwentyfive", "mods": { ... } }

curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/list-rewrite-rules/run \
  | jq '.count'
# Expected: a positive integer if permalinks have been flushed

curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/list-sidebars/run \
  | jq '.sidebars | length'
# Expected: the count of registered sidebars from the active theme

curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/list-widgets/run
# Expected: per-sidebar widget lists + registered widgets registry
```

### 3. Media, comments

```bash
curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/list-image-sizes/run \
  | jq '.sizes[] | select(.name=="thumbnail")'
# Expected: { "name": "thumbnail", "width": 150, "height": 150, "crop": true }

curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/get-comment-count/run
# Expected site-wide: { "success": true, "counts": { "approved": …, "spam": …, "total_comments": … } }
```

### 4. Maintenance mode

```bash
# When the site is NOT upgrading
curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/get-maintenance-mode-status/run
# Expected: { "success": true, "active": false, ... }

# Simulate upgrade (from the plugin root, as WP-CLI or manual touch)
echo "<?php \$upgrading = time(); ?>" > /path/to/wordpress-7-0/app/public/.maintenance

curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/get-maintenance-mode-status/run
# Expected: { "success": true, "active": true, "since": <unix>, "is_stale": false, ... }

rm /path/to/wordpress-7-0/app/public/.maintenance
```

### 5. Cron reachability

```bash
curl -u admin:APP_PASSWORD \
  http://wordpress-7-0.local/wp-json/wp-abilities/v1/abilities/acrossai/test-wp-cron/run
# Expected on Local: { "success": true, "reachable": true, "disable_wp_cron": false, ... }
```

### 6. Admin UI sanity

- Load `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-abilities-library`.
- Confirm all 11 new abilities appear in their expected sub-groups (Core, Database, Files, Themes, Settings, Widgets, Media, Comments, Health, Cron).
- The new **Widgets** category appears in the Library page tab list.
- Every row shows `manage_options` as the permission, `readonly: true`, `idempotent: true`, and `destructive: false`.

### 7. Automated tests

```bash
composer install
composer run test    # ~14 new PHPUnit methods pass alongside existing suite
composer run phpcs   # zero errors, zero warnings
composer run phpstan # zero errors at level 8
```

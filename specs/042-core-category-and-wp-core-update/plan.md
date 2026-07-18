# Implementation Plan: Feature 042

**Feature Branch**: `042-core-category-and-wp-core-update`
**Base**: `main` at `0e8e03b` (release 0.0.10)
**Target release**: 0.0.11

## Approach

Additive: no changes to existing modules or contracts. New Category folder + two new abilities + a filename-helper diff. Both new abilities model directly on existing plugin/theme lifecycle abilities so future maintainers instantly recognize the shape.

## New files

### Core category

- `includes/Abilities/Core/Category_Registrar.php` — final singleton mirroring `FileManager/Category_Registrar.php`. Slug `acrossai-abilities-manager-core`, label preserves the mojibake `â` character to match the existing 17 Category_Registrar labels (this is a legacy encoding artifact across the whole codebase; not fixing it in this feature to stay pattern-consistent).
- `includes/Abilities/Core/Wp_Core_Update_Check.php` — read-only. Modelled on `Plugins/Update_Check.php` (which already invokes `get_core_updates()` for its `core` sub-object). Empty input schema. Output flattens the WP offer object into JSON-friendly fields.
- `includes/Abilities/Core/Wp_Core_Update.php` — modelled on `Plugins/Plugin_Update.php` and `Themes/Theme_Update.php`. Wraps `Core_Upgrader::upgrade()`. Optional `version` (+ optional `locale`) inputs; when omitted, uses the first `response=upgrade` offer.

### Tests

- `tests/phpunit/abilities/Test_Feature_042_Core_Update.php` — nine source-inspection tests covering: filename scheme (drop `backup-` prefix + 12-char random suffix, add microtime + ms + slug/unix/ms concatenation + ultimate slug fallback), Core Category_Registrar shape, Wp_Core_Update_Check shape + read-only annotations, Wp_Core_Update guards (`Core_Upgrader`, `File_Mods_Guard`, multisite guard, `WP_Ajax_Upgrader_Skin`), permission_callback ANDing `manage_options` with `update_core`, optional `version` + `locale` inputs, and bootstrap wiring.

## Modified files

- `includes/Abilities/Utilities/Backups_Storage.php` — replace `random_backup_filename()` body with the `{slug}-{unix}-{ms}.zip` scheme. Signature unchanged. Extract two tiny helpers (`filename_slug_segment()`, `filename_time_segments()`) for readability.
- `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — add 1 line to `register_category_callbacks()` for the new Category_Registrar, add 2 `new Core\…()` lines to `register_abilities()`.
- `phpunit.xml.dist` — new `feature-042-unit` testsuite.

## Reusables

- **`Ability_Definition`** (`includes/Modules/Library/Ability_Definition.php`) — both new abilities extend it; constructor auto-hooks the filter.
- **`File_Mods_Guard`** (`includes/Abilities/Utilities/File_Mods_Guard.php`) — `Wp_Core_Update` guards on `blocked_response('install')`.
- **`Plugins/Update_Check.php`** — reference for the `get_core_updates()` shape mapping. Feature 042's `Wp_Core_Update_Check` output is a superset of the `core` sub-object `Update_Check` already exposes.
- **`Plugins/Plugin_Update.php` + `Themes/Theme_Update.php`** — reference for the `Plugin_Upgrader::bulk_upgrade()` result-interpretation shape. `Core_Upgrader::upgrade()` returns the same `true | WP_Error | false | null` union; the result-interpretation ladder is identical.
- **WP core**: `get_core_updates()`, `find_core_update()`, `Core_Upgrader`, `WP_Ajax_Upgrader_Skin`, `get_bloginfo('version')`, `get_locale()`, `microtime(true)`, `sanitize_key()`, `sanitize_text_field()`. No third-party libs.

## WP core update flow (mirrors wp-admin/update-core.php)

```
require_once ABSPATH . 'wp-admin/includes/update.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';

$from_version = (string) get_bloginfo('version');

$update = ! empty($input['version'])
    ? find_core_update( $version, '' !== $locale ? $locale : get_locale() )
    : ( get_core_updates()[0] ?? null );

// null / false / response !== 'upgrade' → return clean no-op envelope

$skin     = new \WP_Ajax_Upgrader_Skin();
$upgrader = new \Core_Upgrader( $skin );
$result   = $upgrader->upgrade( $update );

$to_version = (string) get_bloginfo('version');
// interpret $result identically to Plugin_Update / Theme_Update
```

## Security constraints

See [security-constraints.md](./security-constraints.md).

## Verification

- `composer run phpstan` (level 8) — zero errors
- `composer run phpcs` — zero errors
- `composer test` — full suite green (target: 153 tests / 504 assertions)
- Manual e2e: Core tab visible in Library UI; `wp-core-update-check` returns available/new_version fields; `wp-core-update` upgrades a version-pinned Local site; `zip-create` returns the new `{slug}-{unix}-{ms}.zip` filename shape.
- Post-implementation: run `/speckit.memory-md.capture-from-diff` to harvest durable memory entries from the diff.

## Ship

Two PRs on merge, following the 041 → 0.0.9/0.0.10 cadence:

1. **Feature PR** on branch `042-core-category-and-wp-core-update` → `main`. Ships the specs backfill + code + tests.
2. **Release PR** on branch `release-0.0.11` → `main`. Version bump + Changelog + Upgrade Notice.

Then tag `0.0.11` and cut GitHub release.

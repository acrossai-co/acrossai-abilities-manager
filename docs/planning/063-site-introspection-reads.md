# Feature 063 — Site introspection read endpoints

**Status**: input brief for `/speckit-specify`. Written 2026-08-11.

## Problem

Agents driving a WordPress site over REST/MCP frequently need small, single-purpose introspection reads that WordPress does not expose through a public REST endpoint: the WP core version string, `$wpdb->prefix`, the current rewrite rules array, active-theme theme mods, registered image sizes, sidebar/widget assignments, whether the site is in maintenance mode, whether WP-Cron is reachable, and the value of a single `wp-config.php` constant. Each of these is a one-liner around a WordPress core function; none of them exist as an ability today.

This feature adds 11 read-only abilities. Every ability is a thin wrapper around one WP core function. None mutate state. `manage_options` remains the sole access gate for consistency with the rest of the surface.

Also introduces one new ability category — **Widgets** — because widget/sidebar introspection is neither a theme concept, a menu concept, nor a block concept and deserves its own category slug (`acrossai-abilities-manager-widgets`).

## Proposed abilities

Slug convention per `DEC-SLUG-CONVENTION-VERB-FIRST` (Feature 058): `acrossai/<verb>-<subject>`.

| # | Slug | Category | Core API | Output |
|---|---|---|---|---|
| 1 | `acrossai/get-wp-version` | `Core` | `get_bloginfo('version')`, `is_multisite()` | `{ success, version, is_multisite }` |
| 2 | `acrossai/get-db-prefix` | `Database` | `$wpdb->prefix`, `$wpdb->base_prefix` | `{ success, prefix, base_prefix }` |
| 3 | `acrossai/get-wp-config-constant` | `FileManager` | `defined()` + `constant()` | `{ success, constant, defined: bool, value }` |
| 4 | `acrossai/list-theme-mods` | `Themes` | `get_theme_mods()` for `get_stylesheet()` | `{ success, theme, mods: object }` |
| 5 | `acrossai/list-rewrite-rules` | `Settings` | `get_option('rewrite_rules')` | `{ success, rules: object, count }` |
| 6 | `acrossai/list-widgets` | **Widgets (new)** | `wp_get_sidebars_widgets()` + `$wp_registered_widgets` | `{ success, sidebars: object<string,string[]>, widgets: object }` |
| 7 | `acrossai/list-sidebars` | **Widgets (new)** | `$GLOBALS['wp_registered_sidebars']` | `{ success, sidebars: [{ id, name, description, before_widget, after_widget, before_title, after_title }] }` |
| 8 | `acrossai/list-image-sizes` | `Media` | `get_intermediate_image_sizes()` + `wp_get_additional_image_sizes()` + core `get_option('thumbnail_size_w')` etc. | `{ success, sizes: [{ name, width, height, crop }] }` |
| 9 | `acrossai/get-comment-count` | `Comments` | `wp_count_comments( $post_id ?? 0 )` | `{ success, counts: { approved, moderated, spam, trash, post-trashed, total_comments } }` |
| 10 | `acrossai/get-maintenance-mode-status` | `SiteHealth` | file_exists(ABSPATH . '.maintenance') + parse the `$upgrading` timestamp | `{ success, active: bool, since?: int (unix), is_stale?: bool }` — stale means the timestamp is > 10 minutes old (WP's own threshold) |
| 11 | `acrossai/test-wp-cron` | `Cron` | `wp_remote_get( site_url('wp-cron.php?doing_wp_cron'), ['blocking' => false, 'timeout' => 0.01] )` + `defined('DISABLE_WP_CRON') && DISABLE_WP_CRON` | `{ success, reachable: bool, disable_wp_cron: bool, message }` |

### Input specifics

- `acrossai/get-wp-config-constant` — `{ constant: string (required) }`. Guardrail: reject any constant in `const BLOCKED_CONSTANTS = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT', 'DB_PASSWORD']` — return `success: false` with `code: 'blocked_constant'`. Value of every other constant returned as-is.
- `acrossai/get-comment-count` — `{ post_id?: int (default 0 = whole site) }`.
- Everything else takes no input.

All 11 have `readonly: true, idempotent: true, destructive: false` annotations.

## New Widgets category

New directory: `includes/Abilities/Widgets/` with three files. Category_Registrar mirrors the shape of `includes/Abilities/Menus/Category_Registrar.php`:

- Namespace: `AcrossAI_Abilities_Manager\Includes\Abilities\Widgets`.
- Slug: `acrossai-abilities-manager-widgets`.
- Label: `Acrossai Abilities Manager – Widgets`.
- Description: `Abilities for inspecting legacy WordPress widgets and registered sidebars.`

Wired into `AcrossAI_Core_Abilities_Bootstrap::register_category_callbacks()` mirroring the AdminMenu / ContentSearch pattern at lines 84–85 (add one `$loader->add_action()` line under a new `// Feature 063 — Widgets category.` comment).

## Reused utilities (do not reinvent)

- **`Ability_Definition`** parent class — every new class extends it.
- **`Menus/Category_Registrar.php`** — mirror verbatim for the new `Widgets/Category_Registrar.php`.
- **PHPUnit fixtures** — `WP_UnitTestCase` for widgets/sidebars requires registering test widgets/sidebars via `wp_widgets_init()` — mirror the pattern from any existing widget-touching test in wp-develop core (safe reference: `wp-develop/tests/phpunit/tests/widgets.php`).

## Common shape (all 11)

- Correct namespace per category directory.
- `permission_callback => static function (): bool { return current_user_can( 'manage_options' ); }` — LITERAL, verbatim.
- `meta.show_in_rest = true`, `meta.mcp = { public: false, type: 'tool' }`.
- `meta.acrossai.sub_group` matches existing category convention (e.g., every `Themes/*` uses `sub_group => 'themes'`; new `Widgets/*` uses `sub_group => 'widgets'`).
- All string inputs sanitized with `sanitize_text_field()`.
- All returned messages wrapped in `__( '...', 'acrossai-abilities-manager' )`.

## Bootstrap wiring

Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php`:

1. **`register_category_callbacks()`** — add one line for Widgets under `// Feature 063 — Widgets category.` (mirrors lines 83–85 pattern):
   ```php
   $loader->add_action( 'wp_abilities_api_categories_init', Widgets\Category_Registrar::instance(), 'register' );
   ```

2. **`register_abilities()`** — 11 new `new Category\Ability();` lines, each placed inside its category's existing block (or, for Widgets, in a new labeled block after Menus).

## Testing

Under `tests/phpunit/abilities/`, one test file per ability. `WP_UnitTestCase` fixtures for widgets/sidebars require calling `wp_widgets_init()` in `setUp()` (matches wp-develop convention). For `test-wp-cron`, mock the HTTP layer via `add_filter('pre_http_request', …)` — matches existing HTTP-testing pattern in `tests/phpunit/abilities/Test_Feature_042_Core_Update.php`. Target: ~11 golden-path tests + 3 guardrail tests (blocked-constant lookup, non-existent theme-mod key, stale-maintenance detection).

## Delivery

Feature branch off `main`, no version bump — will be rolled into a single `release-0.0.23` alongside features 062 and 064. See `/Users/raftaar1191/.claude/plans/prepare-a-plan-for-refactored-fern.md` for the unified release plan.

# Phase 1 Data Model: Site introspection read endpoints

**Status**: No new persistent entities. Every read reflects existing WordPress core state; no writes at all in this feature.

## Read sources

Every ability's data source is an existing WordPress core primitive:

| Ability | Source |
|---|---|
| `get-wp-version` | `get_bloginfo('version')` + `is_multisite()` |
| `get-db-prefix` | `$wpdb->prefix`, `$wpdb->base_prefix` |
| `get-wp-config-constant` | `defined()` + `constant()` |
| `list-theme-mods` | `get_theme_mods()` for `get_stylesheet()` |
| `list-rewrite-rules` | `get_option('rewrite_rules')` |
| `list-widgets` | `wp_get_sidebars_widgets()` + `$GLOBALS['wp_registered_widgets']` |
| `list-sidebars` | `$GLOBALS['wp_registered_sidebars']` |
| `list-image-sizes` | `get_intermediate_image_sizes()` + `wp_get_additional_image_sizes()` + `get_option(*_size_w/h/crop)` |
| `get-comment-count` | `wp_count_comments( $post_id )` |
| `get-maintenance-mode-status` | `file_exists( ABSPATH . '.maintenance' )` + `include ABSPATH . '.maintenance'` (reads `$upgrading`) |
| `test-wp-cron` | `wp_remote_get( site_url('wp-cron.php?doing_wp_cron') )` + `defined('DISABLE_WP_CRON')` |

## Entities (all existing, WordPress-managed)

### WordPress Environment

- **Storage**: PHP globals (`$GLOBALS['wp_version']`), `wp-config.php`-defined constants, WordPress options.
- **Attributes read by this feature**:
  - version string, multisite flag, DB table prefix, defined `wp-config.php` constant values.
- **Mutations**: none.

### Theme Modifications

- **Storage**: `theme_mods_<stylesheet>` option in `wp_options`.
- **Attributes**: caller-defined arbitrary key/value pairs (Customizer settings, theme options).
- **Mutations**: none.

### Rewrite Rules

- **Storage**: `rewrite_rules` option in `wp_options`.
- **Attributes**: `{ [regex_pattern: string]: string /* query template */ }`.
- **Mutations**: none.

### Widget & Sidebar

- **Widget storage**: `sidebars_widgets` option in `wp_options` (per-sidebar array of widget instance identifiers) + `$GLOBALS['wp_registered_widgets']` (runtime registry of widget classes).
- **Sidebar storage**: `$GLOBALS['wp_registered_sidebars']` (runtime registry populated by `register_sidebar()`).
- **Mutations**: none.

### Image Size

- **Storage**: WordPress-core defaults live in `wp_options` (`thumbnail_size_w`, etc.); additional sizes registered via `add_image_size()` live only in `$GLOBALS['_wp_additional_image_sizes']` for the current request.
- **Attributes**: `name`, `width`, `height`, `crop`.
- **Mutations**: none.

### Comment Count Summary

- **Storage**: computed on demand by WordPress core from `wp_comments`.
- **Attributes**: per-status counters (`approved`, `moderated`, `spam`, `trash`, `post-trashed`) + `total_comments`.
- **Mutations**: none.

### Maintenance Marker

- **Storage**: `ABSPATH/.maintenance` PHP file. Contains `$upgrading` timestamp assignment.
- **Attributes**: existence, timestamp, staleness (derived).
- **Mutations**: none.

### Cron Reachability Status

- **Storage**: none — computed per-invocation.
- **Attributes**: `reachable: bool`, `disable_wp_cron: bool`.
- **Mutations**: none. The probe fires a non-blocking HTTP GET which does not wait for the response body.

## State transitions

None — every ability is a pure read.

## Cross-entity invariants

None new; this feature adds no invariants, only reflects existing WordPress-managed state.

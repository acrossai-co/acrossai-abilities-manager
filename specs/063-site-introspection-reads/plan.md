# Implementation Plan: Site introspection read endpoints

**Branch**: `063-site-introspection-reads` | **Date**: 2026-08-11 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/063-site-introspection-reads/spec.md`

## Summary

Add 11 new read-only abilities and 1 new ability category to the existing `AcrossAI Abilities Manager` runtime:

- **9 single-purpose reads** distributed across existing categories: `Core` (get-wp-version), `Database` (get-db-prefix), `FileManager` (get-wp-config-constant), `Themes` (list-theme-mods), `Settings` (list-rewrite-rules), `Media` (list-image-sizes), `Comments` (get-comment-count), `SiteHealth` (get-maintenance-mode-status), `Cron` (test-wp-cron).
- **2 widget/sidebar reads** under a new `Widgets/` category (`list-widgets`, `list-sidebars`).

Every ability is a thin wrapper around one WordPress core function (`get_bloginfo`, `$wpdb->prefix`, `constant()`, `get_theme_mods`, `get_option('rewrite_rules')`, `wp_get_sidebars_widgets`, `get_intermediate_image_sizes`, `wp_count_comments`, filesystem probe of `.maintenance`, `wp_remote_get` against `wp-cron.php`). All are `readonly: true, idempotent: true, destructive: false`. `manage_options` remains the sole access gate.

**Technical approach:** zero new modules, zero new database tables, zero new option keys. The single architectural addition is the `Widgets` category — a new directory `includes/Abilities/Widgets/` with a Category_Registrar (verbatim copy of `Menus/Category_Registrar.php` shape) + 2 ability classes. Bootstrap wiring gains one `add_action` call in `register_category_callbacks()` (for the new category) and 11 new `new Category\Class();` lines in `register_abilities()`.

## Technical Context

**Language/Version**: PHP 8.1+.
**Primary Dependencies**: WordPress 6.9+; existing `Ability_Definition` parent class + WP core Abilities API. No new Composer or npm packages.
**Storage**: Reads only. Sources: `$wpdb` object properties (prefix), WP core globals (`$wp_registered_widgets`, `$wp_registered_sidebars`, `$wp_rewrite`), WordPress options (`theme_mods_*`, `rewrite_rules`), `wp-config.php` PHP constants (via `defined()` + `constant()`), the `.maintenance` file at `ABSPATH`, WP core functions (`wp_count_comments`, `get_intermediate_image_sizes`, `get_bloginfo`), and one outbound HTTP request via `wp_remote_get()` for the cron-test ability.
**Testing**: PHPUnit 10.5. Fixtures via `WP_UnitTestCase`; widget/sidebar tests call `wp_widgets_init()` in `setUp()`. The cron-test HTTP path is mocked via `add_filter('pre_http_request', ...)` (established pattern from `tests/phpunit/abilities/Test_Feature_042_Core_Update.php`).
**Target Platform**: WordPress 6.9+ on PHP 8.1 through 8.5 (existing CI matrix).
**Project Type**: WordPress plugin — single project.
**Performance Goals**: 90% of the 11 abilities respond in under 100 ms on a warmed object cache (spec SC-006). The `test-wp-cron` ability is bounded by network latency to the site's own hostname (typically <50 ms on localhost, arbitrary on production).
**Constraints**: `manage_options` capability gate on every ability. No admin-side JS/UI changes — new abilities and the new Widgets category render automatically in the existing Custom Abilities and Library admin surfaces.
**Scale/Scope**: 11 new ability classes (thin, ~80–150 lines each including docblocks) + 1 new Category_Registrar + 1 bootstrap edit. ~14 new PHPUnit test methods (11 golden-path + 3 guardrail per spec: blocked-constant, stale-maintenance, non-existent-theme-mod).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Modular Architecture — PASS

Each new ability is a self-contained subclass under a category directory. The new `Widgets/` directory mirrors the shape of `Menus/` (small closely-related-abilities cluster). No cross-module dependencies.

### II. WordPress Standards Compliance — PASS

- PHPCS (WPCS strict): every new file will match the formatting of one existing peer (`Menus/List_Menus.php` for widgets/sidebars, `Cron/List_Cron_Jobs.php` for the cron test, etc.). Verified pre-commit by running `composer run phpcs`.
- PHPStan level 8: every WP core function referenced is declared in the `wordpress-stubs` package. `wp_get_sidebars_widgets()`, `$GLOBALS['wp_registered_widgets']`, `get_intermediate_image_sizes()`, `wp_count_comments()` all have stubs.
- Plugin Check: no `eval`/`extract`/shell exec. Zero raw SQL — reads use `get_option()` / `$wpdb->prefix` (property access, no query).
- Multisite: reads target the currently-active site; documented in Assumptions.

### III. User-Centric Design — PASS (N/A, no admin UI)

No new admin pages or forms. The new `Widgets` category surface flows through the existing DataViews-backed admin table automatically.

### IV. Security First — PASS

- Sanitization at entry: every string input is passed through `sanitize_text_field()` in `execute()`. Only 3 abilities take a non-trivial input: `get-wp-config-constant` (constant name string, sanitized + block-listed), `get-comment-count` (integer post_id, `absint()`-cast), and the widget/sidebar abilities (no input).
- Capability check: `permission_callback` gates every ability on `manage_options`.
- Prepared queries: no raw SQL is written by this feature; every DB read goes through `get_option()` or `$wpdb->prefix` (property access).
- The `get-wp-config-constant` block-list defends the constants that would otherwise expose auth keys or the DB password (FR-004): `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`, `DB_PASSWORD`. Match performed case-sensitively (matches PHP `defined()` behaviour).
- The `test-wp-cron` outbound HTTP request uses `wp_remote_get()` per constitution §II code-quality rules; non-blocking with 0.01s timeout so it never hangs a REST response.

### V. Extensibility Without Core Modification — PASS

New files under new + existing category dirs. Bootstrap wiring appends one new `add_action()` call and 11 new instantiation lines to existing methods — no method signatures change.

### VI. Reusability & DRY Principle — PASS

Reuses:
- `Ability_Definition` parent class for all 11.
- `Menus/Category_Registrar.php` as the verbatim template for the new `Widgets/Category_Registrar.php`.
- `add_filter('pre_http_request', ...)` HTTP-mocking pattern from `Test_Feature_042_Core_Update.php`.
- `wp_remote_get()` per WordPress standards; never `curl` directly.

The block-listed constants are hardcoded on the single class that needs them (`Get_Wp_Config_Constant`) rather than extracted; no other class references them.

### VII. Definition of Done — PLANNED

Same gates as feature 062. ~14 new PHPUnit test methods, PHPCS + PHPStan + ESLint (N/A) all zero on the branch.

**Overall gate: PASS.**

## Project Structure

### Documentation (this feature)

```text
specs/063-site-introspection-reads/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── abilities.md     # Phase 1 output
├── checklists/
│   └── requirements.md  # /speckit-specify output
├── spec.md              # /speckit-specify output
└── tasks.md             # /speckit-tasks output (later)
```

### Source Code (repository root)

```text
includes/
└── Abilities/
    ├── Core/
    │   └── Get_Wp_Version.php                # NEW — acrossai/get-wp-version
    ├── Database/
    │   └── Get_Db_Prefix.php                 # NEW — acrossai/get-db-prefix
    ├── FileManager/
    │   └── Get_Wp_Config_Constant.php        # NEW — acrossai/get-wp-config-constant
    ├── Themes/
    │   └── List_Theme_Mods.php               # NEW — acrossai/list-theme-mods
    ├── Settings/
    │   └── List_Rewrite_Rules.php            # NEW — acrossai/list-rewrite-rules
    ├── Widgets/                              # NEW DIRECTORY
    │   ├── Category_Registrar.php            # NEW — mirrors Menus/Category_Registrar
    │   ├── List_Widgets.php                  # NEW — acrossai/list-widgets
    │   └── List_Sidebars.php                 # NEW — acrossai/list-sidebars
    ├── Media/
    │   └── List_Image_Sizes.php              # NEW — acrossai/list-image-sizes
    ├── Comments/
    │   └── Get_Comment_Count.php             # NEW — acrossai/get-comment-count
    ├── SiteHealth/
    │   └── Get_Maintenance_Mode_Status.php   # NEW — acrossai/get-maintenance-mode-status
    ├── Cron/
    │   └── Test_Wp_Cron.php                  # NEW — acrossai/test-wp-cron
    └── AcrossAI_Core_Abilities_Bootstrap.php # MODIFIED — +1 category action, +11 instantiations

tests/
└── phpunit/
    └── abilities/
        ├── Test_Get_Wp_Version.php               # NEW
        ├── Test_Get_Db_Prefix.php                # NEW
        ├── Test_Get_Wp_Config_Constant.php       # NEW
        ├── Test_List_Theme_Mods.php              # NEW
        ├── Test_List_Rewrite_Rules.php           # NEW
        ├── Test_List_Widgets.php                 # NEW
        ├── Test_List_Sidebars.php                # NEW
        ├── Test_List_Image_Sizes.php             # NEW
        ├── Test_Get_Comment_Count.php            # NEW
        ├── Test_Get_Maintenance_Mode_Status.php  # NEW
        └── Test_Test_Wp_Cron.php                 # NEW
```

**Structure Decision**: WordPress plugin single-project layout. One new category dir (`Widgets/`) added alongside existing peers; the other 9 abilities land inside existing category dirs. No `admin/`, `public/`, `src/`, or Composer changes.

## Complexity Tracking

*No entries — Constitution Check passes on every principle.*

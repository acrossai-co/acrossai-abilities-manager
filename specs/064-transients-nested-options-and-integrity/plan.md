# Implementation Plan: Transient CRUD, nested option access, plugin lifecycle & checksum integrity

**Branch**: `064-transients-nested-options-and-integrity` | **Date**: 2026-08-11 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/064-transients-nested-options-and-integrity/spec.md`

## Summary

Add 11 new abilities across four existing ability categories:

- **4 transient CRUD** under `Cache/`: `get-transient`, `list-transients`, `delete-transient`, `delete-expired-transients`.
- **2 nested-key option operations** under `Options/`: `get-nested-option-value` (read), `patch-option-value` (write).
- **1 post-meta append** under `Content/`: `add-post-meta` (append semantics, complements existing update/delete).
- **3 plugin-lifecycle** under `Plugins/`: `search-wp-plugin-directory` (WP.org discovery), `uninstall-plugin`, `verify-plugin-checksums`.
- **1 core-integrity** under `Core/`: `verify-core-checksums`.

Every ability uses the literal `permission_callback = static function (): bool { return current_user_can( 'manage_options' ); }` and inherits the `Ability_Definition` parent class. Two abilities issue outbound HTTP requests (both to `api.wordpress.org`) — the two `verify-*-checksums` abilities. Two abilities are destructive with input guardrails: `patch-option-value` (block-list of core options), `uninstall-plugin` (must-be-inactive + `DISALLOW_FILE_MODS`).

**Technical approach:** zero new modules, zero new categories, zero new database tables, zero new option keys. All 11 land inside existing category directories. Bootstrap wiring is a single edit in `AcrossAI_Core_Abilities_Bootstrap::register_abilities()` — 11 new instantiation lines spread across the existing Cache, Options, Content, Plugins, and Core blocks.

## Technical Context

**Language/Version**: PHP 8.1+.
**Primary Dependencies**: WordPress 6.9+; existing `Ability_Definition` parent class; existing utilities `Plugin_Helpers::resolve_plugin()` and `File_Mods_Guard` (already used by Install_Plugin / Delete_Theme / Recovery abilities). No new Composer or npm packages.
**Storage**: Reads and writes against existing WordPress-managed tables and options. Transients live in `$wpdb->options` (blog-scope) or `$wpdb->sitemeta` (site-scope). Nested-option reads/writes go through `get_option()` / `update_option()`. Post-meta append via `add_post_meta()`. No new tables or option keys are introduced.
**Testing**: PHPUnit 10.5. Fixtures via `WP_UnitTestCase`. HTTP requests to `api.wordpress.org` (from `plugins_api()` and checksums fetch) are mocked via `add_filter('pre_http_request', ...)` — same pattern as feature 063 and existing `Test_Feature_042_Core_Update.php`.
**Target Platform**: WordPress 6.9+ on PHP 8.1 through 8.5.
**Project Type**: WordPress plugin — single project.
**Performance Goals**: 90% of `search-wp-plugin-directory` invocations return within 3 seconds against wordpress.org (spec SC-006, excludes cold-start and unreachable-API cases). All other abilities target sub-100ms on a warmed object cache.
**Constraints**: `manage_options` gate on every ability. No admin JS/UI changes. Outbound HTTP restricted to `api.wordpress.org` (canonical WordPress.org endpoints — plugin directory query, plugin checksums manifest, core checksums manifest).
**Scale/Scope**: 11 new ability classes (~100–250 lines each including docblocks) + 1 bootstrap edit. ~21 new PHPUnit methods (11 golden-path + 10 guardrail per spec's Success Criteria).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Modular Architecture — PASS

11 self-contained subclasses spread across 5 existing category directories. No cross-module dependencies. Every class extends `Ability_Definition` and does one thing (spec's Modular Architecture rationale).

### II. WordPress Standards Compliance — PASS

- PHPCS (WPCS strict): every new file mirrors an existing peer (`Cache/Flush_Transients.php` for the transient CRUD, `Options/Update_Option.php` for nested-option patch, `Plugins/Install_Plugin.php` for uninstall, etc.).
- PHPStan level 8: every WP core function called is stubbed by `wordpress-stubs`. `delete_expired_transients()`, `plugins_api()`, `uninstall_plugin()`, `get_core_checksums()` all have signatures.
- Plugin Check: no `eval`/`extract`/shell exec. The `list-transients` ability uses `$wpdb->prepare()` with `%s` placeholders for the `LIKE` pattern — no raw interpolation. Verify-checksums abilities use `wp_remote_get()` for HTTP (per constitution §II).
- Multisite: `list-transients` accepts a `site_only` flag that switches to `$wpdb->sitemeta` on multisite; single-site installs ignore the flag. `delete-transient` accepts a `site` flag that toggles between `delete_transient()` and `delete_site_transient()`.

### III. User-Centric Design — PASS (N/A, no admin UI)

No new admin pages or forms. New abilities render through the existing DataViews-backed admin surfaces.

### IV. Security First — PASS

- Sanitization at entry: every string input passed through `sanitize_text_field()`. Integer inputs (post_id, limit, offset, user_id) cast via `absint()` / `(int)`.
- Capability check: `permission_callback` gates every ability on `manage_options`.
- Prepared queries: `list-transients` uses `$wpdb->prepare( "... LIKE %s", $wpdb->esc_like('_transient_') . '%' )` — no raw table-name interpolation (uses `$wpdb->options` object property; identifier via `%i` is not needed because it's a WP-managed property).
- `patch-option-value` guardrail: rejects any option name in the block-list already used by the existing `Update_Option` ability. Enum-driven for the operation field (`insert`/`update`/`delete` only).
- `uninstall-plugin` guardrails: (a) refuses if plugin is currently active (via `is_plugin_active()`); (b) refuses when `DISALLOW_FILE_MODS` is defined truthy (via existing `File_Mods_Guard::blocked_response()`).
- `search-wp-plugin-directory` output: filters `plugins_api()`'s raw response through `wp_kses_post()` on the `short_description` field (WP.org returns HTML there) — matches WP admin's own plugin-install screen sanitization.
- Checksum verifies: file-content hashing uses `md5_file()` per WP core's own approach; no user-controlled filesystem walking (the ability walks the plugin's own directory only, resolved through `Plugin_Helpers::resolve_plugin()` for fuzzy input).

### V. Extensibility Without Core Modification — PASS

11 new files under existing category dirs. Bootstrap wiring appends 11 lines — no method signatures change.

### VI. Reusability & DRY Principle — PASS

Reuses:
- `Ability_Definition` parent class for all 11.
- `Plugin_Helpers::resolve_plugin()` (already used by Recovery abilities) for fuzzy plugin slug/file resolution in `uninstall-plugin` and `verify-plugin-checksums`.
- `File_Mods_Guard::blocked_response()` for the `DISALLOW_FILE_MODS` gate in `uninstall-plugin`.
- Existing `BLOCKED_OPTIONS` block-list from `Update_Option.php` for the `patch-option-value` guardrail (if not already extracted, either reference by direct constant lookup or extract to a `const` on `Update_Option` for reuse — decision deferred to `/speckit-tasks`).
- `Update_Post_Meta.php` input schema shape as the template for `Add_Post_Meta.php` (same `key`/`meta_key` and `value`/`meta_value` aliases).
- `add_filter('pre_http_request', ...)` HTTP-mocking pattern for both `verify-*-checksums` and `search-wp-plugin-directory` tests.

### VII. Definition of Done — PLANNED

Same gates as features 062 and 063. ~21 new PHPUnit methods, PHPCS + PHPStan + ESLint (N/A) zero.

**Overall gate: PASS.**

## Project Structure

### Documentation (this feature)

```text
specs/064-transients-nested-options-and-integrity/
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
    ├── Cache/
    │   ├── Get_Transient.php                 # NEW — cache/get-transient
    │   ├── List_Transients.php               # NEW — cache/list-transients
    │   ├── Delete_Transient.php              # NEW — cache/delete-transient
    │   └── Delete_Expired_Transients.php     # NEW — cache/delete-expired-transients
    ├── Options/
    │   ├── Get_Nested_Option_Value.php       # NEW — options/get-nested-option-value
    │   └── Patch_Option_Value.php            # NEW — options/patch-option-value
    ├── Content/
    │   └── Add_Post_Meta.php                 # NEW — content/add-post-meta
    ├── Plugins/
    │   ├── Search_Wp_Plugin_Directory.php    # NEW — plugins/search-wp-plugin-directory
    │   ├── Uninstall_Plugin.php              # NEW — plugins/uninstall-plugin
    │   └── Verify_Plugin_Checksums.php       # NEW — plugins/verify-plugin-checksums
    ├── Core/
    │   └── Verify_Core_Checksums.php         # NEW — core/verify-core-checksums
    └── AcrossAI_Core_Abilities_Bootstrap.php # MODIFIED — +11 instantiations

tests/
└── phpunit/
    └── abilities/
        ├── Test_Get_Transient.php                    # NEW
        ├── Test_List_Transients.php                  # NEW
        ├── Test_Delete_Transient.php                 # NEW
        ├── Test_Delete_Expired_Transients.php        # NEW
        ├── Test_Get_Nested_Option_Value.php          # NEW
        ├── Test_Patch_Option_Value.php               # NEW
        ├── Test_Add_Post_Meta.php                    # NEW
        ├── Test_Search_Wp_Plugin_Directory.php       # NEW
        ├── Test_Uninstall_Plugin.php                 # NEW
        ├── Test_Verify_Plugin_Checksums.php          # NEW
        └── Test_Verify_Core_Checksums.php            # NEW
```

**Structure Decision**: WordPress plugin single-project layout. All 11 new abilities land in existing category dirs. No new category, no new module, no `admin/`, `src/`, or Composer changes.

## Complexity Tracking

*No entries — Constitution Check passes on every principle.*

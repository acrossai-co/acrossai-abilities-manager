---
description: "Implementation tasks for feature 063 — Site introspection read endpoints"
---

# Tasks: Site introspection read endpoints

**Input**: Design documents from `specs/063-site-introspection-reads/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/abilities.md](./contracts/abilities.md), paired input brief at `docs/planning/063-site-introspection-reads.md`
**Tests**: Included per constitution §VII.

**Organization**: Tasks grouped by user story (US1: single-purpose facts; US2: widgets/sidebars) plus Setup, Wiring, and QA phases.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no ordering dependency).
- **[Story]**: `US1`, `US2`, or `Setup` / `Wiring` / `QA`.

## Phase 1: Setup

- [ ] **T001** [Setup] Baseline: `composer install`, then `composer run test`, `composer run phpcs`, `composer run phpstan` — all clean on the `main` merge base.
- [ ] **T002** [P] [Setup] Skim reference peers: `includes/Abilities/Menus/List_Menus.php` (small read-only ability shape + Menus/Category_Registrar for the Widgets/ template), `includes/Abilities/Cron/List_Cron_Jobs.php` (read shape + cron mocking), `includes/Abilities/Users/Get_Role_Capabilities.php` (read shape + i18n).
- [ ] **T003** [P] [Setup] Skim `tests/phpunit/abilities/Test_Feature_042_Core_Update.php` for the `add_filter('pre_http_request', ...)` mocking pattern — used by `test-wp-cron`.

## Phase 2: User Story 1 — Nine single-purpose facts (P1)

Each ability is a thin wrapper. Test file per ability. Bootstrap wiring accumulates in Phase 4.

- [ ] **T010** [P] [US1] Create `includes/Abilities/Core/Get_Wp_Version.php` implementing `acrossai/get-wp-version` per contracts §1. `execute()` returns `{ success: true, version: get_bloginfo('version'), is_multisite: is_multisite(), message: __('WordPress version fetched.', ...) }`.
- [ ] **T011** [P] [US1] Create `includes/Abilities/Database/Get_Db_Prefix.php` implementing `acrossai/get-db-prefix` per contracts §2. Access `$GLOBALS['wpdb']->prefix` and `->base_prefix`.
- [ ] **T012** [P] [US1] Create `includes/Abilities/FileManager/Get_Wp_Config_Constant.php` implementing `acrossai/get-wp-config-constant` per contracts §3. `const BLOCKED_CONSTANTS = ['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT','DB_PASSWORD']`. Guard: if `in_array($constant, self::BLOCKED_CONSTANTS, true)`, refuse with `blocked_reason: 'sensitive_constant'`. Else return `defined($constant)` + `constant($constant)` (when defined).
- [ ] **T013** [P] [US1] Create `includes/Abilities/Themes/List_Theme_Mods.php` implementing `acrossai/list-theme-mods` per contracts §4. Return `get_stylesheet()` and `get_theme_mods() ?: []`.
- [ ] **T014** [P] [US1] Create `includes/Abilities/Settings/List_Rewrite_Rules.php` implementing `acrossai/list-rewrite-rules` per contracts §5. Return `get_option('rewrite_rules', [])` + count.
- [ ] **T015** [P] [US1] Create `includes/Abilities/Media/List_Image_Sizes.php` implementing `acrossai/list-image-sizes` per contracts §8. For each name from `get_intermediate_image_sizes()`, resolve dimensions via `wp_get_additional_image_sizes()[$name]` or the WP-core defaults (`get_option("{$name}_size_w"|"_size_h"|"_crop")` for the four core sizes).
- [ ] **T016** [P] [US1] Create `includes/Abilities/Comments/Get_Comment_Count.php` implementing `acrossai/get-comment-count` per contracts §9. Input `post_id` optional (default `0` = site-wide); `absint()`-cast; call `wp_count_comments((int) $post_id)`. Return as object.
- [ ] **T017** [P] [US1] Create `includes/Abilities/SiteHealth/Get_Maintenance_Mode_Status.php` implementing `acrossai/get-maintenance-mode-status` per contracts §10. Check `file_exists(ABSPATH . '.maintenance')`. When present, read `$upgrading` by `include`-ing the file inside a sandbox function scope; compute `is_stale: (time() - $upgrading) > 600`. Handle unreadable-file case gracefully (return `active: true` without `since`/`is_stale`).
- [ ] **T018** [P] [US1] Create `includes/Abilities/Cron/Test_Wp_Cron.php` implementing `acrossai/test-wp-cron` per contracts §11. `$response = wp_remote_get(site_url('wp-cron.php?doing_wp_cron'), ['blocking' => false, 'timeout' => 0.01]);`. Return `reachable: ! is_wp_error($response)`, `disable_wp_cron: defined('DISABLE_WP_CRON') && DISABLE_WP_CRON`.

Tests (US1):

- [ ] **T020** [P] [US1] `tests/phpunit/abilities/Test_Get_Wp_Version.php` — golden path returns `version` matching `get_bloginfo('version')`.
- [ ] **T021** [P] [US1] `tests/phpunit/abilities/Test_Get_Db_Prefix.php` — golden path returns `prefix` matching `$GLOBALS['wpdb']->prefix`.
- [ ] **T022** [P] [US1] `tests/phpunit/abilities/Test_Get_Wp_Config_Constant.php`. Golden: `WP_DEBUG` (defined in `wp-tests-config.php`) returns `defined: true` + boolean value. Guardrails: (a) undefined constant → `defined: false`, no `value`; (b) each of the 9 blocked constants → `blocked_reason: 'sensitive_constant'`.
- [ ] **T023** [P] [US1] `tests/phpunit/abilities/Test_List_Theme_Mods.php` — seed a theme mod via `set_theme_mod`, assert response includes it.
- [ ] **T024** [P] [US1] `tests/phpunit/abilities/Test_List_Rewrite_Rules.php` — after `flush_rewrite_rules()`, assert `count > 0`.
- [ ] **T025** [P] [US1] `tests/phpunit/abilities/Test_List_Image_Sizes.php` — assert response contains all four core sizes (`thumbnail`, `medium`, `medium_large`, `large`) with non-zero width/height.
- [ ] **T026** [P] [US1] `tests/phpunit/abilities/Test_Get_Comment_Count.php` — seed 2 approved, 1 pending, 1 spam via `$this->factory->comment->create_many()`; assert counters match.
- [ ] **T027** [P] [US1] `tests/phpunit/abilities/Test_Get_Maintenance_Mode_Status.php`. Golden: assert `active: false` on a fresh WP test env. Guardrail: `file_put_contents(ABSPATH . '.maintenance', '<?php $upgrading = time() - 1200; ?>')` (>10min old), assert `active: true, is_stale: true`. Clean up in `tearDown()`.
- [ ] **T028** [P] [US1] `tests/phpunit/abilities/Test_Test_Wp_Cron.php`. Mock `pre_http_request` to return an empty successful response; assert `reachable: true`. Second test: mock to return `WP_Error`; assert `reachable: false`. Third test: `define('DISABLE_WP_CRON', true)` in a sub-process or via `putenv` proxy — assert `disable_wp_cron: true` (this may require constant-mocking via `runkit7` extension if available, or an `@runInSeparateProcess` PHPUnit annotation; if unavailable, skip and document).

## Phase 3: User Story 2 — Widgets and sidebars (P2) + new Widgets category

- [ ] **T030** [US2] Create `includes/Abilities/Widgets/` directory.
- [ ] **T031** [US2] Create `includes/Abilities/Widgets/Category_Registrar.php` — verbatim copy of `includes/Abilities/Menus/Category_Registrar.php` with (a) namespace changed to `AcrossAI_Abilities_Manager\Includes\Abilities\Widgets`, (b) category slug `acrossai-abilities-manager-widgets`, (c) label `Acrossai Abilities Manager – Widgets`, (d) description matching the input brief.
- [ ] **T032** [US2] Create `includes/Abilities/Widgets/List_Widgets.php` implementing `acrossai/list-widgets` per contracts §6. Response: `sidebars: wp_get_sidebars_widgets()` + `widgets: $GLOBALS['wp_registered_widgets']` (each entry's `name` and `classname` fields at minimum).
- [ ] **T033** [US2] Create `includes/Abilities/Widgets/List_Sidebars.php` implementing `acrossai/list-sidebars` per contracts §7. Iterate `$GLOBALS['wp_registered_sidebars']`, project each into the schema shape.
- [ ] **T034** [P] [US2] `tests/phpunit/abilities/Test_List_Widgets.php` — call `wp_widgets_init()` in `setUp()`; register a test sidebar + widget; assert response contains both.
- [ ] **T035** [P] [US2] `tests/phpunit/abilities/Test_List_Sidebars.php` — `wp_widgets_init()` in `setUp()`; register a test sidebar; assert response contains its id + name + wrapper HTML.

## Phase 4: Bootstrap wiring

- [ ] **T040** [Wiring] Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_category_callbacks()` — add (under a new `// Feature 063 — Widgets category.` comment): `$loader->add_action( 'wp_abilities_api_categories_init', Widgets\Category_Registrar::instance(), 'register' );`.
- [ ] **T041** [Wiring] Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` — 11 new instantiation lines placed inside their existing category blocks:
  - Under `// Core` block: `new Core\Get_Wp_Version();`
  - Under `// Database` block: `new Database\Get_Db_Prefix();`
  - Under `// FileManager` block: `new FileManager\Get_Wp_Config_Constant();`
  - Under `// Themes` block: `new Themes\List_Theme_Mods();`
  - Under `// Settings` block: `new Settings\List_Rewrite_Rules();`
  - Under `// Media` block: `new Media\List_Image_Sizes();`
  - Under `// Comments` block: `new Comments\Get_Comment_Count();`
  - Under `// SiteHealth` block: `new SiteHealth\Get_Maintenance_Mode_Status();`
  - Under `// Cron` block: `new Cron\Test_Wp_Cron();`
  - New block after Menus: `new Widgets\List_Widgets();` and `new Widgets\List_Sidebars();`.

## Phase 5: Cross-cutting quality gates

- [ ] **T050** [QA] `composer run phpcs` — zero errors, zero warnings across all 12 new class files (11 ability + 1 category registrar) + all 11 new test files.
- [ ] **T051** [QA] `composer run phpstan` at level 8 — zero errors.
- [ ] **T052** [QA] `composer run test` — every new PHPUnit method passes + no regressions.
- [ ] **T053** [P] [QA] Load `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-abilities-library` in a browser; verify the new **Widgets** tab appears and holds exactly 2 abilities; the other 9 abilities appear under their expected sub-groups.
- [ ] **T054** [P] [QA] Run through `quickstart.md` sections 1 through 6. Every expected result MUST match.

## Independent-completion checkpoint

US1 (P1) can ship without US2 (P2) — the 9 single-purpose reads have no dependency on the Widgets category. If time constrains, US1 becomes the MVP slice; US2 follows in a second commit on the same branch.

## Not in scope for this feature

- No version bump / changelog entry — reserved for the unified `release-0.0.23` cut across features 062, 063, 064.
- No admin JS/CSS changes.
- No modification to any of the 219 existing abilities.

---

description: "Task list for Feature 038 — AcrossAI Main Menu Integration"
---

# Tasks: AcrossAI Main Menu Integration

**Input**: Design documents from `specs/038-acrossai-main-menu-integration/`
**Prerequisites**: spec.md, plan.md, research.md, data-model.md, contracts/menu-and-settings-contracts.md, quickstart.md, memory-synthesis.md, security-constraints.md (inline first pass), security-review-plan.md (independent second pass)

**Tests**: Included for the only new logic added by this feature — the boot-resilience guard in `includes/Main.php` + the activation guard in `acrossai-abilities-manager.php`. Constitution §VII mandates unit tests for "all new logic"; pure config edits (menu slug re-parenting, option_group string change, hook_suffix string update) are verified via manual quickstart per project convention.

**Organization**: Tasks grouped by user story (US1, US2, US3, US4 from spec.md). Phase 2 Foundational covers the shared host-menu bootstrap that every user story depends on; the boot-resilience work (US3, P1) is implemented EARLY in Phase 3 because the rest of the menu work risks re-fataling without it, and US1 cannot be safely test-driven on a fragile vendor state. All 7 findings from `security-review-plan.md` (SEC-001 .. SEC-007) are folded into the tasks below — see in-line `[SEC-NNN]` annotations.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files / no dependencies on incomplete tasks)
- **[Story]**: User story label (US1, US2, US3, US4) — present only in Phase 3+
- **[SEC-NNN]**: Folds a security-review-plan.md finding into the task

## Path Conventions

WordPress plugin (single project). Source under `includes/`, `admin/`, `acrossai-abilities-manager.php` at root. Tests under `tests/phpunit/`. No JS changes in this feature.

---

## Phase 1: Setup

**Purpose**: Pre-flight checks, dependency landing, and reference-doc hygiene before any code changes.

- [x] T001 [SEC-007] Fix line-number drift in `docs/planning/038-acrossai-main-menu-integration.md`: the existing `register_activation_hook` calls live at lines 68–69 (NOT "below line ~95"); the `acrossai_abilities_manager_run()` invocation is at line 97. Update TASK-1 and TASK-6 prose accordingly. File: `docs/planning/038-acrossai-main-menu-integration.md`
- [x] T002 Add `"acrossai-co/main-menu": "^0.0.2"` to the `require` block of `composer.json`. File: `composer.json`
- [x] T003 `composer update acrossai-co/main-menu` ran; ultimately bumped to ^0.0.4 (U001). `vendor/acrossai-co/main-menu/` present. Host package uses **PSR-4** (registered in `vendor/composer/jetpack_autoload_psr4.php` as `'AcrossAI_Main_Menu\\' => …/main-menu/src`), NOT the classmap — original T003 wording assumed classmap registration; corrected here. `composer.lock` regenerated.
- [x] T004 [SEC-003] [SEC-005] Vendor audit complete — **PASS**. Audited `acrossai-co/main-menu` v0.0.4 at SHA `a2c02cf178dd8bf44e1416ceaad00dcb5b279fe3`. All entry points (`MenuRegistrar::register_parent`, `register_settings_submenu`, `DashboardRenderer::render`, `PageRenderer::render`, per-tab capability check) gate on `manage_options`. All variable output escaped via `esc_html`/`esc_attr`/`esc_url`. Both forms post to `options.php` (Settings API handles nonce). `$_GET['tab']` sanitized via `sanitize_key( wp_unslash(…) )`. Zero `$wpdb`/`eval`/`exec`/`shell_exec`/`file_get_contents($…)`/`extract` patterns. SEC-003 + SEC-005 closed. Full audit recorded in `security-review-plan.md` → "Vendor Audit (T004 / SEC-003 + SEC-005 close-out)" section.
- [x] T005 Pre-flight confirmed: `node -v` = 22.22.0 (≥ 20, satisfies `DEC-NODE-20-BUILD-REQUIRED`); current branch is `038-acrossai-main-menu-integration`; `git status` shows modifications only inside the expected scope (entry file, `includes/Main.php`, `admin/`, `composer.json`, `composer.lock`, `uninstall.php`, `phpunit.xml.dist`, plus the spec/planning/test additions).

---

## Phase 2: Foundational (host menu bootstrap)

**Purpose**: Make the shared `acrossai` top-level menu exist on every admin request before any plugin submenu registration tries to bind to it. Every user story depends on this.

**⚠️ CRITICAL**: No user story phase can begin until Phase 2 is complete. Phase 2 ALSO requires Phase 3 US3 (boot resilience) to ship in the SAME release, but Phase 3 US3 can be implemented in parallel with Phases 3 US1, 4 US2, and 5 US4 once Phase 2 is in place.

- [x] T006 [SEC-004] Add the host menu bootstrap to `acrossai-abilities-manager.php` immediately before the `acrossai_abilities_manager_run();` call at line 97, as a `plugins_loaded` priority-0 callback with TWO guards: `did_action( 'acrossai_main_menu_bootstrapped' )` for multi-consumer idempotency AND `class_exists( \AcrossAI_Main_Menu\SettingsPage::class )` for graceful-degradation per §V. On a successful instantiation, also fire `do_action( 'acrossai_main_menu_bootstrapped' )` so later consumers short-circuit. The bootstrap MUST stay in this file — do not move it into `includes/Main.php`. File: `acrossai-abilities-manager.php`

   Reference shape (final wording is the implementer's):
   ```php
   add_action(
       'plugins_loaded',
       static function () {
           if ( did_action( 'acrossai_main_menu_bootstrapped' ) ) {
               return;
           }
           if ( class_exists( \AcrossAI_Main_Menu\SettingsPage::class ) ) {
               new \AcrossAI_Main_Menu\SettingsPage();
               do_action( 'acrossai_main_menu_bootstrapped' );
           }
       },
       0
   );
   ```

- [x] T007 Phase-2 checkpoint confirmed live: user-reported on a dev install that the AcrossAI top-level menu was missing → root-caused to `composer update` never having run → fixed by installing the package → menu appeared. Subsequent fatal ("Cannot declare class") root-caused to a smoke-test mu-plugin re-requiring the same source from a different path → fixed by adding `class_exists`+`did_action` paired guards to the mu-plugin. Final state: AcrossAI parent menu visible with the expected submenus.

**Checkpoint**: AcrossAI parent menu present; plugin's own pages still on their old top-level entry. Phases 3, 4, 5, and 6 unblocked.

---

## Phase 3: User Story 3 — Plugin never WSODs when its dependencies are absent (Priority: P1)

**Goal**: When `vendor/autoload_packages.php` is missing, the plugin (a) blocks activation with a friendly `wp_die`, (b) on already-active installations enters a degraded mode where the admin remains usable and a `manage_options`-gated admin notice tells the operator to run `composer install`, (c) never produces the existing `AcrossAI_Loader` class-not-found fatal at `includes/Main.php:228`.

**Independent Test**: Reproduce the 30-Jun-2026 fatal trace by renaming `vendor/` away; confirm `wp plugin activate acrossai-abilities-manager` fails with the friendly message AND `debug.log` contains NO `AcrossAI_Loader` fatal. Restore `vendor/` and confirm normal boot resumes without the notice. (Quickstart Q-008.)

### Tests for User Story 3 ⚠️

> Write FIRST, ensure they FAIL before T010/T011/T012 land.

- [x] T008 [P] [US3] PHPUnit test for `Main::$vendor_missing` flag: implemented as a structural source-inspection test in `tests/phpunit/Includes/Test_Boot_Resilience.php` (combined with T009 — single file). Rationale: the project's stub bootstrap cannot instantiate `Main` (Freemius dep blocks the autoloader and most WP globals are stubs); structural tests verify the durable contracts (property exists, flag is set on absence, constructor short-circuits before `load_dependencies()`). File: `tests/phpunit/Includes/Test_Boot_Resilience.php`
- [x] T009 [P] [US3] PHPUnit structural test for activation guard ordering: asserts `add_action( 'activate_' . plugin_basename( __FILE__ ), …, 1 )` registration is present, the callback checks `file_exists( __DIR__ . '/vendor/autoload_packages.php' )`, and the `wp_die` message uses `esc_html__()` with the `acrossai-abilities-manager` text domain. Combined with T008 — single file. File: `tests/phpunit/Includes/Test_Boot_Resilience.php`

### Implementation for User Story 3

- [x] T010 [US3] Added private `bool $vendor_missing = false;` property to `Main`. Updated `load_composer_dependencies()` to set the flag and `return;` early when `vendor/autoload_packages.php` is absent. No `error_log` call. File: `includes/Main.php`
- [x] T011 [SEC-001] [US3] `Main::__construct()` short-circuits when `$this->vendor_missing` is true. The admin notice callback is a `static function ()` closure (no `use($this)`), uses only `current_user_can`, `printf`, and `esc_html__` — zero references to `$this->`, `self::`, `static::`, or plugin-namespaced FQCNs. Closure body verified by the structural test added in T008/T009 (`test_admin_notice_closure_is_self_contained`). File: `includes/Main.php`

   Reference shape (final copy is the implementer's):
   ```php
   if ( $this->vendor_missing ) {
       add_action(
           'admin_notices',
           static function () {
               if ( ! current_user_can( 'manage_options' ) ) {
                   return;
               }
               printf(
                   '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
                   esc_html__( 'AcrossAI Abilities Manager:', 'acrossai-abilities-manager' ),
                   esc_html__( 'The Composer autoloader is missing. Run "composer install" inside the plugin directory to restore functionality.', 'acrossai-abilities-manager' )
               );
           }
       );
       return;
   }
   ```

- [x] T012 [SEC-002] [US3] Added activation guard via `add_action( 'activate_' . plugin_basename( __FILE__ ), $cb, 1 )` immediately below the existing `register_activation_hook` / `register_deactivation_hook` pair (lines 68–69). Runs at priority 1, before the existing default-priority-10 `acrossai_abilities_manager_activate` callback. `wp_die` message uses `esc_html__()` with the `acrossai-abilities-manager` text domain. File: `acrossai-abilities-manager.php`

   Reference shape (final copy is the implementer's):
   ```php
   add_action(
       'activate_' . plugin_basename( __FILE__ ),
       static function () {
           if ( ! file_exists( __DIR__ . '/vendor/autoload_packages.php' ) ) {
               wp_die(
                   esc_html__( 'AcrossAI Abilities Manager cannot activate: the Composer autoloader is missing. Run "composer install" inside the plugin directory and try again.', 'acrossai-abilities-manager' )
               );
           }
       },
       1
   );
   ```

- [ ] T013 [US3] Run quickstart Q-008 end-to-end: rename `vendor/` away → `wp plugin activate` MUST fail loudly + `debug.log` MUST contain no `AcrossAI_Loader` fatal; restore `vendor/` → next admin load MUST boot normally and the notice MUST disappear. Capture the verification output (terminal + grep of `debug.log`) and paste into the task's commit message OR into a `T013-verification.txt` next to this tasks file. Tests pass = the original 30-Jun-2026 reproducer no longer fires.

**Checkpoint**: US3 is fully functional and testable independently — the plugin survives missing vendor in three sequences (activation attempt, runtime degradation, restoration).

---

## Phase 4: User Story 1 — Unified AcrossAI menu for administrators (Priority: P1) 🎯 MVP

**Goal**: WP admin shows a single "AcrossAI" top-level entry whose submenus appear in the order Settings → Abilities → Library → Logs → Add-ons. The previous standalone "Abilities Manager" top-level entry is gone.

**Independent Test**: Activate the plugin on a fresh install where the host package is also present; the admin sidebar shows exactly one AcrossAI parent with the five submenus in the expected order, and clicking each loads its page without enqueue or render errors. (Quickstart Q-002 through Q-005.)

### Implementation for User Story 1

- [x] T014 [US1] `Menu::main_menu` → `Menu::register_submenu`; replaced `add_menu_page()` with `add_submenu_page( 'acrossai', …, 1 )` keeping menu_slug `'acrossai-abilities-manager'` and capability `'manage_options'`. Icon arg dropped. File: `admin/Partials/Menu.php`
- [x] T015 [US1] Updated `includes/Main.php` Loader registration to call the renamed `register_submenu` method. File: `includes/Main.php`
- [x] T016 [P] [US1] `LibraryMenu::register_submenu` parent_slug `'acrossai-abilities-manager'` → `'acrossai'`; added position arg `2`. Menu slug, capability, titles unchanged. File: `admin/Partials/LibraryMenu.php`
- [x] T017 [P] [US1] `LogsMenu::register_submenu` parent_slug → `'acrossai'`; added position arg `3`. File: `admin/Partials/LogsMenu.php`
- [x] T018 [US1] `AddonsPage` constructor first arg `'acrossai-abilities-manager'` → `'acrossai'`. No vendor changes. File: `includes/Main.php`
- [x] T019 [P] [US1] `admin/Main.php` `is_manager_page` returns `'acrossai_page_acrossai-abilities-manager' === $hook_suffix`. File: `admin/Main.php`
- [x] T020 [P] [US1] `admin/Main.php` `is_settings_page` returns `'acrossai_page_acrossai-settings' === $hook_suffix`. File: `admin/Main.php`
- [ ] T021 [US1] Quickstart Q-005 hook-suffix verification (MANDATORY per `BUG-LIBRARY-HOOK-SUFFIX`): temporarily add `error_log( '[ACROSSAI-038] hook_suffix=' . $hook_suffix );` inside `admin/Main.php::enqueue_scripts()` gated behind `defined('WP_DEBUG_LOG') && WP_DEBUG_LOG`. Load the Abilities page and the Settings page; confirm `debug.log` shows `hook_suffix=acrossai_page_acrossai-abilities-manager` and `hook_suffix=acrossai_page_acrossai-settings` respectively. Remove the `error_log()` line before commit. (Q-005 in quickstart.md.)

**Checkpoint**: US1 is fully functional — sidebar shows AcrossAI parent with five submenus in correct order; Abilities page enqueues its assets; the old top-level "Abilities Manager" entry is gone.

---

## Phase 5: User Story 2 — Existing settings survive the upgrade (Priority: P1)

**Goal**: Three plugin options (`acrossai_abilities_per_page`, `acrossai_abilities_log_retention_days`, `acrossai_abilities_uninstall_delete_data`) keep their pre-upgrade values and appear as sections inside the shared host Settings page. The custom standalone Settings page is gone; visiting its legacy URL returns the standard WP "page does not exist" response.

**Independent Test**: Pre-upgrade, set non-default values for all three options via `wp option update`. Apply Phase 5. Navigate to AcrossAI → Settings and confirm all three fields display the prior values; `wp option get <name>` returns the same values. Change a field, save, confirm round-trip persistence. (Quickstart Q-006.)

### Implementation for User Story 2

- [x] T022 [US2] Deleted `SettingsMenu::register_submenu()` and `SettingsMenu::render()`. Kept singleton scaffolding, sanitizer methods, `render_*_field()` callbacks, and `register_settings()`. File: `admin/Partials/SettingsMenu.php`
- [x] T023 [US2] In `register_settings()`: all three `register_setting()` option_group args and all three `add_settings_section()`/`add_settings_field()` `$page` args now use `'acrossai-settings'`. Option NAMES, sanitizers, defaults, section IDs, section titles unchanged. File: `admin/Partials/SettingsMenu.php`
- [x] T024 [US2] Deleted the `admin_menu` Loader registration for SettingsMenu in `includes/Main.php`. Kept the `admin_init` hook for `register_settings` and the `$settings_menu = SettingsMenu::instance();` line. File: `includes/Main.php`
- [ ] T025 [US2] Run quickstart Q-006 round-trip: pre-set values via `wp option update`; apply Phase 5; `wp option get` MUST return the same values; the AcrossAI → Settings page MUST display them non-blank; save a change → reload → new value persists. Capture the before/after `wp option get` output and paste into the task's commit message.

**Checkpoint**: US2 is fully functional — all three settings round-trip through the new host Settings page with zero data loss; legacy Settings URL returns the standard not-found response.

---

## Phase 6: User Story 4 — Existing bookmarks and external links keep working (Priority: P2)

**Goal**: Pre-upgrade bookmarked URLs (`wp-admin/admin.php?page=acrossai-abilities-manager`, `…library`, `…logs`) continue to resolve to their respective page contents post-upgrade. The plugin-action-links row "Settings" and "Logs" still works.

**Independent Test**: Before upgrade, bookmark each of the three URLs. After Phases 2–5, click each bookmark; each MUST load the matching page (Abilities Manager UI, Library list, Logs list) without 404 or redirect. (Quickstart Q-007 + Q-010.)

### Implementation for User Story 4

- [ ] T026 [US4] Verify by URL (no code change — this story is satisfied by-design through TASK-2/TASK-3 preserving menu_slugs): from a logged-in admin, hit each of `wp-admin/admin.php?page=acrossai-abilities-manager`, `wp-admin/admin.php?page=acrossai-abilities-library`, `wp-admin/admin.php?page=acrossai-abilities-logs`. Each MUST return HTTP 200 and render its full UI. Also hit `wp-admin/admin.php?page=acrossai-abilities-settings` (legacy Settings URL) and confirm it returns the standard WP "page does not exist" / "Cheatin', uh?" response — NO fallback handler MUST exist for this slug. (Quickstart Q-007.)
- [ ] T027 [US4] Verify the Plugins → Installed Plugins action-links row for this plugin: the "Settings" and "Logs" links (rendered by `admin/Main.php::plugin_action_links`) MUST still resolve to working pages. Source lines 384–385 reference the preserved menu_slugs so no code change is needed; this task is verification only.

**Checkpoint**: US4 is fully functional — bookmarks resolve; plugin-action links work; legacy Settings URL fails-soft.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [x] T028 [P] [SEC-006] Added `\delete_option( 'acrossai_abilities_per_page' );` inside the `if ( $acrossai_delete_data ) { ... }` block. File: `uninstall.php`

---

## Upgrade Addendum — `acrossai-co/main-menu` 0.0.2 → 0.0.4 (Tabs)

Post-implementation, the host package shipped a tabs feature (v0.0.4, 2026-06-30). The user requested adoption immediately ("we have tab so") to make the Settings page composition explicit and prevent silent breakage when sibling AcrossAI plugins register their own tabs (per the host README: "once any plugin registers a tab, sections still attached to the bare `'acrossai-settings'` slug are not rendered").

- [x] U001 Composer pin `acrossai-co/main-menu` bumped from `^0.0.2` to `^0.0.4`; `composer update` ran successfully (installed 0.0.4). File: `composer.json` + `composer.lock`
- [x] U002 Registered "Abilities" tab via the `acrossai_settings_tabs` filter. Added `SettingsMenu::TAB_SLUG = 'abilities'` constant and `SettingsMenu::register_tab( $tabs ): array` method appending `[ 'slug' => 'abilities', 'label' => __( 'Abilities', ... ), 'priority' => 10 ]`. Wired the filter via Loader in `includes/Main.php::define_admin_hooks()` alongside the existing `admin_init` registration. Switched all six `add_settings_section()`/`add_settings_field()` `$page` args from `'acrossai-settings'` to `\AcrossAI_Main_Menu\SettingsPage::tab_page_slug( self::TAB_SLUG )` (resolves to `'acrossai-settings-abilities'`). **`option_group` stays `'acrossai-settings'`** per the host's contract — the shared group keeps `register_setting`, the nonce, and the save flow working regardless of which tab a section lives under. Files: `admin/Partials/SettingsMenu.php`, `includes/Main.php`
- [x] U003 Reverted section title prefixes added one turn earlier ("Abilities — Display Settings" → "Display Settings", same for Log and Uninstall). The tab itself carries the "Abilities" scope; double-scoping the section titles inside it would be visual noise. The `PATTERN-SHARED-SETTINGS-SECTION-PREFIX` capture candidate (T032 d) was updated with the tab-mode nuance below. File: `admin/Partials/SettingsMenu.php`
- [x] U004 `php -l` + `vendor/bin/phpcs --standard=phpcs.xml.dist` clean on the two touched files. CLI verification of `SettingsPage::tab_page_slug()` fatals from a non-WP process (jetpack-autoloader calls `wp_normalize_path()`) — expected; the slug derivation works inside WordPress.

**Manual verification (you):**

- `/wp-admin/admin.php?page=acrossai-settings` should now show a `nav-tab-wrapper` with one tab labelled "Abilities". Clicking it shows Display / Log / Uninstall sections.
- Sibling AcrossAI plugins that later hook `acrossai_settings_tabs` will appear as additional tabs alongside Abilities — automatically, no code change needed.
- Save round-trip on any field should persist via `options.php` and reload onto the same tab (host uses `_wp_http_referer` to preserve the active tab).

**Hook-suffix impact**: NONE. The host's `MenuRegistrar::register_settings_submenu()` still registers `add_submenu_page( 'acrossai', …, 'acrossai-settings', … )`, so `$hook_suffix` is still `acrossai_page_acrossai-settings`. T019/T020 strings remain correct.

**Boot-flow impact**: NONE. Our `plugins_loaded` priority-0 bootstrap still constructs `\AcrossAI_Main_Menu\SettingsPage` exactly as before; 0.0.4 added the internal `DashboardRenderer` but kept the public constructor signature.

- [x] T029 Quality gates green: `composer dump-autoload`, `composer run phpcs`, `composer run phpstan`, `npm run lint:js` all pass after `admin/Partials/SettingsMenu.php` stray-blank-line fix. File: project-wide
- [ ] T030 Run the full quickstart Q-010 sidebar walkthrough on a fresh `wp-env` install: one "AcrossAI" top-level entry; five submenus in order Settings → Abilities → Library → Logs → Add-ons; every submenu loads; "Abilities Manager" is no longer top-level; plugin-action links work; no PHP notices in `debug.log` across the entire walkthrough. (Quickstart Q-010.)
- [x] T031 `CLAUDE.md` SPECKIT pointer reads `specs/038-acrossai-main-menu-integration/plan.md` (verified — set during /speckit-architecture-guard-governed-plan turn). File: `CLAUDE.md`
- [ ] T032 Capture memory entries via `/speckit-memory-md-capture` (USER TO RUN — not auto-triggered per saved preference `feedback_user_runs_speckit_commands`). Propose entries for:
    - **(a) NEW `PATTERN-ADMIN-NOTICE-SELF-CONTAINED`** — anchored to `includes/Main.php:286-298` and the T011 closure. Degraded-mode admin notices use only globally-available WP functions; no plugin-namespaced FQCNs, no `$this->`, no `self::`/`static::`, no `use($this)` inside the closure body. Otherwise the notice itself fatals in the very state it was meant to surface.
    - **(b) NEW `PATTERN-ACTIVATION-HOOK-EARLY-PRIORITY`** — from T012. Vendor-prerequisite activation guards register via `add_action( 'activate_' . plugin_basename( __FILE__ ), $cb, 1 )` (priority 1) so they execute BEFORE the default-priority-10 callback that depends on the prerequisite. Plain `register_activation_hook` runs at priority 10 and is too late.
    - **(c) NEW `PATTERN-SHARED-MENU-CONSUMER-IDEMPOTENCY`** — from T006 + refined post-implementation by a real-world incident with the `wp-content/mu-plugins/acrossai-tab-smoketest.php` smoke harness. Consumers of a shared external menu package (multiple AcrossAI plugins → one `acrossai-co/main-menu`) guard their bootstrap with BOTH checks paired:
        - `class_exists( '\\AcrossAI_Main_Menu\\SettingsPage', false )` — second arg `false` prevents the check from triggering the autoloader. Critical when ANOTHER copy of the package source was hard-`require_once`d from a different file path (development mu-plugin, alternate vendored copy, etc.). `require_once` dedupes by file path, NOT class name — so two different paths declaring the same class fatal with "Cannot declare class". A bare `class_exists($fqcn)` would trigger autoload here and could itself fatal mid-check.
        - `did_action( 'acrossai_main_menu_bootstrapped' )` — short-circuit if any consumer already fired the canonical scoped action.
        After a successful instantiation the consumer MUST fire `do_action( 'acrossai_main_menu_bootstrapped' )` so other consumers (loaded later in the request lifecycle) short-circuit cleanly. Pure `class_exists` alone is necessary but not sufficient; paired with `did_action` it covers both the "class already declared" and "menu already wired" failure modes.
    - **(d) NEW `PATTERN-SHARED-SETTINGS-SECTION-SCOPE`** — surfaced post-implementation by user feedback, then refined when v0.0.4 of the host package shipped per-plugin tabs. Rule: when contributing sections to the shared `acrossai-settings` Settings host, each plugin MUST scope its sections so admins can tell which plugin owns what. Two modes:
        - **Tabbed mode (preferred, v0.0.4+)** — register a tab via `add_filter( 'acrossai_settings_tabs', … )` and target it with `\AcrossAI_Main_Menu\SettingsPage::tab_page_slug( '<your-tab-slug>' )`. The tab title IS the scope; individual section titles stay plain ("Display Settings", not "Abilities — Display Settings"). Reference: `admin/Partials/SettingsMenu.php::register_tab()` in this plugin.
        - **Flat mode (fallback, only when no tab is registered)** — prefix every section title with the plugin scope using an em-dash: `__( 'Abilities — Display Settings', … )`. Required because the host renders sections in registration order with no visual separator between plugins.
        Implementation note: prefer tabbed mode whenever possible; flat-mode prefixing is a hedge for the rare case where a plugin can't register a tab (e.g. running against host < 0.0.4).
    - **(e) SCOPE EXTENSION** `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` — covering the entry-file bootstrap case from T006 (shared top-level menus must own their parent independently of any single consumer's Loader).
    - **(f) SCOPE EXTENSION** `DEC-STABLE-UPGRADE-WINDOW` — covering shared-internal AcrossAI org packages on 0.x (carve-out from the "wait for v1.0.0" rule).
    - **(g) SCOPE NOTE on `DEC-MENU-HOOK-SUFFIX`** — recording the `sanitize_title( 'AcrossAI' ) === 'acrossai'` dependency from T019/T020. Fragility flag: if the host package's menu title ever changes, both hardcoded suffix strings in `admin/Main.php` MUST update with it.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — can start immediately.
- **Phase 2 (Foundational)**: Depends on Phase 1 completion (T001–T005 ALL done). BLOCKS Phases 3, 4, 5, 6.
- **Phase 3 (US3 Boot Resilience)**: Depends on Phase 2 completion. Should land BEFORE Phases 4/5/6 ship to avoid compounding fatal risk during their testing, but Phases 4/5/6 can be CODED in parallel with Phase 3.
- **Phase 4 (US1 Unified Menu)**: Depends on Phase 2 completion.
- **Phase 5 (US2 Settings Survive)**: Depends on Phase 2 completion AND Phase 4 T015 (renames `Menu::main_menu` → `Menu::register_submenu` in the same `Main.php` block that Phase 5 T024 also edits — sequential).
- **Phase 6 (US4 Bookmarks)**: Depends on Phase 4 (preserved slugs are what makes US4 trivially pass) and Phase 5 (legacy Settings URL behaviour).
- **Phase 7 (Polish)**: Depends on Phases 3–6.

### User Story Dependencies

- **US3 (P1)**: Independent of US1/US2/US4 — purely a boot-time guard. Ship in same release as US1/US2 to avoid mixed-version fragility, but the code does not cross-depend.
- **US1 (P1)**: Independent of US2/US4. Touches `Main.php`, `Menu.php`, `LibraryMenu.php`, `LogsMenu.php`, `admin/Main.php`.
- **US2 (P1)**: Touches `Main.php` (line 264) and `SettingsMenu.php`. Coordinates with US1 only on the `Main.php` line ordering — split the diff carefully if implementing concurrently.
- **US4 (P2)**: Verification-only; satisfied by US1 + US2 preserving menu slugs and not registering a legacy Settings fallback.

### Within Each User Story

- Tests (US3 only) FIRST and FAIL before implementation.
- For US1/US2: edits to sibling files in `admin/Partials/` can run in parallel; edits that touch `Main.php` line by line must serialize.
- After each implementation task, run T029's quality-gate sweep for that TASK (do not batch).

### Parallel Opportunities

- Phase 1: T001 / T002 / T005 are [P]-able (different files). T003 depends on T002. T004 depends on T003.
- Phase 3 implementation: T010 / T011 / T012 touch different sections of files; T011 depends on T010 (uses the `$vendor_missing` flag T010 introduces). T012 in entry file is [P] with T010/T011 in `Main.php`.
- Phase 4: T016 + T017 (Library, Logs) are [P]. T019 + T020 (`admin/Main.php` lines 343, 354) are [P] with each other and with the partial edits. T014/T015/T018 all touch `Main.php` or `Menu.php` — serialize.
- Phase 5: T022 / T023 both edit `SettingsMenu.php` — serialize. T024 in `Main.php` — serialize with T015/T018 if those are in flight.
- Phase 7: T028 (`uninstall.php`) is [P] with the others.

---

## Parallel Example: User Story 1 (Phase 4)

```bash
# After T014 and T015 land in series (both touch Main.php / Menu.php contract):
Task: "Change LibraryMenu parent_slug + add position arg [T016]"   # admin/Partials/LibraryMenu.php
Task: "Change LogsMenu parent_slug + add position arg    [T017]"   # admin/Partials/LogsMenu.php

# Then in series (Main.php):
Task: "AddonsPage ctor first arg                          [T018]"   # includes/Main.php

# Then in parallel (admin/Main.php lines 343 and 354 are non-overlapping):
Task: "Update line 343 hook suffix                        [T019]"
Task: "Update line 354 hook suffix                        [T020]"

# Then verify:
Task: "Quickstart Q-005 hook-suffix verification          [T021]"
```

---

## Implementation Strategy

### MVP First (User Story 3 + User Story 1)

1. Complete Phase 1: Setup (composer dep + vendor audit + planning-doc fix).
2. Complete Phase 2: Foundational (host menu bootstrap).
3. Complete Phase 3: US3 (boot resilience) — landing this first makes everything else safe to test.
4. Complete Phase 4: US1 (unified menu) — visible deliverable, the central feature goal.
5. **STOP and VALIDATE**: Q-002 through Q-005 + Q-008. Plugin loads under any vendor state; sidebar shows the new structure.
6. This is a shippable MVP for an internal release. US2 + US4 add value but don't gate the release.

### Incremental Delivery

1. Phase 1 + Phase 2 → demo: AcrossAI parent visible; nothing else changed yet.
2. Phase 3 → demo: drop `vendor/`, plugin gracefully degrades with notice; restore, normal boot.
3. Phase 4 → demo: full sidebar re-parented, ordered correctly.
4. Phase 5 → demo: Settings appear inside the host page, values preserved.
5. Phase 6 → demo: bookmarks click through cleanly; legacy Settings URL fails-soft.
6. Phase 7 → release: quality gates green, uninstall data-minimization closed.

### Parallel Team Strategy

With multiple developers, once Phase 2 lands:

- Developer A: Phase 3 (US3 Boot Resilience) — `includes/Main.php` + `acrossai-abilities-manager.php` + new test files.
- Developer B: Phase 4 (US1 Unified Menu) — `admin/Partials/Menu.php` + `LibraryMenu.php` + `LogsMenu.php` + `admin/Main.php`.
- Developer C: Phase 5 (US2 Settings) — `admin/Partials/SettingsMenu.php`. Coordinate with B on the `includes/Main.php` line ordering.

US3 + US1 + US2 land in parallel; US4 is verified after all three.

---

## Notes

- [P] = different files / no incomplete dependencies.
- [Story] label maps task to spec.md user story for traceability.
- [SEC-NNN] annotations fold findings from `security-review-plan.md` into the implementation tasks rather than creating separate security-fix tasks.
- Verify tests fail before implementing (US3 only — other user stories are config-style edits verified via manual quickstart).
- Run quality gates AFTER EACH TASK, not batched (per planning doc's explicit constraint and Constitution §VII).
- Commit after each logical group (per TASK or per user-story phase, whichever the implementer prefers — both are acceptable).
- Stop at any Phase checkpoint to validate the current story independently.
- Avoid: vague tasks, same-file conflicts (the dependencies section calls these out per phase), cross-story dependencies that break independent test/deploy.

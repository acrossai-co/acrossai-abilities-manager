# Phase 0 Research: AcrossAI Main Menu Integration

**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

The user's planning doc and the spec resolve almost every research question. This file records the few derivations that depended on WordPress core behaviour or on the host package's public contract.

---

## R-001 — Submenu hook_suffix format under parent `acrossai`

**Decision**: Hardcode `acrossai_page_<menu_slug>` for every plugin submenu.

**Rationale**: WordPress derives the admin-page hook suffix in `get_plugin_page_hookname( $plugin_page, $parent_page )` (see `wp-admin/includes/plugin.php`). For a submenu registered via `add_submenu_page( 'acrossai', ... )`, the parent_page argument is `'acrossai'`, and the function looks up `$GLOBALS['admin_page_hooks']['acrossai']` to choose the `$page_type` prefix. That global is populated by `add_menu_page()` as `$admin_page_hooks[ $menu_slug ] = sanitize_title( $menu_title )`. For the host package, the menu title is the literal string `AcrossAI`, and `sanitize_title( 'AcrossAI' )` returns `'acrossai'` (lowercase, no separator changes needed). The final hook suffix is therefore `'acrossai' . '_page_' . $menu_slug`, e.g. `acrossai_page_acrossai-abilities-manager`.

**Alternatives considered**:

- Using `get_hook_suffix()` at runtime — rejected by `DEC-MENU-HOOK-SUFFIX`. The decision explicitly favours hardcoded strings to avoid the coupling and timing risk of querying a global.
- Building the string at runtime from the parent menu's title — rejected as fragile because the host package could rename the menu title in a minor release. The hardcoded form fails loudly in test instead of silently in production.

**Mitigation**: TASK-5 mandates a pre-commit verification step — log `$hook_suffix` from `admin_enqueue_scripts` on both pages and confirm equality with the hardcoded strings. Repeated in [quickstart.md](./quickstart.md) Q-005.

**Anti-pattern guard**: BUG-LIBRARY-HOOK-SUFFIX (submenu hook suffixes derive from `sanitize_title( parent menu title )`, not the parent slug). The match here is coincidental — recorded in `memory-synthesis.md` and surfaced for capture into `DEC-MENU-HOOK-SUFFIX` scope notes.

---

## R-002 — Where to bootstrap the host menu package

**Decision**: `add_action( 'plugins_loaded', function () { if ( class_exists( \AcrossAI_Main_Menu\SettingsPage::class ) ) { new \AcrossAI_Main_Menu\SettingsPage(); } }, 0 )` in `acrossai-abilities-manager.php`, immediately before the existing `acrossai_abilities_manager_run()` call.

**Rationale**: The host menu must exist before any submenu hooks fire on `admin_menu` default priority 10. Constructor self-registers hooks. `class_exists()` guard provides §V Integration Resilience graceful degradation. Entry-file placement makes the package the canonical owner of the top-level menu, independent of the plugin's internal Loader — a property required for a SHARED menu that multiple AcrossAI plugins will eventually consume.

**Alternatives considered**:

- Bootstrap inside `Main::define_admin_hooks()` (`DEC-EXTERNAL-PACKAGE-HOOK-CTOR` precedent) — rejected. `define_admin_hooks()` is called from `Main::__construct()`, which itself runs at `plugins_loaded` priority 10 (default). Adding the host bootstrap there would be too late for other AcrossAI plugins binding their own submenus on `admin_menu` P10.
- Bootstrap inside `Main::load_dependencies()` — rejected. Constitution §I Boot Flow Rule forbids hook-registering code in `load_dependencies()` ("No hook-registering code MAY run inside `load_dependencies()`").
- Bootstrap inside `Main::__construct()` directly — rejected for the same timing reason as the first alternative; also forces every consumer to host its own bootstrap, defeating the canonical-owner goal.

**Constitution interaction**: This is the new accepted deviation extending `DEC-EXTERNAL-PACKAGE-HOOK-CTOR`. Documented in plan.md Complexity Tracking. Proposed capture: extend the existing decision's scope to cover plugin-entry-file bootstraps for SHARED top-level menus.

---

## R-003 — Vendor-missing boot resilience

**Decision**: Two-pronged defence — `Main::load_composer_dependencies()` sets a private `$vendor_missing` flag when `vendor/autoload_packages.php` is absent; `Main::__construct()` checks the flag immediately after that call and short-circuits (registers a `manage_options`-gated `admin_notices` callback, then `return`s before `load_dependencies()`). Separately, `register_activation_hook` in the entry file blocks fresh activation with `wp_die()` when the autoloader is absent.

**Rationale**: The existing silent-skip pattern in `load_composer_dependencies()` (lines 199–209) is what causes the 30-Jun-2026 11:46:52 UTC fatal — once the autoloader fails to load, the next line (`$this->load_dependencies()` → `AcrossAI_Loader::instance()`) tries to resolve a class that has no autoload mapping registered. The fix is to detect the absence and STOP before the call site that needs the autoloader. Multisite-safety drives the no-auto-deactivate choice.

**Alternatives considered**:

- Auto-deactivate via `deactivate_plugins()` in `admin_init` — rejected because it surprises operators (their CI says "plugin active" but a runtime check says otherwise) and can cascade across multisite networks if vendor is missing on multiple sites at once.
- Log only via `error_log()` — rejected as user-invisible. A site operator on a fresh checkout sees nothing in `wp-admin` and has no signal pointing them at composer.
- Bail with `wp_die()` at every admin request — rejected as far too aggressive; the admin notice + soft-disabled plugin pattern preserves access to the rest of `wp-admin`.

**Constitution alignment**:

- §V Extensibility Without Core Modification — "Plugin MUST function correctly and degrade gracefully when an integrated plugin or service is absent." The autoloader is, conceptually, an integrated dependency.
- §IV Security First — admin notice gates on `current_user_can( 'manage_options' )` per `DEC-FAIL-OPEN-NOTICE`; the notice text uses `esc_html__()` with text domain `'acrossai-abilities-manager'`.

**Anti-pattern guards applied**: `BUG-EXTERNAL-PACKAGE-CTOR-SILENT` (silent ctor failure must surface via admin notice), `BUG-ABSPATH-STATIC-CLASS` (activation guard callback lives inside the file's existing `defined( 'ABSPATH' )` guard).

---

## R-004 — Settings re-registration on the host page

**Decision**: Change `option_group` (passed to `register_setting()`) and `$page` slug (passed to `add_settings_section()` / `add_settings_field()`) from `'acrossai_abilities_settings'` / `'acrossai-abilities-settings'` to the unified `'acrossai-settings'`. Preserve all three option NAMES, all three sanitizer callbacks, all three default values, all three section IDs, and all three section titles.

**Rationale**: The host page is a vanilla WP Settings API page where both the option_group and the `$page` slug are unified to `'acrossai-settings'`. Existing values continue to resolve because option NAMES are what `get_option()` keys on — option_group only affects what `options.php` accepts on the form-submit POST and which sections render where on `do_settings_sections( $page )`.

**Alternatives considered**:

- Rename option NAMES to also use the `acrossai-settings` prefix — rejected. Forces a one-time migration on every existing install, and the spec's SC-001 demands 100% lossless upgrade.
- Keep the custom Settings page as a hidden fallback — rejected by the planning doc's explicit constraint ("Do not register a fallback 'legacy' Settings page").

**Constitution alignment**: `DEC-SETTINGS-API-DEVIATION` continues to apply (≤5 scalar fields → WP Settings API is allowed instead of DataForm).

---

## R-005 — Submenu position values

**Decision**: Abilities `$position = 1`, Library `$position = 2`, Logs `$position = 3`. Settings is host-owned and lands first by host implementation. Add-ons inherits its order from the vendor's `admin_menu` priority 20 — no explicit position passed.

**Rationale**: Final sidebar order matches the agreed UX: Settings → Abilities → Library → Logs → Add-ons. Vendor packages cannot be assumed to honour explicit `$position` if they register past priority 10; AddonsPage's priority 20 reliably trails the plugin's own submenus.

**Verification**: Manual sidebar walkthrough after TASK-3 implementation (see [quickstart.md](./quickstart.md) Q-003).

---

## Notes

- All five research items are decided without `NEEDS CLARIFICATION` markers in the spec.
- No PR-level open questions remain. The plan can proceed to `/speckit-tasks` after the user reviews this file and the plan.

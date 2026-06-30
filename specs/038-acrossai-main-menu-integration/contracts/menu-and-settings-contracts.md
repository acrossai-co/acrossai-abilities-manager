# Contracts: Menu and Settings Public Surface

**Plan**: [../plan.md](../plan.md)

This file enumerates the public-surface elements that this plugin commits to as part of Feature 038. Each contract is named with a stable ID. Any future change to a contract requires a new `DEC-*` entry in `docs/memory/DECISIONS.md`.

---

## C-001 — Host package API (consumed by this plugin)

**Source**: `acrossai-co/main-menu` v0.0.2 (composer dependency).

| Symbol | Type | Value / signature | Used by |
|---|---|---|---|
| `\AcrossAI_Main_Menu\SettingsPage` | class | constructor self-registers `admin_menu` hooks (host-defined priority) | TASK-1 bootstrap |
| `\AcrossAI_Main_Menu\SettingsPage::PARENT_SLUG` | constant | `'acrossai'` | All submenu `add_submenu_page` parent_slug args |
| `\AcrossAI_Main_Menu\SettingsPage::SETTINGS_SLUG` | constant | `'acrossai-settings'` | Settings option_group + `$page` slug |

**Versioning**: Pinned to `^0.0.2` in `composer.json`. Upgrade to `^0.1.x` requires re-validating all contracts in this file.

**Failure mode**: If `\AcrossAI_Main_Menu\SettingsPage` is not autoloadable (vendor present but the package is absent — partial composer state), the `class_exists()` guard in the entry-file bootstrap silently skips. No top-level menu is created; the plugin's submenus then fail to render under any visible parent. This is acceptable degradation per §V Integration Resilience but is a different failure mode from "autoloader entirely missing" (see C-005).

---

## C-002 — Plugin submenus (registered by this plugin)

| Menu slug | Parent slug | Position arg | Capability | Method (renamed from / unchanged) | Source file |
|---|---|---|---|---|---|
| `acrossai-abilities-manager` | `'acrossai'` | 1 | `manage_options` | `register_submenu` (renamed from `main_menu`) | `admin/Partials/Menu.php` |
| `acrossai-abilities-library` | `'acrossai'` | 2 | (existing) | `register_submenu` (unchanged) | `admin/Partials/LibraryMenu.php` |
| `acrossai-abilities-logs` | `'acrossai'` | 3 | (existing) | `register_submenu` (unchanged) | `admin/Partials/LogsMenu.php` |

**Invariants**:

- Menu slugs are stable. They do NOT change in this feature; they are part of the plugin's public URL surface (`wp-admin/admin.php?page=<menu-slug>`).
- The `Menu::contents()` callback signature and the `'dashicons-admin-tools'` icon argument are removed only because submenus don't accept an icon — capability and slug stay.

---

## C-003 — Settings persistence (consumed by `get_option()` everywhere they are read)

| Option name | option_group (CHANGED) | $page slug (CHANGED) | Sanitizer (unchanged) | Section ID (unchanged) |
|---|---|---|---|---|
| `acrossai_abilities_per_page` | `acrossai-settings` | `acrossai-settings` | `SettingsMenu::sanitize_per_page` (existing) | (existing) |
| `acrossai_abilities_log_retention_days` | `acrossai-settings` | `acrossai-settings` | `SettingsMenu::sanitize_log_retention_days` (existing) | (existing) |
| `acrossai_abilities_uninstall_delete_data` | `acrossai-settings` | `acrossai-settings` | existing checkbox sanitizer (PATTERN-CHECKBOX-SANITIZE) | (existing) |

**Invariants**:

- Option NAMES are immutable across this upgrade. Spec SC-001 requires 100% lossless settings preservation.
- Sanitizer callbacks, default values, and section IDs/titles are immutable across this upgrade.
- `option_group` and `$page` slug change to the unified `'acrossai-settings'` value (host's public API constant `\AcrossAI_Main_Menu\SettingsPage::SETTINGS_SLUG`).

---

## C-004 — Admin hook-suffix strings (consumed in `admin/Main.php`)

| Hook suffix | Source page | Consumer | Constraint |
|---|---|---|---|
| `acrossai_page_acrossai-abilities-manager` | Abilities submenu (formerly top-level) | `admin/Main.php:343` page-gated enqueue check | MUST equal the `$hook_suffix` returned by `add_submenu_page` for the Abilities page. Verified at TASK-5 pre-commit. |
| `acrossai_page_acrossai-settings` | Host Settings page (formerly plugin's standalone settings submenu) | `admin/Main.php:354` page-gated enqueue check | MUST equal the `$hook_suffix` returned by the host's `add_submenu_page` for `acrossai-settings`. Verified at TASK-5 pre-commit. |

**Derivation**: WordPress builds the suffix as `sanitize_title( parent_menu_title ) . '_page_' . menu_slug`. The host's parent menu title is `'AcrossAI'`; `sanitize_title( 'AcrossAI' ) === 'acrossai'`. Both strings hold.

**Fragility**: If the host package ever changes its parent menu title, these strings will diverge silently and the React UI on the Abilities page + the Settings stylesheet will both fail to load with no error. The TASK-5 verification step is the regression guard; the dependency is recorded for capture into `DEC-MENU-HOOK-SUFFIX` scope notes.

---

## C-005 — Vendor-missing admin notice (registered by `Main`)

| Property | Value |
|---|---|
| Hook | `admin_notices` |
| Capability gate | `current_user_can( 'manage_options' )` — required by `DEC-FAIL-OPEN-NOTICE`; silent return for users without the cap |
| CSS class | `notice notice-error` |
| Body i18n | `esc_html__( 'AcrossAI Abilities Manager: The composer autoloader is missing. Run "composer install" inside the plugin directory to restore functionality.', 'acrossai-abilities-manager' )` (exact wording to be finalized at implementation; the contract is "actionable, escaped, correct text domain") |
| Dismissible | NO — the underlying condition persists across requests; a dismiss would only mask the problem |
| Side effects | The plugin does NOT register menus, hooks, or settings while degraded. WordPress shows the plugin as "Active" in the plugins list but it is functionally off. |

**Multisite behaviour**: The notice fires on every admin screen for users who have `manage_options` on the current blog. The plugin does NOT call `deactivate_plugins()` — preserving multisite-safety.

---

## C-006 — Activation guard (registered in `acrossai-abilities-manager.php`)

| Property | Value |
|---|---|
| Registration | `register_activation_hook( __FILE__, callable )` |
| Precondition check | `file_exists( __DIR__ . '/vendor/autoload_packages.php' )` |
| Failure handler | `wp_die( esc_html__( 'AcrossAI Abilities Manager cannot activate: the composer autoloader is missing. Run "composer install" inside the plugin directory and try again.', 'acrossai-abilities-manager' ) )` |
| Outcome on pass | Activation proceeds normally; no further side effects from this guard |

---

## C-007 — Out of scope (NOT contracts of this feature)

- The host's vendor code under `vendor/acrossai-co/main-menu/` and `vendor/acrossai-co/addons-page/` is owned by its publishers. This feature does NOT touch it; if changes are needed, they live upstream.
- The legacy Settings URL `wp-admin/admin.php?page=acrossai-abilities-settings` is intentionally unregistered post-upgrade. The standard WP "page does not exist" response is acceptable.
- No new REST routes, no new AJAX endpoints, no new JS handles, no new SCSS files.

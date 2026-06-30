# Phase 1 Data Model: AcrossAI Main Menu Integration

**Plan**: [plan.md](./plan.md)

This feature does not introduce database schema changes. The "data model" here describes the menu hierarchy, the option storage contract, and the boot-resilience state machine.

---

## E-001 — Admin menu hierarchy

**Owner**: `acrossai-co/main-menu` package (vendor) registers the parent. This plugin and the `acrossai-co/addons-page` package register submenus.

| Slug | Role | Registered by | Hook / priority | Position arg |
|---|---|---|---|---|
| `acrossai` | Top-level menu | Host package `\AcrossAI_Main_Menu\SettingsPage` constructor (called on `plugins_loaded` P0 by this plugin's entry file) | `admin_menu` (host-defined priority) | host-defined |
| `acrossai-settings` | Host Settings page (submenu) | Host package | `admin_menu` (host-defined, before plugin submenus) | implicit first |
| `acrossai-abilities-manager` | Abilities (submenu) | `Menu::register_submenu` (renamed from `main_menu`) | `admin_menu` P10 | 1 |
| `acrossai-abilities-library` | Library (submenu) | `LibraryMenu::register_submenu` | `admin_menu` P10 | 2 |
| `acrossai-abilities-logs` | Logs (submenu) | `LogsMenu::register_submenu` | `admin_menu` P10 | 3 |
| `wpb-addons` | Add-ons (submenu, vendor) | `AddonsPage` constructor invoked in `Main::define_admin_hooks()` with `class_exists` guard; vendor binds on `admin_menu` P20 | `admin_menu` P20 | (none — vendor priority sorts last) |

**Invariants**:

- Menu slugs are stable. They were the slugs before this feature; they remain the slugs after.
- The host parent MUST be registered before the plugin's submenu hooks fire — enforced by `plugins_loaded` P0 bootstrap.
- The plugin MUST NOT call `add_menu_page()` anywhere; `Menu.php` transitions to `add_submenu_page()`.

---

## E-002 — Plugin settings (three `wp_options` rows)

| Option name | Sanitizer (preserved) | Default (preserved) | Section ID (preserved) | Section title (preserved) | $page (CHANGED) | option_group (CHANGED) |
|---|---|---|---|---|---|---|
| `acrossai_abilities_per_page` | existing | existing | existing | existing | `acrossai-settings` | `acrossai-settings` |
| `acrossai_abilities_log_retention_days` | existing | existing | existing | existing | `acrossai-settings` | `acrossai-settings` |
| `acrossai_abilities_uninstall_delete_data` | existing | existing | existing | existing | `acrossai-settings` | `acrossai-settings` |

**Persistence contract**:

- Option NAMES are what `get_option()` keys on; preserving them guarantees existing-value resolution after upgrade (spec SC-001).
- `option_group` only governs the form-submit handshake at `options.php` and which `do_settings_sections()` call renders each section.
- `$page` slug only governs which page the section renders on.
- Neither `option_group` nor `$page` slug touches the stored value.

---

## E-003 — Boot-resilience state machine (`Main`)

```text
                        ┌─────────────────────────────┐
                        │   __construct() entered     │
                        └─────────────┬───────────────┘
                                      ▼
                        ┌─────────────────────────────┐
                        │  load_composer_dependencies │
                        └─────────────┬───────────────┘
                                      ▼
                  ┌───────────────────┴───────────────────┐
                  │ file_exists(autoload_packages.php)?   │
                  └───────┬───────────────────────┬───────┘
                          │ yes                   │ no
                          ▼                       ▼
                  ┌──────────────────┐   ┌──────────────────────┐
                  │ require_once it   │   │ $vendor_missing=true │
                  │ $vendor_missing=  │   └──────────┬───────────┘
                  │ false (default)   │              ▼
                  └────────┬──────────┘   ┌──────────────────────────────┐
                           ▼              │ register admin_notices       │
              ┌─────────────────────────┐ │ callback (manage_options     │
              │ load_dependencies()      │ │ gated, esc_html__-escaped,  │
              │ → AcrossAI_Loader::      │ │ acrossai-abilities-manager  │
              │   instance()             │ │ text domain)                │
              └────────┬─────────────────┘ │ return early — no           │
                       ▼                   │ load_dependencies, no       │
              ┌──────────────────┐         │ load_hooks                  │
              │ load_hooks()      │         └──────────────────────────────┘
              └──────────────────┘
```

**States**:

- `vendor_missing = false` (default): normal boot, full hook registration.
- `vendor_missing = true`: degraded boot, admin notice only, no hook registration, no menu, no fatals.

**Transitions**:

- Normal → degraded: physical removal of `vendor/autoload_packages.php` (e.g. incomplete deploy). Next request boots degraded.
- Degraded → normal: restoring `vendor/` (e.g. running `composer install`). Next request boots normal; notice disappears.

---

## E-004 — Activation guard

| Trigger | File | Behaviour |
|---|---|---|
| `register_activation_hook( __FILE__, callable )` in `acrossai-abilities-manager.php` | `acrossai-abilities-manager.php` | If `file_exists( __DIR__ . '/vendor/autoload_packages.php' )` is false, `wp_die()` with an `esc_html__()`-escaped message in text domain `'acrossai-abilities-manager'` instructing the operator to run `composer install`. Prevents partial activation. |

---

## E-005 — Page-gated asset enqueue contract

| Hook suffix | Enqueue context | Source line |
|---|---|---|
| `acrossai_page_acrossai-abilities-manager` | React app + styles for Abilities | `admin/Main.php:343` (was `toplevel_page_acrossai-abilities-manager`) |
| `acrossai_page_acrossai-settings` | Settings-page styles | `admin/Main.php:354` (was `acrossai-abilities-manager_page_acrossai-abilities-settings`) |

Verification of these strings is a hard pre-commit step in TASK-5 (see [quickstart.md](./quickstart.md) Q-005).

---

## E-006 — Out-of-scope (explicitly NOT in this feature's data model)

- No new DB tables, no `wp_postmeta`/`wp_usermeta` keys, no `wp_options` rows added beyond the three preserved ones.
- No new REST routes, no new AJAX endpoints, no new nonces.
- No new JS handles, no new SCSS files, no `webpack` entries.
- No Action Scheduler jobs.
- No CLI commands.

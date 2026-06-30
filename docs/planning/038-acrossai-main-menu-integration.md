# Planning: AcrossAI Main Menu Package Integration (Feature 038)

Adopt the shared `acrossai-co/main-menu` package (v0.0.2+) as the plugin's
top-level admin menu and unified Settings host. Move every existing submenu
(Library, Logs, Add-ons) under the new `acrossai` parent, convert the current
top-level "Abilities Manager" page into a submenu titled "Abilities", remove
the custom Settings page, and re-register the three existing settings against
the host's WP Settings API page slug so they appear inside the shared
Settings page.

---

## Speckit Workflow

```markdown
# 1. Branch
/speckit.git.feature "acrossai-main-menu-integration"

# 2. Specify
/speckit.specify "Adopt acrossai-co/main-menu v0.0.2 as the shared top-level
admin menu and unified Settings host. Re-parent Library, Logs, Add-ons under
the new `acrossai` menu. Convert the existing top-level Abilities Manager page
into a submenu titled `Abilities`. Remove the custom Settings page and
re-register the three settings (per_page, log_retention_days,
uninstall_delete_data) against the host's Settings API page/group slug
`acrossai-settings` so they render as sections inside the shared host page.
Preserve all three option names so existing user values continue to resolve.
Patch the two hardcoded hook_suffix comparisons in admin/Main.php to match
the new parent."
```

### Detailed Description for `/speckit.specify`

> **Before writing a single line of code, read and internalize all three of
> these governing documents in full:**
>
> 1. `.agents/skills/wp-plugin-development/SKILL.md` — and every file under
>    `.agents/skills/wp-plugin-development/references/` (boot-flow, security,
>    structure, hooks, rest-api).
> 2. `.specify/memory/CONSTITUTION.md` — pay special attention to §I Modular
>    Architecture, §II WordPress Standards, §V Integration Resilience,
>    §VI DRY, §VII Definition of Done, and the Boot Flow Rule and Module
>    Contract in the Architecture section.
> 3. `AGENTS.md` — the singleton pattern block, hook registration rules, and
>    the Before Commit Checklist.
>
> Then read the package's documentation:
>
> - `https://github.com/acrossai-co/main-menu/blob/0.0.2/README.md`
> - `https://github.com/acrossai-co/main-menu/releases/tag/0.0.2`
>
> Public API the work depends on:
> `\AcrossAI_Main_Menu\SettingsPage::PARENT_SLUG === 'acrossai'`,
> `\AcrossAI_Main_Menu\SettingsPage::SETTINGS_SLUG === 'acrossai-settings'`.
> The host page is a standard WP Settings API host: both the option_group
> and the `$page` slug passed to `add_settings_section()` /
> `add_settings_field()` are the unified string `'acrossai-settings'`.
>
> Every decision in this task — composer pinning, bootstrap hook, parent
> slug change, settings re-registration, hook_suffix string updates — must be
> justified against the three governing documents above. If a choice is not
> explicitly covered, default to the most restrictive interpretation of the
> Constitution. Do not write code that would fail any Definition-of-Done gate:
> PHPStan level 8, PHPCS, security review, all `__()` calls using the correct
> text domain `'acrossai-abilities-manager'`, and
> `npm run validate-packages`.
>
> ---
>
> **TASK-1 — Add composer dependency and bootstrap the host menu**
>
> Files: `composer.json`, `composer.lock`, `acrossai-abilities-manager.php`
>
> Read `.agents/skills/wp-plugin-development/references/boot-flow.md` and
> `CONSTITUTION.md §V Integration Resilience` before editing either file.
>
> Add the package to `composer.json` `require`:
> ```json
> "acrossai-co/main-menu": "^0.0.2"
> ```
> Run `composer update acrossai-co/main-menu` and commit the updated
> `composer.lock`. The package depends on `automattic/jetpack-autoloader: ^5.0`,
> already required by this plugin, so it boots through the existing
> `vendor/autoload_packages.php` require at `includes/Main.php:206`. Do not
> add a second autoload include.
>
> In `acrossai-abilities-manager.php`, just before the existing
> `acrossai_abilities_manager_run()` invocation at line **97** (NOT
> line ~95 — that was an earlier draft), register a guarded bootstrap
> on `plugins_loaded` with priority `0` so the host menu exists by the
> time any submenu hooks fire on default priority `10`:
> ```php
> add_action(
>     'plugins_loaded',
>     function () {
>         if ( class_exists( \AcrossAI_Main_Menu\SettingsPage::class ) ) {
>             new \AcrossAI_Main_Menu\SettingsPage();
>         }
>     },
>     0
> );
> ```
> The `class_exists` guard mirrors the AddonsPage pattern at
> `includes/Main.php:273` and satisfies §V Integration Resilience: graceful
> behaviour when vendor is absent. Do not move this bootstrap into
> `includes/Main.php` — keeping it in the main plugin file makes the package
> the single canonical owner of the top-level menu, independent of the
> plugin's internal Loader.
>
> ---
>
> **TASK-2 — Convert the top-level "Abilities Manager" page to a submenu**
>
> Files: `admin/Partials/Menu.php`, `includes/Main.php`
>
> Read `CONSTITUTION.md §I Modular Architecture` and
> `AGENTS.md §Hook registration rules` before editing.
>
> In `admin/Partials/Menu.php`, replace the `add_menu_page()` call in
> `main_menu()` (lines 59-67) with `add_submenu_page()` under parent
> `'acrossai'`. Keep the menu_slug as `'acrossai-abilities-manager'` — do not
> rename it. This minimises blast radius: the JS bundle handles, the
> `admin.php?page=...` URL in the plugin action link at `admin/Main.php:384`,
> and any external references stay valid.
>
> ```php
> add_submenu_page(
>     'acrossai',
>     __( 'Abilities Manager', 'acrossai-abilities-manager' ),
>     __( 'Abilities', 'acrossai-abilities-manager' ),
>     'manage_options',
>     'acrossai-abilities-manager',
>     array( $this, 'contents' ),
>     1
> );
> ```
>
> The 7th argument (`$position = 1`) places this submenu immediately after
> the host Settings entry, matching the agreed sidebar order: Settings,
> Abilities, Library, Logs, Add-ons.
>
> Do not change `Menu::contents()`, the `dashicons-admin-tools` icon, the
> capability, or the constructor signature. The icon argument is dropped
> because submenus do not accept one. Rename the method from `main_menu` to
> `register_submenu` for clarity and consistency with sibling classes
> (`LibraryMenu::register_submenu`, `LogsMenu::register_submenu`).
>
> In `includes/Main.php` line 251, update the Loader call to match the
> renamed method:
> ```php
> $this->loader->add_action( 'admin_menu', $main_menu, 'register_submenu' );
> ```
> Do not change variable naming or move the registration order.
>
> ---
>
> **TASK-3 — Re-parent Library, Logs, and Add-ons under `acrossai`**
>
> Files: `admin/Partials/LibraryMenu.php`,
>        `admin/Partials/LogsMenu.php`,
>        `includes/Main.php`
>
> Read `CONSTITUTION.md §I Modular Architecture` before editing.
>
> In `admin/Partials/LibraryMenu.php` at line 70 (the parent_slug arg of
> `add_submenu_page()`), change `'acrossai-abilities-manager'` to
> `'acrossai'`. Add a 7th `$position = 2` argument so Library lands after
> "Abilities". Do not change the menu_slug `'acrossai-abilities-library'`,
> capability, page/menu titles, or the `register_submenu()` method name.
>
> In `admin/Partials/LogsMenu.php` at line 78, apply the same parent_slug
> change. Add `$position = 3`. Keep menu_slug `'acrossai-abilities-logs'`
> unchanged.
>
> In `includes/Main.php` at line 276 (the first argument of the
> `new \AcrossAI_Addon\AddonsPage( ... )` constructor inside the guarded
> `class_exists` block), change `'acrossai-abilities-manager'` to
> `'acrossai'`. Leave every other constructor arg (the Freemius config, the
> plugin file constant) unchanged. Do not touch any file under
> `vendor/acrossai-co/addons-page/` — vendor code is owned by the package.
> The Add-ons submenu inherits its order from the vendor's hook priority
> (currently `admin_menu` priority 20), so no explicit position is needed.
>
> ---
>
> **TASK-4 — Remove the custom Settings page; re-register settings on
> the host**
>
> Files: `admin/Partials/SettingsMenu.php`, `includes/Main.php`
>
> Read `CONSTITUTION.md §IV Security First`, `§V Integration Resilience`,
> and `.agents/skills/wp-plugin-development/references/security.md` before
> editing. The host page is the standard WP Settings API: option_group and
> `$page` slug are both `'acrossai-settings'`.
>
> In `admin/Partials/SettingsMenu.php`:
> - Delete `register_submenu()` (lines 66-75) entirely — the host owns the
>   menu entry now.
> - Delete `render()` (lines 245-261) — the host renders the form.
> - In `register_settings()` change the option_group passed to all three
>   `register_setting()` calls (lines 88, 98, 108) from
>   `'acrossai_abilities_settings'` to `'acrossai-settings'`.
> - In `register_settings()` change the `$page` slug passed to all three
>   `add_settings_section()` calls (lines 121, 137, 153) and all three
>   `add_settings_field()` calls (lines 128, 144, 160) from
>   `'acrossai-abilities-settings'` to `'acrossai-settings'`.
> - Do NOT change the option NAMES: `acrossai_abilities_log_retention_days`,
>   `acrossai_abilities_uninstall_delete_data`, `acrossai_abilities_per_page`
>   must remain identical so existing values continue to resolve via
>   `get_option()` everywhere they are read.
> - Do NOT change the three `render_*_field()` callbacks, the two sanitizer
>   methods, the default values, the section IDs, or the section titles.
>   The user-facing form layout is identical — only the host slug changes.
>
> In `includes/Main.php` at line 264, delete the
> `$this->loader->add_action( 'admin_menu', $settings_menu, 'register_submenu' )`
> line. Keep the `admin_init` hook for `register_settings` on line 265 — the
> settings still need to register on every admin request. Keep the
> `$settings_menu = SettingsMenu::instance();` line above it.
>
> ---
>
> **TASK-5 — Update hardcoded hook_suffix comparisons**
>
> File: `admin/Main.php`
>
> Read `.agents/skills/wp-plugin-development/references/hooks.md` before
> editing. WordPress derives `$hook_suffix` from the page's parent slug and
> menu_slug. Moving these pages under `acrossai` rewrites both suffixes; the
> page-gated enqueue checks must update with them.
>
> - Line 343: change
>   `'toplevel_page_acrossai-abilities-manager' === $hook_suffix`
>   to
>   `'acrossai_page_acrossai-abilities-manager' === $hook_suffix`
>   (formerly top-level, now submenu of `acrossai`).
> - Line 354: change
>   `'acrossai-abilities-manager_page_acrossai-abilities-settings' === $hook_suffix`
>   to
>   `'acrossai_page_acrossai-settings' === $hook_suffix`
>   (Settings is now the host page under `acrossai`).
>
> Do not change the plugin action link URLs at lines 384-385 — they reference
> `admin.php?page=acrossai-abilities-manager` and
> `admin.php?page=acrossai-abilities-logs`, which still resolve correctly
> because the menu_slugs were preserved in TASK-2 and TASK-3.
>
> Before committing, briefly verify the two suffix strings by logging
> `$hook_suffix` from `admin_enqueue_scripts` on the Abilities and Settings
> pages and confirming the strings match what's in the code. Remove the
> debug log before committing.
>
> ---
>
> **TASK-6 — Fail gracefully when the composer autoloader is missing**
>
> Files: `acrossai-abilities-manager.php`, `includes/Main.php`
>
> Read `CONSTITUTION.md §V Integration Resilience` and
> `.agents/skills/wp-plugin-development/references/boot-flow.md` before
> editing. This task makes the existing TASK-1 acceptance check —
> "with the vendor directory removed, the plugin still activates without
> fatal errors" — actually pass. The current boot path silently skips the
> autoloader require at `includes/Main.php:206-208` when
> `vendor/autoload_packages.php` is absent, then fatals two lines later at
> `includes/Main.php:228` (`AcrossAI_Loader::instance()`) because the PSR-4
> autoloader is the only thing that maps
> `AcrossAI_Abilities_Manager\Includes\AcrossAI_Loader` to its file.
>
> Reproducer (observed on 30-Jun-2026):
> ```
> PHP Fatal error: Uncaught Error: Class
> "AcrossAI_Abilities_Manager\Includes\AcrossAI_Loader" not found
>   in .../includes/Main.php:228
> #0 .../includes/Main.php(125): Main->load_dependencies()
> #1 .../includes/Main.php(142): Main->__construct()
> #2 .../acrossai-abilities-manager.php(90): Main::instance()
> ```
>
> In `includes/Main.php`:
> - Add a private boolean property `$vendor_missing = false;` alongside the
>   other private properties.
> - In `load_composer_dependencies()` (lines 199-209), when the
>   `file_exists()` check fails, set `$this->vendor_missing = true;` and
>   return early. Do not silently fall through.
> - In `__construct()` (lines 113-128), immediately after
>   `$this->load_composer_dependencies();` (line 123), check
>   `$this->vendor_missing`. If true: register an `admin_notices` callback
>   that prints an error-class notice instructing admins to run
>   `composer install` (text domain `'acrossai-abilities-manager'`,
>   escaped with `esc_html__`), then `return;` before
>   `load_dependencies()` and `load_hooks()`. Do not attempt to call the
>   Loader, define hooks, or build the React app — the plugin runs in a
>   degraded "needs install" mode until vendor is restored.
> - Do not auto-deactivate the plugin. WordPress will keep it "active" in
>   the list with a persistent notice, mirroring the WooCommerce missing-
>   dependency pattern and avoiding side effects on multisite networks.
>
> In `acrossai-abilities-manager.php`:
> - Register the activation-time guard alongside the existing
>   `register_activation_hook` / `register_deactivation_hook` pair at
>   lines **68–69** (NOT "below line ~95" — that was an earlier draft).
>   Register via `add_action( 'activate_' . plugin_basename( __FILE__ ),
>   $callback, 1 )` so it executes at priority 1, BEFORE the existing
>   default-priority-10 `acrossai_abilities_manager_activate` callback
>   that requires the autoloader. If the existing callback ran first
>   on a missing-vendor install it would fatal before our guard could
>   `wp_die`. The callback checks `file_exists( __DIR__ .
>   '/vendor/autoload_packages.php' )` and, if missing, calls `wp_die()`
>   with an i18n'd message explaining that `composer install` must be run
>   before activation. Use the `'acrossai-abilities-manager'` text domain
>   and `esc_html__`. This prevents fresh activation from ever fataling.
>
> Do not move the boot-resilience flag into a global, a separate singleton,
> or a new class. Keep the check inside `Main` so it lives next to the
> Loader it gates. Do not log the missing-vendor condition to `error_log` —
> the admin notice is the user-facing signal; PHP's own error logger
> already records anything that would still fatal.
>
> ---
>
> **CONSTRAINTS**
>
> - Do not rename the existing menu_slugs `acrossai-abilities-manager`,
>   `acrossai-abilities-library`, or `acrossai-abilities-logs`. Only their
>   parent and registration function change.
> - Do not rename the three option names. Option_group changes; option names
>   stay so existing data carries over without migration.
> - Do not touch any file under `vendor/`. The Add-ons submenu slug
>   `wpb-addons` remains owned by the vendor package.
> - Do not introduce a new JS bundle. v0.0.2 of the package is pure PHP —
>   no host JS handle to depend on, and no Fill bundle to ship.
> - Do not register a fallback "legacy" Settings page. The custom Settings
>   page is removed cleanly; users with the old URL get the standard
>   "page does not exist" admin response.
> - Every task must leave PHPStan level 8 and PHPCS passing individually —
>   do not batch tasks and check only at the end.
> - Manually test the WP admin sidebar after each TASK — confirm menu
>   ordering and clickability before moving on.

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer dump-autoload
composer run phpcs
composer run phpstan
npm run lint:js

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual Verification Checklist

### TASK-1 — Composer dependency and bootstrap
- [ ] `composer.json` `require` includes `"acrossai-co/main-menu": "^0.0.2"`.
- [ ] `composer.lock` regenerated and committed.
- [ ] `vendor/acrossai-co/main-menu/` exists; `vendor/composer/jetpack_autoload_classmap.php` includes `AcrossAI_Main_Menu\SettingsPage`.
- [ ] `acrossai-abilities-manager.php` registers a guarded `plugins_loaded`/priority-0 callback that constructs `\AcrossAI_Main_Menu\SettingsPage`.
- [ ] With the vendor directory removed, the plugin still activates without fatal errors (Integration Resilience guard works).

### TASK-2 — Top-level converted to "Abilities" submenu
- [ ] `admin/Partials/Menu.php` calls `add_submenu_page()` (not `add_menu_page`), parent `'acrossai'`, menu_slug `'acrossai-abilities-manager'`, menu title "Abilities", position `1`.
- [ ] `Menu::main_menu` renamed to `Menu::register_submenu`.
- [ ] `includes/Main.php` line 251 hooks the renamed method.
- [ ] WP admin sidebar shows "Abilities" as a submenu under "AcrossAI", and clicking it loads the React app root.

### TASK-3 — Library, Logs, Add-ons re-parented
- [ ] `admin/Partials/LibraryMenu.php:70` parent_slug is `'acrossai'`; position is `2`.
- [ ] `admin/Partials/LogsMenu.php:78` parent_slug is `'acrossai'`; position is `3`.
- [ ] `includes/Main.php:276` AddonsPage constructor first arg is `'acrossai'`.
- [ ] Sidebar order under AcrossAI: Settings → Abilities → Library → Logs → Add-ons.
- [ ] No leftover "Abilities Manager" top-level entry.

### TASK-4 — Custom Settings page removed, sections moved to host
- [ ] `SettingsMenu::register_submenu()` and `SettingsMenu::render()` deleted.
- [ ] `register_setting()` calls use option_group `'acrossai-settings'`.
- [ ] `add_settings_section()` and `add_settings_field()` calls use `$page = 'acrossai-settings'`.
- [ ] Option names unchanged: `acrossai_abilities_log_retention_days`, `acrossai_abilities_uninstall_delete_data`, `acrossai_abilities_per_page`.
- [ ] `includes/Main.php:264` `admin_menu` Loader line for SettingsMenu removed; line 265 `admin_init` registration kept.
- [ ] AcrossAI → Settings shows Display, Log, and Uninstall sections; saving each field round-trips through `wp option get`.
- [ ] Visiting `wp-admin/admin.php?page=acrossai-abilities-settings` no longer renders the old page.

### TASK-5 — Hook_suffix updates
- [ ] `admin/Main.php:343` matches `'acrossai_page_acrossai-abilities-manager'`.
- [ ] `admin/Main.php:354` matches `'acrossai_page_acrossai-settings'`.
- [ ] Abilities page enqueues its scripts/styles (verified in browser DevTools Network tab).
- [ ] Settings page enqueues its scripts/styles (verified in browser DevTools Network tab).
- [ ] Plugins screen action links "Settings" and "Logs" still resolve to working pages.

### TASK-6 — Boot resilience when vendor autoloader is missing
- [ ] `includes/Main.php` has a `$vendor_missing` flag set by `load_composer_dependencies()` when `vendor/autoload_packages.php` is absent.
- [ ] `Main::__construct()` returns early (no `load_dependencies()`, no `load_hooks()`) when `$vendor_missing` is true.
- [ ] When the `vendor/` directory is renamed/deleted, the plugin does NOT fatal on load — admin pages render normally and an error-class notice on every admin screen tells the user to run `composer install` (text domain `acrossai-abilities-manager`, value passes through `esc_html__`).
- [ ] `register_activation_hook` callback in `acrossai-abilities-manager.php` blocks activation with `wp_die()` when `vendor/autoload_packages.php` is absent.
- [ ] After restoring `vendor/` (re-running `composer install`), the next admin request boots the plugin normally with no notice and no fatal — exactly reproduces the original reproducer trace passing.
- [ ] PHPStan level 8 — zero new errors introduced by the resilience guard.

### Quality gates
- [ ] PHPStan level 8 — zero errors.
- [ ] PHPCS — zero errors.
- [ ] `npm run lint:js` — zero errors (no JS changes expected, but confirm).
- [ ] `npm run validate-packages` — passes.
- [ ] Existing option values from a pre-upgrade install still surface on the new Settings page (data preservation check).

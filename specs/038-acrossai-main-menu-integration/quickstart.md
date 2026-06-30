# Quickstart: Verifying the AcrossAI Main Menu Integration

**Plan**: [plan.md](./plan.md)

Use this file after each TASK in `docs/planning/038-acrossai-main-menu-integration.md` to verify the change before moving on. Every step is executable on a local WordPress dev install with WP-CLI available. Per the planning doc constraint: **do not batch — verify after every TASK, not at the end.**

---

## Q-001 — Composer dependency installed (TASK-1)

```bash
# From the plugin root.
composer update acrossai-co/main-menu
test -d vendor/acrossai-co/main-menu && echo "OK: vendor/acrossai-co/main-menu present"
grep -q '"acrossai-co/main-menu"' composer.json && echo "OK: composer.json updated"
grep -q '"name": "acrossai-co/main-menu"' composer.lock && echo "OK: composer.lock updated"

# Confirm Jetpack Autoloader picked up the package.
grep -l "AcrossAI_Main_Menu" vendor/composer/jetpack_autoload_classmap.php
```

**Pass when**: All four lines print `OK`; jetpack classmap includes `AcrossAI_Main_Menu\SettingsPage`.

---

## Q-002 — Bootstrap registered and visible (TASK-1)

```bash
# Confirm the bootstrap is in the right file and on the right hook.
grep -n "plugins_loaded" acrossai-abilities-manager.php
grep -n "AcrossAI_Main_Menu" acrossai-abilities-manager.php

# Sanity: the bootstrap is NOT inside includes/Main.php.
! grep -rn "AcrossAI_Main_Menu" includes/ admin/ && echo "OK: bootstrap only in entry file"
```

**Then**: load `/wp-admin/` in the browser. Confirm a top-level "AcrossAI" menu appears in the left sidebar.

**Pass when**: Bootstrap is in `acrossai-abilities-manager.php`, on `plugins_loaded` priority `0`, guarded by `class_exists()`. Sidebar shows the AcrossAI parent.

---

## Q-003 — Abilities is now a submenu under AcrossAI (TASK-2)

In the WP admin sidebar:

- Confirm "Abilities Manager" no longer appears as a top-level entry.
- Confirm the AcrossAI parent expands to show "Abilities" as a submenu.
- Click "Abilities" — confirm the React app loads (page contents unchanged from prior version).
- Confirm `Menu::main_menu` has been renamed to `Menu::register_submenu` in `admin/Partials/Menu.php` and that `includes/Main.php` line 251 references the new method name.

```bash
grep -n "register_submenu" admin/Partials/Menu.php
grep -n "'register_submenu'" includes/Main.php
```

**Pass when**: Both greps return matches; sidebar reflects the new hierarchy; Abilities page renders.

---

## Q-004 — Library, Logs, Add-ons reparented (TASK-3)

```bash
grep -n "'acrossai'" admin/Partials/LibraryMenu.php   # parent_slug arg
grep -n "'acrossai'" admin/Partials/LogsMenu.php       # parent_slug arg
grep -n "'acrossai'" includes/Main.php                  # AddonsPage ctor first arg
```

In the sidebar under AcrossAI confirm the order: **Settings → Abilities → Library → Logs → Add-ons**.

Click each submenu and confirm the page renders without errors.

**Pass when**: Greps confirm the slug change; sidebar order matches; every submenu renders.

---

## Q-005 — Hook-suffix strings verified (TASK-5) — MANDATORY

Temporarily add the following inside `admin/Main.php::enqueue_scripts()` (or `enqueue_styles()`) at the top:

```php
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    error_log( '[ACROSSAI-038] hook_suffix=' . $hook_suffix );
}
```

Load each of the three pages: AcrossAI → Settings, AcrossAI → Abilities, AcrossAI → Library (sanity check).

`tail -n 40 wp-content/debug.log`:

- Settings page MUST log `hook_suffix=acrossai_page_acrossai-settings`
- Abilities page MUST log `hook_suffix=acrossai_page_acrossai-abilities-manager`

Compare those strings against `admin/Main.php` lines 343 and 354 — they MUST be identical.

```bash
grep -n "acrossai_page_acrossai-abilities-manager" admin/Main.php   # line 343
grep -n "acrossai_page_acrossai-settings" admin/Main.php             # line 354
```

**Then remove the temporary `error_log()` and re-verify PHPCS / PHPStan / Plugin Check are still clean.**

**Pass when**: Logged suffixes match hardcoded strings exactly; debug log line removed; quality gates green.

---

## Q-006 — Settings preserved across the host page change (TASK-4)

Before applying TASK-4, on a fresh install set non-default values:

```bash
wp option update acrossai_abilities_per_page 50
wp option update acrossai_abilities_log_retention_days 90
wp option update acrossai_abilities_uninstall_delete_data 1
```

Apply TASK-4 then re-read:

```bash
wp option get acrossai_abilities_per_page                # MUST print 50
wp option get acrossai_abilities_log_retention_days      # MUST print 90
wp option get acrossai_abilities_uninstall_delete_data   # MUST print 1
```

Navigate to AcrossAI → Settings (now host-rendered). Confirm:

- All three fields display the values above (no blanks, no defaults).
- The section IDs and titles match pre-upgrade.
- Changing any field and clicking Save → page reloads with the new value → `wp option get …` reflects the new value.

**Pass when**: All three `wp option get` reads return the pre-upgrade values; UI matches; save round-trips.

---

## Q-007 — Legacy Settings URL is unregistered (TASK-4 + Q-006)

```bash
# Should NOT load the old standalone settings page.
curl -s -o /dev/null -w '%{http_code}\n' "$(wp option get siteurl)/wp-admin/admin.php?page=acrossai-abilities-settings"
```

**Pass when**: WordPress returns the standard "page does not exist" response (typically still HTTP 200 with a "Cheatin', uh?" body, depending on WP version). No PHP fatal in `debug.log`. The plugin did NOT register a fallback.

---

## Q-008 — Plugin survives missing autoloader (TASK-6) — THE REGRESSION TEST

Reproduce the 30-Jun-2026 fatal, then confirm the fix:

```bash
# 1. Snapshot vendor away.
mv vendor /tmp/acrossai-vendor-snapshot

# 2. Deactivate then attempt to reactivate from the Plugins screen.
#    Expected: activation blocked with the friendly error message.
#    Browser: WP shows the wp_die screen, NOT a WSOD, NOT the old fatal.
wp plugin deactivate acrossai-abilities-manager
wp plugin activate acrossai-abilities-manager   # MUST fail loudly, no fatal in php-error log
```

**Pass when**: `wp plugin activate` exits non-zero with the friendly message; `tail -n 100 wp-content/debug.log` contains NO `AcrossAI_Loader` class-not-found fatal.

```bash
# 3. Confirm degraded mode on an already-active plugin.
#    Re-introduce activation tracking by reactivating with vendor restored briefly
#    then removing vendor again to simulate a partial deploy.
mv /tmp/acrossai-vendor-snapshot vendor
wp plugin activate acrossai-abilities-manager
mv vendor /tmp/acrossai-vendor-snapshot

#    Load any wp-admin page. Expected:
#    - Page renders normally (no WSOD).
#    - An error-class notice at the top reads: "AcrossAI Abilities Manager: The composer
#      autoloader is missing. Run 'composer install' ..."
#    - The notice ONLY appears for users with manage_options.
#    - debug.log contains NO fatal from AcrossAI_Loader.

# 4. Restore and confirm normal boot resumes.
mv /tmp/acrossai-vendor-snapshot vendor
```

**Pass when**: After restoring vendor, the notice disappears on next admin load; the AcrossAI top-level menu re-appears; all submenus open; no fatals at any point.

---

## Q-009 — Quality gates (per TASK, not batched)

After each TASK:

```bash
composer run phpcs
composer run phpstan
npm run validate-packages

# Plugin Check (production surface only — see CONSTITUTION §II).
# Run via wp-env per PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT.
```

**Pass when**: All four exit `0`; no new errors introduced compared to the pre-TASK baseline.

---

## Q-010 — Final sidebar walkthrough (after all TASKS)

Open `/wp-admin/`. Confirm:

- One "AcrossAI" top-level entry.
- Five submenus in order: Settings, Abilities, Library, Logs, Add-ons.
- Each submenu loads its page; React/styles render where expected.
- "Abilities Manager" does NOT appear as a top-level entry.
- Plugins → Installed Plugins → action links "Settings" and "Logs" for this plugin still resolve.

**Pass when**: All five items confirmed. Feature 038 done; ready for `/speckit-tasks` validation closure and commit.

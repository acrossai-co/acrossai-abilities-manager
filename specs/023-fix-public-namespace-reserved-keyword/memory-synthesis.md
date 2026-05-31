# Memory Synthesis

## Current Scope

Feature 023 bundles a full rebrand from WPBoilerplate → AcrossWP (10 PHP files + composer.json + README.txt), an uninstall-gate behavior fix, a Logger query variable-naming cleanup (spread operator), deletion of the `plugin-check.yml` workflow, and a permanent namespace rename: `AcrossAI_Abilities_Manager\Public` → `AcrossAI_Abilities_Manager\Front`.

## Why This Exists

The user made several manual changes directly on `main` (rebrand, uninstall fix, logger refactor, plugin-check.yml deletion) that were never captured in a spec. This feature also fixes the root cause of the `--ignore=public/Main.php` workaround added in 022: `public` is a PHP reserved keyword since PHP 5.0 and cannot be used as a namespace segment.

## Already Done (uncommitted on main)

All of the following changes are in the working tree but NOT yet committed:

### Rebrand: WPBoilerplate → AcrossWP
| File | Change |
|---|---|
| `acrossai-abilities-manager.php` | `@link`, Plugin URI, Description, Author, Author URI |
| `admin/Main.php` | `@link`, `@author` |
| `includes/Main.php` | `@link`, `@author`, `define()` param names (`$acrossai_name`, `$acrossai_value`) |
| `includes/AcrossAI_Activator.php` | `@author` |
| `includes/AcrossAI_Deactivator.php` | `@link`, `@author` |
| `includes/AcrossAI_Loader.php` | `@link`, `@author` |
| `public/Main.php` | `@link`, `@author` |
| `public/Partials/display.php` | `@link` |
| `README.txt` | Donate link |
| `composer.json` | `support.issues` URL |

### Uninstall gate fix
`uninstall.php`: `delete_option( 'acrossai_abilities_log_retention_days' )` and `delete_option( 'acrossai_abilities_uninstall_delete_data' )` moved inside the `$acrossai_delete_data` gate — options are now preserved by default on uninstall.

### Logger query cleanup
`includes/Modules/Logger/AcrossAI_Logger_Query.php`:
- `$count_values` → `$count_params`
- `$final_values` → `$select_params`
- `$wpdb->prepare($sql, $array)` → `$wpdb->prepare($sql, ...$params)` (spread operator)

### Workflow deletion
`.github/workflows/plugin-check.yml` — deleted entirely.

## Still Pending (namespace fix — the actual 023 change)

- **Affected files (exact)**:
  - `public/Main.php:12` — `namespace AcrossAI_Abilities_Manager\Public;` → `namespace AcrossAI_Abilities_Manager\Front;`
  - `includes/Main.php:297` — `new \AcrossAI_Abilities_Manager\Public\Main(` → `new \AcrossAI_Abilities_Manager\Front\Main(`
  - `composer.json:31` — autoload PSR-4 key rename
  - `.github/workflows/phpcompat.yml` — remove `--ignore=public/Main.php`

- **Chosen rename**: `Front` — avoids all PHP reserved keywords, follows common WordPress plugin conventions.

- **Autoload**: After editing `composer.json`, `composer dump-autoload` must be run. Do NOT manually edit vendor files.

## What Does NOT Change

- No REST endpoints, DB schema, admin menus, or hooks
- No other namespace changes
- `public/Partials/display.php` is not namespaced — no change needed

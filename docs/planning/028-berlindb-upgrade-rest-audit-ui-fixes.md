# Planning: BerlinDB Upgrade, PHP 8.1 Minimum, REST Permission Audit, Abilities Table UI Fixes (Feature 028)

Five independent maintenance tasks shipped in a single branch:

1. **BerlinDB `^3.0.0` upgrade** — bump `berlindb/core` and the coordinating `wpboilerplate/wpb-access-control` package so the whole dependency tree resolves against v3.
2. **PHP minimum version bump to 8.1** — update every place that declares or documents `7.4` as the minimum: `composer.json`, plugin header, `README.txt`, CI workflows, CONSTITUTION.md, agent skill files.
3. **REST `permission_callback` compliance audit** — verify every `register_rest_route()` and `wp_register_ability()` call carries a non-trivial `permission_callback`; fix any gap.
4. **Remove the `X of Y items` label** from the abilities table top-nav bar.
5. **Right-align the bottom pagination** of the abilities table.

Tasks 4 and 5 are pure React/SCSS changes in `src/js/abilities/components/AbilitiesList.jsx` and the matching stylesheet. Tasks 1–3 are PHP/Composer/config changes. All five can be parallelised during implementation.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit-git-feature "028-berlindb-upgrade-rest-audit-ui-fixes"

# 2. Specify → Plan → Tasks → Implement
/speckit-architecture-guard-governed-implement
```

---

## Background — what is already done; do NOT redo it

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | `berlindb/core 2.0.2` is in `composer.lock`; `composer.json` currently requires `^2.0` | `grep berlindb composer.json composer.lock` |
| B-2 | `wpboilerplate/wpb-access-control v1.1.1` is in `composer.lock`; its own `composer.json` requires `berlindb/core ^2.0` | `cat vendor/wpboilerplate/wpb-access-control/composer.json` |
| B-3 | `"php": ">=7.4"` is in `composer.json`; `Requires PHP: 7.4` is in both `acrossai-abilities-manager.php` and `README.txt` | `grep -n "7.4\|Requires PHP" composer.json acrossai-abilities-manager.php README.txt` |
| B-4 | `phpcompat.yml` CI job is named "PHP 7.4+ Compatibility" and passes `--runtime-set testVersion 7.4-` to PHPCompatibility; no PHPUnit matrix workflow exists yet | read `.github/workflows/phpcompat.yml` |
| B-5 | CONSTITUTION.md §VIII states `compatible with WordPress 6.9+ and PHP 7.4+` | read `.specify/memory/CONSTITUTION.md:117` |
| B-6 | `.agents/skills/wp-packages-strategy/SKILL.md` compatibility line says `PHP 7.4+` | read `.agents/skills/wp-packages-strategy/SKILL.md:4` |
| B-7 | `phpunit/phpunit ^13.2@dev` in `require-dev` already requires PHP 8.2+; this is a second reason to raise the floor | `grep phpunit composer.json` |
| B-8 | All existing REST routes (`Abilities`, `Logger`, `Library`) already use `check_permission()` delegates that gate on `manage_options` + nonce | `grep -rn permission_callback includes/Modules/` |
| B-9 | The abilities table is custom HTML (not `@wordpress/dataviews`) in `src/js/abilities/components/AbilitiesList.jsx` | read `AbilitiesList.jsx` |
| B-10 | The `X of Y items` label lives at `AbilitiesList.jsx:578–582` inside a `.tn-pages` div in the top tablenav | read `AbilitiesList.jsx:578` |
| B-11 | The bottom pagination is at `AbilitiesList.jsx:943–981` inside `<div className="tablenav-pages tablenav-pages-below">` | read `AbilitiesList.jsx:943` |
| B-12 | The abilities SCSS lives at `src/scss/` (check for an `abilities` or `admin` stylesheet there) | `find src/scss -name "*.scss"` |
| B-13 | Constitution v1.4.4 requires `defined( 'ABSPATH' ) || exit;` in every PHP file and all hooks wired via Loader in `includes/Main.php` | read `.specify/memory/CONSTITUTION.md` |

---

## Task 1 — BerlinDB `^3.0.0` Upgrade

### Problem

`berlindb/core 2.0.2` is installed. The main plugin's `composer.json` requires `^2.0` because `wpboilerplate/wpb-access-control v1.1.1` declared `"berlindb/core": "^2.0"`. `wpb-access-control v1.2.0` has been released with BerlinDB 3.0 support — update to it directly. No backward compatibility with BerlinDB 2.x is needed.

### BerlinDB 3.0 breaking changes (confirmed from source diff)

| Breaking change | v2 | v3 |
|---|---|---|
| **Namespace move** | `BerlinDB\Database\{Table,Query,Row,Schema}` | `BerlinDB\Database\Kern\{Table,Query,Row,Schema}` |
| **`set_schema()` is now private** | Subclass overrides `protected set_schema()` to assign a SQL string to `$this->schema` | `set_schema()` cannot be overridden; set `protected $schema = MySchema::class` and let Schema generate the SQL |

All property names (`$name`, `$version`, `$upgrades`, `$db_version_key`, `$table_name`, `$table_alias`, `$table_schema`, `$item_name`, `$item_shape`, `$cache_group`, `$columns`) and method signatures (`add_item`, `delete_item`, `query`) are **unchanged**.

### Steps

1. Add VCS repository entry for `wpb-access-control` in `composer.json` (not yet on Packagist):
   ```json
   { "type": "vcs", "url": "https://github.com/WPBoilerplate/wpb-access-control" }
   ```
2. Run:
   ```bash
   composer require \
     wpboilerplate/wpb-access-control:^1.2.0 \
     berlindb/core:^3.0.0 \
     --update-with-dependencies
   ```
3. Run PHPStan L8 clean pass.
4. Run PHPCS clean pass.

### Acceptance criteria

- `composer install` exits 0 with `berlindb/core 3.0.0` and `wpb-access-control v1.2.0` in the lock file.
- PHPStan L8 clean.
- PHPCS clean.

---

## Task 2 — PHP Minimum Version Bump to 8.1

### Problem

The plugin declares `Requires PHP: 7.4` but already uses `phpunit/phpunit ^13.2` in dev (which requires PHP 8.2+), and PHP 7.4 reached end-of-life in November 2022. PHP 8.1 is the lowest still-supported version with active security fixes. Raising the floor lets us use native PHP 8.1 features (enums, readonly properties, intersection types, first-class callable syntax) and removes maintenance burden from 7.4/8.0 compatibility shims.

### Complete list of files to update

| File | Current value | New value |
|------|---------------|-----------|
| `composer.json` | `"php": ">=7.4"` | `"php": ">=8.1"` |
| `acrossai-abilities-manager.php` | `* Requires PHP:      7.4` | `* Requires PHP:      8.1` |
| `README.txt` | `Requires PHP: 7.4` | `Requires PHP: 8.1` |
| `.github/workflows/phpcompat.yml` | job name `PHP 7.4+ Compatibility`; `testVersion 7.4-` | `PHP 8.1+ Compatibility`; `testVersion 8.1-` |
| `.github/workflows/phpunit.yml` | does not exist | new PHPUnit matrix workflow testing PHP 8.1, 8.2, 8.3, 8.4, 8.5 |
| `.specify/memory/CONSTITUTION.md` | `compatible with WordPress 6.9+ and PHP 7.4+` | `PHP 8.1+` |
| `.agents/skills/wp-packages-strategy/SKILL.md` | `PHP 7.4+` | `PHP 8.1+` |

### Steps

1. Edit each file in the table above.
2. Run `composer validate` — ensures `composer.json` is well-formed after the edit.
3. Run `composer install --prefer-dist --no-progress` — verifies all declared dependencies can resolve under `>=8.1`.
4. Run PHPCompatibility scan locally to confirm no 8.1-incompatible syntax remains:
   ```bash
   vendor/bin/phpcs \
     --standard=PHPCompatibility \
     --extensions=php \
     --runtime-set testVersion 8.1- \
     acrossai-abilities-manager.php uninstall.php includes/ admin/ public/
   ```
5. Run PHPStan L8 clean pass.
6. Run the full PHPUnit test suite to confirm no test now fails due to the bumped version constraint:
   ```bash
   vendor/bin/phpunit
   ```
7. Push to CI — the new `.github/workflows/phpunit.yml` matrix (PHP 8.1, 8.2, 8.3, 8.4, 8.5) must turn green for all five jobs.

### Acceptance criteria

- `Requires PHP: 8.1` in both plugin header and `README.txt`.
- `"php": ">=8.1"` in `composer.json`.
- `testVersion 8.1-` in `phpcompat.yml`; job name updated to "PHP 8.1+ Compatibility".
- `.github/workflows/phpunit.yml` exists with `matrix.php: ['8.1', '8.2', '8.3', '8.4', '8.5']`; all five jobs pass.
- CONSTITUTION.md and agent skill file reflect 8.1+.
- PHPCompatibility scan exits 0.
- PHPStan L8 exits 0.
- PHPUnit test suite passes on all five PHP versions.

---

## Task 3 — REST `permission_callback` Compliance Audit

### Problem

The WordPress Plugin Check tool (and `wp.org` review) flag any `register_rest_route()` call that omits `permission_callback` or sets it to `'__return_true'`. The existing routes all use proper callbacks, but this must be verified against every route in the plugin, including any added in Feature 027, and documented so future routes are never shipped without one.

### What to check

| File | Routes | Expected callback |
|------|--------|-------------------|
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` | POST, PATCH, DELETE `/abilities` | `AcrossAI_Abilities_Rest_Controller::check_permission()` |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php` | GET `/abilities`, GET `/abilities/<slug>` | `AcrossAI_Abilities_Rest_Controller::check_permission()` |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Category_Controller.php` | GET `/categories` | same |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php` | GET `/exposure` | same |
| `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` | GET `/logger/logs` | `AcrossAI_Logger_Controller::check_permission()` |
| `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` | GET + POST `/abilities/config` | `AcrossAI_Ability_Library_Rest_Controller::check_permission()` |

### Pattern every route MUST follow

```php
register_rest_route( 'acrossai-abilities-manager/v1', '/my-endpoint', array(
    'methods'             => 'GET',
    'callback'            => array( $this, 'handle' ),
    'permission_callback' => array( AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission' ),
) );
```

The `check_permission()` implementation must:
- Gate on `current_user_can( 'manage_options' )`.
- Verify `wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' )`.
- Return a `WP_Error` with `status: 403` on failure; return `true` on success.

### Steps

1. `grep -rn "register_rest_route\|permission_callback\|__return_true" includes/` — confirm every `register_rest_route()` call has a `permission_callback` key and none use `'__return_true'` or a bare closure that always returns `true`.
2. For any route missing the callback, add the correct delegate reference.
3. Run Plugin Check locally if Docker is available; otherwise, rely on CI gate.

### Acceptance criteria

- Zero routes with missing or trivially-open `permission_callback`.
- Plugin Check passes with no `permission_callback` warnings.

---

## Task 4 — Remove `X of Y items` label from abilities table

### Problem

The top tablenav of the abilities table shows `${abilities.length} of ${total} items` (e.g. "10 of 91 items"). This text is redundant — the pagination already conveys position — and clutters the UI.

### Location

`src/js/abilities/components/AbilitiesList.jsx:578–582`

```jsx
<div className="tn-pages">
    {isLoading
        ? __('Loading…', 'acrossai-abilities-manager')
        : `${abilities.length} ${__('of', 'acrossai-abilities-manager')} ${total} ${__('items', 'acrossai-abilities-manager')}`}
</div>
```

### Fix

Remove the entire `<div className="tn-pages">…</div>` block (lines 578–582). Do not replace it with anything.

### Acceptance criteria

- No `X of Y items` text visible in the abilities table top tablenav.
- No JS console errors.
- Pagination still works correctly.

---

## Task 5 — Right-align bottom pagination

### Problem

The bottom pagination row (`tablenav-pages-below`) is left-aligned by default. It should be right-aligned to match the WordPress admin table conventions.

### Location

`src/js/abilities/components/AbilitiesList.jsx:943–981` renders:
```jsx
<div className="tablenav-pages tablenav-pages-below">
```

The matching SCSS is in `src/scss/` (find the abilities admin stylesheet).

### Fix

Add a CSS rule targeting `.tablenav-pages-below`:
```scss
.tablenav-pages-below {
    text-align: right;
}
```

Add it to the abilities admin stylesheet in `src/scss/`.

### Acceptance criteria

- Bottom pagination is right-aligned.
- Top pagination (if any) is unaffected.
- No visual regressions in the abilities table.

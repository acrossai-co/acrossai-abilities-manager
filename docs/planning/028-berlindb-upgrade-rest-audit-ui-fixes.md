# Planning: BerlinDB Upgrade, PHP 8.1 Minimum, REST Permission Audit, Abilities Table UI Fixes (Feature 028)

Five independent maintenance tasks shipped in a single branch:

1. **BerlinDB `^3.0.0` upgrade** — bump `berlindb/core` and `wpboilerplate/wpb-access-control` to their v3-compatible releases so the full dependency tree resolves against BerlinDB 3.
2. **PHP minimum version bump to 8.1** — update every place that declares `7.4` as the minimum: `composer.json`, plugin header, `README.txt`, `AGENTS.md`, CI workflows, CONSTITUTION.md, agent skill file. Add a PHPUnit matrix CI job covering PHP 8.1–8.5.
3. **REST `permission_callback` compliance audit** — verify every `register_rest_route()` and `wp_register_ability()` call carries a non-trivial `permission_callback`; fix any gap.
4. **Remove the `X of Y items` label** from the abilities table top-nav bar.
5. **Right-align the bottom pagination** of the abilities table.

Tasks 4 and 5 are pure React/SCSS changes in `src/js/abilities/components/AbilitiesList.jsx` and `src/scss/abilities/admin.scss`. Tasks 1–3 are PHP/Composer/config changes. All five can be parallelised during implementation.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "028-berlindb-upgrade-rest-audit-ui-fixes"

# 2. Specify → Plan → Tasks → Implement
/speckit-architecture-guard-governed-implement
```

---

## Background — Current State (verified on main as of 2026-06-09)

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | `composer.json` requires `"berlindb/core": "^2.0"` and `"wpboilerplate/wpb-access-control": "^1.1.1"` | `grep berlindb composer.json` |
| B-2 | `wpb-access-control v1.2.0` is tagged on GitHub with full BerlinDB 3.0 support (`BerlinDB\Database\Kern\*` namespace, `protected $schema = RuleSchema::class`); the VCS repository entry is needed since the package is not on Packagist | `composer show wpboilerplate/wpb-access-control` after adding VCS entry |
| B-3 | `"php": ">=7.4"` is in `composer.json`; `* Requires PHP: 7.4` is in `acrossai-abilities-manager.php:27`; `Requires PHP: 7.4` is in `README.txt:7` | `grep -n "7\.4\|Requires PHP" composer.json acrossai-abilities-manager.php README.txt` |
| B-4 | `AGENTS.md:12` declares `php_min_version: "7.4"` | `grep php_min_version AGENTS.md` |
| B-5 | `phpcompat.yml` job name is "PHP 7.4+ Compatibility"; passes `--runtime-set testVersion 7.4-` | read `.github/workflows/phpcompat.yml` |
| B-6 | No `phpunit.yml` workflow exists; PHPUnit is not run in CI at all | `ls .github/workflows/` |
| B-7 | `CONSTITUTION.md:117` states `compatible with WordPress 6.9+ and PHP 7.4+` | `grep -n "PHP 7" .specify/memory/CONSTITUTION.md` |
| B-8 | `.agents/skills/wp-packages-strategy/SKILL.md:4` declares `compatibility: "WordPress 6.0+, PHP 7.4+, ..."` | `grep PHP .agents/skills/wp-packages-strategy/SKILL.md` |
| B-9 | `phpunit/phpunit ^13.2@dev` in `require-dev` already requires PHP 8.2+; this is a second reason to raise the floor | `grep phpunit composer.json` |
| B-10 | All REST routes already have `permission_callback` delegates; Abilities routes use `check_permission()` via `$permission` variable set in the constructor; Logger route uses `AcrossAI_Logger_Controller::check_permission()`; Library config routes use `AcrossAI_Ability_Library_Rest_Controller::check_permission()` | `grep -rn permission_callback includes/Modules/` |
| B-11 | The abilities table is custom HTML (not `@wordpress/dataviews`) in `src/js/abilities/components/AbilitiesList.jsx` | read `AbilitiesList.jsx` |
| B-12 | The `X of Y items` label is at `AbilitiesList.jsx:578–582` inside `<div className="tn-pages">` in the top tablenav | read `AbilitiesList.jsx:578` |
| B-13 | The bottom pagination div is `<div className="tablenav-pages tablenav-pages-below">` at `AbilitiesList.jsx:943` | read `AbilitiesList.jsx:943` |
| B-14 | `.tablenav-pages-below` already has a `margin-top: 8px` rule in `src/scss/abilities/admin.scss:308` but no `text-align` rule | read `src/scss/abilities/admin.scss:308` |

---

## CHANGE-1 — BerlinDB `^3.0.0` Upgrade

**Files**:
- `composer.json`
- `composer.lock` (regenerated)

### BerlinDB 3.0 breaking changes (confirmed from source)

| Breaking change | v2 | v3 |
|---|---|---|
| **Namespace move** | `BerlinDB\Database\{Table,Query,Row,Schema}` | `BerlinDB\Database\Kern\{Table,Query,Row,Schema}` |
| **`set_schema()` is now private** | Subclass overrides `protected set_schema()` to assign a SQL string to `$this->schema` | Cannot be overridden; set `protected $schema = MySchema::class` instead |

All property names and method signatures are unchanged. `wpb-access-control v1.2.0` already contains the full BerlinDB 3.0 migration in all four of its Database files — no changes to the vendor package source are needed.

### `composer.json` — add VCS repository and bump constraints

Current shape:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/WPBoilerplate/wpb-addons-page"
    }
],
"require": {
    "wpboilerplate/wpb-access-control": "^1.1.1",
    "berlindb/core": "^2.0",
```

Target shape:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/WPBoilerplate/wpb-addons-page"
    },
    {
        "type": "vcs",
        "url": "https://github.com/WPBoilerplate/wpb-access-control"
    }
],
"require": {
    "wpboilerplate/wpb-access-control": "^1.2.0",
    "berlindb/core": "^3.0.0",
```

Then run:

```bash
composer update wpboilerplate/wpb-access-control berlindb/core --with-all-dependencies
```

Rules:
- Keep `"minimum-stability": "dev"` — other packages still require it.
- Do not remove the VCS entry for `wpb-addons-page`.
- Do not change any other constraint.

---

## CHANGE-2 — PHP Minimum Version Bump to 8.1

**Files**: `composer.json`, `acrossai-abilities-manager.php`, `README.txt`, `AGENTS.md`, `.github/workflows/phpcompat.yml`, `.github/workflows/phpunit.yml` (new), `.specify/memory/CONSTITUTION.md`, `.agents/skills/wp-packages-strategy/SKILL.md`

### Complete list of edits

| File | Current value | New value |
|------|---------------|-----------|
| `composer.json` | `"php": ">=7.4"` | `"php": ">=8.1"` |
| `acrossai-abilities-manager.php:27` | `* Requires PHP:      7.4` | `* Requires PHP:      8.1` |
| `README.txt:7` | `Requires PHP: 7.4` | `Requires PHP: 8.1` |
| `AGENTS.md:12` | `php_min_version: "7.4"` | `php_min_version: "8.1"` |
| `phpcompat.yml` job name | `PHP 7.4+ Compatibility` | `PHP 8.1+ Compatibility` |
| `phpcompat.yml` testVersion | `--runtime-set testVersion 7.4-` | `--runtime-set testVersion 8.1-` |
| `phpunit.yml` | does not exist | new matrix workflow (see below) |
| `CONSTITUTION.md:117` | `compatible with WordPress 6.9+ and PHP 7.4+` | `PHP 8.1+` |
| `.agents/skills/wp-packages-strategy/SKILL.md:4` | `PHP 7.4+` | `PHP 8.1+` |

### New file: `.github/workflows/phpunit.yml`

```yaml
name: PHPUnit

on:
  push:
    branches: [main]
  pull_request:
    paths:
      - '**.php'
      - 'composer.json'
      - 'composer.lock'
      - 'phpunit.xml.dist'

permissions: {}

jobs:
  phpunit:
    name: PHPUnit (PHP ${{ matrix.php }})
    runs-on: ubuntu-latest
    timeout-minutes: 15
    permissions:
      contents: read

    strategy:
      fail-fast: false
      matrix:
        php: ['8.1', '8.2', '8.3', '8.4', '8.5']

    steps:
      - uses: actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5 # v4

      - uses: shivammathur/setup-php@7c071dfe9dc99bdf297fa79cb49ea005b9fcadbc # v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      - run: composer install --prefer-dist --no-progress

      - name: Run PHPUnit
        run: vendor/bin/phpunit
```

Rules:
- `fail-fast: false` — all five jobs run even if one fails, so per-version failures are visible independently.
- `coverage: none` — keeps CI fast; coverage is not a gate here.
- Use the same pinned action SHAs already used in `phpstan.yml` and `phpcompat.yml`.

---

## CHANGE-3 — REST `permission_callback` Compliance Audit

**Scope**: `includes/Modules/`

All existing REST routes already carry `permission_callback`. This change is an audit + documentation step, not a code fix, unless a gap is found.

### Routes to verify

| File | Routes | Expected callback |
|------|--------|-------------------|
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` | POST, PATCH, DELETE, PUT `/abilities` | `$permission` set from `AcrossAI_Abilities_Rest_Controller::check_permission()` in constructor |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php` | GET `/abilities`, GET `/abilities/<slug>` | same |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Category_Controller.php` | GET `/categories` | `AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission'` |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php` | GET `/exposure` | same |
| `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` | GET `/logger/logs` | `AcrossAI_Logger_Controller::instance(), 'check_permission'` |
| `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` | GET + POST `/abilities/config` | `AcrossAI_Ability_Library_Rest_Controller::instance(), 'check_permission'` |

### Audit command

```bash
grep -rn "register_rest_route\|permission_callback\|__return_true" includes/
```

Expected: every `register_rest_route()` call has a `permission_callback` key; none use `'__return_true'` or a bare closure that always returns `true`.

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

If the audit finds all routes compliant, record that in the PR description. If a gap is found, add the correct delegate reference before merging.

---

## CHANGE-4 — Remove `X of Y items` Label

**File**: `src/js/abilities/components/AbilitiesList.jsx`

The top tablenav shows a redundant item-count string alongside the pagination controls.

### Current code at lines 578–582

```jsx
<div className="tn-pages">
    {isLoading
        ? __('Loading…', 'acrossai-abilities-manager')
        : `${abilities.length} ${__('of', 'acrossai-abilities-manager')} ${total} ${__('items', 'acrossai-abilities-manager')}`}
</div>
```

### Fix

Remove the entire `<div className="tn-pages">…</div>` block. Do not replace it with anything.

Rules:
- Do not remove or alter the `.tablenav-pages` div directly below it (lines 584+); that div contains the page count and pagination links and must stay.
- Do not change the bottom pagination `<div className="tablenav-pages tablenav-pages-below">` at line 943.
- Do not touch `.tn-pages` CSS rules in `src/scss/abilities/admin.scss`; removing dead CSS is a separate concern.

---

## CHANGE-5 — Right-align Bottom Pagination

**File**: `src/scss/abilities/admin.scss`

The bottom pagination row is currently left-aligned. WordPress admin table conventions place bottom pagination on the right.

### Current rule at line 308

```scss
.tablenav-pages-below {
    margin-top: 8px;
}
```

### Target rule

```scss
.tablenav-pages-below {
    margin-top:  8px;
    text-align:  right;
}
```

Rules:
- Only add `text-align: right`; do not change the existing `margin-top` value.
- Do not add `text-align` to `.tablenav-pages` (the top pagination block); only the `-below` variant needs it.
- Do not modify any other rule in the file.

---

## What Must NOT Change

- Do not change REST endpoint paths, namespaces, or response shapes.
- Do not change database schemas or table names.
- Do not change any test file to satisfy CI.
- Do not remove the `"minimum-stability": "dev"` key from `composer.json`.
- Do not remove the VCS repository entry for `wpb-addons-page`.
- Do not touch the top pagination `.tablenav-pages` block when fixing the bottom one.
- Do not change `phpunit.xml.dist` configuration.

---

## Expected Files Changed

```text
composer.json
composer.lock
acrossai-abilities-manager.php
README.txt
AGENTS.md
.github/workflows/phpcompat.yml
.github/workflows/phpunit.yml             (new)
.specify/memory/CONSTITUTION.md
.agents/skills/wp-packages-strategy/SKILL.md
src/js/abilities/components/AbilitiesList.jsx
src/scss/abilities/admin.scss
```

If the REST audit finds a gap, add the relevant REST controller file(s) to the list.

---

## Validation Checklist

### BerlinDB upgrade

- [ ] `grep berlindb composer.lock` shows `berlindb/core 3.0.0`.
- [ ] `grep wpb-access-control composer.lock` shows `v1.2.0`.
- [ ] `composer install` exits 0 with no conflict errors.

### PHP version bump

- [ ] `grep "Requires PHP" acrossai-abilities-manager.php README.txt` returns `8.1` in both.
- [ ] `grep '"php"' composer.json` returns `">=8.1"`.
- [ ] `grep php_min_version AGENTS.md` returns `"8.1"`.
- [ ] `phpcompat.yml` job name is "PHP 8.1+ Compatibility"; `testVersion 8.1-`.
- [ ] `.github/workflows/phpunit.yml` exists with matrix `['8.1', '8.2', '8.3', '8.4', '8.5']`.
- [ ] `CONSTITUTION.md:117` reads `PHP 8.1+`.
- [ ] `.agents/skills/wp-packages-strategy/SKILL.md:4` reads `PHP 8.1+`.

### REST audit

- [ ] `grep -rn "__return_true" includes/` returns nothing.
- [ ] Every `register_rest_route()` call found by `grep -rn register_rest_route includes/` has a `permission_callback` key in its args array.

### UI fixes

- [ ] `grep -n "tn-pages" src/js/abilities/components/AbilitiesList.jsx` returns nothing (block removed).
- [ ] `grep -n "text-align.*right" src/scss/abilities/admin.scss` returns a match for `.tablenav-pages-below`.
- [ ] No JS console errors on the abilities list page.
- [ ] Bottom pagination is visually right-aligned; top tablenav is unaffected.

### Quality gates

- [ ] `composer run phpstan` passes at level 8.
- [ ] `composer run phpcs` (production surface) passes with no new errors.
- [ ] `vendor/bin/phpunit` passes locally.
- [ ] PHPUnit matrix CI (8.1–8.5) passes in GitHub Actions.
- [ ] PHPCompatibility CI passes with `testVersion 8.1-`.

---

## Spec-kit Commands

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
composer update wpboilerplate/wpb-access-control berlindb/core --with-all-dependencies
composer run phpstan
composer run phpcs
vendor/bin/phpunit

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

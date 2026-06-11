# Implementation Plan: Post-031 Hotfixes (Feature 032)

**Branch**: `032-post-031-hotfixes` | **Date**: 2026-06-11 | **Spec**: [spec.md](spec.md)

---

## Summary

Three independent hotfixes discovered after Feature 031 shipped:

**Fix A** — Two BerlinDB parent-contract violations in `AcrossAI_Ability_Logs_Query` caused
PHP Fatal errors on class load. BerlinDB v3 parent `Query` declares `get_table_name()` as
`public` and `get_logs()` with a second `$operator` param — both must be matched exactly.

**Fix B** — `inject_mcp_tools()` had only three access gates. The third (AC rules) is
fail-open: if no rule is configured, `user_has_ability_access()` returns `true` and abilities
are injected for all users, bypassing the ability's own `permission_callback` (e.g.
`manage_options`). Fix: add a fourth gate using `WP_Ability::check_permissions()`.

**Fix C** — Library submenu was registered last in `define_admin_hooks()`, making it fourth
in the menu. Moving its registration immediately after the main menu makes it second.

---

## Technical Context

- **Language/Version**: PHP 8.1+
- **Affected files**: 3 PHP files — no JS, no DB schema, no new REST routes
- **Testing**: Manual smoke test + PHPStan L8 + PHPCS
- **Risk**: Low — all three fixes are single-method or single-line changes

---

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| §I Modular Architecture | **PASS** | Changes confined to Logger/Database/, Abilities/, includes/Main.php |
| §II WordPress Standards | **PASS** | PHPCS clean; PHPStan L8 clean |
| §IV Security First | **PASS** | Fix B closes a security hole; Check 4 uses `check_permissions()` |
| §V Extensibility | **PASS** | No new hooks; AC fail-open behavior documented |
| §VII Definition of Done | **TRACKED** | All gates passed |

---

## Architecture

### Fix A — BerlinDB visibility + signature

`AcrossAI_Ability_Logs_Query` extends `BerlinDB\Database\Kern\Query`. PHP requires that
overriding methods match or widen the parent's access level and be signature-compatible.
`get_table_name()` was `private` (parent is `public`) and `get_logs()` was missing the
`string $operator = 'and'` second parameter.

**Key lesson** (BUG-BERLINDDB-QUERY-OVERRIDE-COMPAT): always run `phpstan --level=8` after
adding any BerlinDB override method — PHPStan catches these contract violations.

### Fix B — inject_mcp_tools() fourth gate

```
inject_mcp_tools() check order:
  1. pass_as_tool = 1          ← pre-filter
  2. mcp_servers allowlist     ← server-specific
  3. AC rules                  ← fail-open when absent
  4. check_permissions() [NEW] ← authoritative; always enforced
```

`WP_Ability::check_permissions()` returns `bool|WP_Error`. Returns `false` or `WP_Error`
when denied — both cases trigger `continue` in the injection loop.

**Key lesson** (BUG-WP-ABILITY-CHECK-PERMISSIONS): `WP_Ability` has NO
`get_permission_callback()` or `get_args()`. Probing for them via `method_exists()` silently
falls through. Always use `check_permissions()`.

### Fix C — Menu registration order

WordPress `add_submenu_page()` renders submenus in call order within the same hook priority.
Moving the Library block from line ~333 to immediately after line 251 (main menu) puts it
second without any priority adjustment.

---

## Implementation Changes

### FIX-A-1 — `AcrossAI_Ability_Logs_Query::get_table_name()`

**File**: `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php:200`

```php
// Before:
private function get_table_name(): string {

// After:
public function get_table_name(): string {
```

### FIX-A-2 — `AcrossAI_Ability_Logs_Query::get_logs()`

**File**: `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php:121`

```php
// Before:
public function get_logs( array $args = array() ): array {

// After:
public function get_logs( array $args = array(), string $operator = 'and' ): array {
```

`$operator` is accepted but not forwarded — the Logger query layer uses single-operator
queries only. Adding it satisfies the parent contract without behavioral change.

### FIX-B — `inject_mcp_tools()` Check 4

**File**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (~L687)

After Check 3 (AC rules), add:

```php
// Check 4: the ability's own permission_callback via WP_Ability::check_permissions().
$wp_ability = \wp_get_ability( $slug );
if ( $wp_ability ) {
    $perm_result = $wp_ability->check_permissions();
    if ( \is_wp_error( $perm_result ) || false === $perm_result ) {
        continue;
    }
}
```

### FIX-C — Library submenu registration order

**File**: `includes/Main.php`

Move the Library block (previously lines ~331–334) to immediately after `$main_menu`
registration (line 251), before `$logs_menu`:

```php
$main_menu = new Menu(...);
$this->loader->add_action( 'admin_menu', $main_menu, 'main_menu' );

// Library submenu — second position (Feature 027/031).
$ability_library_menu = LibraryMenu::instance();
$this->loader->add_action( 'admin_menu', $ability_library_menu, 'register_submenu' );

// Logs submenu ...
```

---

## What Must NOT Change

- `$operator` must NOT be forwarded to `$this->query()` in `get_logs()` — the Logger
  REST controller always passes the default and adding operator support is out of scope
- The three existing checks in `inject_mcp_tools()` (pass_as_tool, mcp_servers, AC rules)
- The `user_has_ability_access()` fail-open behavior — it is documented and intentional
- All other submenu registration positions (Logs, Settings, Add-ons)

---

## Validation

- [ ] Zero PHP Fatal errors in `debug.log` after plugin activation
- [ ] Non-admin user sees no admin-only ability in MCP tools when Pass as Tool = On
- [ ] Admin menu: Library is second item
- [ ] `composer phpcs` — zero errors
- [ ] `composer phpstan` level 8 — zero errors

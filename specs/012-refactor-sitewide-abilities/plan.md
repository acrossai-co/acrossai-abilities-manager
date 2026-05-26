# Implementation Plan: Refactor Sitewide Module into Abilities Module

**Branch**: `012-refactor-sitewide-abilities` | **Date**: 2026-05-24 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/012-refactor-sitewide-abilities/spec.md`
**Memory Synthesis**: [memory-synthesis.md](./memory-synthesis.md)

---

## Summary

PHP-only module consolidation. Delete the `includes/Modules/Sitewide/` module and move all durable logic (BerlinDB Table/Schema/Row classes, Override Processor, Access Control) into `includes/Modules/Abilities/`. Extend `AcrossAI_Abilities_Query` with 4 override CRUD methods ported from `AcrossAI_Sitewide_Query` and update its `$table_schema`/`$item_shape` properties to point at the new Abilities DB classes. Update 4 internal consumers that still reference Sitewide classes (`AcrossAI_Abilities_Read_Controller`, `AcrossAI_Abilities_Processor`, `AcrossAI_Ability_Registry_Query`, `AcrossAI_Abilities_Formatter`). Rewire `includes/Main.php` and update `includes/AcrossAI_Activator.php`. Delete the Sitewide REST sub-controllers. No DB schema changes, no hook name changes, no UI changes.

---

## Technical Context

**Language/Version**: PHP 7.4+ / WordPress 6.9+
**Primary Dependencies**: BerlinDB (via `wpb-access-control` or direct), `wpb-access-control` library
**Storage**: `acrossai_abilities` table — table name unchanged; no migration
**Testing**: PHPCS strict, PHPStan level 8, manual smoke test of admin UI + REST endpoints
**Target Platform**: WordPress single-site admin
**Performance Goals**: No performance impact; reduces symbol load
**Constraints**: Logger module untouched (FR-009). Hook names, REST namespaces, capability strings unchanged. No `private __construct()` on Table class (DEC-TABLE-SOFT-SINGLETON).
**Scale/Scope**: ~12 PHP files changed, ~8 files deleted, 1 directory removed

---

## Constitution Check

| Rule | Status | Notes |
|---|---|---|
| AC-HOOKS-MAIN | ✓ REQUIRED | All hook changes confined to `includes/Main.php` only; named-variable pattern enforced |
| AC-ENQUEUE-ADMIN | ✓ EXEMPT | No asset changes in this feature |
| AC-REST-SPLIT | ✓ PASS | Sitewide REST controllers deleted, not refactored; no new >400-line controllers |
| AC-REGISTRY-QUERY | ✓ REQUIRED | `AcrossAI_Ability_Registry_Query` must be updated (injected type changes) |
| AC-FILE-HEADER-PATTERN | ✓ REQUIRED | All moved/renamed files need correct `@package`/`@subpackage`/`@since` headers |
| DEC-TABLE-SOFT-SINGLETON | ✓ CRITICAL | New `AcrossAI_Abilities_Table` MUST NOT have `private function __construct()` |
| DEC-JSON-SIZE-GUARD | ✓ REQUIRED | `save_override()` port must preserve `$max_json_bytes = 65536` guard |
| DEC-BY-SOURCE-AUTHZ | ✓ REQUIRED | All ported query methods must carry `AUTHORIZATION CONTRACT` docblock |
| SEC-03 (`$global = false`) | ✓ CRITICAL | New `AcrossAI_Abilities_Table` must set `$global = false` |
| SEC-04 (strict type comparison) | ✓ REQUIRED | Access Control move must preserve `===` comparisons |
| ARCH-ADV-001 (`boot()` deviation) | ✓ PRESERVED | Override Processor `boot()` pattern travels with the class move |
| §II PHPCS/PHPStan | ✓ REQUIRED | Must pass at completion |
| §IV Security First | ✓ PASS | No new attack surface; reducing surface by deleting Sitewide REST |

**No Constitution violations. Proceed.**

---

## Project Structure

### Documentation (this feature)

```text
specs/012-refactor-sitewide-abilities/
├── spec.md
├── plan.md                 ← this file
├── memory-synthesis.md
├── checklists/
│   └── requirements.md
└── tasks.md                ← /speckit.tasks output
```

### Source Code Affected

```text
includes/
├── AcrossAI_Activator.php                                   # CHANGE: table class ref
├── Main.php                                                 # CHANGE: hook rewiring
├── Modules/
│   ├── Abilities/
│   │   ├── Database/
│   │   │   ├── AcrossAI_Abilities_Query.php                 # CHANGE: extend + update refs
│   │   │   ├── AcrossAI_Abilities_Table.php                 # CREATE: moved from Sitewide
│   │   │   ├── AcrossAI_Abilities_Schema.php                # CREATE: moved from Sitewide
│   │   │   └── AcrossAI_Abilities_Row.php                   # CREATE: moved from Sitewide
│   │   ├── AcrossAI_Ability_Override_Processor.php          # CREATE: moved from Sitewide
│   │   └── AcrossAI_Abilities_Access_Control.php            # CREATE: moved from Sitewide
│   │       (renamed from AcrossAI_Sitewide_Access_Control)
│   └── Sitewide/                                            # DELETE: entire directory
│       ├── Database/
│       │   ├── AcrossAI_Sitewide_Table.php
│       │   ├── AcrossAI_Sitewide_Schema.php
│       │   ├── AcrossAI_Sitewide_Row.php
│       │   └── AcrossAI_Sitewide_Query.php
│       ├── AcrossAI_Ability_Override_Processor.php
│       ├── AcrossAI_Sitewide_Access_Control.php
│       ├── AcrossAI_Sitewide_Rest_Controller.php
│       ├── Rest/                                            # DELETE: entire directory
│       └── index.php
└── Utilities/
    ├── AcrossAI_Ability_Registry_Query.php                  # CHANGE: use + type hints
    └── AcrossAI_Abilities_Formatter.php                     # CHANGE: use + type hints
```

---

## Implementation Design

Implementation order is dependency-driven: create Abilities DB classes first so they can be
referenced by everything else. Port query methods before updating consumers. Update consumers
before rewiring bootstrap. Delete Sitewide only after all references are gone.

---

### Change 1 — Create `AcrossAI_Abilities_Table.php`

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php`
**Source**: Copy from `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php`

**Class rename**: `AcrossAI_Sitewide_Table` → `AcrossAI_Abilities_Table`
**Namespace**: `AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database`
**Schema class reference**: update `$schema` to point at `AcrossAI_Abilities_Schema::class`

**Critical constraints (memory)**:
- **MUST** preserve `$global = false` (SEC-03 — multisite isolation)
- **MUST NOT** add `private function __construct()` (DEC-TABLE-SOFT-SINGLETON — `AcrossAI_Activator` uses `new AcrossAI_Abilities_Table()` directly)
- `$_instance` + `instance()` soft singleton — convention only, no language enforcement
- Update `@subpackage` header to `AcrossAI_Abilities_Manager/includes/Modules/Abilities/Database`

**Risk**: Low — only PHP class/namespace rename, no logic change.

---

### Change 2 — Create `AcrossAI_Abilities_Schema.php`

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`
**Source**: Copy from `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php`

**Class rename**: `AcrossAI_Sitewide_Schema` → `AcrossAI_Abilities_Schema`
**Namespace**: `AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database`
**Header**: update `@subpackage` to `AcrossAI_Abilities_Manager/includes/Modules/Abilities/Database`

**Risk**: Low — pure rename, no logic change.

---

### Change 3 — Create `AcrossAI_Abilities_Row.php`

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php`
**Source**: Copy from `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php`

**Class rename**: `AcrossAI_Sitewide_Row` → `AcrossAI_Abilities_Row`
**Namespace**: `AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database`
**Header**: update `@subpackage` to `AcrossAI_Abilities_Manager/includes/Modules/Abilities/Database`

**Risk**: Low — pure rename, no logic change. `get_json_fields()` static method preserved.

---

### Change 4 — Extend `AcrossAI_Abilities_Query.php`

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`
**Source for ported methods**: `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php`

**Sub-change 4a — Update `use` statements**:
```php
// REMOVE:
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Schema;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row;

// ADD:
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Schema;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Row;
```

**Sub-change 4b — Update `$table_schema` and `$item_shape` properties**:
```php
// BEFORE:
protected $table_schema = AcrossAI_Sitewide_Schema::class; // ← reused, no duplication
protected $item_shape   = AcrossAI_Sitewide_Row::class;   // ← reused, no duplication

// AFTER:
protected $table_schema = AcrossAI_Abilities_Schema::class;
protected $item_shape   = AcrossAI_Abilities_Row::class;
```

**Sub-change 4c — Update docblock**: Remove the "reused, no duplication" architecture comment (deliberate supersession per spec Assumption/clarification Q3). Update to document self-contained Abilities module ownership.

**Sub-change 4d — Update all existing return type hints and closure parameter types**: The following existing methods reference `AcrossAI_Sitewide_Row` in their return types or closure signatures:
- `get_ability_by_id()`: return type `?AcrossAI_Sitewide_Row` → `?AcrossAI_Abilities_Row`
- `get_ability_by_slug()`: return type `?AcrossAI_Sitewide_Row` → `?AcrossAI_Abilities_Row`
- `by_source()`: return type `array` (closures at lines ~348 and ~366 use `AcrossAI_Sitewide_Row $row` parameter type hints) → `AcrossAI_Abilities_Row $row`
- `get_paginated()`: closures use `AcrossAI_Sitewide_Row $row` → `AcrossAI_Abilities_Row $row`

**Sub-change 4e — Port 4 override CRUD methods** from `AcrossAI_Sitewide_Query`:
1. `get_override_by_slug( string $slug ): ?AcrossAI_Abilities_Row` — SEC-01: `sanitize_ability_slug()` applied to `$slug` before query
2. `save_override( string $slug, array $fields ): bool` — MUST preserve: (a) `$max_json_bytes = 65536` JSON size guard (DEC-JSON-SIZE-GUARD); (b) `before_save` / `after_save` hook calls on complete `$fields` only (BUG-PARTIAL-HOOK-FIELDS); (c) `AUTHORIZATION CONTRACT` docblock (DEC-BY-SOURCE-AUTHZ)
3. `delete_override_by_slug( string $slug ): bool` — SEC-01: `sanitize_ability_slug()` applied
4. `get_all_overrides(): array` — MUST use `'number' => 0` (not `-1`, not `9999`) — BUG-BERLINDB-UNLIMITED

**Not porting**: `by_source()` (already present in `AcrossAI_Abilities_Query` with equivalent logic)

**AUTHORIZATION CONTRACT docblock** (required on all 4 ported methods, verbatim pattern):
```php
/**
 * AUTHORIZATION CONTRACT (DEC-BY-SOURCE-AUTHZ):
 * This is an authorization-free DB helper.
 * Every caller surfacing results to an HTTP response MUST enforce
 * current_user_can( 'manage_options' ) before invoking this method.
 * See OWASP A01:2025.
 */
```

**Risk**: Medium — largest single file change. Verify PHPStan passes after this step.

---

### Change 5 — Create `AcrossAI_Ability_Override_Processor.php` (Abilities)

**File**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`
**Source**: Copy from `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`

**Changes**:
- Namespace: `AcrossAI_Abilities_Manager\Includes\Modules\Abilities`
- Update `use` statements: any Sitewide DB class references → Abilities equivalents
- Update `@subpackage` header to `AcrossAI_Abilities_Manager/includes/Modules/Abilities`

**Preservation requirements**:
- `boot()` method with conditional PATH-A/PATH-B hook wiring MUST be preserved verbatim (ARCH-ADV-001)
- Override cache mechanism preserved without modification

**Risk**: Low — namespace rename + use statement updates only.

---

### Change 6 — Create `AcrossAI_Abilities_Access_Control.php`

**File**: `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php`
**Source**: Copy from `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php`

**Changes**:
- Class rename: `AcrossAI_Sitewide_Access_Control` → `AcrossAI_Abilities_Access_Control`
- Namespace: `AcrossAI_Abilities_Manager\Includes\Modules\Abilities`
- Update `use` statements for any Sitewide DB class references → Abilities equivalents
- Update `@subpackage` header to `AcrossAI_Abilities_Manager/includes/Modules/Abilities`

**Preservation requirements**:
- All `===` strict comparisons MUST be preserved (SEC-04, BUG-LOOSE-COMPARISON-BYPASS)
- `wpb-access-control` library integration points unchanged; only PHP class container moves

**Risk**: Low.

---

### Change 7 — Update `AcrossAI_Abilities_Processor.php`

**File**: `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`

**Change**: Replace `use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row` with `use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Row`. Update all `AcrossAI_Sitewide_Row` type references in method signatures and docblocks.

**Risk**: Low — 1 use statement + type hint updates.

---

### Change 8 — Update `AcrossAI_Abilities_Read_Controller.php`

**File**: `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php`

**Change**: Replace `use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query` with `use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query`. Update any type hints referencing `AcrossAI_Sitewide_Query`.

**Risk**: Low.

---

### Change 9 — Update `AcrossAI_Ability_Registry_Query.php`

**File**: `includes/Utilities/AcrossAI_Ability_Registry_Query.php`

**Changes**:
- Replace `use` for `AcrossAI_Sitewide_Query` → `AcrossAI_Abilities_Query` (AC-REGISTRY-QUERY)
- Update all method parameter type hints from `AcrossAI_Sitewide_Query` → `AcrossAI_Abilities_Query`
- Verify `get_override_by_slug()` call site — method is now present on `AcrossAI_Abilities_Query` (ported in Change 4)

**Risk**: Low. Verify `query()` method signature still compatible after type change.

---

### Change 10 — Update `AcrossAI_Abilities_Formatter.php`

**File**: `includes/Utilities/AcrossAI_Abilities_Formatter.php`

**Changes**:
- Replace `use` for `AcrossAI_Sitewide_Row` → `AcrossAI_Abilities_Row`
- Update all 4 method parameter type hints from `AcrossAI_Sitewide_Row` → `AcrossAI_Abilities_Row`

**Risk**: Low. Static-only class; no instance changes.

---

### Change 11 — Update `includes/Main.php`

**File**: `includes/Main.php`

**Sub-change 11a — Replace Table class** (FR-006, FR-011):
```php
// BEFORE:
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table;
...
$sitewide_table = AcrossAI_Sitewide_Table::instance();
$this->loader->add_action( '...', $sitewide_table, '...' );

// AFTER:
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Table;
...
$abilities_table = AcrossAI_Abilities_Table::instance();
$this->loader->add_action( '...', $abilities_table, '...' );
```

**Sub-change 11b — Remove Sitewide_Rest_Controller wiring** (FR-005, FR-006):
Remove the `use` statement and all `$this->loader->add_action( 'rest_api_init', ... )` calls for `AcrossAI_Sitewide_Rest_Controller`.

**Sub-change 11c — Replace Access Control class** (FR-004, FR-006):
```php
// BEFORE:
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Access_Control;
...
$sitewide_ac = AcrossAI_Sitewide_Access_Control::instance();

// AFTER:
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control;
...
$abilities_ac = AcrossAI_Abilities_Access_Control::instance();
```

**Sub-change 11d — Re-wire Override Processor cache bust** (CRITICAL-01 — security review finding):

The existing `acrossai_abilities_sitewide_after_save` hook wiring (line 319) fires ONLY when the Sitewide REST Override Controller saves — a controller being deleted in Change 13. Without re-wiring, the override cache is never busted after save/delete, causing stale overrides to persist (OWASP A01:2025).

```php
// REMOVE (wires to a hook that will no longer fire after Change 13):
$this->loader->add_action( 'acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook' );

// ADD (wire to all three Abilities Write Controller mutation hooks):
$this->loader->add_action( 'acrossai_abilities_after_create', $override_processor, 'bust_cache_hook' );
$this->loader->add_action( 'acrossai_abilities_after_update', $override_processor, 'bust_cache_hook' );
$this->loader->add_action( 'acrossai_abilities_after_delete', $override_processor, 'bust_cache_hook' );
```

`bust_cache_hook(): void` accepts no parameters — compatible with all three action signatures.

**Architecture constraint**: Named variable MUST be resolved before passing to `$this->loader->add_action()` — no inline `::instance()` (AC-HOOKS-MAIN).

**Risk**: Medium — affects bootstrap; verify all hooks still fire correctly.

---

### Change 12 — Update `includes/AcrossAI_Activator.php`

**File**: `includes/AcrossAI_Activator.php`

**Change**: Replace `use AcrossAI_Sitewide_Table` with `use AcrossAI_Abilities_Table`. Update instantiation `new AcrossAI_Sitewide_Table()` → `new AcrossAI_Abilities_Table()`.

**Critical constraint**: `new AcrossAI_Abilities_Table()` (not `::instance()`) must work — DEC-TABLE-SOFT-SINGLETON requires no `private __construct()` on Table class.

**Risk**: Low.

---

### Change 13 — Delete `includes/Modules/Sitewide/`

**Action**: Delete the entire directory and all contents after all references in non-Sitewide files have been updated (Changes 4–12 complete).

**Files deleted**:
- `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php`
- `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php`
- `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php`
- `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php`
- `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`
- `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php`
- `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php`
- `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php`
- `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php`
- `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Mcp_Controller.php`
- `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php`
- `includes/Modules/Sitewide/Rest/index.php`
- `includes/Modules/Sitewide/index.php`

**Verification before delete**: `grep -r "Sitewide" includes/ --include="*.php"` must return only the Sitewide directory itself (no orphan references in non-Sitewide files).

**Risk**: Low if Changes 4–12 are complete. High if any reference remains — verify grep first.

---

## Implementation Order (Dependency Chain)

```
[1] Create Abilities_Table    (no deps)
[2] Create Abilities_Schema   (no deps)
[3] Create Abilities_Row      (no deps)
[4] Extend Abilities_Query    (deps: 1,2,3)
[5] Create Override_Processor (deps: 4)
[6] Create Abilities_AC       (deps: 4)
[7] Update Abilities_Processor (deps: 3)
[8] Update Read_Controller     (deps: 4)
[9] Update Registry_Query      (deps: 4)
[10] Update Formatter           (deps: 3)
[11] Update Main.php            (deps: 1,5,6)
[12] Update Activator.php       (deps: 1)
[13] Delete Sitewide/           (deps: 4-12 ALL complete; run grep verify first)
```

---

## Security Design

- No new endpoints or data paths introduced — net reduction in attack surface (Sitewide REST deleted)
- `save_override()` port: JSON 64 KB size guard from DEC-JSON-SIZE-GUARD MUST be verified as preserved
- Access control class: `===` strict comparisons preserved (SEC-04, BUG-LOOSE-COMPARISON-BYPASS)
- `$global = false` on `AcrossAI_Abilities_Table` preserved (SEC-03 multisite isolation)
- All 4 ported query methods carry `AUTHORIZATION CONTRACT` docblock (DEC-BY-SOURCE-AUTHZ)

---

## Verification Checklist

- [ ] `includes/Modules/Sitewide/` directory absent
- [ ] `grep -r "AcrossAI_Sitewide" includes/ --include="*.php"` — zero results
- [ ] `grep -r "AcrossAI_Sitewide" admin/ --include="*.php"` — zero results
- [ ] `composer run phpstan` — zero errors (PHPStan level 8)
- [ ] `composer run phpcs` — zero violations
- [ ] Plugin activates without PHP errors on fresh install
- [ ] Admin Abilities page loads and overrides save/display correctly
- [ ] `GET /wp-json/acrossai-abilities-manager/v1/sitewide/abilities` returns 404
- [ ] `GET /wp-json/acrossai-abilities-manager/v1/abilities` returns unchanged correct response

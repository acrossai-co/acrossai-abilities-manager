# Architecture

Last reviewed: 2026-05-22

## System Overview

AcrossAI Abilities Manager is a WordPress plugin that adds a management UI
and runtime enforcement layer on top of the WordPress Abilities API
(WP 6.9+). Admins configure per-ability overrides in the Manager UI; those
overrides are applied at request boot time on all non-Manager requests.

The plugin follows the WordPress Plugin Boilerplate PSR-4 layout with a
central Loader/Main.php hook surface, BerlinDB for override persistence,
and a REST-API-first admin UI backed by @wordpress/dataviews.

## Major Components

- **`includes/Main.php`**: Sole hook-registration surface. All Loader
  wiring lives here. No other class calls `add_action` / `add_filter`
  through the Loader (exception: ARCH-ADV-001 in boot()).
- **`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor`**:
  Static runtime processor. Bridges DB overrides into WP ability
  registrations at `plugins_loaded P20` via PATH A/B branching. Wires
  `wp_register_ability_args`, `wp_abilities_api_init`, and
  `mcp_adapter_expose_ability` directly in `boot()` (ARCH-ADV-001 deviation).
- **`includes/Modules/Abilities/Database/`**: Self-contained BerlinDB classes —
  `AcrossAI_Abilities_Table`, `AcrossAI_Abilities_Schema`, `AcrossAI_Abilities_Row`,
  and `AcrossAI_Abilities_Query`. `AcrossAI_Abilities_Query` is the single entry
  point for all DB reads/writes. JSON field decoding happens in
  `AcrossAI_Abilities_Row::__construct()` via `get_json_fields()`. Supersedes the
  Sitewide DB classes (Feature 012).
- **`includes/Modules/Abilities/AcrossAI_Abilities_Access_Control`**: Wraps
  `wpb-access-control` library for per-ability rule storage and permission
  callback injection.
- **`includes/Utilities/`**: Shared sanitization (`AcrossAI_Sanitizer`),
  field utilities.

## Boundaries

- **Manager REST namespace** (`acrossai-abilities-manager/v1`): PATH A —
  override injection is skipped entirely. All Manager UI reads see pure
  WP registry values, never merged override values.
- **All other requests** (PATH B): override injection fires at boot; blocked
  abilities are unregistered after all registrations complete.
- **BerlinDB layer**: `AcrossAI_Abilities_Query` is the only entry point for
  DB reads/writes. No direct `$wpdb` SQL in module or REST classes.
- **Hook surface**: Only `includes/Main.php` wires hooks through the Loader.
  `boot()` in the processor is the only approved exception (ARCH-ADV-001).

## Integrations

- **WordPress Abilities API** (WP 6.9+): `wp_register_ability_args`,
  `wp_abilities_api_init`, `wp_unregister_ability`.
- **MCP Adapter** (external plugin): `mcp_adapter_expose_ability` filter
  for per-server allowlist enforcement (PATH B only, `accepted_args = 3`).
- **`wpb-access-control` library**: Per-ability permission rules. Injected
  at `$args['permission_callback']` time. Fails open when library absent.
- **(Removed in Feature 034)** The `wpb-mcp-servers-list` library was used until
  Feature 034 (2026-06-14) to collect registered MCP server IDs for the deleted
  Allowed Servers admin dropdown. The library has been excised; no replacement.
  Any plugin needing MCP-server enumeration chooses its own mechanism via the
  5-hook extension surface from `specs/034-.../contracts/extension-hooks.md`.
- **Action Scheduler**: Not currently used; prefer for any future async jobs.

## Risks / Complexity Hotspots

- **Override cache TTL (12h transient)**: Stale cache after delete/reset is
  mitigated by direct `bust_cache()` calls in Override + Bulk controllers.
  Any new write path must fire one of `acrossai_abilities_after_create`,
  `acrossai_abilities_after_update`, or `acrossai_abilities_after_delete`; if none
  apply, call `bust_cache()` directly (W-001 pattern — see DECISIONS.md).
- **PATH A detection is a performance hint, not a security gate**: If the
  Manager REST namespace constant is misconfigured, override injection may
  fire on Manager requests. REST routes remain protected by `check_permission()`
  independently of PATH detection.
- **`mcp_adapter_expose_ability` accepted_args = 3**: Fail-open when
  `$server_id` is empty (MCP adapter passes fewer than 3 args). Update
  `accepted_args` if MCP adapter contract changes.
- **PHPUnit blocked**: No WP test bootstrap in project. All PHPUnit test
  files exist but cannot be run until `phpunit.xml.dist` + WP bootstrap
  shim are added (T014, pre-existing gap).


## AC-QUERY-LAYER-FILTERING

All list-endpoint filtering (search, sort, pagination, field filtering) MUST occur in the query builder layer (`AcrossAI_Ability_Registry_Query`), not in the REST controller. Pagination headers (`X-WP-Total`, `X-WP-TotalPages`) MUST reflect the filtered results.

**Rationale**: Query layer is the single source of truth for "what items exist in the result set". Filtering at query layer ensures pagination counts are accurate, search/sort/filter operations treat filtered items as non-existent, and REST controller doesn't duplicate filtering logic.

**Pattern**: In query builder loop, before adding to results: `if ( condition ) { continue; }` to skip filtered items and prevent them from being added to result set.

**Example**: `AcrossAI_Ability_Registry_Query::query()` excludes protected abilities at line 67–70 before appending to `$results[]`.

**Reference**: Feature 005 (commit `62d25ad`), plan.md FR-001, FR-003.

---

## PATTERN-SINGLE-SOURCE-UTILITY

When a logical concept is used in multiple places (query layer + REST controller), extract it to a single utility class with public static methods. Call the utility from both locations instead of duplicating logic.

**Benefits**:
- DRY principle enforced (Constitution §VI)
- Single edit point (fix once, applies everywhere)
- Easier to test in isolation
- Self-documenting (utility name = concept name)

**Structure**:
```php
// includes/Utilities/AcrossAI_SingleSourceTruth.php
class AcrossAI_SingleSourceTruth {
    public static function get_items(): array { /* return list */ }
    public static function is_member( string $id ): bool {
        return in_array( $id, self::get_items(), true );
    }
}

// Usage location 1: Query layer
if ( AcrossAI_SingleSourceTruth::is_member( $item_id ) ) { ... }

// Usage location 2: REST controller
if ( AcrossAI_SingleSourceTruth::is_member( $slug ) ) { ... }
```

**When to Apply**: Logic used in 2+ locations, small enough for `Utilities/`, stateless (only static methods).

**Reference**: `AcrossAI_Protected_Abilities` (feature 005, commit `62d25ad`), called from query layer + REST controller.

## PATTERN-STAGE-NAMING

In modules with multi-stage data transformations (raw → processed → formatted → stored), use distinct variable names for each transformation stage. This improves code clarity and prevents accidental overwrites.

**Pattern**:
```php
// Stage 1: Raw extraction
$output_value = $result;

// Status detection based on raw value
if ( is_wp_error( $result ) ) {
    $output_value = $result->get_error_message();
}

// Stage 2: Formatting/truncation
$formatted_output = AcrossAI_Logger_Formatter::format_value( $output_value );

// Stage 3: Storage
$entry['output'] = $formatted_output;
```

**Why this matters**:
- Reader immediately sees which stage each variable represents
- Prevents conditional overwrites from affecting later logic
- Self-documents the transformation pipeline
- Easier to debug (set breakpoints at each stage)

**When to Apply**: Any class processing data through 3+ stages (raw, validated, transformed, formatted, stored).

**Reference**: `AcrossAI_Ability_Logger::finish_pending_entry()` (feature 006, lines 195–220), where `$output_value` (raw) vs. `$formatted_output` (stage 2) enables clear status detection logic without confusion.

**Evidence**:
Feature 006 (2026-05-20): Refactored logger to use `$output_value` (raw), `$formatted_output` (formatted). Code review confirmed improved readability. PHPCS 0 errors.

---

## PATTERN-FEATURE-ASSET-SEPARATION

When a feature module has its own admin UI, separate its assets from the main manager assets. Use feature-specific asset handles instead of generic names to prevent coupling and enable independent rebuild/versioning.

**Pattern**:
```
build/
  css/
    index.css              # main manager assets
    logger.css             # feature-specific: Feature 006
  js/
    index.js               # main manager assets
    logger.js              # feature-specific: Feature 006
```

**In Admin/Main.php**:
```php
public function enqueue_styles( string $hook_suffix ) {
    $on_abilities = false !== strpos( $hook_suffix, 'acrossai-abilities-manager' );
    $on_logs      = false !== strpos( $hook_suffix, 'acrossai-abilities-logs' );
    
    if ( ! $on_abilities && ! $on_logs ) {
        return;
    }
    
    // Main assets
    if ( $on_abilities ) {
        wp_enqueue_style( 'acrossai-abilities-manager', ... );
    }
    
    // Feature-specific assets
    if ( $on_logs && $this->logger_asset_file ) {
        wp_enqueue_style( 'acrossai-abilities-logger', ... );
    }
}
```

**Why this matters**:
- Each feature can be built/deployed independently
- No cross-feature asset conflicts
- Clear ownership of which CSS/JS belongs to which feature
- `webpack.config.js` can define separate entry points

**When to Apply**: When a feature module adds new admin pages or tabs with dedicated UI.

**Reference**: Feature 006 logger (2026-05-20): Assets named `logger.css`, `logger.js`, `logger.asset.php` (not `index.*`). Admin/Main.php extended hook suffix detection to load logger assets only on `acrossai-abilities-logs` page.

**Evidence**:
Old pattern: `build/js/index.css` + `build/js/index.js` used for all admin UI (coupled).
New pattern: `build/css/logger.css` + `build/js/logger.js` isolated to logger tab (decoupled).
Admin/Main.php enqueue_scripts() now checks both `acrossai-abilities-manager` and `acrossai-abilities-logs` hook suffixes before enqueueing.


## Keep Here
- stable system boundaries (PATH A/B, Manager namespace, BerlinDB layer)
- ownership lines between modules or services
- integration constraints that affect many features (ARCH-ADV-001, W-001)

## Never Store Here
- step-by-step implementation plans
- one-off feature details
- stale diagrams without current boundaries

Update the review date when boundaries, ownership, or integrations materially change.

## AC-FILE-HEADER-PATTERN

All PHP files must follow a standardized file header pattern. This ensures consistency across the codebase and enables automated tooling.

**Exact pattern**:
```php
<?php
/**
 * Brief description (one line).
 *
 * Longer description (optional, 1-2 sentences).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
```

**Key rules**:
- `@package`: Always `AcrossAI_Abilities_Manager` (underscores, not backslashes)
- `@subpackage`: Full PSR-4 path starting with `AcrossAI_Abilities_Manager`, e.g., `AcrossAI_Abilities_Manager/includes/Modules/Logger`
- `@since`: Always `0.1.0` (not `1.0.0`; 0.1.0 represents initial plugin release)
- ABSPATH check: Use `defined( 'ABSPATH' ) || exit;` format (modern guard, single line with `||`)
- Namespace: Matches @subpackage with backslashes and follows underscore convention

**Reference file**:
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` demonstrates the correct pattern.

**Evidence**:
Feature 006 (2026-05-19): Fixed file headers in 3 logger files to match this pattern. All changed from old-style `if ( ! defined( 'ABSPATH' ) ) { exit; }` to modern guard. All changed from `@package AcrossAI\Abilities\Logger` to `@package AcrossAI_Abilities_Manager`. PHPCS 0 errors, PHPStan L8 exit 0.

**Why this is durable**:
New developers copy-paste headers from existing files. If all files follow one pattern, copy-paste stays consistent. If files vary, inconsistency spreads. This constraint prevents drift.

---

## 2026-05-20 — Enable dependency upgrades without plugin code changes (ARCH-ZERO-CODE-DEPENDENCY-UPGRADE)

**Pattern**: Architecture that allows dependency upgrades (composer constraint changes only) without modifying plugin code

**Conditions** (all required):
1. **Singleton-based service integration** — Services are accessed via `::instance()` static factory, not direct instantiation
2. **Interface-based dependency injection** — Integration points use service locators or abstract interfaces, not concrete class dependencies
3. **No breaking API changes** — Pre-validated via pre-update audit (changelog, API signature review, security scan)
4. **Clean separation of concerns** — Library is isolated from plugin hooks, Main.php, and core architecture

**Implementation Pattern**:
```php
// ✅ Singleton + Service Locator (supports zero-code upgrades)
class AcrossAI_Sitewide_Access_Control {
    private static $_instance = null;
    
    public static function instance() {
        if ( null === self::$_instance ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    private function __construct() {}
    
    public function get_manager() {
        // Service locator pattern — library is encapsulated
        $ac = new wpboilerplate\AccessControlLibrary();
        return $ac->get_manager();
    }
}

// Usage: always via instance()
$ac = AcrossAI_Sitewide_Access_Control::instance();
$manager = $ac->get_manager(); // Works regardless of library version
```

**Benefit**: Allows upgrades to ^X.Y constraints with zero plugin code changes; only composer.json and composer.lock are modified.

**Validation**: All Phase 1 tests must pass without plugin code changes. If code changes are required, the upgrade is NOT zero-code; refactor architecture or escalate as Feature X-N (separate task).

**Evidence**:
Feature 007 (2026-05-20): Upgraded wpb-access-control dev-main → ^1.0 with:
- **0 plugin files modified** (only composer.json, composer.lock)
- **0 code changes to AcrossAI_Sitewide_Access_Control** (pre-existing singleton pattern worked as-is)
- **100% Phase 1 test pass rate** (6/6 tests, no code adaptation needed)
- **All security constraints validated** (DEC-PERM-CB, SEC-04, SEC-03, DEC-FAIL-OPEN-NOTICE)

**Counter-Example** (do NOT do this):
```php
// ❌ Direct instantiation (breaks with API changes)
public function get_manager() {
    return new wpboilerplate\AccessControlManager(); // If constructor signature changes, breaks
}

// ❌ Static method coupling (hard to version)
$manager = AccessControlManager::get_instance(); // Hardcoded class name

// ❌ Concrete class properties (prevents upgrades)
private AccessControlManager $manager; // If interface changes, code breaks
```

**Where to Look Next**:
- `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php` (singleton + service locator pattern)
- `specs/007-upgrade-access-control/` (zero-code upgrade example)
- `.specify/memory/CONSTITUTION.md` (singleton pattern requirement)

**Maintenance Rule**:
When adding new library integrations, architect using singleton + service locator pattern to enable future zero-code upgrades. Document public API contracts in code comments. Test integration points with multiple library versions (if available) before locking to a specific constraint.


---

## PATTERN-ENQUEUE-PAGE-GUARD

Page detection in `enqueue_styles()` and `enqueue_scripts()` MUST use dedicated `is_*_page()` boolean helper methods. Never use intermediate `strpos` variables (`$on_abilities`, `$on_logs`). Each helper uses Yoda `===` strict comparison against a hardcoded WP hook suffix string.

**Required pattern**:
```php
public function enqueue_scripts( string $hook_suffix ) {
    if ( ! $this->is_manager_page( $hook_suffix ) && ! $this->is_logs_page( $hook_suffix ) ) {
        return;
    }
    // ...
}

private function is_manager_page( string $hook_suffix ): bool {
    return 'toplevel_page_acrossai-abilities-manager' === $hook_suffix;
}
```

**Forbidden pattern**:
```php
$on_abilities = false !== strpos( $hook_suffix, 'acrossai-abilities-manager' ); // strpos variable
$on_logs      = false !== strpos( $hook_suffix, 'acrossai-abilities-logs' );    // strpos variable
if ( ! $on_abilities && ! $on_logs ) { return; }
```

**Why this matters**:
- `strpos` intermediate variables are architecture violations (V1/V2 flagged in Feature 011 review)
- `===` strict comparison prevents type-coercion bypass (SC-011-04)
- Named helpers are self-documenting and reusable across enqueue methods
- Extends AC-ENQUEUE-ADMIN constraint; see also DEC-MENU-HOOK-SUFFIX

**Evidence**: Feature 011 (2026-05-24) — V1/V2 architecture violations resolved in T011. PHPCS exit 0, PHPStan L8 exit 0.

**Reference**: `admin/Main.php` (`is_manager_page()`, `is_logs_page()` — canonical implementations).

---

## PATTERN-ASSET-DECOMMISSION-ORDER

When decommissioning a webpack bundle, the PHP constructor `include` MUST be removed before deleting source files or build artifacts. Removing the built file before the PHP include causes a PHP fatal error on the next page load.

**Correct order**:
1. Remove PHP constructor `include` and associated property (T008 pattern)
2. Remove webpack entry from `webpack.config.js`
3. Delete source files (`src/js/bundle/`, `src/scss/bundle/`)
4. Run a clean build (`npm run build`)

**Wrong order** (causes PHP fatal):
- Delete `build/js/bundle.asset.php` ← triggers fatal immediately
- Then try to remove the PHP include

**Why this matters**:
- WordPress boots PHP before any build step; a missing `include` target is a fatal error
- The fatal is hard to diagnose because the asset file is simply absent with no PHP warning at the include site
- This ordering risk was documented as RISK-001 in Feature 011 `tasks.md` before implementation began

**Evidence**: Feature 011 (2026-05-24) — PLAN-SEC-003 flagged the ordering risk in `specs/011-merge-abilities-ui/security-constraints.md`. Task order enforced: T008 (PHP) → T002/T003 (sources) → T001 (webpack) → T015 (build). Zero PHP fatals during implementation.

**Reference**: `specs/011-merge-abilities-ui/tasks.md` (T008, RISK-001), `admin/Main.php` constructor (correct `file_exists()` guard pattern).

---

## PATTERN-MODULE-DECOMMISSION

When decommissioning a module and merging it into a target module, follow this ordered sequence:

1. Create renamed DB layer classes (Table, Schema, Row) in the target module directory.
2. Update target Query's `$table_schema`/`$item_shape` + all `use` statements and return types.
3. Port CRUD methods from old Query into target Query (apply `sanitize_ability_slug()` as first statement per SEC-01).
4. Update all consumers (Processor, Access Control, admin classes) to import from the target module.
5. Remove old module bootstrap wiring from `Main.php`.
6. Delete old REST controllers entirely — no porting needed if the target module already has REST coverage for the same data.
7. Pre-deletion grep: confirm zero references to old class names before deleting any files.
8. Delete the old module directory.

**Never delete files before step 7 passes cleanly.**

**Reference**: Feature 012 — Sitewide module decommission into Abilities module; tasks T002–T021 (commit `56139de`).

---

## PATTERN-BERLINDDB-QUERY-PORT

When porting a BerlinDB Query class to a renamed module, do not create new Row/Schema/Table classes from scratch. Only update:

- (a) the two `use` statements pointing to the old Row/Schema
- (b) `$table_schema = NewSchema::class` and `$item_shape = NewRow::class`
- (c) all method return types and closure parameter types: `OldRow` → `NewRow`

The renamed DB classes are sufficient; no logic changes needed beyond the above three areas.

**Reference**: Feature 012 T005 — `AcrossAI_Abilities_Query.php` ported from Sitewide to Abilities without new Row/Schema classes (commit `56139de`).

---

## PATTERN-NAMED-EXPORT-JEST

To unit-test a pure helper embedded in a React component module without rendering the component, export the helper as a named `export function` alongside the default component export. Jest imports it with `const { helper } = require('../path/Component.jsx')`. The default export is unaffected.

**Rules**:
- The helper must be side-effect free and stateless (no hook calls, no closure over component state).
- Named exports from JSX component files are approved for testability only — not for shared business logic (use `src/js/utilities/` for that).
- The Jest test file must mock all `@wordpress/*` imports used by the module (`@wordpress/i18n`, `@wordpress/data`, etc.).

**Example**:
```js
// AbilityForm.jsx
export function validateRequiredFields(ability, slugSuffix) { /* pure logic */ }
export default function AbilityForm(props) { /* component */ }

// validateRequiredFields.test.js
const { validateRequiredFields } = require('../../../src/js/abilities/components/AbilityForm.jsx');
```

**Reference**: Feature 013 — `export function validateRequiredFields(ability, slugSuffix)` in `src/js/abilities/components/AbilityForm.jsx`, tested in `tests/jest/abilities/validateRequiredFields.test.js` (15 tests). Commit `35e9003`.

---

## ARCH-SANITIZER-TWO-CLASS

The sanitization layer has two classes. Always use the correct one.

- **`AcrossAI_Sanitizer`** (`includes/Utilities/AcrossAI_Sanitizer.php`): Base class. Owns `sanitize_mcp_servers_array()` and the `MAX_MCP_SERVERS` / `MAX_SERVER_ID_LENGTH` constants. FQCN: `\AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer`.
- **`AcrossAI_Abilities_Sanitizer`** (`includes/Utilities/AcrossAI_Abilities_Sanitizer.php`): Thin wrapper. Owns `sanitize_mcp_servers()` which delegates to the base class.

**Rule**: PHPUnit tests for MCP-server sanitization MUST use `AcrossAI_Sanitizer::sanitize_mcp_servers_array()` via its FQCN. Using `AcrossAI_Abilities_Sanitizer` is valid at call sites but the method signature differs; tests targeting boundary constants must target the base class.

**Evidence**: Feature 016 (2026-05-27) — `AbilitiesValidationTest.php` T017 tests use `\AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer::sanitize_mcp_servers_array()` directly. All 6 pass.

---

## ARCH-PHPUNIT-BOOTSTRAP

PHPUnit bootstrap for this plugin requires two specific preconditions:

1. **ABSPATH before autoloader**: `define('ABSPATH', dirname(__DIR__) . '/')` must appear in `tests/bootstrap.php` before `require_once vendor/autoload.php`. The `defined('ABSPATH') || exit` guard in every plugin file will silently exit and produce 0 tests if this order is wrong.
2. **Narrow `phpunit.xml.dist` scope**: Only include test files that do NOT transitively load BerlinDB Table subclasses. BerlinDB Table constructors call `add_action()` / `get_option()` — functions absent from stub bootstrap. DB-dependent test files (`AbilitiesQueryTest`, `AbilitiesWriteControllerTest`, `AbilitiesReadControllerTest`, `AbilitiesProcessorTest`, `AbilitiesExposureControllerTest`) require a real WP environment and must be excluded from the stub-bootstrap suite.

**Reference**: `tests/bootstrap.php`, `phpunit.xml.dist`.

---

## ARCH-ABILITYFORM-SECTION-ORDER

The canonical `AbilityForm.jsx` section DOM order is:

| # | Section | Variant |
|---|---------|---------|
| 1 | Identity | A (db) only |
| 2 | Site Permission | B (non-db) only |
| 3 | MCP Exposure (+ extension slot) | A + B |
| 4 | Annotation Overrides | A + B |
| **5** | **User Access** | **A + B** |
| 6 | Callback | A (db) only |
| 7 | Schema | A (db) only |

Numbers are global across both variants; sections not applicable to a variant simply do not render. All seven `.sect` divs must be children of a single `.panel` div. **As of Feature 034 (2026-06-14)**, the tail of Section 3 (MCP Exposure) renders the public extension slot `applyFilters( 'acrossai_abilities.form.extra_sections', [], { abilityId, slug, draft, isNonDb } )` at the location formerly occupied by the deleted Allowed Servers checkbox list. The slot is part of the public contract per FR-010 of Feature 034. New features adding sections must insert between Annotation Overrides (4) and User Access (5), or after Schema (7) — never outside the `.panel`. New features adding extension hook callsites should follow the dot-notation JS naming convention established by Feature 034 (`acrossai_abilities.form.*`).

**Evidence**: Feature 016 (2026-05-27) — commits `5de0307`, `161d1d4`, `e341d1a` restored order, corrected numbers 1–6. Feature 018 (2026-05-29) — inserted User Access as Section 5, renumbered Callback → 6, Schema → 7. Feature 034 (2026-06-14) — Section 3's Allowed Servers content replaced with `extra_sections` extension slot.

---

## PATTERN-AC-COMPONENT-INTEGRATION

Integrating the `@wpb/access-control` (`wpb-access-control`) vendor library into a React form:

1. **Named import only**: `import { AccessControl } from '@wpb/access-control'` — never default import.
2. **Webpack alias → `AccessControl.js`, not `index.js`**: `index.js` imports `AccessControl.scss`, which would extract CSS into the JS bundle's CSS sidecar. Point the alias directly at `AccessControl.js` to avoid SCSS double-extraction.
3. **SCSS import in the feature's SCSS entry**: `@import '../../../vendor/wpboilerplate/wpb-access-control/js/AccessControl';` appended to `src/scss/abilities/admin.scss` — never imported in JS.
4. **Module-level `abilitiesConfig`**: `const abilitiesConfig = window.acrossaiAbilitiesManager || {}` declared outside the component function (stable for the page lifetime).
5. **Three-branch rendering gate**:
   - `isCreate` → placeholder `<p>`
   - `!isCreate && !abilitiesConfig.access_control_available` → warning notice
   - `!isCreate && savedAbility?.ability_slug && abilitiesConfig.access_control_available` → `<AccessControl namespace="acrossai-abilities" resourceKey={savedAbility.ability_slug} restApiRoot={abilitiesConfig.rest_url || '/wp-json'} nonce={abilitiesConfig.nonce || ''} hideHeader hideSaveButton onChange={handleAcChange} />`
6. **No `onSave` prop**: the component manages its own save lifecycle; never integrate it with the main form save flow.
7. **`acSaveOk` dirty-reset pattern**: see `DEC-AC-SAVE-FLOW-PATTERN` in `DECISIONS.md`.
8. **`acInitialRef` baseline pattern**: see `DEC-ACINITIAL-REF-BASELINE` in `DECISIONS.md`.

**Evidence**: Feature 018 (2026-05-29). `src/js/abilities/components/AbilityForm.jsx`, `webpack.config.js`, `src/scss/abilities/admin.scss`.

---

## PATTERN-JEST-SECTION-SCOPE

When multiple `.sect` divs in `AbilityForm.jsx` share the same CSS class names
(e.g., `p.desc`, `p.notice-warning`), scope test assertions to the target section
by finding the `.sect` whose `sect-num` text matches:

```js
const getSection5 = () =>
    Array.from(container.querySelectorAll('.sect')).find(
        (sect) => sect.querySelector('.sect-num')?.textContent.trim() === '5'
    );
const sect5 = getSection5();
const placeholder = sect5.querySelector('p.desc'); // scoped — not global
```

Always scope to the target section. Global `container.querySelector('p.desc')` will
find the first match across all sections, producing false positives.

**Evidence**: Feature 018 T022 (2026-05-29) — `p.desc` from Section 3 ("No MCP servers registered yet.") was falsely matching Section 5 assertion.

---

## PATTERN-CHECKBOX-SANITIZE (2026-05-29, Feature 019)

Checkbox `register_setting()` sanitize callbacks MUST handle absent values. Browsers do not transmit unchecked checkbox inputs, so the callback receives `null`/`''` when the box is unchecked. Use `empty($value) ? 0 : 1` in a named public method (`sanitize_*_flag()`), not an inline closure (Settings API cannot serialize closures). Pass it as `array( $this, 'sanitize_*_flag' )`.

**Canonical example**: `SettingsMenu::sanitize_uninstall_flag()` in `admin/Partials/SettingsMenu.php`.

---

## PATTERN-UNINSTALL-DATA-GATE (2026-05-29, Feature 019)

`uninstall.php` MUST guard all destructive SQL inside `if ( (bool) get_option('acrossai_abilities_uninstall_delete_data', 0) )`. Tables and data are **preserved by default** (option default `0`). Plugin-owned settings options (config, not data) are always removed unconditionally.

**Rationale**: Prevents accidental data loss on plugin reinstall cycles or test-env uninstall triggers.

---

## PATTERN-LOGGER-OPTION-FEED-FILTER (2026-05-29, Feature 019)

When a module reads a settings option AND exposes an `apply_filters()` hook, feed the option value as the filter default: `apply_filters( 'hook', get_option( 'key', 0 ) )`. The scheduling guard short-circuits when the effective value is `0` (never schedule). This decouples "should I schedule?" from execution and preserves filter-based override for testing/programmatic control.

**Canonical example**: `AcrossAI_Ability_Logger::schedule_cleanup()` + `cleanup_old_logs()` in `includes/Modules/Logger/AcrossAI_Ability_Logger.php`.

---

## PATTERN-WP-DEBUG-LOG-GUARD (2026-05-30, Feature 020)

Wrap every `error_log()` call in a `WP_DEBUG_LOG` conditional guard for Plugin Check compliance. Never suppress or remove `error_log()` calls — guard them so they only fire when debug logging is explicitly enabled by the site owner.

**Canonical pattern** (identical for all call sites; must be exact):
```php
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    error_log( '...' );
}
```

Key rules:
- `defined()` check BEFORE boolean evaluation — avoids PHP notice on undefined constant
- `phpcs:ignore` moves INSIDE the guard, on the line immediately before `error_log()`
- Never use `WP_DEBUG` alone; never use `ini_get`; never vary the pattern across call sites

**Evidence**: Feature 020 — 12 `error_log()` calls guarded across 5 PHP files; Plugin Check CI passes with zero non-suppressed errors.

---

## PATTERN-CI-WORKFLOW-HARDENING (2026-05-30, Feature 020)

GitHub Actions CI workflows must apply three hardening measures:
1. **SHA-pin every `uses:` reference** to an immutable commit hash with the mutable tag as a comment: `uses: actions/checkout@<sha> # v4`. Prevents supply-chain substitution if an upstream tag is moved.
2. **Declare `permissions: {}` at the workflow level** (before `jobs:`), with specific permissions granted only at the job level. Prevents future jobs from inheriting broader token grants.
3. **Set `timeout-minutes` on every job** to fail fast and prevent unbounded runner consumption. Use `15` minimum for jobs that start Docker containers (e.g. `wp-env start`); `10` is sufficient for jobs with no container startup.

**Canonical example**: `.github/workflows/plugin-check.yml` — permissions: {}, timeout-minutes: 10, three SHA-pinned actions.

**Evidence**: Feature 020 — architecture review V1–V3 findings; all three applied.

---

## PATTERN-CONSTITUTION-SYNC-REPORT (2026-05-30, Feature 020)

Every `CONSTITUTION.md` version bump (MAJOR, MINOR, or PATCH) must also update the `<!-- SYNC IMPACT REPORT -->` HTML comment at the very top of the file. The comment must list: version change (e.g. `1.4.2 → 1.4.3`), modified sections, rationale, templates reviewed, and any deferred TODOs.

**Why**: The sync report is the primary audit trail for architecture governance changes. An unupdated sync report will mislead architecture reviewers about what changed and when.

**Evidence**: Feature 019 (1.4.1 → 1.4.2), Feature 020 (1.4.2 → 1.4.3).

---

## PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT (2026-05-30, Feature 020)

**Do NOT use `WordPress/plugin-check-action@v1` directly** — it has a silent exit-0 bug on Node 24.16 (`ubuntu-latest` ≥ 2026-05-25). See `BUG-PLUGIN-CHECK-ACTION-NODE24`.

The canonical CI pattern for running Plugin Check is to inline the steps manually:

```yaml
- name: Install @wordpress/env
  run: npm install -g --no-fund @wordpress/env

- name: Create wp-env config
  run: |
    cat > .wp-env.json << 'EOF'
    {
      "core": null,
      "plugins": [],
      "testsEnvironment": false,
      "mappings": {
        "wp-content/plugins/<your-slug>": "."
      }
    }
    EOF

- name: Start WordPress environment
  run: wp-env start

- name: Install Plugin Check
  run: wp-env run cli wp plugin install plugin-check --activate

- name: Run Plugin Check
  run: |
    wp-env run cli wp plugin activate <your-slug>
    wp-env run cli wp plugin check <your-slug> \
      --ignore-codes=<phpcs_error_codes> \
      --include-experimental
```

**Key rules**:
- `"plugins": []` in `.wp-env.json` — never add URL-based plugins here; install them via WP-CLI post-boot
- `"testsEnvironment": false` — starts one environment instead of two (faster, avoids Docker resource issues)
- `--ignore-warnings` does NOT exist on `wp plugin check` CLI — use `--ignore-codes` only
- `timeout-minutes: 15` minimum — Docker container startup adds significant time vs plain CI steps

**Evidence**: `.github/workflows/plugin-check.yml` at commit `d58f487` on branch `020-plugin-check-ci`.

---

### PATTERN-REGISTERED-CALLBACK-TRUST (Feature 021, 2026-05-31)

**When to use**: Any time an ability or plugin feature needs to execute a callable that was previously stored as PHP code in the database.

**Pattern**:
1. DB row stores a `sanitize_key()` callback key string only — never executable PHP.
2. Version-controlled plugin/theme code registers callables via `apply_filters('acrossai_abilities_registered_callbacks', array())`.
3. At execution time: retrieve the allow-list, apply `sanitize_key()` to the stored key, check `isset($callbacks[$key]) && is_callable($callbacks[$key])`, then dispatch via `call_user_func($callbacks[$key], $input)`.
4. Any missing or unregistered key returns `WP_Error('unsupported_callback_type')` — never a silent no-op.
5. Existing `php_code` DB rows must fail closed (step 4); they must never be silently mapped or executed.

**Why**: Eliminates `eval()` (OWASP A03) while preserving extensibility. Trust boundary is version control, not the database.

**Evidence**: `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` `registered_callback` case; `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`; `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`; Feature 021 commits `ec358de`–`8d2cdef`.

---

### PATTERN-CI-QUALITY-GATE-SPLIT (Feature 022, 2026-05-31)

**Pattern**: Split PHP quality gates into three dedicated CI workflows, each with a single concern:

| Workflow | Standard | Scope |
|----------|----------|-------|
| `phpcs.yml` | WPCS (`phpcs.xml.dist`) | All PHP paths scanned by `phpcs.xml.dist` |
| `phpstan.yml` | PHPStan level 8 | All PHP paths in `phpstan.neon.dist` |
| `phpcompat.yml` | PHPCompatibility `testVersion 7.4-` | Production dirs only: `acrossai-abilities-manager.php`, `uninstall.php`, `includes/`, `admin/`, `public/` |

**Key rules**:
- PHPCompatibility is in `phpcompat.yml` only — it was removed from `phpcs.xml.dist` to prevent double-counting and allow different scan scopes.
- All three workflows use `permissions: {}` at workflow level, `permissions: contents: read` at job level, SHA-pinned `actions/checkout` and `shivammathur/setup-php`, and `timeout-minutes: 10`.
- `phpcs.xml.dist` excludes `tests/`, `.specify/`, `docs/`, `specs/`, `src/`, `.claude/`, `.agents/`, `.github/` via `<exclude-pattern>` entries.

**Why**: One workflow per concern means failures are immediately attributable; PHPCompatibility scoped to production dirs avoids false positives from test stubs.

**Evidence**: `.github/workflows/phpcs.yml`, `phpstan.yml`, `phpcompat.yml`; commit `9da22d7` on branch `022-ci-workflows-phpcs-cleanup`.

---

### PATTERN-WORDPRESS-PEER-DEPENDENCIES (Feature 025, 2026-06-03)

**Pattern**: `@wordpress/data`, `@wordpress/element`, and `@wordpress/i18n` are WordPress script globals — they are provided by WordPress at runtime via `wp_enqueue_script` dependency handles, not bundled. Declare them in `peerDependencies` in `package.json` to prevent the ESLint `import/no-extraneous-dependencies` rule from flagging import statements for these packages.

**Key rules**:
- Add all `@wordpress/*` packages that are imported in source files but not bundled (i.e., provided by WordPress at runtime) to the `peerDependencies` object with version `"*"`.
- Do NOT add them to `devDependencies` — doing so would cause ESLint to treat them as local packages and potentially conflict with the `import/no-extraneous-dependencies` peer-vs-dev distinction.
- Packages that ARE bundled (e.g., `@wordpress/dataviews`, `@wordpress/icons`) belong in `dependencies` or `devDependencies` depending on whether they are production or build-only.

**Why**: ESLint's `import/no-extraneous-dependencies` rule raises an error for any import whose package is not listed in `dependencies`, `devDependencies`, or `peerDependencies`. WordPress globals are neither dependencies (not bundled) nor devDependencies (not test-only); `peerDependencies` is the semantically correct category.

**Evidence**: `package.json` `peerDependencies` block added in Feature 025 for `@wordpress/data`, `@wordpress/element`, `@wordpress/i18n`. ESLint reported 3× `import/no-extraneous-dependencies` errors before the fix; 0 after.

---

### PATTERN-JESTENV-WPSCRIPTS (Feature 025, 2026-06-03)

**Pattern**: Any Jest test that depends on browser APIs (`localStorage`, `sessionStorage`, `window`, `document`, etc.) must be run via `npx wp-scripts test-unit-js`, not plain `npx jest`. The `@wordpress/scripts` test runner uses `@wordpress/jest-preset-default` which sets `testEnvironment: 'jsdom'`.

**Key rules**:
- Use `npx wp-scripts test-unit-js` as the canonical test runner for all Jest tests in this project.
- Plain `npx jest` defaults to a Node environment where `localStorage` is `undefined`, causing `ReferenceError: localStorage is not defined` at module evaluation time.
- If a test file uses `jest.mock()` for `@wordpress/*` packages, those mocks must still cover all named exports the module under test imports — the jsdom environment does not change mock requirements.

**Why**: `@wordpress/jest-preset-default` configures jsdom and also sets up `@wordpress/jest-console` and other WP-specific test utilities. Running outside `wp-scripts` loses all of this setup.

**Evidence**: `tests/jest/abilities/column-prefs.test.js` — 8 tests fail with `ReferenceError: localStorage is not defined` under plain `npx jest`; all 8 pass under `npx wp-scripts test-unit-js`. Feature 025.

---

### 2026-06-06 — Add-on registration filters MUST fire at init P99, not early priority (PATTERN-ADDON-FILTER-LATE-INIT)

**Status**: Active

**Why this is durable**
When the plugin exposes a filter that add-ons use to register definitions (e.g., `acrossai_abilities_api_init`), the filter must be applied late in `init` (P99) — not early (P10–P20). Add-ons typically hook at the default `init` priority (10). If the plugin fires the collection filter at P20, any add-on that hooks at P21–P99 silently misses registration with no error.

**Decision**
Plugin-defined filters whose purpose is to collect definitions or registrations from add-ons MUST be fired at `init` priority 99 (or the highest feasible late priority). Applying the filter early forces add-on developers to know the plugin's internal priority and hook earlier — an undocumented contract violation. Late firing gives add-ons the full `init` window.

**Pattern**
```php
// In the Registry::collect() method, wired via Loader at init P99:
public function collect(): void {
    if ( ! is_null( self::$definitions ) ) return; // idempotent
    $raw = apply_filters( 'acrossai_abilities_api_init', array() );
    self::$definitions = self::validate_and_normalize( $raw );
}
// In Main.php define_public_hooks():
$keys_registry = AcrossAI_Keys_Registry::instance();
$this->loader->add_action( 'init', $keys_registry, 'collect', 99 );
```

**Tradeoffs**
- Gained: add-ons can hook at any `init` priority ≤ 98 without coordination.
- Made harder: nothing meaningful — the Registry's static cache makes subsequent calls idempotent regardless of when collection runs.
- Reconsider: if a downstream consumer needs definitions before `init P99` completes (it does not — `wp_abilities_api_init` fires after `init`).

**Future mistake prevented**
Do not pick an early `init` priority (P10–P20) for collection filters. A future developer writing a new add-on who hooks at default P10 would appear to work, then break for any add-on hooking at P21+, creating silent, hard-to-diagnose missed registrations.

**Evidence**
Feature 027: plan.md initially proposed `init P20`; corrected to P99 by Security Review finding SEC-004 / SC-027-04 (specs/027-keys-submenu/security-constraints.md).

### PATTERN-PROTECTED-SLUGS-JS-LOCALIZE

When a PHP-managed list needs to gate UI behavior in JSX, expose it from PHP via
the `window.acrossaiAbilitiesManager` inline script in `admin/Main.php` rather
than hardcoding it in JSX.

**Pattern**
```php
// admin/Main.php — inline script
'protected_slugs' => \AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Protected_Abilities::get_protected_slugs(),
```
```jsx
// AbilitiesList.jsx
const PROTECTED_SLUGS = window.acrossaiAbilitiesManager?.protected_slugs || [];
```

**Why this is durable**
`AcrossAI_Protected_Abilities::get_protected_slugs()` is the single source of
truth for protected slugs (DEC-PROTECTED-SLUGS-PATTERN). Hardcoding the slug list
in JSX would duplicate it and create drift when the PHP list changes (e.g. when
a new mcp-adapter system tool is added via the filter). Discovered in Feature 029
when the plan referenced a non-existent `PROTECTED_SLUGS` JS constant.

**Future mistake prevented**
Do not define a `PROTECTED_SLUGS` array literal in JSX. Always read from
`window.acrossaiAbilitiesManager?.protected_slugs`. Apply this pattern to any
future PHP-managed list that gates admin UI behavior.

**Where to look next**
`admin/Main.php` (inline script localization, Feature 029 addition),
`src/js/abilities/components/AbilitiesList.jsx` (`PROTECTED_SLUGS` constant),
`includes/Utilities/AcrossAI_Protected_Abilities.php` (PHP source of truth),
DEC-PROTECTED-SLUGS-PATTERN (PHP-side centralization decision).

---

### 2026-06-11 — PATTERN-VENDOR-ASSET-FAMILY-HANDLE

**Pattern**
When registering a vendor library's CSS/JS asset via `wp_register_style()` /
`wp_register_script()`, the handle MUST carry the consuming plugin's prefix —
NOT the vendor library's own name. The vendor's name is for filesystem paths
and Composer; the WordPress handle is in the global asset registry and must
satisfy Plugin Check's 4+ character unique-prefix rule.

For a **family of plugins** that all bundle the same vendor library via
Jetpack Autoloader (Jetpack picks the highest PHP version across the family but
does not manage asset URLs), all plugins MUST register the asset under the
**same family-level handle**. WordPress's `WP_Dependencies::add()` silently
returns false on duplicate handle registration, so first-to-register wins and
the CSS loads exactly once site-wide — no duplicate `<link>` tags, no
conflicts, no `wp_style_is()` guard required.

**Convention for the AcrossAI family**:
- Handle format: `acrossai-<vendor-package-name>` (e.g.
  `acrossai-wpb-access-control` for `wpboilerplate/wpb-access-control`)
- Carries the 8-char `acrossai-` prefix (Plugin Check satisfied)
- Vendor name is preserved in the suffix for debuggability (DOM inspection
  reveals which library shipped the CSS)
- Same handle in every AcrossAI add-on that bundles the same library

**Anti-patterns to avoid**:
- Registering with the vendor's bare name (`'wpb-access-control'`) — fails
  Plugin Check; also race-prone if another vendor coincidentally uses the
  same handle.
- Per-plugin-scoped handles (`'acrossai-abma-wpb-access-control'`,
  `'acrossai-cora-wpb-access-control'`) — loads the same CSS twice from
  different vendor/ paths; wasteful.
- Renaming the upstream Composer package to "fix" the prefix — doesn't fix
  the handle (which is what Plugin Check checks); breaks every other
  consumer of the package.

**Limitation worth knowing**:
First-loaded plugin wins the URL, so its `vendor/` copy of the CSS is what
gets served — even if Jetpack Autoloader selected a different plugin's
higher-version PHP. The cleanest long-term fix is for the vendor library
itself to expose an `Assets::register()` method called from the Jetpack-
Autoloader-selected copy, so PHP version and CSS version stay in sync.
Until then, the family-level handle is the right pragmatic answer.

**Evidence**
`admin/Main.php:179, 188` — handle renamed from `'wpb-access-control'` to
`'acrossai-wpb-access-control'`. Plugin Check finding "Looks like there is
an element not using common prefixes" resolved with two single-line edits;
36 acrossai-prefixed elements become 37.

---

## PATTERN-REQUIRED-FIELD-MULTI-LAYER-AUDIT

**Status**: Active (established Feature 034)

When auditing the claim "every field the form treats as required has matching
4xx-on-missing server-side enforcement", check **all three** WordPress validation
layers, not just one. A field enforced at any one layer is technically secure, but
the audit must verify which layer enforces it — a JS-hook attack surface (e.g.,
`acrossai_abilities.form.save_payload`) lets subscribers strip fields from the REST
payload before serialization, and the auditor must trace which server-side layer
catches that case.

**The three layers**:

1. **REST `args` schema** — `'required' => true`, `'sanitize_callback'`,
   `'validate_callback'`. Lives in the route-registration block of the relevant
   REST controller (e.g., `AcrossAI_Abilities_Write_Controller::register_routes()`).
2. **Sanitizer presence guards** — early-return WP_Error with explicit `missing_X`
   codes inside `sanitize_create_request()` / inside the controller's main handler
   (e.g., `AcrossAI_Abilities_Write_Controller::create_ability()` lines 196-204:
   `missing_label`, `missing_description`, `missing_category`).
3. **Validator** — deeper presence + format checks returning WP_Error 400 with
   codes like `invalid_slug` (e.g., `AcrossAI_Abilities_Validator::validate_ability()`
   lines 412-414).

**Audit procedure**:

For each field the JS form treats as required (find via `validateRequiredFields` or
the form's explicit required-attribute set):
1. Grep the REST controller's `register_routes()` for the field name — is it in the
   `args` schema with `'required' => true`?
2. Grep the sanitizer / controller handler for an early-return guard returning
   `missing_<field>` or equivalent 400.
3. Grep the validator for a guard returning `invalid_<field>` / `missing_<field>` /
   equivalent 400 with empty-check.

A field that passes only at layer (3) is still secure but produces less specific
error codes for the client. A field that passes only at layer (1) may be bypassed
by a JS-hook filter that strips it — the request reaches the server with the field
absent, layer (1) coerces silently, and you discover the gap only by tracing layers
(2) and (3).

**Reference**: Feature 034 SEC-001 audit (pre-implementation, `specs/034-.../security-constraints.md`)
verified `label`/`description`/`category` at layer (2). Feature 034 F2 finding
(post-implementation, via `/speckit-analyze`) discovered `slug_suffix` was NOT
enforced at layer (2) but WAS enforced at layer (3) — without the layer (3) check,
F2 would have falsely reported a vulnerability. Without layer (2) enforcement of
`slug_suffix`, a malicious `acrossai_abilities.form.save_payload` subscriber
stripping it would have produced an ambiguous `invalid_slug` error instead of a
clean 400-with-`missing_slug_suffix`. The audit is now a three-step procedure;
documented here so future security reviews don't repeat the layer-1-only
false-positive risk.

---

## PATTERN-JS-HOOK-CADENCE-SPEC

**Status**: Active (established Feature 034)

When exposing a JS action or filter via `@wordpress/hooks` to extension subscribers,
the spec entry MUST include **three** things, not two:

1. **Hook name** — canonical, versioned per the contract's deprecation cycle.
2. **Payload shape** — typed context object, every key listed and versioned.
3. **Reference-equality and firing-cadence semantics** — exactly when the hook
   fires, with three sub-questions answered:
   - Per-React-commit (selector reference changed) vs per-Redux-dispatch (store
     updated) vs per-event (DOM event)?
   - Is internal debouncing applied? (Default and recommended: "no — subscribers
     own their own debounce/throttle policy".)
   - Which value-equality function gates the fire (`Object.is`, `===`, deep-equal,
     hash)?

**Why item 3 matters**:

Item 3 is invisible by default. Spec authors usually write items 1 and 2 and assume
item 3 is "obvious"; it isn't. Different subscribers will subscribe assuming
different cadences, and only one of them will be right.

- Subscriber A assumes "fires per keystroke" and writes a 250ms debounce.
- Subscriber B assumes "fires per dispatched Redux action" and writes no debounce.
- Subscriber C assumes "fires once per save" and writes blocking logic.

Without an explicit contract, all three will ship and one (or all three) will be
wrong about which is right. Pin it in the spec; the implementation will then match
because it's now contractual — and reviewers can catch implementation drift early.

**Spec wording template**:

> The `{hook_name}` action fires on every React commit where the value returned by
> the Redux `{selector_name}` selector inside a `useEffect([value])` block has
> changed reference. Reference-equality uses `Object.is` (React's default). The
> plugin applies no internal debouncing — subscribers MUST own their own
> debounce/throttle policy. Subscribers MUST design against per-React-commit
> cadence, NOT per-Redux-dispatch cadence (which may be more or less frequent if
> intermediate states are batched out by re-render scheduling).

Apply equivalent wording for any cadence semantics (per-event, per-mount, etc.).
The key is naming the trigger, the equality semantics, and the subscriber's
obligation.

**Reference**: Feature 034 FR-005 (`acrossai_abilities.form.draft_changed`) shipped
with a partial cadence spec ("fires on every React commit where the form's draft
state reference has changed") that didn't pin which draft reference. F3 finding
caught this; spec amended to specify the Redux `getDraftAbility` selector + the
`useEffect([draft])` block. Implementation at
`src/js/abilities/components/AbilityForm.jsx:318` already matched the new wording —
the gap was in spec, not code. For any future JS extension hook this plugin or a
sibling plugin exposes, lead with the three-item template above.

---

### 2026-06-16 — PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION

**Pattern**
When a feature retires an Active decision: (1) mark the retired decision `**Status**: Superseded
by <new-DEC-NAME> (<date>)` and **keep the original entry intact** as historical context; (2) if
a *related canonical pattern* loses its current in-plugin consumer but remains conceptually valid
for future consumers, **annotate with a forward-pointer note** rather than marking Superseded —
e.g. *"Consumer removed in Feature N; pattern retained for the future X plugin which is expected
to be the next consumer."* Do not silently delete decision entries.

**Rationale**
Preserves the decision audit trail and prevents future Spec Kit synthesis from silently
regenerating retired features. Distinguishes "this decision no longer applies" (Supersede) from
"this pattern is correct but lives elsewhere now" (Annotate). Feature 035 applied both:
`DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` was Superseded by `DEC-PASS-AS-TOOL-REMOVED`;
`DEC-MCP-INJECT-REFLECTION-PATTERN` was annotated with a forward pointer to the future
`acrossai-mcp-manager` plugin.

**Evidence**
`docs/memory/DECISIONS.md` — `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` (Superseded) vs
`DEC-MCP-INJECT-REFLECTION-PATTERN` (annotated). Established by Feature 035.

---

### 2026-06-16 — PATTERN-HELPER-DELETION-GREP-FIRST

**Pattern**
When removing a private helper as part of a feature deletion, ALWAYS run
`grep -rEn '<helper_name>' includes/ src/ tests/` BEFORE the deletion task starts. If the grep
returns ANY hit outside the helper itself and the file you are deleting:
1. KEEP the helper in place.
2. Add a TODO note above the method enumerating the surviving caller(s).
3. If the helper carries a load-bearing partner-gate lesson (e.g. AC-rule fail-open paired with
   `WP_Ability::check_permissions()`), lift the lesson from BUGS.md into the helper's docblock so
   any future maintainer encountering the helper sees the constraint at the call site.

**Rationale**
A helper that "appears" to be deletion-target-private may have been adopted by an unrelated
surviving consumer between the original feature and the removal. Deleting unconditionally produces
a fatal class-load error or a silent permission bypass. Feature 035 caught
`user_has_ability_access()` had a second caller at `build_permission_callback()` line 386 — the
helper was preserved and the partner-gate guidance was lifted from
`BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` into the helper's docblock.

This pattern is narrower than `BUG-INVENTORY-GREP-MISS` (which covers general feature-removal
inventory grep) — it specifically targets helper deletion gates and the docblock-lift step.

**Evidence**
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` — `user_has_ability_access()`
preserved with updated docblock; `specs/035-remove-pass-as-tool/user_has_ability_access-callers.txt`
preflight grep result.

---

### 2026-06-23 — PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH

**Pattern**
`AcrossAI_Ability_Library_Registry::validate_and_normalize()` applies `wp_kses_post()` to
`category_label` and `slug_label`, but only **key-allowlist-filters** the `args` sub-array
(`array_intersect_key( $item['args'], array_flip( ALLOWED_ARGS_FIELDS ) )`). Every `args` *value* —
`description`, `label`, `sub_group_label`, `meta`, `input_schema`, `output_schema`, etc. — reaches
`window.acrossaiAbilityLibraryData.definitions[i].args.*` as the raw string (or array) the add-on
author registered. When you add a NEW consumer of any `args.*` value, you MUST choose one of:

1. **React text-node consumer** (`<p>{value}</p>`, `<span>{value}</span>`) — safe. JSX escapes by
   default. This is Feature 036's choice for `description` rendering (`LibraryCard.js` lines 178-181
   and 192-196).
2. **`dangerouslySetInnerHTML`, PHP server-rendered output, email template, or any non-JSX
   consumer** — MUST escape at the call site (`esc_html()`, `wp_kses_post()`, equivalent) OR add
   `wp_kses_post()` at the Registry boundary to harden it for all current and future consumers.

Never assume `args.*` values are pre-sanitized. The key allowlist proves a field is *permitted*; it
does NOT prove the contents are safe.

**Rationale**
The Library Registry intentionally trades server-side sanitization for forwards-compatible field
shapes (so `meta` / `input_schema` can stay structurally valid JSON, etc.). The consequence is that
XSS containment for `args.*` lives with the *first* consumer to ship. Today that contract is "React
text-node escape." Adding a second consumer that bypasses JSX would silently inherit XSS surface
that nobody owns. Feature 036's plan-level security review captured this as `SEC-001` and chose
defense option (c) — rely on FR-006 ("description MUST be rendered as plain text … the browser MUST
NOT interpret them as markup") as the forward guardrail. This entry elevates that guardrail from
feature-local to durable.

**Evidence**
`includes/Modules/Library/AcrossAI_Ability_Library_Registry.php:188` (the `array_intersect_key`
allowlist gate, no value sanitization); `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php:205-207`
(contrast: `category_label` / `slug_label` ARE `wp_kses_post`'d at the same depth);
`src/js/ability-library/components/LibraryCard.js:178-196` (current safe JSX text-node consumer);
`specs/036-library-page-full-width-and-descriptions/security-constraints.md` (`SEC-001` finding
that motivated this entry).

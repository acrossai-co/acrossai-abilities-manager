# Implementation Plan: Absorb Core Abilities Companion Into Manager

**Branch**: `046-absorb-core-abilities-into-manager` | **Date**: 2026-07-13 | **Spec**: [spec.md](./spec.md)  
**Input**: Feature specification from `/specs/046-absorb-core-abilities-into-manager/spec.md`  
**Memory synthesis**: [memory-synthesis.md](./memory-synthesis.md)  
**Working planning doc**: [docs/planning/046-absorb-core-abilities-into-manager.md](../../docs/planning/046-absorb-core-abilities-into-manager.md)

## Summary

Absorb every runtime PHP file from the `acrossai-core-abilities` companion
plugin into `acrossai-abilities-manager` under a new isolated tree
`includes/Abilities/`. Rebrand every occurrence of "Acrossai Core Abilities"
(labels, category slugs, class names, function names, internal identifiers) to
"Acrossai Abilities Manager" — a known-breaking change for any external caller
that referenced the legacy `acrossai-core-abilities-<domain>` category slugs.
Migrate the companion's option keys at activation. Merge the Core settings
field into the existing Abilities settings tab (no new `?tab=core` URL); fold
the two prior uninstall-opt-in checkboxes into the manager's single existing
opt-in. Rewire boot flow so **all 193 registered classes** (17 `Category_Registrar`
+ 176 ability classes; 8 additional helper classes (Formatters, Routes,
Moderation) are moved but not directly registered) register through a single
new orchestrator
(`AcrossAI_Core_Abilities_Bootstrap`) whose actions are wired from `Main.php`
via the Loader — preserving the Constitution's Boot Flow Rule.

## Technical Context

**Language/Version**: PHP 8.1+ (per Constitution §II, v1.4.6)  
**Primary Dependencies**: WordPress 6.9+ (Abilities API — `wp_register_ability`, `wp_register_ability_category`); Composer PSR-4 + Jetpack Autoloader (existing); `acrossai-co/main-menu` 0.0.14 (existing shared settings host)  
**Storage**: WordPress `wp_options` for two migrated keys (`acrossai_abilities_manager_extra_mimes` and the manager's existing `acrossai_abilities_uninstall_delete_data`). No new tables.  
**Testing**: PHPUnit 10 with the manager's existing `tests/phpunit/` bootstrap; no Jest changes (no JS/SCSS moved)  
**Target Platform**: WordPress admin + REST/CLI runtime; multisite-compatible  
**Project Type**: WordPress plugin (single project)  
**Performance Goals**: Boot-time impact of adding 176 ability class instantiations on `plugins_loaded @ P20` must not add noticeable admin-page latency; the source plugin already imposed this same cost, so the migration is boot-time neutral relative to today's two-plugin install.  
**Constraints**: PHPStan level 8 zero errors, PHPCS WPCS strict zero errors, Plugin Check clean on production surface, `composer test` green.  
**Scale/Scope**: 210+ PHP files moved (17 category folders × avg 12 files + Utilities set + 1 admin partial + 1 new Bootstrap class), 17 category slug renames, 1 spec-driven option-key rename, 4 constitutional touch-points to reconcile (see Constitution Check).

## Constitution Check

**Constitution version**: 1.4.8 (2026-07-01).

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Gate results

| Principle / Rule | Status | Notes |
|---|---|---|
| §I Modular Architecture — module enumeration | ⚠ **Deviation** | `includes/Abilities/` is a NEW tier at includes/-level (not under `Modules/`). Constitution §I enumerates 5 module areas under `Modules/`; the absorbed code doesn't fit any of them. See Complexity Tracking. |
| §I Modular Architecture — "no code duplication between modules" | ✔ Gated | The absorbed `Utilities/` nests under `includes/Abilities/Utilities/` — deliberately isolated from `includes/Utilities/` (per the user's spec direction, avoiding name collision). Post-migration DRY audit added as Phase 1 task. |
| §II WordPress Standards — PHPCS strict / PHPStan L8 / Plugin Check | ✔ Gated | Every moved file goes through the four bulk rewrites (namespace, `use`, text-domain, plugin constants) and each rewrite must land the file passing all three quality gates. Task list will chunk this per category folder. |
| §II WordPress Standards — SQL identifiers with `%i` | ✔ Not applicable | No new SQL / no new tables in this feature. The activation-time option migration uses WP option APIs only. |
| §II WordPress Standards — forbidden functions | ✔ Gated | Absorbed code will be audited before merge: `grep -RnE '\beval\(|extract\(|shell_exec\(|exec\(' includes/Abilities` must return zero hits. |
| §III User-Centric Design (NON-NEGOTIABLE) — DataForm/DataViews | ✔ Deviation-already-accepted | The absorbed extra-MIME-types field renders through the WP Settings API into the existing Abilities tab. Covered by `DEC-SETTINGS-API-DEVIATION` (≤5 scalar fields). |
| §IV Security First (NON-NEGOTIABLE) — sanitize / escape / nonce / capability | ✔ Gated | The moved `Core_Settings_Menu` already carries its sanitize/escape hooks; the rename must not drop them. Nonce + `manage_options` cap already enforced by the Settings API host. |
| §V Extensibility Without Core Modification | ✔ Not applicable | No integration hooks or filter surfaces are added or removed. |
| §VI Reusability & DRY | ⚠ **Audit required** | The absorbed `Utilities/` set (e.g., `Cron_Helpers`, `Plugin_Helpers`, `Mime_Types_Store`) may overlap semantically with the manager's existing `includes/Utilities/` set. Phase 1 adds a DRY audit task; overlap resolution deferred to a follow-up spec, not this feature. |
| §VI — `@wordpress/*` tier | ✔ Not applicable | No JS/SCSS moved. |
| §VII Definition of Done | ✔ Enforced | Existing composer/npm scripts remain the gate. Adds two new grep-based gates: "no `Acrossai_Core_Abilities` substring" and "no `acrossai-core-abilities-` category slug" in the moved tree. |
| **Boot Flow Rule** — Only `Main.php::define_admin_hooks/define_public_hooks` call the Loader | ✔ **Compliant — false-conflict retraction** | Spot-check on 2026-07-13 (post-copy) confirmed the source pattern is **not** what the plan originally assumed: `Category_Registrar` classes ship with empty constructors + a synchronous `register()` method; ability classes extend the manager's own `Ability_Definition` base and have no constructors of their own. All hook registration is already channeled through the companion Main.php via `$this->loader->add_action(...)` calls; that responsibility moves to the new `AcrossAI_Core_Abilities_Bootstrap` which is wired from the manager's `Main.php::define_public_hooks()` via the Loader. No 218-class refactor needed. See violation-detection.md V-01 retraction. |
| **Boot Flow Rule** — Singleton `instance()` pattern | ⚠ **Audit required** | The absorbed singleton classes may use `$_instance` (companion convention); the manager uses `$instance` post-`DEC-SINGLETON-PSR2-PROPERTY`. Bulk-rename included in the migration rewrites. |
| **Admin Partials Rule** — admin classes live under `admin/Partials/` | ✔ Gated | Moved `Core_Settings_Menu.php` lands at `admin/Partials/`, namespace `AcrossAI_Abilities_Manager\Admin\Partials`. Matches rule literally. |
| **REST Controller Pattern** | ✔ Not applicable | No REST controllers moved (companion plugin owns none). |
| **Integration Resilience** — optional integrations degrade gracefully | ✔ Gated | Absorbed helpers (JetEngine, Multilang) already carry availability checks per the source plugin; migration preserves them verbatim. |

### Gate outcome

Three deviations declared, all documented in Complexity Tracking below with
justification. The Boot Flow Rule "HARD conflict" originally identified in
memory-synthesis.md was **retracted** after 2026-07-13 spot-check: the source
never carried constructor `add_action` calls on the 193 classes. All hook
registration already channels through the companion Main.php's Loader calls;
that responsibility transfers cleanly to the new Bootstrap wired from
`Main.php::define_public_hooks()`. No 193-class refactor is needed.
No blocking violations remain.

## Project Structure

### Documentation (this feature)

```text
specs/046-absorb-core-abilities-into-manager/
├── spec.md                    # Feature specification (complete)
├── plan.md                    # This file
├── memory-synthesis.md        # Retrieved constraints (already written)
├── security-constraints.md    # Phase 0 output (to be written next)
├── tasks.md                   # Phase 2 output (created by /speckit-tasks)
└── checklists/
    └── requirements.md        # Spec quality checklist (complete)
```

### Source Code (repository root)

The absorbed tree lives inside the existing manager plugin. Only new/modified
paths are listed; existing manager code is not enumerated.

```text
wp-content/plugins/acrossai-abilities-manager/
├── includes/
│   ├── Main.php                                     # MODIFIED: wire Bootstrap + Core_Settings_Menu
│   ├── AcrossAI_Activator.php                       # MODIFIED: run option-key migration
│   ├── Utilities/                                   # UNCHANGED (manager's own)
│   ├── Modules/                                     # UNCHANGED (manager's Abilities/Library modules)
│   └── Abilities/                                   # NEW TIER (Constitutional deviation, see below)
│       ├── AcrossAI_Core_Abilities_Bootstrap.php    # NEW: singleton orchestrator, sole hook-adder
│       ├── Utilities/                               # from companion /includes/Utilities/
│       │   ├── Block_Info.php, Cron_Helpers.php, File_Mods_Guard.php,
│       │   │   Jet_Engine_Helpers.php, Mime_Types_Store.php,
│       │   │   Multilang_Helpers.php, Plugin_Helpers.php,
│       │   │   Theme_Helpers.php, User_Helpers.php
│       │   ├── Block_Style_Variations/  Global_Styles/  Pattern/
│       │   ├── Template/                Template_Part/
│       ├── Block/          (30 files)   Cache/         (4)    Comments/    (13)
│       ├── Content/        (28)         Cron/          (16)   Database/    (10)
│       ├── FileManager/    (9)          Fonts/         (9)    Media/       (11)
│       ├── Menus/          (12)         Options/       (6)    Plugins/     (9)
│       ├── Settings/       (12)         SiteHealth/    (3)    Taxonomies/  (12)
│       ├── Themes/         (8)          Users/         (9)
├── admin/
│   └── Partials/
│       └── Core_Settings_Menu.php                   # MOVED from companion Includes/Admin/Partials/
├── uninstall.php                                    # MODIFIED: delete migrated MIME-types option under existing gate
└── composer.json                                    # UNCHANGED (existing PSR-4 covers the new tree)
```

**Structure Decision**: `includes/Abilities/` sits as a new tier alongside
`includes/Modules/` and `includes/Utilities/`. This is a **documented
deviation** from the Constitution's §I Directory Layout — see Complexity
Tracking. Rationale: the absorbed code is not one module in the Constitution's
sense (it's 17 capability domains); placing it under `Modules/` would collide
naming-wise with the existing `Modules/Abilities/` (the manager's own
override / registry module).

## Phase 0 — Research artefacts

Working reference: [docs/planning/046-absorb-core-abilities-into-manager.md](../../docs/planning/046-absorb-core-abilities-into-manager.md)
already carries the deep working plan (source inventory, bulk-rewrite rules,
autoload notes, execution order, verification checklist). Phase 0 items:

- **Source-plugin inventory (frozen 2026-07-13)** — 17 category folders totalling
  201 PHP files (17 Category_Registrar + 176 ability classes + 8 helper classes
  such as Formatters, Routes, Moderation) in
  `wp-content/plugins/acrossai-core-abilities/includes/Abilities/`;
  9 top-level Utilities + 5 Utilities sub-folders; 1 admin partial
  (`Core_Settings_Menu.php`).
- **Companion database tables** — none. Only options
  (`acrossai_core_abilities_extra_mimes`, `acrossai_core_abilities_uninstall_delete_data`).
- **Companion JS/SCSS assets** — none.
- **Companion boot lifecycle** — `Category_Registrar::instance()->register()`
  on `wp_abilities_api_categories_init @ P10`; ability class constructors
  self-register on `wp_abilities_api_init` via `add_action` from
  `plugins_loaded @ P20` instantiations.
- **Path A vs Path B for AC-HOOKS-MAIN** — **retracted / false alarm**.
  2026-07-13 spot-check confirmed the 193 classes never carried constructor
  `add_action` calls. The apparent conflict was a misreading of the source.
  No refactor and no new deviation are required. Category_Registrar shape:
  empty constructor + synchronous `register()` method. Ability class shape:
  extends `Ability_Definition`, no constructor, no `add_action`. See
  violation-detection.md V-01 retraction.
- **Category label content is preserved verbatim** except for the mandatory
  "Acrossai Core Abilities" → "Acrossai Abilities Manager" text rewrite
  (per spec Q2 revised).

## Phase 1 — Design contracts

### 1. Rewrite matrix (applied to every moved PHP file)

Executed as a repeatable per-file transform. Order matters — the text-domain
replace runs before any partial-match substitution so `acrossai-abilities-manager`
never gets accidentally re-processed.

| Order | Transform | Scope |
|---|---|---|
| 1 | `namespace Acrossai_Core_Abilities\Includes\Abilities\<Cat>;` → `namespace AcrossAI_Abilities_Manager\Includes\Abilities\<Cat>;` | 201 category files |
| 1 | `namespace Acrossai_Core_Abilities\Includes\Utilities[\Sub];` → `namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities[\Sub];` | 9 + 15 utilities |
| 1 | `namespace Acrossai_Core_Abilities\Includes\Admin\Partials;` → `namespace AcrossAI_Abilities_Manager\Admin\Partials;` | 1 admin partial |
| 2 | `use Acrossai_Core_Abilities\Includes\…` → the new namespace | all imports |
| 3 | `'acrossai-core-abilities'` (i18n text-domain arg) → `'acrossai-abilities-manager'` | every `__()`/`_e()`/`esc_html__()`/`esc_attr__()`/`_x()`/`_n()`/`_nx()` |
| 4 | `ACROSSAI_CORE_ABILITIES_PLUGIN_*` → `ACROSSAI_ABILITIES_MANAGER_PLUGIN_*` (URL, PATH, BASENAME, FILE, NAME, NAME_SLUG, VERSION) | any constants read at runtime |
| 5 | Category label / description **text content** `"Acrossai Core Abilities"` → `"Acrossai Abilities Manager"` | Category_Registrar files + any ability class that carries the wording |
| 6 | Category slug `acrossai-core-abilities-<domain>` → `acrossai-abilities-manager-<domain>` | 17 Category_Registrar files, uniformly |
| 7 | Class / function / method / constant / variable names containing "Core Abilities" wording → "Abilities Manager" wording | project-wide across the moved tree |
| 8 | Singleton property `$_instance` → `$instance` | per DEC-SINGLETON-PSR2-PROPERTY, all moved singletons |
| 9 | `@package` docblock header rewritten to `@package AcrossAI_Abilities_Manager`, `@subpackage <path>`, `@since 0.1.0` | every moved file per AC-FILE-HEADER-PATTERN |

Bulk-rewrite tooling: use `sed`/`perl` per-file with a small shell wrapper so
each transform writes the file synchronously — following
`BUG-PYTHON-STRREPLACE-PARTIAL-WRITE`. Every PHP file is opened with `phpcbf`
after all rewrites to normalize whitespace; then PHPCS+PHPStan run in a
chunked mode (one category folder at a time) to isolate failures.

### 2. Boot-flow wiring — the single Bootstrap orchestrator (no class refactor)

**Retracted**: no 218-class refactor. The 2026-07-13 spot-check confirmed
the classes were never in the shape my earlier plan assumed. Actual pattern:

- Every `Category_Registrar` (17 files) already ships with an **empty
  constructor** and a synchronous `register()` method. The rewrite matrix
  normalized `$_instance` → `$instance` and rebranded label + slug; nothing
  else to touch.
- Every ability class (176 files) extends `Ability_Definition` (in the
  manager's `includes/Modules/Library/`). Ability classes carry no
  constructor and no `add_action`. Instantiating them triggers
  `Ability_Definition::__construct()` which calls
  `add_filter('acrossai_abilities_api_init', 'push_definition')` inherited
  by every subclass. **Instantiation is the trigger** — no per-class hook
  wiring exists to strip.

The new `AcrossAI_Core_Abilities_Bootstrap` therefore just mimics the
companion Main.php's registration flow, driven from the manager's Loader.
It also carries three items my earlier plan missed:
`Cron_Helpers::register_filter()`, the `Media\Upload_Media::CHUNK_SWEEP_HOOK`
action, and `Media\Upload_Media::register_sweep_cron()`.

```php
// includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php
namespace AcrossAI_Abilities_Manager\Includes\Abilities;

use AcrossAI_Abilities_Manager\Includes\AcrossAI_Loader;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Cron_Helpers;

class AcrossAI_Core_Abilities_Bootstrap {
    protected static $instance = null;
    public static function instance(): self { /* PSR2 pattern */ }
    private function __construct() {}

    // Called from Main.php::define_public_hooks() with the manager's Loader.
    // Adds 17 loader.add_action(wp_abilities_api_categories_init, …) calls,
    // one per category. Matches the companion Main.php's original wiring.
    public function register_category_callbacks( AcrossAI_Loader $loader ): void {
        $loader->add_action( 'wp_abilities_api_categories_init', Plugins\Category_Registrar::instance(), 'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Themes\Category_Registrar::instance(), 'register' );
        // ...17 lines total, in the companion's original order
    }

    // Wired from Main.php::define_public_hooks() to plugins_loaded @ P20.
    // Instantiating an ability class triggers Ability_Definition::__construct()
    // which hooks the acrossai_abilities_api_init filter — the Library
    // Processor then makes the actual wp_register_ability() calls.
    public function register_abilities(): void {
        if ( ! class_exists( '\AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition', false ) ) {
            return;
        }
        new Plugins\Plugin_Activate();
        new Plugins\Plugin_Deactivate();
        // ...176 lines total, in the companion's original order (companion Main.php lines ~330-505)

        // Extras the companion Main.php also ran:
        Cron_Helpers::register_filter();
        add_action( Media\Upload_Media::CHUNK_SWEEP_HOOK, array( Media\Upload_Media::class, 'sweep_chunk_sessions' ) );
        Media\Upload_Media::register_sweep_cron();
    }
}
```

Main.php wiring (variable-first per Boot Flow Rule):

```php
// includes/Main.php::define_public_hooks()
$core_abilities_bootstrap = \AcrossAI_Abilities_Manager\Includes\Abilities\AcrossAI_Core_Abilities_Bootstrap::instance();
$core_abilities_bootstrap->register_category_callbacks( $this->loader );          // adds 17 loader hooks
$this->loader->add_action( 'plugins_loaded', $core_abilities_bootstrap, 'register_abilities', 20 );

// includes/Main.php::define_admin_hooks()
$core_settings_menu = \AcrossAI_Abilities_Manager\Admin\Partials\Core_Settings_Menu::instance();
$this->loader->add_action( 'admin_init', $core_settings_menu, 'register_settings' );
// No acrossai_settings_tabs filter — extra-MIME-types field renders inside
// the existing Abilities tab (spec Q3).
```

Rationale for `register_category_callbacks( $loader )` taking the Loader
instead of hooking on its own hook: the 17 category registrations need to
appear as individual `$this->loader->add_action(...)` calls to honor
AC-HOOKS-MAIN literally. Passing the Loader keeps the 17-line block
co-located with its category peers instead of bloating `Main.php`.
Equivalent alternative: inline all 17 `$this->loader->add_action(...)`
calls directly in `define_public_hooks()` — same compliance, more verbose.

### 3. Core settings — merge into Abilities tab

`Core_Settings_Menu` after the move:
- Namespace: `AcrossAI_Abilities_Manager\Admin\Partials`
- Keeps its Settings API `register_settings()` method but **only registers the
  extra-MIME-types field** (the uninstall opt-in is dropped — the manager's
  existing single opt-in governs both). Field is added to the manager's
  existing Abilities-tab section (not a new section) via
  `add_settings_field()` on the manager's shared `option_group`.
- Drops `register_tab()` (the Abilities tab already exists — no new tab).
- Continues to sanitize the MIME-types textarea input with the same rules the
  companion used.
- Reads/writes `acrossai_abilities_manager_extra_mimes` (the post-migration
  key).

### 4. Activation-time option migration

Executed inside `AcrossAI_Activator::activate()` at existing activation
priority. Idempotent; safe on repeated activation.

```php
// Pseudocode — real implementation lives in AcrossAI_Activator.
$legacy_mimes = get_option( 'acrossai_core_abilities_extra_mimes', null );
if ( $legacy_mimes !== null ) {
    // Only copy if the manager-branded key is not already set — preserves manual edits.
    if ( get_option( 'acrossai_abilities_manager_extra_mimes', null ) === null ) {
        update_option( 'acrossai_abilities_manager_extra_mimes', $legacy_mimes );
    }
    delete_option( 'acrossai_core_abilities_extra_mimes' );
}

$legacy_delete_gate = (bool) get_option( 'acrossai_core_abilities_uninstall_delete_data', false );
if ( $legacy_delete_gate ) {
    // OR into the manager's existing single opt-in — never demote a true to false.
    if ( ! (bool) get_option( 'acrossai_abilities_uninstall_delete_data', false ) ) {
        update_option( 'acrossai_abilities_uninstall_delete_data', 1 );
    }
}
// Always sweep the legacy key so it doesn't reappear on next activation.
delete_option( 'acrossai_core_abilities_uninstall_delete_data' );
```

### 5. Uninstall path

`uninstall.php` gains one additional line inside the existing
`$acrossai_delete_data` gate (per `PATTERN-UNINSTALL-DATA-GATE` and to avoid
`BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`):

```php
if ( $acrossai_delete_data ) {
    // ...existing manager cleanup unchanged...
    delete_option( 'acrossai_abilities_manager_extra_mimes' );
}
```

The legacy companion keys are already swept at activation, so they don't need
uninstall handling.

### 6. Data model

Trivial. Two options changed:

| Key | Type | Where set | Where read |
|---|---|---|---|
| `acrossai_abilities_manager_extra_mimes` | string (textarea) | Abilities settings tab | `Mime_Types_Store` (moved) |
| `acrossai_abilities_uninstall_delete_data` | bool | Abilities settings tab (unchanged); ORed at activation | `uninstall.php` |

No new custom tables. No BerlinDB schema changes.

### 7. Quality gates & audits (post-migration)

Automated audits added by this feature:

1. `grep -R "Acrossai_Core_Abilities" includes/Abilities admin/Partials/Core_Settings_Menu.php` → 0 matches
2. `grep -R "'acrossai-core-abilities'" includes/Abilities admin/Partials/Core_Settings_Menu.php` → 0 matches
3. `grep -R "acrossai-core-abilities-" includes/Abilities admin/Partials/Core_Settings_Menu.php` → 0 matches (category slug rebrand)
4. `grep -R "ACROSSAI_CORE_ABILITIES_" includes/Abilities admin/Partials/Core_Settings_Menu.php` → 0 matches
5. `grep -RnE '\beval\(|extract\(|shell_exec\(|passthru\(|exec\(|popen\(|proc_open\(|system\(' includes/Abilities` → 0 matches (Constitution §II)
6. Per `PATTERN-GREP-AUDIT-VS-MANDATED-STRINGS`, audits exclude `specs/` (where the Clarifications block MUST name the legacy slug) — audit patterns are scoped to `includes/Abilities` and the moved admin partial.
7. `composer phpstan`, `composer phpcs`, `composer test` — all zero errors.
8. `npm run validate-packages` still green (no changes expected, no JS moved).
9. Optional: Plugin Check on the production plugin surface (`.github/workflows/plugin-check.yml`) — should stay green.

### 8. Manual verification (Phase 1 quickstart)

1. Fresh WP install with only the manager active (no companion on disk).
2. Activate the manager. Confirm no PHP fatals.
3. Enumerate categories via WP-CLI or REST: expect 17 categories with slugs `acrossai-abilities-manager-<domain>` and **zero** legacy `acrossai-core-abilities-<domain>` slugs.
4. Open `?page=acrossai-settings&tab=abilities` in wp-admin. Expect: manager's own fields + the extra-MIME-types field. Expect **exactly one** "Delete data on uninstall" checkbox. Expect NO `?tab=core` URL to be reachable.
5. Enter a MIME type, save, reload, confirm persistence.
6. Toggle the master uninstall opt-in, confirm it saves.
7. Upgrade path: on a site where the legacy `acrossai_core_abilities_extra_mimes` and `acrossai_core_abilities_uninstall_delete_data` options pre-existed (simulate by seeding them in the DB before activation), activate the manager, confirm the values migrate into `acrossai_abilities_manager_extra_mimes` and OR into the manager's existing opt-in; confirm the legacy option rows are deleted.

## Complexity Tracking

Two Constitutional deviations are declared for this feature. Both are
documented here per §Governance ("appears to violate a principle MUST either
be refactored or include documented justification in the feature plan").

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| **§I Directory Layout — new `includes/Abilities/` tier at includes/-level, not under `Modules/`** | The absorbed code is 17 heterogeneous capability domains (Block, Cache, Comments, Content, …), not a single self-contained feature module. §I's "one module per feature area" doesn't apply. Placing it under `includes/Modules/` would need a name that doesn't collide with the manager's existing `Modules/Abilities/` (registry-override module); the natural name "Abilities" is taken. The user's spec explicitly directed the new folder to `includes/Abilities/` (isolated) to keep the absorbed code visually separate from the manager's own modules. | Placing under `Modules/CoreAbilities/` would satisfy §I but (a) carries the "Core Abilities" wording the user's Q2 revised answer explicitly retires and (b) reads as another feature module in a directory list that already has 5 semantically distinct ones. `Modules/AbsorbedAbilities/` reads as a description of the migration event, not the code's purpose. The cleanest expression is a new tier next to `Modules/`, with a follow-up Constitution PATCH bump (1.4.8 → 1.4.9) to enumerate `includes/Abilities/` in the Directory Layout. That amendment is deferred to a companion Governance-only spec after this feature ships. |
| **§I Modular Architecture — potential DRY violation between the absorbed `includes/Abilities/Utilities/` set and the manager's existing `includes/Utilities/` set** | The two utility sets serve different concerns: the manager's utilities operate on ability-registry / override rows; the absorbed set operates on WordPress content (blocks, patterns, options, cron, plugins). No 1:1 overlap is expected. Nesting the absorbed set under `Abilities/Utilities/` keeps the boundary explicit — and matches the user's spec direction ("Do not merge into existing includes/Utilities/"). | Merging into `includes/Utilities/` would either force renames on the manager's helpers (breaking existing callers) or create ambiguous flat namespace. Deferred: a Phase-N follow-up spec can audit whether any absorbed helper (e.g., a generic `Multilang_Helpers`) genuinely duplicates a manager helper and consolidate; not in scope for this feature. |
| **§VII Definition of Done — PHPCS zero errors NOT met on the absorbed tree** | The migration surfaces 766 residual PHPCS errors under `includes/Abilities/` after the T007 bulk rewrite matrix + T008 phpcbf pass. Most errors are docblock-quality items (`Squiz.Commenting.FunctionComment.MissingParamComment` × 303, `MissingParamTag` × 252, `MissingShort` × 82) plus 43 `file_system_operations_is_writable` and 21 short-ternary hits. Manager-owned code (`includes/Modules/*`, `includes/Main.php`, `admin/Partials/`) remains PHPCS-clean. Tracked as follow-up spec `specs/050-046-phpcs-doc-baseline/` with an explicit remediation script plan (extend `scripts/046-add-fn-docblocks.pl` with `@param` descriptions + column-aligned type/var spacing, then hand-fix the ~74 code-level errors). | Blocking merge on all 766 errors before shipping the migration MVP is disproportionate — the absorbed tree behaves correctly (PHPStan L8 clean, 117/117 PHPUnit tests pass, forbidden-function grep clean). Fixing the baseline in-line with the migration would require another ~30 minutes of iterative scripting, and the errors are non-blocking under the "eliminate the repo-wide PHPCS baseline first" clause of Constitution §II (which permits a documented remediation plan). Spec 050 IS that documented plan. |

### Path A vs Path B for AC-HOOKS-MAIN (RETRACTED)

The Boot Flow Rule conflict identified in `memory-synthesis.md` and this
plan's earlier revision was **retracted** on 2026-07-13 after a source
spot-check. Neither Path A (refactor) nor Path B (new deviation) is
required — the conflict did not exist. The 193 absorbed classes never
carried constructor `add_action` calls. Category_Registrar classes
already have empty constructors and a synchronous `register()` method,
and ability classes never had a constructor of their own. All hook
registration was already channeled through the companion Main.php's
Loader calls, and that responsibility transfers cleanly to the new
Bootstrap wired from the manager's `Main.php::define_public_hooks()`. See
violation-detection.md V-01 retraction and the revised §Phase 1 §2 above
for the actual Bootstrap shape.

## Phase 2 preview — task-breakdown themes (created by `/speckit-tasks`)

Not part of this file. Expected task themes:

1. Copy the 17 category folders + Utilities + admin partial into their target paths (git-tracked).
2. Apply the 9-order rewrite matrix per folder; run PHPCBF + PHPStan + PHPCS after each folder.
3. Refactor 218 classes to remove constructor `add_action`; expose synchronous `register()`.
4. Write `AcrossAI_Core_Abilities_Bootstrap.php`; wire in `Main.php::define_public_hooks()`.
5. Wire moved `Core_Settings_Menu` in `Main.php::define_admin_hooks()`.
6. Add activation-time option-migration block in `AcrossAI_Activator`.
7. Add uninstall-gate line in `uninstall.php`.
8. Run `composer dump-autoload`, then the full quality-gate sweep.
9. Verify via the Phase-1 quickstart against a wp-env fixture.

## Next actions

1. Write `security-constraints.md` (Phase 0 output for the governance chain) — see `/speckit-security-review-plan` next.
2. Regenerate `docs/planning/046-absorb-core-abilities-into-manager.md` to reflect the Path A refactor decision (currently written for a less refactor-heavy shape).
3. Run `/speckit-tasks` to generate `tasks.md`.
4. Run `/speckit-architecture-guard-violation-detection` (below).

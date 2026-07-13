# Planning: Absorb `acrossai-core-abilities` Into The Manager (Feature 046)

Fold all runtime code from the companion plugin `acrossai-core-abilities` into
`acrossai-abilities-manager` under a single isolated tree, without interleaving
it with existing manager code.

The companion ships **201 ability classes** across **17 categories** (Block,
Cache, Comments, Content, Cron, Database, FileManager, Fonts, Media, Menus,
Options, Plugins, Settings, SiteHealth, Taxonomies, Themes, Users), a shared
`Utilities/` helper set, and **one admin Settings tab** (`Core_Settings_Menu`:
extra MIME types + uninstall opt-in). This feature migrates that runtime
surface into the manager so the two-plugin footprint collapses to one
deliverable.

The companion plugin `acrossai-core-abilities` is assumed **not installed** on
target sites at the moment the migrated manager is activated (spec §Clarifications
Q1). This feature does not modify or delete the companion plugin folder in
this repository; removal from any site that still has it is handled
operationally, outside this feature.

**Authoritative sources (in read order)**:
- `specs/046-absorb-core-abilities-into-manager/spec.md` — user-facing spec
- `specs/046-absorb-core-abilities-into-manager/plan.md` — governed plan
  (Constitution + memory-synthesis compliance)
- `specs/046-absorb-core-abilities-into-manager/security-constraints.md`
- `specs/046-absorb-core-abilities-into-manager/violation-detection.md`

This document is the internal working plan and mirrors what plan.md decided
at a lower level of detail. If plan.md and this doc disagree, plan.md wins.

---

## Spec-kit Workflow (historical)

The four turns already run against this branch:

- `/speckit-git-feature "046-absorb-core-abilities-into-manager"` — branch created
- `/speckit-specify …` — spec drafted (see spec.md)
- `/speckit-clarify` — 4 questions answered; spec Clarifications block records the answers
- `/speckit-memory-md-plan-with-memory` — memory-synthesis.md produced
- `/speckit-architecture-guard-governed-plan` — plan.md + security-constraints.md + violation-detection.md produced

Next: `/speckit-tasks`, `/speckit-memory-md-capture` (four candidate captures listed in the governed plan's summary).

---

## Scope

### In scope

- Copy every PHP file from the companion's `includes/Abilities/` (17 categories, 201 files) into the manager's new `includes/Abilities/` tree.
- Copy the companion's `includes/Utilities/` into `includes/Abilities/Utilities/` (nested under the new tree, isolated from the manager's own `includes/Utilities/`).
- Copy the companion's `includes/Admin/Partials/Core_Settings_Menu.php` into the manager's `admin/Partials/`.
- Apply the bulk rewrites (see CHANGE-5).
- Refactor the 218 absorbed classes so hooks are added ONLY from the new Bootstrap orchestrator (Path A — resolves the Boot Flow Rule conflict, see plan.md V-01).
- Add the new `AcrossAI_Core_Abilities_Bootstrap` orchestrator; wire it from `Main.php::define_public_hooks()`.
- Wire moved `Core_Settings_Menu` from `Main.php::define_admin_hooks()`. Its extra-MIME-types field merges into the existing **Abilities** tab; no new `?tab=core` URL is exposed.
- Add activation-time option-key migration in `AcrossAI_Activator`.
- Add uninstall-gate line in `uninstall.php` for the migrated MIME-types option.
- Run `composer dump-autoload`.

### Out of scope

- Companion bootstrap files (`acrossai-core-abilities.php`, `includes/Main.php`, `Loader.php`, `Activator.php`, `Deactivator.php`, `I18n.php`, `uninstall.php`) — manager owns boot, autoload, i18n.
- Companion `composer.json` / `composer.lock` / `package.json` / `README*` / `LICENSE*` / `.pot` / `docs/` / `specs/` / config files.
- Companion build/asset pipeline — no JS/CSS exists to move.
- Modifying or deleting the companion plugin folder in this repository — separate operational task.
- Regenerating `languages/acrossai-abilities-manager.pot` — follow-up spec.
- DRY consolidation between `includes/Abilities/Utilities/` and the manager's `includes/Utilities/` — follow-up spec (plan.md V-03).
- Constitution PATCH bump enumerating `includes/Abilities/` under §I Directory Layout — follow-up governance spec (plan.md V-02).

---

## Background — Source Inventory

Verified against the companion plugin on 2026-07-13.

### Companion `includes/Abilities/` categories (17 folders, 201 files)

| Folder | File count |
|--------|-----------|
| `Block/`             | 30 |
| `Cache/`             | 4  |
| `Comments/`          | 13 |
| `Content/`           | 28 |
| `Cron/`              | 16 |
| `Database/`          | 10 |
| `FileManager/`       | 9  |
| `Fonts/`             | 9  |
| `Media/`             | 11 |
| `Menus/`             | 12 |
| `Options/`           | 6  |
| `Plugins/`           | 9  |
| `Settings/`          | 12 |
| `SiteHealth/`        | 3  |
| `Taxonomies/`        | 12 |
| `Themes/`            | 8  |
| `Users/`             | 9  |

### Companion `includes/Utilities/`

- Top-level: `Block_Info.php`, `Cron_Helpers.php`, `File_Mods_Guard.php`, `Jet_Engine_Helpers.php`, `Mime_Types_Store.php`, `Multilang_Helpers.php`, `Plugin_Helpers.php`, `Theme_Helpers.php`, `User_Helpers.php`.
- Sub-folders: `Block_Style_Variations/`, `Global_Styles/`, `Pattern/`, `Template/`, `Template_Part/`.

### Companion `includes/Admin/Partials/`

- `Core_Settings_Menu.php` (singleton) — owns options `acrossai_core_abilities_extra_mimes` and `acrossai_core_abilities_uninstall_delete_data`.

### Companion registration flow (source-of-truth for the refactor)

1. On `wp_abilities_api_categories_init @ P10` — 17 `Category_Registrar::instance()->register()` calls `wp_register_ability_category()`.
2. On `plugins_loaded @ P20` — 201 ability classes are instantiated; each constructor calls `add_action('wp_abilities_api_init', [$this, 'register'])`; then on `wp_abilities_api_init` each class's `register()` calls `wp_register_ability()`.

**This constructor-hook pattern violates the manager's Boot Flow Rule** and is refactored by CHANGE-4 (see below).

---

## Target Structure

New / modified paths inside `wp-content/plugins/acrossai-abilities-manager/`:

```
includes/
  Main.php                                        # MODIFIED (wire Bootstrap + Core_Settings_Menu)
  AcrossAI_Activator.php                          # MODIFIED (activation-time option migration)
  Utilities/                                      # UNCHANGED (manager's own)
  Modules/                                        # UNCHANGED (manager's Abilities/Library modules)
  Abilities/                                      # NEW TIER
    AcrossAI_Core_Abilities_Bootstrap.php         # NEW singleton orchestrator (sole hook-adder)
    Utilities/                                    # from companion /includes/Utilities/
      Block_Info.php, Cron_Helpers.php, ...
      Block_Style_Variations/, Global_Styles/, Pattern/, Template/, Template_Part/
    Block/            (30)   Cache/            (4)    Comments/    (13)
    Content/          (28)   Cron/             (16)   Database/    (10)
    FileManager/      (9)    Fonts/            (9)    Media/       (11)
    Menus/            (12)   Options/          (6)    Plugins/     (9)
    Settings/         (12)   SiteHealth/       (3)    Taxonomies/  (12)
    Themes/           (8)    Users/            (9)

admin/
  Partials/
    Core_Settings_Menu.php                        # MOVED from companion Admin/Partials/

uninstall.php                                     # MODIFIED (delete migrated MIME-types option inside existing gate)
composer.json                                     # UNCHANGED (existing PSR-4 covers the new tree)
```

Autoload: no `composer.json` change. The manager's existing PSR-4 mapping
`AcrossAI_Abilities_Manager\Includes\` → `includes/` covers every new file
under `Abilities/` automatically, and `AcrossAI_Abilities_Manager\Admin\` →
`admin/` covers the moved partial. Just run `composer dump-autoload`.

---

## Namespace & Slug Mapping

**The rebrand is uniform** across labels, category slugs, class/function names,
and internal identifiers (spec §Clarifications Q2 revised).

| Source | Target |
|---|---|
| Namespace `Acrossai_Core_Abilities\Includes\Abilities\<Cat>` | `AcrossAI_Abilities_Manager\Includes\Abilities\<Cat>` |
| Namespace `Acrossai_Core_Abilities\Includes\Utilities[\Sub]` | `AcrossAI_Abilities_Manager\Includes\Abilities\Utilities[\Sub]` |
| Namespace `Acrossai_Core_Abilities\Includes\Admin\Partials` | `AcrossAI_Abilities_Manager\Admin\Partials` |
| Category slug `acrossai-core-abilities-<domain>` | `acrossai-abilities-manager-<domain>` (all 17) |
| Text domain arg `'acrossai-core-abilities'` | `'acrossai-abilities-manager'` |
| Label text `"Acrossai Core Abilities — …"` | `"Acrossai Abilities Manager — …"` |
| Constant `ACROSSAI_CORE_ABILITIES_PLUGIN_*` | `ACROSSAI_ABILITIES_MANAGER_PLUGIN_*` |
| Any class/function/method/constant/variable name containing "Core Abilities" | matching name with "Abilities Manager" |
| Singleton property `$_instance` (companion convention) | `$instance` (manager convention per DEC-SINGLETON-PSR2-PROPERTY) |
| Option key `acrossai_core_abilities_extra_mimes` | `acrossai_abilities_manager_extra_mimes` |
| Option key `acrossai_core_abilities_uninstall_delete_data` | folded into the manager's existing `acrossai_abilities_uninstall_delete_data` (OR-monotonic; not a separate key) |

---

## CHANGE-1 — Copy `includes/Abilities/` categories (17 folders, 201 files)

- **Source root**: `wp-content/plugins/acrossai-core-abilities/includes/Abilities/`
- **Target root**: `wp-content/plugins/acrossai-abilities-manager/includes/Abilities/`

Copy each of the 17 category folders as-is. Do not rename files. Do not merge
`Category_Registrar.php` files across categories. Apply bulk rewrites
(CHANGE-5) after the copy.

---

## CHANGE-2 — Copy `includes/Utilities/` (nested under Abilities)

- **Source root**: `wp-content/plugins/acrossai-core-abilities/includes/Utilities/`
- **Target root**: `wp-content/plugins/acrossai-abilities-manager/includes/Abilities/Utilities/`

The nesting is deliberate. The manager already has its own `includes/Utilities/`
(`AcrossAI_Abilities_Formatter`, `AcrossAI_Sanitizer`, etc.). Nesting the
companion's set under `includes/Abilities/Utilities/` isolates them.

Preserve the five sub-folders: `Block_Style_Variations/`, `Global_Styles/`,
`Pattern/`, `Template/`, `Template_Part/`.

---

## CHANGE-3 — Move admin `Core_Settings_Menu.php` to `admin/Partials/`

- **Source**: `wp-content/plugins/acrossai-core-abilities/includes/Admin/Partials/Core_Settings_Menu.php`
- **Target**: `wp-content/plugins/acrossai-abilities-manager/admin/Partials/Core_Settings_Menu.php`

After CHANGE-5, the class has:
- Namespace `AcrossAI_Abilities_Manager\Admin\Partials`
- No `register_tab` method — the extra-MIME-types field renders inside the
  existing **Abilities** tab, not a new Core tab.
- No uninstall-opt-in field — the manager's single existing opt-in governs
  everything (spec §Clarifications Q4).
- Reads/writes `acrossai_abilities_manager_extra_mimes`.

---

## CHANGE-4 — New Bootstrap orchestrator (no class-level refactor)

**Retracted**: the "Path-A refactor of the 218 classes" described in the
previous version of this doc turned out to be based on an incorrect
assumption about the companion source. A 2026-07-13 spot-check confirmed:

- Every `Category_Registrar` already ships with an **empty constructor** and a
  synchronous `register()` method — no `add_action` inside. The T007 rewrite
  matrix has already normalized `$_instance` → `$instance` and rebranded the
  slug/label. **Nothing further to touch.**
- Every ability class extends `Ability_Definition` (the manager's own base,
  in `includes/Modules/Library/`). Ability classes carry no constructor and
  no `add_action`. Instantiating them triggers
  `Ability_Definition::__construct()`, inherited from the base, which calls
  `add_filter('acrossai_abilities_api_init', 'push_definition')`. **Instantiation
  is the trigger** — no per-class hook wiring to strip.

Only **CHANGE-4c (the new Bootstrap)** applies. CHANGE-4a and CHANGE-4b are
retired.

### 4c. New Bootstrap class

`includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php`. Mimics the
companion Main.php's registration flow (companion Main.php lines ~200–530)
driven from the manager's Loader. Note the three extras the previous
version of this doc missed: `Cron_Helpers::register_filter()`, the
`Media\Upload_Media::CHUNK_SWEEP_HOOK` cron action, and
`Media\Upload_Media::register_sweep_cron()`.

```php
<?php
namespace AcrossAI_Abilities_Manager\Includes\Abilities;

use AcrossAI_Abilities_Manager\Includes\AcrossAI_Loader;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Cron_Helpers;

class AcrossAI_Core_Abilities_Bootstrap {

    protected static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Called from Main.php::define_public_hooks() with the manager's Loader.
     * Adds 17 wp_abilities_api_categories_init callbacks — one per category —
     * mirroring the companion Main.php's original loader wiring.
     */
    public function register_category_callbacks( AcrossAI_Loader $loader ): void {
        $loader->add_action( 'wp_abilities_api_categories_init', Plugins\Category_Registrar::instance(),      'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Themes\Category_Registrar::instance(),       'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', FileManager\Category_Registrar::instance(),  'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Cache\Category_Registrar::instance(),        'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Database\Category_Registrar::instance(),     'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Users\Category_Registrar::instance(),        'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Block\Category_Registrar::instance(),        'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Settings\Category_Registrar::instance(),     'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Fonts\Category_Registrar::instance(),        'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Content\Category_Registrar::instance(),      'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Taxonomies\Category_Registrar::instance(),   'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Media\Category_Registrar::instance(),        'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Comments\Category_Registrar::instance(),     'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Menus\Category_Registrar::instance(),        'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Options\Category_Registrar::instance(),      'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', Cron\Category_Registrar::instance(),         'register' );
        $loader->add_action( 'wp_abilities_api_categories_init', SiteHealth\Category_Registrar::instance(),   'register' );
    }

    /**
     * Wired from Main.php::define_public_hooks() to plugins_loaded @ P20.
     * Instantiating an ability class triggers Ability_Definition::__construct()
     * which hooks acrossai_abilities_api_init — the Library Processor then
     * makes the actual wp_register_ability() calls.
     */
    public function register_abilities(): void {
        if ( ! class_exists( '\AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition', false ) ) {
            return;
        }
        new Plugins\Plugin_Activate();
        new Plugins\Plugin_Deactivate();
        // ... 201 lines total, in the companion Main.php's original order
        //     (Plugins, Settings, Themes, Users, Cache, Database, FileManager,
        //     Block, Fonts, Content, Taxonomies, Media, Comments, Menus,
        //     Options, Cron, SiteHealth — see companion Main.php lines ~330-530).

        // Extras the companion Main.php also ran (previously missed):
        Cron_Helpers::register_filter();
        add_action( Media\Upload_Media::CHUNK_SWEEP_HOOK, array( Media\Upload_Media::class, 'sweep_chunk_sessions' ) );
        Media\Upload_Media::register_sweep_cron();
    }
}
```

**Hook target**: `register_abilities` wires to `plugins_loaded @ P20`
(matching the companion Main.php's original hook point). The 17 category
hooks wire to `wp_abilities_api_categories_init` at whatever priority
`AcrossAI_Loader::add_action` defaults to (typically P10), also matching
the companion's original wiring.

---

## CHANGE-5 — Bulk rewrites across every moved PHP file

Apply these transforms in order to every PHP file under the new
`includes/Abilities/` tree and to the moved `admin/Partials/Core_Settings_Menu.php`.
Order matters — see 5c on the text-domain replace-first rule.

### 5a. Namespace declaration

- `namespace Acrossai_Core_Abilities\Includes\Abilities\<Category>;` → `namespace AcrossAI_Abilities_Manager\Includes\Abilities\<Category>;`
- `namespace Acrossai_Core_Abilities\Includes\Utilities;` → `namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities;`
- `namespace Acrossai_Core_Abilities\Includes\Utilities\<Subfolder>;` → `namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\<Subfolder>;`
- `namespace Acrossai_Core_Abilities\Includes\Admin\Partials;` → `namespace AcrossAI_Abilities_Manager\Admin\Partials;`

### 5b. `use` imports and FQCN references

Rewrite every `use Acrossai_Core_Abilities\Includes\…;` and every inline
`\Acrossai_Core_Abilities\…` reference to the corresponding new namespace
path. Grep across all moved files to confirm no `Acrossai_Core_Abilities`
substring remains.

### 5c. Text domain (replace-first rule)

Replace every occurrence of `'acrossai-core-abilities'` with
`'acrossai-abilities-manager'` in `__()`, `_e()`, `esc_html__()`,
`esc_attr__()`, `_x()`, `_n()`, `_nx()`, and any docblock references.

**Do this transform before ANY other partial-match substitution** so
`acrossai-abilities-manager` never gets accidentally re-rewritten by later
rules.

### 5d. Plugin constants

| Companion constant | Manager constant |
|---|---|
| `ACROSSAI_CORE_ABILITIES_PLUGIN_URL` | `ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL` |
| `ACROSSAI_CORE_ABILITIES_PLUGIN_PATH` | `ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH` |
| `ACROSSAI_CORE_ABILITIES_PLUGIN_BASENAME` | `ACROSSAI_ABILITIES_MANAGER_PLUGIN_BASENAME` |
| `ACROSSAI_CORE_ABILITIES_PLUGIN_FILE` | `ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE` |
| `ACROSSAI_CORE_ABILITIES_PLUGIN_NAME_SLUG` | `ACROSSAI_ABILITIES_MANAGER_PLUGIN_NAME_SLUG` |
| `ACROSSAI_CORE_ABILITIES_PLUGIN_NAME` | `ACROSSAI_ABILITIES_MANAGER_PLUGIN_NAME` |
| `ACROSSAI_CORE_ABILITIES_VERSION` | `ACROSSAI_ABILITIES_MANAGER_VERSION` |

### 5e. Visible label text — "Acrossai Core Abilities" → "Acrossai Abilities Manager"

Every admin-visible label and description string that carried the "Acrossai
Core Abilities" wording is rewritten to "Acrossai Abilities Manager". This is
the spec §Clarifications Q2-revised mandate — labels do NOT preserve their
old wording.

### 5f. Category slug rebrand

Every category slug of the form `acrossai-core-abilities-<domain>` becomes
`acrossai-abilities-manager-<domain>`. Applied uniformly across all 17
`Category_Registrar` files. This is an intentional breaking change for
downstream integrators (spec §US2 rewritten; FR-001).

### 5g. Class / function / method / constant / variable names containing "Core Abilities"

Rebrand any identifier containing the "Core Abilities" wording to "Abilities
Manager" (or equivalent manager-branded wording). This includes class names,
function names, method names, constants, variable names, and any other
symbol. Apply uniformly across the moved tree.

### 5h. Singleton property rename

`protected static $_instance = null;` → `protected static $instance = null;`
across every moved singleton. Update all internal references
(`self::$_instance` → `self::$instance`). Per DEC-SINGLETON-PSR2-PROPERTY.

### 5i. Docblock `@package` / `@subpackage` / `@since` header

Every moved file's docblock header rewritten to:
```
@package    AcrossAI_Abilities_Manager
@subpackage Includes\Abilities\<full\path\segment>
@since      0.1.0
```
Per AC-FILE-HEADER-PATTERN.

### 5j. Bulk-rewrite tooling note

Use `sed` or `perl` per-file with a small shell wrapper so each transform
writes the file synchronously (per BUG-PYTHON-STRREPLACE-PARTIAL-WRITE — do
NOT rely on Python `str.replace` batching that writes only at the end).
Every PHP file is post-processed with `phpcbf` after all rewrites to
normalize whitespace; PHPCS + PHPStan run in chunked mode (one category
folder at a time) so failures are isolated.

---

## CHANGE-6 — Wire Bootstrap and Core Settings Menu in `includes/Main.php`

Follow the manager's variable-first AC-HOOKS-MAIN convention: instantiate,
then wire.

### In `define_public_hooks()`

```php
$core_abilities_bootstrap = \AcrossAI_Abilities_Manager\Includes\Abilities\AcrossAI_Core_Abilities_Bootstrap::instance();
$core_abilities_bootstrap->register_category_callbacks( $this->loader ); // adds 17 loader hooks
$this->loader->add_action( 'plugins_loaded', $core_abilities_bootstrap, 'register_abilities', 20 );
```

### In `define_admin_hooks()`

```php
$core_settings_menu = \AcrossAI_Abilities_Manager\Admin\Partials\Core_Settings_Menu::instance();
$this->loader->add_action( 'admin_init', $core_settings_menu, 'register_settings' );
```

Note the omission compared to the earlier draft: no `add_filter( 'acrossai_settings_tabs', …, 'register_tab', 30 )`.
The extra-MIME-types field is added inside the existing Abilities tab via
`register_settings()`; no new tab is registered.

---

## CHANGE-7 — Activation-time option-key migration (new — in `AcrossAI_Activator`)

Executed inside `AcrossAI_Activator::activate()` at the existing activation
priority. Idempotent; safe on repeated activation.

```php
// 1. Migrate the extra-MIME-types option: copy legacy value into the manager-branded key
//    only if the manager-branded key hasn't been set yet; always delete the legacy key.
$legacy_mimes = get_option( 'acrossai_core_abilities_extra_mimes', null );
if ( null !== $legacy_mimes ) {
    if ( null === get_option( 'acrossai_abilities_manager_extra_mimes', null ) ) {
        update_option( 'acrossai_abilities_manager_extra_mimes', $legacy_mimes );
    }
    delete_option( 'acrossai_core_abilities_extra_mimes' );
}

// 2. Fold the legacy uninstall opt-in into the manager's existing single opt-in.
//    OR-monotonic: only ever flip false → true; never demote a manager true.
$legacy_delete_gate = (bool) get_option( 'acrossai_core_abilities_uninstall_delete_data', false );
if ( $legacy_delete_gate && ! (bool) get_option( 'acrossai_abilities_uninstall_delete_data', false ) ) {
    update_option( 'acrossai_abilities_uninstall_delete_data', 1 );
}
delete_option( 'acrossai_core_abilities_uninstall_delete_data' );
```

Property: idempotency. After the first activation on a legacy site, the
legacy keys no longer exist, so subsequent activations are a no-op for the
migration.

---

## CHANGE-8 — `uninstall.php` — delete the migrated MIME-types option inside the existing gate

Add one line inside the existing `$acrossai_delete_data` gate. This follows
PATTERN-UNINSTALL-DATA-GATE and avoids BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE.

```php
if ( $acrossai_delete_data ) {
    // ...existing manager cleanup unchanged...
    delete_option( 'acrossai_abilities_manager_extra_mimes' );
}
```

The legacy companion option keys are already swept at activation (CHANGE-7),
so they don't need uninstall handling.

---

## CHANGE-9 — Regenerate autoload

Run `composer dump-autoload` in the manager plugin directory. No
`composer.json` change is required. Verify no missing-file warnings appear
during dump. Verify a fresh page load does not error with "Class not found"
for any of the moved classes.

---

## Quality-Gate Audits (added by this feature)

Runnable from repo root; must pass zero-hit gates before merge.

```bash
# 1-4: No residual companion identity anywhere in the moved tree
grep -R "Acrossai_Core_Abilities" includes/Abilities admin/Partials/Core_Settings_Menu.php
grep -R "'acrossai-core-abilities'" includes/Abilities admin/Partials/Core_Settings_Menu.php
grep -R "acrossai-core-abilities-" includes/Abilities admin/Partials/Core_Settings_Menu.php   # category-slug rebrand
grep -R "ACROSSAI_CORE_ABILITIES_" includes/Abilities admin/Partials/Core_Settings_Menu.php

# 5: Constitution §II forbidden functions
grep -RnE '\beval\(|extract\(|shell_exec\(|passthru\(|exec\(|popen\(|proc_open\(|system\(' includes/Abilities

# 6-8: Existing quality gates over the whole plugin
composer phpstan
composer phpcs
composer test
npm run validate-packages

# 9 (optional): Plugin Check on production surface
# .github/workflows/plugin-check.yml
```

Scope note per PATTERN-GREP-AUDIT-VS-MANDATED-STRINGS: the audits above
explicitly do NOT include `specs/` — that directory MUST reference the
legacy slug in the spec's Clarifications block.

---

## Manual Verification (quickstart)

1. Fresh WordPress install with only the manager on disk (no companion).
2. Activate the manager. Confirm no PHP fatals.
3. Enumerate categories (via WP-CLI or REST): expect 17 categories with slugs `acrossai-abilities-manager-<domain>`; expect **zero** `acrossai-core-abilities-<domain>` slugs.
4. Open `?page=acrossai-settings&tab=abilities`. Expect: manager's own fields + the absorbed extra-MIME-types field; exactly ONE "Delete data on uninstall" checkbox; no reachable `?tab=core` URL.
5. Save a MIME type, reload, confirm the value persists under `acrossai_abilities_manager_extra_mimes` in `wp_options`.
6. Toggle the master uninstall opt-in, confirm it saves.
7. Upgrade-path fixture: seed a DB with `acrossai_core_abilities_extra_mimes` = "text/x-foo" and `acrossai_core_abilities_uninstall_delete_data` = 1. Activate. Confirm the value migrates to `acrossai_abilities_manager_extra_mimes`; the manager's existing `acrossai_abilities_uninstall_delete_data` becomes 1; both legacy rows are deleted.
8. Run the quality-gate audits above; expect zero hits from grep, zero errors from phpstan/phpcs/test.

---

## Release Notes — Category Slug Renames (Breaking Change)

Feature 046 rebrands every ability category slug from
`acrossai-core-abilities-<domain>` to `acrossai-abilities-manager-<domain>`.
Downstream integrators that reference the old slugs must update on cutover.

**Sibling-plugin audit (2026-07-13)**: sibling plugins on the workstation
(`acrossai-buddyboss-abilities`, `acrossai-claude-connectors`,
`acrossai-mcp-manager`, `acrossai-mcp-manager-npm`,
`acrossai-model-manager`) all show **zero** legacy slug references
(`grep -rn "acrossai-core-abilities-"`). No coordinated PR needed inside
this workstation. External integrators still need to update.

### Full slug rename map

| Old slug | New slug |
|---|---|
| `acrossai-core-abilities-block`         | `acrossai-abilities-manager-block` |
| `acrossai-core-abilities-cache`         | `acrossai-abilities-manager-cache` |
| `acrossai-core-abilities-comments`      | `acrossai-abilities-manager-comments` |
| `acrossai-core-abilities-content`       | `acrossai-abilities-manager-content` |
| `acrossai-core-abilities-cron`          | `acrossai-abilities-manager-cron` |
| `acrossai-core-abilities-database`      | `acrossai-abilities-manager-database` |
| `acrossai-core-abilities-file-manager`  | `acrossai-abilities-manager-file-manager` |
| `acrossai-core-abilities-fonts`         | `acrossai-abilities-manager-fonts` |
| `acrossai-core-abilities-media`         | `acrossai-abilities-manager-media` |
| `acrossai-core-abilities-menus`         | `acrossai-abilities-manager-menus` |
| `acrossai-core-abilities-options`       | `acrossai-abilities-manager-options` |
| `acrossai-core-abilities-plugins`       | `acrossai-abilities-manager-plugins` |
| `acrossai-core-abilities-settings`      | `acrossai-abilities-manager-settings` |
| `acrossai-core-abilities-site-health`   | `acrossai-abilities-manager-site-health` |
| `acrossai-core-abilities-taxonomies`    | `acrossai-abilities-manager-taxonomies` |
| `acrossai-core-abilities-themes`        | `acrossai-abilities-manager-themes` |
| `acrossai-core-abilities-users`         | `acrossai-abilities-manager-users` |

Ability slug prefix: `acrossai-core-abilities/<verb>` → `acrossai-abilities-manager/<verb>`
(applies uniformly to all 176 registered abilities). Ability payload shapes
are unchanged — only the slug/name identifiers rebrand.

---

## Follow-ups (out of scope for this feature)

- **Constitution PATCH bump**: enumerate `includes/Abilities/` in the §I Directory Layout (governance-only spec — see `specs/047-constitution-include-abilities-tier/`). Also lift the four candidate memory captures from the governed-plan summary via `/speckit-memory-md-capture` (`DEC-ABSORBED-CODE-INCLUDES-TIER`, `PATTERN-BULK-REWRITE-MATRIX`, `PATTERN-OPTION-KEY-MIGRATION-OR-MONOTONIC`, `PATTERN-CONSTRUCTOR-HOOK-REFACTOR-PATH-A`).
- **DRY audit** between `includes/Abilities/Utilities/` and the manager's `includes/Utilities/` — see `specs/048-dry-audit-abilities-utilities/`.
- **`.pot` regeneration** — see `specs/049-regenerate-pot/`.
- **PHPCS baseline for absorbed tree** — see `specs/050-046-phpcs-doc-baseline/` (766 residual errors, remediation script planned).
- **Companion plugin folder removal** — operational, separate task; not tracked as a spec.

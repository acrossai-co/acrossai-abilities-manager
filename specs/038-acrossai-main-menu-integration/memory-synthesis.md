# Memory Synthesis

## Current Scope

Feature 038 adopts the `acrossai-co/main-menu` v0.0.2 package as a shared top-level admin menu and Settings host for the Abilities Manager plugin. Affected modules:

- **Plugin boot path** (`acrossai-abilities-manager.php`, `includes/Main.php`) — composer dependency, package bootstrap, and graceful degradation when `vendor/autoload_packages.php` is missing (TASK-1, TASK-6 — regression of `AcrossAI_Loader` class-not-found fatal at `includes/Main.php:228`).
- **Admin menu registration** (`admin/Partials/Menu.php`, `LibraryMenu.php`, `LogsMenu.php`, `includes/Main.php`) — convert top-level Abilities Manager to a submenu, re-parent Library/Logs/Add-ons under `acrossai` (TASK-2, TASK-3).
- **Settings registration** (`admin/Partials/SettingsMenu.php`) — drop custom Settings page; re-register the three options against the host's `acrossai-settings` page/group (TASK-4).
- **Admin enqueue gating** (`admin/Main.php` lines 343, 354) — update hardcoded `hook_suffix` comparisons for the relocated pages (TASK-5).

## Relevant Decisions

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** (Reason Included: TASK-1 instantiates `\AcrossAI_Main_Menu\SettingsPage` via constructor whose ctor self-registers hooks; this is the exact case the decision covers, Status: Active, Source: DECISIONS.md). **Soft conflict**: the decision prescribes instantiation in `define_admin_hooks()` with `class_exists()` guard, but TASK-1 places the bootstrap in the plugin entry file on `plugins_loaded` priority 0. See Conflict Warnings below.
- **DEC-MENU-HOOK-SUFFIX** (Reason Included: TASK-5 hardcodes new hook-suffix strings (`acrossai_page_*`) and avoids `get_hook_suffix()` coupling — directly aligns, Status: Active, Source: DECISIONS.md).
- **DEC-SETTINGS-API-DEVIATION** (Reason Included: TASK-4 keeps the three settings on the WP Settings API; deviation accepted for ≤5-field scalar settings pages, Status: Active, Source: DECISIONS.md).
- **DEC-FAIL-OPEN-NOTICE** (Reason Included: TASK-6 admin notice for missing autoloader must pair with `current_user_can( 'manage_options' )` per this decision — TASK-6 wording does not yet specify the cap gate, Status: Active, Source: DECISIONS.md).
- **DEC-NAMESPACE-CONVENTION** (Reason Included: the fatal `AcrossAI_Abilities_Manager\Includes\AcrossAI_Loader` confirms the underscore convention; no rename needed, Status: Active, Source: DECISIONS.md).

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason Included: TASK-1 places `add_action('plugins_loaded', …, 0)` directly in `acrossai-abilities-manager.php`, bypassing the Loader pattern that AC-HOOKS-MAIN mandates for normal cases. Justified by TASK-1's note (host menu must own top-level menu independently of plugin Loader) — but needs an accepted deviation entry, Source: CONSTITUTION.md §I).
- **AC-ENQUEUE-ADMIN** (Reason Included: TASK-5 modifies enqueue gating in `admin/Main.php::enqueue_scripts/styles` — stays within the constraint, Source: CONSTITUTION.md §I).
- **AC-MENU-IN-PLACE** (Reason Included: TASK-2/TASK-3 update `Menu.php`/`LibraryMenu.php`/`LogsMenu.php` in-place; no new menu classes — directly aligns, Source: FR-020 of prior feature, retained constraint).

## Accepted Deviations

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** (Reason Included: precedent for external Composer packages whose constructors self-register hooks; TASK-1 extends this pattern from `define_admin_hooks()` to the plugin entry file, Status: Accepted-Deviation; an extension/new entry is needed to bless the entry-file location).
- **DEC-SETTINGS-API-DEVIATION** (Reason Included: keeps the three plugin settings on the WP Settings API rather than DataForm, Status: Accepted-Deviation).

## Relevant Security Constraints

- No SEC-* constraint directly affected — the change set modifies menu hierarchy and Settings host slugs, not access-control rules or data persistence boundaries. The three preserved option names (per_page, log_retention_days, uninstall_delete_data) retain their existing sanitizer callbacks (PATTERN-CHECKBOX-SANITIZE, PATTERN-UNINSTALL-DATA-GATE) per TASK-4's "DO NOT change sanitizer methods" constraint.

## Related Historical Lessons

- **BUG-LIBRARY-HOOK-SUFFIX** (Reason Included: warns submenu `hook_suffix` derives from `sanitize_title($parent_menu_title)`, not the parent slug. TASK-5's strings `'acrossai_page_*'` are correct ONLY because `sanitize_title('AcrossAI') === 'acrossai'` — a lucky coincidence. TASK-5's pre-commit verification step that logs `$hook_suffix` is mandatory; do not skip).
- **BUG-EXTERNAL-PACKAGE-CTOR-SILENT** (Reason Included: TASK-1's `class_exists` guard returns false silently when vendor is absent — must pair with TASK-6's admin notice so the silent-fail mode is observable to admins).
- **BUG-ABSPATH-STATIC-CLASS** (Reason Included: TASK-6 activation-hook callback in the plugin entry file must be inside the existing `defined('ABSPATH')` guard).
- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR + Feature 026 worklog** (Reason Included: precedent for the same composer-package-with-hookable-ctor pattern; TASK-1 follows Feature 026's blueprint with one location difference).
- **Feature 019 worklog** (Reason Included: established the Settings API + uninstall-gate + scalar-field pattern that TASK-4 preserves verbatim — only `option_group` and `$page` strings change).

## Conflict Warnings

- **SOFT (resolvable)**: TASK-1 bootstrap location vs DEC-EXTERNAL-PACKAGE-HOOK-CTOR + AC-HOOKS-MAIN. The decision and constraint say constructor-self-registering external packages get instantiated in `define_admin_hooks()` with `class_exists()` guard; TASK-1 places it in the plugin entry file (`acrossai-abilities-manager.php`) on `plugins_loaded` priority 0. TASK-1's stated justification (the host package owns the top-level menu independently of the plugin's internal Loader; submenus on default `admin_menu` priority 10 need the parent to exist by then) is reasonable. **Resolution**: capture this as a NEW accepted deviation or extension of DEC-EXTERNAL-PACKAGE-HOOK-CTOR in DECISIONS.md after `/speckit-plan`. Do not block planning.
- **SOFT (resolvable)**: TASK-6 admin notice vs DEC-FAIL-OPEN-NOTICE. The decision requires the missing-library notice to gate on `current_user_can( 'manage_options' )`; TASK-6's prose says "every admin screen" without naming the cap. **Resolution**: plan must add `current_user_can('manage_options')` to the notice callback. Do not block planning.
- **WATCH-OUT (not a conflict)**: TASK-5 hook-suffix strings depend on `sanitize_title('AcrossAI') === 'acrossai'`. If the menu-package upstream ever changes its menu_title, the suffix will diverge. The pre-commit verification step in TASK-5 catches the current value; record the dependency in DEC-MENU-HOOK-SUFFIX scope.

## Retrieval Notes

- Index entries considered: 19 of ≤20 budget (full Active Decisions + Architecture Constraints + Accepted Deviations + relevant Bug Patterns + Feature 019/026 worklog milestones).
- Source sections read: `docs/memory/INDEX.md` (all 233 lines); spec at `specs/038-acrossai-main-menu-integration/spec.md`; planning doc at `docs/planning/038-acrossai-main-menu-integration.md`. Did NOT read DECISIONS.md/ARCHITECTURE.md/BUGS.md/CONSTITUTION.md full bodies (index entries plus IDs were sufficient).
- Optimizer: disabled (no `.specify/extensions/memory-md/config.yml`); markdown-only flow used.
- Synthesis word count: under 900-word budget.
- Capture candidates flagged for `/speckit-memory-md-capture` after `/speckit-plan` completes: (1) new accepted deviation extending DEC-EXTERNAL-PACKAGE-HOOK-CTOR to plugin-entry-file bootstraps, (2) record the `sanitize_title('AcrossAI') === 'acrossai'` dependency under DEC-MENU-HOOK-SUFFIX scope.

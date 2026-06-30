# Implementation Plan: AcrossAI Main Menu Integration

**Branch**: `038-acrossai-main-menu-integration` | **Date**: 2026-06-30 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/038-acrossai-main-menu-integration/spec.md`

**Note**: This plan was generated inline by `/speckit-architecture-guard-governed-plan`. The orchestrator's documented fallback path was followed because the user prefers to invoke each `/speckit-*` command manually (no auto-chaining). The implementation breakdown (file paths, line numbers, exact code shapes) is authored by the user in `docs/planning/038-acrossai-main-menu-integration.md` — TASK-1 through TASK-6. This plan binds that breakdown to Constitution principles, applies the soft-conflict resolutions surfaced by `memory-synthesis.md`, and adds the missing details (capability gating, hook-suffix verification, deviation registration) that the planning doc left implicit.

## Summary

Adopt the `acrossai-co/main-menu` package (v0.0.2, pure PHP, Jetpack Autoloader-managed) as a shared parent admin menu and Settings host. Re-parent the four AcrossAI admin entries — Abilities, Library, Logs, Add-ons — under the new `acrossai` top-level menu, with explicit `$position` arguments fixing the order Settings → Abilities → Library → Logs → Add-ons. Delete the plugin's custom Settings page and re-register its three options (`acrossai_abilities_per_page`, `acrossai_abilities_log_retention_days`, `acrossai_abilities_uninstall_delete_data`) against the host's unified `acrossai-settings` option_group and `$page` slug — option NAMES are preserved so existing values keep resolving via `get_option()`. Patch two hardcoded `$hook_suffix` comparisons in `admin/Main.php` so page-gated asset enqueues continue to target the now-relocated Abilities and Settings pages. Add boot-resilience: when `vendor/autoload_packages.php` is absent, the plugin must short-circuit with a `manage_options`-gated admin notice (no fatal, no auto-deactivation) and the activation hook must `wp_die` rather than allow partial activation.

**Technical approach**: Six surgical edits across five files and one new composer dependency. No new PHP classes, no new modules, no REST changes, no JS bundle changes, no DB migration. The bootstrap pattern mirrors Feature 026's `AddonsPage` integration with one location difference: the host menu bootstrap lives in the plugin entry file on `plugins_loaded` priority 0 (rather than `define_admin_hooks()`) because the shared menu must exist before any submenu hooks fire on `admin_menu` default priority 10 — that location difference is registered as a new accepted deviation extending `DEC-EXTERNAL-PACKAGE-HOOK-CTOR`.

## Technical Context

**Language/Version**: PHP 8.1+ (CONSTITUTION §II), no JavaScript changes (v0.0.2 of the host package is pure server-side).
**Primary Dependencies**: NEW — `acrossai-co/main-menu: ^0.0.2`. Boots via existing `automattic/jetpack-autoloader: ^5.0` require at `includes/Main.php:206`; no second autoload include. EXISTING — `acrossai-co/addons-page: ^0.0.19` (constructor first arg changes from `'acrossai-abilities-manager'` to `'acrossai'`).
**Storage**: N/A — three existing `wp_options` rows (`acrossai_abilities_per_page`, `acrossai_abilities_log_retention_days`, `acrossai_abilities_uninstall_delete_data`) keep their names. Option_group used for `register_setting()` and form submission changes; values persist unchanged.
**Testing**: PHPUnit for boot-resilience guard, PHPStan level 8 (per CONSTITUTION §II), PHPCS WPCS strict, manual WP-admin sidebar walkthrough per TASK after each task. `npm run validate-packages` before commit.
**Target Platform**: WordPress 6.6+ admin, multisite-compatible (no auto-deactivation in degraded mode).
**Project Type**: WordPress plugin — single project.
**Performance Goals**: No regression. The `plugins_loaded` P0 bootstrap adds one `class_exists()` check + one constructor call per admin request; negligible. Boot-resilience flag adds one `file_exists()` call (already present) and one notice registration when degraded.
**Constraints**: Do not modify any file under `vendor/`. Do not rename existing menu_slugs or option names. Do not introduce a new JS bundle. Do not register a fallback for the legacy Settings URL. Every task must leave PHPStan L8 + PHPCS individually green before moving to the next.
**Scale/Scope**: 5 PHP files edited (`composer.json`, `acrossai-abilities-manager.php`, `includes/Main.php`, `admin/Partials/Menu.php`, `admin/Partials/LibraryMenu.php`, `admin/Partials/LogsMenu.php`, `admin/Partials/SettingsMenu.php`, `admin/Main.php`), 1 composer dependency added, 1 lockfile regenerated, no new files. Approximately 60 PHP lines added, 20 deleted.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked at end of Phase 1.*

| Principle (CONSTITUTION.md) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Boot Flow Rule (Main.php is single source of hook registration) | Partial deviation | ⚠️ **New Accepted Deviation** | TASK-1 places `add_action('plugins_loaded', …, 0)` in `acrossai-abilities-manager.php`, bypassing `includes/Main.php` for one hook. Justified: the host menu must be the canonical owner of the top-level menu and must exist before any submenu hooks fire on `admin_menu` priority 10. Registered as extension of `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` (see Complexity Tracking). |
| §I Admin Partials Rule (admin/Partials/ for menu/render/enqueue) | Yes | ✅ Pass | TASK-2/3/4 edit `Menu.php`, `LibraryMenu.php`, `LogsMenu.php`, `SettingsMenu.php` in place. TASK-5 edits `admin/Main.php`. No menu/render code added outside `admin/Partials/` or `admin/Main.php`. |
| §I REST Controller Pattern | No REST changes | ✅ Pass | N/A. |
| §I `permission_callback` return type | No REST changes | ✅ Pass | N/A. |
| §I Module Contract (singleton + private ctor) | Existing pattern preserved | ✅ Pass | `Menu`, `LibraryMenu`, `LogsMenu`, `SettingsMenu` keep their existing singleton + `register_submenu()` shape. `Menu::main_menu` renamed to `Menu::register_submenu` for consistency with siblings. |
| §II WordPress Standards — PHPStan L8 / PHPCS / Plugin Check | Yes | 🔲 Verify per-task | TASK-by-TASK gating; no batch-at-end pass. |
| §II `acrossai_` prefix on functions/hooks/classes | Yes | ✅ Pass | All new identifiers (admin notice callback, activation guard callback, vendor-missing flag) use the existing project namespace + underscore convention. |
| §II Multisite compatible | Yes | ✅ Pass | Plugin must NOT auto-deactivate in degraded mode (DEC-FAIL-OPEN-NOTICE compliant + multisite-safe). |
| §III UI Contract (`DataForm`/`DataViews` for forms/lists) | Pre-existing deviation | ✅ Pass | Settings remain on WP Settings API per `DEC-SETTINGS-API-DEVIATION` (≤5 scalar fields). TASK-4 changes the host page/group, NOT the form pattern. |
| §IV Security First (sanitize/escape/nonce/capability) | Yes — admin notice + activation guard | ✅ Pass | Admin notice callback gates on `current_user_can( 'manage_options' )` per `DEC-FAIL-OPEN-NOTICE` (this addition resolves the Synthesis soft conflict). Notice body uses `esc_html__()` with text domain `'acrossai-abilities-manager'`. `wp_die()` in activation guard uses `esc_html__()` for its message. No new nonces required (read-only notice; no form submission). |
| §V Extensibility Without Core Modification — graceful degradation when optional integrations absent | Yes — central requirement | ✅ Pass | TASK-1 wraps host bootstrap in `class_exists()` guard; TASK-6 handles the autoloader-missing case end-to-end (admin notice + activation block). Plugin remains functional in degraded mode. |
| §VI DRY | Yes | ✅ Pass | Reuses the existing `AddonsPage` `class_exists()` guard pattern. No new utility introduced. |
| §VII Definition of Done — all gates per-task | Yes | 🔲 Enforced at `/speckit-implement` time | Each TASK leaves PHPStan L8, PHPCS, Plugin Check (production surface only), and `npm run validate-packages` green individually before the next TASK begins. |
| **Code Quality & Workflow** — `npm run validate-packages` before commit | Yes | 🔲 Verify at implement time | Run during `/speckit-implement`. |
| **Code Quality & Workflow** — never modify `.agents/tools/` | Yes | ✅ Pass | Untouched. |

**Constitution Gate**: **PASS** with ONE new accepted deviation (entry-file bootstrap for shared top-level menu package — see Complexity Tracking). One pre-existing deviation (`DEC-SETTINGS-API-DEVIATION`) continues to apply with no scope expansion.

## Memory Synthesis Findings

Synthesized in [memory-synthesis.md](./memory-synthesis.md). Highlights applied to this plan:

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** — TASK-1 follows the spirit of this decision (constructor-self-registering external package, `class_exists()` guard) but in the plugin entry file rather than `define_admin_hooks()`. Plan adds the location deviation to Complexity Tracking and proposes it as a new accepted-deviation entry for capture after planning.
- **DEC-FAIL-OPEN-NOTICE** — TASK-6's admin notice MUST gate on `current_user_can( 'manage_options' )`. The planning doc didn't specify this; the plan does, and the gate appears in Constitution Check §IV row and in the `contracts/` notice signature.
- **DEC-MENU-HOOK-SUFFIX** — TASK-5 hardcodes `'acrossai_page_*'` strings. This works because `sanitize_title( 'AcrossAI' ) === 'acrossai'` — confirmed by manual derivation against WordPress's `get_plugin_page_hookname()` and `add_menu_page()` (`$admin_page_hooks[ $menu_slug ] = sanitize_title( $menu_title )`). TASK-5's pre-commit verification step (log `$hook_suffix` from `admin_enqueue_scripts`) is mandatory — captured as a hard quickstart step.
- **BUG-LIBRARY-HOOK-SUFFIX** — applies here; the verification step above closes it.
- **BUG-EXTERNAL-PACKAGE-CTOR-SILENT** — TASK-1's `class_exists()` returns `false` silently when the host package is absent; TASK-6's admin notice (for the broader vendor-missing case) is the user-visible signal that catches the silent failure mode.
- **BUG-ABSPATH-STATIC-CLASS** — the activation guard callback in `acrossai-abilities-manager.php` lives inside the existing `defined( 'ABSPATH' )` guard at the top of the file; no new ABSPATH guard needed.
- **Feature 026 worklog** — direct blueprint for TASK-1's composer integration; TASK-1 follows the same pattern with one location shift.
- **Feature 019 worklog** — direct blueprint for TASK-4's Settings-API treatment; only the `option_group` and `$page` strings change.

## Project Structure

### Documentation (this feature)

```text
specs/038-acrossai-main-menu-integration/
├── spec.md                                  # User stories, FRs, success criteria
├── plan.md                                  # This file
├── research.md                              # Phase 0 — resolved unknowns
├── data-model.md                            # Phase 1 — entities (menu nodes, settings, contracts)
├── contracts/
│   └── menu-and-settings-contracts.md       # Phase 1 — host-package API, option names, hook suffixes
├── quickstart.md                            # Phase 1 — verification recipes per TASK
├── memory-synthesis.md                      # Memory synthesis (already generated)
├── security-constraints.md                  # Step 4 — inline security review
├── checklists/
│   └── requirements.md                      # Spec quality checklist (already generated)
└── tasks.md                                 # Phase 2 — generated by /speckit-tasks (NOT created here)
```

### Source Code (repository root)

```text
acrossai-abilities-manager.php                 # TASK-1 (host bootstrap on plugins_loaded P0)
                                               # TASK-6 (register_activation_hook guard)
composer.json                                  # TASK-1 (add acrossai-co/main-menu: ^0.0.2)
composer.lock                                  # TASK-1 (regenerated)
includes/
└── Main.php                                   # TASK-2 (line 251: register_submenu method rename)
                                               # TASK-3 (line 276: AddonsPage parent_slug arg)
                                               # TASK-4 (line 264: drop SettingsMenu admin_menu loader line)
                                               # TASK-6 (load_dependencies + __construct: vendor_missing flag)
admin/
├── Main.php                                   # TASK-5 (lines 343, 354: hook_suffix strings)
└── Partials/
    ├── Menu.php                               # TASK-2 (add_menu_page → add_submenu_page; method rename)
    ├── LibraryMenu.php                        # TASK-3 (parent_slug + $position arg)
    ├── LogsMenu.php                           # TASK-3 (parent_slug + $position arg)
    └── SettingsMenu.php                       # TASK-4 (delete register_submenu + render; rewrite option_group + $page)

vendor/                                        # untouched (acrossai-co/main-menu lands here via composer)
```

**Structure Decision**: Single-project WordPress plugin. No new directories. Eight existing files modified. One new composer dependency. The architecture-guard violation-detection pass below confirms no module-boundary crossings.

## Phase 0 — Research Findings

All unknowns are resolved by the user's planning doc and the memory synthesis. No `NEEDS CLARIFICATION` markers were emitted by `/speckit-specify`. Detailed research notes in [research.md](./research.md). Summary of decisions made:

| Question | Decision | Rationale |
|---|---|---|
| Where to bootstrap the host menu package? | Plugin entry file, `plugins_loaded` priority 0, `class_exists` guard | Host menu must exist before any submenu hooks fire on `admin_menu` P10; entry-file placement makes the package the canonical owner independent of plugin Loader. Accepted deviation from DEC-EXTERNAL-PACKAGE-HOOK-CTOR. |
| What position values for submenus? | Settings (host-owned, implicit) → Abilities `$position=1` → Library `$position=2` → Logs `$position=3` → Add-ons (vendor priority 20, no explicit position) | Matches the agreed sidebar order; Add-ons trails because vendor registers at `admin_menu` priority 20. |
| Should we rename menu_slugs? | NO | Preserves bookmarked URLs, plugin-action-links, and JS bundle handles. Only parent changes. |
| Should we rename option NAMES? | NO | Preserves `get_option()` lookups across the upgrade. Only option_group and `$page` slug change. |
| What's the correct submenu hook_suffix format? | `acrossai_page_<menu_slug>` | Derived from `add_menu_page()` setting `$admin_page_hooks['acrossai'] = sanitize_title('AcrossAI') === 'acrossai'`. Verification step in TASK-5 confirms at runtime. |
| Should the plugin auto-deactivate when vendor is missing? | NO | DEC-FAIL-OPEN-NOTICE + multisite-safety. Plugin stays "active" in the list with a persistent admin notice; admin notice gates on `current_user_can('manage_options')`. |
| Should activation be blocked when vendor is missing? | YES | `register_activation_hook` callback `wp_die`s with an `esc_html__()` message. Prevents partial activation. |
| Should we register a fallback for the legacy Settings URL? | NO | Standard WP "page does not exist" response is acceptable per the planning doc's explicit constraint. |

## Phase 1 — Design

### Data Model

See [data-model.md](./data-model.md). Entities:

- **AcrossAI parent menu** (host-owned) — slug `acrossai`, `$admin_page_hooks` derived value `acrossai`.
- **Shared Settings page** (host-owned) — slug `acrossai-settings`, option_group `acrossai-settings`, `$page` slug `acrossai-settings` (unified).
- **Plugin submenus** — Abilities (`acrossai-abilities-manager`), Library (`acrossai-abilities-library`), Logs (`acrossai-abilities-logs`); each owns its own menu_slug verbatim.
- **Plugin settings** (three options) — names preserved; sanitizers preserved; section IDs preserved; section titles preserved.
- **Composer autoloader** — `vendor/autoload_packages.php`; presence-or-absence is the boot-resilience switch.

### Contracts

See [contracts/menu-and-settings-contracts.md](./contracts/menu-and-settings-contracts.md). Public-surface elements pinned by this plan:

- Host package API (constants and class names).
- WordPress hook-suffix strings the plugin commits to.
- Option names, option_group, and `$page` slug.
- Admin notice signature (callback name, capability gate, text-domain expectations).
- Activation-hook contract.

### Quickstart

See [quickstart.md](./quickstart.md). Per-TASK verification recipes that an implementer can run in sequence.

### CLAUDE.md Update

Plan reference inside `<!-- SPECKIT START --> ... <!-- SPECKIT END -->` markers updated to `specs/038-acrossai-main-menu-integration/plan.md`.

## Complexity Tracking

> Filled because one Constitution principle has a documented new deviation.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| §I Boot Flow Rule — entry-file `add_action('plugins_loaded', …, 0)` for host menu bootstrap | The shared menu must be the canonical owner of the top-level menu, independent of the plugin's internal Loader, and must exist before any plugin's `admin_menu` priority-10 submenu registration fires. Hooking it through `Main::define_admin_hooks()` would not work because that hook lives on `admin_menu` itself, which is already too late. Moving the bootstrap inside `Main::__construct()` would force every consuming AcrossAI plugin to host its own copy of the host menu, defeating the "single canonical owner" goal. | Registering the bootstrap via the existing Loader inside `Main::define_admin_hooks()` — rejected because `define_admin_hooks()` is called from `Main::__construct()` which runs at `plugins_loaded` priority 10 (default), too late for the host menu to be visible to submenus that bind on `admin_menu`. Registering inside `Main::load_dependencies()` — rejected because the Constitution explicitly forbids hook-registering code there. |

**Proposed memory entry** (to be captured via `/speckit-memory-md-capture` after planning lands): extend `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` scope to cover plugin-entry-file bootstraps when the external package supplies a SHARED top-level menu that must exist before `admin_menu` P10 fires. Cite Feature 038 as the reference implementation.

## Architecture Guard — Violation Detection (Inline)

Per orchestrator Step 5, run inline against `.specify/memory/CONSTITUTION.md` + this plan + `memory-synthesis.md` + `security-constraints.md`. Findings:

1. **One Boot Flow Rule deviation** — disclosed and justified in Complexity Tracking above. Not a drift; an intentional, scoped extension of the existing `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` accepted deviation. **Severity: ACCEPTED-DEVIATION** (not BLOCKING).
2. **Zero Security-Architecture Conflicts** — the admin notice gates on `manage_options` (Constitution §IV row + memory-synthesis resolution); the activation `wp_die` message is `esc_html__()`-wrapped; no new REST routes or AJAX endpoints introduced.
3. **Zero module-boundary crossings** — no class in `includes/Modules/` is touched. The changes live in `includes/Main.php`, `admin/Partials/`, `admin/Main.php`, and the plugin entry file. The new host package vendor is in `vendor/`, untouched as required.
4. **One existing UI deviation continues** (`DEC-SETTINGS-API-DEVIATION`); scope unchanged.
5. **Hook-suffix verification** (against BUG-LIBRARY-HOOK-SUFFIX) is enforced as a TASK-5 pre-commit step + a quickstart row. Not a drift; a planned mitigation.

**Architecture Guard Verdict**: **PASS** — proceed to `/speckit-tasks`.

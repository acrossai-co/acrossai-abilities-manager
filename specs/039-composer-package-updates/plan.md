# Implementation Plan: Composer Package Updates — wpb-access-control v2 + main-menu absorbs addons-page

**Branch**: `039-composer-package-updates` | **Date**: 2026-07-01 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/039-composer-package-updates/spec.md`

**Note**: This plan was generated inline by `/speckit-architecture-guard-governed-plan`. The orchestrator's documented fallback path was followed because the user prefers to invoke each `/speckit-*` command manually (no auto-chaining of sub-skills). The implementation breakdown (file paths, line numbers, exact code shapes) is authored by the user in `docs/planning/039-composer-package-updates.md` — TASK-1 through TASK-5. This plan binds that breakdown to Constitution principles, applies the soft-conflict resolutions surfaced by `memory-synthesis.md`, and confirms no new accepted deviation is required (every Constitution principle is met within existing deviations).

## Summary

Adopt two breaking upstream composer releases in a single feature: (a) `acrossai-co/main-menu` v0.0.8, which absorbs the Add-ons admin UI previously shipped as the standalone `acrossai-co/addons-page` package — class name `\AcrossAI_Addon\AddonsPage` is preserved upstream; only the constructor positional order changes (first `$menu_slug` argument dropped, `$parent_slug` becomes an optional third arg defaulting to `'acrossai'`); `freemius/wordpress-sdk ^2.0` arrives transitively; (b) `wpboilerplate/wpb-access-control` v2.0.0, which introduces per-consumer DB tables via a new `$table_slug` argument on both `AccessControlManager::__construct()` and `RuleTable::__construct()`. This plugin adopts slug `'abilities'` so it owns a private `{prefix}abilities_access_control` table, `wpb_ac_abilities_db_version` schema option, `wpb_ac_abilities` cache group, and `/wpb-ac/v1/abilities/...` REST namespace — fully isolated from any future sibling consumer of the library.

**Technical approach**: Five surgical edits across five files and one composer manifest change. No new PHP classes, no new modules, no REST controller code (the library owns its routes), no JS bundle changes, no migration code. The activator creates the new per-consumer table via the same `( new Table() )->maybe_upgrade();` shape used for the plugin's other two tables (sanctioned by `DEC-TABLE-SOFT-SINGLETON`). The uninstall path drops the new table and option key while leaving the legacy `{prefix}wpb_access_control` table explicitly orphaned per the user's "no backward compatibility" constraint.

## Technical Context

**Language/Version**: PHP 8.1+ (CONSTITUTION §II), no JavaScript changes (the React component bundled with wpb-access-control v2 keeps the same `wpbAcConfig.namespace` contract — per-consumer slugging is server-side only).
**Primary Dependencies**: CHANGED — `wpboilerplate/wpb-access-control: ^1.6.0` → `^2.0.0`; `acrossai-co/main-menu: ^0.0.4` → `^0.0.8`. REMOVED — `acrossai-co/addons-page: ^0.0.19`. NEW (transitive) — `freemius/wordpress-sdk: ^2.0` via main-menu. Existing — `automattic/jetpack-autoloader: ^5.0`, `berlindb/core: ^3.0.0`.
**Storage**: One NEW per-consumer table — `{prefix}abilities_access_control` (BerlinDB-managed schema version `202605120001`, `$global = false` → per-site under multisite). One NEW schema-version option — `wpb_ac_abilities_db_version`. One LEGACY artifact intentionally orphaned — `{prefix}wpb_access_control` table and `wpb_access_control_db_version` option are NEITHER read NOR migrated NOR deleted by this feature on existing installs.
**Testing**: PHPUnit for existing access-control bootstrap test (must be updated to assert the two-arg constructor), PHPStan level 8 (per CONSTITUTION §II), PHPCS WPCS strict. `npm run validate-packages` before commit. Manual: visit Add-ons submenu, visit per-ability Access Control panel, save a rule, inspect database for `{prefix}abilities_access_control`.
**Target Platform**: WordPress 6.9+ admin, PHP 8.1+, multisite-compatible (per-site table prefix per SEC-03).
**Project Type**: WordPress plugin — single project.
**Performance Goals**: Zero regression. Activation gains one `( new RuleTable( 'abilities' ) )->maybe_upgrade();` call (idempotent, sub-millisecond on cached schema). Runtime: `boot_manager()` adds one extra string argument to the existing manager constructor call — no additional indirection.
**Constraints**: Do not modify any file under `vendor/`. Do not migrate, copy, or read from the legacy `{prefix}wpb_access_control` table. Do not introduce a runtime filter for the access-control table slug. Do not call `dbDelta()` directly. Do not rename, alias, or re-export `\AcrossAI_Addon\AddonsPage`. Every task must leave PHPStan L8 + PHPCS individually green before moving to the next.
**Scale/Scope**: 5 files edited — `composer.json`, `composer.lock` (regenerated), `includes/Main.php` (lines 316–348, AddonsPage block), `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` (constants block + `boot_manager()`), `includes/AcrossAI_Activator.php` (imports + activate() line 43), `uninstall.php` (lines 31, 37). Approximately 15 PHP lines changed, 0 added, 0 deleted (net). One composer dep removed, no direct deps added (Freemius comes transitively).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked at end of Phase 1.*

| Principle (CONSTITUTION.md) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Boot Flow Rule (Main.php is single source of hook registration) | Yes | ✅ Pass | No new hooks are registered. The existing AddonsPage `new ...()` self-registering constructor inside the `class_exists` guard at `includes/Main.php:322` continues to operate under the existing `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` accepted deviation. No new deviation introduced. |
| §I Admin Partials Rule (admin/Partials/ for menu/render/enqueue) | Yes | ✅ Pass | No admin/Partials/ files are touched. Asset enqueue at `admin/Main.php` continues to load the same bundle path (`vendor/wpboilerplate/wpb-access-control/assets/build/`). |
| §I REST Controller Pattern | No new REST controllers | ✅ Pass | The library owns its REST routes (`/wpb-ac/v1/abilities/...`). The consumer's `register_rest_api()` call shape is unchanged. |
| §I `permission_callback` return type | No new REST endpoints | ✅ Pass | Library-internal; out of scope for the consumer plugin. |
| §I Module Contract (singleton + private ctor) | Yes | ✅ Pass | `AcrossAI_Abilities_Access_Control` keeps its singleton + private ctor. `AcrossAI_Activator` direct-instantiates `RuleTable` (BerlinDB Table subclass) — sanctioned by `DEC-TABLE-SOFT-SINGLETON`. |
| §II WordPress Standards — PHPStan L8 / PHPCS / Plugin Check | Yes | 🔲 Verify per-task | TASK-by-TASK gating; no batch-at-end pass. The new `TABLE_SLUG` constant introduces no type-narrowing complications. |
| §II `acrossai_` prefix on functions/hooks/classes | Yes | ✅ Pass | No new functions/hooks/classes introduced. The new constant `TABLE_SLUG` lives on an already-prefixed class (`AcrossAI_Abilities_Access_Control`). |
| §II Multisite compatible | Yes | ✅ Pass | `RuleTable::$global` MUST remain `false` upstream (security re-validation step in `security-constraints.md`); confirmed in v2.0.0 source. Per-site `{prefix}abilities_access_control` honors SEC-03. |
| §III UI Contract (`DataForm`/`DataViews` for forms/lists) | Pre-existing deviation | ✅ Pass | The Access Control React UI is library-owned; the consumer mounts a single `<div id="wpb-access-control">` and lets the library hydrate it. No DataForm/DataViews change. `DEC-SETTINGS-API-DEVIATION` continues to apply to the settings page (unchanged). |
| §IV Security First (sanitize/escape/nonce/capability) | Yes | ✅ Pass | No new input boundaries. The AddonsPage admin-notice fallback already gates on `current_user_can('manage_options')` per `DEC-FAIL-OPEN-NOTICE`; the AC library's `maybe_show_library_notice` does the same. Both gates remain. See `security-constraints.md` for the post-upgrade re-validation gate (DEC-REVALIDATE-SECURITY-POST-UPGRADE). |
| §V Extensibility Without Core Modification — graceful degradation when optional integrations absent | Yes | ✅ Pass | Existing `class_exists()` guards around AddonsPage (line 322) and AccessControlManager (line 98) continue to work; both packages' classes resolve through Jetpack Autoloader. Plugin loads in degraded mode if either vendor is absent. |
| §VI DRY | Yes | ✅ Pass | The new `TABLE_SLUG` constant is referenced from exactly two places: `boot_manager()` and `AcrossAI_Activator::activate()`. The same constant name flows through. `uninstall.php` cannot reference it (uninstall runs before plugin classes load) — the table name and option key are literal strings there; this is acceptable per `PATTERN-UNINSTALL-DATA-GATE`. |
| §VII Definition of Done — all gates per-task | Yes | 🔲 Enforced at `/speckit-implement` time | Each TASK leaves PHPStan L8, PHPCS, Plugin Check (production surface only), and `npm run validate-packages` green individually before the next TASK begins. |
| **Code Quality & Workflow** — `npm run validate-packages` before commit | Yes | 🔲 Verify at implement time | No JS dependency change, but the validator must still pass. |
| **Code Quality & Workflow** — never modify `.agents/tools/` | Yes | ✅ Pass | Untouched. |
| **Integration Resilience** — graceful degradation; no fatals on missing vendor | Yes | ✅ Pass | Both existing fail-open paths (AddonsPage `class_exists` + try/catch; AC library `class_exists` + admin notice) continue to operate. Feature 038's TASK-6 boot-resilience guard at `Main::load_composer_dependencies()` already covers the vendor-missing case. |

**Constitution Gate**: **PASS** — no new accepted deviation required. Two pre-existing accepted deviations continue to apply with no scope expansion: `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` (AddonsPage call site) and `DEC-SETTINGS-API-DEVIATION` (settings page; out of this feature's scope). One existing decision (`DEC-TABLE-SOFT-SINGLETON`) is exercised at its sanctioned boundary (BerlinDB Table instantiation in the activator).

## Memory Synthesis Findings

Synthesized in [memory-synthesis.md](./memory-synthesis.md). Highlights applied to this plan:

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** — TASK-2 preserves the existing accepted-deviation shape verbatim; only the argument list to `new \AcrossAI_Addon\AddonsPage(...)` changes. No new scope expansion.
- **DEC-FAIL-OPEN-NOTICE** — Both vendor-absence guards (AddonsPage try/catch, AC library `maybe_show_library_notice`) continue to enforce `current_user_can('manage_options')` and `esc_html()` on notice text. TASK-2's edit must not regress this — the plan explicitly preserves the try/catch + admin_notices closure block (lines 322–347) unchanged.
- **DEC-FREEMIUS-PER-PLUGIN-INIT** — Freemius credentials (`fs_product_id`, `fs_public_key`, `fs_slug`) flow per-plugin through the AddonsPage args array; nothing is centralized. Transitive `freemius/wordpress-sdk ^2.0` does not change this pattern.
- **DEC-REVALIDATE-SECURITY-POST-UPGRADE** — Treated as a mandatory plan-time gate. `security-constraints.md` records the re-validation of SEC-03, SEC-04, DEC-PERM-CB, and DEC-FAIL-OPEN-NOTICE against the v2 REST namespace and per-consumer table.
- **DEC-STABLE-UPGRADE-WINDOW-INTERNAL-ORG** — `acrossai-co/main-menu` v0.0.8 is below v1.0.0; the Feature 038 internal-org exemption applies (audit performed via README inspection + composer.lock SHA pin).
- **DEC-TABLE-SOFT-SINGLETON** — TASK-4's `( new RuleTable( ... ) )->maybe_upgrade();` direct instantiation is sanctioned; the existing two table-create lines use the same shape.
- **PATTERN-ADMIN-NOTICE-SELF-CONTAINED** — The TASK-2 try/catch admin_notices closure already complies (only WP globals inside the closure, no `$this` capture, manage_options gate). Verified by inspection.
- **PATTERN-UNINSTALL-DATA-GATE** — TASK-5 keeps every change inside the existing `if ( $acrossai_delete_data )` block; `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE` is actively guarded against.
- **PATTERN-VENDOR-ASSET-FAMILY-HANDLE** — Asset enqueue path and handle naming are unchanged.
- **BUG-EXTERNAL-PACKAGE-CTOR-SILENT** — The AddonsPage try/catch already wires an `admin_notices` callback (not silent); TASK-2 must preserve it.

## Project Structure

### Documentation (this feature)

```text
specs/039-composer-package-updates/
├── spec.md                                  # User stories, FRs, success criteria (already authored)
├── plan.md                                  # This file
├── memory-synthesis.md                      # Memory synthesis (already authored)
├── security-constraints.md                  # Step 4 — inline security review (authored alongside this plan)
├── checklists/
│   └── requirements.md                      # Spec quality checklist (already authored)
└── tasks.md                                 # Phase 2 — generated by /speckit-tasks (NOT created here)
```

A separate detailed implementation breakdown (TASK-1 through TASK-5 with line-precise edits) is held at `docs/planning/039-composer-package-updates.md` and is the canonical source for `/speckit-tasks`.

### Source Code (repository root)

```text
composer.json                                  # TASK-1 (drop addons-page; bump main-menu + wpb-access-control)
composer.lock                                  # TASK-1 (regenerated)
includes/
├── Main.php                                   # TASK-2 (lines 316–348: drop $menu_slug arg from AddonsPage)
├── AcrossAI_Activator.php                     # TASK-4 (import + line 43: pass TABLE_SLUG to RuleTable)
└── Modules/Abilities/
    └── AcrossAI_Abilities_Access_Control.php  # TASK-3 (add TABLE_SLUG const; pass it to AccessControlManager)
uninstall.php                                  # TASK-5 (line 31: rename table; line 37: rename option key)

vendor/                                        # untouched (acrossai-co/main-menu replaces acrossai-co/addons-page; wpb-access-control bumps to v2)
```

**Structure Decision**: Single-project WordPress plugin. No new directories. Five existing files modified. One composer dependency removed, two bumped, one (Freemius) arriving transitively. The architecture-guard violation-detection pass below confirms zero module-boundary crossings and zero new accepted deviations.

## Phase 0 — Research Findings

All unknowns were resolved in the pre-plan exploration (captured in `docs/planning/039-composer-package-updates.md` and `memory-synthesis.md`). No `NEEDS CLARIFICATION` markers exist. Summary of decisions made:

| Question | Decision | Rationale |
|---|---|---|
| Where does AddonsPage now ship? | `vendor/acrossai-co/main-menu/src/Addons/`, autoloaded under PSR-4 `AcrossAI_Addon\\`. Class name `\AcrossAI_Addon\AddonsPage` preserved. | Upstream main-menu v0.0.7 composer.json declares the additional PSR-4 mapping. Class name preserved to keep consumer migration trivial. |
| Should we keep `fs_slug` in the args array? | YES — keep it. | New constructor signature (`?string $consumer_main_file = null, array $args = [], string $parent_slug = 'acrossai'`) accepts `fs_slug` as optional in the args array; defaulting to `MenuRegistrar::SUBMENU_SLUG` if omitted. Preserving the existing literal `'acrossai-abilities-manager'` keeps Freemius product identification stable. |
| What slug for the per-consumer access-control table? | `'abilities'` | Short, all-lowercase, matches `^[a-z0-9_]{1,32}$`. Produces `{prefix}abilities_access_control` (read cleanly in DB inspector), `wpb_ac_abilities_db_version` (matches library's documented `wpb_ac_{slug}_db_version` format), `/wpb-ac/v1/abilities/...` (clean REST namespace), `wpb_ac_abilities` (clean cache group). |
| Where should the per-consumer table be created? | In the activator, mirroring the existing pattern (`( new XYZ_Table() )->maybe_upgrade();`). | `DEC-TABLE-SOFT-SINGLETON` sanctions direct instantiation in the activator. Sibling consistency with the two existing table-create lines is preferred over the README's "no activation hook needed" guidance — the latter would defer table creation until first admin_init, which is fine for most cases but creates a window where a front-end request could hit a missing table. |
| Should we migrate from `{prefix}wpb_access_control`? | NO — explicit user constraint. | "Do not care about backward compatibility." Release-note communication (spec FR-012) is the mitigation. |
| Should we drop the legacy table on update? | NO — leave it orphaned. | Same constraint. Admins who want cleanup can drop it manually. |
| Should the slug be filterable at runtime? | NO — hardcoded. | The slug is a plugin-level identity, not a configuration value. A runtime filter would mean storage could change underneath a running plugin — a foot-gun for no real-world need. |
| Constructor positional order for AddonsPage v0.0.7 | `(?string $consumer_main_file = null, array $args = [], string $parent_slug = 'acrossai')` | Confirmed by reading `vendor/acrossai-co/main-menu/src/Addons/AddonsPage.php` via GitHub API. Drop the first positional `'acrossai'` argument; `$parent_slug` defaults to `'acrossai'` so the third arg can be omitted. |

## Phase 1 — Design

### Data Model

No new data model. Entities (already documented in `spec.md`):

- **Plugin-owned Access Control Storage** — physical table `{prefix}abilities_access_control` (NEW); schema owned by `\WPBoilerplate\AccessControl\Database\Rule\RuleSchema`; schema version `202605120001`; per-site under multisite (`$global = false`).
- **Schema Version Marker** — option `wpb_ac_abilities_db_version` (NEW). Format per upstream README: `wpb_ac_{slug}_db_version`.
- **Legacy Shared Storage** — table `{prefix}wpb_access_control` and option `wpb_access_control_db_version` are present on existing installs and left orphaned. NEITHER read NOR modified NOR deleted.
- **Access Control Rule** — logical unit unchanged; storage relocates from the legacy shared table to the new per-consumer table for any new rules written post-upgrade.

### Contracts

External contracts the plugin commits to (pinned in `composer.lock`):

- `\AcrossAI_Addon\AddonsPage::__construct( ?string $consumer_main_file = null, array $args = [], string $parent_slug = 'acrossai' )` — args MUST include `fs_product_id` and `fs_public_key` (validated upstream; throws `\InvalidArgumentException` otherwise). `fs_slug` optional.
- `\WPBoilerplate\AccessControl\AccessControlManager::__construct( string $providers_filter, string $table_slug = '' )` — second arg validated against `^[a-z0-9_]{1,32}$`; throws `\InvalidArgumentException` on mismatch.
- `\WPBoilerplate\AccessControl\Database\Rule\RuleTable::__construct( string $table_slug )` — same slug validation.

Plugin-side contracts established by this feature:

- `AcrossAI_Abilities_Access_Control::TABLE_SLUG = 'abilities'` — single source of truth for the access-control table slug. Referenced from `boot_manager()` and `AcrossAI_Activator::activate()`.
- `uninstall.php` literal strings `'abilities_access_control'` and `'wpb_ac_abilities_db_version'` — duplicated by design (uninstall runs before plugin classes load and cannot reference `TABLE_SLUG`).

### Quickstart

Per-TASK verification recipes are held in `docs/planning/039-composer-package-updates.md` under "Manual Verification Checklist". Highlights:

- TASK-1: `composer update --with-all-dependencies wpboilerplate/wpb-access-control acrossai-co/main-menu acrossai-co/addons-page`. Confirm lockfile drops addons-page and adds freemius/wordpress-sdk transitively.
- TASK-4: Deactivate + delete + reactivate on a clean test site → `wp db query "SHOW TABLES LIKE '%abilities_access_control%'"` returns one row.
- TASK-5: With `acrossai_abilities_uninstall_delete_data = 1`, uninstall → confirm the new table is dropped and the new option is removed. The legacy artifacts MUST remain.

### CLAUDE.md Update

No update needed. The existing `CLAUDE.md` reference inside `<!-- SPECKIT START --> ... <!-- SPECKIT END -->` markers can be updated to `specs/039-composer-package-updates/plan.md` if the `/speckit-tasks` step requires it.

## Complexity Tracking

> Empty — no Constitution principle has a new deviation.

This feature operates entirely within existing accepted deviations and decisions:

- `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` — preserved at the AddonsPage call site; no scope change.
- `DEC-TABLE-SOFT-SINGLETON` — exercised at its sanctioned activator boundary.
- `DEC-SETTINGS-API-DEVIATION` — unrelated; continues to apply to the settings page (out of this feature's scope).

## Architecture Guard — Violation Detection (Inline)

Per orchestrator Step 5, run inline against `.specify/memory/CONSTITUTION.md` + this plan + `memory-synthesis.md` + `security-constraints.md`. Findings:

1. **Zero new Constitution deviations**. Every principle in §I–§VII is satisfied within existing accepted deviations. The Constitution Check table above is the audit trail.
2. **Zero Security-Architecture Conflicts**. The two fail-open admin notices preserve `manage_options` capability gates and `esc_html()` escaping; no new REST endpoints introduced (library owns all routes); no new AJAX handlers; multisite isolation preserved via BerlinDB's per-site prefix (SEC-03 re-validation in `security-constraints.md`).
3. **Zero module-boundary crossings**. The five edited files are: composer manifest (root), one entry in `includes/Main.php` (no new dependency on a sibling module), one constant + one method-body edit in `Includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` (within its own module), one import + one line in `includes/AcrossAI_Activator.php` (the activator already imports from the Abilities module, so the new `use AcrossAI_Abilities_Access_Control` is consistent with existing imports), and one root-level `uninstall.php`. No new cross-module call.
4. **One soft conflict resolved**: `ARCH-ZERO-CODE-DEPENDENCY-UPGRADE` vs. reality — the upstream packages ship genuinely breaking constructor signatures; code changes are unavoidable. The pattern remains aspirational; Feature 039 is the second upstream-driven exception after Feature 036. No memory update required at plan time.
5. **One soft conflict acknowledged**: Implicit "preserve user data on upgrade" norm vs. explicit "no backward compatibility" instruction. Release-note communication (spec FR-012) is the documented mitigation. Architecture Guard records this for the architecture-review pass to confirm post-implementation.

**Architecture Guard Verdict**: **PASS** — proceed to `/speckit-tasks`.

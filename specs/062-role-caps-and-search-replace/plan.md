# Implementation Plan: Role & capability CRUD + site-wide DB search-replace

**Branch**: `062-role-caps-and-search-replace` | **Date**: 2026-08-11 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/062-role-caps-and-search-replace/spec.md`

## Summary

Add 8 new abilities to the existing `AcrossAI Abilities Manager` runtime:

- **7 role & capability writers** under the existing `Users/` category (`add-role-capability`, `remove-role-capability`, `create-role`, `delete-role`, `reset-role`, `add-user-capability`, `remove-user-capability`).
- **1 site-wide DB writer** under the existing `Database/` category (`search-replace`) with a **safe-by-default `dry_run: true`** input and serialized-data-aware walking of every affected table.

Every new ability is a subclass of `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition` and follows the existing pattern verbatim: `permission_callback = static function (): bool { return current_user_can( 'manage_options' ); }`, plus input guardrails codified per the spec's FRs (last-admin protection, WordPress-core cap block-list for the administrator role, role-holder count check on delete, `DEFAULT_ROLES` allowlist for reset, `dry_run: true` default + table allowlist for search-replace).

**Technical approach:** zero new modules, zero new database tables, zero new option keys. All 8 abilities are thin adapters over WordPress core functions (`WP_Role::add_cap/remove_cap`, `WP_User::add_cap/remove_cap`, `add_role/remove_role`, `populate_roles`, `$wpdb->get_col/get_results/update`). Bootstrap wiring is a single edit in `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` — 8 new `new Category\Class();` lines placed inside the existing `Users` and `Database` blocks (no new category registrar required).

## Technical Context

**Language/Version**: PHP 8.1+ (per constitution §II; matches the plugin's declared minimum). Class files use PHP 7.4-compatible syntax except where WP core defines PHP 8.1+ types (`WP_Role::add_cap(): void` on 8.1+).
**Primary Dependencies**: WordPress 6.9+; the plugin's existing `Ability_Definition` parent class + WP core Abilities API (`wp_register_ability()`, `wp_register_ability_category()`). No new Composer packages, no new npm packages.
**Storage**: WordPress core option table only. Roles live in the serialized `wp_user_roles` option (already managed by `add_role`/`remove_role`/`populate_roles`). User capability overrides live in per-user meta (already managed by `WP_User::add_cap`/`remove_cap`). `search-replace` reads and writes existing WordPress-managed tables (posts, postmeta, options, etc.); it does not create tables or options of its own.
**Testing**: PHPUnit 10.5 (`composer run test`) — inherited from the plugin's existing test rig. Tests extend `WP_UnitTestCase` and use factories (`$this->factory->user->create()`, direct `add_role()`) exactly as the existing `tests/phpunit/abilities/` files do.
**Target Platform**: WordPress 6.9+ on PHP 8.1 through 8.5 (matches the plugin's existing CI matrix). Single-site and multisite; the `search-replace` and role writes operate on the currently-active site's tables/roles only.
**Project Type**: WordPress plugin — single project (no separate frontend/backend split).
**Performance Goals**: Golden-path role/cap writers complete in under 50 ms on a warmed object cache. `search-replace` in dry-run mode over ~5000 rows across `wp_posts` / `wp_postmeta` / `wp_options` completes in under 30 seconds (per spec SC-002).
**Constraints**: `manage_options` capability gate on every ability. No admin-side JS/UI changes — the abilities appear in the existing Custom Abilities admin table automatically because they inherit `show_in_rest = true` and the plugin's admin UI queries the WP Abilities API.
**Scale/Scope**: 8 new ability classes (~150–250 lines each including docblocks) plus 1 bootstrap edit. ~16 new PHPUnit test methods (8 golden-path + 8 guardrail per spec SC-002 through SC-007). Zero new files under `admin/`, `src/`, or `public/`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Modular Architecture — PASS

Each new ability is a self-contained subclass under an existing category directory (`includes/Abilities/Users/`, `includes/Abilities/Database/`). No cross-module dependencies. No shared state between the new classes and their siblings. Shared logic (permission callback, i18n domain, `Ability_Definition` inheritance) is already extracted; the new classes reuse it verbatim.

### II. WordPress Standards Compliance — PASS

- PHPCS (WPCS strict): the new class files will match the existing formatting of `includes/Abilities/Users/Get_Role_Capabilities.php`, which passes the current CI PHPCS gate. Verified pre-implementation by running `composer run phpcs` against a scaffolded stub before adding logic.
- PHPStan level 8: WordPress-stubs already declares the signatures of every WP core function this feature calls (`WP_Role::add_cap`, `add_role`, `$wpdb->update`, etc.). No new stubs required.
- Plugin Check: no `eval()`, no `extract()`, no shell/process execution, no direct SQL identifier interpolation. `$wpdb->prepare()` is used for every value; the `search-replace` table allowlist mirrors the existing `Update_Db_Rows.php` pattern (validate table names against `$wpdb->get_col('SHOW TABLES')` before any query — this pattern already ships in v0.0.22 and is Plugin Check clean).
- Multisite: the reads/writes target the currently-active site. Documented in Assumptions (spec §Assumptions).

### III. User-Centric Design — PASS (N/A, no admin UI)

This feature adds no admin pages, forms, or listings. The new abilities render automatically in the existing Custom Abilities table via `@wordpress/dataviews` (already governed by DataViews per constitution §III). No new UI code is introduced.

### IV. Security First (NON-NEGOTIABLE) — PASS

- Sanitization at entry: every string input is passed through `sanitize_text_field()` in `execute()` (mirrors `Update_Post_Meta.php:107`).
- Capability check: `permission_callback` gates every ability on `manage_options` verbatim as the existing 219 abilities. This is enforced by WP core before `execute()` runs (via WP Abilities API).
- Nonce verification: not applicable — WP core Abilities API's REST controller (`/wp-json/wp-abilities/v1/*/run`) handles nonce/authentication upstream; the ability's `execute_callback` never receives an unauthenticated request.
- Prepared queries: `search-replace` uses `$wpdb->update()` with format specs (auto-escapes values) and `$wpdb->prepare()` with `%i` for dynamic identifiers per constitution §II. No raw interpolation.
- Guardrails: last-admin protection, WP core administrator-cap block-list, `DEFAULT_ROLES` allowlist for reset, `dry_run: true` default for search-replace, table allowlist for search-replace — every guardrail listed in FR-010 through FR-018.

### V. Extensibility Without Core Modification — PASS

Every new ability lives in a new file under existing category directories. Bootstrap wiring is a single append to `register_abilities()` — no existing methods are refactored, no existing signatures change, no existing constants change. Optional dependencies: none.

### VI. Reusability & DRY Principle — PASS

Reuses:
- `Ability_Definition` parent class → auto-hooks each ability into `acrossai_abilities_api_init`.
- Table-existence check pattern from `Update_Db_Rows.php:156–166` → mirrored in `Search_Replace.php`.
- Role/cap function usage patterns from `Users/Get_Role_Capabilities.php`, `List_User_Roles.php`, `Update_User.php` → coding style matches those files verbatim.
- `sanitize_text_field()` + `__()` i18n discipline → identical to every existing ability.
No duplication introduced; no new utility helpers needed. `CORE_ADMIN_CAPS` constant is hardcoded per-class (Add/Remove Role Capability, Remove User Capability) rather than extracted to a shared utility because it is used only inside those three classes and extracting it would be premature abstraction (per plugin's stated "three similar lines is better than a premature abstraction" preference in CLAUDE.md).

### VII. Definition of Done — PLANNED

Every gate in constitution §VII will be verified before the feature branch is merged:
- PHPCS zero errors / zero warnings via `composer run phpcs`.
- PHPStan level 8 zero errors via `composer run phpstan`.
- ESLint: N/A (no JS changes).
- Security review: sanitization, escaping (not applicable — abilities return arrays that WP core Abilities API serializes as JSON), capability, guardrails — verified per-class.
- Unit tests: 16+ new PHPUnit methods (8 golden-path + 8 guardrail per spec SC breakdown).
- DataForm / DataViews: N/A (no new UI).
- Prefix: all class names + namespaces already conform to the existing `AcrossAI_Abilities_Manager\Includes\Abilities\*` convention.
- `npm run validate-packages`: N/A (no npm dependency changes).

**Overall gate: PASS. No violations, no complexity-tracking entries needed.**

## Project Structure

### Documentation (this feature)

```text
specs/062-role-caps-and-search-replace/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── abilities.md     # Phase 1 output — input/output contract per ability
├── checklists/
│   └── requirements.md  # Written by /speckit-specify
├── spec.md              # Written by /speckit-specify
└── tasks.md             # Written by /speckit-tasks (NOT this command)
```

### Source Code (repository root)

```text
includes/
└── Abilities/
    ├── Users/
    │   ├── Add_Role_Capability.php         # NEW — acrossai/add-role-capability
    │   ├── Remove_Role_Capability.php      # NEW — acrossai/remove-role-capability
    │   ├── Create_Role.php                 # NEW — acrossai/create-role
    │   ├── Delete_Role.php                 # NEW — acrossai/delete-role
    │   ├── Reset_Role.php                  # NEW — acrossai/reset-role
    │   ├── Add_User_Capability.php         # NEW — acrossai/add-user-capability
    │   ├── Remove_User_Capability.php      # NEW — acrossai/remove-user-capability
    │   ├── Category_Registrar.php          # UNCHANGED
    │   ├── Get_Role_Capabilities.php       # UNCHANGED (reference pattern)
    │   ├── List_User_Roles.php             # UNCHANGED (reference pattern)
    │   ├── Update_User.php                 # UNCHANGED (reference pattern)
    │   └── ...                             # 5 other existing files unchanged
    ├── Database/
    │   ├── Search_Replace.php              # NEW — acrossai/search-replace
    │   ├── Update_Db_Rows.php              # UNCHANGED (reference pattern for table allowlist)
    │   ├── Delete_Db_Rows.php              # UNCHANGED (reference pattern)
    │   └── ...                             # 6 other existing files unchanged
    └── AcrossAI_Core_Abilities_Bootstrap.php   # MODIFIED — 8 new instantiation lines

tests/
└── phpunit/
    └── abilities/
        ├── Test_Add_Role_Capability.php        # NEW
        ├── Test_Remove_Role_Capability.php     # NEW
        ├── Test_Create_Role.php                # NEW
        ├── Test_Delete_Role.php                # NEW
        ├── Test_Reset_Role.php                 # NEW
        ├── Test_Add_User_Capability.php        # NEW
        ├── Test_Remove_User_Capability.php     # NEW
        └── Test_Search_Replace.php             # NEW
```

**Structure Decision**: WordPress plugin single-project layout. All feature code lives under `includes/Abilities/{Users,Database}/` next to existing peers. Tests mirror the pattern under `tests/phpunit/abilities/`. No new directories, no `admin/`, `public/`, `src/`, or Composer changes. Zero dependency additions.

## Complexity Tracking

*No entries — Constitution Check passes on every principle with no justifications required.*

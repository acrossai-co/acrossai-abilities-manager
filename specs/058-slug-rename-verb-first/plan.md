# Implementation Plan: Slug rename — namespace to `acrossai/`, suffixes to verb-first

**Branch**: `058-slug-rename-verb-first` | **Date**: 2026-07-25 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/058-slug-rename-verb-first/spec.md`
**Synthesis**: [memory-synthesis.md](memory-synthesis.md)
**PR**: [#88](https://github.com/acrossai-co/acrossai-abilities-manager/pull/88) — three commits (f401e09 word-order flip → bc23e6e namespace + class rename → 88dd7c0 migration removal)

**Note**: This plan is back-filled to document what shipped. `/speckit-plan` was not auto-invoked — per `feedback_user_runs_speckit_commands`, the user runs speckit workflows themselves. This document captures the technical decisions taken during the interactive implementation session.

## Summary

Rename all 219 ability slugs from the pre-0.0.16 mixed subject-first / verb-first form under `acrossai-abilities-manager/` to a uniform `acrossai/<verb>-<subject>` form. Rename 162 corresponding PHP class files and their `class X` declarations to match. Update the client-side prefix constant, server-side prefix injection, byte-count limits, plugin REST namespace, all tests, all docs. Ship with NO data migration (per user directive — plugin has very few users, breaking is acceptable).

Approach in three commits:

1. **f401e09** — Word-order flip under the LONG namespace. 163 slug renames applied via perl look-behind + longest-first sort. Shipped with an auto-migration wired to `activate()` + `admin_init`.
2. **bc23e6e** — Namespace shortening to `acrossai/` + 162 class-file renames to match slugs. Migration extended to handle both prefix + suffix change. Byte-count comments and limits updated to reflect the shorter prefix. Caught one perl false positive: `admin/Main.php::plugin_action_links()` compared the plugin's `plugin_basename` (`'acrossai-abilities-manager/acrossai-abilities-manager.php'`) — a filesystem identifier that started with the pattern being replaced. Reverted via explicit `Edit`.
3. **88dd7c0** — Delete the migration entirely. Plugin is small-user; the mental overhead of a one-shot migration outweighs its value. Users clear old rows manually from the admin UI.

## Technical Context

**Language/Version**: PHP 8.1+ (per Constitution §II), JavaScript (ES2020+ via `@wordpress/scripts` webpack) + JSX. No package.json change.
**Primary Dependencies**: `@wordpress/element`, `@wordpress/data`, `@wordpress/api-fetch`, `@wordpress/i18n`. Composer PSR-4 autoload for PHP class loading. No new composer/npm packages.
**Storage**: Unchanged schema — `{prefix}acrossai_abilities.ability_slug` (varchar 255), `{prefix}abilities_access_control.key` (varchar 255), and sibling MCP plugin's `{prefix}acrossai_mcp_server_abilities.ability_slug`. NO data migration ships — see commit 88dd7c0.
**Testing**: PHPUnit 10.5 via `./vendor/bin/phpunit` (170 tests). Jest via `./node_modules/.bin/jest` (pre-existing ESM-transform failures unrelated to this feature).
**Target Platform**: WordPress 6.9+, PHP 8.1+.
**Project Type**: WordPress plugin — single project.
**Performance Goals**: N/A (bulk rewrite, one-shot).
**Constraints**: No new REST routes / DB tables / composer or npm dependencies. No backwards-compat aliases (users must clear old rows manually).
**Scale/Scope**: 258 files touched across 3 commits. 162 class-file renames, 219 ability registrations rewritten, 1 REST namespace change, 3 places bumped to version 0.0.16.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Verdict | Evidence |
|---|---|---|
| §I Modular Architecture | ✅ Pass | Changes are internal to the Abilities module and its supporting utilities. No cross-module coupling introduced. |
| §II WordPress Standards Compliance | ✅ Pass | PHP 8.1+ / WP 6.9+ floor untouched. Plugin Check surface unchanged. `@wordpress/scripts` build regenerated. |
| §III User-Centric Design (NON-NEGOTIABLE) | ✅ Pass | Admin UI unchanged in behaviour; the Slug input's prefix display was BROKEN pre-0.0.16 and this feature RESTORES the intended split-input UX. No new DataViews/DataForm surface. |
| §IV Security First (NON-NEGOTIABLE) | ✅ Pass | No new REST endpoints. `sanitize_ability_slug()` and validator length checks updated in lockstep with the shorter prefix. Slug values on URL paths continue to be raw pass-through (matches the fix from BUG-COMPOSER-SLUG-ENCODE-STRIPS in Feature 056). |
| §V Extensibility Without Core Modification | ✅ Pass | Ability registration API surface (`wp_register_ability` args shape) unchanged. |
| §VI Reusability & DRY | ✅ Pass | Rename map is a single 163-entry table (`/tmp/slug_map.txt` during implementation, embedded into the migration class then removed). |
| §VII Definition of Done | ✅ Pass | PHPUnit 170/170 green after every commit. `npm run build` regenerated JS assets. `php -l` clean across all 276 ability files and 5 modified core files. |

**Re-check after Phase 1 design**: no new violations. The plugin_basename false positive caught in commit bc23e6e was a mechanical-rewrite artefact, not a design flaw — captured as a new bug pattern below.

## Project Structure

### Documentation (this feature)

```text
specs/058-slug-rename-verb-first/
├── spec.md                      # feature spec (written)
├── memory-synthesis.md          # memory-md synthesis (written)
├── plan.md                      # this file
├── tasks.md                     # task list (written)
└── checklists/
    └── requirements.md          # spec quality checklist (written)
```

No `contracts/` — no new REST endpoints introduced. No `data-model.md` — no schema change. No `research.md` — decisions captured inline in the spec's Motivation section.

### Source Code (repository root)

```text
includes/
├── AcrossAI_Activator.php                          # migration wiring removed (commit 88dd7c0)
├── Main.php                                        # migration admin_init hook removed (commit 88dd7c0); REST namespace constant untouched (uses the module controllers' own consts)
├── Abilities/
│   ├── AcrossAI_Core_Abilities_Bootstrap.php       # 219 `new X\Y();` lines updated to new class names (commit bc23e6e)
│   ├── AdminMenu/    (5 abilities renamed)         # e.g. Admin_Menu_Get_Context.php → Get_Admin_Menu_Context.php
│   ├── Block/        (34 abilities renamed)
│   ├── Cache/        (3 renamed)
│   ├── Comments/     (1 renamed; 10 already verb-first)
│   ├── Content/      (12 renamed; 16 already verb-first)
│   ├── ContentSearch/(11 renamed)
│   ├── Core/         (4 renamed — wp-core-* → *-wp-core)
│   ├── Cron/         (15 renamed — cron-* → *-cron-{job,schedule})
│   ├── Database/     (9 renamed)
│   ├── FileManager/  (14 renamed)
│   ├── Fonts/        (8 renamed)
│   ├── Media/        (3 renamed; 7 already verb-first)
│   ├── Menus/        (2 renamed; 10 already verb-first)
│   ├── Options/      (0 renamed; 5 already verb-first)
│   ├── Plugins/      (10 renamed)
│   ├── Settings/     (10 renamed)
│   ├── SiteHealth/   (3 renamed)
│   ├── Taxonomies/   (2 renamed; 8 already verb-first)
│   ├── Themes/       (9 renamed)
│   ├── Users/        (9 renamed)
│   └── Utilities/    (Plugin_Helpers.php + Theme_Helpers.php cross-refs updated by perl sweep)
├── Modules/
│   └── Abilities/
│       ├── Rest/AcrossAI_Abilities_Rest_Controller.php   # REST_NAMESPACE 'acrossai-abilities-manager/v1' → 'acrossai/v1'
│       ├── Rest/AcrossAI_Abilities_Write_Controller.php  # prefix injection line 215 → 'acrossai/'
│       └── Database/                                    # AcrossAI_Slug_Rename_Migration_058.php created in bc23e6e, DELETED in 88dd7c0
└── Utilities/
    ├── AcrossAI_Abilities_Sanitizer.php                 # byte cap 227 → 246 (255 - 9)
    └── AcrossAI_Abilities_Validator.php                 # full-length check uses 'acrossai/'

src/js/
└── abilities/
    ├── components/AbilityForm.jsx                       # SLUG_PREFIX 'acrossai-abilities/' → 'acrossai/'
    ├── components/AbilitiesList.jsx                     # SLUG_PREFIX 'acrossai-abilities/' → 'acrossai/'
    └── api/client.js                                    # ACL URL pattern preserved (uses 'acrossai-abilities' as ACL namespace, distinct concept)

admin/
└── Main.php                                             # rest_namespace 'acrossai/v1'; plugin_basename check reverted to correct 'acrossai-abilities-manager/acrossai-abilities-manager.php'

tests/phpunit/                                           # 12 test fixtures updated (slug strings + class refs)
tests/jest/                                              # 2 test fixtures updated

docs/
├── FEATURES.md                                          # illustrative slug examples updated
├── memory/ARCHITECTURE.md                               # class file paths updated
├── memory/BUGS.md                                       # illustrative slug examples updated
├── memory/DECISIONS.md                                  # historical DEC entries updated where they cite the REST namespace
└── memory/WORKLOG.md                                    # Feature 046 entry's illustrative slug updated

README.txt                                               # 0.0.16 changelog entry + stable-tag bump
acrossai-abilities-manager.php                           # Version: 0.0.16
uninstall.php                                            # migration marker cleanup removed in commit 88dd7c0

build/js/                                                # regenerated via `npm run build`
```

**Structure Decision**: Single WordPress plugin project. Feature spans PHP source, JS source, tests, docs, and rebuilt JS artefacts. No new top-level directories introduced.

## Phase 0 — Research (retrospective)

Key decisions taken during the interactive session and their rationale:

1. **Namespace `acrossai/` (not `aam/`, not `acrossai-abilities/`)** — matches ecosystem pattern of "plugin-owned single-word namespace" (`core/`, `woocommerce/`, `jetpack/`). Sibling AcrossAI plugins own their own namespaces (`acrossai-buddyboss-abilities/*`, `acrossai-mcp-manager/*`), so `acrossai/*` reserved for THIS plugin poses no collision risk.
2. **Verb-first suffix** — matches the ability's `label` word order ("Get Site Title" ↔ `get-site-title`), matches the WP core MCP adapter built-ins (`mcp-adapter/discover-abilities`, `mcp-adapter/get-ability-info`, `mcp-adapter/execute-ability`), matches every major function-calling / MCP tool-use spec convention.
3. **Class file rename + PHP class rename** — chosen after two-choice presentation to user (keep class names OR flip both slug + class). User picked "point 3" (both), so class names now align with slugs → grep-by-slug finds the registering class immediately.
4. **No data migration ships** — original commit bc23e6e included one; user reversed the decision in commit 88dd7c0 because the plugin is small-user and the mental overhead of a one-shot migration outweighs its value. Users with saved overrides clear them manually.
5. **REST namespace shortened in lockstep** (`acrossai/v1`) — automatic consequence of the perl sweep, kept intentional because it matches the new slug prefix and avoids the awkwardness of `/wp-json/acrossai-abilities-manager/v1/abilities/settings/get-site-title/run`.
6. **ACL library namespace preserved** (`acrossai-abilities` in `/wpb-ac/v1/*/rules/acrossai-abilities/{slug}`) — a separate concept from slug prefix, stored in `wp_abilities_access_control.namespace`. Preserved via perl `(?<!/)` look-behind.

## Phase 1 — Design

### Rename script

`/tmp/rename_slugs.php` (transient) — reads `/tmp/slug_map.txt` (163 `old|new` pairs), for each pair applies a perl regex substitution across a specific file scope (`includes/`, `tests/`, `src/js/`, `admin/`, `docs/memory/`, `README.txt`, `docs/FEATURES.md`). Match pattern: `PREFIX + old` followed by `[^a-zA-Z0-9_\-]|$` — terminator character class prevents shorter old slugs from matching inside longer new ones.

### Class rename script

`/tmp/rename_classes.php` (transient) — for each of 163 mappings:
1. Locate the file that REGISTERS the new slug (grep for `'name' => 'acrossai-abilities-manager/<new>'` pattern in the file, restricted to registration-line match to avoid helper cross-references).
2. Extract current class name from the file via `preg_match('/^(?:final |abstract )?class (\w+)/m')`.
3. Compute new class name from new slug via `implode('_', array_map('ucfirst', explode('-', $newSlug)))`.
4. Rename the file (`rename()`) and update the `class X` declaration inside.
5. Emit `/tmp/class_rename_map.txt` for the second-pass reference sweep.

Second pass — perl one-liner across bootstrap + ability files + tests + docs, using word-boundary `\b` and longest-first alternation to handle prefix collisions (`Cron_Delete` vs `Cron_Delete_All`).

### Namespace sweep

Perl one-liner: `s{(?<!/)acrossai-abilities-manager/}{acrossai/}g` applied to every in-scope file. The `(?<!/)` look-behind skips any occurrence preceded by `/`, preserving filesystem paths (e.g. `/wp-content/plugins/acrossai-abilities-manager/…`) and the ACL library's namespace (`rules/acrossai-abilities/…`).

Caught false positive: `admin/Main.php::plugin_action_links()` line 415 compared the plugin `$file` basename `'acrossai-abilities-manager/acrossai-abilities-manager.php'`. Perl's look-behind treats `'` (the opening quote) as "not `/`", so the first occurrence matched and was wrongly rewritten to `'acrossai/acrossai-abilities-manager.php'`. Fix: explicit `Edit` reverted the false positive.

### Migration (added in bc23e6e, removed in 88dd7c0)

Original migration handled both changes atomically:
- Per-slug UPDATE (163×3 tables): `WHERE ability_slug = OLD_PREFIX+$old` → `SET ability_slug = NEW_PREFIX+$new`.
- Bulk REPLACE (1×3 tables): `WHERE ability_slug LIKE OLD_PREFIX+%` → `SET ability_slug = REPLACE(ability_slug, OLD_PREFIX, NEW_PREFIX)` — catches the 56 unchanged-suffix rows.
- Wrapped in single `START TRANSACTION` / `COMMIT`. Idempotent via option flag.
- Wired at `activate()` (fresh activation) AND `admin_init` (upgrade path).

Removed in commit 88dd7c0 per user directive. No replacement — users clear old rows manually.

## Complexity Tracking

| Deviation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Two commits before the migration removal | Original spec included the migration; user changed their mind mid-implementation. | Squashing after the fact would erase the audit trail of the design decision reversal. Kept the three-commit history for review clarity. |
| Perl look-behind used instead of a Python/PHP AST-based rewriter | Rewrite is over string literals in comments and strings, not code identifiers requiring parsing. Look-behind + explicit false-positive correction is O(seconds) vs O(hours) for AST tooling. | AST tooling would still miss the docstring cases and would need per-language toolchains (PHP + JS + Markdown). Not worth it for one-shot rewrite. |

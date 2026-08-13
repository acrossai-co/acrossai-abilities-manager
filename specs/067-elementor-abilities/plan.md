# Implementation Plan: Elementor Ability Suite

**Branch**: `067-elementor-abilities` | **Date**: 2026-08-13 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/067-elementor-abilities/spec.md`

## Summary

Ship **88 new abilities** giving clients complete Elementor coverage: schema discovery (`get-widget-controls`), raw `_elementor_data` R/W (get/update/patch/clone-data), element-by-ID operations (find/get/update/merge/delete/remove/move/duplicate/reorder), authoring primitives (add-container, add-widget, create-page, update-page-settings, 5 widget shortcuts), full template CRUD + import/export, kit & site-settings management, Theme Builder conditions, cache control, 28 design-audit abilities, and Elementor Pro Custom Code + Form Submissions (8 Pro-gated).

**Technical approach.** All 88 abilities gated on `class_exists( '\Elementor\Plugin' )` at registration and execution time (defense-in-depth). Six shared utility classes under `includes/Abilities/Utilities/Elementor/` centralise document I/O, tree helpers, template queries, widget-schema summarisation, audit orchestration, and static Elementor.com guidance data. Ports the source plugin (`mcp-abilities-elementor`, GPL-2.0+) from procedural closures into AcrossAI's class-per-ability convention (extends `Ability_Definition`, one file per ability, registered via `acrossai_abilities_api_init`).

## Technical Context

**Language/Version**: PHP 8.1+ (per Constitution §II)
**Primary Dependencies**: WordPress 6.9+, WordPress Abilities API, Elementor 3.x+ (`\Elementor\Plugin::instance()`, `->widgets_manager->get_widget_types()`, `->documents`, `->kits_manager`), Elementor Pro (for 8 Pro-gated abilities), existing plugin base classes `Ability_Definition` + `AcrossAI_Ability_Override_Processor`
**Storage**: WordPress `wp_posts.post_content`, `wp_postmeta` (`_elementor_data`, `_elementor_page_settings`, `_elementor_edit_mode`, `_elementor_template_type`, `_elementor_conditions`, `_elementor_version`, `_wp_page_template`), Elementor Kit posts, `elementor_library` CPT, `elementor_snippet` CPT (Pro), Elementor Pro form-submissions table
**Testing**: PHPUnit ^13.2 via `composer test`; source-inspection pattern in `tests/phpunit/abilities/` matching `Test_Block_Tree.php` / `Test_Add_Post_Meta.php`
**Target Platform**: Server-side WordPress plugin (WP 6.9+ / PHP 8.1+ / Elementor 3.x+)
**Project Type**: WordPress plugin — single-project layout under `includes/`
**Performance Goals**: Discovery calls (`get-widget-controls`, `get-official-widget-catalog`) return in <500ms; document reads in <200ms for pages up to 100 elements; writes complete in <500ms (dominated by `wp_update_post`); design-audit aggregators (`evaluate-design`) complete in <3s for pages up to 100 elements (SC-009)
**Constraints**: 100% backward compat with existing 263 abilities (SC-010 baseline suite must continue to pass); zero fatal errors when Elementor is absent (SC-003); 0 partial writes on validation failure (SC-007); 95%+ round-trip fidelity (SC-008); force_replace/force_delete guards on all populated-document destructive writes
**Scale/Scope**: 88 new ability classes + 6 utility classes + 1 category registrar + 88 test files + 6 utility tests = ~101 new files; approximately 17-21K LOC total; total plugin ability surface grows from 263 → 351

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

### I. Modular Architecture — PASS
- 88 ability classes each in their own file under `includes/Abilities/Elementor/`, each registering exactly one ability via `Ability_Definition::push_definition`.
- 6 utility classes under `includes/Abilities/Utilities/Elementor/` centralise every reusable primitive (document I/O, tree ops, template query, widget-schema summary, audit orchestration, guidance catalog) per §I second-use rule.
- No cross-module coupling introduced. All new abilities register via the same `acrossai_abilities_api_init` hook already used by every existing ability.

### II. WordPress Standards Compliance — PASS (PLANNED)
- WPCS strict + PHPStan level 8 + ESLint zero errors enforced via existing `composer phpcs` / `composer phpstan` tooling.
- Plugin Check: zero errors expected — no new SQL (all reads/writes go through `WP_Query`, `get_post_meta`, `update_post_meta`, `wp_insert_post`, `wp_update_post`, `wp_delete_post`), no forbidden functions, no new REST endpoints beyond what the Abilities API auto-provides.
- PHP 8.1+ typed properties and return types throughout.
- All exports use the `acrossai_` prefix (namespace already `AcrossAI_Abilities_Manager\…`).
- No new options or cron; per-post CSS cache invalidation delegated to Elementor's own `\Elementor\Plugin::$instance->files_manager->clear_cache()`.

### III. User-Centric Design (NON-NEGOTIABLE) — N/A
- No UI surface in this feature. Abilities appear in the existing Library UI (managed by `AcrossAI_Ability_Library_Registry`) with no new admin screens. Constitution §III (DataForm/DataViews mandate) does not apply.

### IV. Security First (NON-NEGOTIABLE) — PASS (PLANNED)
- Every write ability enforces `manage_options` + `edit_posts` globally plus `edit_post($post_id)` per-post — matches existing content-ability capability model (FR-036).
- Post-type whitelist blocks internal/uneditable types (FR-037).
- Widget-type validation before mutation (`get-widget-controls` used to validate types before `add-widget`).
- `force_replace=true` / `force_delete=true` guards on all destructive writes against populated documents (FR-040).
- All DB writes flow through `wp_update_post()` / `update_post_meta()` — no custom SQL. `wp_slash()` applied to all JSON-carrying meta writes (FR-038) to preserve JSON escape sequences.
- Runtime Elementor-missing gate returns clean `error_code: elementor_missing` envelope rather than fatal (SC-003).
- Pro abilities additionally gated on Elementor Pro (SC-002).
- Deactivation-mid-session tolerance verified by defense-in-depth per-ability guards (FR-005).

### V. Extensibility Without Core Modification — PASS
- Registration is hook-only (`acrossai_abilities_api_init` filter). No monkey-patching of Elementor or WP core.
- Graceful degradation when Elementor is absent — every ability silently absents itself; category not registered; plugin loads without errors (SC-001, FR-003).
- Third-party Elementor extensions (Jet Engine, Crocoblock, etc.) discoverable via `get-widget-controls` without special-casing — the ability queries the live widget registry at runtime.
- Elementor Pro absence handled with the same graceful pattern for the 8 Pro-only abilities.

### VI. Reusability & DRY Principle — PASS
- 6 shared utilities extract every reusable primitive on second use per §VI. First use is inline in the source plugin; after this port, every AcrossAI Elementor ability consumes the shared utility.
- Category registrar mirrors `Block/Category_Registrar.php` — same pattern, different slug.
- Ability class scaffolding follows the Feature 066 template (extends `Ability_Definition`, one file per ability).

### VII. Definition of Done — PASS (PLANNED)
- PHPCS/PHPStan/ESLint: zero errors (checked at commit).
- PHPUnit: 88 new source-inspection tests + 6 utility tests + integration coverage for the 4 P1 workhorse abilities (get-widget-controls, get-data, update-element, add-widget).
- Security review: covered by the standard `after_plan` and `after_implement` security-review extension hooks.
- Full 577-test baseline suite continues to pass (SC-010).
- `acrossai_` prefix on every registered ability and global function (already the plugin-wide convention).
- Changelog entry added to `readme.txt` at 0.0.25 release.

**Gate result**: PASS on all seven principles. No violations require justification.

## Project Structure

### Documentation (this feature)

```text
specs/067-elementor-abilities/
├── plan.md              # This file (/speckit-plan output)
├── research.md          # Phase 0 — 10 decisions on gating idiom, force-guard idiom, Pro-detect strategy, response envelope, wp_slash policy, widget-schema output shape, Elementor version compat, cache-invalidation strategy, category naming, slug convention
├── data-model.md        # Phase 1 — 9 entities (Document, Element, Widget Type, Template, Kit, Theme Builder Condition, Custom Code Snippet, Form Submission, Response Envelope)
├── quickstart.md        # Phase 1 — end-to-end walkthrough building a page, updating, moving, templating, kit-editing, running audits, Pro flows
├── contracts/
│   └── abilities.md     # Phase 1 — 88 ability contracts (input/output JSON schemas + error codes), grouped by tier
├── checklists/
│   └── requirements.md  # Spec-quality checklist (already generated by /speckit-specify)
├── spec.md              # Feature spec (/speckit-specify output)
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
includes/
├── Abilities/
│   ├── Ability_Definition.php                  # existing base class (no change)
│   ├── AcrossAI_Core_Abilities_Bootstrap.php   # MODIFY — register new category + conditionally instantiate 88 abilities
│   ├── Block/ / Content/ / etc.                # existing domains (no change)
│   ├── Elementor/                              # NEW DOMAIN
│   │   ├── Category_Registrar.php              # NEW — mirrors Block/Category_Registrar.php
│   │   └── *.php                               # NEW — 88 ability classes (80 free + 8 Pro-gated)
│   └── Utilities/
│       ├── Block_Info.php / Block_Tree.php     # existing (no change)
│       └── Elementor/                          # NEW UTILITY DIRECTORY
│           ├── Document_Repository.php         # NEW — _elementor_data load/save + tree helpers
│           ├── Template_Query.php              # NEW — elementor_library CPT query + pattern scoring
│           ├── Widget_Controls.php             # NEW — widget control summarisation
│           ├── Guidance_Catalog.php            # NEW — static Elementor.com guidance data
│           └── Design_Audit_Runner.php         # NEW — audit orchestrator for evaluate-design / suggest-design-fixes

tests/
└── phpunit/
    └── abilities/
        ├── Test_Elementor_<Ability_Class>.php  # NEW — 88 source-inspection test files
        └── Test_Elementor_<Utility>.php        # NEW — 6 utility test files
```

**Structure Decision**: WordPress plugin single-project layout — the plugin already organises abilities under `includes/Abilities/<Domain>/` (Block, Content, Cache, Comments, Content, Cron, Database, FileManager, Fonts, Media, Menus, Options, Plugins, Recovery, Settings, SiteHealth, Taxonomies, Themes, Users, Widgets, AdminMenu, Core, ContentSearch) with utilities under `includes/Abilities/Utilities/`. New abilities and utilities follow that established layout with `Elementor/` treated as a first-class domain (mirroring `Block/`) rather than under `Integrations/`.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

_Constitution Check passed all seven principles. No violations to justify._

_Scope note: this feature ships 88 abilities (5× Feature 066's size). Justified because the abilities are structurally uniform (extends same base class, uses same 6 utilities), all covered by shared source-inspection test infrastructure, and split into safer functional groups by the phased task list from `/speckit-tasks`. The 88-ability scope was explicitly locked by the user during plan-mode approval._

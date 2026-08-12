# Implementation Plan: Block Tree Mutation & Nested Editing

**Branch**: `066-block-tree-mutation-and-nested-editing` | **Date**: 2026-08-12 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/066-block-tree-mutation-and-nested-editing/spec.md`

## Summary

Provide complete read/write control over the Gutenberg block tree inside a WordPress post — including nested blocks — via six new abilities (`get-post-blocks`, `add-block`, `remove-block`, `duplicate-block`, `move-block`, `insert-pattern`) plus an enhancement to the existing `update-post-block` ability that accepts a canonical path input for nested addressing while preserving 100% backward compatibility with existing consumers.

**Technical approach.** One shared utility class (`includes/Abilities/Utilities/Block_Tree.php`) implements the canonical block-path scheme (an ordered array of zero-based integers) and provides pure tree primitives — walk, get-at-path, insert-at-path, remove-at-path, replace-at-path, move — plus block-name validation and attribute-schema validation. Every new and modified ability consumes this utility rather than duplicating tree logic. Wire-level shape mirrors existing ability responses (`{ success, <payload>, message }`) and every ability registers via the existing `Ability_Definition` → `acrossai_abilities_api_init` → `AcrossAI_Core_Abilities_Bootstrap` pipeline used by every other content ability.

## Technical Context

**Language/Version**: PHP 8.1+ (per Constitution §II)
**Primary Dependencies**: WordPress 6.9+, WordPress Abilities API (`wp_register_ability`), WordPress core block functions (`parse_blocks`, `serialize_blocks`, `WP_Block_Type_Registry`), existing plugin base class `Ability_Definition`, existing `Block_Info` utility
**Storage**: WordPress `wp_posts.post_content` (round-tripped through core block parser); no new tables, no new options
**Testing**: PHPUnit ^13.2 via `composer test`; existing source-inspection pattern in `tests/phpunit/abilities/`; integration tests via `WP_UnitTestCase` with `factory->post->create_and_get()` fixtures
**Target Platform**: Server-side WordPress plugin (any hosting environment WP 6.9+ / PHP 8.1+ supports)
**Project Type**: WordPress plugin — single-project layout under `includes/`
**Performance Goals**: Read of a post's block tree returns in <100ms for posts up to 500 total blocks (typical realistic ceiling); write operations complete in <200ms (dominated by `wp_update_post` DB write)
**Constraints**: Must preserve `post_content` round-trip fidelity where `parse_blocks`/`serialize_blocks` allow (95%+ per SC-004); 0 partial writes on validation failure (SC-005); no breaking change to existing `update-post-block` inputs (SC-003)
**Scale/Scope**: 6 new ability classes + 1 utility class + 1 modified ability class + 7 new PHPUnit test files; approximately 800-1200 LOC total across ability classes; utility ~300-400 LOC

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

### I. Modular Architecture — PASS
- Six new ability classes live alongside the existing `Update_Post_Block` under `includes/Abilities/Content/` — each is a self-contained module registering one ability. The shared `Block_Tree` utility centralises tree logic per §I ("extract to `includes/Utilities/` on second use") — this is the second use of block-tree walking (first use is inside `Update_Post_Block::execute`).
- No cross-module coupling introduced; new abilities register via the same `acrossai_abilities_api_init` hook as every existing ability.

### II. WordPress Standards Compliance — PASS (PLANNED)
- WPCS strict + PHPStan level 8 + ESLint zero errors: enforced via existing `composer phpcs`, `composer phpstan`, and ESLint tooling.
- Plugin Check: zero errors expected — no new SQL, no new forbidden functions, no new AJAX/REST surfaces beyond what the Abilities API auto-provides.
- PHP 8.1+ typed properties and return types throughout.
- All exports use the `acrossai_` prefix (namespace already `AcrossAI_Abilities_Manager\…`).
- No new options, transients, cron, or file writes.

### III. User-Centric Design (NON-NEGOTIABLE) — N/A
- No UI surface in this feature. Abilities are consumed by clients over the Abilities API. Constitution §III (DataForm/DataViews mandate) does not apply.

### IV. Security First (NON-NEGOTIABLE) — PASS (PLANNED)
- Every write ability enforces the same capability model as the existing `Update_Post_Block::execute` — global `manage_options` + `edit_posts`, plus per-post `edit_post($post_id)` (FR-018).
- Block-name regex validation before mutation (FR-008).
- Attribute-schema validation against registered block type when available (FR-020).
- Post-type whitelist blocks internal/uneditable types (FR-019).
- All DB writes flow through `wp_update_post()` — no custom SQL, no direct `$wpdb->query()`.
- Read ability requires post-read capability to prevent leaking private content.

### V. Extensibility Without Core Modification — PASS
- Registration is hook-only (`acrossai_abilities_api_init`). No monkey-patching of core.
- Graceful degradation: when a block type is not registered, attribute-schema validation warns but does not hard-fail — client can still author custom blocks (FR-020).
- Third-party block registrations work through the same interface without special-casing.

### VI. Reusability & DRY Principle — PASS
- `Block_Tree` extracts tree-walking on second use per §VI ("extract to `includes/Utilities/` on second use"). First use lives inside `Update_Post_Block::execute` (private inline logic); after this feature, both `Update_Post_Block` and the six new abilities consume the shared utility.
- Pattern-source resolution reused from the existing `Read_Block_Pattern` helper — no duplicate source-scanning (per feature spec assumption 4).
- Block-name regex validation moves from a private constant in `Update_Post_Block` into `Block_Tree::validate_block_name` — single source of truth.

### VII. Definition of Done — PASS (PLANNED)
- PHPCS/PHPStan/ESLint: zero errors (checked at commit).
- PHPUnit: one test file per new ability plus one for `Block_Tree` primitives — 8 new files.
- Security review: covered by the standard `after_plan` and `after_implement` hooks in `.specify/extensions.yml`.
- No DRY violations (see §VI).
- `acrossai_` prefix on every registered ability and global function (already the plugin-wide convention).

**Gate result**: PASS on all principles. No violations require justification.

## Project Structure

### Documentation (this feature)

```text
specs/066-block-tree-mutation-and-nested-editing/
├── plan.md              # This file (/speckit-plan output)
├── research.md          # Phase 0 output — path scheme, atomicity, pattern-source reuse
├── data-model.md        # Phase 1 output — Block / Block path / response envelope entities
├── quickstart.md        # Phase 1 output — canonical read → mutate → verify walkthrough
├── contracts/
│   └── abilities.md     # Ability contracts (input/output schemas per ability)
├── checklists/
│   └── requirements.md  # Spec-quality checklist (already generated by /speckit-specify)
├── spec.md              # Feature spec (/speckit-specify output)
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
includes/
├── Abilities/
│   ├── Ability_Definition.php                 # existing base class (no change)
│   ├── AcrossAI_Core_Abilities_Bootstrap.php  # MODIFY — register 6 new ability classes
│   ├── Content/
│   │   ├── Update_Post_Block.php              # MODIFY — accept optional `path` input; delegate to Block_Tree
│   │   ├── Get_Post_Blocks.php                # NEW — read ability
│   │   ├── Add_Block.php                      # NEW — write ability
│   │   ├── Remove_Block.php                   # NEW — write ability
│   │   ├── Duplicate_Block.php                # NEW — write ability
│   │   ├── Move_Block.php                     # NEW — write ability
│   │   └── Insert_Pattern.php                 # NEW — write ability (reuses Read_Block_Pattern's source resolver)
│   ├── Block/                                 # existing block-registry abilities (no change)
│   └── Utilities/
│       ├── Block_Info.php                     # existing (no change) — used for schema lookup
│       └── Block_Tree.php                     # NEW — shared tree-path utility

tests/
└── phpunit/
    └── abilities/
        ├── Test_Get_Post_Blocks.php           # NEW
        ├── Test_Add_Block.php                 # NEW
        ├── Test_Remove_Block.php              # NEW
        ├── Test_Duplicate_Block.php           # NEW
        ├── Test_Move_Block.php                # NEW
        ├── Test_Insert_Pattern.php            # NEW
        ├── Test_Update_Post_Block_Nested.php  # NEW — nested-path branch coverage
        └── Test_Block_Tree.php                # NEW — utility primitives
```

**Structure Decision**: WordPress plugin single-project layout — the plugin already organises abilities under `includes/Abilities/<Category>/` with utilities under `includes/Abilities/Utilities/`. New abilities and the new utility follow that established layout with no new top-level directories. Tests use the existing PHPUnit convention under `tests/phpunit/abilities/`.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

_Constitution Check passed all seven principles. No violations to justify._

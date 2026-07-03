---
description: "Task list for Feature 041 — Library display fields under meta.acrossai"
---

# Tasks: Library display fields under `$args['meta']['acrossai']`

**Input**: Design documents from `/specs/041-library-fields-meta-acrossai-namespace/`
**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [memory-synthesis.md](./memory-synthesis.md)

**Tests**: Test count changes from 103 → 105. Two new negative regression tests added to `Test_Ability_Definition.php`. Two existing "allowlist passthrough" tests rewritten to assert the post-041 hard-cut behavior.

**Organization**: Tasks grouped by phase; each task has an exact target file + verification step.

## Format: `[ID] [P?] Description`

- **[P]**: Can run in parallel (different files, no dependencies).

## Phase 1: Setup

- [x] **T001** Confirm branch `041-library-fields-meta-acrossai-namespace` created from `main` post-0.0.3 tag. Working tree clean.

## Phase 2: Foundational (Blocking Prerequisites)

- [x] **T002** Grep audit — in-plugin `Ability_Definition` subclasses:
  ```
  grep -rEn 'extends\s+Ability_Definition|extends Ability_Definition' includes/ admin/ --include='*.php'
  ```
  Zero hits confirmed. Abstract base is purely for external add-on developers; refactor is fully self-contained.

- [x] **T003** Grep audit — existing test fixtures + assertions referencing the three fields:
  ```
  grep -lrE "'sub_group'|'tab_group'|'sub_group_label'" tests/ --include='*.php'
  ```
  Files identified: `Test_Ability_Definition.php`, `Test_Ability_Library_Registry.php`, `Test_Ability_Library_Config.php` (Config only has one occurrence documenting the stray-field example — no update needed).

**Checkpoint**: Refactor scope confirmed as 2 code files + 2 test files + 4 memory files + 4 spec-kit files.

## Phase 3: Code Refactor

- [x] **T004** Edit `includes/Modules/Library/Ability_Definition.php`:
  - Update class-level docblock (lines ~22-27) to document the canonical `$args['meta']['acrossai']` shape.
  - Rewrite `push_definition()` extraction block: replace direct reads of `$args['sub_group']`, `$args['tab_group']`, `$args['sub_group_label']` with reads via a `$meta_acrossai` intermediate variable extracted from `$args['meta']['acrossai']`.
  - `is_array()` guard on `$args['meta']['acrossai']` before extraction.
  - Preserve exact `$row` shape — no downstream changes.

- [x] **T005** Edit `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`:
  - Remove three entries from `ALLOWED_ARGS_FIELDS`: `'sub_group'`, `'sub_group_label'`, `'tab_group'`.
  - Update the constant's docblock to reference Feature 041 and explain that `meta.acrossai.*` sub-keys pass through the `'meta'` allowlist entry.

## Phase 4: Test Fixture Migration

- [x] **T006** Edit `tests/phpunit/Modules/Library/Test_Ability_Definition.php`:
  - Migrate all fixture `ability()` return arrays: move `sub_group` / `sub_group_label` / `tab_group` from top-level of `args` into `args.meta.acrossai`.
  - Add new negative test: `test_push_definition_ignores_top_level_sub_group_pre_041_shape()` — asserts that a fixture using the legacy top-level shape produces a row with NO `sub_group` / `sub_group_label` / `tab_group` at row-top.
  - Add new precedence test: `test_push_definition_reads_only_from_meta_acrossai_when_both_shapes_present()` — asserts that when both shapes co-exist, only `meta.acrossai` values reach the row.

- [x] **T007** Edit `tests/phpunit/Modules/Library/Test_Ability_Library_Registry.php`:
  - Update class-level docblock to reference Feature 041 and the new hard-cut behavior.
  - Migrate mid-file fixture `args` sub-arrays (in `test_registry_accepts_sub_group_and_derives_label` + `test_registry_accepts_tab_group`) to nested `meta.acrossai` shape for realism.
  - Rewrite `test_registry_allows_sub_group_in_args_allowlist` → `test_registry_strips_top_level_sub_group_from_args_post_041`: assert that top-level `sub_group`/`sub_group_label` in args are STRIPPED and `meta.acrossai` in args SURVIVES via the `meta` allowlist entry.
  - Rewrite `test_registry_allows_tab_group_in_args_allowlist` → `test_registry_strips_top_level_tab_group_from_args_post_041`: same inversion for `tab_group`.

## Phase 5: Quality Gates

- [x] **T008** [P] `composer test` — PHPUnit runs cleanly. Test count 103 → 105 (two new negative tests in T006). Zero failures, zero errors.

- [x] **T009** [P] `composer phpstan` — PHPStan level 8 clean, zero errors.

- [x] **T010** [P] `composer phpcs` — WPCS strict clean, zero errors / zero warnings. 45/45 files.

## Phase 6: Spec-Kit Artefacts + Memory Hygiene

- [x] **T011** Create `specs/041-library-fields-meta-acrossai-namespace/spec.md` — 8 FRs, 6 SCs, 2 user stories, 5 edge cases.

- [x] **T012** Create `specs/041-library-fields-meta-acrossai-namespace/plan.md` — Constitution check (all pass; §V and §VI strengthened), technical context, phase-0 research findings, phase-1 design summary.

- [x] **T013** Create `specs/041-library-fields-meta-acrossai-namespace/tasks.md` — this file.

- [x] **T014** Create `specs/041-library-fields-meta-acrossai-namespace/memory-synthesis.md` — synthesized memory pointers relevant to the refactor.

- [x] **T015** Edit `docs/memory/ARCHITECTURE.md` — add `PATTERN-META-ACROSSAI-NAMESPACE` entry documenting the new canonical namespace for plugin-specific ability fields. Add forward-pointer note to `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH` noting Feature 041 changed the canonical input shape without altering the Registry-boundary sanitize discipline.

- [x] **T016** Edit `docs/memory/DECISIONS.md` — add `DEC-META-ACROSSAI-NAMESPACE` entry documenting the hard-cut decision + rationale.

- [x] **T017** Edit `docs/memory/WORKLOG.md` — add Feature 041 milestone entry (top-of-file per WORKLOG convention).

- [x] **T018** Edit `docs/memory/INDEX.md` — add three new routing rows: PATTERN row under `## Implementation Patterns`; DEC row under `## Active Decisions`; WORKLOG row under `## Worklog Milestones (continued)`.

## Phase 7: Verification + Merge

- [x] **T019** Full-repo grep audit before merge:
  ```
  grep -rEn "args\['sub_group'\]|args\['tab_group'\]|args\['sub_group_label'\]" includes/ admin/ tests/ --include='*.php'
  ```
  Expected: only occurrences remaining are inside migration comments (Ability_Definition.php `push_definition()` refactor note) and negative-regression tests (Test_Ability_Definition.php `test_push_definition_ignores_top_level_sub_group_pre_041_shape` + `test_push_definition_reads_only_from_meta_acrossai_when_both_shapes_present`).

- [ ] **T020** Manual Library UI walkthrough on a test install:
  - Register an ability via the new nested shape → confirm sub-group heading + tab-group tab render.
  - Register an ability via the OLD top-level shape → confirm ability card appears WITHOUT a sub-group heading (proves hard cut).
  - Confirm no PHP notices / no JS console errors.

- [x] **T021** Push branch to origin and open PR against `main`.

**Final Checkpoint**: All quality gates green; grep audit passes; manual walkthrough (T020) pending pre-merge validation.

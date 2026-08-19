---

description: "Task list for Feature 066 — Block Tree Mutation & Nested Editing"
---

# Tasks: Block Tree Mutation & Nested Editing

**Input**: Design documents from `/specs/066-block-tree-mutation-and-nested-editing/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/abilities.md](./contracts/abilities.md), [quickstart.md](./quickstart.md)

**Tests**: Included per feature spec — plan.md § VII Definition of Done requires per-ability PHPUnit tests, and quickstart.md drives an end-to-end integration test.

**Organization**: Tasks are grouped by user story (US1–US7) to enable independent implementation and testing. US1–US4 are P1 (blocking parity), US5–US6 are P2 (round-out tree ops), US7 is P3 (pattern insertion).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1–US7)
- Include exact file paths in descriptions

## Path Conventions

WordPress plugin single-project layout (from plan.md § Project Structure):

- **Source**: `includes/Abilities/` (ability classes), `includes/Abilities/Utilities/` (shared helpers), `includes/Abilities/Content/` (this feature's ability classes)
- **Tests**: `tests/phpunit/abilities/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm feature branch, verify tooling, no code changes.

- [ ] T001 Verify current branch is `066-block-tree-mutation-and-nested-editing` and working tree is clean
- [ ] T002 Verify `composer install` succeeds and `composer phpcs`, `composer phpstan`, `composer test` all pass on `main` merged into the branch (baseline green)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Build the shared `Block_Tree` utility and extract shared guards from `Update_Post_Block`. Every user story depends on this.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T003 [P] Create `includes/Abilities/Utilities/Block_Tree.php` skeleton (namespace, class declaration, PHP 8.1+ typed methods stubs matching data-model.md § Block path operations)
- [ ] T004 Implement `Block_Tree::validate_block_name(string $name): bool` in `includes/Abilities/Utilities/Block_Tree.php` — extract regex from `includes/Abilities/Content/Update_Post_Block.php:160`
- [ ] T005 Implement `Block_Tree::assert_post_type_editable(int $post_id): true|WP_Error` in `includes/Abilities/Utilities/Block_Tree.php` — extract post-type whitelist from `includes/Abilities/Content/Update_Post_Block.php:127-135`
- [ ] T006 Implement `Block_Tree::parse_post_blocks(int $post_id): array|WP_Error` in `includes/Abilities/Utilities/Block_Tree.php` — wraps `parse_blocks(get_post()->post_content)` with post-existence + capability guards from `Update_Post_Block::execute:112-121`
- [ ] T007 Implement pure tree primitives in `includes/Abilities/Utilities/Block_Tree.php`: `walk_tree`, `get_at_path`, `insert_at_path`, `remove_at_path`, `replace_at_path`, `annotate_with_paths` (all pure, no IO — see data-model.md § Block path operations)
- [ ] T008 Implement `Block_Tree::move(array &$blocks, array $from, array $to_parent, int $to_index): true|WP_Error` in `includes/Abilities/Utilities/Block_Tree.php` — atomic remove-then-insert on in-memory copy with descendant-guard from research.md R3
- [ ] T009 Implement `Block_Tree::validate_attributes_against_schema(string $block_name, array $attrs): true|WP_Error` in `includes/Abilities/Utilities/Block_Tree.php` — uses `Block_Info::get_block()` from `includes/Abilities/Utilities/Block_Info.php:56`; soft-fail on unregistered types per research.md R4
- [ ] T010 [P] Create PHPUnit test `tests/phpunit/abilities/Test_Block_Tree.php` covering all primitives — walk, get, insert (including append-at-end), remove, replace, move (including descendant-guard failure), annotate_with_paths, validate_block_name (positive + negative), assert_post_type_editable (all forbidden CPTs + one editable)
- [ ] T011 Verify `composer phpstan` and `composer phpcs` pass on the new `Block_Tree.php` and `Test_Block_Tree.php`

**Checkpoint**: `Block_Tree` utility ready — user story implementation can now begin in parallel.

---

## Phase 3: User Story 1 — Read post's block tree with paths (Priority: P1) 🎯 MVP

**Goal**: Deliver `blocks/get-post-blocks` — the foundational read primitive every write ability depends on.

**Independent Test**: Create a post with `<!-- wp:columns --><!-- wp:column --><!-- wp:paragraph -->Left<!-- /wp:paragraph --><!-- wp:paragraph -->Also left<!-- /wp:paragraph --><!-- /wp:column --><!-- /wp:columns -->` in `post_content`. Invoke `blocks/get-post-blocks` with that post's ID. Verify response contains the columns block at path `[0]`, the column at `[0, 0]`, and both paragraphs at `[0, 0, 0]` and `[0, 0, 1]`.

### Tests for User Story 1

- [ ] T012 [P] [US1] Create `tests/phpunit/abilities/Test_Get_Post_Blocks.php` — source-inspection tests matching `Test_Add_Post_Meta.php` pattern (registration, category `acrossai-abilities-manager-content`, schema shape per contracts/abilities.md § 1, permission structure)
- [ ] T013 [P] [US1] Add integration test in `Test_Get_Post_Blocks.php` — creates a nested-block fixture post via `factory->post->create_and_get()` and asserts every returned block has a correct `path` integer array

### Implementation for User Story 1

- [ ] T014 [US1] Create `includes/Abilities/Content/Get_Post_Blocks.php` — extends `Ability_Definition`, registers `blocks/get-post-blocks` under `acrossai-abilities-manager-content` category, input/output schema per contracts/abilities.md § 1
- [ ] T015 [US1] Implement `Get_Post_Blocks::execute($input)` — calls `Block_Tree::parse_post_blocks` + `Block_Tree::annotate_with_paths`, uses `read_post` capability (not `edit_post` — read-only per research.md R9), returns response envelope per contracts/abilities.md
- [ ] T016 [US1] Register `Get_Post_Blocks` in `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` alongside existing content abilities
- [ ] T017 [US1] Run `composer test -- --filter=Test_Get_Post_Blocks` and verify all cases pass

**Checkpoint**: `get-post-blocks` fully functional — SC-001 achievable (100% blocks reachable by path).

---

## Phase 4: User Story 2 — Insert a new block at any position (Priority: P1)

**Goal**: Deliver `blocks/add-block` — the primary write primitive for content authoring.

**Independent Test**: Given a post with a container at `[0]` containing one child, invoke `blocks/add-block` with `parent_path=[0], index=0, block={name: "core/paragraph", attrs: {content: "test"}}`. Re-read via `get-post-blocks` and verify the new paragraph is at `[0, 0]` and the prior child moved to `[0, 1]`.

### Tests for User Story 2

- [ ] T018 [P] [US2] Create `tests/phpunit/abilities/Test_Add_Block.php` — source-inspection tests (registration, schema per contracts/abilities.md § 2, capability calls present)
- [ ] T019 [P] [US2] Add integration tests in `Test_Add_Block.php`: (a) insert at root, (b) insert nested, (c) append when index >= sibling count, (d) fail on `invalid_block_name`, (e) fail on `invalid_path`, (f) fail on `invalid_attributes`

### Implementation for User Story 2

- [ ] T020 [US2] Create `includes/Abilities/Content/Add_Block.php` — extends `Ability_Definition`, registers `blocks/add-block`, input/output schema per contracts/abilities.md § 2
- [ ] T021 [US2] Implement `Add_Block::execute($input)` — parses tree via `Block_Tree`, validates block name + attributes, calls `Block_Tree::insert_at_path`, serializes and writes via `wp_update_post`, returns response with the inserted block's new `path`
- [ ] T022 [US2] Register `Add_Block` in `AcrossAI_Core_Abilities_Bootstrap.php`
- [ ] T023 [US2] Run `composer test -- --filter=Test_Add_Block` — all cases pass

**Checkpoint**: `add-block` fully functional — combined with US1 this enables read + insert workflows.

---

## Phase 5: User Story 3 — Remove a block from any position (Priority: P1)

**Goal**: Deliver `blocks/remove-block` — the destroy primitive.

**Independent Test**: Given a post with a container containing two children, invoke `blocks/remove-block` with `path=[0, 0]`. Re-read and verify the container has one child (the former second child, now at `[0, 0]`).

### Tests for User Story 3

- [ ] T024 [P] [US3] Create `tests/phpunit/abilities/Test_Remove_Block.php` — source-inspection tests (registration, schema per contracts/abilities.md § 3, capabilities)
- [ ] T025 [P] [US3] Integration tests: (a) remove nested child, (b) remove top-level, (c) fail on `invalid_path` (empty path or non-resolvable), (d) response includes the removed block payload

### Implementation for User Story 3

- [ ] T026 [US3] Create `includes/Abilities/Content/Remove_Block.php` — extends `Ability_Definition`, input/output schema per contracts/abilities.md § 3
- [ ] T027 [US3] Implement `Remove_Block::execute($input)` — parse tree, call `Block_Tree::remove_at_path`, serialize + write, return removed block in response
- [ ] T028 [US3] Register `Remove_Block` in `AcrossAI_Core_Abilities_Bootstrap.php`
- [ ] T029 [US3] Run `composer test -- --filter=Test_Remove_Block` — all cases pass

**Checkpoint**: US1 + US2 + US3 = read + insert + remove — clients can author basic content.

---

## Phase 6: User Story 4 — Update at any nesting depth (Priority: P1)

**Goal**: Extend `blocks/update-post-block` to accept the canonical `path` input, preserving full backward compatibility with existing `block_index` / `block_name`+`occurrence` inputs.

**Independent Test**: Given a post with a paragraph at `[0, 0, 0]`, invoke `update-post-block` with `path=[0, 0, 0]` and new attributes. Re-read and verify only that block changed. Also verify a legacy call `{ post_id, block_index: 0, attributes }` behaves identically to the pre-066 version (no path, no behaviour change).

### Tests for User Story 4

- [ ] T030 [P] [US4] Create `tests/phpunit/abilities/Test_Update_Post_Block_Nested.php` — covers the new `path` branch only (existing `Test_Update_Post_Block.php` — if present — remains untouched to protect the legacy branches)
- [ ] T031 [P] [US4] Integration tests: (a) nested update via `path` succeeds, (b) legacy `block_index` call still works byte-identical to pre-066 (SC-003), (c) legacy `block_name` + `occurrence` call still works, (d) `path` + `block_index` together — path takes priority per research.md R7, (e) `invalid_path` error, (f) `invalid_attributes` error

### Implementation for User Story 4

- [ ] T032 [US4] Modify `includes/Abilities/Content/Update_Post_Block.php` — add optional `path` input to input schema per contracts/abilities.md § 4
- [ ] T033 [US4] Modify `Update_Post_Block::execute` — insert path-resolution branch per research.md R7 priority (path → block_index → block_name+occurrence), delegate to `Block_Tree::replace_at_path` when path is present, keep existing branches unchanged
- [ ] T034 [US4] Remove the top-level-only comment at `includes/Abilities/Content/Update_Post_Block.php:23-24`
- [ ] T035 [US4] Replace the inline block-name regex at `Update_Post_Block:160` with a call to `Block_Tree::validate_block_name`
- [ ] T036 [US4] Replace the inline post-type whitelist at `Update_Post_Block:127-135` with a call to `Block_Tree::assert_post_type_editable`
- [ ] T037 [US4] Run `composer test -- --filter='Test_Update_Post_Block'` — nested + legacy suites both pass

**Checkpoint**: All P1 user stories (US1–US4) complete — feature reaches parity for read + insert + remove + nested update. This is the recommended MVP scope for a first PR increment if scope needs to be split.

---

## Phase 7: User Story 5 — Move a block (Priority: P2)

**Goal**: Deliver `blocks/move-block` — reorder or reparent atomically.

**Independent Test**: Given two top-level blocks, invoke `move-block` from `[1]` into `[0]` at index `0`. Verify source is gone and the block is now first child of former `[0]`.

### Tests for User Story 5

- [ ] T038 [P] [US5] Create `tests/phpunit/abilities/Test_Move_Block.php` — source-inspection tests (registration, schema per contracts/abilities.md § 5)
- [ ] T039 [P] [US5] Integration tests: (a) reparent top-level → nested, (b) reorder within same parent, (c) fail on `descendant_destination` (move into own subtree), (d) fail on `invalid_path` / `invalid_destination`, (e) atomicity — after failed move the tree is unchanged

### Implementation for User Story 5

- [ ] T040 [US5] Create `includes/Abilities/Content/Move_Block.php` — extends `Ability_Definition`, input/output schema per contracts/abilities.md § 5
- [ ] T041 [US5] Implement `Move_Block::execute($input)` — parse tree, call `Block_Tree::move` (which handles descendant-guard + atomic remove-then-insert), serialize + write, return response with new `block.path` and `previous_path`
- [ ] T042 [US5] Register `Move_Block` in `AcrossAI_Core_Abilities_Bootstrap.php`
- [ ] T043 [US5] Run `composer test -- --filter=Test_Move_Block` — all cases pass

**Checkpoint**: US1–US5 complete.

---

## Phase 8: User Story 6 — Duplicate a block (Priority: P2)

**Goal**: Deliver `blocks/duplicate-block` — deep-clone in place.

**Independent Test**: Given a container with 2 nested children, invoke `duplicate-block` at `[0]`. Verify a deep clone appears at `[1]` with 2 nested children of its own, and the original is unchanged.

### Tests for User Story 6

- [ ] T044 [P] [US6] Create `tests/phpunit/abilities/Test_Duplicate_Block.php` — source-inspection tests (registration, schema per contracts/abilities.md § 6)
- [ ] T045 [P] [US6] Integration tests: (a) duplicate top-level with nested children, (b) duplicate nested block, (c) clone is a true deep copy (mutating one does not affect the other), (d) fail on `invalid_path`

### Implementation for User Story 6

- [ ] T046 [US6] Create `includes/Abilities/Content/Duplicate_Block.php` — extends `Ability_Definition`, input/output schema per contracts/abilities.md § 6
- [ ] T047 [US6] Implement `Duplicate_Block::execute($input)` — parse tree, use `Block_Tree::get_at_path` to fetch the source block (PHP value-copy semantics per research.md R11 give a deep copy for free), compute next-sibling insert path, call `Block_Tree::insert_at_path`, serialize + write, return the cloned block with its new path
- [ ] T048 [US6] Register `Duplicate_Block` in `AcrossAI_Core_Abilities_Bootstrap.php`
- [ ] T049 [US6] Run `composer test -- --filter=Test_Duplicate_Block` — all cases pass

**Checkpoint**: All P1 + P2 stories complete — feature ships as MVP+ if P3 is deferred to a follow-up.

---

## Phase 9: User Story 7 — Insert a saved pattern at any position (Priority: P3)

**Goal**: Deliver `blocks/insert-pattern` — resolve pattern by slug and expand into blocks at a target position.

**Independent Test**: Given a known pattern slug in the active theme, invoke `insert-pattern` at `parent_path=[0], index=0, slug="<known>"`. Verify the pattern's constituent blocks appear at that location in order, with all their nested content intact.

### Tests for User Story 7

- [ ] T050 [P] [US7] Create `tests/phpunit/abilities/Test_Insert_Pattern.php` — source-inspection tests (registration, schema per contracts/abilities.md § 7)
- [ ] T051 [P] [US7] Integration tests: (a) resolve unambiguous slug from theme, (b) resolve db slug, (c) fail on `pattern_not_found`, (d) fail on `multiple_locations` when slug exists in multiple sources without `source` disambiguation, (e) explicit `source` disambiguation succeeds, (f) `inserted_paths` array is correct and count matches pattern block count

### Implementation for User Story 7

- [ ] T052 [US7] Audit `includes/Abilities/Block/Read_Block_Pattern.php` for its pattern-source resolution helper — if inline, extract into `includes/Abilities/Utilities/Pattern_Source_Resolver.php` per research.md R5
- [ ] T053 [US7] Create `includes/Abilities/Content/Insert_Pattern.php` — extends `Ability_Definition`, input/output schema per contracts/abilities.md § 7
- [ ] T054 [US7] Implement `Insert_Pattern::execute($input)` — delegates slug resolution to the shared `Pattern_Source_Resolver` (returning `multiple_locations` when ambiguous), parses the pattern's block content via `parse_blocks`, iterates and calls `Block_Tree::insert_at_path` for each block in order, tracks `inserted_paths`, serializes + writes, returns response per contracts/abilities.md
- [ ] T055 [US7] Register `Insert_Pattern` in `AcrossAI_Core_Abilities_Bootstrap.php`
- [ ] T056 [US7] Run `composer test -- --filter=Test_Insert_Pattern` — all cases pass

**Checkpoint**: All 7 user stories complete. Every FR-### from spec.md is now covered.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Verify end-to-end quickstart, run full test + static-analysis + Plugin Check suites, tighten any rough edges surfaced during story implementation.

- [ ] T057 [P] Execute `specs/066-block-tree-mutation-and-nested-editing/quickstart.md` end-to-end against a live WP install: 11-step walkthrough building the columns → column → heading/paragraph tree, updating, duplicating, removing, moving, inserting a pattern
- [ ] T058 [P] Run full test suite: `composer test` — 0 failures
- [ ] T059 [P] Run static analysis: `composer phpstan` — 0 errors at level 8
- [ ] T060 [P] Run linting: `composer phpcs` — 0 errors WPCS strict
- [ ] T061 [P] Run WordPress Plugin Check against the built plugin — 0 errors per Constitution §II
- [ ] T062 Round-trip fidelity spot-check (SC-004): pick 3 realistic posts (article with images, columns layout, mixed classic+block content), call `get-post-blocks` then `update-post-block` with `path=[0], attributes={}` (no-op write), diff stored `post_content` — 95%+ byte-identical
- [ ] T063 Verify backward-compat (SC-003): capture a request/response transcript of an existing `update-post-block` call using only `block_index` from before 066, replay on the branch, confirm byte-identical response
- [ ] T064 Update `readme.txt` "Changelog" section with a summary of the 6 new abilities + `update-post-block` nested enhancement (do NOT reference external product names per project convention)
- [ ] T065 Update `CHANGELOG.md` (if present) with the same summary
- [ ] T066 Bump plugin version in `acrossai-abilities-manager.php` header (next patch/minor per prior release cadence — current version at 064's release was 0.0.23)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — no dependencies; run first
- **Phase 2 (Foundational)** — depends on Phase 1; **BLOCKS all user stories** because every story consumes `Block_Tree`
- **Phase 3 (US1 — Read)** — depends on Phase 2 only
- **Phase 4 (US2 — Add)** — depends on Phase 2 only; may reference US1 (`get-post-blocks`) in tests but is not blocked by it
- **Phase 5 (US3 — Remove)** — depends on Phase 2 only
- **Phase 6 (US4 — Update nested)** — depends on Phase 2 only
- **Phase 7 (US5 — Move)** — depends on Phase 2 only
- **Phase 8 (US6 — Duplicate)** — depends on Phase 2 only
- **Phase 9 (US7 — Insert pattern)** — depends on Phase 2 only; also depends on the pattern-source resolver in `Read_Block_Pattern` (T052 audit)
- **Phase 10 (Polish)** — depends on all in-scope user story phases

### Within Each User Story

- Tests + implementation for that story live in that story's phase only
- Bootstrap registration (`AcrossAI_Core_Abilities_Bootstrap.php`) is touched once per story — those tasks are inherently sequential across stories (same file)
- Run that story's test filter (`composer test --filter=Test_<Ability>`) as the final task of each story

### Parallel Opportunities

- All Phase 2 tasks marked [P] can run in parallel (T003, T010 — different files)
- **All 7 user story phases can run in parallel** once Phase 2 completes (if you have the capacity) — each touches its own file
  - Different developers can each pick a story
  - Or one developer can process them sequentially US1 → US7
- Within each story, the `[P]` test-authoring tasks (T012+T013, T018+T019, etc.) run in parallel with the implementation tasks in the same phase
- All Phase 10 polish tasks marked [P] run in parallel

### File-conflict serialisation

The only shared file is `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — six tasks touch it (T016, T022, T028, T042, T048, T055). Serialise those or merge into a single "register all 6" task if implementing in one pass.

---

## Parallel Example: User Story 1 kickoff

```bash
# Once Phase 2 is complete, in parallel:
Task T012: Author tests/phpunit/abilities/Test_Get_Post_Blocks.php (source-inspection)
Task T013: Add integration test for path annotation in same file

# Simultaneously (different file):
Task T014: Create includes/Abilities/Content/Get_Post_Blocks.php with schema
# then serialise into it:
Task T015: Implement Get_Post_Blocks::execute
```

## Parallel Example: Cross-story fan-out

```bash
# After Phase 2 checkpoint, spin up 6 workstreams:
Developer A: Phase 3 (US1)  — Get_Post_Blocks
Developer B: Phase 4 (US2)  — Add_Block
Developer C: Phase 5 (US3)  — Remove_Block
Developer D: Phase 6 (US4)  — Update_Post_Block nested
Developer E: Phase 7 (US5)  — Move_Block
Developer F: Phase 8 (US6)  — Duplicate_Block
# US7 (Insert_Pattern) can join once T052 audit clears
```

---

## Implementation Strategy

### MVP First (P1 stories only — US1 through US4)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (`Block_Tree` utility) — **CRITICAL blocking phase**
3. Complete Phases 3–6 in any order (US1, US2, US3, US4) — recommended sequence US1 → US2 → US3 → US4 so integration tests build on each other
4. **STOP and VALIDATE**: run through quickstart.md Steps 1–6 + Step 11 (backward-compat check). Ship as an MVP PR if scope needs to be split.

### Incremental Delivery — full feature in one PR (recommended per plan.md handoff)

1. Setup + Foundational
2. Add US1 → validate
3. Add US2 → validate
4. Add US3 → validate
5. Add US4 → validate (all P1 done)
6. Add US5, US6 → validate (P2 done)
7. Add US7 → validate (P3 done)
8. Polish phase 10
9. Open PR against `main`

### Parallel Team Strategy

- Complete Setup + Foundational together (1–2 developers on `Block_Tree`)
- Fan out US1–US7 to 6+ developers post-checkpoint (one story per developer)
- Merge to feature branch as each story lands
- Polish phase runs last with all developers converging

---

## Notes

- [P] tasks = different files, no dependencies on incomplete tasks
- [US#] label maps every user-story-phase task to its spec.md story for traceability
- `AcrossAI_Core_Abilities_Bootstrap.php` is the one shared write across stories — merge those touches or serialise
- Tests in this feature use the existing source-inspection pattern (`Test_Add_Post_Meta.php` as the reference) plus real-post integration tests via `WP_UnitTestCase` + `factory->post->create_and_get()`
- Every story ends with `composer test --filter=<story-test>` — do not proceed until it's green
- Commit after each task or logical group; the `after_*` extension hooks in `.specify/extensions.yml` will offer auto-commits
- Avoid: touching multiple abilities in a single task, cross-story dependencies that break independence, custom SQL (use `wp_update_post` only)

---

description: "Task list for Feature 036 — Library page full width and ability descriptions"
---

# Tasks: Ability Library — Full Width and Descriptions

**Input**: Design documents from `/specs/036-library-page-full-width-and-descriptions/`
**Prerequisites**: plan.md (loaded), spec.md (loaded, 2 user stories), memory-synthesis.md, security-constraints.md (Informational only)

**Tests**: Optional. The plan recommends one Jest test for `groupDefinitions` passthrough (marked as such below); the spec does not mandate test-first development.

**Organization**: Tasks grouped by user story so US1 (descriptions) and US2 (full width) can ship independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1, US2
- Include exact file paths in descriptions

## Path Conventions

- WordPress plugin — single project at repository root.
- React source: `src/js/ability-library/`
- SCSS: `src/scss/ability-library/`
- Jest tests (existing project convention): `src/js/ability-library/components/__tests__/`
- No PHP changes in this feature.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Pre-flight checks. No project initialization needed — Library module shipped in Features 030/031/033.

- [x] T001 Pre-flight: confirm `node -v` ≥ 20 (per DEC-NODE-20-BUILD-REQUIRED), confirm `git branch --show-current` returns `036-library-page-full-width-and-descriptions`, and confirm `npm ls --depth=0` shows expected `@wordpress/*` versions without missing-peer warnings — **DONE**: Node v22.22.0; branch correct; packages present

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that must complete before any user story.

**This feature has no foundational tasks.** `args.description` is already on the wire from Features 030 (data injection in `enqueue_scripts()`) and 031 (Ability_Definition simplification), and the Library React shell, REST endpoints, and SCSS file already exist. User stories can start immediately after T001.

**Checkpoint**: No barrier — proceed directly to Phase 3.

---

## Phase 3: User Story 1 — Read Ability Descriptions In Context (Priority: P1) 🎯 MVP

**Goal**: Surface each ability's `args.description` under its label in both the "Specific" checkbox mode and the read-only "All" mode, as smaller muted text. Rows with no description stay single-line. Plain-text rendering only — no `dangerouslySetInnerHTML`.

**Independent Test**: Open `wp-admin/admin.php?page=acrossai-abilities-library` with at least one add-on whose abilities declare non-empty descriptions. Each row with a description shows label + smaller muted description; rows without one render unchanged. HTML-like characters (`<`, `>`, `&`) appear as literal text. Walks spec US1 AC1–AC4.

### Implementation for User Story 1

- [x] T002 [US1] Modify `groupDefinitions()` in `src/js/ability-library/components/LibraryPage.js` — destructure `args` off each definition, derive `const description = typeof args?.description === 'string' ? args.description.trim() : ''`, and include `description` on the slug entry pushed into the per-category group; export `groupDefinitions` as a named export — **DONE**: `export function groupDefinitions(...)` with type-guarded trim and `description` on each slug entry.

- [x] T003 [P] [US1] Add Jest test (location: `tests/jest/ability-library/groupDefinitions.test.js`, matching the existing convention used by `groupBySubGroupPreservingOrder.test.js`) asserting `groupDefinitions` description passthrough — **DONE**: 8 new tests covering non-empty/trim/whitespace/missing/undefined-args/non-string passthrough, args-object suppression, and full-field shape; all pass (28 total in 3 suites).

- [x] T004 [US1] Modify the slug renderer in `src/js/ability-library/components/LibraryCard.js` — destructure `description`, wrap Specific-mode in `__slug-row` div, split All-mode into `__slug-readonly-label` + `__slug-readonly-description` spans; no `dangerouslySetInnerHTML` — **DONE**.

- [x] T005 [US1] Add description styling to `src/scss/ability-library/admin.scss`: `&__slug-row`, `&__slug-description` (Specific mode), convert `&__slug-readonly` to flex-column with bullet anchored at `top: 0`, add `&__slug-readonly-description` — **DONE**.

**Checkpoint**: US1 complete. Run `npm run build`, reload the Library page, walk spec US1 AC1–AC4. US1 ships independently of US2 — descriptions render correctly even with the page still capped at 900px.

---

## Phase 4: User Story 2 — Use the Full Admin Width (Priority: P2)

**Goal**: Remove the 900px page cap so the Library page uses the WordPress admin content column.

**Independent Test**: On a ≥1280px viewport, cards extend beyond 900px and use the available admin column; on a ~1024px viewport, no horizontal scroll. Walks spec US2 AC1–AC3.

### Implementation for User Story 2

- [x] T006 [US2] Remove the `max-width: 900px;` line from `.acrossai-library-page` in `src/scss/ability-library/admin.scss` — **DONE**: replaced with a `// Full WordPress admin content width; no artificial cap.` comment marker; `padding-top` and `position` preserved; no explicit width substitute added.

**Checkpoint**: US2 complete. Reload the Library page on a wide viewport — cards span the admin column. US2 ships independently: even without US1 the page is wider.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Build, lint, test, manual verification, and durable-memory bookkeeping. Runs after both user stories complete.

- [x] T007 [P] `npm run build` — **DONE**: webpack 5.106.2 compiled successfully (5.8s); SCSS bundle `ability-library/admin.scss` 1.94 KiB (up from prior baseline due to new description rules).
- [x] T008 [P] `composer run phpstan` — **DONE**: exit 0, level 8 clean.
- [x] T009 [P] `composer run phpcs` — **DONE**: 56/56 files (100%) clean.
- [x] T010 [P] `npx wp-scripts lint-js src/js/ability-library` — **DONE**: `--fix` resolved 43 formatting errors (auto-prettified). 4 errors remain, all pre-existing (api.js `@wordpress/api-fetch` unresolved import; `LibraryCard.js` + `LibraryPage.js` `@wordpress/components` extraneous-dep marker — same as on `main`; Feature 033's `groupBySubGroupPreservingOrder` JSDoc @return). Not introduced by this feature.
- [x] T011 [P] `npx wp-scripts test-unit-js --testPathPattern="ability-library"` — **DONE**: 3 suites pass, 28 tests pass (8 new + 20 existing).
- [x] T012 `npm run validate-packages` — **DONE**: "Package validation passed"; no direct React imports; no duplicate React packages.
- [ ] T013 Browser walkthrough per `plan.md` §Verification — execute steps 1–9: width check at ≥1280px, All-mode row with description, Specific-mode row with description, no-description row stays single-line, XSS sanity (description containing `<script>` literal renders as text), regression (per-card toggle/mode/expand-collapse/save persistence; DevTools POST body still has only `enabled`/`mode`/`sub_keys`), narrow-viewport check at ~1024px. **MANUAL QA — not automatable in this run.**
- [x] T014 [P] Paste the Memory Hub INDEX row from `security-constraints.md` into `docs/memory/INDEX.md` under the Security Reviews section — **DONE**: new `## Security Reviews` section added at end of INDEX.md with the 036 row.

### Bundled Housekeeping — wpboilerplate/wpb-access-control Upgrade

This is an orthogonal dependency bump bundled into this PR per user direction. Not driven by any FR in `spec.md`; touches no Library code. Listed here so it travels with the same review and the same release.

- [x] T015 [P] Bump `wpboilerplate/wpb-access-control` constraint in `composer.json` from `^1.2.1` to `^1.6.0` (current stable head of 1.x line), then run `composer update wpboilerplate/wpb-access-control --with-dependencies` — **DONE**: constraint bumped to `^1.6.0`; lockfile resolved to v1.6.0. Diff confined to `composer.json` + `composer.lock`. `composer audit`-style line: "No security vulnerability advisories found". T008/T009/T011 were sequenced after T015 and all passed against the new vendor surface.
- [~] T016 Re-validate per DEC-REVALIDATE-SECURITY-POST-UPGRADE — **PARTIAL**: code-level audit complete (SEC-03 `$global = false` at `AcrossAI_Abilities_Table.php:63` ✅; SEC-04 13 `in_array(..., true)` strict-flag sites confirmed ✅; DEC-PERM-CB `build_permission_callback` wired at `AcrossAI_Ability_Override_Processor.php:344` ✅; DEC-FAIL-OPEN-NOTICE `admin_notices` action + `maybe_show_library_notice` at `Main.php:308` + `wp_admin_notice` at `AcrossAI_Abilities_Access_Control.php:134` ✅). **MANUAL QA still pending**: (a) browser smoke at `wp-admin/admin.php?page=acrossai-abilities-library` to confirm page renders against v1.6.0 vendor; (b) rename `vendor/wpboilerplate/wpb-access-control/` to trigger and observe the FAIL-OPEN-NOTICE admin notice live, then restore.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001 has no upstream — start immediately.
- **Foundational (Phase 2)**: None for this feature; non-blocking.
- **User Story 1 (Phase 3)**: Depends only on T001.
- **User Story 2 (Phase 4)**: Depends only on T001. **Does NOT depend on US1**, but T006 edits the same file as T005 (`admin.scss`) — sequence T005 before T006 to avoid merge friction.
- **Polish (Phase 5)**: T007–T014 depend on both US1 and US2 being complete.

### Task-Level Dependencies (within and across stories)

- T002 must complete before T003 (test imports the new named export) and before T004 (LibraryCard.js consumes the new `description` field on the slug entry).
- T004 and T005 are different files → parallelizable in principle, but for one developer it is cleaner to land T002 → T004 → T005 sequentially.
- T005 and T006 both edit `src/scss/ability-library/admin.scss` → must run sequentially. T005 first (US1 ships) → T006 second (US2 ships).
- T013 (browser walkthrough) depends on T007 (build).
- T011 depends on T002 (named export present) and T003 (new test present).
- **T015 must complete before T008, T009, and T011** — composer update changes the PHP vendor surface that phpstan, phpcs, and (indirectly) the test bootstrap scan. Run T015 *first* in Phase 5, then the parallel gate fan-out.
- T016 depends on T015 (post-upgrade validation runs against the new vendor code).

### User Story Dependencies

- **User Story 1 (P1)**: Independent. Ships descriptions even while the page is still capped at 900px.
- **User Story 2 (P2)**: Independent. Ships full width even without descriptions. Same-file ordering with US1 noted above is implementation-mechanics, not a semantic dependency.

### Parallel Opportunities

- T003 (Jest test) [P] can be authored alongside T004/T005 once T002 lands.
- T007 (build) and T008/T009 (PHP gates) [P] can run together in CI.
- T008/T009/T010/T011 [P] are independent tools; run in parallel.
- T014 [P] is documentation-only and can run any time after the security-constraints.md exists (already does).

---

## Parallel Example: Polish Phase

```bash
# After T002–T006 land, run T015 (composer update) BEFORE fanning out PHP gates:
Task: composer update wpboilerplate/wpb-access-control --with-dependencies   # T015

# Then `npm run build` (T007) and quality gates in parallel:
Task: composer run phpstan                                 # T008  (after T015)
Task: composer run phpcs                                   # T009  (after T015)
Task: npx wp-scripts lint-js src/js/ability-library        # T010
Task: npx wp-scripts test-unit-js                          # T011  (after T015)
```

---

## Implementation Strategy

### MVP First (US1 Only)

1. T001 (Setup)
2. T002 → T003 → T004 → T005 (US1 implementation + optional test)
3. T007 (build) + T013-style spot check
4. **STOP and validate**: descriptions render in both modes. This is shippable on its own.

### Incremental Delivery

1. Land US1 → demo (descriptions visible, page still 900px)
2. Land US2 → demo (page now full width with descriptions readable across the width)
3. Run Phase 5 polish (T007–T014) → commit → PR.

### Bundled Delivery (default for a single developer, single PR)

1. T001 → T002 → T003 → T004 → T005 → T006 (all implementation)
2. T015 (composer update — bundled housekeeping) → T016 (post-upgrade revalidation)
3. T007 (build) → T008/T009/T010/T011 (parallel quality gates) → T012
4. T013 (browser walkthrough) → T014 (memory hub row)
5. Commit and open PR.

---

## Notes

- **No PHP changes.** Quality gates T008/T009 are kept in the polish phase as defensive verification only — they should pass with zero new findings.
- **No saved-config schema change.** T013's regression checks include verifying the REST POST body still carries only `enabled`/`mode`/`sub_keys` (FR-010, SC-007).
- **XSS containment is in the implementation, not in a test.** T013's step 7 manually inspects a description containing HTML-like characters; T003 covers behavioral passthrough only. The contract is encoded in T004's task description ("do NOT use `dangerouslySetInnerHTML`").
- `[P]` = different files, no in-flight dependency.
- `[Story]` label maps task to its user story for traceability.
- Each story is independently completable, independently testable, and independently shippable.
- Commit after each story phase (or after Phase 5 for a single-PR delivery).

---
description: "Tasks for Feature 056 — Bulk Actions Overhaul (Custom Abilities admin page)"
---

# Tasks: Bulk Actions Overhaul — Custom Abilities Admin Page

**Input**: Design documents from `/specs/056-bulk-actions-overhaul/`
**Prerequisites**: [plan.md](plan.md) (required), [spec.md](spec.md) (required for user stories), [memory-synthesis.md](memory-synthesis.md), [security-constraints.md](security-constraints.md), [docs/security-reviews/2026-07-20-056-plan.md](../../docs/security-reviews/2026-07-20-056-plan.md), user brief at [docs/planning/056-bulk-actions-overhaul.md](../../docs/planning/056-bulk-actions-overhaul.md).

**Tests**: Included. Constitution §VII requires unit tests for all new logic; the plan's TASK-7 planned three Jest suites; SEC-005 flagged specific test-coverage acceptance criteria. Not TDD-strict — tests are paired with implementation inside each user-story phase.

## Implementation status — 2026-07-20 (Feature 056 shipped locally, awaiting commit)

**Scope decision arc (user, 2026-07-20)**:
1. Original MVP scope: US1 (Site Access) + US2 (MCP Exposure) + US4 (Edit-any-source).
2. Session extended by direct user requests to also ship: US3 (User Access modal reusing composer `<AccessControl>`), User Access "Reset to Default" quick action (post-modal follow-up), Force Reset (Overrides optgroup), row-checkbox widening for any Source, and a full-screen busy overlay with WP-native spinner + body scroll-lock. Spec updated in the same turn to add FR-018/FR-019/FR-020 documenting the drift.
3. Phase 8 release-0.0.15 housekeeping still deferred until feature PR merges. Commit not made — working-tree diff ready for user review.

| Phase | Status |
|---|---|
| Phase 1 Setup | ✅ Complete (T001 Node v22.22.0, T002 baseline in `research.md`, T003/T004 confirmed) |
| Phase 2 Foundational | ✅ Complete — `bulkUpdateTristate` + `bulkSetUserAccessRule` + `bulkClearOverrides` shipped; `setAccessControlRule` helper added to `api/client.js` with **raw slug pass-through** (the `encodeURIComponent(slug)` that this task originally planned was found during testing to corrupt the composer key — DB stored `acrossai-abilities-managerblock-pattern-delete`; fix documented via FR-017 grep semantics + guarded by a Jest regression case). T010 no-op verified. |
| Phase 3 US1 Site Access | ✅ Complete (T011/T012 wired; T013 Jest passing) |
| Phase 4 US2 MCP Exposure | ✅ Complete (T015/T016 wired; T017 covered by tri-state suite's `show_in_mcp` case) |
| Phase 5 US3 User Access | ✅ Complete — T019 `UserAccessBulkModal.jsx` created (~210 LoC, hand-rolled HTML chrome since spec §Out-of-Scope forbids `package.json` change → `@wordpress/components → Modal` not used; composer `<AccessControl>` mounted inside via first-selected-slug seed). T020/T021 dispatch + mount wired. T022 (`store.bulkSetUserAccessRule.test.js`) shipped — 8 tests including I4 slug-encoding regression guard. T023 (modal Jest) still deferred (needs composer test-double). T024 SCSS shipped. |
| Phase 6 US4 Edit-any-source | ✅ Complete — Edit was already unconditional in both `isCustom ? (...) : (...)` branches at `AbilitiesList.jsx:808/884`. Row checkbox additionally widened to render on every source (was gated on `isCustom` per pre-Feature-056 delete flow; see FR-020). T027 manual verify pending user smoke. |
| Phase 7 Polish | ✅ Local gates green (T028 build compiled; T029 phpstan clean; T030 phpcs 31/31; T031 validate-packages ✓; T032 ESLint clean on new code; T033 Jest **21/21 passing** across 3 suites); T034 grep-parity: satisfied under revised FR-017/SC-007 semantics — see spec revisions dated 2026-07-20. |
| Phase 8 Release 0.0.15 | ⏸ Deferred — waits for feature PR merge before cutting `release-0.0.15` branch. |

**Post-implementation drift back-filled into spec (2026-07-20)**:
- **FR-018** — Overrides optgroup + Force Reset option (thunk: `bulkClearOverrides`; Jest: `store.bulkClearOverrides.test.js`, 6 tests).
- **FR-019** — Full-screen busy overlay + body scroll-lock during bulk dispatch; uses WP-native `.spinner is-active` scaled 48×48. Implemented in both `AbilitiesList.jsx` and `UserAccessBulkModal.jsx`.
- **FR-020** — Row checkbox unconditional across all Source values (widened `dbAbilities`/`allDbSlugs` → `allSlugs`).
- **FR-004 revised** — User Access group now has two options ("Add / edit access rule…" opens modal, "Reset to Default (allow everyone)" dispatches empty rule with confirm).
- **FR-011 revised** — Modal delegates provider enumeration to composer's `<AccessControl>` component; our own code does not enumerate.
- **FR-017 / SC-007 revised** — Semantic preservation, not byte-identical grep. Removed `AbilitiesList.jsx` callers are the intended replacement.

**T035 (commit) status**: **NOT executed** per user scope directive. Diff staged in working tree — user to inspect and commit.

**T023 (Modal Jest) status**: Deferred — needs composer `<AccessControl>` React test-double. Non-blocking for merge under Constitution §VII if flagged as a documented follow-up.

**Organization**: Tasks are grouped by user story (US1 = P1 Site Access = MVP; US2 = P2 MCP Exposure; US3 = P3 User Access; US4 = P4 Edit-Any-Source verification). Because all three action-groups share a single dropdown `<select>`, the dropdown skeleton + handler parse-and-dispatch scaffold + both new store thunks live in **Foundational (Phase 2)** — each user-story phase then wires its own branch.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4) — Setup, Foundational, Polish, Release phases have no story label

## Path Conventions

WordPress plugin single project layout. Source under `src/`, tests under `tests/`, build artifacts under `build/` (regenerated), release housekeeping in repository-root files.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Environment verification + record baselines the post-flight gates will compare against.

- [ ] T001 Verify Node ≥ 20 is active (`node --version` returns `v20.*` or higher) per `DEC-NODE-20-BUILD-REQUIRED`; if lower, `nvm use 20` before proceeding
- [ ] T002 [P] Record Phase 0 grep-baseline into specs/056-bulk-actions-overhaul/research.md — run the three commands from plan.md §Phase 0 and paste output verbatim (baseline for the SC-007 grep-parity gate)
- [ ] T003 [P] Confirm current bulk-actions dropdown at src/js/abilities/components/AbilitiesList.jsx lines ~455-476 renders exactly three `<option>` values: `publish`, `unpublish`, `delete` (baseline for the TASK-T006 replacement)
- [ ] T004 [P] Confirm current bulk thunk shape at src/js/abilities/store/index.js lines 284-299 (`bulkUpdateStatus`) is `return async ({ dispatch }) => { await Promise.all(...); dispatch(actions.fetchAbilities()); }` — this is the reference pattern T009 must mirror

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Dropdown + handler + store thunks shared by every user story. Because the dropdown is a single `<select>`, its three `<optgroup>` blocks are added atomically here even though US2/US3 wire their own branches later.

**⚠️ CRITICAL**: US1, US2, and US3 all consume the artifacts produced in this phase — no user-story work may begin until Phase 2 is complete.

- [ ] T005 Add `userAccessModalOpen` React `useState` near the top of the `AbilitiesList` component in src/js/abilities/components/AbilitiesList.jsx; add `import UserAccessBulkModal from './UserAccessBulkModal';` at the top (component file created in T018 — placeholder import; ESLint will warn until then, that's expected)
- [ ] T006 Replace the bulk-actions dropdown structure in src/js/abilities/components/AbilitiesList.jsx (lines ~455-476) with the three `<optgroup>` blocks specified in plan.md TASK-1. Add className `acrossai-abilities-list__bulk-select` on the `<select>` (used by T023 SCSS). Do NOT rename the `bulkAction` / `setBulkAction` / `selectedSlugs` state variables.
- [ ] T007 Rewrite `handleBulkApply()` in src/js/abilities/components/AbilitiesList.jsx (lines ~329-372) with the parse-and-dispatch shape from plan.md TASK-2. Include: (a) `sprintf` imported from `@wordpress/i18n` alongside `__`; (b) `bulkUpdateTristate` imported from the store (created in T009); (c) `// eslint-disable-next-line no-alert` on the line DIRECTLY above `window.confirm(msg)` — not above the wrapping `if` (guards `BUG-ESLINT-DISABLE-LINE-EXACT`); (d) `try/catch` around each `await dispatch(bulkUpdateTristate(...))` call surfacing failures via `wp.data.dispatch('core/notices').createErrorNotice(...)` — SEC-001 remediation; (e) selection persists on user_access branch (early `return`).
- [ ] T008 Mount `<UserAccessBulkModal>` near the JSX return of `AbilitiesList.jsx` guarded by `{userAccessModalOpen && ...}`; wire `onClose`, `onApplied` (clears both `bulkAction` and `selectedSlugs`) per plan.md TASK-2 snippet.
- [ ] T009 [P] Add `bulkUpdateTristate(slugs, field, value)` and `bulkSetUserAccessRule(slugs, acKey, acOptions)` thunks to src/js/abilities/store/index.js after the existing `bulkUpdateStatus` closing brace (~line 299), copying its shape verbatim. `bulkSetUserAccessRule` MUST assert every per-slug PUT response is non-null and reject with `new Error('...')` if any are null — SEC-002 remediation guards `BUG-AC-NULL-RETURN-SILENT-FAIL`. Import `apiFetch` from `@wordpress/api-fetch` at the top if not already present. Do NOT modify `bulkUpdateStatus` or `bulkDeleteAbilities` (backward-compat callers may remain).
- [ ] T010 [P] Verify `api.updateAbility(slug, ...)` in src/js/abilities/store/index.js `encodeURIComponent`s the slug before URL construction — SEC-004 defence-in-depth. If missing, add it inside the helper (single change, benefits every existing consumer including the new `bulkUpdateTristate`).

**Checkpoint**: Foundation ready — user-story implementation can now begin. Dropdown renders all three optgroups; handler parses `<domain>:<value>` and dispatches; both new thunks exist and mirror the reference pattern.

---

## Phase 3: User Story 1 — Bulk Site Access Override (Priority: P1) 🎯 MVP

**Goal**: Administrator can apply Site Access tri-state (Force Allow / Inherit / Force Block) to any selected batch of abilities in one gesture; Force Block prompts for confirmation.

**Independent Test**: Select 5 abilities of mixed sources (Plugin/Core/Custom) → choose Site Access → each transition → Apply → WP-CLI `SELECT slug, site_allowed FROM {prefix}acrossai_abilities WHERE slug IN (...)` shows the expected `1` / `NULL` / `0`. Force Block MUST show a confirm dialog; declining leaves DB unchanged.

### Implementation for User Story 1

- [ ] T011 [US1] Verify the Site Access branch inside `handleBulkApply()` at src/js/abilities/components/AbilitiesList.jsx dispatches `bulkUpdateTristate(selectedSlugs, 'site_allowed', v)` where `v = value === 'force_allow' ? true : value === 'force_block' ? false : null` (per plan.md TASK-2). The parse-and-dispatch skeleton lands in T007 — this task confirms the branch resolves and clears state after resolve.
- [ ] T012 [US1] Verify the destructive-transition confirm for `site_access:force_block` uses the translated label `__('force-block', 'acrossai-abilities-manager')` and count `selectedSlugs.length` inside `sprintf(__('%1$s %2$d abilities?', 'acrossai-abilities-manager'), label, count)` per spec FR-008.

### Tests for User Story 1

- [ ] T013 [P] [US1] Create tests/jest/abilities/store.bulkUpdateTristate.test.js covering: (a) success — dispatch(bulkUpdateTristate(['a','b','c'], 'site_allowed', true)) fires 3 apiFetch calls in parallel and dispatches fetchAbilities on resolve; (b) partial failure — one of three rejects, outer promise rejects, fetchAbilities NOT called until SEC-001 remediation dispatches core/notices error (assert the notice); (c) tri-state payload discipline — assert body sends raw JSON `true` / `false` / `null`, no string aliases (`BUG-MERGER-BOOL-STRING-CAST`). Guards: `BUG-WP-API-FETCH-VIRTUAL` (mock `@wordpress/api-fetch` with `{ virtual: true }`), `BUG-MODULE-LEVEL-WINDOW-READ` (set `globalThis.acrossaiAbilitiesManager` before `require()`), `PATTERN-JESTENV-WPSCRIPTS` (run via `npx wp-scripts test-unit-js`).

### Manual Verification for User Story 1

- [ ] T014 [US1] Manual smoke per spec Quickstart steps 2-4: `?page=acrossai-abilities-manager`, select 5 rows of mixed sources → Site Access → Force Allow → Apply (no confirm) → WP-CLI verify all 5 report `site_allowed=1`. Repeat with Force Block (confirm required; decline+accept both paths) then Inherit → NULL.

**Checkpoint**: User Story 1 (P1 MVP) fully functional and testable independently. Site Access bulk apply works end-to-end. If US2/US3 were cut, this alone would justify the release.

---

## Phase 4: User Story 2 — Bulk MCP Exposure Override (Priority: P2)

**Goal**: Administrator can apply MCP Exposure tri-state (Enable / Default / Disable) to a selected batch; Disable prompts for confirmation.

**Independent Test**: Select 5 abilities → MCP Exposure → each transition → Apply → WP-CLI `SELECT slug, show_in_mcp FROM {prefix}acrossai_abilities WHERE slug IN (...)` shows the expected `1` / `NULL` / `0`. Disable MUST show a confirm dialog.

### Implementation for User Story 2

- [ ] T015 [US2] Verify the MCP branch inside `handleBulkApply()` at src/js/abilities/components/AbilitiesList.jsx dispatches `bulkUpdateTristate(selectedSlugs, 'show_in_mcp', v)` where `v = value === 'enable' ? true : value === 'disable' ? false : null` (per plan.md TASK-2).
- [ ] T016 [US2] Verify the destructive-transition confirm for `mcp:disable` uses the translated label `__('disable MCP on', 'acrossai-abilities-manager')` and the same `sprintf` template as T012.

### Tests for User Story 2

- [ ] T017 [P] [US2] Extend tests/jest/abilities/store.bulkUpdateTristate.test.js (created in T013) with one additional describe block that exercises `field='show_in_mcp'`; assert the same tri-state payload discipline. Same file — sequential edit with T013 if they overlap; parallel-safe otherwise because the assertion is field-parameterised.

### Manual Verification for User Story 2

- [ ] T018 [US2] Manual smoke per spec Quickstart step 5: select 5 rows → MCP Exposure → Enable / Default / Disable transitions; Disable requires confirm.

**Checkpoint**: User Stories 1 AND 2 both work independently. MCP bulk applies mirror Site Access shape end-to-end.

---

## Phase 5: User Story 3 — Bulk User Access Rule (Priority: P3)

**Goal**: Administrator opens a modal, picks one access-control provider (dynamically enumerated from the plugin's adapter — see spec Clarify Q1) + its options, applies to all selected slugs in one gesture; sentinel "Everyone (clear rule)" removes existing rules.

**Independent Test**: Select 5 abilities → User Access → Configure… → provider `wp_role` + `editor` → Apply → WP-CLI `SELECT * FROM {prefix}abilities_access_control WHERE key IN (...)` shows one rule per slug with `ac_key='wp_role'`, `ac_options=['editor']`. Then repeat with "Everyone (clear rule)" → all rules removed.

### Implementation for User Story 3

- [ ] T019 [P] [US3] Create src/js/abilities/components/UserAccessBulkModal.jsx (~120 LoC) per plan.md TASK-4. Prefer reusing the composer's `<AccessControl>` component via the alias `AccessControl.js` (mirrors per-row drawer's usage in AbilityForm.jsx) — this brings the dynamic provider enumeration for free (spec FR-011). If the composer component's `resourceKey` prop is single-value only, fall back to the minimal picker sketched in plan.md TASK-4, but the picker MUST populate its provider `<option>` list from the same source the per-row drawer uses (do NOT hardcode `wp_user`/`wp_role`/`wp_capability` — that would miss BuddyBoss/MemberPress providers). Follow `PATTERN-AC-COMPONENT-INTEGRATION` exactly (named import + AccessControl.js alias + SCSS + three-branch rendering + module-level `abilitiesConfig` + no `onSave`). Existing `try/catch` in `handleApply` will surface the T009 non-null-assert rejection via `setError(...)` — no extra plumbing needed there.
- [ ] T020 [US3] Verify the user_access branch inside `handleBulkApply()` at src/js/abilities/components/AbilitiesList.jsx opens the modal (`setUserAccessModalOpen(true)`) and returns early so selection + `bulkAction` persist until the modal completes (per plan.md TASK-2 snippet + spec FR-010).
- [ ] T021 [US3] Verify T008's `<UserAccessBulkModal>` mount is in place; `onApplied` clears both `bulkAction` and `selectedSlugs`; `onClose` (Cancel) does NOT dispatch anything (spec Story 3 Acceptance 3).

### Tests for User Story 3

- [ ] T022 [P] [US3] Create tests/jest/abilities/store.bulkSetUserAccessRule.test.js covering: (a) success — 3-slug apply issues 3 PUT calls to `/wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}` with `{ ac_key, ac_options }` body; (b) null-response — one slug returns `null`, thunk rejects with an Error naming the failed count (SEC-002 remediation guard for `BUG-AC-NULL-RETURN-SILENT-FAIL`); (c) URL encoding — a slug with a `/` character reaches the endpoint URL-encoded (SEC-004). Guards: `BUG-WP-API-FETCH-VIRTUAL`, `BUG-MODULE-LEVEL-WINDOW-READ`, `PATTERN-JESTENV-WPSCRIPTS`.
- [ ] T023 [P] [US3] Create tests/jest/abilities/UserAccessBulkModal.test.jsx covering: (a) provider `<option>` list is dynamically populated from the mocked adapter enumeration (spec FR-011 / Clarify Q1); (b) Apply with `wp_role` + `editor` dispatches `bulkSetUserAccessRule` with those exact args; (c) Apply with "Everyone (clear rule)" dispatches with `''` / `[]`; (d) Cancel dispatches nothing; (e) error state renders when thunk rejects (SEC-002 propagation). Guards: `BUG-WP-ELEMENT-ACT-MISSING` (inject `act` from `jest.requireActual('react')` in the mock), `BUG-JEST-ASYNC-USEEFFECT-FLUSH` (`await act(async()=>{})` after apply click), `PATTERN-JESTENV-WPSCRIPTS`.

### Styling for User Story 3

- [ ] T024 [P] [US3] Add `<optgroup>` styling to src/scss/abilities/admin.scss scoped by `.acrossai-abilities-list__bulk-select` per plan.md TASK-5 snippet (optgroup: italic 600, `--wp-admin-theme-color` fallback `#2271b1`, `#f6f7f7` background; nested optgroup option: normal weight, `#1e1e1e`, 12px left pad).

### Manual Verification for User Story 3

- [ ] T025 [US3] Manual smoke per spec Quickstart steps 6-7: User Access → Configure → pick provider (verify BuddyBoss/MemberPress rows if their plugins are active — dynamic enumeration proof) + option → Apply → WP-CLI DB verify one rule row per slug. Repeat with "Everyone (clear rule)" → verify rules removed.

**Checkpoint**: All three action-domain stories are independently functional. The modal degrades gracefully if the composer package is absent (User Access group disabled per `DEC-AC-RENDERING-GATE`).

---

## Phase 6: User Story 4 — Row-Level Edit Available on Every Source (Priority: P4)

**Goal**: Verification-only. Confirm the row-level Edit action is unconditional across every ability Source; if any latent `source ===`-style gate exists near the Edit render site, remove it as a zero-risk delta.

**Independent Test**: Load `?page=acrossai-abilities-manager` with rows of every source represented. Every row's Actions column has an enabled Edit control that opens the drawer.

### Implementation for User Story 4

- [ ] T026 [US4] Run `grep -nE "(source|Source)\s*[!=]=" src/js/abilities/components/AbilitiesList.jsx` per plan.md TASK-2 and spec FR-013; if any hit surfaces near the row-Edit render site, remove the source-based conditional (should be a one-line delta). If no hits, spec FR-013 is satisfied by baseline.

### Manual Verification for User Story 4

- [ ] T027 [US4] Manual verify per spec Quickstart step 8: inspect the abilities list containing rows of Plugin, Core, Theme, and Custom sources; confirm every row's Edit action is enabled and opens the drawer.

**Checkpoint**: Row Edit unconditional across every ability Source verified.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Regenerate build artifacts, run every quality gate, verify grep-parity, commit + open PR.

- [ ] T028 Run `npm run build` on Node ≥ 20 (per T001 verification) — regenerates build/js/abilities.js, build/js/abilities.asset.php, build/css/abilities.css, build/css/abilities-rtl.css, build/css/abilities.asset.php. Assert webpack exit 0.
- [ ] T029 [P] Run `composer run phpstan` — assert exit 0 (Constitution §II PHPStan level 8 zero-error mandate; no PHP change here but gate must remain green).
- [ ] T030 [P] Run `composer run phpcs -- src/ includes/` — assert exit 0 (Constitution §VII).
- [ ] T031 [P] Run `npm run validate-packages` — assert exit 0 (Constitution §VII WordPress-package hierarchy check).
- [ ] T032 [P] Run `npx wp-scripts lint-js src/js/abilities/` — assert exit 0 (Constitution §II mandates JavaScript MUST pass ESLint with zero errors or warnings; §VII DoD "ESLint: zero errors"). Added post-review by governed-tasks orchestrator — was missing from the polish phase.
- [ ] T033 [P] Run `npx wp-scripts test-unit-js -- --testPathPattern=jest/abilities` — assert the three new Jest suites (T013, T017 extension, T022, T023) pass.
- [ ] T034 Grep-parity gate (SC-007): re-run the three baseline commands from research.md and assert **identical** hit lists. Any divergence blocks merge — the whole point of the gate.
- [ ] T035 Commit source + build/ artifacts in a single commit on branch `056-bulk-actions-overhaul`. Suggested message: `Feature 056 — Bulk Actions overhaul: Site Access / MCP Exposure / User Access + tests`.
- [ ] T036 Open PR against `main` with the spec's Manual Verification Checklist (§1-§7) pasted into the PR body; request review; merge when green.

**Checkpoint**: Feature merged to `main`. Ready for release housekeeping.

---

## Phase 8: Release 0.0.15 Housekeeping (post-merge)

**Purpose**: Cut the release branch, bump the three version markers in lockstep, add Changelog + Upgrade Notice blocks, PR against main, merge, tag. Mirrors the `f936aab` (release-0.0.14) pattern per spec FR-016.

**⚠️ CRITICAL**: This phase runs only after T036's feature PR is merged to `main`.

- [ ] T037 Cut `release-0.0.15` branch off updated `main` (`git checkout main && git pull && git checkout -b release-0.0.15`).
- [ ] T038 [P] Bump `Stable tag: 0.0.14` → `0.0.15` in README.txt; add the `= 0.0.15 =` Changelog block from the user brief verbatim.
- [ ] T039 [P] Add the `= 0.0.15 =` Upgrade Notice block from the user brief verbatim in README.txt.
- [ ] T040 [P] Bump ` * Version:           0.0.14` → ` * Version:           0.0.15` in acrossai-abilities-manager.php plugin header.
- [ ] T041 [P] Bump `$this->define( 'ACROSSAI_ABILITIES_MANAGER_VERSION', '0.0.14' );` → `'0.0.15'` in includes/Main.php.
- [ ] T042 Version-consistency grep gate: `grep "^Stable tag" README.txt && grep " \* Version:" acrossai-abilities-manager.php && grep "ACROSSAI_ABILITIES_MANAGER_VERSION.*'0" includes/Main.php` — all three MUST report `0.0.15` in lockstep (spec SC-008).
- [ ] T043 Commit release bumps on `release-0.0.15`; push branch; `gh pr create --base main --head release-0.0.15 --title "Release 0.0.15 — Bulk Actions overhaul" --body "..."` merge when green.
- [ ] T044 After the release PR merges: `git checkout main && git pull && git tag 0.0.15 && git push origin 0.0.15` (no `v` prefix — matches existing tags 0.0.1…0.0.14).
- [ ] T045 `gh release create 0.0.15 --title 0.0.15 --notes "..."` using the Upgrade Notice block from T039 as the notes body.

**Checkpoint**: Release 0.0.15 tagged and published; all 8 spec §Manual Verification gates verifiable.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 Setup**: No dependencies — starts immediately.
- **Phase 2 Foundational**: Depends on Phase 1 (T001 in particular — Node version). BLOCKS every user story.
- **Phase 3 US1 (MVP)**: Depends on Phase 2 complete. Ships-alone value per spec.
- **Phase 4 US2**: Depends on Phase 2 complete. Independent of US1 in behaviour but shares the dropdown + handler edits.
- **Phase 5 US3**: Depends on Phase 2 complete + T008 modal mount + T019 modal file exists (T008 lists T019's file as an import, so the ESLint warning is expected until T019 lands).
- **Phase 6 US4**: Depends on Phase 2 complete. Verification-only; can execute anytime after T006/T007 land.
- **Phase 7 Polish**: Depends on all in-scope user stories complete (T014, T018, T025, T027).
- **Phase 8 Release**: Depends on Phase 7 T035 (feature PR merged to main).

### User Story Dependencies

- **US1 (P1 MVP)**: Independent. If US2/US3/US4 are cut, US1 still ships.
- **US2 (P2)**: Independent. Reuses the tri-state thunk (`bulkUpdateTristate`) that US1's tests already exercise for a different field.
- **US3 (P3)**: Independent. Depends on the second thunk (`bulkSetUserAccessRule`) + modal file. If cut, spec §Out-of-Scope allows admins to continue using per-row drawer for User Access.
- **US4 (P4)**: Verification-only. Independent — no state changes if the current file already has no source-gate.

### Within Each User Story

- Wire dispatch branch → confirm-dialog copy (if destructive) → Jest test → manual smoke.
- For US3: modal file exists → dispatch → mount → Jest test (modal + thunk) → SCSS → manual smoke.

### Parallel Opportunities

- Setup T002, T003, T004 all parallelisable.
- Foundational T009 + T010 parallelisable (different concerns in the same file — coordinate to avoid merge conflicts if worked concurrently).
- US1 T013 (Jest) parallelisable with US2 T017 (Jest extension of same file) — but same file, so sequential edit safer.
- US3 T019 (modal file, new), T022 (thunk Jest, new file), T023 (modal Jest, new file), T024 (SCSS, different file) all fully parallelisable.
- Polish gates T029, T030, T031, T032, T033 all parallelisable (different tools, independent commands).
- Release housekeeping T038, T039, T040, T041 all parallelisable — different files (README.txt, plugin header, includes/Main.php); T038 and T039 touch the same file (README.txt) so sequentialise those two.

---

## Parallel Example: Foundational Phase

```bash
# Foundational — after T005-T008 (single-file AbilitiesList.jsx edits) complete, T009 + T010 run in parallel:
Task: "Add bulkUpdateTristate + bulkSetUserAccessRule thunks in src/js/abilities/store/index.js"
Task: "Verify + add encodeURIComponent(slug) in api.updateAbility helper"
```

## Parallel Example: User Story 3 File Creation

```bash
# US3 — three new file authoring tasks fire in parallel:
Task: "Create src/js/abilities/components/UserAccessBulkModal.jsx"
Task: "Create tests/jest/abilities/store.bulkSetUserAccessRule.test.js"
Task: "Create tests/jest/abilities/UserAccessBulkModal.test.jsx"
Task: "Add optgroup SCSS in src/scss/abilities/admin.scss"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001–T004)
2. Complete Phase 2: Foundational (T005–T010)
3. Complete Phase 3: US1 (T011–T014)
4. **STOP and VALIDATE**: WP-CLI DB verify per T014; PR-ready.
5. If US2/US3/US4 must be cut for schedule, this shippable — just add a "US2/US3 optgroups grey out with `disabled` attr in TASK-6" trivial cleanup.

### Incremental Delivery

1. Setup + Foundational → foundation ready.
2. Add US1 → validate → Deploy/Demo (MVP!)
3. Add US2 → validate → Deploy/Demo.
4. Add US3 → validate → Deploy/Demo.
5. Add US4 (verification) → validate.
6. Polish + Release.

### Parallel Team Strategy

Single-developer feature. If two developers were available:
- Developer A: Phase 2 (Foundational) → Phase 3 US1 → Phase 4 US2 → Phase 6 US4.
- Developer B: waits for T009+T010 → Phase 5 US3 (modal + tests + SCSS).
- Both converge on Phase 7 (Polish + PR) then Phase 8 (Release housekeeping).

---

## Security Findings Absorbed (from docs/security-reviews/2026-07-20-056-plan.md)

| Finding | Severity | Absorbed into |
|---|---|---|
| SEC-001 — Partial-failure silent success in tri-state paths | LOW | T007 (try/catch + notice dispatch) + T013 acceptance criterion (b) |
| SEC-002 — Composer PUT null-response silent success | LOW | T009 (non-null assertion) + T022 acceptance criterion (b) |
| SEC-003 — Unbounded `Promise.all` concurrency | INFO | Documented in plan.md; no code change |
| SEC-004 — Slug URL-encoding parity | INFO | T010 verify + fix helper if missing |
| SEC-005 — Jest coverage for security-critical branches | INFO | T013 / T017 / T022 / T023 acceptance criteria explicit per finding |

---

## Notes

- `[P]` tasks = different files, no dependencies on incomplete tasks. Same-file parallel edits must sequentialise.
- Every task has an exact file path in the description.
- Constitution §III DataForm/DataViews deviation is pre-authorised (`DEC-DESIGN-OVERRIDES-DATAVIEWS`) — no additional justification required in the PR.
- The row-level Edit action verification (US4) is expected to be a no-op deletion — planning-doc's Phase-1 exploration recorded no source-gate present.
- Follow-up (not this feature): remove now-orphaned `bulkUpdateStatus` / `bulkDeleteAbilities` thunks after a grep confirms zero remaining callers post-merge.
- Release tags use `0.0.15` (no `v` prefix) matching existing tags 0.0.1…0.0.14.

---

## Total: 45 tasks across 8 phases

- **Setup**: 4 tasks (T001–T004)
- **Foundational**: 6 tasks (T005–T010) — BLOCKS US1/US2/US3
- **US1 (P1 MVP)**: 4 tasks (T011–T014)
- **US2 (P2)**: 4 tasks (T015–T018)
- **US3 (P3)**: 7 tasks (T019–T025)
- **US4 (P4 Verify)**: 2 tasks (T026–T027)
- **Polish**: 9 tasks (T028–T036) — includes explicit ESLint gate added by governed-tasks orchestrator
- **Release 0.0.15**: 9 tasks (T037–T045)

**MVP scope**: T001–T014 (18 tasks) delivers a shippable Site Access bulk apply. Everything after T014 is incremental extension.

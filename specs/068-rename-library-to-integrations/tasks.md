# Tasks: Rename "Ability Library" admin page to "Ability Integrations"

**Input**: Design documents in `/specs/068-rename-library-to-integrations/`
**Feature Branch**: `079-rename-library-to-integrations`
**Status**: All tasks complete (PR #129)

## Phase 1: Setup

*Prerequisites for the rename — nothing beyond a clean checkout.*

- [x] T001 Cut feature branch `079-rename-library-to-integrations` off `main`.
- [x] T002 Confirm baseline `composer test` runs cleanly on the branch (1006 tests, 2040 assertions).

## Phase 2: Foundational

*No foundational infrastructure needed — this is a pure string / slug edit.*

## Phase 3: User Story 1 — Admin sees "Integrations" in the sidebar and URL (P1)

**Goal**: The sidebar label, page title, URL slug, and page h1 all read "Integrations" / "Ability Integrations".

**Independent test**: Load `/wp-admin`, verify submenu reads "Integrations", click it, verify URL is `?page=acrossai-abilities-integrations`, verify h1 reads "Ability Integrations".

- [x] T003 [US1] Change the submenu label from `__( 'Library', ... )` to `__( 'Integrations', ... )` in `admin/Partials/LibraryMenu.php:72`.
- [x] T004 [US1] Change the page title from `__( 'Ability Library', ... )` to `__( 'Ability Integrations', ... )` in `admin/Partials/LibraryMenu.php:71`.
- [x] T005 [US1] Change the `add_submenu_page()` slug from `'acrossai-abilities-library'` to `'acrossai-abilities-integrations'` in `admin/Partials/LibraryMenu.php:74`.
- [x] T006 [US1] Change the React h1 heading text from `__('Ability Library', ...)` to `__('Ability Integrations', ...)` in `src/js/ability-library/components/LibraryPage.js:404`.
- [x] T007 [US1] Run `npm run build` and confirm the compiled `build/js/ability-library.js` contains "Ability Integrations" and zero occurrences of "Ability Library".

## Phase 4: User Story 2 — Tab deep-links still work under the new slug (P1)

**Goal**: `?page=acrossai-abilities-integrations&tab=elementor` still deep-links to the Elementor tab; browser Back / Forward still preserve state.

**Independent test**: Load the deep-link URL directly, verify Elementor tab is active on first paint; click another tab, verify URL updates; press Back, verify previous tab re-selects.

- [x] T008 [US2] Verify `useLibraryTabSync` uses only the `tab` query arg (not `page`) — no code change needed since `getQueryArg` / `addQueryArgs` / `removeQueryArgs` only touch the `tab` key.
- [x] T009 [US2] Update the doc-comment URL examples in `src/js/ability-library/hooks/useLibraryTabSync.js:7-8` to reflect the new slug (`acrossai-abilities-integrations`).
- [x] T010 [P] [US2] Update the URL literals in `tests/jest/ability-library/useLibraryTabSync.test.js` (8 occurrences) — textual replacement of `acrossai-abilities-library` → `acrossai-abilities-integrations`. Preserves URL-manipulation assertions.

## Phase 5: User Story 3 — Old bookmarks 404 explicitly (P2)

**Goal**: Admins who hit the old URL get a WP-native "cannot load" screen (not a silent misdirect), and the changelog warns them explicitly.

**Independent test**: Visit `?page=acrossai-abilities-library` after deploy — WP renders its standard permission/not-found screen.

- [x] T011 [US3] Confirm no legacy alias / redirect from the old slug is registered — WP core's `add_submenu_page` de-registration is implicit when the slug changes, and no `admin_init` redirect exists in the codebase for this. No code change needed; documented in `spec.md` Edge Cases.
- [x] T012 [US3] Append a `= Unreleased (NOT YET RELEASED) =` changelog entry in `README.txt` explicitly noting: (a) the label/title/slug change; (b) that bookmarks to the old slug will 404; (c) what is NOT changed (REST namespace, class names, hooks, DOM id).

## Phase 6: Polish & Cross-Cutting Concerns

- [x] T013 Run full `composer test` — expect 1006 tests, 2040 assertions, all pass.
- [x] T014 Run `npm run build` and stage the rebuilt `build/js/ability-library.js` + `build/js/ability-library.asset.php`.
- [x] T015 Commit the 7 changed files with a descriptive message; push to `origin/079-rename-library-to-integrations`.
- [x] T016 Open PR #129 against `main` with a body that documents scope, the deliberately-not-renamed list, and the bookmark-breakage caveat.
- [x] T017 Author retroactive spec-kit artifacts under `specs/068-rename-library-to-integrations/` (this file + spec.md + plan.md + checklists/requirements.md); update `.specify/feature.json` and `CLAUDE.md` to point here.

## Dependencies

- Phase 1 (T001–T002) must complete before any other work.
- Phase 3 (T003–T007) and Phase 4 (T008–T010) can proceed in parallel — different files, no interdependencies.
- Phase 5 (T011–T012) must run after Phase 3 (T005) so the changelog reflects the actual new slug.
- Phase 6 (T013–T017) is the release wrap and depends on all prior phases.

## Parallel execution examples

Within Phase 3 + Phase 4, the following can be done in a single edit sitting:
- T003, T004, T005 (all in `LibraryMenu.php` — one edit)
- T006 (in `LibraryPage.js`)
- T009 (in `useLibraryTabSync.js`)
- T010 (in `useLibraryTabSync.test.js`)

All 5 files are independent — a single mass-edit session followed by one `npm run build` (T007) and one `composer test` (T013) resolves the whole feature.

## Implementation strategy — MVP boundary

**MVP = User Story 1 alone.** Just changing the visible name + URL delivers the entire user-observable value. User Story 2 is automatic (the tab-sync hook doesn't hardcode the slug). User Story 3 is a documentation task, not code.

If time-constrained, ship US1 tasks (T003–T007) first, then US2 (T008–T010), then US3 (T011–T012). In practice this PR delivered all three together because the total work is under an hour.

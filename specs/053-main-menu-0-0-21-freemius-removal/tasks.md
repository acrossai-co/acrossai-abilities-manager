---

description: "Task list for Feature 053 — Bump acrossai-co/main-menu 0.0.14 → 0.0.23, remove Freemius, restyle Library header, self-filter Add-ons"
---

# Tasks: Feature 053

**Input**: Design documents from `/specs/053-main-menu-0-0-21-freemius-removal/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [memory-synthesis.md](./memory-synthesis.md), [security-constraints.md](./security-constraints.md), [architecture-review.md](./architecture-review.md)
**Backfilled**: All tasks completed at implementation time; this file is the post-implementation record.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Parallelizable (different files, no dependencies)
- **[Story]**: US1 (Freemius removal) / US2 (header row layout) / US3 (self-filter)

---

## Phase 1: Setup

- [x] T001 Cut branch `053-main-menu-0-0-21-freemius-removal` from `main`.

---

## Phase 2: US1 — Freemius removal + main-menu bump (Priority: P1)

- [x] T002 [US1] Bump `composer.json` `acrossai-co/main-menu` from `0.0.14` to `0.0.21`.
- [x] T003 [US1] Run `composer update acrossai-co/main-menu --with-all-dependencies`; confirm `freemius/wordpress-sdk` drops from `composer.lock` and `vendor/freemius/` is removed.
- [x] T004 [US1] Delete the entire `if ( class_exists( \AcrossAI_Addon\AddonsPage::class ) ) { … }` block in `includes/Main.php::define_admin_hooks()` (previously lines 330-363, including the `fs_*` args + try/catch/admin_notices fallback). Replace with an explanatory comment about the 0.0.21 architecture (`\AcrossAI_Main_Menu\MenuRegistrar` now registers the Add-ons submenu automatically when `SettingsPage` boots).
- [x] T005 [US1] Update `README.txt` — 4 non-historical sections:
  - "Third-party integrations (optional)" bullet — remove Freemius line
  - "Add-ons:" install section — drop paid-add-on item 3
  - FAQ "Does this plugin make external HTTP requests?" — rewrite to state zero external requests
  - Screenshot 5 caption — "free and premium" → "free"
  - "External Services" block — rewrite to state zero external HTTP
  - "Privacy Policy" block — rewrite to state zero data collection
  Historical changelog entries for 0.0.1 and 0.0.6 mentioning Freemius are preserved as-is.
- [x] T006 [US1] Quality gate: `composer phpstan` L8 zero errors; `composer phpcs -- includes/Main.php` zero errors; `composer test` 129/129 pass.

**Commit 1**: `7888a18 Feature 053 — Bump acrossai-co/main-menu 0.0.14 → 0.0.21 + remove Freemius`

---

## Phase 3: US2 — Library header row on one line (Priority: P2)

- [x] T007 [US2] `admin/Partials/LibraryMenu.php::render()` — remove the server-rendered `<h1>Ability Library</h1>`. Container `<div class="wrap">` stays as bare React mount point.
- [x] T008 [US2] `src/js/ability-library/components/LibraryPage.js` — inside `<div className="acrossai-library-page__header">`, prepend `<h1 className="acrossai-library-page__title">` and wrap the two `<Button>`s in `<div className="acrossai-library-page__header-actions">`.
- [x] T009 [US2] `src/scss/ability-library/admin.scss` — flip `.acrossai-library-page__header` from `justify-content: flex-end` to `justify-content: space-between`. Add `.acrossai-library-page__title` block (WP admin H1 defaults: 23px / 400 / #1e1e1e / margin:0). Add `.acrossai-library-page__header-actions` (flex row, gap 8px).
- [x] T010 [US2] `npm run build` — regenerate `build/js/ability-library.js` (source 25.7 → 26.0 KiB) + `.asset.php` + `build/css/*`.
- [x] T011 [US2] Quality gate: Jest 82/82 pass (H1 addition inert to pure-helper tests); PHPCS clean on `admin/Partials/LibraryMenu.php`.

**Commit 2**: `cfd30e2 Feature 053 — Library page title + Enable All / Disable All on same row`

---

## Phase 4: Follow-on version bumps (Priority: P1)

- [x] T012 [US1] Bump `composer.json` `acrossai-co/main-menu 0.0.21 → 0.0.22`. Regenerate lockfile. Verify consumer API surface unchanged (`SettingsPage`, `AddonsPageRenderer`, `MenuRegistrar` all still present). Quality gates green.

**Commit 3**: `c4f2788 Feature 053 — Bump acrossai-co/main-menu 0.0.21 → 0.0.22`

---

## Phase 5: US3 — Self-filter on acrossai_addons (Priority: P3)

- [x] T013a [US3] `admin/Main.php` — new public method `filter_out_self_from_addons( $addons )`. Defensive: non-array input returns untouched; non-array entries pass through; entries with `slug === 'acrossai-abilities-manager'` are stripped.
- [x] T013b [US3] `includes/Main.php::define_admin_hooks()` — wire `$this->loader->add_filter( 'acrossai_addons', $plugin_admin, 'filter_out_self_from_addons' )` on the existing `$plugin_admin` named variable (variable-first per `AC-HOOKS-MAIN`).
- [x] T013c [US3] Quality gate: PHPStan L8; PHPCS on both touched files; PHPUnit 129/129.

**Commit 4**: `5d0ad84 Feature 053 — Filter out self from acrossai_addons list`

---

## Phase 6: Follow-on version bump (Priority: P1)

- [x] T014 [US1] Bump `composer.json` `acrossai-co/main-menu 0.0.22 → 0.0.23`. Regenerate lockfile. Verify consumer API surface unchanged. Full quality gates (PHPStan + PHPCS + PHPUnit + Jest + validate-packages + build) all green.

**Commit 5**: `4f8b114 Feature 053 — Bump acrossai-co/main-menu 0.0.22 → 0.0.23`

---

## Phase 7: Spec-kit artifact backfill + memory capture

- [x] T015 Create `specs/053-main-menu-0-0-21-freemius-removal/{spec,plan,tasks,memory-synthesis,security-constraints,architecture-review}.md` + `checklists/requirements.md` reflecting the shipped scope.
- [x] T016 Update `docs/memory/DECISIONS.md` and `docs/memory/INDEX.md` — mark `DEC-FREEMIUS-PER-PLUGIN-INIT` as `Superseded (Feature 053)`.
- [x] T017 Append WORKLOG milestone entry to `docs/memory/WORKLOG.md` and register the routing row in `docs/memory/INDEX.md` under `## Worklog Milestones (continued)`.

**Commit 6**: (this commit) — Feature 053 spec-kit artifacts + memory capture.

---

## Phase 8: Manual verification (user gate)

- [ ] T018 Manual wp-env verification per plan.md § Phase 1 Quickstart (6 checkpoints): `composer install` drops Freemius; Add-ons page renders without plugin's own card; Library page shows title + buttons on one row; Feature 052 flows still work; main list renders; activation/deactivation cycle clean.

---

## Dependencies & Execution Order

- **Phase 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8** (chronological — each phase's commits landed sequentially on the branch).
- Within Phase 2 / 3 / 5: composer bumps come first (T002/T003), then code changes (T004/T007/T008/T013), then docs (T005), then quality gates (T006/T011/T013c). No parallel opportunities within phases since each edit is on a small set of files.
- Cross-phase parallelism: none — later phases' commits build on earlier phases' state.

## Post-implementation notes

- **Deviations from the original plan** (see `/Users/raftaar1191/.claude/plans/so-here-i-am-witty-koala.md`):
  - Originally scoped to `main-menu 0.0.14 → 0.0.21` only; expanded during implementation to include 0.0.22 and 0.0.23 as upstream published them.
  - Originally scoped to remove `fs_*` args from the `AddonsPage` constructor; the class itself turned out to be gone in 0.0.21, so the entire instantiation block was deleted instead.
  - Two additional scope areas (US2 header row, US3 self-filter) were requested during implementation and shipped in the same PR.
- **Follow-ups deferred**:
  - Release 0.0.8: version bump + changelog + upgrade notice + tag + GitHub Release.
  - Cleanup of stale `fs_*` / `freemius_*` options in `wp_options` — optional operational task.
  - PR title update: `0.0.14 → 0.0.21` → `0.0.14 → 0.0.23` — cosmetic, awaiting user decision.

## Summary

- **Phase 1 (Setup)**: 1/1 ✅
- **Phase 2 (US1 Freemius removal + bump to 0.0.21)**: 5/5 ✅
- **Phase 3 (US2 header row layout)**: 5/5 ✅
- **Phase 4 (bump to 0.0.22)**: 1/1 ✅
- **Phase 5 (US3 self-filter)**: 3/3 ✅
- **Phase 6 (bump to 0.0.23)**: 1/1 ✅
- **Phase 7 (spec-kit artifacts + memory)**: 3/3 ✅
- **Phase 8 (manual wp-env verification)**: 0/1 (user gate)

**Quality gates (all green pre-commit and pre-push)**: PHPStan L8 ✅ • PHPCS ✅ • PHPUnit 129/129 ✅ • Jest 82/82 ✅ • validate-packages ✅ • build ✅

---
description: "Tasks for Feature 058 — slug rename to acrossai/ + verb-first suffixes"
---

# Tasks: Slug rename — namespace to `acrossai/`, suffixes to verb-first

**Input**: Design documents from `/specs/058-slug-rename-verb-first/`
**Prerequisites**: [plan.md](plan.md) (required), [spec.md](spec.md) (required for user stories), [memory-synthesis.md](memory-synthesis.md).
**PR**: [#88](https://github.com/acrossai-co/acrossai-abilities-manager/pull/88) — three commits (f401e09, bc23e6e, 88dd7c0).

**Status**: Shipped (documentation back-filled 2026-07-25).

**Organization**: Tasks grouped by user story. US1 (verb-first suffixes) and US2 (short namespace) are technically decoupled but ship together in the same release because they share the same rewrite mechanics. US3 (UI Slug prefix fix) is a small fix that piggybacks on US2 (since it also updates the `SLUG_PREFIX` JS constant). US4 (class file rename) requires US1 first so mapping is derivable from the new slug values.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4)
- Setup / Foundational / Polish phases have no story label

## Path Conventions

Single-project WordPress plugin. Paths shown relative to the plugin root (`wp-content/plugins/acrossai-abilities-manager/`).

---

## Phase 1: Setup

- [x] **T001** Verify the 219 ability slugs inventory. `grep -rhoE "'name'\s*=>\s*'acrossai-abilities-manager/[^']+'" includes/Abilities --include="*.php" | sed -E "s/.*acrossai-abilities-manager\///" | sed "s/'//" | sort > /tmp/actual_slugs.txt` → 219 lines, zero duplicates.
- [x] **T002** Confirm no sibling AcrossAI plugin uses the `acrossai/` prefix. `grep -rn "'name'\s*=>\s*'acrossai/'" ../acrossai-* --include="*.php"` → empty. Confirmed: `acrossai-buddyboss-abilities/*`, `acrossai-mcp-manager/*`, etc. all use their own distinct namespaces.
- [x] **T003** Verify `AcrossAI_Sanitizer::sanitize_ability_slug()` accepts arbitrary word order (`[^a-zA-Z0-9\-_\/]` regex only excludes non-slug chars — no enforcement of subject-first).

**Checkpoint**: Ready to begin rewriting.

---

## Phase 2: Foundational (Rename mechanics)

**Purpose**: Build the rewrite scripts that all user stories will rely on.

- [x] **T004** Write `/tmp/slug_map.txt` — 163 lines of `old|new` slug suffix pairs. Verify uniqueness on both sides via `cut -d'|' -f1 | sort | uniq -d` and `cut -d'|' -f2 | sort | uniq -d` — both empty.
- [x] **T005** Verify every old slug in the map exists in exactly one registration file. `for old in <map keys>; do grep -c "'name'\s*=>\s*'acrossai-abilities-manager/${old}'" includes/Abilities/**/*.php; done` → all 1.
- [x] **T006** Write `/tmp/rename_slugs.php` — perl-regex-based suffix rewriter with terminator character class (`[^a-zA-Z0-9_\-]|$`) to prevent shorter old slugs matching inside longer new ones. Dry-run first.

**Checkpoint**: Rewrite tooling ready.

---

## Phase 3: US1 — Verb-first suffixes under the LONG namespace (commit f401e09) 🎯 MVP

**Goal**: Every ability slug reads verb-first (still under `acrossai-abilities-manager/` at this stage).

- [x] **T007** [US1] Apply `/tmp/rename_slugs.php` — rewrites 200 occurrences across 174 files (219 registrations + helpers + tests + docs).
- [x] **T008** [US1] Verify: `grep -rhoE "'name'\s*=>\s*'acrossai-abilities-manager/[^']+'" includes/Abilities` → 219 unique NEW slugs, zero OLD slugs remaining.
- [x] **T009** [US1] Update `AbilitiesValidationTest::test_validate_slug_suffix_rejects_overlong` — regenerated with a 247-char suffix to trigger the >255 check under the shorter prefix.
- [x] **T010** [US1] Update `docs/FEATURES.md` and `docs/memory/BUGS.md` — illustrative slug examples reflect new form.
- [x] **T011** [US1] Update `README.txt` — 0.0.16 changelog entry.
- [x] **T012** [US1] Bump plugin version to `0.0.16` in three places (plugin header, `ACROSSAI_ABILITIES_MANAGER_VERSION`, README stable-tag).
- [x] **T013** [US1] Run PHPUnit — 170/170 pass.
- [x] **T014** [US1] Commit `f401e09` — "Feature 058 — standardize ability slugs on verb-first form".

**Checkpoint**: US1 shipped. Ability slugs verb-first under long namespace.

---

## Phase 4: US2 + US3 + US4 — Short namespace + class rename + UI prefix fix (commit bc23e6e)

**Goal**: Namespace shortened to `acrossai/`; 162 class files renamed to match slugs; Slug input UI shows the correct prefix.

- [x] **T015** [US4] Write `/tmp/rename_classes.php` — for each of 163 mappings: locate registration file, extract current class name, compute new class name from new slug, rename file, update `class X` declaration. Guarded to only match registration lines (`'name' =>` pattern), not helper cross-references. Emits `/tmp/class_rename_map.txt`.
- [x] **T016** [US4] Apply the script — 162 file renames (1 mapping produced a class name already correct → no-op).
- [x] **T017** [US4] Sweep bootstrap + tests + docs for old class references — perl one-liner with word-boundary `\b` and longest-first alternation to handle prefix collisions (`Cron_Delete` vs `Cron_Delete_All`).
- [x] **T018** [US4] PHP syntax check on all 276 ability files + 5 core files — zero errors.
- [x] **T019** [US2] Apply namespace sweep: `perl -i -pe 's{(?<!/)acrossai-abilities-manager/}{acrossai/}g'` across 256 files. Look-behind preserves filesystem paths and ACL library namespace.
- [x] **T020** [US3] Verify JS `SLUG_PREFIX` constants in `AbilityForm.jsx` and `AbilitiesList.jsx` now show `'acrossai/'` (auto-updated by the namespace sweep).
- [x] **T021** [US2] Update `AcrossAI_Abilities_Sanitizer::sanitize_slug_suffix()` byte cap: `227` → `246` (255 − 9).
- [x] **T022** [US2] **Bug fix**: `admin/Main.php::plugin_action_links()` — perl sweep wrongly rewrote plugin_basename `'acrossai-abilities-manager/acrossai-abilities-manager.php'` to `'acrossai/acrossai-abilities-manager.php'`. Reverted via explicit `Edit`.
- [x] **T023** Run PHPUnit — 170/170 pass.
- [x] **T024** `npm run build` — regenerate `build/js/abilities.js` and `build/js/ability-library.js`. Confirmed built assets contain only `acrossai/` prefix (except the intentional ACL library URL path).
- [x] **T025** Update `README.txt` 0.0.16 changelog — mention namespace shortening, class-file rename, and (at this stage) the migration.
- [x] **T026** Commit `bc23e6e` — "Shorten namespace to acrossai/ and align class filenames to verb-first".

**Checkpoint**: US2 + US3 + US4 all shipped in one commit. All 219 abilities under `acrossai/*` prefix; class files match slugs; UI Slug input renders the correct prefix + suffix split.

---

## Phase 5: Migration removal (commit 88dd7c0)

**Purpose**: The migration added in bc23e6e is deemed unnecessary; user directs its removal.

- [x] **T027** Remove `AcrossAI_Slug_Rename_Migration_058::maybe_run()` call from `AcrossAI_Activator::activate()` and drop the `use` import.
- [x] **T028** Remove the `add_action( 'admin_init', ...)` hook from `Main.php::define_admin_hooks()`.
- [x] **T029** Remove the `delete_option( 'acrossai_abilities_slug_rename_058_done' )` line from `uninstall.php`.
- [x] **T030** Delete `includes/Modules/Abilities/Database/AcrossAI_Slug_Rename_Migration_058.php` (350 lines).
- [x] **T031** Update `README.txt` 0.0.16 changelog — drop the auto-migration bullet, replace with a "clear old rows manually and re-add" note.
- [x] **T032** Verify: `grep -rn "Slug_Rename_Migration_058\|slug_rename_058_done" .` returns zero results (outside `.git/` and `vendor/`).
- [x] **T033** Run PHPUnit — 170/170 pass.
- [x] **T034** Commit `88dd7c0` — "Remove slug-rename migration".

**Checkpoint**: Migration fully removed. Release is code-only rename with no runtime data-migration code.

---

## Phase 6: Documentation (this phase)

**Purpose**: Back-fill spec-kit artefacts so the PR has proper documentation.

- [x] **T035** Write `specs/058-slug-rename-verb-first/spec.md`.
- [x] **T036** Write `specs/058-slug-rename-verb-first/plan.md`.
- [x] **T037** Write `specs/058-slug-rename-verb-first/tasks.md` (this file).
- [x] **T038** Write `specs/058-slug-rename-verb-first/memory-synthesis.md`.
- [x] **T039** Write `specs/058-slug-rename-verb-first/checklists/requirements.md`.
- [x] **T040** Append durable knowledge to `docs/memory/DECISIONS.md`, `docs/memory/WORKLOG.md`, and `docs/memory/BUGS.md` — the naming-convention decision, the feature summary with future-mistake prevention notes, and the `plugin_basename` mechanical-rewrite bug pattern.
- [ ] **T041** Push documentation commit to `058-slug-rename-verb-first`; PR #88 auto-updates.

---

## Dependencies

- Phase 2 (rewrite mechanics) must complete before Phases 3/4.
- US1 (verb-first suffixes) must ship before US4 (class rename) because the class rename derives the new class names from the new slug values.
- US2 (namespace shortening) is technically independent of US1 but shipped in the same commit to amortise the perl-sweep + JS rebuild cost.
- Phase 5 (migration removal) is a design-decision reversal on top of the migration added in Phase 4.
- Phase 6 (documentation) trails all implementation; safe to write after the fact.

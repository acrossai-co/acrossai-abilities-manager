# Implementation Plan: Bulk Actions Overhaul — Custom Abilities Admin Page

**Branch**: `056-bulk-actions-overhaul` | **Date**: 2026-07-20 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/056-bulk-actions-overhaul/spec.md`
**Synthesis**: [memory-synthesis.md](memory-synthesis.md) — v1 (memory-md, markdown-only; no flash-mem)
**Orchestrator note**: Generated inline via `/speckit-architecture-guard-governed-plan` fallback path (`/speckit-plan` skill available but not auto-invoked per project preference).

## Summary

Replace the Custom Abilities admin page's Bulk Actions dropdown (currently three misleading WP-CPT verbs: `publish` / `unpublish` / `delete`) with three ability-native operations that mirror the per-row edit drawer: **Site Access** tri-state (`site_allowed`), **MCP Exposure** tri-state (`show_in_mcp`), and **User Access** (opens a modal that applies one `wpboilerplate/wpb-access-control` rule to every selected slug). Two new Redux-style store thunks — `bulkUpdateTristate` and `bulkSetUserAccessRule` — loop the existing per-slug REST endpoints under `Promise.all`, mirroring the shape of the pre-existing `bulkUpdateStatus`. Destructive transitions (Force Block, MCP Disable) prompt for confirmation. The row-level Edit action is verified unconditional across all Source values. Client-side only — no PHP, no new REST, no new DB tables, no new composer / npm packages. Ships as `release-0.0.15` following the `0.0.14` (`f936aab`) pattern.

## Technical Context

**Language/Version**: JavaScript (ES2020+ via `@wordpress/scripts` webpack) + JSX; SCSS. No PHP change.
**Primary Dependencies**: `@wordpress/element`, `@wordpress/data`, `@wordpress/api-fetch`, `@wordpress/i18n`; the composer's `<AccessControl>` React component from `@wpb/access-control`. Modal chrome is **hand-rolled HTML/SCSS** — the original plan referenced `@wordpress/components → Modal/Button` but adding `@wordpress/components` to `peerDependencies` was rejected during implementation to honour spec §Out-of-Scope's no-`package.json`-change mandate; plain HTML + WordPress's native `.button` / `.spinner is-active` classes cover the same UX without a dependency add. (Revised 2026-07-20.)
**Storage**: Unchanged — writes flow through pre-existing REST endpoints to `{prefix}acrossai_abilities.site_allowed` / `{prefix}acrossai_abilities.show_in_mcp` (owned by the Abilities module) and to `{prefix}abilities_access_control` (owned by the composer package).
**Testing**: Jest via `npx wp-scripts test-unit-js` (per `PATTERN-JESTENV-WPSCRIPTS`); PHPStan level 8 + PHPCS as regression gates (no new PHP but existing gates must remain green).
**Target Platform**: WordPress 6.9+, PHP 8.1+ (per Constitution §II v1.4.6 update). Browser: whatever the WP admin supports.
**Project Type**: WordPress plugin — single project (this repo).
**Performance Goals**: Bulk apply on 50 selected slugs completes in ≤5s over a normal local-network round-trip. Concurrency pattern is unbounded `Promise.all` — mirrors `bulkUpdateStatus` per user brief; larger selections rely on the server's ability to absorb parallel writes on the pre-existing endpoints (no new load pattern introduced).
**Constraints**: No new REST routes / DB tables / composer or npm dependencies. Tri-state JSON payloads MUST be raw `true` / `false` / `null` (per `BUG-MERGER-BOOL-STRING-CAST`). ESLint zero-warning; PHPStan L8 zero-error; Plugin Check clean; Jest suites green.
**Scale/Scope**: One JSX file edited, one new JSX file (~120 LoC), one JS file edited (~40 new LoC), one SCSS block added, `build/` regenerated. Version-marker bumps to three files for the follow-up `release-0.0.15` cut.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Verdict | Evidence |
|---|---|---|
| §I Modular Architecture | ✅ Pass | No new module; edits stay inside the Abilities module's React admin surface (`src/js/abilities/`). No cross-module coupling introduced. |
| §II WordPress Standards Compliance | ✅ Pass | JS-only feature; ESLint gate applies; no new PHP; PHP 8.1+ / WP 6.9+ floor untouched; Plugin Check surface unchanged. `DEC-NODE-20-BUILD-REQUIRED` observed on TASK-6. |
| §III User-Centric Design (NON-NEGOTIABLE) | ⚠️ Deviation — pre-approved | The bulk-actions dropdown is a plain `<select>` + `<optgroup>` (not DataViews/DataForm) and the User Access modal is `@wordpress/components → Modal` (not a DataForm). This deviation is **pre-approved** by `DEC-DESIGN-OVERRIDES-DATAVIEWS` (Active). Recorded again in Complexity Tracking for completeness. |
| §IV Security First (NON-NEGOTIABLE) | ✅ Pass | No new PHP endpoints; every request routes through pre-existing controllers that already enforce nonce (`X-WP-Nonce`), `manage_options`, and slug sanitisation (`SEC-01`). `apiFetch` auto-injects the nonce. Slug values in URL paths are `encodeURIComponent`'d before assembly. Per-user policy: `permission_callback` audit intentionally skipped for this plan (feedback memory: `feedback_skip_permission_callback_audit`). |
| §V Extensibility Without Core Modification | ✅ Pass | User Access group is gated by `access_control_available` (per `DEC-AC-RENDERING-GATE`) so absence of the composer package degrades cleanly — the group becomes disabled/hidden, other bulk operations remain usable. |
| §VI Reusability & DRY | ✅ Pass | New thunks mirror `bulkUpdateStatus` verbatim, reuse existing `api.updateAbility` client + `@wordpress/api-fetch`; no duplicated helper. Modal reuses composer's `<AccessControl>` component whenever its API allows. |
| §VII Definition of Done | 🕒 Committed at TASK-7 | PHPCS + PHPStan gates green; ESLint zero-warning; Jest tests added for the two new thunks + the modal's apply flow; DataForm/DataViews check is N/A here (see §III deviation); `npm run validate-packages` runs pre-commit. |

**Re-check after Phase 1 design**: no new violations introduced by the design decisions in Phase 1 below. The §III deviation remains the only outstanding item and is pre-authorised.

## Project Structure

### Documentation (this feature)

```text
specs/056-bulk-actions-overhaul/
├── spec.md                      # feature spec (already written)
├── memory-synthesis.md          # memory-md synthesis (already written)
├── plan.md                      # this file
├── checklists/
│   └── requirements.md          # spec quality checklist (already written)
├── research.md                  # Phase 0 output (below — see Phase 0 section)
├── data-model.md                # Phase 1 output (below — see Phase 1 section)
├── contracts/
│   └── (none — no new contracts; existing REST endpoints preserved verbatim)
├── quickstart.md                # Phase 1 output (below — see Phase 1 section)
└── tasks.md                     # Phase 2 output (generated by /speckit-tasks)
```

Because no new contracts are introduced, the `contracts/` directory is intentionally empty — the two preserved REST routes (`POST /acrossai-abilities-manager/v1/abilities/{slug}` and `PUT /wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}`) already have controllers and schemas in the codebase; grep-parity gate (spec SC-007) is the contract-preservation proof.

### Source Code (repository root)

```text
src/js/abilities/
├── components/
│   ├── AbilitiesList.jsx           # EDIT: dropdown structure + handleBulkApply + modal mount
│   ├── AbilityForm.jsx             # UNTOUCHED (out-of-scope per spec)
│   └── UserAccessBulkModal.jsx     # NEW (~120 LoC): reuses composer <AccessControl> when possible; falls back to SelectControl-driven picker
└── store/
    └── index.js                    # EDIT: add bulkUpdateTristate + bulkSetUserAccessRule; import apiFetch if not already present

src/scss/abilities/
└── admin.scss                      # EDIT: add optgroup styling block for the bulk-select

build/                              # REGENERATED via `npm run build` — committed alongside source in one commit
├── js/abilities.js
├── js/abilities.asset.php
├── css/abilities.css
├── css/abilities-rtl.css
└── css/abilities.asset.php

# Release housekeeping (release-0.0.15 branch — cut after this feature merges to main)
README.txt                          # bump Stable tag; add Changelog + Upgrade Notice blocks
acrossai-abilities-manager.php      # bump plugin-header Version
includes/Main.php                   # bump ACROSSAI_ABILITIES_MANAGER_VERSION constant

# Tests
tests/jest/abilities/
├── store.bulkUpdateTristate.test.js       # NEW: mock apiFetch, assert Promise.all + refetch
├── store.bulkSetUserAccessRule.test.js    # NEW: mock apiFetch, assert PUT path + payload shape
└── UserAccessBulkModal.test.jsx           # NEW: render, provider select, apply → thunk dispatch
```

**Structure Decision**: Reuse the existing project layout — `src/js/abilities/{components,store}` — with one new component file. No new subdirectories, no new webpack entry, no new enqueue call (rides the existing `abilities.js` / `abilities.css` bundle per `AC-ENQUEUE-ADMIN`).

## Phase 0 — Research

The user brief has already pre-researched most decisions and captured them verbatim in `docs/planning/056-bulk-actions-overhaul.md`. This phase records the remaining resolved questions and confirms the preserved-API grep-gate baseline.

**Resolved by user brief** (no further research needed):
- **Concurrency pattern**: unbounded `Promise.all` — mirrors `bulkUpdateStatus` at `src/js/abilities/store/index.js:284-299`.
- **Tri-state JSON shape**: raw `true` / `false` / `null` — string aliases forbidden (guards `BUG-MERGER-BOOL-STRING-CAST`).
- **Destructive-transition confirms**: only on `site_access:force_block` and `mcp:disable`. Other four transitions apply immediately.
- **Composer PUT endpoint semantics**: PUT is idempotent-replace — a bulk apply replaces the existing rule wholesale (no merge).
- **Release pattern**: separate `release-0.0.15` branch cut off `main` after feature merge; three version-marker bumps + Changelog + Upgrade Notice + tag `0.0.15` (no `v` prefix).

**Resolved by /speckit-clarify Q1** (spec §Clarifications → Session 2026-07-20):
- **User Access provider list**: dynamic enumeration — modal reads whatever the plugin's access-control adapter (`AcrossAI_Abilities_Access_Control`) registers at boot (matches per-row edit drawer behaviour). Falls back gracefully when the composer package is absent (User Access group disabled per `DEC-AC-RENDERING-GATE`).

**Preserved-API grep baseline** (record this run's output; must match the post-implementation run to satisfy SC-007):

```bash
# Baseline commands — capture output and store in specs/056-bulk-actions-overhaul/research.md
grep -rEn '(bulkUpdateStatus|bulkDeleteAbilities|api\.updateAbility)' \
    --include='*.js' --include='*.jsx' src/
grep -rEn "acrossai-abilities-manager/v1/abilities/" --include='*.php' includes/
grep -rEn "wpb-ac/v1/abilities/rules" \
    --include='*.js' --include='*.jsx' src/ vendor/wpboilerplate/
```

**Output**: This baseline will be recorded in `specs/056-bulk-actions-overhaul/research.md` as the first task of Phase 3 implementation (immediately before TASK-1 edits begin). Post-flight parity is a required PR gate.

## Phase 1 — Design

### Data Model

No schema change. All storage columns and access-control rows already exist. Recording the reference shape for review:

| Domain | Field / Rule Key | JSON Value | Storage |
|---|---|---|---|
| Site Access | `site_allowed` (body key of `POST /abilities/{slug}`) | `true` / `false` / `null` | `{prefix}acrossai_abilities.site_allowed` |
| MCP Exposure | `show_in_mcp` (body key of `POST /abilities/{slug}`) | `true` / `false` / `null` | `{prefix}acrossai_abilities.show_in_mcp` |
| User Access | `{ac_key, ac_options[]}` (body of `PUT /wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}`) | `ac_key ∈ {'', 'wp_user', 'wp_role', 'wp_capability', ...}` + provider-specific options array | `{prefix}abilities_access_control` (composer-owned) |

Client state additions (React-only):

- `userAccessModalOpen: boolean` — new `useState` in `AbilitiesList.jsx`, toggles modal visibility.
- Modal-local: `acKey`, `acOptions`, `busy`, `error` — hooks inside `UserAccessBulkModal.jsx`.

### API Contracts

**Preserved verbatim (no changes):**

- `POST /wp-json/acrossai-abilities-manager/v1/abilities/{slug}` with body `{ site_allowed?: bool|null, show_in_mcp?: bool|null }` — accepts the tri-state writes.
- `PUT /wp-json/wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}` with body `{ ac_key: string, ac_options: string[] }` — replaces the User Access rule.

The two new client thunks are the ONLY new consumers of these routes:

```
bulkUpdateTristate(slugs, field, value) → Promise.all(slugs.map(s => api.updateAbility(s, {[field]: value}))) → dispatch(fetchAbilities())
bulkSetUserAccessRule(slugs, acKey, acOptions) → Promise.all(slugs.map(s => apiFetch({path: BASE + encodeURIComponent(s), method: 'PUT', data: {ac_key, ac_options}}))) → dispatch(fetchAbilities())
```

No contract test files needed — the contracts are unchanged; grep-parity is the proof.

### Quickstart (local verification recipe)

Full manual smoke lives in the spec's TASK-7 checklist. Abbreviated recipe:

1. Load `?page=acrossai-abilities-manager` on a WordPress install with ≥5 abilities of mixed sources (`plugin`, `core`, `custom`).
2. Select 5 abilities.
3. **Site Access** → Force Allow → Apply — no confirm; `WP-CLI: wp db query "SELECT slug, site_allowed FROM {prefix}acrossai_abilities WHERE slug IN (...)"` shows `1` for all 5.
4. Repeat for Force Block (**confirm required**), Inherit.
5. Repeat all three transitions for MCP Exposure (`show_in_mcp` column; Disable requires confirm).
6. **User Access** → Configure… → provider `wp_role`, option `editor`, Apply → `wp db query "SELECT * FROM {prefix}abilities_access_control WHERE key IN (...)"` shows one rule per slug.
7. Repeat User Access → "Everyone (clear rule)" → rules removed for all 5.
8. Inspect any row of source `plugin` / `core` / `theme` — the row-level Edit action is enabled and opens the drawer.

## Phase 2 — Task Plan Preview

`/speckit-tasks` will emit the ordered TASK list. The user brief already carries the canonical decomposition (TASK-1 through TASK-8) in `docs/planning/056-bulk-actions-overhaul.md`. Preview of the expected task shape (final version comes from `/speckit-tasks`):

- **T001** — Record Phase 0 grep-baseline into `research.md`.
- **T002** — Replace Bulk Actions dropdown structure in `AbilitiesList.jsx` (three `<optgroup>` blocks). Guard: `BUG-ABILITYFORM-JSX-MIXED-DEPTHS` doesn't apply (we're not touching `AbilityForm.jsx`), but tab-depth consistency inside `AbilitiesList.jsx` must be preserved. Add the `useState` for `userAccessModalOpen`.
- **T003** — Rewrite `handleBulkApply()` in `AbilitiesList.jsx` to parse-and-dispatch. Guard: `BUG-ESLINT-DISABLE-LINE-EXACT` — the `// eslint-disable-next-line no-alert` MUST sit directly above the `window.confirm(msg)` call, not above the wrapping `if`. Import `sprintf` from `@wordpress/i18n` alongside `__`. Import `bulkUpdateTristate` from the store.
- **T004** — Add `bulkUpdateTristate` + `bulkSetUserAccessRule` to `src/js/abilities/store/index.js` following the `bulkUpdateStatus` reference at lines 284-299. Guard: `BUG-MERGER-BOOL-STRING-CAST` — no string casts on tri-state payloads.
- **T005** — Create `src/js/abilities/components/UserAccessBulkModal.jsx`. Reuse composer's `<AccessControl>` if its API supports bulk `resourceKey`; else fallback to a minimal picker that **dynamically enumerates providers** from the plugin's adapter (per spec FR-011 clarify). Guard: `PATTERN-AC-COMPONENT-INTEGRATION` (named import + `AccessControl.js` alias + SCSS + three-branch rendering + module-level `abilitiesConfig` + no `onSave`).
- **T006** — Add `<optgroup>` styling to `src/scss/abilities/admin.scss` scoped by `.acrossai-abilities-list__bulk-select` (add that class in T002 if the current `<select>` has no class or a different class).
- **T007** — Add Jest tests: `store.bulkUpdateTristate.test.js`, `store.bulkSetUserAccessRule.test.js`, `UserAccessBulkModal.test.jsx`. Guards: `BUG-WP-ELEMENT-ACT-MISSING` (inject `act` in mock), `BUG-MODULE-LEVEL-WINDOW-READ` (set `globalThis.acrossaiAbilitiesManager` before `require()`), `BUG-JEST-ASYNC-USEEFFECT-FLUSH` (`await act(async()=>{})` after apply), `BUG-WP-API-FETCH-VIRTUAL` (`jest.mock('@wordpress/api-fetch', () => ..., { virtual: true })`), `PATTERN-JESTENV-WPSCRIPTS` (run via `npx wp-scripts test-unit-js`, not plain `npx jest`).
- **T008** — `npm run build` on Node ≥ 20 (per `DEC-NODE-20-BUILD-REQUIRED`); commit `build/` artifacts alongside source in one commit.
- **T009** — Grep-parity gate (SC-007): re-run Phase 0 baseline commands, assert identical output. Verify row-Edit is unconditional (`grep -nE "(source|Source)\s*[!=]=" src/js/abilities/components/AbilitiesList.jsx` returns no hits near the Edit render site).
- **T010** — `composer run phpstan` + `composer run phpcs -- src/ includes/` (both zero-exit).
- **T011** — Manual smoke per Quickstart (Phase 1).
- **T012** — Open PR against `main`; merge.
- **T013** — Cut `release-0.0.15` branch off updated `main`; bump `README.txt` `Stable tag`, `acrossai-abilities-manager.php` `Version`, `includes/Main.php` `ACROSSAI_ABILITIES_MANAGER_VERSION` constant; add `= 0.0.15 =` Changelog + Upgrade Notice blocks (copy from user brief); commit; PR against `main`; merge; tag `0.0.15` (no `v` prefix); `gh release create 0.0.15 --title 0.0.15 --notes "..."`.

## Complexity Tracking

Fill only for justified violations.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Constitution §III (User-Centric Design mandates DataForm/DataViews) — feature ships a plain `<select>` + `<optgroup>` for the bulk-actions toolbar and an `@wordpress/components → Modal` for User Access | The Custom Abilities admin page's user-facing design prototype (formalised as `DEC-DESIGN-OVERRIDES-DATAVIEWS`) explicitly overrides §III for this surface. A DataForm bulk-actions row would look and feel wrong next to the existing plain-HTML checkbox column and Actions column that this feature does not touch. | Rebuilding the entire list in DataViews to bring the bulk-actions toolbar into DataForm compliance is a much larger, out-of-scope refactor; `DEC-DESIGN-OVERRIDES-DATAVIEWS` was accepted precisely to avoid that. |

## References

- Spec: [spec.md](spec.md) (17 FRs, 8 SCs, 1 clarification)
- Memory synthesis: [memory-synthesis.md](memory-synthesis.md)
- User brief: `docs/planning/056-bulk-actions-overhaul.md` (canonical TASK-1..TASK-8 decomposition)
- Constitution: `.specify/memory/CONSTITUTION.md` v1.4.8
- Governing pattern: `PATTERN-AC-COMPONENT-INTEGRATION` (ARCHITECTURE.md)
- Governing decision: `DEC-DESIGN-OVERRIDES-DATAVIEWS`, `DEC-AC-RENDERING-GATE`, `DEC-AC-SAVE-FLOW-PATTERN`, `DEC-ABILITIES-LIST-UX-025`, `DEC-NODE-20-BUILD-REQUIRED` (DECISIONS.md)
- Guarded bug patterns: `BUG-MERGER-BOOL-STRING-CAST`, `BUG-AC-NULL-RETURN-SILENT-FAIL`, `BUG-ESLINT-DISABLE-LINE-EXACT` (BUGS.md); plus the four Feature-018 Jest gotchas cited in T007.

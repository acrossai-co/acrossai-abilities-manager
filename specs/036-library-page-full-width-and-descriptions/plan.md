# Implementation Plan: Ability Library — Full Width and Descriptions

**Branch**: `036-library-page-full-width-and-descriptions` | **Date**: 2026-06-18 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/036-library-page-full-width-and-descriptions/spec.md`

**Note**: This plan was generated inline by `/speckit-architecture-guard-governed-plan` because the orchestrator runs in the same agent context.

## Summary

The Ability Library admin page already receives every ability's `description` field on the wire (PHP-side `Ability_Definition::push_definition()` stores the entire `args` array on each definition row, and the Registry's `ALLOWED_ARGS_FIELDS` allowlist permits `description`). The React grouping helper `groupDefinitions()` in `LibraryPage.js` currently destructures only label/slug fields and drops `args.description` before it reaches the card component. This feature threads `description` through that helper, renders it under each ability row (both interactive "Specific" checkboxes and read-only "All" rows) as small muted plain text, and removes the page-level `max-width: 900px` constraint so the page uses the WordPress admin content width.

**Technical approach**: React-only change in `groupDefinitions()` and `LibraryCard.js`, plus targeted SCSS updates in `src/scss/ability-library/admin.scss`. No PHP, no REST contract change, no DB or saved-config schema change, no migration. Description is rendered as a JSX text node — implicit XSS-safe by React's default escaping — never via `dangerouslySetInnerHTML`.

## Technical Context

**Language/Version**: PHP 8.1+ (unchanged — not touched), JavaScript ES2022 via `@wordpress/scripts` Babel toolchain
**Primary Dependencies**: `@wordpress/components` (CheckboxControl, RadioControl, ToggleControl, Button), `@wordpress/element` (Fragment, useState, useEffect, useRef), `@wordpress/icons` (chevronDown, chevronUp), `@wordpress/i18n` — all already imported in `LibraryPage.js` and `LibraryCard.js`. No new dependencies.
**Storage**: N/A — saved config schema unchanged (`enabled`, `mode`, `sub_keys`).
**Testing**: Jest via `@wordpress/scripts test-unit-js` for React, PHPUnit for PHP (no PHP changes), `composer run phpstan` and `composer run phpcs` for static analysis (no expected impact — no PHP edits).
**Target Platform**: WordPress 6.6+ admin (`wp-admin/admin.php?page=acrossai-abilities-library`), modern evergreen browsers.
**Project Type**: WordPress plugin — single project, no client/server split inside the plugin.
**Performance Goals**: No regression. Render path is purely additive (one extra text node per ability when description exists). Page renders well under a second on a fresh admin page load with up to a few hundred abilities.
**Constraints**: Description capped at 1000 chars by `DEC-DESCRIPTION-VALIDATION-PATTERN` on the write side; render path can assume single-paragraph plain text. Must not regress Feature 030 (data injection moved to `enqueue_scripts()` with `wp_add_inline_script('before')`) or Feature 033 (per-card disclosure + sub-group display).
**Scale/Scope**: ~3 files touched, ~30 lines of JSX added, ~20 lines of SCSS added, 1 SCSS line removed. Optional one-test addition for `groupDefinitions()` passthrough.

## Constitution Check

*GATE: Must pass before implementation. Re-checked at end of plan.*

| Principle (CONSTITUTION.md) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Boot Flow Rule (Main.php is single source of hook registration) | No PHP changes | ✅ Pass | No new hooks; no `add_action`/`add_filter` calls touched. |
| §I Admin Partials Rule (admin/Partials/ for menu/render/enqueue) | No new admin class | ✅ Pass | `LibraryMenu.php` and `admin/Main.php` untouched. |
| §I REST Controller Pattern (orchestrator + sub-controllers, ≤400 lines) | No REST changes | ✅ Pass | `AcrossAI_Ability_Library_Rest_Controller` and `AcrossAI_Ability_Library_Config_Controller` untouched. |
| §I REST `permission_callback` return type (only `true`/`false`/`WP_Error`) | No REST changes | ✅ Pass | N/A — no controller edits. |
| §I Module Contract (singleton pattern, private ctor, no cross-module deps) | No new modules | ✅ Pass | N/A — no new PHP classes. |
| §II PHP Standards (`acrossai_` prefix, nonces on forms/AJAX, capability checks, sanitize on input/escape on render, no deprecated APIs) | No PHP changes | ✅ Pass | Trivially satisfied. |
| §II Quality Gate (PHPCS, PHPStan L8, ESLint clean; tests pass; DataForm/DataViews mandated) | Partial | ⚠️ Documented Deviation (pre-existing) | Library page predates the DataForm/DataViews mandate and is implemented as a custom React UI with `CheckboxControl`/`RadioControl`/`ToggleControl` — same pattern that shipped in Features 030–033. `DEC-DESIGN-OVERRIDES-DATAVIEWS` already covers this for Admin/Abilities. Feature 036 introduces NO new form or list pattern — it adds one text node under an existing CheckboxControl. No new deviation. |
| §III UI Contract (`DataForm`/`DataViews` for forms and lists, no custom duplicates) | Library page already uses custom React (see above) | ⚠️ Pre-existing | Same status — display-only addition, not a new form/list pattern. |
| §III Database (use `$wpdb->prepare()`; prefer options/meta; custom tables need justification) | No DB changes | ✅ Pass | N/A. |
| §III Integration Resilience (availability checks for optional integrations) | N/A | ✅ Pass | No new integrations. |
| **Code Quality & Workflow** — `npm run validate-packages` before commit | Yes | 🔲 Verify at implement time | Run during `/speckit-implement`. |
| **Code Quality & Workflow** — never modify `.agents/tools/` | N/A | ✅ Pass | Untouched. |

**Constitution Gate**: **PASS** with two pre-existing UI-pattern deviations already documented in memory (`DEC-DESIGN-OVERRIDES-DATAVIEWS`). No new violations or deviations introduced.

## Memory Synthesis Findings

Synthesized in `memory-synthesis.md` (this directory). Highlights applied to this plan:

- **DEC-LIBRARY-CATEGORY-SLUG-REBRAND** — `sub_keys` on-disk key preserved. The plan explicitly does not write `description` into saved configuration; it is read fresh from each definition on every render.
- **DEC-DESCRIPTION-VALIDATION-PATTERN** — Description max 1000 chars on the write side. Renderer assumes single-paragraph plain text; no novel-length handling needed.
- **DEC-NODE-20-BUILD-REQUIRED** — Pre-flight: confirm `node -v` ≥ 20 before `npm run build` during implementation.
- **PATTERN-NAMED-EXPORT-JEST** — If a unit test is added for `groupDefinitions()` passthrough, expose it as a named export from `LibraryPage.js` (the same pattern already used for `groupBySubGroupPreservingOrder` in `LibraryCard.js`).
- **AC-ENQUEUE-ADMIN** — Data injection stays in `admin/Main::enqueue_scripts()`. The plan adds no new enqueue logic.
- **BUG-WP-LOCALIZE-SCRIPT-RENDER** — Inherited fix from Feature 030 is preserved; this plan touches no PHP, so there is no risk of regressing it.
- **BUG-JEST-MOCK-LIST-STALENESS** — No new `@wordpress/*` imports planned; existing Jest mock allowlists for `LibraryCard.js` stay current.

## Project Structure

### Documentation (this feature)

```text
specs/036-library-page-full-width-and-descriptions/
├── spec.md              # Feature spec (created by /speckit-specify)
├── memory-synthesis.md  # Memory synthesis (created by /speckit-memory-md-plan-with-memory)
├── plan.md              # This file (created by /speckit-architecture-guard-governed-plan)
├── checklists/
│   └── requirements.md  # Spec quality checklist (16/16 pass)
└── tasks.md             # To be created by /speckit-tasks
```

`research.md`, `data-model.md`, `quickstart.md`, and `contracts/` are intentionally omitted — this feature has no new tech to research, no data model changes, no REST contracts, and the verification path is short enough to live inside `plan.md` (see "Verification" below).

### Source Code (repository root)

This is a WordPress plugin (single project). Touched files:

```text
src/
├── js/
│   └── ability-library/
│       └── components/
│           ├── LibraryPage.js        # MODIFY — thread description through groupDefinitions()
│           └── LibraryCard.js        # MODIFY — render description under each row
└── scss/
    └── ability-library/
        └── admin.scss                # MODIFY — drop max-width, add description styles
```

Untouched (intentional):

```text
includes/Modules/Library/             # All PHP unchanged
admin/Main.php                        # enqueue_scripts() / wp_add_inline_script() unchanged
src/js/ability-library/api.js         # REST client unchanged
src/js/ability-library/index.js       # Mount root unchanged
```

**Structure Decision**: Standard WordPress plugin layout per CONSTITUTION.md §I Directory Layout. No new directories.

## Implementation Phases

### Phase 0 — Pre-flight (≈5 min)

1. Confirm Node version: `node -v` ≥ 20 (per `DEC-NODE-20-BUILD-REQUIRED`).
2. Confirm `npm install` is current: `npm ls --depth=0` shows expected `@wordpress/*` versions.
3. Confirm working tree is on branch `036-library-page-full-width-and-descriptions`.
4. (Optional) Open `wp-admin/admin.php?page=acrossai-abilities-library` and inspect `window.acrossaiAbilityLibraryData.definitions[0].args.description` in DevTools to confirm descriptions are on the wire on this site.

### Phase 1 — Thread `description` Through the Grouping Helper

**File**: `src/js/ability-library/components/LibraryPage.js`

Extend `groupDefinitions()`'s destructure to include `args`, derive a trimmed `description` once, and add it to the slug entry pushed onto each group.

Behaviour rules:
- `description` is `typeof args?.description === 'string' ? args.description.trim() : ''`.
- The empty-string result is what downstream conditionals (`description && …`) test against — single source of truth for "no description".
- Do not add a fallback to `slugLabel` or `name`; an absent description must NOT silently borrow another field.

**Optional sub-task (P1)**: Export `groupDefinitions` as a named export so it can be unit-tested (mirrors the `groupBySubGroupPreservingOrder` precedent). Add a single Jest test asserting that a definition with `args.description = 'hello'` produces a slug entry with `description: 'hello'`, and that an absent or whitespace-only description becomes `''`.

### Phase 2 — Render Description in `LibraryCard.js`

**File**: `src/js/ability-library/components/LibraryCard.js`

In the slug renderer inside the `items.map(...)` block, destructure `description` alongside `slug`, `slugLabel`, `name`. Render in both branches:

- **Specific mode**: wrap the existing `CheckboxControl` plus a conditional `<p className="acrossai-library-card__slug-description">{description}</p>` inside a `<div key={slug} className="acrossai-library-card__slug-row">`. Move `key={slug}` from the (former) `CheckboxControl` to the new wrapping `<div>` to avoid React duplicate-key warnings.
- **All mode**: split the current `<div>` content into a `<span className="acrossai-library-card__slug-readonly-label">…</span>` + a conditional `<span className="acrossai-library-card__slug-readonly-description">{description}</span>`.

Behaviour rules:
- Plain-text render only — never use `dangerouslySetInnerHTML`. JSX text-node escaping is sufficient (`<`, `>`, `&` appear as literal characters).
- The `description &&` guard prevents an empty wrapper when the description is empty/whitespace.
- Do not modify `groupBySubGroupPreservingOrder()` — its tested signature is unchanged. The new `description` field travels alongside the existing slug fields without affecting grouping order.

### Phase 3 — SCSS Updates

**File**: `src/scss/ability-library/admin.scss`

1. **Drop the 900px page cap** — remove the `max-width: 900px;` line at `.acrossai-library-page` (currently line 19). Leave `padding-top` and `position` in place. Do not replace with `100%` or any other explicit width.
2. **Add Specific-mode styles** inside `.acrossai-library-card`:
   - `&__slug-row { display: flex; flex-direction: column; gap: 2px; }`
   - `&__slug-description { margin: 0 0 0 24px; font-size: 12px; color: $muted; line-height: 1.4; }`
3. **Extend All-mode styles** for `&__slug-readonly`: convert the existing block to `flex-direction: column` with `gap: 2px`, anchor the existing bullet `::before` at `top: 0`, and add `&__slug-readonly-description { font-size: 12px; color: $muted; line-height: 1.4; }`.

The `24px` left offset on `&__slug-description` aligns the description text under the checkbox label, not under the checkbox box itself — chosen to track `@wordpress/components`' current `CheckboxControl` checkbox+gap width. If a future WPDS upgrade changes this, the single `24px` value is the only thing to adjust.

### Phase 4 — Build & Manual Verification

1. `npm run build` (Node ≥ 20).
2. Open `wp-admin/admin.php?page=acrossai-abilities-library` on a site with at least one add-on that registers abilities with non-empty descriptions.
3. Walk the spec's Validation surface:
   - Acceptance Scenarios US1 AC1–AC4 (descriptions in All + Specific modes, no-description case, HTML-char escaping).
   - Acceptance Scenarios US2 AC1–AC3 (1920px width usage, 1024px non-scroll, long-description overflow).
   - Regression — per-card toggle, mode switch, expand/collapse chevron, sub-group headings, save persistence.

### Phase 5 — Quality Gates

1. `composer run phpstan` — expected pass (no PHP changed).
2. `composer run phpcs` — expected pass (no PHP changed).
3. `npm run lint:js` (or `npx wp-scripts lint-js src/js/ability-library`) — must pass with no new warnings.
4. `npx wp-scripts test-unit-js` — existing Jest suite must pass; the new test for `groupDefinitions()` passthrough (if added in Phase 1) must pass.
5. `npm run validate-packages` (CONSTITUTION §II requirement) before commit.

## Verification

Verification lives directly in this plan because the feature is UI-only and short to walk through. There is no separate `quickstart.md`.

**Browser walkthrough**:
1. Activate at least one add-on that registers an ability with `args.description` set (any first-party Acrossai add-on).
2. Open `wp-admin/admin.php?page=acrossai-abilities-library`.
3. **Width**: confirm cards extend well past 900px on a ≥1280px viewport, using the WP admin content column.
4. **All mode**: pick a card in "All" mode — read-only rows show label + indented muted description on a second line, with a single bullet at row start.
5. **Specific mode**: switch the same card to "Specific" — each checkbox row shows label + indented muted description; description aligns under the label, not under the checkbox.
6. **No-description row**: find a row whose ability did not declare a description (any add-on without `args.description`) — row stays single-line with no extra spacing.
7. **XSS sanity**: pick (or temporarily fixture) an ability whose description contains `<script>alert(1)</script>`. Confirm the literal text appears in the row; nothing executes; DOM inspector shows text-node content, not a real `<script>` element.
8. **Regression**: toggle the card off and back on (state persists across reload); switch modes (state persists); toggle a checkbox in "Specific" (saves; DevTools Network shows POST body with only `enabled`/`mode`/`sub_keys` keys — no `description` key in the payload).
9. **Narrow viewport**: resize to ~1024px wide — page content does not scroll horizontally; descriptions wrap within the card.

## Architecture Validation (`/speckit-architecture-guard-violation-detection`)

Inline check (orchestrator is `architecture-guard`):

| Concern | Source | Verdict |
|---|---|---|
| Boot Flow Rule violations (hooks outside Main.php) | CONSTITUTION §I | ✅ No new hooks; no PHP edits. |
| Admin Partials Rule (admin classes only in admin/Partials/) | CONSTITUTION §I | ✅ No new admin classes. |
| Module Contract (singleton + private ctor) | CONSTITUTION §I | ✅ No new PHP modules. |
| REST controller split / `permission_callback` typing | CONSTITUTION §I | ✅ No REST changes. |
| Namespace mirrors directory path | CONSTITUTION §I | ✅ No new PHP files. |
| UI Contract (DataForm/DataViews mandate) | CONSTITUTION §III | ⚠️ Pre-existing deviation (Library page custom React UI, established Features 030–033). Feature 036 adds NO new form/list pattern; renders a text node under an existing CheckboxControl. No new deviation introduced. |
| `acrossai_` prefix on PHP symbols | CONSTITUTION §II | ✅ No PHP edits. |
| `$wpdb->prepare()` for SQL | CONSTITUTION §III | ✅ No SQL. |
| Integration Resilience (optional integrations wrapped) | CONSTITUTION §III | ✅ No new integrations. |
| Quality gates (PHPCS/PHPStan/ESLint/Jest) | CONSTITUTION Quality Gate | 🔲 Will be enforced in Phase 5; no new code paths that should fail any gate. |
| Memory: saved-config shape preserved (`DEC-LIBRARY-CATEGORY-SLUG-REBRAND`) | memory-synthesis.md | ✅ FR-010 explicitly preserves shape. |
| Memory: render-side XSS safety | memory-synthesis.md / FR-006 | ✅ Plain-text JSX node only; no `dangerouslySetInnerHTML`. |
| Memory: data injection location unchanged (`AC-ENQUEUE-ADMIN`, `BUG-WP-LOCALIZE-SCRIPT-RENDER`) | memory-synthesis.md | ✅ No PHP edits. |

**Verdict**: **No drift detected**, **no security-architecture conflicts**. The single noted UI Contract deviation is pre-existing and previously documented (see `DEC-DESIGN-OVERRIDES-DATAVIEWS` for the Admin/Abilities precedent; Library page custom React established Features 030–033). Feature 036 introduces nothing new under that heading.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| (None — no new violations introduced) | — | — |

## Re-check Constitution After Plan

| Gate | Status |
|---|---|
| Code Quality & Workflow (validate-packages, prefixes, no .agents/ edits) | Will be checked at `/speckit-implement`; this plan adds no PHP and uses only existing JS imports. |
| Architecture & UI Standards | Pre-existing Library UI deviation only; no new pattern duplication. |
| Quality Gate | Phase 5 enforces; Plan-time: green. |

**Constitution Gate (post-plan)**: **PASS**.

## Bundled Housekeeping (not driven by spec)

- **`wpboilerplate/wpb-access-control` version bump** — `composer.json` constraint raised from `^1.2.1` to `^1.6.0` (current stable head of the 1.x line per `DEC-STABLE-UPGRADE-WINDOW`; lockfile resolves to v1.6.0). Bundled into this PR per user direction; not driven by any FR. Tracked in `tasks.md` as T015 (bump + `composer update`) and T016 (post-upgrade revalidation per `DEC-REVALIDATE-SECURITY-POST-UPGRADE` — re-check SEC-03, SEC-04, DEC-PERM-CB, DEC-FAIL-OPEN-NOTICE). Sequence in Phase 5: T015 runs before T008/T009/T011 so those gates scan the new vendor surface.

## Out of Scope

- Promoting `description` to a top-level row field on the Registry (requires Registry contract change; not in this feature).
- Server-side `wp_kses_post` sanitization on the PHP side (description is rendered as plain text in the browser; sanitization at write time would be a Registry change).
- Persisting `description` into saved configuration (display-only).
- Card-level max-widths or grid layouts (full-width per the requirement).
- Mobile/responsive media queries (WordPress admin handles this).
- Renaming `args.description` or adjusting `ALLOWED_ARGS_FIELDS`.
- Bumping the constitution version — this feature surfaces no new principle.

## Open Questions

None. The spec's Q&A was complete; clarifications were not needed.

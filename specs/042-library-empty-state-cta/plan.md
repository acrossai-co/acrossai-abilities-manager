# Implementation Plan: Library empty-state refresh with Add-ons CTA

**Branch**: `042-library-empty-state-cta` | **Date**: 2026-07-07 | **Spec**: [spec.md](./spec.md)

## Summary

Replace the pre-042 plain-paragraph empty state on **AcrossAI → Library** with a structured, card-styled block that guides fresh installs directly to the Add-ons catalogue. Three-file code change (one PHP enqueue tweak + one React component edit + one SCSS block) plus one rebuild of the ability-library bundle. No REST, DB, or hook changes. Reuses existing SCSS tokens and `@wordpress/icons` — no new pattern or decision entries required.

## Technical Context

**Language/Version**: PHP 8.1+ (CONSTITUTION §II); JavaScript ES2020 / React 18 via `@wordpress/scripts`.
**Primary Dependencies**: `@wordpress/components` (already loaded), `@wordpress/icons` (already loaded for LibraryCard chevrons), `@wordpress/element`, `@wordpress/i18n`. No new npm dependency.
**Storage**: No change. Feature 042 does not read or write any option, meta, or DB row.
**Testing**: No new PHPUnit coverage. The change is a JSX render branch guarded by `items.length === 0`; manual walkthrough is the accepted verification path (T009). Test count unchanged (holds at 105).
**Target Platform**: WordPress 6.9+ admin, PHP 8.1+, modern evergreen browsers.
**Project Type**: WordPress plugin — single project.
**Performance Goals**: Zero regression. The extra `data.addonsUrl` key adds ~60 bytes to the localized JSON blob; the extra JSX subtree only mounts when `items.length === 0` (fresh installs). Non-empty Library pages are unaffected.
**Constraints**: No new i18n textdomain; all new strings use the existing `'acrossai-abilities-manager'` textdomain. No inline styles — all visuals live in `src/scss/ability-library/admin.scss`. No breakage of the existing `restBase` / `nonce` / `definitions` payload keys.
**Scale/Scope**: 3 code files edited (`admin/Main.php`, `src/js/ability-library/components/LibraryPage.js`, `src/scss/ability-library/admin.scss`), 2 build outputs regenerated (`build/js/ability-library.js`, `build/css/ability-library*.css`), 4 spec-kit files added. No memory files edited.

## Constitution Check

| Principle (CONSTITUTION.md v1.4.8) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Library module ownership | Yes | ✅ Pass | Empty-state markup lives in `src/js/ability-library/components/LibraryPage.js`; server-side enqueue lives in `admin/Main.php` per `AC-ENQUEUE-ADMIN`. No cross-module dependency introduced. |
| §I Boot Flow Rule | Yes | ✅ Pass | No new hook registration. `Main.php` (root) untouched. |
| §I Admin Partials Rule | Yes | ✅ Pass | `admin/Partials/LibraryMenu.php` untouched. |
| §I Module Contract (singleton) | Yes | ✅ Pass | No module singleton touched. |
| §II WordPress Standards | Yes | ✅ Pass | New PHP is a single array-key addition in an existing `wp_json_encode()` call. JSX follows existing PATTERN-NAMED-EXPORT-JEST conventions (though no new tested export is introduced). |
| §II `acrossai_` prefix | Yes | ✅ Pass | No new PHP identifiers. New CSS classes prefixed `acrossai-library-page__empty-*`. New localized key is `addonsUrl` (camelCase, JS-facing — consistent with existing `restBase`). |
| §II Multisite compatible | Yes | ✅ Pass | `admin_url()` is multisite-safe. No `$wpdb`, no site-scoped code. |
| §III UI Contract (DataForm / DataViews) | Yes | ✅ Pass | The empty state is not a DataForm/DataViews context; it is a page-level informational state. Follows wp-admin card styling conventions. |
| §IV Security First | Yes | ✅ Pass | `addonsUrl` is produced via `admin_url()` on the server; consumed as an anchor `href` in React (React auto-escapes attribute values). No user input flows into the empty state. |
| §V Extensibility Without Core Modification | Yes | ✅ Pass | No new extension point introduced or removed. |
| §VI DRY | Yes | ✅ Pass | Reuses existing `acrossaiAbilityLibraryData` payload; reuses existing SCSS token palette; reuses existing `@wordpress/icons` package. |
| §VII Definition of Done | Yes | ✅ Verified via per-task gates in [tasks.md](./tasks.md). |

**Constitution Gate**: **PASS**. No amendment required. No accepted-deviation change.

## Memory Synthesis Findings

Synthesized in [memory-synthesis.md](./memory-synthesis.md). Highlights applied to this plan:

- **`AC-ENQUEUE-ADMIN`** (Feature 027) — the localized `acrossaiAbilityLibraryData` blob is injected via `wp_add_inline_script(…, 'before')` in `admin/Main::enqueue_scripts()`. Feature 042 adds one key (`addonsUrl`) to the existing `wp_json_encode()` array. No new inline-script call.
- **Feature 026 Addons-page integration** (specs/026) — established `acrossai-addons` as the Add-ons page slug via the `acrossai-co/main-menu` vendor package's `MenuRegistrar::SUBMENU_SLUG`. Feature 042's `admin_url('admin.php?page=acrossai-addons')` is stable against that slug.
- **Feature 038 Main-menu integration** — reaffirmed `acrossai` as the shared parent slug and preserved the Add-ons submenu URL. Feature 042 does not touch the menu registration.
- **BEM SCSS convention** in `admin.scss` (`.acrossai-library-page__empty`, `.acrossai-library-card__slug-row`, etc.) — Feature 042 extends the `.acrossai-library-page__empty` block from a lone element to a five-element block (`icon`, `title`, `description`, `actions`, `hint`).
- **No new memory pattern warranted**. The change reuses established conventions and does not introduce a novel architectural rule.

## Project Structure

### Documentation (this feature)

```text
specs/042-library-empty-state-cta/
├── spec.md              # 8 FRs, 6 SCs, 2 user stories, 5 edge cases
├── plan.md              # This file
├── tasks.md             # 3 task groups
└── memory-synthesis.md  # Memory hygiene synthesis
```

### Source Code (repository root)

**Files EDITED** (3 code + 2 rebuilt bundles):

```text
admin/Main.php                                           # +1 line — addonsUrl injected into localized data
src/js/ability-library/components/LibraryPage.js         # Empty-state block replaced with structured markup
src/scss/ability-library/admin.scss                      # .acrossai-library-page__empty block expanded

build/js/ability-library.js                              # Regenerated via `npm run build`
build/css/ability-library.css                            # Regenerated via `npm run build`
build/css/ability-library-rtl.css                        # Regenerated via `npm run build`
build/js/ability-library.asset.php                       # Regenerated (dependency hash)
build/css/ability-library.asset.php                      # Regenerated (dependency hash)
```

**Files NOT touched**:

```text
includes/                                                # No PHP module edited
admin/Partials/                                          # LibraryMenu render is unchanged
tests/phpunit/                                           # No test added or modified
docs/memory/                                             # No memory entry added (per plan)
```

**Structure Decision**: Single-project WordPress plugin. Display-only polish feature. No new directories, no new modules, no new tables, no new dependencies, no new memory patterns.

## Phase 0 — Research Findings

| Question | Decision | Rationale |
|---|---|---|
| Where should the Add-ons URL be produced — PHP or JS? | **PHP, via `admin_url()`**, injected through the existing localized blob. | Keeps the slug knowable to server-side code (future PHP callers can reuse the same source of truth). Avoids duplicating the slug literal in JS. Matches how `restBase` is injected (also derived from a PHP constant). |
| Add a new localized data blob or extend the existing one? | **Extend the existing `acrossaiAbilityLibraryData` blob**. | One less inline script tag; one less `window.*` global to reason about. Cost: single new key `addonsUrl`. |
| Which WordPress icon should the empty state use? | **`plugins`** from `@wordpress/icons`. | Semantically matches "install an add-on"; already available in the loaded `@wordpress/icons` bundle (no dep add). |
| Icon size? | **40px** (Icon component `size` prop). | Fits the 72px circular icon well with 16px padding; consistent with wp-admin empty-state conventions. |
| Should the CTA button carry an external icon? | **Yes — `external` from `@wordpress/icons`**, right-positioned. | Signals to the user that the button navigates away from the current page even though it stays within wp-admin. Same package, no dep add. |
| Should there be a secondary "Refresh page" or "Documentation" link? | **No**. | Keeps the empty state focused on the single most useful action (install an add-on). A second link would compete with the primary CTA for attention. |
| Should the "AcrossAI Core Abilities" name be a link to WordPress.org? | **No**. | The Add-ons page already surfaces install buttons for the correct catalogue entries. Adding an off-site link would fragment the user's install path. |
| Add a memory pattern for "empty-state design"? | **No**. | The change is a one-off polish, not a repeating pattern. Adding a memory entry would inflate the memory footprint for no future planning value. |

## Phase 1 — Design

### Data Model

No change. Zero DB schema modifications. Zero option additions. Zero meta additions.

### Contracts

Post-Feature 042 contracts:

- **Localized JS data blob (`window.acrossaiAbilityLibraryData`)**:
  ```jsonc
  {
    "definitions": [ /* … existing … */ ],
    "restBase":    "https://…/wp-json/acrossai-abilities-library/v1",
    "nonce":       "…",
    "addonsUrl":   "https://…/wp-admin/admin.php?page=acrossai-addons"   // NEW in 042
  }
  ```
- **Empty-state DOM contract** (rendered only when `items.length === 0`):
  ```html
  <div class="acrossai-library-page__empty" role="region" aria-labelledby="acrossai-library-empty-title">
    <div class="acrossai-library-page__empty-icon">…svg…</div>
    <h2 id="acrossai-library-empty-title" class="acrossai-library-page__empty-title">No abilities registered yet</h2>
    <p class="acrossai-library-page__empty-description">…mentions "AcrossAI Core Abilities"…</p>
    <div class="acrossai-library-page__empty-actions">
      <a class="components-button is-primary" href="…addonsUrl…">Browse add-ons</a>
    </div>
    <p class="acrossai-library-page__empty-hint">Tip: open the Add-ons page…</p>
  </div>
  ```
- **REST**: unchanged. No REST field added or removed.

### Quickstart

Per-task verification recipes in [tasks.md](./tasks.md). Feature 042 does not need a separate quickstart.md — the manual walkthrough is a single WP-Admin → Library page check on a fresh install.

## Complexity Tracking

Nothing to track. Zero deviations. Zero accepted-deviation-status changes. Zero Constitution amendments. Zero new modules, tables, dependencies, or memory patterns.

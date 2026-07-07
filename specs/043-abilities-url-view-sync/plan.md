# Implementation Plan: Deep-linkable Edit URLs for Custom Abilities

**Branch**: `043-abilities-url-view-sync` | **Date**: 2026-07-07 | **Spec**: [spec.md](./spec.md)

## Summary

Introduce a single URL-sync layer in the Custom Abilities React root (`AbilitiesManager.jsx`) that keeps `window.location` in lockstep with the `acrossai/abilities` store's `view` state. Two-file code change — one new hook (`useUrlViewSync.js`) + a one-line import + one-line call in `AbilitiesManager.jsx`. Rebuilds the abilities bundle. Zero PHP / REST / DB changes. Uses `@wordpress/url` for all URL manipulation so `wp-url` is auto-declared as a bundle dep by `@wordpress/scripts`.

## Technical Context

**Language/Version**: JavaScript ES2020 / React 18 via `@wordpress/scripts`. PHP untouched.
**Primary Dependencies**: `@wordpress/element` (existing), `@wordpress/data` (existing), `@wordpress/url` (NEW at build-dep level — auto-injected). No new npm package add; `@wordpress/url` is already installed as a transitive dep of `@wordpress/scripts` and is available as an external at runtime via the shared `wp-url` script.
**Storage**: No change. No option added, no meta, no DB row read or written.
**Testing**: No new PHPUnit test. Manual UI walkthrough (T009) is the accepted verification path. The two pure helpers (`parseViewFromUrl`, `buildUrlFromView`) are named exports per `PATTERN-NAMED-EXPORT-JEST` — a Jest test could be added later without React-render machinery. PHP test count unchanged (holds at 105).
**Target Platform**: WordPress 6.9+ admin, PHP 8.1+, modern evergreen browsers.
**Project Type**: WordPress plugin — single project.
**Performance Goals**: Zero regression. The hook adds three `useEffect`s (one mount-only, one on `view` change, one for `popstate` listener lifecycle) — all O(1). Bundle size grows by <1 KB minified.
**Constraints**: No new i18n strings (the hook renders no UI). No new script/style enqueue. No change to the `wp_localize_script` payload. No change to the `AbilityForm` or `AbilitiesList` component logic (Edit stays a `<button>`).
**Scale/Scope**: 1 new JS file (~110 LoC with comments), 1 edited JS file (2 lines: an import + a hook call), 2 rebuilt bundles, 4 spec-kit files. Zero memory-file edits.

## Constitution Check

| Principle (CONSTITUTION.md v1.4.8) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Abilities module ownership | Yes | ✅ Pass | New hook lives under `src/js/abilities/hooks/`, alongside the module it belongs to. No cross-module dependency introduced. |
| §I Boot Flow Rule | Yes | ✅ Pass | No hook registration or PHP loader edit. Root `Main.php` untouched. |
| §I Admin Partials Rule | Yes | ✅ Pass | `admin/Partials/Menu.php` untouched. |
| §I Module Contract (singleton) | Yes | ✅ Pass | Frontend-only change; PHP singletons unaffected. |
| §II WordPress Standards | Yes | ✅ Pass | Uses `@wordpress/url` for URL manipulation — canonical WP JS package. Uses `@wordpress/data` selectors/dispatchers per the existing store convention. |
| §II `acrossai_` prefix | Yes | ✅ Pass | No new PHP identifiers. JS hook name follows React convention (`useUrlViewSync`). |
| §II Multisite compatible | Yes | ✅ Pass | Client-only; `window.location` is per-request per-user. |
| §III UI Contract (DataForm / DataViews) | Yes | ✅ Pass | Feature 043 does not touch the DataViews table or DataForm surface. It sits above them, at the router layer. |
| §IV Security First | Yes | ✅ Pass | No user input flows into the URL sync (view state is dispatched by trusted button clicks). `@wordpress/url` handles encoding safely. |
| §V Extensibility Without Core Modification | Yes | ✅ Pass | No new extension point added or removed. |
| §VI DRY | Yes | ✅ Pass | Reuses existing `setView` action, existing `AbilityForm` mount-time `fetchAbility(slug)` path (line 277), existing `beforeunload` guard, existing scroll save/restore. |
| §VII Definition of Done | Yes | ✅ Verified via per-task gates in [tasks.md](./tasks.md). |

**Constitution Gate**: **PASS**. No amendment required. No accepted-deviation change.

## Memory Synthesis Findings

Synthesized in [memory-synthesis.md](./memory-synthesis.md). Highlights applied to this plan:

- **`PATTERN-NAMED-EXPORT-JEST`** — the two pure helpers (`parseViewFromUrl`, `buildUrlFromView`) are named exports so they can be Jest-tested without rendering React. Applied.
- **`AC-ENQUEUE-ADMIN`** (Feature 027) — the abilities bundle is enqueued via `wp_register_script(..., $this->abilities_asset_file['dependencies'], ...)`. Because `@wordpress/scripts`' DependencyExtractionWebpackPlugin auto-writes `wp-url` into `abilities.asset.php` on the next `npm run build`, WordPress will automatically enqueue the shared `wp-url` script. No manual PHP dep-array edit needed.
- **`AbilityForm.jsx:267-279`** — on-mount `dispatch.fetchAbility(slug)` code path already exists for edit mode. Deep-linked mounts flow through the exact same code path (no `initialAbility` from the list) and get correct behavior for free, including error surfacing.
- **No new memory pattern warranted**. `useUrlViewSync` is a one-off router-layer hook; capturing it as a plugin-wide pattern would be premature (n=1).

## Project Structure

### Documentation (this feature)

```text
specs/043-abilities-url-view-sync/
├── spec.md              # 8 FRs, 7 SCs, 2 user stories, 8 edge cases
├── plan.md              # This file
├── tasks.md             # 10 tasks across 6 phases
└── memory-synthesis.md  # Memory hygiene synthesis
```

### Source Code (repository root)

**Files ADDED** (1):

```text
src/js/abilities/hooks/
└── useUrlViewSync.js                                     # NEW — hook + two pure helpers
```

**Files EDITED** (1):

```text
src/js/abilities/components/AbilitiesManager.jsx         # import + hook call (2-line diff)
```

**Files REBUILT** (2):

```text
build/js/abilities.js                                    # +~1 KB minified
build/js/abilities.asset.php                             # `wp-url` auto-added to deps
```

**Files NOT touched**:

```text
admin/                                                   # No PHP admin edit
includes/                                                # No PHP module edit
src/js/abilities/store/                                  # No store change (reuses setView)
src/js/abilities/components/AbilitiesList.jsx            # Edit button stays a <button>
src/js/abilities/components/AbilityForm.jsx              # AbilityForm handles fetchAbility on mount already
tests/phpunit/                                           # No test added or modified
docs/memory/                                             # No memory entry added
```

**Structure Decision**: Single-project WordPress plugin. Router-layer UX feature. No new directories except the new `hooks/` folder inside the existing abilities module. No new tables, dependencies, or memory patterns.

## Phase 0 — Research Findings

| Question | Decision | Rationale |
|---|---|---|
| Convert the Edit `<button>` to an `<a href>`? | **No**. Keep as `<button>`. | Middle-click / cmd-click "open in new tab" on an `<a>` would bypass the store-driven fetch and produce inconsistent state. Deep-link support via mount-time URL parse covers new-tab correctly. Button element also matches the interaction semantics (state change on same page) and preserves screen-reader affordances. |
| URL param naming? | **`?action=edit&slug=<slug>`** | Matches WP-core convention (`?action=edit&user_id=…`, `?action=edit&post=…`). Zero surprise for anyone familiar with wp-admin. |
| Cover the `create` view too? | **No — deferred**. | User confirmed: only Edit for now. No user-visible "Add New" button ships. When it does, extending the hook to handle `?action=new` is ~5 LoC and can happen in the same commit that adds the button. |
| URL library — `@wordpress/url` or raw `URLSearchParams`? | **`@wordpress/url`**. | User feedback: "make sure we are using the wordpress packages". `@wordpress/scripts`' DependencyExtractionWebpackPlugin auto-declares `wp-url` in the bundle asset file — no manual PHP dep-array edit. Uses the same encoded/decoded conventions the rest of wp-admin uses. |
| History push vs. replace? | **Always `pushState`, with same-URL skip**. | Simpler than tracking "was this deep-linked or user-initiated". The same-URL skip prevents duplicate first-render history entries. Skipping in that one case gives semantically correct back-button behavior without complexity. |
| Handle `beforeunload` interception on Back? | **No — do nothing**. | `pushState` is a same-document navigation and does not fire `beforeunload` in any browser. The existing dirty-form guard continues to guard real page unloads. Adding a router-level dirty guard for Back-into-list would be a UX change beyond this feature's scope. |
| Register a memory pattern for router-layer URL sync? | **No**. | n=1. Capture on recurrence. If a second admin surface (e.g. Keys, Library) gains its own URL sync layer, capture then. |

## Phase 1 — Design

### Data Model

No change. Zero DB schema modifications. Zero option additions.

### Contracts

Post-Feature 043 client-side contracts:

- **URL scheme owned by this feature**:
  ```
  ?page=acrossai-abilities-manager                              → view = 'list'
  ?page=acrossai-abilities-manager&action=edit&slug=<slug>      → view = { mode: 'edit', slug: <slug> }
  ```
  Any other query args (`_wpnonce`, notice flags, add-on tracking) are preserved.

- **Hook module contract** — `src/js/abilities/hooks/useUrlViewSync.js`:
  ```jsx
  export function parseViewFromUrl(url: string): 'list' | { mode: 'edit', slug: string }
  export function buildUrlFromView(view, currentUrl: string): string
  export default function useUrlViewSync(): void
  ```
  The two pure helpers are named-exported for testability. The default export is the React hook.

- **`AbilitiesManager.jsx` contract**: adds `useUrlViewSync()` call as the first line of the component body. No other change; router logic and effects (`beforeunload`, scroll save/restore) unchanged.

- **REST**: unchanged. Deep-linked mounts flow through the existing `dispatch.fetchAbility(slug)` at `AbilityForm.jsx:277` — same code path as a page reload during edit.

### Quickstart

Per-task verification recipes in [tasks.md](./tasks.md). Feature 043 does not need a separate quickstart.md — the manual walkthrough is a 6-step click-through on any WP install with at least one Custom Ability registered.

## Complexity Tracking

Nothing to track. Zero deviations. Zero accepted-deviation-status changes. Zero Constitution amendments. Zero new modules, tables, dependencies (only a build-time WP dep auto-declared by DependencyExtractionWebpackPlugin), or memory patterns.

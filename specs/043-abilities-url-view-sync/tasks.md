---
description: "Task list for Feature 043 — Deep-linkable Edit URLs for Custom Abilities"
---

# Tasks: Deep-linkable Edit URLs for Custom Abilities

**Input**: Design documents from `/specs/043-abilities-url-view-sync/`
**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [memory-synthesis.md](./memory-synthesis.md)

**Tests**: No new PHPUnit tests. Test count unchanged (holds at 105). Manual walkthrough (T009) is the accepted verification path. The two pure hook helpers (`parseViewFromUrl`, `buildUrlFromView`) are named exports so a Jest test can be added later.

**Organization**: Tasks grouped by phase; each task has an exact target file + verification step.

## Format: `[ID] [P?] Description`

- **[P]**: Can run in parallel (different files, no dependencies).

## Phase 1: Setup

- [x] **T001** Confirm branch `043-abilities-url-view-sync` created from `main` post-0.0.5. Working tree clean apart from unrelated `.claude/settings.local.json` + `.phpunit.cache/test-results` local dev state.

## Phase 2: Foundational (Blocking Prerequisites)

- [x] **T002** Grep audit — confirm the `acrossai/abilities` store already exposes a `setView` action and a `getView` selector, so the hook can piggy-back rather than adding new store surface:
  ```
  grep -n "setView\|getView" src/js/abilities/store/index.js
  ```
  Confirmed: action creator at line 186 (`setView: (view) => ({ type: SET_VIEW, view })`) and selector at the store bottom.

- [x] **T003** Grep audit — confirm `AbilityForm` in edit mode fetches by slug on mount (so deep-linked mounts have a data-load path with no extra work):
  ```
  grep -n "dispatch.fetchAbility" src/js/abilities/components/AbilityForm.jsx
  ```
  Confirmed at line 277: `dispatch.fetchAbility(slug)`.

- [x] **T004** Grep audit — confirm no other admin page in this plugin already uses a URL-sync layer we could reuse:
  ```
  grep -rn "history.pushState\|popstate" src/js/
  ```
  Confirmed: zero hits pre-043. This is the first URL-sync layer in the plugin.

**Checkpoint**: Scope confirmed as 1 new hook file + 1 edited component file + 2 rebuilt bundles + 4 spec-kit files. No PHP, no store, no REST, no memory-file edit.

## Phase 3: New hook

- [x] **T005** Create `src/js/abilities/hooks/useUrlViewSync.js`:
  - Imports: `useEffect` from `@wordpress/element`; `useSelect`, `useDispatch` from `@wordpress/data`; `addQueryArgs`, `getQueryArg`, `removeQueryArgs` from `@wordpress/url`; `STORE_NAME` from the store.
  - Named exports: `parseViewFromUrl(url)` — returns `'list'` or `{ mode: 'edit', slug }`; `buildUrlFromView(view, currentUrl)` — returns a URL with `action`/`slug` set/removed and all other args preserved.
  - Default export: `useUrlViewSync()` hook with three effects:
    1. Mount-once: parse `location.href`, if action=edit + slug present, dispatch `setView({ mode: 'edit', slug })`.
    2. On `view` change: build target URL; if it differs from `location.href`, `history.pushState({}, '', nextUrl)`.
    3. `popstate` listener: on browser Back/Forward, re-parse URL and dispatch `setView(...)`.

## Phase 4: Wire the hook into the root

- [x] **T006** Edit `src/js/abilities/components/AbilitiesManager.jsx`:
  - Add `import useUrlViewSync from '../hooks/useUrlViewSync';` next to the existing store import.
  - Call `useUrlViewSync();` as the first line of the `AbilitiesManager()` component body.
  - No other change. Existing `beforeunload` guard, scroll save/restore, and view-router branches stay verbatim.

## Phase 5: Build + verify bundle

- [x] **T007** [P] Run `npm run build`. Confirm zero errors.
- [x] **T008** [P] Verify `build/js/abilities.asset.php` has `wp-url` in its `dependencies` array (auto-added by `@wordpress/scripts`' DependencyExtractionWebpackPlugin):
  ```
  cat build/js/abilities.asset.php
  ```
  Expected: `dependencies` array contains `wp-url`.

- [x] **T009** [P] Verify the bundle no longer contains a new `URLSearchParams` reference (the hook uses `@wordpress/url` instead). Count should remain at 1 — the pre-existing one inside `src/js/abilities/api/client.js`:
  ```
  grep -c "URLSearchParams" build/js/abilities.js
  ```
  Expected: 1.

## Phase 6: Verification + PR

- [ ] **T010** Manual walkthrough on the local WordPress 7.0 install:
  1. Visit `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-abilities-manager` → list renders, URL unchanged.
  2. Click **Edit** on `ai/comment-analysis` → URL becomes `…&action=edit&slug=ai%2Fcomment-analysis`; form renders with that ability loaded.
  3. Click browser **Back** → returns to list; URL drops `action` and `slug`.
  4. Click browser **Forward** → returns to edit; URL restored.
  5. Copy the edit URL, open in a fresh tab → form loads directly on the same ability (no list flash).
  6. Visit `…&action=edit&slug=does-not-exist` → form mounts, REST 404, existing error banner surfaces.
  7. Visit `…&action=edit` (no slug) → falls back to list.
  8. Make an edit (do not save), click browser Back → NO `beforeunload` prompt. Reload the tab while dirty → prompt still fires (regression guard).

- [x] **T011** Commit changes on `043-abilities-url-view-sync` (spec-kit + code + build). Push to origin.

- [x] **T012** Open PR against `main` titled "Feature 043 — Deep-linkable Edit URLs for Custom Abilities" with a body referencing this spec directory.

**Final Checkpoint**: T001–T009 and T011–T012 completed. T010 manual walkthrough pending pre-merge validation on the local install.

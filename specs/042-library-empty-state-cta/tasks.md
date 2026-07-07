---
description: "Task list for Feature 042 — Library empty-state refresh with Add-ons CTA"
---

# Tasks: Library empty-state refresh with Add-ons CTA

**Input**: Design documents from `/specs/042-library-empty-state-cta/`
**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [memory-synthesis.md](./memory-synthesis.md)

**Tests**: No new PHPUnit tests. Test count unchanged (holds at 105). Verification is a manual UI walkthrough on an empty install (T009).

**Organization**: Tasks grouped by phase; each task has an exact target file + verification step.

## Format: `[ID] [P?] Description`

- **[P]**: Can run in parallel (different files, no dependencies).

## Phase 1: Setup

- [x] **T001** Confirm branch `042-library-empty-state-cta` created from `main` post-0.0.5. Working tree clean.

## Phase 2: Foundational (Blocking Prerequisites)

- [x] **T002** Grep audit — confirm the `acrossai-addons` submenu slug is the canonical Add-ons page identifier and is stable:
  ```
  grep -rn "SUBMENU_SLUG\s*=\s*'acrossai-addons'" vendor/acrossai-co/main-menu/src/
  ```
  Confirmed at `vendor/acrossai-co/main-menu/src/Addons/MenuRegistrar.php:7`. Established by Feature 026; preserved by Feature 038.

- [x] **T003** Grep audit — locate the pre-042 empty-state string in source:
  ```
  grep -rn "No abilities registered yet" src/
  ```
  Confirmed at `src/js/ability-library/components/LibraryPage.js` (single hit inside the `items.length === 0` branch).

**Checkpoint**: Scope confirmed as 3 code files + 2 rebuilt bundles + 4 spec-kit files. No PHP module edited. No test file touched.

## Phase 3: Server-side wiring

- [x] **T004** Edit `admin/Main.php` — inside the `enqueue_scripts()` Library-page branch, add `'addonsUrl' => admin_url('admin.php?page=acrossai-addons')` to the array passed to `wp_json_encode()` for the `window.acrossaiAbilityLibraryData` inline script. Existing keys (`definitions`, `restBase`, `nonce`) unchanged.

## Phase 4: React empty-state block

- [x] **T005** Edit `src/js/ability-library/components/LibraryPage.js`:
  - Import `Button` from `@wordpress/components`, and `Icon`, `plugins`, `external` from `@wordpress/icons`.
  - Replace the single-`<p>` `items.length === 0` branch with a five-element structured block:
    1. `.acrossai-library-page__empty-icon` — 40px `plugins` icon inside a circular well.
    2. `<h2 id="acrossai-library-empty-title">` — "No abilities registered yet".
    3. `.acrossai-library-page__empty-description` — mentions "AcrossAI Core Abilities" by name.
    4. `.acrossai-library-page__empty-actions` — primary Button with `href={data.addonsUrl}` and right-positioned `external` icon; wrapped in `{data.addonsUrl && (…)}` guard.
    5. `.acrossai-library-page__empty-hint` — "Tip: open the Add-ons page…" divider-separated hint line.
  - Wrapper carries `role="region"` and `aria-labelledby="acrossai-library-empty-title"` (FR-005).

## Phase 5: Styles

- [x] **T006** Edit `src/scss/ability-library/admin.scss` — expand the `.acrossai-library-page__empty` block from a single colour rule to a five-element BEM block:
  - Block: card (`background:#fff`, `border`, `border-radius:8px`, `padding:40px 24px`, `box-shadow`, `text-align:center`, `max-width:640px`, auto margins).
  - `__empty-icon`: 72px circular well, `#f0f6fc` fill, `#2271b1` foreground, SVG `fill: currentColor`.
  - `__empty-title`: 18px semibold, `$txt` colour.
  - `__empty-description`: `$muted` colour, 14px, `max-width:480px`, `line-height:1.6`.
  - `__empty-actions`: 16px bottom margin; nested `svg` left-margin 4px.
  - `__empty-hint`: 12px, `$muted`, top border `1px dashed $border`, 16px top padding.

## Phase 6: Build

- [x] **T007** Run `npm run build` and confirm zero errors. Confirm the new class names land in `build/css/ability-library.css`:
  ```
  grep -o "acrossai-library-page__empty[a-z-]*" build/css/ability-library.css | sort -u
  ```
  Expected: six entries (block + 5 elements).

- [x] **T008** Verify localized data payload includes `addonsUrl` and the new strings landed in the built bundle:
  ```
  grep -o "AcrossAI Core Abilities\|Browse add-ons\|addonsUrl" build/js/ability-library.js | sort -u
  ```
  Expected: all three tokens present.

## Phase 7: Verification + PR

- [ ] **T009** Manual walkthrough on the local WordPress 7.0 install:
  - Visit `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-abilities-library`.
  - Confirm the empty-state card renders (not the pre-042 paragraph).
  - Confirm the description mentions "AcrossAI Core Abilities" by name.
  - Click "Browse add-ons" → confirm the browser navigates to `admin.php?page=acrossai-addons`.
  - Open DevTools → confirm zero JS console errors and zero PHP notices in `wp-content/debug.log` (if `WP_DEBUG` is on).
  - After installing at least one add-on that registers an ability, reload the Library page and confirm the empty-state card is GONE and the tab-panel + LibraryCard grid renders as before (regression guard for FR-007 / SC-006).

- [x] **T010** Commit changes on `042-library-empty-state-cta` and push to origin. Open PR against `main` with a title like "Feature 042 — Library empty-state refresh with Add-ons CTA" and a body referencing this spec directory.

**Final Checkpoint**: T001–T008 and T010 completed. T009 manual walkthrough pending pre-merge validation on the local install.

# Memory Synthesis — Feature 043

**Feature**: Deep-linkable Edit URLs for Custom Abilities
**Date**: 2026-07-07
**Query**: "abilities admin url router history push view state react"

## Retrieved memory entries (pre-implementation)

### Patterns referenced

- **`PATTERN-NAMED-EXPORT-JEST`** (ARCHITECTURE.md, established across the JS codebase — `LibraryPage.js`, `LibraryCard.js`, `groupDefinitions`, `filterItemsByTabGroup`, etc.) — pure helpers used by React components are exposed as **named exports** so they can be unit-tested with Jest without rendering React. **Applied**: `parseViewFromUrl` and `buildUrlFromView` in `useUrlViewSync.js` are named exports; the hook itself is the default export.

- **`AC-ENQUEUE-ADMIN`** (Feature 027 architecture constraint) — the abilities bundle is registered via `wp_register_script(..., $this->abilities_asset_file['dependencies'], ...)` in `admin/Main::enqueue_scripts()`. Because `@wordpress/scripts`' `DependencyExtractionWebpackPlugin` writes the JS-side dependency list into `abilities.asset.php` on every build, importing `@wordpress/url` in the new hook automatically adds `wp-url` to the enqueued script's deps. **No manual PHP dep-array edit is needed.**

- **`PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`** (ARCHITECTURE.md, Feature 037) — not applicable. Feature 043 does not touch the ability-definition input surface.

### Decisions referenced

- **`DEC-META-ACROSSAI-NAMESPACE`** (DECISIONS.md, Feature 041) — not applicable. Feature 043 does not touch ability args.
- **`DEC-HOOK-PARAM-EXTRACTION`** (DECISIONS.md, Feature 040 supersedes) — general pattern of defensive `is_array()` / `isset()` extraction. Not directly applicable (JS-side, not PHP), but the same discipline informs the `parseViewFromUrl` guards (typeof + non-empty check on `slug`).

### Architecture constraints referenced

- **`AC-HOOKS-MAIN`** — only root `Main.php` registers hooks via the Loader. Feature 043 does NOT register or remove any PHP hook.
- **`AC-ENQUEUE-ADMIN`** (see above) — the auto-injection of `wp-url` into `abilities.asset.php` means we do not need to change the PHP enqueue.

### Bug patterns referenced

None applicable. Feature 043 does not touch data merging, override, or cast surfaces.

### Worklog milestones referenced

- **Feature 011** — introduced the Custom Abilities top-level admin page.
- **Feature 026** — Add-ons page integration; established `acrossai-addons` submenu slug.
- **Feature 027** — Ability Library submenu + the `AC-ENQUEUE-ADMIN` constraint (Library uses `wp_add_inline_script`; Abilities uses `wp_localize_script`-equivalent via `enqueue_scripts`).
- **Feature 033/037** — Library sub-group + tab-group display fields (documented for cross-reference only; unrelated to 043).

## Soft conflicts detected

None. Feature 043 sits above the existing store slice, uses established conventions (`@wordpress/data`, `@wordpress/element`, `@wordpress/url`), and does not touch any surface owned by another decision or pattern.

## Applied to plan

- **`@wordpress/url` for URL manipulation** — user feedback ("make sure we are using the wordpress packages"). Also aligns with `AC-ENQUEUE-ADMIN` — WP-registered externals are the preferred runtime dependency form for admin JS bundles.
- **`@wordpress/element` + `@wordpress/data`** for React and store access — matches the pattern used by every other component in `src/js/abilities/`.
- **Named-exported pure helpers** in `useUrlViewSync.js` per `PATTERN-NAMED-EXPORT-JEST`.
- **Reuse `dispatch.fetchAbility(slug)` at `AbilityForm.jsx:277`** — deep-linked mounts have no `initialAbility` and rely on this existing REST fetch path. Zero new fetch code.
- **No `<a href>` conversion for the Edit button** — deep-link support via URL parse on mount handles new-tab correctly, so the accessibility argument (button semantics matching interaction) wins.

## Post-implementation memory-hygiene actions

**None planned.** Feature 043 is a router-layer polish on a single admin page. Capturing a `PATTERN-ADMIN-URL-VIEW-SYNC` at n=1 would violate the "capture on recurrence" rule. If the Ability Library page or the Keys page later gains its own URL sync layer, the recurring convention could be captured then.

## Token savings vs full-memory read

Optimizer disabled (`.specify/extensions/memory-md/config.yml` absent). Markdown-only synthesis flow used. No token-report generated.

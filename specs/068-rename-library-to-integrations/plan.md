# Implementation Plan: Rename "Ability Library" admin page to "Ability Integrations"

**Branch**: `079-rename-library-to-integrations` | **Date**: 2026-08-14 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/068-rename-library-to-integrations/spec.md`

## Summary

Surface-only rename of the plugin's admin page. Three visible i18n strings change ("Ability Library" → "Ability Integrations", "Library" → "Integrations"); the `add_submenu_page()` slug changes (`acrossai-abilities-library` → `acrossai-abilities-integrations`); the React h1 in `LibraryPage.js` changes; the compiled JS bundle is rebuilt via `wp-scripts build`. Nothing else in the codebase is renamed — REST namespace, PHP class names, hook names, DOM mount id, directory paths, filter names, and internal method names all remain on their existing "Library" spellings. This is a deliberate scope choice made by the user when presented with "Surface only" vs "Full rename".

## Technical Context

**Language/Version**: PHP 8.1+, JavaScript (React) via `@wordpress/scripts` toolchain
**Primary Dependencies**: WordPress 6.9+, `@wordpress/element`, `@wordpress/url`, `@wordpress/components`, `@wordpress/i18n`
**Storage**: N/A (no persistence layer touched — pure UI/routing change)
**Testing**: PHPUnit 10.5 (source-inspection style) for PHP; jest exists but is not wired into CI, so text-only asserts in `useLibraryTabSync.test.js` are updated but not runtime-verified
**Target Platform**: WordPress `wp-admin`
**Project Type**: WordPress plugin (single-project layout)
**Performance Goals**: N/A (no request-path code path is added or removed)
**Constraints**: Must not break the REST endpoint namespace `/wp-json/acrossai-abilities-library/v1/`; must not break the DOM mount contract between PHP-rendered `<div id="acrossai-library-root">` and JS `document.getElementById('acrossai-library-root')`
**Scale/Scope**: 7 files edited. 3 PHP strings, 1 JS heading, 2 doc-comment URL examples, 8 jest test URL literals, 2 rebuilt bundle assets (`build/js/ability-library.js` + `.asset.php`).

## Constitution Check

*GATE: Must pass before implementation. Re-check after implementation.*

- **I. Modular Architecture** — No new modules, files, or abstractions. Pure edit-in-place. ✅
- **II. WP Standards** — Menu label / page title / slug all i18n'd via `__()` with the correct text domain (`acrossai-abilities-manager`). No forbidden functions introduced. PHPCS (WPCS strict) passes on the edited files. ✅
- **III. User-Centric Design** — Rename is directly user-requested; addresses observed confusion between "Library" (a catalog implying browsing) and "Integrations" (a management surface for third-party bridges). ✅
- **IV. Security First** — Capability check on the submenu (`manage_options`) is unchanged; no new user-supplied data flows added. ✅
- **V. Extensibility** — The REST namespace stays put, preserving external caller contracts. Internal filter/action hooks stay put. Third-party code depending on any of these continues to work. ✅
- **VI. DRY** — No new duplication introduced; the 3 string references (label + title + slug) live in the same file (`admin/Partials/LibraryMenu.php`) and are the single source of truth. ✅
- **VII. Definition of Done** — PHPUnit 1006/1006 pass; PHPCS clean on edited files; PHPStan level 8 clean; `npm run build` compiles cleanly; changelog explicitly notes the breaking bookmark change. ✅

**Gate status**: PASS — no violations.

## Project Structure

### Documentation (this feature)

```text
specs/068-rename-library-to-integrations/
├── plan.md                    # This file
├── spec.md                    # Feature specification
├── tasks.md                   # Phased task list
└── checklists/
    └── requirements.md        # Quality checklist
```

### Source Code (repository root — files touched by this feature)

```text
admin/
└── Partials/
    └── LibraryMenu.php                          # submenu label, page title, slug

src/js/ability-library/
├── components/
│   └── LibraryPage.js                           # h1 heading string
└── hooks/
    └── useLibraryTabSync.js                     # doc-comment URL examples

build/js/                                        # rebuilt via `npm run build`
├── ability-library.js
└── ability-library.asset.php

tests/jest/ability-library/
└── useLibraryTabSync.test.js                    # test URL literals

README.txt                                       # Unreleased changelog entry
```

**Structure Decision**: Single-project WordPress plugin layout (Option 1 from the template). No new directories are created; every edited path already exists.

## Complexity Tracking

No constitution violations to justify.

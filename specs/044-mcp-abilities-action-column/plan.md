# Implementation Plan: MCP Manager Abilities Tab — "Action" Column with Edit Deep-Link + MCP Exposure Warning

**Branch**: `044-mcp-abilities-action-column` | **Date**: 2026-07-08 | **Spec**: [spec.md](./spec.md)

## Summary

Two related additions:

1. Ship a small cross-plugin JS extension in this plugin (`acrossai-abilities-manager`) that consumes the public JS-filter contract exposed by `acrossai-mcp-manager` and appends a new **Action** column with an **Edit** deep-link button to that plugin's Abilities tab DataViews table. The button opens the edit form in a **new tab** via `target="_blank"` + `rel="noopener noreferrer"`.

2. Add a red-labeled warning callout to Section 3 (MCP Exposure) of the Ability Edit form, clarifying that the "Show in MCP" toggle applies to **all** MCP servers on the site.

Five-file change — one new JS entry (`src/js/mcp-abilities-extension/index.js`), one webpack config entry, a small addition to `admin/Main.php` for the guarded enqueue, a JSX insertion inside `AbilityForm.jsx`, and a new SCSS class in `src/scss/abilities/admin.scss`. Zero PHP hook registration, zero REST, zero DB, zero changes to the MCP Manager plugin, zero changes to the URL scheme (Feature 043's URL scheme is consumed as-is).

## Technical Context

**Language/Version**: JavaScript ES2020 / React JSX via `@wordpress/scripts`; PHP 8.1+.
**Primary Dependencies**: `@wordpress/hooks` (NEW at build-dep level for this bundle — auto-injected), `@wordpress/components`, `@wordpress/element`, `@wordpress/i18n`, `@wordpress/url`. All are existing transitive deps of `@wordpress/scripts` and are available as WP-registered runtime externals (`wp-hooks`, `wp-components`, `wp-element`, `wp-i18n`, `wp-url`).
**Storage**: No change. No option added, no meta, no DB row read or written.
**Testing**: No new PHPUnit test. Manual walkthrough (T012) is the accepted verification path. The single JS entry is small enough that the filter registration is testable via a shallow Jest render of the returned field object; deferred until a second sibling extension appears (per §Post-implementation memory-hygiene).
**Target Platform**: WordPress 6.9+ admin, PHP 8.1+, modern evergreen browsers.
**Project Type**: WordPress plugin — single project, cross-plugin extension.
**Performance Goals**: Zero regression. The filter callback is O(1) — spreads the existing fields array once and appends one field object. Render is a single React `<Button>` per row (~198 rows worst-case per the docs' example) — no state, no fetch.
**Constraints**: No CSS bundle. No `wp_set_script_translations()` (matches plugin-wide convention). No changes to any file under `acrossai-mcp-manager/`. No use of the MCP Manager's internal APIs — only the public JS filter surface documented in `acrossai-mcp-manager/docs/abilities-tab-js-filters.md`. Text-domain: `acrossai-abilities-manager` for the `Action` and `Edit` strings.
**Scale/Scope**: 1 new JS file (~40 LoC with header comment), 1 edited webpack config (single entry addition), 1 edited PHP file (`admin/Main.php` — one property, one manifest load in constructor, one guard method, one enqueue block), 1 rebuilt bundle, 4 spec-kit files. Zero memory-file edits.

## Constitution Check

| Principle (CONSTITUTION.md v1.4.8) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Abilities module ownership | Yes | ✅ Pass | New JS module lives under `src/js/mcp-abilities-extension/`, isolated from the `abilities/` and `ability-library/` modules. It consumes only cross-plugin public JS surface — no import from either sibling module. |
| §I Boot Flow Rule | Yes | ✅ Pass | No new hook registration; the sole PHP change adds a property, a guard, and an enqueue block inside the existing `admin/Main` class. Root `Main.php` loader untouched. |
| §I Admin Partials Rule | Yes | ✅ Pass | `admin/Partials/*` untouched. |
| §I Module Contract (singleton) | Yes | ✅ Pass | `admin/Main` remains a singleton; the additions are private methods and a private property. |
| §II WordPress Standards | Yes | ✅ Pass | Uses `@wordpress/hooks`, `@wordpress/components`, `@wordpress/url` — canonical WP JS packages. PHP uses `admin_url()`, `wp_add_inline_script()`, `wp_register_script()`, `wp_enqueue_script()`, `sanitize_key()`, `wp_unslash()`. |
| §II `acrossai_` prefix | Yes | ✅ Pass | Script handle: `acrossai-abilities-manager-mcp-extension`. JS global: `window.acrossaiAbilitiesManagerMcpExtension`. Field id: `aam_action` (short-slug prefix). PHP method: `is_mcp_manager_abilities_tab`. |
| §II Multisite compatible | Yes | ✅ Pass | Client-only rendering; `admin_url()` resolves correctly per-site on network installs. |
| §III UI Contract (DataForm / DataViews) | Yes | ✅ Pass | The extension consumes the MCP Manager's DataViews contract via a documented filter — additive only, no re-shape of existing fields. |
| §IV Security First | Yes | ✅ Pass | `item.slug` is server-generated (came from the WP Abilities API). `addQueryArgs()` handles URL encoding safely. PHP guard uses `sanitize_key( wp_unslash() )` on all query args. No user input flows into the enqueue or URL construction. |
| §V Extensibility Without Core Modification | Yes | ✅ Pass | The whole feature IS an example of extensibility without core modification — the MCP Manager's public JS filter surface. |
| §VI DRY | Yes | ✅ Pass | Reuses Feature 043's URL scheme, `@wordpress/url` for encoding, `wp-components` `Button` for styling, and the plugin's existing manifest-load + guarded-enqueue pattern in `admin/Main.php`. |
| §VII Definition of Done | Yes | ✅ Verified via per-task gates in [tasks.md](./tasks.md). |

**Constitution Gate**: **PASS**. No amendment required. No accepted-deviation change.

## Memory Synthesis Findings

Synthesized in [memory-synthesis.md](./memory-synthesis.md). Highlights applied to this plan:

- **`AC-ENQUEUE-ADMIN`** (Feature 027) — the same manifest-load + guarded-enqueue pattern used for `js/abilities` and `js/ability-library` bundles applies here verbatim. Load `.asset.php` defensively in the constructor, register + enqueue in `enqueue_scripts()` under a private guard. No manual PHP dep-array edit except appending `acrossai-mcp-manager-abilities` to enforce load order.
- **`PATTERN-NAMED-EXPORT-JEST`** — not applied at n=1 (single-file extension with no pure helpers worth extracting).
- **Feature 043 URL scheme** — the deep-link URL `?page=acrossai-abilities-manager&action=edit&slug=<slug>` is the accepted canonical form. Consuming it here does not create a new dependency — the mount-time URL parse at `AbilitiesManager.jsx` already handles arbitrary entry points.
- **No new memory pattern warranted at n=1**. First cross-plugin JS-filter extension in the codebase; capture if a second one appears.

## Project Structure

### Documentation (this feature)

```text
specs/044-mcp-abilities-action-column/
├── spec.md              # 10 FRs, 8 SCs, 2 user stories, 7 edge cases
├── plan.md              # This file
├── tasks.md             # 12 tasks across 6 phases
└── memory-synthesis.md  # Memory hygiene synthesis
```

### Source Code (repository root)

**Files ADDED** (1):

```text
src/js/mcp-abilities-extension/
└── index.js                                             # NEW — ~40 LoC filter registration + Button render
```

**Files EDITED** (4):

```text
webpack.config.js                                        # +1 entry: 'js/mcp-abilities-extension'
admin/Main.php                                           # +1 property, +1 manifest load, +1 guard method, +1 enqueue block
src/js/abilities/components/AbilityForm.jsx              # +1 JSX block: red-tinted MCP-Exposure warning inside Section 3 header
src/scss/abilities/admin.scss                            # +1 CSS class: .sect-note-warn (red background + red left border + red text)
```

**Files EMITTED by build** (4):

```text
build/js/mcp-abilities-extension.js                      # NEW bundle
build/js/mcp-abilities-extension.asset.php               # NEW manifest — deps auto-injected
build/js/abilities.js                                    # rebuilt (JSX addition)
build/css/abilities.css                                  # rebuilt (SCSS class addition)
```

**Files NOT touched**:

```text
acrossai-mcp-manager/                                    # No cross-plugin edits
src/js/abilities/                                        # Feature 043's Custom Abilities UI unchanged
src/js/ability-library/                                  # Library UI unchanged
admin/Partials/                                          # No new admin partial
includes/                                                # No new PHP module
tests/phpunit/                                           # No test added or modified
docs/memory/                                             # No memory entry added
```

**Structure Decision**: Single-project WordPress plugin, one-directional cross-plugin extension. No new directories except the new `src/js/mcp-abilities-extension/` folder. No new tables, no new PHP hooks, no new REST endpoints, no new memory patterns.

## Phase 0 — Research Findings

| Question | Decision | Rationale |
|---|---|---|
| Where does the enqueue live — this plugin's `admin/Main.php` or a new class? | **This plugin's `admin/Main.php`**. | Consistent with `js/abilities` and `js/ability-library` enqueues (see `admin/Main.php:192-200` and constructor loader at `admin/Main.php:108-125`). No new admin partial is warranted for a single bundle. |
| Enqueue gate — hook suffix or `$_GET` query args? | **Query args**. | The MCP Manager's hook suffix belongs to that plugin's `add_submenu_page()` call and is not stable from this side. Query-arg gating is exactly what the MCP Manager itself uses at `admin/Main.php:216-222`. Mirrors that pattern. |
| Script handle naming? | **`acrossai-abilities-manager-mcp-extension`**. | Follows the plugin's `acrossai-abilities-manager-*` prefix convention (see `acrossai-abilities-manager-abilities`, `acrossai-abilities-manager-ability-library`). |
| Field id naming? | **`aam_action`**. | Namespaced short-slug prefix ("aam" = AcrossAI Abilities Manager). Cannot collide with the MCP Manager built-ins (`slug`, `label`, `type`, `category`, `description`, `is_exposed`), and cannot collide with any hypothetical third-party extender using their own prefix. |
| Edit link — `<Button href>` or `<Button onClick>` + `window.location`? | **`href`** with `target="_blank"` + `rel="noopener noreferrer"`. | Product decision: the Edit action always opens in a new tab so the admin never loses their MCP-Abilities-tab context (browsing 100+ abilities, editing a handful). `href` (not `onClick`) preserves right-click / cmd-click behavior for admins who want a different tab-handling flow. `noopener noreferrer` isolates the new tab from the source page — WCAG-safe and matches wp-admin conventions for `_blank` links. |
| Warning callout — `@wordpress/components` `Notice` or a plain `<div>` + SCSS class? | **Plain `<div className="sect-note-warn" role="note">`**. | `Notice` is dismissible-by-default and page-scoped (top-of-page banner semantics). The MCP-Exposure warning is a persistent, per-section advisory that must remain visible every time the section renders. A styled `<div>` with `role="note"` matches the semantic intent and reuses the existing `$red = #d63638` token in `admin.scss`. |
| Warning callout — inherited abilities only, or every source? | **Every source**. | The site-wide side-effect applies universally: even when the plugin declares `show_in_mcp: no`, an admin who overrides it to "enable" here still exposes the ability to every MCP server. Suppressing the callout on custom (db) abilities would send the wrong signal. |
| URL construction — `@wordpress/url` `addQueryArgs` or template string? | **`addQueryArgs`**. | Handles slug encoding (the `/` in `acrossai-core-abilities/block-pattern-list` becomes `%2F`) safely. Matches Feature 043's use of `@wordpress/url` on the consumer side (round-trip guarantee). Auto-declares `wp-url` as a bundle dep. |
| Base URL — hardcoded string or PHP-injected? | **PHP-injected via `wp_add_inline_script(..., 'before')`**. | `admin_url('admin.php?page=acrossai-abilities-manager')` resolves correctly for subdirectory installs, non-standard `wp-admin` paths, and network sites. Hardcoding `admin.php?...` breaks those cases. Falls back to hardcoded default only if the global is missing (defensive-only). |
| Dep array — auto-detected only, or also `acrossai-mcp-manager-abilities`? | **Both**. | Auto-detected deps (from `.asset.php`) cover the WP packages. Adding `acrossai-mcp-manager-abilities` manually enforces load order (WP orders enqueue by dep graph) AND makes the bundle a silent no-op when the MCP Manager plugin is deactivated (WP refuses to enqueue when a declared dep is unregistered). Double win. |
| Register a memory pattern for cross-plugin JS-filter extensions? | **No**. | n=1. First such extension in the codebase. Capture on recurrence — if a Keys-plugin extension or a second column on this same surface ships, capture then as `PATTERN-CROSS-PLUGIN-JS-FILTER-EXTENSION`. |

## Phase 1 — Design

### Data Model

No change. Zero DB schema modifications. Zero option additions. Zero meta additions.

### Contracts

**Consumed** (external, MCP Manager plugin owns):

- **`acrossaiMcpManager.abilities.fields`** — `@wordpress/hooks` filter, applied at `acrossai-mcp-manager/src/js/abilities.js:584-588`. Signature: `applyFilters(name, fields: Array<Field>, ctx: { serverId: number, serverSlug: string }): Array<Field>`. Additive-only merge reducer at `abilities.js:591` drops any field whose `id` matches a built-in. Return type must be an array (else `safeApplyFilters` falls back to the pre-filter value).
- **Row `item` shape** — `{ slug: string, label: string, type: string, category: string, description: string, is_exposed: boolean, has_override: boolean }`. Only `item.slug` is consumed by this feature. Source: `acrossai-mcp-manager/src/js/abilities.js:359-368`.
- **Enqueue handle** — `acrossai-mcp-manager-abilities`, registered at `acrossai-mcp-manager/admin/Main.php:229-236` with `wp-hooks` in its dep manifest → `wp.hooks` is guaranteed available when our bundle runs.

**Emitted** (new, owned by this feature):

- **JS bundle** — `build/js/mcp-abilities-extension.js`. On load, calls `addFilter('acrossaiMcpManager.abilities.fields', 'acrossai-abilities-manager/action-edit', callback)`. Callback signature: `(fields: Array<Field>, ctx: { serverId, serverSlug }) => Array<Field>`. Returns `[ ...fields, actionField ]` where `actionField` has id `aam_action`.
- **JS global** — `window.acrossaiAbilitiesManagerMcpExtension = { editBaseUrl: string }`. Localized via `wp_add_inline_script(handle, ..., 'before')` before the bundle loads. The bundle reads this and falls back to `admin.php?page=acrossai-abilities-manager` if missing.
- **PHP guard** — `\AcrossAI_Abilities_Manager\Admin\Main::is_mcp_manager_abilities_tab(): bool`. Private. Checks `$_GET['page'] === 'acrossai_mcp_manager'` AND `$_GET['action'] === 'edit'` AND `$_GET['tab'] === 'abilities'`, all via `sanitize_key( wp_unslash( ... ) )`. Nonce-check-exempt via `phpcs:ignore`.

### Quickstart

Per-task verification recipes in [tasks.md](./tasks.md). Feature 044 does not need a separate quickstart.md — the manual walkthrough is a 5-step click-through on any WP install with both plugins active and at least one MCP server registered exposing at least one ability.

## Complexity Tracking

Nothing to track. Zero deviations. Zero accepted-deviation-status changes. Zero Constitution amendments. Zero new modules, tables, or memory patterns. One new build-time WP dep (`wp-hooks`) auto-declared by `DependencyExtractionWebpackPlugin`.

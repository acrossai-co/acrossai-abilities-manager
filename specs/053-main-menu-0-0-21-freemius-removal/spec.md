# Feature Specification: Bump acrossai-co/main-menu 0.0.14 → 0.0.23, remove Freemius, restyle Library header, self-filter Add-ons

**Feature Branch**: `053-main-menu-0-0-21-freemius-removal`
**Created**: 2026-07-17
**Status**: Draft (backfilled post-implementation — reflects shipped code across 5 commits on PR #69)
**Input**: User description: (multi-turn) — "remove the composer require freemius/wordpress-sdk from the main-menu composer package so update the package to 0.0.21 and remove all the code that is related to freemium from this plugins itself" → "can you make the title and Enable All and Disable All button in one line add this in the same pr" → "simple update the main-menu to 0.0.22" → "in the main-menu we have the filter to add/remove the array and if the current plugin is activate please remove it from the filter acrossai_addons" → "simple update the main-menu to 0.0.23"

## Clarifications

### Session 2026-07-17

- Q: How should the release ship — dedicated PR, or bundled with a version bump? → A: Feature branch + PR only. Version bump / changelog / tag deferred to a future release cycle.
- Q: In `acrossai-co/main-menu 0.0.21`, the `\AcrossAI_Addon\AddonsPage` class the plugin used to instantiate no longer exists — how to reconcile? → A: Delete the entire instantiation block (including the try/catch fallback). The Add-ons submenu is now registered internally by `\AcrossAI_Main_Menu\MenuRegistrar` when the shared `SettingsPage` bootstrap runs (already handled by this plugin at `plugins_loaded @ P0`).
- Q: Should the plugin appear in its own Add-ons page listing? → A: No. Filter out the plugin's own entry from the shared `acrossai_addons` list so the Add-ons page shows only other companion plugins.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Zero external service surface via Freemius (Priority: P1)

An administrator installs and runs the plugin. The plugin makes zero external HTTP requests on its own, sends zero data to Freemius, and drops the Freemius vendored SDK entirely from the installable package. The Add-ons page continues to work — it just lists only free WordPress.org add-ons, installable via the standard WP plugin installer.

**Why this priority**: The Freemius SDK is a large vendor tree (~2,000+ files) contributing to plugin ZIP size and external-service surface. Upstream `acrossai-co/main-menu 0.0.21` dropped its Freemius dependency, freeing this plugin to do the same. This is the headline change for the release.

**Independent Test**: `composer show freemius/wordpress-sdk` returns "package not found". `vendor/freemius/` directory does not exist. Grep across production PHP files for `freemius`, `Freemius`, `fs_product_id`, `fs_public_key`, `fs_slug`, `AcrossAI_Addon` returns zero hits (excluding historical changelog entries in `README.txt` and one explanatory comment block in `includes/Main.php`).

**Acceptance Scenarios**:

1. **Given** the plugin is freshly installed via `composer install`, **When** the administrator inspects `vendor/`, **Then** no `freemius/` subdirectory exists.
2. **Given** the plugin is activated, **When** the administrator opens any admin page or the site frontend, **Then** no HTTP request is made to `freemius.com` or any Freemius API endpoint.
3. **Given** the administrator opens `?page=acrossai-addons`, **When** the page renders, **Then** it displays free companion plugins with Install / Activate / Deactivate CTAs and no Connect / Login / Buy affordances.
4. **Given** the site was previously running an older version with Freemius connected, **When** the administrator upgrades to this release, **Then** no data is sent to Freemius from this plugin post-upgrade; any stale `wp_options` rows with `fs_*` or `freemius_*` prefixes are inert (deletion is out of scope for this release).

---

### User Story 2 — Library page title and bulk-action buttons on one horizontal row (Priority: P2)

An administrator viewing the Ability Library page sees the page title "Ability Library" (H1) on the left and the `Enable All` / `Disable All` bulk-action buttons on the right — both on the same horizontal row. The tab strip renders below.

**Why this priority**: Feature 052 (0.0.7) added the bulk-action buttons in a separate row BELOW the WordPress-rendered `<h1>Ability Library</h1>`, wasting vertical space. Compacting to one row is a small ergonomic win and matches administrator expectations for admin page layouts.

**Independent Test**: Open `?page=acrossai-abilities-library`. Verify a single horizontal row above the tab strip contains the title text "Ability Library" on the left and two buttons `Enable All` + `Disable All` on the right. Verify no duplicate H1 renders on the page.

**Acceptance Scenarios**:

1. **Given** the Library page loads, **When** the administrator inspects the page, **Then** exactly one `<h1>` element renders with the text "Ability Library".
2. **Given** the Library page loads, **When** the administrator looks at the layout, **Then** the title and both bulk-action buttons occupy a single horizontal row above the tab strip.
3. **Given** the browser is narrow, **When** the layout wraps, **Then** the buttons remain grouped as a unit (do not split between rows).

---

### User Story 3 — Plugin excluded from its own Add-ons page listing (Priority: P3)

An administrator opens the Add-ons page. The plugin (AcrossAI Abilities Manager) does not appear in the list — since it is obviously already active, showing it as an installable option would be redundant and confusing. All other baseline companion plugins in the shared `acrossai-co/main-menu` list continue to render.

**Why this priority**: Cosmetic / UX polish that improves the Add-ons page for administrators of THIS plugin without affecting other plugins. Any companion plugin can adopt the same self-filter pattern.

**Independent Test**: Open `?page=acrossai-addons`. Verify the AcrossAI Abilities Manager card does NOT appear. Verify the other baseline entries (AcrossAI MCP Manager, AcrossAI Model Manager, Turn Off AI Features) DO appear.

**Acceptance Scenarios**:

1. **Given** the plugin is active and the Add-ons page renders, **When** the administrator scans the card grid, **Then** no card with `data-slug="acrossai-abilities-manager"` is present.
2. **Given** another companion plugin (e.g. AcrossAI MCP Manager) is also active and applies its own similar self-filter, **When** the Add-ons page renders, **Then** each plugin's self-filter runs independently and both cards disappear from the list.

---

### Edge Cases

- **Companion plugin absent**: If `\AcrossAI_Main_Menu\SettingsPage` is not available (Composer autoloader missing, plugin isolation), the existing entry-file `class_exists` guard at `plugins_loaded @ P0` keeps the plugin from fataling. No Add-ons submenu is registered; the plugin's other functionality is unaffected. Verified by the existing `PATTERN-ADMIN-NOTICE-SELF-CONTAINED` bootstrap.
- **Custom filter that adds a duplicate**: If a downstream plugin adds a duplicate `acrossai-abilities-manager` entry to `acrossai_addons` via its own filter, this plugin's filter still removes it — the filter runs across every entry, not just the baseline.
- **Non-array filter input**: If some other filter returns non-array, the self-filter callback returns the input untouched (defensive input guard).
- **Prior release upgrade path**: Administrators upgrading from `0.0.7` (or any prior release that had Freemius) will find any `fs_*` / `freemius_*` options in `wp_options` are now inert — no external requests, no premium flow. Cleanup of stale options is left for a future release (out of scope for Feature 053).

## Requirements *(mandatory)*

### Functional Requirements

**Freemius removal (User Story 1):**

- **FR-001**: `composer.json` MUST require `acrossai-co/main-menu` at version `0.0.23` (or the currently-latest compatible tag) — the release train that dropped the `freemius/wordpress-sdk` dependency starts at 0.0.21.
- **FR-002**: `composer.lock` MUST NOT contain a `freemius/wordpress-sdk` package entry after resolution.
- **FR-003**: The plugin MUST NOT reference the `\AcrossAI_Addon\AddonsPage` class from production code. The class was removed in `main-menu 0.0.21`.
- **FR-004**: The plugin MUST NOT pass Freemius-specific arguments (`fs_product_id`, `fs_public_key`, `fs_slug`) to any vendor class.
- **FR-005**: `README.txt` MUST describe the Add-ons page as offering only free WordPress.org add-ons. Historical changelog entries and Upgrade Notice entries for prior releases (0.0.1, 0.0.6) that mention Freemius MUST be preserved as-is — they document what those releases shipped and are historical record.
- **FR-006**: The plugin MUST make zero external HTTP requests on its own after this release. HTTP requests originating from the WordPress core plugin installer (used to install free add-ons on administrator action) are outside the plugin's control and are not attributable to the plugin.

**Header row layout (User Story 2):**

- **FR-007**: The Library admin page MUST render exactly one `<h1>` element with the text "Ability Library".
- **FR-008**: The Library page MUST render the H1 title and the `Enable All` / `Disable All` buttons on the same horizontal row, separated by flex `space-between` (title anchored left, buttons anchored right).
- **FR-009**: The tab strip and per-category cards MUST continue to render below the header row unchanged.
- **FR-010**: The PHP-side page renderer (`admin/Partials/LibraryMenu.php::render()`) MUST NOT render a `<h1>` — the React app owns the title so the header row can be a single cohesive flex layout.

**Self-filter (User Story 3):**

- **FR-011**: The plugin MUST hook the `acrossai_addons` filter with a callback that returns the input array minus any entries whose `slug` matches `'acrossai-abilities-manager'`.
- **FR-012**: The filter callback MUST be defensive: if the filter input is not an array, return it untouched; if individual entries are not arrays or lack a `slug` key, keep them in the output (do not silently drop malformed entries added by other plugins).
- **FR-013**: The filter MUST be registered via the plugin's Loader in `includes/Main.php::define_admin_hooks()` per `AC-HOOKS-MAIN` variable-first pattern.

**Preservation of existing behavior:**

- **FR-014**: The Add-ons submenu URL `?page=acrossai-addons` MUST continue to resolve to the shared Add-ons page rendered by `\AcrossAI_Main_Menu\AddonsPageRenderer`. This URL is unchanged from prior releases.
- **FR-015**: Feature 052 behavior (tab-scoped bulk toggle, URL-synced tabs, disabled-card UI) MUST continue to work unchanged.
- **FR-016**: All other admin surfaces (Abilities Manager main list, Settings tab, MCP Manager Abilities extension) MUST continue to render and function unchanged.

### Key Entities *(include if feature involves data)*

- **Add-on entry**: A row in the `acrossai_addons` filter output. Each entry is an associative array with at minimum a `slug` key; other keys (`name`, `description`, `icon`, `more_url`, `source`, `download_url`, `install_folder`) are optional. This plugin only reads `slug` when applying the self-filter.
- **Composer package pin**: `acrossai-co/main-menu` is pinned by exact version in `composer.json` per the `DEC-STABLE-UPGRADE-WINDOW-INTERNAL-ORG` internal-org exception.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Zero `freemius`, `Freemius`, `fs_product_id`, `fs_public_key`, `fs_slug`, or `AcrossAI_Addon` references in production code (`.php` / `.json` / `.js` / `.jsx` under `admin/`, `includes/`, `src/`, `acrossai-abilities-manager.php`). Historical `README.txt` changelog entries excluded from this measurement.
- **SC-002**: `vendor/freemius/` directory does not exist after a fresh `composer install`.
- **SC-003**: The installable plugin ZIP is smaller than the 0.0.7 release by at least the size of the Freemius vendored SDK tree (measurable via `composer install --no-dev` + `du -sh vendor/`).
- **SC-004**: On the Library admin page, the H1 title and both bulk-action buttons render on a single horizontal row — verifiable in DevTools by inspecting the `.acrossai-library-page__header` element and confirming exactly one child H1 and one child actions container, both direct children.
- **SC-005**: On the Add-ons admin page, the plugin's own card (`data-slug="acrossai-abilities-manager"`) is absent, while every other baseline entry (verified: `acrossai-mcp-manager`, `acrossai-model-manager`, `turn-off-ai-features`) is present.
- **SC-006**: All 129 PHPUnit tests continue to pass. All 82+ Jest tests in `tests/jest/ability-library/` continue to pass.
- **SC-007**: PHPStan level 8, PHPCS strict, `npm run validate-packages`, and `npm run build` all remain green quality gates.

## Assumptions

- **Upstream package stability**: `acrossai-co/main-menu 0.0.23` (and any subsequent point release) preserves the consumer API surface: `\AcrossAI_Main_Menu\SettingsPage`, `\AcrossAI_Main_Menu\MenuRegistrar`, `\AcrossAI_Main_Menu\AddonsPageRenderer`. Verified in `vendor/acrossai-co/main-menu/src/` at implementation time.
- **Add-ons filter contract**: The `acrossai_addons` filter accepts an array of entries and expects an array back. Each entry is an associative array with a required `slug` key. This is the documented contract in `AddonsPageRenderer.php` at the top of its class docblock.
- **No consumer of removed AddonsPage args**: No downstream code references the `fs_product_id` / `fs_public_key` / `fs_slug` args this plugin used to pass — these were consumed only by the (now-deleted) `\AcrossAI_Addon\AddonsPage` constructor and only ever wired to the Freemius connect flow.
- **wp_options cleanup deferred**: Any pre-existing `fs_*` or `freemius_*` options in `wp_options` are inert after Freemius removal. Cleanup is an operational task (`wp option delete`) or a future `uninstall.php` opt-in — not shipped in Feature 053.
- **Single-admin editing**: No concurrency reconciliation for the Add-ons page or Library page bulk actions. Last-writer-wins as inherited from Feature 052.
- **Release timing deferred**: This branch does code + docs only. A future release cycle (0.0.8 or later) will bump the version constant, add changelog + upgrade notice, tag, and create the GitHub Release.

## Post-implementation deviations from plan

The original plan (see `/Users/raftaar1191/.claude/plans/so-here-i-am-witty-koala.md`) targeted only:

- Bump `main-menu 0.0.14 → 0.0.21`
- Remove Freemius `fs_*` args from `AddonsPage` constructor (turned out the class itself was gone in 0.0.21 — deleted the whole block instead)
- Update 4 non-historical `README.txt` sections

During implementation, three additional changes were requested and shipped in the same PR:

1. **Bumps to 0.0.22 and then 0.0.23** — accumulated over the course of implementation as upstream published new tags. Non-breaking; no code changes required.
2. **Library page header row layout** — title + Enable All / Disable All buttons collapsed onto a single horizontal row. Not related to Freemius removal but shipped in the same PR at user request.
3. **Self-filter on `acrossai_addons`** — the plugin no longer appears in its own Add-ons page listing. Not related to Freemius removal but shipped in the same PR at user request.

The three deviations are documented in the Clarifications section above (Q1/Q2/Q3) and reflected in FR-007..FR-013 above.

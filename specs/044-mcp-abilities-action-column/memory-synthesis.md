# Memory Synthesis — Feature 044

**Feature**: MCP Manager Abilities Tab — "Action" Column with Edit Deep-Link
**Date**: 2026-07-08
**Query**: "cross-plugin JS filter extension enqueue admin wp-hooks add column DataViews"

## Retrieved memory entries (pre-implementation)

### Patterns referenced

- **`AC-ENQUEUE-ADMIN`** (Feature 027, ARCHITECTURE.md) — every admin JS bundle in this plugin follows the same lifecycle: `.asset.php` is loaded defensively in the constructor, then `wp_register_script(handle, url, $asset['dependencies'], $asset['version'], true)` + `wp_enqueue_script(handle)` is called inside `admin/Main::enqueue_scripts()` gated by a private `is_*_page()` guard. Feature 044 mirrors this pattern for the new `mcp-abilities-extension` bundle. The only twist: `array_merge($asset['dependencies'], ['acrossai-mcp-manager-abilities'])` to append the sibling-plugin's handle so WP orders enqueue by dep graph and refuses to enqueue if the sibling is deactivated.

- **`PATTERN-NAMED-EXPORT-JEST`** (ARCHITECTURE.md) — used across `LibraryPage.js`, `LibraryCard.js`, and Feature 043's `useUrlViewSync.js`. **Not applied** at n=1 for Feature 044: the extension is a single filter registration with no pure helpers worth extracting. If a second column or transformation is added later, the field-object factory function becomes worth named-exporting.

- **Feature 043's URL scheme** (`?page=acrossai-abilities-manager&action=edit&slug=<slug>`) — canonical deep-link URL for the Custom Abilities edit view. Consumed as-is by Feature 044's Edit button. `@wordpress/url` `addQueryArgs` on our side + `parseViewFromUrl` on the consumer side round-trip cleanly, including the `/` inside slugs.

### Decisions referenced

- **`DEC-MENU-HOOK-SUFFIX`** (referenced in `admin/Partials/LibraryMenu.php:62`) — for this plugin's own pages we cache the hook suffix returned by `add_submenu_page()` and guard on it. **Not applied** for Feature 044's guard: the target page belongs to the sibling `acrossai-mcp-manager` plugin, whose hook suffix we can't reach directly. Instead we mirror the MCP Manager's own guard style — query-arg checks with `sanitize_key( wp_unslash() )`.

- **`DEC-PROTECTED-SLUGS-PATTERN`** (referenced in `AbilitiesList.jsx:125-126`) — not applicable. Feature 044 does not touch protected-slugs enforcement.

### Architecture constraints referenced

- **`AC-HOOKS-MAIN`** — only root `includes/Main.php` registers hooks via the Loader. Feature 044 does NOT register or remove any PHP hook. The new enqueue block runs on the already-registered `admin_enqueue_scripts` hook (registered at `includes/Main.php:285-287`).

- **`AC-ENQUEUE-ADMIN`** (see above) — the auto-injection of WP-package deps into `mcp-abilities-extension.asset.php` by `@wordpress/scripts`' `DependencyExtractionWebpackPlugin` means we do not need to manually declare `wp-hooks`, `wp-components`, `wp-element`, `wp-i18n`, or `wp-url` in the PHP dep array. The only manual dep is `acrossai-mcp-manager-abilities`, which cannot be auto-detected because it's not a JS import — it's a WP-registered script owned by a sibling plugin.

### Bug patterns referenced

None applicable. Feature 044 does not touch data merging, override, cast, or REST surfaces.

### Worklog milestones referenced

- **Feature 026** — Add-ons page integration; established the `acrossai-addons` submenu slug alongside the plugin's admin surfaces.
- **Feature 027** — Ability Library submenu + the `AC-ENQUEUE-ADMIN` constraint. Directly informs Feature 044's enqueue.
- **Feature 038** — AcrossAI main-menu integration; established the shared `acrossai` parent menu slug that both this plugin and the MCP Manager sit under. Not directly relevant to Feature 044 (which extends the sibling's page, not the parent menu).
- **Feature 043** — Deep-linkable Edit URLs for Custom Abilities. Feature 044 is a downstream consumer — it emits the URL scheme that 043 taught the app to parse.

## Soft conflicts detected

None. Feature 044 introduces the first cross-plugin JS-filter extension in the codebase. It consumes a documented public JS filter surface owned by another plugin and does not touch any surface owned by another decision or pattern within this repo.

**Cross-plugin risk noted** (not a memory conflict, but a decision to log here so future maintainers see it): the MCP Manager filter `acrossaiMcpManager.abilities.fields` is marked `@since 0.1.0 @experimental` in its docs — it may change without notice before 1.0.0. Feature 044 explicitly accepts that risk. If the filter contract changes, this extension breaks silently (column disappears) — not white-screens (safeApplyFilters catches throws).

## Applied to plan

- **`@wordpress/hooks` `addFilter`** — the WP-canonical JS event-hook mechanism. Consuming the sibling's `applyFilters` requires this exact package.
- **`@wordpress/components` `Button`** — matches DataViews table styling out of the box. No custom CSS needed.
- **`@wordpress/url` `addQueryArgs`** — same package Feature 043's consumer side uses. Guarantees round-trip encoding for slug values containing `/`.
- **`@wordpress/i18n` `__`** — text-domain `acrossai-abilities-manager` for "Action" and "Edit" strings. Consistent with all other user-facing strings in this plugin.
- **`admin_url()` PHP helper** — the only correct way to build a wp-admin URL that survives subdirectory installs and non-standard `wp-admin` paths. Hardcoding would break multisite and unusual hosting setups.
- **`sanitize_key( wp_unslash() )` for `$_GET` query args** — matches WP security-hardening conventions AND the MCP Manager's own guard style at `../acrossai-mcp-manager/admin/Main.php:216-222`.
- **Namespaced field id `aam_action`** — the docs at `acrossai-mcp-manager/docs/abilities-tab-js-filters.md:250` explicitly recommend a unique namespaced id to prevent silent drops by the additive-only merge reducer.

## Post-implementation memory-hygiene actions

**None planned.** Feature 044 is a one-off cross-plugin filter registration. Capturing a `PATTERN-CROSS-PLUGIN-JS-FILTER-EXTENSION` at n=1 would violate the "capture on recurrence" rule.

**If a second such extension ships** (e.g., the Keys submenu ships its own column on this same filter surface, or another sibling plugin ships a column, or this feature grows a second column), capture the recurring convention then: guarded enqueue + `array_merge` deps + namespaced field id + `<Button href>` link pattern.

## Token savings vs full-memory read

Optimizer disabled (`.specify/extensions/memory-md/config.yml` absent). Markdown-only synthesis flow used. No token-report generated.

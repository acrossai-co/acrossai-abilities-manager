# Feature Specification: MCP Manager Abilities Tab — "Action" Column with Edit Deep-Link + MCP Exposure Warning

**Feature Branch**: `044-mcp-abilities-action-column`
**Created**: 2026-07-08
**Status**: Implemented
**Input**: User description: (a) "by ref to this acrossai-mcp-manager/docs/abilities-tab-js-filters.md, add a new column called Action, and in that show the Edit button of each ability like Edit → `admin.php?page=acrossai-abilities-manager&action=edit&slug=<slug>`." (b) "when user click on 'Edit' button it should open in new tabs not in the same". (c) "add a note into this via red label that if you enable the MCP here it will be enable for all the MCP server" (referring to Section 3 — MCP Exposure of the AbilityForm).

## Overview

Two related additions:

1. Ship a small JS extension in this plugin (`acrossai-abilities-manager`) that consumes the sibling `acrossai-mcp-manager` plugin's public JS-filter contract — `acrossaiMcpManager.abilities.fields` — and appends a new **Action** column to the MCP Manager's Abilities tab DataViews table. Each row's cell renders an **Edit** button whose `href` deep-links back into this plugin's Custom Abilities edit view (URL scheme delivered by Feature 043). The button opens the deep-link in a **new tab** so the admin never loses their MCP-tab context.

2. Add a red-labeled warning callout inside the **MCP Exposure** section (Section 3) of the Ability Edit form (`AbilityForm.jsx`) that clarifies the site-wide side-effect of the "Show in MCP" toggle: enabling MCP here applies to **all** MCP servers on the site, not just one.

The extension change is entirely one-directional: this plugin ships the extension bundle; the MCP Manager plugin is not modified. If the MCP Manager is not active, the bundle does not enqueue and no user-visible change occurs. The warning callout is unconditional — it renders on every ability's edit form regardless of source.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin jumps from an MCP-exposed ability into its Abilities Manager edit form (Priority: P1)

An admin has both plugins active. They open the MCP Manager's per-server Abilities tab (`admin.php?page=acrossai_mcp_manager&action=edit&server=<id>&tab=abilities`) to inspect which abilities the server exposes. They see an ability that needs configuration (e.g. `acrossai-core-abilities/block-pattern-list`), click the row's **Edit** button, and land directly on that ability's edit form inside the Abilities Manager screen — no intermediate list-screen click.

**Why this priority**: closing the "audit → edit" loop is the primary reason to sit both plugins side-by-side. Today the admin must open a new tab, navigate to Abilities Manager, click Edit, and find the row again. Feature 043 already delivered the deep-link URL scheme; this feature exposes it where the admin actually starts the workflow.

**Independent Test**: On a fresh install with both plugins active and at least one MCP server registered exposing at least one ability, visit the Abilities tab. Verify:
1. A right-most **Action** column is present, one **Edit** button per row.
2. Clicking an Edit button opens `admin.php?page=acrossai-abilities-manager&action=edit&slug=<slug>` (percent-encoded).
3. The Abilities Manager edit form renders that ability directly — the list view never flashes (Feature 043 mount-time URL parse).
4. Right-click the button → "Open in new tab" produces a working new tab with the same edit form.

**Acceptance Scenarios**:

1. **Given** both plugins active and the MCP Manager Abilities tab is showing rows, **When** the admin clicks **Edit** on the row for `acrossai-core-abilities/block-pattern-list`, **Then** navigation is `admin.php?page=acrossai-abilities-manager&action=edit&slug=acrossai-core-abilities%2Fblock-pattern-list` and the Abilities Manager edit form loads that ability.
2. **Given** the admin cmd/ctrl-clicks the Edit button, **When** the browser opens the URL in a background tab, **Then** the Abilities Manager edit form for the same ability loads in that tab (no list flash — Feature 043 handles mount-time URL parse).
3. **Given** only the `acrossai-mcp-manager` plugin is active (this plugin deactivated), **When** the admin opens the Abilities tab, **Then** the Action column is absent and the table renders its built-in columns unchanged.
4. **Given** only this plugin is active (MCP Manager deactivated), **When** the admin opens any wp-admin page, **Then** the extension bundle does not enqueue anywhere; no console errors.
5. **Given** both plugins active but the admin is on a screen other than the MCP Manager Abilities tab (e.g. the main MCP list, or an unrelated wp-admin screen), **When** the page loads, **Then** the extension bundle is not enqueued (guard fails).

---

### User Story 2 — Extension survives the "additive-only" invariant (Priority: P2)

The MCP Manager plugin enforces that extensions may only append new columns, not overwrite the reserved built-in field ids (`slug`, `label`, `type`, `category`, `description`, `is_exposed`). Our extension uses a namespaced id (`aam_action`) that cannot collide with either the built-ins or a hypothetical third-party extender.

**Why this priority**: it's a defensive-correctness guarantee. If the id ever collided, our column would silently vanish; the namespaced id prevents that class of bug forever.

**Independent Test**: Grep the codebase for `aam_action` and confirm the id is used only in `src/js/mcp-abilities-extension/index.js`.

---

### Edge Cases

- **Slug with slash (`acrossai-core-abilities/block-pattern-list`)**: `@wordpress/url` `addQueryArgs(baseUrl, { slug: 'ai/foo' })` writes `slug=ai%2Ffoo` (the `/` is encoded). Feature 043's `parseViewFromUrl` decodes it back to `ai/foo`. No manual `encodeURIComponent` needed.
- **`window.acrossaiAbilitiesManagerMcpExtension` global missing** (unlikely — always injected via `wp_add_inline_script` in `enqueue_scripts()` before the bundle loads): the JS falls back to `admin.php?page=acrossai-abilities-manager`. Sub-directory and non-standard `wp-admin` installs will land on the wrong URL in that failure mode; the inline script is the primary correct-URL guarantor.
- **MCP Manager plugin deactivated after this bundle is registered**: the bundle depends on `acrossai-mcp-manager-abilities`. WordPress's `wp_enqueue_script` refuses to enqueue when a declared dep is unregistered, so the failure mode is a silent no-op — the correct outcome.
- **MCP Manager renames or removes the `acrossaiMcpManager.abilities.fields` filter** (breaking-change scenario): our `addFilter` call still registers, but the filter never fires, so the column simply does not appear. No JS error. Per the MCP Manager docs the filter is `@since 0.1.0 @experimental` — this feature explicitly accepts that pre-1.0 breakage risk.
- **Multiple abilities sharing a slug across MCP servers**: the URL only carries `slug`, which is globally unique in the Abilities Manager namespace. The MCP server context (`serverId`) is intentionally not persisted through the deep-link — the destination edit form is per-ability, not per-server-per-ability.
- **`?action=<unknown>` param on MCP Manager URL**: unrelated to this feature. Our bundle only reacts to `page=acrossai_mcp_manager` + `action=edit` + `tab=abilities`.
- **Extension bundle running before the MCP Manager bundle**: prevented by declaring `acrossai-mcp-manager-abilities` as a script dep; WordPress orders enqueue by dep graph.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The extension MUST register a `@wordpress/hooks` filter on `acrossaiMcpManager.abilities.fields` under a unique handle (`acrossai-abilities-manager/action-edit`) and append exactly one field object to the input array.
- **FR-002**: The appended field object MUST use id `aam_action` (namespaced short-slug prefix, not collidable with any built-in). It MUST set `enableSorting: false` and `enableHiding: false`.
- **FR-003**: The field's `render` MUST return a `@wordpress/components` `<Button variant="secondary" size="small" href={editUrl} target="_blank" rel="noopener noreferrer">` whose `editUrl` is built by `addQueryArgs(baseEditUrl, { action: 'edit', slug: item.slug })` using `@wordpress/url`. `target="_blank"` opens the edit form in a new browser tab; `rel="noopener noreferrer"` isolates the new tab from the source page (WCAG-safe and matches wp-admin's `_blank` conventions).
- **FR-004**: `baseEditUrl` MUST come from `window.acrossaiAbilitiesManagerMcpExtension.editBaseUrl`, injected via `wp_add_inline_script(... 'before')` in this plugin's `admin/Main::enqueue_scripts()`. The PHP side MUST use `admin_url( 'admin.php?page=acrossai-abilities-manager' )` (not a hardcoded string) so subdirectory installs and non-standard `wp-admin` paths resolve correctly.
- **FR-005**: The PHP enqueue MUST be gated by three query-arg checks in `is_mcp_manager_abilities_tab()`: `$_GET['page'] === 'acrossai_mcp_manager'`, `$_GET['action'] === 'edit'`, `$_GET['tab'] === 'abilities'`. All three MUST use `sanitize_key( wp_unslash( ... ) )` and be nonce-check-exempt with `phpcs:ignore WordPress.Security.NonceVerification.Recommended` (mirrors the MCP Manager's own guard convention).
- **FR-006**: The registered script handle MUST be `acrossai-abilities-manager-mcp-extension` and its dependency array MUST include `acrossai-mcp-manager-abilities` in addition to the deps auto-declared by `@wordpress/scripts` in the emitted `.asset.php` file. Rationale: guarantees load order AND makes the extension a silent no-op when the MCP Manager plugin is not active.
- **FR-007**: The extension MUST NOT modify, redefine, or attempt to remove any built-in field id (`slug`, `label`, `type`, `category`, `description`, `is_exposed`). Filter callback MUST spread the input array first (`[ ...fields, actionField ]`) — never re-emit filtered copies.
- **FR-008**: The extension bundle MUST be built via a new webpack entry `js/mcp-abilities-extension` sourcing `src/js/mcp-abilities-extension/index.js`. No CSS entry is required — button styling comes from `wp-components`.
- **FR-009**: No changes to the `acrossai-mcp-manager` plugin are permitted. No PHP hook, REST endpoint, database schema, or option is added or modified on either plugin.
- **FR-010**: The extension MUST NOT rely on any private/internal API of the MCP Manager. Only the public JS-filter surface documented in `acrossai-mcp-manager/docs/abilities-tab-js-filters.md` may be consumed.

- **FR-011**: The Ability Edit form (`AbilityForm.jsx`) MUST render a red-labeled warning callout inside Section 3 (MCP Exposure) — positioned between the `.sect-hdr` block and the first form field (`Show in MCP`). Copy: `Heads up: Enabling MCP here applies this ability to all MCP servers on this site.` The word "Heads up:" MUST be bolded. The callout MUST render for both custom (db-source) and inherited (plugin/theme/core-source) abilities; the site-wide side-effect applies universally.

- **FR-012**: The warning callout MUST use a new CSS class `sect-note-warn` defined in `src/scss/abilities/admin.scss`, styled with a red-tinted background (`#fceaea`), a 3px red left border (`$red = #d63638`), red body text, `role="note"` for assistive tech, and left padding aligned with the existing `.sect-desc` (32px). It MUST NOT use `@wordpress/components` `Notice` — that component is dismissible-by-default and page-scoped, which is the wrong semantic for a persistent per-section advisory.

### Key Entities

- **Filter contract** (external, MCP Manager plugin owns) — `acrossaiMcpManager.abilities.fields` filter, applied at `acrossai-mcp-manager/src/js/abilities.js:584-588`. Additive-only merge reducer at `abilities.js:591`.
- **Enqueue handle to depend on** — `acrossai-mcp-manager-abilities`, registered at `acrossai-mcp-manager/admin/Main.php:229-236`.
- **Row `item` shape** — `{ slug, label, type, category, description, is_exposed, has_override }`, assembled at `acrossai-mcp-manager/src/js/abilities.js:359-368`. Only `item.slug` is consumed by this feature.
- **`window.acrossaiAbilitiesManagerMcpExtension`** (new, owned by this feature) — a small JSON object localized before the bundle: `{ editBaseUrl: string }`. Not extended in this feature; the shape is minimal so future keys can be added without breaking existing consumers.
- **PHP guard** — new private method `is_mcp_manager_abilities_tab()` on `\AcrossAI_Abilities_Manager\Admin\Main`, alongside the existing `is_manager_page()` / `is_library_page()` guards.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a fresh install with both plugins active and one MCP server exposing at least one ability, opening `admin.php?page=acrossai_mcp_manager&action=edit&server=<id>&tab=abilities` shows a new right-most **Action** column with an **Edit** button in every row.
- **SC-002**: Clicking Edit on the row for `acrossai-core-abilities/block-pattern-list` navigates to `admin.php?page=acrossai-abilities-manager&action=edit&slug=acrossai-core-abilities%2Fblock-pattern-list` and the Abilities Manager edit form loads that ability (Feature 043 code path).
- **SC-003**: cmd/ctrl-click on an Edit button opens the same URL in a new tab and the edit form loads without a list-flash.
- **SC-004**: With `acrossai-mcp-manager` deactivated, the bundle does not enqueue on any wp-admin screen (verified via the browser network tab and `wp_scripts()->registered['acrossai-abilities-manager-mcp-extension']` inspection). No console errors on any admin page.
- **SC-005**: With this plugin deactivated (MCP Manager active), the Abilities tab renders its built-in columns unchanged; no Action column; no console errors.
- **SC-006**: `build/js/mcp-abilities-extension.asset.php` declares at minimum `wp-hooks`, `wp-components`, `wp-element`, `wp-i18n`, `wp-url` in its `dependencies` array (auto-injected by `@wordpress/scripts`' `DependencyExtractionWebpackPlugin`).
- **SC-007**: `npm run build` succeeds with zero errors. Bundle size for the new entry stays under 5 KB minified (small filter registration + one button render — nothing heavy).
- **SC-008**: PHP test suite still passes with zero delta in test count. No PHPUnit test is added or modified.

- **SC-009**: Clicking the Action-column **Edit** button opens the destination URL in a new browser tab. The MCP Manager tab remains focused/loaded (verified by inspecting `document.hidden` on the source tab post-click).

- **SC-010**: On any ability's edit form (custom or inherited), the "MCP Exposure" section renders a red-tinted callout with the copy `Heads up: Enabling MCP here applies this ability to all MCP servers on this site.` positioned between the section description and the "Show in MCP" tri-chip. Verified visually against the wireframe reference and by DOM inspection (`document.querySelector('.sect-note-warn').textContent` matches).

## Assumptions

- **The MCP Manager plugin will remain active** on any site where this feature has business value. There is no cross-plugin dependency in `composer.json` — this is a soft optional dependency handled by the enqueue guard.
- **`acrossaiMcpManager.abilities.fields` is API-stable enough** to consume. The MCP Manager docs mark it `@since 0.1.0 @experimental`, so pre-1.0 breakage is accepted risk. When the MCP Manager tags 1.0.0, the filter becomes semver-stable per its own contract.
- **The `<Button href>` approach is preferred over `onClick` + `window.location`**. Rationale: right-click / cmd-click / middle-click all work out of the box, matching wp-admin's link conventions. Feature 043's mount-time URL parse guarantees that opening in a new tab lands on the edit form directly.
- **No custom CSS is warranted**. `wp-components` `<Button>` inherits DataViews-table styling; adding a stylesheet for a single button would violate DRY.
- **No changes to the existing `AbilitiesList.jsx` Edit button**. Feature 043 kept it as a `<button>` for accessibility reasons; that decision is preserved and orthogonal to this feature (which adds a link-shaped button in a different plugin's table).
- **`serverId` is intentionally NOT threaded into the deep-link URL**. The Abilities Manager edit form is per-ability, not per-server-per-ability. If a future feature requires opening the form scoped to a server, that URL param can be added alongside `slug` in the same encoding.
- **No new memory pattern warranted at n=1**. If a second sibling plugin later registers on the MCP Manager's filter surface (or if we add a second column to this same surface), the `PATTERN-CROSS-PLUGIN-JS-FILTER-EXTENSION` could be captured then.

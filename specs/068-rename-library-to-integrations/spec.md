# Feature Specification: Rename "Ability Library" admin page to "Ability Integrations"

**Feature Branch**: `079-rename-library-to-integrations`
**Created**: 2026-08-14
**Status**: Implemented (PR #129 open)
**Input**: User description: "change the name from library to integrations also the slug https://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-abilities-library&tab=elementor"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin sees "Integrations" in the sidebar and URL (Priority: P1)

A site administrator opens WP admin, expects to manage AcrossAI integrations, and sees a submenu item labeled "Integrations" under the AcrossAI parent menu. Clicking it opens a page titled "Ability Integrations" at a URL matching that name. The old label ("Library") no longer appears anywhere in the visible chrome.

**Why this priority**: This is the entire feature. The rename is user-facing terminology; every downstream story (deep links, tab-preservation, external doc updates) depends on the visible page + URL being renamed.

**Independent Test**: Load `/wp-admin` on a site with the plugin active. Verify the sidebar item under "AcrossAI Abilities Manager" reads "Integrations", clicking it opens `?page=acrossai-abilities-integrations`, and the h1 on that page reads "Ability Integrations".

**Acceptance Scenarios**:

1. **Given** the plugin is active, **When** an admin opens WP admin, **Then** the AcrossAI submenu shows an "Integrations" item (not "Library").
2. **Given** the admin clicks the Integrations submenu, **When** the page loads, **Then** the URL becomes `?page=acrossai-abilities-integrations` and the browser tab title / h1 both read "Ability Integrations".
3. **Given** the admin views the page HTML, **When** they inspect the top-level heading, **Then** they find "Ability Integrations", not "Ability Library".

---

### User Story 2 - Tab deep-links still work under the new slug (Priority: P1)

A caller (an admin or an external doc) opens a URL like `?page=acrossai-abilities-integrations&tab=elementor`. The Integrations page opens with the Elementor tab pre-selected. Browser back/forward preserves tab state. Sharing the URL with another admin reproduces the same tab.

**Why this priority**: Feature is unusable if deep-links break — every tab is bookmarkable / shareable, and the tab-sync hook is the mechanism that makes it so.

**Independent Test**: Navigate to `?page=acrossai-abilities-integrations&tab=elementor` directly. Verify the Elementor tab is active on first paint (no flicker to "All"). Click a different tab; verify the URL updates. Press browser Back; verify the previous tab re-selects.

**Acceptance Scenarios**:

1. **Given** the URL `?page=acrossai-abilities-integrations&tab=elementor`, **When** the page loads, **Then** the Elementor tab is the active tab.
2. **Given** an active tab, **When** the admin clicks another tab, **Then** the URL updates to reflect the new tab without a full page reload.
3. **Given** the admin has navigated between tabs, **When** they press browser Back, **Then** the previous tab is re-selected.

---

### User Story 3 - Old bookmarks produce a clear 404, not a silent misdirect (Priority: P2)

An admin who saved a bookmark to `?page=acrossai-abilities-library` under the old slug clicks it after the rename. WordPress serves its standard "Sorry, you are not allowed to access this page" screen. They do not get an unrelated page silently rendered. The changelog documents the breaking change so admins know to re-bookmark.

**Why this priority**: Non-blocking (admins can just re-navigate via the sidebar), but silent misdirects would erode trust. Explicit failure is better than surprise.

**Independent Test**: Visit `?page=acrossai-abilities-library` after PR #129 lands. Verify WP renders a "not found" error, not any unrelated admin page.

**Acceptance Scenarios**:

1. **Given** the rename is deployed, **When** an admin visits the old URL, **Then** WP core returns its standard "You do not have sufficient permissions" / "Cannot load this page" screen.
2. **Given** the changelog is published, **When** an admin reads the release notes, **Then** they see an explicit note that the URL slug changed and bookmarks must be updated.

### Edge Cases

- **Elementor tab discovery under new slug**: PR #128 flipped Elementor abilities to their own `tab_group => 'elementor'`. The new "Elementor" tab must reachable under both `?page=acrossai-abilities-integrations` (with tab strip) and `?page=acrossai-abilities-integrations&tab=elementor` (deep link).
- **REST endpoint namespace**: `/wp-json/acrossai-abilities-library/v1/` is intentionally NOT renamed — external MCP callers may hard-depend on it. Renaming the admin page slug must not touch the REST namespace.
- **DOM mount id**: The `<div id="acrossai-library-root">` mount point is paired between PHP (LibraryMenu.php) and JS (index.js). Renaming only one side breaks React mounting. Not renamed in this feature.
- **PHP class names, hooks, filters, directory paths**: All internal `Library` naming (LibraryMenu class, `is_library_page()` method, `includes/Modules/Library/`, `src/js/ability-library/`) is untouched. This is an explicit deferral — a broader rename is a separate feature if ever desired.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The WordPress admin submenu label under "AcrossAI Abilities Manager" MUST read "Integrations" (not "Library").
- **FR-002**: The admin page title (browser tab title) MUST read "Ability Integrations" (not "Ability Library").
- **FR-003**: The admin page URL slug MUST be `acrossai-abilities-integrations` (not `acrossai-abilities-library`), such that the full URL is `wp-admin/admin.php?page=acrossai-abilities-integrations`.
- **FR-004**: The visible h1 heading on the page MUST read "Ability Integrations" (not "Ability Library").
- **FR-005**: The tab-sync mechanism (`useLibraryTabSync`) MUST continue to preserve the active tab in `?tab=<slug>` under the new page slug — mount-time deep-link parsing, activeTab → URL mirroring, and popstate handling all still work.
- **FR-006**: The REST endpoint namespace (`/wp-json/acrossai-abilities-library/v1/`) MUST remain unchanged.
- **FR-007**: The DOM mount id (`acrossai-library-root`), PHP class names, method names, filter/action hook names, and directory paths MUST remain unchanged.
- **FR-008**: The changelog MUST explicitly document that admins with saved bookmarks to the old slug will get a 404 and must re-bookmark.
- **FR-009**: The compiled JS bundle (`build/js/ability-library.js`) MUST be rebuilt so the h1 label change reaches end users; the pre-existing bundle asset filename need NOT change.

### Key Entities *(include if feature involves data)*

- **Admin page slug**: WordPress `add_submenu_page()` slug — a string identifier used as the `?page=` query arg and to derive the WP hook suffix used by `is_library_page()` to gate script enqueuing.
- **Menu label**: Human-readable text shown in the WP admin sidebar. i18n'd via `__()`.
- **Page title**: The browser tab title, first argument to `add_submenu_page()`. i18n'd via `__()`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of visible occurrences of "Ability Library" and "Library" (as a menu label) on the admin page are replaced with "Ability Integrations" / "Integrations". A `grep -r "Ability Library" src/ admin/` returns zero matches after the change.
- **SC-002**: The URL `wp-admin/admin.php?page=acrossai-abilities-integrations` loads the admin page successfully; the URL `?page=acrossai-abilities-library` does not.
- **SC-003**: The full PHPUnit suite (1006 tests, 2040 assertions) continues to pass unchanged — the rename is data / string / slug only, no logic branches change.
- **SC-004**: `npm run build` completes cleanly and the compiled `build/js/ability-library.js` contains "Ability Integrations" and zero occurrences of "Ability Library".
- **SC-005**: The REST namespace `/wp-json/acrossai-abilities-library/v1/` continues to respond to authenticated requests — no external MCP caller is broken by this feature.

## Assumptions

- **Surface-only scope was explicitly chosen** — the user selected "Surface only" over "Full rename" when offered both options. Internal class / hook / directory renames are deferred to a separate future feature (or never, if the deprecation cost outweighs the naming purity).
- **WordPress does NOT auto-redirect old admin page slugs** — this is standard WP behavior. Consuming the resulting 404 is acceptable UX for a first release; adding a redirect from old→new slug is not in scope.
- **The plugin ships pre-built JS in `build/js/`** — the compiled bundle IS committed to the repo, so any user-visible JS string change requires a `npm run build` and a bundle commit as part of the same PR.
- **No external code depends on the admin page slug** — internal AcrossAI documentation / dev docs will need one-off updates; that is out of scope for this PR and handled as follow-up.

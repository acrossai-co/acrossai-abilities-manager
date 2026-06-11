# Feature Specification: Library Page Fix + AddonsPage Package Rebrand

**Feature Branch**: `030-library-page-fix-and-addons-page-rebrand`
**Created**: 2026-06-11
**Status**: Draft
**Input**: User description: "Two fixes: A) Blank Library admin page: move wp_localize_script out of LibraryMenu::render() and replace with wp_add_inline_script inside admin/Main::enqueue_scripts() — matching the existing pattern on the Abilities Manager page. B) Rename Composer dependency from wpboilerplate/addons-page to acrossai-co/addons-page. Update composer.json repository entry and require key. Update PHP namespace reference in includes/Main.php (class_exists guard and new instantiation). Confirm new namespace from new package's composer.json before implementing."

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Library page shows content on first load (Priority: P1)

An administrator opens the Abilities Manager plugin menu, clicks "Library", and immediately
sees either the list of ability groups provided by installed add-ons, or a clear message
explaining that no add-ons have registered abilities yet. The page never appears blank or
empty without explanation.

**Why this priority**: The Library page is the primary discovery surface for add-on abilities.
A blank page gives administrators no signal about whether the page is broken, loading, or
simply empty — eroding trust in the plugin.

**Independent Test**: Navigate to the Ability Library admin page on a WordPress site with no
add-ons installed. The page must show the "No abilities registered yet" empty-state message.
Then activate an add-on that registers abilities and reload — the ability group cards must
appear.

**Acceptance Scenarios**:

1. **Given** no ability add-ons are installed, **When** an admin navigates to the Library page, **Then** the page displays a readable empty-state message explaining that abilities appear here when add-ons register them.
2. **Given** at least one add-on has registered ability definitions, **When** an admin loads the Library page, **Then** one card per registered ability group is visible on the first page load without any refresh required.
3. **Given** the Library page has loaded, **When** the admin inspects the page, **Then** the page title "Ability Library" is visible and no browser console errors are produced by the plugin's own scripts.

---

### User Story 2 — Add-ons page continues to work after package rename (Priority: P1)

An administrator opens the "Add-ons" submenu under the Abilities Manager. The page renders
correctly — showing installed and available add-ons, Freemius pricing, and opt-in controls —
identically to how it functioned before the package was renamed.

**Why this priority**: The Add-ons page is the commercial entry point for the plugin. A
broken page prevents admins from discovering or purchasing add-ons, directly impacting
revenue.

**Independent Test**: After the Composer dependency is updated to `acrossai-co/addons-page`,
navigate to the Add-ons submenu. The page must render with no PHP warnings, no white screen,
and all Add-ons page functionality intact.

**Acceptance Scenarios**:

1. **Given** the updated package is installed, **When** an admin visits the Add-ons submenu, **Then** the page renders correctly with no visible errors or blank sections.
2. **Given** the updated package is absent from the vendor directory, **When** WordPress initialises the plugin, **Then** the plugin loads without a fatal error and the Add-ons submenu is simply not registered.
3. **Given** the package is installed and the admin has previously used the Add-ons page, **When** they visit it after the update, **Then** their existing Freemius opt-in state and any previously installed add-ons are unchanged.

---

### Edge Cases

- Library page loaded before any add-on fires the collection filter: must show the empty-state message, never a PHP error.
- The `acrossai-co/addons-page` package namespace differs from the old `wpboilerplate/addons-page`: the plugin must reference the correct new namespace; the old class reference must be completely removed.
- The new package is not yet in the vendor directory when the admin visits the site: the `class_exists()` guard must prevent a fatal error.
- Multiple add-ons register abilities with the same `main_key`: the Library page groups them under a single card (existing behaviour; must not regress).

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Ability Library admin page MUST display either ability group cards or a non-blank empty-state message on every first page load, with no blank output at any point.
- **FR-002**: The page data required by the Library interface (registered ability definitions, REST endpoint base URL, and authentication nonce) MUST be injected into the page before the browser interface initialises, so no secondary request or page refresh is needed to display content.
- **FR-003**: The plugin MUST continue to display the Add-ons submenu page and all its functionality after the Composer dependency is updated from `wpboilerplate/addons-page` to `acrossai-co/addons-page`.
- **FR-004**: The Add-ons page behaviour MUST be identical before and after the package rename — no change to features, displayed content, or the Freemius integration flow.
- **FR-005**: If the Add-ons package is absent from the vendor directory, the plugin MUST skip Add-ons submenu registration without producing a PHP fatal error or warning.
- **FR-006**: The new package namespace reference in plugin code MUST exactly match the namespace declared in the new package's own `composer.json` — the correct namespace must be confirmed from the installed package before writing the code.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Navigating to the Ability Library admin page produces visible content (cards or empty-state message) on the first load in 100% of tested scenarios — zero blank page occurrences.
- **SC-002**: The Add-ons submenu page renders without errors after the package update, with all existing Freemius features intact.
- **SC-003**: Static analysis (PHPCS and PHPStan level 8) reports zero new errors in all modified files after both changes.
- **SC-004**: No PHP fatal errors or warnings related to the AddonsPage class appear in the error log on any WordPress page load after the package update.

---

## Assumptions

- The new `acrossai-co/addons-page` package is a functional equivalent of `wpboilerplate/addons-page` v0.0.17 — same constructor signature, same hook behaviour.
- The new package is available via a VCS source (GitHub) or via Packagist under the `acrossai-co` vendor name; the exact URL is resolved during implementation.
- Freemius opt-in state and any installed add-on data are stored by Freemius itself, not by the `addons-page` package, so they survive the package swap without migration.
- The Library empty-state message ("No abilities registered yet") is intentional and acceptable behaviour when no add-ons have registered definitions — it does not require a separate "loading" indicator.
- No database schema changes are required for either fix.
- No new admin pages, REST namespaces, or JavaScript bundles are introduced.

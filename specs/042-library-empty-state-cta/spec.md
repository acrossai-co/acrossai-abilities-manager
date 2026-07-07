# Feature Specification: Library empty-state refresh with Add-ons CTA

**Feature Branch**: `042-library-empty-state-cta`
**Created**: 2026-07-07
**Status**: Implemented
**Input**: User description: "if `admin.php?page=acrossai-abilities-library` is empty then we are showing a message that 'No abilities registered yet. Activate an add-on that provides abilities.' in place of that show a proper message with styling n all please go to `admin.php?page=acrossai-addons` and download 'AcrossAI Core Abilities' and many more to see the list of library"

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Site owner lands on an empty Library page and is guided to the Add-ons catalogue (Priority: P1)

A site owner has just installed and activated `acrossai-abilities-manager` without any AcrossAI add-on plugins. When they open **AcrossAI → Library**, they see a purpose-built empty-state panel — an icon, a heading, an explanation that Library entries come from add-ons (with "AcrossAI Core Abilities" called out by name as the expected first install), and a primary button that takes them directly to the Add-ons page where they can install and activate the missing add-ons. The empty state matches wp-admin design tokens (card, border, dashed hint divider) so it looks intentional rather than accidental.

**Why this priority**: The pre-042 message ("No abilities registered yet. Activate an add-on that provides abilities.") was a single sentence with no styling, no icon, and no next-step action. A first-time user had no obvious signal that a specific add-on named "AcrossAI Core Abilities" is available on the Add-ons page, and no in-product path to reach it. This friction shows up on every fresh install.

**Independent Test**: Fresh WordPress install with only `acrossai-abilities-manager` active — no AcrossAI add-on installed. Visit `admin.php?page=acrossai-abilities-library`. Verify:
1. The empty-state panel renders as a card (not a bare paragraph).
2. The heading reads "No abilities registered yet".
3. The description explicitly mentions "AcrossAI Core Abilities" by name.
4. The primary "Browse add-ons" button links to `admin.php?page=acrossai-addons`.
5. Clicking the button lands on the Add-ons page.

**Acceptance Scenarios**:

1. **Given** zero abilities are registered (no add-on installed), **When** the Library page renders, **Then** the empty-state card is shown with icon, heading, description, primary CTA button, and hint line — not the pre-042 plain paragraph.
2. **Given** the empty-state card is rendered, **When** the "Browse add-ons" button is clicked, **Then** the browser navigates to `admin_url('admin.php?page=acrossai-addons')` (same origin, same admin).
3. **Given** at least one add-on providing abilities is active (definitions array non-empty), **When** the Library page renders, **Then** the empty-state card MUST NOT appear; the existing tab-panel / card grid renders as before.

---

### User Story 2 — Screen-reader user perceives the empty state as a region with a labelled heading (Priority: P2)

A screen-reader user opening the Library page on a fresh install expects the empty-state block to announce itself as a region with a labelled heading and a discoverable action button. The wrapper carries `role="region"` and `aria-labelledby` pointing at the heading `id`. Assistive tech reads the heading + description + button label in a coherent flow rather than as three loose text nodes.

**Why this priority**: The pre-042 `<p>` had no landmark and no heading — the screen-reader experience was a single anonymous sentence with no way to skip past or jump to it.

**Independent Test**: Load the Library page with VoiceOver / NVDA. Navigate by landmark → the empty state announces as a region. Navigate by heading → the "No abilities registered yet" heading is reachable. Navigate by button → the "Browse add-ons" button is reachable and reads its label.

---

### Edge Cases

- **`addonsUrl` missing from localized data**: If `window.acrossaiAbilityLibraryData.addonsUrl` is absent (e.g. an older build cached in the browser), the icon + heading + description + hint STILL render; only the primary button is skipped. No JS error is thrown. This is guarded by `data.addonsUrl && (…)` in the JSX.
- **Definitions array present but no items matched the current tab**: Not applicable to the empty-state code path — that scenario is handled by the existing TabPanel filter and does not touch `items.length === 0`.
- **RTL locale**: The empty state is centered (`text-align: center`) and the icon well is symmetric; no directional layout to flip. RTL styles compile through `wp-scripts build` (`ability-library-rtl.css`) automatically.
- **Long translations**: The description sits inside `max-width: 480px` with `line-height: 1.6` and wraps naturally; the CTA button label is short (~13 chars) and unlikely to break the button layout even in verbose locales.
- **Add-ons page slug moves**: Empty-state relies on `admin_url('admin.php?page=acrossai-addons')` server-side. If the shared `acrossai-co/main-menu` package renames the Add-ons submenu slug, both this URL and the vendor package must be updated in lockstep. Fallback: the button gracefully hides (see edge case above) — the description + hint still guide the user by add-on name.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: When `groupDefinitions(data.definitions || [])` returns an empty array, the Library page MUST render a structured empty-state block containing an icon well, heading, description paragraph, primary CTA button, and hint line — not the pre-042 plain `<p>`.
- **FR-002**: The empty-state description MUST reference "AcrossAI Core Abilities" by name as the canonical first-install add-on so users have a concrete name to look for on the Add-ons page.
- **FR-003**: The primary CTA button MUST link to the URL provided as `window.acrossaiAbilityLibraryData.addonsUrl`; that URL MUST be produced server-side via `admin_url('admin.php?page=acrossai-addons')` (matches the slug registered by the `acrossai-co/main-menu` vendor package).
- **FR-004**: When `data.addonsUrl` is falsy, the icon + heading + description + hint MUST still render; only the CTA button block is skipped. The rendered markup MUST NOT throw a JS runtime error.
- **FR-005**: The empty-state wrapper MUST carry `role="region"` and `aria-labelledby` referencing the heading `id` for landmark navigation.
- **FR-006**: The empty-state block MUST use the existing SCSS token palette (`$border`, `$border-dk`, `$txt`, `$muted`, `$bg`) so it inherits the same visual language as the LibraryCard components. No new colour tokens are introduced.
- **FR-007**: When at least one ability is registered, the empty-state block MUST NOT render (`items.length === 0` remains the sole render guard).
- **FR-008**: The wp-admin admin/Main enqueue payload MUST include `addonsUrl` alongside `definitions`, `restBase`, and `nonce` in the `window.acrossaiAbilityLibraryData` blob. No other server-side change is required (no REST field, no DB, no hook, no capability).

### Key Entities *(include if feature involves data)*

- **`acrossaiAbilityLibraryData` localized payload** — the `wp_add_inline_script('acrossai-ability-library-js', 'window.acrossaiAbilityLibraryData = …', 'before')` object. Post-042 the shape gains one new key: `addonsUrl` (string, the `admin_url('admin.php?page=acrossai-addons')` output). Existing keys (`definitions`, `restBase`, `nonce`) are unchanged.
- **Empty-state block** — a React component subtree rendered when `items.length === 0`. Composed of five children (icon well, heading, description, CTA button block, hint line). Rendered inside the existing `<div className="acrossai-library-page">` wrapper.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a fresh WordPress install with only `acrossai-abilities-manager` active, visiting `admin.php?page=acrossai-abilities-library` renders a structured card-style empty state (not the pre-042 single-line paragraph). Verified in a browser walk-through (T009).
- **SC-002**: The empty-state description contains the substring "AcrossAI Core Abilities" (grep-verifiable at build/js/ability-library.js).
- **SC-003**: The primary CTA anchor's `href` equals `admin_url('admin.php?page=acrossai-addons')` at render time. Verified by opening DevTools → Elements → inspecting the anchor.
- **SC-004**: `npm run build` succeeds with zero errors. `build/css/ability-library.css` contains all six new class names: `acrossai-library-page__empty`, `…__empty-icon`, `…__empty-title`, `…__empty-description`, `…__empty-actions`, `…__empty-hint`.
- **SC-005**: Loading the Library page on the empty install produces zero PHP warnings, zero JS console errors, and no accessibility violations against the "landmarks must be labelled" / "buttons must have discernible names" axe-core rules.
- **SC-006**: When at least one ability is registered, the empty-state block is NOT present in the rendered DOM (regression guard).

## Assumptions

- **`acrossai-addons` slug is stable in this feature's timeframe.** The Add-ons submenu is registered by the `acrossai-co/main-menu` vendor package (see `vendor/acrossai-co/main-menu/src/Addons/MenuRegistrar.php::SUBMENU_SLUG`). Feature 026 established the slug; Feature 038 kept it. Changing it requires a coordinated vendor package update, at which point the localized `addonsUrl` and any hard-coded references migrate together.
- **"AcrossAI Core Abilities" is the canonical first-install add-on name.** The empty state calls it out by name so users have a concrete target to search for on the Add-ons catalogue. If the flagship add-on is ever renamed or replaced as the default entry point, the description string MUST be updated.
- **No REST or DB change.** Feature 042 is a display-only UX polish. No new endpoints, no new options, no new capabilities, no new nonces. `restBase`, `nonce` remain unchanged; `addonsUrl` is a plain admin URL that requires no permission check beyond WordPress's own `manage_options` gate on the Library page.
- **No i18n regression.** All new strings pass through `__()` with the existing `'acrossai-abilities-manager'` textdomain. Translators will pick them up on the next .pot regeneration; this is not part of Feature 042's scope.
- **No new memory pattern is required.** The change reuses established conventions: `wp_add_inline_script` for localization (existing `AC-ENQUEUE-ADMIN`), `@wordpress/icons` for iconography (existing `LibraryCard.js` precedent), and BEM-style SCSS block/element naming (existing `.acrossai-library-page__*` block). No new PATTERN or DEC entry is warranted.

# Feature Specification: Ability Library — Full Width and Descriptions

**Feature Branch**: `036-library-page-full-width-and-descriptions`
**Created**: 2026-06-18
**Status**: Draft
**Input**: User description: "Update the Ability Library admin page (wp-admin/admin.php?page=acrossai-abilities-library) so that (1) each ability row shows the ability's description directly under its label in both the Specific and All panels, in a smaller muted style; (2) the page uses the full width of the WordPress admin content area instead of being constrained to 900px; (3) abilities without a description render unchanged — no empty wrapper, no placeholder; (4) description renders as plain text (never as raw HTML)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Read Ability Descriptions In Context (Priority: P1)

A site administrator viewing the Ability Library wants to understand what each ability does *while choosing whether to enable it*, without leaving the page or guessing from a terse label. Today the page only shows a label/slug per row, so the admin has to recognize each ability from its short label alone. This story exposes the description that add-on authors already attach to every ability so the admin can read what the ability does in the same view where they toggle it on or off.

**Why this priority**: This is the primary user need behind the feature. Without it, the page remains a list of opaque labels and the admin's decision about which abilities to expose to AI clients is uninformed. The descriptions already exist in the data; the page simply does not show them.

**Independent Test**: Open the Ability Library page on a site with at least one add-on whose abilities declare descriptions. Each ability row should show the description text immediately under its label, visually distinct from the label (smaller, muted). Toggle a card to "Specific" — the descriptions still appear under each checkbox. Toggle it back to "All" — descriptions still appear under each read-only row.

**Acceptance Scenarios**:

1. **Given** an active add-on registers an ability with a non-empty description, **When** the administrator opens the Ability Library page and the ability's card is enabled in "All" mode, **Then** the ability row shows its label followed by the description text underneath, visually distinct (smaller, muted) from the label.
2. **Given** the same ability, **When** the administrator switches the card to "Specific" mode, **Then** the checkbox row shows the ability label and the description appears directly under that label, indented to align under the label (not under the checkbox).
3. **Given** an ability whose description is missing, empty, or whitespace-only, **When** the row renders in either mode, **Then** the row displays exactly as it does today — the label/slug line only, with no empty space, separator, or placeholder text where the description would be.
4. **Given** a description that contains characters that look like HTML (e.g. `<`, `>`, `&`), **When** the row renders, **Then** the page shows the literal characters as text — no browser-side HTML interpretation, no markup, no rendered tags.

---

### User Story 2 — Use the Full Admin Width on Wide Displays (Priority: P2)

A site administrator on a typical desktop monitor (≥1280px wide) wants the Ability Library content to use the available WordPress admin column instead of being cropped to a narrow 900-pixel column with empty space on the right. With descriptions now appearing on each row (Story 1), the narrower column would force descriptions to wrap awkwardly; widening the page makes both labels and descriptions readable without scrolling or unnecessary wrapping.

**Why this priority**: Story 1 is the headline change; Story 2 makes Story 1 readable on wide displays. On its own, full-width is useful but secondary — the page is functional at 900px today.

**Independent Test**: Open the Ability Library page on a viewport ≥1280px wide. The card area should extend to the available WordPress admin content width (the same width WordPress core uses for its own admin pages), not stop at a fixed inner column.

**Acceptance Scenarios**:

1. **Given** the administrator opens the page on a 1920px-wide viewport, **When** the page renders, **Then** the cards extend to use the available WordPress admin content area, with no artificial inner column limit narrower than what WordPress's own admin pages use.
2. **Given** the administrator opens the page on a narrower viewport (around 1024px), **When** the page renders, **Then** cards still display without horizontal scrolling on the page-level content area.
3. **Given** a card with many abilities and long descriptions, **When** the card is expanded in either mode, **Then** descriptions and labels flow within the card without forcing the page to scroll horizontally.

---

### Edge Cases

- **No description present** — Row renders exactly as today; no empty paragraph, no whitespace gap, no placeholder text.
- **Whitespace-only description** — Treated as no description; row renders exactly as today.
- **HTML-like characters in description** — Rendered as literal text; never interpreted as markup. (See US1, AC4.)
- **Very long description (multi-sentence)** — Wraps within the row's available width; does not break card layout, does not force horizontal scroll.
- **No registered abilities** — The existing empty-state message ("No abilities registered yet…") still appears unchanged. Full-width behavior applies whether or not abilities are present.
- **Mode switch with active descriptions** — Switching a card between "All" and "Specific" must continue to render descriptions in the new mode without flicker, duplicate rows, or stale rows.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Ability Library page MUST display each ability's description directly under its label whenever the ability declares a non-empty description.
- **FR-002**: Descriptions MUST appear in both the read-only "All" mode rows and the interactive "Specific" mode checkbox rows.
- **FR-003**: The description MUST be visually subordinate to the label (smaller text size, muted color) so the label remains the primary identifier of the row.
- **FR-004**: In "Specific" mode, the description MUST be indented to align under the ability label (not under the checkbox control), so the label and description read as a single grouped item.
- **FR-005**: When an ability's description is missing, empty, or contains only whitespace, the row MUST render with the label only — with no empty wrapper, separator, placeholder text, or extra vertical spacing where the description would have appeared.
- **FR-006**: Description content MUST be rendered as plain text. Any HTML-like characters in the description value MUST appear as literal characters in the page; the browser MUST NOT interpret them as markup.
- **FR-007**: The Library page content MUST use the available width of the WordPress admin content area. The page MUST NOT cap its inner column to a fixed pixel width that is narrower than the surrounding WordPress admin column.
- **FR-008**: The change to the page width MUST be scoped to the Library page. The Library page MUST NOT widen or otherwise restyle the shared WordPress admin chrome.
- **FR-009**: Existing card behavior MUST be preserved: per-card enable/disable toggle, "All / Specific" mode radio, per-card expand/collapse chevron, sub-group headings inside the "Specific" panel, and saving on every change.
- **FR-010**: Saved configuration data MUST NOT change shape or schema. The description is a display-only field surfaced from the existing ability definition; it MUST NOT be stored in saved Library configuration.
- **FR-011**: Abilities registered after this change that omit a description MUST continue to display correctly with no description block, requiring no migration or fallback configuration.

### Key Entities *(include if feature involves data)*

- **Ability Definition** — Each ability registered by an add-on exposes a human-readable label, a slug, an optional sub-group, and an optional description string. This feature adds the description to what the Library page displays; it does not change how definitions are produced, validated, or stored.
- **Library Card** — Visual grouping of abilities by category. This feature does not change the card model or its persisted configuration (enable state, mode, per-slug toggles); it changes what each row inside a card visually renders.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of abilities whose registered description is non-empty display that description text under their label on the Library page, in both "All" and "Specific" modes.
- **SC-002**: 100% of abilities whose registered description is empty, missing, or whitespace-only display the same single-line row as before this feature, with no extra vertical space and no placeholder text.
- **SC-003**: 0 abilities render description content as parsed HTML. Manual inspection on a row whose description contains `<`, `>`, or `&` characters shows those characters as literal text.
- **SC-004**: On a viewport ≥1280px wide, the Library page content area uses at least 95% of the WordPress admin content column width (i.e. it is no longer visibly capped at ~900px).
- **SC-005**: On a viewport of 1024px wide, the Library page renders without horizontal scrolling on the admin content area.
- **SC-006**: All existing per-card behaviors (toggle, mode switch, expand/collapse, sub-group headings, save) continue to work after the change. A site administrator can complete a full configuration change (enable card, switch to Specific, check specific abilities, reload page, see saved state) without observing any new errors or visual regressions.
- **SC-007**: The Library configuration REST payload sent on save retains its existing shape (no new keys, no removed keys, no renamed keys). A round-trip save and reload preserves selections identically to behavior before this feature.

## Assumptions

- Add-on authors who want descriptions to appear will fill the description field on the abilities they register. Add-ons that do not provide descriptions remain valid; their rows simply render label-only.
- Description values originate from add-on code that the site owner has already chosen to install. Even though description content is not separately HTML-sanitized, treating it as plain text in the browser is sufficient defense against accidental or hostile markup; no additional server-side sanitization is required for this feature.
- The WordPress admin layout reliably provides a sensible maximum content width for admin pages; removing the Library page's inner cap simply lets the page inherit that width.
- This feature does not require a database migration, a saved-config migration, or a REST contract change. The description is read from the same live ability definition data that already drives the page.
- "Full width" means the WordPress admin content area's width, not the browser viewport. The WordPress admin sidebar (`#adminmenuwrap`) remains in place and unaffected.
- Mobile and very narrow viewports (<1024px) continue to be handled by WordPress's existing responsive admin styles; this feature does not introduce new media queries.

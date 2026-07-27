# Feature Specification: Library Third-Party Integration Toggles

**Feature Branch**: `060-library-third-party-integration-toggles`
**Created**: 2026-07-26
**Status**: Draft
**Input**: User description: "Add a 'third-party integration' card pattern to the Ability Library page so site admins can toggle plugins whose WP Abilities API abilities are gated behind a plugin-specific filter. First integration: Advanced Custom Fields (ACF), which requires enabling ACF's own AI setting before it registers its FieldGroup / PostType / Taxonomy abilities. Base class + one concrete ACF subclass; toggle state reuses the existing library configuration store; card UI is toggle-only with a fixed readonly ability list; integrations default OFF; every visible surface is gated on the target plugin being installed and active."

## Clarifications

### Session 2026-07-26

- Q: What WordPress capability must an admin hold to flip a third-party integration toggle? → A: Filterable, defaults to `manage_options` — exposed via `apply_filters( 'acrossai_integration_toggle_capability', 'manage_options', $integration_slug )` so sites and specific integrations can require stricter permissions.

### Session 2026-07-27

- Q: Should the integration tab be extensible so that a separate third-party plugin can add its own custom ability cards next to the integration's toggle card? → A: **Yes**, via the existing tab_group mechanism. Any third-party plugin can register additional cards on an integration's tab by (1) registering its ability category on `wp_abilities_api_categories_init` — WP core rejects abilities whose category is not pre-registered, (2) extending `Ability_Definition`, and (3) setting `meta.acrossai.tab_group` to the integration's published `TAB_GROUP` constant (e.g. `ACF::TAB_GROUP`). No new plumbing in this plugin — the pattern is enforced by requirement and documented on the base class + a worked demo mu-plugin in the quickstart. New User Story 5 + FR-017 + FR-018 capture the requirement.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Enable a third-party plugin's AI abilities from one place (Priority: P1)

A site administrator wants their AI/MCP clients to be able to use Advanced Custom Fields (ACF)
ability calls (create field groups, create post types, create taxonomies). Today those abilities
only register if the admin hand-edits a `functions.php` snippet or knows the exact filter to
enable inside ACF. As an admin, they open the AcrossAI Ability Library page, see a dedicated
"Acf" tab, flip a single toggle on, and immediately ACF's AI abilities become available to their
AI clients — no code editing, no support tickets, no navigating to ACF's own settings screen.

**Why this priority**: This is the entire point of the feature. Without this user journey there
is no visible product change. Any other work only matters because it makes this journey work.

**Independent Test**: With ACF activated and integration toggle off, ACF abilities are absent
from the ability list. Flip the toggle on, reload, confirm ACF abilities are present. Flip the
toggle off, reload, confirm they are gone again. Verifiable end-to-end with just the Library page
and any ability-listing surface (WP-CLI, MCP client, or the "All" tab).

**Acceptance Scenarios**:

1. **Given** ACF is active and the ACF integration has never been toggled, **When** the admin
   opens the Ability Library page, **Then** they see an "Acf" tab containing one card labeled
   "Advanced Custom Fields (AI)" with a toggle that is **off** by default and a readonly list of
   the three ability groups ACF will expose (Field Groups, Post Types, Taxonomies) with a short
   description under each.
2. **Given** the ACF integration card is off, **When** the admin flips the toggle on, **Then**
   the change auto-saves without requiring a separate "Save" click, and reloading the page shows
   the toggle still on.
3. **Given** the admin has just enabled the ACF integration, **When** any downstream client
   requests the current ability list (e.g. via an MCP `discover-abilities` call, the WP-CLI
   ability list, or by looking at the "All" tab in the library), **Then** ACF's field-group,
   post-type, and taxonomy abilities are present.
4. **Given** the ACF integration is enabled and ACF's abilities are visible to clients, **When**
   the admin flips the toggle off and reloads, **Then** ACF's abilities are no longer visible to
   clients on the next request.

---

### User Story 2 — Add another third-party integration in the future without new plumbing (Priority: P2)

An extension developer or future project contributor wants to add support for another
filter-gated third-party plugin (e.g. WooCommerce AI, WPForms AI). They subclass the
integration base class in a single PHP file, declare four things (identifier, display label,
plugin-detection check, the filter to attach), list the abilities the plugin will expose, and
register the class once. A new dedicated tab appears on the Library page automatically with the
same toggle-only UI. No REST endpoint, no admin-page routing, no JavaScript changes are needed.

**Why this priority**: Justifies the abstract base class rather than a one-off ACF card. Without
this second journey the feature is over-engineered for a single integration.

**Independent Test**: Create a throwaway second subclass in a test plugin, activate it, confirm
a new tab appears with the same UI shape as the ACF tab and behaves identically for enable/disable.

**Acceptance Scenarios**:

1. **Given** a developer has authored a second integration subclass following the documented
   contract, **When** they instantiate it during plugin bootstrap, **Then** a new tab labeled by
   their integration's display label appears on the Library page with a toggle-only card
   listing the abilities they declared, without any changes to REST controllers, JavaScript, or
   admin-menu registration.

---

### User Story 3 — Deactivating the target plugin never breaks the admin page (Priority: P2)

A site admin has enabled the ACF integration, and later deactivates ACF entirely (perhaps
switching to a different custom-fields plugin). When they next open the AcrossAI Ability Library
page, they must see a clean page with no error notices, no orphan "Acf" tab pointing at nothing,
and no PHP warnings in the debug log. If they later re-activate ACF, the "Acf" tab must reappear
in the state they last left it.

**Why this priority**: Protects the site from a very common failure mode (plugin deactivation)
that would otherwise create fatal errors or a confusing admin UI. Directly reinforces the
promise that toggling an integration is safe.

**Independent Test**: Enable ACF and the ACF integration. Deactivate ACF. Load the Library page.
Confirm no "Acf" tab, no PHP notices about missing ACF classes. Re-activate ACF and confirm the
tab returns with the toggle still on.

**Acceptance Scenarios**:

1. **Given** the ACF integration is toggled on and ACF is subsequently deactivated, **When** the
   admin loads the Library page, **Then** the "Acf" tab and card are entirely absent, and no
   PHP notices or errors reference undefined ACF classes.
2. **Given** ACF has been re-activated after a period of being deactivated, **When** the admin
   loads the Library page, **Then** the "Acf" tab reappears with the toggle in whatever state the
   admin last saved it (previously on stays on; previously off stays off).

---

### User Story 4 — Integration is never on by accident (Priority: P3)

When a site first installs and activates AcrossAI Abilities Manager alongside ACF, the ACF AI
abilities must not silently become available to AI clients. Turning them on must always be an
explicit admin decision, because enabling AI-driven schema manipulation on a production site is
a decision the admin needs to own.

**Why this priority**: A safety property, not a discoverable interaction. Loud enough to justify
being called out but doesn't need its own UI element.

**Independent Test**: On a fresh install with ACF already active, verify that the ACF integration
card is present with the toggle **off** and that ACF's abilities are absent from every ability-
listing surface until the admin flips the toggle.

**Acceptance Scenarios**:

1. **Given** a fresh site with both plugins active but no admin has ever visited the Library
   page, **When** an AI client asks for the current ability list, **Then** ACF's AI abilities are
   not included.

---

### User Story 5 — A third-party plugin adds custom ability cards to an integration tab (Priority: P2)

A separate WordPress plugin — for example, an `acrossai-acf-extras` add-on — wants to register
its own AI abilities that are conceptually related to an integration (say, ACF) and have them
appear as regular library cards on the same tab as the integration's toggle card. This turns the
integration tab into an extensibility surface where the "default" integration abilities coexist
with any number of "custom" abilities contributed by other plugins, all managed from a single
tab.

**Why this priority**: Enables the ecosystem play. Without it, integrations are isolated cards
with no growth path; with it, any AcrossAI-related plugin can plug into an integration's tab
without changes to this plugin.

**Independent Test**: Install a small mu-plugin or add-on plugin that (a) registers its own
ability category on `wp_abilities_api_categories_init`, (b) extends `Ability_Definition`, and
(c) sets `meta.acrossai.tab_group` to the integration's published `TAB_GROUP` constant. Load the
integration's tab and confirm the add-on's card appears alongside the integration card, that it
looks and behaves like a regular library card (toggle, expand, All/Specific), and that the
add-on's abilities show up in the Custom Abilities table and in MCP `discover-abilities`.

**Acceptance Scenarios**:

1. **Given** a third-party plugin has (a) registered its category on
   `wp_abilities_api_categories_init` and (b) instantiated an `Ability_Definition` subclass
   whose ability declares `meta.acrossai.tab_group = ACF::TAB_GROUP`, **When** an admin loads
   the ACF tab of the Library page, **Then** the add-on's card renders as a regular library
   card (with toggle, expand, All/Specific radio, and per-ability checkboxes) alongside the ACF
   integration toggle card.
2. **Given** an add-on card is enabled, **When** a downstream client queries the ability list
   (Custom Abilities table, MCP `discover-abilities`, or WP-CLI), **Then** the add-on's
   abilities are present.
3. **Given** an add-on's ability subclass is instantiated but its category was **not**
   registered on `wp_abilities_api_categories_init`, **When** the admin loads the ACF tab,
   **Then** the add-on's card still renders (because the Library UI reads from the Registry)
   but the underlying abilities are silently rejected by WP core's
   `wp_register_ability()` and do NOT appear in the Custom Abilities table or MCP
   `discover-abilities`. This is a WP core rule, not a Feature 060 defect — the documentation
   and quickstart both call it out as required step 1 of the extension pattern.

---

### Edge Cases

- **Target plugin installed but deactivated.** The integration tab must not appear and no filter
  must be attached, regardless of the saved toggle state.
- **Target plugin uninstalled entirely.** Same behavior as deactivated. No references to
  third-party plugin classes may be evaluated.
- **Admin toggles the integration on, then deactivates the target plugin without toggling off
  first.** Saved config keeps the "on" state but no UI is shown and no filter is attached.
  Re-activating the target plugin restores the tab in the "on" state without any admin action.
- **Two integrations declared for the same third-party plugin.** Not supported in this feature;
  the second registration would collide on the shared tab identifier. Out of scope.
- **Third-party plugin registers its abilities through a hook that has already fired by the
  time the integration toggle runs.** The base class must attach the enabling filter early
  enough (before the third-party plugin's own registration hook) that the third-party sees the
  filter as truthy on the current request.
- **Multisite with ACF network-active on some sites but not others.** The plugin-detection
  check must reflect the *current site's* activation state, so an admin visiting the Library on
  a site where ACF is not active sees no ACF tab even if the network has it enabled elsewhere.
- **Downgrade / plugin removal path.** Removing the AcrossAI Abilities Manager plugin does not
  need to clean up integration entries in the shared library configuration store; those keys
  become inert without their subclass. No orphan-cleanup requirement in this feature.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a base pattern (abstract class) so that any third-party plugin
  whose AI abilities are gated behind a filter can be exposed as its own tab on the Ability
  Library page without changes to REST endpoints, admin-menu registration, or the ability-
  library JavaScript build.
- **FR-002**: The base pattern MUST require each concrete integration to declare exactly four
  things: (a) a stable identifier used as both the tab and card identifier, (b) a human-readable
  display label, (c) a plugin-detection check that returns whether the target plugin is
  installed and active on the current site, and (d) the filter attachment the base class should
  perform on the admin's behalf when the toggle is on.
- **FR-003**: The base pattern MUST additionally require each concrete integration to declare a
  fixed list of the abilities the target plugin will expose (identifier, label, and a short
  description per ability), used only for display inside the card.
- **FR-004**: Each integration MUST appear as its own tab on the Ability Library page. The tab
  MUST NOT appear when the target plugin is missing or deactivated on the current site,
  regardless of any saved toggle state.
- **FR-005**: Each integration MUST render as a single card with only two visible elements:
  a header enable/disable toggle, and a readonly list of the ability entries declared by the
  subclass. There MUST NOT be an "All/Specific" mode selector, and there MUST NOT be per-ability
  checkboxes for integration cards.
- **FR-006**: The toggle state for each integration MUST persist across page loads and admin
  sessions using the same site-wide storage the existing library uses for other category toggles.
  No new storage key, REST endpoint, or controller MUST be introduced by this feature.
- **FR-007**: An integration whose stored toggle is on and whose target plugin is currently
  active MUST cause the base class to attach the subclass-provided filter early enough in the
  request lifecycle that the target plugin's own registration hook picks up the filter as truthy
  on the same request.
- **FR-008**: An integration MUST default to **off** when the admin has never toggled it. A
  missing entry in the shared configuration store MUST be interpreted as "off" for integration
  categories, even though the same absent-entry state means "on" for regular (non-integration)
  library categories. This asymmetry MUST NOT leak into any general library code paths beyond
  a single documented helper.
- **FR-009**: Toggling an integration on or off MUST auto-save (no explicit "Save" button) and
  reloading the page MUST reflect the saved state without additional user action.
- **FR-010**: When an admin enables an integration and the target plugin subsequently exposes
  its abilities, those abilities MUST become visible on the "All" tab of the Library and to any
  downstream ability-listing consumer (WP-CLI, MCP discover-abilities) on the next request.
- **FR-011**: When an admin disables an integration whose target plugin previously exposed
  abilities, those abilities MUST disappear from all ability-listing surfaces on the next
  request.
- **FR-012**: Deactivating the target plugin after an integration was toggled on MUST NOT
  mutate the saved integration configuration. Re-activating the target plugin MUST restore the
  tab and card in whatever on/off state the admin last saved.
- **FR-013**: No PHP notice, warning, or fatal error MUST be triggered by loading the Ability
  Library page in any of these states: target plugin never installed, target plugin installed
  but deactivated, target plugin activated but integration toggle off, target plugin activated
  and integration toggle on.
- **FR-014**: The integration pattern MUST NOT modify the on-disk shape of the shared library
  configuration store, and MUST NOT change the behavior of the existing configuration
  sanitization routine that other library categories use.
- **FR-015**: The current tab-grouping mechanism used by the Ability Library page MUST continue
  to work for existing (non-integration) categories with no visible change. Adding, enabling, or
  disabling an integration MUST NOT affect any other tab, category card, or ability entry.
- **FR-016**: Toggling any third-party integration on or off MUST require the WordPress
  capability returned by `apply_filters( 'acrossai_integration_toggle_capability', 'manage_options', $integration_slug )`.
  The default value MUST be `manage_options` so integration toggling matches the capability
  currently required by the rest of the Ability Library page. Sites that want stricter control
  (per-site or per-integration) MUST be able to raise the requirement without a code change to
  this plugin, by attaching a filter that returns a stricter capability string. The permission
  check MUST be enforced on the same server-side write path that persists the toggle (not only
  in the JavaScript UI) so a stricter capability cannot be bypassed by a crafted REST request.
- **FR-017**: Each concrete integration MUST publish its `tab_group` identifier as a public
  `const TAB_GROUP` on the subclass so that third-party plugins can reference a stable
  identifier when routing their own ability cards onto the integration's tab (instead of
  hardcoding a string).
- **FR-018**: The plugin MUST support the following extension pattern so that a separate
  third-party plugin can add regular ability cards onto an integration's tab: (a) the
  third-party plugin registers its ability category on `wp_abilities_api_categories_init` via
  `wp_register_ability_category()`, (b) the third-party plugin instantiates an
  `Ability_Definition` subclass whose ability's `args.meta.acrossai.tab_group` equals the
  integration's published `TAB_GROUP` constant, and (c) the third-party plugin uses a distinct
  category slug from the integration's own slug so its card renders as a separate library card
  (with toggle, expand, All/Specific radio) rather than merging into the integration's
  read-only card. The pattern MUST work without any code changes to this plugin — no new
  filter, no new admin API, no new registration path. The extension pattern MUST be documented
  in the integration base class's docblock AND in a worked demo mu-plugin in the feature's
  quickstart doc.

### Key Entities *(include if feature involves data)*

- **Third-party integration**: A named pairing between one identifier (used as both tab and
  card key), one display label, one plugin-detection check, one filter attachment, and one
  fixed list of the abilities the third-party plugin will expose when enabled.
- **Integration toggle state**: A per-site enabled/disabled boolean per integration identifier,
  persisted in the same site-wide library configuration store used for other library categories.
  Missing entry means "off" for integration categories.
- **Ability entry (display-only)**: An identifier + label + short description tuple used only
  to render the readonly ability list inside an integration card. Never persisted; never used
  to execute an ability.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A site admin can turn on the ACF AI integration from the AcrossAI Ability Library
  page in a single click, without editing any code, editing any file, or navigating to any other
  admin screen.
- **SC-002**: 100% of the visible integration tabs, cards, and filter attachments on the
  Library page correspond to third-party plugins that are currently active on the current site.
  A deactivated third-party plugin never surfaces its integration tab.
- **SC-003**: A fresh install of AcrossAI Abilities Manager alongside ACF exposes 0 ACF AI
  abilities to any downstream consumer (WP-CLI, MCP client, REST) until the admin explicitly
  flips the ACF integration toggle to on.
- **SC-004**: Toggling an integration on or off takes effect on the very next page load or
  ability-list request — the admin never has to run a cache flush, ability-refresh command,
  or restart action to see the change.
- **SC-005**: Adding a second, third, or fourth integration in the future requires creating a
  new subclass file only; no changes to any admin page, REST controller, JavaScript bundle
  build, or admin-menu registration are required for the new tab to appear.
- **SC-006**: 0 PHP notices, warnings, or fatal errors are triggered by loading the Ability
  Library page across the four lifecycle states of the target plugin (never installed;
  installed but deactivated; active with toggle off; active with toggle on).

## Assumptions

- The existing AcrossAI Ability Library page is the correct home for third-party integration
  toggles. No separate settings screen or standalone admin page is warranted for this feature.
- The existing shared library configuration store is the appropriate persistence layer for
  integration toggles. Reusing it avoids a second REST endpoint and a second admin-facing option
  to reason about.
- ACF's own `enable_acf_ai` setting is the correct filter to attach when the admin enables the
  ACF integration, and this filter is stable enough across ACF versions that a static reference
  is appropriate.
- Integration cards do not need per-ability opt-in/opt-out. Once an integration is enabled, all
  of that plugin's AI abilities are exposed. Admins who need finer control can disable
  individual abilities via the existing per-category machinery *after* the third-party plugin
  registers them.
- Integration cards do not need to bundle their own visual asset (logo, brand color, external
  link) in this feature. A plain label + description list is sufficient for MVP.
- Uninstalling AcrossAI Abilities Manager does not need to clean up integration entries in the
  shared library configuration store. Those entries become inert without their subclass and can
  be removed by a future migration if needed.
- WordPress.org / freemium distribution constraints do not restrict the naming of a third-party
  integration tab (displaying "Advanced Custom Fields (AI)" as a tab label is acceptable).
- The plugin-detection semantics required for this feature are: "is the target plugin's PHP
  code currently loaded on the current site so that its stable public symbols are defined." The
  subclass is responsible for choosing the appropriate check for its target plugin.

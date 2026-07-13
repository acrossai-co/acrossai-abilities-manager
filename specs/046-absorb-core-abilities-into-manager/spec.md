# Feature Specification: Absorb Core Abilities Companion Into Manager

**Feature Branch**: `046-absorb-core-abilities-into-manager`  
**Created**: 2026-07-13  
**Status**: Draft  
**Input**: User description: "Absorb the companion plugin acrossai-core-abilities into acrossai-abilities-manager … create a new isolated tree includes/Abilities/ inside the manager that holds every runtime PHP file from the companion (17 category folders totalling 201 PHP files — 17 Category_Registrars + 176 ability classes + 8 helper classes (Formatters, Routes, Moderation), plus Utilities helpers, and the single Core Settings admin tab) … preserve category slugs and behaviour, unify text domain, wire everything through the manager's existing boot flow, no companion plugin required after activation."

## Clarifications

### Session 2026-07-13

- Q: When both the manager and the companion plugin (`acrossai-core-abilities`) are active at the same time, what UX should the manager provide? → A: Non-applicable — the companion plugin will not exist on target sites when the migrated manager is activated. The spec removes the co-active-plugin functional requirement, acceptance scenario, related edge cases, and the associated success criterion. This feature still does not modify or delete the companion plugin folder in this repository; removal on production sites is handled operationally.
- Q: After the migration, should visible category and ability label text still say "Acrossai Core Abilities — …" or be rebranded? → A: **Rebrand every occurrence of "Acrossai Core Abilities" to "Acrossai Abilities Manager"** throughout the absorbed code — including admin-visible category label text and descriptions, **category slugs** (e.g., `acrossai-core-abilities-plugins` → `acrossai-abilities-manager-plugins`, applied uniformly to all 17 categories), class names, function names, and any other internal identifiers that carried the "Core Abilities" wording. This intentionally breaks downstream callers that referenced the old category slugs; US2 is rewritten to reflect this migration cost.
- Q: Should the absorbed Core admin settings render as their own tab, and should the companion's option keys stay unchanged? → A: Merge the Core settings fields into the existing **Abilities** tab (URL `?page=acrossai-settings&tab=abilities`); the standalone Core tab (URL `?page=acrossai-settings&tab=core`) is not created. Migrate the companion's option keys at activation to manager-branded names: `acrossai_core_abilities_extra_mimes` → `acrossai_abilities_manager_extra_mimes`, `acrossai_core_abilities_uninstall_delete_data` → `acrossai_abilities_manager_uninstall_delete_data`. After migration the manager reads/writes only the new keys and deletes the companion keys.
- Q: After merging into the Abilities tab, the tab would carry two visually-identical "Delete data on uninstall" checkboxes. Should they be consolidated? → A: Consolidate into a single master opt-in. Keep only the manager's existing `acrossai_abilities_uninstall_delete_data` — when true, uninstall deletes both the manager's own data AND the absorbed extra-MIME-types option. Drop the migrated companion opt-in key entirely (no `acrossai_abilities_manager_uninstall_delete_data` is created). On activation, if the companion's opt-in was true, OR that value into the manager's existing opt-in before deleting the companion key, so the admin's prior "delete on uninstall" intent is preserved.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Site admin manages abilities through a single plugin (Priority: P1)

A WordPress site administrator installs and activates only the AcrossAI Abilities
Manager plugin. They see the full inventory of 176 abilities across all 17
categories (Block, Cache, Comments, Content, Cron, Database, FileManager, Fonts,
Media, Menus, Options, Plugins, Settings, SiteHealth, Taxonomies, Themes, Users)
listed in the Abilities admin screen and available via the WP Abilities API.
They no longer need to also install, activate, update, or troubleshoot the
separate `acrossai-core-abilities` companion plugin.

**Why this priority**: This is the primary motivating outcome — eliminating the
two-plugin coupling and delivering the entire ability inventory in one
installable, one-updatable package. Every other benefit (single autoloader,
single quality gate, single .pot) follows from this.

**Independent Test**: On a fresh WordPress site with only the Abilities Manager
active (companion plugin deactivated or absent), open the Abilities admin page
and confirm all 176 abilities are enumerated under their 17 categories. Invoke
one ability from each category via the WP Abilities API and confirm it
executes.

**Acceptance Scenarios**:

1. **Given** the Abilities Manager is active and the companion plugin is
   deactivated, **When** an admin opens the Abilities admin page, **Then** all
   17 categories and all 176 abilities are listed and appear in the same order
   / grouping as they did when the companion plugin owned them.
2. **Given** the Abilities Manager is active and the companion plugin is
   deactivated, **When** an admin invokes an ability via the WP Abilities API
   (for example, listing plugins or reading a post), **Then** the ability
   executes and returns the same result it did when the companion plugin owned
   it.

---

### User Story 2 — Downstream integrators migrate to rebranded slugs in one coordinated cutover (Priority: P2)

Consumers that reference ability category slugs directly (MCP servers, REST
callers, WP-CLI scripts, tests) receive a **known breaking change**: every
category slug of the form `acrossai-core-abilities-<domain>` becomes
`acrossai-abilities-manager-<domain>`. Downstream integrators update their
references in one coordinated cutover synchronised with the manager plugin's
release. Payload shapes for each ability remain unchanged; only the category
slugs (and the class / function / label identifiers behind them) rebrand.

**Why this priority**: This is an intentional breaking change, but it is
secondary to delivering the consolidated plugin. It is the price for retiring
the "Acrossai Core Abilities" identity across the codebase and admin UI.
Consumers know the rename is coming (documented in this spec) and can adapt in
lockstep with the release.

**Independent Test**: Take the list of category slugs the companion plugin
exposed pre-migration. Programmatically rewrite each `acrossai-core-abilities-`
prefix to `acrossai-abilities-manager-`. Enumerate categories via the WP
Abilities API after the migration and confirm every rewritten slug is present
and resolves to the expected set of abilities. Confirm that the original
`acrossai-core-abilities-` slugs are NOT present.

**Acceptance Scenarios**:

1. **Given** the migration is complete, **When** an MCP server or REST client
   enumerates categories via the WP Abilities API, **Then** every category slug
   uses the `acrossai-abilities-manager-` prefix and no slug uses the legacy
   `acrossai-core-abilities-` prefix.
2. **Given** the migration is complete, **When** a caller invokes an ability
   whose category was renamed, **Then** the ability executes and returns a
   payload of the same shape it did before the migration (payload contract
   preserved even though the parent category slug changed).
3. **Given** a downstream caller that hard-coded an old
   `acrossai-core-abilities-<domain>` category slug, **When** it runs against
   the post-migration manager, **Then** the enumeration returns no match for
   the old slug, giving the caller a clear signal to update to the rebranded
   prefix.

---

### User Story 3 — Site admin retains the Core settings fields inside the Abilities tab (Priority: P2)

The admin still sees the extra-allowed-upload-MIME-types field the companion's
Core tab used to own, but it now renders **inside the existing Abilities tab**
(URL `…?page=acrossai-settings&tab=abilities`) alongside the manager's own
settings fields. There is no separate Core tab and no separate uninstall
opt-in — the manager's existing single opt-in governs everything.

**Why this priority**: This is a small but user-visible surface. If the field
silently disappears, admins lose the ability to configure custom MIME types.
Merging into the existing Abilities tab (instead of adding another tab) and
folding the companion opt-in into the manager's single opt-in keeps the
settings surface consolidated and non-confusing. Testable independently from
the ability inventory.

**Independent Test**: With only the Abilities Manager active, open
`…?page=acrossai-settings&tab=abilities` and confirm the extra-MIME-types field
renders below the manager's own Abilities settings fields. Confirm there is
exactly one "Delete data on uninstall" checkbox on the tab. Change the MIME-types
value, save, reload, and confirm it persists under
`acrossai_abilities_manager_extra_mimes`.

**Acceptance Scenarios**:

1. **Given** the Abilities Manager is active and the admin opens
   `…?page=acrossai-settings&tab=abilities`, **When** the tab renders, **Then**
   the extra-MIME-types field is visible on the same tab as the manager's own
   Abilities settings fields; exactly one "Delete data on uninstall" checkbox
   is present (the manager's own); and no separate Core tab exists.
2. **Given** the Abilities tab is open, **When** the admin enters a MIME type
   and saves, **Then** the value persists on reload under the manager-branded
   option key `acrossai_abilities_manager_extra_mimes` and is honored by
   the media upload workflow.
3. **Given** the manager's `acrossai_abilities_uninstall_delete_data` opt-in
   is enabled and the Abilities Manager is later uninstalled, **When**
   WordPress runs the plugin's uninstall handler, **Then** the migrated
   `acrossai_abilities_manager_extra_mimes` option is removed alongside
   the manager's own uninstall cleanup.
4. **Given** a site upgrading from a state where the companion plugin was
   previously installed and the companion's options are still present in
   `wp_options`, **When** the Abilities Manager is activated on the migrated
   code, **Then** the extra-MIME-types value is copied to the manager-branded
   key; the companion's `uninstall_delete_data` value is OR'd into the manager's
   existing opt-in (so a prior true value is preserved); both companion keys
   are then deleted. Admins see their prior configuration preserved.

---

### User Story 4 — Plugin maintainer runs one quality gate (Priority: P3)

The plugin maintainer runs a single quality gate (`composer phpstan`,
`composer phpcs`, `composer test`) that covers the entire ability inventory,
including the previously-separate companion code. The maintainer no longer has
to coordinate two composer configurations, two PHPStan levels, two PHPCS
baselines, or two release cadences.

**Why this priority**: This is a maintainer-facing outcome that follows from
consolidation. It is not directly user-visible but reduces long-term operational
cost.

**Independent Test**: On the feature branch after the migration, run
`composer phpstan`, `composer phpcs`, and `composer test` against the Abilities
Manager plugin only. Confirm the new `includes/Abilities/` tree is analyzed and
that no test bypasses the new code.

**Acceptance Scenarios**:

1. **Given** the migration is complete, **When** the maintainer runs
   `composer phpstan`, **Then** the analysis includes the new
   `includes/Abilities/` tree and reports no new violations relative to the
   pre-migration baseline.
2. **Given** the migration is complete, **When** the maintainer runs
   `composer test`, **Then** existing manager tests still pass, and no test
   is silently skipped or broken by the migration.

---

### Edge Cases

- **Companion options missing on fresh install**: When neither the companion
  option key nor the migrated manager-branded key exists (fresh install of the
  post-migration manager on a site that never had the companion), the
  activation-time migration is a no-op and the settings-tab code paths read
  `acrossai_abilities_manager_extra_mimes` with an empty-list default and
  the manager's existing uninstall opt-in with its normal default (off).
- **Activation-time migration idempotency**: If activation runs multiple times
  (for example, admin toggles the plugin off and on), the migration MUST
  detect that `acrossai_abilities_manager_extra_mimes` already holds a
  value and MUST NOT overwrite it from a stale companion key. The uninstall-
  opt-in OR is naturally idempotent because it can only ever transition false
  → true. Once the companion keys have been copied and deleted, subsequent
  activations are a no-op for the migration.
- **WP Abilities API host missing**: If the WP Abilities API is not available
  at `plugins_loaded @ P20`, ability registration silently no-ops (upstream WP
  behavior). The manager continues to load its other features.
- **Downstream caller assumed a specific plugin folder path**: Callers that
  hard-coded the companion plugin folder (e.g., asset URLs relative to
  `acrossai-core-abilities`) will need to update to the manager's paths. In
  scope for this feature: rewrite plugin-URL/PATH constants inside all moved
  files. Out of scope: notifying external callers.
- **Downstream caller hard-coded a legacy category slug**: Callers that used
  the pre-migration `acrossai-core-abilities-<domain>` slug will not find a
  matching category post-migration and must update to the
  `acrossai-abilities-manager-<domain>` prefix. This is an intentional
  breaking change (see FR-001, US2).
- **New ability category added upstream**: If new category folders appear in
  the source plugin after this migration, they are out of scope for this
  feature and must be added through a subsequent change.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The manager plugin MUST register all 17 ability categories that
  the companion plugin registered, using **rebranded** category slugs of the
  form `acrossai-abilities-manager-<domain>` (e.g., the companion's
  `acrossai-core-abilities-plugins` becomes `acrossai-abilities-manager-plugins`).
  The rename MUST be applied uniformly across all 17 categories. Legacy
  `acrossai-core-abilities-<domain>` slugs MUST NOT be registered.
- **FR-002**: The manager plugin MUST register all 176 ability classes that the
  companion plugin registered. Ability payload shapes MUST remain unchanged.
  Ability slugs MUST use the manager-branded prefix wherever the companion's
  slug contained "core-abilities" wording; any ability slug that did NOT
  contain "core-abilities" wording remains unchanged.
- **FR-003**: Category and ability registration MUST occur at the same
  WordPress hook lifecycle points the companion used
  (`wp_abilities_api_categories_init` at priority 10 for categories;
  `plugins_loaded` at priority 20 for ability class instantiation).
- **FR-004**: The manager plugin MUST render the extra-allowed-upload-MIME-
  types field the companion's Core tab used to own **inside the existing
  Abilities tab** (URL `…?page=acrossai-settings&tab=abilities`) alongside the
  manager's own Abilities settings fields. The manager MUST NOT register a
  separate Core tab and MUST NOT expose the `…?tab=core` URL. The manager MUST
  NOT render a second uninstall-opt-in checkbox on the tab — the manager's
  existing `acrossai_abilities_uninstall_delete_data` opt-in is the single
  master control and governs both the manager's own data and the absorbed
  extra-MIME-types option.
- **FR-005**: The manager plugin MUST NOT introduce a new top-level admin menu
  entry and MUST NOT introduce a new tab inside the shared AcrossAI settings
  page for the absorbed fields. All absorbed settings render inside the existing
  Abilities tab.
- **FR-006**: All user-facing strings inside the absorbed code MUST load
  translations from the manager plugin's text domain
  (`acrossai-abilities-manager`), not the companion plugin's text domain.
  Additionally, every occurrence of the visible wording "Acrossai Core
  Abilities" inside category labels, category descriptions, ability labels,
  and any other admin-visible string MUST be rewritten to "Acrossai Abilities
  Manager". After the migration admins MUST see "Acrossai Abilities Manager —
  …" wherever they previously saw "Acrossai Core Abilities — …".
- **FR-006a**: Internal identifiers inside the absorbed code that contain the
  "Core Abilities" wording MUST be rebranded to use "Abilities Manager" (or
  equivalent manager-branded wording): class names, function names, method
  names, constants, variable names, and any other symbol. The rename MUST be
  applied uniformly and consistently so the manager codebase carries no
  residual "Core Abilities" identifier after the migration.
- **FR-007**: All plugin-URL / plugin-PATH / plugin-basename / plugin-version
  constants referenced inside the absorbed code MUST resolve to the manager
  plugin's constants (not the companion's), so URL-relative and PATH-relative
  operations continue to point at real files.
- **FR-008**: On activation, the manager plugin MUST perform a one-time,
  idempotent migration of the companion's option keys:
  (a) Copy `acrossai_core_abilities_extra_mimes` to
  `acrossai_abilities_manager_extra_mimes` verbatim if the manager-branded
  key does not already hold a value; then delete the companion key.
  (b) OR the boolean value of `acrossai_core_abilities_uninstall_delete_data`
  into the manager's existing `acrossai_abilities_uninstall_delete_data`
  (i.e., only ever set the manager's opt-in to true if the companion's was
  true; never demote a manager opt-in that is already true); then delete the
  companion key. No separate
  `acrossai_abilities_manager_uninstall_delete_data` key is created.
  After activation completes, all reads and writes MUST use only
  `acrossai_abilities_manager_extra_mimes` and the manager's existing
  `acrossai_abilities_uninstall_delete_data`. The manager's uninstall handler
  MUST, when the single opt-in is true, delete both the manager's own data
  AND the `acrossai_abilities_manager_extra_mimes` option, alongside the
  manager's existing uninstall cleanup paths (which MUST continue to work
  unchanged).
- **FR-009**: The manager plugin MUST NOT declare a runtime dependency on the
  companion plugin. After activation, all 176 abilities MUST be available even
  if the companion plugin folder is entirely absent.
- **FR-010**: The absorbed code MUST live in an isolated tree
  (`includes/Abilities/` and the single admin partial in `admin/Partials/`)
  and MUST NOT be interleaved with the manager's existing modules
  (`includes/Modules/Abilities/`, `includes/Modules/Library/`,
  `includes/Utilities/`).
- **FR-011**: The manager plugin's autoloader MUST resolve every class inside
  the new `includes/Abilities/` tree without requiring a companion-plugin
  autoloader or a separate `require` chain.
- **FR-012**: The migration MUST NOT alter the shape of any REST or MCP payload
  produced by an absorbed ability, so downstream integrations relying on
  payload keys, types, or ordering continue to work without change.
- **FR-013**: The migration MUST NOT alter any database schema. The companion
  plugin owns no dedicated database tables; the manager MUST NOT introduce new
  tables to satisfy this feature.
- **FR-014**: The manager plugin MUST NOT modify, delete, or otherwise touch
  the companion plugin folder on disk as part of this feature. Removal of the
  companion plugin from production sites is handled operationally, separately
  from this feature.

### Key Entities *(include if feature involves data)*

- **Ability Category**: A named grouping of related abilities registered with
  the WP Abilities API. Identified by a slug of the form
  `acrossai-abilities-manager-<domain>` (rebranded from the companion's
  `acrossai-core-abilities-<domain>` form). Ownership migrates from the
  companion plugin to the manager plugin; the slug is intentionally rebranded
  as part of the migration.
- **Ability**: A single named unit of work registered with the WP Abilities API
  (e.g., "list installed plugins", "read post revisions"). Identified by a
  stable slug. Payload shape does not change during the migration. Ability
  slugs are rebranded only where they contained the "core-abilities" wording;
  ability slugs that did not contain that wording remain unchanged.
- **Core Settings Field**: One admin-facing option — the extra-allowed-MIME-
  types list — that was formerly rendered on the companion's Core tab. In this
  feature it renders inside the existing Abilities tab. The option key is
  renamed at activation from `acrossai_core_abilities_extra_mimes` to
  `acrossai_abilities_manager_extra_mimes`; the value is preserved
  verbatim. The companion's separate uninstall-opt-in is folded into the
  manager's existing single opt-in and does not survive as its own entity.
- **Uninstall Opt-In**: A single stored boolean at
  `acrossai_abilities_uninstall_delete_data` (the manager's existing key) that
  determines whether ALL plugin-owned data — both the manager's original data
  and the absorbed extra-MIME-types option — is deleted when the manager
  plugin is uninstalled. Activation ORs the companion's prior opt-in into this
  key so admin intent is preserved.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: With only the manager plugin active, 100% of the 17 companion
  categories and 100% of the 176 companion abilities appear in the WP Abilities
  API enumeration.
- **SC-002**: 100% of the pre-migration category slugs are renamed to the
  `acrossai-abilities-manager-<domain>` form; 0% of legacy
  `acrossai-core-abilities-<domain>` category slugs are registered
  post-migration. Ability payload shapes remain identical so downstream
  callers only need to update category-slug references, not payload parsing.
- **SC-003**: Site admins can consolidate the two-plugin install into a
  single-plugin install with a single deactivate + reload step (deactivate the
  companion plugin, reload the WordPress admin).
- **SC-004**: The absorbed extra-MIME-types field renders inside the existing
  Abilities tab (URL `…?page=acrossai-settings&tab=abilities`) alongside the
  manager's own fields, and saves under
  `acrossai_abilities_manager_extra_mimes` in under 5 seconds of admin
  interaction time (matching the pre-migration save latency). Exactly one
  uninstall-opt-in checkbox is visible on the tab. No `…?tab=core` URL is
  reachable after the migration.
- **SC-005**: Zero references to the companion's text domain
  (`acrossai-core-abilities`) remain inside the manager plugin's new
  `includes/Abilities/` tree after the migration.
- **SC-006**: Zero references to companion plugin-URL/PATH/basename/version
  constants remain inside the manager plugin's new `includes/Abilities/` tree
  after the migration.
- **SC-007**: Existing manager PHPUnit test suite continues to pass unchanged;
  no test in `Modules/Abilities/` or `Modules/Library/` is silently skipped or
  broken by the migration.

## Assumptions

- The companion plugin (`acrossai-core-abilities`) is not installed or active
  on target sites when the migrated manager is activated. Because of that, this
  feature does not implement UX for a co-active state and does not attempt to
  auto-deactivate the companion. Removal of the companion from production
  sites happens operationally, outside the scope of this feature.
- Downstream MCP servers, REST callers, WP-CLI scripts, and integration tests
  that reference category slugs directly are treated as an intentional
  breaking-change surface. The migration renames category slugs from
  `acrossai-core-abilities-<domain>` to `acrossai-abilities-manager-<domain>`;
  downstream consumers coordinate their cutover with the manager release.
  Ability payload shapes remain identical, so only slug references need to
  update — not payload parsing. PHP class namespaces also change (they are
  implementation-level and downstream code should not reference them
  directly).
- The WP Abilities API is available at the same lifecycle points the companion
  plugin relied on (`wp_abilities_api_categories_init` and
  `plugins_loaded @ P20`). This assumption matches the companion plugin's
  original design.
- The manager plugin already bootstraps the shared AcrossAI settings page host
  at `plugins_loaded @ P0` (Feature 038). The absorbed Core tab attaches to
  that host through the shared `acrossai_settings_tabs` filter. This feature
  does not need to add a new admin menu.
- The companion plugin has no dedicated database tables — only options. This
  matches the observed source inventory (2026-07-13). If a table exists that
  was missed during inventory, uninstall behavior must be revisited before
  release.
- No JavaScript or SCSS assets are being moved. The companion plugin has no
  enqueued frontend or admin assets outside of what the manager already
  provides.
- Regeneration of the `.pot` translation template is a follow-up in a separate
  spec. This feature ships with strings changed but does not re-issue the
  translation catalog.

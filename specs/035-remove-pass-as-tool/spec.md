# Feature Specification: Remove "Pass as Tool" Capability

**Feature Branch**: `035-remove-pass-as-tool`
**Created**: 2026-06-16
**Status**: Draft
**Input**: User description: "Remove the 'Pass as Tool' feature (Feature 029) completely from the plugin. Background: Feature 029 added a tri-state tinyint column 'pass_as_tool' to the acrossai_abilities table and a runtime hook (AcrossAI_Ability_Override_Processor::inject_mcp_tools, registered at mcp_adapter_init P20) that injects opted-in ability slugs into every connected MCP server's private component_registry via Reflection. Feature 034 already deleted the per-server 'mcp_servers' allowlist that previously gated this injection. Feature 035 removes the remaining pass_as_tool surface."

## Clarifications

### Session 2026-06-16

- Q: Should the obsolete `pass_as_tool` column on existing installs be removed via an automated `maybe_upgrade()` ALTER routine (with a table-version bump), or via manual operator action (deactivate plugin → drop table → reactivate)? → A: Manual. This is a new pre-launch plugin; no automated migration is provided. Developers deactivate the plugin, manually drop the abilities table, and reactivate so the table is recreated by the schema without the obsolete column. No `maybe_upgrade()` routine, no `$version` bump, no schema-migration code is added by this feature.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Plugin no longer auto-injects abilities into MCP servers (Priority: P1)

A site administrator has the Abilities Manager active alongside one or more MCP servers (the
default server plus any servers contributed by other plugins). Before this feature, abilities
marked "Pass as Tool" were silently registered into every MCP server's tool registry at server
initialization, irrespective of the server's own configuration. After this feature, the Abilities
Manager stops doing that injection entirely; each MCP server's tool list is determined by that
server's owner — typically the forthcoming `acrossai-mcp-manager` plugin.

**Why this priority**: This is the core behavior change. Without it, the feature has not actually
been removed and the architectural inversion started in Feature 034 is incomplete. The MCP server
ecosystem cannot move to owner-controlled tool lists while the Abilities Manager keeps cross-cutting
into them.

**Independent Test**: Activate the plugin with one or more MCP servers present. Before this
feature, abilities with `pass_as_tool = 1` (or equivalent) appeared in every server's
`tools/list` response and were callable via `tools/call`. After this feature, those abilities are
absent from every server's tool list unless the server's own owner has registered them. The plugin
emits no MCP-server-init log entries and registers no Reflection-based hooks against private MCP
adapter internals.

**Acceptance Scenarios**:

1. **Given** the plugin is active on a site with the default MCP server and at least one ability
   previously flagged as "Pass as Tool", **When** an MCP client requests `tools/list` from any
   server, **Then** the previously injected ability slugs are not present in the response (only
   the server's own registered tools appear).
2. **Given** the plugin is active, **When** an MCP server is initialized (regardless of priority
   or owner), **Then** the Abilities Manager does not register additional callable tools into that
   server's registry.
3. **Given** any third-party plugin or theme inspects the WordPress hook system, **When** it
   enumerates listeners on `mcp_adapter_init`, **Then** no listener owned by the Abilities Manager
   appears.

---

### User Story 2 - Admin UI no longer offers "Pass as Tool" controls (Priority: P1)

A plugin administrator opens the Abilities list and the Ability edit screen. Before this feature,
the list table had a "Pass as Tool" column with a per-row On/Off toggle, and the edit screen had a
dedicated "Pass as Tool" section (Section 4) with a tri-state chip selector and a warning banner
about server load. After this feature, both surfaces are gone; the surrounding columns and
sections still render correctly with no visual gaps.

**Why this priority**: Same priority as US1 — leaving the UI in place would mislead admins into
thinking a removed capability is still functional. The control must disappear at the same time as
the runtime hook.

**Independent Test**: Load the abilities list page; confirm no "Pass as Tool" column header, no
toggle cells, and no entry for it inside the column-visibility menu. Open any ability edit
screen; confirm no Section 4 "Pass as Tool" block, and that the section numbering of remaining
sections is contiguous (no gap where Section 4 used to be). Confirm save still works for every
other field. Browser console emits no React/PropTypes errors related to a missing field.

**Acceptance Scenarios**:

1. **Given** an administrator visits the abilities list page, **When** the table renders,
   **Then** there is no "Pass as Tool" column header or cell, the column-visibility selector
   contains no "Pass as Tool" entry, and the remaining columns reflow without empty slots.
2. **Given** an administrator opens an ability for editing, **When** the form renders, **Then** no
   "Pass as Tool" section is shown, the visible section numbers are contiguous, and saving the
   ability succeeds without sending or expecting a `pass_as_tool` value.
3. **Given** a user previously had column preferences that included a "Pass as Tool" toggle,
   **When** the list page loads, **Then** the missing preference key is ignored gracefully without
   any console error or broken column visibility.

---

### User Story 3 - REST API stops accepting or returning the field (Priority: P2)

External clients (the React admin app, MCP servers, integration tests, or third-party tooling)
read and write abilities through the plugin's REST API. Before this feature, ability records
included a `pass_as_tool` field on every list/edit/exposure response, and the field could be
written via create/update endpoints. After this feature, the field is absent from every response
shape, and write requests that still include it silently ignore the value (no validation error,
no persistence).

**Why this priority**: Strictly downstream of US1 and US2 — once the runtime hook and UI are gone,
the field is no longer meaningful. Backward compatibility (silent acceptance of obsolete keys) is
intentional so pre-launch clients pinned to older payload shapes still function during the
transition.

**Independent Test**: Hit the abilities list endpoint and confirm `pass_as_tool` is not in any
returned record. Hit the merged ability endpoint (registry + override) and confirm the same. POST
a create request that includes `pass_as_tool` in the body; the request succeeds, the response does
not echo the field, and re-reading the row never surfaces the value.

**Acceptance Scenarios**:

1. **Given** a REST client requests the abilities list, **When** the response is returned,
   **Then** no entry in the collection contains a `pass_as_tool` key.
2. **Given** a REST client requests the MCP exposure collection, **When** the response is
   returned, **Then** no entry contains a `pass_as_tool` key.
3. **Given** a REST client sends an update request including `pass_as_tool: true`, **When** the
   plugin processes the request, **Then** the request completes successfully, the field is not
   persisted, the response shape contains no `pass_as_tool` key, and a subsequent read confirms no
   value was stored.

---

### User Story 4 - Fresh table on next install (Priority: P2)

A developer working on a pre-launch environment removes the obsolete column by hand. The
documented procedure is: deactivate the plugin, drop the abilities table directly in the
database, then activate the plugin again — the schema is recreated without the obsolete column.
No automated migration runs; no schema version bump is performed; no `maybe_upgrade()` routine is
added.

**Why this priority**: Pre-launch context — no production sites and no real user data are
affected. A documented manual procedure is sufficient and avoids carrying migration code that
would be obsolete by launch.

**Independent Test**: On an environment that already has the abilities table with the obsolete
column, deactivate the plugin, drop the abilities table manually via SQL or a database tool,
reactivate the plugin, and confirm the freshly created table contains no `pass_as_tool` column
(`SHOW COLUMNS LIKE 'pass_as_tool'` returns empty). On a clean environment with no abilities
table, simply activating the plugin yields the same outcome.

**Acceptance Scenarios**:

1. **Given** a clean environment with no abilities table, **When** the plugin is activated,
   **Then** the freshly created abilities table contains no `pass_as_tool` column.
2. **Given** an existing development environment with an abilities table that still has the
   obsolete column, **When** the developer follows the documented procedure (deactivate, drop
   table, reactivate), **Then** the recreated table contains no `pass_as_tool` column and no
   abilities-related runtime error is raised on reactivation.
3. **Given** a developer skips the manual drop and merely reactivates the plugin, **When** the
   plugin runs, **Then** runtime code never reads or writes the obsolete column (because no
   production code path references it anymore); the column may persist harmlessly in the table
   until the developer chooses to drop it.

---

### User Story 5 - Project memory and governance reflect the removal (Priority: P3)

A future contributor (human or AI) opens the project memory artifacts and the constitution.
Before this feature, those documents described `pass_as_tool` as an active design decision with
associated bugs and patterns. After this feature, the decision entry is marked superseded, the
relevant bug entries record their resolution, and the durable lessons (private constructor
singletons, AC-rule fail-open with `check_permissions()` fallback) are preserved. The
forthcoming `acrossai-mcp-manager` plugin inherits the residual responsibility, recorded
explicitly.

**Why this priority**: Lower than the user-facing changes because it doesn't affect end-user
behavior. It is, however, mandatory for future-conversation correctness — without it, the next
Spec Kit run will regenerate the removed feature.

**Independent Test**: Open `docs/memory/DECISIONS.md` and confirm the original entry for the
feature is marked `Superseded`, a new dated entry records the removal and its rationale, and the
referenced bug entries reflect their new status. Open `docs/memory/INDEX.md` and confirm the rows
for the relevant decisions and bugs are updated. Confirm the worklog has a Feature 035 entry.

**Acceptance Scenarios**:

1. **Given** a contributor reads the project's decisions log, **When** they search for the
   per-ability MCP tool injection design, **Then** they find a clearly dated `Superseded` marker
   plus a new entry explaining the removal and the deferred ownership.
2. **Given** a contributor reads the project's bugs log, **When** they look up the bugs that arose
   from the feature, **Then** they see their status updated to "Resolved (feature removed)" with
   the durable lessons preserved where applicable.
3. **Given** a contributor reads the constitution, **When** the document mentions per-ability MCP
   tool injection, **Then** any such mention is removed and the constitution version is bumped
   with the standard sync-impact report.

---

### Edge Cases

- **Existing records with `pass_as_tool = 1`**: No code path reads the column after this feature
  lands. Developers drop the abilities table by hand when they want a clean schema; values stored
  in the old column are discarded then. No "fallback exposure" path is added — the data is
  intentionally gone.
- **Third-party plugins/themes that previously depended on the injection**: Out of scope; if any
  exist (none are known in-tree), they need to register their own MCP server tool list directly.
- **Clients that POST `pass_as_tool` in payloads after this feature lands**: The field is silently
  ignored. No error is returned, no deprecation warning is emitted to the wire — the value is
  simply not persisted and the response shape contains no echo.
- **Column-visibility preferences persisted in browser storage that still contain
  `pass_as_tool`**: The orphan key is ignored. The column-visibility loader must not raise an
  error or break the surrounding columns.
- **AbilityForm's tri-state UI elsewhere**: Other tri-state controls (Show in REST, Show in MCP,
  Site Allowed, Readonly, Destructive, Idempotent) are unaffected.
- **The shared "Show in MCP" flag**: Remains untouched. It still governs whether an ability is
  exposed to MCP at all.
- **Repeated activations on a dev environment that has not had its table dropped**: Activation
  remains a no-op for the schema (no auto-ALTER is attempted). The obsolete column simply persists
  in the table; runtime code never reads or writes it.
- **Existing test fixtures that set `pass_as_tool` on rows**: Mixed-concern tests are edited to
  drop the key; pure `pass_as_tool` test suites are deleted in full.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST NOT register any listener that injects abilities into MCP servers'
  tool registries at server initialization time. Any prior runtime hook that did so is removed.
- **FR-002**: The persistent storage for abilities MUST NOT contain a `pass_as_tool` column or any
  successor column with equivalent semantics. On new installations the column is never created.
  On existing pre-launch development environments, the column is removed by the documented manual
  procedure (deactivate plugin → drop abilities table → reactivate plugin); no automated migration
  is provided by this feature.
- **FR-003**: The admin UI MUST NOT render any control labelled "Pass as Tool" — neither in the
  ability list table nor in the ability edit form. The surrounding columns/sections must reflow
  without leaving visual gaps, and section numbers must be contiguous.
- **FR-004**: The admin column-visibility selector MUST NOT contain a "Pass as Tool" entry, and
  persisted column-visibility preferences from previous versions that reference the removed key
  MUST be ignored without producing console errors or broken table layouts.
- **FR-005**: REST responses describing abilities (list, single, exposure, and merged-registry
  shapes) MUST NOT contain a `pass_as_tool` key.
- **FR-006**: REST endpoints that accept ability updates MUST silently ignore any `pass_as_tool`
  value supplied by a client. No validation error is returned, the field is not persisted, and the
  response does not echo it.
- **FR-007**: Internal write paths (sanitizer, formatter, merger, query layer) MUST NOT contain
  any reference to `pass_as_tool`. A repository-wide search of the production code paths for the
  identifier must return zero hits.
- **FR-008**: All test suites dedicated to the removed feature MUST be deleted in full.
  Mixed-concern tests MUST have their `pass_as_tool` fixtures and assertions removed while their
  remaining coverage stays intact, so the test suite continues to pass after this feature lands.
- **FR-009**: The schema definition MUST stop describing the `pass_as_tool` column so that any
  fresh-install code path (BerlinDB `dbDelta`/install) creates the table without the column. No
  automated `maybe_upgrade()` ALTER routine is introduced; the table's `$version` is not bumped.
  Existing development environments are cleaned by the documented manual procedure in FR-002.
- **FR-010**: Project memory artifacts (the decisions log, the bugs log, the index, and the
  worklog) MUST be updated to mark the prior design decision as superseded, record the removal
  with its date and rationale, and update bug-entry statuses where the removal closes the
  underlying issue. Durable lessons (BerlinDB private-constructor singletons, AC-rule fail-open
  with permission-callback fallback) MUST be preserved.
- **FR-011**: The project constitution MUST be reviewed for references to per-ability MCP tool
  injection. If any are found, they are removed and the constitution version is bumped at the
  PATCH level with the standard sync-impact report.
- **FR-012**: All quality gates (static analysis, code style, plugin-check, unit tests, frontend
  build) MUST pass after the removal, with no regression introduced into adjacent functionality.

### Out-of-Scope (must NOT change)

- The general "Show in MCP" flag and every other tri-state ability field.
- The remainder of the override processor: override-arg injection, blocked-ability unregistration,
  cache management, and the Manager REST early-return path.
- Protected-slugs handling and the localization channel used by other UI cells.
- REST endpoint paths and any response field other than `pass_as_tool`.
- The Library, Logger, and Access Control modules.
- Historical spec/planning artifacts for features 029, 032, and 034 — they remain as archival
  records and are not edited by this feature.

### Key Entities *(include if feature involves data)*

- **Ability record (post-removal)**: The unit of data stored for each ability override. Carries
  identity, status, presentation (label, description, category), the existing tri-state ability
  flags (Show in REST, Show in MCP, Site Allowed, Readonly, Destructive, Idempotent), MCP type,
  callback configuration, and audit fields. Does **not** carry a "pass as tool" flag.
- **MCP server (third-party owned)**: An external participant that owns its own tool list. After
  this feature, the Abilities Manager neither inspects nor modifies the registries of these
  servers.
- **Project memory record**: A dated, named entry in the decisions/bugs/worklog logs. After this
  feature, the prior `pass_as_tool` decision record is marked superseded; the new removal record
  inherits its place. The durable lessons recorded alongside the original entry are preserved.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator using a standard MCP client sees zero ability slugs in any MCP
  server's `tools/list` response that the Abilities Manager itself injected — only server-owned
  tools appear. (Measurable: enumerate `tools/list` before/after; the differential equals exactly
  the previously injected slugs.)
- **SC-002**: Loading the abilities list page on a typical site takes no longer than before this
  feature, and the rendered table has one fewer column. (Measurable: lighthouse/page-load timing
  unchanged within noise; column count decreases by exactly one.)
- **SC-003**: After following the documented manual procedure (deactivate, drop abilities table,
  reactivate), the recreated abilities table contains no `pass_as_tool` column. On a clean
  environment, activation alone produces the same outcome. (Measurable: post-activation
  `SHOW COLUMNS LIKE 'pass_as_tool'` returns an empty result set in both scenarios.)
- **SC-004**: 100% of REST responses describing abilities are free of the `pass_as_tool` key, and
  POST requests that include the key receive a normal success response without persisting the
  value. (Measurable: schema diff + persistence read-back.)
- **SC-005**: 100% of repository searches for the identifier across the production code surface
  (excluding historical archives and this feature's own artifacts) return zero hits. (Measurable:
  exact grep target documented in the validation checklist.) The single carve-out is the
  inverse-assertion contract-pin test added under FR-006 (currently
  `tests/phpunit/abilities/AbilitiesPassAsToolRemovalTest.php`), which must reference the removed
  identifier to assert its absence from sanitizer output. Hits inside that file are intentional
  and do not block SC-005.
- **SC-006**: All existing quality gates remain green after the removal (static analysis, code
  style, plugin check, PHPUnit, Jest, frontend build). No new test failures are introduced
  outside the deliberately deleted `pass_as_tool` suites.

## Assumptions

- The plugin is pre-launch. No production sites depend on the removed capability, so destructive
  schema migration with no data preservation is acceptable.
- The forthcoming `acrossai-mcp-manager` plugin will take ownership of per-server tool lists.
  This feature does not deliver that plugin; it only removes the cross-cutting injection that
  prevented an owner-controlled model.
- Silent acceptance of obsolete `pass_as_tool` keys in inbound REST requests is intentional for
  the transition window. No deprecation warning is wired into the wire response, since
  pre-launch clients are still under active development against the same repository.
- The existing override-processor's non-MCP-injection responsibilities (override-arg injection,
  unregister-blocked-abilities, cache management, Manager REST early-return) remain valuable
  and stay in place. Only the `pass_as_tool` injection path is removed from that file.
- This plugin is pre-launch and the only environments holding the obsolete column are developer
  sandboxes and CI fixtures. Developers will deactivate the plugin, drop the abilities table
  directly, then reactivate the plugin to obtain a clean schema. No automated `maybe_upgrade()`
  routine, table-version bump, or ALTER statement is added by this feature. Carrying such code
  past launch would create dead weight; deferring the schema cleanup to a manual procedure keeps
  the codebase free of one-shot migrations.
- Browser column-visibility preferences are best-effort and may legitimately contain orphan keys
  from a prior plugin version. Defensive loading already tolerates unknown keys; the removal
  must not introduce any new strict requirement on the stored shape.

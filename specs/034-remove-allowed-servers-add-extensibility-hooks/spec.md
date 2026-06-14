# Feature Specification: Remove Allowed Servers, Add Extensibility Hooks

**Feature Branch**: `034-remove-allowed-servers-add-extensibility-hooks`
**Created**: 2026-06-14
**Status**: Draft
**Input**: User description: Remove the per-ability "Allowed Servers" setting from `acrossai-abilities-manager` entirely and replace it with a small, MCP-agnostic extension-point surface (React + PHP hooks) so a future `acrossai-mcp-manager` plugin can inject its own server-mapping UI into the ability form without this plugin knowing about MCP servers. The `mcp_servers` database column is removed from the schema; this plugin has not been launched yet, so no upgrade migration is shipped — fresh installs simply never create the column, and any dev install with stale data is handled by manually dropping the table and reactivating.

## Clarifications

### Session 2026-06-14

- Q: Are the keys forwarded by the `acrossai_abilities.form.extra_sections` context object (`abilityId`, `slug`, `draft`, `isNonDb`) part of the public extension contract, or implementation detail that can change between releases? → A: Frozen contract — the four keys are stable; additions are non-breaking; removals or renames require a deprecation cycle (parallel emission of old + new for ≥1 minor release before removal). Same posture as hook names per FR-010.
- Q: What firing cadence is the contract for `acrossai_abilities.form.draft_changed`? → A: Fire on every React commit where the draft state reference changed (per-keystroke for text fields, per-batch for grouped updates). Extensions own debouncing policy; the plugin adds no internal debounce.
- Q: Is the JS global variable that receives the localized abilities admin data part of the public extension contract? → A: Yes. The variable name `window.acrossaiAbilitiesManager` (already in use as of `admin/Main.php:254-256`) is part of the contract alongside the five hook names and the context object's four keys; renaming requires the FR-010 deprecation cycle. Note: data injection currently uses `wp_add_inline_script` with JSON-encoded data, not `wp_localize_script` (the planning doc's example was illustrative); the `acrossai_abilities_admin_localize_data` filter wraps that data array regardless of injection mechanism.

## Architectural Context

### Architectural inversion

This feature is not just a UI removal — it is a **layering inversion**. Prior to this PR, `acrossai-abilities-manager` owned both the ability registry AND the per-ability MCP-server allowlist (a transport-specific authorization concern). After this PR, the abilities plugin is a **pure ability registry** with no MCP-specific code, and a future `acrossai-mcp-manager` plugin will own the MCP-server ↔ ability mapping and its enforcement, reaching into this plugin only through the five public extension hooks established by this feature. The inversion lets each module evolve independently and removes a coupling to a specific MCP-adapter package implementation. See `architecture-migration-plan.md` for the full migration narrative (Phase 1 = this PR; Phase 2 = future MCP manager plugin).

### Composer dependency removal

This PR also removes the `wpboilerplate/wpb-mcp-servers-list` Composer dependency in its entirety. The package was the source of the MCP servers list endpoint (`GET /wpb-mcp-servers-list/v1/servers`) consumed by the deleted Allowed Servers UI and was wired in `includes/Main.php` (`McpServersList::collect` at `rest_api_init` priority 20 + `RestEndpoint::register`). With the Allowed Servers UI deleted (US1) and no other internal consumer of either the endpoint or the `wpb_mcp_servers_list_rest_capability` filter, the package becomes dead weight and is excised. Future MCP-server enumeration (if needed by the eventual `acrossai-mcp-manager` plugin) is that plugin's concern; it will choose its own enumeration mechanism. **Constitution §Integration Resilience must be amended** to retract the "MUST use `wpboilerplate/wpb-mcp-servers-list`" mandate (the canonical-pattern paragraph added in Constitution v1.4.1) — this requires a minor version bump and a sync-impact-report entry.

### Security posture change

This feature **deletes a fail-closed authorization layer** previously implemented in two places:
1. `AcrossAI_Abilities_Exposure_Controller` — fail-closed exclusion of abilities from the MCP exposure list when the ability had a non-empty `mcp_servers` allowlist and the requesting server's `server_id` was unknown or empty.
2. `AcrossAI_Ability_Override_Processor` — fail-closed per-server allowlist gate inside both the override-injection path (`inject_override_args`) and the pass-as-tool injection path (`inject_mcp_tools` from Feature 029). Note: removing `mcp_servers` means **Feature 029's `pass_as_tool` injection also loses its per-server allowlist filter** — an ability with `pass_as_tool=1` will be injected on every MCP server once this PR ships, until Phase 2 (the future MCP manager plugin) re-implements equivalent enforcement.

Post-merge, abilities are **unconditionally exposed** to all MCP servers — both for general exposure and for `pass_as_tool` injection. This is acceptable in the pre-launch context (the plugin has not been launched publicly; no production sites are affected) and is the intentional first phase of the architectural inversion above. The future `acrossai-mcp-manager` plugin's re-implementation **MUST be fail-closed by default** to match the prior posture; this requirement is recorded here so the future implementer cannot accidentally default to fail-open.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Abilities plugin becomes a pure ability registry (Priority: P1)

A site administrator who manages abilities sees a clean ability edit form that contains no server-selection UI. The plugin behaves as a focused ability registry: it defines abilities, exposes them via REST, and stores their definitions. It has no opinion about which MCP servers may use a given ability — that concern belongs to a separate plugin.

**Why this priority**: This is the core architectural inversion. Without it, the plugin keeps shipping a half-built feature (server selection that has no enforcement layer in this plugin) and continues to leak MCP-specific concepts into a plugin meant to be transport-agnostic. Every other story in this spec depends on the removal landing first.

**Independent Test**: A site administrator opens the ability edit page in WP admin with no other plugins installed and confirms (a) no "Allowed Servers" UI block appears, (b) all other ability fields (label, slug, callback type, readonly, schemas, etc.) save and round-trip unchanged, and (c) the REST response for an ability contains no `mcp_servers` field.

**Acceptance Scenarios**:

1. **Given** a fresh install of the abilities plugin with no extension plugins active, **When** an administrator opens an ability edit page, **Then** the form renders with all standard fields and no "Allowed Servers" section.
2. **Given** a REST client posting `mcp_servers: ["server-a"]` in an ability create or update request, **When** the request is processed, **Then** the field is silently ignored (not rejected, not stored, not returned in the response).
3. **Given** the abilities plugin is the only active plugin, **When** an administrator creates, edits, saves, and deletes abilities, **Then** every operation succeeds with no console errors, no PHP notices, and no broken UI states.

---

### User Story 2 - Future MCP manager plugin can inject server-mapping UI (Priority: P2)

A plugin developer building a separate `acrossai-mcp-manager` plugin (or any third-party extension) can render their own UI block inside the ability edit form, observe form draft changes, and piggy-back data on the save request — all without the abilities plugin knowing the extension exists. The extension surface is intentionally small: an extra-sections slot, a draft-changed notification, a save-payload filter, a PHP page-bootstrap action, and a PHP localize-data filter.

**Why this priority**: This story enables the future architecture but is not blocking on day one. Even with zero extensions installed, story 1 still delivers value. However, shipping the hook seams now (rather than later) is essential — adding extension points retroactively requires consumers to gate on plugin versions and complicates the eventual MCP manager plugin's release.

**Independent Test**: A test MU plugin registers handlers for each of the five public hook names and confirms (a) a custom UI block renders in the form where the old Allowed Servers block lived, (b) the form's current draft state is observable in real time via the draft-changed action, (c) data added by the save-payload filter reaches the REST request, (d) the PHP page-load action fires after the abilities admin script is enqueued, and (e) keys added via the localize-data filter appear in the browser's localized JS data object.

**Acceptance Scenarios**:

1. **Given** an extension plugin subscribes to the form's extra-sections filter and returns one or more React nodes, **When** the ability edit page renders, **Then** those nodes appear in the form layout with the same surrounding spacing/styling discipline as the removed Allowed Servers block.
2. **Given** an extension subscribes to the draft-changed action, **When** the administrator edits any field in the form, **Then** the extension's handler receives the current draft state on each change.
3. **Given** an extension subscribes to the save-payload filter and adds a key, **When** the administrator saves the ability, **Then** the REST request body contains the added key.
4. **Given** an extension subscribes to the PHP form-settings-registered action, **When** the abilities admin page loads, **Then** the action fires after the admin script bundle is enqueued and the handle is available for the extension to attach further scripts to.
5. **Given** an extension subscribes to the admin localize-data filter and adds an entry, **When** the admin page renders, **Then** that entry appears in the localized JS data the form reads on mount.
6. **Given** no extensions are subscribed to any of the five hooks, **When** any admin or REST operation runs, **Then** behavior is identical to a baseline where the hooks were not invoked at all (no errors, no extra markup, no extra REST fields).

---

### Edge Cases

- **Hook called with zero subscribers**: Every new hook (3 JS, 2 PHP) must produce baseline-identical behavior when nothing is listening. The filters must return their default input unchanged; the actions must complete without side effects from this plugin.
- **Extension returns invalid value from extra-sections filter**: Specification requirement is that the slot consumes React nodes only. Extensions that return non-renderable values are the extension's bug — the abilities plugin is not responsible for defensive normalization beyond what React's renderer naturally tolerates.
- **REST request contains the obsolete `mcp_servers` field**: Silently ignored. No error returned; the request succeeds as if the field were not present. This avoids breaking any clients still sending the field during the upgrade window.
- **Form save-payload filter mutates required fields**: An extension that breaks the payload (e.g., strips `slug`) is responsible for its own bug. The abilities plugin's REST layer continues to validate required fields and will reject malformed requests with the existing error semantics.
- **Draft-changed action handler throws**: If an extension's handler throws, the form's normal user-input flow should not be derailed — but this plugin does not need to add a try/catch wrapper. WordPress's hooks system does not isolate exceptions; this is a known platform constraint and consistent with all existing `do_action` callsites.

## Requirements *(mandatory)*

### Functional Requirements

**Removal:**

- **FR-001**: The plugin MUST NOT expose, store, accept, return, or reference an `mcp_servers` field on any ability — in the database schema, REST request/response shape, sanitizer output, formatter output, registry args, or admin UI.
- **FR-002**: The plugin MUST NOT retain any `mcp_*`-named hook, option, table, function, constant, or class in code shipped after this feature. A reader unfamiliar with MCP MUST NOT be able to infer the feature's prior existence from the remaining code. **Exception**: a small number of inline PHP comments at deletion sites (specifically: `AcrossAI_Ability_Override_Processor` and `AcrossAI_Abilities_Exposure_Controller`) MAY reference the deleted `mcp_servers` name when documenting the "Security posture change" — the fail-closed allowlist enforcement was deliberately removed and relocated to a future sibling plugin, and an audit-trail comment is more valuable to future maintainers than literal compliance with the no-inference rule. Such comments MUST be limited to security-posture-change documentation; gratuitous historical references to `mcp_servers` are NOT covered by this exception.
- **FR-003**: The plugin MUST silently accept (without error) any inbound REST create or update request that includes an `mcp_servers` key, and MUST NOT echo that key back in the response.

**Extension surface (React):**

- **FR-004**: The plugin MUST expose a filter named `acrossai_abilities.form.extra_sections` in the ability edit form, invoked at the location of the removed Allowed Servers block, that accepts an initial empty array and forwards a context object containing exactly these four keys: `abilityId` (ability identifier or `null` for a new ability), `slug` (ability slug or `null`), `draft` (the current in-form draft state), and `isNonDb` (boolean — `true` if the ability is registered in code and not database-backed). These four keys are part of the public extension contract (see FR-010); adding new keys is permitted and non-breaking, while removing or renaming any of the four requires the FR-010 deprecation cycle.
- **FR-005**: The plugin MUST expose an action named `acrossai_abilities.form.draft_changed` that fires on every React commit where the form's draft state reference has changed (per-keystroke for text fields; per-batch for grouped state updates), passing the current draft to subscribers. The plugin MUST NOT apply any internal debouncing — debouncing is the subscriber's responsibility. **Reference-equality semantics**: the "draft state reference" is the value returned by the Redux `getDraftAbility` selector inside a `useEffect([draft])` block — so the action fires on every React commit where that selector returned a new reference, NOT on every Redux store dispatch (which may be more or less frequent if intermediate states are batched by re-render scheduling). Subscribers MUST design against per-React-commit cadence, not per-Redux-dispatch cadence.
- **FR-006**: The plugin MUST expose a filter named `acrossai_abilities.form.save_payload` invoked immediately before the REST save request is sent, allowing subscribers to mutate or augment the outgoing payload.

**Extension surface (PHP):**

- **FR-007**: The plugin MUST fire an action named `acrossai_abilities_form_settings_registered` from the abilities admin page bootstrap, after the abilities admin script bundle has been enqueued and its handle is available for subsequent enqueues to attach to.
- **FR-008**: The plugin MUST wrap the abilities admin data array in a filter named `acrossai_abilities_admin_localize_data` before that array is serialized and injected into the page as `window.acrossaiAbilitiesManager` (the existing global, currently injected via `wp_add_inline_script` in the admin bootstrap). The global variable name `acrossaiAbilitiesManager` is part of the public extension contract (see FR-010) — extensions read added keys as `window.acrossaiAbilitiesManager.theirKey`.

**Hook contract guarantees:**

- **FR-009**: With zero subscribers to any of the five new hooks, the abilities plugin's user-visible behavior MUST be identical to a baseline where the hooks were not invoked at all.
- **FR-010**: The following collectively form the public extension contract and MUST NOT be renamed without a deprecation cycle (parallel emission of old + new for ≥1 minor release before removal):
  1. The five hook names: `acrossai_abilities.form.extra_sections`, `acrossai_abilities.form.draft_changed`, `acrossai_abilities.form.save_payload`, `acrossai_abilities_form_settings_registered`, `acrossai_abilities_admin_localize_data`.
  2. The four context-object keys forwarded by `acrossai_abilities.form.extra_sections` (see FR-004): `abilityId`, `slug`, `draft`, `isNonDb`.
  3. The three context-object keys forwarded by `acrossai_abilities.form.save_payload` (see FR-006): `abilityId`, `slug`, `isNonDb`. (Note: no `draft` here — the filter receives `basePayload` as its first argument; the context object is metadata about the ability being saved, not the form's working draft.)
  4. The JS global variable name `acrossaiAbilitiesManager` (see FR-008) used to expose data injected via the localize-data filter.

  Adding new hooks, new context-object keys, or new properties to the JS global is non-breaking and does NOT require a deprecation cycle.

**Schema (fresh installs only — no migration shipped):**

- **FR-011**: On a fresh install, the abilities table MUST be created without an `mcp_servers` column. (The plugin has not been launched yet; no upgrade migration is shipped. Dev installs with stale data are handled by manually dropping the table and reactivating.)
- **FR-012**: The plugin MUST NOT ship any code that drops, renames, or otherwise migrates the `mcp_servers` column on existing installs. Removing the column from the schema definition is sufficient.

**Tests:**

- **FR-013**: The plugin MUST remove all automated tests (JavaScript and PHP) that assert on the `mcp_servers` field, including the dedicated Jest file for the Allowed Servers checkbox.
- **FR-014**: The plugin MUST NOT add new automated tests covering the hook pass-through behavior; manual smoke verification via an example MU plugin snippet is the documented verification path.

### Key Entities

- **Ability**: A registered unit of behavior in the abilities plugin. After this feature, an ability's persistent fields no longer include any server-related metadata. Server ↔ ability mapping, if needed by a future extension, lives outside this plugin's data model.
- **Form Extension Surface**: The five named hooks (`acrossai_abilities.form.extra_sections`, `acrossai_abilities.form.draft_changed`, `acrossai_abilities.form.save_payload`, `acrossai_abilities_form_settings_registered`, `acrossai_abilities_admin_localize_data`) collectively comprise the public contract that downstream plugins build against. They are versioned and stable.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A repository-wide search for `mcp_servers`, `mcpServers`, `MAX_MCP_SERVERS`, `MAX_SERVER_ID_LENGTH`, `sanitize_mcp_servers`, `mcpAdapterAvailable`, `handleServerToggle`, or `handleAllServersToggle` across `includes/`, `src/`, and `tests/` returns zero results.
- **SC-002**: A site administrator opens the ability edit page on a clean install and confirms no "Allowed Servers" UI is present, with zero JavaScript console errors and zero PHP notices in the debug log.
- **SC-003**: An example MU plugin subscribing to all five new hooks demonstrates each hook firing as specified (custom block renders, draft observations stream, save payload mutation reaches the request, PHP action fires after enqueue, localize-data additions appear in the JS data object) — verified via browser DevTools.
- **SC-004**: The same MU plugin deactivated produces an admin experience indistinguishable from the same plugin never having existed — same markup, same network requests, same JS data, zero errors.
- **SC-005**: A REST client that continues to send `mcp_servers` in ability requests receives 2xx responses with no `mcp_servers` field in the response body and no validation errors.
- **SC-006**: The five new hook names appear unchanged in the shipping release; any future renaming proposal triggers the deprecation cycle described in FR-010 rather than a silent rename.

## Assumptions

- The `acrossai-mcp-manager` plugin does not yet exist. The extension surface is being added in anticipation of that future plugin, and the abilities plugin must function correctly with zero subscribers.
- Existing `mcp_servers` row values have no production importance worth preserving — the prior Allowed Servers feature shipped recently and was a UI-only filter never wired to enforcement.
- The plugin already depends on `@wordpress/hooks` transitively via `@wordpress/scripts`, so no new package needs to be added to support the React-side hooks.
- The plugin has not been launched publicly yet; no upgrade migration is shipped. Dev installs with stale `mcp_servers` data are handled by manually dropping the abilities table and reactivating the plugin (fresh install then creates the table without the column).
- The dot-notation JS hook naming convention (`acrossai_abilities.form.*`) established by this feature will be the project's standard for any future JS hooks; PHP hooks continue to use the existing snake_case `acrossai_abilities_*` convention.
- Manual smoke verification of the new hooks is sufficient — a test that mocks `@wordpress/hooks` would test the mock, not the contract, so it is not worth writing.

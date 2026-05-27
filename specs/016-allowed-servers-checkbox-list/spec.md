# Feature Specification: Allowed Servers Checkbox List

**Feature Branch**: `016-allowed-servers-checkbox-list`
**Created**: 2026-05-27
**Status**: Draft
**Input**: Replace the free-text mcp_servers input in AbilityForm with a visual checkbox list of registered MCP servers fetched from the wpb-mcp-servers-list REST endpoint.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure MCP Server Access for an Ability (Priority: P1)

A site administrator editing an ability in the Abilities admin UI wants to restrict which MCP servers can execute that ability. Instead of manually typing server IDs into a text field, they see a visual checkbox list of all registered MCP servers and can check/uncheck them.

**Why this priority**: The checkbox list is the entire deliverable. Without it, no other story can function. It replaces a completely manual and error-prone text input.

**Independent Test**: Can be fully tested by opening an ability in edit mode and verifying the "Allowed Servers" field shows a checkbox list populated from the REST endpoint, allowing the admin to toggle servers and save — delivering accurate, validated `mcp_servers` selection end-to-end.

**Acceptance Scenarios**:

1. **Given** an ability is open in edit mode and at least one MCP server is registered, **When** the admin views the MCP Exposure section, **Then** the "Allowed Servers" row shows a checkbox list (not a text input), with one checkbox per registered server showing the server name and ID.
2. **Given** `mcp_servers` is `null` (default), **When** the form loads, **Then** an "All servers (default)" checkbox is shown checked, and all individual server checkboxes are unchecked.
3. **Given** the admin checks one server checkbox, **When** they save the ability, **Then** `mcp_servers` is saved as an array containing that server's ID.
4. **Given** `mcp_servers` is an array of server IDs, **When** the admin unchecks the last checked server, **Then** `mcp_servers` is set to `null` (all servers / default).
5. **Given** "All servers (default)" is checked and the admin clicks it, **When** the change is processed, **Then** `mcp_servers` remains `null` (checking "all" already means null, toggling does nothing or is a no-op from the null state).

---

### User Story 2 - Handle Edge States Gracefully (Priority: P2)

An administrator opens the ability form when the MCP adapter is not active, or when no MCP servers have been registered yet. The form must not break or show an empty/broken list — it must display an informative notice.

**Why this priority**: Without proper edge-state handling, the UI would show a blank section or a JavaScript error, degrading the admin experience.

**Independent Test**: Can be tested by disabling the MCP adapter or removing all servers, then opening an ability form — the "Allowed Servers" row should show a contextual notice instead of an empty or broken list.

**Acceptance Scenarios**:

1. **Given** the MCP adapter is not active (`adapter_available: false`), **When** the form loads, **Then** a short notice is shown in place of the checkbox list, and no server checkboxes are rendered.
2. **Given** the adapter is active but no servers are registered (`servers: []`), **When** the form loads, **Then** a "No MCP servers registered yet." notice is shown in place of the list.
3. **Given** the REST endpoint call is in progress, **When** the form renders, **Then** a loading indicator (spinner or text) is shown until the response arrives.

---

### User Story 3 - Preserve Unknown Saved Server IDs (Priority: P3)

An administrator had previously saved an ability with a server ID that no longer exists in the current server registry. When they re-open the form, the previously-saved server ID must still appear as a checked item so the saved value is not silently dropped or corrupted.

**Why this priority**: Data integrity. Silent data loss on edit would be a regression — the admin must be able to see and explicitly remove stale server references rather than having them disappear automatically.

**Independent Test**: Can be tested by saving `mcp_servers: ["old-id"]`, then loading the form with a server registry that does not include `"old-id"` — the item must appear as a checked checkbox in the list.

**Acceptance Scenarios**:

1. **Given** `mcp_servers` contains `["old-id"]` and the fetched server list does not include `"old-id"`, **When** the form loads, **Then** `"old-id"` appears as a checked item in the list alongside the registered servers.
2. **Given** the admin unchecks the stale `"old-id"` entry, **When** the ability is saved, **Then** `mcp_servers` no longer contains `"old-id"`.

---

### User Story 4 - isNonDb "Plugin declares:" Hint (Priority: P4)

For non-db abilities (those registered by plugins), the "Allowed Servers" field must still show the "Plugin declares:" registry hint below the checkbox list, consistent with all other overridable fields in the form.

**Why this priority**: Consistency with the established hint pattern for all overridable fields. Lower priority because it is a display-only concern.

**Independent Test**: Can be tested by opening a non-db ability and checking that the registry hint appears beneath the server checkbox list, showing the value from `savedAbility._registry?.mcp_servers`.

**Acceptance Scenarios**:

1. **Given** a non-db ability with a registry-declared `mcp_servers` value is open, **When** the admin views the MCP Exposure section, **Then** a "Plugin declares: [value]" hint appears below the checkbox list.
2. **Given** a non-db ability with no registry-declared `mcp_servers`, **When** the form loads, **Then** the hint shows "Plugin declares: not set".

---

### Edge Cases

- What happens when the REST endpoint is unreachable or returns an error? An error notice is shown AND any currently-saved server IDs are rendered as checked stale items — no data loss even when the endpoint is down.
- What happens when `mcp_servers` is an empty array `[]`? It must be collapsed to `null` — `[]` is never a valid saved value.
- What happens when the user toggles the "All servers (default)" checkbox while individual servers are also checked? Checking "all" should collapse to `null` and uncheck all individual servers.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST expose registered MCP servers at `GET /wp-json/wpb-mcp-servers-list/v1/servers` by registering the `RestEndpoint` class at `rest_api_init` priority 20 in `includes/Main.php`.
- **FR-002**: The "Allowed Servers" field in AbilityForm MUST be a checkbox list populated from the REST endpoint, replacing the current free-text input.
- **FR-003**: System MUST display an "All servers (default)" checkbox that is checked when `mcp_servers` is `null`; checking it MUST set `mcp_servers` to `null`.
- **FR-004**: Each registered server MUST be shown with its `name` as the label and `id` as sub-text.
- **FR-005**: Toggling a server checkbox MUST add or remove that server's ID from the `mcp_servers` array.
- **FR-006**: Removing the last selected server MUST collapse `mcp_servers` to `null` (not `[]`).
- **FR-007**: System MUST show a loading state while the server list is being fetched.
- **FR-008**: System MUST show a notice (no list) when `adapter_available` is `false`.
- **FR-009**: System MUST show a "No MCP servers registered yet." notice when the adapter is available but `servers` is empty.
- **FR-010**: System MUST render stale server IDs (present in `mcp_servers` but absent from the fetched list) as checked items with the raw ID as the label and "(not registered)" as sub-text, to prevent silent data loss.
- **FR-011**: The `isNonDb` "Plugin declares:" hint MUST remain below the checkbox list, reading from `savedAbility._registry?.mcp_servers`.
- **FR-012**: The `mcp_servers` data contract MUST NOT change — `null` = all servers, array = specific servers.
- **FR-013**: When the REST endpoint call fails (any error status), System MUST show an error notice ("Could not load server list. Please reload.") AND render any currently-saved server IDs from `draftAbility.mcp_servers` as checked stale items so no saved data is lost.
- **FR-014**: The "Allowed Servers" field MUST be shown at all times in the MCP Exposure section, regardless of the current `show_in_mcp` value.

### Key Entities

- **MCP Server**: Represents a registered MCP server. Has `id` (string, unique identifier) and `name` (string, human-readable label).
- **Server List Response**: The REST response shape `{ adapter_available: boolean, servers: Array<{ id, name, ... }> }`.
- **Ability Override**: The `mcp_servers` field in the overridable ability fields — `null` means all servers, array means specific servers.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can select allowed MCP servers for an ability using checkboxes without typing any server IDs manually.
- **SC-002**: All four edge states (loading, adapter unavailable, no servers, REST error) are communicated to the administrator with a visible notice — no blank or broken UI.
- **SC-003**: Saving an ability with specific servers checked results in `mcp_servers` containing only the selected IDs; saving with "All servers (default)" results in `mcp_servers: null`.
- **SC-004**: A previously-saved server ID that no longer exists in the registry is always visible as a checked item — zero cases of silent data loss on edit.
- **SC-005**: All quality gates pass with zero errors: PHPStan level 8, PHPCS, ESLint, and webpack build.

## Clarifications

### Session 2026-05-27

- Q: What should the UI show when the REST call itself fails (network/server error)? → A: Show an error notice AND render any currently-saved server IDs as checked stale items — data is never silently lost.
- Q: How should a stale (removed/unknown) server ID be displayed in the list? → A: Raw ID as the label with "(not registered)" as sub-text.
- Q: Should "Allowed Servers" be hidden or disabled when show_in_mcp is false/null? → A: Always shown — visibility is not conditional on show_in_mcp.

## Assumptions

- The `wpboilerplate/wpb-mcp-servers-list` Composer package is already installed and its `McpServersList::collect()` is already wired in `includes/Main.php`.
- The `RestEndpoint::register()` method is a static method that can be registered with WordPress `add_action` using the class string as the callable.
- `apiFetch`, `useState`, and `useEffect` are already imported in `AbilityForm.jsx` — no new JavaScript dependencies are required.
- The REST endpoint is accessible to users with `manage_options` capability via the standard WordPress REST nonce.
- `mcp_servers: []` (empty array) is never a valid persisted value — it must always be collapsed to `null`.
- The existing `prepare_fields_for_write()` pipeline handles `null` for `mcp_servers` correctly — no PHP changes to the write path are needed.
- Mobile/responsive design of the checkbox list is out of scope for this feature; the existing admin panel breakpoints apply.

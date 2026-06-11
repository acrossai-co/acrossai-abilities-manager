# Feature Specification: Post-031 Hotfixes

**Feature Branch**: `032-post-031-hotfixes`
**Created**: 2026-06-11
**Status**: Complete
**Input**: Three bugs surfaced after Feature 031 shipped: (A) PHP Fatal errors in
`AcrossAI_Ability_Logs_Query` due to BerlinDB parent contract violations; (B) non-admin users
could see admin-only abilities in MCP tools when Pass as Tool was enabled; (C) Library submenu
appeared fourth instead of second in the admin menu.

---

## Clarifications

### Session 2026-06-11

- Q: Should these fixes ship as a single PR or separate PRs? → A: Single PR (`032-post-031-hotfixes`) — all three are small, independent, and low-risk.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Plugin loads without PHP Fatal errors (Priority: P1)

An administrator activates or reloads the plugin. No PHP Fatal errors appear in the error
log. The Logger module initialises correctly and the Execution Logs submenu is accessible.

**Why this priority**: Fatal errors on class load bring down the entire site. Two separate
BerlinDB parent-contract violations in `AcrossAI_Ability_Logs_Query` caused immediate fatals.

**Independent Test**: Check `wp-content/debug.log` after activation — zero `PHP Fatal error`
lines mentioning `AcrossAI_Ability_Logs_Query`.

**Acceptance Scenarios**:

1. **Given** the plugin is active, **When** WordPress loads, **Then** no PHP Fatal errors related to `AcrossAI_Ability_Logs_Query` appear in the error log.
2. **Given** the plugin is active, **When** an admin navigates to Abilities Manager → Logs, **Then** the page renders with execution log data.

---

### User Story 2 — Pass as Tool respects ability permission gates (Priority: P1)

An administrator enables Pass as Tool on an admin-only ability (e.g. one with
`current_user_can('manage_options')` as its `permission_callback`). A non-admin user
connecting via MCP does **not** see that ability in the tools list — the ability's own
permission gate is enforced at injection time.

**Why this priority**: Security regression — non-admin users were seeing and potentially
executing abilities they should never have access to.

**Independent Test**: Log in as a subscriber. Check the MCP tools list — admin-only abilities
must not appear, regardless of whether an explicit AC rule is configured.

**Acceptance Scenarios**:

1. **Given** Pass as Tool is On for an ability with `manage_options` permission_callback, **When** a non-admin user queries MCP tools, **Then** the ability is absent from the list.
2. **Given** Pass as Tool is On and an AC rule exists for the ability, **When** a user without the required access queries MCP tools, **Then** the ability is still absent.
3. **Given** Pass as Tool is On for an ability with no permission restrictions, **When** any user queries MCP tools, **Then** the ability appears normally.

---

### User Story 3 — Library appears second in the admin menu (Priority: P2)

An administrator opens the Abilities Manager submenu. Library is the second item
(immediately after "Abilities Manager"), making it the primary discovery surface as intended.

**Why this priority**: UX correctness — Library was the most important submenu but appeared
fourth due to registration order.

**Independent Test**: Open `wp-admin → Abilities Manager`. Verify menu order:
Abilities Manager → Library → Logs → Settings → Add-ons.

**Acceptance Scenarios**:

1. **Given** the plugin is active, **When** an admin opens the Abilities Manager menu, **Then** Library is the second item after the main menu entry.

---

### Edge Cases

- Pass as Tool On + ability not registered in WordPress (e.g. add-on deactivated) — `wp_get_ability()` returns null; injection is skipped silently (pre-existing safe behavior).
- `check_permissions()` returns `WP_Error` (invalid callback) — treated as denied; ability not injected.
- BerlinDB Logger table not yet created (first activation) — `get_logs()` called with default `$operator = 'and'`; no behavioral change.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `AcrossAI_Ability_Logs_Query::get_table_name()` MUST be declared `public` to match the BerlinDB parent class visibility contract.
- **FR-002**: `AcrossAI_Ability_Logs_Query::get_logs()` MUST include `string $operator = 'and'` as a second parameter to match the BerlinDB parent class signature.
- **FR-003**: `inject_mcp_tools()` MUST call `WP_Ability::check_permissions()` for each candidate slug and skip injection when it returns `false` or `WP_Error`.
- **FR-004**: The Library submenu MUST be registered immediately after the main menu, making it the second item in the Abilities Manager submenu list.
- **FR-005**: The fix to `inject_mcp_tools()` MUST NOT break Pass as Tool behavior for abilities with no permission restrictions — those must still be injected normally.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Zero `PHP Fatal error` lines mentioning `AcrossAI_Ability_Logs_Query` in the WordPress error log after plugin activation.
- **SC-002**: Non-admin users see zero admin-only abilities in their MCP tools list when Pass as Tool is enabled and no explicit AC rule is configured.
- **SC-003**: Admin menu shows Library as the second submenu item in 100% of tested admin page loads.
- **SC-004**: PHPCS and PHPStan level 8 report zero new errors across all modified files.

---

## Assumptions

- BerlinDB parent class `Query::get_table_name()` is `public` and `Query::get_logs()` requires `(array $args = [], string $operator = 'and')` — confirmed from source.
- `WP_Ability::check_permissions()` is the correct public API for evaluating a registered ability's permission gate — confirmed from `wp-includes/abilities-api/class-wp-ability.php`.
- WordPress registers submenus in the order `add_submenu_page()` is called within the same `admin_menu` priority — registration order = display order.
- The `$operator` parameter added to `get_logs()` is not forwarded to `query()` internally — no behavioral change to the Logger query layer.

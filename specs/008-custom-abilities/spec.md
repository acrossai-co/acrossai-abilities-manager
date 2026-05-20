# Feature Specification: Custom Abilities Manager

**Feature Branch**: `008-custom-abilities`  
**Created**: 2026-05-20  
**Status**: Draft  

## Overview

Enable WordPress administrators to define new custom abilities directly through the database without requiring PHP code. This feature introduces a database-driven ability registration system with a dedicated admin interface, REST API, and integration with the WordPress Abilities API and MCP servers.

## User Scenarios & Testing

### User Story 1 - Admin Creates Custom Ability (Priority: P1)

As a WordPress admin, I want to create a new custom ability via the admin UI by specifying its slug, label, description, and basic properties.

**Why this priority**: Core functionality—admins must be able to define abilities before any other feature can operate.

**Independent Test**: Ability can be fully created, saved to database, and listed in the admin table without any dependent features.

**Acceptance Scenarios**:

1. **Given** the admin is on the Custom Abilities admin page, **When** they click "Add New Ability", **Then** a DataForm opens with all required fields (slug, label, description, category, enabled, permission type)
2. **Given** an admin has filled the form with valid data (unique slug in namespace/name format), **When** they click "Create", **Then** the ability is saved to the database and a success message appears
3. **Given** an admin creates an ability with a duplicate slug, **When** they click "Create", **Then** an error message appears indicating the slug must be unique
4. **Given** an ability has been created, **When** the admin navigates to the Custom Abilities page, **Then** the new ability appears in the DataViews table with all properties visible

---

### User Story 2 - Admin Configures Ability Behavior (Priority: P1)

As a WordPress admin, I want to specify how a custom ability is executed: no-op (disabled), filter hook callback, or remote POST callback with callback configuration.

**Why this priority**: Determines whether the ability can actually execute—foundational to the feature.

**Independent Test**: Callback configuration is saved and retrievable via REST API without requiring execution.

**Acceptance Scenarios**:

1. **Given** an admin is editing an ability, **When** they select `callback_type = "filter_hook"`, **Then** a `callback_config` JSON field appears allowing them to specify the hook name
2. **Given** an admin selects `callback_type = "wp_remote_post"`, **When** they enter a webhook URL in `callback_config`, **Then** the URL is validated and saved
3. **Given** an admin selects `callback_type = "noop"`, **When** they save, **Then** the ability is marked as non-functional but still listed

---

### User Story 3 - Admin Restricts Ability Access (Priority: P1)

As a WordPress admin, I want to control who can invoke each custom ability by setting its permission type (always allow, logged-in only, or specific capability).

**Why this priority**: Security-critical—unauthorized access must be prevented.

**Independent Test**: Permission restrictions can be configured and retrieved without executing the ability.

**Acceptance Scenarios**:

1. **Given** an admin is creating an ability, **When** they set `permission_type = "always_allow"`, **Then** the `permission_config` is optional/empty
2. **Given** an admin sets `permission_type = "capability"`, **When** they enter a capability name (e.g., "manage_options"), **Then** the capability is saved in `permission_config`
3. **Given** an ability has `permission_type = "logged_in"`, **When** a non-logged-in user attempts to call it, **Then** access is denied

---

### User Story 4 - Ability Registers in WordPress API (Priority: P1)

As a developer/integration, I want all enabled custom abilities to automatically register with the WordPress Abilities API so they can be discovered and invoked via standard mechanisms.

**Why this priority**: Enables integration with the broader WordPress abilities ecosystem—essential for adoption.

**Independent Test**: Ability can be queried via the WordPress Abilities API without manual code.

**Acceptance Scenarios**:

1. **Given** a custom ability exists in the database with `enabled = 1`, **When** WordPress loads (on `wp_abilities_api_init`), **Then** the ability is registered with `wp_register_ability()`
2. **Given** a custom ability is disabled (`enabled = 0`), **When** WordPress loads, **Then** the ability is not registered
3. **Given** the ability is registered, **When** a developer calls `wp_get_ability( 'namespace/ability-slug' )`, **Then** the ability details are returned including metadata

---

### User Story 5 - REST API CRUD Operations (Priority: P2)

As an external tool or UI, I want to manage custom abilities via REST API endpoints so I can automate ability creation and updates.

**Why this priority**: Enables programmatic management and third-party integrations.

**Independent Test**: Full CRUD operations work independently without admin UI.

**Acceptance Scenarios**:

1. **Given** a user has `manage_options` capability, **When** they POST to `/wp-json/acrossai-abilities-manager/v1/custom-abilities`, **Then** a new ability is created
2. **Given** a user lacks `manage_options`, **When** they attempt to POST, **Then** a 403 Forbidden response is returned
3. **Given** an ability exists, **When** a user with `manage_options` GETs `/wp-json/acrossai-abilities-manager/v1/custom-abilities/{id}`, **Then** the full ability object is returned
4. **Given** an ability exists, **When** a user with `manage_options` PUTs an updated property, **Then** the ability is updated and persisted

---

### User Story 6 - Admin Views All Abilities in Table (Priority: P2)

As an admin, I want to see all custom abilities in a sortable, filterable table so I can manage them efficiently.

**Why this priority**: Provides visibility and discoverability—enhances usability.

**Independent Test**: Table displays and filters without depending on edit/create operations.

**Acceptance Scenarios**:

1. **Given** multiple custom abilities exist, **When** the admin navigates to Custom Abilities, **Then** all abilities are listed in a DataViews table with columns: slug, label, category, enabled, permission_type, callback_type
2. **Given** the admin clicks the "Enabled" column header, **When** the table re-renders, **Then** abilities are sorted by enabled status
3. **Given** the admin enters a search term, **When** the search is applied, **Then** only abilities matching the slug or label are displayed

---

### User Story 7 - MCP Server Integration (Priority: P3)

As an admin, I want to expose custom abilities to MCP servers so they can be invoked through MCP clients.

**Why this priority**: Optional integration—extends capabilities but not required for MVP.

**Independent Test**: MCP metadata can be configured without requiring an MCP server.

**Acceptance Scenarios**:

1. **Given** an ability is marked `show_in_mcp = 1`, **When** an MCP server queries the abilities, **Then** the ability is included with its `mcp_type` (tool, resource, prompt) and associated servers list
2. **Given** an ability is marked `show_in_mcp = 0`, **When** an MCP server queries, **Then** the ability is not listed

---

### Edge Cases

- What happens if a custom ability's slug conflicts with a built-in ability slug?
- How does the system handle a callback_config that points to a non-existent webhook URL?
- If an ability is marked readonly=1, can it still be deleted by an admin, or is deletion prevented?
- What happens if the database table is missing during WordPress initialization?
- Can an ability's permission_config reference a non-existent capability, and how is that validated?

## Requirements

### Functional Requirements

1. **Database Schema** — Create table `{prefix}acrossai_custom_abilities` with:
   - `id` (bigint primary key)
   - `ability_slug` (varchar unique, pattern: namespace/name)
   - `label`, `description`, `category` (text fields)
   - `enabled` (bool), `callback_type` (enum: noop|filter_hook|wp_remote_post), `callback_config` (JSON)
   - `permission_type` (enum: always_allow|logged_in|capability), `permission_config` (JSON)
   - `input_schema`, `output_schema` (JSON)
   - `show_in_rest`, `show_in_mcp` (bool), `mcp_type` (enum), `mcp_servers` (JSON array)
   - `readonly`, `destructive`, `idempotent` (nullable tinyint: NULL=inherit, 0=false, 1=true)
   - `created_at`, `updated_at`, `created_by`, `updated_by` (timestamps/user IDs)

2. **BerlinDB Implementation** — Implement 4-file pattern:
   - `Schema` — table definition
   - `Row` — row object with properties
   - `Query` — query builder (filtering, pagination)
   - `Table` — table registration and manager

3. **Abilities API Registration** — On `wp_abilities_api_init`, fetch all enabled abilities from database and call `wp_register_ability()` for each

4. **REST API** — Routes under `/wp-json/acrossai-abilities-manager/v1/custom-abilities`:
   - `GET /` — list abilities with pagination/filtering
   - `POST /` — create new ability
   - `GET /{id}` — retrieve single ability
   - `PUT /{id}` — update ability
   - `DELETE /{id}` — delete ability
   - All endpoints require `manage_options` capability

5. **Admin UI** — New "Custom Abilities" submenu under Abilities Manager:
   - DataViews table listing all abilities
   - DataForm for create/edit modal with all fields
   - Bulk actions (enable/disable/delete)
   - Singleton menu class pattern

6. **Namespace & Code Organization**:
   - `AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability` (namespace)
   - Schema: `Custom_Ability_Schema`
   - Row: `Custom_Ability_Row`
   - Query: `Custom_Ability_Query`
   - Table: `Custom_Ability_Table`
   - REST Controller: `Custom_Ability_REST_Controller` or decomposed per pattern

### Non-Functional Requirements

- **Security**: Sanitize input, escape output, validate capability checks on all REST endpoints, prepared statements for all DB queries
- **Performance**: Index on `ability_slug`, `enabled` for fast lookups during registration
- **Multisite**: Ensure table is per-site (set `$global = false` in Table registration)
- **Backward Compatibility**: No breaking changes to existing Abilities API or admin UI

## Success Criteria

1. **Ability Creation** — Admins can create custom abilities via UI/API and abilities appear immediately in admin table
2. **Database Persistence** — All ability properties are correctly stored and retrievable from database
3. **Abilities API Registration** — All enabled abilities register with WordPress Abilities API on init hook
4. **REST API Functionality** — CRUD operations work correctly with proper permission checks and data validation
5. **Admin UI Usability** — DataViews table and DataForm render correctly with intuitive workflows
6. **Security Validation** — All input is sanitized, output escaped, capabilities checked; PHPStan level 8 and PHPCS pass
7. **Test Coverage** — Unit tests cover database operations, API registration, REST endpoints, and permission checks
8. **Performance** — Ability registration completes in under 100ms for up to 500 custom abilities

## Key Entities

### Custom Ability

- **Attributes**: slug, label, description, category, enabled, callback_type, callback_config, permission_type, permission_config, input_schema, output_schema, show_in_rest, show_in_mcp, mcp_type, mcp_servers, readonly, destructive, idempotent, created_at, updated_at, created_by, updated_by
- **Primary Behavior**: Defined and stored in database; registered to WordPress Abilities API; executed via callback mechanism
- **Relationships**: Belongs to plugin; can be exposed to MCP servers; has REST endpoints

## Constraints & Dependencies

- **Dependency**: WordPress Abilities API must be registered before custom abilities can hook into it
- **Constraint**: Ability slugs must follow `namespace/name` pattern for consistency
- **Constraint**: `callback_type` determines execution mechanism; invalid configs should gracefully degrade to noop
- **Constraint**: Multisite support requires per-site table (no shared table)

## Assumptions

- WordPress 6.9+ is installed with Abilities API support
- `@wordpress/dataviews` v14.2+ is available for admin UI (exports DataForm)
- Admins have basic understanding of ability slugs and callback concepts
- Database table creation uses BerlinDB standard (basedon existing patterns in plugin)
- Permission checks use standard WordPress capabilities (e.g., `manage_options`)

## Design Notes

- Follow existing plugin patterns: singleton classes, Loader hook registration in Main.php, asset enqueue in Admin\Main
- Use DataViews and DataForm consistently with Constitution principle III
- Ensure REST controller follows modularization pattern (thin orchestrator + sub-controllers if needed)
- Validate slug uniqueness during create/update
- Consider cached ability registration for performance (invalidate on ability update)

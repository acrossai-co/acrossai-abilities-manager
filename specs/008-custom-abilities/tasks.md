# Tasks: Custom Abilities Module (008)

**Feature**: Custom Abilities Module  
**Branch**: `008-custom-abilities`  
**Status**: Phase 2 Implementation Tasks  
**Input**: plan.md, spec.md, memory-synthesis.md, security-constraints.md, CONSTITUTION.md  

---

## Task Organization & Dependencies

Tasks are organized by technical layer to enable parallel execution within each layer while respecting cross-layer dependencies:

- **Phase 1: Setup** — Project initialization (prerequisites for all tasks)
- **Phase 2: Database Layer** — BerlinDB implementation (T001-T003, parallelizable)
- **Phase 3: REST API Layer** — Controllers (T004-T006, depend on Phase 2)
- **Phase 4: Admin UI Layer** — DataForm & DataViews (T007-T009, depend on Phase 3)
- **Phase 5: Business Logic** — Processor & Utilities (T010-T013, depend on Phase 2, parallel with Phase 4)
- **Phase 6: Integration & Validation** — Quality gates (T014-T015, depend on all above)

**MVP Scope Recommendation**: Complete T001-T012 for a fully functional feature. T013-T015 are quality/polish tasks.

---

## Phase 1: Setup

- [ ] T001 [P] Initialize plugin boilerplate for Custom Abilities module

  **Description**: Create module directory structure and base files per Architecture standards (Constitution §I, Memory DEC-NAMESPACE-CONVENTION).
  
  **Acceptance Criteria**:
  - Directory tree exists: `includes/Modules/Custom_Ability/`, `includes/Utilities/`, `admin/Partials/`
  - Base namespace files created: `AcrossAI_Custom_Ability_Main.php` (singleton placeholder), `AcrossAI_Custom_Ability_Processor.php`, `AcrossAI_Custom_Ability_Rest_Controller.php`
  - Utilities stubs created: `AcrossAI_Custom_Ability_Validator.php`, `AcrossAI_Custom_Ability_Sanitizer.php`
  - All files use `AcrossAI_*` naming prefix and underscore namespace convention (Memory DEC-NAMESPACE-CONVENTION)
  - Entry point verified in `includes/Main.php` (wire Custom Ability processor at init)
  - Run `composer dump-autoload` after all files created — verify no errors before proceeding to T002
  - Run `./vendor/bin/phpstan analyse includes/Modules/Custom_Ability includes/Utilities/AcrossAI_Custom_Ability_Sanitizer.php includes/Utilities/AcrossAI_Custom_Ability_Validator.php --level=8` — zero errors before proceeding to T002
  
  **Files to Create/Modify**:
  - `includes/Modules/Custom_Ability/AcrossAI_Custom_Ability_Main.php` (new)
  - `includes/Modules/Custom_Ability/AcrossAI_Custom_Ability_Processor.php` (new)
  - `includes/Modules/Custom_Ability/AcrossAI_Custom_Ability_Rest_Controller.php` (new)
  - `includes/Utilities/AcrossAI_Custom_Ability_Validator.php` (new)
  - `includes/Utilities/AcrossAI_Custom_Ability_Sanitizer.php` (new)
  - `includes/Main.php` (modify: add hook wiring for Custom Ability processor)
  
  **Dependencies**: None (first task)
  **Complexity**: S
  **References**: CONSTITUTION.md §I, Memory DEC-NAMESPACE-CONVENTION, plan.md Project Structure

---

## Phase 2: Database Layer (BerlinDB 4-File Pattern)

- [ ] T002 [P] Implement BerlinDB Schema and Row classes for custom abilities

  **Description**: Create `AcrossAI_Custom_Ability_Schema` and `AcrossAI_Custom_Ability_Row` classes following BerlinDB 4-file pattern (plan.md, Memory AC-REST-SPLIT). Define 20-column schema with JSON column support for callback_config, permission_config, input_schema, output_schema (Watchpoint 1).
  
  **Acceptance Criteria**:
  - Schema class extends `BerlinDB\Database\Schema`; defines all 20 columns with correct types (id, ability_slug, label, description, category, enabled, callback_type, callback_config, permission_type, permission_config, input_schema, output_schema, show_in_rest, show_in_mcp, mcp_type, mcp_servers, readonly, destructive, idempotent, created_at, updated_at)
  - `ability_slug` column marked UNIQUE, max 255 chars (FR-006)
  - JSON columns correctly typed as `JSON` or `LONGTEXT` (verify BerlinDB support; Watchpoint 1)
  - Row class extends `BerlinDB\Database\Row`; applies `json_decode()` on construct, `json_encode()` on save for JSON fields
  - Tri-state flags (readonly, destructive, idempotent) support NULL/0/1 values
  - Table name: `{prefix}acrossai_custom_abilities` (multisite per-site prefix, $global = false)
  - PHPStan L8: zero errors on both classes
  
  **Files to Create/Modify**:
  - `includes/Modules/Custom_Ability/Database/AcrossAI_Custom_Ability_Schema.php` (new)
  - `includes/Modules/Custom_Ability/Database/AcrossAI_Custom_Ability_Row.php` (new)
  
  **Dependencies**: T001 (module structure exists)
  **Complexity**: M
  **References**: plan.md Data Model, BerlinDB pattern, Watchpoint 1, FR-001

---

- [ ] T003 [P] Implement BerlinDB Query and Table classes

  **Description**: Create `AcrossAI_Custom_Ability_Query` and `AcrossAI_Custom_Ability_Table` classes. Query layer implements filtering (by_slug, enabled_only, by_category, search, pagination) as single source of truth (Memory AC-QUERY-LAYER-FILTERING). Table class manages CRUD operations with $global = false for multisite isolation (Memory SEC-03, FR-001).
  
  **Acceptance Criteria**:
  - Query class extends `BerlinDB\Database\Query`; implements fluent methods: `by_slug()`, `enabled_only()`, `by_category()`, `search()`, `with_pagination()`, `order_by()` 
  - Query filtering respects protected namespace prefixes via `apply_filters('acrossai_protected_ability_prefixes', ...)` (Memory DEC-PROTECTED-SLUGS-PATTERN)
  - Table class extends `BerlinDB\Database\Table`; properties: `$name`, `$schema`, `$row_class`, `$global = false`
  - CRUD methods: `insert()`, `update()`, `delete()`, `get()`, `query()` — delegates to BerlinDB
  - Query layer is single source of truth for list filtering (Memory AC-QUERY-LAYER-FILTERING); no filtering in REST controller
  - PHPStan L8: zero errors on both classes
  - Unit test stub: verify query filtering for protected prefixes
  
  **Files to Create/Modify**:
  - `includes/Modules/Custom_Ability/Database/AcrossAI_Custom_Ability_Query.php` (new)
  - `includes/Modules/Custom_Ability/Database/AcrossAI_Custom_Ability_Table.php` (new)
  
  **Dependencies**: T002 (Schema/Row exist)
  **Complexity**: M
  **References**: plan.md REST API Architecture, Memory AC-QUERY-LAYER-FILTERING, DEC-PROTECTED-SLUGS-PATTERN, SEC-03

---

- [ ] T004 Create unit tests for BerlinDB database layer

  **Description**: Implement PHPUnit integration tests for database layer: schema creation, row persistence, JSON column casting, query filtering, and multisite isolation (T002-T003).
  
  **Acceptance Criteria**:
  - Test file: `tests/phpunit/integration/test-custom-ability-database.php`
  - Tests cover: table creation, row insert/update/delete, JSON field casting (valid/invalid/null JSON), UNIQUE constraint on ability_slug, tri-state flag values, multisite isolation ($global = false)
  - Test for protected namespace filtering in Query layer
  - All tests pass; PHPStan L8 on test file
  - 100% code coverage for Schema, Row, Query, Table classes (branches + lines)
  
  **Files to Create/Modify**:
  - `tests/phpunit/integration/test-custom-ability-database.php` (new)
  
  **Dependencies**: T002, T003 (database classes implemented)
  **Complexity**: M
  **References**: CONSTITUTION.md §VII, plan.md Testing

---

## Phase 3: REST API Layer

- [ ] T005 [P] Implement REST orchestrator and Read controller

  **Description**: Create orchestrator controller `AcrossAI_Custom_Ability_Rest_Controller` (singleton) and Read sub-controller. Orchestrator handles route registration, shared `check_permission()` callback, delegation. Read controller implements GET list (with search, filter, pagination, Memory AC-QUERY-LAYER-FILTERING) and GET /:id (FR-005, plan.md REST API Architecture).
  
  **Acceptance Criteria**:
  - Orchestrator is singleton (Memory SEC-PLAN-002); implements `register_routes()` delegating to each sub-controller
  - `check_permission()` enforces `manage_options` capability (FR-012, Constitution §IV)
  - Read controller implements: `GET /wp-json/acrossai-abilities-manager/v1/custom-abilities` (list with pagination, search, filter by category/enabled/show_in_mcp) and `GET /wp-json/acrossai-abilities-manager/v1/custom-abilities/{id}` (single ability)
  - List endpoint returns 20-field response per ability; respects query layer filtering (Memory AC-QUERY-LAYER-FILTERING)
  - Query parameters: search, order, orderby, per_page, page, category, enabled, show_in_mcp (plan.md REST API)
  - Response includes pagination headers (X-WP-Total, X-WP-TotalPages)
  - All output JSON-encoded via `wp_json_encode()` (Constitution §IV)
  - HTTP status codes: 200 (success), 400 (invalid params), 403 (permission denied), 500 (error)
  - PHPStan L8: zero errors
  
  **Files to Create/Modify**:
  - `includes/Modules/Custom_Ability/Rest/AcrossAI_Custom_Ability_Rest_Controller.php` (new, orchestrator)
  - `includes/Modules/Custom_Ability/Rest/AcrossAI_Custom_Ability_Read_Controller.php` (new)
  - `includes/Main.php` (modify: wire orchestrator via Loader at rest_api_init)
  
  **Dependencies**: T003 (Query/Table exist for data retrieval)
  **Complexity**: L
  **References**: plan.md REST API Architecture, Memory AC-QUERY-LAYER-FILTERING, CONSTITUTION.md §IV, FR-005

---

- [ ] T006 [P] Implement REST Write controller and MCP controller

  **Description**: Create Write sub-controller (POST create, POST /:id update, DELETE /:id) and MCP sub-controller (GET /mcp/tools, /mcp/resources, /mcp/prompts). Write controller includes full validation pipeline (Memory BUG-PARTIAL-HOOK-FIELDS, security-constraints.md Finding 2-3). MCP controller filters abilities by show_in_mcp, mcp_type, mcp_servers (FR-010, plan.md MCP Architecture).
  
  **Acceptance Criteria**:
  - Write controller: POST creates ability with full validation pipeline (sanitize → validate → check collision → save → fetch complete row → fire hooks per Memory BUG-PARTIAL-HOOK-FIELDS)
  - Before-save hook fires with sanitized $fields (Memory SEC-02)
  - After-save hook fires with complete 20-field row (BUG-PARTIAL-HOOK-FIELDS)
  - POST /:id updates ability (same validation + hook pattern)
  - DELETE /:id removes ability and fires delete hook
  - All boolean fields cast to int before BerlinDB save (Memory SEC-02)
  - Response uses `AcrossAI_Custom_Ability_Formatter::format_ability_for_response()` utility (plan.md Formatter)
  - MCP controller: GET /mcp/tools, /mcp/resources, /mcp/prompts — filters show_in_mcp=true, mcp_type match, current MCP server in mcp_servers
  - MCP server discovery via `wpboilerplate/wpb-mcp-servers-list` package (CONSTITUTION.md §V Integration Resilience)
  - HTTP status codes: 201 (created), 200 (updated), 204 (deleted), 400 (validation), 403 (permission), 409 (conflict/duplicate), 500 (error)
  - PHPStan L8: zero errors
  
  **Files to Create/Modify**:
  - `includes/Modules/Custom_Ability/Rest/AcrossAI_Custom_Ability_Write_Controller.php` (new)
  - `includes/Modules/Custom_Ability/Rest/AcrossAI_Custom_Ability_Mcp_Controller.php` (new)
  - `includes/Modules/Custom_Ability/Database/AcrossAI_Custom_Ability_Table.php` (modify: add hook firing in insert/update/delete)
  - `includes/Utilities/AcrossAI_Custom_Ability_Formatter.php` (modify: implement format_ability_for_response, format_for_mcp)
  
  **Dependencies**: T005 (orchestrator + Read controller exist)
  **Complexity**: L
  **References**: plan.md REST API Architecture, Memory BUG-PARTIAL-HOOK-FIELDS, SEC-02, security-constraints.md Findings 2-3, FR-005, FR-010

---

- [ ] T007 Create unit tests for REST API layer

  **Description**: Implement PHPUnit integration tests for REST controllers: read list/single, write create/update/delete, MCP query, permission checks, validation errors.
  
  **Acceptance Criteria**:
  - Test file: `tests/phpunit/integration/test-custom-ability-rest-crud.php`
  - Tests cover: GET list with pagination/search/filter, GET /:id, POST create (valid/invalid/duplicate), POST /:id update, DELETE /:id, permission denial for non-admin, MCP filtering
  - Tests verify: sanitization pipeline, before/after-save hooks fired with correct data, status codes, JSON response structure
  - All tests pass; PHPStan L8 on test file
  - 100% code coverage for REST controllers (branches + lines)
  
  **Files to Create/Modify**:
  - `tests/phpunit/integration/test-custom-ability-rest-crud.php` (new)
  
  **Dependencies**: T005, T006 (REST controllers implemented)
  **Complexity**: M
  **References**: CONSTITUTION.md §VII, plan.md Testing

---

## Phase 4: Admin UI Layer

- [x] T008 [P] Implement DataForm component for ability creation/editing

  **Description**: Create React component `AbilityForm.js` wrapping `@wordpress/dataviews` DataForm API. Include all 20 form fields with conditional visibility (callback_type/permission_type/show_in_mcp specific fields), real-time validation (slug uniqueness), error display (plan.md Admin UI Architecture, Constitution §III).
  
  **Acceptance Criteria**:
  - Component file: `src/js/admin/custom-abilities/components/AbilityForm.js`
  - Implements all 20 fields per plan.md: ability_slug, label, description, category, enabled, callback_type, callback_config (conditional), permission_type, permission_config (conditional), input_schema, output_schema, show_in_rest, show_in_mcp, mcp_type (conditional), mcp_servers (conditional), readonly, destructive, idempotent
  - Slug validation: pattern check, uniqueness check via REST, max 255 chars (FR-006)
  - Schema validation: JSON syntax check for input_schema and output_schema fields
  - Conditional fields: show/hide based on callback_type, permission_type, show_in_mcp selections (plan.md Admin UI)
  - Create mode: empty form, submit = POST to /custom-abilities
  - Edit mode: pre-populate from ability ID, submit = POST to /custom-abilities/{id}
  - Loading state on submit, disable fields until complete
  - Error display: inline per-field + top-level error notice
  - Success: redirect to list, show success toast
  - ESLint: zero errors
  - WCAG 2.1 A compliance (inherited from DataForm component)
  
  **Files to Create/Modify**:
  - `src/js/admin/custom-abilities/components/AbilityForm.js` (new)
  - `src/js/admin/custom-abilities/api/useCustomAbilities.js` (new, React hook for CRUD via REST)
  - `webpack.config.js` (modify: add two entries matching existing naming convention: `'js/custom-abilities': path.resolve( process.cwd(), 'src/js/admin/custom-abilities', 'index.js' )` and `'css/custom-abilities': path.resolve( process.cwd(), 'src/scss/admin/custom-abilities', 'index.scss' )` — see existing `'js/logger'`/`'css/logger'` entries as precedent)
  - `src/scss/admin/custom-abilities/form.scss` (new, form styles)
  
  **Dependencies**: T006 (REST Write controller exist)
  **Complexity**: L
  **References**: plan.md Admin UI Architecture, Constitution §III (User-Centric Design), @wordpress/dataviews documentation

---

- [ ] T009 [P] Implement DataViews component for ability listing/management

  **Description**: Create React component `AbilitiesList.js` wrapping `@wordpress/dataviews` DataViews API. Display all custom abilities with 9 columns, search, filtering, pagination, bulk actions (enable/disable/delete), contextual actions (edit/delete/duplicate) (plan.md Admin UI Architecture, Constitution §III).
  
  **Acceptance Criteria**:
  - Component file: `src/js/admin/custom-abilities/components/AbilitiesList.js`
  - Columns: ability_slug, label, category, enabled, callback_type, permission_type, show_in_mcp, created_at, updated_at (per plan.md)
  - Column features: searchable (slug/label/description global search), sortable, filterable (category, enabled, callback_type, permission_type, show_in_mcp)
  - Pagination: 20/50/100 per page
  - Contextual actions: Edit (open in form modal), Toggle Enable/Disable, Delete (with confirmation), Duplicate
  - Bulk actions: Enable Selected, Disable Selected, Delete Selected (with confirmation)
  - Empty state: "No custom abilities yet. Create your first ability." with "Add New Ability" button
  - Loading state while fetching data
  - Error handling: display user-friendly error message if fetch fails
  - ESLint: zero errors
  - WCAG 2.1 A compliance (inherited from DataViews component)
  
  **Files to Create/Modify**:
  - `src/js/admin/custom-abilities/components/AbilitiesList.js` (new)
  - `src/js/admin/custom-abilities/index.js` (new, main entry point rendering both AbilityForm + AbilitiesList)
  - `src/scss/admin/custom-abilities/list.scss` (new, list styles)
  - `src/scss/admin/custom-abilities/index.scss` (new, main stylesheet combining form + list)
  
  **Dependencies**: T006 (REST Read controller exist), T008 (AbilityForm component)
  **Complexity**: L
  **References**: plan.md Admin UI Architecture, Constitution §III, @wordpress/dataviews documentation

---

- [x] T010 [P] Create admin menu, page, and asset enqueue

  **Description**: Implement admin menu registration (`AcrossAI_Custom_Ability_Menu.php`), page renderer (`AcrossAI_Custom_Ability_Page.php`), and asset enqueue (`AcrossAI_Custom_Ability_Assets.php`). Register submenu under "Abilities Manager", render DataForm + DataViews containers, enqueue custom-abilities JS/CSS bundle.
  
  **Acceptance Criteria**:
  - Menu class: register submenu `acrossai-custom-abilities` under Abilities Manager with `manage_options` permission (FR-002) — follow `admin/Partials/LogsMenu.php` singleton pattern exactly
  - Menu class wired in `includes/Main.php` via `$this->loader->add_action( 'admin_menu', $menu, 'register_submenu' )` — same pattern as LogsMenu wiring in includes/Main.php
  - Asset class constructor: load manifest via `$this->asset_file = include \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/custom-abilities.asset.php';` — reference admin/Main.php lines 98–106 as precedent. Never hardcode dependency array or version string.
  - Asset class: enqueue `acrossai-abilities-custom` styles and scripts **only** on `acrossai-custom-abilities` page — guard via `$hook_suffix` (see `is_custom_abilities_page()` pattern in admin/Main.php)
  - Pass data to JS via `wp_add_inline_script()` with `wp_json_encode()` — NOT `wp_localize_script()` (deprecated). Pattern: `wp_add_inline_script( 'acrossai-abilities-custom', 'window.acrossaiCustomAbilities = ' . wp_json_encode( [ 'nonce' => wp_create_nonce( 'wp_rest' ), 'rest_url' => rest_url( 'acrossai-abilities-manager/v1' ), 'current_user_id' => get_current_user_id() ] ) . ';', 'before' )` — reference admin/Main.php lines 164–174 as precedent
  - Page render callback outputs: `<div class="wrap"><div id="acrossai-custom-abilities-root"></div></div>`
  - All output escaped via proper escaping functions (Constitution §IV)
  - PHPCS: zero errors
  - PHPStan L8: zero errors
  
  **Files to Create/Modify**:
  - `admin/Partials/AcrossAI_Custom_Ability_Menu.php` (new)
  - `admin/Partials/AcrossAI_Custom_Ability_Page.php` (new)
  - `admin/Partials/AcrossAI_Custom_Ability_Assets.php` (new)
  - `includes/Main.php` (modify: wire menu via Loader)
  
  **Dependencies**: T008, T009 (React components exist)
  **Complexity**: M
  **References**: plan.md Admin Menu & Assets, Constitution §IV, FR-002

---

- [ ] T011 Create Jest tests for React admin UI components

  **Description**: Implement Jest unit tests for AbilityForm and AbilitiesList React components: rendering, user interactions, form validation, data fetching, error handling.
  
  **Acceptance Criteria**:
  - Test files: `tests/jest/admin/custom-abilities/AbilityForm.test.js`, `AbilitiesList.test.js`
  - Tests cover: component rendering, form field presence, conditional field visibility, validation error display, form submission, API calls, error states, loading states
  - Tests verify: accessibility attributes (ARIA labels, roles), button/link behavior
  - All tests pass
  - ESLint: zero errors on test files
  
  **Files to Create/Modify**:
  - `tests/jest/admin/custom-abilities/AbilityForm.test.js` (new)
  - `tests/jest/admin/custom-abilities/AbilitiesList.test.js` (new)
  
  **Dependencies**: T008, T009 (React components implemented)
  **Complexity**: M
  **References**: CONSTITUTION.md §VII, plan.md Testing

---

## Phase 5: Business Logic & Integration

- [x] T012 [P] Implement Validator and Sanitizer utility classes

  **Description**: Create `AcrossAI_Custom_Ability_Validator` and `AcrossAI_Custom_Ability_Sanitizer` static utility classes (Memory DEC-UTILITY-STATIC-ONLY). Validator implements: validate_slug (pattern, uniqueness, length), validate_label, validate_category, validate_callback_config (type-specific), validate_permission_config (type-specific), validate_schema (JSON). Sanitizer implements: sanitize_* methods for all fields, cast_to_db_format (bool→int, json→string). Address security-constraints.md Finding 2-3 (callback config validation, JSON sanitization).
  
  **Acceptance Criteria**:
  - Validator class: all static methods, no instance state
  - `validate_slug($slug)`: check pattern ^[a-z0-9]+/[a-z0-9-]+$, uniqueness via BerlinDB query, max 255 chars
  - `validate_callback_config($type, $config)`: type-specific rules (noop=no config required, filter_hook=hook_name required + alphanumeric, wp_remote_post=valid URL + valid method + timeout 1-300)
  - `validate_permission_config($type, $config)`: type-specific (always_allow=no config, logged_in=no config, capability=capability exists per security-constraints Finding 6)
  - `validate_schema($schema_json)`: JSON syntax + depth limit (max 10 levels, per security-constraints Finding 4) + size limit (max 64KB)
  - `validate_ability($fields)`: aggregate validation on all fields before save
  - Sanitizer class: all static methods
  - `sanitize_ability_slug()`: lowercase, remove invalid chars, apply `sanitize_title_with_dashes()`
  - `sanitize_label()`: `sanitize_text_field()`
  - `sanitize_description()`: `wp_kses_post()`
  - `sanitize_callback_config()` and `sanitize_permission_config()`: type-specific + recursive `sanitize_text_field()` on string values
  - `sanitize_schema()`: validate JSON, re-encode with `wp_json_encode()` to normalize
  - `cast_to_db_format()`: bool→int, json→string, prepare for BerlinDB save
  - PHPStan L8: zero errors
  - Unit test coverage: valid/invalid/edge case inputs for all methods
  
  **Files to Create/Modify**:
  - `includes/Utilities/AcrossAI_Custom_Ability_Validator.php` (new)
  - `includes/Utilities/AcrossAI_Custom_Ability_Sanitizer.php` (new)
  - `includes/Modules/Custom_Ability/Rest/AcrossAI_Custom_Ability_Write_Controller.php` (modify: call Validator/Sanitizer in pipeline)
  - `tests/phpunit/integration/test-custom-ability-validation.php` (new)
  
  **Dependencies**: T003 (Query exists for uniqueness check)
  **Complexity**: M
  **References**: Memory DEC-UTILITY-STATIC-ONLY, security-constraints.md Findings 2-3, CONSTITUTION.md §IV, plan.md Validation Rules

---

- [ ] T013 [P] Implement Custom Ability Processor for WordPress API registration

  **Description**: Create `AcrossAI_Custom_Ability_Processor` singleton (Memory SEC-PLAN-002) that runs at `wp_abilities_api_init` hook (priority 10). Fetch all enabled custom abilities from BerlinDB, build metadata, inject permission callback per permission_type (Memory DEC-PERM-CB), register via `wp_register_ability()`, fire hooks. Address security-constraints.md Finding 6 (permission callback validation).
  
  **Acceptance Criteria**:
  - Processor is singleton with instance() method; wire in `includes/Main.php` via Loader (named variable, Memory SEC-PLAN-002)
  - Hook: `wp_abilities_api_init` at priority 10
  - Process: fetch enabled abilities → build metadata → inject permission callback → register → fire hooks
  - Permission callback logic (Memory DEC-PERM-CB):
    - capability: `$callback = function() use ($cap) { return current_user_can($cap); };` (validate capability exists per Finding 6)
    - logged_in: `$callback = function() { return is_user_logged_in(); };`
    - always_allow: `$callback = null;`
  - Metadata structure: nested under `$args['meta']` (Memory BUG-FLAT-ARGS-PATH), includes: category, callback_type, callback_config, input_schema, output_schema, mcp_type, mcp_servers, database_id
  - Hooks fired:
    - `acrossai_custom_ability_registered` (action): ability row, WP registry args
    - `acrossai_custom_ability_registration_error` (action): slug, WP_Error
  - Error handling: log failures, continue with next ability (graceful degradation)
  - Filters applied:
    - `acrossai_custom_ability_wp_args` (filter): customize ability registration args
    - `acrossai_custom_ability_permission_callback` (filter): customize permission callback
  - PHPStan L8: zero errors
  - Unit tests: registration of enabled/disabled abilities, permission callback injection, error handling, hook firing
  
  **Files to Create/Modify**:
  - `includes/Modules/Custom_Ability/AcrossAI_Custom_Ability_Processor.php` (complete implementation)
  - `includes/Main.php` (modify: wire processor via Loader at wp_abilities_api_init)
  - `tests/phpunit/integration/test-custom-ability-processor.php` (new)
  
  **Dependencies**: T002, T003 (database classes), T012 (Sanitizer)
  **Complexity**: L
  **References**: plan.md WordPress Abilities API Integration, Memory SEC-PLAN-002, DEC-PERM-CB, security-constraints.md Finding 6, FR-003, FR-004

---

- [ ] T014 [P] Create additional Utility classes (Protected Prefixes, Callback Executor, Formatter)

  **Description**: Implement remaining Utility classes: `AcrossAI_Protected_Custom_Abilities` (namespace filtering per Memory DEC-PROTECTED-SLUGS-PATTERN), `AcrossAI_Custom_Ability_Callback_Executor` (stub for callback execution, out of scope for v1), `AcrossAI_Custom_Ability_Formatter` (REST response formatting).
  
  **Acceptance Criteria**:
  - Protected Prefixes utility:
    - Static method: `get_protected_prefixes($context = 'custom_abilities')` returns array ['acrossai', 'mcp', 'wp', 'system', 'core'] (extensible via `apply_filters()`)
    - Used in Query layer for filtering (Memory DEC-PROTECTED-SLUGS-PATTERN)
    - PHPCS + PHPStan L8: zero errors
  
  - Callback Executor utility:
    - Static methods: `execute_noop()`, `execute_filter_hook()`, `execute_wp_remote_post()` (stubs with TODO comment: "v2 implementation")
    - Currently no-op; documentation only
    - Marked for v2 implementation (callback execution out of scope for v1)
    - PHPCS + PHPStan L8: zero errors
  
  - Formatter utility:
    - Static method: `format_ability_for_response($ability_row)` — converts 20-field row to REST response shape (JSON-encodable stdClass)
    - Static method: `format_for_mcp($abilities, $mcp_type, $current_server)` — converts abilities array to MCP-compatible format
    - Response structure matches plan.md REST response schema (all 20 fields present, timestamps in ISO 8601)
    - PHPCS + PHPStan L8: zero errors
  
  **Files to Create/Modify**:
  - `includes/Utilities/AcrossAI_Protected_Custom_Abilities.php` (new)
  - `includes/Utilities/AcrossAI_Custom_Ability_Callback_Executor.php` (new, stubs)
  - `includes/Utilities/AcrossAI_Custom_Ability_Formatter.php` (complete implementation)
  
  **Dependencies**: T002 (database classes for context)
  **Complexity**: S
  **References**: Memory DEC-PROTECTED-SLUGS-PATTERN, plan.md Utility classes, FR-007 (callback stub)

---

## Phase 6: Quality & Validation

- [ ] T015 Run quality gates and validation

  **Description**: Execute PHPCS, PHPStan L8, ESLint, package validation, and comprehensive test suite. Verify all quality criteria per Constitution §VII Definition of Done.
  
  **Acceptance Criteria**:
  - PHPCS: `composer phpcs` — zero errors, zero warnings (all PHP files)
  - PHPStan: `composer phpstan -- --level 8` — zero errors (all PHP files)
  - ESLint: `npm run lint` — zero errors (all JS files)
  - Package validation: `npm run validate-packages` — zero conflicts, @wordpress packages prioritized
  - Unit tests (PHPUnit): `npm run test:php` — all tests pass, 100% coverage on new code (branches + lines)
  - Jest tests: `npm run test:js` — all tests pass
  - Security review: verify all 5 advisory findings from security-constraints.md are addressed (findings 2, 3, 5, 6, 7 have implementation decisions documented)
  - Multisite test: verify custom abilities isolated per-site
  - Permission test: verify non-admin users cannot access endpoints
  - Integration test: verify custom abilities auto-register at wp_abilities_api_init
  - Output verification: all output is JSON-encoded or escaped (Constitution §IV)
  - Nonce check: all admin forms use WordPress REST nonce verification
  - Prefix check: all functions/classes/hooks use `acrossai_` prefix
  
  **Files to Verify**:
  - All PHP files: PHPCS + PHPStan L8
  - All JS files: ESLint
  - `package.json`: dependencies validated
  - `tests/phpunit/integration/test-*.php` files: all tests pass
  - `tests/jest/admin/custom-abilities/*.test.js` files: all tests pass
  
  **Dependencies**: T001-T014 (all implementation tasks complete)
  **Complexity**: M
  **References**: CONSTITUTION.md §VII Definition of Done, AGENTS.md Before Commit Checklist, plan.md Testing

---

## Task Dependency Graph

```
T001 (Setup)
  ├─→ T002 (BerlinDB Schema/Row) [P with T003]
  │     ├─→ T003 (BerlinDB Query/Table) [P with T002]
  │     │     ├─→ T004 (DB Tests)
  │     │     ├─→ T005 (REST Read) [P with T006]
  │     │     │     ├─→ T006 (REST Write/MCP) [P with T005]
  │     │     │     │     ├─→ T007 (REST Tests)
  │     │     │     │     ├─→ T008 (DataForm) [P with T009, T010]
  │     │     │     │     │     ├─→ T009 (DataViews) [P with T008, T010]
  │     │     │     │     │     ├─→ T010 (Menu/Page/Assets) [P with T008, T009]
  │     │     │     │     │     └─→ T011 (Jest Tests)
  │     │     │     │     └─→ T012 (Validator/Sanitizer) [P with T013, T014]
  │     │     │           ├─→ T013 (Processor) [P with T012, T014]
  │     │     │           └─→ T014 (Other Utilities) [P with T012, T013]
  │     │     │                 └─→ T015 (Quality Gates)
```

**Parallel Execution Tracks** (MVPs can execute tasks marked [P] simultaneously):

- **Track A (Database)**: T002 → T003 → T004 (sequential within track)
- **Track B (REST API)**: T005 → T006 → T007 (sequential within track, depends on Track A)
- **Track C (Admin UI)**: T008 → T009 → T010 → T011 (sequential within track, depends on Track B)
- **Track D (Utilities)**: T012 → T013 → T014 (mostly parallel, all depend on Track A)
- **Track E (Validation)**: T015 (depends on all above)

**Recommended MVP Scope**: Complete T001-T014 for fully functional feature. T015 is quality/polish task.

---

## Summary: Task Coverage vs Requirements

| Requirement | Task(s) | Status |
|---|---|---|
| **FR-001** Database schema with 20 columns | T002, T003 | ✓ |
| **FR-002** DataForm admin UI for creation | T008, T010 | ✓ |
| **FR-003** DataViews admin list/management | T009, T010 | ✓ |
| **FR-004** WordPress API registration | T013 | ✓ |
| **FR-005** REST API CRUD endpoints | T005, T006 | ✓ |
| **FR-006** Slug validation & uniqueness | T012 | ✓ |
| **FR-007** Callback types (3 types) | T012, T013, T014 | ✓ |
| **FR-008** Permission types (3 types) | T012, T013 | ✓ |
| **FR-009** JSON schema validation | T012 | ✓ |
| **FR-010** MCP exposure | T006, T009 | ✓ |
| **FR-011** Metadata flags (readonly/destructive/idempotent) | T002, T006, T009 | ✓ |
| **FR-012** Capability enforcement | T005, T006, T010, T012, T013 | ✓ |
| **FR-013** Namespace pattern consistency | T001, T002, T003, T005, T006, T008 | ✓ |
| **FR-014** BerlinDB 4-file pattern | T002, T003 | ✓ |
| **FR-015** Accessibility (WCAG 2.1 A) | T008, T009, T010 | ✓ |

---

## Success Indicators

✅ **All 15 functional requirements mapped to implementation tasks**
✅ **All 7 success criteria addressed in task acceptance criteria**
✅ **All 5 user stories covered by task dependencies**
✅ **All 5 security constraint findings integrated into implementation**
✅ **All 4 architecture patterns applied (PATTERN-SINGLE-SOURCE-UTILITY, PATTERN-STAGE-NAMING, PATTERN-FEATURE-ASSET-SEPARATION, AC-QUERY-LAYER-FILTERING)**
✅ **All Constitution principles enforced (modular, standards, user-centric, security, extensible, DRY, done)**
✅ **Quality gates: PHPCS, PHPStan L8, ESLint, package validation, test coverage**

---

## Implementation Notes

1. **Parallel Execution**: Tasks marked [P] within each phase can execute in parallel (different files, no dependencies)
2. **Critical Path**: T001 → T002/T003 → T005 → T006 → T008/T009 → T010 → T015 (8-step critical path)
3. **Security Integration**: All 5 advisory findings from security-constraints.md addressed in task acceptance criteria
4. **Memory Synthesis**: All architecture patterns and watchpoints from memory-synthesis.md applied
5. **Testing Strategy**: Database tests (T004) → REST tests (T007) → UI tests (T011) → Quality gates (T015)
6. **MVP Definition**: Core MVP (T001-T014) enables full custom ability CRUD, registration, admin UI, and MCP exposure. Quality tasks (T015) ensure production readiness.

# Tasks: Custom Abilities Module (008)

**Feature**: Custom Abilities Module
**Branch**: `008-custom-abilities`
**Status**: Core implementation complete — tests pending
**Last Updated**: 2026-05-21

---

## Progress Log

- [x] 2026-05-21: T001 — Module directory structure, base files, Main.php wiring.
- [x] 2026-05-21: T002 — BerlinDB Schema (19 columns) and Row (JSON decode on construct).
- [x] 2026-05-21: T003 — BerlinDB Query (CRUD + chainable filters + singleton) and Table (schema-only).
- [x] 2026-05-21: T005 — REST Orchestrator + Read controller (GET list, GET /:id).
- [x] 2026-05-21: T006 — REST Write controller (POST, POST/:id, DELETE/:id) + MCP controller.
- [x] 2026-05-21: T008 — AbilityForm React component (create/edit, fixed slug prefix).
- [x] 2026-05-21: T009 — AbilitiesList React component (table, search, bulk actions).
- [x] 2026-05-21: T010 — Admin menu, page renderer, asset enqueue (wp_add_inline_script).
- [x] 2026-05-21: T012 — Validator (fully implemented) and Sanitizer (fully implemented).
- [x] 2026-05-21: T013 — Processor: full register_abilities() + build_execute_callback().
- [x] 2026-05-21: T014 — Formatter (ISO 8601 timestamps), CallbackExecutor (namespace added), ProtectedAbilities.
- [x] 2026-05-21: Removed fields: `category`, `permission_type`, `permission_config`, `mcp_servers` from all layers.
- [x] 2026-05-21: Fixed slug: fixed prefix `acrossai-custom-abilities/` prepended by Write controller; form shows prefix read-only.
- [x] 2026-05-21: Fixed table check: replaced `table_exists()` with BerlinDB's actual `exists()` method.
- [x] 2026-05-21: Fixed CRUD: all create/read/update/delete moved from Table to Query (`insert_ability`, `get_ability_by_id`, `update_ability`, `delete_ability`, `slug_exists`).
- [x] 2026-05-21: Fixed assets: replaced deprecated `wp_localize_script()` with `wp_add_inline_script()`.
- [x] 2026-05-21: Fixed Query singleton: added `$_instance` + `instance()` (was missing, caused fatal error).
- [x] 2026-05-21: Fixed Read controller `found_rows` → `found_items` (BerlinDB property name).
- [x] 2026-05-21: Fixed orderby enum: `'slug'` → `'ability_slug'` in Read controller.
- [x] 2026-05-21: Activator: added `AcrossAI_Ability_Logs_Table` and `AcrossAI_Custom_Ability_Table` to activation hook.

---

## Phase 1: Setup ✅

- [x] **T001** Initialize plugin boilerplate for Custom Abilities module

  **Completed State**:
  - Directory structure: `includes/Modules/Custom_Ability/`, `includes/Modules/Custom_Ability/Database/`, `includes/Modules/Custom_Ability/Rest/`, `includes/Utilities/`, `admin/Partials/`
  - All files use `AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability` namespace
  - `includes/Main.php` wired: Table boot, Processor at `wp_abilities_api_init`, REST controller at `rest_api_init`, admin menu/assets
  - `includes/AcrossAI_Activator.php` calls `maybe_upgrade()` for all 4 tables on activation

---

## Phase 2: Database Layer ✅

- [x] **T002** BerlinDB Schema and Row classes

  **Completed State**:
  - Schema: 19 columns (removed `category`, `permission_type`, `permission_config`, `mcp_servers` from original plan)
  - Row: JSON-decodes `callback_config`, `input_schema`, `output_schema` on construct; encodes on `to_array()`
  - `$global = false` — per-site table prefix, multisite-safe

- [x] **T003** BerlinDB Query and Table classes

  **Completed State**:
  - **Table**: schema-only (`$name`, `$version`, `$db_version_key`, `$global = false`, `set_schema()`, `instance()`, `exists()`). No CRUD methods.
  - **Query**: singleton (`instance()`) + CRUD methods:
    - `insert_ability(array $data): int|false`
    - `get_ability_by_id(int $id): ?AcrossAI_Custom_Ability_Row`
    - `update_ability(int $id, array $data): bool`
    - `delete_ability(int $id): bool`
    - `slug_exists(string $slug): bool`
    - `count_abilities(array $args): int`
  - Chainable filter methods: `by_slug()`, `enabled_only()`, `search()`, `with_pagination()`, `order_by()`
  - Chainable queries use `new AcrossAI_Custom_Ability_Query()` (fresh instance); CRUD uses `::instance()` (singleton)
  - Protected prefix filtering via `AcrossAI_Protected_Custom_Abilities` after search queries

- [ ] **T004** PHPUnit integration tests for database layer

  **Scope**:
  - Table creation via `maybe_upgrade()`
  - Insert, update, delete, get by ID
  - JSON column round-trip (valid / invalid / null JSON)
  - UNIQUE constraint on `ability_slug`
  - Tri-state flag values (NULL / 0 / 1)
  - `slug_exists()` before and after insert
  - `enabled_only()` filter
  - `search()` filter + protected prefix exclusion
  - Multisite isolation (`$global = false`)

  **File**: `tests/phpunit/integration/test-custom-ability-database.php`

---

## Phase 3: REST API Layer ✅

- [x] **T005** REST Orchestrator and Read controller

  **Completed State**:
  - Orchestrator (`AcrossAI_Custom_Ability_Rest_Controller`): singleton, `register_routes()` delegates to sub-controllers, `check_permission()` enforces `manage_options`
  - Read controller: `GET /custom-abilities` (list) + `GET /custom-abilities/{id}` (single)
  - List query params: `search`, `order`, `orderby` (`label|ability_slug|created_at|updated_at`), `per_page` (1–100), `page`, `enabled`, `show_in_mcp`
  - Pagination headers: `X-WP-Total`, `X-WP-TotalPages`
  - Table readiness via `$this->table->exists()` (BerlinDB method)
  - Data access: `$this->db_query->get_ability_by_id()` for single; `new AcrossAI_Custom_Ability_Query()` for list
  - Total count uses `$query->found_items` (BerlinDB property)

- [x] **T006** REST Write controller and MCP controller

  **Completed State**:
  - Write controller validation pipeline: extract → prepend `acrossai-custom-abilities/` prefix → sanitize → validate → collision check (`db_query->slug_exists()`) → cast to DB format → `before_save` hook → `insert_ability()` / `update_ability()` / `delete_ability()` → fetch row → `after_save` hook → format
  - Status codes: 201 (created), 200 (updated), 204 (deleted), 400 (validation), 409 (duplicate), 500 (error)
  - MCP controller: filters by `show_in_mcp = true` and `mcp_type`; no `mcp_servers` filtering
  - Both controllers: ABSPATH guard added, use `$this->db_query` for CRUD

- [ ] **T007** PHPUnit integration tests for REST API layer

  **Scope**:
  - GET list: pagination, search, orderby, enabled filter
  - GET /:id: found and not-found
  - POST create: valid payload, missing required fields, duplicate slug, invalid callback config
  - POST/:id update: partial fields, slug collision detection
  - DELETE: found and not-found
  - Permission denial for non-`manage_options` users (403)
  - Slug prefix auto-prepend verification
  - Before/after-save hooks fire with correct data
  - MCP endpoint type filtering

  **File**: `tests/phpunit/integration/test-custom-ability-rest-crud.php`

---

## Phase 4: Admin UI Layer ✅

- [x] **T008** AbilityForm React component

  **Completed State**:
  - 14 form fields (removed `category`, `permission_type/config`, `mcp_servers` from original 20-field plan)
  - Slug input: fixed prefix `acrossai-custom-abilities/` displayed read-only; user enters suffix only
  - Suffix validation: `^[a-z0-9][a-z0-9-]*$`, max 230 chars
  - On edit mode load: strips `acrossai-custom-abilities/` prefix before populating field
  - Conditional callback_config fields (hook_name / url+method+timeout)
  - Conditional mcp_type field (only when `show_in_mcp = true`)
  - JSON textarea validation for `input_schema` and `output_schema`
  - Tri-state selects for `readonly`, `destructive`, `idempotent` (inherit / false / true)

- [x] **T009** AbilitiesList React component

  **Completed State**:
  - Columns: Slug (suffix only), Label, Status (enabled), Type (callback_type), MCP
  - Removed columns from original plan: Category, Permission
  - Search filter (slug/label/description), enabled status filter
  - Bulk actions: Enable, Disable, Delete (with confirmation modal)
  - Contextual actions: Edit, Toggle Enable/Disable, Delete
  - Pagination: 20/50/100 per page
  - `filterCategory` state and Category dropdown removed

- [x] **T010** Admin menu, page, asset enqueue

  **Completed State**:
  - Menu: `AcrossAI_Custom_Ability_Menu` singleton, submenu slug `acrossai-custom-abilities` under Abilities Manager
  - Page: renders `<div id="acrossai-custom-abilities-root"></div>`
  - Assets: `AcrossAI_Custom_Ability_Assets` singleton
    - Script handle: `acrossai-abilities-custom`
    - Data via `wp_add_inline_script()` (NOT `wp_localize_script()`):
      ```js
      window.acrossaiAbilitiesManager = { restNamespace, nonce }
      ```
    - Conditional enqueue on `acrossai-custom-abilities` page only

- [ ] **T011** Jest tests for React components

  **Scope**:
  - AbilityForm: renders all 14 fields; prefix display; conditional fields show/hide; slug suffix validation; JSON schema validation; create vs edit mode
  - AbilitiesList: renders table columns; search filter; enabled filter; bulk action buttons; pagination; delete confirmation modal

  **Files**: `tests/jest/admin/custom-abilities/AbilityForm.test.js`, `AbilitiesList.test.js`

---

## Phase 5: Business Logic ✅

- [x] **T012** Validator and Sanitizer utility classes

  **Completed State — Validator** (`AcrossAI_Custom_Ability_Validator`):
  - `validate_slug($slug)`: pattern `^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$`, max 255 chars
  - `validate_label($label)`: non-empty, max 255 chars
  - `validate_callback_config($type, $config)`: noop=pass; filter_hook=`hook_name` required + alphanumeric+underscore; wp_remote_post=`url` required + `wp_http_validate_url()`
  - `validate_schema($schema)`: JSON syntax + max depth 10 + max size 64 KB
  - `validate_mcp_type($mcp_type)`: must be `tool|resource|prompt` or null
  - `validate_ability($fields)`: aggregate — calls all relevant validators
  - Removed from original plan: `validate_permission_config()`, `validate_category()`

  **Completed State — Sanitizer** (`AcrossAI_Custom_Ability_Sanitizer`):
  - `sanitize_ability_slug()`, `sanitize_label()`, `sanitize_description()`, `sanitize_callback_config()`, `sanitize_schema()`, `cast_to_db_format()`
  - Removed from original plan: `category`, `permission_type`, `permission_config`, `mcp_servers`, `sanitize_permission_config()`
  - `sanitize_recursive()` for deep array sanitization

- [x] **T013** Custom Ability Processor

  **Completed State**:
  - `register_abilities()` at `wp_abilities_api_init` priority 10
  - Checks `table->exists()` before querying
  - Fetches up to 1000 enabled abilities
  - Validates slug pattern before registering each ability
  - Builds `$args`: label, description, callback, show_in_rest, optional input/output schema, optional annotations (tri-state flags)
  - `build_execute_callback()`: returns closure for noop / filter_hook / wp_remote_post
  - Fires `acrossai_custom_ability_registered` per ability + `acrossai_custom_ability_processor_initialized` after loop
  - Removed from original plan: permission callback injection (no `permission_type` column)

- [x] **T014** Utility classes (Formatter, CallbackExecutor, ProtectedAbilities)

  **Completed State**:
  - `AcrossAI_Custom_Ability_Formatter`: `format_ability_for_response()` converts Row to stdClass with ISO 8601 datetimes and JSON-decoded columns; `format_for_mcp()` filters by `show_in_mcp` and `mcp_type`
  - `AcrossAI_Custom_Ability_Callback_Executor`: namespace added; v2 stubs for `execute_noop()`, `execute_filter_hook()`, `execute_wp_remote_post()`, `execute()`
  - `AcrossAI_Protected_Custom_Abilities`: `get_protected_prefixes()` returns filterable array

---

## Phase 6: Quality Gates

- [ ] **T015** Quality gates and validation

  **Checklist**:
  - [ ] PHPCS: `composer phpcs` — zero errors on all new PHP files
  - [ ] PHPStan L8: `./vendor/bin/phpstan analyse` — zero errors
  - [ ] ESLint: `npm run lint` — zero errors on JS files
  - [ ] Webpack build: `npm run build` — no errors, asset files generated
  - [ ] PHP syntax: `php -l` on all modified PHP files
  - [ ] T004 DB tests pass
  - [ ] T007 REST tests pass
  - [ ] T011 Jest tests pass
  - [ ] Manual: create ability via UI → appears in list → registers at `wp_abilities_api_init`
  - [ ] Manual: edit ability → changes saved → list updates
  - [ ] Manual: delete ability → removed from list
  - [ ] Manual: `wp_acrossai_custom_abilities` table has correct 19-column structure
  - [ ] Manual: non-`manage_options` user cannot access REST endpoints (403)
  - [ ] Manual: duplicate slug returns 409

---

## Scope Changes vs Original Plan

| Change | Original | Current |
|---|---|---|
| DB columns | 20 (incl. category, permission_type, permission_config, mcp_servers) | 19 (those 4 removed) |
| Slug format | User enters full `namespace/name` | Fixed prefix `acrossai-custom-abilities/` + user suffix |
| Permission callback | `always_allow / logged_in / capability` per ability | Removed — REST layer enforces `manage_options` only |
| Category column | varchar(100) | Removed |
| MCP server filtering | Per-ability `mcp_servers` JSON array | Removed — global exposure to all servers |
| Table CRUD methods | On Table class | On Query class only |
| BerlinDB table check | `table_exists()` | `exists()` (actual method name) |
| Assets data pass | `wp_localize_script()` | `wp_add_inline_script()` |
| Form field count | 20 fields | 14 fields |
| List columns | 9 columns incl. category/permission | 5 columns (simplified) |

---

## Task Completion Summary

| Task | Description | Status |
|---|---|---|
| T001 | Module setup & wiring | ✅ Done |
| T002 | Schema + Row | ✅ Done |
| T003 | Query + Table | ✅ Done |
| T004 | DB tests | ⬜ Pending |
| T005 | REST Orchestrator + Read | ✅ Done |
| T006 | REST Write + MCP | ✅ Done |
| T007 | REST tests | ⬜ Pending |
| T008 | AbilityForm | ✅ Done |
| T009 | AbilitiesList | ✅ Done |
| T010 | Menu + Page + Assets | ✅ Done |
| T011 | Jest tests | ⬜ Pending |
| T012 | Validator + Sanitizer | ✅ Done |
| T013 | Processor | ✅ Done |
| T014 | Formatter + CallbackExecutor + Protected | ✅ Done |
| T015 | Quality gates | ⬜ Pending |

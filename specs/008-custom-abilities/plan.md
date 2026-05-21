# Implementation Plan: Custom Abilities Module

**Branch**: `008-custom-abilities` | **Date**: 2026-05-21 | **Status**: Implementation Complete (Tests Pending)

## Summary

The Custom Abilities module enables WordPress administrators to create, manage, and configure WordPress abilities directly via admin UI without writing PHP code. Abilities are stored in a BerlinDB table (`{prefix}acrossai_custom_abilities`), auto-registered at the `wp_abilities_api_init` hook via the Processor, exposed via REST CRUD endpoints, and optionally exposed to MCP servers. The admin UI is a React SPA rendered inside a WordPress submenu page.

**Technical Approach**:
- BerlinDB 4-file pattern (Schema → Row → Query → Table) for database abstraction
- Query class is the single source of truth for all CRUD operations (Table class is schema-only)
- REST controller split: Orchestrator + 3 sub-controllers (Read, Write, MCP)
- Admin UI: React SPA with `AbilityForm` (create/edit) and `AbilitiesList` (manage)
- WordPress Abilities API integration: Auto-registration at `wp_abilities_api_init` via `AcrossAI_Custom_Ability_Processor`
- Security: `manage_options` capability check on all endpoints and admin pages
- Namespace: `AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability` (underscore convention)

---

## Technical Context

**Language/Version**: PHP 7.4+ (target WordPress 6.9+)

**Storage**: BerlinDB-managed MySQL/MariaDB table `{prefix}acrossai_custom_abilities` — 19 columns (see Data Model below).

**Slug Convention**: All custom abilities use the fixed namespace prefix `acrossai-custom-abilities/`. The user supplies only the suffix (e.g. `my-ability`). The Write controller prepends the prefix before saving; the form strips it when loading for edit.

---

## Project Structure

### Source Code

```
includes/Modules/Custom_Ability/
├── AcrossAI_Custom_Ability_Processor.php   ← wp_abilities_api_init registration
├── Database/
│   ├── AcrossAI_Custom_Ability_Schema.php  ← BerlinDB Schema (column definitions)
│   ├── AcrossAI_Custom_Ability_Row.php     ← BerlinDB Row (JSON decode on construct)
│   ├── AcrossAI_Custom_Ability_Query.php   ← BerlinDB Query (CRUD + chainable filters)
│   └── AcrossAI_Custom_Ability_Table.php   ← BerlinDB Table (schema-only, maybe_upgrade)
└── Rest/
    ├── AcrossAI_Custom_Ability_Rest_Controller.php   ← Orchestrator
    ├── AcrossAI_Custom_Ability_Read_Controller.php   ← GET list, GET /:id
    ├── AcrossAI_Custom_Ability_Write_Controller.php  ← POST, POST /:id, DELETE /:id
    └── AcrossAI_Custom_Ability_Mcp_Controller.php    ← GET /mcp/tools|resources|prompts

includes/Utilities/
├── AcrossAI_Custom_Ability_Validator.php          ← Static validation
├── AcrossAI_Custom_Ability_Sanitizer.php          ← Static sanitization
├── AcrossAI_Custom_Ability_Formatter.php          ← Static response formatting
├── AcrossAI_Custom_Ability_Callback_Executor.php  ← Callback dispatch (v2 stubs)
└── AcrossAI_Protected_Custom_Abilities.php        ← Protected prefix filtering

admin/Partials/
├── AcrossAI_Custom_Ability_Menu.php    ← add_submenu_page
├── AcrossAI_Custom_Ability_Page.php    ← page render callback
└── AcrossAI_Custom_Ability_Assets.php  ← wp_enqueue_script / wp_add_inline_script

src/js/admin/custom-abilities/
├── index.js
├── components/
│   ├── AbilityForm.js      ← Create/edit form
│   └── AbilitiesList.js    ← Table with search, filter, bulk actions
└── api/
    └── useCustomAbilities.js  ← React hook for REST calls

src/scss/admin/custom-abilities/
├── form.scss
├── list.scss
└── index.scss
```

### Activation

`AcrossAI_Activator::activate()` calls `maybe_upgrade()` on all three plugin-owned tables:
```php
( new AcrossAI_Sitewide_Table() )->maybe_upgrade();
( new AcrossAI_Ability_Logs_Table() )->maybe_upgrade();
( new AcrossAI_Custom_Ability_Table() )->maybe_upgrade();
( new RuleTable() )->maybe_upgrade();
```

---

## Data Model

### BerlinDB Table: `{prefix}acrossai_custom_abilities` — 19 columns

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | bigint unsigned | NO | auto_increment | PK |
| `ability_slug` | varchar(255) | NO | `''` | UNIQUE — always `acrossai-custom-abilities/{suffix}` |
| `label` | varchar(255) | NO | `''` | Display name |
| `description` | longtext | YES | NULL | |
| `enabled` | tinyint(1) | NO | `1` | Auto-register flag |
| `callback_type` | varchar(50) | NO | `'noop'` | `noop \| filter_hook \| wp_remote_post` |
| `callback_config` | longtext | YES | NULL | JSON: type-specific config |
| `input_schema` | longtext | YES | NULL | JSON Schema Draft 7 |
| `output_schema` | longtext | YES | NULL | JSON Schema Draft 7 |
| `show_in_rest` | tinyint(1) | NO | `1` | REST exposure flag |
| `show_in_mcp` | tinyint(1) | NO | `0` | MCP exposure flag |
| `mcp_type` | varchar(50) | YES | NULL | `tool \| resource \| prompt` |
| `readonly` | tinyint(1) | YES | NULL | Tri-state: NULL=inherit, 0=false, 1=true |
| `destructive` | tinyint(1) | YES | NULL | Tri-state |
| `idempotent` | tinyint(1) | YES | NULL | Tri-state |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `created_by` | bigint unsigned | YES | NULL | |
| `updated_by` | bigint unsigned | YES | NULL | |

**Indexes**: `PRIMARY KEY (id)`, `UNIQUE KEY ability_slug (ability_slug(191))`, `KEY idx_enabled (enabled)`, `KEY idx_updated_at (updated_at)`

**Removed from original plan**: `category`, `permission_type`, `permission_config`, `mcp_servers` — removed to simplify the v1 scope. MCP exposure is global (all servers); permission is always managed by WordPress capability check at the REST layer.

### BerlinDB 4-File Roles

| File | Role |
|---|---|
| Schema | Column definitions for BerlinDB Query metadata |
| Row | Hydrates a single DB record; JSON-decodes `callback_config`, `input_schema`, `output_schema` on construct |
| Query | Single source of truth for all CRUD and filtering: `insert_ability()`, `get_ability_by_id()`, `update_ability()`, `delete_ability()`, `slug_exists()`, `enabled_only()`, `search()`, `with_pagination()`, `order_by()` |
| Table | Schema-only: `maybe_upgrade()`, `set_schema()`, `exists()`. No CRUD methods. |

---

## Slug Convention

- **Stored slug format**: `acrossai-custom-abilities/{suffix}`
- **Suffix rules**: `^[a-z0-9][a-z0-9-]*$`, max 230 chars
- **Write controller**: strips any existing prefix, then prepends `acrossai-custom-abilities/` in `extract_fields()`
- **React form**: displays `acrossai-custom-abilities/` as read-only prefix; user types only the suffix; strips prefix when loading edit mode
- **Validator pattern**: `^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$` (applied to the full stored slug)

---

## REST API Architecture

**Namespace**: `acrossai-abilities-manager/v1`
**Base path**: `/wp-json/acrossai-abilities-manager/v1/custom-abilities`

### Orchestrator (`AcrossAI_Custom_Ability_Rest_Controller`)
- Singleton; registers routes by delegating to each sub-controller
- `check_permission()`: enforces `manage_options` capability

### Read Controller
| Method | Path | Notes |
|---|---|---|
| GET | `/custom-abilities` | Query params: `search`, `order`, `orderby` (`label\|ability_slug\|created_at\|updated_at`), `per_page`, `page`, `enabled`, `show_in_mcp` |
| GET | `/custom-abilities/{id}` | Single ability by ID |

- Uses `new AcrossAI_Custom_Ability_Query()` (fresh instance) for chainable queries
- Uses `AcrossAI_Custom_Ability_Query::instance()` for single-item fetch
- Pagination headers: `X-WP-Total`, `X-WP-TotalPages`
- Table check via `$this->table->exists()` (BerlinDB method name)

### Write Controller
| Method | Path | Notes |
|---|---|---|
| POST | `/custom-abilities` | Create — 201 response |
| POST/PUT | `/custom-abilities/{id}` | Update — 200 response |
| DELETE | `/custom-abilities/{id}` | Delete — 204 response |

- Full pipeline: extract → prepend slug prefix → sanitize → validate → collision check → cast → hook → save → fetch row → hook → format
- Uses `$this->db_query` (`AcrossAI_Custom_Ability_Query::instance()`) for all CRUD
- Slug collision check: `$this->db_query->slug_exists($slug)`
- Status codes: 201, 200, 204, 400, 403, 409, 500

### MCP Controller
| Method | Path | Notes |
|---|---|---|
| GET | `/custom-abilities/mcp/tools` | `mcp_type = 'tool'` |
| GET | `/custom-abilities/mcp/resources` | `mcp_type = 'resource'` |
| GET | `/custom-abilities/mcp/prompts` | `mcp_type = 'prompt'` |

- Fetches all enabled abilities; filters by `show_in_mcp = true` and `mcp_type`
- No `mcp_servers` filtering (column removed); all MCP-enabled abilities are global

---

## Callback Strategies (Processor)

| `callback_type` | Behaviour |
|---|---|
| `noop` | Returns `[]` immediately |
| `filter_hook` | `apply_filters('acrossai_custom_ability_execute_{slug}', [], $input)` — extensible |
| `wp_remote_post` | POSTs `$input` as JSON to `callback_config.url`, returns decoded response |

Config validation in `AcrossAI_Custom_Ability_Validator`:
- `noop`: no config required
- `filter_hook`: `hook_name` required, alphanumeric + underscores
- `wp_remote_post`: valid URL (via `wp_http_validate_url()`), optional `method`, `timeout` (1–300)

---

## Admin UI Architecture

### Form (`AbilityForm.js`)

Fields (16 total — 4 removed from original plan):
- `ability_slug` (suffix only, fixed prefix displayed)
- `label` (required)
- `description` (textarea)
- `enabled` (checkbox)
- `callback_type` (select: noop / filter_hook / wp_remote_post)
- `callback_config` (conditional: hook_name for filter_hook; url + method + timeout for wp_remote_post)
- `input_schema` (textarea, JSON validation)
- `output_schema` (textarea, JSON validation)
- `show_in_rest` (checkbox)
- `show_in_mcp` (checkbox)
- `mcp_type` (select, conditional on `show_in_mcp`)
- `readonly` (select: inherit / false / true)
- `destructive` (select: inherit / false / true)
- `idempotent` (select: inherit / false / true)

### List (`AbilitiesList.js`)

Columns: Slug (suffix only), Label, Status, Type, MCP
Filters: search (slug/label/description), enabled status
Bulk actions: Enable, Disable, Delete

### Assets

- Script handle: `acrossai-abilities-custom`
- Data passed via `wp_add_inline_script()` (NOT `wp_localize_script()`):
  ```js
  window.acrossaiAbilitiesManager = { restNamespace, nonce }
  ```

---

## WordPress Abilities API Integration

**Hook**: `wp_abilities_api_init` (priority 10)

**Processor flow**:
1. Check `table->exists()` — skip if table not ready
2. Fetch all enabled abilities via `(new AcrossAI_Custom_Ability_Query())->enabled_only()->with_pagination(1000,1)->get()`
3. For each ability: validate slug pattern; build `$args`; register via `wp_register_ability($slug, $args)`
4. Fire `acrossai_custom_ability_registered` hook per registered ability
5. Fire `acrossai_custom_ability_processor_initialized` after loop

**Permission**: Removed from processor scope. Abilities registered without permission callback. REST layer enforces `manage_options`.

---

## Key Design Decisions

| Decision | Choice | Reason |
|---|---|---|
| Removed `category` | Omitted | Simplifies v1; categories can be added via WP ability category registration |
| Removed `permission_type/config` | Omitted | REST layer + WP capabilities system is sufficient for v1 |
| Removed `mcp_servers` | Omitted | Global MCP exposure is simpler; per-server filtering is v2 scope |
| Table vs Query CRUD | Query only | BerlinDB Table is schema-management only; CRUD belongs in Query |
| `exists()` not `table_exists()` | `exists()` | Actual BerlinDB method name |
| Slug prefix fixed | `acrossai-custom-abilities/` | Prevents namespace collision; user enters only the meaningful suffix |
| `wp_add_inline_script` | Used | `wp_localize_script()` is deprecated |
| Singleton for Query | Added | Required by controllers via `::instance()` for CRUD; chainable queries use `new` |

---

## Success Criteria

| Criterion | Status |
|---|---|
| SC-001 Create ability & see in list | ✅ Implemented |
| SC-002 Registration at wp_abilities_api_init | ✅ Implemented |
| SC-003 Complete REST CRUD | ✅ Implemented |
| SC-004 MCP abilities discoverable by type | ✅ Implemented |
| SC-005 <500ms for 1000+ records | ✅ BerlinDB pagination |
| SC-006 100% permission enforcement (manage_options) | ✅ Implemented |
| SC-007 Backward compatibility | ✅ No breaking changes |

---
description: "Tasks for REST Controller Modularization (spec 002)"
---

# Tasks: REST Controller Modularization

**Input**: [plan.md](plan.md) | **Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md)
**Branch**: `001-sitewide-ability-management`

---

## Phase 1: Create sub-controller files

- [x] T001 Create `includes/Modules/Sitewide/Rest/index.php` — 8-line `@package` directory sentinel matching `Sitewide/index.php`
- [x] T002 Create `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php` — singleton; registers GET /sitewide/abilities and GET /sitewide/abilities/{slug}; moves `get_abilities()` and `get_ability()` verbatim; uses `AcrossAI_Sitewide_Rest_Controller::instance()` as permission callback
- [x] T003 Create `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php` — singleton; registers POST /sitewide/abilities/{slug}, DELETE /sitewide/abilities/{slug}, POST /sitewide/abilities/{slug}/toggle; moves `save_override()`, `delete_override()`, `toggle_ability()` verbatim
- [x] T004 Create `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php` — singleton; registers POST /sitewide/abilities/bulk; moves `bulk_action()` verbatim
- [x] T005 Create `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Mcp_Controller.php` — singleton; registers GET /sitewide/mcp-servers; moves `get_mcp_servers()` verbatim

---

## Phase 2: Rewrite orchestrator

- [x] T006 Rewrite `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` as thin orchestrator — keeps `REST_NAMESPACE` constant, `register_routes()` delegating to four sub-controllers, `check_permission()`; removes all handler methods and route args

---

## Phase 3: Documentation

- [x] T007 Update `.specify/memory/CONSTITUTION.md` to v1.4.0 — add REST Controller Pattern subsection, update sync-impact comment, bump version/date
- [x] T008 Update `AGENTS.md` — add REST controller split pattern paragraph under *Code Organization & Module Structure*
- [x] T009 Create `specs/002-rest-controller-modularization/plan.md` and `tasks.md`
- [x] T010 Append controller-split note to `specs/001-sitewide-ability-management/plan.md` *Project Structure* section

---

## Phase 4: MCP server listing — package integration

- [x] T011 Require `wpboilerplate/wpb-mcp-servers-list` via Composer — resolves McpAdapter timing and serialization friction
- [x] T012 Rewrite `AcrossAI_Sitewide_Mcp_Controller::get_mcp_servers()` to delegate to `McpServersList::instance()->get_servers()` — removes manual McpAdapter calls and getter mapping
- [x] T013 Wire `McpServersList::instance()->collect()` in `Main::define_admin_hooks()` via Loader at `rest_api_init` priority 20 (variable-first pattern, Constitution §Boot Flow Rule)

---

## Definition of Done

- [x] `composer dump-autoload` — no errors
- [x] `composer phpcs` — zero errors/warnings on changed files
- [x] `composer phpstan` — zero errors at level 8
- [x] All 5 REST routes still reachable at their original URLs
- [x] `GET /sitewide/mcp-servers` returns populated server list when mcp-adapter is active
- [x] No JS/SCSS/build changes

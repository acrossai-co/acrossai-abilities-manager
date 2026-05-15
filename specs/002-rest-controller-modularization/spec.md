# Specification: REST Controller Modularization

**Feature Branch**: `001-sitewide-ability-management`
**Created**: 2026-05-14
**Status**: Complete
**Type**: Internal refactor (no user-visible changes)

---

## Problem Statement

`AcrossAI_Sitewide_Rest_Controller` grew to 670 lines and owned seven unrelated
responsibilities: route registration, permission checking, ability reads, override writes,
toggle, bulk actions, and MCP server listing. A single file touching every user story made
targeted edits noisy and code reviews unnecessarily broad.

Additionally, the MCP server listing handler called `McpAdapter::instance()->get_servers()`
directly — an approach that silently returned an empty array due to undocumented timing
constraints in the mcp-adapter plugin.

---

## Requirements

### R1 — Single Responsibility per File

Each handler group MUST live in its own class file:

| Handler group | Routes |
|---|---|
| Ability reads | GET /sitewide/abilities, GET /sitewide/abilities/{slug} |
| Override writes | POST /abilities/{slug}, DELETE /abilities/{slug}, POST .../toggle |
| Bulk actions | POST /abilities/bulk |
| MCP server listing | GET /sitewide/mcp-servers |

No handler group file may exceed one cohesive concern.

### R2 — Unchanged REST Contract

All five existing REST endpoint URLs, HTTP methods, request parameters, and response shapes
MUST remain identical. No breaking changes.

### R3 — Unchanged Security Model

`manage_options` capability + nonce verification MUST be enforced on every route.
The permission check MUST NOT be duplicated — all sub-controllers reference a single shared
`check_permission()` on the orchestrator.

### R4 — Boot Flow Rule Compliance

Only the orchestrator (`AcrossAI_Sitewide_Rest_Controller`) is wired in `includes/Main.php`.
Sub-controllers MUST NOT register any WordPress hooks themselves.

### R5 — MCP Server Listing: Reliable Data

`GET /sitewide/mcp-servers` MUST return a populated server list when the mcp-adapter plugin
is active, at any request time. The implementation MUST NOT depend on undocumented McpAdapter
timing internals.

**Acceptance**: With mcp-adapter active and at least one server registered, the endpoint
returns a JSON array with at least one entry containing `id`, `name`, `description`,
`version`, `endpoint_url`, `tools`, `resources`, and `prompts` fields.

### R6 — Codified Pattern

The decomposition MUST be documented as the canonical REST Controller Pattern in
`CONSTITUTION.md` and `AGENTS.md` for use by future sibling modules
(`PerUser`, `McpServer`, `CustomAbility`, `Webmcp`).

---

## Out of Scope

- Any changes to handler method bodies or REST response shapes.
- Fixing the stale `RestControllerTest.php` (tracked separately).
- JS, SCSS, or build pipeline changes.
- New REST endpoints.

---

## Success Criteria

- All five REST endpoints still pass a manual smoke test.
- `GET /sitewide/mcp-servers` returns populated data when mcp-adapter is active.
- `composer phpcs` and `composer phpstan` pass at zero errors.
- No file in `includes/Modules/Sitewide/Rest/` exceeds the scope of one handler group.
- Constitution and AGENTS.md document the REST Controller Pattern.

# Research: REST Controller Modularization

**Phase**: 0 | **Date**: 2026-05-14 | **Plan**: [plan.md](plan.md)

All decisions below were resolved from spec analysis, source code investigation, and
constitution constraints. No NEEDS CLARIFICATION items remain.

---

## Decision 1: Sub-controller Decomposition Boundary

**Decision**: Four sub-controllers — `Abilities`, `Override`, `Bulk`, `Mcp` — matching the
four user story groups from spec 001.

**Rationale**:
- One sub-controller per user story group satisfies Constitution §I (single responsibility).
- The toggle handler (`POST .../toggle`) ships with `Override`, not as its own file: it is a
  40-line specialisation of `save_override()` that writes the same DB row via
  `AcrossAI_Sitewide_Query::save_override()`. A separate 60-line file with its own singleton
  ceremony would trade clarity for boilerplate.
- `REST_NAMESPACE` stays on the orchestrator as a `public const` — sub-controllers reference
  it rather than duplicating it (Constitution §VI).

**Alternatives considered**:
- Toggle as its own sub-controller — rejected: too small, same data boundary as Override.
- Flat single-file approach — rejected: original 670-line file is the problem being solved.

---

## Decision 2: Permission Callback Placement

**Decision**: `check_permission()` stays on the orchestrator (`AcrossAI_Sitewide_Rest_Controller`);
sub-controllers reference it via `array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' )`.

**Rationale**:
- Avoids duplicating the nonce + `manage_options` check in four places (Constitution §VI).
- Not a circular dependency — the orchestrator's `check_permission()` does not call back into
  sub-controllers.
- A utility class for a single-module concern would be over-engineering.

**Critical pattern** (every sub-controller route):
```php
'permission_callback' => array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' ),
```

---

## Decision 3: MCP Server Listing — Direct McpAdapter vs. Package

**Decision**: Use `wpboilerplate/wpb-mcp-servers-list` Composer package. Direct calls to
`\WP\MCP\Core\McpAdapter::instance()->get_servers()` are prohibited.

**Rationale**:

Direct `McpAdapter` consumption has three compounding problems discovered during debugging:

1. **Timing**: `McpAdapter::instance()` hooks its own `init()` on `rest_api_init` at priority
   15. `init()` fires `do_action('mcp_adapter_init')`, and `DefaultServerFactory::create()`
   runs at priority 10 within that action. If `McpAdapter::instance()` is first called inside
   a REST callback (after `rest_api_init` has already completed), `init()` never runs and
   `$servers` stays permanently empty.

2. **Silent guard**: `create_server()` inside mcp-adapter contains:
   ```php
   if ( ! doing_action( 'mcp_adapter_init' ) ) { return; }
   ```
   Server creation silently no-ops if called outside that specific action window, producing
   no error or log entry.

3. **Non-serializable objects**: `McpAdapter::get_servers()` returns `McpServer[]`. All
   `McpServer` properties are `private`, so `json_encode()` / `rest_ensure_response()`
   serializes every object as `{}`. The caller must manually map getters
   (`get_server_id()`, `get_server_name()`, etc.) to build a usable array.

**Package solution**: `wpboilerplate/wpb-mcp-servers-list` encapsulates all three:
- `collect()` is safe to call at any point after McpAdapter's priority 15 (call at priority 20+).
- `ServerData` implements `JsonSerializable` — `rest_ensure_response()` serializes correctly
  without manual mapping.
- Idempotent: subsequent `collect()` calls are no-ops.

**Wiring** (in `Main::define_admin_hooks()`, variable-first per Constitution §Boot Flow Rule):
```php
$mcp_servers_list = \WPBoilerplate\McpServersList\McpServersList::instance();
$this->loader->add_action( 'rest_api_init', $mcp_servers_list, 'collect', 20 );
```

**REST callback** (`AcrossAI_Sitewide_Mcp_Controller::get_mcp_servers()`):
```php
return rest_ensure_response( McpServersList::instance()->get_servers() );
```

**GitHub issue filed**: `WordPress/mcp-adapter#180` — documents the timing and serialization
problems for the mcp-adapter maintainers.

**Alternatives considered**:
- Cache pattern (hooking `mcp_adapter_init` at priority 999, storing results in a controller
  property) — valid workaround but requires the plugin to know mcp-adapter internals. The
  package is the cleaner, documented, forward-compatible approach.
- Direct getter mapping without a package — rejected: couples the plugin to McpServer's
  private API; breaks silently if mcp-adapter renames getters.

---

## Decision 4: Hook Registration for McpServersList::collect()

**Decision**: Wire via the Loader in `Main::define_admin_hooks()` at priority 20, not via
`McpServersList::bootstrap()` (which calls `add_action` internally).

**Rationale**:
- Constitution §Boot Flow Rule requires ALL hooks to trace through the Loader in
  `Main::define_admin_hooks()`. `bootstrap()` calls `add_action` directly, bypassing
  the Loader — a boot flow violation even though the behaviour is equivalent.
- Variable-first wiring makes the priority explicit and readable.

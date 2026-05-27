# Security Constraints: Feature 016 — Allowed Servers Checkbox List

**Date**: 2026-05-27
**Reviewer**: GitHub Copilot (automated)
**Plan reviewed**: `specs/016-allowed-servers-checkbox-list/plan.md`
**Spec reviewed**: `specs/016-allowed-servers-checkbox-list/spec.md`
**Artifacts also consulted**:
- `vendor/wpboilerplate/wpb-mcp-servers-list/src/RestEndpoint.php`
- `vendor/wpboilerplate/wpb-mcp-servers-list/src/Data/ServerData.php`
- `includes/Utilities/AcrossAI_Sanitizer.php`
- `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`
- `specs/016-allowed-servers-checkbox-list/memory-synthesis.md`

---

## Executive Summary

The feature is low-risk overall. The REST endpoint is read-only, admin-gated,
and nonce-protected. React JSX rendering prevents XSS. The existing server-side
sanitizer handles `mcp_servers` write validation. Two findings require
documentation before implementation: a MEDIUM-severity filterable capability
that third-party code could weaken, and a LOW-severity server-side gap where
the `[] → null` business invariant is enforced only client-side.

---

## Findings

### MEDIUM — Capability Filter Weakening (`wpb_mcp_servers_list_rest_capability`)

**Location**: `vendor/wpboilerplate/wpb-mcp-servers-list/src/RestEndpoint.php`,
`permission_callback` closure.

**Detail**: The vendor endpoint applies `apply_filters('wpb_mcp_servers_list_rest_capability', $capability)`
before calling `current_user_can()`. Any plugin or theme loaded on the site can
hook this filter and lower the required capability to `'edit_posts'`, `'read'`,
or any other string. If triggered, the MCP server list (including `endpoint_url`,
`tools`, `resources`, and `prompts`) would be exposed to lower-privileged users.

**Plan gap**: The plan states the endpoint "uses `manage_options` capability
check (confirmed in vendor source)" but does not document that this can be
overridden via a publicly documented filter. No counter-measure or monitoring
note is included.

**Mitigation required before implementation**:
1. Add a note to the implementation task stating that the plugin must NOT add any
   hook on `wpb_mcp_servers_list_rest_capability` and must document this as a
   known external attack surface in the security notes.
2. Optionally add a defensive assertion in a developer-facing hook comment in
   `Main.php` near the registration line warning integrators not to lower the
   capability.
3. The plugin's own security policy should state: "Do not register hooks on
   `wpb_mcp_servers_list_rest_capability` that lower the capability below
   `manage_options`."

**This does not block implementation** — it requires documentation and an
explicit "no-override" commitment in the task notes.

---

### LOW — Server-Side `[] → null` Invariant Not Enforced

**Location**: `includes/Utilities/AcrossAI_Sanitizer.php`, `sanitize_mcp_servers_array()`.

**Detail**: The function returns an empty array `[]` when the input is an array
whose items all sanitize to empty strings. The plan's `[] → null` business rule
is enforced only in the client-side toggle handler (`next.length === 0 ? null : next`).
A direct REST PUT/PATCH request with `mcp_servers: []` (bypassing the UI)
would persist `[]` to the database, violating the invariant.

```php
// Current sanitizer — returns [] if all items are empty strings
$sanitized = array();
foreach ( $value as $server_id ) {
    $clean = sanitize_text_field( (string) $server_id );
    if ( '' !== $clean ) {
        $sanitized[] = $clean;
    }
}
return $sanitized; // could return [] if input was ["", ""]
```

**Mitigation required before implementation**: Add a post-filter step to
`sanitize_mcp_servers_array()` (or to `AcrossAI_Abilities_Sanitizer::sanitize_mcp_servers()`):

```php
return empty( $sanitized ) ? null : $sanitized;
```

This brings server-side behaviour in line with the documented contract and
protects against direct API manipulation. The fix is a one-line addition in
an existing utility method with no architectural impact.

---

### LOW — REST Response Over-Exposes Server Metadata

**Location**: `vendor/wpboilerplate/wpb-mcp-servers-list/src/Data/ServerData.php`,
`to_array()`.

**Detail**: The endpoint returns `endpoint_url`, `tools`, `resources`, and
`prompts` for each server. The AbilityForm UI consumes only `id` and `name`.
If the capability filter finding (MEDIUM above) is ever exploited, broader
internal MCP configuration is exposed.

**Within the intended access level** (`manage_options`), the data is appropriate
for site administrators. No secrets or credentials are included in `ServerData`.
This finding is informational within the current permission model.

**Mitigation**: No change required for this feature. Document that the
full server payload is accessible to any consumer of this endpoint, and
confirm no credential or secret material is ever stored in `ServerData`
fields. This should be revisited if the capability filter is ever broadened.

---

## Confirmed Secure Patterns

| Area | Status | Evidence |
|:---|:---:|:---|
| REST authentication — `manage_options` via `current_user_can()` | ✅ SAFE | `RestEndpoint.php` line ~110: strict bool return from `current_user_can($required)` |
| REST method restriction — GET (READABLE) only | ✅ SAFE | `'methods' => \WP_REST_Server::READABLE` in `RestEndpoint::register()` |
| CSRF protection | ✅ SAFE | `@wordpress/api-fetch` sends the WP REST nonce automatically in the admin context; no additional CSRF token needed |
| XSS in checkbox list — `server.name` | ✅ SAFE | React JSX text interpolation (`{server.name}`, `{server.id}`) auto-escapes HTML entities; no `dangerouslySetInnerHTML` in plan |
| XSS via stale IDs — `draftAbility.mcp_servers` | ✅ SAFE | Stale IDs originate from the DB, were sanitized via `sanitize_text_field()` on write; React text rendering escapes them on display |
| Server-side input sanitization — write path | ✅ SAFE | `AcrossAI_Abilities_Sanitizer::sanitize_mcp_servers()` delegates to `sanitize_mcp_servers_array()` + `sanitize_text_field()` per item; existing pipeline unchanged |
| No new trust boundary | ✅ SAFE | Endpoint is read-only; no new input sanitization boundary; write path unchanged |
| No new PHP class / namespace collision | ✅ SAFE | Plugin registers `RestEndpoint::class` string; class originates from Composer-managed vendor with its own namespace |
| Type-safety in toggle handler | ✅ SAFE | `Array.isArray(draftAbility.mcp_servers)` guard before array operations; `Array.includes()` uses strict equality |
| No new JS dependency | ✅ SAFE | Uses existing `@wordpress/api-fetch` and React; no new network dependencies |
| Hook registration pattern (AC-HOOKS-MAIN) | ✅ SAFE | Named variable `$mcp_servers_rest` before `add_action` — complies with Constitution §I |
| No stored capability bypass | ✅ SAFE | Capability is checked per-request in the `permission_callback` closure; no caching of auth decisions |

---

## Trust Boundaries

```
Browser (admin context)
  └── apiFetch (nonce-signed)
        └── WP REST API boundary ──── permission_callback: manage_options
              └── RestEndpoint::get_callback()
                    └── McpServersList::instance() → read-only data
```

- Only authenticated WordPress administrators (`manage_options`) cross the REST
  boundary. Unauthenticated requests receive HTTP 401. Subscribers/editors
  receive HTTP 403.
- The filter `wpb_mcp_servers_list_rest_capability` is the only mechanism that
  could move the trust boundary. See MEDIUM finding above.
- `draftAbility.mcp_servers` state is local to the React component; it reaches
  the server only through the existing abilities write endpoint (already
  sanitized).

---

## Security-Architecture Conflicts

None. The feature is consistent with the established permission model
(`manage_options` for all abilities admin operations), the existing write
pipeline (no changes), and the apiFetch/nonce pattern used elsewhere in the
plugin.

---

## Pre-Implementation Checklist

- [x] Add implementation task note: "Do NOT add filter hook on
  `wpb_mcp_servers_list_rest_capability`; document as external attack surface."
  → **Resolved by T011** (Phase 7): PHP warning comment added directly above the
  `$mcp_servers_rest` line in `includes/Main.php`.
- [x] Apply `[] → null` collapse fix in `sanitize_mcp_servers_array()` or
  `AcrossAI_Abilities_Sanitizer::sanitize_mcp_servers()` before the write
  controller task is completed.
  → **Resolved by T002** (Phase 2, P1-B): `return empty( $sanitized ) ? null : $sanitized;`
  added at the end of `sanitize_mcp_servers_array()`.
- [x] Confirm no `endpoint_url` or other server metadata fields are rendered
  as HTML attributes or links in AbilityForm.jsx (only `id` and `name` as text).
  → **Resolved by T007 specification**: task explicitly constrains the render to
  `server.name` as label and `server.id` as sub-text; no `dangerouslySetInnerHTML`
  in plan; T015 (ESLint) enforces no unsafe JSX patterns.

---

## Task Security Review

**Date**: 2026-05-27
**Reviewer**: GitHub Copilot (automated — speckit.security-review.tasks)
**Tasks reviewed**: T001–T015 in `specs/016-allowed-servers-checkbox-list/tasks.md`

### Executive Summary

T001–T015 cover all material security requirements identified in this document.
No new high-risk or unmitigated findings were discovered during task review.
One informational note is recorded below. Verdict: **PASS**.

### Task Coverage Matrix

| Security Requirement | Coverage | Task(s) |
|:---|:---:|:---|
| RestEndpoint registered with correct hook pattern (AC-HOOKS-MAIN) | ✅ | T001 |
| `[] → null` server-side invariant enforced (P1-B) | ✅ | T002 |
| CSRF — `apiFetch` WP REST nonce (platform guarantee) | ✅ | T005 (uses `apiFetch`) |
| XSS — React JSX auto-escaping for `server.name` / `server.id` | ✅ | T007, T009 |
| XSS — stale IDs rendered as JSX text, not HTML attributes | ✅ | T009 |
| Authorization — `manage_options` capability on read endpoint | ✅ | Vendor; T001 wires it |
| Capability filter warning comment in `Main.php` (P1-A) | ✅ | T011 |
| `endpoint_url` / metadata fields NOT rendered in AbilityForm | ✅ | T007 specification (id + name only) |
| No `dangerouslySetInnerHTML` in plan | ✅ | T007 plan constraint |
| Quality gates: PHPCS, PHPStan L8, ESLint, webpack | ✅ | T012–T015 |
| Data integrity — stale IDs preserved on form load | ✅ | T009 |
| Audit/logging | N/A | Feature is a read-only endpoint + UI toggle; no new write boundary |
| SQL injection | N/A | No direct SQL; mcp_servers write path unchanged |
| CSP | N/A | No dynamic `<script>`/`<style>` injection; no eval; no unsafe-inline |

### Informational Note

**Metadata field verification (pre-implementation checklist item 3)**:
T007's task description specifies rendering only `server.name` and `server.id`.
There is no dedicated verification checkpoint confirming `endpoint_url`, `tools`,
`resources`, and `prompts` are excluded from the rendered HTML. Risk is LOW because
(a) the task specification itself is the constraint, (b) React text interpolation
auto-escapes any unexpected value, and (c) T015 (ESLint) runs before completion.

**Recommendation**: Add one acceptance criterion to T007's checkpoint:
> "Verify in browser DevTools that only `name` and `id` are rendered for each
> server item; `endpoint_url`, `tools`, `resources`, and `prompts` must not
> appear as attribute values or text content in the DOM."

This is informational — it does not block implementation or change the verdict.

### Missing Security Tasks

None. All trust boundary controls, validation invariants, and cross-cutting
concerns are represented in T001–T015 or confirmed as platform guarantees.

### Verdict

✅ **PASS** — T001–T015 cover all known security requirements for Feature 016.

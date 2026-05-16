# Security Constraints: Ability Override Processor (004)

**Reviewed**: 2026-05-16 | **Plan**: [plan.md](plan.md) | **Standard**: OWASP Top 10 2021

---

## Findings Summary

| ID | Severity | Category | Status |
|---|---|---|---|
| SEC-005 | LOW | A08 Integrity — Transient type validation | REQUIRES TASK |
| SEC-006 | INFO | A01 Access Control — PATH A spoofability | DOCUMENTED / ACCEPTABLE |
| SEC-007 | INFO | A01 Access Control — `bust_cache()` public static | DOCUMENTED / ACCEPTABLE |

---

## Finding Detail

### SEC-005 (LOW): Transient output not type-validated before use

**OWASP**: A08 — Software and Data Integrity Failures

**Description**: `load_overrides_cache()` calls `get_transient()` and uses the returned value to
populate the in-memory cache. If the transient value is corrupted (e.g., via a compromised object
cache, a manual `wp_cache_set()`, or database row corruption), the processor could receive an
unexpected type (e.g., `false`, `null`, a non-array scalar) and either throw a PHP error or silently
behave as if there are no overrides.

**Risk**: Low. Exploitation requires write access to the WordPress object cache or the `wp_options`
table (where transients are stored when no persistent object cache is configured). Both require
elevated server access — beyond typical WordPress-level attacks.

**Required Mitigation**: `load_overrides_cache()` MUST validate that `get_transient()` returns
an array before using it. If not an array, treat as cache miss and rebuild from DB.

```php
$cached = get_transient( 'acrossai_ability_overrides_cache' );
if ( ! is_array( $cached ) ) {
    // Cache miss or corrupted — rebuild from DB.
    $cached = null;
}
```

**Task required**: Add to implementation task T004-01 (class implementation). Not a plan blocker.

---

### SEC-006 (INFO): PATH A detection can be "spoofed" by crafted REQUEST_URI

**OWASP**: A01 — Broken Access Control

**Description**: An attacker who can set `$_SERVER['REQUEST_URI']` to contain `acrossai-abilities/`
will receive PATH A treatment (override injection skipped). This means their request would see
unmodified WP registry values instead of DB overrides.

**Why Acceptable**: PATH A is not a privilege escalation path. Skipping override injection does not
grant additional capabilities — it just means the request sees unmodified ability registrations.
All REST endpoint access remains gated by `check_permission()` (manage_options + nonce) which runs
independently. An unauthenticated spoofed request still fails at the permission gate.

**Documentation**: Plan §N-001 already calls this out. No task required. Confirm in code comment.

---

### SEC-007 (INFO): `bust_cache()` is a public static method callable by any code

**OWASP**: A01 — Broken Access Control (partial concern)

**Description**: `bust_cache()` is `public static`, making it callable from any plugin or theme.
A third-party plugin could call `AcrossAI_Ability_Override_Processor::bust_cache()` to trigger
a cache clear, causing a DB query on the next request.

**Why Acceptable**: The worst outcome is an extra DB query (cache miss rebuild). No data is
exposed or modified. `bust_cache()` only deletes a transient and clears a static var. This is a
DoS-class concern only (forced cache rebuild) and is far below the severity threshold for a
remediation task. `bust_cache()` must remain public because it is called from REST controllers
and wired via `add_action`.

**Recommendation**: No code change required. Consider adding `@internal` docblock annotation
noting the method is public by necessity (hook callback + cross-controller call), not part of
the public plugin API.

---

## OWASP Top 10 — Full Sweep

| # | Category | Verdict | Notes |
|---|---|---|---|
| A01 | Broken Access Control | ✅ PASS with INFO | PATH A is not an auth gate. See SEC-006, SEC-007. |
| A02 | Cryptographic Failures | ✅ N/A | No crypto operations. |
| A03 | Injection | ✅ PASS | No raw SQL. `$_SERVER` values used in boolean comparison only, never echoed. DB reads via BerlinDB (parameterised). |
| A04 | Insecure Design | ✅ PASS | PATH A/B intent clearly documented. No security decision delegated to heuristic detection. |
| A05 | Security Misconfiguration | ✅ PASS | No new config surface introduced. Transient key is a constant. |
| A06 | Vulnerable Components | ✅ N/A | No new dependencies. |
| A07 | Identification and Authentication | ✅ N/A | No auth logic in this class. |
| A08 | Software and Data Integrity | ⚠️ LOW | Transient output type-validation missing. See SEC-005. |
| A09 | Security Logging and Monitoring | ✅ N/A | No auth events to log. Cache misses are not security events. |
| A10 | SSRF | ✅ N/A | No outbound HTTP requests. |

---

## Authorization Boundary Notes

This class has NO new authorization boundary. It executes in the context of the current WP request
before any REST route processing. The class itself:
- Makes no capability checks (it operates on pre-validated DB data)
- Issues no nonce checks (it reads from a cached DB layer, not from request input)
- Calls no `current_user_can()` or `wp_verify_nonce()`

This is correct: the authorization boundary for overrides is at the REST write layer
(`AcrossAI_Sitewide_Rest_Controller::check_permission()`), not at the read/inject layer.

---

## Required Action Items

| Task | Severity | Where |
|---|---|---|
| Add `is_array()` guard in `load_overrides_cache()` before consuming transient output | LOW | T004-01 (class implementation) |
| Add `@internal` docblock to `bust_cache()` | INFO | T004-01 |

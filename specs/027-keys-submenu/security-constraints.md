---
document_type: security-review
review_type: plan
assessment_date: 2026-06-06
codebase_analyzed: acrossai-abilities-manager/specs/027-keys-submenu
total_files_analyzed: 6
total_findings: 6
overall_risk: MODERATE
critical_count: 0
high_count: 0
medium_count: 4
low_count: 0
informational_count: 2
owasp_categories: [A03, A04, A05]
cwe_ids: [CWE-20, CWE-116, CWE-285, CWE-400, CWE-269]
field_summaries:
  document_type: "Always 'security-review'. Allows indexers to skip non-review documents."
  review_type: "Which command generated this document: audit, branch, staged, plan, tasks, or followup."
  assessment_date: "ISO 8601 date the review was performed (YYYY-MM-DD)."
  overall_risk: "Highest severity tier with active findings (CRITICAL, HIGH, MODERATE, LOW, INFORMATIONAL)."
  critical_count: "Number of Critical findings (CVSS 9.0-10.0)."
  high_count: "Number of High findings (CVSS 7.0-8.9)."
  medium_count: "Number of Medium findings (CVSS 4.0-6.9)."
  low_count: "Number of Low findings (CVSS 0.1-3.9)."
  informational_count: "Number of Informational findings."
  owasp_categories: "OWASP Top 10 2025 categories (A01-A10) that have at least one finding."
  cwe_ids: "CWE identifiers referenced in this document."
  finding_id: "Unique finding identifier (SEC-NNN) for cross-referencing and task linkage."
  location: "File path and line number of the vulnerable code (path/to/file.ext:line)."
  owasp_category: "OWASP Top 10 2025 category for this finding (AXX:2025-Name)."
  cwe: "Common Weakness Enumeration identifier with short name (CWE-NNN: Name)."
  cvss_score: "CVSS v3.1 base score (0.0-10.0). 9.0+=Critical, 7.0-8.9=High, 4.0-6.9=Medium, 0.1-3.9=Low."
  spec_kit_task: "Spec-Kit task ID for backlog tracking and remediation follow-up (TASK-SEC-NNN)."
---

# Security Review — Plan Review (Revised): Feature 027 Keys Submenu

**Revision**: Second review of revised plan.md. Previous review date: 2026-06-06 (first review).
**Changes since first review**: Dedicated `AbilityAPI` module + `acrossai-abilities-api/v1` namespace; Logger namespace migration to `acrossai-abilities-log/v1`; class prefix changed to `AcrossAI_Ability_API_*`.

## Executive Summary

The revised plan remains well-structured from a security perspective. Three findings from the first review carry forward (SEC-001/002/003). One previous finding (SEC-004: filter timing gap) is **resolved** — plan now specifies `init P99`. One new medium finding (SEC-006) was identified from the revised architecture: the plan does not specify which of the two existing orchestrator patterns (`AcrossAI_Abilities_Rest_Controller` standalone vs. `AcrossAI_Logger_Controller` extending `WP_REST_Controller`) the new AbilityAPI orchestrator should follow, creating permission-check implementation ambiguity. Two informational findings noted.

**Overall Risk: MODERATE** (4 medium findings, 0 high, 0 critical — all behind `manage_options`).

---

## Plan Artifacts Reviewed

| Artifact | Path | Notes |
|---|---|---|
| Feature spec | `specs/027-keys-submenu/spec.md` | Unchanged |
| Implementation plan | `specs/027-keys-submenu/plan.md` | Revised — dedicated AbilityAPI module |
| Memory synthesis | `specs/027-keys-submenu/memory-synthesis.md` | Refreshed |
| Project security constraints | `docs/memory/security-constraints.md` | Existing project-wide rules |
| Abilities REST orchestrator | `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php` | Reference pattern |
| Logger REST orchestrator | `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php` | Divergent pattern |

---

## Vulnerability Findings

### SEC-001 — Config POST Body: No Size or Count Limits Specified *(Carried Forward)*

| Field | Value |
|---|---|
| **Finding ID** | SEC-001 |
| **Severity** | MEDIUM |
| **CVSS Score** | 5.3 |
| **OWASP** | A04:2025-Insecure Design |
| **CWE** | CWE-400: Uncontrolled Resource Consumption |
| **Location** | `plan.md` → Phase 1.2 (AcrossAI_Ability_API_Config::save_config) |
| **Spec-Kit Task** | TASK-SEC-001 |
| **Status** | Open |

**Description**: `AcrossAI_Ability_API_Config::save_config()` has no documented upper bounds on config size.

**Mitigation**: `AcrossAI_Ability_API_Config` MUST define and enforce:
```php
const MAX_KEY_LENGTH = 100;  // after sanitize_key()
const MAX_MAIN_KEYS  = 200;  // array_slice() outer config
const MAX_SUB_KEYS   = 500;  // array_slice() per sub_keys array
```
Pattern mirrors `DEC-MCP-SERVER-SANITIZE` (`sanitize_mcp_servers_array` uses `array_slice(100)`).

---

### SEC-002 — add-on Args Allowlist Not Defined in Plan *(Carried Forward)*

| Field | Value |
|---|---|
| **Finding ID** | SEC-002 |
| **Severity** | MEDIUM |
| **CVSS Score** | 4.8 |
| **OWASP** | A03:2025-Injection |
| **CWE** | CWE-20: Improper Input Validation |
| **Location** | `plan.md` → Phase 1.1 (AcrossAI_Ability_API_Registry::validate_and_normalize) |
| **Spec-Kit Task** | TASK-SEC-002 |
| **Status** | Open |

**Description**: The Registry passes add-on ability args to `wp_register_ability()` without a documented field allowlist.

**Mitigation**: Plan and implementation MUST define:
```php
const ALLOWED_ARGS_FIELDS = [
    'label', 'description', 'category', 'callback', 'callback_type',
    'show_in_rest', 'show_in_mcp',
];
```
Apply `array_intersect_key()` in `validate_and_normalize()` before passing to `wp_register_ability()`.

---

### SEC-003 — React Label Rendering: No Explicit Escape Requirement *(Carried Forward)*

| Field | Value |
|---|---|
| **Finding ID** | SEC-003 |
| **Severity** | MEDIUM |
| **CVSS Score** | 4.2 |
| **OWASP** | A03:2025-Injection |
| **CWE** | CWE-116: Improper Encoding or Escaping of Output |
| **Location** | `plan.md` → Phase 1.5 (React Architecture — MainKeyCard.js) |
| **Spec-Kit Task** | TASK-SEC-003 |
| **Status** | Open |

**Description**: `main_key_label` and `sub_key_label` localized via `window.acrossaiAbilityAPIData` must not be rendered via `dangerouslySetInnerHTML`.

**Mitigation**: Tasks for `MainKeyCard.js` MUST explicitly state: render labels as JSX text content only (`{item.main_key_label}`). Add ESLint rule or comment guard if needed.

---

### SEC-004 — add-on Filter Timing Gap *(RESOLVED)*

| Field | Value |
|---|---|
| **Finding ID** | SEC-004 |
| **Severity** | LOW |
| **Status** | ✅ Resolved — plan D3 specifies `init P99`; add-ons at default P10 are guaranteed to run first |

No action required.

---

### SEC-005 (INFORMATIONAL) — Config GET Exposes Ability Topology *(Carried Forward)*

| Field | Value |
|---|---|
| **Finding ID** | SEC-005 |
| **Severity** | INFORMATIONAL |
| **Status** | Accepted — behind `manage_options`; no remediation required |

---

### SEC-006 — AbilityAPI Orchestrator Permission-Check Pattern Ambiguous *(New)*

| Field | Value |
|---|---|
| **Finding ID** | SEC-006 |
| **Severity** | MEDIUM |
| **CVSS Score** | 4.9 |
| **OWASP** | A05:2025-Security Misconfiguration |
| **CWE** | CWE-285: Improper Authorization |
| **Location** | `plan.md` → Phase 1.4 (AcrossAI_Ability_API_Rest_Controller) |
| **Spec-Kit Task** | TASK-SEC-006 |
| **Status** | Open |

**Description**: The plugin has **two divergent orchestrator patterns**:

1. **Abilities orchestrator** (`AcrossAI_Abilities_Rest_Controller`) — standalone singleton, custom `check_permission()` that explicitly verifies `current_user_can('manage_options')` AND validates `X-WP-Nonce` header via `wp_verify_nonce($nonce, 'wp_rest')`. Returns `WP_Error(403)` on failure.

2. **Logger orchestrator** (`AcrossAI_Logger_Controller`) — extends WordPress core `WP_REST_Controller`, uses a different permission pattern inherited from the WP base class.

The plan calls for a new `AcrossAI_Ability_API_Rest_Controller` but does not specify which pattern to follow. A developer who copies from `AcrossAI_Logger_Controller` would inherit a different permission model; one who copies from `AcrossAI_Abilities_Rest_Controller` gets the correct standalone + explicit nonce check.

**Risk**: If the AbilityAPI orchestrator inherits `WP_REST_Controller` instead of standing alone, its `check_permission()` may not enforce the explicit nonce check, allowing unauthenticated REST clients that satisfy `current_user_can()` but lack a valid nonce to call the config endpoint without CSRF protection.

**Mitigation**: The plan MUST explicitly specify:

> `AcrossAI_Ability_API_Rest_Controller` MUST follow the `AcrossAI_Abilities_Rest_Controller` pattern (standalone singleton, NOT extending `WP_REST_Controller`). Its `check_permission()` MUST implement the identical two-gate check:
> ```php
> public function check_permission( \WP_REST_Request $request ) {
>     if ( ! current_user_can( 'manage_options' ) ) {
>         return new \WP_Error( 'rest_forbidden', ..., array( 'status' => 403 ) );
>     }
>     $nonce = $request->get_header( 'X-WP-Nonce' );
>     if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
>         return new \WP_Error( 'rest_forbidden', ..., array( 'status' => 403 ) );
>     }
>     return true;
> }
> ```

---

### SEC-007 (INFORMATIONAL) — Logger Namespace Migration Creates Orphaned Endpoint *(New)*

| Field | Value |
|---|---|
| **Finding ID** | SEC-007 |
| **Severity** | INFORMATIONAL |
| **OWASP** | A05:2025-Security Misconfiguration |
| **Status** | Accepted — old endpoint returns 404 after migration; no security risk since it was behind `manage_options` |

**Note**: Any external consumer (add-on, third-party tool) calling `GET /acrossai-abilities/v1/logger/logs` will receive 404 after the migration. Consider documenting the namespace change in the plugin changelog. No security remediation required.

---

## Confirmed Secure Patterns

| Pattern | Location | Notes |
|---|---|---|
| `manage_options` + REST nonce (existing) | `AcrossAI_Abilities_Rest_Controller::check_permission()` | Reference implementation for AbilityAPI orchestrator to copy verbatim |
| Capability check on page render | `AbilityAPIMenu::contents()` | `current_user_can('manage_options')` + `wp_die()` |
| `sanitize_key()` for config keys | Plan § D5 | Correct function; strips HTML + limits charset |
| `acrossai_abilities_api_init` at `init P99` | Plan § D3 | SEC-004 resolved — maximum add-on flexibility |
| `file_exists()` asset guard | admin/Main.php modifications | BUG-UNCONDITIONAL-ASSET-INCLUDE addressed |
| `delete_site_option()` inside data gate | uninstall.php | BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE pattern applied |
| Dedicated `acrossai-abilities-api/v1` namespace | Plan § D1 | No wildcard collision with `acrossai-abilities-manager/v1` |
| Logger migration — 5-file atomicity required | Plan § D2 + DEC-LOGGER-NAMESPACE-MIGRATION | Partial migration risk documented and blocked by task constraint |

---

## Required Security Constraints for Implementation

### SC-027-01: Config Sanitizer Limits *(carries forward)*
`AcrossAI_Ability_API_Config` MUST enforce:
- `MAX_KEY_LENGTH = 100` after `sanitize_key()`
- `MAX_MAIN_KEYS = 200` with `array_slice()`
- `MAX_SUB_KEYS_PER_MAIN = 500` with `array_slice()`
- `mode` validated against `['all', 'specific']`; default `'all'` on invalid
- `enabled` / `sub_key` flags cast with `(bool)` — strict (SEC-04)

### SC-027-02: Registry Args Allowlist *(carries forward)*
`AcrossAI_Ability_API_Registry::validate_and_normalize()` MUST:
1. Validate all four manager fields: `main_key`, `main_key_label`, `sub_key`, `sub_key_label`
2. Sanitize `main_key`/`sub_key` with `sanitize_key()` + length limit
3. Sanitize labels with `sanitize_text_field()`
4. Strip all ability args NOT in `ALLOWED_ARGS_FIELDS` via `array_intersect_key()`
5. Guard debug output with `WP_DEBUG_LOG`

### SC-027-03: React Label Safety *(carries forward)*
`MainKeyCard.js` MUST render `main_key_label` and `sub_key_label` as JSX text only — no `dangerouslySetInnerHTML`.

### SC-027-05: AbilityAPI Permission Callback *(carries forward, updated)*
`AcrossAI_Ability_API_Rest_Controller` MUST be a standalone singleton (NOT extending `WP_REST_Controller`). Its `check_permission()` MUST match the two-gate pattern from `AcrossAI_Abilities_Rest_Controller` exactly: `current_user_can('manage_options')` first, then `wp_verify_nonce($nonce, 'wp_rest')`. Sub-controller routes reference `array( AcrossAI_Ability_API_Rest_Controller::instance(), 'check_permission' )`.

### SC-027-06: AbilityAPI Orchestrator Pattern *(new — addresses SEC-006)*
Plan.md MUST add under Phase 1.4: "AbilityAPI REST orchestrator follows the `AcrossAI_Abilities_Rest_Controller` standalone pattern, not the `AcrossAI_Logger_Controller` WP_REST_Controller inheritance pattern." Add this to Task T009 as an explicit implementation constraint.

---

## Memory Hub INDEX.md Row

```text
| specs/027-keys-submenu/security-constraints.md | plan | 2026-06-06 | MODERATE | C:0 H:0 M:4 L:0 | A03,A04,A05 |
```

---
document_type: security-review
review_type: plan
assessment_date: 2026-06-14
codebase_analyzed: acrossai-abilities-manager (Feature 034 plan artifacts)
total_files_analyzed: 5
total_findings: 3
overall_risk: LOW
critical_count: 0
high_count: 0
medium_count: 0
low_count: 2
informational_count: 1
owasp_categories: [A04, A05, A08]
cwe_ids: [CWE-200, CWE-602, CWE-915]
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

# Security Review — Plan: Feature 034 (Remove Allowed Servers + Add Extensibility Hooks)

## Executive Summary

This feature **net-reduces** the plugin's input attack surface: it deletes ~5 sanitization sites, the `mcp_servers` REST argument schema on two write routes, and one schema column. It **adds** five extension hooks (3 JS, 2 PHP) that all operate within the existing WordPress admin trust boundary; none introduce a new trust boundary crossing, network sink, or unauthenticated entrypoint.

**Overall risk: LOW.** Zero critical or high findings. Two LOW findings on hook-contract guardrails (subscriber misuse vectors). One informational note on the documented extension trust model. **No migration is shipped** (plugin not yet launched per FR-011/FR-012) — the prior SEC-INFO-001 finding about `%i` placeholder in migration code is no longer applicable and has been removed.

Per project memory `feedback_skip_permission_callback_audit`, REST `permission_callback` compliance was **not** audited in this review.

## Plan artifacts reviewed

| File | Purpose | Lines |
|---|---|---|
| `specs/034-.../spec.md` | Feature spec, 16 FRs, 3 clarifications | ~270 |
| `specs/034-.../plan.md` | Implementation plan, Constitution gate (1 tracked deviation §VII) | ~135 |
| `specs/034-.../research.md` | 7 Phase-0 decisions with rationale | ~115 |
| `specs/034-.../data-model.md` | Schema removal + hook payload shapes | ~115 |
| `specs/034-.../contracts/extension-hooks.md` | Public contract for 5 hooks + JS global | ~190 |
| `specs/034-.../quickstart.md` | MU smoke-test plugin + 14-item verification | ~125 |
| `specs/034-.../memory-synthesis.md` | Memory-guided constraints | ~870 words |

## Trust-boundary analysis

| Boundary | Crossed by this feature? | Notes |
|---|---|---|
| Anonymous web → REST | **No** — feature only modifies existing authenticated REST args | Removed `mcp_servers` REST arg; remaining auth unchanged |
| Browser → admin form | **No** — only deletes UI and adds in-process hook slots | All form interactions remain `manage_options` + nonce-gated via existing controllers |
| Extension plugin (PHP/JS) → host plugin internals | **Yes — explicitly intentional** | Extensions installed by an admin run in the admin trust boundary (same as any WP plugin). See SEC-INFO-001. |
| Database write paths | **No new sinks added** | No migration shipped; schema column removed via BerlinDB schema definition only |
| Cross-site / multisite | **No** — fresh installs only, no migration needed | Per FR-011/FR-012, no upgrade code runs on multisite installs |

## Vulnerability Findings

### SEC-001 — `acrossai_abilities.form.save_payload` filter has no required-field re-validation guidance (LOW)

- **finding_id**: SEC-001
- **location**: `specs/034-.../contracts/extension-hooks.md` (Filter: `acrossai_abilities.form.save_payload`)
- **owasp_category**: A04:2025 — Insecure Design
- **cwe**: CWE-602: Client-Side Enforcement of Server-Side Security
- **cvss_score**: 3.1 (Low) — vector: AV:N/AC:H/PR:H/UI:R/S:U/C:N/I:L/A:L
- **spec_kit_task**: TASK-SEC-001
- **Description**: The plan documents that a misbehaving extension stripping required fields from the save payload is "the extension's bug" and "this plugin's REST layer continues to validate required fields and will reject malformed requests with the existing error semantics." This is correct — but the implementation MUST verify that **every** required field is enforced server-side, not just on the React side. An extension that strips `slug` from the payload should hit a 400, not a server-side coercion to empty.
- **Why it matters**: If any REST validation rule was historically enforced only by the form (because the form always sent the field), the new filter creates a path to bypass that rule. CWE-602.
- **Recommendation**: During implementation of CHANGE-2 + CHANGE-4, sweep the abilities REST write routes and confirm every field referenced as "required" in `AbilityForm.jsx` validation has a matching `'required' => true` (or equivalent) in `AcrossAI_Abilities_Write_Controller.php` arg schema. If gaps exist, fix the controller — not the filter.
- **Effort**: < 30 min review during implementation.

### SEC-002 — `acrossai_abilities_admin_localize_data` filter lacks data-minimization guidance for subscribers (LOW)

- **finding_id**: SEC-002
- **location**: `specs/034-.../contracts/extension-hooks.md` (Filter: `acrossai_abilities_admin_localize_data`)
- **owasp_category**: A05:2025 — Security Misconfiguration
- **cwe**: CWE-200: Exposure of Sensitive Information to an Unauthorized Actor
- **cvss_score**: 3.7 (Low) — vector: AV:N/AC:H/PR:L/UI:R/S:U/C:L/I:N/A:N
- **spec_kit_task**: TASK-SEC-002
- **Description**: The contract document tells extensions they can add keys to `window.acrossaiAbilitiesManager` via this filter. It does not warn that values added become readable by **every** script running on that admin page — including third-party admin plugins, browser extensions running as content scripts, and any XSS payload that survives WP's escaping. A subscriber that accidentally adds API keys, user PII, or DB-internal IDs has just exposed them.
- **Why it matters**: Extension authors might naively dump server-side data (e.g., a full server config) into the global. This is a contract-documentation issue, not a flaw in this plugin's code.
- **Recommendation**: Add a security advisory to the `acrossai_abilities_admin_localize_data` filter section in `contracts/extension-hooks.md` (and the corresponding inline PHP comment at the filter callsite) explicitly noting: (a) values are exposed in a browser-accessible JS global, (b) subscribers MUST data-minimize and MUST NOT include secrets, API tokens, hashed credentials, or user PII beyond what is strictly required for UI rendering. Suggest namespace-prefixed keys (already in contract — reinforce why).
- **Effort**: ~10 min documentation edit. Apply during implementation of CHANGE-5.

### SEC-INFO-001 — Extension trust model is implicit, not documented (INFORMATIONAL)

- **finding_id**: SEC-INFO-001
- **location**: `specs/034-.../contracts/extension-hooks.md` (overall)
- **owasp_category**: A08:2025 — Software & Data Integrity Failures
- **cwe**: CWE-915: Improperly Controlled Modification of Dynamically-Determined Object Attributes
- **cvss_score**: 0.0 (Informational)
- **spec_kit_task**: TASK-SEC-INFO-001
- **Description**: The contract document describes how extensions subscribe to the hooks but does not state the implicit trust model: **extensions are as trusted as any other WordPress plugin installed on the site**. They run in the same PHP process and same browser context as the host plugin. A malicious extension can already do anything a WP plugin can do (modify DB, exfiltrate data, escalate privileges) — the new hooks do not expand this attack surface, but they make extension authoring easier and might invite more third-party extensions, increasing supply-chain exposure.
- **Why it matters**: This is **not a vulnerability**. It is a documentation gap that could mislead future readers into thinking the hook surface needs sandboxing or capability checks. It does not, but the absence of that statement may invite "let's add a permission check on the filter" PRs that would be wrong-headed.
- **Recommendation**: Add a one-paragraph "Trust model" section to `contracts/extension-hooks.md` stating: extensions execute in the admin trust boundary; the hooks add no sandbox; subscribers are trusted as much as any installed WP plugin; this plugin does NOT and will NOT add capability or nonce checks at the hook callsites (because that would falsely imply isolation that does not exist). This is purely documentation — no code change.

## Confirmed Secure Patterns

| Pattern | Where confirmed | Why secure |
|---|---|---|
| **Input-surface reduction** | spec.md FR-001 / FR-002 / FR-003; plan.md §IV row | Deletes `sanitize_mcp_servers_array()`, two REST arg schema entries, and one persistent column. Silent acceptance of obsolete `mcp_servers` field (FR-003) is defensive — it ignores rather than reflecting/persisting unknown input. |
| **No migration code shipped** | research.md "Decision: No upgrade migration shipped"; data-model.md "No upgrade migration shipped"; spec FR-011/FR-012 | Plugin not yet launched; eliminates a whole class of migration-related risk (SQL injection in DDL, multisite race conditions, %i placeholder regressions, idempotency bugs, BerlinDB version-bump misuse) by simply not adding the code. Dev installs manually drop the table — a contained, observable action. |
| **No new authenticated endpoint** | All artifacts | Feature only deletes REST args and adds in-process hooks; no new routes, no new permission boundaries. |
| **Public contract for hook + global names** | spec.md FR-010; contracts/extension-hooks.md | Pinning the 5 hook names, 4 context-object keys, and the `window.acrossaiAbilitiesManager` global as contract prevents the BUG-PROTECTED-SLUGS-PATTERN class of silent-rename regressions for downstream extensions. |
| **`@wordpress/hooks` over custom event bus** | research.md Decision 2 | Uses WordPress-native primitive; no new code surface to test for hook delivery; extension authors use familiar `wp.hooks.addFilter` API. |
| **`wp_add_inline_script` placement** | research.md Decision 4; quickstart.md | Confirms via `BUG-WP-LOCALIZE-SCRIPT-RENDER` lesson that data injection happens in `enqueue_scripts()`, not `render()`. Inline `'before'` position ensures the global is populated before the bundle module-level code runs. |
| **Memory-aligned approach** | memory-synthesis.md | Plan honors SEC-02 (sanitization preserved on remaining fields), SEC-03 (multisite per-site migration), DEC-PLUGIN-CHECK-PRODUCTION-SURFACE (`%i` placeholder), and 5 known JSX-edit bug patterns. |

## Memory Hub INDEX.md Row

```text
| specs/034-remove-allowed-servers-add-extensibility-hooks/security-constraints.md | plan | 2026-06-14 | LOW | C:0 H:0 M:0 L:2 | A04,A05,A08 |
```

## Action Plan & Next Steps

1. **Apply LOW findings during implementation** (no blocking remediation needed pre-implementation):
   - SEC-001: ~30 min REST args audit during US1 (verify required fields enforced server-side).
   - SEC-002: ~10 min docstring + contract addendum during US2 (data-minimization warning).
2. **Apply INFORMATIONAL during implementation**:
   - SEC-INFO-001: add one-paragraph "Trust model" section to `contracts/extension-hooks.md` — pure documentation.
3. **Durable memory preservation**: No systemic new vulnerability classes identified; no `/speckit-memory-md-capture` invocation needed at this stage. If implementation surfaces a new pattern (e.g., a reusable "hook-callsite security comment" idiom), capture post-implementation via `/speckit-memory-md-capture-from-diff`.
4. **Remediation planning**: No critical/high findings — `/speckit-security-review-followup` is NOT required. Findings are tracked inline in this document and should be applied during normal implementation, not as separate tickets.
5. **Proceed to architecture review**: orchestrator's next step.

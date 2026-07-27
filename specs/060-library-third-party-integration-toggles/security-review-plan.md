---
document_type: security-review
review_type: plan
assessment_date: 2026-07-26
codebase_analyzed: acrossai-abilities-manager (Feature 060 plan artifacts)
total_files_analyzed: 6
total_findings: 5
overall_risk: LOW
critical_count: 0
high_count: 0
medium_count: 0
low_count: 2
informational_count: 3
owasp_categories: [A01:2025, A05:2025, A09:2025]
cwe_ids: [CWE-285, CWE-703, CWE-778, CWE-345]
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

# Security Review — Feature 060 Plan

## Executive Summary

Feature 060 introduces a reusable third-party integration toggle pattern for the AcrossAI Ability Library page, plus one concrete Advanced Custom Fields (ACF) subclass. The design is **secure-by-default** and reuses existing sanitised inputs, an existing authenticated REST route, and an existing option storage layer. The plan explicitly adds a filterable capability check (`acrossai_integration_toggle_capability`) that raises — never lowers — the authorization floor.

**Overall risk: LOW.** No critical, high, or moderate findings. Two low-severity findings relate to (a) fail-open behaviour if a third-party subclass's `enable_filter()` throws, and (b) confusable-symbol risk in the ACF plugin-detection check. Three informational findings recommend defensive improvements around denial logging, unreachable-execute-callback verification, and post-sanitization data flow to the capability filter.

The plan is **safe to proceed to implementation** provided the two low findings are addressed either now (during implementation) or as tracked follow-ups. The three informational findings are non-blocking.

## Plan Artifacts Reviewed

| # | Artifact | Location | Purpose |
|---|----------|----------|---------|
| 1 | `plan.md` | `specs/060-library-third-party-integration-toggles/plan.md` | Implementation plan with Constitution Check & Complexity Tracking |
| 2 | `spec.md` | `specs/060-library-third-party-integration-toggles/spec.md` | Business requirements (FR-001…FR-016, SC-001…SC-006, 4 user stories) |
| 3 | `security-constraints.md` | `specs/060-library-third-party-integration-toggles/security-constraints.md` | Inline security review from `/speckit-architecture-guard-governed-plan` |
| 4 | `memory-synthesis.md` | `specs/060-library-third-party-integration-toggles/memory-synthesis.md` | Constraint map (Constitution §I Boot Flow, SEC-03 multisite, PATTERN-ADDON-FILTER-LATE-INIT) |
| 5 | `checklists/requirements.md` | `specs/060-library-third-party-integration-toggles/checklists/requirements.md` | Spec quality gate (16/16 pass) |
| 6 | Planning doc | `docs/planning/060-library-third-party-integration-toggles.md` | Original technical design |

Cross-referenced against:
- `.specify/memory/CONSTITUTION.md` v1.4.8 (§I, §II, §IV, §V, §VI, §VII)
- `docs/memory/INDEX.md` (60+ entries scanned; 6 decisions, 4 architecture constraints, 2 accepted deviations, 2 security constraints, 3 bug patterns applied)
- `docs/memory/security-constraints.md` (SEC-03, SEC-04)

## Vulnerability Findings

### SEC-001 — Base class does not isolate third-party subclass exceptions in `enable_filter()`

- **Finding ID**: SEC-001
- **Location**: `includes/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base.php` — `maybe_enable()` callback (to be authored during implementation)
- **OWASP Category**: A05:2025 Security Misconfiguration
- **CWE**: CWE-703: Improper Check or Handling of Exceptional Conditions
- **CVSS Score**: 3.1 (Low) — AV:L / AC:H / PR:H / UI:N / S:U / C:N / I:N / A:L
- **Spec-Kit Task**: TASK-SEC-001

**Description**: The plan says the base class calls the subclass's `enable_filter()` at `plugins_loaded` P20 when the toggle is on. If a third-party integration subclass's `enable_filter()` throws — because the target plugin's public API changed unexpectedly, because a helper the subclass calls fails, or because of a downstream dependency error — the exception will bubble up during the `plugins_loaded` hook and can fatally break every admin page load and REST request for the site, not just the Library page. Constitution §V Integration Resilience explicitly forbids optional integrations from throwing fatals or producing broken UIs when absent.

**Remediation**:
1. Wrap the `$this->enable_filter()` call in `maybe_enable()` in a try/catch (or a set_error_handler for pre-PHP-7 legacy environments, but PHP 8.1+ minimum makes catch sufficient).
2. On exception, log via `error_log` behind the standard `PATTERN-WP-DEBUG-LOG-GUARD` (WP_DEBUG_LOG guard) and fail closed (do not attach the filter, do not surface the card in an unusable state).
3. Add a PHPUnit test that constructs a subclass whose `enable_filter()` throws and asserts (a) no exception propagates, (b) no subsequent `plugins_loaded` callback observes a broken state.

**Reference**: Constitution §V Integration Resilience; `BUG-EXTERNAL-PACKAGE-CTOR-SILENT` (`docs/memory/BUGS.md`).

---

### SEC-002 — ACF plugin-detection check is subject to symbol collision spoofing

- **Finding ID**: SEC-002
- **Location**: `includes/Abilities/Integrations/ACF.php` — `is_plugin_active()` (to be authored during implementation)
- **OWASP Category**: A05:2025 Security Misconfiguration
- **CWE**: CWE-345: Insufficient Verification of Data Authenticity
- **CVSS Score**: 2.6 (Low) — AV:L / AC:H / PR:H / UI:N / S:U / C:N / I:L / A:N
- **Spec-Kit Task**: TASK-SEC-002

**Description**: The plan specifies `is_plugin_active()` returns `class_exists( 'ACF' ) || defined( 'ACF_VERSION' )`. Either predicate is a global-namespace symbol that any other plugin could define with the same name. A malicious or careless plugin that declared a class or constant named `ACF` / `ACF_VERSION` would cause our integration to (a) render the "Acf" tab, (b) attach `add_filter( 'acf/settings/enable_acf_ai', '__return_true' )` on `plugins_loaded` when the admin toggles on — a filter that is a no-op if real ACF is absent (fail-safe), but the UI would still mis-represent that ACF is available. Low likelihood, low impact (no code execution, no data exposure), but worth hardening.

**Remediation**:
1. Prefer a compound check that verifies BOTH a class AND a stable function/method that indicates ACF is initialised: e.g. `defined( 'ACF_VERSION' ) && function_exists( 'acf_get_setting' )`.
2. Document the exact detection check in the ACF subclass docblock with a "spoofing considered" note so future subclass authors follow the pattern.

**Reference**: `docs/memory/BUGS.md` (defensive plugin-detection precedents in `PATTERN-ADMIN-NOTICE-SELF-CONTAINED`).

---

### SEC-003 — Missing extension point for capability-check denials (Informational)

- **Finding ID**: SEC-003
- **Location**: `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` — write path (to be modified during implementation)
- **OWASP Category**: A09:2025 Security Logging and Monitoring Failures
- **CWE**: CWE-778: Insufficient Logging
- **CVSS Score**: 0.0 (Informational)
- **Spec-Kit Task**: TASK-SEC-003 (optional)

**Description**: When a stricter site raises `acrossai_integration_toggle_capability` to something like `manage_network_options`, a `manage_options`-only user who tries to flip the toggle receives a 403 with no site-side audit trail. Sites that require compliance logging cannot record denied integration-toggle attempts without amending core code.

**Recommendation** (non-blocking): fire `do_action( 'acrossai_integration_toggle_denied', $integration_slug, $required_capability, get_current_user_id() )` immediately before returning the 403. Sites that need audit logging can hook it. Cost: 1 line + a docblock.

---

### SEC-004 — Synthetic definition rows' placeholder `execute_callback` is not exercised by any test (Informational)

- **Finding ID**: SEC-004
- **Location**: `includes/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base.php` — `push_definition()` (to be authored during implementation)
- **OWASP Category**: A01:2025 Broken Access Control
- **CWE**: CWE-285: Improper Authorization
- **CVSS Score**: 0.0 (Informational)
- **Spec-Kit Task**: TASK-SEC-004 (optional)

**Description**: The planning doc CHANGE-1 notes that synthetic definition rows include a "placeholder `execute_callback` returning `WP_Error` if invoked, since these rows are display-only". If any downstream consumer somehow reaches the execute path for a synthetic row (unlikely given the Registry-Processor split, but not impossible), the fail-closed `WP_Error` is the correct behaviour. The plan does not include a test that verifies this fail-closed path is actually reached rather than accidentally shadowed by the real target-plugin ability of the same name.

**Recommendation** (non-blocking): add a single PHPUnit assertion that invoking `execute_callback` on a synthetic row returns `WP_Error`. Ensures the fail-closed contract stays enforced if implementation details change later.

---

### SEC-005 — Capability filter data flow needs to be post-sanitization (Informational)

- **Finding ID**: SEC-005
- **Location**: `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` — write path (to be modified during implementation)
- **OWASP Category**: A01:2025 Broken Access Control
- **CWE**: CWE-285: Improper Authorization
- **CVSS Score**: 0.0 (Informational)
- **Spec-Kit Task**: TASK-SEC-005 (optional)

**Description**: The plan and the security-constraints doc agree that the capability filter is called with `$integration_slug` from the incoming save payload. The plan does not explicitly state whether the slug passed to `apply_filters('acrossai_integration_toggle_capability', 'manage_options', $slug)` is the raw POST key or the post-`sanitize_key_field()` value. Passing the raw value could let a crafted payload smuggle non-canonical strings to the filter (e.g. `"acf\0admin"` or Unicode homoglyphs), potentially confusing site-side filter logic that switches on the slug.

**Recommendation** (non-blocking): explicitly document — and test — that the slug value passed to the filter is the sanitised, post-`sanitize_key_field()` value. Add one PHPUnit case that submits a payload with a non-canonical key and asserts the filter receives the sanitised form.

## Confirmed Secure Patterns

The plan already applies these secure-by-default patterns correctly:

- **Server-side authorization is the source of truth.** The capability check runs on the REST write path (`AZ-01` in `security-constraints.md`), not merely in the JavaScript UI. Confirmed by FR-016 and plan's Constitution Check §IV.
- **Filter can only *raise* the capability requirement, never lower it.** The default is `manage_options` (the existing floor). A misconfigured filter returning an unknown or empty string fails closed via `current_user_can()` returning false (`AZ-02`). No fail-open path exists.
- **Input sanitisation reuses the audited existing helper.** `card_variant` and `integration_slug` go through `AcrossAI_Ability_Library_Config::sanitize_key_field()` at the Registry boundary — same treatment established for `tab_group` in Feature 037.
- **`is_plugin_active()` per-request, never cached.** `TB-02` and plan's Boot Flow section explicitly require per-request evaluation. This prevents stale UI leaking after a deactivation.
- **No new REST endpoint.** Reuses the existing `check_permission`-gated route, inheriting `X-WP-Nonce` verification and the `manage_options` floor.
- **No new option keys.** State reuses `acrossai_library_config` with no shape change; `DI-02` confirms no unauthenticated-read leak is introduced.
- **Multisite scope asymmetry is documented, not accidental.** `DI-01` and spec Edge Case both explicitly capture that toggle state is network-wide while UI visibility is per-site.
- **Zero suppressions planned.** No `phpcs:ignore`, no Plugin Check ignore, no PHPStan `@phpstan-ignore` added by this feature.
- **Integration defaults OFF.** FR-008 and SC-003 make explicit-opt-in the safety property. Aligned with WordPress admin conventions for AI-related capability toggles.
- **Constitution §V Integration Resilience is called out in plan.** Target-plugin absence is a first-class edge case (FR-004, FR-012, FR-013).

## Action Plan & Next Steps

### Blocking gate before implementation

None. The two low-severity findings (SEC-001, SEC-002) are correctness-and-hardening items best addressed *during* implementation, not blockers to starting it. They should be captured as `TASK-SEC-001` and `TASK-SEC-002` in `tasks.md` and marked required-for-merge.

### Recommended follow-ups (non-blocking)

- SEC-003, SEC-004, SEC-005 as informational tasks. If the team prefers to defer, document them in the feature's follow-up log rather than dropping them entirely.

### Durable Memory Preservation

Deferred per user preference for manual invocation of speckit commands (`user_runs_speckit_commands` memory). **No new systemic patterns** were identified by this security review that would justify a durable capture — the findings are feature-local hardening items, not repeatable architectural decisions. If SEC-001's try/catch pattern turns out to be adopted by additional integration subclasses in the future, promoting it to a new `PATTERN-INTEGRATION-ENABLE-FAIL-CLOSED` entry would be justified at that time. For this feature alone, capture is not required.

### Remediation planning

If the team decides SEC-001 or SEC-002 should be tracked as follow-up work rather than resolved during Feature 060 implementation, run `/speckit-security-review-followup` to convert them into remediation tasks. Otherwise, they flow into the upcoming `/speckit-tasks` output as `TASK-SEC-001` / `TASK-SEC-002` inline.

---

## Memory Hub INDEX.md Row

```text
| specs/060-library-third-party-integration-toggles/security-review-plan.md | plan | 2026-07-26 | LOW | C:0 H:0 M:0 L:2 | A01,A05,A09 |
```

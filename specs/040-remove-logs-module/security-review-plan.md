---
document_type: security-review
review_type: plan
assessment_date: 2026-07-01
codebase_analyzed: acrossai-abilities-manager (specs/040-remove-logs-module)
total_files_analyzed: 6
total_findings: 4
overall_risk: INFORMATIONAL
critical_count: 0
high_count: 0
medium_count: 0
low_count: 0
informational_count: 4
owasp_categories: [A01, A05, A09]
cwe_ids: [CWE-284, CWE-778, CWE-1053]
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

# Security Review — Plan: Remove the Logger module (Feature 040)

## Executive Summary

Feature 040 is a **full-module removal** with **no in-plugin replacement**. Logging moves to a future companion plugin outside this repository. The net effect on this plugin's attack surface is a **reduction**: one authenticated REST namespace (`acrossai-abilities-log/v1`), one admin submenu (`acrossai-abilities-logs`), one Settings API field (`log_retention_days`), one `permission_callback` wrapper (at priority 100001), one custom DB table (`{prefix}acrossai_ability_logs`), and one Action Scheduler job (`acrossai_ability_logger_cleanup`) all going away. **No new attack surface is introduced.** No new user input, no new nonces, no new capabilities, no new auth boundaries.

Data-strategy follows Feature 039's precedent: legacy Logger artifacts (table + two options + AS queue entries) are **orphaned on upgraded installs** and dropped only when the admin opts into the existing `acrossai_abilities_uninstall_delete_data` gate. External integrations polling the removed REST namespace receive standard WordPress 404 responses.

**Overall risk: INFORMATIONAL.** All four findings are advisory — verification steps and communication requirements, not exploitable weaknesses. Zero Critical/High/Medium/Low findings.

**Plan vs. reality**: `security-constraints.md` (produced during the governed-plan orchestration) is the lightweight working companion to this formal artifact. Both documents converge on the same verdict.

## Plan Artifacts Reviewed

| File | Role | Read |
|---|---|---|
| `specs/040-remove-logs-module/spec.md` | Feature specification (3 user stories, 12 FRs, 8 SCs, 5 edge cases, 5 entities) | ✓ |
| `specs/040-remove-logs-module/plan.md` | Implementation plan (Constitution Check 13/13 PASS + 1 governance amendment; zero new deviations; Complexity Tracking limited to the PATCH-level Constitution amendment) | ✓ |
| `specs/040-remove-logs-module/memory-synthesis.md` | Memory synthesis (5 patterns, 2 active decisions, 4 decisions pending Superseded, 3 bug-pattern guards, 2 worklog precedents, 1 soft conflict resolved) | ✓ |
| `specs/040-remove-logs-module/security-constraints.md` | Inline security notes from governed-plan orchestration (lightweight predecessor to this formal review; 6 trust boundaries analyzed) | ✓ |
| `docs/planning/040-remove-logs-module.md` | Author's detailed TASK-1 through TASK-8 implementation breakdown with per-task manual verification checklists | ✓ |
| `.specify/memory/CONSTITUTION.md` v1.4.7 | Constitution (§I Modular Architecture, §II WordPress Standards, §IV Security First, §V Integration Resilience, §Governance amendment procedure) | ✓ |
| `docs/memory/INDEX.md` | Memory routing map — `PATTERN-MODULE-DECOMMISSION`, `PATTERN-UNINSTALL-DATA-GATE`, `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`, `BUG-INVENTORY-GREP-MISS`, `DEC-FAIL-OPEN-NOTICE`, `DEC-VARIADIC-CALLBACK-WRAP` (pending Superseded), `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS`, `BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE` | ✓ |

**Not present** (not required for a removal feature): `research.md`, `data-model.md`, `quickstart.md`, `contracts/`, `.specify/memory/security_constitution.md`.

## Vulnerability Findings

### SEC-001 — INFORMATIONAL: Verify P100001 `permission_callback` wrapper removal doesn't re-expose historical permission-bypass bugs

- **Finding ID**: SEC-001
- **Location**: `includes/Main.php:400-412` (Logger boot block deleted in TASK-3); target auth surface = every ability's `permission_callback` post-Feature 040
- **OWASP Category**: A01:2025 Broken Access Control (adjacent)
- **CWE**: CWE-284: Improper Access Control (verification-only; not a present defect)
- **CVSS v3.1 Score**: 0.0 (verification requirement; no active vulnerability)
- **Spec-Kit Task**: TASK-SEC-001

**Description**: TASK-3 removes `add_filter( 'wp_register_ability_args', $logger, 'wrap_permission_callback', 100001, 2 )`. The wrapper (`DEC-VARIADIC-CALLBACK-WRAP`) intercepted every registered ability's `permission_callback` at priority 100001 and logged denials. **The wrapper did NOT add or modify authorization** — it invoked the underlying callback verbatim. Its removal should therefore be auth-neutral.

However, two historical bugs relate to permission handling around abilities:
- `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` (Feature 032; HIGH-severity fix) — `inject_mcp_tools()` must call `check_permissions()` as a 4th gate because AC rules are fail-open.
- `BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE` (Feature 028) — `permission_callback` returning `WP_REST_Response` is truthy, silently granting access.

Neither bug lives in the Logger; both were fixed in the Abilities module and elsewhere. But the P100001 wrapper's proximity to `permission_callback` handling warrants explicit verification that no downstream consumer accidentally depended on the wrapper for observability of a bypass condition.

**Verification**: As part of US1's post-TASK-3 acceptance:
- PHPStan L8 must remain clean (would catch dangling `use` statements referring to the deleted wrapper).
- `grep -rEn 'wrap_permission_callback|BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS|BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE' includes/ admin/ src/` should return references only inside memory-file docblocks (never inside runtime code).
- Manual: on a test install, save an Access Control rule that denies a subscriber → attempt to execute the ability as that subscriber → confirm the AC denial still fires. The Feature 032 4-gate check + Feature 028 return-type check should both still gate.

**Status**: **OPEN — advisory verification required post-TASK-3.**

---

### SEC-002 — INFORMATIONAL: TASK-5 uninstall additions must stay inside the opt-in gate

- **Finding ID**: SEC-002
- **Location**: `uninstall.php` (existing `if ( $acrossai_delete_data ) { ... }` block; TASK-5 adds three new lines inside it)
- **OWASP Category**: A05:2025 Security Misconfiguration
- **CWE**: CWE-1053: Missing Documentation for Design (defensive-coding subclass)
- **CVSS v3.1 Score**: 0.0 (governance verification; not a present defect)
- **Spec-Kit Task**: TASK-SEC-002

**Description**: TASK-5 introduces three new destructive operations to `uninstall.php`:
1. `DROP TABLE {prefix}acrossai_ability_logs` (via `$wpdb->prepare('DROP TABLE IF EXISTS %i', ...)` — safe `%i` identifier placeholder per Constitution §II).
2. `\delete_option( 'acrossai_ability_logs_db_version' )`.
3. `as_unschedule_all_actions( 'acrossai_ability_logger_cleanup' )` — guarded by `function_exists()` to remain safe when Action Scheduler is inactive.

All three MUST land INSIDE the existing `if ( $acrossai_delete_data ) { ... }` block. Placing them outside the gate would trigger `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE` — the plugin would wipe user data on every uninstall regardless of the admin's opt-in preference. This is a data-integrity/user-trust concern, not a technical exploit.

**Verification**: Post-TASK-5 diff review must confirm the exact indentation and containment. Automated check: `git diff main..HEAD -- uninstall.php` shows all additions bounded by the pre-existing gate braces.

**Status**: **OPEN — advisory diff verification required post-TASK-5.**

---

### SEC-003 — INFORMATIONAL: Removal reduces observability of permission_callback denials

- **Finding ID**: SEC-003
- **Location**: `includes/Main.php:400-412` (Logger boot deleted in TASK-3)
- **OWASP Category**: A09:2025 Security Logging and Monitoring Failures
- **CWE**: CWE-778: Insufficient Logging
- **CVSS v3.1 Score**: 0.0 (observability reduction by design; not a defect)
- **Spec-Kit Task**: TASK-SEC-003

**Description**: The removed P100001 `permission_callback` wrapper wrapped every ability's callback to log denials into the `{prefix}acrossai_ability_logs` table (source enum: `permission_denied`). Post-Feature 040, denials fire but are not logged anywhere by this plugin. This is a deliberate observability reduction — the entire logging feature is being extracted.

Site owners who used the Logger for security monitoring (e.g., alerting on ability-denial spikes) lose that signal. They MUST either (a) wait for the future companion plugin, (b) install a third-party WordPress activity-logger that hooks the same events, or (c) roll their own consumer via the still-available upstream hooks.

**Mitigation**: FR-011 release notes MUST explicitly flag this loss for site owners. Recommend adding a specific line: "Ability execution denials (previously logged to the Logs page) are no longer recorded by this plugin. If you rely on this signal, install a compatible logging plugin or hook `wp_after_execute_ability` directly."

**Verification**: US1 walkthrough confirms the Logs page is gone. Release-note review confirms the observability-loss disclosure is present.

**Status**: **OPEN — advisory release-note enhancement recommended.**

---

### SEC-004 — INFORMATIONAL: External REST clients + admin bookmarks 404 post-upgrade

- **Finding ID**: SEC-004
- **Location**: `README.txt` Unreleased changelog + Upgrade Notice (per TASK-8)
- **OWASP Category**: A05:2025 Security Misconfiguration (governance)
- **CWE**: CWE-1053: Missing Documentation for Design
- **CVSS v3.1 Score**: 0.0 (communication requirement; not a technical defect)
- **Spec-Kit Task**: TASK-SEC-004

**Description**: Post-Feature 040:
- Any external integration polling `GET /wp-json/acrossai-abilities-log/v1/logger/logs` receives 404.
- Any admin who bookmarked `wp-admin/admin.php?page=acrossai-abilities-logs` receives the standard WordPress "page does not exist" admin response.

Both are by-design outcomes (per Feature 038's precedent for removed URLs), but they represent break points for previously-working integrations. Silent 404s can be interpreted as service outages by naïve external monitoring tools. Release-note communication MUST cover both cases so integration owners can update.

**Verification**: TASK-8 release-note review MUST confirm both bullets are present:
- Explicit mention of the REST namespace 404.
- Explicit mention of the admin URL 404.
- Manual cleanup SQL for admins who want the orphaned table + option keys purged without opt-in uninstall.

**Status**: **OPEN — advisory release-note completeness check.**

## Confirmed Secure Patterns

The plan preserves the following pre-existing secure patterns without regression:

| Pattern | Location | Preserved by |
|---|---|---|
| **Fail-open admin notice with `manage_options` gate** (`DEC-FAIL-OPEN-NOTICE`) | `includes/Main.php:335-349` (unchanged by Feature 040) | SC-006 explicit acceptance criterion |
| **Uninstall data gate** (`PATTERN-UNINSTALL-DATA-GATE`, `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`) | `uninstall.php:22-46` (existing `if ( $acrossai_delete_data )` block) | TASK-5 explicit constraint; SEC-002 verification |
| **`$wpdb->prepare('DROP TABLE IF EXISTS %i', ...)` for schema identifiers** | `uninstall.php:29-31` (existing pattern; TASK-5 adds one more line matching the shape) | TASK-5 example code embeds `%i` |
| **Access Control panel + REST endpoints (Feature 039)** | `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` + vendor library — untouched by Feature 040 | Constitution Check row §I Module Contract; US1 acceptance scenario 3 |
| **Vendor-package `class_exists` guards** (`DEC-EXTERNAL-PACKAGE-HOOK-CTOR`) | `includes/Main.php:324` (AddonsPage) — untouched | Unchanged in diff |
| **Multisite per-site prefix on remaining tables** (SEC-03) | Abilities table + `abilities_access_control` table both continue with BerlinDB default `$global = false` | FR-009 (other tables unaffected) |
| **PSR-4 namespace conformance** (§I Namespace Rule) | Post-Feature 040, all remaining classes follow `AcrossAI_Abilities_Manager\...` root; Logger classes are the only PSR-4 map entries removed | TASK-4 followed by `composer dump-autoload` |
| **Constitution amendment procedure with SYNC IMPACT REPORT** (`PATTERN-CONSTITUTION-SYNC-REPORT`) | `.specify/memory/CONSTITUTION.md` PATCH bump v1.4.7 → v1.4.8 in TASK-8 | Complexity Tracking (`plan.md`) mandates the SYNC IMPACT REPORT block |

## Supply-Chain Audit

**No composer manifest change.** Feature 040 does not add, remove, bump, or pin any external dependency. No supply-chain risk introduced.

Vendor packages continue at their Feature 039 versions:
- `wpboilerplate/wpb-access-control ^2.0.0`
- `acrossai-co/main-menu ^0.0.8`
- `automattic/jetpack-autoloader ^5.0`
- `berlindb/core ^3.0.0`
- `freemius/wordpress-sdk ^2.0` (transitive)

The removal of the Logger's REST namespace does not affect any of these packages. `composer dump-autoload` (run at the end of TASK-4) regenerates the PSR-4 classmap with the Logger entries dropped — no vendor code changes.

## Action Plan & Next Steps

1. **Address SEC-001 through SEC-004**: **Optional / non-blocking**. All four are verification/communication requirements that fold into existing tasks (TASK-3 verification, TASK-5 diff review, TASK-8 release notes). No new task or code change required beyond what `docs/planning/040-remove-logs-module.md` already schedules.
2. **Merge decision**: **PASS** at planning phase. Zero Critical/High/Medium/Low findings. Overall risk INFORMATIONAL.
3. **Post-merge follow-up (optional)**: If a future feature reintroduces logging in a different form (e.g., a lightweight event bus for the Abilities module), reassess whether SEC-003's observability-loss disclosure still applies.
4. **Durable Memory Preservation**: **NOT triggered.** No systemic vulnerabilities. No new reusable security pattern surfaced. The "verify wrapper removal doesn't re-expose historical bugs" reasoning (SEC-001) is a specific instance of the general `PATTERN-HELPER-DELETION-GREP-FIRST` protocol already captured in memory — no new pattern to add. The "release-note communication for removed endpoints" concern (SEC-004) is a specific instance of the general `DEC-REVALIDATE-SECURITY-POST-UPGRADE` philosophy — no new pattern to add.

---

## Memory Hub INDEX.md Row

```text
| specs/040-remove-logs-module/security-review-plan.md | plan | 2026-07-01 | INFORMATIONAL | C:0 H:0 M:0 L:0 I:4 | A01,A05,A09 |
```

Paste under the "Security Reviews" table in `docs/memory/INDEX.md` (append after the Feature 039 staged-review row).

---
document_type: security-review
review_type: plan
assessment_date: 2026-07-13
codebase_analyzed: acrossai-abilities-manager (Feature 046 — Absorb Core Abilities Companion Into Manager)
total_files_analyzed: 5
total_findings: 5
overall_risk: INFORMATIONAL
critical_count: 0
high_count: 0
medium_count: 0
low_count: 1
informational_count: 4
owasp_categories: [A03, A05, A08]
cwe_ids: [CWE-285, CWE-693, CWE-1188]
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

# Security Review — Plan Phase — Feature 046

**Reviewer**: `/speckit-security-review-plan` (Anthropic Claude, inline)
**Feature**: 046 Absorb Core Abilities Companion Into Manager
**Branch**: `046-absorb-core-abilities-into-manager`
**Review scope**: Spec-Kit plan artifacts only — no source code inspection.

## Executive Summary

**Verdict**: **INFORMATIONAL — safe to implement.** No design decisions in
the plan introduce new attack surface, and every planned change either
preserves existing permission gates verbatim or improves the plugin's
compliance with existing security-related architecture memory
(PATTERN-UNINSTALL-DATA-GATE, BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE,
DEC-SINGLETON-PSR2-PROPERTY, SEC-03 multisite isolation).

The migration is a code-relocation feature: 218 absorbed classes and one
admin partial retain their existing sanitizers, capability checks, and
permission callbacks. No new REST namespaces, AJAX endpoints, custom tables,
authentication surfaces, or file-upload paths are introduced. The one
behavior change with authorization implications (category slug rebrand) is
an **intentional breaking change documented in the spec** (US2, FR-001) and
affects downstream integrators only — not internal permission decisions.

The plan explicitly encodes multiple secure patterns already present in
project memory: OR-monotonic opt-in migration to preserve admin intent, the
uninstall-data gate wrapping every destructive delete, `%i` for future SQL
identifiers (not exercised here since no new SQL exists), Constitution §II
forbidden-function grep gates on the moved tree, and
`PATTERN-GREP-AUDIT-VS-MANDATED-STRINGS` scoping so audits do not
self-flag the Clarifications block.

Per project memory feedback
(`~/.claude/…/memory/feedback_skip_permission_callback_audit.md`), this
review does **not** audit `permission_callback` compliance on the 201
absorbed ability classes. Those gates carry over verbatim from the
companion plugin.

## Plan Artifacts Reviewed

| Artifact | Path | Read |
|---|---|---|
| Feature specification | `specs/046-absorb-core-abilities-into-manager/spec.md` | ✅ |
| Implementation plan | `specs/046-absorb-core-abilities-into-manager/plan.md` | ✅ |
| Memory synthesis | `specs/046-absorb-core-abilities-into-manager/memory-synthesis.md` | ✅ |
| Inline security constraints | `specs/046-absorb-core-abilities-into-manager/security-constraints.md` | ✅ |
| Architecture violation detection | `specs/046-absorb-core-abilities-into-manager/violation-detection.md` | ✅ |
| Constitution | `.specify/memory/CONSTITUTION.md` v1.4.8 (§IV Security First) | ✅ |
| Memory INDEX | `docs/memory/INDEX.md` (Security Constraints + Bug Patterns + Patterns) | ✅ |
| Working planning doc | `docs/planning/046-absorb-core-abilities-into-manager.md` | ✅ |

No `research.md`, `data-model.md`, `quickstart.md`, or `contracts/` produced
for this feature (data model is trivial and lives inline in plan.md §Phase 1).
No dedicated `.specify/memory/security_constitution.md` — Constitution §IV
serves as the security baseline.

## Vulnerability Findings

### SEC-046-01 — Category slug rebrand affects downstream authorization contracts

- **finding_id**: SEC-046-01
- **location**: `specs/046-absorb-core-abilities-into-manager/plan.md` §Constitution Check → §I Modular Architecture; §Phase 1 §1 rewrite matrix rule #6; `specs/046-absorb-core-abilities-into-manager/spec.md` FR-001, FR-002, US2 (rewritten)
- **owasp_category**: A05:2025-Security Misconfiguration
- **cwe**: CWE-1188: Initialization of a Resource with an Insecure Default
- **cvss_score**: 3.1 (LOW)
- **spec_kit_task**: TASK-SEC-046-01

**Description**. Every category slug is rebranded from
`acrossai-core-abilities-<domain>` to `acrossai-abilities-manager-<domain>`.
Downstream code that keyed access-control or capability decisions off legacy
slug strings will stop matching post-migration. If any downstream system
fails **open** on unmatched slugs (grants access when no rule matches),
this rename could silently widen access.

**Why LOW not INFO**. The plan cannot control every downstream integrator's
default posture. `plan.md` explicitly documents this as an intentional
breaking change (US2 rewritten to "migrate to rebranded slugs in one
coordinated cutover"), which is the correct architectural direction. Score
reflects the residual downstream risk that plugin owners cannot fully
mitigate from within this feature.

**Mitigation already in plan**: spec §Clarifications Q2-revised, US2, FR-001;
security-constraints.md §"Boundaries That DO Change".

**Recommended additional mitigation**:
- Add a one-line release-notes entry explicitly listing the 17 slug renames.
- If any first-party AcrossAI plugin (mcp-manager, etc.) currently keys off
  the legacy slugs, coordinate a same-release update — track in `/speckit-tasks`.

---

### SEC-046-02 — Activation-time option migration must be idempotent and OR-monotonic

- **finding_id**: SEC-046-02
- **location**: `specs/046-absorb-core-abilities-into-manager/plan.md` §Phase 1 §4; `docs/planning/046-absorb-core-abilities-into-manager.md` CHANGE-7
- **owasp_category**: A08:2025-Software and Data Integrity Failures
- **cwe**: CWE-693: Protection Mechanism Failure
- **cvss_score**: 0.0 (INFORMATIONAL — design property, not a defect)
- **spec_kit_task**: TASK-SEC-046-02

**Description**. The plan specifies two properties for the activation-time
migration in `AcrossAI_Activator::activate()`:

1. **Idempotency**: repeated activation must not overwrite a manager-branded
   value with a stale legacy value. The plan guards with a
   `get_option( 'acrossai_abilities_manager_extra_mimes', null ) === null`
   check before copying.
2. **OR-monotonic opt-in fold**: the legacy uninstall opt-in is OR'd into
   the manager's existing single opt-in — never demoted. If the admin had
   `acrossai_abilities_uninstall_delete_data = 1` on the manager before
   activation, the legacy value cannot flip it back to 0.

Both properties protect against admin data-integrity regressions. Explicit
in the plan; INFORMATIONAL because it is a design guarantee to verify at
implementation time, not a design flaw.

**Recommended verification tasks**:
- PHPUnit case: seed both legacy keys with values → activate → assert new
  keys hold copies; run activator again → assert values unchanged.
- PHPUnit case: seed manager opt-in = 1 and legacy opt-in = 0 → activate →
  assert manager opt-in remains 1.
- PHPUnit case: seed manager opt-in = 0 and legacy opt-in = 1 → activate →
  assert manager opt-in flipped to 1.

---

### SEC-046-03 — Forbidden functions must be zero in the absorbed tree

- **finding_id**: SEC-046-03
- **location**: `specs/046-absorb-core-abilities-into-manager/plan.md` §Phase 1 §7 audit #5; `docs/planning/046-absorb-core-abilities-into-manager.md` §Quality-Gate Audits gate 5
- **owasp_category**: A03:2025-Injection
- **cwe**: CWE-77: Improper Neutralization of Special Elements used in a Command
- **cvss_score**: 0.0 (INFORMATIONAL — CI gate, not a finding)
- **spec_kit_task**: TASK-SEC-046-03

**Description**. Constitution §II forbids `eval()`, `extract()`, and
shell/process execution functions in production plugin code. The plan
includes a grep gate:

```
grep -RnE '\beval\(|extract\(|shell_exec\(|passthru\(|exec\(|popen\(|proc_open\(|system\(' includes/Abilities
```

The 2026-07-13 companion inventory showed no such calls. This gate is a
belt-and-suspenders check that must return zero hits before merge.
INFORMATIONAL because it is a gate, not a finding; the gate is well-scoped
per `PATTERN-GREP-AUDIT-VS-MANDATED-STRINGS`.

**Recommended verification task**:
- Add gate to the `/speckit-tasks` output as a merge blocker.

---

### SEC-046-04 — Uninstall must not delete data outside the opt-in gate

- **finding_id**: SEC-046-04
- **location**: `specs/046-absorb-core-abilities-into-manager/plan.md` §Phase 1 §5; `docs/planning/046-absorb-core-abilities-into-manager.md` CHANGE-8
- **owasp_category**: A05:2025-Security Misconfiguration
- **cwe**: CWE-693: Protection Mechanism Failure
- **cvss_score**: 0.0 (INFORMATIONAL — compliance confirmation)
- **spec_kit_task**: TASK-SEC-046-04

**Description**. The plan adds `delete_option( 'acrossai_abilities_manager_extra_mimes' )`
**inside** the existing `$acrossai_delete_data` gate in `uninstall.php`.
Complies with `PATTERN-UNINSTALL-DATA-GATE`; avoids the
`BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE` regression that would wipe settings
on every uninstall regardless of the opt-in.

The legacy companion keys
(`acrossai_core_abilities_extra_mimes`,
`acrossai_core_abilities_uninstall_delete_data`) are already swept during
activation-time migration (SEC-046-02), so they need no uninstall handling.

**Recommended verification task**:
- PHPUnit case: opt-in = 0, uninstall runs, assert
  `acrossai_abilities_manager_extra_mimes` remains in `wp_options`.

---

### SEC-046-05 — Bootstrap iterates 218 classes on `wp_abilities_api_init` — no new permission surface

- **finding_id**: SEC-046-05
- **location**: `specs/046-absorb-core-abilities-into-manager/plan.md` §Phase 1 §2 (Bootstrap wiring); `docs/planning/046-absorb-core-abilities-into-manager.md` CHANGE-4
- **owasp_category**: A05:2025-Security Misconfiguration
- **cwe**: CWE-285: Improper Authorization
- **cvss_score**: 0.0 (INFORMATIONAL — pattern confirmation)
- **spec_kit_task**: TASK-SEC-046-05

**Description**. The new `AcrossAI_Core_Abilities_Bootstrap` is the sole
hook-adder for the absorbed tree; wired from `Main.php` via the Loader on
`wp_abilities_api_categories_init @ P10` and `wp_abilities_api_init @ P10`.
Its `register_categories()` and `register_abilities()` methods call
`wp_register_ability_category()` and `wp_register_ability()` respectively.
Neither method is capability-scoped inside the Bootstrap — capability
enforcement is the WP Abilities API's responsibility on each ability's
own registered permission gate (which the plan preserves verbatim from the
companion, per project memory feedback which excludes those gates from this
audit).

INFORMATIONAL because it confirms the Path A refactor (plan.md V-01) does
not shift any authorization boundary — the Bootstrap is a passive
orchestrator, not a policy point.

**Recommended verification task**: none required at this layer; downstream
`/speckit-architecture-guard-architecture-review` after implementation
should confirm no ability class was accidentally stripped of its
permission gate during the constructor refactor.

## Confirmed Secure Patterns

The plan explicitly composes with several project-memory patterns:

| Pattern / Memory ID | Where applied in plan |
|---|---|
| `PATTERN-UNINSTALL-DATA-GATE` | plan.md §Phase 1 §5; planning CHANGE-8 wraps the migrated MIME-types option delete inside the existing `$acrossai_delete_data` gate. |
| `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE` (avoidance) | Same as above — explicit reference in security-constraints.md. |
| `PATTERN-GREP-AUDIT-VS-MANDATED-STRINGS` | plan.md §Phase 1 §7; audit patterns scoped to `includes/Abilities` and the moved admin partial, explicitly excluding `specs/` where the Clarifications block MUST name the legacy slug. |
| `BUG-PYTHON-STRREPLACE-PARTIAL-WRITE` (avoidance) | plan.md §Phase 1 §1 tooling note: use `sed`/`perl` per-file synchronous writes, not Python batching. |
| `SEC-03` (multisite isolation) | Unchanged — no new tables. |
| `SEC-04` (strict type comparison for access control) | plan.md §Phase 1 §4 uses strict `!== null` for the MIME-types key and strict `(bool)` casts for the opt-in. |
| `DEC-SINGLETON-PSR2-PROPERTY` | plan.md §Phase 1 §1 rewrite matrix rule #8: `$_instance` → `$instance` across every moved singleton. |
| `AC-FILE-HEADER-PATTERN` | plan.md §Phase 1 §1 rewrite matrix rule #9: every moved file's `@package`/`@subpackage`/`@since` header rewritten. |
| `DEC-SETTINGS-API-DEVIATION` | plan.md Constitution Check §III: absorbed MIME-types field uses WP Settings API in the existing Abilities tab (already an accepted deviation for ≤5 scalar fields). |
| Constitution §II forbidden-function bar | plan.md §Phase 1 §7 audit #5 is the grep gate for `eval/extract/shell_exec/passthru/exec/popen/proc_open/system`. |
| Constitution §II SQL `%i` mandate | Not exercised (no new SQL). |

## Threat Model Deltas

None. This is a code-relocation feature. No new external inputs, no new
network paths, no new privilege boundary transitions, no changes to
authentication or session lifecycle, no new secret material.

The one behavior change with cross-plugin implications (category slug
rebrand — SEC-046-01) is explicitly out-of-scope for internal authorization
decisions in this plugin; it only affects downstream systems that reference
the plugin's public identity via slug strings.

## Deferred / Not-Audited Surfaces

Per project memory feedback (`feedback_skip_permission_callback_audit.md`),
the `permission_callback` implementations on the 201 absorbed ability
classes are **not** re-audited by this review. Rationale: gates carry over
verbatim from the companion plugin, which owned them upstream. If a later
architecture review confirms a specific ability's gate was accidentally
weakened by the Path A constructor refactor (SEC-046-05), that would be a
separate finding filed against implementation, not against this plan.

## Action Plan & Next Steps

1. **Add security verification tasks** to the upcoming `tasks.md`
   (produced by `/speckit-tasks`):
   - `TASK-SEC-046-01`: coordinate downstream slug-reference updates.
   - `TASK-SEC-046-02`: PHPUnit — activation-time migration idempotency + OR-monotonic.
   - `TASK-SEC-046-03`: forbidden-function grep merge gate.
   - `TASK-SEC-046-04`: PHPUnit — uninstall gate honors opt-in for the migrated key.
   - `TASK-SEC-046-05`: post-implementation architecture-review confirmation that no ability class's permission gate was accidentally stripped during the constructor refactor.
2. **Durable Memory Preservation**: no new systemic security patterns were
   discovered by this review — the plan reuses existing memory rules
   verbatim. No `/speckit-memory-md-capture` invocation is required from
   the security-review angle. (The governed-plan turn already surfaced
   four candidate captures on the architecture axis; see `plan.md`
   §Recommended Actions.)
3. **Remediation Planning**: no CRITICAL or HIGH findings → no
   `/speckit-security-review-followup` needed.
4. **Next Spec-Kit step**: `/speckit-tasks`, seeded by plan.md §Phase 2
   and the five SEC-046-* verification items above.

---

## Memory Hub INDEX.md Row

Paste the following row into `docs/memory/INDEX.md` under the "Security Reviews" table:

```text
| specs/046-absorb-core-abilities-into-manager/security-review-plan.md | plan | 2026-07-13 | INFORMATIONAL | C:0 H:0 M:0 L:1 I:4 | A03,A05,A08 |
```

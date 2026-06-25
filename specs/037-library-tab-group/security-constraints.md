---
document_type: security-review
review_type: plan
assessment_date: 2026-06-25
codebase_analyzed: acrossai-abilities-manager (Feature 037 — Library Tab Group)
total_files_analyzed: 4
total_findings: 1
overall_risk: INFORMATIONAL
critical_count: 0
high_count: 0
medium_count: 0
low_count: 0
informational_count: 1
owasp_categories: [A03]
cwe_ids: [CWE-79, CWE-20]
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

# Security Review — Plan (Feature 037 Library Tab Group)

## Executive Summary

Feature 037 introduces a single new untrusted-input field (`args['tab_group']`) on the existing `Ability_Definition` extension contract and renders it on the Library admin page as a tab label. The plan **adopts the strongest available mitigation on day one**: value-sanitization at the Registry trust boundary via `AcrossAI_Ability_Library_Config::sanitize_key_field()` (the same helper already proven on `category`, `slug`, and `sub_group`). This goes one step beyond what existing `args.*` fields receive — current Registry behavior is key-allowlist only, as captured by the durable lesson `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH` from Feature 036.

**Result**: no exploitable surface introduced by this feature. The plan is cleared for implementation. One **informational** observation is recorded below regarding the broader (pre-existing) args-raw-passthrough gap that remains for other fields — this is outside Feature 037's scope but worth flagging for future planning.

**Risk level**: INFORMATIONAL — C:0 H:0 M:0 L:0 I:1
**OWASP categories considered**: A03 Injection
**CWEs considered**: CWE-79 (XSS), CWE-20 (Improper Input Validation)

## Plan Artifacts Reviewed

| Artifact | Path | Status |
|---|---|---|
| Feature spec | `specs/037-library-tab-group/spec.md` | Reviewed |
| Implementation plan | `specs/037-library-tab-group/plan.md` | Reviewed |
| Memory synthesis | `specs/037-library-tab-group/memory-synthesis.md` | Reviewed |
| Spec quality checklist | `specs/037-library-tab-group/checklists/requirements.md` | Reviewed |
| Project constitution | `.specify/memory/CONSTITUTION.md` | Referenced (via memory-synthesis selections) |
| Memory hub index | `docs/memory/INDEX.md` | Referenced |

`research.md`, `data-model.md`, `contracts/`, `quickstart.md` — intentionally omitted by the plan (no new tech, no data model, no REST contract, verification path inside `plan.md`).

## Trust Model

| Source | Trust level | Channel | Sanitization point |
|---|---|---|---|
| Third-party add-on PHP (untrusted) | Untrusted | `acrossai_abilities_api_init` filter → Registry collection at `init` P99 | Registry `validate_and_normalize()` — `sanitize_key_field()` applied to `tab_group` (NEW in this feature) |
| Registry-validated definition row | Trusted | `wp_add_inline_script('before')` payload `window.acrossaiAbilityLibraryData` | (none required after boundary sanitization) |
| React component tree | Trusted | JSX text nodes | React default escaping (defence-in-depth) |
| Saved configuration (`AcrossAI_Ability_Library_Config`) | N/A — `tab_group` is never written | — | — |
| REST endpoints (`/acrossai-abilities-library/v1/abilities/config`) | N/A — REST surface unchanged | — | — |

## Vulnerability Findings

### SEC-001 — Pre-existing args-raw-passthrough gap (out of scope, documented)

- **Severity**: INFORMATIONAL
- **CVSS v3.1 score**: 0.0 (no exploitable surface introduced by *this* feature)
- **OWASP category**: A03:2025-Injection
- **CWE**: CWE-20: Improper Input Validation
- **Location**: `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php:188` (existing `array_intersect_key` strip)
- **Spec-Kit task**: TASK-SEC-N/A (out of scope for this feature; track separately)

**Observation**: The Registry validates the **keys** of `args` against `ALLOWED_ARGS_FIELDS` but does NOT value-sanitize most fields (`label`, `description`, `meta`). This was captured as the durable lesson `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH` during Feature 036. Feature 037 explicitly closes this gap **for `tab_group`** by adding `sanitize_key_field()` at the boundary — but the broader gap for other `args.*` fields remains.

**Why this is INFORMATIONAL, not a finding**:
- Feature 037 does NOT introduce a new instance of the gap. It introduces a new field that is *fully sanitized at the boundary*, which is the safer-than-existing pattern.
- The broader gap is a documented, pre-existing condition (captured in `docs/memory/ARCHITECTURE.md` as `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`) that the team is already tracking.
- React's default JSX text-node escaping is the defence-in-depth layer currently relied on for the other fields.

**Recommendation**:
- For this feature: no action required — the plan already does the right thing.
- For future planning: consider a follow-up feature that promotes value-sanitization of *all* `args.*` fields to the Registry boundary, with per-field strategies (e.g. `wp_kses_post` for `description`, `sanitize_key` for slug-like fields). This is out of scope for Feature 037.

## Confirmed Secure Patterns

| # | Pattern | Where applied | Reference |
|---|---|---|---|
| 1 | **Trust-boundary sanitization** — sanitize untrusted input the moment it crosses into trusted code | Registry `validate_and_normalize()` invokes `AcrossAI_Ability_Library_Config::sanitize_key_field()` on `tab_group` before storing on the entry row | `DEC-DESCRIPTION-VALIDATION-PATTERN`, `SEC-01` (slug sanitization precedent) |
| 2 | **Allowlist-first input shape** — explicit `ALLOWED_ARGS_FIELDS` + `OPTIONAL_FIELDS` constants | Registry strips any key not on the allowlist before processing | Registry.php:61–85 (existing) |
| 3 | **Single-encoding render path** — JSX text nodes, no `dangerouslySetInnerHTML` | `LibraryPage` renders `tab_group` (post-sanitize) as a text node inside `TabPanel` | React default escaping |
| 4 | **Display-only invariant** — `tab_group` MUST NOT be persisted, MUST NOT affect REST, MUST NOT affect execution | Stated explicitly in spec FR-009 and tested by SC-005; plan reaffirms in Constitution Check and Phase descriptions | spec.md FR-009, SC-005; plan.md Summary |
| 5 | **Charset + length cap** — `sanitize_key()` + 100-char cap via the existing helper | Identical to the constraints already applied to `category`, `slug`, `sub_group` | `AcrossAI_Ability_Library_Config::sanitize_key_field()` |
| 6 | **No new authorization surface** — Library page already gated by `manage_options` on the submenu; no REST changes | Confirmed by plan's "no REST changes" assertion across multiple sections | plan.md Technical Context, Constitution Check |
| 7 | **No async / concurrency** — Registry collection synchronous at `init` P99; React state is component-local | No cross-tab / cross-session shared mutable state for the active-tab selection | plan.md Phase 2 |

## Implementation Hardening Reminders

These are NOT findings — they are implementation gates the plan already commits to:

- **PHPStan level 8** must pass after the Registry array-key changes (per Constitution §II quality gate).
- **PHPCS** must pass on the new PHP additions (no new sniffs expected; mirrored from sub_group block).
- **Jest** must pass with the updated `jest.mock('@wordpress/components', …)` allowlist that includes `TabPanel` (per `BUG-JEST-MOCK-LIST-STALENESS`).
- **No `dangerouslySetInnerHTML`** anywhere in the new tab-bar render path. The plan does not introduce it; reviewers should verify during implementation diff review.

## Verification Against Project Memory

| Memory entry | Relevance | Plan compliance |
|---|---|---|
| `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH` (Feature 036) | Critical — defines the boundary-sanitization expectation for new `args.*` consumers | ✅ Plan sanitizes at Registry; closes the gap for this field |
| `DEC-DESCRIPTION-VALIDATION-PATTERN` | Establishes that display-only `args` fields are validated/sanitized server-side | ✅ Same precedent applied to `tab_group` |
| `SEC-01` (`sanitize_ability_slug` at REST endpoints) | Slug-charset sanitization precedent | ✅ Same charset (`sanitize_key()`) applied via `sanitize_key_field()` |
| `feedback_skip_permission_callback_audit` (session memory) | Skip `permission_callback` compliance audit | ✅ Skipped — no REST changes to audit anyway |
| `BUG-WP-LOCALIZE-SCRIPT-RENDER` | Library data injection must use `wp_add_inline_script('before')`, not `wp_localize_script` from render | ✅ Plan changes no enqueue logic; existing inheritance preserved |

## Plan Acceptance

- [x] Trust boundaries identified and protections explicit.
- [x] No new authentication/authorization paths introduced.
- [x] All new untrusted input sanitized at the trust boundary (sanitize-on-input).
- [x] No new injection surface in any OWASP A03 sub-category.
- [x] No async/concurrency risk.
- [x] Display-only invariant explicitly stated and testable (SC-005).
- [x] Confirmed alignment with all relevant project-memory entries.

**Plan is cleared for implementation from a security perspective. Overall risk: INFORMATIONAL.**

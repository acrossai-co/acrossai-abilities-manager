---
document_type: security-review
review_type: plan
assessment_date: 2026-06-18
codebase_analyzed: AcrossAI Abilities Manager — Feature 036 (Library page full width and descriptions)
total_files_analyzed: 4
total_findings: 1
overall_risk: INFORMATIONAL
critical_count: 0
high_count: 0
medium_count: 0
low_count: 0
informational_count: 1
owasp_categories: [A03]
cwe_ids: [CWE-79]
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

# Security Review — Plan Artifact (Feature 036)

## Executive Summary

Feature 036 surfaces `args.description` from registered abilities on the Ability Library admin page and removes the page's 900px width cap. The plan is a React + SCSS-only change; no PHP, REST, database, or authorization logic is touched. The new data path is **add-on → existing PHP Registry → existing JSON inline script → existing `window.acrossaiAbilityLibraryData` → React text node**.

The only credible threat surface is reflected/stored XSS via untrusted description content. The plan correctly mitigates this by rendering description as a plain JSX text node, never via `dangerouslySetInnerHTML` (spec FR-006, plan Phase 2 "Behaviour rules"). React's default text-node escaping is a sound, well-understood defense. **There are no Critical, High, Medium, or Low findings**, and one Informational defense-in-depth observation is recorded below.

**Overall Risk**: **INFORMATIONAL**. Implementation may proceed without remediation work.

## Plan Artifacts Reviewed

| Artifact | Path | Read | Material to review? |
|---|---|---|---|
| Feature spec | `specs/036-library-page-full-width-and-descriptions/spec.md` | ✅ | Yes — FR-006 + Assumptions cover the rendering safety contract |
| Implementation plan | `specs/036-library-page-full-width-and-descriptions/plan.md` | ✅ | Yes — Phases 1–3 + Architecture Validation table |
| Memory synthesis | `specs/036-library-page-full-width-and-descriptions/memory-synthesis.md` | ✅ | Yes — confirms no SEC-01..SEC-04 surface touched |
| Spec quality checklist | `specs/036-library-page-full-width-and-descriptions/checklists/requirements.md` | ✅ | Yes — confirms scope bounded |
| `docs/memory/security-constraints.md` | repo-wide | ✅ (prior turn) | Reference — only SEC-04 defined, not applicable to this feature |
| `.specify/memory/CONSTITUTION.md` §III Code Quality (escape on render) | repo-wide | ✅ (prior turn) | Reference — applied via JSX text-node escape |
| `research.md` / `data-model.md` / `contracts/` / `quickstart.md` | feature dir | n/a | Plan justifies omission (no research, no data model, no contracts) |
| Prior feature security review | `docs/security-reviews/2026-06-09-028-plan.md` | exists, not relevant to this feature scope | — |

## Vulnerability Findings

### SEC-001 — Description value crosses PHP→browser boundary without server-side escaping; sole defense is React's text-node render (Informational)

| Field | Value |
|---|---|
| **finding_id** | SEC-001 |
| **location** | `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php:209` (where `args` is stored on the validated entry without per-field sanitization) — exposed by plan Phase 1/2 (consume `args.description` in React) |
| **owasp_category** | A03:2025-Injection |
| **cwe** | CWE-79: Cross-site Scripting (Reflected/Stored) |
| **cvss_score** | 0.0 (Informational — pre-empts a future regression; current plan is safe) |
| **spec_kit_task** | TASK-SEC-001 (optional follow-up only) |

**Observation**: `Ability_Definition::push_definition()` and `AcrossAI_Ability_Library_Registry::validate_and_normalize()` apply `wp_kses_post()` to `category_label` and `slug_label` (lines 205 and 207 of the Registry), but `args` — including `description` — is only key-allowlist filtered (line 188: `array_intersect_key`). The description value reaches `window.acrossaiAbilityLibraryData.definitions[i].args.description` as the raw string the add-on registered. Feature 036's plan correctly relies on React text-node escaping for safety (FR-006, plan §"Phase 2 — Behaviour rules"). On the current code path, this is sound: `<p>{description}</p>` is XSS-safe.

**Why this is Informational, not a finding to block implementation**:

1. The defense is correct for the path being shipped.
2. Description originates from add-on code the site owner has explicitly installed — the trust boundary is established by the WordPress plugin install flow, not by description content. The synthesis records this explicitly (Relevant Security Constraints section).
3. Spec Assumptions states: "no additional server-side sanitization is required for this feature."

**Why it is worth recording**: A future feature that reads `args.description` from a non-React renderer — e.g. a PHP admin notice, a server-rendered card, or a future React component that uses `dangerouslySetInnerHTML` to support Markdown — would silently bypass the only escape and create stored XSS. Today's contract is "React text node escapes it"; that contract is invisible to a future reader of the Registry code.

**Optional follow-ups** (not required by the spec):

- (a) **Defense-in-depth at the PHP boundary**: apply `wp_kses_post()` (or `esc_html()` if no markup is ever desired) to `args['description']` during validation in the Registry. This would harden the field at the source without changing the React render path. Cost: one line of PHP. Trade-off: forecloses any future legitimate inline-formatting use case.
- (b) **Documented contract**: add a single-line comment at `AcrossAI_Ability_Library_Registry::validate_and_normalize()` stating "args values are NOT pre-sanitized; consumers MUST escape on render (React text nodes do this automatically)." Cost: a comment. Trade-off: relies on future readers heeding the comment.
- (c) **Status quo + spec memory**: rely on the spec's explicit FR-006 ("description MUST be rendered as plain text … the browser MUST NOT interpret them as markup") to keep future implementers honest. This is the current plan choice.

**Recommendation**: For Feature 036 itself, **no action required**. If the team is comfortable with the spec FR-006 contract acting as the single forward guardrail, ship as planned. If a quick defense-in-depth pass is welcome, option (a) is a one-line PHP edit out of scope for this feature, suitable for a follow-up ticket.

## Confirmed Secure Patterns

The plan demonstrates the following secure-by-design choices, recorded so they are not lost in future refactors:

| # | Pattern | Where in plan |
|---|---|---|
| 1 | **Render-side XSS containment via JSX text nodes only**. No `dangerouslySetInnerHTML` allowed for description. | Spec FR-006; plan Phase 2 "Behaviour rules" bullet 1 |
| 2 | **No expansion of the data injection surface**. The existing `wp_add_inline_script('before')` payload already carries `description`; the plan touches zero PHP. This honors `BUG-WP-LOCALIZE-SCRIPT-RENDER` and `AC-ENQUEUE-ADMIN`. | Plan Memory Synthesis Findings + §Untouched files |
| 3 | **No persistence of description into saved configuration**. Description is read fresh from each definition on every page load — no new XSS-able field reaches the database. | Spec FR-010, plan Phase 1 "Behaviour rules" |
| 4 | **REST contract unchanged**. No new endpoints, no new auth surface, no new permission_callback signatures to review. | Plan §Summary + §Architecture Validation row "REST controller split / permission_callback typing" |
| 5 | **No new SQL or `$wpdb` usage**. Plan §Architecture Validation explicitly rules out DB changes; SEC-01..SEC-03 are non-issues for this feature. | Plan §Architecture Validation; memory-synthesis §"Relevant Security Constraints" |
| 6 | **No new capability checks needed**. The page already requires `manage_options`; the description data is a subset of what the page already loads. | Plan §Memory Synthesis Findings (data injection unchanged) |
| 7 | **Saved-config schema preserved**. `enabled` / `mode` / `sub_keys` shape unchanged means there's no risk that an attacker who somehow flips a config field can exfiltrate or alter description rendering. | Spec FR-010, plan §Memory Synthesis Findings |
| 8 | **Defensive `args?.description` access in JS**. Plan Phase 1 destructures with optional-chaining + type guard, so a malformed `definitions` payload cannot throw at render time and cannot leak a value of an unexpected type into the DOM. | Plan Phase 1 — `typeof args?.description === 'string' ? args.description.trim() : ''` |
| 9 | **Plain-text width change**. The SCSS edit drops `max-width: 900px` — no new selectors that target shared WordPress admin chrome, no class names that could collide with WP-core admin (spec FR-008 + plan Phase 3). Zero security implication. | Plan Phase 3 + spec FR-008 |
| 10 | **Pre-existing UI Contract deviation properly scoped**. The plan flags the Library page's custom React UI as a pre-existing `DEC-DESIGN-OVERRIDES-DATAVIEWS` deviation and explicitly notes no new deviation is introduced — protects against drift accusations during the post-implementation security review. | Plan §Constitution Check + §Architecture Validation |

## Action Plan & Next Steps

1. **Durable Memory Preservation**: **Not triggered**. This review surfaces no new systemic vulnerability and no new reusable security pattern — only a documented defense-in-depth observation about an existing PHP boundary that is in scope for a future follow-up, not this feature. `/speckit-memory-md-capture` is **not required**.
2. **Remediation Planning**: **Not triggered**. No Critical or High findings. `/speckit-security-review-followup` is **not required** for Feature 036. If the team wants to track Option (a) above as a separate hardening ticket, `/speckit-security-review-followup` can generate it from this artifact.
3. **Proceed to**: `/speckit-tasks` to generate `tasks.md`, or commit current artifacts with `/speckit-git-commit`.

---

## Memory Hub INDEX.md Row

Paste this row into `docs/memory/INDEX.md` under the Security Reviews section (or wherever the project's table convention places plan-level reviews):

```text
| specs/036-library-page-full-width-and-descriptions/security-constraints.md | plan | 2026-06-18 | INFORMATIONAL | C:0 H:0 M:0 L:0 | A03 |
```

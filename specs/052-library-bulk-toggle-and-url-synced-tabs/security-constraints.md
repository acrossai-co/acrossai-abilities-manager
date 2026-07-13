---
document_type: security-review
review_type: plan
assessment_date: 2026-07-13
codebase_analyzed: acrossai-abilities-manager (Feature 052 plan)
total_files_analyzed: 4
total_findings: 4
overall_risk: INFORMATIONAL
critical_count: 0
high_count: 0
medium_count: 0
low_count: 0
informational_count: 4
owasp_categories: [A01, A03, A05, A09]
cwe_ids: []
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

# SECURITY REVIEW REPORT — Feature 052 PLAN

## Executive Summary

Feature 052 adds two administrator-only UX affordances to an already-authenticated WordPress admin page (`?page=acrossai-abilities-library`): (A) two side-by-side bulk-action `<Button>`s scoped to the currently active tab, and (B) URL-synced tabs. The plan introduces **zero** new user-input surfaces, **zero** new REST routes, **zero** new capability boundaries, **zero** new database columns or schemas, and **zero** new external dependencies. All persistence continues to flow through the existing `POST /acrossai-abilities-library/v1/abilities/config` route, which was audited under Features 027 and 041 and enforces `current_user_can('manage_options')` plus `wp_verify_nonce($_, 'wp_rest')` in `AcrossAI_Ability_Library_Config_Controller::check_permission()`. Per the user rule in scope (`Skip permission_callback REST audit`), that gate is treated as an unchanged baseline for this review and not re-audited line-by-line.

**Overall risk: INFORMATIONAL.** No Critical, High, Medium, or Low findings. Four informational items are recorded for implementation hygiene — each maps to an already-known durable bug pattern or contract already reflected in the planning artifact, and each is folded into a specific CHANGE-N section.

## Plan artifacts reviewed

- `specs/052-library-bulk-toggle-and-url-synced-tabs/spec.md` (19 FRs, 3 user stories, Session 2026-07-13 clarification on button no-op contract)
- `specs/052-library-bulk-toggle-and-url-synced-tabs/plan.md` (Constitution v1.4.8 gate, Phase 0 research, Phase 1 design, re-check)
- `specs/052-library-bulk-toggle-and-url-synced-tabs/memory-synthesis.md` (index-first retrieval)
- `docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md` (implementation-detail source)

Cross-referenced against:
- `.specify/memory/CONSTITUTION.md` v1.4.8 (no separate `security_constitution.md` present in this project — security rules live in §IV of the main Constitution)
- `docs/memory/INDEX.md` §Security Constraints (SEC-01…SEC-04) and §Bug Patterns
- Pre-existing behavior of `AcrossAI_Ability_Library_Config`, `AcrossAI_Ability_Library_Registry`, `AcrossAI_Ability_Library_Config_Controller`

## Trust boundaries and threat assumptions

- **Client → Server (REST)**: The bulk-assembled config object crosses at `POST /acrossai-abilities-library/v1/abilities/config`. Controller enforces `manage_options` + REST nonce (unchanged). Tab-scoping is a client-side UX affordance; the server accepts whatever full config an authenticated administrator submits — a lower-role user who bypasses the JS entirely still fails the capability check. This threat model is unchanged from today.
- **Server → Client (localization)**: `window.acrossaiAbilityLibraryData.bulkToggleState` is a fixed-enum server-computed string (`'all' | 'none' | 'mixed'`). No untrusted input reaches this value.
- **Client-only state (URL sync)**: `?tab=<slug>` is administrator-supplied via URL. It is validated against the runtime `tabGroups` list before being applied to state; unknown values fall back to the `ALL_TABS_KEY` sentinel (FR-014). No slug value is written back to the DOM outside a React text node.

## Authentication, authorization, session decisions

- No new authentication surface. Authorization continues to be `manage_options` on the existing REST route.
- Session semantics unchanged — WP's cookie-based nonce lifecycle governs the write path.
- The plan does not introduce a per-tab or per-category permission model; all-or-nothing at the page-owner level, matching the existing per-card toggle contract.

## Data flow, privacy, and minimization

- Only administrator-editable configuration data is touched. No PII. No user-generated content. No third-party data.
- Sparse-storage semantics (existing `sanitize_entry()` and `save_config()`) continue to strip all-default entries — data-at-rest is minimized.
- Multisite isolation preserved via existing `get_site_option()` / `update_site_option()` in `AcrossAI_Ability_Library_Config` (matches `SEC-03` intent: no cross-site leakage).

## Dependency and platform choices

- No new npm dependencies. All imports (`@wordpress/components`, `@wordpress/element`, `@wordpress/i18n`, `@wordpress/url`, `@wordpress/api-fetch`) are pre-existing peerDependencies bundled by WordPress core. `PATTERN-WORDPRESS-PEER-DEPENDENCIES` respected.
- No new Composer packages. Zero supply-chain surface added by this feature.
- Node ≥ 20 build environment enforced by `DEC-NODE-20-BUILD-REQUIRED` — bundle regeneration cannot silently ship a broken artifact.
- Target platform matches Constitution §II: PHP 8.1+, WP 6.9+, multisite-compatible.

## Validation, logging, error handling

- Server-side validation is unchanged — `sanitize_entry()` runs on every REST write. Feature 052 does not extend or bypass it.
- No server-side logging surface introduced. No new `error_log()` calls planned; `PATTERN-WP-DEBUG-LOG-GUARD` therefore not exercised.
- Client-side error handling for the bulk save is explicitly specified in FR-007 (revert on-screen state + surface a visible error). The planning artifact CHANGE-4c handlers implement this via `saveConfig(next).catch(() => setError(__('Failed to save.', …)))`. No silent-fail path.

## Secrets handling & deployment hardening

- N/A — no secrets involved (no API keys, tokens, passwords, or connection strings touched).
- No CI/CD workflow changes. Existing `phpcs.yml`, `phpstan.yml`, `phpcompat.yml`, plugin-check job continue as gates. Feature 052 does not adjust ignore-codes, SHA pins, or permissions:{} scoping on any workflow file.

## Vulnerability findings

None (no Critical, High, Medium, or Low).

## Confirmed secure patterns

- **Reuse of already-gated REST route** — `POST /abilities/config` remains the SOLE persistence channel. No parallel write path added.
- **Client-side URL parsing without server reflection** — `?tab=<slug>` is validated against the runtime `tabGroups` list; unknown values map to the `ALL_TABS_KEY` sentinel before ever affecting DOM or history state (planning artifact CHANGE-3 `parseTabFromUrl` contract).
- **Sparse-storage default** — fully-enabled admin state maps to an empty option row, minimizing data-at-rest.
- **Read-only static helpers** — new PHP methods are stateless, argument-less, and touch only already-vetted read paths. No SQL. No external calls.
- **Peer-dependency-only JS imports** — no new npm attack surface. Aligns with `PATTERN-WORDPRESS-PEER-DEPENDENCIES`.
- **Session 2026-07-13 clarification** — buttons stay actionable + clicks are silent no-ops (Option C). This removes a class of ambiguity (`aria-disabled` vs `disabled` vs neither) that could have caused inconsistent enforcement rendering in future audits.

## Informational items (implementation hygiene)

### [INFO] SEC-052-I-001 — `class_exists()` MUST use default `autoload=on`
**Location**: `includes/Modules/Library/Ability_Definition.php` (planned `registered_category_slugs()`)
**OWASP Category**: A05:2025 — Security Misconfiguration
**CWE**: none (class-loading semantic)
**CVSS Score**: 0.0 (informational — hygiene)
**Description**: The helper guards against a missing Registry with `class_exists()`. If `false` is passed as the second argument, the composer autoloader is disabled for that check — when nothing else has yet referenced `AcrossAI_Ability_Library_Registry`, the check returns `false` and the surrounding guard silently no-ops. `is_all_disabled()` and `bulk_toggle_state()` would then under-count categories with no PHP fatal, no log entry, no test failure. This is the Feature 046 bootstrap incident captured as `BUG-CLASS-EXISTS-AUTOLOAD-FALSE-SILENT`.
**Remediation**: Do NOT pass `false` to `class_exists()`. Use the default `class_exists('\Fully\Qualified\Name')` form. Add a PHPUnit case that seeds config with entries and asserts the helper correctly reads the Registry.
**Spec-Kit Task**: TASK-SEC-001 (implementation-time — folded into CHANGE-1)

### [INFO] SEC-052-I-002 — `bulkToggleState` localization MUST live in `enqueue_scripts()`
**Location**: `admin/Main.php::enqueue_scripts()`
**OWASP Category**: A05:2025 — Security Misconfiguration (load-order hazard)
**CWE**: none (WP admin lifecycle)
**CVSS Score**: 0.0 (informational — hygiene)
**Description**: The initial-paint hint must be emitted via `wp_add_inline_script('before')` inside `Admin\Main::enqueue_scripts()`, appended to the SAME localized-data array that already ships `definitions`, `restBase`, `nonce`, `addonsUrl`. Placing it inside a page render callback fires too late in the WP admin pipeline and yields a blank page — the Feature-030 failure mode `BUG-WP-LOCALIZE-SCRIPT-RENDER`. Also aligned with `AC-ENQUEUE-ADMIN` (Constitution §I).
**Remediation**: Add exactly one new key to the existing localized-data array. Do NOT create a second `wp_localize_script()` call.
**Spec-Kit Task**: TASK-SEC-002 (implementation-time — folded into CHANGE-2)

### [INFO] SEC-052-I-003 — `?tab=<slug>` MUST return sentinel on unknown value
**Location**: `src/js/ability-library/hooks/useLibraryTabSync.js` (planned `parseTabFromUrl`)
**OWASP Category**: A03:2025 — Injection (defense-in-depth against reflected values)
**CWE**: none (validation contract)
**CVSS Score**: 0.0 (informational — hygiene)
**Description**: `?tab=<slug>` is administrator-supplied via the URL. FR-014 mandates fallback-to-`All` for unknown slugs. The implementation MUST return the `allTabsKey` sentinel when the value is not present in `validSlugs` — NOT the raw URL value. React's default JSX escaping is sufficient for the ultimate render (activeTab as a React text node), but the sentinel-return contract prevents an unknown value from ever entering `history.pushState` or affecting downstream scope-derivation logic. This matches the planned helper contract; the review confirms the contract is correct and MUST NOT be loosened during implementation.
**Remediation**: Implement `parseTabFromUrl(url, validSlugs, allTabsKey)` with strict inclusion check (`validSlugs.includes(candidate)`) before returning the URL value. Add a Jest case for the invalid-slug fallback.
**Spec-Kit Task**: TASK-SEC-003 (implementation-time — folded into CHANGE-3)

### [INFO] SEC-052-I-004 — Preserve disabled-card UI contract on bulk-disable path
**Location**: `src/js/ability-library/components/LibraryCard.js` (existing gating conditions at lines 70, 107, 138)
**OWASP Category**: A01:2025 — Broken Access Control (visual clarity of enforcement state)
**CWE**: none (UX enforcement invariant)
**CVSS Score**: 0.0 (informational — hygiene)
**Description**: FR-017 and the spec's Disabled-Card UI Contract section require that a bulk-disabled card renders identically to a per-card-disabled card. If bulk-disable ever rendered a disabled card WITH the mode selector or slug list still visible, an administrator would see configurability affordances on a category that is administratively off — a visual inconsistency that could mask enforcement state and lead to misjudged blast-radius during operational lockdowns. Also cross-references OWASP A09 (misleading UI state = security-logging-quality concern for post-incident forensics).
**Remediation**: Keep the three `enabled && …` gating conditions in `LibraryCard.js` unchanged. Add a Jest render assertion in the extended `LibraryPage.test.js` that compares the header-row DOM of a manually-disabled category against a bulk-disabled category and asserts identity.
**Spec-Kit Task**: TASK-SEC-004 (implementation-time — folded into CHANGE-6 Jest extensions)

## Action Plan & Next Steps

### 1. Durable Memory Preservation (Mandatory Check)

**Skipped** — no new systemic vulnerabilities, no new reusable security patterns, no new auth boundary decisions were surfaced by this review. All four informational items map to already-captured durable memory:

- SEC-052-I-001 → `BUG-CLASS-EXISTS-AUTOLOAD-FALSE-SILENT` (already in BUGS.md, Feature 046)
- SEC-052-I-002 → `BUG-WP-LOCALIZE-SCRIPT-RENDER` (already in BUGS.md, Feature 030) + `AC-ENQUEUE-ADMIN` (Constitution §I)
- SEC-052-I-003 → generic validation contract; no new pattern
- SEC-052-I-004 → `FR-017` in the spec + the disabled-card UI contract; already codified in-spec

No `/speckit.memory-md.capture` invocation triggered.

### 2. Remediation Planning

No Critical or High findings. `/speckit.security-review.followup` is **NOT** recommended for this plan — the four informational items are already reflected in the planning artifact's CHANGE-N sections and Validation Checklist non-regression items.

### 3. Post-implementation

Run `/speckit-security-review-staged` against the actual diff once implementation completes. That pass will confirm SEC-052-I-001 through -004 landed in code as planned and will surface any drift the plan-level review could not see (e.g. inadvertent introduction of a second inline script call, or loosening of a LibraryCard gate).

---

## Memory Hub INDEX.md Row

```text
| specs/052-library-bulk-toggle-and-url-synced-tabs/security-constraints.md | plan | 2026-07-13 | INFORMATIONAL | C:0 H:0 M:0 L:0 | A01,A03,A05,A09 |
```

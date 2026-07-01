---
document_type: security-review
review_type: staged
assessment_date: 2026-07-01
codebase_analyzed: acrossai-abilities-manager (branch 039-composer-package-updates, commits 454f287 + 2a6d8c5)
total_files_analyzed: 8
total_findings: 6
overall_risk: LOW
critical_count: 0
high_count: 0
medium_count: 0
low_count: 1
informational_count: 5
owasp_categories: [A04, A05, A06]
cwe_ids: [CWE-209, CWE-1104, CWE-754]
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

# SECURITY REVIEW REPORT — STAGED CHANGES

## Executive Summary

Scope interpreted as the **branch delta ready for merge to `main`** — two commits (`454f287` Feature 039 main delivery + `2a6d8c5` JS fix + memory capture) — per Feature 038's precedent (its `security-review-staged.md` covered the branch diff, not the literal `git diff --cached` uncommitted set). Literal staged/uncommitted set is `.claude/settings.local.json` + `.phpunit.cache/test-results` (editor/tool state; no code).

**Overall risk: LOW.** All five plan-time SEC findings (`security-review-plan.md`) are confirmed remediated by the actual implementation. One new LOW finding (SEC-006) surfaced from the follow-up JS-fix commit — a resilience gap on undefined slug, not a security exposure. Four INFORMATIONAL observations document the plan-time findings' as-implemented state. No new CRITICAL, HIGH, or MEDIUM findings introduced by the branch diff.

**Plan → staged deltas**:
- Plan-review: LOW × 1 + INFORMATIONAL × 4 (5 findings)
- Staged-review: LOW × 1 + INFORMATIONAL × 5 (6 findings; SEC-001…SEC-005 carry over as verified-closed; SEC-006 is new)
- **Net new attack surface: none.** Net new resilience gap: one (SEC-006, JS undefined-slug fallback).

## Staged Diff Reviewed

**Code files (in-scope for security review)** — 8 files, +82 / −29 lines:

| File | Change | Origin |
|---|---|---|
| `composer.json` | wpb-ac ^1.6→^2.0; main-menu ^0.0.4→^0.0.8; drop addons-page | `454f287` |
| `includes/Main.php` | Drop `'acrossai'` positional arg from AddonsPage constructor + update comment | `454f287` |
| `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` | Add `TABLE_SLUG='abilities'` constant + pass to `AccessControlManager` | `454f287` |
| `includes/AcrossAI_Activator.php` | Import `AC_Access_Control`; pass `TABLE_SLUG` to `RuleTable` | `454f287` |
| `uninstall.php` | Target new table + option name; legacy left orphaned | `454f287` |
| `tests/phpunit/sitewide/AccessControlBootstrapTest.php` | Retarget to current wrapper; assert 2-arg constructor + slug regex + slugged REST routes | `454f287` |
| `admin/Main.php` | Add `access_control_slug` to localize_script data | `2a6d8c5` |
| `src/js/abilities/components/AbilityForm.jsx` | Add `pluginSlug` prop + interpolate slug into manual save URL | `2a6d8c5` |

**Out-of-scope generated/config/docs** (verified as documentation or generated output, not reviewed for injection/logic vulnerabilities): `composer.lock`, `build/js/abilities.js`, `build/js/abilities.asset.php`, `README.txt`, `docs/memory/{ARCHITECTURE,BUGS,INDEX,WORKLOG}.md`, `docs/planning/039-composer-package-updates.md`, `specs/039-composer-package-updates/*`, `.specify/feature.json`.

## Vulnerability Findings

### SEC-001 — INFORMATIONAL: Admin_notices closure preservation verified (plan-time SEC-001 CLOSED)

- **Location**: `includes/Main.php:335-349`
- **OWASP Category**: A04:2025 Insecure Design
- **CWE**: CWE-209 (Sensitive Info in Error Message) — theoretical, not realized
- **CVSS v3.1 Score**: 0.0 (verification-only; no active vulnerability)
- **Spec-Kit Task**: T011 (already checked)

**Description**: Plan-time SEC-001 required that the AddonsPage `try/catch` + `admin_notices` closure at lines 335-349 be byte-for-byte preserved (`current_user_can('manage_options')` gate + `esc_html()` output). Confirmed via `git diff`: the only changes to `includes/Main.php:316-349` are (a) leading comment header update (documentation) and (b) dropping the `'acrossai',` positional argument at line 325. The closure body is untouched — capability gate + escaping both intact.

**Verification method**: `git diff main..HEAD -- includes/Main.php` shows exactly two hunks; the closure block appears as unchanged context. No fail-open notice regression.

**Status**: **CLOSED** — plan-time mitigation verified in code.

---

### SEC-002 — INFORMATIONAL: Multisite table isolation verified (plan-time SEC-002 CLOSED)

- **Location**: `vendor/wpboilerplate/wpb-access-control/src/Database/Rule/RuleTable.php` (upstream, verified post-install)
- **OWASP Category**: A01:2025 Broken Access Control
- **CWE**: CWE-668 (Exposure of Resource to Wrong Sphere) — theoretical, not realized
- **CVSS v3.1 Score**: 0.0
- **Spec-Kit Task**: T004 (already checked)

**Description**: Plan-time SEC-002 required verification that upstream v2.0.0 preserves `$global = false` on `RuleTable` (BerlinDB default), giving per-site `{prefix}abilities_access_control` under multisite. Confirmed by `grep '\$global\s*=' vendor/wpboilerplate/wpb-access-control/src/Database/Rule/RuleTable.php` returning zero matches → BerlinDB default of `false` applies. Multisite isolation preserved.

**Status**: **CLOSED**.

---

### SEC-003 — INFORMATIONAL: Strict comparison in `user_has_access()` verified (plan-time SEC-003 CLOSED)

- **Location**: `vendor/wpboilerplate/wpb-access-control/src/AccessControlManager.php`
- **OWASP Category**: A01:2025 Broken Access Control
- **CWE**: CWE-697 (Incorrect Comparison) — theoretical, not realized
- **CVSS v3.1 Score**: 0.0
- **Spec-Kit Task**: T005 (already checked)

**Description**: Plan-time SEC-003 required no loose comparisons in v2's access hierarchy. Confirmed via `grep -nE '(user_has_access|access_control_key|access_control_value).*[^=!]==[^=]' vendor/wpboilerplate/wpb-access-control/src/AccessControlManager.php` returning zero matches. SEC-04 + `BUG-LOOSE-COMPARISON-BYPASS` not regressed by v2.0.0.

**Status**: **CLOSED**.

---

### SEC-004 — INFORMATIONAL: `permission_callback` return-type audit passed (plan-time SEC-004 CLOSED)

- **Location**: `vendor/wpboilerplate/wpb-access-control/src/RestApi/RulesController.php:227-244` (`check_permission()`)
- **OWASP Category**: A01:2025 Broken Access Control
- **CWE**: CWE-284 (Improper Access Control) — theoretical, not realized
- **CVSS v3.1 Score**: 0.0
- **Spec-Kit Task**: T006 (already checked)

**Description**: Plan-time SEC-004 required verification that all `permission_callback` implementations return `true|false|WP_Error` per Constitution §III REST return-type rule. All 5 `permission_callback` references in v2.0.0's `RulesController.php` map to `array($this, 'check_permission')`. `check_permission()` docblock and body both confirm `@return true|WP_Error` (returns `true` on success, `new WP_Error('rest_forbidden', ..., ['status' => 403])` on failure). `BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE` not regressed.

**Status**: **CLOSED**.

---

### SEC-005 — INFORMATIONAL: Release-note communication delivered (plan-time SEC-005 CLOSED)

- **Location**: `README.txt` = Unreleased changelog + Upgrade Notice sections
- **OWASP Category**: A05:2025 Security Misconfiguration
- **CWE**: CWE-1053 (Missing Documentation for Design) — theoretical, not realized
- **CVSS v3.1 Score**: 0.0
- **Spec-Kit Task**: T023 (already checked)

**Description**: Plan-time SEC-005 required release-note communication covering three bullets: (1) legacy rules no longer read, (2) admins must audit + reconfigure, (3) legacy table left orphaned with manual-drop SQL provided. All three bullets present in the shipped README.txt Unreleased entry AND in the Upgrade Notice. The Upgrade Notice specifically warns admins about the audit-and-reconfigure requirement.

**Status**: **CLOSED**.

---

### SEC-006 — LOW: JS-side undefined-slug fallback missing (NEW finding from `2a6d8c5`)

- **Location**: `src/js/abilities/components/AbilityForm.jsx:546` and `:1495`
- **OWASP Category**: A05:2025 Security Misconfiguration (defensive-coding subclass)
- **CWE**: CWE-754 (Improper Check for Unusual or Exceptional Conditions)
- **CVSS v3.1 Score**: 2.4 (AV:N/AC:H/PR:H/UI:N/S:U/C:N/I:N/A:L) — Low
- **Spec-Kit Task**: TASK-SEC-006

**Description**: The follow-up JS fix at `2a6d8c5` interpolates `abilitiesConfig.access_control_slug` into the manual save URL (line 546) and passes it as the `pluginSlug` prop to `<AccessControl>` (line 1495). If the value is `undefined` (e.g., stale browser cache serving pre-`2a6d8c5` HTML that lacks the localize key), the URL becomes `/wpb-ac/v1/undefined/rules/...` → vendor's REST server returns 404 → `apiFetch(...).catch(() => {})` swallows the error → user sees an empty-state UI with no on-screen error.

**Security impact**: **None direct**. No data leak (404 is a standard response, no info disclosed). No auth bypass (REST endpoints still enforce their own `permission_callback` returning `true|WP_Error`). No injection (interpolation happens client-side into a URL the client is authorized to issue). The impact is purely resilience — the user is confused about why their save didn't persist. This is the same failure signature that surfaced the underlying bug during your live-install feedback ("I am not see the whole value 5").

**Attack scenario**: Not practically exploitable. A stale cache on the admin's browser produces user confusion for one page-load until they hard-refresh; there's no mechanism for an attacker to force `access_control_slug` to `undefined` server-side (it's a hardcoded PHP constant, not user input).

**Recommended Mitigation**: Add a defensive early-return at both call sites:

```js
// Line 546 area, before constructing acUrl:
if (!abilitiesConfig.access_control_slug) {
    console.error('Access control save skipped: access_control_slug not localized (upgrade to Feature 039+ or clear browser cache).');
    return;  // or fall through the try block bypassing the AC save; ability save already succeeded
}
```

For line 1495 the component itself would need the fallback — since we don't own the vendor component, an alternative is a conditional render:

```jsx
{!isCreate &&
    savedAbility?.ability_slug &&
    abilitiesConfig.access_control_available &&
    abilitiesConfig.access_control_slug && (   // ← added guard
        <AccessControl
            pluginSlug={abilitiesConfig.access_control_slug}
            ...
        />
    )}
```

**Priority**: LOW. Not blocking for this branch's merge — the bug requires a stale-cache condition that self-heals on refresh. Fold into a subsequent hardening PR, or accept as-is with a follow-up task.

**Status**: **OPEN** — remediation optional; not blocking.

## Confirmed Secure Patterns (as-shipped verification)

The staged branch diff **preserves** all pre-existing secure patterns without regression. Explicit re-verification for merge confidence:

| Pattern | Location | Preserved? | Evidence |
|---|---|---|---|
| **Fail-open admin notice + `manage_options` gate** (`DEC-FAIL-OPEN-NOTICE`) | `includes/Main.php:335-349` | ✅ | SEC-001 diff verification (T011) |
| **Admin notice self-containment** (`PATTERN-ADMIN-NOTICE-SELF-CONTAINED`) | Both notice closures | ✅ | Unchanged by diff |
| **Uninstall data gate** (`PATTERN-UNINSTALL-DATA-GATE`, `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`) | `uninstall.php:22-46` | ✅ | New drop + delete_option lines stay inside the existing `if ( $acrossai_delete_data )` block |
| **`$wpdb->prepare('DROP TABLE IF EXISTS %i', ...)` for schema identifiers** | `uninstall.php:29-31` | ✅ | `%i` placeholder preserved |
| **BerlinDB soft-singleton in activator** (`DEC-TABLE-SOFT-SINGLETON`) | `AcrossAI_Activator::activate()` | ✅ | Third sibling `( new XYZ_Table() )->maybe_upgrade();` line follows the existing pattern |
| **Freemius per-plugin isolation** (`DEC-FREEMIUS-PER-PLUGIN-INIT`) | `includes/Main.php:326-333` | ✅ | Per-plugin credentials (fs_product_id / fs_public_key / fs_slug) still flow through the AddonsPage args array |
| **`class_exists` guard on external package** (`DEC-EXTERNAL-PACKAGE-HOOK-CTOR`) | `includes/Main.php:324` | ✅ | Unchanged |
| **Namespace/cache/REST slug isolation** (multi-plugin coexistence goal) | `TABLE_SLUG='abilities'` propagates deterministically | ✅ | Grep shows exactly 3 PHP consumers + 1 localize + 2 JS consumers, single source `AcrossAI_Abilities_Access_Control::TABLE_SLUG` |
| **Localized data minimization** | `admin/Main.php:257-266` | ✅ | New `access_control_slug` key is a hardcoded literal (`'abilities'`); no PII, no secret, no user-controlled value. `wp_json_encode()` used for output |
| **Nonce discipline** | `admin/Main.php:257` `wp_create_nonce('wp_rest')` | ✅ | Unchanged; existing pattern |

## Supply-Chain Audit

Composer changes: `wpb-access-control ^1.6.0 → ^2.0.0`, `main-menu ^0.0.4 → ^0.0.8`, `addons-page` dropped, `freemius/wordpress-sdk ^2.0` arrives transitively.

- **`composer update` output**: "No security vulnerability advisories found."
- **`freemius/wordpress-sdk`**: pinned via lockfile to a specific SHA. Not new to this plugin's dependency tree (was transitive via the old `addons-page` package); only the direct-vs-transitive shape changed.
- **`wpb-access-control` v2.0.0**: post-install vendor code verification (SEC-002/003/004 above) confirms upstream did not regress the four security patterns this plugin relies on.
- **`main-menu` v0.0.8**: constructor signature `(?string, array, string)` verified stable; `SUBMENU_SLUG` rename `wpb-addons → acrossai-addons` documented in Upgrade Notice (user-facing, non-security impact).

**CWE-1104 (Use of Unmaintained Third-Party Components)**: not applicable — both packages are actively maintained (main-menu had 3 commits in the last 24h; wpb-access-control just cut v2.0.0).

## Action Plan

1. **Address SEC-006**: **Optional / non-blocking**. If closing before merge, add the two defensive guards described above (LOW, ~4 lines of code, ~5 min work). Otherwise, log as a follow-up task and merge as-is — the bug requires a stale-cache condition and self-heals on refresh.
2. **Close SEC-001 through SEC-005**: **Already done.** All 5 plan-time findings confirmed remediated in the shipped code. No further action needed.
3. **Merge decision**: **PASS.** Zero CRITICAL / HIGH / MEDIUM findings across both plan-time and staged reviews. Branch is release-ready pending your 6 outstanding manual verifications (T014, T018, T019, T020, T021, T028 — T015 was implicitly validated by the live-install feedback that produced SEC-006's underlying bug).
4. **Post-merge follow-up (optional)**: capture SEC-006 as a durable BUG pattern if a similar undefined-localize condition surfaces in a future feature. Not warranted from one instance.
5. **Durable Memory Preservation**: **NOT triggered.** No systemic vulnerabilities. No new reusable security pattern surfaced by this review — the SEC-002/003/004 verification-gate pattern was already captured via `DEC-REVALIDATE-SECURITY-POST-UPGRADE`, and the JS-audit lesson from the follow-up commit is already captured as `PATTERN-VENDOR-LIB-JS-CONSUMER-AUDIT` + `BUG-VENDOR-LIB-JS-URL-SLUG-MISSING`. This staged review adds only SEC-006 (a resilience gap tied to one specific code path), which doesn't rise to a durable memory candidate.

---

## Memory Hub INDEX.md Row

```text
| specs/039-composer-package-updates/security-review-staged.md | staged | 2026-07-01 | LOW | C:0 H:0 M:0 L:1 I:5 | A04,A05,A06 |
```

Paste under the "Security Reviews" table in `docs/memory/INDEX.md` (append after the Feature 039 plan-review row).

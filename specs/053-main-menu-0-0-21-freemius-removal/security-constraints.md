---
document_type: security-review
review_type: plan
assessment_date: 2026-07-17
codebase_analyzed: acrossai-abilities-manager (Feature 053 branch)
total_files_analyzed: 6
total_findings: 0
overall_risk: INFORMATIONAL
critical_count: 0
high_count: 0
medium_count: 0
low_count: 0
informational_count: 2
owasp_categories: [A05, A09]
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

# SECURITY REVIEW REPORT — Feature 053

## Executive Summary

Feature 053 REDUCES the plugin's external-service surface: the `freemius/wordpress-sdk` dependency (and its ability to phone home to `freemius.com` on administrator connect action) is removed entirely. The three code-level scope areas — Composer bump, Freemius removal, header row layout, self-filter — introduce zero new user-input surfaces, zero new REST routes, zero new capability boundaries, zero new database columns or option keys. Per the standing user rule (`Skip permission_callback REST audit`), the pre-existing REST route gates on `POST /acrossai-abilities-library/v1/abilities/config` and any other permission_callback are treated as an unchanged baseline and not re-audited line-by-line.

**Overall risk: INFORMATIONAL.** Two informational items are recorded below — both are stale-state observations post-Freemius-removal, not vulnerabilities in the shipped diff.

## Plan artifacts reviewed

- `specs/053-main-menu-0-0-21-freemius-removal/spec.md` (16 FRs, 3 user stories, 3 Clarifications)
- `specs/053-main-menu-0-0-21-freemius-removal/plan.md` (Constitution v1.4.8 gate, Phase 0 research, Phase 1 design, re-check)
- `specs/053-main-menu-0-0-21-freemius-removal/memory-synthesis.md`
- Modified source: `composer.json`, `composer.lock`, `admin/Main.php`, `admin/Partials/LibraryMenu.php`, `includes/Main.php`, `src/js/ability-library/components/LibraryPage.js`, `src/scss/ability-library/admin.scss`, `README.txt`

## Trust boundaries and threat assumptions

- **External services**: **REDUCED.** Prior release depended on Freemius (`freemius.com`); Freemius removal eliminates that channel. Free add-ons install via WordPress core plugin installer, which contacts `wordpress.org` — a WordPress-core-native trust boundary, not attributable to this plugin.
- **Admin authorization**: unchanged. Existing `manage_options` gate on the Library page + REST route unchanged.
- **Filter contract**: the new `acrossai_addons` filter callback receives its input from `\AcrossAI_Main_Menu\AddonsPageRenderer` via `apply_filters('acrossai_addons', $baseline)`. Input is trusted (comes from a vendored package + other consumer plugins' filter callbacks). Callback is defensive against malformed input (non-array, missing `slug` key).

## Authentication, authorization, session decisions

- No new authentication surface. No changes to nonce or capability requirements.
- The Add-ons page's Install / Activate / Deactivate flows are handled by `\AcrossAI_Main_Menu\AddonsAjaxHandlers` (vendored, out of this plugin's scope). Those handlers enforce standard WordPress `install_plugins` / `activate_plugins` capabilities.
- Session semantics unchanged.

## Data flow, privacy, minimization

- **Data collected / transmitted by this plugin**: zero. Freemius was the only external-transmission channel; now removed. `README.txt § Privacy Policy` rewritten to state "This plugin does not collect, store, or transmit any user data."
- No PII, no telemetry, no analytics.
- Multisite isolation unchanged (already inherited from `AcrossAI_Ability_Library_Config` via `get_site_option()`).

## Dependency and platform choices

- **Removed**: `freemius/wordpress-sdk 2.13.3` (entire ~2,000-file vendored SDK tree).
- **Bumped**: `acrossai-co/main-menu 0.0.14 → 0.0.23` (3-hop journey through 0.0.21, 0.0.22, 0.0.23); `automattic/jetpack-autoloader v5.0.20 → v5.0.21` (transitive).
- **Zero new dependencies added.** No new npm packages.
- Target platform unchanged: PHP 8.1+, WP 6.9+, multisite-compatible.

## Validation, logging, error handling

- Server-side validation unchanged. No new REST endpoints; `sanitize_entry()` in `AcrossAI_Ability_Library_Config` unchanged.
- No new logging surface. No new `error_log()` calls; `PATTERN-WP-DEBUG-LOG-GUARD` not exercised.
- The former `try/catch/admin_notices` block around the `\AcrossAI_Addon\AddonsPage` instantiation was deleted along with the class-reference — the class no longer exists in 0.0.21+, so the block would fail-open silently regardless. Deletion is the right call. The remaining Feature-038 admin-notice bootstrap for `SettingsPage` (in `acrossai-abilities-manager.php`) is unchanged.

## Secrets handling & deployment hardening

- N/A — no secrets involved. The former `fs_product_id: '34418'` and `fs_public_key: 'pk_d61a7ddb1a619f7697fbb4fc397b6'` args were public identifiers, not secrets, and are now removed from the codebase.
- No CI/CD workflow changes.

## Vulnerability findings

None.

## Confirmed secure patterns

- **Freemius removal reduces external-service surface.** No new attack surface added.
- **Self-filter is defensive.** `filter_out_self_from_addons()` in `admin/Main.php` guards against non-array input and non-array entries — malformed data from other plugins' filter callbacks cannot cause a fatal.
- **Filter registration follows Boot Flow Rule.** Wired via `$this->loader->add_filter(...)` on the existing `$plugin_admin` named variable — `AC-HOOKS-MAIN` respected.
- **Reuse of already-gated vendored surface.** The Add-ons page rendering, AJAX handling, and plugin installation are all owned by `\AcrossAI_Main_Menu\Addons*` classes (vendored). This plugin does not re-implement any of those flows; the self-filter is a pure data-transform on the entry list.
- **DOM contract preserves a11y H1 invariant.** Moving `<h1>Ability Library</h1>` from PHP-rendered to React-rendered maintains exactly one H1 per page (verified via `wp-admin` DOM inspection).
- **Historical changelog entries preserved.** README updates avoid rewriting the 0.0.1 / 0.0.6 changelog and Upgrade Notice sections that mention Freemius — those are historical record.

## Informational items

### [INFO] SEC-053-I-001 — Stale Freemius options may remain in wp_options on upgraded sites
**Location**: N/A — pre-existing installations, not new code
**OWASP Category**: A05:2025 — Security Misconfiguration (stale-state hygiene)
**CWE**: none
**CVSS Score**: 0.0 (informational — no exploitation path)
**Description**: Administrators upgrading from 0.0.7 (or earlier) may have `fs_*` or `freemius_*` prefixed rows in `wp_options` from the previously-active Freemius SDK. Post-upgrade those rows are inert (nothing reads them), but they consume storage and could confuse operators inspecting the option table.
**Remediation**: Optional. If concerning to your operations team, delete via WP-CLI: `wp option list --search='fs_*' --json | jq -r '.[].option_name' | xargs -I% wp option delete %`. A future release could optionally fold this cleanup into `uninstall.php` behind the `acrossai_abilities_uninstall_delete_data` opt-in — deferred as an out-of-scope follow-up per the spec's Assumptions section.
**Spec-Kit Task**: N/A (operational, not implementation)

### [INFO] SEC-053-I-002 — Historical changelog entries retain Freemius references
**Location**: `README.txt:113`, `README.txt:136`, `README.txt:145` (all inside `== Changelog ==` and `== Upgrade Notice ==` sections for 0.0.1 and 0.0.6)
**OWASP Category**: A09:2025 — Security Logging and Monitoring Failures (documentation integrity)
**CWE**: none
**CVSS Score**: 0.0 (informational — deliberate preservation)
**Description**: The historical changelog entries for prior releases (0.0.1 mentioning "Freemius integration"; 0.0.6 mentioning "Freemius credentials rotated"; 0.0.6 Upgrade Notice mentioning credential rotation) are deliberately preserved as they document what those releases shipped. A future changelog entry for 0.0.8 (which will land Feature 053 as a release) MUST make it clear that Freemius integration is REMOVED going forward — otherwise a user reading the changelog top-down might misinterpret the historical entries as current state.
**Remediation**: When the future 0.0.8 release cycle adds its changelog entry, include an explicit "**Freemius integration removed**" bullet at the top of the 0.0.8 changelog + a clear Upgrade Notice ("this release removes Freemius; if you previously connected an account, that connection is now inert and any stale wp_options rows can be safely deleted").
**Spec-Kit Task**: To be picked up in the release-0.0.8 spec.

## Action Plan

### 1. Durable Memory Preservation (Mandatory Check)

**Trigger `/speckit-memory-md-capture-from-diff` proactively.** Two candidates emerged:

- **DEC-FREEMIUS-PER-PLUGIN-INIT** — mark as **Superseded (Feature 053)** in `docs/memory/DECISIONS.md` and `docs/memory/INDEX.md`. Rationale: the decision codified per-product-id Freemius keying; Feature 053 removes Freemius entirely from this plugin.
- **WORKLOG entry** for Feature 053 — a milestone entry matching the Feature 046 / 052 format documenting the shipped scope, deviations, quality gates, and where-to-look references.

### 2. Remediation

No Critical / High / Medium / Low findings. Both informational items map to future follow-ups (SEC-053-I-001 is operational; SEC-053-I-002 will be addressed in the future 0.0.8 release cycle).

### 3. Post-implementation

Once PR #69 merges, run `/speckit-security-review-staged` against the merged diff to double-confirm the security posture. No blocking work.

---

## Memory Hub INDEX.md Row

```text
| specs/053-main-menu-0-0-21-freemius-removal/security-constraints.md | plan | 2026-07-17 | INFORMATIONAL | C:0 H:0 M:0 L:0 | A05,A09 |
```

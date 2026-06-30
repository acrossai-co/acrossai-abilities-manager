---
document_type: security-review
review_type: staged
assessment_date: 2026-06-30
codebase_analyzed: acrossai-abilities-manager / Feature 038 (working-tree set; git index empty at review time)
total_files_analyzed: 12
total_findings: 2
overall_risk: LOW
critical_count: 0
high_count: 0
medium_count: 0
low_count: 1
informational_count: 1
owasp_categories: [A06]
cwe_ids: [CWE-1104, CWE-829]
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

**Reviewer note**: Index was empty at review time (nothing in `git diff --cached`). Reviewed the working-tree set — the files that will be staged when the user runs `git add`. This is the pre-commit gate for Feature 038 (AcrossAI Main Menu Integration).

## Executive Summary

**Zero NEW vulnerabilities** introduced by the staged delta. All 5 actionable security findings from the plan-phase second-opinion review (SEC-001 self-contained closure, SEC-002 priority-1 activation guard, SEC-004 multi-consumer idempotency, SEC-006 uninstall data minimization, SEC-007 planning-doc drift) are **resolved in the staged code** — verified by inspection of the actual file contents and confirmed by the `tests/phpunit/Includes/Test_Boot_Resilience.php` structural tests.

The 2 remaining findings (SEC-003 0.0.x pin, SEC-005 vendor audit) are dependency-trust concerns. Both were known pre-implementation, depend on the same external action (T004 vendor audit of `vendor/acrossai-co/main-menu/`), and do not block committing the code — they're "should-resolve-before-merge" not "must-resolve-before-commit".

**Overall risk: LOW.** Safe to commit.

## Staged Diff Reviewed

12 plugin source files + 1 out-of-repo coexistence fix:

| File | Touches | Purpose |
|---|---|---|
| `acrossai-abilities-manager.php` | T006, T012 | Host bootstrap + priority-1 activation guard |
| `includes/Main.php` | T010, T011, T015, T018, T024, U002 | Vendor-missing flag, admin notice, Loader registrations, AddonsPage parent slug |
| `admin/Main.php` | T019, T020 | Hook-suffix strings for relocated pages |
| `admin/Partials/Menu.php` | T014 | `main_menu` → `register_submenu` |
| `admin/Partials/LibraryMenu.php` | T016 | Re-parent + position 2 |
| `admin/Partials/LogsMenu.php` | T017 | Re-parent + position 3 |
| `admin/Partials/SettingsMenu.php` | T022, T023, U002, U003 | Delete render/register_submenu, switch to `tab_page_slug('abilities')`, add `register_tab()`, revert section title prefixes |
| `composer.json` | T002, U001 | New dep `acrossai-co/main-menu: ^0.0.4` |
| `composer.lock` | T002, U001 | Regenerated |
| `uninstall.php` | T028 / SEC-006 | `delete_option('acrossai_abilities_per_page')` inside data gate |
| `phpunit.xml.dist` | T008, T009 | New `includes-unit` test suite |
| `tests/phpunit/Includes/Test_Boot_Resilience.php` | T008, T009 (NEW) | 12 structural tests pinning admin-notice/activation-guard contracts |
| `wp-content/mu-plugins/acrossai-tab-smoketest.php` | post-impl coexistence fix | Adds paired `class_exists($fqcn, false)` + `did_action()` guards |

**Untracked spec/doc artifacts** (not security-relevant): `specs/038-…/`, `docs/planning/038-…md`.

## Vulnerability Findings

### LOW — `acrossai-co/main-menu` pinned at 0.0.x dev branch

**Location:** `composer.json:21`

```diff
+ "acrossai-co/main-menu": "^0.0.4"
```

**OWASP Category:** A06:2025-Vulnerable and Outdated Components
**CWE:** CWE-1104: Use of Unmaintained Third Party Components
**CVSS (v3.1, base):** 3.1 — `AV:L/AC:H/PR:N/UI:N/S:U/C:N/I:N/A:L`
**Spec-Kit Task:** TASK-SEC-003 (open, pending T004 in `tasks.md`)

**Description:** The plugin now depends on `acrossai-co/main-menu` at version `^0.0.4`. Under Composer's 0.x rules this locks to `>= 0.0.4, < 0.0.5` (patch-only). The project's existing `DEC-STABLE-UPGRADE-WINDOW` decision says: *"Prioritize first stable releases (v1.0.0, v1.0.1) when upgrading from dev branches."* The dependency is internal AcrossAI org code, not external third-party — but the decision intent (delay-on-0.x) is bypassed. Upgrade history this session: `^0.0.2` → `^0.0.4` mid-implementation to consume the new tabs feature; SEC-003 carried over from the plan-phase review.

**Remediation:**

1. Audit the vendored copy at `vendor/acrossai-co/main-menu/` (capability gates in `SettingsPage::render()` / `DashboardRenderer::render()`, `esc_*` on all variable output, form action pointing to `options.php`).
2. Pin the audited commit SHA (e.g., `dist.reference` in `composer.lock`) until v1.0.0 is cut.
3. Record audit outcome + SHA in `specs/038-acrossai-main-menu-integration/security-review-plan.md` or as a new accepted-deviation in `docs/memory/DECISIONS.md`.

T004 in `specs/038-acrossai-main-menu-integration/tasks.md` covers this. Committing the code does NOT require this to be done first; merging the branch SHOULD.

### INFORMATIONAL — Trust delegation to vendored host package not yet audited

**Location:** `vendor/acrossai-co/main-menu/` (runtime trust surface)

**OWASP Category:** A06:2025-Vulnerable and Outdated Components / A04:2025-Insecure Design (trust boundary)
**CWE:** CWE-829: Inclusion of Functionality from Untrusted Control Sphere
**CVSS:** 0.0 (informational; depends on host package internals)
**Spec-Kit Task:** TASK-SEC-005 (open, same T004 closes both)

**Description:** The shared Settings page and the new `DashboardRenderer` (introduced in v0.0.4) are rendered entirely by the host package. We delegate trust for: page capability gate (assumed `manage_options`), output escaping, form target, and the new dashboard rendering surface at `wp-admin/admin.php?page=acrossai`. The architecture is sound but the actual vendor code has not yet been fully audited end-to-end.

**Partial verification already done** (during the v0.0.4 upgrade investigation): `SettingsPage::__construct` registers `admin_menu` hooks for `register_parent` (capability `'manage_options'`) and `register_settings_submenu` (also `'manage_options'`). `PageRenderer::render()` opens with `if ( ! current_user_can( 'manage_options' ) ) { return; }` and posts to `options.php`. These satisfy the most critical checks. **Not yet inspected**: the new `DashboardRenderer` class and the tab nav-bar HTML output path in `PageRenderer`.

**Remediation:** Same as SEC-003 — vendor audit (T004). Specifically verify:

1. `DashboardRenderer::render()` gates capability + escapes output.
2. Tab nav-bar HTML in `PageRenderer::render_tab_nav()` `esc_html`'s the tab label and `esc_attr`'s the tab slug.
3. Pin commit SHA after audit.

## Confirmed Secure Patterns (verified in the staged code)

- **Admin notice closure is self-contained** (SEC-001 resolved): `includes/Main.php` registers a `static function ()` closure that uses only `current_user_can`, `printf`, and `esc_html__` — zero references to `$this->`, `self::`, `static::`, or any plugin-namespaced FQCN. Structural test `tests/phpunit/Includes/Test_Boot_Resilience.php::test_admin_notice_closure_is_self_contained` enforces this contract going forward.
- **Activation guard at priority 1** (SEC-002 resolved): `acrossai-abilities-manager.php` registers `add_action( 'activate_' . plugin_basename( __FILE__ ), …, 1 )` so the guard fires BEFORE the existing default-priority-10 `acrossai_abilities_manager_activate` callback that would otherwise fatal first on missing vendor.
- **Multi-consumer idempotency** (SEC-004 resolved): bootstrap uses paired `did_action( 'acrossai_main_menu_bootstrapped' )` + `class_exists( …, false )` guards; mu-plugin coexistence fix mirrors the same pattern. Verified live (resolved the "Cannot declare class" fatal observed during implementation).
- **Capability gating on admin notice**: `static function () { if ( ! current_user_can( 'manage_options' ) ) { return; } … }` — aligns with `DEC-FAIL-OPEN-NOTICE`.
- **All new user-facing strings are escaped + text-domain-correct**: `esc_html__( '…', 'acrossai-abilities-manager' )` used uniformly across the admin notice, the `wp_die` activation message, and the new tab label.
- **Settings option NAMES preserved**: `acrossai_abilities_per_page`, `acrossai_abilities_log_retention_days`, `acrossai_abilities_uninstall_delete_data` keep their names exactly. No silent data migration.
- **Sanitizer callbacks preserved**: `sanitize_per_page`, `sanitize_uninstall_flag`, `absint` for log retention — all unchanged. Only the `option_group` and `$page` slug changed.
- **Data-minimization gap closed** (SEC-006 resolved): `uninstall.php` now deletes all three preserved options when `$acrossai_delete_data` is true.
- **Hardcoded-secret scan clean**: zero matches for `password`/`api[_-]?key`/`secret`/`token`/`BEGIN.*PRIVATE` patterns in the diff or untracked files.
- **No new `echo`/`print` without escape**: zero matches in the diff.
- **No new SQL**: zero matches.
- **Filter callback defensively guards input type**: `register_tab( $tabs ): array { if ( ! is_array( $tabs ) ) { $tabs = array(); } … }` — protects against misbehaving sibling filters.
- **mu-plugin coexistence fix is correct** (out-of-repo): uses `class_exists( $fqcn, false )` (the `false` second arg explicitly disables autoload during the check, preventing recursive autoload during degraded state), paired with `did_action()`. Fires the canonical `acrossai_main_menu_bootstrapped` signal after successful instantiation so future consumers short-circuit.
- **Activation guard `wp_die` message uses correct text domain**: `esc_html__( '…', 'acrossai-abilities-manager' )`.
- **No new REST routes, no new AJAX endpoints, no new nonces required**: the form-submit handshake at `options.php` is owned by WordPress core + the host package, not this plugin.

## Confirmed Resolved (from plan-phase review)

| Plan-phase finding | Severity | Status in staged code |
|---|---|---|
| SEC-001 self-contained admin notice closure | MEDIUM | ✅ Resolved |
| SEC-002 activation guard priority 1 | MEDIUM | ✅ Resolved |
| SEC-003 0.0.x pin | LOW | ⏳ Carried over — depends on T004 |
| SEC-004 multi-consumer idempotency | LOW | ✅ Resolved |
| SEC-005 vendor audit | INFO | ⏳ Carried over — depends on T004 |
| SEC-006 uninstall.php per_page | INFO | ✅ Resolved |
| SEC-007 planning-doc line drift | INFO | ✅ Resolved |

## Action Plan

1. **Commit the working tree as-is.** No security finding blocks committing this diff. The two carried-over findings (SEC-003 + SEC-005) are dependency-trust concerns that the commit itself can't address — they need vendor inspection, which is best done against the installed `vendor/acrossai-co/main-menu/` tree.
2. **Before merging the feature branch to `main`**, run T004 (vendor audit on `vendor/acrossai-co/main-menu/`): confirm `MenuRegistrar::register_parent`, `DashboardRenderer::render`, `PageRenderer::render` + `render_tab_nav` all gate capability and escape output; pin the audited commit SHA in composer; record audit outcome in `security-review-plan.md` or as a `DEC-VENDOR-AUDIT-038` entry in `docs/memory/DECISIONS.md`.
3. **No `/speckit-memory-md-capture` invocation needed from this turn.** The systemic patterns surfaced during this branch (admin-notice self-containment, activation-hook priority, paired idempotency guards, tab-mode section-scope rule) are already enumerated in `tasks.md` T032 candidates (a)-(g). No NEW patterns were discovered during this staged scan.

## Memory Hub INDEX.md Row

Append to `docs/memory/INDEX.md` under "Security Reviews":

```text
| specs/038-acrossai-main-menu-integration/security-review-staged.md | staged | 2026-06-30 | LOW | C:0 H:0 M:0 L:1 I:1 | A06 |
```

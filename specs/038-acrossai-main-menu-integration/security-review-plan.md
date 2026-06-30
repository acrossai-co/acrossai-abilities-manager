---
document_type: security-review
review_type: plan
assessment_date: 2026-06-30
codebase_analyzed: acrossai-abilities-manager / Feature 038 (AcrossAI Main Menu Integration)
total_files_analyzed: 9
total_findings: 7
overall_risk: MODERATE
critical_count: 0
high_count: 0
medium_count: 2
low_count: 2
informational_count: 3
owasp_categories: [A04, A05, A06]
cwe_ids: [CWE-665, CWE-754, CWE-829, CWE-1104]
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

# Security Review (Plan Phase) — Feature 038: AcrossAI Main Menu Integration

## Executive Summary

This is the **independent second-opinion** review the `governed-plan` summary recommended after my own inline first pass (in `security-constraints.md`). The two passes diverge: the inline pass concluded **C:0 H:0 M:0 L:0** while this independent pass identifies **2 MEDIUM, 2 LOW, and 3 INFORMATIONAL findings**. The divergence is itself a useful signal — the new findings cluster around boot-time reentrancy and the multi-consumer future-state that motivates the whole feature, neither of which the first pass attacked head-on.

None of the findings rise to HIGH or CRITICAL. None introduce new attack surface visible to anonymous or low-privilege users. The change set's overall effect on the plugin's security posture is **positive**: it converts a deterministic boot-time fatal into a graceful degraded state with a visible admin notice — closing the existing 30-Jun-2026 11:46:52 UTC fatal-trace regression. The findings below are all about **ensuring the new code paths can't themselves regress** during implementation.

**Recommended disposition**: address MEDIUM-1 and MEDIUM-2 in the plan before `/speckit-tasks`. LOW and INFORMATIONAL items can be tracked as follow-ups or accepted with documentation.

## Plan Artifacts Reviewed

- `specs/038-acrossai-main-menu-integration/spec.md`
- `specs/038-acrossai-main-menu-integration/plan.md`
- `specs/038-acrossai-main-menu-integration/research.md`
- `specs/038-acrossai-main-menu-integration/data-model.md`
- `specs/038-acrossai-main-menu-integration/contracts/menu-and-settings-contracts.md`
- `specs/038-acrossai-main-menu-integration/quickstart.md`
- `specs/038-acrossai-main-menu-integration/memory-synthesis.md`
- `specs/038-acrossai-main-menu-integration/security-constraints.md` (inline first pass)
- `docs/planning/038-acrossai-main-menu-integration.md` (authoritative task breakdown)
- Source samples: `acrossai-abilities-manager.php` (lines 55–97), `includes/Main.php` (lines 113–298), `uninstall.php`, `admin/Partials/LibraryMenu.php`, `admin/Partials/LogsMenu.php`
- Memory: `docs/memory/INDEX.md`

## Vulnerability Findings

---

### SEC-001 — Boot-resilience admin-notice callback can itself fatal if it touches autoloaded classes

- **Location**: `includes/Main.php::__construct()` (to be added by TASK-6); admin notice callback registered when `$vendor_missing === true`.
- **Severity**: **MEDIUM**
- **OWASP**: A05:2025-Security Misconfiguration
- **CWE**: CWE-754: Improper Check for Unusual or Exceptional Conditions
- **CVSS (v3.1, base)**: 5.3 — `AV:L/AC:H/PR:H/UI:N/S:U/C:N/I:N/A:H`
- **Spec-Kit task**: TASK-SEC-001

**Problem**

The plan declares that when `$vendor_missing === true`, `Main::__construct()` registers an `admin_notices` callback and `return`s early. That callback fires on `admin_notices` — long after `plugins_loaded`. Without the autoloader, **any reference inside the callback to a namespaced plugin class will fatal** (the original symptom of this whole feature). PHP's autoloader-not-found error during `admin_notices` produces a non-blocking but still-visible PHP error on every admin page render.

The plan and the contracts file (C-005) describe the callback in prose but do not pin the call signature or forbid touching plugin classes. An implementer mirroring the existing pattern at `includes/Main.php:286-298` is fine; an implementer who reaches for a helper from `includes/Utilities/` or who uses `$this->plugin_name` (a property set on the Main instance, which IS still available via closure — fine in this case but easy to break later) creates a latent fatal.

**Why the inline pass missed it**: the inline review focused on §IV controls (sanitization, escaping, capability, nonce) and validated their presence. It did not model the callback's own runtime environment.

**Evidence**

- Existing canonical pattern at `includes/Main.php:286-298` is self-contained — only uses globals (`current_user_can`, `printf`, `esc_html`) and a captured `$error_message` string. It would survive the degraded mode. Anything that *deviates* from this shape risks fatal.
- Plan's contract C-005 specifies the capability gate and escape function but does not lock down "only WP globals; no plugin-namespaced symbols".

**Remediation (must be in plan before `/speckit-tasks`)**

1. Pin TASK-6's admin-notice callback as a **closure mirroring `includes/Main.php:286-298`** verbatim, with the message text changed to the missing-autoloader copy.
2. Forbid the callback from referencing `$this->`, `self::`, `static::`, `use ( $this )`, or any FQCN under `AcrossAI_Abilities_Manager\…`. Allowed references: `current_user_can`, `printf`, `esc_html`, `esc_html__`, `_e`, `__`, `_x`, the captured message string.
3. Add a unit test (or at minimum a quickstart check) that deletes vendor, loads `/wp-admin/`, and confirms the PHP error log is empty.

---

### SEC-002 — Activation-hook ordering: existing callback will run before TASK-6 guard and fatal first

- **Location**: `acrossai-abilities-manager.php:68` (existing `register_activation_hook` callback) vs the TASK-6 guard to be added.
- **Severity**: **MEDIUM**
- **OWASP**: A05:2025-Security Misconfiguration
- **CWE**: CWE-665: Improper Initialization
- **CVSS (v3.1, base)**: 4.0 — `AV:L/AC:H/PR:H/UI:R/S:U/C:N/I:N/A:L`
- **Spec-Kit task**: TASK-SEC-002

**Problem**

The plan adopts the planning-doc's wording: "Register an activation hook... immediately below the existing `acrossai_abilities_manager_run()` invocation at line ~95." That instruction is wrong on two counts:

1. **Wrong line number** — the existing `register_activation_hook( __FILE__, 'AcrossAI_Abilities_Manager\acrossai_abilities_manager_activate' )` call is at **line 68**, not "below line ~95". Line 95 is the `add_action( 'plugins_loaded', ... )` registration, not the activation hook.
2. **Wrong ordering** — `register_activation_hook` translates to `add_action( 'activate_<plugin>', $cb, 10, 1 )`. Multiple registrations stack in registration order at priority 10. If TASK-6 simply calls `register_activation_hook(...)` AFTER the existing line-68 call, the existing `acrossai_abilities_manager_activate` callback runs FIRST. That callback does `require_once .../includes/AcrossAI_Activator.php; \Includes\AcrossAI_Activator::activate()`. If `AcrossAI_Activator::activate()` transitively references autoloaded classes, **it fatals before the TASK-6 guard ever fires** — defeating the guard's entire purpose.

The plan's spec acceptance test (US3, scenario 1) demands "activation is blocked with a friendly error explaining that `composer install` must be run first, no PHP fatal is recorded". An ordering mistake silently breaks that contract.

**Why the inline pass missed it**: the inline review treated TASK-6 as a single atomic edit. The ordering vs the existing activation callback was not modeled.

**Evidence**

- `acrossai-abilities-manager.php:55-57` — existing `acrossai_abilities_manager_activate` requires `includes/AcrossAI_Activator.php` directly (so the file is loaded), but `AcrossAI_Activator::activate()` itself may transitively require autoloaded classes for DB schema setup (BerlinDB tables, etc.).
- `acrossai-abilities-manager.php:68` — existing registration line.

**Remediation (must be in plan before `/speckit-tasks`)**

1. Plan TASK-6 must direct the implementer to register the guard with **priority 1** (or wrap the existing callback) so it runs first:
   ```php
   add_action(
       'activate_' . plugin_basename( __FILE__ ),
       function () {
           if ( ! file_exists( __DIR__ . '/vendor/autoload_packages.php' ) ) {
               wp_die( esc_html__( '…', 'acrossai-abilities-manager' ) );
           }
       },
       1
   );
   ```
   (Or `register_activation_hook` callback that uses `remove_action`+`add_action` to re-order — less clean. Direct `add_action` at priority 1 is preferred.)
2. Plan TASK-6 must correct the line-number reference. The new registration line lives near line 69 (right after the existing activation/deactivation hook pair), NOT "below line ~95".
3. Quickstart Q-008 step 2 must explicitly assert "no fatal" in `debug.log` between the failed `wp plugin activate` call and the `wp_die` rendering.

---

### SEC-003 — Composer dependency pinned at 0.0.x conflicts with DEC-STABLE-UPGRADE-WINDOW

- **Location**: `composer.json` (to be added by TASK-1): `"acrossai-co/main-menu": "^0.0.2"`.
- **Severity**: **LOW**
- **OWASP**: A06:2025-Vulnerable and Outdated Components
- **CWE**: CWE-1104: Use of Unmaintained Third Party Components
- **CVSS (v3.1, base)**: 3.1 — `AV:L/AC:H/PR:N/UI:N/S:U/C:N/I:N/A:L`
- **Spec-Kit task**: TASK-SEC-003

**Problem**

`docs/memory/INDEX.md` records the active decision `DEC-STABLE-UPGRADE-WINDOW`: "Prioritize first stable releases (v1.0.0, v1.0.1) when upgrading from dev branches." TASK-1 pins `^0.0.2`, which under Composer's 0.x rules resolves to `>= 0.0.2 < 0.0.3` — strictly patch-only within the 0.0 release line. That is conservative for forward compatibility, but it **knowingly takes on a 0.x dependency**, putting the plugin under the "dev branch" bar of `DEC-STABLE-UPGRADE-WINDOW`.

The dependency is a SHARED top-level menu owned by an internal AcrossAI org repo, so the supply-chain risk is internal rather than third-party — but the decision's intent (delay-on-0.x) is still bypassed.

**Remediation**

1. Either: pin `acrossai-co/main-menu` at the SHA of the reviewed commit (`^0.0.2` plus `dist.reference` lock) until v1.0.0 is cut, AND record a NEW accepted deviation extending the scope of `DEC-STABLE-UPGRADE-WINDOW` to allow shared-internal AcrossAI org packages on 0.x.
2. Or: hold the feature for v1.0.0 of the host package.
3. Either way, capture the choice via `/speckit-memory-md-capture` so future readers don't repeat the question.

---

### SEC-004 — Multi-consumer race: two AcrossAI plugins both bootstrap `\AcrossAI_Main_Menu\SettingsPage` on `plugins_loaded` P0

- **Location**: `acrossai-abilities-manager.php` (TASK-1 bootstrap site) — at runtime in any installation with ≥2 AcrossAI plugins active.
- **Severity**: **LOW**
- **OWASP**: A04:2025-Insecure Design
- **CWE**: CWE-665: Improper Initialization
- **CVSS (v3.1, base)**: 3.7 — `AV:L/AC:H/PR:H/UI:N/S:U/C:N/I:L/A:L`
- **Spec-Kit task**: TASK-SEC-004

**Problem**

The whole point of Feature 038 (per spec US1) is to allow many AcrossAI plugins to share one top-level menu. The TASK-1 pattern is:

```php
add_action(
    'plugins_loaded',
    function () {
        if ( class_exists( \AcrossAI_Main_Menu\SettingsPage::class ) ) {
            new \AcrossAI_Main_Menu\SettingsPage();
        }
    },
    0
);
```

If two AcrossAI plugins both register this exact bootstrap, BOTH will see `class_exists()` return `true` (because the class is already loaded once the autoloader is registered) and BOTH will instantiate `new SettingsPage()`. The `class_exists` check is **about autoload availability**, not **about prior instantiation**.

What happens next depends entirely on the host package's `SettingsPage::__construct()`:

- If it self-registers `add_action( 'admin_menu', … )` without an idempotency guard, two consumers produce two registrations → potentially duplicate menus, duplicate `do_settings_sections` calls, double form submissions on save.
- If the host implements `SettingsPage` as a singleton with a private constructor, the second `new SettingsPage()` will PHP-fatal (private ctor).
- If the host uses a public ctor but guards internally via `did_action()` / a static flag, the second call is a no-op — the safest design.

The plan trusts the host package without specifying which of those behaviours holds. A future second-consumer plugin (which the spec's US1 explicitly anticipates) could trigger the duplicate-menu or private-ctor-fatal failure mode.

**Remediation**

1. **Plan must require an idempotency guard on the consumer side**:
   ```php
   add_action(
       'plugins_loaded',
       function () {
           if ( did_action( 'acrossai_main_menu_bootstrapped' ) ) {
               return;
           }
           if ( class_exists( \AcrossAI_Main_Menu\SettingsPage::class ) ) {
               new \AcrossAI_Main_Menu\SettingsPage();
               do_action( 'acrossai_main_menu_bootstrapped' );
           }
       },
       0
   );
   ```
   This coordination signal works across multiple consumers without requiring a change to the host package.
2. **Alternative**: confirm with the host-package maintainer that `SettingsPage::__construct()` is itself idempotent (`did_action` or static-flag guarded). Record the answer in the contracts file (C-001) so future consumers don't re-litigate the question.

---

### SEC-005 — Third-party trust delegation: the host Settings page is fully owned by `acrossai-co/main-menu`

- **Location**: contracts file C-001 — `\AcrossAI_Main_Menu\SettingsPage::SETTINGS_SLUG`.
- **Severity**: **INFORMATIONAL**
- **OWASP**: A06:2025-Vulnerable and Outdated Components
- **CWE**: CWE-829: Inclusion of Functionality from Untrusted Control Sphere
- **CVSS (v3.1, base)**: 0.0 (informational; depends on host package)
- **Spec-Kit task**: TASK-SEC-005

**Problem**

This plugin delegates rendering of the shared Settings page to the host package. The plugin trusts the host to:

- Gate the page on a sane capability (we expect `manage_options`).
- Escape its own output at render time.
- Verify the WP Settings API `_wpnonce` correctly (this is standard WP Settings API behaviour; trust is shifted to the host's correct use of `do_settings_sections` / `submit_button`).
- Not introduce its own XSS / CSRF vectors in the host page's chrome (any header, footer, breadcrumb, settings-link area).

The plan does not require a code-level review of the vendored package before pinning. For a non-internal third-party package this would be a higher-severity finding; because `acrossai-co/main-menu` is owned by the same org, it's informational — but the trust delegation is real and should be documented.

**Remediation**

1. Open `vendor/acrossai-co/main-menu/` after `composer update` lands and confirm:
   - The `SettingsPage::render()` (or equivalent) calls `current_user_can( 'manage_options' )` (or stricter).
   - All variable output is wrapped in an `esc_*` function.
   - The form posts to `options.php` (standard WP Settings API endpoint, which handles nonce verification correctly).
2. Record the audited commit SHA in the plan or in `DECISIONS.md` (alongside the SEC-003 recommendation).
3. Add a CI check that the host package's installed version's SHA matches the audited SHA, failing the build if it drifts.

---

### SEC-006 — Pre-existing: `uninstall.php` does not delete `acrossai_abilities_per_page`

- **Location**: `uninstall.php` — only deletes `acrossai_abilities_log_retention_days` and `acrossai_abilities_uninstall_delete_data`, not `acrossai_abilities_per_page`.
- **Severity**: **INFORMATIONAL**
- **OWASP**: A04:2025-Insecure Design (data minimization)
- **CWE**: CWE-1188: Initialization of a Resource with an Insecure Default
- **CVSS (v3.1, base)**: 0.0 (informational; pre-existing, not introduced by this feature)
- **Spec-Kit task**: TASK-SEC-006

**Problem**

This is not a Feature 038 regression — it's pre-existing — but Feature 038's spec lists all three options (per_page, log_retention_days, uninstall_delete_data) as preserved data. After uninstall with "delete data" enabled, `acrossai_abilities_per_page` is left as an orphan row in `wp_options`. Negligible data exposure (just an integer setting) but inconsistent with the documented data-deletion contract.

**Remediation**

1. Add `\delete_option( 'acrossai_abilities_per_page' );` to the `if ( $acrossai_delete_data )` block in `uninstall.php`.
2. This is a tiny one-line fix; bundling it into TASK-4 (the Settings-API task) keeps the data-preservation surface consistent in one commit.

---

### SEC-007 — Planning-doc line-number drift may confuse the implementer

- **Location**: `docs/planning/038-acrossai-main-menu-integration.md` — multiple line-number references to the entry file that don't match the actual file.
- **Severity**: **INFORMATIONAL**
- **OWASP**: A05:2025-Security Misconfiguration (process)
- **CWE**: CWE-1059: Insufficient Technical Documentation
- **CVSS (v3.1, base)**: 0.0
- **Spec-Kit task**: TASK-SEC-007

**Problem**

The planning doc references "the existing `acrossai_abilities_manager_run()` invocation at line ~95" and "register an activation hook... immediately below" that point. The actual `acrossai_abilities_manager_run()` invocation is at line **97**, and the existing `register_activation_hook` calls are at lines **68–69**, not "below line 95". This is just stale prose — but it converges with SEC-002 (ordering), where an implementer following the planning doc literally would register the new activation hook in the wrong place AND at the wrong priority.

**Remediation**

1. Update planning doc TASK-1 line references: `acrossai_abilities_manager_run()` is at line 97; the existing activation/deactivation hook pair is at lines 68–69.
2. Update planning doc TASK-6 to direct the new activation guard to register near line 69 (alongside the existing hooks) at priority 1, not "below line 95".
3. After the edit, do a grep sweep for any other "line ~N" references in the planning doc against current file state.

---

## Confirmed Secure Patterns

The following design choices in the plan are confirmed secure and should NOT change:

- **Option name preservation** (FR-003, C-003) — eliminates an entire class of data-loss-during-rename risks. Net positive vs the "rename to `acrossai-*` prefix" alternative.
- **Sanitizer preservation** (TASK-4 constraint) — the three existing sanitizer callbacks (`SettingsMenu::sanitize_per_page`, `sanitize_log_retention_days`, the checkbox sanitizer following PATTERN-CHECKBOX-SANITIZE) carry forward unchanged. No new input boundary introduced.
- **Capability consistency** — all three plugin submenus and the proposed admin notice gate on `manage_options`. Verified via grep across `admin/Partials/LibraryMenu.php`, `admin/Partials/LogsMenu.php`, and the existing admin-notices closure at `includes/Main.php:286-298`.
- **`uninstall.php` is autoloader-free** — verified: it uses only `$wpdb`, `get_option`, `delete_option`, `delete_site_option`. The "soft-disabled" state introduced by TASK-6 does not break Delete on a vendor-missing install. (Strikes a worry I'd had going in.)
- **`class_exists` guard** on host bootstrap (TASK-1) — standard §V Integration Resilience pattern; correct even if SEC-004's race condition needs additional coordination.
- **No new REST routes, no new AJAX, no new nonces** — the change set's auth-boundary delta is zero. The form-submit handshake at `options.php` is owned by WordPress core + the host package, not this plugin.
- **No new dynamic SQL, no eval, no shell calls** — §II forbidden-function compliance preserved.
- **`esc_html__` with `'acrossai-abilities-manager'` text domain** — plan/contracts pin this on every introduced user-facing string.

## Action Plan & Next Steps

1. **Plan amendments required before `/speckit-tasks`** (MEDIUM findings):
   - **SEC-001**: Add a "callback shape contract" to the plan (or to contracts/C-005) pinning the admin-notice callback as a self-contained closure mirroring `includes/Main.php:286-298`. Forbid plugin-namespaced references in the closure body.
   - **SEC-002**: Correct the planning-doc line references AND require the TASK-6 activation guard to register at priority 1 (or via `add_action( 'activate_<basename>', $cb, 1 )` directly) so it runs before the existing activation callback.
2. **Plan annotations for follow-up** (LOW + INFORMATIONAL):
   - **SEC-003** + **SEC-005**: Audit the host-package `vendor/acrossai-co/main-menu/` after `composer update` and pin the audited commit SHA; capture the audit outcome via `/speckit-memory-md-capture`.
   - **SEC-004**: Add the multi-consumer idempotency guard to TASK-1's bootstrap closure (`did_action( 'acrossai_main_menu_bootstrapped' )` pattern), OR confirm host idempotency and document.
   - **SEC-006**: Fold the one-line `\delete_option( 'acrossai_abilities_per_page' );` into TASK-4 as a defensive sweep.
   - **SEC-007**: Edit the planning doc to fix the line-number references called out in SEC-002 / SEC-007.
3. **Durable Memory Preservation** (per skill's mandatory check): the systemic patterns surfaced by this review warrant capture. Per the user's saved preference (`feedback_user_runs_speckit_commands`), I will **not** auto-invoke `/speckit-memory-md-capture`. Propose the following entries for the user to run capture on manually:
   - **NEW PATTERN — `PATTERN-ADMIN-NOTICE-SELF-CONTAINED`**: degraded-mode admin notices must use only globally-available WP functions; no plugin-namespaced references inside the closure. Anchored to `includes/Main.php:286-298` as the canonical implementation.
   - **NEW PATTERN — `PATTERN-ACTIVATION-HOOK-EARLY-PRIORITY`**: vendor-prerequisite activation guards register at `add_action( 'activate_<basename>', $cb, 1 )` so they execute before any priority-10 activation callback that depends on the prerequisite.
   - **NEW PATTERN — `PATTERN-SHARED-MENU-CONSUMER-IDEMPOTENCY`**: consumers of a shared external menu package guard their bootstrap with `did_action( 'acrossai_main_menu_bootstrapped' )` (or equivalent) to be safe under multi-consumer installations.
   - **SCOPE EXTENSION — `DEC-STABLE-UPGRADE-WINDOW`**: explicit carve-out for shared-internal AcrossAI org packages while they remain pre-v1.0.0.
4. **Remediation Planning** (per skill): no CRITICAL or HIGH findings; `/speckit-security-review-followup` is **not required**. MEDIUM findings can be folded into the existing TASK-1 and TASK-6 prose during `/speckit-tasks` synthesis.

---

## Memory Hub INDEX.md Row

Proposed line to append to `docs/memory/INDEX.md` under the "Security Reviews" table:

```text
| specs/038-acrossai-main-menu-integration/security-review-plan.md | plan | 2026-06-30 | MODERATE | C:0 H:0 M:2 L:2 I:3 | A04,A05,A06 |
```

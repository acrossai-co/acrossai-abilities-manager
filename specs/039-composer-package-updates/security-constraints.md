# Security Review (Plan-Time): Composer Package Updates — Feature 039

**Branch**: `039-composer-package-updates` | **Date**: 2026-07-01 | **Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)
**Review type**: plan-time (pre-implementation)
**Reviewer**: inline (orchestrator Step 4 of `/speckit-architecture-guard-governed-plan`)

## Risk Summary

| Severity | Count | OWASP |
|---|---|---|
| CRITICAL | 0 | — |
| HIGH | 0 | — |
| MEDIUM | 0 | — |
| LOW | 1 | A06 |
| INFORMATIONAL | 3 | A04, A06, A09 |

**Overall risk**: **LOW** — the feature is dependency-update plumbing. No new untrusted input boundaries, no new REST endpoints, no new AJAX handlers, no new admin actions. The two surfaces with security implications (multisite table isolation, fail-open library-absence notices) are pre-existing patterns being preserved across an upstream bump. `DEC-REVALIDATE-SECURITY-POST-UPGRADE` is the only mandatory plan-time gate.

## Trust Boundary Analysis

### B1: Composer dependency resolution (build-time)

**Boundary**: `composer install` / `composer update` resolves upstream packages from VCS + Packagist.

**Trust assumption**: Upstream packages (`acrossai-co/main-menu` v0.0.7, `wpboilerplate/wpb-access-control` v2.0.0, `freemius/wordpress-sdk ^2.0`) are signed-via-VCS and pinned to specific commit SHAs in `composer.lock`. The plugin trusts the `composer.json` `repositories` block (VCS) and Packagist.

**Risk**: A compromised upstream tag could ship malicious code that the plugin would auto-load. This risk exists today; the upgrade does not change its profile.

**Mitigation**: SHA pinning in `composer.lock` (already in effect via Composer's normal lockfile behavior). No new direct dependency requires a custom `repositories` entry — main-menu and Freemius resolve via Packagist; wpb-access-control resolves via the existing VCS entry. **No change to mitigation surface.**

**OWASP**: A06 (Vulnerable and Outdated Components) — mitigated by lockfile SHA pinning.
**Severity**: INFORMATIONAL.
**Action**: None required at plan time. `DEC-STABLE-UPGRADE-WINDOW-INTERNAL-ORG` records the internal-org exemption for main-menu's sub-v1.0.0 status.

---

### B2: Access Control table isolation under multisite

**Boundary**: BerlinDB-managed table creation under multisite — `RuleTable::$global` MUST remain `false` for per-site prefix.

**Trust assumption**: Upstream wpb-access-control v2.0.0 retains the `$global = false` property on `RuleTable` (or equivalent semantics for the new per-consumer architecture).

**Risk**: If upstream silently changed `$global` to `true`, the per-consumer table would become network-wide and a rule set on one sub-site would apply to all sub-sites — a SEC-03 violation that could leak access-control intent across tenants.

**Mitigation**: Verified upstream by direct inspection of `vendor/wpboilerplate/wpb-access-control/src/Database/Rule/RuleTable.php` after `composer update`. The README's "Multisite" section confirms `{prefix}wpb_access_control` (and by extension `{prefix}{slug}_access_control`) uses `$wpdb->prefix` → per-site.

**OWASP**: A04 (Insecure Design) — would only manifest if multisite isolation broke.
**Severity**: INFORMATIONAL (verified mitigation; no current risk).
**Action**: TASK-1 must include "inspect `vendor/wpboilerplate/wpb-access-control/src/Database/Rule/RuleTable.php` for `$global = false`" as a post-composer-update verification step. Block the rest of the implementation if this property is not present or is `true`.

---

### B3: Fail-open library-absence admin notices (manage_options gate)

**Boundary**: When the AddonsPage class is absent (vendor missing), the existing try/catch around `new \AcrossAI_Addon\AddonsPage(...)` registers an `admin_notices` closure. Similarly, `AcrossAI_Abilities_Access_Control::maybe_show_library_notice` registers an admin notice when the AccessControlManager class is absent. Both gates fire visibly only for `manage_options`-capable users.

**Trust assumption**: Notice closures continue to gate on `current_user_can('manage_options')` and escape all output with `esc_html()`. Compliance per `DEC-FAIL-OPEN-NOTICE` and `PATTERN-ADMIN-NOTICE-SELF-CONTAINED`.

**Risk**: If TASK-2 inadvertently removes the `current_user_can` check or the `esc_html` escaping while editing the AddonsPage block, the notice could leak vendor error details (e.g., a Freemius API error message) to non-admin users — minor information disclosure.

**Mitigation**: TASK-2 explicitly preserves the try/catch + admin_notices closure block (lines 322–347) unchanged. Only the inner `new \AcrossAI_Addon\AddonsPage(...)` arguments change. The capability gate and `esc_html` calls inside the closure are not touched.

**OWASP**: A06 (Vulnerable and Outdated Components) — adjacent; A09 (Security Logging and Monitoring Failures) — adjacent if notices were silenced.
**Severity**: LOW — bounded by reviewer enforcement of the "do not modify the closure body" constraint during implementation. Architecture review should diff lines 322–347 verbatim.
**Action**: Architecture-review gate (`/speckit-architecture-guard-architecture-review`) must verify that lines 322–347 of `includes/Main.php` are byte-for-byte unchanged except for the AddonsPage constructor arg list. Add a pinned diff assertion to the architecture-verify pass.

---

### B4: Slug input validation (defense in depth)

**Boundary**: `AccessControlManager::__construct( string $providers_filter, string $table_slug = '' )` and `RuleTable::__construct( string $table_slug )` validate `$table_slug` against `^[a-z0-9_]{1,32}$` upstream.

**Trust assumption**: The plugin passes `'abilities'` as a hardcoded constant (`TABLE_SLUG`). This value is known to pass validation. No user input reaches the slug argument.

**Risk**: Future maintainer changes the constant to a value containing slashes, hyphens, or capital letters → `\InvalidArgumentException` at construction time → fatal on plugin activation OR on first admin request after activation.

**Mitigation**: PHPStan level 8 will not catch slug-format mismatches (the constant is a `string`). Acceptable mitigation: the slug is set once in this feature and not expected to change. If a future maintainer does change it, the upstream library's loud-fail behavior is the right outcome — better than silently falling back to a shared table.

**OWASP**: A04 (Insecure Design) — adjacent; the validation is upstream, not consumer-side.
**Severity**: INFORMATIONAL.
**Action**: None required at plan time. Optional improvement: add a one-line PHPUnit assertion that `TABLE_SLUG` matches the validation regex.

---

## Authorization & Capability Audit

- **Add-ons submenu**: Registered upstream with `'install_plugins'` capability — unchanged across the package move.
- **Access Control REST routes** (`/wpb-ac/v1/abilities/...`): Default `manage_options` per upstream; overridable via `wpb_access_control_rest_permission` filter — unchanged. `DEC-PERM-CB` (AC rule-gated permission_callback injection) continues to apply with no scope change.
- **Admin notices** (both vendor-absence paths): `manage_options` gate per `DEC-FAIL-OPEN-NOTICE` — preserved.
- **Uninstall data deletion**: Opt-in via `acrossai_abilities_uninstall_delete_data` option — preserved. The new table-drop and option-delete lines are inside the existing gate (`PATTERN-UNINSTALL-DATA-GATE`).

**No new admin actions, no new AJAX endpoints, no new nonces.** The feature does not introduce any new authorization decision.

## Async / Background Context

- **BerlinDB `maybe_upgrade()`** runs synchronously inside the activation hook (existing pattern). No new async surface introduced.
- **Freemius transitive dependency** introduces no new background HTTP calls beyond what `acrossai-co/addons-page` v0.0.19 already performed; the Add-ons UI behavior is logically unchanged.
- **No Action Scheduler / WP-Cron additions.**

## Data Isolation & Validation Risks

- **Per-consumer table isolation**: The whole point of this upgrade. Verified at B2.
- **REST namespace isolation**: New routes at `/wpb-ac/v1/abilities/...`; legacy `/wpb-ac/v1/rules/...` (non-slug) routes are no longer registered by the v2 manager. This is a deliberate breaking change — any external integration that hit the legacy routes will receive 404. Release-note communication (spec FR-012) covers this for human consumers; programmatic consumers are out of scope.
- **Cache group isolation**: `wpb_ac_abilities` (new) replaces the shared `wpb_ac` group from v1. No data leakage path between the two cache groups.

## Post-Upgrade Security Re-validation Gate (DEC-REVALIDATE-SECURITY-POST-UPGRADE)

This is a **mandatory** plan-time-and-implementation-time gate. After TASK-1 (composer update) lands and before TASK-3/4 reference the new manager/table constructors:

1. **SEC-03 (multisite per-site prefix)**: Inspect `vendor/wpboilerplate/wpb-access-control/src/Database/Rule/RuleTable.php` — confirm `$global` is `false` or absent (BerlinDB default). **Blocking if violated.**
2. **SEC-04 (strict type comparison in AC checks)**: Inspect `vendor/wpboilerplate/wpb-access-control/src/AccessControlManager.php` `user_has_access()` — confirm strict comparison still applies; no loose `==` introduced. **Blocking if violated.**
3. **DEC-PERM-CB (AC rule-gated permission_callback injection)**: Confirm `register_rest_api()` still hooks into `rest_api_init` (unchanged); the consumer's `register_rest_api()` call shape is preserved. **Verify at runtime via test endpoint.**
4. **DEC-FAIL-OPEN-NOTICE (manage_options-gated admin notice)**: Diff `includes/Main.php` lines 322–347 byte-for-byte against pre-change baseline (modulo the constructor arg list). **Architecture-review gate enforces this.**
5. **BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE**: Inspect `vendor/wpboilerplate/wpb-access-control/src/Rest/...` controllers — confirm no `permission_callback` returns `WP_REST_Response` (would silently grant access). **Blocking if violated.**

If any of the five blocking checks fail, halt implementation and surface to the user for upstream coordination before proceeding.

## Recommendations

| ID | Severity | Action | Owner | Phase |
|---|---|---|---|---|
| REC-01 | INFORMATIONAL | Add a post-composer-update verification step to TASK-1: inspect `RuleTable.php` for `$global = false`. | implementer | TASK-1 |
| REC-02 | LOW | Architecture-review pass must verify lines 322–347 of `includes/Main.php` are byte-for-byte unchanged except for the AddonsPage constructor arg list. | architecture-guard | post-TASK-2 |
| REC-03 | INFORMATIONAL | Optional: add a PHPUnit assertion that `AcrossAI_Abilities_Access_Control::TABLE_SLUG` matches `^[a-z0-9_]{1,32}$`. | implementer | optional |
| REC-04 | INFORMATIONAL | Run the full DEC-REVALIDATE-SECURITY-POST-UPGRADE 5-point gate after `composer update` and before TASK-3 begins. | implementer | between TASK-1 and TASK-3 |

## Verdict

**Security gate: PASS** — proceed to `/speckit-tasks`. One LOW-severity finding (REC-02) is mitigated by the architecture-review pass already scheduled in the governed workflow. Three INFORMATIONAL findings are advisory.

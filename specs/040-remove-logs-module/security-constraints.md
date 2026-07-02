# Security Constraints — Plan-time notes (Feature 040)

**Branch**: `040-remove-logs-module` | **Date**: 2026-07-01
**Review type**: plan-time (pre-implementation), inline via orchestrator Step 4
**Companion artifact**: to be produced separately by `/speckit-security-review-plan` (paired formal report with YAML frontmatter + SEC-NNN findings)

## Risk Profile

**Overall risk**: **LOW / INFORMATIONAL** — this is a full-module REMOVAL. The net effect is **attack-surface reduction**: one admin submenu, one Settings API field, one REST namespace, one permission_callback wrapper, and one custom DB table are all going away. No new attack surface is introduced.

## Trust Boundary Analysis

### B1: REST namespace `acrossai-abilities-log/v1` — REMOVED

**Change**: TASK-1 removes the entire namespace. `GET /wp-json/acrossai-abilities-log/v1/logger/logs` and all sub-routes 404 post-Feature 040.

**Security impact**: **Positive.** One less authenticated REST endpoint on the plugin's attack surface. The endpoint required `manage_options` — a properly-gated admin endpoint — but any endpoint is still a surface. Its removal is a net win.

**Communicated via**: FR-011 release notes; external integrations must migrate to the companion plugin (once available) or their own logging solution.

**Verification**: FR-004 grep + manual `curl` after implementation.

### B2: Logger admin submenu + `is_logs_page` guard — REMOVED

**Change**: TASK-1 removes `admin/Partials/LogsMenu.php` (menu class) and the `is_logs_page`-guarded CSS/JS enqueue blocks in `admin/Main.php`.

**Security impact**: **Positive.** One less admin page + one less JS bundle running in admin context. Reduces script surface and eliminates a potential XSS vector (however slim) for anyone finding a way to inject into logger's `wpbAcConfig`-style localize globals.

**Verification**: FR-002 manual walkthrough.

### B3: Settings API `log_retention_days` field — REMOVED

**Change**: TASK-2 removes the `register_setting` + `add_settings_section` + `add_settings_field` calls + the `sanitize_log_retention_days()` + `render_log_retention_days_field()` methods.

**Security impact**: **Neutral to positive.** The sanitize callback was correct (integer cast), the render callback was properly escaped. Removing the field removes one input surface and one form field the WP Settings API had to nonce-protect. No auth-related change.

**Verification**: FR-003 verified via Settings page manual walkthrough.

### B4: `wp_register_ability_args` P100001 permission_callback wrapper — REMOVED

**Change**: TASK-3 removes `add_filter( 'wp_register_ability_args', $logger, 'wrap_permission_callback', 100001, 2 )`.

**Security impact**: **Neutral.** The wrapper (`DEC-VARIADIC-CALLBACK-WRAP`) wrapped every ability's `permission_callback` to log denials — it did NOT add or modify authorization. After Feature 040, wrapped callbacks fire directly without observability. **Auth semantics are unchanged**; only visibility is reduced.

**Related to `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` and `BUG-WP-ABILITY-CHECK-PERMISSIONS`**: those bugs are about `check_permissions()` being probed as a non-existent method — they don't involve the Logger's wrapper. Removing the wrapper does not re-open those bugs; the fixes for those live in the Abilities module and are unaffected.

**Verification**: FR-005 grep for the 4 upstream hook names returns zero matches in `includes/` + `admin/`. Also: FR-010 formalizes that no plugin-side code registers callbacks on these hooks post-Feature 040 — companion plugin extensibility contract.

### B5: `uninstall.php` opt-in gate — extended (TASK-5)

**Change**: TASK-5 adds three new destructive operations inside the existing `if ( $acrossai_delete_data ) { ... }` gate:
- `DROP TABLE {prefix}acrossai_ability_logs` (via `$wpdb->prepare('DROP TABLE IF EXISTS %i', ...)` — safe identifier escaping)
- `delete_option( 'acrossai_ability_logs_db_version' )`
- `as_unschedule_all_actions( 'acrossai_ability_logger_cleanup' )` — guarded by `function_exists()` (safe when AS is inactive)

**Security impact**: **Neutral.** All additions stay INSIDE the opt-in gate (`PATTERN-UNINSTALL-DATA-GATE` preserved; `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE` guarded against). The `%i` placeholder for the table identifier is the correct WP-safe pattern per Constitution §II SQL identifier escaping. `as_unschedule_all_actions` is a WP Action Scheduler API call — safe.

**Verification**: SC-005 confirms zero rows remain after opt-in uninstall.

### B6: Action Scheduler queue on existing installs (upgrade path) — orphaned

**Change**: On an upgraded install, pending `acrossai_ability_logger_cleanup` queue entries remain in the AS database until either (a) the AS scheduler dequeues them (whereupon `do_action('acrossai_ability_logger_cleanup')` is a no-op because the callback no longer exists — no fatal) or (b) opt-in uninstall clears them via TASK-5.

**Security impact**: **None.** No hooked-callback = no code executes. The queue entries are inert database rows. Not a DoS vector (AS is throttled per WP's own rules). Not an info-disclosure vector. Not an auth surface.

**Verification**: Edge case documented in spec.md; no explicit task needed.

## Data Isolation & Validation Risks

- **Legacy logs data (existing installs)**: sits in the orphaned `{prefix}acrossai_ability_logs` table. Nothing in the Feature 040 codebase reads or writes it. **Not a data-leak vector** — the data was already admin-authored (or system-authored under admin authority) and the table is per-site under multisite (BerlinDB default `$global = false`). Sitting orphaned in the DB does not change its visibility to non-admin users (they never had access via WP APIs to begin with; the removed REST endpoint was `manage_options`-gated).
- **Retention option (`acrossai_abilities_log_retention_days`)**: also orphaned on upgraded installs. It's a single integer. No sensitive information.
- **AS queue entries**: contain the hook name (`acrossai_ability_logger_cleanup`) and a schedule time. No sensitive information.

## Post-Removal Security Re-validation

**No `DEC-REVALIDATE-SECURITY-POST-UPGRADE` gate required** — Feature 040 does not upgrade or replace a security-relevant library. The security-relevant components being removed (permission_callback wrapper at P100001, REST endpoints) were observability-only or standard admin-gated; their removal does not require post-hoc re-validation.

**However**, the Feature 039 re-validation gate for wpb-access-control v2 (`SEC-002/003/004`) continues to apply to the Access Control panel that Feature 040 does NOT touch. Confirm by regression walkthrough that TASK-1 through TASK-8 leave the Access Control panel functional — this is subsumed by US1's acceptance scenario 3.

## Recommendations

| ID | Severity | Action | Owner | Phase |
|---|---|---|---|---|
| REC-01 | INFORMATIONAL | The final full-repo grep audit (per `docs/planning/040-remove-logs-module.md` verification checklist) is a MERGE-BLOCKER. Not just a nice-to-have. | implementer | pre-merge |
| REC-02 | INFORMATIONAL | When formally re-running `/speckit-security-review-plan`, produce the paired `security-review-plan.md` with YAML frontmatter + SEC-NNN findings + INDEX row. This file is lightweight planning notes only. | reviewer | between plan + tasks |
| REC-03 | INFORMATIONAL | Verify (as part of US1 acceptance) that removing the P100001 wrapper does not unintentionally re-expose any bug fixed in Feature 032 (`BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS`) or Feature 028 (`BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE`). Grep + PHPStan L8 should catch dangling references. | implementer | post-TASK-3 |

## Verdict

**Security gate: PASS.** Removal-only feature; net attack-surface reduction; no new surfaces; all destructive uninstall operations stay inside the existing opt-in gate. Two lightweight informational recommendations (REC-01 grep as merge blocker, REC-02 formal review artifact) and one regression-adjacent verification (REC-03) are all folded into the existing task/verification structure — no new task required.

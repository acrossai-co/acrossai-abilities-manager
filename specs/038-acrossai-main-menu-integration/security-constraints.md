# Security Constraints: AcrossAI Main Menu Integration

**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

**Note**: This file was generated **inline** by the `governed-plan` orchestrator because the user prefers to invoke each `/speckit-*` command manually (no auto-chaining). The user may re-run `/speckit-security-review-plan` later for an independent second-opinion review. This inline pass applies Constitution §IV Security First and the project's existing security memory entries against the change set.

## Scope of this review

The change set affects:

1. **Admin menu registration** (no auth boundary — admin-side only, all callers already gated by WP admin auth).
2. **Plugin entry-file bootstrap** (no user input; no DB; no network).
3. **WP Settings API option re-registration** (preserves existing sanitizer callbacks verbatim — no new sanitization surface introduced).
4. **`$hook_suffix` comparison strings** (string equality; no user input).
5. **Boot-resilience admin notice + activation guard** (new admin-side surfaces; require capability + escaping review).

The change set does NOT affect:

- REST endpoints, AJAX endpoints, nonces, REST `permission_callback`s.
- Database writes, queries, or schema (no new tables; option names preserved; sanitizers preserved).
- File uploads or filesystem mutations.
- Outbound HTTP (`wp_remote_*`).
- Client-side code (no JS bundle, no SCSS changes).
- Access control rules (`wpb-ac/v1` untouched).
- Multisite isolation boundaries (`SEC-03` continues to apply unchanged — the relevant `$global = false` setting on `AcrossAI_Abilities_Table` is not in scope here).

## Per-control review against §IV

| §IV control | Applies? | Status | Notes |
|---|---|---|---|
| Input sanitization at boundaries | No new boundary | ✅ N/A | The three preserved options keep their existing sanitizer callbacks (`SettingsMenu::sanitize_per_page`, `sanitize_log_retention_days`, the existing checkbox sanitizer). TASK-4's "Do NOT change sanitizer methods" constraint preserves this. |
| Output escaping at point of render | Yes — new admin notice + activation `wp_die` message | ✅ Required by plan | Plan/contract C-005 and C-006 specify `esc_html__( ..., 'acrossai-abilities-manager' )`. Implementation MUST verify this is wrapped at render time, NOT pre-escaped at definition time, to comply with the project's escape-at-render rule. |
| Nonce verification on forms/AJAX | No new forms or AJAX | ✅ N/A | The host package owns the Settings form's nonce handling (standard WP Settings API + `options.php` flow). |
| Capability check on admin actions | Yes — admin notice callback | ✅ Required by plan | Plan/contract C-005 explicitly requires `current_user_can( 'manage_options' )` gate per `DEC-FAIL-OPEN-NOTICE`. Implementation must NOT skip this. |
| `$wpdb->prepare()` on DB queries | No DB queries introduced | ✅ N/A | None. |
| File upload validation | No file uploads | ✅ N/A | None. |
| Deprecated functions | None used | ✅ Pass | `add_action`, `add_submenu_page`, `register_setting`, `add_settings_section`, `add_settings_field`, `register_activation_hook`, `wp_die`, `esc_html__` — all current API. |

## Trust boundaries

- **Browser → WP admin**: existing WP cookie auth + capability checks. No new authenticated surface here.
- **WP → composer autoloader**: the boot path now treats absence of `vendor/autoload_packages.php` as a soft-fail-with-notice rather than a hard fatal. This **improves** the security posture: a hard fatal during admin requests can leave WP in inconsistent states (cron not running, plugin marked active but non-functional, REST API broken for other plugins). The soft-fail keeps WP functional while signalling the missing dependency.
- **Vendor packages → plugin**: the `class_exists()` guard around `\AcrossAI_Main_Menu\SettingsPage` mirrors the existing `AddonsPage` pattern. No new trust extended to the vendor.

## Data isolation and validation risks

- **Option preservation (no data migration)**: option NAMES are immutable across the upgrade. There is no risk of a renamed-option overwriting an existing user value because no rename occurs.
- **option_group change**: the WP Settings API uses `option_group` only to validate the form submission's `_wpnonce` / `option_page` hidden field against the registered group. Changing `option_group` from `'acrossai_abilities_settings'` to `'acrossai-settings'` invalidates any user form that was loaded under the old slug and submitted after the upgrade — the most they can experience is a one-shot "you do not have permission" rejection, after which a page reload restores normal flow. No data loss; no privilege escalation.
- **No new dynamic SQL**: `%i` rule from CONSTITUTION §II does not apply (no new SQL).

## Authorization assumptions

- The Abilities, Library, Logs, and Add-ons submenus all keep their existing capability arguments (the plan explicitly forbids changing them). Re-parenting under `acrossai` does NOT change who can access them; WP enforces the per-page capability before rendering regardless of parent.
- The host Settings page is owned by `acrossai-co/main-menu`. Trust here is delegated to the host package. **Recommendation**: when reviewing the host package version pin (`^0.0.2`), verify the host's submenu capability is at least `manage_options` (consistent with our settings).

## Async security context

- No new async work introduced (no Action Scheduler jobs, no cron, no background loops).
- The `plugins_loaded` priority-0 bootstrap is synchronous and idempotent (`class_exists` guard + standard ctor).

## Findings

- **C:0 H:0 M:0 L:0** — no critical, high, medium, or low security findings introduced by the change set.
- **Informational**: the host-package capability assumption (above) is the only externally-trusted gate; recommend recording it as a memory entry when capturing the new accepted deviation for entry-file bootstraps.

## OWASP coverage

- **A01 Broken Access Control**: admin notice gated on `manage_options`; activation guard runs in admin context; no new bypassable surface.
- **A03 Injection**: no new SQL, no eval, no shell. The `wp_die` and `admin_notices` message strings are static literals wrapped in `esc_html__`.
- **A09 Security Logging & Monitoring Failures**: the change set EXPLICITLY converts a silent fatal into a visible admin notice — a net improvement.

## Recommended verifications at `/speckit-implement`

1. Grep the implementation for `current_user_can` near the admin notice registration site — MUST find the guard.
2. Grep for `esc_html__` and `esc_html_e` near the notice body and the `wp_die` message — MUST find the escape.
3. Confirm text domain `'acrossai-abilities-manager'` in every introduced string.
4. Run Plugin Check on the production surface (per `PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT`).

## Note on this inline review

This inline review is a **first pass**. The user may run `/speckit-security-review-plan` (the dedicated skill) afterwards for an independent second-opinion review with broader pattern coverage. Both reviews should reach the same conclusion if there is nothing novel here; divergence between them would itself be a useful signal worth investigating.

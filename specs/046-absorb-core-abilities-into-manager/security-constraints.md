# Security Constraints — Feature 046

**Feature**: 046 Absorb Core Abilities Companion Into Manager
**Scope**: plan-level security review, per `/speckit-security-review-plan`
**Date**: 2026-07-13
**Reviewer**: architecture-guard orchestrator (inline)

## Executive Verdict

**Risk level**: INFORMATIONAL. No net new security surface introduced. The
migration relocates existing runtime abilities that already ship in a sibling
plugin — trust boundaries do not shift; capabilities and sanitizers migrate
verbatim. The only real net-new code paths are the activation-time option
migration and the Bootstrap's iteration over 218 classes; both are
capability-gated by WordPress's own activation lifecycle and Abilities API,
respectively.

## Findings

| Sev | Area | Finding |
|---|---|---|
| INFO | Data ingress — extra MIME types field | `Core_Settings_Menu` continues to sanitize the textarea input through the same rules the companion used. Preserved verbatim during the move (verify during PR review, no design change needed). |
| INFO | Data at rest — option-key migration | Activation reads `acrossai_core_abilities_extra_mimes` verbatim into `acrossai_abilities_manager_extra_mimes`. No new user-controlled input path — the value was already present in `wp_options` and was already user-editable through the (soon-removed) companion Core tab. |
| INFO | Data at rest — uninstall opt-in OR | Activation ORs the companion's boolean uninstall opt-in into the manager's existing opt-in. Direction is monotonic (false → true only); never demotes a true, so an admin's prior "delete on uninstall" intent cannot be silently disabled. |
| INFO | Uninstall gate | Migrated `acrossai_abilities_manager_extra_mimes` deletion sits inside the existing `$acrossai_delete_data` gate — matches `PATTERN-UNINSTALL-DATA-GATE`, protects against `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`. |
| INFO | Category / ability slug rebrand as authorization surface | Any downstream access-control rule that used the legacy `acrossai-core-abilities-<domain>` category slug as a policy predicate will stop matching post-migration. Not a vulnerability in this plugin — but plugins that fail-open on unmatched slugs could silently grant access. Downstream integrators MUST update their slug references (see spec US2, FR-001). |
| INFO | Bootstrap's `wp_abilities_api_init` hook | The Bootstrap iterates 218 classes to call `wp_register_ability_category()` and `wp_register_ability()` on the WP-owned init hooks. WP's Abilities API itself owns permission_callback wiring on each ability; the Bootstrap adds no new permission surface. |
| INFO | File-manager and DB abilities in the absorbed set | Ability classes such as `FileManager\File_Delete`, `Database\Db_Delete`, `Plugins\Plugin_Deactivate` gated by `manage_options` (or stricter) inside the WP Abilities API. The migration preserves those gates verbatim — no change to authorization behavior. |
| INFO | Forbidden functions inside absorbed code | The plan-phase quality-gate audit includes `grep -RnE '\beval\(|extract\(|shell_exec\(|passthru\(|exec\(|popen\(|proc_open\(|system\(' includes/Abilities` per Constitution §II. Companion inventory (2026-07-13) shows no such calls in production; grep must confirm zero hits before merge. |

## Boundaries That Do NOT Change

- Same 201 abilities, same permission surface, same payload shapes.
- No new REST namespaces registered by this feature.
- No new AJAX endpoints.
- No new custom database tables.
- No new file-upload paths (the extra-MIME-types option was already used to
  gate WordPress-native uploads — the migration doesn't broaden that surface).

## Boundaries That DO Change

- **Category / ability slug namespace**: legacy `acrossai-core-abilities-<domain>`
  slugs are no longer registered; only `acrossai-abilities-manager-<domain>` are.
  Downstream authorization or feature-gating that keyed off legacy slugs stops
  matching. **Intentional breaking change**, documented in spec Q2-revised and
  spec US2.
- **Option key names**: `acrossai_core_abilities_extra_mimes` no longer
  exists after activation. Downstream code reading that option directly (rare
  — this is an admin-only key) will see `null`. Migration copies the value to
  the new key; downstream should read the new key.
- **Text-domain identity**: strings inside the absorbed code load from
  `acrossai-abilities-manager` catalog. Custom `.mo` files that targeted the
  legacy text domain no longer resolve — regenerate the `.pot` in the
  follow-up spec.

## Threat Model Deltas

None. This is a code-relocation feature. No new external inputs, no new
network paths, no new privilege boundary transitions.

## Compliance With Existing Security Constraints

- **SEC-01** (`sanitize_ability_slug()` at REST endpoints): unchanged — no
  REST endpoints owned by this feature.
- **SEC-02** (`before_save` hook fires on sanitized `$fields`): unchanged —
  no REST save paths in the feature.
- **SEC-03** (`AcrossAI_Abilities_Table::$global = false`, multisite
  isolation): unchanged — no new tables.
- **SEC-04** (strict type comparison for access control): unchanged — no
  new AC gates. The activation migration uses strict `!== null` for the
  MIME-types key and strict `(bool)` casts for the opt-in.

## Recommendations for Task Phase

1. Add a task to run the 5-pattern forbidden-function grep (see plan §7) and
   fail merge if any hit lands in `includes/Abilities/`.
2. Add a task to confirm the option-key migration is idempotent: run
   activator twice against a seeded DB, assert only-one-copy semantics.
3. Add a task to verify the uninstall gate protects the migrated MIME-types
   key — run `uninstall.php` against a live DB with the opt-in false and
   confirm the option persists.
4. **Skip**: permission_callback compliance audit for the 201 absorbed
   abilities. Per project memory feedback, this audit is not required as
   part of this migration; abilities carry over WP-Abilities-API-managed
   permission gates verbatim.

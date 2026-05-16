# Memory Synthesis

feature: 001-sitewide-ability-management
status: complete
hard_conflicts: 0
soft_conflicts: 1
assumptions_to_confirm: 0

<!-- Keep metadata keys in this order. Keep every section below, even when empty. -->
<!-- Use stable item IDs like [C1], [D1], [B1], [A1], [Q1], [W1], [V1]. -->
<!-- Use "- [none]" for empty sections, and keep conflict counts aligned with listed conflicts. -->
<!-- Keep this file within retrieval.max_synthesis_words, default 900 words. -->

## Current Scope
Feature 001 is **fully implemented** (T001–T048 and RT-01 complete; RT-03 pending — mechanical class-rename). Five user stories: DataViews table (US1), toggle (US2), slide-in DataForms panel (US3), Reset Override (US4), Bulk actions (US5). REST controller decomposed into orchestrator + 4 sub-controllers in `includes/Modules/Sitewide/Rest/`. Synthesis purpose: preserve implementation learnings for downstream features (spec-002, spec-003 access control UI, spec-004 override processor).

## Relevant Decisions
- [D1] BerlinDB upsert: `add_item()` returns integer ID (check `!== false && > 0`); `update_item()` returns object (check `!== false`). (Reason: all future BerlinDB upsert calls, Status: Active, Source: plan.md Decision 9)
- [D2] PHP bool→int before BerlinDB: cast `true → 1`, `false → 0`; leave `null` unchanged. Prevents `$wpdb` format `%s` → empty string `''` rejected by MySQL strict mode on tinyint columns. (Reason: every nullable tinyint BerlinDB column, Status: Active, Source: plan.md Decision 9b)
- [D3] `has_param()` for partial-field saves: collect only explicitly sent fields to avoid silently overwriting other-tab DB values with null. (Reason: any multi-tab or partial-save REST endpoint, Status: Active, Source: plan.md Decision 10)
- [D4] DEC-PERM-CB: AC rule-gated `permission_callback` injection runs independently of the override row. Fail-open when `get_manager()` returns null. Do not guard inside `isset($_overrides_cache)`. (Reason: spec-003/004 depend on this contract, Status: Active, Source: DECISIONS.md 2026-05-16)
- [D5] DEC-FAIL-OPEN-NOTICE: Any fail-open optional-library behavior MUST pair with an `admin_notices` Loader hook, gated by `current_user_can('manage_options')` + library availability check. Notice must name: absent library, inactive enforcement, required action. (Reason: governs all future optional-library integrations, Status: Active, Source: DECISIONS.md 2026-05-17)

## Active Architecture Constraints
- [A1] `includes/Main.php` is the ONLY file that calls `$this->loader->add_action/add_filter`. Variable-first pattern — never inline `::instance()` as the hook object. (Source: CONSTITUTION.md §I Boot Flow Rule v1.4.1)
- [A2] Admin asset enqueue (`wp_enqueue_script/style`) ONLY in `Admin\Main::enqueue_scripts()` / `enqueue_styles()` — never in Partials or module classes. (Source: CONSTITUTION.md §I + plan.md Decision 6)
- [A3] REST controller split: >400 lines or >1 user story → orchestrator + per-domain sub-controllers in `includes/Modules/<Feature>/Rest/`. Orchestrator owns `REST_NAMESPACE`, `register_routes()`, `check_permission()` only. `Main.php` wires only orchestrator. (Source: CONSTITUTION.md §I REST Controller Pattern v1.4.1)
- [A4] Filter/sort/paginate in `AcrossAI_Ability_Registry_Query::query()` only — never inlined in REST controllers. All future list endpoints reuse this. (Source: CONSTITUTION.md §I RF-03, plan.md T006b)
- [A5] `DataForm` is from `@wordpress/dataviews` — there is **no** separate `@wordpress/dataforms` package. Constitution §III (DataForms requirement) = `DataForm` from `@wordpress/dataviews`. Load as external, not bundled. (Reason: plan.md line 197 carried wrong package name; corrects recurring documentation error, Source: plan.md lines 51/56, CONSTITUTION.md §III v1.4.1)

## Accepted Deviations
- [DEV1] `McpVisibilityControl.jsx` exempt from Constitution §III DataForms requirement: 4-state compound control with 3 interdependent fields cannot map to independent DataForm fields. (Status: Accepted-Deviation, Source: plan.md RT-01 note)
- [DEV2] ARCH-ADV-001: `AcrossAI_Ability_Override_Processor::boot()` wires `wp_register_ability_args` / `wp_abilities_api_init` via direct `add_filter`/`add_action` (bypasses Loader). Accepted — Loader cannot express PATH-A/B conditional registration. Do NOT move to `Main.php`; fires on Manager REST requests and corrupts registry layer. (Status: Accepted-Deviation, Source: DECISIONS.md 2026-05-16)

## Relevant Security Constraints
- [S1] Nonce verified on all 7 REST endpoints inside `check_permission()`. (Source: plan.md §IV)
- [S2] `AcrossAI_Sanitizer::sanitize_ability_slug()` on every slug URL parameter — SEC-01. (Source: plan.md T025/T028/T034/T037)
- [S3] `after_save` hook fires with sanitized `$fields` only — SEC-02. Partial-save endpoints must pass full 9-field array (re-fetched via `get_override_by_slug()` post-save), not local `$fields` subset. (Source: plan.md T025; commit 5c6fce6)

## Related Historical Lessons
- [B1] BUG-BERLINDB-UNLIMITED: `number => -1` → `absint` → LIMIT 1. Use `number => 0` for unlimited. (Reason: spec-004 get_all_overrides(), Source: BUGS.md)
- [B4] BUG-PARTIAL-HOOK-FIELDS: Partial-save endpoints must re-fetch the full saved row and pass complete 9-field array to `after_save` — not local `$fields` subset. (Reason: any new partial-save endpoint; spec-004 hooks, Source: BUGS.md 2026-05-17)
- [B5] BUG-UNIMPLEMENTED-HOOK: Hooks declared in plan §V may be silently absent. Grep every declared hook name after implementation; confirm matching `do_action()`/`apply_filters()` call exists; cast filter return to expected type. (Reason: all future features with extensibility hooks, Source: BUGS.md 2026-05-17)

## Feature-to-Memory Conflicts
- [SC1] plan.md line 197 still reads `@wordpress/dataforms`; correct package is `@wordpress/dataviews`. Soft conflict — documentation only; implementation is correct. [A5] reflects the corrected name.

## Assumptions Requiring Confirmation
- [none]

## Implementation Watchpoints
- [W1] Access Control UI (spec-003): wire via `wpb-access-control` library using `acrossai_abilities_access_control_providers` filter — not a new plugin-owned table or routes.
- [W2] RT-03 pending: `Activator`, `Deactivator`, `I18n`, `Loader` need `AcrossAI_` prefix. Schedule as standalone cleanup before spec-002 completes.

## Retrieval Notes
- Index entries: DEC-PERM-CB, ARCH-ADV-001, DEC-FAIL-OPEN-NOTICE, BUG-BERLINDB-UNLIMITED, BUG-PARTIAL-HOOK-FIELDS, BUG-UNIMPLEMENTED-HOOK, all 5 AC-* constraints (8 of 20 budget)
- Source sections: DECISIONS.md (3 active), BUGS.md (4 active), INDEX.md full
- Budget: 5 decisions, 5 constraints, 2 deviations, 3 security, 3 bugs — within all limits

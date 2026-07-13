# Architecture Violation Detection — Feature 046

**Feature**: 046 Absorb Core Abilities Companion Into Manager
**Inputs**: plan.md, .specify/memory/CONSTITUTION.md v1.4.8, memory-synthesis.md, security-constraints.md
**Mode**: inline (governed-plan orchestrator; `/speckit-architecture-guard-violation-detection` not invoked as a slash command)
**Date**: 2026-07-13

## Scanned Against

- Constitution §I–§VII (v1.4.8)
- Architecture & UI Standards: Directory Layout, Namespace Rule, Admin Partials Rule, Boot Flow Rule, REST Controller Pattern, Module Contract, UI Contract, Database, Integration Resilience
- Memory-synthesis constraints (5 decisions, 4 architecture constraints, 2 accepted deviations, 2 security constraints, 3 historical lessons)

## Detected Drift / Violations

| ID | Category | Severity | Status in plan | Notes |
|---|---|---|---|---|
| V-01 | Boot Flow Rule (assumed constructor `add_action` in 218 classes) | HARD (initial) → **RETRACTED** | **False positive** | 2026-07-13 spot-check confirmed the assumption was wrong. Category_Registrar classes ship with empty constructors + a synchronous `register()` method. Ability classes extend `Ability_Definition` and carry no constructor — instantiation alone triggers `add_filter('acrossai_abilities_api_init', 'push_definition')` inherited from the base class. All hook registration was already channeled through the companion Main.php's Loader; that responsibility transfers to the new `AcrossAI_Core_Abilities_Bootstrap` wired from `Main.php::define_public_hooks()`. No refactor of the 218 classes required, no new accepted deviation created. See plan.md §Phase 1 §2 for the actual Bootstrap shape. |
| V-02 | §I Directory Layout — `includes/Abilities/` at includes/-tier, not under `Modules/` | SOFT (documented deviation) | **Accepted / justified** | Plan §Complexity Tracking documents rationale: absorbed code is 17 capability domains, not one module; would collide with `Modules/Abilities/` name. Follow-up: Constitution PATCH bump to enumerate the new tier. Not a blocker for this feature. |
| V-03 | §VI Reusability & DRY — potential overlap between absorbed `Utilities/` and manager `Utilities/` | SOFT | **Deferred + tracked** | Plan §Complexity Tracking documents that overlap resolution is a follow-up spec, not blocking. No known 1:1 overlap in the source inventory. |
| V-04 | DEC-SINGLETON-PSR2-PROPERTY | SOFT | **Resolved in rewrite matrix** | Bulk rename `$_instance` → `$instance` across every moved singleton, per rewrite matrix rule #8. |
| V-05 | AC-FILE-HEADER-PATTERN | SOFT | **Resolved in rewrite matrix** | `@package AcrossAI_Abilities_Manager`, `@subpackage <path>`, `@since 0.1.0` rewritten per matrix rule #9. |
| V-06 | DEC-UTILITY-STATIC-ONLY (companion Utilities may carry state) | SOFT | **Audit task added** | Plan §Complexity Tracking → §VI audit; task list will include a per-file static-ness check for the absorbed `Utilities/`. |
| V-07 | §II Plugin Check — forbidden functions inside absorbed code | HARD if any hits | **Gated** | Plan §7 quality-gate audits `eval / extract / shell_exec / passthru / exec / popen / proc_open / system` across `includes/Abilities/`. Source inventory (2026-07-13) shows no such calls; grep must confirm before merge. |
| V-08 | §III DataForm/DataViews NON-NEGOTIABLE for admin forms | N/A | **Accepted deviation covers this** | Extra-MIME-types field renders via WP Settings API — covered by `DEC-SETTINGS-API-DEVIATION`. No new custom form UI introduced. |
| V-09 | §IV Security First — sanitize/escape/nonce/capability | N/A | **Preserved verbatim** | `Core_Settings_Menu`'s sanitize hooks migrate as-is; Settings API host handles nonce + `manage_options`. No net-new security surface (see security-constraints.md). |
| V-10 | PATTERN-UNINSTALL-DATA-GATE + BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE | N/A | **Compliant** | `uninstall.php` addition sits inside the existing `$acrossai_delete_data` gate. |
| V-11 | PATTERN-GREP-AUDIT-VS-MANDATED-STRINGS | N/A | **Compliant** | Plan §7 audit patterns explicitly scope to `includes/Abilities/ admin/Partials/Core_Settings_Menu.php` and exclude `specs/` (where Clarifications MUST name the legacy slug). |
| V-12 | BUG-PYTHON-STRREPLACE-PARTIAL-WRITE | N/A | **Compliant** | Plan calls out per-transform synchronous writes (sed/perl per-file). |
| V-13 | Category slug rebrand as intentional breaking change | INFO | **Explicit in spec + plan + security** | Documented in spec FR-001, US2, and security-constraints.md. Downstream integrators are notified via spec; no in-plugin compatibility shim. |

## Security-Architecture Conflicts

None. security-constraints.md finds no net new security surface. The one
behavior change with downstream implications (category slug rebrand) is
architecturally documented and security-tagged.

## Drift Findings Not Present In Plan

None. Every constraint from memory-synthesis.md is either addressed in the
plan (violation resolved) or explicitly deferred with justification.

## Recommended Actions

1. **Proceed** — no HARD violations remain unresolved. The plan is
   architecturally compliant.
2. Run `/speckit-tasks` to produce `tasks.md` — plan's Phase 2 preview seeds
   the 9 task themes.
3. After tasks are approved, run `/speckit-architecture-guard-refactor-generator`
   to convert V-02 and V-03 into follow-up refactor tasks (Constitution
   amendment; Utilities DRY audit).
4. After implementation, run `/speckit-architecture-guard-architecture-review`
   for the post-implementation gate.

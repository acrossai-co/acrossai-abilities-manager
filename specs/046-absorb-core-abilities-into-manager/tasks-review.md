# Governed-Tasks Review — Feature 046

**Reviewer**: `/speckit-architecture-guard-governed-tasks` (inline)
**Date**: 2026-07-13
**Inputs**: tasks.md, plan.md, spec.md, memory-synthesis.md, violation-detection.md, security-review-plan.md, .specify/memory/CONSTITUTION.md v1.4.8

## Verdict

**PASS — tasks.md is architecturally sound and security-aligned.** All 48
tasks trace to a plan concern, a Constitution principle, or a documented
memory rule. No orphan tasks, no missing prerequisites, no security
verification gaps.

## Coverage Matrix

| Plan concern | Task(s) | Notes |
|---|---|---|
| CHANGE-1 raw copy of 17 category folders | T004 | Verifies 218-file count |
| CHANGE-2 raw copy of Utilities/ | T005 | Preserves 5 sub-folders |
| CHANGE-3 admin partial move | T006 | Single file |
| CHANGE-4a Category_Registrar refactor | T015 | Constructor emptied, `register()` retained |
| CHANGE-4b ability class refactor | T016 | 201 classes, method renamed to `register()` |
| CHANGE-4c new Bootstrap orchestrator | T017 | PSR2 `$instance` explicit |
| CHANGE-5 bulk rewrite (all 9 sub-rules) | T003 (tool) + T007 (apply) | Ordered per plan §Phase 1 §1 |
| CHANGE-6 wire Bootstrap + Core_Settings_Menu | T018, T026 | Variable-first AC-HOOKS-MAIN |
| CHANGE-7 activation option migration | T027 | OR-monotonic + idempotent |
| CHANGE-8 uninstall gate | T028 | Inside existing `$acrossai_delete_data` |
| CHANGE-9 composer dump-autoload | T011 | With missing-file verification |
| Grep merge gates (4 audits) | T009, T039 | Belt-and-suspenders |
| Forbidden-function gate | T010, T039 | Constitution §II |
| PHPStan / PHPCS / PHPUnit / npm-validate | T019, T020, T029, T035, T036, T037, T038 | Chunked per category |
| Manual verification (quickstart) | T021, T030, T031 | US1 + US3 |

## Constitution / Memory Cross-Check

| Rule / Memory ID | Task(s) enforcing it | Status |
|---|---|---|
| §I Modular Architecture (Path-A resolves Boot Flow conflict) | T015, T016, T017, T018 | ✔ Enforced |
| §II PHPStan L8 / PHPCS strict | T019, T020, T029, T035, T036 | ✔ Enforced |
| §II Forbidden functions | T010, T039 | ✔ Enforced |
| §II SQL `%i` | N/A (no new SQL) | ✔ Not applicable |
| §III DataForm/DataViews | N/A (DEC-SETTINGS-API-DEVIATION covers Settings API use) | ✔ Deviation-accepted |
| §IV Security (sanitize/escape/nonce/cap) | T025 (Core_Settings_Menu refactor preserves sanitize hooks); permission gates preserved verbatim (skipped per memory feedback) | ✔ Enforced / scoped skip |
| §V Extensibility | N/A (no new integration points) | ✔ Not applicable |
| §VI Reusability & DRY | T045 (follow-up spec stub for Utilities DRY audit) | ✔ Deferred + tracked |
| §VII Definition of Done | T035, T036, T037, T038 + all PHPUnit test tasks | ✔ Enforced |
| AC-HOOKS-MAIN | T015, T016, T018, T026 (Path A + variable-first wiring) | ✔ Enforced |
| AC-FILE-HEADER-PATTERN | T007 rewrite matrix rule 5i | ✔ Enforced |
| Admin Partials Rule | T006, T025 (Core_Settings_Menu lives in admin/Partials/, correct namespace) | ✔ Enforced |
| Module Contract (singleton `instance()`) | T017 (Bootstrap), T025 (Core_Settings_Menu) | ✔ Enforced |
| DEC-NAMESPACE-CONVENTION | T007 rewrite matrix rule 5a | ✔ Enforced |
| DEC-SINGLETON-PSR2-PROPERTY | T007 rule 5h, T017 explicit | ✔ Enforced |
| DEC-SETTINGS-API-DEVIATION | T025 accepts, T024 (test) validates | ✔ Enforced |
| PATTERN-UNINSTALL-DATA-GATE | T028, T023 (test) | ✔ Enforced |
| BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE (avoidance) | T028, T023 | ✔ Enforced |
| PATTERN-GREP-AUDIT-VS-MANDATED-STRINGS | T009 (scope excludes specs/) | ✔ Enforced |
| BUG-PYTHON-STRREPLACE-PARTIAL-WRITE (avoidance) | T003 (sed/perl per-file) | ✔ Enforced |
| SEC-046-01 (slug rebrand downstream) | T033, T034 | ✔ Mitigated |
| SEC-046-02 (activation idempotency + OR-monotonic) | T022 | ✔ Verified |
| SEC-046-03 (forbidden functions) | T010, T039 | ✔ Gated |
| SEC-046-04 (uninstall gate) | T023 | ✔ Verified |
| SEC-046-05 (permission-gate sample) | T041 | ✔ Scoped |

## Refactor Generator Consideration

Two documented deviations (V-02, V-03) already have follow-up spec stubs
(T044, T045). No new refactor tasks required — `/speckit-architecture-guard-refactor-generator`
is not needed for this feature.

## Task Independence Check

- **US1 → US2 dependency**: US2 verification depends on US1's slug rebrand
  landing (spec's US2 is now a *verification of* the rebrand US1 delivers).
  This is explicit in `tasks.md` §Dependencies. Not a violation — the US1
  MVP is still independently shippable and testable; US2 tests operate over
  the US1 output.
- **US1 vs US3 parallelism**: US1 (Path A refactor + Bootstrap) and US3
  (Core settings + activation + uninstall) touch disjoint code paths inside
  `Main.php` (`define_public_hooks` vs `define_admin_hooks`) and disjoint
  files elsewhere. Confirmed parallelizable.

## No Findings

No new architectural drift, no new security concerns, no missing
verification. Tasks.md is ready to execute.

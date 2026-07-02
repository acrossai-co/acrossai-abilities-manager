# Implementation Plan: Remove the Logger module

**Branch**: `040-remove-logs-module` | **Date**: 2026-07-01 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/040-remove-logs-module/spec.md`

**Note**: This plan was generated inline by `/speckit-architecture-guard-governed-plan`. The orchestrator's documented fallback path was followed because the user prefers to invoke each `/speckit-*` command manually (no auto-chaining of sub-skills). The implementation breakdown (file paths, line numbers, exact deletion order) is authored by the user in `docs/planning/040-remove-logs-module.md` — TASK-1 through TASK-8. This plan binds that breakdown to Constitution principles, applies the soft-conflict resolutions surfaced by `memory-synthesis.md`, and adds one explicit task that the planning doc left implicit: the Constitution §I module-enumeration amendment.

## Summary

Fully decommission the Logger module from `acrossai-abilities-manager` without in-plugin replacement. Delete `includes/Modules/Logger/**` (8 PHP files), the two Logger utility classes in `includes/Utilities/`, `admin/Partials/LogsMenu.php`, the three logger JS/SCSS files, 5 PHPUnit test files, and the two `webpack.config.js` `js/logger`/`css/logger` entries. Unwire the Logger from `includes/Main.php` (Logs menu registration + 6 Logger boot-block hook registrations + REST controller wiring + Table setup — 7 total add_action/add_filter calls split across TASK-1 and TASK-3), from `includes/AcrossAI_Activator.php` (one table-create line), and remove the `log_retention_days` field from `admin/Partials/SettingsMenu.php`. Add table drop + Action Scheduler cleanup to `uninstall.php` inside the existing opt-in gate. Update `README.txt` Unreleased changelog. Mark four Logger-consumer decisions as Superseded (Feature 040) and annotate three Logger-referenced patterns with forward-pointer notes. Amend Constitution §I module enumeration + Directory Layout to drop `Logger/` (PATCH bump v1.4.7 → v1.4.8).

**Technical approach**: Ordered 8-task decommission per `PATTERN-MODULE-DECOMMISSION`. Deletion order runs consumer-first (Main.php + admin/Main.php + Activator + SettingsMenu.php in TASK-1 through TASK-3, TASK-5) then module-inward (TASK-4 deletes the Logger dir + utilities; TASK-6 deletes JS/SCSS + webpack entries; TASK-7 deletes tests + xml.dist entries). Data strategy mirrors Feature 039: legacy `{prefix}acrossai_ability_logs` table + retention option remain orphaned on existing installs, dropped only on opt-in uninstall. No composer changes. No new deviation introduced; one Constitution wording amendment (PATCH scope).

## Technical Context

**Language/Version**: PHP 8.1+ (CONSTITUTION §II), no JavaScript source additions (all JS changes are deletions).
**Primary Dependencies**: Unchanged. No composer add/remove. `wpboilerplate/wpb-access-control ^2.0.0`, `acrossai-co/main-menu ^0.0.8`, `automattic/jetpack-autoloader ^5.0`, `berlindb/core ^3.0.0` all continue.
**Storage**: DELETION — `{prefix}acrossai_ability_logs` no longer created on activation; existing table orphaned on upgrade, dropped on opt-in uninstall. `acrossai_ability_logs_db_version` + `acrossai_abilities_log_retention_days` options: same treatment. **NO new tables. NO migrations.** Two preserved tables (`{prefix}acrossai_abilities`, `{prefix}abilities_access_control`) and their schema-version markers are unaffected.
**Testing**: Post-Feature 040 test suite drops from 103 tests to ~85 (5 Logger test files × ~15-20 tests each). `phpunit.xml.dist` `<file>` entries pruned atomically with test-file deletion (per `BUG-PHPUNIT-AUTODISCOVERY-PREFIX`). No new tests added — removal features add no new business logic. PHPStan L8, PHPCS 56/x (file count drops by ~10), `npm run validate-packages`, `npm run build` all still gate.
**Target Platform**: WordPress 6.9+ admin, PHP 8.1+, multisite-compatible (unchanged).
**Project Type**: WordPress plugin — single project.
**Performance Goals**: Zero regression. Boot path shortens by ~10 lines (7 hook registrations + REST setup + Table setup removed). Activation shortens by one `maybe_upgrade()` call. Admin page load unchanged for non-Logger pages; Logs page 404s.
**Constraints**: Do not migrate log data. Do not touch `vendor/`. Do not add a companion-plugin dependency check. Do not re-register the upstream ability hooks the Logger consumed under different names. Do not delete Logger memory entries silently (mark Superseded per `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`). Every task must leave PHPStan L8 + PHPCS individually green.
**Scale/Scope**: ~20 files deleted, ~7 files edited, 1 composer manifest unchanged, 1 Constitution amendment. Approximately 30 code lines added (uninstall additions + Constitution SYNC IMPACT REPORT), ~2000+ lines deleted (Logger module + utilities + tests + JS + SCSS + build outputs).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked at end of Phase 1.*

| Principle (CONSTITUTION.md v1.4.7) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Module enumeration (six areas) | **Yes — REQUIRES AMENDMENT** | ⚠️ **PATCH bump task added (TASK-8)** | Current §I explicitly names "Ability Execution Logging" as one of six active feature areas; Directory Layout enumerates `Logger/`. Feature 040 removes both. PATCH-level amendment per §Governance ("clarifications, wording fixes, or non-semantic refinements") — no principle added/removed. See Complexity Tracking below. |
| §I Boot Flow Rule — Main.php is the single source of hook registration | Yes | ✅ Pass | TASK-1 removes the Logs menu registration + REST controller wiring; TASK-3 removes the 6 Logger boot-block hook registrations (mcp_adapter_pre_tool_call P5, wp_before_execute_ability P10, wp_after_execute_ability P10, wp_register_ability_args P100001, acrossai_ability_logger_cleanup P10, plugins_loaded schedule_cleanup P20). Following this codebase's convention, REST wiring and boot-block registrations share the same method — verify the actual method (`define_admin_hooks` vs `define_public_hooks`) via `git show HEAD~N:includes/Main.php` before merge. No new hooks added. `AC-HOOKS-MAIN` invariant preserved (Main.php remains the only file calling `$this->loader->add_action/add_filter`). |
| §I Admin Partials Rule (admin/Partials/ for menu/render/enqueue) | Yes | ✅ Pass | TASK-1 deletes `admin/Partials/LogsMenu.php` (menu class) and edits `admin/Main.php` (asset enqueue helper `is_logs_page`). TASK-2 edits `admin/Partials/SettingsMenu.php` (removes one settings field). All Admin partial edits stay inside `admin/Partials/`; no admin logic leaks into `includes/`. |
| §I REST Controller Pattern | No new REST controllers | ✅ Pass | TASK-1 removes the Logger REST namespace registration; no new REST controllers introduced. Deleted controllers (`AcrossAI_Logger_Controller`, `AcrossAI_Logger_Logs_Controller`) followed the orchestrator + sub-controller pattern; both go together in TASK-4. |
| §I `permission_callback` return type | No new REST endpoints | ✅ Pass | N/A — removal-only. |
| §I Module Contract (singleton + private ctor) | Existing modules unchanged | ✅ Pass | Only Logger's singletons are deleted. Abilities/Library/AbilityAPI singletons untouched. |
| §II WordPress Standards — PHPStan L8 / PHPCS / Plugin Check | Yes | 🔲 Verify per-task | Dangling `use` statements + stale `class_exists()` references (if any) would break PHPStan L8 → hard blocker. TASK-by-TASK gating enforced. |
| §II `acrossai_` prefix on functions/hooks/classes | Yes | ✅ Pass | No new identifiers introduced. |
| §II Multisite compatible | Yes | ✅ Pass | Legacy Logger table used `$wpdb->prefix` (per-site); orphaning it does not affect network-wide state. Uninstall AS-cleanup call is safe when AS is absent. |
| §III UI Contract (`DataForm`/`DataViews`) | Pre-existing deviation | ✅ Pass | Settings API deviation (`DEC-SETTINGS-API-DEVIATION`) continues; TASK-2 removes one field, doesn't change the pattern. |
| §IV Security First (sanitize/escape/nonce/capability) | Yes | ✅ Pass | Net effect is attack-surface reduction (REST namespace + admin submenu + one form field removed). `PATTERN-UNINSTALL-DATA-GATE` preserved in TASK-5. No new nonces, no new capability checks needed. See `security-constraints.md`. |
| §V Extensibility Without Core Modification — graceful degradation | Yes | ✅ Pass | Existing Feature 038 boot-resilience guard (fail-open notice on missing vendor) is unaffected. FR-010 formalizes that the four upstream ability hooks remain available for any consumer (including a future companion plugin). SC-006 verifies missing-vendor path unchanged. |
| §VI DRY | Yes | ✅ Pass | Removals only. Two Logger utilities (`AcrossAI_Logger_Formatter`, `AcrossAI_Logger_Source_Detector`) are grep-verified to have zero cross-module consumers (TASK-4 `PATTERN-HELPER-DELETION-GREP-FIRST` gate) before deletion. |
| §VII Definition of Done — all gates per-task | Yes | 🔲 Enforced at `/speckit-implement` time | Per-task PHPStan L8 + PHPCS + `npm run validate-packages` gating. Final `composer test` reports reduced test count with zero failures. |
| **Code Quality & Workflow** — `npm run validate-packages` before commit | Yes | 🔲 Verify at implement time | Runs before every commit. |
| **Code Quality & Workflow** — never modify `.agents/tools/` | Yes | ✅ Pass | Untouched. |
| **Governance §Amendment Procedure** — SYNC IMPACT REPORT for version bumps | Yes | ⚠️ **TASK-8 responsibility** | PATCH bump (v1.4.7 → v1.4.8) requires updating the HTML comment SYNC IMPACT REPORT block at the top of CONSTITUTION.md, per `PATTERN-CONSTITUTION-SYNC-REPORT` (memory). |

**Constitution Gate**: **PASS** with one PATCH-level amendment mandated by the removal itself (TASK-8). No new accepted deviation introduced. Two pre-existing accepted deviations continue at unchanged scope (`DEC-EXTERNAL-PACKAGE-HOOK-CTOR` — AddonsPage call site; `DEC-SETTINGS-API-DEVIATION` — settings page). One existing decision (`ARCH-ADV-001`) receives an annotation update in TASK-8 (its "Logger boot() removed in Feature 017" note extended to note the full-module removal in Feature 040).

## Memory Synthesis Findings

Synthesized in [memory-synthesis.md](./memory-synthesis.md). Highlights applied to this plan:

- **PATTERN-MODULE-DECOMMISSION** — Feature 040 follows the canonical 8-step protocol. Feature 012's Sitewide decommission is the precedent for the "no-replacement" variant. Feature 040 is the second such instance.
- **PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION** — TASK-8 marks four Logger-consumer Decisions as Superseded (bodies intact) and annotates three Logger-referenced Patterns with forward-pointer notes. `ARCH-ADV-001`'s existing "Logger boot() removed in Feature 017" annotation is the shape precedent.
- **PATTERN-HELPER-DELETION-GREP-FIRST** — TASK-4's grep-before-delete is mandatory. If any consumer surfaces for the two Logger utilities outside the module dir, the utility is KEPT and Logger-boundary lessons are lifted into its docblock.
- **PATTERN-UNINSTALL-DATA-GATE** — TASK-5's table drop + option delete + AS unschedule all land INSIDE the existing `if ( $acrossai_delete_data )` block. `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE` is actively guarded against.
- **PATTERN-ASSET-DECOMMISSION-ORDER** — TASK-1 removes PHP enqueue first; TASK-6 then removes webpack entries + source; then `npm run build` regenerates; then stale `build/` outputs are cleaned.
- **BUG-INVENTORY-GREP-MISS** (Feature 034 anti-pattern) — actively countered by the Phase 0 grep protocol embedded in TASK-4 + the final full-repo grep as the merge blocker (per docs/planning/040 verification checklist).
- **BUG-PHPUNIT-AUTODISCOVERY-PREFIX** — TASK-7 removes `<file>` entries from `phpunit.xml.dist` atomically with test file deletion.
- **DEC-TABLE-SOFT-SINGLETON** — Feature 040 removes exactly one sanctioned `( new Table() )->maybe_upgrade();` call (Logger's), leaving the two sibling calls (`AcrossAI_Abilities_Table`, `RuleTable( 'abilities' )`) intact.
- **DEC-FAIL-OPEN-NOTICE** — SC-006 requires the Feature 038 vendor-missing fail-open path remains functional; TASK-8 memory review notes that no code path added by Feature 040 alters it.

**Soft conflict resolution (from `memory-synthesis.md`)**:
- **Constitution §I module-enumeration amendment** is captured as TASK-8's Constitution edit (see Complexity Tracking below). Not a deviation — a required amendment tied to a factual change in the codebase.
- **`ARCH-ADV-001` annotation extension** is captured as part of TASK-8's memory hygiene work.

## Project Structure

### Documentation (this feature)

```text
specs/040-remove-logs-module/
├── spec.md                                  # User stories (US1-US3), 12 FRs, 8 SCs, 5 edge cases
├── plan.md                                  # This file
├── memory-synthesis.md                      # Memory synthesis (previous turn)
├── security-constraints.md                  # Plan-time security notes (this turn)
├── checklists/
│   └── requirements.md                      # Spec quality checklist (all items checked)
└── tasks.md                                 # Phase 2 — generated by /speckit-tasks
```

Reference implementation breakdown lives at `docs/planning/040-remove-logs-module.md` — TASK-1 through TASK-8 with per-task manual verification checklists.

### Source Code (repository root)

**Files DELETED** (~20):

```text
includes/Modules/Logger/                                      # entire dir + 8 PHP files
├── AcrossAI_Ability_Logger.php
├── Database/
│   ├── AcrossAI_Ability_Logs_Table.php
│   ├── AcrossAI_Ability_Logs_Schema.php
│   ├── AcrossAI_Ability_Logs_Row.php
│   └── AcrossAI_Ability_Logs_Query.php
└── Rest/
    ├── AcrossAI_Logger_Controller.php
    └── AcrossAI_Logger_Logs_Controller.php

includes/Utilities/
├── AcrossAI_Logger_Formatter.php                             # after grep-first (PATTERN-HELPER-DELETION-GREP-FIRST)
└── AcrossAI_Logger_Source_Detector.php                       # after grep-first

admin/Partials/LogsMenu.php                                    # menu class

src/js/
├── index.js                                                  # logger bundle entrypoint
└── components/LogsTable.js                                    # React table

src/scss/logs-table.scss                                       # SCSS

tests/phpunit/Modules/Logger/                                  # entire dir + 4 test files
tests/phpunit/Utilities/AcrossAI_Logger_Source_Detector_Test.php

build/js/logger*                                               # stale outputs after webpack regen
build/css/logger*
```

**Files EDITED**:

```text
includes/Main.php                                              # TASK-1 + TASK-3 (delete blocks at lines ~300-303 and ~400-412 + Logger `use` imports)
admin/Main.php                                                 # TASK-1 (delete $logger_asset_file, is_logs_page, JS/CSS enqueue guards, action link)
admin/Partials/SettingsMenu.php                                # TASK-2 (delete log_retention_days field + methods)
includes/AcrossAI_Activator.php                                # TASK-5 (delete Logger use + maybe_upgrade line)
uninstall.php                                                  # TASK-5 (add table drop + AS cleanup inside existing gate)
webpack.config.js                                              # TASK-6 (delete js/logger + css/logger entries)
phpunit.xml.dist                                               # TASK-7 (remove <file> entries for deleted tests)
README.txt                                                     # TASK-8 (Unreleased changelog + Upgrade Notice)
docs/memory/DECISIONS.md                                       # TASK-8 (mark 4 decisions Superseded)
docs/memory/ARCHITECTURE.md                                    # TASK-8 (annotate 3 patterns; update ARCH-ADV-001)
docs/memory/WORKLOG.md                                         # TASK-8 (Feature 040 milestone)
docs/memory/INDEX.md                                           # TASK-8 (update Superseded rows + add worklog + spec/plan/tasks/security rows)
.specify/memory/CONSTITUTION.md                                # TASK-8 (PATCH bump v1.4.7 → v1.4.8: drop Logger from §I + Directory Layout + SYNC IMPACT REPORT)
CLAUDE.md                                                      # optional TASK-8 housekeeping (SPECKIT plan ref)
composer.json                                                  # no changes (verify)
```

**Structure Decision**: Single-project WordPress plugin. Removal-only feature. No new directories, no new modules, no new tables, no new dependencies. Architecture-guard violation detection below confirms zero drift.

## Phase 0 — Research Findings

All unknowns are resolved by the pre-plan exploration (Explore agent inventory) and captured in `docs/planning/040-remove-logs-module.md`. No `NEEDS CLARIFICATION` markers exist. Summary of decisions:

| Question | Decision | Rationale |
|---|---|---|
| Deletion order | Consumers first (Main.php, admin/Main.php, Activator, SettingsMenu.php in TASK-1/2/3/5), then module (TASK-4), then front-end (TASK-6), then tests (TASK-7), then memory + Constitution (TASK-8) | `PATTERN-MODULE-DECOMMISSION` mandates consumer-first to avoid dangling `use` statements failing PHPStan mid-decommission. `PATTERN-ASSET-DECOMMISSION-ORDER` mandates PHP-enqueue side before webpack entry + source. |
| Two utility classes — delete or keep? | Delete (after grep-first) | Per inventory, both are Logger-scoped despite living in `includes/Utilities/`. `PATTERN-HELPER-DELETION-GREP-FIRST` requires an exhaustive grep BEFORE deletion; if any cross-module consumer surfaces, KEEP the utility. |
| Legacy table + option cleanup? | No auto-migration; drop on opt-in uninstall | Feature 039 precedent. `PATTERN-UNINSTALL-DATA-GATE`. Communicated via FR-011 release notes + manual SQL in README.txt. |
| Action Scheduler queue drain? | Defensive `as_unschedule_all_actions()` inside opt-in uninstall gate | AS queued items become no-ops when the callback is absent, so upgrade is safe without cleanup. Uninstall cleanup is the polite thing to do without being required. `function_exists()` guards against AS being inactive on the site. |
| Constitution amendment scope? | PATCH bump v1.4.7 → v1.4.8 | Per §Governance PATCH criterion ("clarifications, wording fixes, or non-semantic refinements") — removing an obsolete enumeration entry is a wording fix, not a principle change. SYNC IMPACT REPORT block updated per `PATTERN-CONSTITUTION-SYNC-REPORT`. |
| Memory hygiene shape | 4 Decisions marked Superseded (bodies intact); 3 Patterns annotated with forward-pointer notes; `ARCH-ADV-001` annotation extended | `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION` mandates never silent-delete. `ARCH-ADV-001`'s existing Feature 017 annotation is the shape precedent. |
| Companion plugin coordination? | None. FR-010 formalizes zero-callback contract. | Companion plugin (if it ever exists) consumes upstream WP hooks directly. Feature 040 makes no assumption about its presence or absence. Not a dependency, not a "reserved slot" — just absence of interference. |
| Bookmarks + external REST clients | 404 by design | Consistent with Feature 038's removed-Settings-URL treatment. Documented in FR-011 release notes. |

## Phase 1 — Design

### Data Model

Full removal. Post-Feature 040 data model consists of exactly two plugin-owned tables (unchanged from Feature 039):

- `{prefix}acrossai_abilities` — ability overrides, owned by the Abilities module
- `{prefix}abilities_access_control` — per-consumer access-control storage from Feature 039

**Legacy Logger artifacts (explicitly orphaned)**:
- `{prefix}acrossai_ability_logs` table
- `acrossai_ability_logs_db_version` option
- `acrossai_abilities_log_retention_days` option
- Action Scheduler queue entries hooked on `acrossai_ability_logger_cleanup`

Legacy artifacts NEITHER read NOR modified NOR deleted by Feature 040 on existing installs. Dropped only on opt-in uninstall (TASK-5).

### Contracts

Post-Feature 040 external contracts:

- **Preserved plugin surface**: AcrossAI parent menu → Abilities / Library / Add-ons / Settings submenus (four, down from five). Settings page: two fields (`_per_page`, `_uninstall_delete_data`). Access Control panel embedded in ability edit (unchanged from Feature 039). REST namespaces: `acrossai-abilities-manager/v1` (Abilities), `wpb-ac/v1/abilities/` (vendor Access Control).
- **Removed contracts**: REST namespace `acrossai-abilities-log/v1` no longer exists. Admin page `wp-admin/admin.php?page=acrossai-abilities-logs` 404s. Settings field `acrossai_abilities_log_retention_days` no longer registered.
- **Companion plugin extensibility contract (FR-010)**: `wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `mcp_adapter_pre_tool_call` are unclaimed by this plugin. Any consumer may register callbacks at any priority.

### Quickstart

Per-TASK verification recipes live in `docs/planning/040-remove-logs-module.md` under "Manual Verification Checklist" — full-repo grep + PHPStan/PHPCS + `npm run build` + `composer test` + WP-admin walkthroughs. Feature 040 does not need a separate quickstart.md.

### CLAUDE.md Update

Optional as part of TASK-8 housekeeping: update the SPECKIT plan reference to `specs/040-remove-logs-module/plan.md` (currently points at 039). Same as prior features.

## Complexity Tracking

> Filled because ONE Constitution amendment is required (not a deviation from a principle — a codebase-Constitution sync).

| Constitution work item | Why needed | Simpler alternative rejected because |
|---|---|---|
| §I module enumeration amendment ("six active feature areas" → "five") + Directory Layout `Logger/` removal (PATCH bump v1.4.7 → v1.4.8) | The Constitution's §I enumeration is a factual claim about the codebase (which modules exist, and how they map to feature areas). Removing the Logger module WITHOUT amending the Constitution leaves the Constitution asserting a module that no longer exists — a maintainability defect. Per §Governance, PATCH bumps cover "clarifications, wording fixes, or non-semantic refinements"; correcting an obsolete enumeration is precisely this class of change. Requires SYNC IMPACT REPORT block update per `PATTERN-CONSTITUTION-SYNC-REPORT`. | (a) Leave the Constitution stale — rejected because future plans reading §I would incorrectly assume the Logger exists, potentially blocking legitimate work (e.g., a new module named `Logger` for a different purpose) or generating "Constitution vs. codebase" architecture-guard false positives. (b) MINOR bump instead — rejected because no principle is added or removed; enumeration correction is PATCH scope. (c) Defer to a separate feature — rejected because the Constitution and codebase MUST stay in sync; deferring creates a real drift window. |

**Not a deviation — a mandatory amendment.** Every Constitution principle in §I–§VII continues to be honored. Two pre-existing accepted deviations (`DEC-EXTERNAL-PACKAGE-HOOK-CTOR`, `DEC-SETTINGS-API-DEVIATION`) continue with no scope change.

## Architecture Guard — Violation Detection (Inline)

Per orchestrator Step 5, run inline against `.specify/memory/CONSTITUTION.md v1.4.7` + this plan + `memory-synthesis.md` + `security-constraints.md`. Findings:

1. **Zero code-level Constitution violations.** Every §I–§VII principle passes. TASK-1's removal of the Logs menu registration + REST controller wiring and TASK-3's removal of the 6 Logger boot-block hook registrations preserve the Boot Flow Rule (Main.php remains the sole registrar). TASK-1's admin/Partials edits stay inside the correct namespace boundary. TASK-4's grep-first + delete respects DRY + `PATTERN-HELPER-DELETION-GREP-FIRST`. TASK-5's uninstall additions stay inside the opt-in gate (`PATTERN-UNINSTALL-DATA-GATE`).
2. **Zero Security-Architecture Conflicts.** Attack surface reduction (REST namespace + admin submenu + one form field removed). Missing-vendor fail-open path (`DEC-FAIL-OPEN-NOTICE`) unchanged. Permission_callback wrapper at P100001 was observability-only; removing it does not change auth semantics.
3. **Zero module-boundary crossings.** The Logger deletions do not introduce new imports elsewhere. The `use` statement additions expected in Activator (`AcrossAI_Abilities_Access_Control::TABLE_SLUG` from Feature 039) already exist; no NEW cross-module import is added by Feature 040.
4. **One mandated Constitution amendment** (TASK-8) — surfaced in synthesis as SOFT CONFLICT, resolved in Complexity Tracking as a mandatory PATCH bump. Not a drift; a factual correction.
5. **One `ARCH-ADV-001` annotation extension** (TASK-8) — existing "Logger boot() removed in Feature 017" annotation extended to note the full-module removal in Feature 040. The deviation itself stays Active (still applies to Override Processor).
6. **Verification-blocker grep** (per `BUG-INVENTORY-GREP-MISS` counter): a repository-wide `grep -rEn 'Logger|acrossai_ability_logs|acrossai-abilities-log|acrossai_ability_logger|LogsTable|LogsMenu|is_logs_page|logger_asset_file|log_retention' includes/ admin/ src/ tests/ acrossai-abilities-manager.php uninstall.php webpack.config.js` MUST return zero matches before merge. Non-zero = incomplete decommission. Captured in tasks.md as the final gate.

**Architecture Guard Verdict**: **PASS** — proceed to `/speckit-tasks`. One mandatory Constitution amendment (TASK-8) is captured in the task sequence; not a deviation.

# Memory Synthesis

## Current Scope

Feature 040 — full decommission of the Logger module without replacement. Delete `includes/Modules/Logger/**` (8 files), `includes/Utilities/AcrossAI_Logger_{Formatter,Source_Detector}.php`, `admin/Partials/LogsMenu.php`, `src/js/index.js` + `src/js/components/LogsTable.js` + `src/scss/logs-table.scss`, 5 PHPUnit test files, webpack `js/logger` + `css/logger` entries, and build artifacts. Unwire the Logger from `includes/Main.php` (7 hook registrations + REST controller + Table setup), from `includes/AcrossAI_Activator.php` (table create), and remove the `log_retention_days` field from `admin/Partials/SettingsMenu.php`. Add table drop + Action Scheduler cleanup to `uninstall.php` inside the existing opt-in gate. No cross-module consumers exist outside the boot wiring; no replacement is wired.

Affected modules: `Logger` (deleted), `Admin/Partials` (LogsMenu deleted, SettingsMenu edited), `Includes/Main` (7 lines block deleted), `Includes/Activator` (one table-create line deleted), `Utilities` (two Logger utilities deleted), `Tests` (5 files + phpunit.xml.dist entries), memory (4 decisions Superseded + 3 patterns annotated), Constitution §I (module count drops 6 → 5).

## Relevant Decisions

- **DEC-TABLE-SOFT-SINGLETON** (Reason: sanctioned by Feature 039 for direct `( new Table() )->maybe_upgrade();` in activator; Feature 040 removes exactly one such call — the Logger's — leaving the two sibling calls intact. No violation. Status: Active. Source: DECISIONS.md)
- **DEC-FAIL-OPEN-NOTICE** (Reason: SC-006 requires the missing-vendor fail-open path to remain functional after Feature 040. No new degraded-mode path added; existing Feature 038 guard at `Main::load_composer_dependencies()` still fires. Status: Active. Source: DECISIONS.md)
- **Four Logger-consumer decisions destined for Superseded status via FR-012**: `DEC-HOOK-PARAM-EXTRACTION`, `DEC-DURATION-CALC-TIMESTAMPS`, `DEC-VARIADIC-CALLBACK-WRAP`, `DEC-LOGGER-NAMESPACE-MIGRATION`. Per project convention, these are marked Superseded (bodies intact) rather than governing Feature 040's plan — they are the RESULT of the plan, not INPUT.

## Active Architecture Constraints

- **PATTERN-MODULE-DECOMMISSION** (Reason: canonical 8-step ordered protocol (rename DB → port CRUD → update consumers → delete REST → grep-then-delete). Feature 040 is the second no-replacement instance after Feature 012's Sitewide decommission; "port CRUD → update consumers" middle steps collapse because nothing consumes the Logger from outside boot wiring. Source: ARCHITECTURE.md)
- **PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION** (Reason: TASK-8 mandates that retired Decisions get Superseded status (body intact) and related Patterns that lose their consumer but stay conceptually valid get forward-pointer annotations. `ARCH-ADV-001`'s pre-existing "Logger boot() removed in Feature 017" annotation is the precedent shape. Source: ARCHITECTURE.md)
- **PATTERN-HELPER-DELETION-GREP-FIRST** (Reason: TASK-4 requires an exhaustive grep for `AcrossAI_Logger_Formatter|AcrossAI_Logger_Source_Detector` outside their module dir before deletion. If any cross-module consumer surfaces, the utility is KEPT and its docblock gains the Logger-boundary lessons. Source: ARCHITECTURE.md)
- **PATTERN-UNINSTALL-DATA-GATE** (Reason: TASK-5's table-drop + option-delete + Action Scheduler cleanup all land inside the existing `if ( $acrossai_delete_data )` block. Adding cleanup outside the gate would trigger `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`. Source: ARCHITECTURE.md)
- **PATTERN-ASSET-DECOMMISSION-ORDER** (Reason: TASK-6 mandates the PHP-enqueue-side is removed FIRST (TASK-1 already handled `admin/Main.php`'s `is_logs_page` guards), THEN the webpack entries + source files come out, THEN the stale `build/` outputs are cleaned. Wrong order produces PHP fatals on removed assets. Source: ARCHITECTURE.md)

## Accepted Deviations

- **DEC-SETTINGS-API-DEVIATION** (Reason: continues to apply to the surviving Settings API surface. Feature 040 removes one of three fields; the remaining two (per-page, uninstall-delete-data) continue to use WP Settings API per the pre-existing deviation. No scope change. Source: DECISIONS.md)

## Relevant Security Constraints

- **Removal of the P100001 `permission_callback` wrapper** (from `DEC-VARIADIC-CALLBACK-WRAP`) is security-adjacent: the wrapper only logged denials — it did not add or modify authorization. After Feature 040, wrapped permission callbacks fire directly without observability. No auth semantics change; nothing in `security-constraints.md` or `SEC-01`-`SEC-04` is affected. Verification: FR-005 grep for `wp_register_ability_args` must return zero matches to prove the wrapper is fully unwired. (Source: DECISIONS.md, security-constraints.md)
- **REST namespace `acrossai-abilities-log/v1` removal**: FR-004 removes the entire namespace. No auth surface exposed by that namespace remains callable post-Feature 040. Any external integration polling it 404s. Communicated via FR-011 release notes. Not a Constitution §IV violation — removing an auth-gated endpoint is safer than keeping it.

## Related Historical Lessons

- **BUG-INVENTORY-GREP-MISS** (Reason: Feature 034 shipped a mid-implementation regression because its removal inventory missed 4 consumer PHP files that didn't fit the initial grep search. Feature 040 counters this with a Phase 0 exhaustive `grep -rEn` protocol embedded in TASK-4 + the final full-repo grep as the merge blocker.)
- **BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE** (Reason: TASK-5 must place ALL new destructive operations inside the existing `if ( $acrossai_delete_data )` block. Placing them outside would wipe settings on every uninstall regardless of admin opt-in.)
- **BUG-PHPUNIT-AUTODISCOVERY-PREFIX** (Reason: TASK-7 must remove `<file>` entries from `phpunit.xml.dist` atomically with test file deletion. Stale entries → PHPUnit hard-error on file-not-found. `phpunit.xml.dist` uses explicit `<file>` entries because of this bug.)
- **Feature 012 worklog (2026-05-25)** — Sitewide module decommission; canonical `PATTERN-MODULE-DECOMMISSION` reference. Contrast: Feature 012 had a same-plugin replacement (Abilities module absorbed Sitewide's role); Feature 040 has NO replacement (logging moves to a future companion plugin outside this repo).
- **Feature 039 worklog (2026-07-01)** — Composer package updates; established the "no backward compat" data strategy (orphan legacy artifacts on upgrade; drop only on opt-in uninstall) that Feature 040's SC-004/SC-005 formalize.

## Conflict Warnings

- **SOFT CONFLICT — Constitution §I module enumeration**: `.specify/memory/CONSTITUTION.md` v1.4.7 §I explicitly lists **six active feature areas** including **"Ability Execution Logging"**, and the Directory Layout enumerates the `Logger/` module. Removing the Logger without amending the Constitution would leave a factual inconsistency (Constitution claims six modules; codebase has five). **Resolution**: the plan MUST include a PATCH-level Constitution bump (v1.4.7 → v1.4.8) that (a) removes "Ability Execution Logging" from the six-area sentence in §I (making it "five active feature areas"), (b) removes `Logger/` from the Directory Layout enumeration under Architecture & UI Standards, and (c) adds a SYNC IMPACT REPORT HTML comment at the top per `PATTERN-CONSTITUTION-SYNC-REPORT`. This aligns with §Governance's PATCH criterion ("clarifications, wording fixes, or non-semantic refinements") — no principle is added or removed; only the enumeration is corrected. Add as an explicit acceptance criterion or task in `plan.md` / `tasks.md`.

- **SOFT CONFLICT — `ARCH-ADV-001` pre-existing scope annotation**: `ARCH-ADV-001` already carries an annotation "Logger boot() removed in Feature 017" — the Logger's PATH-A/B `boot()` deviation was already dropped historically. Feature 040 completes the Logger removal; the annotation should be updated to note "Logger module fully removed in Feature 040" while retaining the Active status (the deviation still applies to the Override Processor). No conflict with Feature 040 itself — flag for TASK-8 memory hygiene.

## Retrieval Notes

- Index entries considered: ~18 (cap respected; scanned all Logger-tagged rows + `PATTERN-MODULE-DECOMMISSION`, `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`, `PATTERN-HELPER-DELETION-GREP-FIRST`, `PATTERN-UNINSTALL-DATA-GATE`, `PATTERN-ASSET-DECOMMISSION-ORDER`, `BUG-INVENTORY-GREP-MISS`, `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`, `BUG-PHPUNIT-AUTODISCOVERY-PREFIX`, `DEC-TABLE-SOFT-SINGLETON`, `DEC-FAIL-OPEN-NOTICE`, `DEC-SETTINGS-API-DEVIATION`, `ARCH-ADV-001`).
- Source sections read: `spec.md` (full, this feature); `docs/planning/040-remove-logs-module.md` (full, reference implementation breakdown); `.specify/memory/CONSTITUTION.md` v1.4.7 §I + §Governance (to identify the module enumeration conflict). No durable memory file opened in full — index entries were sufficient; the four Superseded-target decisions and three annotation-target patterns were resolved from INDEX metadata alone.
- Feature memory file (`specs/040-remove-logs-module/memory.md`): not present; not required.
- Budget: 2 active-decisions + 4 to-be-Superseded (informational) / 5 architecture patterns / 1 deviation / 2 security-adjacent notes / 3 bug patterns / 2 worklog items / ~900 words. Within all caps.
- Optimizer: not enabled (`.specify/extensions/memory-md/config.yml` absent); markdown-only index-first retrieval used.

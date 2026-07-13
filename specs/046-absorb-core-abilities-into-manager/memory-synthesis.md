# Memory Synthesis

## Current Scope

Feature 046 absorbs the `acrossai-core-abilities` companion plugin into the
manager. Runtime code (17 category folders, 201 ability classes, and the
companion `Utilities/` helper set) lands under a new isolated tree
`includes/Abilities/` inside the manager. The companion's single admin partial
`Core_Settings_Menu.php` moves to `admin/Partials/`; its two settings fields
(extra MIME types + uninstall opt-in) merge into the existing Abilities tab
inside the shared settings page — no separate Core tab. Companion option
`acrossai_core_abilities_extra_mimes` migrates at activation to
`acrossai_abilities_manager_extra_mimes`; the companion uninstall opt-in
is folded into the manager's existing single `acrossai_abilities_uninstall_delete_data`.
Every occurrence of "Acrossai Core Abilities" (labels, category slugs, class
names, function names) is rebranded to "Acrossai Abilities Manager". This is
an intentional breaking change to any external caller that referenced the
legacy `acrossai-core-abilities-<domain>` category slugs.

## Relevant Decisions

- **DEC-NAMESPACE-CONVENTION** — `AcrossAI_Abilities_Manager\Includes\*` underscore convention. (Reason Included: every moved file's namespace declaration and `use` statement must comply. Status: Active. Source: DECISIONS.md)
- **DEC-USE-STATEMENT-CONSISTENCY** — All `use` statements must match the underscore convention. (Reason Included: the bulk namespace rewrite touches hundreds of imports. Status: Active. Source: DECISIONS.md)
- **DEC-SINGLETON-PSR2-PROPERTY** — Singletons expose `$instance` (not `$_instance`), all 21 existing manager singletons use this. (Reason Included: the new `AcrossAI_Core_Abilities_Bootstrap` singleton and the moved `Core_Settings_Menu` must use `$instance`; source companion code likely uses `$_instance`. Status: Active. Source: DECISIONS.md)
- **DEC-SETTINGS-API-DEVIATION** — WP Settings API is accepted for scalar-field pages (≤5 fields). (Reason Included: FR-004 renders the extra-MIME-types field via the Settings API into the existing Abilities tab. Status: Active. Source: DECISIONS.md)
- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** — external packages whose constructors register hooks may bypass the Loader, gated by `class_exists()`. (Reason Included: precedent for a similar deviation may apply to the 218 absorbed classes that self-register hooks — see Conflict Warnings. Status: Active-accepted-deviation. Source: DECISIONS.md)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** — Only `Main.php` calls `loader->add_action/add_filter`; variable-first pattern. (Reason Included: the absorbed 17 `Category_Registrar` classes and 201 ability classes self-register hooks in constructors / `instance()` calls — see Conflict Warnings. Source: CONSTITUTION.md §I)
- **AC-FILE-HEADER-PATTERN** — `@package AcrossAI_Abilities_Manager`, `@subpackage <full/path>`, `@since 0.1.0`. (Reason Included: every moved PHP file's header must be rewritten during the migration. Source: ARCHITECTURE.md)
- **AC-ENQUEUE-ADMIN** — `wp_enqueue_*` only inside `admin/Main.php`. (Reason Included: no companion assets are being moved, but the "no residual enqueues in moved code" audit needs this baseline. Source: CONSTITUTION.md §I)
- **DEC-UTILITY-STATIC-ONLY** — Utility classes are 100% static; only orchestrators use singleton. (Reason Included: the moved `Utilities/` set includes `Cron_Helpers`, `Plugin_Helpers`, `Mime_Types_Store`, etc. — verify they are stateless-static after rename; the new `Bootstrap` is an orchestrator so singleton is correct. Source: DECISIONS.md)

## Accepted Deviations

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** — External Composer package constructors may self-register hooks with `class_exists()` guard. (Reason Included: precedent for how AC-HOOKS-MAIN can be relaxed. Status: Accepted-deviation, permanent. Source: DECISIONS.md)
- **DEC-SETTINGS-API-DEVIATION** — WP Settings API is accepted for ≤5-field pages instead of DataForm. (Reason Included: FR-004's MIME-types field piggybacks the manager's existing Settings API section. Status: Accepted-deviation. Source: DECISIONS.md)

## Relevant Security Constraints

- **SEC-03** — `AcrossAI_Abilities_Table::$global = false` for multisite isolation. (Reason Included: this feature adds no tables, but the guarantee applies to any DB helper that reads/writes options during activation migration. Source: security-constraints.md)
- **PATTERN-UNINSTALL-DATA-GATE** — `uninstall.php` wraps destructive deletes in an opt-in gate; config option deletion always runs. (Reason Included: FR-008 hooks the migrated MIME-types option deletion into the existing manager gate; unconditional deletion is a known bug (BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE). Source: ARCHITECTURE.md)

## Related Historical Lessons

- **BUG-INVENTORY-GREP-MISS + PATTERN-GREP-AUDIT-VS-MANDATED-STRINGS** — Exhaustive `grep -rEn` across `includes/src/tests/admin/` BEFORE approving any removal-scope inventory; audit patterns must exclude files where FR-N mandates the string (e.g., the Clarifications block naming the legacy `acrossai-core-abilities` slug is expected). (Reason Included: FR-006 + FR-006a + SC-005 require "zero refs to old names" audits and this pattern is the direct guardrail. Sources: BUGS.md, ARCHITECTURE.md)
- **BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE** — Option `delete_option()` calls placed outside `$acrossai_delete_data` gate wipe settings on every uninstall. (Reason Included: FR-008 adds the migrated MIME-types option to the gate; misplaced code caused a real regression. Source: BUGS.md)
- **PATTERN-SHARED-SETTINGS-SECTION-SCOPE** — Sections on a shared Settings API host register a tab + plain titles (preferred) or an em-dash plugin-scope prefix (fallback); `option_group` stays the shared slug. (Reason Included: merging the MIME-types field into the existing Abilities tab must land it in a well-scoped section, not a duplicate tab. Source: ARCHITECTURE.md)

## Conflict Warnings

**HARD (blocking) — AC-HOOKS-MAIN vs. self-registering ability & category classes.**
The companion's 17 `Category_Registrar` classes hook themselves into
`wp_abilities_api_categories_init` (via `add_action` in `instance()`), and the
201 ability class constructors likely hook themselves into
`wp_abilities_api_init`. Moving them as-is violates AC-HOOKS-MAIN ("Only
`Main.php` calls `loader->add_action/add_filter`"). Two paths for the plan
to resolve:

- **Path A — Route through Bootstrap (no code deviation)**: the new
  `AcrossAI_Core_Abilities_Bootstrap` (wired from `Main.php`) itself hooks
  `wp_abilities_api_init`; inside that callback it instantiates each ability
  class and calls a `register()` method directly (no `add_action` per class).
  Same treatment for categories via `register_categories()`. This requires
  refactoring the 218 classes to expose a synchronous `register()` method
  rather than self-registering in their constructor.
- **Path B — Record a new accepted deviation** parallel to
  `DEC-EXTERNAL-PACKAGE-HOOK-CTOR`, scoped to the absorbed 218 classes only,
  gated by `class_exists()` on the ability API's own registration functions.
  Preserves the companion's original class shape (minimal code change) at
  the cost of a new documented deviation.

Plan-phase decision required before task breakdown.

**SOFT — DEC-UTILITY-STATIC-ONLY vs. companion `Utilities/` set.** The
companion's `Cron_Helpers`, `Plugin_Helpers`, `Mime_Types_Store`, etc. may
carry non-static state. Plan should audit them and either refactor to
static-only or document why they need to stay non-static.

**SOFT — DEC-SINGLETON-PSR2-PROPERTY vs. companion singletons.** The
companion likely uses `$_instance`; every moved singleton (`Core_Settings_Menu`,
each `Category_Registrar`) must rename to `$instance` during the move.

## Retrieval Notes

- Considered ~20 index entries out of ~250; selected 5 decisions, 4
  architecture constraints, 2 accepted deviations, 2 security constraints,
  3 historical lessons.
- Read: `.specify/memory/CONSTITUTION.md` (line count only, 374 lines), full
  `docs/memory/INDEX.md`. Did NOT read `DECISIONS.md`, `ARCHITECTURE.md`,
  `BUGS.md`, or `WORKLOG.md` source files — the index rows carried enough
  context for planning.
- Budget: 5 / 5 decisions; 4 / 5 architecture constraints; 2 / 3 accepted
  deviations; 2 / 3 security constraints; 3 / 3 historical lessons; 0 / 2
  worklog items (no recent milestone directly maps to this migration scope).
  Synthesis length ≈ 780 words, within the 900-word cap.
- Two feedback memories honored: skip permission_callback compliance audit
  (`memory/feedback_skip_permission_callback_audit.md`), and user runs
  spec-kit commands manually (`memory/feedback_user_runs_speckit_commands.md`).

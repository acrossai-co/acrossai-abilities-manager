# Implementation Plan: Remove "Pass as Tool" Capability

**Branch**: `035-remove-pass-as-tool` | **Date**: 2026-06-16 | **Spec**: [spec.md](./spec.md)
**Input**: [Feature specification](./spec.md), [Memory synthesis](./memory-synthesis.md),
[Reference planning doc](../../docs/planning/035-remove-pass-as-tool.md)

## Summary

Delete the `pass_as_tool` ability flag end-to-end: the BerlinDB column, the runtime injection hook
(`AcrossAI_Ability_Override_Processor::inject_mcp_tools` at `mcp_adapter_init` P20 via
Reflection), the supporting utilities (Sanitizer / Formatter / Merger), the React admin
surfaces (list column + AbilityForm Section 4 + store), and all dedicated PHPUnit suites.
Migration is **manual** (deactivate plugin → drop abilities table → reactivate) — no
`maybe_upgrade()` routine, no `$version` bump, per Clarifications Session 2026-06-16. Memory
artifacts are updated to mark `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` Superseded, close
`BUG-MCP-TYPE-PASSTOOL-CONFLICT`, and preserve the durable lessons
(`BUG-BERLINDDB-QUERY-PRIVATE-CTOR`, `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS`).

## Technical Context

**Language/Version**: PHP 8.1+ (Constitution §II floor), JavaScript ES2022 (transpiled via
`@wordpress/scripts` / Babel preset-env)
**Primary Dependencies**: WordPress 6.9+, BerlinDB v3 (custom abilities table),
`@wordpress/dataviews`, `@wordpress/i18n`, `@wordpress/element`, React 18, mcp-adapter (consumer
only — never inspected/modified after this feature)
**Storage**: Single custom table `{$wpdb->prefix}acrossai_abilities` (BerlinDB schema). One
column is removed from the schema definition; no other table or column is touched.
**Testing**: PHPUnit 10 with WordPress test stubs (`tests/phpunit/`), Jest via
`@wordpress/scripts test-unit-js` (`tests/jest/`)
**Target Platform**: WordPress 6.9+ on PHP 8.1+, multisite-compatible (per-site prefix retained
via SEC-03)
**Project Type**: WordPress plugin (singular `acrossai-abilities-manager` plugin; no separate
backend/frontend; React admin is bundled into `build/js/abilities.js` via `@wordpress/scripts`).
**Performance Goals**: No measurable change — removal-only feature. Abilities list page load
remains within prior-noise envelope (SC-002). MCP server init no longer pays the cost of the
per-ability Reflection loop in `inject_mcp_tools()`.
**Constraints**:
- No automated DB migration; manual operator procedure documented (FR-002, FR-009).
- Silent ignore of inbound REST `pass_as_tool` keys (FR-006) — leverages WP REST's default
  unknown-param behavior; no new validation branch.
- Section numbering inside AbilityForm.jsx must stay contiguous after Section 4 deletion
  (ARCH-ABILITYFORM-SECTION-ORDER).
- `ARCH-ADV-001` Override-Processor `boot()` deviation stays — its surface narrows by one hook.
**Scale/Scope**: ~12 production PHP/JSX files edited, 6 PHPUnit files deleted + mixed-concern
fixture edits, 4 memory artifacts updated. No new modules, no new dependencies.

## Constitution Check

*GATE: Must pass before implementation begins. Re-check after Phase 1 (tasks) is finalized.*

| # | Principle | Verdict | Notes |
|---|-----------|---------|-------|
| I | Modular Architecture | **Pass** | Scope is confined to the existing `Abilities` module + shared `Utilities/`. No new modules, no cross-module coupling introduced. |
| II | WordPress Standards Compliance | **Pass** | PHPCS / PHPStan L8 / Plugin Check / ESLint must remain green (SC-006). No new SQL, no new forbidden functions, no `%i`-eligible identifiers added or removed. |
| III | User-Centric Design | **Pass — narrowed UI** | Removing one DataViews column and one form Section is a reduction, not new custom UI. `@wordpress/dataviews` invariant preserved. |
| IV | Security First (NON-NEGOTIABLE) | **Pass — net improvement** | Removes a Reflection-based reach into `McpServer::$component_registry` private state. No new input, output, or capability path introduced. `SEC-03`/`SEC-04` preserved. |
| V | Extensibility Without Core Modification | **Pass** | Removes one core-plugin behavior; clients that need it must register their own hooks (e.g., the future `acrossai-mcp-manager`). No upstream package files modified. |
| VI | Reusability & DRY | **Pass** | Deletes code; introduces no duplication. `AcrossAI_Sanitizer::cast_tri_state` (the shared base) is untouched and continues to serve the remaining tri-state fields. |
| VII | Definition of Done | **Pass — explicit DoD in Validation Checklist** | Each DoD checkbox maps to an SC/FR in spec; tasks.md will lift them into terminal verification tasks. |

**Boot Flow Rule**: The deletion target is one `add_action()` call inside
`AcrossAI_Ability_Override_Processor::boot()`. Per `ARCH-ADV-001` (accepted deviation), this
`boot()` is the documented exception to the "only Main.php wires hooks" rule. Removing the line
**shrinks** the deviation surface; tasks must update the `boot()` docblock to reflect the
narrower surviving set (`wp_register_ability_args`, `wp_abilities_api_init`) but MUST NOT remove
the deviation itself.

**Database Constraint**: Custom table is permitted per existing justification; the schema
narrowing (one column removed) does not regress justification. `$global = false` (SEC-03)
remains. `%i` is not needed because no new SQL identifier interpolation is added.

**Conclusion**: All gates pass. No violations require Complexity Tracking entries.

## Project Structure

### Documentation (this feature)

```text
specs/035-remove-pass-as-tool/
├── spec.md                  # Feature specification (clarified)
├── plan.md                  # This file
├── memory-synthesis.md      # Memory-md synthesis from prior turn
├── checklists/
│   └── requirements.md      # Quality checklist
├── security-constraints.md  # Generated by /speckit-security-review-plan (this turn)
└── tasks.md                 # Generated by /speckit-tasks (next phase)
```

`research.md`, `data-model.md`, `quickstart.md`, and `contracts/` are not required for this
feature: it is a pure removal with no new data model, no new contract, and no novel
research. The reference planning doc at `docs/planning/035-remove-pass-as-tool.md` already
contains the inventory and per-file change blocks; tasks.md will reference it directly rather
than duplicate the contents.

### Source Code (repository root)

```text
includes/
├── Main.php                                              # MODIFIED: delete 4-line comment block (lines 374–378)
├── Modules/
│   └── Abilities/
│       ├── AcrossAI_Ability_Override_Processor.php       # MODIFIED: delete inject_mcp_tools(),
│       │                                                   the mcp_adapter_init add_action in boot(),
│       │                                                   user_has_ability_access() if no other caller;
│       │                                                   narrow boot() docblock to surviving hooks
│       └── Database/
│           ├── AcrossAI_Abilities_Schema.php             # MODIFIED: delete pass_as_tool column array
│           ├── AcrossAI_Abilities_Row.php                # MODIFIED: docblock @property, property,
│           │                                                blocked_scalar_columns, tri_state_fields
│           └── AcrossAI_Abilities_Query.php              # MODIFIED: delete get_pass_as_tool_slugs(),
│                                                            remove from $tri_state array
└── Utilities/
    ├── AcrossAI_Ability_Merger.php                       # MODIFIED: remove from $overridable_fields
    ├── AcrossAI_Abilities_Sanitizer.php                  # MODIFIED: remove from $tri_state_fields
    └── AcrossAI_Abilities_Formatter.php                  # MODIFIED: remove from 3 response shapes

src/js/abilities/
├── components/
│   ├── AbilitiesList.jsx                                 # MODIFIED: delete PassAsToolCell + column
│   └── AbilityForm.jsx                                   # MODIFIED: delete Section 4 + payload key;
│                                                            renumber surviving sections
└── store/
    └── index.js                                          # MODIFIED: remove from OVERRIDABLE_FIELDS

build/js/abilities.js                                    # REGENERATED by `npm run build` (do not hand-edit)

tests/phpunit/
├── Utilities/
│   ├── AcrossAI_Abilities_Sanitizer_PassAsTool_Test.php  # DELETED
│   └── AcrossAI_Abilities_Formatter_PassAsTool_Test.php  # DELETED
├── Modules/
│   ├── McpToolsPassthrough/                              # DELETED (dir, after file below removed)
│   │   └── AcrossAI_Mcp_Tools_Passthrough_Test.php       # DELETED
│   └── Abilities/Database/
│       ├── AcrossAI_Abilities_Row_PassAsTool_Test.php    # DELETED
│       ├── AcrossAI_Abilities_Schema_PassAsTool_Test.php # DELETED
│       └── AcrossAI_Abilities_Query_PassAsTool_Test.php  # DELETED
└── **/*                                                  # MIXED-CONCERN tests: remove
                                                            pass_as_tool fixtures + assertions
                                                            (in-place edits; do not delete files)

docs/memory/
├── DECISIONS.md                                          # MODIFIED: add DEC-PASS-AS-TOOL-REMOVED;
│                                                            mark DEC-MCP-TOOLS-PASSTHROUGH-COLUMN Superseded;
│                                                            annotate DEC-MCP-INJECT-REFLECTION-PATTERN
│                                                            with forward-pointer note
├── BUGS.md                                               # MODIFIED: mark BUG-MCP-TYPE-PASSTOOL-CONFLICT
│                                                            Resolved; annotate BUG-INJECT-MCP-TOOLS-
│                                                            PERMISSION-BYPASS context (trigger gone,
│                                                            lesson durable)
├── INDEX.md                                              # MODIFIED: row updates matching DECISIONS/BUGS
│                                                            edits + new DEC-PASS-AS-TOOL-REMOVED row +
│                                                            Feature 035 worklog row
└── WORKLOG.md                                            # MODIFIED: append Feature 035 entry

.specify/memory/CONSTITUTION.md                          # REVIEW only — grep for pass_as_tool /
                                                            inject_mcp_tools / McpToolsPassthrough.
                                                            If hits exist, remove + PATCH version bump
                                                            with sync-impact report. Likely no-op.

AGENTS.md                                                # REVIEW only — same grep; likely no-op.
```

**Structure Decision**: No structural change to the plugin layout. Feature 035 only deletes
content from already-existing files, deletes six dedicated test files plus one test directory,
and updates documentation. No new module, no new directory, no new dependency.

## Implementation Approach (high-level — full task breakdown in tasks.md)

This is a destructive, mechanical removal. The risk class is "decommission with cross-cutting
references" — the lesson from Feature 034 (`BUG-INVENTORY-GREP-MISS`) is that exhaustive grep
**before** approval is mandatory. Tasks.md must encode that as a terminal gate.

Recommended execution order (predecessor → successor):

1. **Inventory gate (preflight)** — run the exhaustive grep target documented in the spec
   Validation Checklist. Confirm the live reference list matches the planning-doc inventory.
   Surface any net-new references before any deletion.
2. **PHP back-end edits** — Schema → Row → Query → Override_Processor → Utilities → Main.php.
   Schema/Row/Query first so the type system is consistent before anything else reads from the
   row.
3. **JavaScript front-end edits** — AbilityForm → AbilitiesList → store. AbilityForm first so the
   section-numbering churn is contained.
4. **Tests** — delete the six dedicated suites and their parent directory; then walk
   mixed-concern tests and strip `pass_as_tool` fixtures / assertions until the in-tests grep is
   clean.
5. **Build** — `npm run build` to regenerate `build/js/abilities.js`. Verify zero warnings; verify
   the bundled output contains no `pass_as_tool` references.
6. **Memory artifacts** — DECISIONS.md, BUGS.md, INDEX.md, WORKLOG.md. CONSTITUTION.md +
   AGENTS.md grep-only.
7. **Quality gates** — `composer phpstan` (level 8), `composer phpcs`, PHPUnit, Jest,
   Plugin Check (`wp-env run cli wp plugin check`).
8. **Final exhaustive grep** — must return zero hits outside the documented historical scope
   (see SC-005 + spec Validation Checklist). This is the feature's terminal acceptance gate.
9. **Manual smoke** — load the abilities list page and the ability edit page; confirm no
   "Pass as Tool" column header, no Section 4, contiguous section numbers, browser console
   clean.

## Risks & Mitigations

- **Risk**: Override_Processor edits accidentally remove `inject_override_args` or
  `unregister_blocked_abilities` along with `inject_mcp_tools`.
  **Mitigation**: Tasks.md must call out the *preserved* methods explicitly in the edit task
  and require a post-edit grep for `function inject_override_args(` and
  `function unregister_blocked_abilities(` returning ≥1 hit each.
- **Risk**: `user_has_ability_access()` is removed but is still called by future code that
  isn't yet merged.
  **Mitigation**: Tasks.md gates deletion of the helper on a fresh grep of `includes/`, `src/`,
  and `tests/` for `user_has_ability_access`. If the only hit is `inject_mcp_tools()`, delete;
  otherwise keep.
- **Risk**: Mixed-concern PHPUnit tests fail because their fixture row objects still set
  `pass_as_tool` after the Row property is removed.
  **Mitigation**: Order tasks so test fixture edits come before deletion of the dedicated test
  files (deleting first removes coverage; failing first would block the deletion).
- **Risk**: Browser column-visibility prefs in `localStorage` contain a `pass_as_tool` key from a
  prior plugin version and break the column loader.
  **Mitigation**: `DEC-COLUMN-VISIBILITY-LOCALSTORAGE` merge-over-defaults already tolerates
  unknown keys. Spec FR-004 and US2 AS3 require this be verified manually.
- **Risk**: The future `acrossai-mcp-manager` plugin assumes `DEC-MCP-INJECT-REFLECTION-PATTERN`
  is still active in *this* repo's memory.
  **Mitigation**: Annotate the decision entry with a forward-pointer note rather than marking it
  Superseded. The pattern is still correct; only its in-this-plugin consumer is removed.

## Complexity Tracking

No constitution violations identified. No entries required.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |

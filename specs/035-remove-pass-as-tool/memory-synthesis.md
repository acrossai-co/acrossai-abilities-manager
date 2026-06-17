# Memory Synthesis

## Current Scope

Feature 035 removes the `pass_as_tool` capability introduced by Feature 029. Affected modules:

- `includes/Modules/Abilities/Database/` (Schema, Row, Query, Table)
- `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (drop `inject_mcp_tools`
  hook and `user_has_ability_access` helper; preserve every other method)
- `includes/Utilities/` (Sanitizer, Formatter, Merger)
- `includes/Main.php` (one comment block)
- `src/js/abilities/components/` (`AbilitiesList.jsx`, `AbilityForm.jsx`) and `store/index.js`
- `tests/phpunit/` (six full deletions + mixed-concern fixture edits)
- `docs/memory/` (DECISIONS, INDEX, BUGS, WORKLOG)
- `.specify/memory/CONSTITUTION.md` (only if it references the removed surface)

Migration: manual (deactivate → drop table → reactivate); **no** `maybe_upgrade()` routine, **no**
table `$version` bump. Confirmed in Clarifications session 2026-06-16.

## Relevant Decisions

- **DEC-MCP-TOOLS-PASSTHROUGH-COLUMN** *(Active → to be marked Superseded by this feature)* —
  Per-ability MCP tool pass-through column + registry injection (`pass_as_tool` tinyint,
  `inject_mcp_tools` at `mcp_adapter_init` P20 via Reflection on
  `McpServer::$component_registry`, deviation ARCH-ADV-001). The decision this feature retires.
  *Reason included*: spec's central target; tasks MUST add the supersession + new
  `DEC-PASS-AS-TOOL-REMOVED` entry. *Source*: DECISIONS.md.
- **DEC-MCP-INJECT-REFLECTION-PATTERN** *(Active)* — `mcp_adapter_init` P20 + Reflection on private
  `McpServer::$component_registry` is the canonical injection pattern; `mcp_adapter_tools_list` is
  display-only. *Reason included*: documents the exact pattern being deleted; flag for status
  update or annotation pointing at the future `acrossai-mcp-manager` plugin. *Source*: DECISIONS.md.
- **ARCH-ADV-001 / DEC-ARCH-ADV-001** *(Active, accepted deviation)* — `boot()` wires hooks
  directly (bypasses Boot Flow Rule) on Override Processor only, for PATH-A/B conditional
  loading. *Reason included*: this feature deletes one of the wired hooks
  (`mcp_adapter_init`); the deviation remains valid for the surviving `wp_register_ability_args`
  and `wp_abilities_api_init` registrations. Do NOT remove the deviation; just narrow its
  documented surface. *Source*: DECISIONS.md.
- **ARCH-ABILITYFORM-SECTION-ORDER** *(Active)* — Canonical AbilityForm.jsx section order
  1–6/1–7 with extension slot at position 3; all inside a single `.panel`. *Reason included*:
  deleting Section 4 ("Pass as Tool") requires renumbering surviving sections to keep numbering
  contiguous. Plan and tasks must call out section-num renumbering as a verification step.
  *Source*: ARCHITECTURE.md.
- **DEC-COLUMN-VISIBILITY-LOCALSTORAGE** *(Active)* — Column prefs in localStorage with
  merge-over-`COLUMN_DEFAULTS`; new columns always default visible. *Reason included*: removing
  the `pass_as_tool` key from `COLUMN_DEFAULTS` plus the merge semantics means orphan localStorage
  entries are silently ignored (already covered by US2 acceptance scenario 3 and FR-004). No new
  defensive code needed. *Source*: DECISIONS.md.

## Active Architecture Constraints

- **AC-HOOKS-MAIN** — Only Main.php calls `loader->add_action`/`add_filter`. *Reason included*:
  the deleted `add_action( 'mcp_adapter_init', … )` line lives inside Override Processor's
  `boot()`, which is the documented exception per ARCH-ADV-001. Removing the line shrinks the
  deviation surface; the constraint is otherwise unaffected. *Source*: CONSTITUTION.md §I.
- **ARCH-UNIFIED-ABILITIES-STORAGE** — Abilities module owns the unified abilities table;
  override rows identified by source semantics. *Reason included*: column removal stays inside
  the abilities-table boundary; no cross-module write paths are introduced. *Source*:
  ARCHITECTURE.md.
- **ARCH-SANITIZER-TWO-CLASS** — `AcrossAI_Sanitizer` (base) ≠ `AcrossAI_Abilities_Sanitizer`
  (wrapper). *Reason included*: dropping `pass_as_tool` from the wrapper's `$tri_state_fields`
  array is a wrapper-level change only; the base class `cast_tri_state` is untouched and still
  used by the remaining tri-state fields. *Source*: ARCHITECTURE.md.

## Accepted Deviations

- **ARCH-ADV-001** *(scope: Override Processor only)* — Conditional hook wiring inside `boot()`.
  *Reason included*: this feature removes one wired hook; the deviation continues to cover the
  surviving hooks. Tasks must annotate the boot() docblock to reflect the narrower set.

## Relevant Security Constraints

- **SEC-04** — Strict type comparison for access control checks. *Reason included*: the surviving
  Override Processor methods (`unregister_blocked_abilities`, `inject_override_args`) still rely
  on `=== false` / `=== true` for tri-state evaluation. Do not relax. *Source*:
  security-constraints.md.
- **SEC-03** — `AcrossAI_Abilities_Table::$global = false` (per-site prefix). *Reason included*:
  remains intact through the column removal; no multisite assumption changes. *Source*:
  security-constraints.md.

## Related Historical Lessons

- **BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS** *(immediate trigger gone after this feature, lesson
  durable)* — `inject_mcp_tools()` required `check_permissions()` as the 4th gate; AC rules are
  fail-open. *Reason included*: the specific function is removed by this feature, but the
  underlying lesson ("AC rule absent ⇒ allow; always pair AC checks with the ability's own
  permission callback") must be preserved in BUGS.md/INDEX and flagged as still-applicable to
  any future code that gates on AC alone.
- **BUG-BERLINDDB-QUERY-PRIVATE-CTOR** *(durable lesson, unaffected)* — `new AcrossAI_Abilities_Query()`
  is a fatal; always use `::instance()`. *Reason included*: still applies broadly; the lesson
  pre-dates Feature 029 and survives intact. Memory cleanup must NOT alter its status.
- **BUG-MCP-TYPE-PASSTOOL-CONFLICT** *(closed by this feature)* — `pass_as_tool=1` +
  `mcp_type=resource/prompt` caused DefaultServerFactory resource errors. *Reason included*:
  with the column and injection removed, the conflict surface is gone; mark Resolved (feature
  removed 2026-06-16).
- **BUG-INVENTORY-GREP-MISS** (Feature 034) — Exhaustive `grep -rEn` across includes/src/tests/admin/
  is mandatory BEFORE marking a removal complete. *Reason included*: this feature is exactly a
  "remove keyword X" task. The validation checklist in spec already encodes this; tasks.md must
  include the exhaustive grep step explicitly as a verification gate.

## Conflict Warnings

- **Intentional supersession (soft conflict)**: `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` is currently
  **Active** but Feature 035 retires it. This is not a violation — it is the explicit purpose of
  the feature. Plan and tasks must include a step to mark it `Superseded by DEC-PASS-AS-TOOL-REMOVED
  (2026-06-16)` rather than deleting the entry.
- **Pattern repositioning (soft conflict)**: `DEC-MCP-INJECT-REFLECTION-PATTERN` documents a
  pattern that no longer has a consumer in this plugin after Feature 035. Recommend annotating
  the entry with a forward pointer to `acrossai-mcp-manager` (the future consumer) rather than
  marking it Superseded — the pattern remains valid; it just lives elsewhere now.
- No **hard conflicts** with constitution, architecture, or safety rules. Planning may proceed.

## Retrieval Notes

- Source files read: `docs/memory/INDEX.md` (full, 219 lines, ~once-per-feature cost).
- Index entries selected: 17 of 20 budget — 5 active decisions, 3 architecture constraints, 1
  accepted deviation, 2 security constraints, 4 bug/lesson patterns, 2 worklog markers (Feature
  029 introduction, Feature 034 immediate predecessor).
- Durable memory files NOT loaded (deferred unless plan-time conflict surfaces): DECISIONS.md
  (full body), BUGS.md (full body), ARCHITECTURE.md, WORKLOG.md, security-constraints.md,
  CONSTITUTION.md.
- Phase: Specify/Plan — boundaries, module ownership, deviation surface narrowing.
- Word count: ~870 (under 900 budget). Full durable memory read: not required.

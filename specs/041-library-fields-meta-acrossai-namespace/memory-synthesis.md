# Memory Synthesis — Feature 041

**Feature**: Library display fields under `$args['meta']['acrossai']`
**Date**: 2026-07-03
**Query**: "meta namespace ability fields plugin extension mcp annotations"

## Retrieved memory entries (pre-implementation)

### Patterns referenced

- **`PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`** (ARCHITECTURE.md, Feature 037 provenance) — Registry allowlists `args` keys but does NOT deep-sanitize passthrough values; consumers responsible for escaping/safe rendering. **Post-041 update**: allowlist entries change (three top-level entries removed; `meta` unchanged), but the sanitize-at-Registry-boundary discipline is unchanged. Only the input-side extraction moves; row-shape and Registry-boundary sanitization are identical.

- **`PATTERN-VENDOR-LIB-JS-CONSUMER-AUDIT`** (ARCHITECTURE.md, Feature 039) — audit protocol for JS-side consumer impact after vendor upgrades. **Applicable here**: verified no JS consumer reads from `args.meta.acrossai` (only reads from row-top `sub_group` / `sub_group_label` / `tab_group`). Refactor is invisible to JS.

- **`PATTERN-META-ACROSSAI-NAMESPACE`** (ARCHITECTURE.md, NEW — created by Feature 041 T015) — canonical namespace for plugin-specific ability extension fields.

### Decisions referenced

- **`DEC-MCP-CAPABILITY-FILTER-WARN`** (DECISIONS.md, Superseded by Feature 034) — precedent for `meta.mcp.*` nested shape (this plugin's earliest use of a nested plugin-specific meta namespace).

- **`DEC-HOOK-PARAM-EXTRACTION`** (DECISIONS.md, Superseded by Feature 040) — pattern of defensive `is_array()` + `isset()` guards when extracting from nested meta structures. Applied in the T004 rewrite.

- **`DEC-META-ACROSSAI-NAMESPACE`** (DECISIONS.md, NEW — created by Feature 041 T016) — the hard-cut consolidation decision.

### Architecture constraints referenced

- **`AC-HOOKS-MAIN`** — only `Main.php` registers hooks via the Loader. Feature 041 does NOT add or remove any hook; `Main.php` is untouched.

### Bug patterns referenced

- **`BUG-INJECT-MISSING-TOP-LEVEL-FIELDS`** (BUGS.md, Feature 024) — writing to `$args[<field>]` when the read side expects `$args['meta']['<ns>'][<field>]` (or vice versa) causes silent field loss. Applicable in reverse for Feature 041: the negative-regression tests (T006) explicitly catch this class of bug for the top-level shape.

- **`BUG-MERGER-BOOL-STRING-CAST`** (BUGS.md, Feature 024) — tri-state field cast bugs. Not applicable here (sub_group / tab_group / sub_group_label are strings, not tri-state).

### Worklog milestones referenced

- **2026-06-14 Feature 033** — Introduced `sub_group` and `sub_group_label` at top level of `$args`.
- **2026-06-25 Feature 037** — Introduced `tab_group` at top level; established `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`.
- **2026-07-01 Feature 040** — Full Logger decommission; sets precedent for hard-cut refactors with no backward-compat fallback. Feature 041 is the second such hard cut.

### FR-009 field-path table (spec 004)

Located at `specs/004-ability-override-processor/spec.md` lines 99-113. Documents the field-path mapping for overridable fields:

- `readonly` / `destructive` / `idempotent` → `$args['meta']['annotations'][<field>]`
- `show_in_mcp` / `mcp_type` / `mcp_servers` → `$args['meta']['mcp'][<field>]`

**Feature 041 does NOT modify this table.** The three fields being refactored are NOT overridable (never persist to DB, never enter the override merge path). The table's scope is unchanged. `PATTERN-META-ACROSSAI-NAMESPACE` documents the parallel `meta.acrossai.*` namespace for NON-overridable plugin-specific fields.

## Soft conflicts detected

None. This refactor consolidates fields under an established pattern (`meta.<ns>` nesting). No principle collision, no decision conflict, no bug reintroduction.

## Applied to plan

- **Hard cut with no fallback** — matches Feature 040 precedent (recent enough hard cut to be current-culture; no BC layer needed).
- **`is_array()` guard** — pattern per `DEC-HOOK-PARAM-EXTRACTION` for defensive nested-array extraction.
- **Row shape preservation** — internal-contract discipline; row is JS-facing via `window.acrossaiAbilityLibraryData`; changing it would ripple through the JS side unnecessarily.
- **Two new memory entries** — `PATTERN-META-ACROSSAI-NAMESPACE` + `DEC-META-ACROSSAI-NAMESPACE` — captured to prevent Feature 042 (hypothetical future field addition) from repeating the Feature 033/037 top-level oversight.

## Post-implementation memory-hygiene actions (T015-T018)

- ARCHITECTURE.md — new `PATTERN-META-ACROSSAI-NAMESPACE` entry (~30 LoC). Forward-pointer added to `PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`.
- DECISIONS.md — new `DEC-META-ACROSSAI-NAMESPACE` entry (~20 LoC).
- WORKLOG.md — Feature 041 milestone entry (~10 LoC, top-of-file per convention).
- INDEX.md — three routing rows.

## Token savings vs full-memory read

Optimizer disabled (`.specify/extensions/memory-md/config.yml` absent). Markdown-only synthesis flow used. No token-report generated.

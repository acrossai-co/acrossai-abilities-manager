# Architecture Violation Detection — Plan Stage (Feature 035)

**Inputs scanned**: `plan.md`, `spec.md`, `memory-synthesis.md`, `security-constraints.md`,
`.specify/memory/CONSTITUTION.md` (v1.4.7 per sync impact reports, footer stale at v1.4.5),
`docs/memory/INDEX.md` (selected entries).

**Verdict**: **No hard architectural conflicts.** Two intentional soft transitions (already
captured in the memory synthesis) are reaffirmed. No Security-Architecture conflict. No
constitution principle is violated by the plan as written.

## Findings

### A-035-1 — `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` intentional supersession (Soft)

**Type**: Decision-state transition (intentional, not drift)
**Severity**: Informational
**Detail**: `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` is currently **Active** in INDEX.md. The plan
explicitly retires it (plan.md → Implementation Approach step 6; memory-synthesis.md → Conflict
Warnings). This is the **point** of the feature; it is not architectural drift. Tasks.md must
include a task that:
- Adds a new `DEC-PASS-AS-TOOL-REMOVED (2026-06-16)` entry to `DECISIONS.md`.
- Updates the existing entry's status line to `Superseded by DEC-PASS-AS-TOOL-REMOVED
  (2026-06-16)` — do NOT delete.
- Updates the matching INDEX.md row's Status column to `Superseded`.

**Action**: Confirmed as a planned task. No remediation needed at plan stage.

### A-035-2 — `DEC-MCP-INJECT-REFLECTION-PATTERN` loses in-plugin consumer (Soft)

**Type**: Pattern-without-consumer
**Severity**: Informational
**Detail**: The decision documents the canonical pattern for injecting tools into MCP servers
via Reflection on `McpServer::$component_registry`. After Feature 035, this plugin's only
consumer of the pattern is gone. The pattern itself remains correct (mcp-adapter's `$component_registry`
is still private; `mcp_adapter_server_config` is still absent in the installed version).

**Action**: Tasks.md must annotate the existing decision entry with a forward-pointer note
("Consumer removed in Feature 035; pattern retained for the future `acrossai-mcp-manager`
plugin"). Do NOT mark Superseded — the pattern is still valid; only its in-this-plugin consumer
is gone.

### A-035-3 — `ARCH-ADV-001` boot() deviation surface narrows (Soft)

**Type**: Accepted-deviation scope narrowing
**Severity**: Informational
**Detail**: The accepted deviation permits `AcrossAI_Ability_Override_Processor::boot()` to
register hooks directly (bypassing the Boot Flow Rule in CONSTITUTION.md §"Architecture & UI
Standards") for PATH-A/B conditional loading. The plan removes one of the wired hooks
(`mcp_adapter_init` P20). The deviation **still applies** to the two surviving hooks
(`wp_register_ability_args`, `wp_abilities_api_init`).

**Action**: Tasks.md must update the `boot()` docblock to enumerate the **two** surviving hooks
where it currently enumerates three. The deviation entry in DECISIONS.md does NOT need a
version bump — its scope is qualitative (PATH-A/B conditional wiring), not quantitative.

### A-035-4 — Constitution version footer is stale (Pre-existing, out-of-scope)

**Type**: Documentation inconsistency (pre-existing)
**Severity**: Informational
**Detail**: `.specify/memory/CONSTITUTION.md` footer reads `**Version**: 1.4.5 | **Ratified**:
2026-05-11 | **Last Amended**: 2026-06-06`, but the sync impact reports at the top show the file
is at v1.4.7 (most recent being Feature 034's v1.4.6 → v1.4.7 retraction). This drift between
the sync-impact log and the footer is **pre-existing** — Features 020 → 021 → 028 → 034 all
bumped the version in the sync report but apparently did not always advance the footer.

**Action**: **Out of scope for Feature 035.** Flag for a future memory-housekeeping pass.
This feature should NOT attempt to fix the footer in a side-effect change — that belongs in a
dedicated documentation cleanup.

### A-035-5 — Constitution §V (Extensibility) compatibility check (Pass)

**Type**: Principle alignment
**Severity**: Informational
**Detail**: §V mandates that integrations must be optional and the plugin must degrade
gracefully when an integrated plugin/service is absent. Removing `inject_mcp_tools()` aligns
this plugin with the inverted ownership model: per-server tool registration is now the
responsibility of MCP server owners, not the Abilities Manager. The future
`acrossai-mcp-manager` plugin is an optional integration; this plugin must continue to function
when it is absent (it does — no code path depends on it).

**Action**: None. Plan is compliant.

### A-035-6 — Constitution §II (Standards) — Plugin Check / PHPCS / PHPStan (Pass-pending-execution)

**Type**: Quality-gate alignment
**Severity**: Informational
**Detail**: The plan's "Quality gates" step in the implementation approach lists `composer
phpstan`, `composer phpcs`, PHPUnit, Jest, and Plugin Check. Each is the canonical green gate
per the constitution and `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE`. Spec SC-006 enforces.

**Action**: Tasks.md must include each gate as an explicit terminal task with an output capture
(or a CI run reference) so the verdict is auditable.

## Security-Architecture Conflict Check

**Result**: **No conflict.**

The security review (security-constraints.md) and the architecture review agree on every
boundary change:

- Removal of Reflection reach into mcp-adapter private state: **security improvement**; no
  architectural rule violated (the Reflection was previously documented as the canonical
  pattern but is now retired in this plugin).
- Silent-ignore of inbound REST `pass_as_tool`: **uses default WP REST behavior**; no
  architectural addition required.
- Manual deactivate-drop-reactivate migration: **explicit deviation from automated migration
  norms** — flagged in the Clarifications session and documented in the spec Assumptions. The
  constitution does not mandate any specific migration strategy for pre-launch plugins, so
  this is a permitted choice.

## Drift / Anti-Pattern Scan

Cross-referenced against the memory-synthesis bug patterns:

- **`BUG-INVENTORY-GREP-MISS`**: The plan's "Inventory gate (preflight)" step + the spec's
  Validation Checklist exhaustive grep target encode the lesson. Tasks.md must execute the
  grep BEFORE any deletion task starts, and again as the terminal acceptance gate.
- **`BUG-BERLINDDB-QUERY-PRIVATE-CTOR`**: Plan does not introduce any `new
  AcrossAI_Abilities_Query()` calls; deleted methods are static. Safe.
- **`BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS`**: Trigger function is being deleted; lesson
  survives via the synthesis's "Related Historical Lessons" entry and the BUGS.md annotation
  task. Safe.

## Recommendation

Proceed to `/speckit-tasks`. The plan is constitution-compliant, security-clean, and
architecturally sound. Five informational findings (A-035-1 through A-035-3, A-035-5, A-035-6)
translate into specific tasks that must appear in tasks.md; A-035-4 is explicitly deferred.

# Architecture Migration Plan: MCP Allowlist Enforcement Inversion

This migration plan documents the central architectural move of Feature 034 — relocating per-ability MCP-server allowlist enforcement OUT of `acrossai-abilities-manager` and into a future, separately-shipped extension plugin (`acrossai-mcp-manager`). It supplements `plan.md`, which focuses on the mechanical removals/additions, by capturing the *architectural inversion* explicitly so future readers see "authorization layer relocated" rather than just "UI removed".

## Current State

```
┌─────────────────────────────────────────────────────────────────┐
│ acrossai-abilities-manager (current)                            │
│                                                                 │
│   AcrossAI_Abilities_Schema                                     │
│     └── mcp_servers column (longtext, nullable, JSON array)     │
│                                                                 │
│   AcrossAI_Abilities_Sanitizer / AcrossAI_Sanitizer             │
│     └── sanitize_mcp_servers() + sanitize_mcp_servers_array()   │
│                                                                 │
│   AcrossAI_Abilities_Write_Controller (REST)                    │
│     └── mcp_servers REST arg schema (create + update routes)    │
│                                                                 │
│   AcrossAI_Abilities_Read_Controller / Formatter                │
│     └── mcp_servers in response shaping                         │
│                                                                 │
│   AcrossAI_Ability_Merger                                       │
│     └── mcp_servers in field list (merge semantics: meta.mcp.servers │
│         OR annotation OR row column → merged ability)           │
│                                                                 │
│   AcrossAI_Abilities_Query                                      │
│     └── mcp_servers non-string guard on BerlinDB save           │
│                                                                 │
│   AcrossAI_Abilities_Exposure_Controller   ★ ENFORCEMENT ★      │
│     └── fail-closed: row.mcp_servers non-empty + server_id      │
│         unknown → EXCLUDE from MCP exposure                     │
│                                                                 │
│   AcrossAI_Ability_Override_Processor      ★ ENFORCEMENT ★      │
│     ├── allowlist injection into $args['meta']['mcp']['servers'] │
│     └── per-server filter hook implementing allowlist semantics │
│                                                                 │
│   AbilityForm.jsx (React)                                       │
│     └── 154-line Allowed Servers UI block                       │
└─────────────────────────────────────────────────────────────────┘
```

### Problems

- **The abilities plugin owns transport-specific authorization** (MCP server allowlisting). This is a layering violation against Constitution §I (modular boundaries): "Per-ability MCP server selection" is an MCP-specific concept that should live in an MCP-management module, not in a transport-agnostic ability registry.
- **The enforcement layer is silently coupled to a particular MCP adapter package implementation** (`WPBoilerplate\McpServersList` / mcp-adapter filter hooks in `Override_Processor`). If the MCP adapter changes vendor or interface, the abilities plugin must be edited — a maintenance trap.
- **The UI surface (`AbilityForm`'s Allowed Servers block) implies a settings-style user choice, but the enforcement that gives it teeth lives elsewhere** (`Exposure_Controller`, `Override_Processor`). A reader of the UI cannot trace what happens on save → which server requests see what.
- **No clean extensibility path exists** for a future MCP-management plugin to take over without modifying this plugin's core files.

## Target State

```
┌─────────────────────────────────────────────────────────────────┐
│ acrossai-abilities-manager (target — this PR)                   │
│                                                                 │
│   Pure ability registry. Owns:                                  │
│     - ability definitions (slug, label, callback, schemas, etc.) │
│     - REST CRUD                                                 │
│     - admin form UI (with extension slot)                       │
│                                                                 │
│   Public extension contract (FR-010):                           │
│     React hooks:                                                │
│       - acrossai_abilities.form.extra_sections (filter)         │
│       - acrossai_abilities.form.draft_changed (action)          │
│       - acrossai_abilities.form.save_payload (filter)           │
│     PHP hooks:                                                  │
│       - acrossai_abilities_form_settings_registered (action)    │
│       - acrossai_abilities_admin_localize_data (filter)         │
│     JS global: window.acrossaiAbilitiesManager                  │
│                                                                 │
│   NO MCP-specific code, no allowlist enforcement, no mcp_servers │
└─────────────────────────────────────────────────────────────────┘
              ▲                                       ▲
              │ subscribes via hooks                  │ enqueues UI
              │                                       │
┌─────────────────────────────────────────────────────────────────┐
│ acrossai-mcp-manager (future — NOT in this PR)                  │
│                                                                 │
│   Owns:                                                         │
│     - per-ability ↔ MCP-server mapping (its own table / option) │
│     - server-mapping UI injected into AbilityForm via           │
│       acrossai_abilities.form.extra_sections                    │
│     - data injected into window.acrossaiAbilitiesManager via    │
│       acrossai_abilities_admin_localize_data                    │
│     - fail-closed enforcement at the MCP exposure boundary      │
│       (matching the prior fail-closed posture)                  │
└─────────────────────────────────────────────────────────────────┘
```

### Benefits

- **Strict layering**: abilities plugin = registry; MCP manager = transport authorization. Each module testable, replaceable, and reasonable about in isolation (Constitution §I).
- **Transport-agnosticism**: the abilities plugin no longer needs to know about MCP-adapter package interfaces. If the MCP stack changes vendor, only the MCP manager plugin changes.
- **Visible extension contract**: the five hooks form a documented, versioned, public API (FR-010 + contracts/extension-hooks.md). Future MCP-management work — by us, by a fork, by a third party — has a stable surface to build against.
- **Reviewable security posture**: removing enforcement here forces the future MCP manager to re-implement it explicitly, in code reviewers can audit as authorization code (instead of as a column on an unrelated table).

## Migration Phases

### Phase 1: This PR — Remove enforcement, ship extension surface (Estimated: 1–2 days)

**Goal**: Abilities plugin becomes a pure registry. Extension hooks exist with zero subscribers. Pre-launch posture acknowledged as "unconditionally exposed until MCP manager ships."

- **Task 1.1**: All US1 tasks from `tasks.md` (T003–T016) — remove `mcp_servers` from PHP/JS/tests. **PLUS** the four missing-file tasks surfaced by security-tasks-review (T010a–T010d). Per security-review SEC-T-001, the following files MUST be added to US1 explicitly:
  - `includes/Utilities/AcrossAI_Ability_Merger.php` (lines 41, 195)
  - `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (lines 678–681)
  - `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php` (lines 106, 141–164) — **deletes fail-closed enforcement**
  - `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (multiple lines) — **deletes MCP allowlist enforcement; CAREFUL — file also contains Feature 029 `pass_as_tool` code that MUST be preserved**
- **Task 1.1b**: Composer dependency removal (T010e–T010h) — delete `wpboilerplate/wpb-mcp-servers-list` from `composer.json`, delete its wiring block in `includes/Main.php` (lines 303-314), run `composer update` to regenerate the lock + drop vendor entries, verify the JS apiFetch endpoint (`/wpb-mcp-servers-list/v1/servers`) is deleted from `AbilityForm.jsx`. **Constitution amendment** required (T029a) since v1.4.1's §Integration Resilience mandated this package.
- **Task 1.2**: Spec-level acknowledgment of the security-posture change per security-review SEC-T-002 AND the Composer dependency removal — both folded into spec.md's "Architectural Context" section so future readers see authorization relocated (not just deleted-and-forgotten) AND see the package excision rationale.
- **Task 1.3**: All US2 tasks (T017–T020) — add the five extension hooks + harden the contract document.
- **Task 1.4**: Polish phase (T021–T030, including T029a Constitution amendment) — gates + memory updates + CONSTITUTION.md PATCH bump.

**Coexistence**: not applicable within this PR — the future MCP manager plugin does not yet exist. Within this PR, "old (enforcement-in-place) → new (no enforcement, hooks exist)" is an atomic boundary. The PR ships in the new state; the old state is gone at merge time.

**Pre-launch context that makes this safe**: the plugin has not been launched yet. There are no production sites currently relying on the fail-closed MCP allowlist. The interim period (between this PR landing and the future MCP manager shipping) is a development-only state with no users affected.

---

### Phase 2: Future PR — Ship `acrossai-mcp-manager` plugin with re-implemented enforcement (Estimated: separate feature, separate spec-kit cycle)

**Goal**: Restore fail-closed MCP allowlist enforcement, but inside the MCP manager plugin — using the public hooks established in Phase 1.

- **Task 2.1**: New plugin scaffolding (`acrossai-mcp-manager/`) with its own constitution/spec-kit lifecycle.
- **Task 2.2**: Per-ability ↔ MCP-server mapping storage owned by the new plugin (custom table OR options API — design decision for that feature's plan).
- **Task 2.3**: Server-mapping UI rendered into `AbilityForm` via `acrossai_abilities.form.extra_sections`. The UI mirrors the deleted Allowed Servers block in behavior (checkbox list of available MCP servers, null = "all", empty = "none allowed", populated = explicit allowlist).
- **Task 2.4**: Fail-closed enforcement re-implemented at the MCP exposure boundary — exact semantics MUST match the deleted `AcrossAI_Abilities_Exposure_Controller` enforcement (per Phase 1 spec acknowledgment): "row with non-empty allowlist + unknown/empty server_id → EXCLUDE." Default: fail-closed, not fail-open.
- **Task 2.5**: Data injected into `window.acrossaiAbilitiesManager.acrossai_mcp_manager` (namespaced key per ARCH-LOW-2) via `acrossai_abilities_admin_localize_data` filter — provides the React UI with the available-servers list.
- **Task 2.6**: Save piggy-backing via `acrossai_abilities.form.save_payload` filter is **OPTIONAL** — the MCP manager plugin can make its own REST calls instead. Choose based on the consistency-vs-decoupling trade-off when that PR is designed.

**Coexistence in Phase 2**:
- Old pattern (enforcement-in-abilities-plugin) is already gone; nothing to coexist with.
- During the gap between Phase 1 merge and Phase 2 ship: abilities are unconditionally exposed. This is acknowledged in spec.md (per Task 1.2) and acceptable pre-launch (per Phase 1 context).
- When Phase 2 ships, the new MCP manager plugin installs alongside the abilities plugin; activation of MCP manager turns enforcement back on with the new (hooks-based) architecture.

---

## Coexistence Strategy

**Why coexistence matters elsewhere — and why it doesn't here**: most architectural migrations need a coexistence layer because production code is in flight and consumers must not break. Feature 034 is unusual because the plugin is **pre-launch**, so there are no consumers to gracefully migrate. The PR ships in the target state with no backward-compatibility shim, no `@deprecated` markers, no parallel implementation. This is intentional and explicitly endorsed by user direction ("plugin is not yet launched").

**Implications**:
- **No deprecation cycle** for the deleted `mcp_servers` column or REST arg — the spec's FR-002 explicitly forbids leaving deprecation shims.
- **No bridge/adapter** between the old "abilities plugin owns enforcement" and the new "MCP manager plugin owns enforcement" — the bridge would be useless without consumers crossing it.
- **Future MCP manager plugin starts greenfield** — no migration data, no compatibility constraints from this plugin's prior enforcement.

If the plugin had been launched, this migration would require a 3-phase plan (parallel-emission of hooks while old enforcement still runs → deprecation announcement → removal). The greenfield context lets us collapse to a single phase + a follow-on plugin.

## Rollback Plan

**Within Phase 1 (this PR)**:
- The PR is split per user-story in commit cadence (per tasks.md Notes: "do NOT bundle US1 and US2 into a single commit"). If US2's hooks misbehave after merge, revert just the US2 commits — US1's removal stands.
- If US1's removal breaks something critical (PHPStan / PHPUnit / Plugin Check), revert the PR entirely. Dev installs that already applied the PR drop their abilities table manually and reactivate from the pre-PR state.

**Between Phase 1 and Phase 2 (interim)**:
- If a need for MCP allowlist enforcement is discovered before Phase 2 is ready, the workaround is either (a) deploy the future MCP manager plugin in a partial state with just the enforcement hook + a static config, or (b) re-introduce enforcement in this plugin via a hotfix that re-adds the `mcp_servers` column (FRESH installs only, no migration). Both are escape hatches; neither is the planned path.

**Phase 2 rollback**:
- Out of scope for this document — handled by that feature's own spec-kit lifecycle.

## Success Criteria

- [ ] T010a, T010b, T010c, T010d added to `tasks.md` covering the four files missed by the original inventory.
- [ ] T010e, T010f, T010g, T010h added to `tasks.md` covering Composer dependency removal.
- [ ] Spec.md "Security posture change" + "Composer dependency removal" notes added per security-review SEC-T-002 + user-directed package removal.
- [ ] T029a added covering Constitution amendment (retract §Integration Resilience wpb-mcp-servers-list mandate; PATCH bump; sync-impact report).
- [ ] All US1 tasks pass T016 grep (revised per SEC-T-004 to include `admin/`, `uninstall.php`, `composer.json`, AND McpServersList / wpb-mcp-servers-list / wpb_mcp_servers_list patterns since the package is fully removed).
- [ ] `composer show wpboilerplate/wpb-mcp-servers-list` returns "package not found" after `composer update`.
- [ ] All US2 hooks fire correctly with zero subscribers (FR-009).
- [ ] PHPStan level 8, PHPCS, Plugin Check, Jest, PHPUnit all green.
- [ ] Manual smoke test (quickstart.md) confirms the abilities plugin functions as a pure registry: no Allowed Servers UI, no enforcement at the exposure boundary, no allowlist injection in Override_Processor.
- [ ] `docs/memory/INDEX.md` updated: `DEC-MCP-SERVER-SANITIZE` → Superseded; new entry for "ARCH-INVERSION-034" recording the move "abilities plugin no longer owns MCP authorization; future MCP manager plugin via hooks established by Feature 034."
- [ ] `docs/memory/WORKLOG.md` Phase-2-readiness note: future MCP manager plugin's spec MUST cite the fail-closed semantics from Feature 034's deleted `Exposure_Controller` so the re-implementation matches the prior posture.

---

```text
Refactor Tasks:

[Refactor Task]
Title: Make the MCP-enforcement-relocation architectural inversion explicit in spec.md
Reason: Feature 034's central architectural move is relocating MCP-server allowlist authorization out of the abilities plugin (where it lives in Exposure_Controller + Override_Processor as fail-closed enforcement) and into a future, separately-shipped MCP manager plugin. Currently spec.md frames this as "remove Allowed Servers UI" + "input surface reduction" — UI-focused language that hides the layering inversion. A future reviewer auditing the diff will see authorization code being deleted without spec-level rationale, which violates Constitution §IV ("documented justification" for any apparent principle deviation) and obscures the architectural intent.
Scope: spec.md — add a one-paragraph "Architectural inversion" note (and a sibling "Security posture change" note per security-tasks-review SEC-T-002) under Background, before Functional Requirements. Single-file edit, ~150 words.
Priority: P1
Suggested Fix: Add the note exactly as described in security-tasks-review SEC-T-002 recommendation #1 — explicitly state that fail-closed enforcement is being deleted, name the two PHP classes whose enforcement code is being removed, acknowledge the interim "unconditionally exposed" posture as pre-launch-only, and pin the requirement that the future MCP manager's re-implementation be fail-closed by default to match prior posture.

[Refactor Task]
Title: Add explicit US1 tasks for the four enforcement/data-touch files missed by the original inventory
Reason: Per security-tasks-review SEC-T-001 (HIGH), four production PHP files reference `mcp_servers` but no task covers them: AcrossAI_Ability_Merger, AcrossAI_Abilities_Query, AcrossAI_Abilities_Exposure_Controller, AcrossAI_Ability_Override_Processor. Two of those (Exposure_Controller, Override_Processor) implement the very authorization layer being relocated (per the refactor task above). The current task list will fail T016's own grep validation and produce PHPStan level 8 failures on the typed-property removal. Architecturally, the absence of these tasks means the "abilities plugin becomes a pure registry" outcome is not actually achieved — the enforcement code survives in two files even after T005/T006 land.
Scope: tasks.md US1 implementation block — insert T010a–T010d between T010 (Formatter) and T011 (REST audit). Each new task is single-file with a clear line range and (for Exposure_Controller / Override_Processor) a careful "preserve Feature 029 pass_as_tool code" guardrail.
Priority: P0
Suggested Fix: Use the four task drafts in security-tasks-review SEC-T-001 recommendation verbatim. P0 (rather than P1) because the current task list will demonstrably fail its own checkpoint without these — this is a Constitution §II "MUST pass" gate violation (PHPStan/Plugin Check), not just a quality refinement.

[Refactor Task]
Title: Document the "form extension-slot triad" as a reusable pattern
Reason: The triplet introduced by US2 (`extra_sections` filter for UI injection + `draft_changed` action for state observation + `save_payload` filter for REST mutation) is a generalizable shape for extending any React-form-with-REST-save in this codebase. Future features that need extension surfaces on other forms (settings, logger filters, addons pages) could follow the same triad. Currently this is only documented in `contracts/extension-hooks.md` as a per-feature contract; promoting it to a reusable pattern entry in ARCHITECTURE.md prevents future re-invention.
Scope: docs/memory/ARCHITECTURE.md — new pattern entry after this feature lands (post-merge, via /speckit-memory-md-capture-from-diff). Name suggestion: `PATTERN-FORM-EXTENSION-TRIAD`.
Priority: P3
Suggested Fix: After implementation, capture the pattern with three bullet points (one per hook), citing Feature 034 as the originating use case and noting the dot-notation JS namespace convention this feature establishes. Opportunistic — only do this when the pattern is reused for a second form, otherwise it stays speculative.
```


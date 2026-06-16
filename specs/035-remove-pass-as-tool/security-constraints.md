---
document_type: security-review
review_type: plan
assessment_date: 2026-06-16
codebase_analyzed: acrossai-abilities-manager (Feature 035 plan stage)
total_files_analyzed: 6
total_findings: 5
overall_risk: LOW
critical_count: 0
high_count: 0
medium_count: 0
low_count: 1
informational_count: 4
owasp_categories: [A04]
cwe_ids: [CWE-665, CWE-1188]
field_summaries:
  document_type: "Always 'security-review'. Allows indexers to skip non-review documents."
  review_type: "Which command generated this document: audit, branch, staged, plan, tasks, or followup."
  assessment_date: "ISO 8601 date the review was performed (YYYY-MM-DD)."
  overall_risk: "Highest severity tier with active findings (CRITICAL, HIGH, MODERATE, LOW, INFORMATIONAL)."
  critical_count: "Number of Critical findings (CVSS 9.0-10.0)."
  high_count: "Number of High findings (CVSS 7.0-8.9)."
  medium_count: "Number of Medium findings (CVSS 4.0-6.9)."
  low_count: "Number of Low findings (CVSS 0.1-3.9)."
  informational_count: "Number of Informational findings."
  owasp_categories: "OWASP Top 10 2025 categories (A01-A10) that have at least one finding."
  cwe_ids: "CWE identifiers referenced in this document."
  finding_id: "Unique finding identifier (SEC-NNN) for cross-referencing and task linkage."
  location: "File path and line number of the vulnerable code (path/to/file.ext:line)."
  owasp_category: "OWASP Top 10 2025 category for this finding (AXX:2025-Name)."
  cwe: "Common Weakness Enumeration identifier with short name (CWE-NNN: Name)."
  cvss_score: "CVSS v3.1 base score (0.0-10.0). 9.0+=Critical, 7.0-8.9=High, 4.0-6.9=Medium, 0.1-3.9=Low."
  spec_kit_task: "Spec-Kit task ID for backlog tracking and remediation follow-up (TASK-SEC-NNN)."
---

# Security Review — Plan Stage (Feature 035)

## Executive Summary

Feature 035 is a **pure removal** of the `pass_as_tool` capability introduced by Feature 029. The
plan deletes:

- A BerlinDB tinyint column (`pass_as_tool`) and its schema/row/query plumbing.
- A runtime hook (`AcrossAI_Ability_Override_Processor::inject_mcp_tools` at `mcp_adapter_init`
  P20) that used PHP **Reflection** to write into the private
  `McpServer::$component_registry` field of an external dependency (mcp-adapter).
- The supporting sanitizer / formatter / merger entries.
- The React admin column + ability-form section.
- All dedicated PHPUnit suites.

**Net-security verdict**: **IMPROVEMENT.** The most consequential security boundary change is
the elimination of cross-cutting Reflection-based writes into a third-party's private internal
state. No new attack surface, no new authentication/authorization path, no new data sink is
introduced.

**Risk profile**: 0 Critical, 0 High, 0 Medium, **1 Low**, 4 Informational. The single Low is
operational/test-coverage: the spec mandates silent ignore of obsolete inbound REST keys
(FR-006), which is correct but should be guarded by an explicit assertion test so a future
implementation drift cannot quietly re-add validation behavior that breaks pre-launch clients.

**Memory hub consistency**: The plan respects every active decision and constraint surfaced by
the synthesis (`memory-synthesis.md`). Two intentional state transitions —
`DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` supersession and `DEC-MCP-INJECT-REFLECTION-PATTERN`
forward-pointer annotation — are correctly framed as supersession rather than constitutional
violation. `ARCH-ADV-001` (Override Processor `boot()` deviation) narrows its surface; the
deviation remains valid for the surviving hooks.

## Plan Artifacts Reviewed

| Artifact | Path | Purpose |
|----------|------|---------|
| Specification | `specs/035-remove-pass-as-tool/spec.md` | Behavioral contract, FR/SC, scope, assumptions, edge cases, clarifications |
| Plan | `specs/035-remove-pass-as-tool/plan.md` | Implementation approach, constitution check, project structure, risks |
| Memory synthesis | `specs/035-remove-pass-as-tool/memory-synthesis.md` | Selected decisions, constraints, accepted deviations, related historical lessons, conflict warnings |
| Reference planning doc | `docs/planning/035-remove-pass-as-tool.md` | File-and-line inventory of every change (working scratchpad) |
| Constitution | `.specify/memory/CONSTITUTION.md` | v1.4.7 per sync-impact reports (footer stale at v1.4.5 — pre-existing, out of scope) |
| Memory hub index | `docs/memory/INDEX.md` | Active decisions, architecture constraints, bug patterns, accepted deviations |

`research.md`, `data-model.md`, `quickstart.md`, and `contracts/` are intentionally not produced
for this feature (per plan: pure removal, no new model, no new contract).

## Trust Boundaries & Threat Model

### Boundary changes

| # | Boundary | Direction | Before | After | Net |
|---|----------|-----------|--------|-------|-----|
| B1 | This plugin → `McpServer::$component_registry` (private) via Reflection | Out | Cross-cutting writes at `mcp_adapter_init` P20 | **Removed.** No code in this plugin touches mcp-adapter private state. | **Improvement** |
| B2 | REST client → `pass_as_tool` field on create/update | In | Sanitized + persisted + read back | **Silent ignore** (default WP REST unknown-param semantics). Field not in sanitizer, not in DB column, not in response shape. | Neutral (default-secure) |
| B3 | MCP `tools/list` → abilities registry | Out | This plugin could inject opted-in slugs | **No contribution.** Each MCP server owns its tool list. | Neutral / improvement (clearer ownership) |

### Surviving authorization gates (preserved by plan)

- **SEC-04** strict-type comparison (`=== true` / `=== false`) — used by remaining override
  processor methods (`inject_override_args`, `unregister_blocked_abilities`). Unaffected.
- **SEC-03** per-site table prefix (`AcrossAI_Abilities_Table::$global = false`) — unaffected.
- **SEC-01** slug sanitize at REST entry — unaffected (the removed field is a tri-state bool,
  not a slug).
- **DEC-PERM-CB** AC-rule-gated `permission_callback` injection — lives in
  `inject_override_args()`, which stays.
- **WP_Ability::check_permissions()** as the authoritative permission gate when AC rules are
  absent — pattern survives via `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` lesson preservation.

### Retired authorization surface

- `AcrossAI_Ability_Override_Processor::user_has_ability_access()` — private helper, only
  caller was `inject_mcp_tools()`. Plan gates deletion on a fresh grep (S-035-3).

## Vulnerability Findings

### SEC-035-001 — Inbound REST writes with obsolete `pass_as_tool` key rely on framework default behavior (LOW)

- **Finding ID**: SEC-035-001
- **Location**: `includes/Utilities/AcrossAI_Abilities_Sanitizer.php:285` (post-removal),
  `includes/Modules/Abilities/Rest/*` (REST routes for ability create/update — plan does not
  modify these)
- **OWASP Category**: A04:2025 - Insecure Design
- **CWE**: CWE-1188: Initialization of a Resource with an Insecure Default
- **CVSS Score**: 2.7 (Low) — `AV:N/AC:L/PR:H/UI:N/S:U/C:N/I:L/A:N`
- **Spec-Kit Task**: TASK-SEC-035-001 (to be added by `/speckit-tasks`)

**Description**: Spec FR-006 mandates that REST endpoints accepting ability updates **silently
ignore** any `pass_as_tool` value supplied by a client (no validation error, no persistence,
no echo). Implementation-wise this relies on WordPress's REST default behavior for unknown
params: the framework drops fields not listed in `args`, the sanitizer's
`$tri_state_fields` array no longer contains the key, and the DB layer has no column to write
to. Silent ignore is the correct choice for this pre-launch transition.

**Risk**: A future implementation regression could re-add `pass_as_tool` to the sanitizer's
`$tri_state_fields` (or to a route's `args` schema) without anyone noticing, because no test
asserts the silent-ignore semantics. The field would then be read but discarded by the DB
layer — a silent failure mode that violates the principle of "fail-loud, fail-early."

**Recommended remediation**: Add a PHPUnit (or integration) test under
`tests/phpunit/Modules/Abilities/Rest/` that:

1. Submits a create/update payload containing `pass_as_tool: true`.
2. Asserts the request returns 200/201 with no `pass_as_tool` key in the response.
3. Reads the row back and asserts no `pass_as_tool` value (and no equivalent metadata key) is
   stored.
4. Runs against the REST controller surface (not just the sanitizer in isolation), so any
   accidental future re-introduction of the field in REST `args` is caught.

This test should be **added during `/speckit-implement`**, not deleted along with the dedicated
`pass_as_tool` test suites. Place it under `tests/phpunit/Modules/Abilities/Rest/` (a new file
named e.g. `AcrossAI_Abilities_PassAsTool_Removal_Test.php`).

### SEC-035-002 — Manual database migration on multisite requires per-blog procedure (LOW → INFORMATIONAL)

- **Finding ID**: SEC-035-002
- **Location**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php` (per-site
  table due to SEC-03 `$global = false`)
- **OWASP Category**: A04:2025 - Insecure Design
- **CWE**: CWE-665: Improper Initialization
- **CVSS Score**: 0.0 (Informational) — `AV:L/AC:H/PR:H/UI:R/S:U/C:N/I:N/A:N`
- **Spec-Kit Task**: TASK-SEC-035-002 (to be added by `/speckit-tasks`)

**Description**: Clarifications Session 2026-06-16 settled on a **manual** migration:
deactivate → drop abilities table → reactivate. Because `AcrossAI_Abilities_Table::$global =
false` (SEC-03 mandates per-site multisite isolation), each subsite in a multisite install has
its own physical table. Running the manual procedure once on the network does **not** clean
non-primary blogs' tables.

**Risk**: Vestigial `pass_as_tool` columns persist on subsite tables indefinitely. **This is
not a security risk** — no production code path reads the column after the plan lands; the
sanitizer / formatter / merger / query layer all stop referencing it. The leftover bytes are
inert. Data isolation between sites is preserved (SEC-03 unchanged). Hence Informational rather
than Low.

**Recommended remediation**: Add a multisite note to the post-implementation quickstart /
release notes pointing developers at the WP-CLI invocation
`wp --url=<blog-url> db query "DROP TABLE {$wpdb->prefix}acrossai_abilities;"` for each blog
that needs a clean schema, or scripted via `wp site list --field=url | xargs ...`. This is
operational guidance, not a code change.

### SEC-035-003 — `user_has_ability_access()` helper deletion depends on grep verification (INFORMATIONAL)

- **Finding ID**: SEC-035-003
- **Location**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php:525–546`
- **OWASP Category**: N/A (process finding, not a vulnerability class)
- **CWE**: N/A
- **CVSS Score**: 0.0 (Informational)
- **Spec-Kit Task**: TASK-SEC-035-003

**Description**: The private helper `user_has_ability_access()` exists today solely to support
`inject_mcp_tools()`. The plan gates its deletion on a fresh
`grep -rEn 'user_has_ability_access' includes/ src/ tests/` before removal. The helper's
internal semantics — **fail-open** AC-rule lookup with `user_has_access()` fallback — would be
unsafe if called from any new code without the partner `check_permissions()` gate that
`inject_mcp_tools()` provided (the lesson encoded in `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS`).

**Risk**: Two failure modes are possible:

1. The grep returns hits beyond `inject_mcp_tools()` (currently none expected), in which case
   deletion would break unrelated callers. Plan correctly says: keep the helper if any other
   caller exists.
2. A reviewer skips the grep, deletes the helper, and a future task that intended to reuse the
   "fail-open AC + authoritative permission_callback fallback" pattern silently grows a new
   permission-bypass path because the canonical helper is gone.

**Recommended remediation**:

- Tasks.md must explicitly enumerate the grep step **before** the deletion step (not just rely
  on the plan-stage risk note).
- `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` BUGS.md annotation must explicitly say: "AC rules
  are fail-open in absence; always pair with `WP_Ability::check_permissions()` for an
  authoritative gate. Helper `user_has_ability_access()` was removed alongside its only caller;
  re-author the equivalent pattern inline at any new call site rather than restoring the
  helper, so the partner gate cannot be forgotten."

### SEC-035-004 — `ARCH-ADV-001` accepted deviation surface narrows (INFORMATIONAL)

- **Finding ID**: SEC-035-004
- **Location**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` — `boot()`
  docblock + body
- **OWASP Category**: N/A
- **CWE**: N/A
- **CVSS Score**: 0.0 (Informational)
- **Spec-Kit Task**: TASK-SEC-035-004

**Description**: `boot()` is the documented exception to the constitutional Boot Flow Rule
(`AC-HOOKS-MAIN` — "only Main.php wires hooks") under accepted deviation `ARCH-ADV-001`.
Today the method wires three hooks directly: `wp_register_ability_args`,
`wp_abilities_api_init`, and `mcp_adapter_init`. The plan removes the third. The deviation
**remains valid** for the surviving two.

**Risk**: If the docblock is not updated to reflect the narrower surface, a future maintainer
might assume the deviation still permits an `mcp_adapter_init` hook and add one back — or,
worse, assume the deviation has been retired and incorrectly remove the surviving two hooks
as well. Both are documentation-induced misreadings, not direct vulnerabilities, but both have
security-adjacent consequences (Boot Flow Rule exists to make hook registration
auditable from a single file).

**Recommended remediation**: Update the `boot()` docblock as part of the same task that
removes the `add_action()` line. The docblock should enumerate the two surviving hooks and
restate that the deviation continues to apply to PATH-A/B conditional wiring.

### SEC-035-005 — Net-security improvement: Reflection-based reach into third-party private state removed (INFORMATIONAL)

- **Finding ID**: SEC-035-005
- **Location**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php:507–522`
  (the Reflection block inside `inject_mcp_tools()`)
- **OWASP Category**: N/A (improvement, not a vulnerability)
- **CWE**: N/A
- **CVSS Score**: 0.0 (Informational, positive)
- **Spec-Kit Task**: None

**Description**: The deleted method used `\ReflectionClass` + `\ReflectionProperty::
setAccessible(true)` to reach into `McpServer::$component_registry` — a private field on an
externally-owned class — and call `register_tools()` on the resulting object. This pattern,
while documented in `DEC-MCP-INJECT-REFLECTION-PATTERN`, sits outside the public API of
mcp-adapter and could break silently on any mcp-adapter upgrade that renames the field,
changes its type, or reorganizes the registry. Removing the reach **eliminates** that
upgrade-fragility class entirely from this plugin.

**Net effect**: The plugin's dependency on mcp-adapter internals collapses to the public hook
`mcp_adapter_init` (still listened to for nothing post-Feature-035 — the entire listener is
removed). Future mcp-adapter releases can change `$component_registry` freely without breaking
this plugin.

**Recommended action**: Record this as the primary security rationale in the new
`DEC-PASS-AS-TOOL-REMOVED` decision entry (alongside the architectural inversion rationale
inherited from Feature 034).

## Confirmed Secure Patterns

The plan preserves the following secure patterns from the project memory hub:

1. **SEC-03 multisite isolation** — `$global = false` retained. The manual migration procedure
   acknowledges per-blog scoping (SEC-035-002 documentation note).
2. **SEC-04 strict-type comparison** — `=== true` / `=== false` checks on tri-state fields
   remain in the surviving override-processor methods.
3. **DEC-PERM-CB** — AC-rule-gated `permission_callback` injection lives in
   `inject_override_args()`, which is preserved by the plan.
4. **`BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` lesson** — promoted from a specific bug entry to
   a durable lesson via the planned BUGS.md annotation. The "AC fail-open + ability's own
   `check_permissions()` as authoritative gate" pattern survives the trigger's deletion.
5. **`BUG-BERLINDDB-QUERY-PRIVATE-CTOR` lesson** — unaffected by this feature; plan does not
   introduce any `new AcrossAI_Abilities_Query()` calls (deleted methods are all static).
6. **Default-safe WP REST unknown-param handling** — silent ignore is achieved without writing
   any new validation branch; the framework's default is the secure default.
7. **PATH-A/B conditional wiring** — Override Processor's Manager-REST early-return is
   preserved; nothing about the request-routing security model changes.

## Action Plan & Next Steps

### Durable Memory Preservation

**Verdict**: No systemic new vulnerabilities or reusable security patterns surfaced by this
review. The findings are operational refinements of patterns already captured in memory
(`BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS`, `DEC-MCP-INJECT-REFLECTION-PATTERN`,
`ARCH-ADV-001`). **No `/speckit-memory-md-capture` execution required at plan stage.** The
durable memory updates (DECISIONS.md, BUGS.md status changes) will happen at
`/speckit-implement` time via `/speckit-memory-md-capture-from-diff`.

### Remediation Planning

No Critical or High findings. **`/speckit-security-review-followup` is not required.** The
five Informational/Low findings translate cleanly into tasks for `/speckit-tasks`:

| Finding | Task ID | Severity | Required action |
|---------|---------|----------|-----------------|
| SEC-035-001 | TASK-SEC-035-001 | LOW | Add PHPUnit/integration test asserting silent-ignore of inbound `pass_as_tool` REST writes |
| SEC-035-002 | TASK-SEC-035-002 | INFO | Add multisite per-blog procedure note to release/quickstart documentation |
| SEC-035-003 | TASK-SEC-035-003 | INFO | Encode grep-before-delete step + annotate `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` with "re-author inline, don't restore helper" guidance |
| SEC-035-004 | TASK-SEC-035-004 | INFO | Update `boot()` docblock to reflect narrowed deviation surface (2 hooks) |
| SEC-035-005 | (informational only) | INFO | Record Reflection-removal as primary security rationale in new `DEC-PASS-AS-TOOL-REMOVED` entry |

### Recommendation

**Proceed to `/speckit-tasks`.** Plan is implementable without security ambiguity. The five
findings above must appear as explicit tasks (or be lifted into existing tasks) in `tasks.md`.

---

## Memory Hub INDEX.md Row

Proposed routing row — paste under the security-review section of `docs/memory/INDEX.md`
(create the section if it does not yet exist):

```text
| specs/035-remove-pass-as-tool/security-constraints.md | plan | 2026-06-16 | LOW | C:0 H:0 M:0 L:1 | A04 |
```

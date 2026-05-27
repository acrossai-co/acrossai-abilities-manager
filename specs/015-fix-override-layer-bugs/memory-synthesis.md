# Memory Synthesis

## Current Scope
Fix 6 discrete bugs in the non-db ability override layer: registry normalisation (`AcrossAI_Ability_Merger`), "Plugin declares:" hints and section order (`AbilityForm.jsx`), draft seeding (`store/index.js`), stale BerlinDB slug-cache on first save (`AcrossAI_Abilities_Query`, `AcrossAI_Abilities_Write_Controller`), and DB schema defaults for `callback_type` / `status` (Schema, Table, Query). Affected modules: Utilities (Merger), Abilities DB layer, Abilities REST Write controller, React store, React form.

## Relevant Decisions
- **DEC-DB-WRITE-BOUNDARY-GUARD** (Reason: `save_override()` has SEC-002 source-injection guard; return-type refactor must NOT remove it, Status: Active, Source: DECISIONS.md)
- **DEC-BY-SOURCE-AUTHZ** (Reason: `get_ability_by_id()` is auth-free; callers gate it — this pattern must continue, Status: Active, Source: DECISIONS.md)
- **DEC-JSON-SIZE-GUARD** (Reason: `mcp_servers` and `callback_config` are JSON fields; 64 KB guard in save paths must stay, Status: Active, Source: DECISIONS.md)
- **DEC-ABILITIES-DUAL-MODE-LIST** (Reason: non-db path goes through `normalize_registry()` + `merge()` — changes to Merger affect all list/single endpoints, Status: Active, Source: DECISIONS.md)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: AbilityForm.jsx is the user-prototype-blessed UI; section reorder is in-scope, Status: Active, Source: DECISIONS.md)

## Active Architecture Constraints
- **AC-HOOKS-MAIN** (Reason: no new hooks are introduced; not at risk, Source: CONSTITUTION.md §I)
- **AC-ENQUEUE-ADMIN** (Reason: no asset changes; not at risk, Source: CONSTITUTION.md §I)
- **ARCH-UNIFIED-ABILITIES-STORAGE** (Reason: override rows live in the unified abilities table; Bug 5 schema change affects that table, Source: ARCHITECTURE.md)
- **CON-001 / CON-002** (Reason: `AcrossAI_Ability_Override_Processor` must not be touched; REST response shape unchanged, Source: spec.md)

## Accepted Deviations
- **ARCH-ADV-001** (Reason: boot() conditional hook pattern; unrelated to this feature, Status: Accepted-Deviation)

## Relevant Security Constraints
- **SEC-002 / DEC-DB-WRITE-BOUNDARY-GUARD**: `save_override()` strips `source='db'` unconditionally. The return-type refactor (Bug 4) must keep this guard intact and in the same position relative to `prepare_fields_for_write()`.
- **DEC-BY-SOURCE-AUTHZ**: `get_ability_by_id()` carries no auth check. Correct — the REST `permission_callback` is the gate. The plan must not add auth checks inside the DB helper.
- **SEC-001**: `sanitize_ability_slug()` is already called at the top of `save_override()`. Must not be moved or removed.

## Related Historical Lessons
- **BUG-FLAT-ARGS-PATH** (Reason: the write path had the same field-path mismatch for MCP fields; Bug 1 is the read-side equivalent in `normalize_registry()`)
- **BUG-ABILITYFORM-JSX-MIXED-DEPTHS** (Reason: Bugs 2 and 6 both modify `AbilityForm.jsx`; read actual tab depths before each str_replace — do NOT assume uniform indentation)
- **BUG-PHPCBF-TABS** (Reason: Python str_replace on PHP files must use `\t`; all PHP Bug 4/5 edits via Python scripts must use tab indentation)
- **BUG-PARTIAL-HOOK-FIELDS** (Reason: after changing `save_override()` return type, verify no `after_save` hook in the Write controller's non-db path receives a partial `$fields` instead of the row — confirmed: no after_save hook in that path currently)
- **BUG-PHPSTAN-SILENT-PASS** (Reason: exit 0 + no output = clean pass; verify by checking exit code explicitly)

## Conflict Warnings
- **SOFT CONFLICT — CON-009 vs existing `create_ability` call**: The spec (FR-018 / CON-009) says `get_ability_by_id()` must be `private` and called only from `save_override()`. However, `get_ability_by_id()` is currently **public** (line 152) and legitimately called from `create_ability()` in the Write Controller (line 248 — db-source path, no cache issue there). Making it private would break `create_ability`. **Resolution**: keep `get_ability_by_id()` public but annotate it as `@internal cache-bypass`; relax CON-009 to apply only to the override save path. The Write Controller's non-db `save_override` path must use the returned row directly (removing `get_override_by_slug()` from that path). This fully resolves the stale-cache bug without requiring a visibility change.
- **SOFT CONFLICT — error code in Write Controller**: Current error code in the non-db save path is `'rest_update_failed'` (status 500). FR-019 / SC-015-C3 requires `'save_override_failed'`. This is a breaking change for any consumer checking the error code. Spec requires it; proceed — no production consumers.

## Retrieval Notes
- Index entries considered: DEC-DB-WRITE-BOUNDARY-GUARD, DEC-BY-SOURCE-AUTHZ, DEC-JSON-SIZE-GUARD, DEC-ABILITIES-DUAL-MODE-LIST, DEC-DESIGN-OVERRIDES-DATAVIEWS, BUG-FLAT-ARGS-PATH, BUG-ABILITYFORM-JSX-MIXED-DEPTHS, BUG-PHPCBF-TABS, BUG-PARTIAL-HOOK-FIELDS, BUG-PHPSTAN-SILENT-PASS (10 of 20 max)
- Source sections read: DECISIONS.md (lines ~830-852), BUGS.md (targeted grep), ARCHITECTURE.md (targeted grep)
- Direct code reads: `AcrossAI_Abilities_Query.php` (lines 152-335), `AcrossAI_Abilities_Write_Controller.php` (lines 220-360), `AcrossAI_Ability_Merger.php` (lines 27-200), `AcrossAI_Ability_Merger.php` overridable_fields (lines 27-42), `store/index.js` (lines 88-115), Schema.php / Table.php (targeted grep)
- Budget: within limits; no full-file memory dumps

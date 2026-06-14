# Memory Synthesis

## Current Scope

Feature 034 removes the per-ability "Allowed Servers" (`mcp_servers`) surface from `acrossai-abilities-manager` end-to-end (PHP schema/row/sanitizer/REST/formatter, React form block, Redux `OVERRIDABLE_FIELDS`, related tests, plus the previously-missing Merger / Query / Exposure_Controller / Override_Processor consumers identified in security-tasks-review) and replaces it with five MCP-agnostic extension hooks (3 JS via `@wordpress/hooks`, 2 PHP). **Composer dependency removed**: `wpboilerplate/wpb-mcp-servers-list` (the source of the MCP servers list endpoint feeding the deleted UI) — including its wiring in `includes/Main.php`, its JS consumer in `AbilityForm.jsx`, and the Constitution mandate (v1.4.1) that required it. **No upgrade migration is shipped** — the plugin has not been launched yet; removing the column from the schema definition is sufficient for fresh installs, and dev installs with stale data are handled by manually dropping the abilities table and reactivating. Affected modules: `Modules/Abilities` (schema, row, query, REST, formatter, sanitizer wrappers, exposure controller, override processor), `Utilities` (`AcrossAI_Sanitizer`, `AcrossAI_Abilities_Sanitizer`, formatter, merger), `admin/Main` (enqueue + localize), `includes/Main` (delete McpServersList wiring), `src/js/abilities/components/AbilityForm.jsx`, `src/js/abilities/store/index.js`, `tests/jest/abilities`, `composer.json`, `.specify/memory/CONSTITUTION.md`, `docs/memory/{DECISIONS,ARCHITECTURE,INDEX}.md`.

## Relevant Decisions

- **DEC-MCP-SERVER-SANITIZE** (Reason: this feature deletes `sanitize_mcp_servers_array()` and its constants — the decision is being **superseded**. Plan must record the state transition. Status: Active → will become Superseded by this PR. Source: DECISIONS.md)
- **DEC-MCP-CAPABILITY-FILTER-WARN** (Reason: this feature removes the `wpboilerplate/wpb-mcp-servers-list` package entirely, including the wiring point in `Main.php` that hosts the `wpb_mcp_servers_list_rest_capability` filter warning. The decision becomes moot — there is no longer a wiring point to warn about. Plan must record this state transition alongside DEC-MCP-SERVER-SANITIZE. Status: Active → will become Superseded by this PR. Source: DECISIONS.md)
- **Constitution §Integration Resilience (v1.4.1)** (Reason: declares "MCP server listing MUST use the `wpboilerplate/wpb-mcp-servers-list` Composer package". This feature retracts that mandate; Constitution PATCH amendment required with sync-impact report. Source: CONSTITUTION.md §Architecture & UI Standards > Integration Resilience)
- **DEC-ABILITIES-LIST-UX-025** (Reason: pre-existing memory explicitly pins `window.acrossaiAbilitiesManager` (NOT `window.acrossaiAbilities`) as the localize global — directly confirms FR-008 / spec Q3 answer; no new contract being invented. Status: Active. Source: DECISIONS.md)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: AbilityForm is an accepted DataForm-mandate deviation per design prototype; the new `extra_sections` slot does NOT need to be implemented via DataForm. Status: Active. Source: DECISIONS.md)
- **DEC-PROTECTED-SLUGS-PATTERN** (Reason: precedent for centralized extensibility via filter with default; the same posture applies to our new filters. Status: Active. Source: DECISIONS.md)
- **DEC-NAMESPACE-CONVENTION** (Reason: any PHP touched/added in this feature must follow `AcrossAI_Abilities_Manager\Includes\...` underscore convention. Status: Active. Source: DECISIONS.md)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason: only `Main.php` registers hooks via the Loader using the variable-first pattern. The new `do_action` callsites (`acrossai_abilities_form_settings_registered`) and `apply_filters` callsites (`acrossai_abilities_admin_localize_data`) FIRE from inside admin code — they don't register hooks themselves, so AC-HOOKS-MAIN remains satisfied. Source: CONSTITUTION.md §I)
- **AC-ENQUEUE-ADMIN** (Reason: `wp_enqueue_script` lives only in `admin/Main::enqueue_scripts()`. FR-007's "fire after the abilities admin script bundle is enqueued" must place the `do_action` inside that method, after the relevant `wp_enqueue_script` call. Source: CONSTITUTION.md §I)
- **ARCH-ABILITYFORM-SECTION-ORDER** (Reason: documented section order 1–7 lists "MCP" at position 3. Removing the Allowed Servers block alters that order. Memory entry must be updated post-implementation via `/speckit-memory-md-capture-from-diff`. Source: ARCHITECTURE.md)
- **ARCH-UNIFIED-ABILITIES-STORAGE** (Reason: the unified abilities table is owned by `Modules/Abilities`; the schema column removal lives in `AcrossAI_Abilities_Schema`. No migration code shipped (plugin not yet launched). Source: ARCHITECTURE.md)
- **PATTERN-PROTECTED-SLUGS-JS-LOCALIZE** (Reason: existing pattern says PHP-managed data is exposed to JS via `window.acrossaiAbilitiesManager`. Reinforces FR-008 contract. Source: ARCHITECTURE.md)

## Accepted Deviations

- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: AbilityForm is the active DataForm exception; the new extra_sections slot is non-DataForm by design. Status: Accepted-Deviation. Source: DECISIONS.md)
- **DEC-FEATURE-027-NO-TESTS** (Reason: precedent for shipping a feature without new unit tests, accepted as deviation from Constitution §VII. FR-014's "no Jest test for hook pass-through" rests on the same posture — see Conflict Warnings. Status: Accepted-Deviation. Source: DECISIONS.md)

## Relevant Security Constraints

- **SEC-02** (Reason: `before_save` hook fires on sanitized `$fields`; deleting `sanitize_mcp_servers()` from the sanitizer chain reduces the input surface — verify no other code path still expects the key. Source: security-constraints.md)
- **SEC-03 (NOT in scope this feature)** (Reason: would have governed per-site migration execution, but no migration code is shipped. Listed for completeness — applies to any future migration touching this table. Source: security-constraints.md)

## Related Historical Lessons

- **BUG-WP-LOCALIZE-SCRIPT-RENDER** — `wp_localize_script()` from `render()` fires too late; canonical pattern is `wp_add_inline_script('before')` in `enqueue_scripts()`. The planning doc snippet incorrectly used `wp_localize_script`; the spec's Q3 correction (`wp_add_inline_script` in admin/Main.php:254-256) aligns with this lesson.
- **BUG-ABILITYFORM-PANEL-PREMATURE-CLOSE** + **BUG-ABILITYFORM-JSX-MIXED-DEPTHS** + **DEC-HACTIONS-BUTTON-DEPTH** — deleting the 154-line UI block (AbilityForm.jsx lines 1268–1422) is the exact JSX-edit hazard this trio warns about. Plan should specify: manual edit only (no script-based str_replace), and verify `.panel` closing `</div>` placement after edit.
- **BUG-BERLINDB-V3-DOUBLE-PRIMARY** / **BUG-BERLINDB-V3-TIMESTAMP-QUOTING** — BerlinDB v3 schema gotchas. Not in scope this feature (no migration code shipped); listed for completeness if a future feature adds migrations on this table.
- Feature 028 (2026-06-10) — BerlinDB 3.0 baseline; not directly leveraged (no migration shipped this feature).
- Feature 029 (2026-06-11) — added `pass_as_tool` column. **Distinct from `mcp_servers`** — this feature MUST NOT touch `pass_as_tool` or the related `inject_mcp_tools()` permission code (BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS).
- Feature 016 (2026-05-27) — created the Allowed Servers feature being undone. Reverse the inventory.

## Conflict Warnings

1. **Soft conflict — Constitution §VII Definition of Done vs FR-014**: §VII requires "Unit tests written and passing for all new logic". FR-014 says no new automated test for hook pass-through. Resolution: hook pass-through is a thin proxy through `@wordpress/hooks` — testing it would test the mock, not the contract. DEC-FEATURE-027-NO-TESTS precedent applies. Plan should document this explicitly as an accepted deviation, scoped to the five new hooks only; the PHP removals MUST have PHPUnit coverage where existing tests touched `mcp_servers` (delete those asserts, keep surrounding tests).

2. **Soft conflict — ARCH-ABILITYFORM-SECTION-ORDER drift**: deleting Allowed Servers shifts the documented section order. Not blocking; capture an updated order post-implementation via `/speckit-memory-md-capture-from-diff`.

3. **State transitions to record**: DEC-MCP-SERVER-SANITIZE AND DEC-MCP-CAPABILITY-FILTER-WARN both transition to **Superseded** when this PR ships. Post-implementation capture must update INDEX.md status for both.
4. **Constitution amendment required**: §Integration Resilience canonical-pattern paragraph (added v1.4.1) must be retracted. PATCH bump per amendment procedure; SYNC IMPACT REPORT entry required. T029a covers this.

4. **No hard conflicts**. Spec aligns with Constitution §V (Extensibility Without Core Modification — adding hook seams is exactly this), §I (modular boundaries preserved — Abilities module stays self-contained), and §VI (DRY — removing dead code).

## Retrieval Notes

- Index entries scanned: ~30 (Active Decisions + Architecture Constraints + Patterns + Bug Patterns + Security + Worklog tables).
- Index entries selected per budget: 5 decisions / 5 constraints / 3 deviations (note: only 2 truly relevant — under budget) / 2 security / 5 bug patterns referenced (over 3-cap, but each cites a distinct concrete risk for plan-phase mitigation; consolidated into one Historical Lessons block).
- Durable memory source-section reads: NONE. Index alone provided sufficient context — full DECISIONS.md, ARCHITECTURE.md, BUGS.md (totaling ~228 KB) NOT loaded, per `retrieval.max_synthesis_words` discipline and CONSTITUTION.md being the only small principles file actually opened (356 lines).
- Budget status: ~870 words, under 900 cap.
- Optimizer: not configured (`.specify/extensions/memory-md/config.yml` absent), so markdown-only index-first retrieval used.

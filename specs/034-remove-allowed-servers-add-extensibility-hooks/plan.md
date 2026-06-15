# Implementation Plan: Remove Allowed Servers, Add Extensibility Hooks

**Branch**: `034-remove-allowed-servers-add-extensibility-hooks` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/034-remove-allowed-servers-add-extensibility-hooks/spec.md`
**Memory**: [memory-synthesis.md](./memory-synthesis.md) — index-first retrieval, ~870 words

## Summary

Strip the per-ability `mcp_servers` column and its entire surface (PHP schema/row/sanitizer/REST/formatter, React form block, Redux `OVERRIDABLE_FIELDS`, related tests) from `acrossai-abilities-manager`, AND remove the now-orphaned `wpboilerplate/wpb-mcp-servers-list` Composer dependency that previously fed the MCP servers list endpoint to the Allowed Servers UI. Replace the deleted UI with five MCP-agnostic extension hooks (3 JS via `@wordpress/hooks`, 2 PHP via WordPress core hooks) so a future `acrossai-mcp-manager` plugin can inject a server-mapping UI without this plugin knowing MCP servers exist. **No upgrade migration is shipped** — the plugin has not been launched yet, so removing the column from the schema definition is sufficient for fresh installs; dev installs with stale data are handled by manually dropping the abilities table and reactivating. Approximately 13 files change (PHP + JS + tests + composer + Constitution + memory), 1 Composer dependency removed, 1 Jest file deleted.

## Technical Context

**Language/Version**: PHP 8.1+ (Constitution §II floor), JavaScript ES2022 via `@wordpress/scripts` toolchain
**Primary Dependencies**: WordPress 6.9+, `@wordpress/hooks` (transitively bundled with `@wordpress/scripts` — confirmed in `package-lock.json`, no new install), `@wordpress/element`, BerlinDB v3 (vendored via Composer; baseline established in Feature 028). **Composer dependency removed**: `wpboilerplate/wpb-mcp-servers-list ^0.0.1` — was the source of the MCP servers list endpoint; orphaned after Allowed Servers UI deletion. Constitution §Integration Resilience mandate ("MUST use this package") is retracted by amendment in this PR.
**Storage**: WordPress `$wpdb` against the existing `{prefix}_acrossai_abilities` BerlinDB-managed table. Schema definition loses one column. No upgrade migration shipped (plugin not yet launched; FR-011/FR-012).
**Testing**: PHPUnit (existing suite, prefix-based discovery per `BUG-PHPUNIT-AUTODISCOVERY-PREFIX`) for PHP changes; `npx wp-scripts test-unit-js` for any remaining JS tests after `mcp-servers-checkbox.test.js` is deleted. No new test files added (per FR-014 — see Complexity Tracking entry).
**Target Platform**: WordPress 6.9+ / PHP 8.1+ admin UI; multisite-compatible.
**Project Type**: WordPress plugin (PHP backend + React admin UI in `src/js/abilities/`)
**Performance Goals**: No measurable change. Removing the Allowed Servers fetcher eliminates one admin-page REST round-trip to the MCP servers list; deletes ~154 lines of React render. Hook pass-through is O(subscribers).
**Constraints**: PHPCS WPCS zero errors; PHPStan level 8 zero errors; ESLint zero errors; Plugin Check clean against production surface; `npm run build` zero warnings; zero behavioral change with zero hook subscribers (FR-009).
**Scale/Scope**: Single-site or multisite WP installs; abilities table typically < 1000 rows; schema change is a single column removal from the BerlinDB schema definition (no migration code).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Applicability | Verdict | Notes |
|---|---|---|---|
| §I Modular Architecture | Abilities module owns the table, the REST controllers, the form, and the migration | ✅ Pass | Self-contained; no cross-module dependencies introduced or violated. |
| §II WordPress Standards | All touched PHP must pass PHPCS/PHPStan/Plugin Check; multisite-compatible per Constitution §II | ✅ Pass (gated) | CI gates enforce. No raw SQL added by this feature (no migration). |
| §III User-Centric Design (NON-NEGOTIABLE) | DataForm/DataViews mandate applies to AbilityForm | ✅ Pass — deviation already accepted | DEC-DESIGN-OVERRIDES-DATAVIEWS exempts AbilityForm from DataForm; new `extra_sections` slot inherits the exemption. No new DataForm/DataViews violation. |
| §IV Security First (NON-NEGOTIABLE) | Sanitization, escaping, nonces, capability checks at every boundary | ✅ Pass | This feature REDUCES input surface (deletes `sanitize_mcp_servers()`, deletes REST args). Existing REST controllers retain `permission_callback` + nonce. No new input endpoints created. Per user memory, permission_callback audit is intentionally skipped. |
| §V Extensibility Without Core Modification (NON-NEGOTIABLE) | All integrations via hooks; degrade gracefully when absent | ✅ Pass — direct alignment | This feature IS the canonical instance of §V. All five hooks must behave identically to baseline with zero subscribers (FR-009). |
| §VI DRY | No code duplication | ✅ Pass | Removes dead code; adds 5 hook callsites — each at a single canonical location. |
| §VII Definition of Done | Tests for all new logic | ⚠️ Partial — see Complexity Tracking | FR-014 declines a Jest test for hook pass-through. Documented deviation, precedent DEC-FEATURE-027-NO-TESTS. PHP removals retain existing PHPUnit coverage with mcp_servers asserts deleted. |

**Gate verdict**: PASS to Phase 0. One documented deviation tracked in Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/034-remove-allowed-servers-add-extensibility-hooks/
├── spec.md                  # /speckit-specify + /speckit-clarify output (3 Q→A bullets)
├── memory-synthesis.md      # /speckit-memory-md-plan-with-memory output (~870 words)
├── plan.md                  # THIS FILE (/speckit-plan output)
├── research.md              # Phase 0 output (this command)
├── data-model.md            # Phase 1 output (this command)
├── quickstart.md            # Phase 1 output (this command — MU smoke-test plugin)
├── contracts/
│   └── extension-hooks.md   # Phase 1 output — public contract for the 5 hooks
├── checklists/
│   └── requirements.md      # /speckit-specify validation (all passed)
└── tasks.md                 # /speckit-tasks output (NOT created here)
```

### Source Code (repository root)

```text
acrossai-abilities-manager/
├── composer.json                                        # MODIFIED: remove wpboilerplate/wpb-mcp-servers-list dependency
├── composer.lock                                        # REGENERATED via composer update
├── admin/
│   └── Main.php                                         # MODIFIED: add do_action + apply_filters around acrossaiAbilitiesManager localize
├── includes/
│   ├── Main.php                                         # MODIFIED: delete McpServersList::collect + RestEndpoint::register wiring (lines 303-314)
│   ├── Modules/
│   │   └── Abilities/
│   │       ├── Database/
│   │       │   ├── AcrossAI_Abilities_Schema.php       # MODIFIED: remove mcp_servers column def
│   │       │   ├── AcrossAI_Abilities_Row.php          # MODIFIED: remove property, JSON-decode entry, ctor decode
│   │       │   └── AcrossAI_Abilities_Query.php        # MODIFIED: remove mcp_servers non-string guard
│   │       ├── Rest/
│   │       │   ├── AcrossAI_Abilities_Write_Controller.php    # MODIFIED: drop arg schema + extraction
│   │       │   ├── AcrossAI_Abilities_Read_Controller.php     # MODIFIED: drop from read response
│   │       │   └── AcrossAI_Abilities_Exposure_Controller.php # MODIFIED: delete fail-closed allowlist enforcement
│   │       └── AcrossAI_Ability_Override_Processor.php # MODIFIED: delete mcp_servers enforcement (preserve pass_as_tool)
│   └── Utilities/
│       ├── AcrossAI_Sanitizer.php                       # MODIFIED: delete constants + sanitize_mcp_servers_array()
│       ├── AcrossAI_Abilities_Sanitizer.php             # MODIFIED: delete sanitize_mcp_servers() + remove calls
│       ├── AcrossAI_Abilities_Formatter.php             # MODIFIED: remove mcp_servers from 4 formatter methods
│       └── AcrossAI_Ability_Merger.php                  # MODIFIED: remove mcp_servers field + merge entry
├── src/js/abilities/
│   ├── components/
│   │   └── AbilityForm.jsx                              # MODIFIED: delete state/handlers/UI block + /wpb-mcp-servers-list/v1/servers fetcher; add 3 hook callsites
│   └── store/
│       └── index.js                                     # MODIFIED: remove mcp_servers from OVERRIDABLE_FIELDS
├── tests/jest/abilities/
│   ├── mcp-servers-checkbox.test.js                     # DELETED
│   └── ability-form-user-access-section.test.jsx        # MODIFIED: drop mcp_servers fixture lines
├── tests/jest/sitewide/
│   ├── store.test.js                                    # MODIFIED: drop mcpServers fixture
│   └── AbilityEditPanel.test.jsx                        # MODIFIED: drop mcp_servers fixture
├── tests/phpunit/
│   ├── abilities/{AbilitiesExposureControllerTest,AbilitiesValidationTest,AbilityOverrideInjectVariantATest}.php  # MODIFIED: per-file deletions
│   ├── sitewide/AbilityMergerTest.php                   # MODIFIED: drop mcp_servers fixtures
│   └── Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php  # MODIFIED: drop 3 allowlist tests + edit makeRow (preserve pass_as_tool tests)
├── .specify/memory/CONSTITUTION.md                      # AMENDED: §Integration Resilience — retract wpb-mcp-servers-list mandate; version bump + sync impact
└── docs/memory/
    ├── DECISIONS.md                                     # UPDATED: DEC-MCP-CAPABILITY-FILTER-WARN → Superseded; DEC-MCP-SERVER-SANITIZE → Superseded
    ├── ARCHITECTURE.md                                  # UPDATED: remove wpb-mcp-servers-list reference; update ARCH-ABILITYFORM-SECTION-ORDER
    └── INDEX.md                                         # UPDATED: append security-review + security-tasks-review rows + worklog
```

**Structure Decision**: This is an existing WordPress plugin. The plan modifies files in their existing module locations; no new modules, directories, or namespaces are introduced. The five hooks are placed:
- React filter/action callsites live exclusively inside `AbilityForm.jsx` (the only consumer of form draft state).
- PHP action + filter callsites live exclusively inside `admin/Main.php::enqueue_scripts()` (the only canonical enqueue site per AC-ENQUEUE-ADMIN).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Constitution §VII "Unit tests for all new logic" — no Jest test for hook pass-through (FR-014) | The "new logic" is three `applyFilters`/`doAction` calls that pass through to `@wordpress/hooks`. A Jest test would require mocking `@wordpress/hooks`, then asserting the mock was called — testing the mock, not the contract. Manual MU-plugin smoke verification (Phase 1 quickstart) covers real behavior. | Writing a Jest test that mocks `@wordpress/hooks` — rejected because (1) it tests test infrastructure, not the hook contract, and (2) precedent DEC-FEATURE-027-NO-TESTS already accepts this trade-off for thin pass-through layers. Scope of the deviation: only the five hook callsites. PHP removals MUST still preserve PHPUnit coverage for surrounding code (delete `mcp_servers` asserts only). |

## Implementation reference

The line-level inventory (file paths, lines, exact deletions for each `mcp_servers` reference) lives in `docs/planning/034-remove-allowed-servers-add-extensibility-hooks.md`. The `/speckit-tasks` step will consume that inventory to generate `tasks.md`. This plan documents architecture decisions and gates only; it does not duplicate the inventory.

## Phase 0 / Phase 1 outputs

See sibling files:
- [research.md](./research.md)
- [data-model.md](./data-model.md)
- [contracts/extension-hooks.md](./contracts/extension-hooks.md)
- [quickstart.md](./quickstart.md)

## Post-design Constitution re-check

Re-evaluated after Phase 1 artifacts produced (research, data-model, contracts, quickstart): no new gate violations introduced. The single tracked deviation (§VII, hook-only Jest skip) remains the only one and is unchanged in scope.

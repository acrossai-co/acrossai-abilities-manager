---
description: "Task list for Feature 034 — Remove Allowed Servers + Add Extensibility Hooks"
---

# Tasks: Remove Allowed Servers, Add Extensibility Hooks

**Input**: Design documents from `/specs/034-remove-allowed-servers-add-extensibility-hooks/`
**Prerequisites**: plan.md ✓ · spec.md ✓ · research.md ✓ · data-model.md ✓ · contracts/extension-hooks.md ✓ · quickstart.md ✓ · memory-synthesis.md ✓ · security-constraints.md ✓
**Line-level inventory**: `docs/planning/034-remove-allowed-servers-add-extensibility-hooks.md`

**No upgrade migration**: per FR-011/FR-012 (plugin not yet launched), this feature ships ONLY the schema-definition column removal. No migration code. Dev installs with stale data are handled by manually dropping the abilities table and reactivating the plugin.

**Tests**: This feature does NOT add new automated tests for hook pass-through (FR-014 + Constitution §VII deviation documented in plan.md). Existing PHPUnit/Jest tests touching `mcp_servers` are deleted, not extended.

**Organization**: Tasks grouped by user story. Story map:
- **US1** (P1, MVP) — Strip `mcp_servers` end-to-end (PHP + JS + tests). Unblocks US2.
- **US2** (P2) — Add the five extension hooks (3 JS, 2 PHP).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks).
- **[Story]**: US1, US2. Setup, Foundational, and Polish phases have NO story label.
- Each task includes the exact file path + the line range or symbol being changed.

## Path Conventions

WordPress plugin. PHP under `includes/`, `admin/`; JS/React under `src/js/abilities/`; tests under `tests/phpunit/` and `tests/jest/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Apply the two architecture-review LOW findings as spec/doc amendments BEFORE any code changes, so downstream work consumes the corrected contract. Confirm clean baseline.

- [x] T001 Amend `specs/034-remove-allowed-servers-add-extensibility-hooks/spec.md` FR-010 to extend public-contract coverage from "the four context-object keys forwarded by `acrossai_abilities.form.extra_sections`" to ALSO include the three context-object keys forwarded by `acrossai_abilities.form.save_payload` (`abilityId`, `slug`, `isNonDb`). Per architecture-review ARCH-LOW-1.
- [x] T002 [P] Confirm baseline is clean before edits: run `composer run phpcs`, `composer run phpstan`, `vendor/bin/phpunit`, `npx wp-scripts test-unit-js`, `npm run build`. Record any pre-existing failures unrelated to Feature 034 so they aren't mis-attributed during implementation.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: No foundational code exists for this feature. The story ordering below IS the foundational constraint: **US2 (slot placement) requires US1's JS deletion to land first**, since the React `extra_sections` slot is placed at the JSX location vacated by the Allowed Servers UI block.

**⚠️ CRITICAL**: Begin US2's React slot work only after US1's JS deletion (T012) is complete. US2's PHP hook work has no US1 dependency and could in principle run in parallel.

---

## Phase 3: User Story 1 — Strip `mcp_servers` end-to-end (Priority: P1) 🎯 MVP

**Goal**: Delete every reference to `mcp_servers` from PHP, React/Redux, and tests, so the abilities plugin reads as if MCP servers never existed. Schema definition also loses the column so fresh installs never create it.

**Independent Test**: After this phase, with no other plugins installed: (a) the T016 exhaustive grep (see below) returns zero hits — including across `includes/`, `src/`, `tests/`, `admin/`, `uninstall.php`, and `composer.json`, AND including the `McpServersList` / `wpb-mcp-servers-list` / `wpb_mcp_servers_list` package-removal patterns; (b) the ability edit page renders with no Allowed Servers UI, zero console errors, zero PHP notices; (c) PHPCS + PHPStan level 8 + Jest + PHPUnit + Plugin Check all green; (d) `npm run build` zero warnings; (e) `composer show wpboilerplate/wpb-mcp-servers-list` returns "package not found"; (f) a freshly installed plugin (drop the abilities table, reactivate) creates the table WITHOUT an `mcp_servers` column; (g) Feature 029's `pass_as_tool` injection still functions for abilities with `pass_as_tool=1` but is no longer per-server filtered (per spec.md "Security posture change").

### Implementation for User Story 1

**PHP — wrapper sanitizer cleared BEFORE base sanitizer** (caller-then-callee ordering avoids transient broken state):

- [x] T003 [US1] In `includes/Utilities/AcrossAI_Abilities_Sanitizer.php` (lines 239-241, 306-308): delete the `sanitize_mcp_servers()` method and remove its calls inside `sanitize_create_request()` and `sanitize_update_request()`.
- [x] T004 [US1] In `includes/Utilities/AcrossAI_Sanitizer.php` (lines 135-168): delete constants `MAX_MCP_SERVERS` and `MAX_SERVER_ID_LENGTH`, and the entire `sanitize_mcp_servers_array()` method. Per spec "do not generalize the constants" — delete cleanly, no replacement helper. After deletion, record that DEC-MCP-SERVER-SANITIZE transitions to Superseded (to be applied in T028 memory update).

**PHP — schema + row + REST + formatter** (all parallel — disjoint files):

- [x] T005 [P] [US1] In `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` (lines 186-191): delete the `mcp_servers` column definition. **This is the only DB-related change in the feature**; fresh installs never create the column. Do NOT bump `$version` in `AcrossAI_Abilities_Table.php` and do NOT add any `__1XX()` upgrade method — no migration is shipped per FR-011/FR-012.
- [x] T006 [P] [US1] In `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php` (lines 175-180, 267, 300-304): delete the `$mcp_servers` property, its entry in the JSON-decode list, and its decode in the constructor.
- [x] T007 [P] [US1] In `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (lines 103-113 for create route, 172-182 for update route): delete the `mcp_servers` REST argument schema entries on both routes. Per research.md Decision "REST silent-acceptance" — DO NOT add `additional_properties: false`; simply removing the arg lets WP REST drop the unknown field silently (satisfies FR-003 with no new code).
- [x] T008 [US1] In `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (lines 306-308 and 526): delete the `mcp_servers` extraction from the sanitized request handler and from the non-db override save path. Depends on T007 (same file, must serialize edits).
- [x] T009 [P] [US1] In `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php`: `grep -n mcp_servers` first to locate any references in read-response shaping, then delete those lines. (If grep returns zero hits, mark task complete with a no-op note.)
- [x] T010 [P] [US1] In `includes/Utilities/AcrossAI_Abilities_Formatter.php` (lines 58, 98, 150, 192): remove `mcp_servers` from all four formatter methods — `format_for_response()`, `format_for_exposure()`, `format_merged_ability()`, `build_registry_args()`. No defensive null fallbacks; the key disappears from each method's output.

**PHP — files missing from the original inventory (per security-tasks-review SEC-T-001 + architecture-migration-plan Task 1.1)**:

- [x] T010a [P] [US1] In `includes/Utilities/AcrossAI_Ability_Merger.php` (lines 41 and 195): delete `mcp_servers` from the merger's field list (line 41) AND the merge map entry that resolves it from `$mcp_meta['servers']` or annotation (line 195). The merger no longer surfaces this field in the merged ability shape.
- [x] T010b [P] [US1] In `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (lines 678-681): delete the `mcp_servers` non-string guard from the BerlinDB save-normalization step. The guard exists to coerce non-string values before encoding; with the column gone, the guard is dead code.
- [x] T010c [US1] In `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php` (lines 106 and 141-164): delete the **fail-closed `mcp_servers` enforcement** at the exposure boundary AND the surrounding docstring claims ("Row with non-empty mcp_servers + empty/unknown server_id → EXCLUDED (fail-closed)"). Add a brief inline comment at the deletion site: `// Per Feature 034 spec.md "Security posture change" — fail-closed MCP allowlist enforcement deleted; re-implemented by acrossai-mcp-manager via acrossai_abilities.form.extra_sections + its own enforcement at the MCP exposure boundary.` Updates this file's PHPDoc + class-level docstring to remove allowlist semantics.
- [x] T010d [US1] In `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`: delete every `mcp_servers` reference (lines 172, 176, 286, 325, 353, 366, 379-380, 445, 624-625, 678-683). **GUARDRAIL: this file ALSO contains Feature 029 `pass_as_tool` code that MUST be preserved.** Specifically:
  - DELETE the `mcp_servers` allowlist check inside `inject_mcp_tools()` (lines 678-683, Check 2). KEEP the `pass_as_tool` early-exit (lines 646-647, 653, Check 1) and its `mcp_type`/`non_tool_types` guard (line 653).
  - DELETE the `mcp_servers` injection in `inject_override_args()` (lines 325, 353, 366, 379-380). KEEP all other override injection logic (`show_in_mcp`, `mcp_type`, etc.).
  - UPDATE the docstring around lines 286, 624-625 to remove "mcp_servers" entries while preserving descriptions of remaining fields/checks.
  - ADD the same inline comment as T010c at the largest deletion site (around line 678): `// Per Feature 034 spec.md "Security posture change" — fail-closed mcp_servers allowlist enforcement deleted; pass_as_tool injection no longer per-server filtered.`
  - This file is single-purpose enough that a manual file-walk after edits, verifying that `pass_as_tool` and `inject_mcp_tools` still function (per quickstart manual smoke item for Feature 029 if available), is recommended.

**Composer dependency removal (wpboilerplate/wpb-mcp-servers-list)** — per spec.md "Composer dependency removal":

- [x] T010e [US1] In `composer.json` (line 20): delete the line `"wpboilerplate/wpb-mcp-servers-list": "^0.0.1",`. Confirm no other lines in the file reference this package.
- [x] T010f [US1] In `includes/Main.php` (lines 303-314): delete the entire `// Collect MCP servers at priority 20…` block — both `add_action` calls (`McpServersList::collect` and `RestEndpoint::register`), the WARNING comment about `wpb_mcp_servers_list_rest_capability`, and the preceding `$mcp_servers_list = …::instance();` / `$mcp_servers_rest = …::class;` named-variable lines. Keep the surrounding lines unchanged (the BerlinDB table line above + the Access Control line below).
- [x] T010g [US1] Regenerate Composer artifacts: run `composer update wpboilerplate/wpb-mcp-servers-list --no-dev` to remove the package from `composer.lock` AND delete the `vendor/wpboilerplate/wpb-mcp-servers-list/` directory. Verify with `composer show | grep -i mcp` returning no rows. Note: this also re-runs `jetpack-autoloader` to drop the package's PSR-4 entries from the generated classmap.
- [x] T010h [US1] Verify the apiFetch path `/wpb-mcp-servers-list/v1/servers` referenced in `src/js/abilities/components/AbilityForm.jsx` (line 320) has been deleted by T012. If T012 was implemented loosely, explicitly confirm the `useEffect` block at lines 318-330 — which calls `apiFetch({ path: '/wpb-mcp-servers-list/v1/servers' })` and populates `setMcpAdapterAvailable` / `setMcpServers` / `setMcpServersError` — is gone. (T012's instruction was "find via grep"; this task makes the specific endpoint path the verification target.)

**REST required-field audit (security finding SEC-001)**:

- [x] T011 [US1] Audit `src/js/abilities/components/AbilityForm.jsx` field-level validation (which fields the form treats as required) against the REST argument schemas in `AcrossAI_Abilities_Write_Controller.php` after T007/T008. For every field the form treats as required, confirm the matching REST arg has `'required' => true` (or equivalent validation that returns 4xx for missing values). If gaps exist, fix the controller — NOT the form. Per security review SEC-001 (CWE-602).

**JS / React removal** (single-file edits — serialize; per BUG-ABILITYFORM-PANEL-PREMATURE-CLOSE):

- [x] T012 [US1] In `src/js/abilities/components/AbilityForm.jsx`, MANUAL EDIT ONLY (no script-driven `str_replace`): delete `mcpServers` / `mcpAdapterAvailable` / `mcpServersError` state (lines 252-255); delete `mcp_servers` from save payload (line 526); delete `handleServerToggle` and `handleAllServersToggle` (lines 604-616); delete `mcpSavedIds` / `mcpFetchedIds` / `mcpStaleIds` / `mcpAllItems` derived values (lines 621-630); delete the entire UI render block (lines 1268-1422 — label, loading/error/adapter-unavailable, checkbox list, stale indicator, plugin-declared hint); delete the `useEffect` at lines 318-330 that calls `apiFetch({ path: '/wpb-mcp-servers-list/v1/servers' })` and populates the three pieces of state above. After deletion: visually verify the `.panel` closing `</div>` position is correct (per BUG-ABILITYFORM-PANEL-PREMATURE-CLOSE), and confirm tab depth conventions per DEC-HACTIONS-BUTTON-DEPTH are preserved on surrounding code. T010h independently verifies the apiFetch endpoint is gone.
- [x] T013 [P] [US1] In `src/js/abilities/store/index.js` (line 48): remove `'mcp_servers'` from the `OVERRIDABLE_FIELDS` array. Verify `JSON.stringify` dirty-check still works for remaining fields.

**Test cleanup** — per-file guidance per security-tasks-review SEC-T-003 (the 8 affected test files each need explicit handling):

- [x] T014 [P] [US1] Delete `tests/jest/abilities/mcp-servers-checkbox.test.js` in full.
- [x] T014a [P] [US1] In `tests/jest/abilities/ability-form-user-access-section.test.jsx` (lines 34, 240, 288): delete the three `mcp_servers: null,` lines from mock/expected objects. Keep all other UserAccess test assertions.
- [x] T014b [P] [US1] In `tests/jest/sitewide/AbilityEditPanel.test.jsx` (line 99): delete the single `mcp_servers: null,` line from the mock object. Keep all surrounding assertions.
- [x] T014c [P] [US1] In `tests/jest/sitewide/store.test.js` (line 38): delete the single `mcpServers: [],` line (note: camelCase, JS-side). Per BUG-JEST-MOCK-LIST-STALENESS, also verify the surrounding `jest.mock()` allowlist (if any) doesn't reference `mcpServers`-only paths.
- [x] T015 [US1] In `tests/phpunit/abilities/AbilitiesExposureControllerTest.php` (lines 105-313+): delete the entire test methods that assert the deleted fail-closed `mcp_servers` enforcement (specifically the `Server-scoped row ... EXCLUDED when server_id is empty (fail-closed)` test, the `Server-scoped row is INCLUDED when server_id matches` test, the `Unrestricted rows (null mcp_servers) are always included` test, and the `insert_published_mcp` helper if no other tests still use it). Keep ExposureController tests covering OTHER concerns (show_in_mcp, mcp_type, etc.).
- [x] T015a [P] [US1] In `tests/phpunit/abilities/AbilitiesValidationTest.php` (lines 611-669+): delete the entire `sanitize_mcp_servers_array` test block — all six test methods (`test_sanitize_mcp_servers_null_returns_null`, `test_sanitize_mcp_servers_non_array_returns_null`, `test_sanitize_mcp_servers_valid_array_returned`, `test_sanitize_mcp_servers_empty_array_returns_null`, `test_sanitize_mcp_servers_only_empty_strings_returns_null`, `test_sanitize_mcp_servers_strips_empty_strings`) and the section comment header `// sanitize_mcp_servers_array — T017 (Feature 016)`. Keep all other AcrossAI_Sanitizer tests.
- [x] T015b [P] [US1] In `tests/phpunit/abilities/AbilityOverrideInjectVariantATest.php` (lines 250, 281, 317, 335, 372, 390): delete the six `'mcp_servers' => null,` lines from mock fixtures. These are placeholder values; deletion does not invalidate surrounding test behavior.
- [x] T015c [P] [US1] In `tests/phpunit/sitewide/AbilityMergerTest.php` (lines 36, 63, 76): delete the three `'mcp_servers' => null,` / `$override->mcp_servers = null;` references from mock fixtures. Keep all other Merger test logic.
- [x] T015d [US1] In `tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php` (lines 25, 37, 56, 58-64, 231-274, and others): **HIGH-CARE EDIT.** This is Feature 029's pass_as_tool test, which currently asserts that `mcp_servers` acts as a per-server allowlist gate inside `inject_mcp_tools` (Check 2 from Override_Processor). Per the Feature 034 spec.md "Security posture change" note and T010d guardrail, the allowlist gate is being deleted; the pass_as_tool injection itself remains.
  - **DELETE** the three test methods that specifically assert the mcp_servers allowlist semantic: `test_mcp_servers_empty_array_blocks_all_servers` (lines 235-247), `test_mcp_servers_allowlist_injects_only_on_matching_server` (lines 251+), `test_mcp_servers_null_injects_on_all_servers` (lines 272+).
  - **EDIT** the `makeRow` helper (lines 56-64) to drop the `$mcp_servers` parameter and the corresponding property — the surviving tests should construct rows with only `pass_as_tool` (and any other Feature 029 fields).
  - **UPDATE** the file-level docstring (line 25 reference to "(d) mcp_servers allowlist") to remove that bullet — the surviving suite asserts only pass_as_tool gating and mcp_type filtering.
  - **KEEP** all tests covering `pass_as_tool` alone, `pass_as_tool` + `mcp_type` interactions (BUG-MCP-TYPE-PASSTOOL-CONFLICT), and `inject_mcp_tools` permission gating (BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS).

**Final-grep validation for this phase** — per security-tasks-review SEC-T-004:

- [x] T016 [US1] Run the exhaustive grep below from repo root — output MUST be empty:
  ```bash
  grep -rEn "mcp_servers|mcpServers|MAX_MCP_SERVERS|MAX_SERVER_ID_LENGTH|sanitize_mcp_servers|mcpAdapterAvailable|handleServerToggle|handleAllServersToggle|McpServersList|wpb-mcp-servers-list|wpb_mcp_servers_list" \
    includes/ src/ tests/ admin/ uninstall.php composer.json \
    --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=build
  ```
  The McpServersList orthogonal exclusion that prior versions of this task carried is **NO LONGER NEEDED** — T010e–T010g remove the `wpboilerplate/wpb-mcp-servers-list` package entirely, so any surviving `McpServersList` / `wpb-mcp-servers-list` / `wpb_mcp_servers_list` / `$mcp_servers_list` / `$mcp_servers_rest` reference is a genuine leftover that must be cleaned up. `composer.json` is in scope to catch the dependency line. If anything remains, fix before claiming US1 complete.

**Checkpoint**: US1 fully functional and testable independently. Plugin reads as if `mcp_servers` never existed in code (verified by T016 exhaustive grep); fresh installs never create the column. Fail-closed MCP allowlist enforcement is gone from BOTH `Exposure_Controller` AND `Override_Processor`'s `inject_mcp_tools` (Feature 029 pass_as_tool injection no longer per-server filtered) — this is the intentional security-posture change documented in spec.md. Dev install with stale data: drop the abilities table manually and reactivate the plugin → table is recreated without the column.

---

## Phase 4: User Story 2 — Five extension hooks (Priority: P2)

**Goal**: Three React hooks + two PHP hooks form the public extension contract that a future `acrossai-mcp-manager` plugin (or any third party) uses to inject a server-mapping UI into the ability form.

**Independent Test**: Drop the MU plugin from `specs/034-.../quickstart.md` into `wp-content/mu-plugins/`. With it active: the test panel renders in the form, `[draft_changed]` logs on every keystroke, save requests carry `_test_save_probe`, `window.acrossaiAbilitiesManager._test_probe` returns the probe string, the `_test_abilities_hook_fired` option holds a recent timestamp. With MU plugin deleted: form renders identically to the post-US1 baseline; no errors.

### Implementation for User Story 2

- [x] T017 [US2] In `src/js/abilities/components/AbilityForm.jsx`, MANUAL EDIT ONLY (per BUG-ABILITYFORM-PANEL-PREMATURE-CLOSE): add three hook callsites — (a) `applyFilters( 'acrossai_abilities.form.extra_sections', [], { abilityId, slug, draft, isNonDb } )` rendered via `.map((node, i) => <Fragment key={i}>{node}</Fragment>)` at the JSX location where the deleted Allowed Servers block lived (T012's deletion site); (b) `useEffect( () => doAction( 'acrossai_abilities.form.draft_changed', draft ), [draft] )` adjacent to other draft-watching effects (NO internal debouncing per FR-005 contract); (c) `applyFilters( 'acrossai_abilities.form.save_payload', basePayload, { abilityId, slug, isNonDb } )` immediately before the REST POST body is constructed. Imports: `import { applyFilters, doAction } from '@wordpress/hooks';` and `import { Fragment } from '@wordpress/element';`. **No `mcp_*` naming anywhere** in the new code.
- [x] T018 [US2] In `admin/Main.php` (around line 254, immediately before the `wp_add_inline_script` call that injects `window.acrossaiAbilitiesManager`): wrap the `$data` array with `$data = apply_filters( 'acrossai_abilities_admin_localize_data', $data );` then pass `$data` (or its JSON encoding) into the existing `wp_add_inline_script`. Add an inline PHP comment per SEC-002: "Subscribers MUST data-minimize — values appear in a browser-accessible JS global. Do NOT add secrets, tokens, hashed credentials, or user PII beyond what is strictly required for UI rendering."
- [x] T019 [US2] In `admin/Main.php`, inside `enqueue_scripts()`: add `do_action( 'acrossai_abilities_form_settings_registered' );` AFTER the `wp_enqueue_script` call for the abilities admin bundle AND AFTER the `wp_add_inline_script` data injection from T018. This ordering ensures subscribers can rely on both the script handle being registered and the localized data being present. Verify placement satisfies AC-ENQUEUE-ADMIN (callsite is in `Admin\Main::enqueue_scripts()`, the canonical enqueue location).

**Contract document hardening (architecture + security follow-ups)**:

- [x] T020 [US2] In `specs/034-remove-allowed-servers-add-extensibility-hooks/contracts/extension-hooks.md`, apply three additions: (a) per SEC-002, add an explicit data-minimization warning to the `acrossai_abilities_admin_localize_data` filter section (values exposed in browser-accessible JS global; no secrets/PII; namespace keys); (b) per SEC-INFO-001, add a short "Trust model" section stating extensions execute in the admin trust boundary, the hooks add no sandbox, no capability/nonce checks are or will be added at the hook callsites; (c) per architecture-review ARCH-LOW-2, enumerate all reserved keys this plugin currently populates on `window.acrossaiAbilitiesManager` (grep `admin/Main.php` data array and list each: `protected_slugs`, `perPage`, `rest_namespace`, etc.) so extension authors have an authoritative collision-avoidance registry; recommend extensions prefix their keys (e.g., `acrossai_mcp_manager_*`).

**Checkpoint**: US2 fully functional and testable independently. All five hooks behave identically to baseline with zero subscribers (FR-009); MU smoke-test plugin demonstrates each hook firing as contracted; contract document is complete and warns about misuse.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Verify all quality gates pass and capture lessons.

- [x] T021 [P] Run `composer run phpcs` from repo root — output MUST be zero errors. Fix anything Feature-034-introduced. Pre-existing baseline issues (recorded in T002) are not in scope.
- [x] T022 [P] Run `composer run phpstan` — PHPStan level 8, zero errors. Per BUG-PHPSTAN-SILENT-PASS, verify `exit 0` AND no output.
- [x] T023 [P] Run `npm run build` — zero warnings (including no "unused import" / "unused variable" warnings from leftovers).
- [x] T024 [P] Run `npx wp-scripts test-unit-js` (NOT plain `npx jest` per PATTERN-JESTENV-WPSCRIPTS) — all Jest specs pass, including remaining abilities specs after T014's deletion.
- [x] T025 [P] Run `vendor/bin/phpunit` — all PHPUnit specs pass, including T015's edited tests. Verify per BUG-PHPUNIT-AUTODISCOVERY-PREFIX that the file-prefix discovery still finds the suite (changes to deleted-line counts shouldn't affect this, but verify the test count didn't silently drop to 0).
- [x] T026 Run Plugin Check against the production surface (per PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT — wp-env + WP-CLI; do NOT use plugin-check-action@v1 due to BUG-PLUGIN-CHECK-ACTION-NODE24). Zero errors, zero warnings on production files.
- [x] T027 Manual smoke verification per `specs/034-remove-allowed-servers-add-extensibility-hooks/quickstart.md` — execute the full 13-item checklist (5 hook verifications with MU plugin enabled, 4 with it disabled, 3 schema-verification items, 1 REST silent-acceptance verification). Record each item's pass/fail in the spec checklists folder.
- [x] T028 [P] Append a worklog entry to `docs/memory/WORKLOG.md` for Feature 034 in the same one-line format used by recent entries (date, feature title, scope tags). Append a routing row for `specs/034-.../security-constraints.md` to `docs/memory/INDEX.md` per the security review output.
- [x] T029 [P] Update `docs/memory/INDEX.md` and the source decision rows in `docs/memory/DECISIONS.md` for both:
  - `DEC-MCP-SERVER-SANITIZE` → Status `Active` → `Superseded` with note: "Superseded by Feature 034 — column and sanitizer removed entirely (no migration shipped)."
  - `DEC-MCP-CAPABILITY-FILTER-WARN` → Status `Active` → `Superseded` with note: "Superseded by Feature 034 — `wpboilerplate/wpb-mcp-servers-list` Composer package removed; the `wpb_mcp_servers_list_rest_capability` filter is no longer wired in this plugin."
  - `ARCH-ABILITYFORM-SECTION-ORDER`: update the section list to reflect that the "MCP" section (position 3) is now the `extra_sections` extension slot (position 3, extension-provided content).
- [x] T029a **Constitution amendment.** In `.specify/memory/CONSTITUTION.md` § "Architecture & UI Standards" > "Integration Resilience" (around line 322–332): retract the "MCP server listing MUST use the `wpboilerplate/wpb-mcp-servers-list` Composer package" mandate added in v1.4.1. Replace with a brief note stating that as of Feature 034, the abilities plugin owns no MCP-server-list integration — any plugin needing MCP server enumeration chooses its own mechanism. Bump version per amendment procedure (PATCH bump → v1.4.7 since this is a retraction of a sub-bullet, not a principle change). Add a `SYNC IMPACT REPORT` entry at the top of CONSTITUTION.md with the rationale: "Feature 034 removes wpb-mcp-servers-list dependency in favor of extension hooks; canonical-pattern paragraph no longer applies." Review templates per the standard procedure. In `docs/memory/ARCHITECTURE.md` (line 58), remove or rewrite the `wpb-mcp-servers-list` bullet to reflect the package's removal.
- [x] T030 User-invoked post-merge: run `/speckit-memory-md-capture-from-diff` to surface durable patterns ("bundled extension-contract" — naming + context shape + JS global as one promise; "planning-doc verification" — clarify must check actual code before pinning contract from doc snippets; "skip-migration-pre-launch" — when a plugin is pre-launch, schema-only changes are sufficient and skip the migration tax; "constitution-amendment-when-removing-mandated-dependency" — removing a Composer package mandated by Constitution requires CONSTITUTION amendment with sync-impact report). This task is performed manually by the user, not automated.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies on code state; T001 is a spec edit, T002 is a baseline check. Can run immediately.
- **Foundational (Phase 2)**: No tasks — sequencing IS the gate.
- **US1 (Phase 3, P1)**: Depends on Phase 1 (T001 spec edit consumed by US2's documentation). Must complete BEFORE US2 (slot placement reuses deletion site).
- **US2 (Phase 4, P2)**: React part (T017) depends on US1's JS deletion (T012). PHP part (T018-T020) is independent of US1.
- **Polish (Phase 5)**: Depends on US1 + US2 complete.

### User Story Dependencies

- **US1 (P1)**: Independent — but unblocks US2's React slot.
- **US2 (P2)**: React work sequential after US1's JS deletion (T012). PHP work (T018-T020) could run in parallel with US1 if helpful.

### Within Each User Story

- T003 → T004 in US1 (caller before callee on the sanitizer chain).
- T007 → T008 in US1 (same file; serialize edits to avoid merge conflicts mid-task).
- T005/T006 (Schema + Row column/property removal) SHOULD complete before T010c/T010d (which delete the consumers of `$row->mcp_servers`) — otherwise PHPStan/runtime errors surface during partial implementation. Recommended order: T005 + T006 first, then T010a–T010d in parallel.
- T010d in US1 requires special care: file contains Feature 029 `pass_as_tool` code that must be preserved. Manual review after edits; do NOT use script-driven `str_replace`.
- T012 → T017 across US1/US2 (delete the block before inserting the slot at its location).

### Parallel Opportunities

- T001 and T002 in Phase 1 (different artifacts).
- T005, T006, T009, T010 within US1 (4 disjoint PHP files).
- T010a, T010b within US1 (Merger + Query, disjoint files). T010c and T010d SHOULD be sequential within themselves (each requires careful inline-comment placement) but disjoint from each other.
- T010e, T010f, T010g, T010h within US1 are sequenced: T010e (composer.json edit) → T010g (composer regenerate) MUST come after; T010f (Main.php block deletion) is independent and can run in parallel with T010e/T010g; T010h (verify apiFetch deleted) depends on T012 completion. Recommended: T010e and T010f in parallel, then T010g once T010e is done.
- T013, T014, T014a, T014b, T014c within US1 (store + 4 Jest files, all disjoint).
- T015a, T015b, T015c within US1 (3 disjoint PHPUnit files of light-touch fixture removals). T015 and T015d are heavier (entire-method deletions); recommend serializing those.
- US2's PHP tasks (T018, T019) can run in parallel with US1 PHP fan-out if developer capacity allows; they touch `admin/Main.php` only.
- T021, T022, T023, T024, T025 in Polish phase (5 independent gate commands).
- T028, T029 in Polish phase (different memory files; small edits).

---

## Parallel Example: User Story 1 PHP fan-out

```bash
# Stage 1 — after T003 + T004 (sanitizer caller-then-callee), launch the 4 originally-inventoried disjoint PHP edits:
Task: "T005 — Delete mcp_servers column def in AcrossAI_Abilities_Schema.php"
Task: "T006 — Delete property + decode in AcrossAI_Abilities_Row.php"
Task: "T009 — Remove mcp_servers from Read Controller response shaping"
Task: "T010 — Remove mcp_servers from 4 formatter methods in AcrossAI_Abilities_Formatter.php"

# Stage 2 — after Schema + Row deletions land (T005, T006), launch the 4 missing-from-inventory cleanups:
Task: "T010a — Delete mcp_servers from Merger field list + merge map"
Task: "T010b — Delete mcp_servers non-string guard in Query"
Task: "T010c — Delete fail-closed enforcement in Exposure_Controller (+ docstring)"
Task: "T010d — Delete mcp_servers from Override_Processor (CAREFUL: preserve pass_as_tool)"

# Stage 3 — composer dependency removal (Main.php wiring is independent of mcp_servers PHP edits):
Task: "T010e — Delete wpb-mcp-servers-list line from composer.json"
Task: "T010f — Delete McpServersList wiring block from includes/Main.php (lines 303-314)"
# Then serially (T010g must follow T010e):
Task: "T010g — composer update + vendor regeneration"
```

## Parallel Example: US2 PHP can overlap US1 PHP

```bash
# US2 PHP (admin/Main.php) is independent of US1 PHP — overlap if developer capacity allows:
Task: "US2 T018-T019 — Add 2 PHP hook callsites in admin/Main.php"
# Concurrent with US1's PHP fan-out (T005-T010), since those touch different files.
# US2's React T017 still must wait for US1's T012.
```

---

## Implementation Strategy

### MVP First (US1 only)

1. Complete Phase 1 (T001-T002) — spec hardening + baseline.
2. Complete Phase 3 — strip `mcp_servers` end-to-end (T003-T016, including T010a-T010d for the four missing-from-original-inventory files and T014a-T015d for the per-file test cleanup).
3. **STOP and VALIDATE**: Run all gates (T021-T025 subset). Run the T016 exhaustive grep (now including `admin/`, `uninstall.php`, `composer.json`, AND the McpServersList / wpb-mcp-servers-list patterns since T010e–T010g removed the package). Verify the ability form renders cleanly, REST silently accepts the obsolete field, AND Feature 029's `pass_as_tool` injection still works (now without per-server allowlist filtering — verify via a manual smoke test if the McpToolsPassthrough test suite alone is insufficient). Verify `composer show wpboilerplate/wpb-mcp-servers-list` returns "package not found".
4. This alone is a shippable state. Ship as MVP if needed; US2 can ship in a subsequent PR.

### Incremental Delivery

1. Setup + US1 → MVP shippable (column removed from schema for fresh installs; fail-closed MCP enforcement gone per spec.md "Security posture change").
2. Add US2 → extension hooks live; future MCP manager plugin can integrate; shippable.
3. Polish → quality gates + memory updates + manual smoke.

### Parallel Team Strategy

With multiple developers:

1. Developer A: US1 Stage 1 PHP fan-out (T005, T006, T009, T010 in parallel after T003-T004).
2. Developer B: US1 Stage 2 PHP fan-out (T010a, T010b, T010c, T010d after Schema + Row drop in Stage 1). T010d is the most labor-intensive — preserve pass_as_tool code carefully.
3. Developer C: US2 PHP (T018-T019) on `admin/Main.php` — independent file from all US1 PHP.
4. After US1's T012 lands: Developer D picks up US2 React (T017) on `AbilityForm.jsx`.
5. Developer E: test cleanup (T014a-T014c + T015a-T015c in parallel, then T015 + T015d serial).

---

## Notes

- **No migration code in this feature** (FR-011/FR-012). The schema definition loses one column; that's the only DB-related change. Do NOT bump BerlinDB `$version` or add an upgrade method. Dev installs handle stale data manually.
- **Composer dependency removed** (T010e–T010g): `wpboilerplate/wpb-mcp-servers-list ^0.0.1` is excised entirely. Constitution §Integration Resilience mandate is retracted (T029a). After this PR, no plugin code references the package; future MCP-server enumeration is the future MCP manager plugin's concern.
- **No new automated tests for hook pass-through** (FR-014, plan.md Complexity Tracking deviation). Manual smoke verification (T027) is the gate.
- **No `mcp_*` naming in any new code** (spec.md FR-002). The plugin must read post-merge as if MCP servers were never a per-ability concern.
- **All hook contract names + context-object keys + the `window.acrossaiAbilitiesManager` global** are public per FR-010 (extended by T001 to also cover `save_payload` context). Rename only via the documented deprecation cycle.
- **Per project memory**, the user invokes spec-kit commands manually. T030's `/speckit-memory-md-capture-from-diff` is the user's call, not auto-triggered.
- **Per project memory**, `permission_callback` REST audit was intentionally skipped during security review and is not in scope for any task here.
- Commit cadence: prefer one commit per task or per small logical group; do NOT bundle US1 and US2 into a single commit (they may need to be reverted independently).

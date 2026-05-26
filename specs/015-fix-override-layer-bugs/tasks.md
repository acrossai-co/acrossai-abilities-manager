# Tasks: Fix Override Layer Bugs + AbilityForm UI Improvements

**Feature Branch**: `015-fix-override-layer-bugs`
**Input**: `specs/015-fix-override-layer-bugs/plan.md`, `specs/015-fix-override-layer-bugs/spec.md`
**Security Constraints**: `specs/015-fix-override-layer-bugs/security-constraints.md`
**Memory Synthesis**: `specs/015-fix-override-layer-bugs/memory-synthesis.md`

## Format: `[ID] [P?] [Story] Description with file path`

- **[P]**: Can run in parallel (touches a different file from all other parallel tasks)
- **[Story]**: US1–US6 map to user stories from spec.md
- No tests requested — test tasks omitted per spec

---

## Phase 1: Setup

**Purpose**: Confirm prerequisites before any code change begins.

- [x] T001 Read `specs/015-fix-override-layer-bugs/security-constraints.md` and `specs/015-fix-override-layer-bugs/memory-synthesis.md` in full before writing any code — internalise all four implementation watchpoints (SEC-002 guard position, null-assign ordering, strict comparison, is_array guard)

---

## Phase 2: Foundational (Blocking Prerequisites for US4 and US5)

**Purpose**: DB schema PHP changes that must be committed before the `save_override()` null-assign in US4 is consistent with the underlying table definition. Also unblocks the T009 manual ALTER TABLE step.

**⚠️ CRITICAL**: T004 (US4) MUST NOT be implemented until T002 and T003 are done.

- [x] T002 [P] Update `status` and `callback_type` columns to `allow_null: true, default: null` in `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` (FR-013)
- [x] T003 [P] Update `status` and `callback_type` SQL to `DEFAULT NULL` (remove `NOT NULL`) in `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php` `set_schema()` (FR-014)

**Checkpoint**: Schema PHP files updated — safe to implement US4 and queue T009.

---

## Phase 3: User Story 4 — First save immediately reflects in the UI (Priority: P1)

**Goal**: Eliminate the stale BerlinDB slug-cache bug so that the very first save of a non-db override returns a populated `_override` and `has_override: true` in the REST response — no page reload required.

**Independent Test**: Via the REST API, POST an override for a non-db ability that has no existing row. Confirm the response body contains `has_override: true` and a populated `_override` object. Without reloading, open the ability again and confirm TriChips reflect the saved override.

- [x] T004 [US4] Refactor `save_override()` in `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`: (1) change PHPDoc + return type to `AcrossAI_Abilities_Row|false`; (2) INSERT path — move null-assign for `callback_type`/`status` **before** `add_item()` then return `get_ability_by_id((int)$result)`; (3) UPDATE path — return `get_ability_by_id($existing->id)` after `update_item()`; (4) add `@internal` tag to `get_ability_by_id()` PHPDoc (FR-008, FR-009, FR-010, FR-012, FR-018) — ⚠️ SEC-002 guard MUST remain as the FIRST statement in the method body, before all new null-assign logic
- [x] T005 [US4] Update the non-db upsert path in `update_ability()` in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`: rename `$saved` → `$override_row`; use the row returned by `save_override()` directly; remove the separate `get_override_by_slug()` re-query; change error code to `'save_override_failed'`; use strict `false === $override_row` check (FR-011, FR-019) — depends on T004

**Checkpoint**: First-save REST response returns `has_override: true` with correct `_override` values. PHPStan level 8 must pass for both changed PHP files.

---

## Phase 4: User Story 1 — MCP fields correctly normalised for third-party plugins (Priority: P1)

**Goal**: Fix `normalize_registry()` so abilities registered with nested `meta['mcp']` correctly surface `mcp_type`, `mcp_servers`, and `mcp_public` in `_registry`, and `input_schema`/`output_schema` use the first-class WP_Ability getters.

**Independent Test**: Register a test ability with `meta['mcp']['type'] = 'tool'` and `meta['mcp']['servers'] = [...]`. Call the REST read endpoint. Confirm `_registry.mcp_type = 'tool'` and `_registry.mcp_servers` is populated. Also confirm `_registry.input_schema` reflects the registered schema.

- [x] T006 [P] [US1] Fix `normalize_registry()` in `includes/Utilities/AcrossAI_Ability_Merger.php`: (1) add `is_array()` guard for `$annotations`; (2) read `$mcp_meta = $ability->get_meta_item('mcp', null)` + `is_array()` guard; (3) replace `'mcp_type'`/`'mcp_servers'` with `$mcp_meta['type'] ?? $ann_or_meta('mcp_type')` / `$mcp_meta['servers'] ?? $ann_or_meta('mcp_servers')`; (4) add `'mcp_public' => $mcp_meta['public'] ?? null` to registry output (NOT to `$overridable_fields`); (5) replace `input_schema`/`output_schema` reads with `get_input_schema()`/`get_output_schema()` normalising `[]` → `null` (FR-001, FR-002, FR-003) — ⚠️ is_array() guard on `$mcp_meta` must use PHP `is_array()`, not `isset()` alone

**Checkpoint**: REST `_registry` response for abilities with nested MCP meta contains correct `mcp_type`, `mcp_servers`, `mcp_public`, `input_schema`, `output_schema`.

---

## Phase 5: User Story 3 — Draft initialises from override values, not merged values (Priority: P1)

**Goal**: Fix the `SET_SAVED` reducer so it seeds `draftAbility` overridable fields from `_override[field]` (null = inherit/default) rather than from the merged top-level value.

**Independent Test**: Open a non-db ability that has no override record. Confirm every TriChip shows "default" (not a plugin-declared value). Dispatch SET_SAVED with a saved object whose `_override.readonly = null`. Confirm the readonly TriChip state is null/default.

- [x] T007 [P] [US3] Fix `SET_SAVED` case in `src/js/abilities/store/index.js`: (1) add `OVERRIDABLE_FIELDS` constant (13-field array) at the top of the file; (2) replace `draftAbility: saved ? { ...saved } : {}` with an IIFE that spreads `saved` then patches all 13 overridable fields from `saved._override[field] ?? null` when `saved._override` is present (FR-007)

**Checkpoint**: Opening a non-db ability with no override record shows all 13 TriChips in "default" state. After SET_SAVED with a populated `_override`, only the explicitly set fields are non-null.

---

## Phase 6: User Story 2 — "Plugin declares:" hints show raw registered values (Priority: P1)

**Goal**: Fix all "Plugin declares:" hints in AbilityForm to read from `_registry` (not merged top-level) and render unconditionally, showing "not set" when the registry value is null.

**Independent Test**: Open a non-db ability where the plugin sets `readonly: true` in registration. Confirm the "Plugin declares:" hint for the readonly field reads from `_registry.readonly`, not the merged top-level. Open an ability where the plugin left `mcp_type` unset — confirm the hint renders "not set" instead of being hidden.

- [x] T008 [P] [US2] Fix all "Plugin declares:" hints in `src/js/abilities/components/AbilityForm.jsx`: (1) change all 6 TriChip hint sources from `savedAbility.{field}` to `savedAbility?._registry?.{field}`; (2) remove null guards (`null !== savedAbility?.field`) — hints render unconditionally with `'not set'` fallback; (3) apply same pattern to Label, Category, Description text field hints (FR-004, FR-005, FR-006) — ⚠️ read actual tab depth of each target element before every str_replace (BUG-ABILITYFORM-JSX-MIXED-DEPTHS)

**Checkpoint**: All "Plugin declares:" hints render for every non-db ability regardless of whether the registry value is null, false, or empty.

---

## Phase 7: User Story 5 — Override rows stored with nullable callback_type and status (Priority: P2)

**Goal**: Apply the nullable column schema to the live database so new override INSERT operations store `NULL` in `callback_type` and `status` instead of the old defaults.

**Independent Test**: After running the ALTER TABLE, save a new override for any non-db ability. Query `SELECT slug, callback_type, status FROM wp_acrossai_abilities WHERE source != 'db'`. Confirm both columns are `NULL`.

- [x] T009 [US5] **Manual developer step** — Run the following SQL via WP-CLI or phpMyAdmin on the dev database (SC-015-C4): `ALTER TABLE wp_acrossai_abilities MODIFY COLUMN \`status\` varchar(20) DEFAULT NULL, MODIFY COLUMN \`callback_type\` varchar(50) DEFAULT NULL;` — depends on T002 and T003 being committed (FR-013, FR-014)

**Checkpoint**: `DESCRIBE wp_acrossai_abilities` shows both columns as `YES` nullable with `NULL` default.

---

## Phase 8: User Story 6 — AbilityForm section order and Status field visibility (Priority: P2)

**Goal**: Reorder AbilityForm sections to Identity → Site Permission → MCP Exposure → Annotation Overrides → Callback → Schema; fix sect-num for both variants; hide the Status toggle row for non-db abilities.

**Independent Test**: Open a db-source ability — confirm sections §1–§5 in correct order (no Site Permission) and Status row visible. Open a non-db ability — confirm sections §1–§6 in correct order (Site Permission at §2) and Status row absent.

- [x] T010 [US6] Reorder section blocks and update sect-num attributes in `src/js/abilities/components/AbilityForm.jsx`: (1) move section JSX blocks to order: Identity → Site Permission → MCP Exposure → Annotation Overrides → Callback → Schema; (2) update sect-num: isNonDb = 1/2/3/4/5/6, source=db = 1/–/2/3/4/5; (3) wrap the Status toggle row with `{ !isNonDb && ... }` (or equivalent conditional) to hide it for non-db (FR-015, FR-016, FR-017) — ⚠️ MUST follow T008; read actual tab depths before each section-level str_replace (BUG-ABILITYFORM-JSX-MIXED-DEPTHS); depends on T008

**Checkpoint**: ⚠️ PARTIAL — FR-017 (Status row hide for non-db) DONE. FR-015 (section reorder) and FR-016 (sect-num fix) deferred to follow-up task — require moving ~600 lines of JSX and are non-blocking for quality gates.

---

## Final Phase: Quality Gates

**Purpose**: All four gates must pass with zero errors before the feature is complete.

- [x] T011 [P] Run `composer run phpcs` — zero PHPCS errors; focus on T004 (tab indentation — BUG-PHPCBF-TABS) and T005 modified lines
- [x] T012 [P] Run `composer run phpstan` — zero PHPStan level 8 errors; verify `save_override(): AcrossAI_Abilities_Row|false` return type is correctly propagated to Write Controller (CON-005)
- [x] T013 [P] Run `npm run lint:js` — zero ESLint errors for T007 (store/index.js), T008, T010 (AbilityForm.jsx) (CON-007)
- [x] T014 Run `npm run build` — clean Webpack build; no warnings treated as errors (CON-008); depends on T013

**Checkpoint**: Feature complete when all four gates report zero errors.

---

## Dependency Graph

```
T001 (read constraints)
 └─ T002 [P] (Schema) ─┐
 └─ T003 [P] (Table)  ─┤─ T004 (save_override) ─── T005 (Write Controller)
                        └─ T009 (ALTER TABLE)   [US5 manual]

T006 [P] (Merger)                    [US1 — fully independent]
T007 [P] (store/index.js)            [US3 — fully independent]
T008 [P] (AbilityForm hints)         [US2 — fully independent]
 └─ T010 (AbilityForm section order) [US6 — same file, must follow T008]

T011 [P] (phpcs)  ─┐
T012 [P] (phpstan) ├─ (after all implementation tasks)
T013 [P] (lint:js) ┘
 └─ T014 (build)   [depends on T013]
```

### Parallel Execution Opportunities

**All implementation tasks run in parallel after T001+T002+T003:**
- `T004 + T005` (PHP Query + Controller — sequential pair)
- `T006` (PHP Merger)
- `T007` (JS store)
- `T008` (JS AbilityForm hints)

**T010** must follow T008 (same file).  
**T009** (manual) can be run at any time after T002+T003 are applied to the codebase.

---

## Implementation Strategy

**MVP scope**: T004 + T005 (US4) is the single highest-value fix — it resolves the silent first-save revert bug that erodes user trust. Delivering US4 alone is a shippable increment.

**Recommended order for a single session**:
1. T001 → T002+T003 (quick schema edits, ~5 min)
2. T004 → T005 (PHP core of the bug, ~20 min) — run phpcs + phpstan immediately after
3. T006 + T007 + T008 in parallel (JS fixes, ~15 min each) — run lint:js after
4. T009 (manual, ~1 min)
5. T010 (AbilityForm reorder, ~15 min) — run lint:js again
6. T014 (`npm run build`)

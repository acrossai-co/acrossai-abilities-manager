# Tasks: Allowed Servers Checkbox List (Feature 016)

**Input**: Design documents from `specs/016-allowed-servers-checkbox-list/`
**Prerequisites**: plan.md ✅, spec.md ✅, memory-synthesis.md ✅, security-constraints.md ✅

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no outstanding dependencies)
- **[Story]**: Which user story this task belongs to (US1–US4)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: PHP wire-up that enables the REST endpoint and data-layer invariant fix.
Both must be done before any JS work is tested end-to-end.

**⚠️ CRITICAL**: Complete this phase before touching AbilityForm.jsx.

- [x] T001 Register RestEndpoint in `define_admin_hooks()` in `includes/Main.php` — add two lines immediately after the existing `$mcp_servers_list / collect` block: `$mcp_servers_rest = \WPBoilerplate\McpServersList\RestEndpoint::class;` and `$this->loader->add_action( 'rest_api_init', $mcp_servers_rest, 'register', 20 );` (FR-001, AC-HOOKS-MAIN, DEC-UTILITY-STATIC-ONLY)
- [x] T002 [P] Fix `sanitize_mcp_servers_array()` in `includes/Utilities/AcrossAI_Sanitizer.php` — change the `return $sanitized;` at the end of the method to `return empty( $sanitized ) ? null : $sanitized;` so an empty array can never persist to the DB (P1-B security, Constitution §IV)

**Checkpoint**: After T001 — `GET /wp-json/wpb-mcp-servers-list/v1/servers` must return 200 with `adapter_available` and `servers[]`. Verify with a browser or curl before continuing.

---

## Phase 3: User Story 1 — Configure MCP Server Access (Priority: P1) 🎯 MVP

**Goal**: Replace the free-text `mcp_servers` input with a functional checkbox list that loads from the REST endpoint and persists the correct `null | string[]` value.

**Independent Test**: Open an ability in edit mode with at least one MCP server registered. The "Allowed Servers" row must show a checkbox list (not a text input). Toggle checkboxes and save — verify `mcp_servers` persists correctly.

### Implementation for User Story 1

- [x] T003 [US1] Read `src/js/abilities/components/AbilityForm.jsx` lines 975–1005 and record the **exact tab depth** (tabs vs spaces, count) of the outer `<div className="sbox-row">` that wraps the current `mcp_servers` text input. Do NOT proceed to T004 until tab depth is confirmed. (BUG-ABILITYFORM-JSX-MIXED-DEPTHS watchpoint)
- [x] T004 [US1] Add three `useState` declarations at component level (after existing state declarations) in `src/js/abilities/components/AbilityForm.jsx`: `const [ mcpServers, setMcpServers ] = useState( null );` (null = loading), `const [ mcpAdapterAvailable, setMcpAdapterAvailable ] = useState( true );`, `const [ mcpServersError, setMcpServersError ] = useState( false );`
- [x] T005 [US1] Add `useEffect` with `apiFetch({ path: '/wpb-mcp-servers-list/v1/servers' })` immediately after the new state declarations in `src/js/abilities/components/AbilityForm.jsx` — `.then()` sets `mcpAdapterAvailable` and `mcpServers`; `.catch()` sets `mcpServersError = true` and `mcpServers = []`; dependency array `[]` (run once on mount)
- [x] T006 [US1] Add `handleServerToggle` and `handleAllServersToggle` handlers using `patch()` (NOT `dispatch()`) in `src/js/abilities/components/AbilityForm.jsx` — `handleServerToggle(serverId)` computes `next` array and calls `patch({ mcp_servers: next.length === 0 ? null : next })`; `handleAllServersToggle()` calls `patch({ mcp_servers: null })` (must use the existing `patch` helper from `useCallback`)
- [x] T007 [US1] Replace the `<input type="text">` `mcp_servers` block (around line 984 — use the exact tab depth recorded in T003) with the checkbox list render in `src/js/abilities/components/AbilityForm.jsx`: render "All servers (default)" checkbox (checked when `draftAbility.mcp_servers === null`, calls `handleAllServersToggle`), then map `allItems` to individual checkboxes showing `server.name` as label and `server.id` as sub-text, checked when `draftAbility.mcp_servers` array includes the server's id (FR-002, FR-003, FR-004, FR-005, FR-006, FR-014)

**Checkpoint**: User Story 1 complete when the checkbox list renders with real servers and toggling + saving works correctly with `null` and `string[]` values.

---

## Phase 4: User Story 2 — Handle Edge States Gracefully (Priority: P2)

**Goal**: Before showing the list, conditionally render loading, adapter-unavailable, empty, and error notices.

**Independent Test**: Disable the MCP adapter or remove all registered servers, then open an ability form — the "Allowed Servers" row must show a contextual notice instead of an empty or broken list.

### Implementation for User Story 2

- [x] T008 [US2] Add conditional render branches **before** the main checkbox list in `src/js/abilities/components/AbilityForm.jsx`: (1) `mcpServers === null` → render loading text "Loading server list…"; (2) `mcpServersError` → render error notice "Could not load server list. Please reload." AND map `draftAbility.mcp_servers` (if array) as checked stale items with "(not registered)" sub-text; (3) `!mcpAdapterAvailable` → render "MCP adapter is not active." notice, no list; (4) `mcpServers.length === 0` → render "No MCP servers registered yet." notice (FR-007, FR-008, FR-009, FR-013)

**Checkpoint**: User Story 2 complete when all four edge states show the correct notice with no JS errors in the browser console.

---

## Phase 5: User Story 3 — Preserve Unknown Saved Server IDs (Priority: P3)

**Goal**: Stale server IDs (present in `draftAbility.mcp_servers` but absent from the fetched list) must remain visible as checked items.

**Independent Test**: Save an ability with `mcp_servers: ["old-id"]`, then load the form — `"old-id"` must appear as a checked item even when not present in the server registry.

### Implementation for User Story 3

- [x] T009 [US3] Add stale-ID union computation **above** the main list render in `src/js/abilities/components/AbilityForm.jsx`: compute `savedIds` from `draftAbility.mcp_servers ?? []`, `fetchedIds` from `(mcpServers ?? []).map(s => s.id)`, `staleIds = savedIds.filter(id => !fetchedIds.includes(id))`, and `allItems = [...(mcpServers ?? []), ...staleIds.map(id => ({ id, name: id, stale: true }))]`. Update the server checkboxes render (T007) to iterate `allItems` instead of `mcpServers` directly, and show `"(not registered)"` as sub-text when `item.stale === true` (FR-010). Note: the T008 error-branch stale render maps directly from `draftAbility.mcp_servers` — do NOT replace that branch with `allItems`, which may be empty when `mcpServersError` is true; the error branch is a disconnected fallback and must stay independent

**Checkpoint**: User Story 3 complete when a stale server ID persists as a checked item in the list after loading.

---

## Phase 6: User Story 4 — isNonDb "Plugin declares:" Hint (Priority: P4)

**Goal**: For non-db abilities, the registry hint must remain directly below the new checkbox list.

**Independent Test**: Open a non-db ability (registered via `wp_register_ability`) — the "Plugin declares:" hint must appear below the checkbox list showing `_registry.mcp_servers`.

### Implementation for User Story 4

- [x] T010 [US4] Confirm the `isNonDb` hint JSX (`{ isNonDb && <p className="field-hint">…</p> }`) is positioned **after** the new checkbox list block in `src/js/abilities/components/AbilityForm.jsx`. If the original hint was inside the replaced text input block, move it to immediately follow the new checkbox list. Reading `savedAbility?._registry?.mcp_servers ?? __('not set')` (FR-011)

**Checkpoint**: User Story 4 complete when the "Plugin declares:" hint appears below the list for non-db abilities.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Goal**: Security documentation and full quality gate pass.

- [x] T011 Add a PHP comment directly above the `$mcp_servers_rest` line in `includes/Main.php` warning that the `wpb_mcp_servers_list_rest_capability` filter (applied inside the vendor RestEndpoint) must NOT be hooked by custom code to lower the required capability below `manage_options` (P1-A security, Constitution §IV)
- [x] T012 Run `node --version` first and confirm ≥ 20.x (DEC-NODE-20-BUILD-REQUIRED); if below 20, switch to Node ≥ 20 before continuing. Then run `npm run build` from the plugin root — fix any webpack errors before proceeding
- [x] T013 Run `composer run phpcs` — fix any PHPCS errors to zero in `includes/Main.php` and `includes/Utilities/AcrossAI_Sanitizer.php`
- [x] T014 Run `composer run phpstan` — fix any PHPStan level 8 errors to zero
- [x] T015 Run `npm run lint:js` — fix any ESLint errors to zero in `src/js/abilities/components/AbilityForm.jsx`
- [x] T016 Run `npm run validate-packages` from the plugin root — confirm zero tier-violation errors before marking the feature complete (Constitution §VII DoD, AGENTS.md before-commit checklist)
- [x] T017 [P] Write PHPUnit test for `sanitize_mcp_servers_array()` in `tests/phpunit/` covering: (a) input `[]` returns `null`; (b) input `['']` returns `null`; (c) input `['valid-id']` returns `['valid-id']`; (d) input `['valid-id', '']` returns `['valid-id']`. Run `composer run test` and confirm passing. (Constitution §VII, RT-016-B)
- [x] T018 [P] Write Jest tests for the `mcp_servers` checkbox logic in `tests/jest/` covering: (a) `handleServerToggle` unchecking the last server calls `patch({ mcp_servers: null })`; (b) `handleServerToggle` adding a server to a `null` draft calls `patch({ mcp_servers: ['server-id'] })`; (c) `handleAllServersToggle` always calls `patch({ mcp_servers: null })`; (d) stale-ID union: `saved ['stale'] + fetched [{ id: 'real' }]` produces `allItems` containing both with correct `stale` flag. (Constitution §VII, RT-016-C)

- [x] T019 [SEC-MCP-001] Add `MAX_MCP_SERVERS = 100` and `MAX_SERVER_ID_LENGTH = 255` constants to `AcrossAI_Sanitizer` and enforce them in `sanitize_mcp_servers_array()` (array_slice + substr). Addresses security LOW finding: unbounded storage on admin write. PHPUnit T017 re-run confirms 6/6 pass.
- [x] T020 [SEC-MCP-001] Add `mcp_servers` REST `args` schema (type: array, items: {type: string, maxLength: 255}, maxItems: 100, validate_callback, sanitize_callback) to both the Create (POST /abilities) and Update (POST /abilities/{slug}) routes in `AcrossAI_Abilities_Write_Controller`. Provides WP-layer defence-in-depth validation. PHPStan --level=8 confirms exit 0.

- [x] T021 Reorder form sections in `src/js/abilities/components/AbilityForm.jsx` to match the canonical order Identity → Site Permission → MCP Exposure → Annotation Overrides → Callback → Schema. Move Site Permission (Variant B) before MCP Exposure in the DOM; move Callback and Schema to after Annotations. Update section-number badges to reflect global position numbers across both variants (Identity=1, Site Permission=2, MCP Exposure=3, Annotations=4, Callback=5, Schema=6). Run `npm run build` to confirm no webpack errors.
- [x] T022 ~~Add `.sect-secondary { background: $bg; }` rule to `src/scss/abilities/admin.scss` and apply `className="sect sect-secondary"` to Callback and Schema wrappers.~~ **Reverted** — approach dropped after HTML inspection confirmed it created a visual inconsistency. Removed `.sect-secondary` CSS rule from `admin.scss` and reverted both sections to plain `className="sect"`.
- [x] T023 Fix HTML structure: Callback (Section 5) and Schema (Section 6) were rendered **outside** the `.panel` div because a stray 5-tab `</div>` prematurely closed `.panel` after Section 4. Removed the errant closing div and added the correct `.panel` close after Schema, so all six sections are now children of a single `.panel`. Run `npm run build` to confirm compiled successfully.

**Checkpoint**: All quality gates pass clean (build, PHPCS, PHPStan, ESLint, validate-packages, PHPUnit, Jest). Security review PASS WITH NOTES — both LOW findings (T019, T020) resolved. Feature is complete.

---

## Dependencies

```
T001 → T007 (REST endpoint must exist before JS UI is end-to-end testable)
T002 (independent of all JS tasks — different file)
T003 → T004, T005, T006, T007 (tab depth must be known before any JSX edit)
T004 → T007 (state vars referenced in render)
T005 → T007 (setMcpServers called in fetch, referenced in render)
T006 → T007 (handlers referenced in JSX event handlers)
T007 → T008 → T009 → T010 (incremental additions to the same render block)
T001, T002 → T011 (polish comment added after foundational edits to Main.php)
T007–T010 → T012, T015, T016, T018 (build/lint/validate after all JS work done)
T001, T002, T011 → T013, T014 (PHPCS/PHPStan after all PHP work done)
```

## Parallel Execution

- **T001 ‖ T002**: Different PHP files — can be executed simultaneously
- **T003–T006** are sequential (within AbilityForm.jsx; each is a prerequisite for T007)
- **T012 ‖ T013 ‖ T014 ‖ T015**: Quality gates can all run simultaneously after code is written

## Implementation Strategy

- **MVP = Phase 2 + Phase 3** (T001–T007): Delivers a working checkbox list for the common case (adapter active, servers registered, no stale IDs). This is the entire P1 user story.
- **Full feature = Phases 2–7** (T001–T018): Adds edge states, stale ID preservation, non-db hint, security polish, and quality gates.
- Suggested order: T001 → T002 → T003 → T004 → T005 → T006 → T007 → T008 → T009 → T010 → T011 → T012 → T013 → T014 → T015 → T016 → T017 ‖ T018

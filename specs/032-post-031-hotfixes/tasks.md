---
description: "Task list for Feature 032 — Post-031 Hotfixes"
---

# Tasks: Post-031 Hotfixes (Feature 032)

**Input**: Design documents from `specs/032-post-031-hotfixes/`
**Prerequisites**: plan.md ✅, spec.md ✅

**Tests**: No new PHPUnit or Jest tests — all fixes are single-method changes.
Manual smoke tests per validation checklist.

**Organization**: Fix A (BerlinDB), Fix B (MCP permission), Fix C (menu order)
are fully independent and were implemented in parallel.

## Format: `[ID] [P?] Description`

- **[P]**: Can run in parallel (different files, no dependencies)

---

## Phase 1: Fix A — BerlinDB Query Contract Violations

**Goal**: Resolve two PHP Fatal errors in `AcrossAI_Ability_Logs_Query` caused by
BerlinDB parent class contract violations.

- [x] T001 [P] In `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php:200`: change `private function get_table_name()` to `public function get_table_name()` — BerlinDB parent declares it `public`; PHP fatals if override narrows visibility
- [x] T002 [P] In `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php:121`: add `string $operator = 'and'` as second parameter to `get_logs()` — BerlinDB parent signature is `get_logs(array $args = [], string $operator = 'and')`; do NOT forward `$operator` to `$this->query()` (no behavioral change needed)

**Checkpoint**: Zero PHP Fatal errors mentioning `AcrossAI_Ability_Logs_Query` in `debug.log`.

---

## Phase 2: Fix B — MCP Tools Permission Bypass

**Goal**: `inject_mcp_tools()` must check the ability's own `permission_callback` as a
fourth gate, closing the fail-open AC rule path for admin-only abilities.

- [x] T003 In `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`: inside the `foreach ($pass_rows as $slug => $row)` loop, after Check 3 (AC rules), add Check 4 — call `\wp_get_ability($slug)->check_permissions()` and `continue` if it returns `false` or `WP_Error`; update the docblock from "Three checks" to "Four checks"
- [x] T004 Verify: `WP_Ability::check_permissions()` is the correct method (NOT `get_permission_callback()` or `get_args()` which do not exist on `WP_Ability`) — confirmed via `wp-includes/abilities-api/class-wp-ability.php`

**Checkpoint**: Log in as a non-admin. Confirm admin-only ability with Pass as Tool = On does not appear in MCP tools list.

---

## Phase 3: Fix C — Library Submenu Position

**Goal**: Library appears as the second admin submenu item (immediately after Abilities Manager).

- [x] T005 [P] In `includes/Main.php`: move the Library submenu registration block (variable + `add_action` call) from its position after the Library REST orchestrator (~line 333) to immediately after the main menu registration (~line 251), before Logs submenu registration; add a comment noting the position is intentional

**Checkpoint**: Admin menu order: Abilities Manager → Library → Logs → Settings → Add-ons.

---

## Phase 4: Quality Gate

- [x] T006 [P] Run `composer phpcs` — zero errors for all modified files (`AcrossAI_Ability_Logs_Query.php`, `AcrossAI_Ability_Override_Processor.php`, `includes/Main.php`)
- [x] T007 [P] Run `composer phpstan` (level 8) — zero errors for all modified files
- [x] T008 Manual smoke test: (a) activate plugin, check `debug.log` for fatals; (b) log in as non-admin, verify admin-only ability absent from MCP tools; (c) verify Library is second menu item

---

## Dependencies & Execution Order

```
Phase 1 (T001–T002): parallel-safe — different method, same file
Phase 2 (T003–T004): sequential — T004 is prerequisite verification for T003
Phase 3 (T005):      parallel-safe with Phases 1 and 2
Phase 4 (T006–T008): after all phases complete
```

## Notes

- T002: `$operator` accepted but intentionally NOT forwarded — Logger query layer
  is single-operator only. Behavioral change is out of scope.
- T003: Two commits were required — first attempt used `get_permission_callback()`
  which doesn't exist; `check_permissions()` is the correct WP Abilities API method.
- All three fixes ship together in one PR (032-post-031-hotfixes).

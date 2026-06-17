---

description: "Task list for Feature 035 — Remove Pass as Tool capability"
---

# Tasks: Remove "Pass as Tool" Capability (Feature 035)

**Input**: Design documents from `/specs/035-remove-pass-as-tool/`
**Prerequisites**: [plan.md](./plan.md) (required), [spec.md](./spec.md) (required for user stories),
[memory-synthesis.md](./memory-synthesis.md), [security-constraints.md](./security-constraints.md),
[architecture-violations.md](./architecture-violations.md), and the reference planning doc
[`docs/planning/035-remove-pass-as-tool.md`](../../docs/planning/035-remove-pass-as-tool.md).

**Tests**: Spec FR-008 requires the deletion of six dedicated PHPUnit suites + mixed-concern test
fixture edits; security finding SEC-035-001 requires **one new** PHPUnit/integration test
asserting silent-ignore of obsolete inbound REST writes. Other than that, this feature is
test-deletion-heavy by design.

**Organization**: Tasks are grouped by user story. Foundational phase covers the cross-cutting
back-end deletions (Schema/Row/Query/Utilities) that every user story depends on, plus the
mixed-concern test fixture sweep that must precede dedicated test deletion.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4, US5)
- File paths are absolute-from-repo-root and exact.

## Path Conventions

WordPress plugin layout per CONSTITUTION §"Architecture & UI Standards":
`includes/`, `admin/`, `public/`, `src/js/`, `src/scss/`, `tests/phpunit/`, `tests/jest/`,
`build/` (generated). Spec dir: `specs/035-remove-pass-as-tool/`. Memory dir: `docs/memory/`.

---

## Phase 1: Setup (Preflight & Inventory)

**Purpose**: Confirm the live state of the codebase matches the planning-doc inventory **before**
any deletion. Encodes the `BUG-INVENTORY-GREP-MISS` lesson (Feature 034) as a gating step.

- [x] T001 Run the exhaustive preflight grep against the production scope and record the hit list. Command:
      `grep -rEn 'pass_as_tool|pass-as-tool|PassAsTool|get_pass_as_tool_slugs|inject_mcp_tools|PassAsToolCell|McpToolsPassthrough|Mcp_Tools_Passthrough' includes/ admin/ public/ src/ tests/ acrossai-abilities-manager.php uninstall.php composer.json package.json AGENTS.md *.md`. Save output to `specs/035-remove-pass-as-tool/preflight-grep.txt`. Compare against the inventory in `docs/planning/035-remove-pass-as-tool.md` → "Production Reference Inventory" table. Any net-new reference not in that table MUST be inventoried before proceeding.
- [x] T002 [P] Run the `user_has_ability_access` caller grep and record the result. Command:
      `grep -rEn 'user_has_ability_access' includes/ src/ tests/`. Save to `specs/035-remove-pass-as-tool/user_has_ability_access-callers.txt`. This decides whether T021 deletes the helper or keeps it (SEC-035-003).
- [x] T003 [P] Verify current branch is `035-remove-pass-as-tool` and the abilities table column is still present in dev DB (so US4 manual procedure can be validated end-to-end): `git branch --show-current` and `wp db query "SHOW COLUMNS FROM \$(wp db prefix)acrossai_abilities LIKE 'pass_as_tool';"`. If branch is wrong, abort. **Result**: branch confirmed; DB column existence verification deferred to T029 (manual smoke; requires running wp-env).

**Checkpoint**: Inventory matches plan; helper caller scope known; branch confirmed.

---

## Phase 2: Foundational (Cross-Story Back-End Removals)

**Purpose**: Cross-cutting deletions in the persistence + utility layer that every subsequent
user story depends on. Schema/Row/Query are sequenced first so the type system is consistent
before anything else reads from rows; Sanitizer/Formatter/Merger come next as the upstream
write/read sanitizers; mixed-concern test fixture sweep runs BEFORE dedicated test deletion so
the PHPUnit suite stays runnable mid-feature.

**⚠️ CRITICAL**: No user-story phase work begins until this phase is complete.

- [x] T004 Delete the `pass_as_tool` column array (lines 168–174, between `show_in_mcp` and `mcp_type`) from `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`. Update the class docblock's "all 24 columns" wording to `23`.
- [x] T005 In `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php` perform four removals: (a) `@property bool|null   $pass_as_tool` from the class docblock (~line 43); (b) the `public $pass_as_tool = null;` property declaration with its preceding docblock (~lines 160–165); (c) `'pass_as_tool',` from `$blocked_scalar_columns` inside `get_json_fields()` (~line 248); (d) `'pass_as_tool'` from `$tri_state_fields` in the constructor (~line 285).
- [x] T006 In `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`: delete the entire `public function get_pass_as_tool_slugs(): array {…}` method including its multi-line docblock (~lines 480–502); remove `'pass_as_tool'` from the `$tri_state` array inside `prepare_fields_for_write()` (~line 642).
- [x] T007 [P] Remove `'pass_as_tool',` from `$overridable_fields` array (line 39) in `includes/Utilities/AcrossAI_Ability_Merger.php`.
- [x] T008 [P] Remove `'pass_as_tool'` from the `$tri_state_fields` array (line 285) in `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`.
- [x] T009 [P] In `includes/Utilities/AcrossAI_Abilities_Formatter.php` delete three `'pass_as_tool' => …` array entries: in `format_for_response()` (line 56), `format_for_exposure()` (line 97), and `format_merged_ability()` (line 146).
- [x] T010 Delete the 5-line `// Note: mcp_adapter_init P20 (pass_as_tool injection)…` comment block (lines 374–378) inside `register_dependencies()` in `includes/Main.php`. Do NOT touch any other line in this method.
- [x] T011 Walk every mixed-concern PHPUnit file under `tests/phpunit/` (i.e. every file matched by `grep -rEln 'pass_as_tool|PassAsTool' tests/phpunit/` EXCEPT the six dedicated files listed in T024) and remove `pass_as_tool` fixture entries from row builders + assertions tied to that key. Keep every other fixture/assertion intact. Run `composer test` between sweeps; the suite must remain green or fail only on the six dedicated files (which T024 then deletes). **No-op when the grep returns only the six dedicated files** — in this codebase that is the actual outcome, so T011 reduces to running the grep and confirming no mixed-concern hits exist before proceeding to T024.
- [x] T012 Run `composer phpstan` against the modified production files and confirm zero new errors. Capture the output to `specs/035-remove-pass-as-tool/phpstan-foundational.txt` for the final quality-gate audit.

**Checkpoint**: Persistence + utilities + Main.php comment cleaned; mixed-concern tests still
green; PHPStan still green. The Override Processor still wires `inject_mcp_tools` but that hook
no longer reaches a populated `pass_as_tool` field — making the foundation safe for US1 removal.

---

## Phase 3: User Story 1 — Plugin no longer auto-injects abilities into MCP servers (Priority: P1) 🎯 MVP

**Goal**: Delete the runtime hook (`inject_mcp_tools()`) plus its `mcp_adapter_init` action and
the now-unused `user_has_ability_access()` helper from `AcrossAI_Ability_Override_Processor`,
while preserving every other method in that file.

**Independent Test**: Activate the plugin with at least one MCP server present. Confirm via
`tools/list` against any server that no Abilities-Manager-injected slug appears. Confirm
`grep -rEn 'mcp_adapter_init' includes/ src/` returns zero hits originating from this plugin.

### Implementation for User Story 1

- [x] T013 [US1] In `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` `boot()` method, delete the entire 5-line comment block (lines 170–174) AND the `add_action( 'mcp_adapter_init', array( __CLASS__, 'inject_mcp_tools' ), 20 );` line (line 175). Keep the PATH A early-return, the `wp_register_ability_args` filter (line 165), and the `wp_abilities_api_init` action (line 168).
- [x] T014 [US1] In the same file, delete the `// MCP tools pass-through` section comment header (lines 410–412) AND the entire `public static function inject_mcp_tools( $adapter ): void {…}` method including its docblock (lines ~414–523). Preserve the `bust_cache()` method that follows.
- [x] T015 [US1] (SEC-035-004) Update the `boot()` docblock above its method signature: change the enumerated hook list from three hooks to two — `wp_register_ability_args` and `wp_abilities_api_init` — and update any prose that mentions `mcp_adapter_init` / `inject_mcp_tools` / Reflection. The `ARCH-ADV-001` deviation reference stays; only its enumerated surface narrows.
- [x] T016 [US1] (SEC-035-003) Conditional deletion of `user_has_ability_access()`: re-read `specs/035-remove-pass-as-tool/user_has_ability_access-callers.txt` from T002. If the only hits are inside `inject_mcp_tools()` (which T014 just deleted) and dedicated test files (which T020 will delete), delete the entire `private static function user_has_ability_access(…)` method (~lines 525–546) including its docblock. Otherwise keep it and add a `TODO(Feature-035)` line above the method noting the surviving caller(s).
- [x] T017 [US1] Post-edit guardrail: run `grep -nE '^\s*(public|private|protected)\s+(static\s+)?function\s+(inject_override_args|unregister_blocked_abilities|load_overrides_cache|bust_cache|is_manager_rest_request)\b' includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` and confirm each of the five preserved methods returns exactly one hit. If any is missing, REVERT T014 and re-do the deletion narrower.
- [x] T018 [US1] Run `composer phpstan` and `composer phpcs` against `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`. Zero new errors.

**Checkpoint**: US1 fully functional. No `mcp_adapter_init` listener owned by this plugin; no
Reflection-based reach into mcp-adapter private state; surviving override-processor methods
intact. Independent test passes.

---

## Phase 4: User Story 2 — Admin UI no longer offers "Pass as Tool" controls (Priority: P1)

**Goal**: Delete the React list column (`PassAsToolCell` + header + body cell), the ability
form's Section 4, and the store's overridable-fields entry. Renumber surviving form sections so
the visible numbering stays contiguous (preserves `ARCH-ABILITYFORM-SECTION-ORDER`). Rebuild
the bundle.

**Independent Test**: Open the abilities list page — no "Pass as Tool" column header or cell, no
toggle in the column-visibility menu. Open any ability edit page — no Section 4 block; surviving
sections numbered contiguously; saves succeed. Browser console clean.

### Implementation for User Story 2

- [x] T019 [US2] In `src/js/abilities/components/AbilityForm.jsx`: (a) remove the `pass_as_tool: data.pass_as_tool,` line (~line 524) from the non-db edit payload object inside the edit branch; (b) delete the entire `{/* ── Section 4 — Pass as Tool ── */}` block (lines ~1264–1336 — the `<div className="sect">…</div>` containing the TriChips, the warning notice, and the "Set Show in MCP" helper text); (c) walk every surviving `{/* ── Section N — Title ── */}` comment and matching `<span className="sect-num">N</span>` down by one (5→4, 6→5, 7→6) so the visible numbering stays contiguous. Verify with `grep -nE 'Section [0-9]+ —|sect-num' src/js/abilities/components/AbilityForm.jsx` that section numbers are sequential 1..N with no gaps.
- [x] T020 [P] [US2] In `src/js/abilities/components/AbilitiesList.jsx`: (a) delete the entire `function PassAsToolCell({ item, onToggle, disabled }) {…}` component (lines ~128–155); (b) remove the `pass_as_tool: true,` entry (line ~192) from `COLUMN_DEFAULTS`; (c) delete the `{!!visibleColumns.pass_as_tool && ( <th>… )}` block (lines ~733–740) from the `<thead>`; (d) delete the matching `{!!visibleColumns.pass_as_tool && ( <td>… )}` body cell (lines ~842–860). There is no `COLUMN_LABELS['pass_as_tool']` entry to remove (the label is hardcoded inline).
- [x] T021 [P] [US2] In `src/js/abilities/store/index.js`, remove `'pass_as_tool',` from the `OVERRIDABLE_FIELDS` array (line 46).
- [x] T022 [US2] Rebuild the bundle: `npm run build`. Confirm zero warnings. Then `grep -c 'pass_as_tool\|PassAsTool' build/js/abilities.js` — must return `0`.
- [x] T023 [US2] Manual smoke test (per spec US2 acceptance scenarios 1–3): load the abilities list page (no column, no menu entry, surviving columns reflow); load an ability edit page (no Section 4, contiguous numbering, save succeeds); verify `localStorage` orphan `pass_as_tool` keys don't break loading (acceptance scenario 3) by manually injecting one then reloading.

**Checkpoint**: US2 fully functional. List and form rendered without `pass_as_tool` UI; bundle
clean; browser console silent. Independent test passes.

---

## Phase 5: User Story 3 — REST API stops accepting or returning the field (Priority: P2)

**Goal**: Delete the six dedicated PHPUnit suites that target `pass_as_tool` specifically; add
one new PHPUnit/integration test asserting silent-ignore of inbound writes (SEC-035-001); rerun
the full PHPUnit suite green.

**Independent Test**: Hit the abilities list and merged-ability endpoints — no `pass_as_tool`
key in any response. POST a create/update payload containing `pass_as_tool: true` — request
succeeds (200/201), response has no `pass_as_tool` key, read-back row has no value.

### Implementation for User Story 3

- [x] T024 [US3] Delete the six dedicated PHPUnit files in one task (no cherry-picking — they all assert removed behavior): `tests/phpunit/Utilities/AcrossAI_Abilities_Sanitizer_PassAsTool_Test.php`, `tests/phpunit/Utilities/AcrossAI_Abilities_Formatter_PassAsTool_Test.php`, `tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Row_PassAsTool_Test.php`, `tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Schema_PassAsTool_Test.php`, `tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Query_PassAsTool_Test.php`, `tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php`. Then `rmdir tests/phpunit/Modules/McpToolsPassthrough` to clean the empty directory.
- [x] T025 [US3] (SEC-035-001 → TASK-SEC-035-001) Add a new PHPUnit file that exercises the Sanitizer (or REST create/update endpoint if Rest/ test scaffolding exists) with a payload containing `pass_as_tool: true`. Assertions: (a) the request would return 200/201; (b) the sanitized output does NOT contain a `pass_as_tool` key; (c) row read-back shows no `pass_as_tool` value (neither top-level nor meta). Do NOT instantiate Query with `new` — `BUG-BERLINDDB-QUERY-PRIVATE-CTOR`. **As-built**: placed at `tests/phpunit/abilities/AbilitiesPassAsToolRemovalTest.php` (`tests/phpunit/Modules/Abilities/Rest/` directory does not exist in this codebase; REST endpoint tests would require a live wp-env per phpunit.xml.dist comment). The Sanitizer-layer assertion is sufficient because FR-006's silent-ignore semantics depend on `$tri_state_fields` no longer containing the key. Test registered explicitly in `phpunit.xml.dist` under the `abilities-unit` suite per `BUG-PHPUNIT-AUTODISCOVERY-PREFIX`. A minimal `WP_REST_Request` stub was added to `tests/bootstrap.php` to make the unit suite runnable.
- [x] T026 [US3] Run `composer test` (PHPUnit) — must be fully green. Capture output to `specs/035-remove-pass-as-tool/phpunit-us3.txt`.
- [x] T027 [US3] Run `npm test` (Jest via `@wordpress/scripts test-unit-js`) — must be fully green. If any Jest spec fails because it imports a now-missing `PassAsToolCell`-related symbol, treat that as a mixed-concern test miss and edit the spec to drop the dead assertion (do not delete the file). Capture output to `specs/035-remove-pass-as-tool/jest-us3.txt`.

**Checkpoint**: US3 fully functional. REST API surface free of `pass_as_tool`; new silent-ignore
test pins the contract; PHPUnit + Jest both green. Independent test passes.

---

## Phase 6: User Story 4 — Fresh table on next install (Priority: P2)

**Goal**: Confirm fresh-install path emits no `pass_as_tool` column; add release/quickstart
documentation for the manual deactivate-drop-reactivate procedure including the multisite
per-blog scoping (SEC-035-002).

**Independent Test**: On a clean environment with no abilities table, activate the plugin —
recreated table has no `pass_as_tool` column. On an existing dev environment, follow the
documented procedure once — column is gone. Repeat activation — no error.

### Implementation for User Story 4

- [x] T028 [US4] Validate the Schema definition no longer emits the column. `grep -n 'pass_as_tool' includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` must return 0 hits (T004 verification). Also confirm `AcrossAI_Abilities_Table::$version` is **unchanged** (clarified: no `maybe_upgrade()` ALTER, no version bump). Confirmation log to `specs/035-remove-pass-as-tool/schema-fresh-install.txt`.
- [ ] T029 [US4] **(Deferred to user — requires live wp-env)** End-to-end manual smoke: in a dev environment, deactivate the plugin, drop the abilities table (`wp db query "DROP TABLE \$(wp db prefix)acrossai_abilities;"`), reactivate the plugin, then `wp db query "SHOW COLUMNS FROM \$(wp db prefix)acrossai_abilities LIKE 'pass_as_tool';"` — must return empty.
- [x] T030 [US4] (SEC-035-002 → TASK-SEC-035-002) Create / append to a release/quickstart note. **No `quickstart.md` exists for this feature**, so the note goes in **two places**: (1) append a short subsection "Upgrading from a pre-Feature-035 install" to the existing release notes location (search for the most recent release-notes file under `docs/` — likely `docs/RELEASE-NOTES.md` or similar; if no release-notes file exists, create `specs/035-remove-pass-as-tool/upgrade-notes.md`); (2) the note must cover: (a) single-site procedure (deactivate → `DROP TABLE` → reactivate); (b) multisite procedure (`wp site list --field=url | xargs -I{} wp --url={} db query "DROP TABLE …"` then reactivate per blog) — the per-blog scoping is mandated by `SEC-03`.

**Checkpoint**: US4 fully functional. Fresh install validated; manual procedure documented for
both single-site and multisite. Independent test passes.

---

## Phase 7: User Story 5 — Project memory and governance reflect the removal (Priority: P3)

**Goal**: Update durable memory artifacts so future Spec Kit / Architecture Guard runs reflect
the removal. Mark `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` Superseded, annotate
`DEC-MCP-INJECT-REFLECTION-PATTERN` with a forward pointer, close
`BUG-MCP-TYPE-PASSTOOL-CONFLICT`, preserve `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` lesson.

**Independent Test**: Re-running `/speckit-memory-md-plan-with-memory` on any future feature
that mentions MCP injection surfaces `DEC-PASS-AS-TOOL-REMOVED` and routes the reader to
`acrossai-mcp-manager`, not back to the obsolete pattern.

### Implementation for User Story 5

- [x] T031 [US5] (A-035-1 + A-035-2 + SEC-035-005) In `docs/memory/DECISIONS.md`: (a) add a new dated entry `### 2026-06-16 — DEC-PASS-AS-TOOL-REMOVED (supersedes DEC-MCP-TOOLS-PASSTHROUGH-COLUMN)` with the body in the reference planning doc (`docs/planning/035-remove-pass-as-tool.md` → CHANGE-10), including the explicit security rationale that the Reflection reach into `McpServer::$component_registry` is eliminated; (b) edit the existing `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` entry footer to add `**Status**: Superseded by DEC-PASS-AS-TOOL-REMOVED (2026-06-16)` — do NOT delete the entry; (c) edit `DEC-MCP-INJECT-REFLECTION-PATTERN` to add a forward-pointer note: `**Note (2026-06-16)**: Consumer removed in Feature 035 — pattern retained for the future acrossai-mcp-manager plugin which is expected to be the next consumer.` Do NOT mark Superseded.
- [x] T032 [US5] [P] (SEC-035-003 → TASK-SEC-035-003) In `docs/memory/BUGS.md`: (a) mark `BUG-MCP-TYPE-PASSTOOL-CONFLICT` with `**Status**: Resolved (Feature 035 — 2026-06-16 — pass_as_tool removed)`; (b) edit `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` to add a postscript: `**Status (2026-06-16)**: Immediate trigger removed by Feature 035. Lesson durable: AC rules are fail-open in absence; always pair an AC check with the ability's own check_permissions() as authoritative gate. Helper user_has_ability_access() was removed alongside its only caller; re-author the equivalent pattern inline at any new call site rather than restoring the helper, so the partner gate cannot be forgotten.` (c) Do NOT touch `BUG-BERLINDDB-QUERY-PRIVATE-CTOR` — it is unaffected and remains an active broad-scope lesson.
- [x] T033 [US5] [P] In `docs/memory/INDEX.md`: (a) update the `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` row Status column to `Superseded`; (b) update the `BUG-MCP-TYPE-PASSTOOL-CONFLICT` row tags / status to reflect `Resolved (2026-06-16)`; (c) add a new row for `DEC-PASS-AS-TOOL-REMOVED` under Active Decisions; (d) update the Feature 029 worklog row to note the supersession (`see Feature 035 / DEC-PASS-AS-TOOL-REMOVED`). Use the same column structure as the surrounding rows.
- [x] T034 [US5] [P] In `docs/memory/WORKLOG.md`: append a `### 2026-06-16 — Feature 035: Remove Pass as Tool` entry following the pattern of the Feature 034 worklog (short summary, supersedes link to DECISIONS.md, exhaustive-grep verification result captured from T036, and the surviving durable lessons listed).
- [x] T035 [US5] Grep CONSTITUTION + AGENTS for residual references: `grep -nE 'pass_as_tool|inject_mcp_tools|McpToolsPassthrough|PassAsToolCell' .specify/memory/CONSTITUTION.md AGENTS.md`. Likely returns 0 hits (the synthesis already verified). If any hit exists, remove it and (for CONSTITUTION only) bump the version at PATCH level with the standard SYNC IMPACT REPORT block per `PATTERN-CONSTITUTION-SYNC-REPORT`. If 0 hits, no constitution bump.

**Checkpoint**: US5 fully functional. All memory artifacts reflect the removal; durable lessons
preserved; future Spec Kit runs will not silently regenerate the feature.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Terminal quality gates plus the SC-005 exhaustive-grep acceptance gate.

- [x] T036 [P] Run `composer phpstan` (level 8) full sweep; zero errors. Capture to `specs/035-remove-pass-as-tool/phpstan-final.txt`.
- [x] T037 [P] Run `composer phpcs` against the changed production PHP files (Schema, Row, Query, Override Processor, Sanitizer, Formatter, Merger, Main); zero new errors. Capture to `specs/035-remove-pass-as-tool/phpcs-final.txt`. Do NOT make the full repo-wide PHPCS the gate (per `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE` — only changed files).
- [x] T038 [P] Run `composer test` (full PHPUnit) — must be fully green. Capture to `specs/035-remove-pass-as-tool/phpunit-final.txt`.
- [x] T039 [P] Run `npm test` (full Jest) — must be fully green. Capture to `specs/035-remove-pass-as-tool/jest-final.txt`.
- [ ] T040 [P] **(Deferred to user — requires live wp-env)** Run Plugin Check via the canonical wp-env path: `wp-env run cli wp plugin check acrossai-abilities-manager --include-experimental --exclude-directories=.agents,.claude,.github,.specify,docs,node_modules,scripts,specs,src,tests --exclude-files=…` (use the exact flag list from `.github/workflows/plugin-check.yml`). Zero errors and zero warnings. Capture to `specs/035-remove-pass-as-tool/plugin-check-final.txt`.
- [x] T041 [P] Verify the rebuilt bundle is clean: `grep -c 'pass_as_tool\|PassAsTool' build/js/abilities.js` returns `0` (this duplicates T022's check at terminal gate time in case any intermediate task accidentally caused a rebuild containing the symbol).
- [x] T042 **TERMINAL GATE — SC-005 + spec Validation Checklist**: Re-run the exhaustive grep from T001 against the post-implementation tree:
      `grep -rEn 'pass_as_tool|pass-as-tool|PassAsTool|get_pass_as_tool_slugs|inject_mcp_tools|PassAsToolCell|McpToolsPassthrough|Mcp_Tools_Passthrough' includes/ admin/ public/ src/ tests/ acrossai-abilities-manager.php uninstall.php composer.json package.json AGENTS.md *.md`. Expected: **zero hits**, except inside `docs/memory/*` (WORKLOG/DECISIONS/INDEX/BUGS — historical entries), `docs/planning/029-mcp-tools-passthrough.md`, `docs/planning/035-remove-pass-as-tool.md`, and `specs/029-*`, `specs/032-*`, `specs/034-*`, `specs/035-*` (historical/working artifacts). Capture to `specs/035-remove-pass-as-tool/final-grep.txt`. **Any other hit blocks completion** and triggers a targeted return to whichever phase owns the surface.
- [ ] T043 Final manual UI smoke: load the abilities list (no column, no menu entry); load an ability edit (no Section 4, contiguous numbering); save a row; confirm browser console clean + PHP debug log clean. Sign-off here marks the feature complete.

**Checkpoint**: All quality gates green; final exhaustive grep returns zero hits in production
scope; manual smoke clean. Feature 035 implementation complete.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately.
- **Phase 2 (Foundational)**: Depends on Phase 1 completion. **BLOCKS all user-story phases.**
- **Phase 3 (US1)**: Depends on Phase 2 completion. May proceed in parallel with US2/US3/US4/US5
  once Phase 2 done.
- **Phase 4 (US2)**: Depends on Phase 2. Independent of US1/US3/US4/US5 once Phase 2 done.
- **Phase 5 (US3)**: Depends on Phase 2 AND on Phase 4 if you want the rebuilt bundle exercised
  by Jest. In practice run US3 PHPUnit gate after US1/US2 because failures may originate from
  either.
- **Phase 6 (US4)**: Depends on Phase 2 (specifically T004 — Schema column removed).
- **Phase 7 (US5)**: Depends on Phase 2 + Phase 3 (so the BUGS.md status updates can quote
  "Feature 035 removed `inject_mcp_tools()`" as a completed fact).
- **Phase 8 (Polish)**: Depends on all desired user-story phases being complete.

### User Story Dependencies

- **US1 (P1)**: Depends on Phase 2 (Schema/Row/Query removed). No dependency on US2/US3/US4/US5.
- **US2 (P1)**: Depends on Phase 2. No dependency on US1/US3/US4/US5. Different file set from US1
  (PHP vs. JSX); fully parallelizable with US1.
- **US3 (P2)**: Depends on Phase 2. Independent of US1/US2 but recommend running its PHPUnit
  gate after US1/US2 so failures localize cleanly.
- **US4 (P2)**: Depends on Phase 2. Independent of US1/US2/US3. Documentation-mostly.
- **US5 (P3)**: Depends on Phase 2 + Phase 3 (so BUGS.md text matches reality). Documentation-only.

### Within Each User Story

- US1: Edit hook registration first (T013), then method body (T014), then docblock (T015), then
  conditional helper deletion (T016), then verify (T017–T018).
- US2: AbilityForm first (T019 — heaviest renumbering churn) → AbilitiesList (T020) → store
  (T021) → bundle rebuild (T022) → smoke (T023).
- US3: Delete dedicated suites (T024) → add silent-ignore test (T025) → run full suites
  (T026–T027).
- US4: Verify Schema (T028) → end-to-end smoke (T029) → docs (T030).
- US5: DECISIONS (T031) → BUGS (T032) → INDEX (T033) → WORKLOG (T034) → constitution/agents
  (T035).

### Parallel Opportunities

- **Phase 1**: T002 and T003 are [P] — run together with T001 (T001 is the longest-running grep
  so kick it off first and run T002/T003 while it streams).
- **Phase 2**: T007/T008/T009 are [P] (three different utility files). T011 (mixed-concern test
  sweep) can interleave with the utility edits because it acts only on test files.
- **Phase 4 (US2)**: T020 and T021 are [P] (different files).
- **Phase 7 (US5)**: T032/T033/T034 are [P] (different memory files).
- **Phase 8 (Polish)**: T036/T037/T038/T039/T040/T041 are [P] (different commands; some bind to
  the same dev DB but read-only).

---

## Parallel Example: Phase 2 Foundational

```bash
# After T004/T005/T006 (sequential — same module + same row shape) complete:
Task T007: Remove 'pass_as_tool' from $overridable_fields in includes/Utilities/AcrossAI_Ability_Merger.php
Task T008: Remove 'pass_as_tool' from $tri_state_fields in includes/Utilities/AcrossAI_Abilities_Sanitizer.php
Task T009: Remove three 'pass_as_tool' keys from format_for_response / format_for_exposure / format_merged_ability in includes/Utilities/AcrossAI_Abilities_Formatter.php
```

## Parallel Example: Phase 8 Polish

```bash
Task T036: composer phpstan
Task T037: composer phpcs
Task T038: composer test
Task T039: npm test
Task T040: wp-env run cli wp plugin check ...
Task T041: grep -c 'pass_as_tool\|PassAsTool' build/js/abilities.js
```

---

## Implementation Strategy

### MVP First (Phases 1+2+3 = US1)

1. Complete Phase 1: Setup (preflight grep + caller analysis + branch confirmation).
2. Complete Phase 2: Foundational (Schema/Row/Query/Utilities/Main/mixed-concern tests +
   PHPStan check).
3. Complete Phase 3: US1 (Override Processor surgery).
4. **STOP and VALIDATE**: Confirm US1 independent test — no `mcp_adapter_init` listener, no
   Reflection reach, surviving methods intact.
5. The plugin is already a working "tools-injection-removed" build at this point. US2–US5 are
   then additive cleanup.

### Incremental Delivery

1. Phase 1 + Phase 2 → Foundation ready.
2. Phase 3 (US1) → MVP (the security improvement is realized).
3. Phase 4 (US2) → UI cleaned.
4. Phase 5 (US3) → REST contract pinned by the new silent-ignore test.
5. Phase 6 (US4) → Migration docs published.
6. Phase 7 (US5) → Memory + governance current.
7. Phase 8 → Terminal gates green.

### Parallel Team Strategy

With multiple developers:

1. Team completes Phase 1 + Phase 2 together.
2. Then split:
   - Developer A: US1 (Override Processor)
   - Developer B: US2 (React UI + rebuild)
   - Developer C: US3 (PHPUnit suite refresh)
   - Developer D: US4 + US5 (docs + memory)
3. Phase 8 quality gates run last, on a single integrated branch.

---

## Notes

- [P] tasks = different files, no inter-task dependencies.
- The mixed-concern test fixture sweep (T011) runs BEFORE the dedicated test deletion (T024) so
  PHPUnit stays green mid-feature.
- The helper deletion (T016) is **conditional** on the T002 grep result. Do NOT delete
  unconditionally.
- The `user_has_ability_access` BUGS.md annotation (T032) explicitly forbids restoring the
  helper as a "convenience" in future work — re-author the partner-gated pattern inline.
- The final exhaustive grep (T042) is the feature's terminal acceptance gate; nothing else
  closes Feature 035.
- The constitution version footer staleness (architecture-violations A-035-4) is **out of
  scope** for this feature; do NOT touch CONSTITUTION.md unless T035 finds a residual
  `pass_as_tool` reference.
- Commit cadence: one logical group per commit (Phase 1, Phase 2, US1, US2, US3, US4, US5,
  Phase 8 gates). The `after_implement` hook will trigger commit/security-review hooks per
  `.specify/extensions.yml`.

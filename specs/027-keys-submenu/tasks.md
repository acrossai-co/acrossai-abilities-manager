# Tasks: Abilities Library Admin Submenu

**Input**: Design documents from `/specs/027-keys-submenu/`
**Prerequisites**: plan.md ✅, spec.md ✅, memory-synthesis.md ✅, security-constraints.md ✅

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

**Active Security Constraints**: SC-027-01 (config size limits), SC-027-02 (args allowlist), SC-027-03 (JSX-only label rendering), SC-027-05/06 (standalone REST orchestrator pattern)

**Accepted Deviations**: DEC-DESIGN-OVERRIDES-DATAVIEWS — custom DataViews card grid with inline form controls accepted; Constitution §III DataForm mandate relaxed for this page.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Webpack entry points and directory scaffolding required before any code can be built or bundled.

- [x] T001 Add `'js/ability-library': './src/js/ability-library/index.js'` and `'css/ability-library': './src/scss/ability-library/admin.scss'` entry points to webpack.config.js
- [x] T002 [P] Create directory structure `src/js/ability-library/components/` and `src/scss/ability-library/` (placeholder files as needed for git tracking)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: PHP business logic, REST infrastructure, and shared JS API layer — all must be complete before any user story phase can begin.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T003 [P] Create `includes/Modules/Library/AcrossAI_Ability_Library_Config.php` — 100% static utility class (DEC-UTILITY-STATIC-ONLY); constants `MAX_KEY_LENGTH=100`, `MAX_KEYS=50`, `MAX_SUB_KEYS=50`; public static methods: `get_config()` reads `get_site_option('acrossai_library_config', [])`, `save_config(array $raw): bool` sanitizes, then strips default-valued entries before writing (sparse storage: skip entries where `enabled===true AND mode==='all' AND empty(sub_keys)`, FR-017/D6), `sanitize_entry(array $raw): array` validates mode against `['all','specific']` (default `'all'` on invalid) and casts enabled/sub_key flags with `(bool)`, `sanitize_key_field(string $key): string` runs `sanitize_key()` + truncates to MAX_KEY_LENGTH (SC-027-01)
- [x] T004 [P] Create `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php` — singleton; `collect()` (hooked at `init P99`) fires `apply_filters('acrossai_abilities_api_init', [])` and caches result; `get_definitions(): array` returns cached collection; `validate_and_normalize(array $raw): array` requires all four fields (`main_key`, `main_key_label`, `sub_key`, `sub_key_label`), sanitizes key fields with `sanitize_key()` + length limit, sanitizes labels with `wp_kses_post()`, strips ability args not in `ALLOWED_ARGS_FIELDS` via `array_intersect_key()`, silently skips invalid definitions with `WP_DEBUG_LOG` guard (SC-027-02, PATTERN-ADDON-FILTER-LATE-INIT)
- [x] T005 Create `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Rest_Controller.php` — **standalone singleton, does NOT extend `WP_REST_Controller`** (mirrors `AcrossAI_Abilities_Rest_Controller` pattern exactly, SC-027-06); `const REST_NAMESPACE = 'acrossai-abilities-library/v1'`; `register_routes()` delegates to `AcrossAI_Ability_Library_Config_Controller::instance()->register_routes()`; `check_permission(\WP_REST_Request $request)` two-gate: (1) `current_user_can('manage_options')` → `WP_Error(403)` on fail, (2) `wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')` → `WP_Error(403)` on fail, returns `true` on pass (SC-027-05)
- [x] T006 Create `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` — singleton; `register_routes()` registers GET and POST `/acrossai-abilities-library/v1/abilities/config`; GET handler returns `AcrossAI_Ability_Library_Config::get_config()` as `WP_REST_Response`; POST handler calls `AcrossAI_Ability_Library_Config::save_config($request->get_json_params())` (strips defaults before write) and returns saved config; `permission_callback = [AcrossAI_Ability_Library_Rest_Controller::instance(), 'check_permission']` on both routes
- [x] T007 Wire Registry and REST in `includes/Main.php` `define_admin_hooks()` — use **named variables** per Boot Flow Rule (inline `instance()` as second arg to `add_action` is prohibited): `$ability_library_registry = AcrossAI_Ability_Library_Registry::instance(); $this->loader->add_action('init', $ability_library_registry, 'collect', 99);` and `$ability_library_rest = AcrossAI_Ability_Library_Rest_Controller::instance(); $this->loader->add_action('rest_api_init', $ability_library_rest, 'register_routes');` (AC-HOOKS-MAIN, Boot Flow Rule)
- [x] T008 [P] Create `src/js/ability-library/api.js` — `fetchConfig()` and `saveConfig(config)` using `@wordpress/api-fetch`; endpoint URL from `window.acrossaiAbilityLibraryData.restBase`; nonce from `window.acrossaiAbilityLibraryData.nonce` passed via `apiFetch` middleware

- [x] T029 [P] Create `includes/Modules/Library/Ability_Definition.php` — abstract base class in namespace `AcrossAI_Abilities_Manager\Includes\Modules\Library`; constructor calls `add_filter('acrossai_abilities_api_init', [$this, 'push_definition'])`; five abstract protected methods: `main_key(): string`, `main_key_label(): string`, `sub_key(): string`, `sub_key_label(): string`, `ability(): array` (must return `['name' => ..., 'args' => [...]]`); public `push_definition(array $definitions): array` assembles the six-field definition from the abstract methods and appends it to `$definitions` (D9); raw `acrossai_abilities_api_init` filter remains a valid alternative — both paths produce identical definition arrays consumed by the Registry

**Checkpoint**: PHP config service (sparse storage), registry, REST endpoints, JS API layer, and add-on authoring base class complete. Verify with WP-CLI (`wp eval`) or REST client before proceeding to user story phases.

---

## Phase 3: User Story 1 — Admin Enables or Disables an Ability Group (Priority: P1) 🎯 MVP

**Goal**: An admin submenu "Library" page exists under the plugin parent menu. It renders ability group cards with a master ON/OFF toggle per card. Toggling a group and waiting for save enables/disables that group's abilities at registration time.

**Independent Test**: Navigate to Library admin submenu → find any group card → flip master toggle to OFF → wait for save → reload page → group shows OFF → confirm ability processor skips that group's abilities.

### Implementation for User Story 1

- [x] T009 [US1] Create `admin/Partials/LibraryMenu.php` — singleton; `register_submenu()` calls `add_submenu_page('acrossai-abilities-manager', __('Ability Library','acrossai-abilities-manager'), __('Library','acrossai-abilities-manager'), 'manage_options', 'acrossai-abilities-library', [$this,'render'])`; `render()` outputs `<div id="acrossai-library-root"></div>`, calls `wp_localize_script` with `window.acrossaiAbilityLibraryData = { definitions: AcrossAI_Ability_Library_Registry::instance()->get_definitions(), restBase: rest_url('acrossai-abilities-library/v1'), nonce: wp_create_nonce('wp_rest') }` (D4, DEC-MENU-HOOK-SUFFIX)
- [x] T010 [US1] Update `admin/Main.php` — add `private function is_library_page(string $hook_suffix): bool { return 'acrossai-abilities-manager_page_acrossai-abilities-library' === $hook_suffix; }` (DEC-MENU-HOOK-SUFFIX); add `library_asset_file` property; in constructor add `file_exists()` guard before `include` of `build/js/ability-library.asset.php` (BUG-UNCONDITIONAL-ASSET-INCLUDE); update `enqueue_scripts()` and `enqueue_styles()` to register and enqueue `acrossai-ability-library-js` / `acrossai-ability-library-css` bundle only when `is_library_page($hook_suffix)` is true (AC-ENQUEUE-ADMIN)
- [x] T011 [US1] Wire LibraryMenu in `includes/Main.php` `define_admin_hooks()` — use **named variable** per Boot Flow Rule: `$ability_library_menu = LibraryMenu::instance(); $this->loader->add_action('admin_menu', $ability_library_menu, 'register_submenu');` (AC-HOOKS-MAIN, Boot Flow Rule)
- [x] T012 [US1] Create `includes/Modules/Library/AcrossAI_Ability_Library_Processor.php` — singleton; `register_abilities()` hooked at `wp_abilities_api_init P5`; reads `AcrossAI_Ability_Library_Registry::instance()->get_definitions()` and `AcrossAI_Ability_Library_Config::get_config()`; `is_permitted(array $definition, array $config): bool` logic: main_key absent from config → `enabled=true, mode='all'` (FR-017, sparse storage — absent key = default enabled); main_key enabled=false → return false (FR-014); mode='all' → return true (FR-015); mode='specific' → return isset(config[main_key][sub_keys][sub_key]) && config[main_key][sub_keys][sub_key]===true, absent sub_key defaults to false (FR-016/FR-018); permitted definitions are passed to `wp_register_ability(definition['name'], definition['args'])`
- [x] T013 [US1] Wire Processor in `includes/Main.php` `define_public_hooks()` — use **named variable** per Boot Flow Rule: `$ability_library_processor = AcrossAI_Ability_Library_Processor::instance(); $this->loader->add_action('wp_abilities_api_init', $ability_library_processor, 'register_abilities', 5);` (AC-HOOKS-MAIN, Boot Flow Rule)
- [x] T014 [P] [US1] Create `src/js/ability-library/components/LibraryCard.js` — renders `item.main_key_label` as JSX text content only (no `dangerouslySetInnerHTML`, SC-027-03); master ON/OFF toggle bound to `config[item.main_key]?.enabled ?? true`; `onChange(mainKey, patch)` callback prop; US1 scope: toggle only (mode selector added in T018)
- [x] T015 [US1] Create `src/js/ability-library/components/LibraryPage.js` — state: `definitions` from `window.acrossaiAbilityLibraryData.definitions`, `config` (object, from REST GET on mount); `useEffect` on mount calls `fetchConfig()` to populate `config` state; DataViews grid with `view={{ type: 'grid' }}`, items derived from definitions grouped by `main_key`; renders `<LibraryCard>` per item; `onChange(mainKey, patch)` updates config state (debounce wired in T020); search and pagination props passed to DataViews (FR-007, DEC-DESIGN-OVERRIDES-DATAVIEWS)
- [x] T016 [US1] Create `src/js/ability-library/index.js` — webpack entry point; imports `LibraryPage`; renders `<LibraryPage />` into `document.getElementById('acrossai-library-root')` using `@wordpress/element`
- [x] T017 [P] [US1] Create `src/scss/ability-library/admin.scss` — Library page styles: card grid layout, toggle switch, mode selector, sub-key checkbox list, saving/error state indicators

**Checkpoint**: US1 fully functional — admin visits Library submenu, sees group cards with ON/OFF toggles, toggle state saves to site option via REST (sparse: only disabled/reconfigured groups stored), processor gates abilities at wp_abilities_api_init P5.

---

## Phase 4: User Story 2 — Admin Selects All or Specific Mode for a Group (Priority: P2)

**Goal**: Each enabled group card shows a mode selector (All / Specific). When Specific is selected, per-sub_key checkboxes appear. Switching back to All hides checkboxes without clearing selections. Processor gates sub_key registration per mode.

**Independent Test**: Enable a group → switch to Specific mode → check one sub_key checkbox → wait for auto-save → reload → only that sub_key's ability active in the abilities API.

### Implementation for User Story 2

- [x] T018 [US2] Extend `src/js/ability-library/components/LibraryCard.js` — add mode selector (SelectControl: All | Specific) bound to `config[item.main_key]?.mode ?? 'all'`; when `mode === 'specific'`, render sub-key checkboxes list with each `sub_key_label` as JSX text (SC-027-03); each checkbox bound to `config[item.main_key]?.sub_keys?.[sub_key] ?? false`; checkboxes hidden when mode is `'all'` (FR-004/005/006); `onChange` emits full updated entry patch including mode and sub_keys
- [x] T019 [US2] Update `src/js/ability-library/components/LibraryPage.js` — ensure `config` state shape accommodates `{ [main_key]: { enabled, mode, sub_keys: { [sub_key]: bool } } }`; validate `onChange` patch deep-merges mode and sub_keys correctly without discarding unchecked sub_key entries on mode switch (FR-002, acceptance scenario 2)

**Checkpoint**: US2 complete — All/Specific mode toggles correctly; sub-ability checkboxes appear in Specific mode and disappear in All mode; selections preserved on mode switch back to All.

---

## Phase 5: User Story 4 — Auto-Save Configuration Without Manual Action (Priority: P2)

**Goal**: Any toggle, mode, or checkbox change auto-saves to the REST endpoint with a 1-second debounce. Initial page load does not trigger a save. Save failures surface as a visible admin error notice.

**Independent Test**: Change any toggle → wait ~1 second → navigate away → return → change persists without any Save button click.

### Implementation for User Story 4

- [x] T020 [US4] Add auto-save to `src/js/ability-library/components/LibraryPage.js` — add `initialLoadComplete` ref (set `true` after first `fetchConfig()` resolves, FR-009 guard); add `isSaving` and `error` state; wrap `onChange` handler in 1000ms debounce that calls `saveConfig(config)` only when `initialLoadComplete.current === true`; on save error set `error` state and render a dismissible admin-notice style error message (FR-008; acceptance scenario 3: initial load must not write); concurrent changes cancel pending debounce and restart (SC-007/SC-008)

**Checkpoint**: US4 complete — changes auto-save within ~1 second of last interaction; initial load does not write config; POST failures surface as visible error notice and do not discard local state.

---

## Phase 6: User Story 3 — Add-on Registers Abilities via Filter Hook (Priority: P3)

**Goal**: The `acrossai_abilities_api_init` filter is the stable public API for add-on developers. Definitions from active add-ons appear in the admin grid; deactivated add-on definitions disappear while their saved config is preserved.

**Independent Test**: Write a minimal add-on hooking `acrossai_abilities_api_init` and appending one definition array → activate it → load the Library admin page → the add-on's main_key card appears in the grid with correct labels and sub_keys.

### Implementation for User Story 3

- [x] T021 [US3] Verify `AcrossAI_Ability_Library_Registry` filter contract — confirm `acrossai_abilities_api_init` filter PHPDoc documents the expected array schema (`main_key`, `main_key_label`, `sub_key`, `sub_key_label` + allowed ability args); confirm invalid definitions (missing required fields, non-conforming ability name) are silently skipped with `if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)` guard (FR-010/011/012); verify `ALLOWED_ARGS_FIELDS` constant matches accepted args of `wp_register_ability()`; no plugin-class dependency required from add-on side
- [x] T022 [US3] Verify `LibraryMenu` localization reflects active registrations — confirm `get_definitions()` output contains only currently-active add-on definitions; confirm deactivated add-on groups are absent from the grid while `acrossai_library_config` site option remains untouched (FR-019, SC-005); add PHPDoc comment in `LibraryMenu::localize_data()` explaining deactivation/reactivation behavior for future maintainers

**Checkpoint**: US3 complete — filter contract stable and documented; definitions reflect live add-on registrations; saved config survives add-on deactivation/reactivation cycle.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Logger namespace migration, uninstall cleanup, and quality gate validation. All tasks in this phase must complete before the feature can be merged.

- [x] T023 Logger namespace migration — update **ALL 5 files atomically in a single commit** (DEC-LOGGER-NAMESPACE-MIGRATION — partial migration causes a Logs page 404 regression; FR-025):
  - `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php` line 35: `'acrossai-abilities/v1'` → `'acrossai-abilities-log/v1'`
  - `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` line 36: `'acrossai-abilities/v1'` → `'acrossai-abilities-log/v1'`
  - `src/js/index.js` line 38: `'/wp-json/acrossai-abilities/v1/logger/logs'` → `'/wp-json/acrossai-abilities-log/v1/logger/logs'`
  - `src/js/components/LogsTable.js` line 93: endpoint default prop → `'/wp-json/acrossai-abilities-log/v1/logger/logs'`
  - `admin/Main.php` line 191: `rest_url('acrossai-abilities/v1/logger/logs')` → `rest_url('acrossai-abilities-log/v1/logger/logs')`
  After updating, verify the Logs submenu still loads and the REST call succeeds.
- [x] T024 [P] Add `delete_site_option('acrossai_library_config')` inside the `$acrossai_delete_data` gate in `uninstall.php`
- [x] T025 [P] Run PHPCS (WPCS) and PHPStan L8 on all new PHP files (`includes/Modules/Library/`, `admin/Partials/LibraryMenu.php`) and modified PHP files (`admin/Main.php`, `includes/Main.php`, both Logger controllers, `uninstall.php`); fix all violations
- [x] T026 [P] Run ESLint on all new and modified JS files (`src/js/ability-library/`, `src/js/index.js`, `src/js/components/LogsTable.js`); fix all violations
- [x] T027 Run `npm run build`; verify `build/js/ability-library.js` and `build/css/ability-library.css` (and their `.asset.php` files) are generated without errors; confirm `build/js/ability-library.asset.php` exists so `admin/Main.php` `file_exists()` guard passes; run `npm run validate-packages` and fix any package hierarchy violations (Constitution §VI/§VII)
- [ ] T028 [P] Run Plugin Check on production surface; fix any flagged issues

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 — BLOCKS all user story phases
- **US1 (Phase 3)**: Depends on Phase 2 — no dependency on US2/US3/US4
- **US2 (Phase 4)**: Depends on US1 (Phase 3) — extends LibraryCard and LibraryPage built in US1
- **US4 (Phase 5)**: Depends on US1 (Phase 3) — adds debounce to LibraryPage onChange handler
- **US3 (Phase 6)**: Depends on Phase 2 (Registry) + Phase 3 (LibraryMenu localization)
- **Polish (Phase 7)**: Depends on all user story phases complete

### Within Each Phase

- T003 (Config) and T004 (Registry) are independent — run in parallel
- T005 (REST Orchestrator) → T006 (Config Controller) — orchestrator pattern required first
- T006 → T007 (hook wiring) — controllers must exist before wiring
- T008 (api.js) — independent of PHP chain, depends on knowing the REST endpoint URL structure
- T009 (LibraryMenu) after T004 (Registry) — calls get_definitions()
- T010 (admin/Main.php) — independent of T009, can run in parallel
- T014 (LibraryCard) can run in parallel with T012/T013 (Processor) — different file trees
- T015 (LibraryPage) after T014 (LibraryCard) — imports LibraryCard
- T016 (index.js) after T015 (LibraryPage)

### Parallel Opportunities

- **Phase 2**: T003 + T004 + T008 in parallel; then T005 → T006 → T007 sequential
- **Phase 3**: T009 + T010 + T014 + T017 in parallel after T007; then T015 after T014; then T016 after T015
- **Phase 7**: T024 + T025 + T026 + T028 in parallel; T027 after T025/T026 pass

---

## Parallel Example: Phase 2 Foundational

```bash
# Group A — run in parallel (no inter-dependency):
Task: "Create AcrossAI_Ability_Library_Config (sparse storage)"  # T003
Task: "Create AcrossAI_Ability_Library_Registry"                 # T004
Task: "Create api.js"                                            # T008

# Group B — sequential after Group A:
Task: "Create AcrossAI_Ability_Library_Rest_Controller"          # T005
Task: "Create AcrossAI_Ability_Library_Config_Controller"        # T006
Task: "Wire Registry and REST in includes/Main.php"              # T007
```

## Parallel Example: Phase 3 US1

```bash
# After T007 completes, run in parallel:
Task: "Create LibraryMenu.php"  # T009
Task: "Update admin/Main.php"   # T010
Task: "Create admin.scss"       # T017

# After T010: Wire LibraryMenu (T011)
# After T011: Create Processor (T012) → Wire Processor (T013)
# After T013: LibraryCard (T014) → LibraryPage (T015) → index.js (T016)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001–T002)
2. Complete Phase 2: Foundational (T003–T008) — CRITICAL, blocks all stories
3. Complete Phase 3: User Story 1 (T009–T017)
4. **STOP and VALIDATE**: Test US1 end-to-end — toggle → REST save (sparse) → processor gate → abilities API
5. Demo or deploy MVP if validated

### Incremental Delivery

1. Phase 1 + 2 → PHP + JS infrastructure ready
2. Phase 3 (US1) → Admin toggle works end-to-end → MVP demo
3. Phase 4 (US2) → Mode selector + sub-key checkboxes → Extended config
4. Phase 5 (US4) → Auto-save → No manual save required
5. Phase 6 (US3) → Add-on filter contract verified and documented
6. Phase 7 → Quality gates + Logger migration → Merge-ready

---

## Notes

- [P] tasks touch different files with no mutual dependency — safe to parallelize within their phase
- [Story] labels map each task to a specific user story for traceability and independent delivery
- **T003** (sparse storage): `save_config()` strips entries where `enabled===true AND mode==='all' AND empty(sub_keys)` before `update_site_option()`. Absent key = default enabled (FR-017). Only non-default entries are persisted.
- **T023** (Logger migration) MUST update all 5 files in a single atomic commit — partial migration creates a Logs page 404 regression that is silent on the PHP side but fatal on the JS side
- **T029** (`Ability_Definition`): class is autoloaded via the manager plugin's PSR-4 Composer config (`AcrossAI_Abilities_Manager\Includes\` → `includes/`). Add-on plugins that extend it must guard instantiation with `class_exists('\AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition')` in `plugins_loaded P20`
- **SC-027-01**: Config size limits (`MAX_KEY_LENGTH=100`, `MAX_KEYS=50`, `MAX_SUB_KEYS=50`) are encoded in T003 — implementation must enforce these via slicing before `update_site_option()`
- **SC-027-02**: `ALLOWED_ARGS_FIELDS` allowlist in T004 must use `array_intersect_key()` — field names to confirm against `wp_register_ability()` accepted args
- **SC-027-03**: All label renders in T014 and T018 must use JSX text content only — no `dangerouslySetInnerHTML`
- **SC-027-05/06**: T005 orchestrator MUST be a standalone singleton — do not copy from `AcrossAI_Logger_Controller` (extends `WP_REST_Controller`), copy from `AcrossAI_Abilities_Rest_Controller` (standalone)
- **BUG-UNCONDITIONAL-ASSET-INCLUDE**: T010 `file_exists()` guard on `.asset.php` is required — missing built file must not fatal on non-Library admin pages
- **BUG-MODULE-LEVEL-WINDOW-READ**: `window.acrossaiAbilityLibraryData` is read inside the LibraryPage component function (not module scope) to avoid access before localization

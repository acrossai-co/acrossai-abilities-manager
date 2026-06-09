# Memory Synthesis

## Current Scope
Feature 027 (`027-keys-submenu`) adds the Ability **Library** admin submenu page under the plugin parent menu. Core module: `includes/Modules/Library/` (Registry, Config, Processor, REST controllers, abstract `Ability_Definition` base class). Admin page: `admin/Partials/LibraryMenu.php`. React UI in `src/js/ability-library/`. Logger REST namespace migrates from `acrossai-abilities/v1` → `acrossai-abilities-log/v1`.

**Implementation status (2026-06-09)**: T001–T027 and T029 complete. Only T028 (Plugin Check) remains open.

Affected modules: `includes/Modules/Library/`, `admin/Partials/LibraryMenu.php`, `admin/Main.php`, `includes/Main.php`, `Logger/Rest/` (namespace rename in both PHP + JS files), webpack config, `uninstall.php`, companion add-on `acrossai-core-abilities` (three ability classes).

---

## Relevant Decisions

- **DEC-MENU-HOOK-SUFFIX** — `LibraryMenu.php` must NOT store `add_submenu_page()` return value; `admin/Main.php::is_library_page()` hardcodes `'acrossai-abilities-manager_page_acrossai-abilities-library'` as a string literal. (Reason: Library submenu page enqueue guard; Status: Active; Source: DECISIONS.md)

- **DEC-LOGGER-NAMESPACE-MIGRATION** — Logger REST namespace moved to `acrossai-abilities-log/v1` in this feature (T023). All 5 files updated atomically: `AcrossAI_Logger_Controller.php:35`, `AcrossAI_Logger_Logs_Controller.php:36`, `src/js/index.js:38`, `src/js/components/LogsTable.js:93`, `admin/Main.php:191`. (Reason: In-scope breaking migration now complete; Status: Active; Source: DECISIONS.md)

- **DEC-ABILITYAPI-NAMESPACE** — AbilityAPI module REST namespace is `acrossai-abilities-api/v1`; Library uses `acrossai-abilities-library/v1`; Logger uses `acrossai-abilities-log/v1`. Never share namespaces across modules. (Reason: Namespace isolation for all three namespaces; Status: Active; Source: DECISIONS.md)

- **DEC-DESIGN-OVERRIDES-DATAVIEWS** — DataViews grid + custom card controls (toggle/mode-selector/checkboxes) in `LibraryCard.js` are acceptable; Constitution §III DataForm mandate superseded by design prototype for this page. (Reason: Spec requires card-based grid; Status: Active; Source: DECISIONS.md)

- **PATTERN-ADDON-FILTER-LATE-INIT** — `acrossai_abilities_api_init` filter fires at `init P99` (Registry's `collect()`). Add-ons hook at default `init P10` or `plugins_loaded P20`. Any hook at priority ≥ 99 silently drops definitions. (Reason: Core timing contract for the Library pipeline; Status: Active; Source: ARCHITECTURE.md)

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** — `Ability_Definition.__construct()` self-registers `acrossai_abilities_api_init` filter, bypassing Loader. Add-on bootstraps at `plugins_loaded P20` with `class_exists()` guard. Cite this decision ID. (Reason: Ability_Definition is now implemented; T029 complete; Status: Accepted-Deviation; Source: DECISIONS.md)

- **DEC-UTILITY-STATIC-ONLY** — `AcrossAI_Ability_Library_Config` is 100% static. Registry and Processor use singleton. (Reason: Config class design constraint; Status: Active; Source: DECISIONS.md)

- **DEC-FEATURE-027-NO-TESTS** — Feature 027 ships without unit tests; Library Config and Processor are tech-debt test candidates. PHPStan L8, PHPCS, ESLint, Plugin Check remain required. (Reason: Accepted deviation that directly scopes DoD; Status: Active; Source: DECISIONS.md)

---

## Active Architecture Constraints

- **AC-HOOKS-MAIN** — Only `includes/Main.php` wires hooks via Loader with named variables. `LibraryMenu` (admin_menu), `AcrossAI_Ability_Library_Registry` (init P99), `AcrossAI_Ability_Library_Processor` (wp_abilities_api_init P5), `AcrossAI_Ability_Library_Rest_Controller` (rest_api_init) all wired in `define_admin_hooks()` / `define_public_hooks()` only. Inline `instance()` as second arg is prohibited. (Source: CONSTITUTION.md §I)

- **AC-ENQUEUE-ADMIN** — Library JS/CSS bundles enqueued only in `Admin\Main::enqueue_scripts/styles` behind `is_library_page()` guard. (Source: CONSTITUTION.md §I)

- **AC-REST-SPLIT** — `AcrossAI_Ability_Library_Rest_Controller` is standalone orchestrator (does NOT extend `WP_REST_Controller`); `AcrossAI_Ability_Library_Config_Controller` is its only sub-controller (GET/POST `/abilities/config`). Mirrors `AcrossAI_Abilities_Rest_Controller` pattern. (Source: CONSTITUTION.md §I)

- **AC-FILE-HEADER-PATTERN** — Every new PHP file: `@package AcrossAI_Abilities_Manager`, `@subpackage full/path`, `@since 0.1.0`. (Source: ARCHITECTURE.md)

- **PATTERN-ADDON-FILTER-LATE-INIT** — Add-on hooks at `plugins_loaded P20`; Registry collects at `init P99`. Never hook `acrossai_abilities_api_init` at init priority ≥ 99 from an add-on. (Source: ARCHITECTURE.md)

- **BUG-UNCONDITIONAL-ASSET-INCLUDE** — `build/js/ability-library.asset.php` include in `admin/Main.php` constructor must be behind `file_exists()` guard. (Source: BUGS.md)

- **BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD** — Literal `/abilities/config` registered before any wildcard routes in `AcrossAI_Ability_Library_Config_Controller`. (Source: BUGS.md)

---

## Accepted Deviations

- **DEC-DESIGN-OVERRIDES-DATAVIEWS** — Custom DataViews card grid with inline form controls (toggle, mode, checkboxes) accepted for Library page; Constitution §III DataForm mandate relaxed. (Status: Accepted-Deviation)

- **DEC-FEATURE-027-NO-TESTS** — No PHPUnit tests for Library module; PHPStan L8 + PHPCS + ESLint remain required gates. (Status: Accepted-Deviation)

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** — `Ability_Definition` constructor self-registers filter, bypassing Loader; `class_exists()` guard required at add-on bootstrap. (Status: Accepted-Deviation)

---

## Relevant Security Constraints

- **SC-027-01** — `AcrossAI_Ability_Library_Config::save_config()` enforces: `MAX_KEY_LENGTH=100` (after `sanitize_key()`), `MAX_KEYS=50`, `MAX_SUB_KEYS=50`. `mode` validated against `['all', 'specific']`. Booleans cast strictly. (Source: security-constraints.md)

- **SC-027-02** — `AcrossAI_Ability_Library_Registry::validate_and_normalize()` uses `ALLOWED_ARGS_FIELDS` allowlist + `array_intersect_key()` before passing to `wp_register_ability()`. (Source: security-constraints.md)

- **SC-027-03** — `LibraryCard.js` renders `main_key_label`/`sub_key_label` as JSX text only — no `dangerouslySetInnerHTML`. Labels sanitized with `wp_kses_post()` at PHP layer before localization. (Source: security-constraints.md)

- **SC-027-05/06** — `AcrossAI_Ability_Library_Rest_Controller` is standalone singleton (no `WP_REST_Controller`). `check_permission()` two-gate: `manage_options` + `wp_verify_nonce(X-WP-Nonce, 'wp_rest')`. (Source: security-constraints.md)

---

## Related Historical Lessons

- **BUG-MCP-PUBLIC-KEY-MAPPING** — `meta.mcp.public` → `show_in_mcp` is canonical; flat `mcp_public` key is never read by the Merger. `Ability_Definition` subclass authors must use nested `meta['mcp']['public']`/`meta['mcp']['type']` structure. (Reason: Ability_Definition example code must be correct)

- **BUG-UNCONDITIONAL-ASSET-INCLUDE** — Library `.asset.php` guarded with `file_exists()` in `admin/Main.php` constructor (T010). Pattern must be repeated for any future asset bundle additions. (Reason: established pattern for this feature)

- **BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD** — Literal `/abilities/config` registered before wildcard routes inside `AcrossAI_Ability_Library_Config_Controller::register_routes()`. (Reason: Library config route is a literal path)

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR + BUG-EXTERNAL-PACKAGE-CTOR-SILENT** — `Ability_Definition` constructor self-registers; `class_exists()` guard on the add-on side protects against missing manager. Guard must be narrow — only the `class_exists` check; `new X()` instantiation outside any suppression. (Reason: Ability_Definition bootstrap pattern now in use)

---

## Conflict Warnings

- None. Logger namespace migration is complete (T023 done). All five touch-points updated atomically.
- `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` conflicts with `AC-HOOKS-MAIN` by design; accepted deviation resolves this for self-registering constructors.
- `DEC-FEATURE-027-NO-TESTS` relaxes §VII DoD; all other quality gates remain active.

---

## Retrieval Notes

- Index entries considered: 20 (all entries reviewed; top 10 by relevance to Library module selected)
- Decisions selected: 8 (DEC-MENU-HOOK-SUFFIX, DEC-LOGGER-NAMESPACE-MIGRATION, DEC-ABILITYAPI-NAMESPACE, DEC-DESIGN-OVERRIDES-DATAVIEWS, PATTERN-ADDON-FILTER-LATE-INIT, DEC-EXTERNAL-PACKAGE-HOOK-CTOR, DEC-UTILITY-STATIC-ONLY, DEC-FEATURE-027-NO-TESTS)
- Architecture constraints selected: 7 (AC-HOOKS-MAIN, AC-ENQUEUE-ADMIN, AC-REST-SPLIT, AC-FILE-HEADER-PATTERN, PATTERN-ADDON-FILTER-LATE-INIT, BUG-UNCONDITIONAL-ASSET-INCLUDE, BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD)
- Security constraints: 4 (SC-027-01/02/03/05/06)
- Synthesis refreshed: 2026-06-09 — updated module names from AbilityAPI → Library, added T029 Ability_Definition status, resolved all hard conflicts
- Budget status: Within 900-word limit

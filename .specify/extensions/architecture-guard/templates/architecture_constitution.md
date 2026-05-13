# Architecture Constitution — AcrossAI Abilities Manager

> This is the architecture enforcement document that Architecture Guard reviews against.
> Project-level governance lives in `.specify/memory/CONSTITUTION.md`.

## Architecture Style

- **Style**: Modular Monolith (WordPress plugin)
- **Primary stack**: PHP 7.4+ / WordPress 6.9+ (backend) + React / @wordpress/scripts (admin UI)
- **Preset guidance**: WordPress plugin boilerplate (WPBoilerplate)

## Layer Boundaries

| Layer | Owns | May Depend On | Must Not Depend On |
| --- | --- | --- | --- |
| Entry (REST / admin hooks) | HTTP route registration, permission checks, request/response mapping | Application layer (utilities, query classes) | Domain internals, raw `$wpdb` directly |
| Application (Utilities) | Sanitization, merging, source detection, registry queries | WordPress APIs, BerlinDB Query classes | REST controller internals, admin classes |
| Data (BerlinDB) | DB schema, table creation, CRUD operations | WordPress `$wpdb`, BerlinDB base classes | REST layer, admin layer, business logic |
| Admin UI (admin/) | Asset enqueue, menu registration, page render | WordPress enqueue APIs | `includes/Modules/` internals, BerlinDB directly |
| JS (React) | DataViews table, DataForms drawer, Redux store | `@wordpress/*` packages, REST API via apiFetch | Raw DOM, jQuery, custom bundled frameworks |

## Business Logic Placement

- REST controllers validate + sanitize input, then delegate to Utility classes — no inline filter/sort/paginate logic in controllers.
- `AcrossAI_Ability_Registry_Query` (Utilities) owns all filter/sort/pagination over `wp_get_abilities()`.
- `AcrossAI_Ability_Merger` (Utilities) owns registry + override merge; REST controllers call it, never inline.
- `AcrossAI_Ability_Source_Detector` (Utilities) owns source detection; controllers call it before `save_override()`.
- BerlinDB Query classes (`AcrossAI_Sitewide_Query`) are pure data access objects — no business logic, no source detection.

## Contracts and Validation

- **Request contracts**: All REST route args declared explicitly in `register_rest_route()` `args` array with `type`, `required`, and `sanitize_callback`. Missing args cause WordPress to return "Invalid parameter(s)".
- **Response contracts**: `rest_ensure_response()` wraps all REST responses; `X-WP-Total` / `X-WP-TotalPages` headers on paginated endpoints.
- **Partial-field save**: PHP handler uses `$request->has_param( $field )` — never `get_param()` for absent optional fields. Prevents tab A save from overwriting tab B's DB values with NULL.
- **Validation boundary**: All external input sanitized at the REST boundary via `AcrossAI_Sanitizer` static methods before any DB or business logic call.

## Data Access Rules

- All DB access goes through BerlinDB Query classes (`AcrossAI_Sitewide_Query`). Raw `$wpdb` is only inside BerlinDB internals.
- `add_item()` returns integer ID on success or `false`; check with `$result !== false && (int) $result > 0`.
- `update_item()` returns the updated object on success or `false`; first arg is the integer primary key, never the slug string.
- PHP boolean tri-state values MUST be cast to `(int)` before BerlinDB (`true → 1`, `false → 0`, `null` left as null). PHP `false` is not an integer; `$wpdb` assigns `%s` format and MySQL 8+ strict mode rejects `''` for `tinyint`.
- `mcp_servers` PHP array MUST be `wp_json_encode()`d before BerlinDB INSERT/UPDATE; JSON-decoded on Row read.
- Nullable BerlinDB columns MUST use `'allow_null' => true` (not `'null' => true`) — the correct key for BerlinDB `Column::parse_args()`.

## Singleton Pattern (Plugin-Wide Convention)

Every feature class (REST controllers, DB Table, DB Query, etc.) MUST implement:

```php
protected static $_instance = null;

public static function instance(): self {
    if ( null === self::$_instance ) {
        self::$_instance = new self();
    }
    return self::$_instance;
}

private function __construct() {
    // dependencies obtained via other ::instance() calls only
}
```

`includes/Main.php` is the ONLY place that calls `$this->loader->add_action()`. All hooks trace directly to `define_admin_hooks()` or `define_public_hooks()`. There is no `Module_Base` abstract class, no `register_hooks( Loader $loader )` delegation, and no `includes/Base/` directory.

## Hook Registration Rules

- `admin_enqueue_scripts` → `Admin\Main::enqueue_styles()` and `enqueue_scripts()` (scoped via `$hook_suffix` guard, manifest loaded in constructor).
- `admin_menu` → `Admin\Partials\Menu::main_menu()`.
- `rest_api_init` → `AcrossAI_Sitewide_Rest_Controller::instance()->register_routes()` (wired in `define_admin_hooks()`).
- `Partials\*` classes own ONLY menu registration and HTML render — never `wp_enqueue_*()`.
- Asset `*.asset.php` manifests loaded in `Admin\Main::__construct()`, never in Partials classes or module classes.

## JS Architecture Rules

- Redux store: `createReduxStore( 'acrossai-abilities/sitewide', ... )` registered via `register()` from `@wordpress/data`.
- React entry: `createRoot( document.getElementById('acrossai-abilities-manager-root') )` in `src/js/sitewide/index.js`.
- Edit panel: `createPortal` slide-in drawer (FR-021 prohibits blocking modal).
- `useEffect` dep in `AbilityEditPanel` MUST be `[slug]` — never `[ability]`. Using `[ability]` re-seeds draft on every `UPDATE_ABILITY` dispatch.
- `deleteOverride` optimistic dispatch MUST include `_override: { site_allowed: null, ... }` (all 8 fields null) to clear stale values from the shallow-spread reducer.
- Tri-state serialization: `RadioControl` value is a string (`'true'`/`'false'`/`'null'`); convert to JS `true`/`false`/`null` on save. Never use `Boolean()` or `!!` — collapses `null` and `false`.

## Module Boundaries

| Module | Owns | Public Contracts | Must Not |
| --- | --- | --- | --- |
| Sitewide | Per-site ability overrides (allow/deny, metadata) | REST `/acrossai-abilities-manager/v1/sitewide/*`; WordPress hooks `acrossai_abilities_sitewide_before_save`, `_after_save` | Access PerUser or McpServer DB tables directly |
| Utilities | Sanitization, merging, source detection, registry queries | Static methods on `AcrossAI_Sanitizer`, `AcrossAI_Ability_Merger`, `AcrossAI_Ability_Registry_Query`, `AcrossAI_Ability_Source_Detector` | Register hooks, access admin globals |

## Blocking Architecture Violations (P0)

- P0: Any REST endpoint missing `permission_callback` with `manage_options` + nonce check.
- P0: Any `wp_enqueue_script()` / `wp_enqueue_style()` call outside `Admin\Main::enqueue_styles()` / `enqueue_scripts()`.
- P0: Any `$this->loader->add_action()` call outside `includes/Main.php::define_admin_hooks()` or `define_public_hooks()`.
- P0: Any feature class using constructor injection instead of the singleton `instance()` pattern.
- P0: Any BerlinDB boolean field passed as PHP `false` without `(int)` cast (causes silent MySQL strict mode failure).
- P0: `$request->get_param()` used for optional REST params instead of `$request->has_param()` gated collection (partial-tab save overwrite bug).

## Accepted Architecture Deviations

- `rest_api_init` hook is wired in `define_admin_hooks()` (not `define_public_hooks()`) because the sitewide management endpoints are admin-only management APIs.
- `AcrossAI_Ability_Registry_Query` accepts `AcrossAI_Sitewide_Query` as a parameter (not via `::instance()`) to keep it testable — this is the only sanctioned exception to the singleton convention for utility classes.

## Architecture Evolution Policy

- Repeated drift from the singleton + direct-wiring pattern should produce a Constitution Update Proposal (bump MINOR version).
- New module integration patterns require explicit approval in `plan.md` before implementation.
- Migration plans are incremental and module-scoped; cross-module refactors require a dedicated spec.

## Refactor and Drift Handling

- P0 violations (see above) block merge; must be fixed before PR approval.
- P1 drift (e.g., hook wired outside Main.php) becomes an immediate refactor task in tasks.md.
- P2 drift (e.g., inline business logic in a controller) is tracked as near-term technical debt.
- P3 cleanup (naming, comment quality) is opportunistic and must not block feature delivery.

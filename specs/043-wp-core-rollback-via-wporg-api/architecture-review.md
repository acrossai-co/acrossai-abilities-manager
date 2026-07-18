# Architecture Review: Feature 043

**Feature**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)

## Verdict

**PASS** — no violations of Constitution §I (Modular Architecture), §V (Extensibility Without Core Modification), or §VI (DRY / Utilities). Feature 043 stays inside the Core Category folder introduced by Feature 042; adds one new ability class + tests + spec-kit. No new module, no new Category folder, no new utility class. Uses only WordPress functions + `Core_Upgrader::upgrade()` — no bundled updater code, no custom HTTP infrastructure.

## Module Enumeration (§I)

Constitution §I locks the module list at 5. Feature 043 confirms no new module was added:

| Module | Touched? | Notes |
| --- | --- | --- |
| Per-User Access Control | No | Access-control code untouched. |
| MCP Server Management | No | MCP surface untouched. |
| Custom Ability Registration | Yes (extended) | Third ability added under the existing Core Category folder. |
| WebMCP Integration | No | WebMCP surface untouched. |
| AbilityAPI Registration Management | No | Registration mechanism unchanged; new ability self-registers via `Ability_Definition::__construct`. |

## Category Folders

Feature 043 adds ZERO new Category folders. The Core folder introduced in Feature 042 now contains three abilities: `Wp_Core_Update_Check`, `Wp_Core_Update`, `Wp_Core_Rollback`.

## Utility Placement (§VI)

Feature 043 does NOT add any new utility class. The `fetch_offer()` + `fetch_all_offers()` helpers are private methods on `Wp_Core_Rollback` — appropriate for single-use logic. If a second future ability needs the WP.org Core API (e.g. a `Wp_Core_Versions_List` ability), the helpers would be lifted into `includes/Abilities/Utilities/Core_Offers_Fetcher.php` per Constitution §VI. Not yet.

## Ability_Definition Base Class

Unchanged. `Wp_Core_Rollback` subclasses it and implements `ability(): array` + `execute(array $input): array`. Constructor auto-hooks `acrossai_abilities_api_init` via the base class.

## Bootstrap Wiring

`AcrossAI_Core_Abilities_Bootstrap::register_abilities()` grew by ONE `new Core\Wp_Core_Rollback();` line next to the two existing Feature 042 abilities. `register_category_callbacks()` is UNTOUCHED — the Core Category_Registrar was registered in Feature 042 and applies to all abilities under `Core/`.

## Boot Flow Rule (§I AC-HOOKS-MAIN)

`includes/Main.php::define_public_hooks()` already wires the bootstrap via a named variable. No changes to `Main.php` needed.

## Extensibility Contracts (§V)

None claimed by Feature 043. The four extensibility hooks (`wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `mcp_adapter_pre_tool_call`) remain unclaimed.

## DataForm / DataViews (§III)

No new admin UI added. The new ability renders through the existing Library page's React components.

## Composer / Dependency (§II)

No new composer requires. Uses only:

- PHP built-ins: `json_decode`, `version_compare`, `rawurlencode`
- WordPress core: `wp_remote_get`, `wp_remote_retrieve_body`, `wp_remote_retrieve_response_code`, `get_site_transient`, `set_site_transient`, `sanitize_key`, `sanitize_text_field`, `get_locale`, `get_bloginfo`, `home_url`, `Core_Upgrader`, `WP_Ajax_Upgrader_Skin`, `is_multisite`, `current_user_can`, `is_wp_error`, `WP_Error`, `DAY_IN_SECONDS`

Zero third-party libs.

## Outbound HTTP surface

Feature 043 introduces the plugin's FIRST outbound HTTP request (to `api.wordpress.org/core/version-check/1.7/`). This is a documentation-worthy change to the plugin's external surface — noted in the plan and security-constraints. Mitigations:

- Hardcoded URL as a class constant (no SSRF surface — C-043-SEC-05).
- Per-locale cache with `DAY_IN_SECONDS` TTL — bounds request rate to ≤ 1/day/locale/site (C-043-SEC-09).
- `timeout => 15` (C-043-SEC-10).
- Standard WordPress User-Agent (C-043-SEC-11).
- No user-controlled URL segments (only sanitized locale in query string).

## Follow-up (out of scope for 043)

- Extract `fetch_all_offers()` + `fetch_offer()` into a shared `Core_Offers_Fetcher` utility when a second consumer appears.
- Add a `Wp_Core_Versions_List` ability that exposes the cached offer list as JSON — would share the transient with `Wp_Core_Rollback`.
- Add a `Wp_Core_Cache_Clear` ability that busts the offer cache manually (for post-mortem debugging).
- Restore-plugin-state-post-failure: automatic re-upgrade to the pre-rollback version if `Core_Upgrader` reports failure mid-upgrade (would need exception handling around WP core's own upgrade path, which is not currently exposed).

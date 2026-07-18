# Architecture Review: Feature 041

**Feature**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)
**Backfilled**: 2026-07-18

## Verdict

**PASS** — no violations of Constitution §I (Modular Architecture) or §V (Extensibility Without Core Modification). No new modules; no cross-module leakage; no ability code touched core WP internals directly (all upgrade paths go through the WP-provided `Plugin_Upgrader` / `Theme_Upgrader` / `unzip_file()` / `download_url()`).

## Module Enumeration (§I)

Constitution §I locks the module list at 5. Feature 041 confirms no new module was added:

| Module | Touched? | Notes |
| --- | --- | --- |
| Per-User Access Control | No | Access-control code untouched. |
| MCP Server Management | No | MCP surface untouched. |
| Custom Ability Registration | Yes (extended) | 8 new abilities added under existing `FileManager/`, `Plugins/`, `Themes/` Category folders — same layer that the 176 absorbed abilities live in. No new Category folder created. |
| WebMCP Integration | No | WebMCP surface untouched. |
| AbilityAPI Registration Management | No | Registration mechanism unchanged; new abilities self-register via `Ability_Definition::__construct`. |

The 0.0.10 fix likewise stays inside `FileManager/Zip_Create.php` — no scope creep to other modules.

## Category Folders (existing)

Feature 041 uses **three** existing Category folders and creates **zero** new ones:

- `includes/Abilities/FileManager/` — six new Zip abilities added next to `File_Read` / `File_Create` / etc.
- `includes/Abilities/Plugins/` — `Plugin_Update` added next to `Plugin_Activate` / `Plugin_Install` / `Update_Check`.
- `includes/Abilities/Themes/` — `Theme_Update` added next to `Theme_Activate` / `Theme_Install` / etc.

## Utility Placement (§VI)

Two new utilities placed under `includes/Abilities/Utilities/`, matching the pattern used by `File_Mods_Guard`, `Plugin_Helpers`, `Theme_Helpers`, `Cron_Helpers`, `Mime_Types_Store`.

Both are introduced on their second use per Constitution §VI "shared logic → Utilities before second use":

- `Backups_Storage` — called by all 6 Zip_* abilities. Six callers, plainly justified.
- `Zip_Target_Resolver` — called by `Zip_Create` and `Zip_Extract`. Two callers, justifies extraction.

## Ability_Definition Base Class

Unchanged. Feature 041 subclasses continue the same pattern the 176 absorbed abilities use: implement `ability(): array` returning `['name', 'args' => [label, description, category, execute_callback, permission_callback, input_schema, output_schema, meta]]`, plus an `execute(array $input): array` method. Constructor auto-hooks `acrossai_abilities_api_init` via the base class.

## Bootstrap Wiring

`AcrossAI_Core_Abilities_Bootstrap::register_abilities()` grew by 8 `new …()` lines + 2 lines for the Zip_Upload sweeper cron next to the existing Upload_Media sweeper. `register_category_callbacks()` is UNTOUCHED — no new Category to register.

## Boot Flow Rule (§I AC-HOOKS-MAIN)

`includes/Main.php::define_public_hooks()` already wires `$core_abilities_bootstrap = AcrossAI_Core_Abilities_Bootstrap::instance();` before adding it to the loader. No changes to `Main.php` were needed. Every `add_action` still traces back to `Main.php` on a named variable.

## Extensibility Contracts (§V)

None claimed by Feature 041. The four extensibility hooks (`wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `mcp_adapter_pre_tool_call`) remain unclaimed. Third-party plugins can still consume them without Feature 041 interference.

## DataForm / DataViews (§III)

No new admin UI added by Feature 041. The 8 new abilities render inside the existing Library page using the existing DataForm/DataViews-backed React components — no bypass, no violation.

## Composer / Dependency (§II)

No new composer requires. All work uses PHP built-ins (`ZipArchive`, `RecursiveIteratorIterator`, `microtime()`) and WordPress core (`unzip_file`, `WP_Filesystem`, `download_url`, `Plugin_Upgrader`, `Theme_Upgrader`, `WP_Ajax_Upgrader_Skin`, `wp_get_upload_dir`, `wp_generate_password`, `sanitize_key`). Zero third-party libs added.

## Fix architectural impact

The 0.0.10 fix modifies only `Zip_Create::append_dir_to_zip()` + `Zip_Create::estimate_tree_size()` and adds two private helpers (`normalize_relative()`, `has_hidden_segment()`). No cross-file impact, no module-boundary change.

## Follow-up (out of scope for 041)

- Backup filename scheme rebalance — moved to Feature 042 (`{slug}-{unix}-{ms}.zip` for readability + sortability at the cost of enumeration-by-guessing defense; directory listing still disabled by `.htaccess`).
- WordPress core update ability — moved to Feature 042 under a new Core category folder.

No violations to remediate; both follow-ups are additive, opt-in, and do not touch Feature 041's contract.

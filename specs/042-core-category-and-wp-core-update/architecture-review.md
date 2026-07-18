# Architecture Review: Feature 042

**Feature**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)

## Verdict

**PASS** — no violations of Constitution §I (Modular Architecture), §V (Extensibility Without Core Modification), or §VI (DRY / Utilities). No new module. One new Category folder (`Core/`) joins the existing 17 as a sub-partition of the Custom Ability Registration module. Two new abilities auto-register via the existing `Ability_Definition` filter; bootstrap wiring is three lines of literal add-lines (one Category_Registrar callback + two `new Core\…()` instantiations) matching the pattern every other absorbed ability uses. No custom HTTP or updater code.

## Module Enumeration (§I)

Constitution §I locks the module list at 5. Feature 042 confirms no new module was added:

| Module | Touched? | Notes |
| --- | --- | --- |
| Per-User Access Control | No | Access-control code untouched. |
| MCP Server Management | No | MCP surface untouched. |
| Custom Ability Registration | Yes (extended) | New `Core/` Category folder joins the existing 17 sub-partitions. Two new abilities inside. |
| WebMCP Integration | No | WebMCP surface untouched. |
| AbilityAPI Registration Management | No | Registration mechanism unchanged; new abilities self-register via `Ability_Definition::__construct`. |

## Category Folders

Feature 042 creates ONE new Category folder — `includes/Abilities/Core/` — bringing the count from 17 to 18. Every existing Category folder shares the same three-file shape (Category_Registrar + one or more ability classes). The Core folder mirrors that shape exactly.

## Utility Placement (§VI)

Feature 042 does NOT add any new utility class. `Backups_Storage::random_backup_filename()` gets two new private helpers (`filename_slug_segment()`, `filename_time_segments()`) inside the same file — private-method extraction to keep the public method readable. Not a shared-utility introduction under Constitution §VI.

## Ability_Definition Base Class

Unchanged. Both new abilities subclass it; both implement `ability(): array` and `execute(array $input): array`. Constructor auto-hooks `acrossai_abilities_api_init` via the base class.

## Bootstrap Wiring

`AcrossAI_Core_Abilities_Bootstrap::register_category_callbacks()` grew by ONE line — the new Core Category_Registrar registration. `register_abilities()` grew by TWO `new Core\…()` lines. All three edits sit next to the existing SiteHealth entries (bottom of each list) to preserve the visual ordering that mirrors Category-folder-ordering.

## Boot Flow Rule (§I AC-HOOKS-MAIN)

`includes/Main.php::define_public_hooks()` already wires the bootstrap via a named variable. No changes to `Main.php` needed. Every `add_action` still traces back to `Main.php` on a named variable.

## Extensibility Contracts (§V)

None claimed by Feature 042. The four extensibility hooks (`wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `mcp_adapter_pre_tool_call`) remain unclaimed.

## DataForm / DataViews (§III)

No new admin UI added by Feature 042. Both new abilities render through the existing Library page's React components. No bypass, no violation.

## Composer / Dependency (§II)

No new composer requires. All work uses PHP built-ins (`microtime`, `str_pad`, `floor`) and WordPress core (`get_core_updates`, `find_core_update`, `Core_Upgrader`, `WP_Ajax_Upgrader_Skin`, `get_bloginfo`, `get_locale`, `is_multisite`, `current_user_can`, `sanitize_text_field`, `sanitize_key`). Zero third-party libs.

## Spec-Kit Backfill Impact

Feature 042 also authors the backfilled `specs/041-backup-restore-abilities-and-updates/` folder documenting the already-shipped Feature 041 + the 0.0.10 fix. This is a documentation-only backfill inside `specs/`; it does NOT modify any Feature 041 shipped code and is not a re-open of Feature 041's contract.

## Follow-up (out of scope for 042)

- Fixing the mojibake `â` character in all 18 Category_Registrar labels (must be done uniformly across the codebase in a separate cosmetic feature).
- Adding a network-wide core-upgrade ability for multisite (out of scope per spec.md).
- Adding a `Wp_Core_Downgrade` ability (WP core has no `Downgrader`; would need a bespoke implementation with different security posture).

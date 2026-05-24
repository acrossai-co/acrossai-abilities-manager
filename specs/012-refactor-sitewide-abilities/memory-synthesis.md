# Memory Synthesis

## Current Scope
PHP-only module consolidation: decommission `includes/Modules/Sitewide/` and merge all durable logic (BerlinDB Table/Schema/Row, Query, Override Processor, Access Control) into `includes/Modules/Abilities/`. Delete Sitewide REST controllers. Update `includes/Main.php` bootstrap wiring. Update 4 named non-Sitewide files with Sitewide imports. No UI, no DB schema, no table name, no hook name changes.

## Relevant Decisions

- **DEC-TABLE-SOFT-SINGLETON** — BerlinDB Table subclasses MUST use soft singleton (`$_instance` + `instance()`) with NO `private function __construct()`. `AcrossAI_Activator` calls `(new AcrossAI_Abilities_Table())->maybe_upgrade()` directly. A private constructor causes a PHP fatal. (Reason: new `AcrossAI_Abilities_Table` directly replaces `AcrossAI_Sitewide_Table` in Activator. Source: DECISIONS.md)

- **DEC-JSON-SIZE-GUARD** — `save_override()` MUST maintain the `$max_json_bytes = 65536` guard inside the JSON registry loop when ported to `AcrossAI_Abilities_Query`. Store `null` (not empty string) when limit exceeded. If the constant changes, update both DB layer and REST validator. (Reason: porting save_override. Source: DECISIONS.md)

- **DEC-BY-SOURCE-AUTHZ** — All ported query methods (`get_override_by_slug`, `save_override`, `delete_override_by_slug`, `get_all_overrides`) MUST remain authorization-free DB helpers with an `AUTHORIZATION CONTRACT` docblock. Callers gate via `permission_callback`. (Reason: architectural contract preserved during port. Source: DECISIONS.md)

- **DEC-NAMESPACE-CONVENTION** — All renamed/moved classes MUST use the `AcrossAI_Abilities_Manager\Includes\Modules\Abilities\*` namespace with underscore convention. All `use` statements must follow the same pattern. (Reason: all class moves. Source: DECISIONS.md)

- **DEC-UTILITY-STATIC-ONLY** — `AcrossAI_Abilities_Formatter` (Utilities) is 100% static; no singleton. Its method type-hints change from `AcrossAI_Sitewide_Row` to `AcrossAI_Abilities_Row` but the static-only constraint must be preserved. (Reason: FR-010 touches Formatter. Source: DECISIONS.md)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** — Only `includes/Main.php` calls `$this->loader->add_action/filter`. Singleton instances MUST be resolved to a named variable before being passed to the Loader. No inline `::instance()` calls. (Reason: FR-006, FR-011. Source: CONSTITUTION.md §I)

- **ARCH-UNIFIED-ABILITIES-STORAGE** — The `acrossai_abilities` table is the single unified store. Override rows are identified by `source` semantics (source=db). The table name must not change; only PHP class names change. (Reason: confirms no migration needed. Source: ARCHITECTURE.md)

- **SEC-03** — New `AcrossAI_Abilities_Table` MUST set `$global = false` to preserve per-site table prefix for multisite isolation. (Reason: direct copy from Sitewide_Table; must not accidentally set `$global = true`. Source: security-constraints.md)

- **AC-REGISTRY-QUERY** — `AcrossAI_Ability_Registry_Query::query()` is the only entry point for filtered/sorted/paginated list queries. After the refactor, its injected `$db_query` parameter type changes from `AcrossAI_Sitewide_Query` to `AcrossAI_Abilities_Query`. All callers must be updated. (Reason: FR-010 scope. Source: ARCHITECTURE.md)

- **AC-FILE-HEADER-PATTERN** — All moved/renamed files MUST carry `@package AcrossAI_Abilities_Manager`, `@subpackage AcrossAI_Abilities_Manager/includes/Modules/Abilities[/Database]`, `@since 0.1.0`. (Reason: PHPCS compliance. Source: ARCHITECTURE.md)

## Accepted Deviations

- **ARCH-ADV-001** — `AcrossAI_Ability_Override_Processor::boot()` wires hooks directly (bypasses Boot Flow Rule) for PATH-A/B conditional loading. This pattern MUST be preserved when the file is moved to the Abilities module. (Reason: Processor is being moved; deviation must travel with it. Status: Active deviation)

## Relevant Security Constraints

- **SEC-01** — `sanitize_ability_slug()` must be applied to slug parameters in ported query methods (`get_override_by_slug`, `delete_override_by_slug`) before DB calls. (Reason: new override CRUD methods handle slugs. Source: security-constraints.md)

- **SEC-03** — (see AC above) `$global = false` must be preserved in `AcrossAI_Abilities_Table`. (Source: security-constraints.md)

- **SEC-04** — `AcrossAI_Abilities_Access_Control` must preserve strict `===` type comparisons from `AcrossAI_Sitewide_Access_Control`. (Reason: BUG-LOOSE-COMPARISON-BYPASS. Source: security-constraints.md)

## Related Historical Lessons

- **BUG-BERLINDB-UNLIMITED** — `get_all_overrides()` MUST use `number => 0` (no LIMIT), NOT `number => -1` (absint(-1)=1 → LIMIT 1) or arbitrary large integers. Already fixed in Sitewide version; preserve in port. (Reason: direct port of `get_all_overrides`. Evidence: fixed 2026-05-16)

- **BUG-PARTIAL-HOOK-FIELDS** — `save_override()` fires `after_save` hooks. Partial-save paths must not fire with incomplete `$fields`. Must be preserved in the ported version. (Reason: porting save_override. Source: BUGS.md)

- **Design decision reversal** — `AcrossAI_Abilities_Query` docblock explicitly documents reuse of Sitewide Schema/Row as intentional. This design decision is deliberately superseded by Feature 012. Docblock must be updated to reflect the new self-contained architecture.

## Conflict Warnings

- None. The refactor is architecturally aligned with the goal of eliminating duplicate module ownership. The `ARCH-UNIFIED-ABILITIES-STORAGE` constraint confirms a single table was always the intent; this refactor consolidates the PHP ownership layer to match.

## Retrieval Notes

- Index entries considered: 20 (all reviewed)
- Source sections read: DECISIONS.md (DEC-TABLE-SOFT-SINGLETON, DEC-JSON-SIZE-GUARD, DEC-BY-SOURCE-AUTHZ), BUGS.md (BUG-BERLINDB-UNLIMITED lines 8–32, BUG-LOOSE-COMPARISON-BYPASS)
- Entries selected: 5 decisions, 5 arch constraints, 3 security, 3 bug patterns, 1 accepted deviation
- Budget: within max_synthesis_words (900); full_memory_read: false

# Feature Specification: Library display fields under `$args['meta']['acrossai']`

**Feature Branch**: `041-library-fields-meta-acrossai-namespace`
**Created**: 2026-07-03
**Status**: Implemented
**Input**: User description: "we have three diff `$sub_group = isset( $args['sub_group'] ) ? (string) $args['sub_group'] : '';`, `$tab_group = isset( $args['tab_group'] ) ? (string) $args['tab_group'] : '';`, and `$row['sub_group_label'] = isset( $args['sub_group_label'] ) && '' !== $args['sub_group_label'] ? (string) $args['sub_group_label'] : ucwords( str_replace( '-', ' ', $sub_group ) );`. We are going to pass this in meta with the key as 'acrossai' like we have for mcp, annotations etc. Use spec-kit; update memory as needed. Send via PR."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Add-on developer declares Library display fields under a consistent namespace (Priority: P1)

An add-on plugin developer extends the abstract `Ability_Definition` base class to register a Library ability. To attach a sub-group heading or tab-group filter, they declare those fields under `$args['meta']['acrossai']` — matching the same nesting pattern already used for `$args['meta']['mcp']['type']`, `$args['meta']['annotations']['readonly']`, and `$args['meta']['show_in_rest']`. The developer never has to remember which fields belong at the top level of `$args` vs under `meta`; all plugin-specific extensions consistently live under `meta.acrossai`.

**Why this priority**: Consistency of the extension API is the single most important quality for third-party integrators. The Features 033 and 037 top-level shape violated the plugin's own established pattern from day one and creates confusion for new integrators reading the codebase.

**Independent Test**: Register a new `Ability_Definition` subclass on a test install with a `meta.acrossai.sub_group` field. Visit WP-Admin → AcrossAI → Library. The ability card renders under the correct sub-group heading. Register a second subclass using the OLD top-level `args['sub_group']` shape. The card renders WITHOUT a sub-group heading (proves hard cut).

**Acceptance Scenarios**:

1. **Given** an add-on plugin extending `Ability_Definition` and returning `['args' => ['label' => 'X', 'category' => 'y', 'meta' => ['acrossai' => ['sub_group' => 'debug-log']]]]` from `ability()`, **When** the Library page renders, **Then** the ability appears under a "Debug Log" sub-heading (auto-derived via `ucwords(str_replace('-', ' ', 'debug-log'))`).
2. **Given** an add-on plugin returning the OLD top-level shape `['args' => ['sub_group' => 'debug-log']]`, **When** the Library page renders, **Then** the ability appears WITHOUT any sub-heading — the top-level field is silently ignored (hard cut). No error is emitted, no exception thrown; the ability still registers correctly for execution.
3. **Given** an add-on plugin providing BOTH shapes (`args['sub_group'] = 'legacy'` AND `args['meta']['acrossai']['sub_group'] = 'canonical'`), **When** the Library page renders, **Then** only the `meta.acrossai.sub_group` value is honored — the top-level shape is ignored regardless of ordering.

---

### User Story 2 — Plugin maintainer consolidates plugin-specific ability fields (Priority: P2)

A future contributor to `acrossai-abilities-manager` adds a new plugin-specific field to the ability shape (e.g. a Library filter, a UI presentation hint). Because Feature 041 established the `meta.acrossai` namespace as the canonical location for such fields, the contributor places it under `meta.acrossai.<field>` without needing to justify the choice. The plugin's memory (`PATTERN-META-ACROSSAI-NAMESPACE`) documents the convention, so architecture-guard runs and code reviews catch any new top-level plugin-specific field.

**Why this priority**: Prevents future drift. Without the pattern captured in memory, the same Feature 033/037-style oversight would recur.

**Independent Test**: A hypothetical Feature 042 that adds a new Library display field must place it under `meta.acrossai.<new_field>`. The memory-synthesis step of the /speckit-plan workflow surfaces `PATTERN-META-ACROSSAI-NAMESPACE`; the plan's constitution-check surfaces `DEC-META-ACROSSAI-NAMESPACE`. Both guide the contributor to the correct namespace without further human intervention.

---

### Edge Cases

- **Empty `meta` array**: `$args['meta'] = []` (present but empty) → `meta_acrossai` extracted as `array()`, `$sub_group` remains `''`, `$tab_group` remains `''`, row-top fields omitted. Same behavior as `meta` key absent entirely.
- **Non-array `meta['acrossai']`**: `$args['meta']['acrossai'] = 'not-an-array'` (developer error) → `is_array()` guard triggers, `meta_acrossai` extracted as `array()`, fields omitted. No fatal, no warning; treated as absent.
- **Empty string sub_group**: `$args['meta']['acrossai']['sub_group'] = ''` → row-top `sub_group` omitted (already the pre-041 behavior; `'' !== $sub_group` guard). Same for `tab_group`.
- **Explicit `sub_group_label` fallback**: When `meta.acrossai.sub_group` is provided but `meta.acrossai.sub_group_label` is absent, the fallback `ucwords(str_replace('-', ' ', $sub_group))` kicks in. `'debug-log'` → `'Debug Log'`.
- **Legacy shape on an existing site upgrading to 0.0.4+ (post-041)**: any add-on plugin still using the top-level shape produces a Library card without the sub-group heading or tab-group tab. The ability itself still registers and executes normally; only the display grouping is lost. Add-on authors migrate over time; no data loss.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `Ability_Definition::push_definition()` MUST read `$sub_group` from `$args['meta']['acrossai']['sub_group']`. Top-level `$args['sub_group']` MUST NOT be read.
- **FR-002**: `Ability_Definition::push_definition()` MUST read `$tab_group` from `$args['meta']['acrossai']['tab_group']`. Top-level `$args['tab_group']` MUST NOT be read.
- **FR-003**: `Ability_Definition::push_definition()` MUST read `$sub_group_label` from `$args['meta']['acrossai']['sub_group_label']` when that key is present and non-empty; otherwise fall back to `ucwords(str_replace('-', ' ', $sub_group))`. Top-level `$args['sub_group_label']` MUST NOT be read.
- **FR-004**: `AcrossAI_Ability_Library_Registry::ALLOWED_ARGS_FIELDS` MUST NOT contain `'sub_group'`, `'sub_group_label'`, or `'tab_group'`. The `'meta'` entry MUST remain (all `meta.*` sub-keys pass through opaquely).
- **FR-005**: The `$row` shape emitted by `push_definition()` MUST remain unchanged from Features 033/037: `sub_group`, `sub_group_label`, `tab_group` at row-top when the corresponding meta.acrossai keys are present. This preserves the internal Registry contract and JS consumption via `window.acrossaiAbilityLibraryData` without any downstream code change.
- **FR-006**: When BOTH the canonical `meta.acrossai.*` shape AND the legacy top-level shape are present in `$args`, only the canonical shape MUST be honored. The legacy shape is silently ignored (no exception, no warning, no notice).
- **FR-007**: The `AcrossAI_Ability_Library_Config` saved-config surface MUST be unchanged. Feature 041 is scoped exclusively to the ability-definition input side; the config store and its persistence remain untouched.
- **FR-008**: The Library REST endpoints (`AcrossAI_Ability_Library_Config_Controller`) MUST NOT be affected. Feature 041 does not add or remove any REST field.

### Key Entities *(include if feature involves data)*

- **Ability Definition** — the abstract base class `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition`. Add-on plugins extend it and implement the single `ability()` method. Post-041, the `args` returned by `ability()` places plugin-specific Library display fields under `meta.acrossai`. Sibling namespace of `meta.mcp` (MCP-specific fields) and `meta.annotations` (WP-core annotations).
- **Library Registry Row** — the internal shape emitted by `push_definition()` and consumed by `AcrossAI_Ability_Library_Registry::validate_and_normalize()`. Row-top exposes `sub_group`, `sub_group_label`, `tab_group` when present (unchanged from Features 033/037). This is an internal contract; downstream JS reads row-top via `window.acrossaiAbilityLibraryData`.
- **`meta.acrossai` namespace** — new namespace introduced by Feature 041. Reserved for plugin-specific extension fields that are not owned by WordPress core (`annotations`, `show_in_rest`) or by the MCP integration (`mcp`). Currently populated by `sub_group`, `sub_group_label`, `tab_group`. Future plugin-specific fields SHOULD live here (per `PATTERN-META-ACROSSAI-NAMESPACE`).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After the Feature 041 refactor is deployed to a test install, an add-on plugin using the canonical `$args['meta']['acrossai']['sub_group']` shape produces a Library card with the expected sub-heading — first attempt, zero PHP warnings, zero JavaScript console errors.
- **SC-002**: After the Feature 041 refactor is deployed, an add-on plugin still using the legacy top-level `$args['sub_group']` shape produces a Library card WITHOUT a sub-heading. No fatal error, no PHP warning. The ability itself still registers correctly and executes normally via `wp_register_ability()`.
- **SC-003**: `AcrossAI_Ability_Library_Registry::ALLOWED_ARGS_FIELDS` contains exactly eight entries (`label`, `description`, `category`, `execute_callback`, `permission_callback`, `input_schema`, `output_schema`, `meta`) — down from eleven in Features 033/037.
- **SC-004**: `composer phpstan` (level 8), `composer phpcs`, and `composer test` all pass with zero errors. Test count changes by +2 (two new negative regression tests in `Test_Ability_Definition.php` covering FR-001 through FR-003 and FR-006).
- **SC-005**: `PATTERN-META-ACROSSAI-NAMESPACE` and `DEC-META-ACROSSAI-NAMESPACE` are captured in `docs/memory/ARCHITECTURE.md` and `docs/memory/DECISIONS.md` respectively. Both have corresponding rows in `docs/memory/INDEX.md`. The Feature 041 milestone is added to `docs/memory/WORKLOG.md`.
- **SC-006**: A grep of the plugin PHP surface for `\$args\['sub_group'\]`, `\$args\['tab_group'\]`, or `\$args\['sub_group_label'\]` (excluding docblocks and negative-regression tests) returns zero matches after Feature 041 ships.

## Assumptions

- **Hard cut is acceptable.** These fields were introduced 2-3 weeks ago (Feature 033 on 2026-06-14; Feature 037 on 2026-06-25). No known third-party add-on plugin is using the top-level shape in production; any such add-on can migrate to the nested shape with a trivial edit.
- **The `meta.acrossai` namespace is reserved for this plugin.** Other plugins that consume `wp_register_ability()` MUST NOT write to `meta.acrossai` — that key belongs to `acrossai-abilities-manager` exclusively. Future sibling plugins in the AcrossAI org (e.g. a future `acrossai-mcp-manager`) that need their own namespace SHOULD use their own key (`meta.acrossai_mcp`, `meta.acrossai_logs`, etc.) or a more granular convention.
- **Row shape is an internal contract, not a public one.** The `$row` structure passed from `Ability_Definition::push_definition()` to `AcrossAI_Ability_Library_Registry::validate_and_normalize()` is not consumed by third parties. Downstream JS (`LibraryPage.js`, `LibraryCard.js`) reads the row-top fields via a `wp_localize_script` payload. Feature 041 preserves the row shape and does not change the JS side.
- **No Constitution amendment required.** All §I–§VII principles are honored. The refactor is purely a consistency improvement on an existing plugin extension surface.
- **No downstream test failures.** The Library test suite is fully self-contained; no test outside `tests/phpunit/Modules/Library/` references `sub_group`, `tab_group`, or `sub_group_label`. Verified via `grep -rn` before implementation.

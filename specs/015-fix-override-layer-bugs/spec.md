# Feature Specification: Fix Override Layer Bugs + AbilityForm UI Improvements

**Feature Branch**: `015-fix-override-layer-bugs`  
**Created**: 2026-05-26  
**Status**: Draft  

## Overview

Harden the non-db ability override edit flow by resolving six discrete bugs identified and prototyped in the `code-done-via-claude` branch. These fixes span the PHP registry normalisation layer, the BerlinDB query/caching layer, the DB schema, the Redux store, and the AbilityForm JSX component.

---

## Clarifications

### Session 2026-05-26

- Q: When a non-db ability has never been saved, what does the REST response include for the `_override` field? → A: `_override` is always present as an object with all 13 overridable keys set to `null` (never omitted for non-db abilities).
- Q: What HTTP status code and error format should the Write controller return when `save_override()` returns `false`? → A: HTTP 500 with `WP_Error( 'save_override_failed', … )`.
- Q: Who applies the `ALTER TABLE` DDL change for Bug 5 schema update, and when? → A: Developer runs it once manually via WP-CLI or phpMyAdmin — no activation hook required.
- Q: How should `mcp.public` (read from nested `meta['mcp']['public']` in Bug 1 fix) be treated? → A: Surfaced in `_registry` for display only — not an overridable field, not stored in the DB override row (SC-015-C5).

## User Scenarios & Testing

### User Story 1 — MCP field values correctly normalised for third-party plugins (Priority: P1)

A site administrator views a non-db ability registered by an external plugin that uses the MCP-adapter nested meta convention (`meta['mcp']['type']`, `meta['mcp']['servers']`). The ability's MCP type, public flag, and server list must be correctly read from the registry and surfaced in the admin UI.

**Why this priority**: Incorrect normalisation silently hides MCP configuration for every external-plugin ability, making the override UI non-functional for the primary integration use case.

**Independent Test**: Register a test ability with nested `meta['mcp']` data. Open it in the admin UI and confirm that the MCP Exposure section shows the plugin-declared values.

**Acceptance Scenarios**:

1. **Given** an ability registered with `meta['mcp']['type'] = 'tool'`, **When** the admin opens that ability, **Then** the "Plugin declares:" MCP type hint shows `tool`.
2. **Given** an ability registered with `meta['mcp']['servers'] = [...]`, **When** the admin opens that ability, **Then** the MCP servers hint reflects the registered server list.
3. **Given** an ability registered with `input_schema` and `output_schema` as first-class properties, **When** the admin opens that ability, **Then** the schema fields show the registered values (not blank).
4. **Given** any non-db ability, **When** `$annotations` is null or not an array, **Then** normalisation completes without a PHP warning.

---

### User Story 2 — "Plugin declares:" hints show the raw registered value (including null) (Priority: P1)

A site administrator edits a non-db ability. Below each overridable field, a "Plugin declares: X" hint must display the raw value the plugin registered — not the merged/effective value. When the plugin did not declare a value, the hint reads "not set" rather than being hidden.

**Why this priority**: Without correct hints, administrators cannot tell what value the plugin registered, making informed override decisions impossible.

**Independent Test**: Register a test ability with `readonly: true`. Open it. Confirm the hint reads the registry `readonly` value; confirm that fields the plugin left null still show a hint of "not set".

**Acceptance Scenarios**:

1. **Given** a non-db ability where the plugin sets `readonly: true`, **When** the admin views the ability, **Then** the "Plugin declares:" hint for the readonly field reads the `_registry.readonly` value, not the merged top-level value.
2. **Given** a non-db ability where the plugin did not set `mcp_type`, **When** the admin views the ability, **Then** the MCP type hint renders "not set" (not hidden).
3. **Given** any of the 6 TriChip fields (show_in_mcp, mcp_type, readonly, destructive, idempotent, show_in_rest), **When** the registry value is null/undefined, **Then** the hint still renders with "not set".
4. **Given** text fields (Label, Category, Description), **When** the plugin declared no value, **Then** a "Plugin declares: not set" hint is shown.

---

### User Story 3 — Draft ability initialises from override values, not merged values (Priority: P1)

When a site administrator opens a non-db ability for editing, each overridable field in the draft must reflect the override record — null in `_override` means "inherit/default", not the plugin-declared value.

**Why this priority**: Seeding drafts from merged values makes TriChips appear pre-set when no override exists, misleading administrators about what they have customised.

**Independent Test**: Open a non-db ability that has no override record. Confirm every TriChip shows "default". Set an override and reload — confirm TriChips reflect only the saved overrides.

**Acceptance Scenarios**:

1. **Given** a non-db ability with no override record (`_override` absent or all null), **When** the admin opens it, **Then** all overridable TriChips show "default".
2. **Given** a non-db ability where `_override.readonly = null`, **When** the admin opens it, **Then** the readonly TriChip shows "default" even though the merged top-level `readonly` is `true`.
3. **Given** a non-db ability where `_override.show_in_mcp = true`, **When** the admin opens it, **Then** the show_in_mcp TriChip shows "yes".
4. **Given** all 13 overridable fields (`label`, `description`, `category`, `callback_type`, `callback_config`, `site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`), **When** loaded from `_override`, **Then** each reflects the override value (or default if null).

---

### User Story 4 — First save of an override immediately reflects in the UI (Priority: P1)

A site administrator saves an override for a non-db ability for the very first time. The REST response and the subsequent UI state must reflect the new override without requiring a page reload.

**Why this priority**: The stale-cache bug makes the first save appear to fail silently — the UI reverts to "default" state after a successful write, eroding user trust.

**Independent Test**: Open a non-db ability with no override. Set one field and save. Without reloading, confirm the field still shows the saved value and `has_override: true` is returned.

**Acceptance Scenarios**:

1. **Given** a non-db ability with no existing override, **When** the admin saves any override field, **Then** the REST response includes `has_override: true` and the populated `_override` object.
2. **Given** the first-save response, **When** the frontend processes `SET_SAVED`, **Then** the saved field retains its value (no revert to "default").
3. **Given** a second save of the same ability, **When** the admin saves again, **Then** the response again includes the correct `_override` values.
4. **Given** a page reload after first save, **When** the admin re-opens the ability, **Then** the override values match what was saved.

---

### User Story 5 — Override rows stored with nullable callback_type and status (Priority: P2)

When an override row is inserted for a non-db ability, the `callback_type` and `status` columns must be stored as `NULL` — not silently defaulted to `'noop'` or `'draft'`.

**Why this priority**: Incorrect DB defaults corrupt data integrity and could interfere with future features that query by `status` or `callback_type`.

**Independent Test**: Save an override for any non-db ability. Query `wp_acrossai_abilities` and confirm the row has `callback_type = NULL` and `status = NULL`.

**Acceptance Scenarios**:

1. **Given** a save-override payload that does not include `callback_type`, **When** the row is inserted, **Then** `callback_type` is `NULL` in the DB.
2. **Given** a save-override payload that does not include `status`, **When** the row is inserted, **Then** `status` is `NULL` in the DB.
3. **Given** the altered schema, **When** the plugin is activated on a fresh install, **Then** both columns are created as `DEFAULT NULL`.

---

### User Story 6 — AbilityForm section order and Status field visibility (Priority: P2)

A site administrator editing an ability sees sections in a logical order that puts governance controls (permissions, MCP exposure, annotations) before implementation details (callback, schema). For non-db abilities, the Status toggle is hidden.

**Why this priority**: Logical grouping reduces cognitive load; hiding the irrelevant Status field prevents administrator confusion.

**Independent Test**: Open a db-source ability and a non-db ability. Confirm section order and numbering for each variant; confirm the Status row is absent for non-db.

**Acceptance Scenarios**:

1. **Given** a db-source ability, **When** the admin opens it, **Then** sections appear in order: Identity (§1) → MCP Exposure (§2) → Annotation Overrides (§3) → Callback (§4) → Schema (§5).
2. **Given** a non-db ability, **When** the admin opens it, **Then** sections appear in order: Identity (§1) → Site Permission (§2) → MCP Exposure (§3) → Annotation Overrides (§4) → Callback (§5) → Schema (§6).
3. **Given** a non-db ability, **When** the admin opens the form, **Then** the Status toggle row is not rendered.
4. **Given** a db-source ability, **When** the admin opens the form, **Then** the Status toggle row is rendered as normal.

---

### Edge Cases

- What happens when a third-party plugin registers both flat `meta.mcp_type` and nested `meta['mcp']['type']`? The nested key takes precedence, flat key is the fallback.
- What happens when `get_ability_by_id()` returns false after INSERT (e.g., DB failure)? `save_override()` returns `false` and the Write controller returns an HTTP 500 response using `WP_Error( 'save_override_failed', 'Failed to confirm saved override.', [ 'status' => 500 ] )` (SC-015-C3).
- What happens when `_override` is present with all 13 values null? This is the canonical "never saved" state for a non-db ability. All TriChips show "default". The REST Read controller always returns this 13-key null-valued object for non-db abilities that have no override row in the DB (SC-015-C2).
- What happens when `input_schema` / `output_schema` are registered as empty arrays `[]`? These are normalised to `null` before storage in the registry snapshot.

---

## Requirements

### Functional Requirements

- **FR-001**: `normalize_registry()` MUST read MCP meta from `meta['mcp']` (nested) with fallback to flat `meta.mcp_type` / `meta.mcp_servers` for backward compatibility.
- **FR-002**: `normalize_registry()` MUST read `input_schema` and `output_schema` via `get_input_schema()` / `get_output_schema()` and normalise `[]` to `null`.
- **FR-003**: `normalize_registry()` MUST guard `$annotations` with `is_array()` before accessing its contents.
- **FR-004**: All 6 TriChip "Plugin declares:" hints MUST read from `savedAbility._registry.*` (not the merged top-level value).
- **FR-005**: "Plugin declares:" hints for TriChip fields MUST render unconditionally, showing `'not set'` when the registry value is null/undefined.
- **FR-006**: "Plugin declares:" hints for text fields (Label, Category, Description) MUST follow the same unconditional-render pattern.
- **FR-007**: The `SET_SAVED` reducer MUST seed all 13 overridable fields in `draftAbility` from `_override[field]` when `saved._override` is present. For non-db abilities, `_override` is always present in the REST response as an object with all 13 keys (values are null when no override has been saved). A null field value means "inherit/default" → TriChip shows "default".
- **FR-008**: `save_override()` MUST return `AcrossAI_Abilities_Row|false` instead of `bool`.
- **FR-009**: The INSERT path of `save_override()` MUST re-read the saved row via the private `get_ability_by_id( (int) $new_id )` helper (which calls `query(['id' => $id, 'number' => 1])`) after `add_item()` to bypass BerlinDB's stale slug query cache.
- **FR-010**: The UPDATE path of `save_override()` MUST re-read the saved row via the same `get_ability_by_id( $existing->id )` helper after `update_item()`.
- **FR-011**: The Write controller MUST use the row returned by `save_override()` directly and MUST NOT perform a separate `get_override_by_slug()` call after save.
- **FR-012**: The INSERT path of `save_override()` MUST explicitly set `callback_type = null` and `status = null` when absent from the input payload.
- **FR-013**: The DB schema for `callback_type` and `status` MUST be updated to `allow_null: true, default: null`.
- **FR-014**: The SQL `set_schema()` definition for `callback_type` and `status` MUST use `DEFAULT NULL` (removing `NOT NULL`).
- **FR-015**: AbilityForm sections MUST render in order: Identity → Site Permission (non-db only) → MCP Exposure → Annotation Overrides → Callback → Schema.
- **FR-016**: Section `sect-num` values MUST be correct for both the db-source variant (§1–§5) and the non-db variant (§1–§6).
- **FR-017**: The Status toggle row MUST be hidden for non-db abilities.
- **FR-018**: A new private method `get_ability_by_id( int $id )` MUST be added to `AcrossAI_Abilities_Query`; it encapsulates `query(['id' => $id, 'number' => 1])` and returns the first row or `false`. It is called only from within `save_override()`.
- **FR-019**: When `save_override()` returns `false`, the Write controller MUST return an HTTP 500 response via `new WP_Error( 'save_override_failed', 'Failed to confirm saved override.', [ 'status' => 500 ] )`.

### Constraints

- **CON-001**: `AcrossAI_Ability_Override_Processor` MUST NOT be modified.
- **CON-002**: The REST response shape (`_registry`, `_override`, top-level merged fields) MUST remain unchanged.
- **CON-003**: `save_override()` MUST remain slug-oriented at its call-site; the ID-based cache bypass is an internal implementation detail.
- **CON-004**: `prepare_fields_for_write()` enum guards MUST NOT be changed.
- **CON-005**: PHPStan level 8 MUST pass with zero errors after the return-type change on `save_override()`.
- **CON-006**: PHPCS MUST pass with zero errors.
- **CON-007**: ESLint MUST pass with zero errors.
- **CON-008**: Webpack build MUST complete cleanly.
- **CON-009**: `get_ability_by_id()` MUST NOT be called from controllers, external code, or any method other than `save_override()`. It is a private cache-bypass mechanism, not a public API. The slug remains the sole external identifier for all lookups.

### Key Entities

- **AcrossAI_Ability_Merger** (`includes/Utilities/AcrossAI_Ability_Merger.php`): Normalises WP_Ability registry objects into flat arrays for use by the override processor.
- **AcrossAI_Abilities_Query** (`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`): BerlinDB query class; owns `save_override()`, `get_override_by_slug()`, and the new private `get_ability_by_id()` helper.
- **AcrossAI_Abilities_Write_Controller** (`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`): REST controller that calls `save_override()` and builds the response.
- **AcrossAI_Abilities_Schema** (`includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`): BerlinDB schema definition for the abilities table.
- **AcrossAI_Abilities_Table** (`includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php`): BerlinDB table definition; contains `set_schema()` SQL.
- **Redux store** (`src/js/abilities/store/index.js`): `SET_SAVED` reducer seeds `draftAbility`.
- **AbilityForm** (`src/js/abilities/components/AbilityForm.jsx`): React form component rendering overridable fields, hints, and sections.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: All 6 "Plugin declares:" hints render for non-db abilities regardless of whether the registry value is null, false, or empty — zero hints are hidden due to null-guard logic.
- **SC-002**: First save of a non-db override returns `has_override: true` in the REST response without requiring a page reload — 100% of first-save attempts succeed without UI reversion.
- **SC-003**: All TriChips on a freshly opened non-db ability (with no override record) show "default" — zero fields are incorrectly pre-populated from merged values.
- **SC-004**: New override rows in the database have `callback_type = NULL` and `status = NULL` — 0% of new override inserts contain incorrect default values.
- **SC-005**: PHPStan level 8, PHPCS, ESLint, and Webpack build all pass with zero errors after changes are applied.
- **SC-006**: AbilityForm section order matches the specified sequence for both the db-source and non-db variants, verified by visual inspection.

---

## Assumptions

- The `code-done-via-claude` branch (commit `84324a3`) serves as the reference prototype; the speckit implementation is the authoritative version.
- No migration script is required for the DB schema change — direct `ALTER TABLE` is acceptable because this is a new plugin with no production data to protect.
- The REST Read controller always includes `_override` in the response for non-db abilities as an object with all 13 overridable fields. When no override row exists in the DB, all 13 values are null. This is an existing response contract (CON-002) that the fix relies on but does not change.
- `get_ability_by_id()` is a new private helper method added to `AcrossAI_Abilities_Query` as part of this fix (SC-015-C1). It encapsulates `query(['id' => $id, 'number' => 1])` to bypass BerlinDB's slug query cache. Its scope is strictly internal to `save_override()` — it is never called from controllers or external code, and introduces no new public API surface.
- Third-party plugins using the MCP-adapter convention register nested `meta['mcp']` data; plugins predating that convention may still use flat keys — both must be supported.
- The WordPress site is running locally and the `ALTER TABLE` DDL change will be applied directly to the dev database.

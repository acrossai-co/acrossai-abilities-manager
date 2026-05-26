# Feature Specification: Refactor Sitewide Module into Abilities Module

**Feature Branch**: `012-refactor-sitewide-abilities`
**Created**: 2026-05-24
**Status**: Draft
**Input**: Decommission the `Sitewide` module and merge all durable logic into the `Abilities` module.

## Clarifications

### Session 2026-05-24

- Q: Should `AcrossAI_Abilities_Read_Controller.php` (which currently imports `AcrossAI_Sitewide_Query`) be explicitly updated in this refactor's scope? → A: Yes — update to import the unified Abilities Query class; scope is explicitly included in FR-010.
- Q: Should `AcrossAI_Ability_Registry_Query.php` and `AcrossAI_Abilities_Formatter.php` (Utilities classes that import Sitewide classes) be explicitly named in FR-010? → A: Yes — explicitly named in FR-010; both are in-scope for this refactor.
- Q: Should the spec explicitly state that the prior `AcrossAI_Abilities_Query` design decision (reusing Sitewide Schema/Row classes intentionally) is reversed by this refactor? → A: Yes — explicitly stated as a deliberate supersession; docblock must be updated during implementation.
- Q: Should `AcrossAI_Abilities_Menu` submenu cleanup be explicitly marked as out-of-scope (completed in Feature 011)? → A: Yes — explicitly noted as completed in Feature 011; no action required in Feature 012.


## User Scenarios & Testing *(mandatory)*

### User Story 1 — Plugin Continues to Work After Module Consolidation (Priority: P1)

As a site administrator, after the module consolidation everything works identically to before — the Abilities Manager admin page loads, ability overrides can be created, edited, and deleted, and override enforcement fires correctly on every page request.

**Why this priority**: Core functionality must not regress. Any break in override persistence or enforcement makes the plugin useless.

**Independent Test**: Activate the refactored plugin, visit the Abilities Manager admin page, create an ability override, verify it persists and enforcement applies on a non-admin request.

**Acceptance Scenarios**:

1. **Given** the plugin is installed with refactored code, **When** a site admin activates or upgrades the plugin, **Then** the plugin activates without PHP errors.
2. **Given** the admin is on the Abilities Manager page, **When** they save an ability override, **Then** the override persists correctly and appears in the list.
3. **Given** saved overrides are present, **When** a non-admin REST or page request arrives, **Then** override enforcement (allow/deny, access-control rules) applies identically to pre-refactor behaviour.

---

### User Story 2 — Decommissioned API Routes Return "Not Found" (Priority: P2)

As a REST API client, after the refactor any call to a `/sitewide/*` endpoint returns a 404 response — the redundant duplicate REST layer has been removed.

**Why this priority**: Dead endpoints that appear registered but serve no purpose mislead API consumers and expand attack surface unnecessarily.

**Independent Test**: Call `GET /wp-json/acrossai-abilities-manager/v1/sitewide/abilities` and verify a 404 response. Simultaneously verify that `/abilities` and `/abilities/{slug}` return unchanged correct responses.

**Acceptance Scenarios**:

1. **Given** the refactored plugin is active, **When** a REST client calls any `/sitewide/` sub-route, **Then** the response is 404 (route not registered).
2. **Given** the refactored plugin is active, **When** a REST client calls existing Abilities endpoints, **Then** responses are identical to pre-refactor behaviour.

---

### User Story 3 — Codebase Has a Single Module for Abilities Logic (Priority: P3)

As a plugin developer, after the refactor the `Sitewide` module directory no longer exists — all database persistence, override processing, and access control live under the `Abilities` module.

**Why this priority**: Developer experience: eliminates the mental overhead of two modules managing the same data. Required before further feature development to avoid compounding duplication.

**Independent Test**: Verify `includes/Modules/Sitewide/` is absent, static analysis reports zero errors, and the abilities admin UI functions end-to-end.

**Acceptance Scenarios**:

1. **Given** the refactor is complete, **When** a developer lists `includes/Modules/`, **Then** only `Abilities/` and `Logger/` are present — no `Sitewide/` directory.
2. **Given** the refactor is complete, **When** static analysis is run across the full plugin, **Then** zero type-safety or namespace errors are reported.
3. **Given** the refactor is complete, **When** the plugin source is searched for decommissioned module references, **Then** zero matches are found in any PHP file.

---

### Edge Cases

- What happens to existing database rows when the Table class is renamed? The database table name (`acrossai_abilities`) is unchanged; only PHP class names change — no data migration is required.
- How does the plugin Activator handle the renamed Table class? `AcrossAI_Activator` must reference the new Table class name for schema setup to continue working on fresh installations.
- What happens if `AcrossAI_Abilities_Query` already has a method with the same name as a ported Sitewide method? Ported methods replace or extend existing stubs; no duplicate implementations are permitted.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: All database classes (table registration, schema definition, row shape) MUST reside exclusively in `includes/Modules/Abilities/Database/` under the `Abilities` module namespace after the refactor.
- **FR-002**: The single query class MUST be the sole entry point for all `acrossai_abilities` database interactions and MUST include the override CRUD methods previously in the Sitewide query class (`get_override_by_slug`, `save_override`, `delete_override_by_slug`, `get_all_overrides`).
- **FR-003**: The ability override processor MUST reside in `includes/Modules/Abilities/` and reference the unified Abilities query class.
- **FR-004**: The access-control class MUST reside in `includes/Modules/Abilities/` with the `Abilities` naming prefix.
- **FR-005**: All Sitewide REST controllers MUST be deleted; no Sitewide REST routes may be registered after the refactor.
- **FR-006**: `includes/Main.php` MUST wire all hooks exclusively through `Abilities` module classes; no Sitewide class references may remain in the bootstrap file.
- **FR-007**: `includes/AcrossAI_Activator.php` MUST reference the new Abilities Table class for database schema setup on activation.
- **FR-008**: The `includes/Modules/Sitewide/` directory MUST be absent from the plugin source after the refactor.
- **FR-009**: The `includes/Modules/Logger/` module MUST remain entirely unchanged.
- **FR-010**: All internal references to the Sitewide module namespace and class prefix MUST be updated to their Abilities equivalents throughout the entire plugin source. This explicitly includes: `AcrossAI_Abilities_Read_Controller.php` (imports `AcrossAI_Sitewide_Query`), `AcrossAI_Abilities_Processor.php` (imports `AcrossAI_Sitewide_Row`), `AcrossAI_Ability_Registry_Query.php` (imports `AcrossAI_Sitewide_Query`), and `AcrossAI_Abilities_Formatter.php` (imports `AcrossAI_Sitewide_Row`) — in addition to the Sitewide files being moved or deleted.
- **FR-011**: Singleton pattern and Loader-compatible named-variable wiring MUST be preserved for all moved classes.

### Key Entities

- **Abilities Table class**: Registers the `acrossai_abilities` DB table via BerlinDB. Moves from Sitewide module. Uses Abilities Schema class.
- **Abilities Schema class**: Defines column schema. Moves from Sitewide module. Namespace updated.
- **Abilities Row class**: Represents a single DB row with JSON field decoding. Moves from Sitewide module. Namespace updated.
- **Abilities Query class** (extended): Unified query entry point. Gains override CRUD methods ported from the Sitewide query class.
- **Ability Override Processor**: Boot-time override injection processor. Moves from Sitewide to Abilities module.
- **Abilities Access Control class**: Per-ability access-rule management. Replaces Sitewide Access Control; naming prefix updated.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: `includes/Modules/Sitewide/` directory is absent from the plugin source.
- **SC-002**: Static analysis produces zero type-safety errors across the full plugin.
- **SC-003**: Code quality analysis produces zero coding standards violations.
- **SC-004**: All existing ability management API endpoints return correct, unchanged responses — zero behaviour regression.
- **SC-005**: All decommissioned `/sitewide/` API routes return 404 (not found) responses.
- **SC-006**: Plugin activates cleanly on a fresh WordPress installation with zero PHP notices, warnings, or fatal errors.
- **SC-007**: No references to the decommissioned Sitewide module namespace or class prefix remain in any PHP source file.

## Assumptions

- The `acrossai_abilities` database table name is unchanged — only PHP class names change, requiring no data migration.
- `AcrossAI_Abilities_Query` already exists and handles DB interactions for the Abilities module; Sitewide query methods will be merged in without duplication.
- **Design decision reversal**: The prior architectural decision in `AcrossAI_Abilities_Query` (documented as "reused, no duplication") to intentionally reuse `AcrossAI_Sitewide_Schema::class` and `AcrossAI_Sitewide_Row::class` is **deliberately superseded** by this refactor. The `$table_schema` and `$item_shape` properties MUST be updated to the new Abilities DB classes, and the docblock comment documenting the prior intent MUST be updated to reflect this decision.
- The `by_source()` method from `AcrossAI_Sitewide_Query` does NOT need to be ported — it is used only by the Sitewide REST layer, which is being deleted.
- BerlinDB supports class-level pointer changes via `$table_schema` and `$item_shape` properties without requiring table changes.
- The access-control library (`wpb-access-control`) integration points remain unchanged; only the PHP class containing them moves to a new namespace.
- All hook names, REST namespaces, capability strings, and admin page slugs remain unchanged.
- The override cache mechanism in the processor is preserved without modification during the move.
- **Out of scope — already done**: `AcrossAI_Abilities_Menu` submenu cleanup (removal of the hook wiring in `includes/Main.php`) was completed in Feature 011 (`011-merge-abilities-ui`). Feature 012 MUST NOT re-attempt this removal.

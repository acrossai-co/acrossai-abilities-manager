# Feature Specification: Ability Form and List Display Fixes

**Feature Branch**: `024-ability-form-display-fixes`
**Created**: 2026-05-31
**Status**: Draft
**Input**: User description: "Fix five bugs surfaced by the core/get-environment-info ability: source attribution, type badge, plugin-declares hints, callback read-only display, and override injection into the live WP registry."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Correct Source Badge for Core Abilities (Priority: P1)

As a WordPress site administrator, when I open the Abilities list and filter by Source, I expect WordPress core abilities (e.g., `core/get-environment-info`) to show the badge "Core" — not "Plugin" — so that I can accurately distinguish core-supplied abilities from plugin-supplied ones.

**Why this priority**: Incorrect source attribution causes confusion when auditing which abilities are provided by WordPress core vs. by installed plugins. It is the most visible data-accuracy bug.

**Independent Test**: Can be fully tested by opening the Abilities list, filtering by Source = "Core", and confirming `core/get-environment-info` appears there. Filtering by Source = "Plugin" and confirming it does NOT appear there is a complete independent verification.

**Acceptance Scenarios**:

1. **Given** the Abilities list is open, **When** I filter by Source = "Core", **Then** `core/get-environment-info` and all other `core/*` abilities display the "Core" badge.
2. **Given** the Abilities list is open, **When** I filter by Source = "Plugin", **Then** `core/get-environment-info` does NOT appear.
3. **Given** a plugin-registered ability that explicitly declares `source = 'plugin'`, **When** the list loads, **Then** that ability continues to show "Plugin" (no regression).

---

### User Story 2 — Correct Type Badge for Non-DB Abilities (Priority: P1)

As a WordPress site administrator, when I view the Abilities list, I expect the Type column to show a meaningful badge for every ability that has a declared callback type — including WP core abilities and plugin-registered non-db abilities — instead of the placeholder dash `—`.

**Why this priority**: The Type column is used to understand how each ability executes. Showing `—` for core abilities makes the column unreliable and hides actionable information.

**Independent Test**: Can be fully tested by viewing the Abilities list and confirming that `core/get-environment-info` (and any other `core/*` ability that declares a callback type) displays a Type badge. DB abilities with an explicit callback type must continue to show the correct badge (no regression).

**Acceptance Scenarios**:

1. **Given** the Abilities list is loaded, **When** I view the Type column for `core/get-environment-info`, **Then** the Type badge reflects the ability's registered callback type (not `—`).
2. **Given** a plugin-registered ability with a merged (override) callback type, **When** I view the list, **Then** the merged value takes precedence over the registry value.
3. **Given** an ability with no declared callback type anywhere, **When** I view the list, **Then** the Type column still shows `—` (no spurious badge).

---

### User Story 3 — "Plugin declares" Hints in the Edit Form (Priority: P2)

As a WordPress site administrator editing a non-db (plugin/core/theme) ability, I want to see what the plugin originally declared for each overridable field (Label, Description, Category, Show in MCP, MCP Type, Readonly, Destructive, Idempotent, Show in REST) displayed as a "Plugin declares: …" hint below each field, so I can understand what I am overriding and make informed decisions.

**Why this priority**: Without these hints, admins can override fields without knowing the original registered value, leading to accidental data loss or confusion. It is a data-transparency bug that affects all non-db ability edits.

**Independent Test**: Can be fully tested by opening the edit form for `core/get-environment-info`, verifying that each of the listed fields shows a "Plugin declares: …" hint below the input, and verifying that the hint reflects the registry-declared value (not the merged/overridden value).

**Acceptance Scenarios**:

1. **Given** I open the edit form for a non-db ability, **When** the Identity section loads, **Then** Label, Description, and Category fields each show a "Plugin declares: …" hint when the registry has a non-empty value for that field.
2. **Given** a non-db ability with a saved label override, **When** I open the edit form, **Then** the "Plugin declares" hint for Label shows the original registry value, not the override.
3. **Given** a non-db ability where the registry declares no value for a field, **When** I open the edit form, **Then** no hint is rendered for that field (the hint is absent, not blank).
4. **Given** I open the edit form for a db ability (Variant A), **When** the form loads, **Then** no "Plugin declares" hints appear (unchanged behaviour).
5. **Given** MCP Exposure and Annotations sections for a non-db ability, **When** I view the hints, **Then** they display the registry-declared value, not the merged/potentially-overridden value.

---

### User Story 4 — Read-Only Callback Section for Non-DB Abilities (Priority: P2)

As a WordPress site administrator, when I open the edit form for a non-db ability, I expect the Callback section to display the registered callback type and config as read-only information — not as editable chip-buttons — because the callback is defined by the plugin and cannot be changed through the admin.

**Why this priority**: Showing interactive controls for a property that cannot actually be changed misleads administrators and creates a false impression of configurability.

**Independent Test**: Can be fully tested by opening the edit form for `core/get-environment-info` and confirming the Callback section shows a static badge (not clickable chips) and, if a callback config is defined, a read-only code block. The Variant A (db ability) form must remain fully editable (no regression).

**Acceptance Scenarios**:

1. **Given** I open the edit form for a non-db ability, **When** the Callback section renders, **Then** the callback type is displayed as a static text badge — no chip-buttons are present.
2. **Given** a non-db ability with a declared callback config, **When** I view the Callback section, **Then** the config is displayed in a read-only code block.
3. **Given** a non-db ability with no declared callback type, **When** I view the Callback section, **Then** "Not defined" is shown instead of empty chips.
4. **Given** I open the edit form for a db ability (Variant A), **When** the Callback section renders, **Then** chip-buttons and config editing remain fully interactive (unchanged behaviour).

---

### User Story 5 — Label/Description/Category Overrides Applied to Live WP Registry (Priority: P3)

As a plugin or theme developer (or MCP adapter) relying on the live WordPress Abilities registry, I expect that when a site administrator saves a label, description, or category override for a non-db ability, the `WP_Ability` object returned by the registry reflects those overrides — not just the REST API response.

**Why this priority**: This is a data-consistency gap rather than a visible UI bug. REST consumers already see correct values; only code consuming the live registry directly is affected.

**Independent Test**: Can be tested by saving a label override for a non-db ability via the admin form, then calling `get_registered_ability('slug')->get_label()` in PHP and confirming it returns the overridden value. Clearing the override must restore the plugin-declared value.

**Acceptance Scenarios**:

1. **Given** a label override has been saved for a non-db ability, **When** `get_registered_ability('slug')->get_label()` is called, **Then** it returns the overridden label.
2. **Given** a label override is cleared (field blanked and saved), **When** `get_registered_ability('slug')->get_label()` is called, **Then** it returns the plugin-declared default (not null or empty).
3. **Given** a db ability (Variant A), **When** its label/description/category are read from the registry, **Then** behaviour is unchanged — values still come from the DB row directly.

---

### Edge Cases

- What happens when an ability has `source = 'plugin'` explicitly registered in its meta — is it still shown as "Plugin" after the source-default fix? (Yes — explicit registration must be preserved.)
- How does the Type column behave when both `item.callback_type` (merged) and `item._registry.callback_type` (registry) are set — which wins? (Merged value always wins.)
- What happens when the "Plugin declares" hint value for a field is an empty string — is the hint shown? (No — only shown when the registry value is non-empty/truthy.)
- What happens when label/description/category are saved as an empty string (clearing an override) — does `inject_override_args()` still inject the empty string? (No — empty string is treated as "not set" and the original plugin-declared value is preserved.)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST display the Source badge "Core" for any ability whose slug prefix matches a known core provider (`core`, `wordpress-core`) and that has no explicit `source` meta item.
- **FR-002**: The system MUST display the correct Type badge in the Abilities list for any ability that has a `callback_type` declared in its registry data, even when the top-level merged `callback_type` is absent.
- **FR-003**: The system MUST display a "Plugin declares: …" hint below the Label, Description, and Category inputs in the ability edit form when a non-db ability has a non-empty registry-declared value for that field.
- **FR-004**: The system MUST display "Plugin declares: …" hints for MCP Exposure fields (`Show in MCP`, `MCP Type`) and Annotations fields (`Readonly`, `Destructive`, `Idempotent`, `Show in REST`) that reflect the registry-declared value — not the merged/overridden value.
- **FR-005**: The system MUST render the Callback section as read-only for non-db abilities, showing the registry-declared callback type as a static badge and callback config as a read-only code block (or "Not defined" when absent).
- **FR-006**: The system MUST inject label, description, and category override values (when non-null and non-empty) into the live WordPress Abilities registry object so that consumers outside REST see the admin-saved overrides.
- **FR-007**: All changes to behaviour for non-db abilities MUST be conditional — Variant A (db abilities) behaviour MUST remain identical before and after these fixes.
- **FR-008**: Plugin-registered abilities that explicitly declare `source = 'plugin'` in their meta MUST continue to display the "Plugin" badge after the source-default fix.

### Key Entities

- **WP_Ability**: A registered WordPress ability object. Has `label`, `description`, `category`, `source`, `callback_type`, `callback_config`, and `_registry` (raw registry data) fields exposed via the REST API.
- **Override Row (`AcrossAI_Abilities_Row`)**: The DB record storing admin-saved overrides for a non-db ability. Has `label`, `description`, `category`, `site_allowed`, `source`, and meta-annotation fields.
- **Merged Ability**: The REST API representation of an ability after merging registry data with override rows. The `_registry` sub-object contains the original plugin-declared values.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After the fix, 100% of `core/*` abilities in the Abilities list display "Core" as their Source badge (zero misattributed as "Plugin").
- **SC-002**: After the fix, all non-db abilities with a declared `callback_type` in their registry data display a Type badge in the list (zero abilities with a declared type show `—`).
- **SC-003**: When editing any non-db ability, every overridable Identity/MCP/Annotation field for which the registry declares a non-empty value shows a "Plugin declares: …" hint — and no hint appears for fields with no registry value.
- **SC-004**: After the fix, the Callback section for every non-db ability renders no interactive controls; the section for every db ability retains full interactivity.
- **SC-005**: A label, description, or category override saved via the admin form is reflected in the live WP registry (`get_registered_ability('slug')->get_label()` etc.) on the next request cycle after the override is saved.
- **SC-006**: All four quality gates pass: `composer run phpcs` exits 0, `vendor/bin/phpstan analyse --level=8` exits 0, `npm run build` exits 0 with no webpack errors, and Variant A regression tests pass — automated PHPUnit/Jest tests covering Variant A (source=db) ability behaviour are a required deliverable of this feature.

## Assumptions

- All five bugs exist because of known, documented root causes — no discovery or investigation phase is needed; only targeted fixes are required.
- The live site WordPress version is ≥ 6.9 and the `wp_register_ability_args` filter is available.
- The `_registry` sub-object is already included in every list-row and form-load REST response — no REST schema changes are needed.
- "Variant A" (db ability) behaviour means abilities with `source = 'db'`; all `isNonDb`-gated changes are guarded by the existing `isNonDb` constant in the form component.
- The fix for source attribution (CHANGE-1) affects only the default fallback — abilities that explicitly register `source = 'plugin'` are unaffected.
- No new TYPE_MAP entries are required; existing map entries cover all callback types encountered in the current codebase.
- The override injection fix (CHANGE-5) does not change the DB save pipeline or REST response shape — it only adds three fields to the live WP registry hydration that were previously omitted.

## Clarifications

### Session 2026-05-31

- Q: When does a label/description/category override saved via the admin become visible in the live WP Abilities registry — on the same HTTP request that performs the save, or on the following request? → A: Next request cycle — the override takes effect on the following page-load/request after it is saved (matches how `wp_register_ability_args` fires at `init`)
- Q: Does "Variant A regression tests pass" in SC-006 require automated PHPUnit/Jest tests, or only manual checklist verification? → A: Automated tests required — PHPUnit/Jest tests covering Variant A (source=db) ability behaviour are a required deliverable of this feature

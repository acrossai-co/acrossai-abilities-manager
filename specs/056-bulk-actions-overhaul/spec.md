# Feature Specification: Bulk Actions Overhaul — Custom Abilities Admin Page

**Feature Branch**: `056-bulk-actions-overhaul`
**Created**: 2026-07-20
**Status**: Draft
**Input**: See `docs/planning/056-bulk-actions-overhaul.md` for the full user-supplied brief, task decomposition, and preserved-API contract.

## Clarifications

### Session 2026-07-20

- Q: Should the User Access bulk modal render a fixed provider list, or dynamically enumerate every provider registered by the plugin's access-control adapter (so BuddyBoss / MemberPress providers appear automatically when their host plugin is active)? → A: Dynamic enumeration — mirrors the per-row edit drawer's behaviour (which mounts the composer's `<AccessControl>` component and inherits whatever providers the composer package sees registered).

## Background

The Custom Abilities admin page (`?page=acrossai-abilities-manager`) exposes a **Bulk Actions** dropdown whose current three options — **Publish / Unpublish / Delete** — come from the WordPress custom-post-type vocabulary and do not describe how ability overrides actually behave. An ability is not a post; there is no publish workflow, and the "delete" verb has misled users into believing they can remove plugin-registered abilities (in reality the delete endpoint only clears overrides on non-`db` sources).

The per-row edit drawer already exposes the *correct* mental model: three ability-native controls — **Site Access** (Force Block / Inherit / Force Allow), **MCP Exposure** (Enable / Disable / Default), and **User Access** (per-user / per-role rules). This feature promotes those same three controls to the bulk-actions layer so that admins can apply the same override across many selected abilities in one gesture, without opening every drawer in turn.

The change is UI-only. Every storage column, sanitiser, and REST endpoint the feature needs already exists on the PHP side — the work is a client-side dropdown refactor, two new store thunks that loop the existing per-slug endpoints under `Promise.all`, and one new modal for the User Access rule picker.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Bulk Site Access Override (Priority: P1)

An administrator managing dozens of registered abilities wants to force-allow (or force-block, or return to inherit) a batch of abilities in one gesture instead of opening each row's edit drawer in turn.

**Why this priority**: Site Access is the highest-frequency override an administrator applies — it decides whether an ability can be invoked at all. The bulk workflow directly replaces the misleading `Publish` / `Unpublish` verbs and is the primary reason the current dropdown is being redesigned. Ship-alone value: even if MCP Exposure and User Access bulk operations are cut, the Site Access bulk tri-state alone justifies the release.

**Independent Test**: Select N abilities on `?page=acrossai-abilities-manager`, choose **Site Access → Force Allow** (or **Inherit**, or **Force Block**), click **Apply**. Confirm all N abilities update to the chosen state after the list refetches, and that the underlying `site_allowed` column stores the correct tri-state value (`true`, `null`, or `false`) for every selected slug.

**Acceptance Scenarios**:

1. **Given** five abilities are selected across mixed sources (Plugin / Core / Custom), **When** the administrator chooses `Site Access → Force Allow` and clicks Apply, **Then** all five abilities report Force Allow after refetch and no confirmation dialog is shown.
2. **Given** five abilities are selected, **When** the administrator chooses `Site Access → Force Block` and clicks Apply, **Then** a confirmation dialog appears naming the action and the count; declining leaves state unchanged; accepting applies Force Block to all five.
3. **Given** five abilities are selected with mixed prior overrides, **When** the administrator chooses `Site Access → Inherit` and clicks Apply, **Then** all five abilities revert to their source-declared default (no override), and no confirmation is shown.
4. **Given** zero abilities are selected, **When** the administrator chooses any Site Access option and clicks Apply, **Then** nothing happens (no request is sent) and no error is shown.

---

### User Story 2 — Bulk MCP Exposure Override (Priority: P2)

An administrator managing which abilities are exposed to the MCP adapter wants to enable, disable, or reset the MCP flag on a batch of abilities in one gesture.

**Why this priority**: MCP Exposure is a lower-frequency operation than Site Access but structurally identical (tri-state override on a single column). Bundling it into the same release amortises the dropdown-refactor work; skipping it would leave admins with a half-migrated UI (Site Access modernised, MCP still forced through the per-row drawer).

**Independent Test**: Select N abilities, choose **MCP Exposure → Enable** (or **Default**, or **Disable**), click **Apply**. Confirm all N abilities update to the chosen state after refetch, and that the underlying `show_in_mcp` column stores the correct tri-state value.

**Acceptance Scenarios**:

1. **Given** five abilities are selected, **When** the administrator chooses `MCP Exposure → Enable` and clicks Apply, **Then** all five report Enabled after refetch and no confirmation dialog is shown.
2. **Given** five abilities are selected, **When** the administrator chooses `MCP Exposure → Disable` and clicks Apply, **Then** a confirmation dialog appears naming the action and the count; declining leaves state unchanged; accepting applies Disable to all five.
3. **Given** five abilities are selected with mixed prior MCP overrides, **When** the administrator chooses `MCP Exposure → Default` and clicks Apply, **Then** all five revert to their source-declared MCP default.

---

### User Story 3 — Bulk User Access Rule (Priority: P3)

An administrator restricting who may invoke abilities wants to apply one access rule (e.g. "role: editor") to a batch of selected abilities in one gesture, instead of opening every row's User Access panel.

**Why this priority**: User Access is stored via the composer-provided access-control package (`wpboilerplate/wpb-access-control`) which exposes a per-slug PUT endpoint but no batch endpoint. The bulk operation loops the per-slug endpoint client-side. Skipping this in v1 is survivable — admins can continue to use the per-row drawer — but bundling it completes the "every per-row override has a bulk equivalent" story that motivates the release.

**Independent Test**: Select N abilities, choose **User Access → Configure…**, pick a provider (e.g. `wp_role`) and an option (e.g. `editor`) in the modal, click **Apply to all**. Confirm one rule row is written per selected slug in the access-control storage table. Then repeat with the "Everyone (clear rule)" option and confirm all N rules are removed.

**Acceptance Scenarios**:

1. **Given** five abilities are selected, **When** the administrator chooses `User Access → Configure…`, picks `Roles` + `editor` in the modal, and clicks Apply, **Then** the modal reports success and one access-control rule row per slug is written, all with `ac_key='wp_role'` and `ac_options=['editor']`.
2. **Given** five abilities are selected and each has an existing User Access rule, **When** the administrator opens the modal, picks `Everyone (clear rule)`, and clicks Apply, **Then** the rule row for every selected slug is removed and non-authenticated invocations become allowed.
3. **Given** the modal is open with a partially chosen provider, **When** the administrator clicks Cancel, **Then** no request is sent, the modal closes, and the underlying selection remains intact.

---

### User Story 4 — Row-Level Edit Available on Every Source (Priority: P4)

An administrator inspecting a row for an ability sourced from any layer (Plugin, Core, Theme, or a custom DB row) must be able to open its edit drawer to inspect or override its tri-state fields.

**Why this priority**: Verification-only work. The row-level Edit action is believed already to be unconditional; this story exists to gate that assumption behind an explicit check. If a `source ===`-style conditional is discovered near the Edit button render site, it must be removed as a zero-risk delta so administrators are never blocked from opening the drawer on a Plugin/Core/Theme row.

**Independent Test**: Inspect the abilities list with rows of every source represented. Confirm the Edit action is present and clickable on every row regardless of Source column value.

**Acceptance Scenarios**:

1. **Given** the abilities list contains rows sourced from Plugin, Core, Theme, and Custom (DB), **When** the administrator loads the page, **Then** every row's Actions column renders an enabled Edit control.
2. **Given** any row (regardless of Source), **When** the administrator clicks Edit, **Then** the edit drawer opens with the current override state populated.

---

### Edge Cases

- **Empty selection** — Apply with no checkbox selected sends no request and shows no error (matches the existing bulk-delete null-op behaviour).
- **Placeholder option chosen** — Apply with the leading empty "Bulk Actions" option selected is a no-op.
- **Partial failure mid-loop** — If one of the per-slug requests fails (e.g. network drop, permission mismatch on one slug), the other requests still resolve; the list refetches and reflects whichever writes succeeded. Failure surfaces to the administrator via existing store-level error handling — no bespoke bulk-error UI is added in this feature.
- **Modal open, selection cleared** — If the administrator opens the User Access modal, then dismisses it without applying, the bulk-selection state and dropdown value remain intact so they can pick a different domain.
- **Concurrent per-row edit** — If another admin edits one of the selected slugs in the per-row drawer while a bulk operation is in flight, the last write wins per slug (no cross-request coordination is attempted).
- **Sources whose overrides cannot be persisted** — For sources where the write controller rejects an override (e.g. an ability that does not exist), the individual per-slug request fails; other slugs in the batch still resolve.
- **Confirm prompt cancellation** — Declining the destructive-transition confirmation dispatches no requests and leaves both the selection and the dropdown value unchanged.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Bulk Actions dropdown on the Custom Abilities admin page MUST expose four grouped categories — **Site Access**, **MCP Exposure**, **User Access**, and **Overrides** — replacing the current Publish / Unpublish / Delete options. (Revised 2026-07-20 from the original three-category shape: **Overrides** added post-implementation on user request; see FR-018.)
- **FR-002**: The Site Access group MUST offer exactly three options — Force Allow, Inherit, Force Block — each mapping to the tri-state values (`true`, `null`, `false`) stored on the `site_allowed` column of the abilities table.
- **FR-003**: The MCP Exposure group MUST offer exactly three options — Enable, Default, Disable — each mapping to the tri-state values (`true`, `null`, `false`) stored on the `show_in_mcp` column of the abilities table.
- **FR-004**: The User Access group MUST offer two options: (a) **Add / edit access rule…** which opens a modal for choosing the access-control provider and its options, and (b) **Reset to Default (allow everyone)** which immediately dispatches an empty rule (`ac_key: ''`, `ac_options: []`) to every selected slug via the composer PUT endpoint. Option (b) MUST prompt for confirmation before dispatch (destructive per FR-008).
- **FR-005**: Applying a Site Access or MCP Exposure bulk option MUST loop the existing per-slug ability-write endpoint under `Promise.all`, mirroring the pattern of the existing bulk-status thunk, and MUST refetch the abilities list on completion so every affected row reflects the new state.
- **FR-006**: Applying a User Access bulk rule MUST loop the existing per-slug composer-provided access-control PUT endpoint under `Promise.all`, writing the same `ac_key` and `ac_options` payload for every selected slug, and MUST refetch the abilities list on completion.
- **FR-007**: The bulk request body for Site Access and MCP Exposure MUST send JSON `true`, `false`, or `null` verbatim — string aliases (e.g. `"inherit"`, `"1"`) MUST NOT be relied on by the client.
- **FR-008**: The system MUST prompt the administrator for confirmation before dispatching any destructive bulk action: **Site Access → Force Block**, **MCP Exposure → Disable**, **User Access → Reset to Default**, and **Overrides → Force Reset** (see FR-018). The prompt MUST name the action and the count of affected abilities. Declining MUST dispatch nothing and MUST leave the selection intact.
- **FR-009**: The four non-destructive transitions (Force Allow, Inherit, MCP Enable, MCP Default) MUST apply without a confirmation prompt. The **User Access → Add / edit access rule…** flow MUST NOT prompt at the dropdown level — the modal's Apply button is the commit point.
- **FR-010**: Applying a bulk operation MUST clear the dropdown selection and the checkbox selection after all per-slug requests complete, except for the User Access flow which MUST preserve the selection until the modal completes its own apply.
- **FR-011**: The User Access modal MUST render the composer's `<AccessControl>` React component so the provider list stays in sync with server-side registrations (the composer package internally enumerates providers via its `/wpb-ac/v1/{slug}/providers` endpoint). The provider set the operator sees MUST match, at any given time, whatever the per-row edit drawer's `<AccessControl>` component would render for a single slug on the same site — so BuddyBoss (`buddy_boss_profile_type`), MemberPress (`memberpress_membership`), or any future integration provider appears automatically whenever its host plugin is active. The modal MUST render the composer component with `hideSaveButton={true}` so the bulk-apply path (not the composer's per-slug PUT) is the commit point. (Revised 2026-07-20: original wording implied our own code enumerates; in practice the composer component owns enumeration.)
- **FR-012**: The User Access modal MUST expose Cancel and Apply controls; Cancel MUST close the modal without dispatching; Apply MUST call the bulk-rule thunk with the currently chosen provider and options and close the modal on success.
- **FR-013**: The row-level Edit action in the abilities list MUST be enabled for every visible row regardless of the row's Source (Plugin / Core / Theme / Custom). Any residual source-based conditional near the Edit render site MUST be removed.
- **FR-014**: No new REST endpoints, database tables, PHP classes, composer packages, or npm packages MUST be introduced by this feature. All work is client-side JS/CSS + release housekeeping.
- **FR-015**: The build artifacts under `build/` (compiled JS/CSS + generated `*.asset.php`) MUST be regenerated and committed alongside the source changes in the same commit that ships the feature.
- **FR-016**: The release MUST ship as `0.0.15` following the `0.0.14` pattern — README `Stable tag`, plugin-header `Version`, and the `ACROSSAI_ABILITIES_MANAGER_VERSION` constant MUST all be updated in lockstep, and Changelog + Upgrade Notice blocks MUST be added to `README.txt` on a dedicated release branch (`release-0.0.15`) merged after the feature branch.
- **FR-017**: The public API surface enumerated in the planning doc (three PHP symbols, two REST routes, one composer package) MUST resolve to the same signatures / paths / body shapes both before and after the feature ships. A pre-flight and post-flight grep MUST return semantically equivalent hit lists: (a) the two preserved REST paths return identical hits; (b) the store's `bulkUpdateStatus` and `bulkDeleteAbilities` thunk definitions still exist for backward compatibility; (c) removals from the `AbilitiesList.jsx` caller sites are expected and permitted (those callers ARE what this feature replaces). Byte-identical grep parity is not required and would be impossible by design. (Revised 2026-07-20 from original "identical hit lists" wording.)
- **FR-018**: The Bulk Actions dropdown MUST include a fourth optgroup — **Overrides** — with a single option **Force Reset (clear all overrides)** that dispatches `DELETE /acrossai-abilities-manager/v1/abilities/{slug}/override` per selected slug via `Promise.all`, wiping every override column (`site_allowed`, `show_in_mcp`, label/description/category overrides, callback overrides) so each ability returns to its source-declared defaults. This action MUST prompt for confirmation (per FR-008). It does NOT touch the composer-owned User Access rule table; a "full reset including User Access" requires the operator to also run **User Access → Reset to Default**. (Added 2026-07-20 post-implementation on user request.)
- **FR-019**: While any bulk dispatch is in flight (immediate-dispatch actions OR the modal's Apply), the UI MUST render a full-screen busy overlay that (a) blurs the underlying admin page (`backdrop-filter: blur`), (b) shows the WordPress-native `.spinner.is-active` scaled to a visible size with an accessible status label announced via `role="status" aria-live="polite"`, and (c) locks body scroll (`overflow: hidden` on the body element) restored on completion. Escape-to-dismiss on the modal MUST be suppressed while the modal's own apply is in flight. (Added 2026-07-20 post-implementation on user request.)
- **FR-020**: The row-level checkbox MUST render on every visible ability row regardless of Source (Plugin / Core / Theme / Custom). The header "select all" checkbox MUST select every visible ability, not just Custom-source rows. Rationale: Feature 056's bulk operations write tri-state overrides that apply to any source — the pre-Feature-056 db-only checkbox gate was a hangover from the deleted Publish/Unpublish/Delete flow (which was db-only). Extends FR-013's edit-any-source principle to the selection column. (Added 2026-07-20 post-implementation.)

### Key Entities

- **Ability override (Site Access)** — Tri-state boolean (`true` / `false` / `null`) written to the `site_allowed` column of the abilities table via the existing per-slug write endpoint.
- **Ability override (MCP Exposure)** — Tri-state boolean written to the `show_in_mcp` column of the same abilities table via the same per-slug write endpoint.
- **Access-control rule** — A `(ac_key, ac_options[])` tuple keyed by the pair (`namespace='acrossai-abilities'`, slug), stored in the access-control table owned by the `wpboilerplate/wpb-access-control` composer package and written via that package's per-slug PUT endpoint.
- **Bulk selection** — Client-only React state: the array of currently-checked slugs on the Abilities List page, mutated by the row checkboxes and the "select all" header checkbox.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can apply a Site Access override to any number of selected abilities in one gesture (dropdown → Apply) and the underlying storage reflects the chosen state for every selected slug after the list refetches, with no per-slug drawer opening.
- **SC-002**: The number of clicks required to apply the same override to N abilities drops from `~4N` (open drawer → change → save → close, per row) to `3` (select all → choose option → Apply) — independent of N.
- **SC-003**: Bulk **Force Block** and bulk **MCP Disable** always surface a confirmation prompt before dispatch; the other four tri-state transitions never do. Verified by manual click-through on the Custom Abilities page.
- **SC-004**: The Bulk Actions dropdown contains zero occurrences of the strings `publish`, `unpublish`, or `delete` after the feature ships.
- **SC-005**: The row-level Edit action is enabled and functional on 100% of visible rows regardless of Source column value.
- **SC-006**: No new REST endpoint, database table, or PHP class is introduced. Static analysis (PHPStan) and coding-standards (PHPCS) checks both exit 0 after the change. Webpack build exits 0.
- **SC-007**: Post-flight grep for the preserved public-API surface satisfies **semantic preservation**: (a) the two REST paths (`acrossai-abilities-manager/v1/abilities/` and `wpb-ac/v1/abilities/rules`) return identical hit lists before + after; (b) the store's `bulkUpdateStatus` and `bulkDeleteAbilities` thunk definitions remain in `store/index.js` (backward-compat deferred-removal per Out-of-Scope); (c) `api.updateAbility` is still exported and callable with the same signature. Removed callers inside `AbilitiesList.jsx` are expected and permitted — replacing them with the tri-state flow IS the feature. (Revised 2026-07-20: original "identical hit lists" wording was unsatisfiable by design.)
- **SC-008**: The release `0.0.15` is tagged from `main` HEAD after the release branch merges, with `README.txt`, `acrossai-abilities-manager.php`, and `includes/Main.php` all reporting `0.0.15` in unison.

## Assumptions

- **Preserved-API contract** — The three PHP symbols (`AcrossAI_Abilities_Query::update_ability`, `AcrossAI_Abilities_Query::save_override`, `AcrossAI_Sanitizer::sanitize_tri_state`) and the two REST routes (`POST /acrossai-abilities-manager/v1/abilities/{slug}` and `PUT /wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}`) already exist and behave as documented in the planning doc; no PHP change is needed.
- **Composer package stability** — `wpboilerplate/wpb-access-control ^2.0.0` is already installed and its PUT rule-set endpoint accepts the payload shape `{ ac_key, ac_options[] }`; no fork or upstream PR is needed.
- **Per-row edit drawer** — `AbilityForm.jsx` (per-row drawer + its User Access panel) is out of scope for this feature; the bulk modal reuses the composer's `<AccessControl>` React component when its API allows so the same dynamic provider set is rendered, otherwise it falls back to a minimal in-house picker that enumerates providers by querying the same adapter-provided list at open time.
- **Row-level Edit already unconditional** — Phase-1 exploration recorded in the planning doc indicates no `source`-based gate exists near the Edit button render site. The verification step in the tasks phase will confirm this and remove any surviving gate if found.
- **Failure-handling parity** — Per-slug failures inside `Promise.all` are surfaced through the existing store-level error path; no bespoke bulk-error UI is added.
- **Selection persistence during modal** — The User Access modal reads the current bulk selection at open time; if the administrator dismisses the modal without applying, the selection remains so they can pick a different domain.
- **Release branch pattern** — Feature branch `056-bulk-actions-overhaul` merges to `main` first; a separate `release-0.0.15` branch is cut afterwards for the three version-marker bumps + Changelog / Upgrade Notice entries, matching the `f936aab` (release-0.0.14) pattern.
- **Follow-up cleanup** — Removal of the now-orphaned `bulkUpdateStatus` and `bulkDeleteAbilities` thunks is explicitly deferred; a follow-up PR (not this feature) will grep-verify zero remaining callers and delete them.

## Out of Scope

- Any PHP change (storage, sanitiser, REST controller, permissions, capabilities).
- Any new database table or migration.
- Any new REST endpoint.
- Changes to the `wpboilerplate/wpb-access-control` composer package (no fork, no upstream PR).
- Changes to the per-row edit drawer (`AbilityForm.jsx`) or its User Access panel.
- Changes to `composer.json` or `package.json` (no new dependencies).
- A bespoke bulk-error UI beyond what the existing store-level error path already surfaces.
- Removal of the now-orphaned `bulkUpdateStatus` / `bulkDeleteAbilities` store thunks — deferred to a follow-up PR.
- Any change to the row-level Actions column beyond the verification-and-remove-if-present gate on the Edit button.

## Dependencies

- **PHP side** — Pre-existing symbols and endpoints listed under Assumptions and enumerated in `docs/planning/056-bulk-actions-overhaul.md`.
- **JS side** — Existing store thunk `bulkUpdateStatus` (as a pattern to mirror), the `api.updateAbility` client, the `@wordpress/api-fetch` module, and the composer's `@wpb/access-control` React component (when reused by the modal).
- **Build toolchain** — `npm run build` (webpack) for regenerating `build/` artifacts; `composer run phpstan` and `composer run phpcs` for static-analysis gates.
- **Release toolchain** — `gh` CLI for opening the release PR and tagging `0.0.15`.

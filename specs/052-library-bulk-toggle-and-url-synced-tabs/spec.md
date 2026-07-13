# Feature Specification: Library Page — Tab-Scoped Bulk Enable/Disable + URL-Synced Tabs

**Feature Branch**: `052-library-bulk-toggle-and-url-synced-tabs`
**Created**: 2026-07-13
**Status**: Draft
**Input**: User description: "Add two UX affordances to the Library admin page (?page=acrossai-abilities-library): (A) two side-by-side bulk-action buttons Enable All / Disable All, scoped to the currently active tab, preserving each category's mode and per-slug selections while flipping only the enabled boolean; (B) URL-synced tabs so the active tab survives refresh, is deep-linkable, and follows browser back/forward navigation. Full source planning artifact: docs/planning/052-library-bulk-toggle-and-url-synced-tabs.md"

## Clarifications

### Session 2026-07-13

- Q: How should the non-actionable state of `Enable All` / `Disable All` be conveyed when a click would be a no-op (every in-scope category already at the target state)? → A: Both buttons always remain visually and interactively active; a redundant click is a silent no-op — the button is never rendered as disabled. No `disabled` attribute, no `aria-disabled`, no visual dimming.
- Q: Should the expand chevron on a category card remain visible when the card is disabled, even though the initial contract said all affordances collapse? → A: Yes. The chevron MUST render whenever the category has at least one registered ability, regardless of the enabled state. Rationale: header-row visual alignment consistency between enabled and disabled cards, which the administrator explicitly requested during live UX iteration.
- Q: When the chevron is clicked on a disabled card, should any ability list be shown below, and if so in what form? → A: Yes — render the readonly ability list (bullet-style rows with the ability label and description). Interactive `<CheckboxControl>` rows MUST NOT render on a disabled card even when the stored `mode` is `specific`. This preserves the read-only preview UX the administrator requested (see Image #9 during live iteration) while keeping the disabled contract intact: nothing on a disabled card can be toggled from the disabled card itself.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Tab-scoped Bulk Enable/Disable (Priority: P1)

An administrator viewing the Ability Library needs to enable or disable every ability category currently shown on the active tab in a single click, without waiting through hundreds of individual toggles and without touching categories on other tabs.

**Why this priority**: With ~17 rebranded categories and ~176 abilities on the page, per-card toggling is untenable for common operations — pre-launch lockdown, post-launch rollout, and per-domain enablement (e.g. "enable everything under Blocks only"). This is the primary reason the feature exists.

**Independent Test**: Load the Library page on the default `All` tab, click `Enable All`, then click `Disable All`. Verify every category card flips to the corresponding state and the changes persist across a page refresh. Repeat on the `Core` tab and verify only Core-tab categories change while every other tab's categories remain in their prior state.

**Acceptance Scenarios**:

1. **Given** the Library page is open on the `All` tab with a mix of enabled and disabled categories, **When** the administrator clicks `Enable All`, **Then** every category (across every tab) becomes enabled and the change persists after refresh.
2. **Given** the Library page is open on the `Core` tab with Core-tab categories partially disabled and non-Core tabs untouched, **When** the administrator clicks `Disable All`, **Then** every Core-tab category becomes disabled while every non-Core category retains its prior enabled/disabled state byte-for-byte.
3. **Given** every category on the currently active tab is already enabled, **When** the administrator clicks `Enable All`, **Then** no network request is issued, no on-screen state changes, and no error is surfaced — the click is a silent no-op. Both buttons continue to render as fully active controls.
4. **Given** every category on the currently active tab is already disabled, **When** the administrator clicks `Disable All`, **Then** no network request is issued, no on-screen state changes, and no error is surfaced — the click is a silent no-op. Both buttons continue to render as fully active controls.
5. **Given** the Library page is empty (no ability definitions registered), **When** the administrator views the header, **Then** both bulk-action controls remain visible and fully active; clicking either is a silent no-op — no network request is issued and no on-screen state changes.

---

### User Story 2 — Direct-Link and Share a Specific Tab (Priority: P2)

An administrator (or a colleague being consulted) needs to open the Library page directly on a specific tab — via a bookmark, a shared link, or a browser back/forward step — so their view matches what someone else described without having to remember to click the tab after landing on the page.

**Why this priority**: The Library page is a shared operational surface. Colleagues frequently point at "the Themes tab" or "the Blocks tab" via chat; today the URL doesn't reflect the active tab, so the link they paste opens on `All` and the recipient has to re-click. Tab-sync is a quality-of-life gap that also makes browser navigation feel correct.

**Independent Test**: Click through each tab and observe the URL update to include a `tab` query parameter that matches. Copy the URL for a specific tab, open it in a new browser session, and verify the page opens directly on that tab. Use the browser back button after switching tabs and verify the active tab syncs back to whatever the URL says.

**Acceptance Scenarios**:

1. **Given** the administrator is on the Library page's default `All` view, **When** they click the `Core` tab, **Then** the browser URL updates to reflect the Core tab without a page reload.
2. **Given** a URL containing `tab=<slug>` is opened directly, **When** the page finishes loading, **Then** the matching tab is active on first paint (not the default `All`).
3. **Given** the administrator has switched between three tabs, **When** they click the browser back button twice, **Then** the active tab reverts through the prior tabs in order.
4. **Given** a URL contains a `tab=<slug>` value that no longer corresponds to any known tab (e.g. the referenced tab has been removed), **When** the page loads, **Then** it opens on the default `All` view without error and without a console warning.
5. **Given** the administrator clicks back onto the `All` tab, **When** they inspect the URL, **Then** the `tab` query parameter is removed entirely so the canonical default URL stays clean.

---

### User Story 3 — Lossless Round-Trip of Per-Category Fine-Tuning (Priority: P3)

An administrator who has previously fine-tuned a category into "specific" mode (choosing individual abilities) needs the bulk `Disable All` action to preserve those choices so that a later `Enable All` restores the same fine-tuning, without silently discarding the customization.

**Why this priority**: Bulk-disable followed by bulk-enable is a common ops cycle (lockdown → all-clear). If bulk actions overwrite the `specific`-mode selections, administrators lose configuration state on every cycle, which erodes trust in the bulk buttons and creates a subtle data-loss hazard. Preserving these choices is not a headline feature — but it's the invariant that makes the bulk buttons safe to use.

**Independent Test**: Open one category, switch it to `Specific` mode, select two of its per-ability checkboxes. Click `Disable All` in scope. Verify the category shows as disabled. Click `Enable All` in scope. Verify the same category returns to `Specific` mode with the same two checkboxes selected — no drift.

**Acceptance Scenarios**:

1. **Given** category `X` has been switched to `Specific` mode with a subset of abilities checked, **When** the administrator bulk-disables in a scope that includes `X`, **Then** `X`'s stored mode remains `Specific` and its checked-ability list remains intact.
2. **Given** category `X` was bulk-disabled per scenario 1, **When** the administrator bulk-enables in a scope that includes `X`, **Then** `X` reappears with mode `Specific` and the exact prior ability selections.
3. **Given** the administrator bulk-disables every category on the `All` tab, **When** the administrator inspects a category's stored settings, **Then** only the enabled flag has changed — the mode and per-ability selections are unchanged.

---

### Edge Cases

- **Empty library**: If no ability definitions are registered (companion plugins uninstalled, activation ordering issue), the header row and its buttons still render, but clicking either button MUST NOT issue a network request and MUST NOT alter stored data. The existing "Ability library is empty" message continues to display below.
- **Concurrent per-card + bulk edits**: If the administrator clicks a per-card toggle and immediately clicks `Disable All` on the same tab before the per-card save completes, the bulk save is the last-writer-wins outcome. This is acceptable — the bulk save is what the administrator ultimately intended.
- **Save failure**: If the network request for a bulk save fails, the on-screen state reverts to the prior configuration and a visible error is surfaced so the administrator knows their intended change did not persist.
- **URL query parameter collision**: The `tab` query parameter must coexist with other query parameters (`page=…`, any future filters). Existing parameters are preserved; only the `tab` parameter is added, updated, or removed.
- **Invalid or unknown tab value in URL**: A `tab=<slug>` value that doesn't match any currently-registered tab silently falls back to the default `All` view.
- **Ability set changes at runtime**: If a companion plugin adds a new ability category after the page has loaded (e.g. via a hot-reload during development), stale tab-scoped state remains bound to the tabs known at load time. The administrator must refresh to pick up new tabs — this is acceptable; ability sets are effectively static at admin-page runtime.
- **Disabled-card visual consistency**: A category card disabled via the bulk `Disable All` action MUST render identically to a category disabled via its own per-card toggle. No stray mode/detail affordances leak through the bulk path.

## Requirements *(mandatory)*

### Functional Requirements

**Bulk enable/disable affordance**

- **FR-001**: The Library admin page MUST render a persistent header row above the tab list containing exactly two administrator-facing bulk-action controls, presented side-by-side.
- **FR-002**: The two controls MUST be labeled `Enable All` and `Disable All` (in that visual order), each acting as an independent, single-click action — no dropdown, no confirmation prompt.
- **FR-003**: Clicking `Enable All` MUST target ONLY the categories currently in scope, defined as: (a) every registered category when the active tab is the default `All` view, otherwise (b) only categories whose tab-group metadata matches the active tab identifier. Categories outside the scope MUST NOT be modified.
- **FR-004**: Clicking `Disable All` MUST target the same in-scope categories described in FR-003 (using the same scope-resolution rule) and MUST NOT modify any out-of-scope category.
- **FR-005**: For each in-scope category, a bulk action MUST change only the enabled/disabled attribute; the category's `mode` selector (all-abilities vs specific-abilities) and its stored per-ability selections MUST be preserved byte-for-byte.
- **FR-006**: Both bulk actions MUST persist the change to the administrator's stored library configuration in a single request. No partial writes; either the whole in-scope set updates or none of it does.
- **FR-007**: On save failure the on-screen state MUST revert to the prior configuration and a visible error message MUST be surfaced to the administrator.
- **FR-008**: Both `Enable All` and `Disable All` MUST always render as fully active, interactive controls — regardless of the current in-scope state. When a click would be a no-op (every in-scope category is already at the target state), the click MUST be a silent no-op: no network request, no on-screen state change, no error. The buttons MUST NOT be visually disabled, MUST NOT set the `disabled` attribute, and MUST NOT set `aria-disabled`.
- **FR-009**: The bulk-action controls MUST remain visible when the library has no registered categories. In that state a click on either control MUST NOT issue any network request or alter stored data.

**URL-synced tabs**

- **FR-010**: Selecting a tab other than the default `All` view MUST update the browser URL to include a `tab=<tab-identifier>` query parameter, without triggering a full page reload.
- **FR-011**: Selecting the default `All` view MUST remove any `tab` query parameter from the browser URL so the canonical default URL contains no `tab=` value.
- **FR-012**: The URL update MUST preserve every other query parameter present in the URL (e.g. `page=…`) in their existing form; only the `tab` parameter is added, updated, or removed.
- **FR-013**: On page load, the initial active tab MUST be derived from the URL's `tab` query parameter when present; otherwise the default `All` view MUST be shown.
- **FR-014**: When the URL's `tab` value does not correspond to any currently-registered tab, the page MUST silently fall back to the default `All` view. No error message and no console warning are shown to the administrator.
- **FR-015**: Browser back/forward navigation MUST re-sync the active tab to whatever the URL says at the destination navigation entry, again without a full page reload.
- **FR-016**: Whenever the active tab changes (via a click or via browser navigation), any downstream scope-dependent state (including the bulk-action controls' targeting) MUST re-derive to match the new active tab immediately.

**Preservation of existing behavior**

- **FR-017**: A category card displayed while disabled MUST render the following affordances:
  - The master enable/disable toggle and the category label — always visible.
  - The expand chevron — visible whenever the category has at least one registered ability, regardless of enabled state (for header-row alignment consistency with the enabled state).
  - The mode selector (All/Specific radio) — hidden while disabled.
  - The ability-list panel — visible when the chevron is expanded (whether the card is enabled or disabled), but rendered in READONLY form on a disabled card (bullet-style rows with the ability label and description). Interactive `<CheckboxControl>` rows MUST NOT render on a disabled card even when the stored `mode` is `specific`; the stored `mode` and `sub_keys` are preserved for re-enable but never surface as an interactive control on the disabled render.

  This visual state MUST be identical whether the disabled state was reached by an individual per-card toggle or by the bulk `Disable All` action.
- **FR-018**: The per-card toggle, the mode selector, and the per-ability checkbox interactions available today (as of Feature 046) MUST continue to work unchanged.
- **FR-019**: The stored library configuration schema MUST remain unchanged. No new fields are added and no existing fields are removed. Sparse-storage behavior (entries at their all-default state are omitted from storage) continues as before.

### Key Entities *(include if feature involves data)*

- **Ability Category**: An administrator-visible grouping of related abilities (e.g. Posts, Users, Blocks). Each category has an enabled/disabled state, a mode (all vs specific), and — when in specific mode — a set of per-ability selections. Categories are the unit of both per-card and bulk actions.
- **Tab Group**: A page-level grouping of ability categories (e.g. Core, Themes, Blocks). Tabs are display-only; a category's tab-group is derived from its own metadata rather than persisted separately. The special `All` view shows every category regardless of tab group.
- **Library Configuration**: The persisted administrator settings for the Library page, keyed by category slug and holding each category's enabled state, mode, and per-ability selections. This is the artifact the bulk actions read and write.
- **Active Tab Selection**: The currently focused tab. Represented as either the default `All` sentinel or a specific tab identifier. Reflected in the URL via a `tab=<identifier>` query parameter (absent when the default `All` is active).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can bring the entire library from a fully-enabled state to a fully-disabled state (or vice versa) in under 10 seconds, using one click and one confirmation of visible feedback — down from the ~5 minutes it takes to click ~17 individual toggles today.
- **SC-002**: An administrator can restrict a bulk action to a specific tab and verify — via the on-screen card state — that no category outside that tab was touched. Verification takes under 30 seconds by tab-switching to spot-check.
- **SC-003**: 100% of bulk `Disable All` → `Enable All` cycles preserve the per-category mode and per-ability selections. An administrator who set 5 specific-mode selections before a lockdown finds all 5 restored after the lockdown ends, with zero manual re-selection.
- **SC-004**: A URL captured from an administrator's Library page (with any specific tab active) opens directly on that same tab in a colleague's browser, in a fresh session, on first paint. No manual tab-click required.
- **SC-005**: Browser back and forward navigation between tabs feels indistinguishable from same-page navigation elsewhere in the admin: no full-page reload, no flash of the default tab, and the active tab always matches the URL after navigation completes.
- **SC-006**: The disabled-card visual state remains a single, consistent rendering regardless of how the card was disabled. An administrator inspecting two disabled cards — one disabled per-card, one disabled via bulk — cannot tell the difference at any point in the render tree (header row AND the expanded readonly ability-list panel below, when the chevron is open).
- **SC-007**: Bulk-action requests do not introduce measurable page-load or interaction latency regressions. The Library page continues to become interactive within the same time budget as the prior release under the same test fixture.

## Assumptions

- **Scope-scoping via existing tab identifiers**: The set of tab identifiers used for the URL parameter and for scoping bulk actions is exactly the same set that the current TabPanel derives from ability metadata (Feature 037 / 046). No new taxonomy is introduced.
- **Existing REST endpoint capacity**: The current `POST` route for saving the full library configuration can accept the merged post-bulk payload without route or schema changes. No new REST endpoint is needed.
- **Sparse-storage remains authoritative**: When every category is at the all-default state (enabled, mode=all, no specific selections), the stored option remains empty and re-hydrates into "all enabled" on the next read. Bulk `Enable All` from a mixed state falls through this sparse-storage path.
- **Single-admin editing at a time**: The Library page is an operational surface used by administrators one at a time. This feature does not attempt to reconcile concurrent multi-administrator writes; last-writer-wins is acceptable.
- **Static ability set during a page session**: Ability categories registered when the page loads remain the same set for the lifetime of the page. If a companion plugin registers a new category mid-session, the administrator must refresh to pick it up.
- **Companion plugin absence is safe**: When the acrossai-core-abilities companion plugin is absent (post-Feature-046 absorbed-code path), the Library still populates and the bulk buttons work over the absorbed-code set.
- **No confirmation modal needed for `Disable All`**: The action is instantly reversible via `Enable All` because mode and per-ability selections are preserved. A confirmation step is deferred as a possible future addition.
- **Administrator role**: The bulk actions and URL-sync affordances are consumed by users who already have permission to edit the Library page. No new permission model is introduced.

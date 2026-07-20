# Memory Synthesis

## Current Scope

Feature 056 replaces the Custom Abilities admin page's Bulk Actions dropdown (`publish` / `unpublish` / `delete`) with three ability-native bulk operations that mirror the per-row edit drawer: **Site Access** tri-state, **MCP Exposure** tri-state, and **User Access** (opens a modal that applies one `wpboilerplate/wpb-access-control` rule to every selected slug). Client-side only — no PHP change, no new REST endpoints, no new database tables, no new composer / npm packages. Ships as `release-0.0.15` following the `0.0.14` pattern. Modules touched: `src/js/abilities/components/AbilitiesList.jsx`, `src/js/abilities/store/index.js`, one new `UserAccessBulkModal.jsx`, `src/scss/abilities/admin.scss`, regenerated `build/` artifacts, and version-marker bumps in `README.txt` / `acrossai-abilities-manager.php` / `includes/Main.php`.

## Relevant Decisions

- **DEC-AC-RENDERING-GATE** — `access_control_available` is a client-side rendering gate only; server auth is enforced by the `wpb-ac/v1` REST endpoints. (Reason Included: bulk modal opens the composer's rule-picker; must hide/disable the User Access group when the gate is false; server-side authorisation is not the client's concern. Status: Active. Source: DECISIONS.md)
- **DEC-AC-SAVE-FLOW-PATTERN** — `acSaveOk` flag: reset dirty state only on confirmed AC save success; failure never blocks the ability save. (Reason Included: the bulk modal's Apply-to-all flow must adopt the same discipline — only close + clear selection after every per-slug PUT resolves. Status: Active. Source: DECISIONS.md)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** — User design prototype overrides Constitution §III's DataViews/DataForm mandate for the Abilities admin page. (Reason Included: authorises the plain `<select>` + `<optgroup>` bulk-actions dropdown instead of a DataForm control — no constitution deviation to log. Status: Active. Source: DECISIONS.md)
- **DEC-NODE-20-BUILD-REQUIRED** — `npm run build` requires Node ≥ 20; `toSorted` silently fails on Node 16. (Reason Included: TASK-6 regenerates `build/js/abilities.*` and `build/css/abilities.*`; the build gate must run on Node 20+. Status: Active. Source: DECISIONS.md)
- **DEC-ABILITIES-LIST-UX-025** — `AbilitiesList`'s runtime config is injected via `window.acrossaiAbilitiesManager` (not `window.acrossaiAbilities`). (Reason Included: the AC-provider enumeration for the modal — clarify Q1 answer B — reads from the same window object surface; keep the naming convention. Status: Active. Source: DECISIONS.md)

## Active Architecture Constraints

- **PATTERN-AC-COMPONENT-INTEGRATION** — Named import + `AccessControl.js` alias + SCSS + three-branch rendering + module-level `abilitiesConfig` + no `onSave`. (Reason Included: the bulk modal reusing the composer's `<AccessControl>` component must follow this exact integration pattern; deviating risks the same class of Jest gotchas that were resolved in Feature 018. Source: ARCHITECTURE.md)
- **AC-ENQUEUE-ADMIN** — `wp_enqueue_script/style` ONLY in `Admin\Main::enqueue_scripts/styles`. (Reason Included: no new admin bundle is registered by this feature; the modal + SCSS + thunks all ride inside the existing `abilities.js` / `abilities.css` enqueue. Source: CONSTITUTION.md §I)
- **ARCH-UNIFIED-ABILITIES-STORAGE** — Abilities module owns the unified abilities table; override rows identified by source semantics. (Reason Included: background context for the tri-state writes — `site_allowed` and `show_in_mcp` are columns on the unified table written via the pre-existing `save_override` path; no schema drift. Source: ARCHITECTURE.md)

## Accepted Deviations

- **DEC-DESIGN-OVERRIDES-DATAVIEWS** — Plain `<select>` + `<optgroup>` bulk-actions dropdown is the authorised UI; no need to shoehorn a DataForm control. (Reason Included: the feature ships a `<select>` — this deviation makes that choice compliant. Status: Accepted-Deviation)

## Relevant Security Constraints

- **SEC-04** — Strict type comparison for access-control checks. (Reason Included: the User Access bulk thunk receives a response from the composer's PUT endpoint whose sentinel-value semantics matter — string aliases and `null` must not be conflated. Source: security-constraints.md)

## Related Historical Lessons

- **Feature 018 (2026-05-29) — User Access section + AC integration pattern + 4 Jest gotchas.** (Reason Included: origin of `PATTERN-AC-COMPONENT-INTEGRATION`; the bulk modal is the second consumer of the composer's `<AccessControl>` component and inherits Feature 018's hard-won integration recipe. Failing to follow it will re-surface the four Jest gotchas: `BUG-WP-ELEMENT-ACT-MISSING`, `BUG-MODULE-LEVEL-WINDOW-READ`, `BUG-JEST-ASYNC-USEEFFECT-FLUSH`, `BUG-WP-API-FETCH-VIRTUAL`.)
- **Feature 015 (2026-05-27) — override layer hardened; tri-state semantics validated via `BUG-MERGER-BOOL-STRING-CAST` and `BUG-DRAFT-SEEDED-FROM-MERGED`.** (Reason Included: the bulk tri-state writes must send strict JSON `true` / `false` / `null` — never string aliases — matching the client-side discipline Feature 015 established.)

## Bug Patterns to Guard Against

- **BUG-MERGER-BOOL-STRING-CAST** — `(string) false === ''`; never string-cast tri-state bool fields; use `null !== $value` only. (Reason Included: `bulkUpdateTristate` must send raw JSON booleans / null — the planning-doc FR-007 already codifies this; guard against a well-meaning reviewer who adds a string cast "for safety".)
- **BUG-AC-NULL-RETURN-SILENT-FAIL** — AC permission checks silently fail when the library returns `null` instead of `false`. (Reason Included: `bulkSetUserAccessRule` must treat a `null` response body as failure, not success — surface the error to the modal via the existing store-level error path.)
- **BUG-ESLINT-DISABLE-LINE-EXACT** — `eslint-disable-next-line` covers exactly one line; must sit directly before the offending call, not before a wrapping `if()`. (Reason Included: `handleBulkApply` calls `window.confirm()` inside a nested `if`; the planning-doc snippet places `// eslint-disable-next-line no-alert` on the line above `window.confirm(msg)` — preserve that exact placement to keep the lint gate green.)

## Conflict Warnings

- No hard conflicts. The feature only writes to fields already governed by pre-existing decisions (`site_allowed`, `show_in_mcp`, `wpb-ac/v1` rule rows) via pre-existing endpoints, and the dropdown UI is authorised by `DEC-DESIGN-OVERRIDES-DATAVIEWS`.
- No soft conflicts detected — every affected surface has a settled active decision or an in-scope pattern.

## Retrieval Notes

- Index entries considered: ~60 (docs/memory/INDEX.md loaded once).
- Selected: 5 decisions / 3 architecture entries / 3 bug patterns / 1 security constraint / 1 accepted deviation / 2 worklog lessons — all within the default retrieval budget (max 5 / 5 / 3 / 3 / 3 / 2 respectively).
- Full durable memory files (`DECISIONS.md`, `ARCHITECTURE.md`, `BUGS.md`) were **not** loaded end-to-end — index rows carried enough context. Source files remain the authoritative reference for the `/speckit-plan` phase if deeper details are needed.
- Explicitly excluded: `Superseded` entries (`DEC-HOOK-PARAM-EXTRACTION`, `DEC-DURATION-CALC-TIMESTAMPS`, `DEC-VARIADIC-CALLBACK-WRAP`, `DEC-MCP-SERVER-SANITIZE`, `DEC-MCP-CAPABILITY-FILTER-WARN`, `DEC-FREEMIUS-PER-PLUGIN-INIT`, `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN`, `DEC-LOGGER-NAMESPACE-MIGRATION`, `DEC-EVAL-PHP-CODE`) — none affect this feature.
- Per-user memory feedback honoured: `permission_callback` compliance is not audited in this synthesis (skip-directive from `~/.claude/.../feedback_skip_permission_callback_audit.md`).

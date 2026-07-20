# Security Constraints — Feature 056

Feature scope is client-side JS/JSX/SCSS + release housekeeping — **no new PHP, no new REST endpoints, no new database tables, no new composer or npm dependencies**. All server-side authorisation, sanitisation, and storage already exists and is unchanged. Per project preference (`~/.claude/.../memory/feedback_skip_permission_callback_audit.md`) `permission_callback` compliance is intentionally not re-audited; the trust chain relies on the two preserved REST controllers whose gates were previously validated.

## Trust Boundaries

| Boundary | Party in charge | Enforcement |
|---|---|---|
| Browser → WordPress REST (`POST /acrossai-abilities-manager/v1/abilities/{slug}`) | Server (`AcrossAI_Abilities_Write_Controller`) | Existing `check_permission()`: `manage_options` capability + `X-WP-Nonce` verification. Slug sanitised via `SEC-01` (`sanitize_ability_slug()`). Tri-state values normalised by `AcrossAI_Sanitizer::sanitize_tri_state`. **Unchanged by this feature.** |
| Browser → WordPress REST (`PUT /wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}`) | Composer package (`wpboilerplate/wpb-access-control`'s `RulesController`) | Composer package enforces its own capability + nonce gates; upstream contract. **Unchanged by this feature.** |
| React → `@wordpress/api-fetch` | Client bundle | `apiFetch` auto-injects `X-WP-Nonce` from `wp.apiFetch.nonceMiddleware` (WP admin bootstraps this). No custom nonce plumbing required. |
| Client → Server slug path construction | Client (this feature) | Slugs interpolated into REST paths MUST use `encodeURIComponent(slug)` (planning-doc spec verbatim); prevents path injection when slugs contain characters like `/`, `?`, `#`, `&`. Server-side sanitiser is defence-in-depth. |

## Data Isolation & Validation Risks

- **Tri-state payload discipline** — Client MUST send raw JSON `true` / `false` / `null` for `site_allowed` and `show_in_mcp`; string aliases (e.g. `"inherit"`, `"1"`) MUST NOT be used from this feature. Guards `BUG-MERGER-BOOL-STRING-CAST`. Constraint asserted by spec FR-007 and reiterated in the plan's Constitution Check (§IV row).
- **AC response handling** — The composer's PUT endpoint may return `null` on a soft failure (upstream package convention). Client MUST treat `null` responses as failure and surface the error to the modal, **not** as success. Guards `BUG-AC-NULL-RETURN-SILENT-FAIL`.
- **Modal content escaping** — All strings rendered inside `UserAccessBulkModal.jsx` come from `__()` / `sprintf()` (i18n-safe by construction) or from server-provided provider metadata that the composer's `<AccessControl>` component already escapes at render time. No raw user-typed input is echoed back untrusted.
- **Confirm-dialog copy** — The `window.confirm(msg)` string is built via `sprintf(__('%1$s %2$d abilities?', 'acrossai-abilities-manager'), label, selectedSlugs.length)`. `label` is a hard-coded literal string; `selectedSlugs.length` is a number. No XSS surface — `window.confirm` renders text, not HTML.
- **Selection state trust** — `selectedSlugs` originates from the row checkboxes populated by the abilities list REST response; slugs are already sanitised at read-time by the query layer. No client-side re-sanitisation needed before dispatch (server does it again on the write path anyway).

## Async Security Context

- **Concurrent per-slug writes** — `Promise.all` fires N writes in parallel. Each write is an independent server-side transaction guarded by the pre-existing capability + nonce checks; no cross-request coordination is introduced. Server-side race hardening (BerlinDB slug-cache reads via ID inside `save_override`, per `BUG-BERLINDDB-STALE-SLUG-CACHE`) is unchanged and covers the "same slug written twice near-simultaneously" edge.
- **Partial failure semantics** — If one per-slug PUT/POST fails, other requests still resolve. The `Promise.all` rejects only when at least one rejects — the failure surfaces to the store's existing error path (no bespoke bulk-error UI added, per spec Edge Cases + FR-010). No half-applied state is stored server-side because each per-slug endpoint is atomic.
- **Modal open ↔ selection race** — Selection state is React-local and cannot change while the modal is open (no other component mutates it). No async race exists here.

## Composer Package Integration (Extensibility §V)

- **Package availability gate** — The User Access `<optgroup>` MUST be disabled (or hidden) when `window.acrossaiAbilitiesManager.access_control_available === false`. Per `DEC-AC-RENDERING-GATE` this is a rendering-only gate; server-side auth is enforced independently by the composer's REST controller. Absence of the composer package MUST NOT break the Site Access or MCP Exposure bulk operations.
- **Provider enumeration** — The modal reads registered providers from the same source the per-row edit drawer uses (dynamic enumeration per spec §Clarifications Q1 / FR-011). No hardcoded provider allowlist that could drift from server capability.

## Findings

**No security warnings.** The feature introduces no new authorisation surface. All new client-side calls consume pre-existing, previously-audited REST endpoints. The three guarded bug patterns (`BUG-MERGER-BOOL-STRING-CAST`, `BUG-AC-NULL-RETURN-SILENT-FAIL`, `BUG-ESLINT-DISABLE-LINE-EXACT`) are called out in the plan and will be verified during code review + `/speckit-implement`.

**Not audited (by policy):**
- `permission_callback` compliance on the preserved REST routes (per `feedback_skip_permission_callback_audit` — server-side controllers unchanged by this feature).

# Planning: Bulk Actions overhaul — Custom Abilities admin page (Feature 056)

Replace the **Publish / Unpublish / Delete** bulk options on the Custom
Abilities admin page (`?page=acrossai-abilities-manager`) with three
ability-native bulk operations that mirror the tri-state overrides already
available in the per-row edit drawer:

1. **Site Access** — Force Block / Inherit / Force Allow (`site_allowed` column)
2. **MCP Exposure** — Enable / Disable / Default (`show_in_mcp` column)
3. **User Access** — per-user / per-role rule via the composer package
   `wpboilerplate/wpb-access-control ^2.0.0` (opens a modal, applies the same
   rule to every selected slug)

The current `publish`/`unpublish`/`delete` verbs are WP-CPT vocabulary and
don't correspond to how this plugin's overrides actually behave. An ability is
not a post; there is no "publish" workflow, and the "delete" verb has already
misled users into thinking they can remove plugin-registered abilities (which
they can't — the delete endpoint only clears overrides on non-`db` sources).

All storage, sanitisation, and REST endpoints required to implement this
feature **already exist** (see the "Preserved API contract" table inside the
speckit spec below). This is a client-side refactor: dropdown structure +
handler + two new store thunks + one new modal. No PHP change. No new REST
endpoints. No new database tables. No `composer.json` change.

**Additional user requirement**: "Allow all ability types to be edited." Phase
1 exploration confirmed the row-level Edit action is already unconditional —
no `source` gate exists at `AbilitiesList.jsx`'s row-render site. The
implementation must **verify** this at TASK-7 (grep for any `source ===`
conditional near the Edit button); if any gate is found, remove it as a
zero-risk delta.

**Destructive-transition safeguard**: bulk `Force Block` and bulk `MCP Disable`
trigger a `window.confirm()` before dispatch. Force Allow, Inherit, MCP
Enable, and MCP Default apply immediately. Matches how the existing bulk
Delete confirms today.

---

## Speckit Workflow

```markdown
# 1. Branch
/speckit.git.feature "bulk-actions-overhaul"

# 2. Specify
/speckit.specify "Replace the Publish/Unpublish/Delete Bulk Actions dropdown
on the Custom Abilities admin page (?page=acrossai-abilities-manager) with
three ability-native bulk operations that mirror the per-row edit drawer:
Site Access tri-state (Force Block / Inherit / Force Allow — writes column
site_allowed), MCP Exposure tri-state (Enable / Disable / Default — writes
column show_in_mcp), and User Access (opens a modal that applies one
wpboilerplate/wpb-access-control rule to every selected slug via PUT
/wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}). Client-side only:
reuse the existing per-slug endpoints POST /acrossai-abilities-manager/v1/
abilities/{slug} (site_allowed | show_in_mcp) and PUT /wpb-ac/v1/abilities/
rules/acrossai-abilities/{slug} (ac_key, ac_options[]). Loop with
Promise.all matching the existing bulkUpdateStatus pattern at
src/js/abilities/store/index.js lines 284-299. window.confirm() prompts
only on the two destructive transitions (site_access:force_block and
mcp:disable). Also verify (do not add) that the row-level Edit button in
src/js/abilities/components/AbilitiesList.jsx is unconditional regardless
of Source column value (Plugin / Core / Theme / Custom); remove any
source-based gate if one exists. Do not touch AbilityForm.jsx per-row
edit controls. Do not add new REST endpoints. Do not change tri-state
storage or sanitisation (already handled by AcrossAI_Sanitizer::
sanitize_tri_state and AcrossAI_Abilities_Query::update_ability /
save_override). Do not fork or PR the composer package. Do not change
composer.json or package.json. Ship as release-0.0.15 following the
release-0.0.14 pattern (commit f936aab): bump README Stable tag +
plugin header Version + ACROSSAI_ABILITIES_MANAGER_VERSION constant,
add Changelog + Upgrade Notice blocks, PR against main, merge, tag."

# 3. Clarify (optional — run only if speckit surfaces unknowns)
/speckit.clarify

# 4. Plan
/speckit.plan

# 5. Tasks
/speckit.tasks

# 6. Implement
/speckit.implement
```

### Detailed Description for `/speckit.specify`

> **Before writing a single line of code, read and internalize all of these
> governing documents in full:**
>
> 1. `AGENTS.md` — this plugin's singleton pattern, hook registration rules,
>    Before Commit Checklist.
> 2. `docs/spec-kit-workflow.md` + `docs/what-is-spec-kit.md` — the workflow
>    this feature runs under.
> 3. `src/js/abilities/components/AbilitiesList.jsx` — the JSX file that
>    renders the Bulk Actions dropdown (lines ~455-476) and the
>    `handleBulkApply()` handler (lines ~329-372). Read the ENTIRE file
>    before editing; the row renderer's Edit-button site is also inside it.
> 4. `src/js/abilities/store/index.js` — the store where the existing
>    `bulkUpdateStatus(slugs, status)` thunk lives at lines 284-299 and
>    `bulkDeleteAbilities(slugs)` at lines 269-282. The two new thunks
>    added by this feature MUST match the same shape (returns an async
>    function of `{ dispatch }`, uses `Promise.all`, dispatches
>    `actions.fetchAbilities()` at the end).
> 5. `src/js/abilities/components/AbilityForm.jsx` around lines 1360-1380 —
>    the existing per-row edit drawer's User Access panel that mounts the
>    composer's `<AccessControl>` component. The new bulk modal reuses this
>    same component when possible.
> 6. `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`
>    — the REST controller that accepts the tri-state writes. Read to
>    confirm the field names (`site_allowed`, `show_in_mcp`) and the
>    accepted input values (`true|false|null` plus string aliases handled by
>    `AcrossAI_Sanitizer::sanitize_tri_state`).
> 7. `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` —
>    the adapter that wires the composer package. Read to confirm the table
>    slug (`abilities`) and the namespace (`acrossai-abilities`) that the
>    User Access modal's REST calls must use.
> 8. `vendor/wpboilerplate/wpb-access-control/src/RestApi/RulesController.php`
>    — the composer-owned REST controller. Read to confirm the exact PUT
>    body shape: `{ ac_key: string, ac_options: string[] }`.
>
> Every decision — dropdown structure, dispatch parsing, modal behaviour,
> confirm-dialog copy, release branch pattern — must be justified against
> the above. Do not invent storage layers or endpoints; everything the
> feature needs is already implemented on the PHP side.
>
> **Public API artifacts to preserve verbatim (grep-gate before + after):**
>
> - `AcrossAI_Abilities_Query::update_ability( int $id, array $fields ): bool`
> - `AcrossAI_Abilities_Query::save_override( string $slug, array $fields )`
> - `AcrossAI_Sanitizer::sanitize_tri_state( mixed $input ): bool|null`
> - REST route: `POST /wp-json/acrossai-abilities-manager/v1/abilities/{slug}`
>   with body `{ site_allowed?: bool|null, show_in_mcp?: bool|null }`
> - REST route: `PUT /wp-json/wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}`
>   with body `{ ac_key: string, ac_options: string[] }`
> - Composer package: `wpboilerplate/wpb-access-control ^2.0.0`
>
> Pre-flight grep (records the caller surface that MUST still resolve after
> every TASK):
>
> ```
> grep -rEn '(bulkUpdateStatus|bulkDeleteAbilities|api\.updateAbility)' \
>     --include='*.js' --include='*.jsx' src/
> grep -rEn "acrossai-abilities-manager/v1/abilities/" \
>     --include='*.php' includes/
> grep -rEn "wpb-ac/v1/abilities/rules" \
>     --include='*.js' --include='*.jsx' src/ vendor/wpboilerplate/
> ```
>
> Every hit that surfaces here MUST still resolve — same function name,
> same REST path, same request/response shape — after every TASK.
>
> **Preserved API contract (data-preservation shape):**
>
> | Domain | Field / Endpoint | Tri-state values (JSON) | Storage |
> | --- | --- | --- | --- |
> | Site Access | `POST /abilities/{slug}` body `site_allowed` | `true` (Force Allow), `false` (Force Block), `null` (Inherit) | `{prefix}acrossai_abilities.site_allowed` |
> | MCP Exposure | `POST /abilities/{slug}` body `show_in_mcp` | `true` (Enable), `false` (Disable), `null` (Default) | `{prefix}acrossai_abilities.show_in_mcp` |
> | User Access | `PUT /wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}` body `{ ac_key, ac_options: [] }` | `ac_key ∈ { '' (clear), 'everyone', 'wp_user', 'wp_role', 'wp_capability', 'buddy_boss_profile_type', 'memberpress_membership' }` | `{prefix}abilities_access_control` |
>
> Accepted `site_allowed` / `show_in_mcp` inputs on the REST controller
> (documented at `AcrossAI_Sanitizer::sanitize_tri_state`): the client can
> also send `"null"`, `"inherit"`, `1`, `"1"`, `0`, `"0"` — normalised to
> the three canonical PHP values. Client code in this feature MUST send
> exact `true|false|null` JSON only; do not rely on string aliases.
>
> ---
>
> **TASK-1 — Replace the Bulk Actions dropdown structure**
>
> Files: `src/js/abilities/components/AbilitiesList.jsx`
>
> Read the current dropdown (lines ~455-476) BEFORE editing. Confirm the
> existing three `<option>` elements are `publish` / `unpublish` / `delete`.
>
> Replace with three `<optgroup>` blocks. Each option's `value` is a
> `<domain>:<value>` string that TASK-2's handler parses:
>
> ```jsx
> <select
>   value={bulkAction}
>   onChange={(e) => setBulkAction(e.target.value)}
>   aria-label={__('Bulk actions', 'acrossai-abilities-manager')}
> >
>   <option value="">
>     {__('Bulk Actions', 'acrossai-abilities-manager')}
>   </option>
>   <optgroup label={__('Site Access', 'acrossai-abilities-manager')}>
>     <option value="site_access:force_allow">
>       {__('Force Allow', 'acrossai-abilities-manager')}
>     </option>
>     <option value="site_access:inherit">
>       {__('Inherit', 'acrossai-abilities-manager')}
>     </option>
>     <option value="site_access:force_block">
>       {__('Force Block', 'acrossai-abilities-manager')}
>     </option>
>   </optgroup>
>   <optgroup label={__('MCP Exposure', 'acrossai-abilities-manager')}>
>     <option value="mcp:enable">
>       {__('Enable', 'acrossai-abilities-manager')}
>     </option>
>     <option value="mcp:default">
>       {__('Default', 'acrossai-abilities-manager')}
>     </option>
>     <option value="mcp:disable">
>       {__('Disable', 'acrossai-abilities-manager')}
>     </option>
>   </optgroup>
>   <optgroup label={__('User Access', 'acrossai-abilities-manager')}>
>     <option value="user_access:configure">
>       {__('Configure…', 'acrossai-abilities-manager')}
>     </option>
>   </optgroup>
> </select>
> ```
>
> Add near the top of the component:
>
> ```js
> const [userAccessModalOpen, setUserAccessModalOpen] = useState(false);
> ```
>
> Do NOT rename `bulkAction` / `setBulkAction` / `selectedSlugs` state
> variables — they are consumed by TASK-2 and by the enclosing `<form>`.
>
> ---
>
> **TASK-2 — Rewrite `handleBulkApply()`**
>
> Files: `src/js/abilities/components/AbilitiesList.jsx`
>
> Read the current handler (lines ~329-372) BEFORE editing. It currently
> branches on `bulkAction === 'publish' | 'unpublish' | 'delete'` and
> dispatches to `bulkUpdateStatus` / `bulkDeleteAbilities`.
>
> Replace with a parse-and-dispatch shape:
>
> ```js
> async function handleBulkApply() {
>   if (!bulkAction || selectedSlugs.length === 0) {
>     return;
>   }
>   const [domain, value] = bulkAction.split(':');
>
>   // Destructive-transition confirms — matches the existing bulk-delete
>   // confirm UX (see the prior implementation).
>   if (
>     (domain === 'site_access' && value === 'force_block') ||
>     (domain === 'mcp' && value === 'disable')
>   ) {
>     const label =
>       domain === 'site_access'
>         ? __('force-block', 'acrossai-abilities-manager')
>         : __('disable MCP on', 'acrossai-abilities-manager');
>     const msg = sprintf(
>       /* translators: 1: action label, 2: count of selected abilities */
>       __('%1$s %2$d abilities?', 'acrossai-abilities-manager'),
>       label,
>       selectedSlugs.length
>     );
>     // eslint-disable-next-line no-alert
>     if (!window.confirm(msg)) {
>       return;
>     }
>   }
>
>   if (domain === 'site_access') {
>     const v =
>       value === 'force_allow' ? true : value === 'force_block' ? false : null;
>     await dispatch(bulkUpdateTristate(selectedSlugs, 'site_allowed', v));
>   } else if (domain === 'mcp') {
>     const v =
>       value === 'enable' ? true : value === 'disable' ? false : null;
>     await dispatch(bulkUpdateTristate(selectedSlugs, 'show_in_mcp', v));
>   } else if (domain === 'user_access') {
>     setUserAccessModalOpen(true);
>     // Selection + bulk-action state must persist while the modal is open,
>     // so DO NOT clear either here. TASK-4's modal calls
>     // clearBulkSelection() after it dispatches.
>     return;
>   }
>
>   setBulkAction('');
>   setSelectedSlugs([]);
> }
> ```
>
> Import `sprintf` from `@wordpress/i18n` alongside `__` if not already
> imported. Import the new `bulkUpdateTristate` thunk from TASK-3.
>
> Mount the modal near the return statement so it renders when
> `userAccessModalOpen` is true:
>
> ```jsx
> {userAccessModalOpen && (
>   <UserAccessBulkModal
>     slugs={selectedSlugs}
>     onClose={() => setUserAccessModalOpen(false)}
>     onApplied={() => {
>       setUserAccessModalOpen(false);
>       setBulkAction('');
>       setSelectedSlugs([]);
>     }}
>   />
> )}
> ```
>
> Do NOT remove `bulkUpdateStatus` or `bulkDeleteAbilities` imports —
> other places in the codebase MAY still call them (grep before removal).
> If the grep from the pre-flight step returns zero remaining callers,
> delete them in a follow-up commit — NOT in this feature.
>
> ---
>
> **TASK-3 — Add two new store thunks**
>
> Files: `src/js/abilities/store/index.js`
>
> Read the existing `bulkUpdateStatus(slugs, status)` at lines 284-299
> BEFORE editing. Copy its shape verbatim — this is the reference pattern.
>
> Add after line 299 (i.e. after the `bulkUpdateStatus` closing brace):
>
> ```js
> /**
>  * Bulk-apply a tri-state override to many abilities.
>  *
>  * Loops the existing per-slug POST /abilities/{slug} endpoint under
>  * Promise.all — mirrors the shape of bulkUpdateStatus.
>  *
>  * @param {string[]} slugs Ability slugs.
>  * @param {'site_allowed'|'show_in_mcp'} field Tri-state field to write.
>  * @param {boolean|null} value true | false | null (Force Allow / Force
>  *                             Block / Inherit; Enable / Disable / Default).
>  */
> bulkUpdateTristate(slugs, field, value) {
>   return async ({ dispatch }) => {
>     await Promise.all(
>       slugs.map((slug) => api.updateAbility(slug, { [field]: value }))
>     );
>     dispatch(actions.fetchAbilities());
>   };
> },
>
> /**
>  * Bulk-apply a wpb-access-control rule to many abilities.
>  *
>  * The composer package has no batch endpoint — this thunk loops the
>  * PUT /wpb-ac/v1/abilities/rules/acrossai-abilities/{slug} endpoint
>  * per slug. Empty acKey clears the rule (everyone allowed).
>  *
>  * @param {string[]} slugs      Ability slugs.
>  * @param {string}   acKey      Access-control provider id (e.g. wp_user,
>  *                              wp_role, wp_capability), or '' to clear.
>  * @param {string[]} acOptions  Provider-specific option ids (user IDs,
>  *                              role slugs, etc.). Empty when acKey === ''.
>  */
> bulkSetUserAccessRule(slugs, acKey, acOptions) {
>   return async ({ dispatch }) => {
>     const base = '/wpb-ac/v1/abilities/rules/acrossai-abilities/';
>     await Promise.all(
>       slugs.map((slug) =>
>         apiFetch({
>           path: base + encodeURIComponent(slug),
>           method: 'PUT',
>           data: { ac_key: acKey, ac_options: acOptions },
>         })
>       )
>     );
>     dispatch(actions.fetchAbilities());
>   };
> },
> ```
>
> Import `apiFetch` from `@wordpress/api-fetch` at the top of the file if
> not already imported (it is used by the existing `api` module — verify
> before adding).
>
> Export both new thunks from the store's action creators bag so
> TASK-2's dispatch calls resolve. Follow the existing export shape (do
> NOT invent a new export style).
>
> Do NOT modify `bulkUpdateStatus` or `bulkDeleteAbilities` — they remain
> for backward compatibility until a follow-up removes the callers.
>
> ---
>
> **TASK-4 — New file `UserAccessBulkModal.jsx`**
>
> Files:
> - `src/js/abilities/components/UserAccessBulkModal.jsx` (NEW)
>
> Read `AbilityForm.jsx` lines 1360-1380 BEFORE creating the new file —
> that block shows how the existing per-row User Access panel mounts the
> composer's `<AccessControl>` component.
>
> Preferred approach: reuse the composer's `<AccessControl>` React
> component from the `@wpb/access-control` npm package the same way
> `AbilityForm.jsx` does. If the component's `resourceKey` prop is
> single-value only (i.e. it does not accept an array of slugs), the
> modal falls back to a minimal in-house form:
>
> ```jsx
> import { Modal, Button, SelectControl } from '@wordpress/components';
> import { useState } from '@wordpress/element';
> import { __, sprintf } from '@wordpress/i18n';
> import { useDispatch } from '@wordpress/data';
> import { STORE_NAME } from '../store';
>
> export default function UserAccessBulkModal({ slugs, onClose, onApplied }) {
>   const [acKey, setAcKey] = useState('');
>   const [acOptions, setAcOptions] = useState([]);
>   const [busy, setBusy] = useState(false);
>   const [error, setError] = useState(null);
>   const { bulkSetUserAccessRule } = useDispatch(STORE_NAME);
>
>   async function handleApply() {
>     setError(null);
>     setBusy(true);
>     try {
>       await bulkSetUserAccessRule(slugs, acKey, acOptions);
>       onApplied();
>     } catch (e) {
>       setError(e?.message || __('Failed to apply.', 'acrossai-abilities-manager'));
>       setBusy(false);
>     }
>   }
>
>   return (
>     <Modal
>       title={sprintf(
>         /* translators: %d: count of selected abilities */
>         __('Configure User Access on %d abilities', 'acrossai-abilities-manager'),
>         slugs.length
>       )}
>       onRequestClose={onClose}
>       className="acrossai-abilities-user-access-bulk-modal"
>     >
>       {/* Prefer the composer's <AccessControl> here if it supports
>           bulk resourceKey; otherwise use a minimal provider + option
>           picker whose values feed bulkSetUserAccessRule(). */}
>       <SelectControl
>         label={__('Access provider', 'acrossai-abilities-manager')}
>         value={acKey}
>         options={[
>           { value: '', label: __('Everyone (clear rule)', 'acrossai-abilities-manager') },
>           { value: 'wp_user', label: __('Specific users', 'acrossai-abilities-manager') },
>           { value: 'wp_role', label: __('Roles', 'acrossai-abilities-manager') },
>           { value: 'wp_capability', label: __('Capabilities', 'acrossai-abilities-manager') },
>         ]}
>         onChange={(v) => {
>           setAcKey(v);
>           setAcOptions([]);
>         }}
>       />
>       {/* Options input — user picker / role select / capability select
>           depending on acKey. Reuse existing components where possible. */}
>
>       {error && <p role="alert">{error}</p>}
>
>       <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
>         <Button variant="secondary" onClick={onClose} disabled={busy}>
>           {__('Cancel', 'acrossai-abilities-manager')}
>         </Button>
>         <Button variant="primary" onClick={handleApply} isBusy={busy}>
>           {__('Apply to all', 'acrossai-abilities-manager')}
>         </Button>
>       </div>
>     </Modal>
>   );
> }
> ```
>
> The provider list in the fallback form MUST match the providers
> registered by the plugin adapter — see
> `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` for
> the current list (the plugin registers `wp_user`, `wp_role`,
> `wp_capability`, plus `everyone` as a sentinel).
>
> Do NOT invent a new REST endpoint — this modal ONLY dispatches the
> `bulkSetUserAccessRule` thunk from TASK-3, which uses the composer's
> existing `PUT /wpb-ac/v1/abilities/rules/...` endpoint.
>
> ---
>
> **TASK-5 — SCSS for the new `<optgroup>` labels**
>
> Files: `src/scss/abilities/admin.scss`
>
> Native browser rendering of `<optgroup>` is inconsistent. Add a small
> rule near the existing bulk-toolbar selectors:
>
> ```scss
> .acrossai-abilities-list__bulk-select {
>   optgroup {
>     font-style:     italic;
>     font-weight:    600;
>     color:          var(--wp-admin-theme-color, #2271b1);
>     background:     #f6f7f7;
>   }
>   optgroup option {
>     font-style:  normal;
>     font-weight: normal;
>     color:       #1e1e1e;
>     padding-left: 12px;
>   }
> }
> ```
>
> Grep for the existing bulk-select class name BEFORE adding — if the
> current `<select>` has a different class or no class, add one on the
> same line as the `<select>` in TASK-1 rather than styling by tag.
>
> ---
>
> **TASK-6 — Rebuild + commit build artifacts**
>
> Files:
> - `build/js/abilities.js`, `build/js/abilities.asset.php`
> - `build/css/abilities.css`, `build/css/abilities-rtl.css`,
>   `build/css/abilities.asset.php`
>
> ```
> npm run build
> ```
>
> Confirm webpack exits 0. Commit the regenerated `build/` files
> alongside the source-code changes in one commit.
>
> ---
>
> **TASK-7 — Verification checklist**
>
> No new PHP code, but re-run:
>
> ```
> composer run phpstan
> composer run phpcs -- src/ includes/
> ```
>
> Both MUST exit 0.
>
> Manual smoke on `?page=acrossai-abilities-manager`:
>
> 1. Dropdown shows exactly three `<optgroup>` labels (Site Access, MCP
>    Exposure, User Access) with the expected inner `<option>` entries.
> 2. Select 5 abilities of mixed sources (`Plugin`, `Core`, `Custom`).
>    Bulk Actions → Site Access → Force Allow → Apply — no confirm shown;
>    all 5 rows report `site_allowed=1` after refetch. Verify DB via
>    WP-CLI: `wp db query "SELECT slug, site_allowed FROM
>    {prefix}acrossai_abilities WHERE slug IN (...)"`.
> 3. Repeat with Force Block → **confirm dialog appears**, decline cancels
>    (no DB change), accept flips all 5 to `site_allowed=0`.
> 4. Repeat with Inherit → all 5 rows report `site_allowed=NULL`.
> 5. Repeat all three transitions for MCP Exposure (`show_in_mcp` column).
>    Disable shows a confirm; Enable and Default apply immediately.
> 6. User Access → Configure… → modal opens, pick `wp_role` + `editor` →
>    Apply → all selected slugs get the same rule row. Verify via
>    `wp db query "SELECT * FROM {prefix}abilities_access_control WHERE
>    key IN (...)"`.
> 7. User Access → Configure… → pick "Everyone (clear rule)" → Apply →
>    all selected slugs have their rules removed.
> 8. Row-level Edit button is present and clickable on every visible row
>    regardless of `Source` (Plugin / Core / Theme / Custom).
>
> Grep verification (row Edit unconditional):
>
> ```
> grep -nE "(source|Source)\s*[!=]=" src/js/abilities/components/AbilitiesList.jsx
> ```
>
> No hits near the row-Edit button render site. If a hit surfaces,
> remove the condition as a zero-risk delta and re-run the smoke.
>
> ---
>
> **TASK-8 — Release housekeeping (release-0.0.15)**
>
> Files:
> - `README.txt` — bump `Stable tag: 0.0.14` → `0.0.15`; add
>   `= 0.0.15 =` blocks in Changelog and Upgrade Notice.
> - `acrossai-abilities-manager.php` — bump `* Version: 0.0.14` →
>   `* Version: 0.0.15`.
> - `includes/Main.php` — bump `ACROSSAI_ABILITIES_MANAGER_VERSION`
>   constant from `'0.0.14'` to `'0.0.15'`.
>
> Follow the pattern from commit `f936aab` (release-0.0.14):
>
> 1. Feature branch merges to main first (this feature 056).
> 2. Cut a separate `release-0.0.15` branch off the updated main.
> 3. Bump the three version markers + add the changelog blocks.
> 4. Commit, push, open PR against main.
> 5. Merge.
> 6. Tag `0.0.15` (no `v` prefix — matches existing tags 0.0.1…0.0.14)
>    on main HEAD. Push tag. `gh release create 0.0.15 --title 0.0.15
>    --notes "..."`.
>
> Suggested Changelog entry:
>
> ```
> = 0.0.15 =
> * **Bulk Actions overhaul — Site Access / MCP Exposure / User Access.** The Custom Abilities admin page's Bulk Actions dropdown replaces Publish / Unpublish / Delete (which never mapped to ability overrides) with the same tri-state operations available in the per-row edit drawer: Site Access (Force Block / Inherit / Force Allow), MCP Exposure (Enable / Disable / Default), and User Access (opens a modal that applies one rule to all selected abilities via the composer-provided access-control REST API). Destructive transitions (Force Block, MCP Disable) prompt for confirmation before applying. Reuses the existing per-slug REST endpoints — no new database tables, no new endpoints, no PHP changes.
> ```
>
> Suggested Upgrade Notice entry:
>
> ```
> = 0.0.15 =
> UI-only release. Replaces the Custom Abilities Bulk Actions dropdown (Publish / Unpublish / Delete) with Site Access, MCP Exposure, and User Access operations that match the per-row edit drawer. Reuses existing REST endpoints; no new database tables, no new endpoints, no permission changes. Safe upgrade.
> ```

---

### Public API artifacts to preserve verbatim (grep-gate before + after)

- `AcrossAI_Abilities_Query::update_ability( int $id, array $fields ): bool` at `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`
- `AcrossAI_Abilities_Query::save_override( string $slug, array $fields )` — same file
- `AcrossAI_Sanitizer::sanitize_tri_state( mixed $input ): bool|null` at `includes/Utilities/AcrossAI_Sanitizer.php`
- REST route: `POST /wp-json/acrossai-abilities-manager/v1/abilities/{slug}`
- REST route: `PUT /wp-json/wpb-ac/v1/abilities/rules/acrossai-abilities/{slug}`
- Composer: `wpboilerplate/wpb-access-control ^2.0.0` — already in `composer.lock`; do not modify

Pre-flight + post-flight grep MUST return identical hit lists for:

```
grep -rEn '(bulkUpdateStatus|bulkDeleteAbilities|api\.updateAbility)' \
    --include='*.js' --include='*.jsx' src/
grep -rEn "'acrossai-abilities-manager/v1/abilities/'" \
    --include='*.php' includes/
grep -rEn "wpb-ac/v1/abilities/rules" \
    --include='*.js' --include='*.jsx' src/ vendor/wpboilerplate/
```

---

### Out of scope

- No PHP changes. All storage, sanitisation, and REST endpoints already exist.
- No new database tables or new REST endpoints.
- No changes to `wpboilerplate/wpb-access-control` (no fork, no PR upstream).
- No changes to how tri-state values are stored / sanitised on the PHP side.
- No changes to per-row edit controls in `AbilityForm.jsx`.
- No `composer.json` change; no `package.json` change (no new npm deps).
- No changes to the row-level Actions column beyond verifying Edit is unconditional.
- Removal of the now-unused `bulkUpdateStatus` / `bulkDeleteAbilities` thunks — deferred to a follow-up after confirming zero remaining callers.

---

## Manual verification checklist (post-implementation, for the PR reviewer)

### 1. Dropdown structure

**Expected**: `?page=acrossai-abilities-manager` renders a Bulk Actions
`<select>` with three `<optgroup>` labels ("Site Access", "MCP Exposure",
"User Access") and no `publish`/`unpublish`/`delete` options anywhere.

**Result**: [ ] PASS  [ ] FAIL

### 2. Tri-state bulk writes

**Preconditions**: 5 abilities selected; note their pre-state `site_allowed`
values via WP-CLI.

For each transition (Force Allow → true, Inherit → NULL, Force Block →
false), verify the DB values flip AND the row `Status` badge in the UI
updates on refetch. Repeat for `show_in_mcp`.

**Result**: [ ] PASS  [ ] FAIL

### 3. Destructive-transition confirms

**Expected**: `window.confirm()` prompt appears on `site_access:force_block`
and `mcp:disable` ONLY. Cancel keeps state unchanged; accept applies.
Other four transitions (Force Allow, Inherit, MCP Enable, MCP Default)
apply immediately.

**Result**: [ ] PASS  [ ] FAIL

### 4. User Access modal loop

**Expected**: `User Access → Configure…` opens the modal; picking
`wp_role` + `editor` and clicking Apply writes one rule row per selected
slug in `{prefix}abilities_access_control`.

**Result**: [ ] PASS  [ ] FAIL

### 5. Edit-any-source

**Expected**: every visible row has an enabled `Edit` action regardless
of `Source` column value (Plugin / Core / Theme / Custom).

**Result**: [ ] PASS  [ ] FAIL

### 6. Static analysis

```
$ composer run phpstan
<expected: exit 0>

$ composer run phpcs -- src/ includes/
<expected: exit 0>

$ npm run build
<expected: webpack compiled successfully>
```

**Result**: [ ] PASS  [ ] FAIL

### 7. Grep-gate parity

Pre-flight and post-flight runs of the three grep commands (above)
return **identical hit lists**.

**Result**: [ ] PASS  [ ] FAIL

### 8. Version consistency (release-0.0.15 PR only)

```
$ grep "^Stable tag" README.txt
Stable tag: 0.0.15

$ grep " \* Version:" acrossai-abilities-manager.php
 * Version:           0.0.15

$ grep "ACROSSAI_ABILITIES_MANAGER_VERSION.*'0" includes/Main.php
$this->define( 'ACROSSAI_ABILITIES_MANAGER_VERSION', '0.0.15' );
```

**Result**: [ ] PASS  [ ] FAIL

---

### Summary & merge decision

| Gate | Status | Evidence |
|---|---|---|
| Dropdown structure (§ 1) | [ ] PASS / [ ] FAIL | screenshot + inspector |
| Tri-state bulk writes (§ 2) | [ ] PASS / [ ] FAIL | WP-CLI output |
| Destructive-transition confirms (§ 3) | [ ] PASS / [ ] FAIL | manual click-through |
| User Access modal loop (§ 4) | [ ] PASS / [ ] FAIL | WP-CLI output |
| Edit-any-source (§ 5) | [ ] PASS / [ ] FAIL | inspection |
| PHPStan L8 + PHPCS + build (§ 6) | [ ] PASS / [ ] FAIL | CI logs |
| Grep-gate parity (§ 7) | [ ] PASS / [ ] FAIL | diff output |
| Release version consistency (§ 8) | [ ] PASS / [ ] FAIL | grep output |

**Merge decision**: [ ] APPROVE  [ ] BLOCK — signature + date required
when all 8 gates are green.

# Planning: Library page Bulk Enable/Disable + URL-synced tabs (Feature 052)

Add two admin-UX affordances to the shared Ability Library page
(`?page=acrossai-abilities-library`):

1. **Two side-by-side bulk-action buttons** in a new right-aligned header
   row: a primary `Enable All` and a secondary `Disable All`. Both actions
   preserve per-category `mode` and `sub_keys` — flipping ONLY the
   category-level `enabled` boolean — so re-enabling recovers prior
   specific-mode selections without loss. **Both actions are scoped to
   the currently active tab.** On the default "All" tab they operate on
   every registered category. On a specific tab (e.g. Core, Themes,
   Blocks) they only touch the categories whose `tab_group === activeTab`.
2. **Two-way URL sync** for the active tab. The URL uses `?tab=<slug>`
   (or is absent for the default "All" view), page load reads it, tab
   clicks push it, browser back/forward re-syncs. Matches the WP admin
   `?page=…&tab=…` convention.

Persisted config shape stays identical to the existing per-card write. Each
touched category ends up with:

```
[<category-slug>] => Array
    (
        [enabled] => 0 or 1
        [mode] => 'all' or 'specific'   (preserved from prior value)
        [sub_keys] => Array (…)          (preserved from prior value)
    )
```

The bulk-toggle uses the existing `POST /acrossai-abilities-library/v1/abilities/config` — no new REST endpoint. A single-source-of-truth helper on the `Ability_Definition` base class answers "are we in an all-enabled, all-disabled, or mixed state?" so the button label(s) can reflect state on first paint without a REST round-trip. Tab-scoped state is re-derived client-side from `config` + `activeTab` whenever the user switches tabs.

This plan is based on the live Library page state as of 2026-07-13 (release 0.0.6). The 17 rebranded categories + 176 abilities absorbed in Feature 046 are the target inventory this feature operates on.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "052-library-bulk-toggle-and-url-synced-tabs"

# 2. Specify
/speckit.specify "Add two UX affordances to the Library admin page (?page=acrossai-abilities-library).

Affordance A — Two side-by-side bulk-action buttons (Enable All / Disable All), scoped to the currently active tab.

Placement: a new right-aligned header row above the TabPanel and card list. Renders TWO separate @wordpress/components Buttons side-by-side: a primary Button labeled 'Enable All' and a secondary Button labeled 'Disable All'. No split-button, no dropdown. Both buttons are always visible.

Scope rule: the action targets ONLY the categories currently in view via the active TabPanel tab.
- On the 'All' tab (activeTab === ALL_TABS_KEY), the action targets every registered category.
- On any specific tab (e.g. activeTab === 'core' or activeTab === 'blocks'), the action targets ONLY the categories whose ability metadata has tab_group === activeTab. Categories in other tabs are NOT touched — their config entries pass through unchanged.

Write semantics: the JS assembles a NEW config object where each in-scope category has {enabled: true|false, mode: prior mode ?? 'all', sub_keys: prior sub_keys ?? {}} — only the enabled boolean flips; mode and sub_keys are preserved from whatever was previously stored for that category. Out-of-scope categories keep their existing config entry byte-for-byte. The full merged object is persisted via a SINGLE POST /acrossai-abilities-library/v1/abilities/config call (existing endpoint; no new route). Sparse-storage on the server continues to strip entries at the all-default state ({enabled: true, mode: 'all', sub_keys: []}) — this happens automatically.

Persisted config shape (one representative entry after Disable All is clicked on the Content tab):
[acrossai-abilities-manager-content-post] => Array
    (
        [enabled] => 0         # flipped
        [mode] => 'specific'   # preserved from prior value
        [sub_keys] => Array ('post/create' => true, …)  # preserved
    )

Preserving sub_keys means that re-enabling after a bulk disable restores the admin's prior per-slug specific-mode selections — no data loss on a single click.

The button labels stay static ('Enable All' / 'Disable All'). Disabled/pressed state is optional: if Enable All is redundant (every in-scope category is already enabled), that button MAY render as disabled; same for Disable All when every in-scope category is already disabled. This is a nice-to-have, not a must.

Affordance B — URL-synced tabs.

The TabPanel migrates from its default uncontrolled-state to a controlled activeTab tracked in React state. A new useLibraryTabSync hook mirrors the pattern in src/js/abilities/hooks/useUrlViewSync.js: on mount it reads ?tab=<slug> from window.location, validates against the current tabGroups list, and initializes activeTab to the matching slug (or the ALL_TABS_KEY sentinel when the query arg is absent or invalid). On activeTab change, it pushes the URL via window.history.pushState using @wordpress/url's addQueryArgs / removeQueryArgs — adding ?tab=<slug> when a specific tab is active, removing the query arg entirely when the sentinel is active so the default URL stays clean. A popstate listener re-derives activeTab on browser back/forward.

Backend:

Add three static helpers on the existing Ability_Definition base class (includes/Modules/Library/Ability_Definition.php) — the class all 176 absorbed ability classes already extend. is_all_enabled(): bool returns true when the saved config represents an all-enabled state (empty option or every persisted entry has enabled=true) across every registered category. is_all_disabled(): bool returns true when every category currently registered has an explicit enabled=false entry in the saved config. bulk_toggle_state(): string returns 'all' | 'none' | 'mixed' — the tri-state the JS consumes for the initial 'All' tab button-disabled hinting. All three read AcrossAI_Ability_Library_Config::get_config() and, for is_all_disabled/bulk_toggle_state, cross-reference AcrossAI_Ability_Library_Registry::instance()->get_definitions() to know the full set of registered categories.

Note: the three PHP helpers operate over the FULL registered set (matching the default 'All' tab on first paint). Tab-scoped state (e.g. 'is every Core-tab category enabled?') is computed client-side inside LibraryPage.js from the live config + activeTab + tabGroups, since tab-groups are runtime-derived from ability metadata and change with every extension. Do not add per-tab PHP helpers.

Localize the bulk_toggle_state() return value into window.acrossaiAbilityLibraryData as bulkToggleState so the initial-paint button hinting (on the default 'All' tab) doesn't need a REST round-trip. After first paint, all button-disabled hinting is derived from client-side state.

Disabled-card UI contract (Feature 052 turn 2 — matches shipped code as of 2026-07-13): when a category card has enabled=false, LibraryCard.js renders (a) the master ToggleControl + category label — always visible, (b) the chevron disclosure — visible when the category has ≥1 registered ability (canExpand = slugs.length > 0), (c) the All/Specific RadioControl — hidden while disabled, (d) the ability-list panel — visible when chevron is expanded (regardless of enabled), rendered as READONLY bullet-style rows on a disabled card. Interactive <CheckboxControl> rows MUST NOT render on a disabled card even when the stored mode is 'specific' — the interactive-row gate is `enabled && mode === 'specific'`. The stored mode and sub_keys are preserved so re-enable restores the prior specific-mode selections. The bulk Disable All action MUST produce card DOM identical to a manual per-card disable at every point in the render tree (header row + expanded readonly panel).

Explicit non-goals: no new REST endpoint (bulk uses the existing config POST); no schema change to acrossai_library_config option (sparse storage preserved); no new per-slug toggle write paths (the readonly rows on disabled cards do NOT emit onChange); no changes to the tab_group data flow (already runtime-derived from ability metadata); no PHP-side ?tab= parsing (fully client-side); no changes to the sibling MCP Manager or other plugins that share the AcrossAI parent menu."
```

---

## Scope Rules

### Ignore from this feature

- **REST endpoints and controllers** other than the existing `AcrossAI_Ability_Library_Config_Controller` — the bulk toggle uses the existing `POST /abilities/config` route.
- **Schema of the `acrossai_library_config` option** — preserve the sparse storage semantics (entries at their default `{enabled: true, mode: 'all', sub_keys: []}` state are stripped by `AcrossAI_Ability_Library_Config::save_config()` and this behavior stays).
- **`LibraryCard.js` per-slug toggle behavior** — the existing single-card ToggleControl + per-slug CheckboxControl flow is untouched.
- **`Ability_Definition::push_definition()` and the `acrossai_abilities_api_init` filter** — no changes to how ability definitions land in the Registry.
- **Tab-group data flow** — tab groups are runtime-derived from `meta.acrossai.tab_group` in each ability's definition via `groupDefinitions` → `collectTabGroups`. That flow stays.
- **PHP-side `?tab=` parsing** — the entire tab-sync is client-side; `admin/Partials/LibraryMenu.php::render()` continues to output a bare React container.

### Must fix

- Add the header row in `LibraryPage.js` with **two separate side-by-side `<Button>`s** — primary `Enable All` and secondary `Disable All`. No split-button, no dropdown.
- Add `handleEnableAll` / `handleDisableAll` handlers that: (a) determine the in-scope category set from `activeTab` + `tabGroups` + raw definitions, (b) assemble a full-config payload where in-scope entries have their `enabled` boolean flipped and their `mode` + `sub_keys` preserved, out-of-scope entries pass through untouched, and (c) call `saveConfig()` once.
- Migrate `<TabPanel>` from uncontrolled to controlled state so the URL-sync hook can drive it.
- Add the `useLibraryTabSync` hook mirroring `useUrlViewSync`.
- Add three static helpers on `Ability_Definition` (they compute FULL-registry state — matches the initial 'All' tab).
- Localize `bulkToggleState` into the existing `window.acrossaiAbilityLibraryData` global.
- Add Jest tests for the new hook + bulk-toggle handlers, INCLUDING tab-scope filtering (see CHANGE-4c).
- Add PHPUnit tests for the three new static helpers.
- Rebuild the `ability-library.js` bundle via `npm run build`.

---

## Background — Existing Building Blocks

Verified against the current tree on 2026-07-13.

### Client-side (React) — `src/js/ability-library/`

| File | Role |
|---|---|
| `components/LibraryPage.js` | Root component. Renders TabPanel + card list. Owns `config` state + `saveConfig` calls. |
| `components/LibraryCard.js` | Per-category card. ToggleControl for `enabled`, RadioControl for `mode`, CheckboxControl per-slug. Calls `onChange(category, entry)` upstream. |
| `api.js` | Thin wrapper around `apiFetch`. Exports `fetchConfig()` / `saveConfig(config)`. |

Key exports from `LibraryPage.js` today:

- `ALL_TABS_KEY = '__all__'` (sentinel)
- `PINNED_FIRST_TAB_GROUP = 'core'` (Feature 046 pin)
- `groupDefinitions(definitions)` — pure, testable
- `collectTabGroups(items)` — pure, testable
- `filterItemsByTabGroup(items, activeTab)` — pure, testable
- `titleCaseTabLabel(value)` — pure, testable

Config shape (per category):

```js
{
  enabled:  true,       // Category master toggle
  mode:     'all',      // 'all' | 'specific'
  sub_keys: {},         // { [slug]: boolean } — used only when mode === 'specific'
}
```

The server strips entries at their all-default state (`enabled: true`, `mode: 'all'`, `sub_keys: []`) — sparse storage.

### Client-side URL-sync reference

`src/js/abilities/hooks/useUrlViewSync.js` already implements the exact same pattern for the Abilities page's list/edit view. It exports:

- `parseViewFromUrl(url)` — pure
- `buildUrlFromView(view, currentUrl)` — pure
- default `useUrlViewSync()` hook with three effects (mount, change, popstate)

Reuse the three-effect pattern verbatim. Imports:

```js
import { addQueryArgs, getQueryArg, removeQueryArgs } from '@wordpress/url';
```

Available via `@wordpress/scripts`; no dependency change.

### Server-side — Library module

| File | Role |
|---|---|
| `includes/Modules/Library/Ability_Definition.php` | Abstract base class. Constructor hooks `acrossai_abilities_api_init`. All 176 absorbed classes extend this. |
| `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php` | Collects definitions at `init @ P99`. Static `::instance()->get_definitions()` returns the normalized list. |
| `includes/Modules/Library/AcrossAI_Ability_Library_Config.php` | Storage model. `OPTION_KEY = 'acrossai_library_config'` in `wp_options` (site-option, network-aware). Static `get_config()`, `save_config()`, `sanitize_entry()`. |
| `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` | REST controller. `GET /abilities/config`, `POST /abilities/config`. `manage_options` + nonce gated. |
| `admin/Main.php::enqueue_scripts()` | Emits `window.acrossaiAbilityLibraryData` with `definitions`, `restBase`, `nonce`, `addonsUrl`. |

Existing PHPUnit coverage: `tests/phpunit/Modules/Library/Test_Ability_Definition.php` and siblings.

### Existing Jest coverage

`tests/jest/ability-library/`:

- `groupDefinitions.test.js` — 4 named-export helpers
- `collectTabGroups.test.js` — includes the Feature-046 `core`-pin cases
- `filterItemsByTabGroup.test.js`
- `titleCaseTabLabel.test.js`
- `groupBySubGroupPreservingOrder.test.js`
- `LibraryPage.test.js` — thin JSX-render guards
- `LibraryCard.test.js`

All follow `PATTERN-NAMED-EXPORT-JEST` — named pure helpers are exported and unit-tested without rendering.

---

## Target Structure

New / modified paths inside `wp-content/plugins/acrossai-abilities-manager/`:

```
src/
  js/
    ability-library/
      components/
        LibraryPage.js                         # MODIFIED (header row + controlled tab + hook wire + bulk handlers)
      hooks/
        useLibraryTabSync.js                   # NEW (URL-sync hook — mirrors useUrlViewSync)
      api.js                                   # UNCHANGED (existing saveConfig covers bulk)
  scss/
    ability-library/
      admin.scss                               # MODIFIED (add .acrossai-library-page__header styling)

includes/
  Modules/
    Library/
      Ability_Definition.php                   # MODIFIED (three new static helpers)

admin/
  Main.php                                     # MODIFIED (localize bulkToggleState)

tests/
  jest/
    ability-library/
      useLibraryTabSync.test.js                # NEW (parse/push URL helpers)
      LibraryPage.test.js                      # MODIFIED (bulk-toggle + header row cases)
  phpunit/
    Modules/
      Library/
        Test_Ability_Definition.php            # MODIFIED (is_all_enabled / is_all_disabled / bulk_toggle_state)

build/
  js/
    ability-library.js                         # REGENERATED by npm run build
    ability-library.asset.php
  css/
    ability-library.css                        # REGENERATED
    ability-library-rtl.css
```

No changes to `composer.json` / `composer.lock` / autoload / activation / uninstall / REST controllers.

---

## CHANGE-1 — New `Ability_Definition` static helpers

**File**: `includes/Modules/Library/Ability_Definition.php`

Add three public static methods AFTER `push_definition()`:

```php
/**
 * Returns true when the saved library config represents an all-enabled state.
 *
 * Every persisted entry must have enabled=true. Empty saved config (the
 * post-Enable-All sparse-storage state) also returns true because absent
 * entries default to enabled=true.
 *
 * @since 0.1.0
 * @return bool
 */
public static function is_all_enabled(): bool {
    $config = AcrossAI_Ability_Library_Config::get_config();
    foreach ( $config as $entry ) {
        if ( isset( $entry['enabled'] ) && false === (bool) $entry['enabled'] ) {
            return false;
        }
    }
    return true;
}

/**
 * Returns true when every currently registered category has an explicit
 * enabled=false entry in the saved library config.
 *
 * Cross-references the Registry to know the full set of registered
 * categories — an admin-visible "Disable All" state requires an
 * explicit false for every one of them (sparse storage never yields
 * this state implicitly).
 *
 * @since 0.1.0
 * @return bool
 */
public static function is_all_disabled(): bool {
    $config     = AcrossAI_Ability_Library_Config::get_config();
    $registered = self::registered_category_slugs();
    if ( empty( $registered ) ) {
        return false;
    }
    foreach ( $registered as $category ) {
        $entry = $config[ $category ] ?? null;
        if ( ! is_array( $entry ) || true === ( $entry['enabled'] ?? true ) ) {
            return false;
        }
    }
    return true;
}

/**
 * Returns the tri-state of the bulk toggle across the FULL registered set.
 *
 * This is the value the JS reads on first paint (initial 'All' tab).
 * After first paint the JS re-derives per-tab state from the live config;
 * this helper is not consulted for tab-scoped decisions.
 *
 * @since 0.1.0
 * @return string One of 'all' | 'none' | 'mixed'.
 */
public static function bulk_toggle_state(): string {
    if ( self::is_all_enabled() ) {
        return 'all';
    }
    if ( self::is_all_disabled() ) {
        return 'none';
    }
    return 'mixed';
}

/**
 * Private helper — collect the unique category slugs from the Library Registry.
 *
 * @since 0.1.0
 * @return string[]
 */
private static function registered_category_slugs(): array {
    if ( ! class_exists( '\AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry' ) ) {
        return array();
    }
    $definitions = AcrossAI_Ability_Library_Registry::instance()->get_definitions();
    $slugs       = array();
    foreach ( $definitions as $def ) {
        if ( isset( $def['category'] ) && '' !== $def['category'] ) {
            $slugs[ $def['category'] ] = true;
        }
    }
    return array_keys( $slugs );
}
```

The file currently has **zero** `use` statements — `AcrossAI_Ability_Library_Config`, `AcrossAI_Ability_Library_Registry`, and `AcrossAI_Ability_Library_Processor` all live in the SAME namespace as `Ability_Definition` (`AcrossAI_Abilities_Manager\Includes\Modules\Library`), so imports are not strictly required and same-namespace unqualified references work. For clarity and PHPCS/PHPStan-friendly reads, add explicit imports at the top of the file:

```php
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Config;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
```

If a preference emerges to keep the file `use`-free (matching the current style), the class-body references may use unqualified names instead — `AcrossAI_Ability_Library_Config::get_config()` resolves the same way. Either choice is acceptable; pick one and be consistent within the file.

---

## CHANGE-2 — Localize `bulkToggleState`

**File**: `admin/Main.php`

Inside `enqueue_scripts()`, locate the `wp_add_inline_script` block that emits `window.acrossaiAbilityLibraryData` (around line 284). Add one new key to the array:

```php
'bulkToggleState' => \AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition::bulk_toggle_state(),
```

This gives the JS the initial-paint state hint for the default 'All' tab — no REST round-trip. Value is one of `'all' | 'none' | 'mixed'`. It's used to decide whether the `Enable All` / `Disable All` buttons should render as disabled (redundant) on first mount. After first mount, tab-scoped state is re-derived in JS.

---

## CHANGE-3 — New `useLibraryTabSync` hook

**File**: `src/js/ability-library/hooks/useLibraryTabSync.js` (NEW)

Mirror the three-effect pattern from `src/js/abilities/hooks/useUrlViewSync.js`. Named exports:

- `parseTabFromUrl( url, validSlugs, allTabsKey )` — reads the `tab` query arg; returns `allTabsKey` when missing OR when the value is not in `validSlugs`.
- `buildUrlFromTab( activeTab, currentUrl, allTabsKey )` — returns a new URL:
  - When `activeTab === allTabsKey`: strip `?tab=` via `removeQueryArgs`.
  - Otherwise: set `?tab=<slug>` via `addQueryArgs`. Preserve every other query arg.
- Default export: `useLibraryTabSync( activeTab, setActiveTab, validSlugs )` — three effects:
  1. Mount effect: `parseTabFromUrl(window.location.href, validSlugs, ALL_TABS_KEY)` → `setActiveTab(next)` if different from current.
  2. Change effect: `buildUrlFromTab(activeTab, window.location.href, ALL_TABS_KEY)` → `window.history.pushState({}, '', nextUrl)` if different from current.
  3. `popstate` listener: on back/forward, re-parse and dispatch.

Import from `@wordpress/url`:

```js
import { addQueryArgs, getQueryArg, removeQueryArgs } from '@wordpress/url';
```

Import `ALL_TABS_KEY` from `../components/LibraryPage` (or hoist it to a shared constants file if it feels cleaner — LibraryPage.js is fine for now).

---

## CHANGE-4 — Wire the hook + controlled TabPanel + header row in `LibraryPage.js`

**File**: `src/js/ability-library/components/LibraryPage.js`

Three sub-changes:

### 4a. Controlled `activeTab` state

Replace the current uncontrolled `<TabPanel initialTabName={ALL_TABS_KEY}>` with a controlled variant. Add near the top of the component:

```js
const [ activeTab, setActiveTab ] = useState( ALL_TABS_KEY );
```

Pass to TabPanel:

```jsx
<TabPanel
    className="acrossai-library-page__tabs"
    tabs={ tabs }
    initialTabName={ activeTab }
    onSelect={ setActiveTab }
>
    { ( tab ) => renderCards( filterItemsByTabGroup( items, tab.name ) ) }
</TabPanel>
```

Note: `TabPanel`'s `initialTabName` is honored only at mount; changes after mount don't re-render the active tab. To fully drive it from state we pass `key={activeTab}` so React remounts the panel when the URL-sync hook flips the tab from browser navigation. (Cheap, and the card list is memoized upstream.)

### 4b. Wire `useLibraryTabSync`

Immediately after `tabGroups` is memoized, wire the hook:

```js
useLibraryTabSync( activeTab, setActiveTab, tabGroups );
```

Import at the top:

```js
import useLibraryTabSync from '../hooks/useLibraryTabSync';
```

### 4c. Header row + two side-by-side buttons (tab-scoped)

Add a new right-aligned header row above the TabPanel/card container containing **two separate `<Button>` elements** — a primary `Enable All` and a secondary `Disable All`. Both actions target ONLY the categories in the currently active tab.

```jsx
<div className="acrossai-library-page__header">
    <Button
        variant="primary"
        onClick={ handleEnableAll }
        disabled={ inScopeBulkState === 'all' }
    >
        { __( 'Enable All', 'acrossai-abilities-manager' ) }
    </Button>
    <Button
        variant="secondary"
        onClick={ handleDisableAll }
        disabled={ inScopeBulkState === 'none' }
        isDestructive
    >
        { __( 'Disable All', 'acrossai-abilities-manager' ) }
    </Button>
</div>
```

Compute the in-scope category slugs and the tri-state hint via memoized selectors near the top of the component (after `tabGroups` and `items`):

```js
const inScopeCategories = useMemo(
    () => collectInScopeCategories( items, activeTab, ALL_TABS_KEY ),
    [ items, activeTab ]
);

const inScopeBulkState = useMemo(
    () => computeInScopeBulkState( config, inScopeCategories ),
    [ config, inScopeCategories ]
);
```

On very first paint, if the initial URL points to the `All` tab, the initial value of `inScopeBulkState` should match `data.bulkToggleState` — a light sanity check the Jest test asserts.

Add the two handlers:

```js
function handleEnableAll() {
    const next = buildBulkPatch( config, inScopeCategories, true );
    setConfig( next );
    saveConfig( next ).catch( () => setError( __( 'Failed to save.', 'acrossai-abilities-manager' ) ) );
}

function handleDisableAll() {
    const next = buildBulkPatch( config, inScopeCategories, false );
    setConfig( next );
    saveConfig( next ).catch( () => setError( __( 'Failed to save.', 'acrossai-abilities-manager' ) ) );
}
```

And three named-export pure helpers (all follow `PATTERN-NAMED-EXPORT-JEST`):

```js
/**
 * Collect the set of category slugs currently in scope for a bulk action,
 * based on the active tab.
 *
 * When `activeTab === allTabsKey`, returns every unique category present in
 * `items`. Otherwise returns only the categories whose `tabGroup === activeTab`.
 *
 * `items` is the already-grouped array produced by `groupDefinitions()` — each
 * entry has {category, categoryLabel, tabGroup, slugs, …}.
 *
 * @param {Array}  items        Grouped category items (from groupDefinitions).
 * @param {string} activeTab    Current tab slug (or ALL_TABS_KEY sentinel).
 * @param {string} allTabsKey   The sentinel for the "All" tab.
 * @return {string[]}           In-scope category slugs.
 */
export function collectInScopeCategories( items, activeTab, allTabsKey ) {
    const out = [];
    for ( const item of items ) {
        if ( ! item.category ) continue;
        if ( activeTab === allTabsKey || item.tabGroup === activeTab ) {
            out.push( item.category );
        }
    }
    return out;
}

/**
 * Build a bulk-toggle patch scoped to a specific set of categories.
 *
 * Only entries for `inScopeCategories` are rewritten — their `enabled`
 * boolean is set to `enabled` and their prior `mode` + `sub_keys` are
 * preserved. Entries outside the scope pass through byte-for-byte.
 *
 * @param {Object}   currentConfig    Existing config keyed by category.
 * @param {string[]} inScopeCategories Categories the action targets.
 * @param {boolean}  enabled          Target enabled value for in-scope entries.
 * @return {Object}                   New config object.
 */
export function buildBulkPatch( currentConfig, inScopeCategories, enabled ) {
    const next = { ...currentConfig };
    for ( const category of inScopeCategories ) {
        const prior = currentConfig[ category ] ?? { mode: 'all', sub_keys: {} };
        next[ category ] = {
            enabled,
            mode:     prior.mode ?? 'all',
            sub_keys: prior.sub_keys ?? {},
        };
    }
    return next;
}

/**
 * Return 'all' | 'none' | 'mixed' for the given in-scope categories against
 * the current config. Missing entries default to enabled=true (matching the
 * server-side sparse-storage semantics).
 *
 * @param {Object}   currentConfig     Full config keyed by category.
 * @param {string[]} inScopeCategories Categories to evaluate.
 * @return {'all' | 'none' | 'mixed'}
 */
export function computeInScopeBulkState( currentConfig, inScopeCategories ) {
    if ( inScopeCategories.length === 0 ) return 'all';
    let anyEnabled = false;
    let anyDisabled = false;
    for ( const category of inScopeCategories ) {
        const isEnabled = currentConfig[ category ]?.enabled ?? true;
        if ( isEnabled ) anyEnabled = true; else anyDisabled = true;
        if ( anyEnabled && anyDisabled ) return 'mixed';
    }
    if ( anyEnabled && ! anyDisabled ) return 'all';
    return 'none';
}
```

Note: `buildBulkPatch` signature changed — it now takes `(currentConfig, inScopeCategories, enabled)` instead of `(definitions, currentConfig, enabled)`. The definitions-to-categories mapping moved into `collectInScopeCategories`, keeping each helper single-purpose.

---

## CHANGE-5 — SCSS for the header row

**File**: `src/scss/ability-library/admin.scss` (or the sibling `.scss` file that owns `.acrossai-library-page`)

Add:

```scss
.acrossai-library-page__header {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;             /* space between Enable All / Disable All */
    padding: 12px 0;
    margin-bottom: 12px;
    border-bottom: 1px solid #dcdcde;
}
```

Match existing `.acrossai-library-page__*` design tokens. The `gap` is what separates the two side-by-side buttons.

---

## CHANGE-6 — Jest tests

**New file**: `tests/jest/ability-library/useLibraryTabSync.test.js`

Cover:

- `parseTabFromUrl( 'http://example.com/?page=acrossai-abilities-library', […slugs], '__all__' )` → `'__all__'` (no `?tab=`)
- `parseTabFromUrl( 'http://example.com/?page=…&tab=core', ['core','themes'], '__all__' )` → `'core'`
- `parseTabFromUrl( 'http://example.com/?page=…&tab=nonexistent', ['core'], '__all__' )` → `'__all__'` (fallback for invalid slug)
- `buildUrlFromTab( '__all__', 'http://example.com/?page=…&tab=core', '__all__' )` → URL without `?tab=`
- `buildUrlFromTab( 'themes', 'http://example.com/?page=…', '__all__' )` → URL with `?tab=themes`, preserving `page` arg
- `buildUrlFromTab( 'blocks', 'http://example.com/?page=…&other=x', '__all__' )` → preserves `other`

**New file**: `tests/jest/ability-library/collectInScopeCategories.test.js`

Cover:

- Given items `[{category:'a',tabGroup:'core'}, {category:'b',tabGroup:'blocks'}, {category:'c',tabGroup:'core'}]` and `activeTab === ALL_TABS_KEY` → returns `['a','b','c']`.
- Same items with `activeTab === 'core'` → returns `['a','c']`.
- Same items with `activeTab === 'blocks'` → returns `['b']`.
- Same items with `activeTab === 'nonexistent'` → returns `[]`.
- Items with missing `category` field are skipped.

**New file**: `tests/jest/ability-library/buildBulkPatch.test.js`

Cover:

- `buildBulkPatch({}, ['a','b'], true)` → `{a: {enabled: true, mode: 'all', sub_keys: {}}, b: same}` (fresh defaults).
- `buildBulkPatch({a: {enabled: true, mode: 'specific', sub_keys: {'a/read': true}}}, ['a'], false)` → `a` becomes `{enabled: false, mode: 'specific', sub_keys: {'a/read': true}}` (mode + sub_keys preserved).
- `buildBulkPatch({a: {enabled: false, …}, b: {enabled: false, …}}, ['a'], true)` → `a` flips to enabled=true; `b` passes through UNCHANGED (out of scope). This is the key tab-scoping assertion.
- Empty `inScopeCategories` → returns a shallow copy of `currentConfig` (no-op).

**New file**: `tests/jest/ability-library/computeInScopeBulkState.test.js`

Cover:

- Empty `inScopeCategories` → `'all'`.
- All in-scope entries enabled → `'all'`.
- All in-scope entries disabled → `'none'`.
- Mixed → `'mixed'`.
- Missing entry defaults to enabled=true (matches sparse-storage) → treated as enabled.

**Extended file**: `tests/jest/ability-library/LibraryPage.test.js`

Cover integration paths:

- Header row renders TWO separate `<button>`s (not a dropdown/split-button). Primary label `Enable All`, secondary label `Disable All`.
- Clicking `Enable All` while `activeTab === ALL_TABS_KEY` calls `saveConfig` with EVERY registered category set to enabled=true.
- Clicking `Disable All` while `activeTab === 'core'` calls `saveConfig` where ONLY core-tab categories flip to enabled=false; every other category's entry passes through byte-for-byte.
- `mode` and `sub_keys` are preserved on both actions.
- When `inScopeBulkState === 'all'` the `Enable All` button is disabled; when `inScopeBulkState === 'none'` the `Disable All` button is disabled.
- On first paint at the default `All` tab, initial button-disabled state matches the localized `data.bulkToggleState`.

Follow the existing mock pattern (`jest.mock('@wordpress/components', …)`, etc.) established by `collectTabGroups.test.js`.

Note: `phpunit.xml.dist` is unrelated — Jest is discovered by wp-scripts automatically under `tests/jest/`.

---

## CHANGE-7 — PHPUnit tests

**File**: `tests/phpunit/Modules/Library/Test_Ability_Definition.php`

Add three test methods:

- `test_is_all_enabled_returns_true_for_empty_config()` — save an empty option; assert `Ability_Definition::is_all_enabled() === true`.
- `test_is_all_enabled_returns_false_when_any_entry_disabled()` — seed the option with `[ 'block' => [ 'enabled' => false, 'mode' => 'all', 'sub_keys' => [] ] ]`; assert `false`.
- `test_is_all_disabled_requires_every_registered_category_disabled()` — seed the option with `false` for every registered category (mock the Registry via a filter or use a test-double); assert `true`. Then remove one entry; assert `false`.
- `test_bulk_toggle_state_returns_all_when_empty()` — assert `'all'`.
- `test_bulk_toggle_state_returns_none_when_all_disabled()` — seed matching state; assert `'none'`.
- `test_bulk_toggle_state_returns_mixed_when_partial()` — seed one enabled + one disabled; assert `'mixed'`.

Follow the existing test conventions (WP_UnitTestCase, `setUp` seeds a clean option, `tearDown` restores).

Register the file in `phpunit.xml.dist` if it isn't already (check first; the file exists per Feature 041 tests).

---

## CHANGE-8 — Build

```
npm run build
```

Regenerates `build/js/ability-library.js` + `.asset.php` + `build/css/ability-library.css` (+ RTL). Commit the build artifacts along with the source.

---

## Quality-gate audits

Run all locally before commit:

```bash
composer phpstan                                                    # zero errors (level 8)
composer phpcs -- includes/Modules/Library/Ability_Definition.php \
                  admin/Main.php                                    # zero errors
composer test                                                       # 123+ passes (3 new PHPUnit cases)
npx wp-scripts test-unit-js tests/jest/ability-library/             # 3 new + updated cases pass
npm run build                                                       # clean compile
npm run validate-packages                                           # clean (no new deps)
```

Grep sanity:

```bash
grep -n "acrossaiAbilityLibraryData" admin/Main.php                 # 'bulkToggleState' present
grep -n "bulk_toggle_state\|is_all_enabled\|is_all_disabled" \
     includes/Modules/Library/Ability_Definition.php                # three methods present
grep -n "useLibraryTabSync" src/js/ability-library/                 # hook wired in LibraryPage
```

---

## Manual verification (quickstart)

On a wp-env fixture with release 0.0.6 active + the acrossai-core-abilities companion NOT installed:

1. Open `?page=acrossai-abilities-library`. Verify the header row shows TWO separate buttons side-by-side: a primary `Enable All` and a secondary `Disable All`. No dropdown arrow, no split-button.
2. On the default `All` tab, click `Enable All`. Verify every category card across every tab shows `Enabled`. Verify `wp_options.acrossai_library_config` is empty or missing (sparse-storage strips the all-default state). The `Enable All` button should now render disabled (no-op).
3. Still on `All`, click `Disable All`. Verify every category card across every tab shows `Disabled`. Verify `acrossai_library_config` contains an entry per registered category with `[enabled] => 0`, `[mode] => 'all'`, `[sub_keys] => []` (or the prior mode/sub_keys if previously customized). The `Disable All` button should now render disabled.
4. Switch to the `Core` tab. Manually toggle two Core categories to Enabled. Click `Disable All` on the Core tab. Verify: (a) both Core categories flip to Disabled, (b) NO non-Core category changed. Confirm with a DB query — non-Core entries should still show `[enabled] => 0` from step 3.
5. Still on `Core`, click `Enable All`. Verify only Core-tab categories flip to Enabled. Non-Core still disabled.
6. Switch to `Blocks` tab. Click `Enable All`. Verify only Blocks-tab categories flip. Repeat for a couple more tabs.
7. Switch back to `All`. Verify the tri-state hint is correct — since some tabs are enabled and others are disabled, both `Enable All` and `Disable All` should be clickable (mixed state).
8. Toggle one category card back to `Enabled` manually via its per-card ToggleControl. Refresh the page. Verify the mixed state persists.
9. Click through tabs (All → Core → Themes → Blocks → Users → Cache → File Manager → Cron → Database → Plugins). Verify the URL updates: `?tab=core`, `?tab=themes`, `?tab=blocks`, etc. Clicking `All` removes the `tab` query arg entirely.
10. Navigate directly to `?page=acrossai-abilities-library&tab=themes`. Verify the Themes tab opens on load, and the bulk buttons' scope reflects the Themes tab.
11. Navigate to `?page=acrossai-abilities-library&tab=nonexistent`. Verify graceful fallback to `All` (no error, no console warning).
12. Use browser back/forward buttons after switching tabs. Verify the active tab re-syncs to whatever the URL says on each navigation, and the bulk-button scope re-derives to match.

Regression checks:

13. Verify per-category ToggleControl still works (single-card enable/disable via `LibraryCard.js`).
14. Verify `mode='specific'` per-slug CheckboxControl still works.
15. Verify the empty-state ("Ability library is empty") still renders when `data.definitions` is empty.
16. Verify a manually-disabled card and a card disabled via bulk `Disable All` render identical DOM. Disabled cards show: (a) the ToggleControl + label, (b) the chevron (when the category has ≥1 ability), and (c) when the chevron is expanded, a readonly bullet-style ability list. Disabled cards MUST NOT show: the All/Specific radio, or any interactive `<CheckboxControl>` rows (even if stored `mode === 'specific'`). See the Disabled-Card UI Contract section below.

---

## Disabled-Card UI Contract (Feature 052 turn 2 — matches shipped code)

When a category card in `LibraryCard.js` has `enabled === false`, the rendered
DOM shows:

- **ToggleControl** (switch + category label) — always visible.
- **Chevron disclosure button** — visible when the category has ≥1 registered
  ability. Gated by `canExpand = slugs.length > 0` on `LibraryCard.js:70`. The
  `enabled &&` clause was intentionally REMOVED in turn 2 so the header row
  stays visually aligned between enabled and disabled cards. Do not re-add the
  `enabled &&` clause.
- **Ability-list panel** — visible when the chevron is expanded, regardless of
  enabled state. Gated by `{slugs.length > 0 && expanded && ( … )}` on
  `LibraryCard.js:143`. The `enabled &&` clause was REMOVED in turn 2.
- **Rows inside the panel** — rendered as READONLY bullet-style rows on a
  disabled card. Gated by the ternary `enabled && mode === 'specific' ? <Checkbox…/> : <div className="…__slug-readonly" />` on `LibraryCard.js:161`. The `enabled &&` prefix was ADDED in turn 2 so no interactive `<CheckboxControl>` renders on a disabled card — even when the stored `mode === 'specific'`. Do not loosen this predicate.

Hidden on disabled cards:

- **All / Specific radio** — gated by `{enabled && ( <RadioControl … /> )}` on
  `LibraryCard.js:107`. This gate MUST remain intact.

The stored `mode` and `sub_keys` are preserved through disable/enable cycles;
re-enabling a card that was in `specific` mode restores the interactive
checkbox rows with the prior selections.

Feature 052 adds bulk `Enable All` / `Disable All` actions. The disabled
visual state produced by bulk-disable MUST match the per-card manual-disable
state at every point in the render tree — header row AND the expanded readonly
panel below.

The `LibraryCard.test.js` extension asserts:

- Chevron predicate returns `true` on disabled + slugs > 0 (contrast with
  Feature 033's original `false`).
- Slug panel predicate returns `true` on disabled + slugs > 0 + expanded.
- Interactive-row predicate returns `false` when disabled, regardless of mode.
- Per-card-disabled and bulk-disabled produce identical predicate outputs
  across chevron / radio / slugPanel / interactive.

## What Must NOT Change

- **REST endpoints and controllers.** The bulk-toggle reuses the existing `POST /acrossai-abilities-library/v1/abilities/config` route. No new routes.
- **`AcrossAI_Ability_Library_Config` model shape.** Sparse storage semantics stay identical; `sanitize_entry()` untouched.
- **`Ability_Definition::__construct()` or `push_definition()` behavior.** No changes to how abilities land in the Registry.
- **`LibraryCard.js` per-slug toggle flow.** Existing ToggleControl / RadioControl / CheckboxControl behavior stays byte-for-byte identical.
- **`LibraryCard.js` disabled-card UI gating (as of Feature 052 turn 2).** The current shipped gates: `canExpand = slugs.length > 0` (line 70); `{enabled && ( <RadioControl … /> )}` (line 107 — this one MUST remain intact); `{slugs.length > 0 && expanded && ( … )}` outer panel (line 143); and the interactive-row ternary `enabled && mode === 'specific'` (line 161 — the `enabled &&` prefix MUST remain intact). See the "Disabled-Card UI Contract" section above for the complete matrix.
- **Tab-group data flow.** Runtime-derivation from `meta.acrossai.tab_group` stays.
- **PHP-side `?tab=` parsing.** `LibraryMenu.php::render()` continues to output a bare React container.
- **`window.acrossaiAbilityLibraryData` other keys.** Only add `bulkToggleState`; don't reorder or rename existing keys (`definitions`, `restBase`, `nonce`, `addonsUrl`).

---

## Expected Files Changed

```text
# New files
src/js/ability-library/hooks/useLibraryTabSync.js
tests/jest/ability-library/useLibraryTabSync.test.js
tests/jest/ability-library/collectInScopeCategories.test.js
tests/jest/ability-library/buildBulkPatch.test.js
tests/jest/ability-library/computeInScopeBulkState.test.js

# Modified files
src/js/ability-library/components/LibraryPage.js            (controlled tab + hook wire + header w/ two Buttons + tab-scoped bulk handlers + three named-export helpers)
src/scss/ability-library/admin.scss                         (or sibling — .acrossai-library-page__header block with gap for side-by-side buttons)
includes/Modules/Library/Ability_Definition.php             (3 static helpers + 1 private helper)
admin/Main.php                                              (bulkToggleState localized)
tests/jest/ability-library/LibraryPage.test.js              (extend with header-row + tab-scoping integration cases)
tests/phpunit/Modules/Library/Test_Ability_Definition.php   (6 new tests for the three helpers)
phpunit.xml.dist                                            (only if Test_Ability_Definition isn't already registered)

# Build artifacts (regenerated)
build/js/ability-library.js
build/js/ability-library.asset.php
build/css/ability-library.css
build/css/ability-library-rtl.css
```

---

## Validation Checklist

### Functional

- [ ] TWO separate buttons appear side-by-side on the right of a new header row above the TabPanel: primary `Enable All`, secondary `Disable All`. No dropdown, no split-button.
- [ ] Both buttons are always visible regardless of the current tab.
- [ ] On the `All` tab, `Enable All` sets EVERY registered category to `enabled: true` while preserving each category's `mode` and `sub_keys`.
- [ ] On the `All` tab, `Disable All` sets EVERY registered category to `enabled: false` while preserving each category's `mode` and `sub_keys`.
- [ ] On a specific tab (e.g. `Core`), `Enable All` flips ONLY categories with `tabGroup === activeTab` to enabled=true. Categories in other tabs pass through byte-for-byte unchanged.
- [ ] On a specific tab, `Disable All` flips ONLY categories with `tabGroup === activeTab` to enabled=false. Categories in other tabs pass through byte-for-byte unchanged.
- [ ] The persisted per-category shape after a bulk action is `{enabled: 0|1, mode: <preserved>, sub_keys: <preserved>}` — only the `enabled` boolean flips.
- [ ] Both operations persist via a single `POST /abilities/config`.
- [ ] `Enable All` renders disabled when every in-scope category is already enabled; `Disable All` renders disabled when every in-scope category is already disabled.
- [ ] `?tab=<slug>` in the URL initializes the correct tab on load.
- [ ] Clicking a tab pushes `?tab=<slug>` to the URL.
- [ ] Clicking "All" removes the `tab` query arg from the URL.
- [ ] Invalid `?tab=<x>` values fall back to "All" without error.
- [ ] Browser back/forward re-syncs the tab state.
- [ ] When the URL flips tabs (via back/forward or a fresh `?tab=…` load), the bulk-button scope re-derives to match the new tab.

### Backend

- [ ] `Ability_Definition::is_all_enabled()` returns true for empty config, false when any entry has `enabled=false`.
- [ ] `Ability_Definition::is_all_disabled()` requires an explicit `enabled=false` entry for every currently-registered category.
- [ ] `Ability_Definition::bulk_toggle_state()` returns `'all' | 'none' | 'mixed'` correctly.
- [ ] `window.acrossaiAbilityLibraryData.bulkToggleState` is populated on page load.

### Non-regression

- [ ] Per-category ToggleControl in `LibraryCard.js` still works.
- [ ] `mode='specific'` per-slug CheckboxControls still work.
- [ ] Sparse storage in `AcrossAI_Ability_Library_Config::save_config()` still strips all-default entries.
- [ ] Empty-state render still triggers when `data.definitions` is empty.
- [ ] Disabled-card UI contract (Feature 052 turn 2): a disabled card renders the ToggleControl + label + chevron (when the category has ≥1 ability). Chevron expansion reveals a READONLY bullet-style ability list. NEVER renders the All/Specific radio or any interactive `<CheckboxControl>` rows (even when stored `mode === 'specific'`). Asserted via Jest predicate tests in `LibraryCard.test.js`.
- [ ] Manual per-card disable and bulk `Disable All` produce identical card DOM at every point in the render tree — header row AND expanded readonly panel.

### Quality gates

- [ ] `composer phpstan` — zero errors (level 8).
- [ ] `composer phpcs -- includes/Modules/Library/Ability_Definition.php admin/Main.php` — zero errors.
- [ ] `composer test` — passes with 6 new PHPUnit cases.
- [ ] `npx wp-scripts test-unit-js tests/jest/ability-library/` — passes with new + extended cases.
- [ ] `npm run build` — clean compile.
- [ ] `npm run validate-packages` — clean.

---

## Follow-ups (out of scope for this feature)

- **Keyboard shortcut** (e.g. `Cmd-Shift-A` for Enable All in scope). WP admin doesn't currently ship a keybinding registry; deferred.
- **Confirmation modal** before `Disable All` runs. Skipped for now — the operation is instantly reversible via `Enable All` (mode/sub_keys preserved).
- **`?filter=` query param** for future filter controls beyond the tab. Not in scope.
- **Snapshot of the pre-bulk state** for one-click undo. Not requested; the tab-scoping limits blast radius, and `mode` + `sub_keys` preservation makes re-enable a lossless restore.

---

## Spec-kit Commands

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer run phpstan
composer run phpcs
npx wp-scripts test-unit-js tests/jest/ability-library/
npm run build

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

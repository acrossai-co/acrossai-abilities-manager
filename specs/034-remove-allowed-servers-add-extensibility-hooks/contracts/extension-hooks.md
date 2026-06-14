# Public Extension Contract — Form Hooks

**Status**: stable (this PR establishes the contract). Future renames require the FR-010 deprecation cycle (parallel emission of old + new names for ≥ 1 minor release before removal).

This document is the **authoritative public contract** consumed by external plugins (e.g., the future `acrossai-mcp-manager`). The names, signatures, payload shapes, firing cadence, and JS global variable name listed here are all part of the contract.

---

## Trust model

Extensions subscribing to these hooks execute inside the WordPress **admin trust boundary** — the same boundary as the host plugin itself. They run in the same PHP process (PHP hooks) and the same browser context (JS hooks) as `acrossai-abilities-manager`. A malicious or buggy extension can already do anything an installed WordPress plugin can do (modify the database, exfiltrate data, escalate privileges within WP) — these hooks do **not** expand that attack surface, but they also do **not** add any sandbox.

What this plugin **does NOT** and **will NOT** add at the hook callsites:

- Capability checks (e.g. `current_user_can( 'manage_options' )`) — capability gating belongs at the REST/page boundary upstream, where it already runs. Adding it at filter callsites would falsely imply isolation that does not exist.
- Nonce verification — same reason.
- Defensive coercion of subscriber return values (e.g. type-checking the array returned from `acrossai_abilities.form.extra_sections`) — broken subscribers are the subscriber's bug.
- Try/catch wrapping of action callbacks — WordPress hooks are not exception-isolated by design; subscribers that throw will surface as unhandled errors. This is consistent with every other `do_action` callsite in WordPress.

Site owners install extensions at their own risk, the same as any other WordPress plugin. The supply-chain exposure of this hook surface is identical to "I installed another WP plugin"; it is not greater because of the hook contract.

---

## React hooks (`@wordpress/hooks`)

External plugins call `wp.hooks.addFilter(...)` / `wp.hooks.addAction(...)` against these names on the JS side. From inside this plugin's bundle, imports come from `@wordpress/hooks`.

### Filter: `acrossai_abilities.form.extra_sections`

**Purpose**: Render extension-provided UI inside the ability edit form where the (now-removed) Allowed Servers block lived.

**Signature**:
```ts
applyFilters(
  'acrossai_abilities.form.extra_sections',
  sections: ReactNode[],          // initial value: []
  context: FormContext            // see below
): ReactNode[]
```

**Context object** (frozen — all four keys are public per FR-010):
```ts
interface FormContext {
  abilityId: string | null;       // ability id, or null when creating a new one
  slug: string | null;            // ability slug, or null when creating
  draft: Record<string, any>;     // entire current form draft
  isNonDb: boolean;               // true if registered in code only
}
```

**Subscriber usage**:
```js
wp.hooks.addFilter(
  'acrossai_abilities.form.extra_sections',
  'acrossai-mcp-manager/server-mapping',
  function ( sections, context ) {
    return [
      ...sections,
      wp.element.createElement( ServerMappingPanel, { abilityId: context.abilityId } ),
    ];
  }
);
```

**Default behavior with zero subscribers**: returns `[]`; no extra sections render. Layout identical to baseline.

**Guarantees**:
- Returned array values are rendered with React keys auto-assigned by index. Extensions returning React elements need not supply their own `key`.
- Filter is called on every form render. Subscribers MUST be pure with respect to `context`.
- Subscribers MUST return an array. Returning non-arrays is a subscriber bug; this plugin will not defensively coerce.

---

### Action: `acrossai_abilities.form.draft_changed`

**Purpose**: Notify extensions whenever the form's draft state updates (e.g., to mirror state, run validation, log activity).

**Signature**:
```ts
doAction( 'acrossai_abilities.form.draft_changed', draft: Record<string, any> )
```

**Firing cadence (contract)**: fires on every React commit where the `draft` state reference has changed. For typical text inputs, that means **per keystroke**; for grouped state updates batched in a single setState, that means once per batch. The plugin applies **no internal debouncing**. Debouncing is the subscriber's responsibility.

**Subscriber usage**:
```js
let scheduled = null;
wp.hooks.addAction(
  'acrossai_abilities.form.draft_changed',
  'acrossai-mcp-manager/mirror-state',
  function ( draft ) {
    clearTimeout( scheduled );
    scheduled = setTimeout( () => myStore.setDraft( draft ), 250 );
  }
);
```

**Default behavior with zero subscribers**: action call returns immediately with no observable effect.

**Guarantees**:
- The `draft` argument is the current draft object reference held by the form's React state. Subscribers MUST NOT mutate it. Treat as read-only.
- WordPress's hooks system does NOT isolate subscriber exceptions; a throwing subscriber will surface as an unhandled error in the form. Subscribers MUST handle their own failures.

---

### Filter: `acrossai_abilities.form.save_payload`

**Purpose**: Allow extensions to mutate or augment the REST request body immediately before save.

**Signature**:
```ts
applyFilters(
  'acrossai_abilities.form.save_payload',
  payload: Record<string, any>,   // base payload built by this plugin
  context: SaveContext            // see below
): Record<string, any>            // augmented payload, sent to REST as-is
```

**Context object**:
```ts
interface SaveContext {
  abilityId: string | null;
  slug: string | null;
  isNonDb: boolean;
}
```

**Subscriber usage**:
```js
wp.hooks.addFilter(
  'acrossai_abilities.form.save_payload',
  'acrossai-mcp-manager/attach-server-ids',
  function ( payload, context ) {
    return { ...payload, _mcp_servers: myStore.getSelectedServers( context.slug ) };
  }
);
```

**Default behavior with zero subscribers**: returns `payload` unchanged.

**Guarantees**:
- The filter runs synchronously before the REST POST. Async subscribers (returning a Promise) are NOT supported — the payload sent to the REST endpoint is whatever the filter returned synchronously.
- This plugin's REST controllers WILL reject malformed payloads. An extension that strips a required field is the extension's bug; this plugin's error handling is not extended to cover it.

---

## PHP hooks (WordPress core)

### Filter: `acrossai_abilities_admin_localize_data`

**Purpose**: Allow extensions to add keys to the localized JS data object that the admin React bundle reads on mount.

**Signature**:
```php
$data = apply_filters( 'acrossai_abilities_admin_localize_data', array $data );
```

**Read endpoint on the JS side**: `window.acrossaiAbilitiesManager` (this name is part of the contract per FR-010). Extension subscribers append keys, then read them as `window.acrossaiAbilitiesManager.theirKey` from JS.

**Subscriber usage**:
```php
add_filter( 'acrossai_abilities_admin_localize_data', function ( array $data ): array {
    $data['acrossai_mcp_manager'] = [
        'servers' => MyMcpManager\get_servers_for_current_user(),
    ];
    return $data;
} );
```

**Default behavior with zero subscribers**: returns `$data` unchanged.

**⚠️ Security — data-minimization (SEC-002)**:

Values added to `$data` are serialized by `wp_json_encode` and injected as a JavaScript global on the admin page. They become readable by **every** script running on that page, including:

- Other admin plugins installed on the same site.
- Browser extensions running as content scripts.
- Any XSS payload that survives WordPress's escaping (a moving target — assume a payload can read your data).

Subscribers MUST data-minimize. Specifically, do **NOT** add:

- API keys, OAuth tokens, or any credentials (even short-lived).
- Hashed credentials, password hashes, or salts.
- Personally identifiable information beyond what is strictly required for UI rendering (e.g., display name is fine; full address or phone number is not).
- Database-internal IDs that the user shouldn't enumerate (e.g., raw `user_id` from another user, internal numeric primary keys that don't appear in the URL anyway).
- Bulk data that could be queried lazily on demand (e.g., the entire list of 10,000 abilities — fetch via REST when needed instead).

If you must expose something sensitive, expose it via an authenticated REST endpoint instead and have the React component fetch it on mount.

**Guarantees**:
- Filter fires inside `admin/Main::enqueue_scripts()` once per admin page load that includes the abilities bundle.
- The data is JSON-encoded via `wp_json_encode` and injected via `wp_add_inline_script( $handle, 'window.acrossaiAbilitiesManager = ...', 'before' )` — so it is available to the bundle synchronously before any module-level code runs.
- Extensions MUST namespace their keys (e.g., prefix with the extension's slug) to avoid collisions. Future versions of this plugin reserve the right to add new keys; collisions with extension-added keys would be the extension's responsibility to resolve.
- The base array shape this plugin owns (existing keys — see **Reserved keys** under `window.acrossaiAbilitiesManager` below) is governed by separate contracts — do NOT modify those keys, only add new ones.

---

### Action: `acrossai_abilities_form_settings_registered`

**Purpose**: Signal that the abilities admin bundle has been enqueued and its localize data has been injected. Extensions hook here to enqueue their own dependent bundles.

**Signature**:
```php
do_action( 'acrossai_abilities_form_settings_registered' );  // no arguments
```

**Subscriber usage**:
```php
add_action( 'acrossai_abilities_form_settings_registered', function (): void {
    wp_enqueue_script(
        'acrossai-mcp-manager/abilities-extension',
        plugins_url( 'build/abilities-extension.js', __FILE__ ),
        [ 'wp-hooks', 'wp-element' ],
        '1.0.0',
        true
    );
} );
```

**Default behavior with zero subscribers**: action returns immediately with no observable effect.

**Guarantees**:
- Action fires AFTER `wp_enqueue_script` for the abilities admin bundle and AFTER `wp_add_inline_script` for `window.acrossaiAbilitiesManager`. Extensions enqueueing here can rely on both being already wired.
- Action fires exactly once per admin page load that includes the abilities bundle. It does NOT fire on non-abilities admin pages.

---

## JS global variable: `window.acrossaiAbilitiesManager`

**Status**: public contract per FR-010.

This window-level global is the read endpoint for any data injected by `acrossai_abilities_admin_localize_data` subscribers (and for this plugin's own existing keys).

**Reserved keys** (owned by `acrossai-abilities-manager` as of Feature 034; subject to additive evolution — extensions MUST NOT use these names):

| Key | Type | Purpose |
|---|---|---|
| `nonce` | `string` | WordPress REST nonce for the current user (use `X-WP-Nonce` header). |
| `rest_url` | `string` | Untrailingslashited site REST root (e.g. `https://example.com/wp-json`). |
| `rest_namespace` | `string` | This plugin's REST namespace prefix (`acrossai-abilities-manager/v1`). |
| `current_user_id` | `number` | The currently logged-in WP user ID (rendering hint only — never trust client-side). |
| `perPage` | `number` | Page size for the abilities list (per `DEC-ABILITIES-LIST-UX-025`). |
| `access_control_available` | `boolean` | Whether the `wpb-access-control` library is loaded — used as a client rendering gate only. Server authorization is independently enforced by `wpb-ac/v1` REST endpoints (SEC-018-02). |
| `protected_slugs` | `string[]` | Ability slugs that cannot be deleted (per `DEC-PROTECTED-SLUGS-PATTERN`). |

The set may grow in future minor releases without notice (additive only, per the contract change procedure below). Extensions MUST avoid collisions by:

1. **Prefixing** their keys with their plugin slug or namespace (e.g. `acrossai_mcp_manager_servers`, `my_extension_state`).
2. Using a single namespaced object key rather than many top-level keys, e.g. `acrossai_mcp_manager: { servers: [...], settings: {...} }` over `acrossai_mcp_manager_servers: [...]` + `acrossai_mcp_manager_settings: {...}`. This keeps the global flat namespace clean.
3. NOT relying on iteration order of `Object.keys( window.acrossaiAbilitiesManager )` — additions may appear anywhere.

If a new reserved key happens to collide with a name an extension has already shipped, the extension MUST rename theirs. The reserved-key list is governed by the FR-010 contract; extension-chosen names are not.

**Read pattern from extensions**:
```js
const myData = window.acrossaiAbilitiesManager?.[ 'my-extension-key' ] ?? {};
```

Use optional chaining and a default — extensions cannot assume their key is present (e.g., if a sibling extension prevented their `add_filter` from firing).

---

## Contract change procedure

If this contract needs to change after shipping:

1. **Add new** (hook name, context key, or window key): non-breaking. Ship in any release.
2. **Rename or remove**: must follow FR-010 deprecation cycle —
   - Continue firing the old name in parallel with the new for ≥ 1 minor release.
   - For action `*_form_settings_registered` and filter `*_admin_localize_data`, this means literally calling `do_action`/`apply_filters` for both names with the same payload.
   - For React hooks, call `applyFilters`/`doAction` for both names.
   - Document the deprecation in the release notes and in `docs/memory/DECISIONS.md`.
3. **Change semantics without renaming**: prefer adding a new hook with the new semantics and deprecating the old, rather than redefining the existing one.

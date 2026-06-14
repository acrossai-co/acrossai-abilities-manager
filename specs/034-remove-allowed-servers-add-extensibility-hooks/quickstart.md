# Quickstart: Smoke-Test the New Extension Hooks

This is the manual verification path for the five new extension hooks (FR-014: no automated test for hook pass-through). Drop the MU plugin below into `wp-content/mu-plugins/test-abilities-hooks.php`, load any ability edit page, and verify the checklist.

## MU plugin snippet

```php
<?php
/**
 * Plugin Name: Test — AcrossAI Abilities Hooks
 * Description: Smoke-tests the 5 extension hooks added in Feature 034.
 *              Drop this file into wp-content/mu-plugins/ and load an ability edit page.
 *              Delete the file to unsubscribe.
 */

// PHP hook 1: localize-data filter — inject a probe key.
add_filter( 'acrossai_abilities_admin_localize_data', function ( array $data ): array {
    $data['_test_probe'] = 'hello from test MU plugin';
    return $data;
} );

// PHP hook 2: page-load action — record that it fired.
add_action( 'acrossai_abilities_form_settings_registered', function (): void {
    update_option( '_test_abilities_hook_fired', current_time( 'mysql' ) );
} );

// React hooks: register via inline JS attached to the abilities admin bundle.
add_action( 'admin_enqueue_scripts', function (): void {
    if ( ! wp_script_is( 'acrossai-abilities-admin', 'enqueued' ) ) {
        return;
    }
    wp_add_inline_script( 'acrossai-abilities-admin', <<<'JS'
( function () {
    if ( ! window.wp || ! window.wp.hooks || ! window.wp.element ) { return; }
    var hooks = window.wp.hooks;
    var el    = window.wp.element.createElement;

    // JS hook 1: extra_sections filter — inject a custom panel.
    hooks.addFilter(
        'acrossai_abilities.form.extra_sections',
        'test/extra-section',
        function ( sections, context ) {
            var probe = window.acrossaiAbilitiesManager
                && window.acrossaiAbilitiesManager._test_probe
                || '(no probe)';
            return sections.concat( [
                el( 'div', {
                    id: 'test-probe-panel',
                    style: { padding: '8px', background: '#ffeecc', border: '1px solid #cc9' },
                },
                    'HELLO from test MU plugin. ',
                    'Ability slug: ' + ( context.slug || '(new)' ) + '. ',
                    'isNonDb: ' + String( context.isNonDb ) + '. ',
                    'Localize probe: ' + probe
                ),
            ] );
        }
    );

    // JS hook 2: draft_changed action — log every draft update.
    hooks.addAction(
        'acrossai_abilities.form.draft_changed',
        'test/draft-log',
        function ( draft ) {
            // eslint-disable-next-line no-console
            console.log( '[test draft_changed]', draft );
        }
    );

    // JS hook 3: save_payload filter — attach a probe to outbound saves.
    hooks.addFilter(
        'acrossai_abilities.form.save_payload',
        'test/save-probe',
        function ( payload, context ) {
            return Object.assign( {}, payload, { _test_save_probe: true, _test_ctx: context } );
        }
    );
}() );
JS
    );
}, 100 );
```

## Verification checklist

Run with the MU plugin enabled, then again with it disabled.

### With the MU plugin enabled

1. **PHP `acrossai_abilities_admin_localize_data` filter**
   - Open any ability edit page (e.g., `wp-admin/admin.php?page=acrossai-abilities&action=edit&id=…`).
   - Open the browser DevTools console and run: `window.acrossaiAbilitiesManager._test_probe`.
   - **Expect**: `'hello from test MU plugin'`.

2. **PHP `acrossai_abilities_form_settings_registered` action**
   - After loading the abilities admin page, run `wp option get _test_abilities_hook_fired` from the project root, or check the option in the DB.
   - **Expect**: a recent timestamp string.

3. **JS `acrossai_abilities.form.extra_sections` filter**
   - Look for a yellow-background panel inside the ability edit form (id `#test-probe-panel`).
   - **Expect**: panel rendered with text including the ability slug, `isNonDb` value, and the localize probe value (verifying the localize+JS path roundtrips end-to-end).

4. **JS `acrossai_abilities.form.draft_changed` action**
   - With DevTools console open, type into any form field (e.g., the label input).
   - **Expect**: a `[test draft_changed] {...}` log line on every keystroke (fires on every React commit per FR-005 contract).

5. **JS `acrossai_abilities.form.save_payload` filter**
   - Open the Network tab. Save the ability (any change).
   - Inspect the outbound `PUT` / `POST` to `/wp-json/acrossai-abilities/v1/abilities/...`.
   - **Expect**: request body contains `_test_save_probe: true` and `_test_ctx: { abilityId, slug, isNonDb }`.

6. **No console errors, no PHP notices** (with `WP_DEBUG` + `WP_DEBUG_LOG` on).

### With the MU plugin disabled (delete the file)

7. **Test panel disappears**. No `#test-probe-panel` div renders.
8. **No errors**. The form renders identically to a baseline where the hooks were never invoked (FR-009).
9. **Save flow**. Save an ability. Network request body does NOT contain `_test_save_probe` / `_test_ctx` / any test key.
10. **No "Allowed Servers" UI** anywhere in the form. The removal is permanent regardless of whether extensions are subscribed.

### Schema verification (no migration shipped — see FR-011 / FR-012)

11. **Fresh install**: `wp db query "DESCRIBE {prefix}_acrossai_abilities"` shows no `mcp_servers` column ever existed.
12. **Dev install with stale data** (manual cleanup, NOT performed by plugin code):
    - `wp db query "DROP TABLE {prefix}_acrossai_abilities"` — drop the table manually.
    - Deactivate + reactivate the plugin (or just load any admin page that triggers BerlinDB `maybe_upgrade()`).
    - `wp db query "DESCRIBE {prefix}_acrossai_abilities"` confirms the table was recreated WITHOUT `mcp_servers`.
13. **REST silent-acceptance**: `wp eval "var_dump( wp_remote_post( rest_url('acrossai-abilities/v1/abilities'), [ 'headers' => [ 'X-WP-Nonce' => '...' ], 'body' => json_encode( [ 'slug' => 'x', 'mcp_servers' => ['a'] ] ) ] ) );"` returns 2xx; the response body contains no `mcp_servers` field.

## Rollback

The MU plugin is the only added artifact. Delete `wp-content/mu-plugins/test-abilities-hooks.php` to remove all test subscriptions; the abilities plugin returns to baseline behavior immediately. The `_test_abilities_hook_fired` option may be left behind harmlessly or deleted with `wp option delete _test_abilities_hook_fired`.

No migration code is shipped, so there is no rollback path needed for schema changes — if a dev needs the old column back, they restore from a DB backup or revert the plugin to a prior commit.

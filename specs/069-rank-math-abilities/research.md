# Research: Rank Math Ability Suite

**Feature**: 069-rank-math-abilities
**Created**: 2026-08-17

All findings below were confirmed by reading the Rank Math source in
`wp-content/plugins/seo-by-rank-math/`, not inferred from documentation. Each drives a concrete
architectural decision; do not "simplify" the design away from them without re-verifying.

Paths are relative to `wp-content/plugins/seo-by-rank-math/`.

---

## F1 — Partial settings writes are safe

`Option_Center::get_changed_settings()` at `includes/admin/class-option-center.php:557`:

```php
private static function get_changed_settings( $current_settings, $new_settings ) {
    $new_settings     = array_filter( $new_settings, [ __CLASS__, 'is_valid_key' ], ARRAY_FILTER_USE_KEY );
    $current_settings = array_filter( $current_settings, [ __CLASS__, 'is_valid_key' ], ARRAY_FILTER_USE_KEY );

    // Merge current settings with new settings, new settings take precedence.
    return array_merge( $current_settings, $new_settings );
}
```

Despite the name it is a merge, not a diff. `Helper::update_all_settings()` then writes the merged
array wholesale.

**Decision**: `Settings_Writer::save()` submits only the fields the caller intends to change. No
read-modify-write, no race window. This is the single most important finding — it is what makes
typed per-panel partial writes viable.

**Verification owed**: integration check #1 in `quickstart.md` (write one field, assert every other
key in `rank-math-options-general` is byte-identical).

---

## F2 — No server-side type source, a lossy default, and two different type vocabularies

Three facts compound here. All verified against Rank Math 1.0.276.

### F2a — there is nothing on the server to ask

`Sanitize_Settings::sanitize()` at `includes/admin/class-sanitize-settings.php:33`:

```php
$type = $field_types[ $field_id ] ?? 'text';
```

The CMB2 field definitions carrying the real per-field `type` exist **only** inside
`CMB2_Options::register_option_page()` (`includes/admin/class-cmb2-options.php:139-190`). That path is
dead code by default: `Helper::is_react_enabled()` returns
`apply_filters( 'rank_math/is_react_enabled', true )` (`includes/helpers/class-conditional.php:264`),
and in React mode `Register_Options_Page` instantiates `RankMath\Admin\Options`, which never
`include`s `includes/settings/{general,titles,sitemap}/*.php`. The live admin UI builds its
`fieldTypes` map client-side in JavaScript.

### F2b — the highest-risk fields are protected by ID, not by type

`sanitize_field()` calls `sanitize_by_field_id()` **first** (`:49`), which short-circuits four field
groups regardless of the type passed and returns `null` to fall through for everything else:

| Field(s) | Handler | Newlines |
|---|---|---|
| `robots_txt_content` | `sanitize_robots_text()` → `wp_strip_all_tags()` (`:186`) | preserved |
| `google_verify`, `bing_verify`, `baidu_verify`, `yandex_verify`, `pinterest_verify`, `norton_verify` | `sanitize_webmaster_tags()` (`:200`) | n/a — scalar |
| `custom_webmaster_tags` | `sanitize_custom_webmaster_tags()` → `wp_kses()` allowing `<meta>` (`:218`) | preserved |
| `console_caching_control` | `sanitize_cache_control()` | n/a |

An earlier draft of this document named `robots_txt_content` and `custom_webmaster_tags` as the
data-loss examples. **That was wrong** — both are ID-protected. The conclusion still holds, but the
risk lives elsewhere.

### F2c — the sanitizer speaks React type names, the definitions speak legacy CMB2 names

The `switch` in `sanitize_field()` (`:56`) has cases `text`, `textarea`, `toggle`, `checkbox`,
`checkboxlist`, `select`, `selectSearch`, `selectVariable`, `searchPage`, `toggleGroup`, `number`,
`file`, `group`, `repeatableGroup` — the names the **React** layer sends. The definitions in
`includes/settings/**` use **legacy CMB2** names. Across all settings sources there are 19 distinct
legacy types and 11 match no case:

| Legacy type | Count | Case? | Falls to |
|---|---|---|---|
| `text`, `toggle`, `select`, `textarea`, `number`, `file`, `group` | 149 | yes | correct handler |
| **`textarea_small`** | **16** | **no** | `default` → **newlines stripped** |
| `radio_inline` | 14 | no | `default` — scalar, benign |
| `notice` | 13 | no | display-only, never writable |
| `multicheck` | 11 | no | `default` via `map_deep` — array, benign |
| `raw` | 7 | no | display-only, never writable |
| `advanced_robots` | 6 | no | `default` |
| `text_url` | 4 | no | `default` — scalar |
| `switch` | 3 | no | `default` |
| `password` | 2 | no | `default` |
| `radio`, `multicheck_inline`, `address` | 3 | no | `default` |

`default` → `sanitize_default_value()` (`:99`) → `sanitize_text_field()` for strings, which collapses
`[\r\n\t ]+` to a single space.

**The genuine data-loss set** is multi-line fields typed `textarea_small` with no ID override:
`nofollow_domains` and `nofollow_exclude_domains` (`includes/settings/general/links.php:77`, `:91`),
`rss_before_content` and `rss_after_content` (`includes/settings/general/others.php:112`, `:121`), and
`pt_{$type}_image_customfields` (`includes/modules/sitemap/settings/post-types.php:57`). Passing the
legacy name through verbatim destroys every one of them.

**Decision**: ship `Settings_Registry` holding our own declarative field-spec table — one entry per
field, citing the Rank Math source file and line it mirrors — and emit **the sanitizer's vocabulary**,
never the legacy names. The legacy→sanitizer map is part of the registry:

| Legacy | Emitted |
|---|---|
| `textarea_small` | `textarea` |
| `multicheck`, `multicheck_inline` | `checkboxlist` |
| `radio`, `radio_inline` | `select` |
| `switch` | `toggle` |
| `text_url`, `password` | `text` |
| `address` | `group` |
| `notice`, `raw` | read-only — rejected on write |

`Settings_Writer` refuses any field absent from the registry, so "forgot to type a field" is
impossible by construction: the write is rejected rather than mis-sanitized.

**Verification owed**: unit test asserting `field_types_for('general-links')` maps `nofollow_domains`
to `textarea` and never to `textarea_small`; unit test asserting `notice`/`raw` fields are read-only;
integration check #2 exercising `nofollow_domains` (unprotected) rather than `robots_txt_content`
(ID-protected).

---

## F3 — `apply_filters('rank_math/tools/{action}')` does not fire outside a `/toolsAction` request

`Database_Tools::hooks()` at `includes/modules/database-tools/class-database-tools.php:45`:

```php
public function hooks() {
    if ( Helper::is_rest() && Str::contains( 'toolsAction', add_query_arg( [] ) ) ) {
        foreach ( $this->get_tools() as $id => $tool ) {
            if ( ! method_exists( $this, $id ) ) {
                continue;
            }
            add_filter( 'rank_math/tools/' . $id, [ $this, $id ] );
        }
    }
}
```

The constructor additionally early-returns when `! Helper::is_advanced_mode()`. So from an ability
context the filter has no listener and `apply_filters()` returns its literal default,
`'Something went wrong.'`.

The three `analytics_*` tools *are* registered unconditionally at
`includes/modules/analytics/class-analytics-common.php:63-65`, but for uniformity we dispatch those
directly too.

**Decision**: `Maintenance_Tools` holds a **static dispatch map to concrete `[class, method]`
pairs**. Never `apply_filters`.

Secondary facts:
- Handler return shapes are inconsistent: either a plain `string`, or
  `['status' => 'error', 'message' => …]`. Normalize.
- `get_tools()` is `private static`; use the public `Database_Tools::get_json_data()` at `:61` for the
  catalogue.
- `get_tools()` is module- and entitlement-conditional, so a static input enum can list a tool that
  is not currently runnable. Validate at runtime and return `tool_unavailable` naming the missing
  module.

**Verification owed**: integration check #3 (call `clear_transients` outside a `/toolsAction`
request, assert a real count comes back).

---

## F4 — `Headless::get_head()` corrupts the live request

`includes/rest/class-headless.php:117`:

```php
private function setup_post_head( $url ) {
    $_SERVER['REQUEST_URI'] = $this->generate_request_uri( $url );
    remove_all_actions( 'wp' );
    remove_all_actions( 'parse_request' );
    wp();
    ...
    header( 'Content-Type: application/json; charset=UTF-8' );
    rank_math()->variables->setup();
    rank_math()->manager->load_modules();
    new Frontend();
}
```

It mutates `$_SERVER`, removes core hooks, re-runs the main query, emits a header, and reloads every
module. An MCP client batching two ability calls into one PHP request would get a corrupted second
call.

**Decision**: `Get_Rendered_Head` performs an **HTTP loopback** via `wp_remote_get()` against
`/wp-json/rankmath/v1/getHead?url=…`. That route's `permission_callback` is `'__return_true'`, so no
auth plumbing is needed. The route is only registered when `general.headless_support` is truthy
(`includes/settings/general/others.php:16`), so report that as a precondition error
(`headless_support_disabled`) rather than a generic failure.

**Verification owed**: integration check #4 (call it, then call a second ability in the same request).

---

## F5 — `Redirections\Export::export()` is unusable and its formatters are private

`includes/modules/redirections/class-export.php:38` reads `Param::get('export')` from `$_GET`, calls
`check_admin_referer()`, emits five `header()` calls, `echo`s the body, and `exit`s.

Every formatter is `private`: `apache()`, `nginx()`, `apache_item()`, `nginx_item()`,
`is_valid_regex()`, `normalize_nginx_redirect()`, `get_comparison()`, `encode2nd()`, `encode_regex()`.

**Decision**: port ~130 lines into `Redirections_Repository::to_apache()` / `::to_nginx()` (same
GPL-2.0-or-later license), each helper carrying an `@see` back to the private original with its line
number. Reflection was rejected — it would break on any Rank Math refactor with no compile-time
signal.

Behaviour to preserve exactly: a source whose regex fails `is_valid_regex()` is emitted **commented
out with `# `** in Apache output and **omitted entirely** from Nginx output. Surface these in a
`warnings[]` array and mark the payload `format_parity: 'ported'`.

**Verification owed**: table-driven unit tests over exact/contains/start/end/regex, `410` → `- [G]`,
`301` → `permanent` vs `redirect`, query-string sources emitting a `RewriteCond`.

---

## F6 — Import/export wrappers are superglobal-bound; the useful methods are not

`Import_Export_Settings::export()` reads `Param::post('panels', …)`; `::import()` calls
`Helper::handle_file_upload()` ($_FILES). Both are unusable from an ability.

But `::get_export_data( array $panels = [] )` at `:207` and
`::do_import_data( array $data, $suppress_hooks = false )` at `:81` are both `public static`.

**Decision**: call those two directly; skip the wrappers. `Export_Settings` takes a `panels` array;
`Import_Settings` takes a `data` object rather than a file upload.

Note `do_import_data()` calls `Backup::create_backup()` first, so import is self-protecting — but
`set_options()` does `update_option( $key, $data[$key] )` with **no merge**, so it overwrites whole
option blobs. `Import_Settings` must therefore be `destructive: true`, require `confirm`, and return
the created backup key so a caller can undo.

---

## F7 — "PRO gating" is mostly entitlement gating

Content AI (`includes/modules/content-ai/`) and AI Visibility (`includes/modules/ai-visibility/`)
both ship in **free core**. They gate on Rank Math *cloud account registration plus credit balance*
(`Helper::get_credits()` at `includes/helpers/class-content-ai.php:137`;
`Api/class-base-controller.php:41` → `Rest_Helper::can_manage_options()` then a `remote_request()`),
**not** on `defined('RANK_MATH_PRO_VERSION')`.

**Decision**: `Rank_Math_Guard` exposes three distinct gate flavours rather than one "is PRO" check:

| Guard | Checks |
|---|---|
| `assert_pro()` | `defined('RANK_MATH_PRO_VERSION') \|\| class_exists('\RankMathPro\Plugin')` |
| `assert_account()` | `! empty( Admin_Helper::get_registration_data() )` |
| `assert_credits( int $min = 1 )` | `Helper::get_credits() >= $min` |

Per the approved plan these abilities are **registered unconditionally** whenever Rank Math is
present and gated at runtime — deliberately unlike `register_elementor_pro_abilities()`, which simply
does not register when Elementor Pro is absent.

---

## F8 — Rank Math normalises legacy toggle representations on any save

Discovered while running integration check 1 against Rank Math 1.0.276, and isolated so our own code
was not involved in the write at all.

`Option_Center::save_settings()` reads the current blob with `Helper::get_settings( $type )`
(`includes/admin/class-option-center.php:406`), merges the submitted fields over it
(`get_changed_settings()`, F1), and persists the whole merged array with
`Helper::update_all_settings()`.

`Helper::get_settings()` delegates to `rank_math()->settings->get()`
(`includes/helpers/class-api.php:70`), which **casts** toggle values: a stored `'off'` reads back as
`false`, `'on'` as `true`. Because the cast values are what gets merged and written, one save
rewrites every legacy string toggle in that option blob to its boolean form.

Isolation test — seed two toggles as legacy strings directly via `update_option()`, then save an
**unrelated** field through **Rank Math's own** API:

```
seeded:  strip_category_base = 'off'    nofollow_image_links = 'on'
read as: strip_category_base = false    nofollow_image_links = true
after Rank Math's own save of breadcrumbs_prefix:
         strip_category_base = false    nofollow_image_links = true   <-- rewritten
```

**This is Rank Math's behaviour, not ours.** Clicking Save on any Rank Math settings screen does the
same thing. It is:

- **one-time** — a second save changes nothing (verified: 0 fields differ on a no-op save once the
  blob is normalised);
- **not data loss** — every consumer reads through `Helper::get_settings()`, which returns the same
  value either way. `'off'` and `false` are indistinguishable downstream.

**Consequence**: the original SC-002, "writing a single settings field leaves every other key in that
option blob byte-identical", is **not achievable** on a site whose blob still holds legacy string
toggles, through any code path including Rank Math's own UI. SC-002 is restated as: no *other*
setting's effective value changes, and any byte-level difference is limited to Rank Math's own
one-time boolean normalisation of toggle fields.

**Verification owed**: integration check 1 asserts effective-value equality via
`Helper::get_settings()` rather than raw `wp_options` equality, and separately asserts that a second
identical write is a true no-op.

---

## Secondary findings

| Finding | Source | Consequence |
|---|---|---|
| `Capability_Manager` accessor is `::get()`, not `::instance()`, and registers **16** caps | `includes/modules/role-manager/class-capability-manager.php:39`, `:55-71` | Correct accessor in `Role_Capability_Repository`; the curated cap list is 16 entries |
| `Helper::set_capabilities()` removes every registered cap absent from the payload, for **all** roles | `includes/helpers/class-wordpress.php:219` | A bulk role-cap writer is a footgun. **Not shipped** — the plugin's existing `acrossai/add-role-capability` / `remove-role-capability` write one cap at a time and cannot trigger the strip |
| `Stats::get()` derives its date range from a **browser cookie** (`get_date_from_cookie('date_range','-30 days')`) | `includes/modules/analytics/class-stats.php:81` | Abilities have no cookie. `Analytics_Repository` must call `set_date_range()` explicitly, immediately before use, and must never cache the instance |
| Analytics callbacks have **mixed signatures** | `Summary::get_posts_summary($post_type)` vs `Keywords::get_keywords_rows(WP_REST_Request)` | `Analytics_Repository` synthesizes a `WP_REST_Request` for the request-taking methods |
| `URL_Inspection::get_inspections()` returns **`null`** (bare `return;`) when `rank_math_analytics_inspections` is missing | `includes/modules/analytics/class-url-inspection.php:90-95` | `Get_Index_Status` maps this to `inspections_table_missing` with a hint pointing at `run-maintenance-tool(tool=recreate_tables)` |
| `Cache_Watcher::clear()` only **queues** work, flushed on `shutdown` via `clear_queued()` | `includes/modules/sitemap/class-cache-watcher.php:284`, `:75` | `Invalidate_Sitemap_Cache` uses `Cache::invalidate_storage()` for the immediate path and calls `clear_queued()` explicitly for the post-scoped path |
| `save_settings()` strips six protected keys, but `maybe_update_htaccess()` runs **before** the strip | `includes/admin/class-option-center.php:388-400`, `:590-615` | `Settings_Registry::DENIED_KEYS` hard-denies `htaccess_allow_editing`, `htaccess_content`, `searchConsole`, `analyticsData`, `analytics`, `usage_tracking`, and `Settings_Writer` strips them again before the call |
| `check_updated_fields( $updated, $is_reset )` does `in_array( $field_id, $updated, true )` | `includes/admin/class-option-center.php:526-539` | Passing `null` is a PHP 8 `TypeError`. Always pass `array_keys( $validated )` — which is also correct for the 12 `rank_math/flush_fields` rewrite-flush triggers |
| `Sanitize_Settings::sanitize_group_value()` distinguishes repeatable from single by `array_keys($v) === range(0, count($v)-1)` | `includes/admin/class-sanitize-settings.php:306` | A gapped or string-keyed JSON payload silently collapses `opening_hours` / `phone_numbers` / `additional_info` / `404_monitor_exclude` into one row. `Settings_Registry::validate()` must `array_values()`-reindex every group |
| `Post_Rest::save_column()` allows exactly `focus_keyword, title, description, image_alt, image_title`; `update_bulk_meta()` silently skips rows and always returns `['success' => true]` | `includes/rest/class-post.php:159`, `:39` | `Bulk_Update_Meta` pre-computes `processed[]` / `skipped[]` with reasons instead of trusting the return |
| `Instant_Indexing::THROTTLE_LIMIT = 5` lives in `submit_url()`, **not** `Api::submit()` | `includes/modules/instant-indexing/class-instant-indexing.php:63`, `:416` | Only auto-submit-on-save is throttled. `Submit_Urls` calling `Api::submit()` is not throttled |
| `Api::submit()` returns `bool`; error detail is in `get_error()` / `get_response_code()` | `includes/modules/instant-indexing/class-api.php:116`, `:363-370` | Map HTTP codes to `indexnow_400` / `_403_invalid_key` / `_422` / `_429_rate_limited` / `_500` |
| `Api::get_key_location()` **computes** the key URL from `home_url()` | `includes/modules/instant-indexing/class-api.php:239` | `indexnow_api_key_location` is derived, therefore read-only. Writable set is `bing_post_types` + `indexnow_api_key` |
| `Admin_Rest::save_module()` also calls `maybe_delete_rewrite_rules()` and fires `rank_math/module_changed` | `includes/rest/class-admin.php:130-141` | Skipping these leaves stale rewrite rules — the bug class that `get-rewrite-status` / `refresh-llms-route` exist to paper over. `Set_Module_State` replicates both; `maybe_delete_rewrite_rules()` is private so mirror it as `Module_Repository::maybe_flush_rewrite()` |
| `Redirection::from()` + `is_infinite_loop()` produce two distinct outcomes | `includes/rest/class-admin.php:250-285` | New redirection → saved but auto-deactivated (`infinite_loop_new`); existing → refused (`infinite_loop_update`). Surface both, don't collapse them |
| `DB::get_redirections()` supports `status` = `all\|active\|inactive\|trashed` | `includes/modules/redirections/class-db.php:68` | `List_Redirections` must expose the filter, otherwise `Delete_Trashed_Redirections` has no discovery path |
| `Local_Seo::get_opening_hours()` keys by `time` and skips rows with an empty `time` | `includes/modules/local-seo/class-local-seo.php:294` | `Settings_Registry::validate()` rejects an empty `time` in the `opening_hours` group rather than letting the row silently vanish |
| Rank Math's own abilities use `mcp => ['public' => true]` | `includes/abilities/class-abilities.php:88` | Ours use `['public' => false, 'type' => 'tool']`, matching the house convention in `includes/Abilities/Elementor/` |

---

## Host-plugin facts (this repository)

| Finding | Source | Consequence |
|---|---|---|
| PHPStan level 8 baseline is **clean**, and `\Elementor\Plugin::$instance` is referenced directly with no `ignoreErrors` entry | `includes/Abilities/Utilities/Elementor/Document_Repository.php:259`; `phpstan.neon.dist` has no unknown-class ignore | **No `phpstan.neon.dist` change is required** for `\RankMath\*` references. Batch 0's conditional edit is resolved as "not needed" |
| `php` is not on `PATH` in this environment | Local (Flywheel) bundles PHP | Run tooling with `PATH="$HOME/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin/bin:$PATH"` |
| `ACROSSAI_ABILITIES_MANAGER_VERSION` was `'0.0.21'` while the header read `0.0.27` | `includes/Main.php:194` | Fixed in an isolated commit ahead of this feature (`8cb5f18`) |
| The plugin already ships generic raw-option abilities | `acrossai/get-option`, `update-option`, `list-options`, `search-options`, `patch-option-value`, `get-nested-option-value`, `delete-option` | Decisive argument for **not** adding Rank Math-specific raw option abilities: the escape hatch already exists, so the Rank Math surface only offers the typed path |
| `acrossai/add-role-capability` / `remove-role-capability` already grant/revoke any WP capability | `includes/Abilities/Users/` | `rank_math_*` caps are WP caps, so no bulk Rank Math role-cap writer is needed |
| `acrossai/list-rewrite-rules` already returns the full persisted rewrite map | `includes/Abilities/Settings/List_Rewrite_Rules.php` | `rank-math-get-rewrite-status` dropped as redundant |
| `find-internal-links` parses **outbound** `<a href>` per post; `audit-internal-links` finds broken same-site links | `includes/Abilities/ContentSearch/` | Neither builds an **inbound** graph nor scans nav menus, so `get-inbound-links` is genuinely distinct |

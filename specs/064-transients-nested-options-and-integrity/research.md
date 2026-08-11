# Phase 0 Research: Transient CRUD, nested option access, plugin lifecycle & checksum integrity

**Status**: No open NEEDS CLARIFICATION markers. Decisions inherited from existing patterns; documented below.

## Decision 1: Transient CRUD scope — WP core transient APIs, not raw options table

**Decision**: `get-transient` calls `get_transient()` / `get_site_transient()`; `delete-transient` calls `delete_transient()` / `delete_site_transient()`; `delete-expired-transients` calls `delete_expired_transients()`. Only `list-transients` queries the options table directly (via `$wpdb->prepare(..., $wpdb->esc_like('_transient_') . '%')`) because WP core has no `list_transients()` helper.

**Rationale**: The WP core APIs handle external-object-cache hits (Redis, Memcached) transparently — reading raw options would miss transients that were never persisted to SQL. The only exception is `list-transients` because listing requires enumeration and WP core does not expose an enumeration helper.

**Alternatives considered**:
- Query the options table directly for reads too. Rejected — misses cache-only transients.
- Use `wp_cache_get()` to list. Rejected — the object cache surface has no enumeration primitive (nor should it).

## Decision 2: Nested-key path representation

**Decision**: The `path` input to `get-nested-option-value` and `patch-option-value` is an array of strings (JSON: `["a", "b", "c"]`), where each element is one array-key or object-property to traverse in order. Numeric keys are also accepted as string ("0", "1", …) — PHP array access accepts both.

**Rationale**: A dot-separated string (`"a.b.c"`) would break for keys that legitimately contain dots (common in WooCommerce settings). Array-of-strings is unambiguous.

**Alternatives considered**:
- Dot-separated string. Rejected — ambiguity with keys containing dots.
- JSONPath expression. Rejected — over-engineered for the read/write use cases here; introduces a parser we don't need.

## Decision 3: `patch-option-value` block-list reuse

**Decision**: Reference the same block-list of protected core options used by the existing `Update_Option` ability. If `Update_Option.php` already exposes it as `const BLOCKED_OPTIONS`, `Patch_Option_Value.php` uses it directly (`Update_Option::BLOCKED_OPTIONS`). Otherwise, extract it to a `const` on `Update_Option.php` in this feature branch so both classes share one authoritative list.

**Rationale**: DRY (constitution §VI). The block-list should have one canonical source, not two.

**Alternatives considered**:
- Duplicate the list in both classes. Rejected — violates DRY.
- Extract to a new `Utilities/Blocked_Options.php`. Rejected — premature abstraction; two consumers do not justify a new utility.

## Decision 4: `add-post-meta` alias inputs match `update-post-meta`

**Decision**: `Add_Post_Meta.php`'s input schema accepts both WP-CLI-style aliases (`key`/`value`) and WP-core-style aliases (`meta_key`/`meta_value`) for maximum client compatibility. Also accepts a `unique?: bool = false` flag matching WordPress core `add_post_meta( ..., $unique )`. This mirrors `Update_Post_Meta.php`'s existing input surface verbatim (only the semantics differ — append vs replace).

**Rationale**: Reduces cognitive load for callers; the plugin already established the aliases-accepted pattern in `update-post-meta`.

**Alternatives considered**:
- Accept only `key`/`value`. Rejected — inconsistent with the sibling ability.

## Decision 5: `search-wp-plugin-directory` output field selection

**Decision**: Request the following fields from `plugins_api()`: `slug`, `name`, `short_description`, `rating`, `active_installs`, `homepage`, `download_link`. Explicitly do not request `sections`, `screenshots`, or `banners` because those are heavy and callers who want them should hit the WP admin `install_plugin_information` screen or a follow-up API call.

**Rationale**: Keeps the response payload bounded (~1KB per plugin) so a page of 10 results fits comfortably in a REST response.

**Alternatives considered**:
- Return everything `plugins_api()` returns. Rejected — payload bloat (banners alone can be many KB per plugin).
- Return only slug + name. Rejected — omits the fields that a caller needs to reason about installability (rating, installs).

## Decision 6: `uninstall-plugin` uses WP core's own `uninstall_plugin()` — no wrapping

**Decision**: Call `uninstall_plugin( $plugin_file )` from `wp-admin/includes/plugin.php` after resolving the caller's fuzzy slug via `Plugin_Helpers::resolve_plugin()`. Do not re-implement the uninstall workflow.

**Rationale**: `uninstall_plugin()` fires the plugin's registered `<slug>_uninstall` hook, respects `register_uninstall_hook()`, and handles the `uninstall.php` fallback. Re-implementing would drift and miss edge cases.

**Alternatives considered**:
- Re-implement using `delete_plugins()`. Rejected — `delete_plugins()` skips the uninstall hook.
- Two-step: fire `<slug>_uninstall` hook then `delete_plugins()`. Rejected — `uninstall_plugin()` does both correctly.

## Decision 7: Checksums verification workflow

**Decision**: For each verify ability:
1. Fetch the expected checksums manifest via `wp_remote_get()` against `api.wordpress.org` (`/core/checksums/1.0/?version=X&locale=Y` or `/plugins/checksums/1.0/?plugin=slug&version=X`). Decode the JSON response.
2. For each file in the manifest, `md5_file()` the on-disk path and compare against the expected hash.
3. For each file present on disk but not in the manifest, mark `status: 'added'` (only reported when `strict: true`).
4. For each file in the manifest but absent on disk, mark `status: 'missing'`.
5. Return `{ per-file results, summary counters }`.

**Rationale**: This is the exact algorithm WordPress core's own hidden `_wp_core_verify_checksums()` uses (in `wp-admin/includes/update-core.php`). Matching that algorithm means the ability's output aligns with what WP-CLI's own `wp core verify-checksums` reports for the same site.

**Alternatives considered**:
- Use SHA-256 hashing. Rejected — the WordPress.org manifest ships MD5 checksums only; SHA-256 would require a different manifest that does not exist.

## Decision 8: PHPUnit HTTP mocking for network-facing abilities

**Decision**: Tests hook `add_filter('pre_http_request', ...)` to short-circuit outbound calls. Golden-path tests return canned manifests / plugin listings. Guardrail tests return `WP_Error` to simulate network failures. Restore filters in `tearDown()`.

**Rationale**: Matches the pattern established in `Test_Feature_042_Core_Update.php`.

**Alternatives considered**: Same as feature 063; rejected same alternatives.

## Open items

None.

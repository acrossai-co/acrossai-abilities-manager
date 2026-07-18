# Security Constraints: Feature 043

**Feature**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)

## Threat Model Summary

Feature 043 adds a controlled downgrade path for WordPress core. Primary threats: (1) unauthorized rollback to a known-vulnerable WP version, (2) rollback during `DISALLOW_FILE_MODS` lockdown, (3) rollback attempted on multisite without network privileges, (4) SSRF via user-controlled URL to the WP.org API, (5) response-body confusion / poisoning of the offer object, (6) request amplification via unbounded API polling.

## Constraints (enforced by shipped code)

### C-043-SEC-01 — Both-capability gate

`permission_callback` requires `current_user_can('manage_options') && current_user_can('update_core')` — same gate as `Wp_Core_Update`. Both must return true; `manage_options` alone is insufficient. Matches WP core's own admin gate for the "Update WordPress" screen.

### C-043-SEC-02 — File_Mods_Guard short-circuit

`execute()` calls `File_Mods_Guard::blocked_response('install')` before any upgrade work AND before any WP.org API fetch. When `DISALLOW_FILE_MODS` is true the ability returns the standard envelope with NO outbound HTTP.

### C-043-SEC-03 — Multisite guard

`is_multisite() && ! current_user_can('update_core')` short-circuits with a clean error before any API fetch — matches `Wp_Core_Update` and WP core's `admin_page_access_denied()` path.

### C-043-SEC-04 — Non-downgrade refusal (guardrail)

`version_compare($input_version, get_bloginfo('version'), '>=')` short-circuits with a clean error steering the caller to `wp-core-update`. Prevents accidental data loss when a caller confuses upgrade and rollback (the two abilities live under the same tab).

### C-043-SEC-05 — Hardcoded API endpoint, no user-controlled URL

The API URL is a private class constant `Wp_Core_Rollback::CORE_API_URL = 'https://api.wordpress.org/core/version-check/1.7/'`. Only the query-string locale is derived from user input, and that goes through `sanitize_text_field()` + `rawurlencode()`. No SSRF surface.

### C-043-SEC-06 — Locale sanitization

Locale input (or the `get_locale()` default) is passed through `sanitize_text_field()` for the URL and `sanitize_key()` for the transient key. The transient key format is `acrossai_abilities_manager_core_offers_{sanitize_key($locale)}` so a hostile locale value can only affect its own cache row.

### C-043-SEC-07 — Response validation

The API response is validated at four layers before use: (a) `is_wp_error($response)` on the transport, (b) HTTP status code === 200, (c) `json_decode()` returns an object with an `offers` array property, (d) each entry has an `object` type + `version` string + `version_compare('4.0', '>=')`. Malformed or truncated responses produce a clean `core_api_malformed` / `core_api_no_offers` error.

### C-043-SEC-08 — No custom bytes, no custom integrity checks

`Core_Upgrader::upgrade($offer)` is called with the offer object VERBATIM — the ability adds only two shape-parity fields (`$offer->response = 'upgrade'`, `$offer->current = $offer->version`) that don't affect the download or verification path. The ZIP URL (`$offer->download`), package URLs (`$offer->packages`), and checksums (embedded in the WP.org signed response WP core validates) all come from the WP.org API. Rollback inherits WP core's signed-tarball verification.

### C-043-SEC-09 — Per-locale offer cache with DAY_IN_SECONDS TTL

The offer list is cached in a per-locale site transient — same posture as the reference `core-rollback` plugin. Bounds request rate to the WP.org API at ≤ 1 request / day / locale / site regardless of how often `wp-core-rollback` is invoked. Matches the WP.org API's own cache-directive posture.

### C-043-SEC-10 — 15-second HTTP timeout

`wp_remote_get()` is called with `timeout => 15`. Prevents hanging admin requests when api.wordpress.org is slow or down.

### C-043-SEC-11 — WP-standard User-Agent

Outbound request carries the standard `WordPress/{version}; {site_url}` User-Agent — matches every other WP core outbound request. No custom identifier that could aid fingerprinting.

### C-043-SEC-12 — WordPress 4.0 floor

Only offers with `version_compare($offer->version, '4.0', '>=')` enter the cache — matches the reference `core-rollback` plugin's floor. WordPress < 4.0 predates modern security posture; rolling back below the floor is refused.

### C-043-SEC-13 — Destructive annotation

The ability's `meta.annotations.destructive = true` flag signals to clients (MCP, UI) that this operation is destructive. Complements the human-readable warning in the ability description.

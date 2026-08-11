# Phase 0 Research: Site introspection read endpoints

**Status**: No open NEEDS CLARIFICATION markers. All decisions inherited from the plugin's established patterns; documented below for auditability.

## Decision 1: New `Widgets/` category vs folding widget reads into an existing category

**Decision**: Introduce a new `Widgets/` ability category under `includes/Abilities/Widgets/` with its own Category_Registrar. The two widget/sidebar reads live inside it.

**Rationale**: Legacy widgets are semantically distinct from theme customisation (`Themes/`), from menus (`Menus/`), and from blocks (`Block/`). Registered sidebars belong to the widget system, not to the block editor. Folding these two reads into any of the three neighbouring categories would surface them under a misleading label to admins scanning the ability library.

**Alternatives considered**:
- Fold into `Themes/` (theme-adjacent). Rejected — plugins also register widgets and sidebars, not just themes.
- Fold into `Block/` (adjacent to `List_Reusable_Blocks`). Rejected — legacy widgets predate the block editor and represent a distinct API surface.
- Fold into `Menus/` (both are theme location systems). Rejected — sidebars are widget slots, not navigation locations; semantic collision would confuse operators.

## Decision 2: Block-list for `get-wp-config-constant`

**Decision**: Hardcode `const BLOCKED_CONSTANTS = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT', 'DB_PASSWORD']` on the class. Reject before any `constant()` call.

**Rationale**: These nine constants are the canonical `wp-config.php` secrets that WordPress ships with fresh-install placeholders and that every site operator is expected to rotate. Exposing any of them via a REST-facing ability would create a straight-line credential-exfiltration primitive on any misconfigured install. The `manage_options` gate is not sufficient defense because a compromised admin session (via XSS, phishing, or a malicious AI agent connected to the site) would inherit that capability.

**Alternatives considered**:
- Configurable via filter (`acrossai_wp_config_blocked_constants`). Rejected — a filter can be attached by any plugin including a malicious one; hardcoding removes an attack surface.
- Case-insensitive match. Rejected — PHP `defined()` is case-sensitive; matching case-sensitively keeps semantics consistent and prevents encoding-based bypass attempts.

## Decision 3: `test-wp-cron` HTTP request semantics

**Decision**: Fire a single non-blocking `wp_remote_get()` at `site_url('wp-cron.php?doing_wp_cron')` with `blocking: false, timeout: 0.01`. Report `reachable: true` if the request initiates without an error; `reachable: false` if `wp_remote_get()` returns a `WP_Error`. Always include `disable_wp_cron: (bool) ( defined('DISABLE_WP_CRON') && DISABLE_WP_CRON )` in the response.

**Rationale**: Matches the semantics that WordPress-facing monitoring tools (e.g., WP Site Health's cron checker) use. Non-blocking + tiny timeout means the ability's own response is bounded even if `wp-cron.php` legitimately takes 30+ seconds to run: we only want to know that the endpoint is *reachable*, not to *wait* for its result. Reporting the `DISABLE_WP_CRON` flag lets the operator distinguish network-blocked from configuration-blocked without a follow-up call.

**Alternatives considered**:
- Blocking request with 5-second timeout. Rejected — would tie up a REST worker for 5 seconds on a misconfigured site.
- Query the WordPress cron queue via `_get_cron_array()` instead of hitting the endpoint. Rejected — that tests whether cron is scheduled, not whether the endpoint is reachable, which is a different (and both-worth-having) fact. This ability targets reachability; the existing `list-cron-jobs` covers the scheduled-jobs question.

## Decision 4: `.maintenance` file staleness threshold

**Decision**: Read the `$upgrading` timestamp inside `.maintenance` and compare against `time() - 600` (10 minutes). If the timestamp is older than 10 minutes, flag `is_stale: true`.

**Rationale**: 10 minutes is the exact threshold WordPress core itself uses in `wp-includes/load.php::wp_maintenance()` to decide whether to bypass the maintenance banner. Matching WP core's own threshold means the ability's response aligns with what the site's front-end will actually do next.

**Alternatives considered**:
- Configurable threshold. Rejected — no operator benefit; WordPress core's own threshold is the canonical answer.

## Decision 5: `get_intermediate_image_sizes()` enrichment strategy

**Decision**: Call `get_intermediate_image_sizes()` to enumerate names, then for each name resolve width/height/crop by (a) `wp_get_additional_image_sizes()[$name]` if present, or (b) the WordPress-core defaults from `get_option("{$name}_size_w")`, `get_option("{$name}_size_h")`, `get_option("{$name}_crop")` for the four core sizes (`thumbnail`, `medium`, `medium_large`, `large`).

**Rationale**: This is the technique the WordPress Media Settings admin page uses to render its own image-size list. Reproducing it verbatim guarantees the ability's output matches what an operator sees in wp-admin.

**Alternatives considered**:
- Return only `wp_get_additional_image_sizes()` (which omits the four WP-core defaults). Rejected — the four core sizes are the ones most callers actually care about.

## Decision 6: PHPUnit HTTP mocking for `test-wp-cron`

**Decision**: Tests hook `add_filter('pre_http_request', ...)` to short-circuit the outbound `wp_remote_get()` call and return canned responses. Restore the filter in `tearDown()`.

**Rationale**: This is the established pattern used in `tests/phpunit/abilities/Test_Feature_042_Core_Update.php` for the `check-wp-core-update` ability, which also fires an outbound HTTP request. Matching the existing pattern keeps the test surface consistent.

**Alternatives considered**:
- Use a mock HTTP transport class. Rejected — the `pre_http_request` filter is the WordPress-native way and requires no new dependency.
- Skip HTTP-layer tests. Rejected — the guardrail requires distinguishing `reachable: true` from `reachable: false` cases; only end-to-end tests can prove that.

## Open items

None.

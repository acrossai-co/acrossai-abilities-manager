# Security Constraints — Feature 060

**Scope**: Third-party integration toggles on the Ability Library page. Inline review performed by `/speckit-architecture-guard-governed-plan` because `spec-kit-security-review` was not invoked as a separate sub-agent (per user preference to keep speckit orchestration self-contained). Findings below have the same weight as a `/speckit-security-review-plan` output and should be treated as gates during implementation.

## Trust Boundaries

**TB-01 — REST config-save write path (`POST /acrossai-abilities-library/v1/abilities/config`)**
This is the only external mutation surface introduced or extended by Feature 060. All integration-toggle state changes flow through this endpoint. It is already gated by:
- WordPress REST cookie authentication.
- `X-WP-Nonce` header verification (existing `check_permission` on the controller — Constitution "REST `permission_callback` Return Type" section).
- `current_user_can( 'manage_options' )` floor.

**Feature 060 adds** a second capability check on the same request: for every entry in the payload whose `category` maps to a registered integration slug, the controller MUST additionally verify `current_user_can( apply_filters( 'acrossai_integration_toggle_capability', 'manage_options', $integration_slug ) )`. Both checks MUST pass. The filter's default (`manage_options`) means default behaviour is unchanged; sites that raise the requirement (e.g. to `manage_network_options`) get stricter enforcement automatically.

**Non-negotiable**: if a site's filter returns a stricter capability, a user holding only `manage_options` MUST receive a 403 from the REST endpoint, regardless of whether the JS UI presented the toggle as interactive. See SC-060-02.

**TB-02 — Third-party plugin activation state**
`is_plugin_active()` is the sole gate that determines whether an integration is *visible* or *wired*. It is called per-request from two hook callbacks (`plugins_loaded` P20 and `acrossai_abilities_api_init` P10) and MUST NOT be memoized across the lifetime of the plugin. Caching the result at construction would leak stale UI when the target plugin is deactivated mid-session.

## Data Isolation

**DI-01 — Multisite scope asymmetry (documented, not a defect)**
`acrossai_library_config` is stored via `get_site_option` / `update_site_option`, which on multisite reads and writes `wp_sitemeta` (network-wide). Integration toggle state is therefore network-scoped: a network admin who enables ACF affects all sites in the network. The **visible** UI, however, is per-site because `is_plugin_active()` reflects the current site's activation state. This is consistent with the rest of the Library page and is called out explicitly in spec Edge Case "Multisite with ACF network-active on some sites but not others" and in `memory-synthesis.md` under SEC-03.

**DI-02 — Integration entries are never exposed to unauthenticated readers**
The REST GET path exists and is `check_permission`-gated; no filter-based read leak is introduced by Feature 060. No new PHP endpoint reads `acrossai_library_config`. Downstream ability consumers (WP-CLI, MCP `discover-abilities`) receive only the *effects* of the toggle (the presence or absence of ACF-registered abilities), not the raw config shape.

## Validation Risks

**VR-01 — `card_variant` field must be sanitized at the Registry boundary**
The Registry accepts `card_variant` as an optional top-level field on each definition row and MUST sanitize it via `AcrossAI_Ability_Library_Config::sanitize_key_field()` (same treatment as `tab_group`, established in Feature 037). Empty values MUST be dropped, not preserved. This prevents any downstream JS from receiving unsanitized keys even if a subclass author accidentally supplies an unusual value.

**VR-02 — Fixed ability list from `abilities()` is display-only**
`abilities()` returns a static array of `{ slug, label, description? }` for card rendering. These entries never touch the WP Abilities API registration path, never execute code, and are rendered through `@wordpress/components` (which escapes). No path exposes them to `wp_options` or the REST GET response outside the definition-row output the Registry already sanitizes.

**VR-03 — Integration slug uniqueness**
Two integrations declared with the same slug would collide on the shared tab identifier. Spec explicitly lists this as **out of scope** (edge case: "Two integrations declared for the same third-party plugin"). Implementation is not required to detect the collision — the second registration will overwrite the first in the definition rows, which is acceptable given that no such conflict exists at ship time.

## Authorization Assumptions

**AZ-01 — Server enforcement, not UI enforcement**
FR-016 explicitly mandates that the capability filter be enforced on the server-side write path, not merely in the JavaScript UI. This is a hard requirement — a stricter site filter must reject a crafted REST request from a `manage_options`-only user with a 403, not merely hide the toggle in the client. Implementation MUST verify this via the security test in SC-060-02.

**AZ-02 — Capability filter cannot escalate**
`current_user_can( $filtered_cap )` requires the caller to hold `$filtered_cap`. If a bad filter returned a nonsense string, `current_user_can` returns false — a fail-closed behaviour that we accept as the safer failure mode. If a bad filter returned an empty string, `current_user_can( '' )` also returns false in modern WordPress. No fail-open path exists.

## Async Security Context

Not applicable. Feature 060 performs no async work, no scheduled tasks, and no background HTTP requests. All logic runs synchronously within the incoming admin/REST request.

## Non-Functional Security Requirements (mapped to spec SCs)

- **SC-060-01 (from spec SC-003)**: On a fresh install with ACF active, 0 ACF abilities MUST be reachable via any authenticated or unauthenticated surface until the admin explicitly toggles the integration on. Verified by MCP `discover-abilities` returning no ACF entries.
- **SC-060-02 (from spec FR-016)**: With `add_filter('acrossai_integration_toggle_capability', fn()=>'manage_network_options')` installed, a user holding only `manage_options` MUST receive HTTP 403 from `POST /acrossai-abilities-library/v1/abilities/config` when the payload targets an integration slug. Verified by an authenticated REST call in the quickstart.
- **SC-060-03 (from spec FR-013)**: Loading the Library page across all four lifecycle states of the target plugin (never installed / installed but deactivated / active with toggle off / active with toggle on) MUST produce 0 PHP notices, warnings, or fatal errors. Verified by inspecting the WP debug log.

## Suppressions Introduced

None planned. No `phpcs:ignore`, no Plugin Check ignore, no PHPStan `@phpstan-ignore`. All new PHP is expected to be clean under Constitution §II.

## References

- Constitution §IV Security First (NON-NEGOTIABLE)
- SEC-03 (multisite/per-site prefix) — `docs/memory/security-constraints.md`
- SEC-04 (strict type comparison) — `docs/memory/security-constraints.md`
- DEC-PLUGIN-CHECK-PRODUCTION-SURFACE — `docs/memory/DECISIONS.md`
- BUG-UNIMPLEMENTED-HOOK — `docs/memory/BUGS.md` (guards against declaring a filter without wiring it)

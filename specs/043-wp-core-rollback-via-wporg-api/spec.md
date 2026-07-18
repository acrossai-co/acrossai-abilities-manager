# Feature Specification: WordPress core rollback via WP.org Core API

**Feature Branch**: `043-wp-core-rollback-via-wporg-api`
**Created**: 2026-07-18
**Status**: Draft
**Input**: User description: "here are the live plugin that does that and I guess it use the wordpress functions I am not 100% sure that this plugin use it /Users/…/core-rollback but if so then take imsperetation from it" (referring to Andy Fragen's `core-rollback` plugin, `https://github.com/afragen/core-rollback`)

## Clarifications

### Session 2026-07-18

- Q: How should we act on the core-rollback finding? → A: Ship Feature 043 `wp-core-rollback` ability now.
- Q (implicit from Feature 042's out-of-scope statement, now revised): Doesn't WP core have no `WP_Downgrader`? → A: Correct, but `Core_Upgrader::upgrade($offer)` does not care whether `$offer->version` is older than current — it installs whatever the offer describes. We fetch an older offer from the WP.org Core API and hand it directly to the upgrader.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Roll back WordPress core through the Abilities API (Priority: P1)

An administrator (or an AI / MCP client the admin has authorized) needs to roll back the site from a broken WP core release to a known-good earlier release without touching wp-admin. They call `wp-core-rollback {version: "6.8.1"}`. The ability fetches the WP.org Core API, locates the 6.8.1 offer, and hands it to `Core_Upgrader::upgrade()` — the same upgrader class that runs when you click "Re-install Now" on the WP dashboard. The site drops to 6.8.1. Re-calling with `version: "6.9.0"` (current or newer) is refused with a clean error steering the caller to `wp-core-update`.

**Why this priority**: The wp-core-update ability shipped in 0.0.11 covers the forward path. Rollback is the natural counterpart — required for real-world incident response — and Feature 042's spec explicitly marked it out-of-scope because WP core has no `WP_Downgrader`. Since Andy Fragen's `core-rollback` plugin demonstrated the WP-core-only technique (manipulate the offer, reuse Core_Upgrader), the loop closes here.

**Independent Test**: On a site running WordPress N, `wp-core-rollback {version: "N-1"}` returns `success=true`, `updated=true`, `from_version=N`, `to_version=N-1`, and `get_bloginfo('version')` reports N-1 after the call. Calling `wp-core-rollback {version: "99.99.99"}` returns a clean error naming the missing version.

**Acceptance Scenarios**:

1. **Given** a site running WordPress N and the WP.org API has an offer for N-1, **When** `wp-core-rollback {version: "N-1"}` is called, **Then** the response is `{success:true, updated:true, from_version:"N", to_version:"N-1", message:"WordPress rolled back from N to N-1."}`.
2. **Given** the same site, **When** `wp-core-rollback {version: "N"}` (same as current) or `{version: "N+1"}` (upgrade) is called, **Then** the response is a clean error steering the caller to `wp-core-update`, and NO Core_Upgrader invocation happens.
3. **Given** the WP.org API returns 404 or malformed JSON, **When** any rollback is attempted, **Then** the response is a clean error surfacing the API failure, and NO Core_Upgrader invocation happens.
4. **Given** `wp-core-rollback {version: "99.99.99"}` (not in the API offer list), **Then** the response is a clean error naming the missing version.
5. **Given** `DISALLOW_FILE_MODS=true` in wp-config, **When** `wp-core-rollback {}` is called, **Then** the response is the standard `File_Mods_Guard` envelope; NO API call is made and NO Core_Upgrader invocation happens.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-043-001**: A new ability `acrossai-abilities-manager/wp-core-rollback` MUST exist under the Core category with `tab_group=core`, `sub_group=lifecycle`.
- **FR-043-002**: `wp-core-rollback`'s input schema MUST require `version` (string). It MAY accept `locale` (string, optional; defaults to `get_locale()`).
- **FR-043-003**: The ability MUST refuse when `version_compare($input_version, get_bloginfo('version'), '>=')` returns true — that's an upgrade path, not a rollback. Response MUST steer the caller to `wp-core-update`.
- **FR-043-004**: The ability MUST fetch the offer list from `https://api.wordpress.org/core/version-check/1.7/?locale={locale}` via `wp_remote_get()`. It MUST NOT bundle its own updater code, its own version list, or any custom integrity verification.
- **FR-043-005**: The offer list MUST be cached in a per-locale site transient (`acrossai_abilities_manager_core_offers_{locale}`) with a `DAY_IN_SECONDS` TTL — matches the reference `core-rollback` plugin's cache posture and the WP.org API's own cache posture.
- **FR-043-006**: The ability MUST pass the fetched offer object directly to `Core_Upgrader::upgrade()` — no transient injection dance is required (the plugin `core-rollback` needs that dance to funnel through the update-core.php UI; we invoke `Core_Upgrader` directly).
- **FR-043-007**: Before handing the offer to Core_Upgrader, the ability MUST set `$offer->response = 'upgrade'` (the WP.org API marks offered versions as `latest` — Core_Upgrader itself only inspects `->download`, `->packages`, `->version`, but forcing `upgrade` keeps the offer shape consistent with what `get_core_updates()` returns for the forward path).
- **FR-043-008**: `wp-core-rollback`'s `permission_callback` MUST require BOTH `manage_options` AND `update_core`. Its `execute()` MUST additionally short-circuit via `File_Mods_Guard::blocked_response('install')` and MUST include a multisite guard.
- **FR-043-009**: The ability MUST report `from_version` (before) and `to_version` (after) using `get_bloginfo('version')`; on API-fetch failure both fields reflect the unchanged current version.
- **FR-043-010**: The ability MUST be instantiated in `AcrossAI_Core_Abilities_Bootstrap::register_abilities()` alongside `Wp_Core_Update_Check` and `Wp_Core_Update`.

### Non-Functional Requirements

- **NFR-043-001**: The new ability MUST match the exact class shape used by `Wp_Core_Update` / `Plugin_Update` / `Theme_Update`.
- **NFR-043-002**: All new code MUST pass PHPStan L8 zero errors and PHPCS strict WPCS zero errors.
- **NFR-043-003**: Test coverage MUST use the source-inspection pattern established by Feature 046 / 041 / 042 — no live WP.org API calls in tests.

## Success Criteria

- **SC-043-001**: The Core tab on the Ability Library page lists a third ability (`Rollback WordPress Core`) alongside the two Feature 042 abilities.
- **SC-043-002**: `composer run phpstan` zero errors at L8; `composer run phpcs` zero errors; `composer test` all pass (target: 163 tests / 531 assertions after +10 tests / +27 assertions).
- **SC-043-003**: `wp-core-rollback` refuses when the target version equals or exceeds current; the error message names `wp-core-update` as the correct forward-path ability.
- **SC-043-004**: `wp-core-rollback` handles WP.org API failure paths cleanly (HTTP non-200, malformed JSON, empty offer list) — the response is a `{success:false, message:…}` envelope with the failure reason surfaced.
- **SC-043-005**: `DISALLOW_FILE_MODS=true` short-circuits the ability BEFORE any API call is made.

## Out of Scope

- Rolling forward via this ability (use `wp-core-update` — the guardrail explicitly steers there).
- Alternate mirrors of the Core API (only api.wordpress.org is used; the "which server bytes come from" question is delegated to WP core just like the wp-core-update path).
- Automatic rollback on failure of `wp-core-update` (would require exception handling around the upgrader that WP core doesn't expose — deferred).
- Downgrading below WordPress 4.0 (the reference `core-rollback` plugin's `version_compare('4.0', '>=')` guard is preserved: only 4.0+ offers make it into the cache).
- Rollback to a non-secure version — WP.org excludes non-secure releases from the offer list; the ability trusts the API to enforce this.

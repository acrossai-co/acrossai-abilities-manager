# Implementation Plan: Feature 043

**Feature Branch**: `043-wp-core-rollback-via-wporg-api`
**Base**: `main` at `53f8763` (release 0.0.11)
**Target release**: 0.0.12

## Approach

Ship one new ability under the existing Core category (added in Feature 042). Read Andy Fragen's `core-rollback` plugin for the technique — but do NOT copy its architecture wholesale: `core-rollback` needs a transient-injection + `pre_http_request` dance because it funnels through the WP dashboard's "Re-install Now" button. Our ability invokes `Core_Upgrader::upgrade()` directly, so we hand it a hand-constructed offer without touching WP core's upgrade transient.

## New files

- `includes/Abilities/Core/Wp_Core_Rollback.php` — one class extending `Ability_Definition`. Modelled on `Wp_Core_Update`; adds a `fetch_offer()` private method that talks to the WP.org Core API via `wp_remote_get()` and caches offers per-locale.
- `tests/phpunit/abilities/Test_Feature_043_Core_Rollback.php` — 10 source-inspection tests: ability scaffolding, both-cap gate, all-guards (File_Mods_Guard + multisite), refuses non-downgrade via `version_compare('>=' )`, wraps `Core_Upgrader::upgrade()` with `WP_Ajax_Upgrader_Skin`, forces `response=upgrade` on the offer, fetches from `api.wordpress.org/core/version-check/1.7/`, caches per-locale with `DAY_IN_SECONDS`, declares `destructive=true`, bootstrap wires it.

## Modified files

- `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — add one `new Core\Wp_Core_Rollback();` line next to the two existing Feature 042 abilities.
- `phpunit.xml.dist` — add `feature-043-unit` testsuite.

## Reusables

- **`Ability_Definition`** — extends it; auto-hooks on construct.
- **`File_Mods_Guard::blocked_response('install')`** — same short-circuit `Wp_Core_Update` uses.
- **`Wp_Core_Update`** (Feature 042) — reference for the class shape, `Core_Upgrader::upgrade()` result-interpretation ladder, and multisite guard.
- **WP core**: `wp_remote_get`, `wp_remote_retrieve_body`, `wp_remote_retrieve_response_code`, `get_site_transient`, `set_site_transient`, `sanitize_key`, `sanitize_text_field`, `get_locale`, `get_bloginfo`, `version_compare`, `Core_Upgrader`, `WP_Ajax_Upgrader_Skin`. No third-party libs.

## Downgrade flow (uses WP core exclusively; no bundled updater)

```
Blocked?  File_Mods_Guard::blocked_response('install') → short-circuit
Multisite? is_multisite() && !current_user_can('update_core') → clean error
Validate: version provided, strictly older than get_bloginfo('version')

Fetch:
  key = 'acrossai_abilities_manager_core_offers_{locale}'
  offers = get_site_transient(key)
  if not cached OR target version not in cache:
      response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/?locale='.$locale)
      parse json, filter 4.0+, index by version
      set_site_transient(key, $offers, DAY_IN_SECONDS)
  offer = $offers[$version]  // returns null → clean 404-style error

Prepare:
  $offer->response = 'upgrade'   // shape parity with get_core_updates()
  $offer->current  = $offer->version

Invoke:
  require_once wp-admin includes: update.php, class-wp-upgrader.php, file.php, misc.php
  $skin     = new WP_Ajax_Upgrader_Skin();
  $upgrader = new Core_Upgrader($skin);
  $result   = $upgrader->upgrade($offer);

Interpret (identical ladder to Wp_Core_Update / Plugin_Update / Theme_Update):
  is_wp_error($result)        → success=true, updated=false, message=$result->get_error_message()
  null/false $result          → drain $skin->get_errors()
  else                        → success=true, updated=true, from/to from get_bloginfo('version')
```

## Security constraints

See [security-constraints.md](./security-constraints.md).

## Verification

- `composer run phpstan` (level 8) — zero errors
- `composer run phpcs` — zero errors
- `composer test` — full suite green (target: 163 tests / 531 assertions after +10 tests / +27 assertions)
- Manual e2e on a Local site running a current WordPress: `wp-core-rollback {version: "N-1"}` where N-1 is a version WP.org still offers → verify site drops to N-1 and `get_bloginfo('version')` reflects it after the call.
- Manual e2e: `wp-core-rollback {version: "N+1"}` → clean refusal, no upgrade attempt.
- Manual e2e: `wp-core-rollback {version: "99.99.99"}` → clean "not in offer list" error.
- Manual e2e: `DISALLOW_FILE_MODS=true` → short-circuit before any HTTP call.
- MCP smoke test via `acrossai-mcp-adapter-default-server`.

## Ship

Two PRs on merge, following the 041/042 cadence:

1. **Feature PR** on branch `043-wp-core-rollback-via-wporg-api` → `main`. Ships the ability + tests + spec-kit.
2. **Release PR** on branch `release-0.0.12` → `main`. Version bump + Changelog + Upgrade Notice.

Then tag `0.0.12` and cut GitHub release.

Post-implementation: revise the pending memory-capture entries (from the 0.0.10 → 0.0.11 range) to include the rollback technique in `PATTERN-WP-CORE-UPGRADER-ABILITY`, add a new `PATTERN-WP-CORE-ROLLBACK-VIA-API-OFFER`, and update the Feature 042 WORKLOG milestone entry to note the follow-up feature — then get approval and write.

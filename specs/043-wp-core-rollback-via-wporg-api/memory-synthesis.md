# Memory Synthesis: Feature 043

**Feature**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)

## Current Scope

Adds a third ability under the Core category folder introduced in Feature 042: `acrossai-abilities-manager/wp-core-rollback`. Rolls back WordPress core to an earlier version by fetching the offer from the WP.org Core API 1.7 endpoint (`https://api.wordpress.org/core/version-check/1.7/`) via `wp_remote_get()`, manipulating two shape-parity fields on the returned offer object (`->response = 'upgrade'`, `->current = ->version`), and handing the offer directly to `Core_Upgrader::upgrade()` — the same class WP core uses for forward updates. Uses only WordPress functions; no bundled updater code. Inspired by Andy Fragen's `core-rollback` plugin (MIT-licensed) but skips its transient / `pre_http_request` injection dance (which that plugin needs to funnel through wp-admin's "Re-install Now" button — we invoke `Core_Upgrader` directly). Target release: 0.0.12.

## Relevant Decisions

- **DEC-043-CORE-UPGRADER-VERSION-DIRECTION-AGNOSTIC** (New: `Core_Upgrader::upgrade($offer)` does not inspect whether `$offer->version` is older or newer than the currently-installed version — it simply installs what the offer describes. This means WP core does NOT need a `WP_Downgrader` class; a WP-core-only rollback ability just needs to hand `Core_Upgrader` an older offer object. Supersedes the "downgrade path is out of scope because WP core has no `WP_Downgrader`" statement in Feature 042's spec. Status: Active, Source: this spec + inspection of `wp-admin/includes/class-core-upgrader.php`)
- **DEC-043-WPORG-CORE-API-DIRECT** (New: The rollback ability fetches offers from `api.wordpress.org/core/version-check/1.7/` directly via `wp_remote_get` rather than going through WP core's `wp_version_check()` + `site_transient_update_core` path. Reason: WP core's transient only exposes the LATEST offer; older versions aren't there. Direct API access is the only way to enumerate offered older releases. Endpoint 1.7 is what the reference `core-rollback` plugin uses; it returns an `offers` array with every version WP.org still exposes. Status: Active, Source: this spec)
- **DEC-043-DIRECT-UPGRADER-VS-DASHBOARD-DANCE** (New: We invoke `Core_Upgrader::upgrade($offer)` directly instead of manipulating `site_transient_update_core` + `pre_http_request` filters to funnel through `update-core.php?action=do-core-reinstall`. Reason: the reference `core-rollback` plugin uses the dashboard-funnel technique because it wires into the WP admin UI; the Abilities API is a headless / MCP-facing interface where direct invocation is simpler and has fewer moving parts. Status: Active, Source: this spec)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason: `Wp_Core_Rollback` auto-registers via `Ability_Definition::__construct`. Bootstrap wiring is one `new Core\Wp_Core_Rollback();` line in `AcrossAI_Core_Abilities_Bootstrap::register_abilities()`. Source: CONSTITUTION.md §I)
- **AC-MODULE-ENUMERATION-LOCKED** (Reason: No new module; the Core Category folder introduced in Feature 042 now contains three abilities. Source: CONSTITUTION.md §I)
- **AC-CAPABILITY-BASELINE** (Reason: Requires both `manage_options` AND `update_core` — matches Wp_Core_Update and WP core's own admin gate. Source: CONSTITUTION.md §IV)
- **AC-FILE-MODS-GUARD** (Reason: Short-circuits via `File_Mods_Guard::blocked_response('install')` before any HTTP or upgrade work. Source: CONSTITUTION.md §IV)
- **AC-PATTERN-PARITY** (Reason: Class shape matches Wp_Core_Update exactly; result-interpretation ladder is the shared 4-line pattern from Plugin_Update / Theme_Update / Wp_Core_Update. Source: this spec + user directive from Feature 042 round)

## Accepted Deviations

None. Feature 043 stays inside the existing Core category folder; adds no new module, no new utility class, no new REST endpoint, no new DB table.

## Relevant Security Constraints

See [security-constraints.md](./security-constraints.md). Thirteen constraints in force:

- C-043-SEC-01 through C-043-SEC-13 — both-cap gate, File_Mods_Guard, multisite guard, non-downgrade refusal (guardrail), hardcoded API URL (no SSRF surface), locale sanitization, response validation (4-layer), no custom bytes / no custom integrity, per-locale cache with day TTL, 15s HTTP timeout, WP-standard User-Agent, WordPress 4.0 floor, destructive annotation.

## Related Historical Lessons

- **BUG-041-01 — Global Deny in .htaccess breaks download URL** — Not exercised.
- **BUG-041-02 — SELF_FIRST does not stop descent on `continue`** — Not exercised.
- **NEW BUG-042-01 — Sub-second filename collision on concurrent zip-create** — Not exercised.
- **NEW technique — `Core_Upgrader::upgrade` accepts hand-constructed offers** (Reason: Core_Upgrader's internal implementation reads `$offer->download`, `$offer->packages`, `$offer->version`, `$offer->locale` and passes them to `WP_Upgrader::install_package()` — which does not inspect `$offer->response` or compare `$offer->version` to `wp_get_wp_version()`. Any well-formed offer works. This is durable knowledge worth capturing to memory. Source: this spec, verified during Feature 043 implementation)

## Conflict Warnings

- Feature 042's spec.md said "WP core has no `WP_Downgrader`" as an out-of-scope justification. That's factually true but MISLEADING — `Core_Upgrader::upgrade()` handles both directions. Feature 043 supersedes Feature 042's out-of-scope statement; the correct framing is now "downgrade is a supported flow via manipulated-offer + `Core_Upgrader::upgrade()`; the multisite bulk-network-upgrade path remains out of scope".

## Retrieval Notes

- Optimizer not enabled — markdown-only, index-first retrieval used.
- Read references at planning time: Andy Fragen's `core-rollback` plugin (`wp-content/plugins/core-rollback/src/Core.php` + `Settings.php`); WP core `Core_Upgrader::upgrade()`; existing `includes/Abilities/Core/Wp_Core_Update.php` (shape reference); `File_Mods_Guard`.
- Full durable-memory reads: NOT performed. Budget status: within limits (~700 words, under the 900-word cap).

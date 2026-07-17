# Implementation Plan: Bump acrossai-co/main-menu 0.0.14 → 0.0.23, remove Freemius, restyle Library header, self-filter Add-ons

**Branch**: `053-main-menu-0-0-21-freemius-removal` | **Date**: 2026-07-17 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification + implementation clarifications (Session 2026-07-17 Q1/Q2/Q3)
**Memory synthesis**: [memory-synthesis.md](./memory-synthesis.md)
**Security review**: [security-constraints.md](./security-constraints.md)
**Architecture review**: [architecture-review.md](./architecture-review.md)

## Summary

Three overlapping scope areas shipped in a single PR (#69):

1. **Composer bump**: `acrossai-co/main-menu 0.0.14 → 0.0.23`. Upstream `0.0.21` dropped the `freemius/wordpress-sdk` dependency and restructured the Add-ons page: the entry-class `\AcrossAI_Addon\AddonsPage` was deleted and its responsibilities moved into `\AcrossAI_Main_Menu\MenuRegistrar` (which runs automatically when `\AcrossAI_Main_Menu\SettingsPage` boots). Subsequent point releases `0.0.22` and `0.0.23` preserved the consumer API surface — no code changes required for the two follow-on bumps.

2. **Freemius removal from this plugin**:
   - Delete the entire `if ( class_exists( \AcrossAI_Addon\AddonsPage::class ) ) { … }` block in `includes/Main.php::define_admin_hooks()` — the class no longer exists and the Add-ons submenu registration is now automatic via `MenuRegistrar`.
   - Remove `freemius/wordpress-sdk` from `composer.lock` (drops out transitively).
   - Update four `README.txt` non-historical sections to describe the plugin as making zero external HTTP requests: Third-party integrations bullet, Install "Add-ons:" section, FAQ "Does this plugin make external HTTP requests?", Screenshot 5 caption, External Services block, Privacy Policy block.
   - Preserve historical changelog entries for `0.0.1` and `0.0.6` that mention Freemius — they document what those releases shipped and are historical record.

3. **Two supplementary UX changes shipped in the same PR at user request**:
   - **Library page header row on one line**: move the `<h1>Ability Library</h1>` out of the PHP-rendered wrap and into the React header row so title + `Enable All` / `Disable All` buttons render on a single horizontal row separated by `justify-content: space-between`.
   - **Self-filter on `acrossai_addons`**: hook the filter with a callback that strips the plugin's own entry (`slug === 'acrossai-abilities-manager'`) from the shared baseline list. Wired via the Loader in `includes/Main.php::define_admin_hooks()` on the existing `$plugin_admin` instance.

Version bump / changelog / tag deferred to a future release cycle (0.0.8 or later). This PR is code + docs + spec-kit artifacts only.

## Technical Context

**Language/Version**: PHP 8.1+ (Constitution §II floor); JavaScript per `@wordpress/scripts` (Node ≥20 build environment per `DEC-NODE-20-BUILD-REQUIRED`).
**Primary Dependencies**: `acrossai-co/main-menu 0.0.23` (was `0.0.14`); `automattic/jetpack-autoloader ^5.0` (transitively bumped `v5.0.20 → v5.0.21`); every other Composer + npm dep unchanged.
**Removed Dependencies**: `freemius/wordpress-sdk` (~2,000 vendored files, dropped from `vendor/freemius/`).
**Storage**: No new options; existing `wp_options` rows with `fs_*` or `freemius_*` prefixes on prior-release sites become inert but are not deleted (out of scope).
**Testing**: PHPUnit 129/129 (no test changes needed — dependency + wiring change); Jest 82/82 across `tests/jest/ability-library/` (no test changes needed for the header-row layout; DOM structure change is inert to the existing pure-helper tests).
**Target Platform**: WordPress 6.9+, PHP 8.1+, multisite-compatible.
**Project Type**: WordPress plugin — admin PHP + React admin surface.
**Performance Goals**: `SC-003` — installable ZIP smaller by at least the Freemius SDK tree size (measurable via `composer install --no-dev` + `du -sh vendor/`). No admin-page latency regressions.
**Constraints**: Consumer API surface of `acrossai-co/main-menu` (`SettingsPage`, `MenuRegistrar`, `AddonsPageRenderer`) is unchanged from 0.0.21 → 0.0.23; if any subsequent bump were to break these, this plan would need revision.
**Scale/Scope**: One composer dep bumped; one PHP class-instantiation block deleted; one JSX element moved; one SCSS rule flipped; one filter callback added; four `README.txt` sections rewritten.

## Constitution Check

Constitution version: **1.4.8**.

| Principle | Verdict | Notes |
|---|---|---|
| §I Modular Architecture | ✅ PASS | All edits confined to `includes/Main.php` + `admin/Main.php` + `admin/Partials/LibraryMenu.php` + `src/js/ability-library/components/LibraryPage.js` + `src/scss/ability-library/admin.scss` + `composer.json` + `composer.lock` + `README.txt`. No new module. |
| §II WPCS Compliance | ✅ PASS | PHPStan L8 zero errors; PHPCS clean on all touched PHP files; ESLint via wp-scripts; Plugin Check clean. `AC-HOOKS-MAIN` respected — the new `acrossai_addons` filter registers via the Loader in `includes/Main.php::define_admin_hooks()`. |
| §III User-Centric Design (NON-NEGOTIABLE) | ⚠️ ACCEPTED DEVIATION (pre-approved) | Library page still uses `<Button>` primitives on the shared React surface — `DEC-DESIGN-OVERRIDES-DATAVIEWS` pre-approves this. The header-row layout change does not introduce a new form or list surface. No new deviation. |
| §IV Security First (NON-NEGOTIABLE) | ✅ PASS | Freemius removal REDUCES external-service surface entirely. No new user-input surfaces, no new REST routes, no new capability boundaries. The `acrossai_addons` filter callback is defensive against non-array input. |
| §V Extensibility Without Core Modification | ✅ PASS | Vendor package version bump only; no vendor edits. Self-filter uses the documented `acrossai_addons` filter contract. |
| §VI DRY & @wordpress-first | ✅ PASS | No new dependencies. `npm run validate-packages` clean. |
| §VII Definition of Done | ✅ PASS | Quality gates enumerated below all remain green. |

**Boot Flow Rule**: The new `acrossai_addons` filter is registered via `$this->loader->add_filter(...)` inside `includes/Main.php::define_admin_hooks()` on the existing `$plugin_admin` named variable. `AC-HOOKS-MAIN` and the variable-first pattern remain satisfied.

**REST `permission_callback` Return Type**: N/A — no REST route changes.

**Overall verdict**: PASS with one pre-approved accepted deviation (`DEC-DESIGN-OVERRIDES-DATAVIEWS`). No new violations.

## Project Structure

### Documentation (this feature)

```text
specs/053-main-menu-0-0-21-freemius-removal/
├── plan.md                    # THIS FILE
├── spec.md                    # Feature specification (backfilled post-implementation)
├── memory-synthesis.md        # Memory-first index selection
├── security-constraints.md    # Plan-level security review
├── architecture-review.md     # Architecture violation detection (no violations)
├── checklists/
│   └── requirements.md        # Spec-quality checklist (all passing)
└── tasks.md                   # Task checklist (all boxes checked — post-implementation)
```

### Source Code (repository root — files touched by this feature)

```text
acrossai-abilities-manager/
├── acrossai-abilities-manager.php                   # UNCHANGED — no Version bump this PR
├── composer.json                                    # MODIFIED — main-menu 0.0.14 → 0.0.23
├── composer.lock                                    # REGENERATED — main-menu upgraded; freemius/wordpress-sdk REMOVED; jetpack-autoloader v5.0.20 → v5.0.21 (transitive)
├── README.txt                                       # MODIFIED — 4 non-historical Freemius mentions removed / rewritten; historical changelog entries preserved
├── admin/
│   ├── Main.php                                     # MODIFIED — new filter_out_self_from_addons() public method
│   └── Partials/
│       └── LibraryMenu.php                          # MODIFIED — removed the server-rendered <h1>Ability Library</h1>
├── includes/
│   └── Main.php                                     # MODIFIED — deleted the \AcrossAI_Addon\AddonsPage instantiation block; wired new acrossai_addons filter via Loader
├── src/
│   ├── js/
│   │   └── ability-library/
│   │       └── components/
│   │           └── LibraryPage.js                   # MODIFIED — added <h1> and __header-actions wrapper inside .acrossai-library-page__header
│   └── scss/
│       └── ability-library/
│           └── admin.scss                           # MODIFIED — .acrossai-library-page__header flipped to justify-content: space-between; added .__title and .__header-actions rules
├── vendor/                                          # REGENERATED via composer install
│   ├── acrossai-co/main-menu/                       # 0.0.14 → 0.0.23
│   ├── automattic/jetpack-autoloader/               # v5.0.20 → v5.0.21
│   └── freemius/                                    # DELETED (entire tree gone)
└── build/                                           # REGENERATED via npm run build
    ├── js/ability-library.js                        # New content hash
    ├── js/ability-library.asset.php                 # New version hash
    ├── css/ability-library.css                      # New content hash
    └── css/ability-library-rtl.css                  # New content hash
```

**Structure Decision**: Standard WordPress plugin layout — same layout used by every previous Library-touching feature. No new top-level directories.

## Phase 0 — Research (compressed)

- **`acrossai-co/main-menu 0.0.21` architecture change**: Read `vendor/acrossai-co/main-menu/src/` after the composer bump. `\AcrossAI_Addon\AddonsPage` class is GONE; replaced by `\AcrossAI_Main_Menu\MenuRegistrar` (internal wiring) + `\AcrossAI_Main_Menu\AddonsPageRenderer` (public renderer with baseline list) + `\AcrossAI_Main_Menu\AddonsAjaxHandlers` + `\AcrossAI_Main_Menu\AddonsInstaller`. Consumers extend the list via the `acrossai_addons` filter — not via constructor args. Menu registration is automatic when the shared `SettingsPage` bootstrap runs.
- **This plugin already bootstraps `SettingsPage`**: At `acrossai-abilities-manager.php:142-154` inside a `plugins_loaded @ P0` closure with a `did_action('acrossai_main_menu_bootstrapped')` idempotency guard. So the Add-ons submenu is registered automatically after the 0.0.21 upgrade — no additional wiring needed.
- **Baseline `acrossai_addons` list in 0.0.21+**: Includes `acrossai-abilities-manager` (this plugin), `acrossai-mcp-manager`, `acrossai-model-manager`, `turn-off-ai-features`. Self-filter is a simple `array_filter`-style callback that drops the entry with matching `slug`.
- **Header row layout options considered**: (A) Move `<h1>` into React — chosen; keeps flex layout cohesive. (B) Keep PHP `<h1>` and use CSS float / absolute positioning to overlay buttons — rejected as fragile. (C) Render two `<h1>` elements one in PHP and one hidden via CSS — rejected as a11y-hostile.
- **Historical bugs to guard against**: `BUG-CLASS-EXISTS-AUTOLOAD-FALSE-SILENT` (already respected — this feature doesn't add new `class_exists` guards); `BUG-WP-LOCALIZE-SCRIPT-RENDER` (no new localization); `BUG-JEST-MOCK-LIST-STALENESS` (Jest mocks already had `Button` from Feature 052; H1 element requires no mock).

## Phase 1 — Design

### Data model (no changes)

No new PHP classes, no new database tables, no new option keys, no new REST routes, no new capability boundaries. Only additive PHP method + JSX/SCSS structural changes.

### Contracts

- **New PHP public method**: `\AcrossAI_Abilities_Manager\Admin\Main::filter_out_self_from_addons( array $addons ): array` — returns the input array minus entries with `slug === 'acrossai-abilities-manager'`. Defensive against non-array inputs and non-array entries.
- **New filter registration**: `$this->loader->add_filter( 'acrossai_addons', $plugin_admin, 'filter_out_self_from_addons' );` in `includes/Main.php::define_admin_hooks()`.
- **Composer dependency change**: `acrossai-co/main-menu`: `0.0.14 → 0.0.23`.
- **PHP class instantiation removed**: The former `new \AcrossAI_Addon\AddonsPage( ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE, [...] )` call is deleted; the Add-ons submenu is now registered automatically by `\AcrossAI_Main_Menu\MenuRegistrar` when the shared `SettingsPage` bootstrap runs.
- **DOM contract change**: The `<h1>` for the Library page moves from `admin/Partials/LibraryMenu.php` (PHP render) to `src/js/ability-library/components/LibraryPage.js` (React render). Still exactly one H1 per page — a11y invariant preserved.
- **Localized data contract**: `window.acrossaiAbilityLibraryData.*` unchanged. `bulkToggleState` still emitted from Feature 052; `addonsUrl` still points at `?page=acrossai-addons`.

### Quickstart

Manual verification steps for reviewer:

1. Run `composer install`. Confirm `vendor/freemius/` does not exist after the install.
2. Visit `?page=acrossai-addons`. Confirm the plugin's own card is absent; confirm other companion plugin cards render normally.
3. Visit `?page=acrossai-abilities-library`. Confirm title "Ability Library" + `Enable All` + `Disable All` render on a single horizontal row above the tab strip.
4. Verify Feature 052 flows still work: bulk enable/disable on the current tab, URL sync (`?tab=<slug>`), disabled-card readonly preview.
5. Verify `?page=acrossai-abilities-manager` (main list) renders normally.
6. Verify plugin activation/deactivation cycle works with no fatal errors or admin_notices.

### Complexity Tracking

None. No Constitution violations require justification.

## Post-design Constitution re-check

Re-checking against the completed Phase 1 design:

- §I Modular Architecture: still PASS.
- §II WPCS: still PASS.
- §III User-Centric Design: still ACCEPTED DEVIATION via `DEC-DESIGN-OVERRIDES-DATAVIEWS`.
- §IV Security First: still PASS. Freemius removal REDUCES surface.
- §V Extensibility: still PASS.
- §VI DRY: still PASS.
- §VII DoD: quality gates all green.

No re-check regressions. Ready for `/speckit-tasks`.

## References

- Spec: [spec.md](./spec.md)
- Memory Synthesis: [memory-synthesis.md](./memory-synthesis.md)
- Security Review: [security-constraints.md](./security-constraints.md)
- Architecture Review: [architecture-review.md](./architecture-review.md)
- Original planning artifact: `/Users/raftaar1191/.claude/plans/so-here-i-am-witty-koala.md` (approved plan, revised in-implementation by user requests to add header-row layout + self-filter + subsequent version bumps)
- `acrossai-co/main-menu` package README: `vendor/acrossai-co/main-menu/README.md`
- `\AcrossAI_Main_Menu\AddonsPageRenderer` filter contract docblock: `vendor/acrossai-co/main-menu/src/AddonsPageRenderer.php`

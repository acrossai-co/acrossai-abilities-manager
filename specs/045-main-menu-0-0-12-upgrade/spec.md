# Feature Specification: Upgrade `acrossai-co/main-menu` 0.0.11 → 0.0.13 (Preserve Abilities Settings Tab)

**Feature Branch**: `045-main-menu-0-0-12-upgrade`
**Created**: 2026-07-08
**Status**: Planned
**Input**: User description: "`acrossai-co/main-menu`: `0.0.11` got updated to `0.0.13`. In the readme you will know how to add a new tab into the settings menu as it added some based class like of thing. `admin.php?page=acrossai-settings&tab=abilities`. After update make sure this works well."

## Overview

Bump the composer requirement `acrossai-co/main-menu` from `0.0.11` to `0.0.13` and migrate the internal call sites for two related API changes:

1. **0.0.12 API change** — the removed static `SettingsPage::tab_page_slug()` helper was replaced by an instance method on `SettingsPageRenderer`, accessed via the new `SettingsPage::get_settings_renderer()` static accessor.
2. **0.0.13 API change** — tabbed rendering now uses a per-tab `option_group` (tab-scoped slug) instead of the shared `'acrossai-settings'`. This fixes a cross-tab option-clobber bug where saving one tab silently wiped other tabs' options. Consumer plugins must pass the tab-scoped slug as the `option_group` argument to `register_setting()`.

The Abilities tab already exists on the shared Settings page (`wp-admin/admin.php?page=acrossai-settings&tab=abilities`) — this feature preserves that tab's behaviour AND fixes the cross-tab clobber for admins who have this plugin active alongside other AcrossAI plugins.

**Historical note:** 0.0.12 was briefly staged during this feature's development. It was superseded before merge by 0.0.13 (same session, once the cross-tab clobber bug was discovered during T009 verification). The commit history on this branch shows the intermediate 0.0.12 state.

`0.0.13` also introduces a new abstract base class `\AcrossAI_Main_Menu\TabbedPageRenderer` used by the new `SettingsPageRenderer` internally. That abstraction is **not** consumed here (it's the plumbing for adding *additional* tabbed admin pages, not for extending the existing Settings page). The existing `acrossai_settings_tabs` filter is unchanged and remains the correct extension point for our Abilities tab.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin opens the Abilities tab on the shared Settings page after upgrade (Priority: P1)

An admin navigates to `wp-admin/admin.php?page=acrossai-settings&tab=abilities` (their existing bookmark). The nav-tab-wrapper shows the "Abilities" tab active. Below, the "Display Settings" section (with the "Abilities per page" field) and the "Uninstall Settings" section (with the "Delete all data on uninstall" checkbox) render exactly as they did on 0.0.11. Changing the per-page value from 20 → 50 and clicking **Save Changes** persists the option; the page reloads with the tab still active and the new value populated.

**Why this priority**: this is the singular acceptance criterion — the URL and settings surface must survive the package upgrade with zero visible regression. If either the tab or its fields disappear, the upgrade has broken the admin's daily workflow.

**Independent Test**: on a WP install with only this plugin active (and no sibling registering a tab on the shared page), navigate to the Abilities tab URL. Both sections must render, the field must accept and persist a new value, and the reloaded page must show the same tab active.

**Acceptance Scenarios**:

1. **Given** this plugin is at 0.0.13 and active, **When** the admin visits `?page=acrossai-settings&tab=abilities`, **Then** the "Abilities" tab is present and active, "Display Settings" and "Uninstall Settings" sections render with their fields, and no PHP notice/warning/fatal is emitted (`WP_DEBUG_LOG` clean).
2. **Given** the admin is on the Abilities tab, **When** they change "Abilities per page" to `50` and click **Save Changes**, **Then** the option `acrossai_abilities_per_page` persists as `50`, the success notice appears, the URL still carries `tab=abilities`, and the field renders `50` on the reload.
3. **Given** the admin is on the Abilities tab, **When** they check the "Delete all data on uninstall" checkbox and click **Save Changes**, **Then** the option `acrossai_abilities_uninstall_delete_data` persists as `1`, and the checkbox is checked on reload.
4. **Given** the admin bookmarks `?page=acrossai-settings&tab=abilities` and opens it from a fresh browser tab, **When** the page loads, **Then** the Abilities tab is active on the first render (no flash of a different tab).

### User Story 2 — Grep confirms zero calls to the removed static method (Priority: P2)

A future maintainer running `composer require acrossai-co/main-menu:^0.0.13` on a downstream site sees no PHP fatal originating from this plugin's PHP surface. Grep for the removed method returns no application-code hits.

**Why this priority**: this is the belt-and-braces guarantee that the migration is complete. A single missed call site would surface as `Uncaught Error: Call to undefined method AcrossAI_Main_Menu\SettingsPage::tab_page_slug()` on the very hook (`admin_init`) that the Settings page relies on.

**Independent Test**: run `grep -rn "SettingsPage::tab_page_slug" --include="*.php" .` from the plugin root. Expected: zero hits in `admin/`, `includes/`, `public/`, `tests/` (only `vendor/` may show hits — and after upgrade, even that path won't contain the removed static any more).

### Edge Cases

- **Sibling plugin coordination hazard**: `jetpack-autoloader` picks the highest version of `acrossai-co/main-menu` across all active plugins at runtime. If this plugin bumps to 0.0.13 while the sibling `acrossai-mcp-manager` still pins 0.0.11, the 0.0.13 code will be loaded for **both** plugins. The sibling still calls the removed `SettingsPage::tab_page_slug()` static at its own `admin/Partials/SettingsMenu.php:113` — that call will fatal on `admin_init` the moment either plugin's Settings tab is rendered. **This feature explicitly ships this plugin only** (per user decision); the sibling upgrade is a mandatory follow-up documented in the PR body and in the assumptions block below.
- **`SettingsPage::get_settings_renderer()` returns null**: the 0.0.13 README's example (README.md:172-175) shows a null-guard because the static returns null when no `SettingsPage` instance has been constructed in the current request. Our bootstrap in `acrossai-abilities-manager.php:142-154` constructs `SettingsPage` on `plugins_loaded` priority 0, so by `admin_init` the renderer is available. But the guard is still added defensively — it makes the code robust to any future boot-ordering change or environment where the package silently fails to boot.
- **`0.0.11` still pinned in `composer.lock` after the `composer.json` bump**: `composer update` (not `composer install`) is required to resolve the new version. Running `composer install` after the `composer.json` edit will refuse to install because the lock is out of date.
- **Freemium/Freemius platform deps**: `0.0.13` still requires `freemius/wordpress-sdk: ^2.0` and `automattic/jetpack-autoloader: ^5.0` — the same as `0.0.11`. No transitive-dep changes expected.
- **`acrossai_settings_tabs` filter**: unchanged in 0.0.13. Our `register_tab()` callback at `admin/Partials/SettingsMenu.php:83-95` continues to fire and continues to shape the tab entry exactly the same way (`slug`, `label`, `priority`).
- **Tab entry `capability` key**: 0.0.13 adds an optional `'capability' => 'manage_options'` gate. We do NOT add one here — the existing tab is intended to be visible to any user with settings access (WP's default `manage_options`, which is already the Settings page's own guard), so an extra per-tab capability check would be redundant.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `composer.json` MUST pin `acrossai-co/main-menu` to `"0.0.13"` (exact version, not a range). Rationale: the existing style pins `0.0.11` exactly; keeping the same style avoids accidental auto-upgrade to a future breaking release.
- **FR-002**: `composer.lock` MUST be regenerated via `composer update acrossai-co/main-menu --with-dependencies` so the lockfile's resolved version and reference SHA (`12c05c9d...`) match the tag `0.0.13` on `github.com/acrossai-co/main-menu`.
- **FR-003**: `vendor/acrossai-co/main-menu/` MUST be regenerated. Expected additions: `src/SettingsPageRenderer.php`, `src/TabbedPageRenderer.php`. Expected removal: `src/PageRenderer.php`. Verified against the 0.0.13 tag on GitHub.
- **FR-004** (0.0.12 API migration): `admin/Partials/SettingsMenu.php::register_settings()` MUST replace the removed static call `\AcrossAI_Main_Menu\SettingsPage::tab_page_slug( self::TAB_SLUG )` with the new instance-method form:
  ```php
  $renderer = \AcrossAI_Main_Menu\SettingsPage::get_settings_renderer();
  if ( ! $renderer ) {
      return;
  }
  $page_slug = $renderer->tab_page_slug( self::TAB_SLUG );
  ```
  The null-guard is REQUIRED — matches the 0.0.13 README's recommended pattern and avoids a fatal in edge boot-order scenarios.
- **FR-005**: The docblock on `register_settings()` (currently lines 97-107) MUST be updated to reference the new API (`SettingsPage::get_settings_renderer()->tab_page_slug()`) and to bump the "since acrossai-co/main-menu v0.0.4+" line to `v0.0.13+`.
- **FR-006**: The `acrossai_settings_tabs` filter registration at `includes/Main.php:309` and the `register_tab()` callback at `admin/Partials/SettingsMenu.php:83-95` MUST remain unchanged. The tab slug (`abilities`), label, and priority MUST NOT change.
- **FR-007** (0.0.13 API migration): The `option_group` argument passed to `register_setting()` MUST be the tab-scoped slug returned by `$renderer->tab_page_slug( self::TAB_SLUG )` — the same slug already used as the section `$page` argument. Rationale: 0.0.13's `TabbedPageRenderer::render()` calls `settings_fields( $tab_scoped )` (not `$page_slug`), so options registered against the shared `'acrossai-settings'` would land in the wrong whitelist and silently no-op on save. This is a breaking change from 0.0.12; the 0.0.13 README's "Migrating from 0.0.12" section documents the one-line migration.
- **FR-008**: No new PHP hooks, no new REST routes, no new DB tables, no new option names, no new admin partial classes. This is a dependency bump + one-line API migration.
- **FR-009**: No new JS bundle, no new SCSS class, no new webpack entry. The rebuild pipeline is not exercised by this feature.
- **FR-010**: No changes to `acrossai-mcp-manager`. That plugin's own migration is a mandatory follow-up (see Assumptions).

### Key Entities

- **`\AcrossAI_Main_Menu\SettingsPage`** (external, 0.0.13) — public entrypoint with `PARENT_SLUG` + `SETTINGS_SLUG` constants and the new `get_settings_renderer(): ?SettingsPageRenderer` accessor.
- **`\AcrossAI_Main_Menu\SettingsPageRenderer`** (external, 0.0.13 NEW) — `final` subclass of `TabbedPageRenderer` pinning the Settings page's slug + tabs-filter key. Exposes the instance method `tab_page_slug( string $tab_slug ): string` used by consumer plugins.
- **`\AcrossAI_Main_Menu\TabbedPageRenderer`** (external, 0.0.13 NEW) — abstract base for building additional tabbed admin pages. Not consumed by this feature; documented here so future readers understand what "based class like of thing" in the user's brief referred to.
- **`\AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu`** — this plugin's tab-registration + settings-registration class. The only file changed by this feature (aside from composer.json/lock/vendor).
- **`acrossai_settings_tabs` filter** — the extension point the package exposes for third-party tab registration. Unchanged between 0.0.11 and 0.0.13.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After `composer update`, `composer.lock` shows `"name": "acrossai-co/main-menu"` with `"version": "0.0.13"` and the source `reference` matching the 0.0.13 tag SHA (`12c05c9d...`).
- **SC-002**: `vendor/acrossai-co/main-menu/src/` contains `SettingsPageRenderer.php` and `TabbedPageRenderer.php`; does NOT contain `PageRenderer.php` (removed in 0.0.13).
- **SC-003**: `grep -rn "SettingsPage::tab_page_slug" --include="*.php" .` returns zero hits inside this plugin's application code (`admin/`, `includes/`, `public/`, `tests/`).
- **SC-004**: Navigating to `wp-admin/admin.php?page=acrossai-settings&tab=abilities` renders the "Abilities" tab as active; the "Display Settings" section with "Abilities per page" field and the "Uninstall Settings" section with the "Delete all data on uninstall" checkbox both render.
- **SC-005**: Saving a changed "Abilities per page" value persists to the `acrossai_abilities_per_page` option and reloads with the tab still active.
- **SC-006**: `WP_DEBUG_LOG` is clean after visiting the Settings tab URL — zero `Uncaught Error: Call to undefined method` entries and zero deprecation notices from `main-menu`.
- **SC-007**: PHP test suite (`composer test`) still passes at 105 tests. Zero new/edited tests.
- **SC-008**: PHPCS (`composer lint`) and PHPStan (`composer phpstan`) succeed with no new warnings on the changed file.

- **SC-009**: Cross-tab option-clobber does NOT occur (0.0.13 regression guard). On an install with this plugin + `acrossai-core-abilities` both active: saving the `?tab=core` form does NOT reset `acrossai_abilities_uninstall_delete_data` to `0`. Symmetrically, saving the `?tab=abilities` form does NOT wipe core-abilities' options. Verified via `wp option get` before/after each tab save (or via the checkbox state on reload).

## Assumptions

- **Sibling plugin coordination is a mandatory follow-up, out of scope for this feature.** `acrossai-mcp-manager` still pins `acrossai-co/main-menu: 0.0.11` and still calls the removed `SettingsPage::tab_page_slug()` static at its own `admin/Partials/SettingsMenu.php:113`. Because `jetpack-autoloader` picks the highest version across all active plugins at runtime, shipping this bump will make the sibling's Settings tab (`?page=acrossai-settings&tab=mcp`) fatal until it is migrated in the same way. The PR body MUST call this out as a **blocking release note** — do not deploy to production without the sibling migration already queued.
- **The Abilities tab URL `?page=acrossai-settings&tab=abilities` is the canonical bookmark** for this plugin's settings surface. Regressing it would break admins' existing bookmarks — hence the Priority-P1 scenario centered on that URL.
- **`get_settings_renderer()` null-guard is defensive.** Per the 0.0.13 source, the accessor returns non-null once `new SettingsPage()` has been constructed once in the request. Our plugin bootstrap on `plugins_loaded` priority 0 does that. But we keep the guard so that if a future edit re-orders boot, the plugin fails silently (no Settings API round-trip) instead of fataling.
- **No new memory pattern warranted at n=1.** First composer bump of `main-menu` in this repo's spec-kit history. If a second breaking bump ships, capture a `PATTERN-MAIN-MENU-BUMP-CHECKLIST` then (bump `composer.json`, `composer update --with-dependencies`, grep-scan for removed APIs, refactor call sites, verify tab URL).
- **`TabbedPageRenderer` is NOT consumed by this feature.** It's the plumbing beneath `SettingsPageRenderer` and would only be relevant if we wanted to add a NEW tabbed admin page (e.g. a "Tools" page). Adding the Abilities tab to the existing Settings page uses the `acrossai_settings_tabs` filter, which is unchanged.
- **PHPUnit test count holds at 105.** No new tests are added — the change is a composer bump + one-file refactor. Existing coverage of `SettingsMenu::register_settings()` (if any) is inherited unchanged.

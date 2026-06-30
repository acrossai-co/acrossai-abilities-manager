# Feature Specification: AcrossAI Main Menu Integration

**Feature Branch**: `038-acrossai-main-menu-integration`
**Created**: 2026-06-30
**Status**: Draft
**Input**: User description: "Adopt acrossai-co/main-menu v0.0.2 as the shared top-level admin menu and unified Settings host. Re-parent Library, Logs, Add-ons under the new `acrossai` menu. Convert the existing top-level Abilities Manager page into a submenu titled `Abilities`. Remove the custom Settings page and re-register the three settings (per_page, log_retention_days, uninstall_delete_data) against the host's Settings API page/group slug `acrossai-settings` so they render as sections inside the shared host page. Preserve all three option names so existing user values continue to resolve. Patch the two hardcoded hook_suffix comparisons in admin/Main.php to match the new parent. Also fix the boot-time fatal that occurs when the composer autoloader is missing (`AcrossAI_Loader` class-not-found at `includes/Main.php:228`)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Unified AcrossAI menu for administrators (Priority: P1)

A WordPress site administrator who has installed the Abilities Manager plugin (and may install other AcrossAI-branded plugins in the future) sees a single, consolidated "AcrossAI" entry in the admin sidebar. Expanding it reveals every AcrossAI surface — Settings, Abilities, Library, Logs, Add-ons — in a predictable order, instead of separate top-level entries that compete for sidebar space.

**Why this priority**: This is the central business goal. As more AcrossAI plugins are released, the existing pattern (each plugin owns a top-level entry) becomes unmanageable and visually noisy. Consolidating under one parent is what every other downstream menu/setting decision serves.

**Independent Test**: Activate the plugin on a fresh WordPress install where the host menu package (`acrossai-co/main-menu`) is also present. Observe the admin sidebar. The test passes when "Abilities Manager" no longer appears as a top-level entry and the AcrossAI top-level menu shows submenus in the order: Settings → Abilities → Library → Logs → Add-ons.

**Acceptance Scenarios**:

1. **Given** the plugin is freshly activated with the host menu package present, **When** the administrator opens the admin sidebar, **Then** a single "AcrossAI" top-level entry exists, "Abilities Manager" no longer appears as a sibling top-level entry, and the AcrossAI submenu order is Settings → Abilities → Library → Logs → Add-ons.
2. **Given** the administrator clicks each AcrossAI submenu in turn, **When** each page loads, **Then** the page renders correctly with its scripts and styles enqueued, no PHP notices appear in the error log, and the breadcrumb / page title matches the clicked submenu.

---

### User Story 2 - Existing settings survive the upgrade (Priority: P1)

A site administrator who has previously configured the plugin (set a custom items-per-page count, adjusted log retention, opted in to delete-on-uninstall) upgrades to the version that adopts the shared menu. After upgrade, every value the administrator previously saved continues to apply — without re-entering anything, without running any migration tool, and without the form fields appearing blank.

**Why this priority**: Data preservation is non-negotiable. A regression here translates directly into user complaints, support burden, and broken downstream behaviour (logs not pruning, list views showing the wrong page size, uninstall failing to clean up). Tied with US1 at P1 because shipping US1 without US2 would be a net negative.

**Independent Test**: On an install with the prior version, set per-page to a non-default value, set log retention to a non-default value, and toggle the uninstall option. Upgrade. Navigate to the new shared Settings page. The test passes when all three fields display the previously saved values, no fields are blank, and changing a field and saving round-trips correctly.

**Acceptance Scenarios**:

1. **Given** prior-version values exist for the three plugin options, **When** the administrator opens the new shared Settings page, **Then** each field displays its prior-version value, no field is reset to a default, and no migration prompt is shown.
2. **Given** the administrator changes a setting on the new shared Settings page and clicks Save, **When** the page reloads, **Then** the new value displays and a subsequent admin request reading the same option returns the new value.

---

### User Story 3 - Plugin never WSODs when its dependencies are absent (Priority: P1)

A developer who clones the plugin source into a local site without first running `composer install`, or a site operator who deploys with the `vendor/` directory stripped, attempts to activate or load the plugin. Instead of producing a PHP fatal error and a white-screen-of-death on the Plugins screen, the admin sees a clear, actionable error message telling them what to do (run `composer install` to restore the autoloader). The rest of the WordPress admin remains accessible.

**Why this priority**: The current code path produces a hard fatal at `includes/Main.php:228` when the composer autoloader file is absent — the existing reproducer trace from 30 June 2026 confirms this. A site operator who triggers this loses access to the admin entirely until they SSH in and either restore vendor or rename the plugin directory. P1 because it blocks any safe demo/clone/deploy that misses a single composer step, and §V Integration Resilience in the project Constitution mandates graceful degradation.

**Independent Test**: On a copy of the plugin source, delete or rename the `vendor/` directory. Attempt to activate the plugin from the Plugins screen. The test passes when activation is blocked with a human-readable error message (no WSOD, no fatal in the PHP log), the Plugins screen remains usable, and restoring `vendor/` allows normal activation immediately afterward.

**Acceptance Scenarios**:

1. **Given** the `vendor/` directory is absent and the plugin is currently inactive, **When** the administrator clicks Activate on the Plugins screen, **Then** activation is blocked with a friendly error explaining that `composer install` must be run first, no PHP fatal is recorded, and no WSOD occurs.
2. **Given** the `vendor/` directory is removed from an already-active plugin (e.g. by an incomplete deploy), **When** the administrator next loads any admin page, **Then** the WP admin renders normally, an error-class admin notice appears explaining the missing autoloader and the remediation step, no fatal is recorded, and other plugins continue to function.
3. **Given** the administrator restores the `vendor/` directory (re-runs `composer install`), **When** they next load an admin page, **Then** the plugin boots normally, the missing-autoloader notice disappears, and all menu entries and settings appear as expected.

---

### User Story 4 - Existing bookmarks and external links keep working (Priority: P2)

An administrator (or external tooling, support documentation, browser bookmark) that previously deep-linked into the plugin via `wp-admin/admin.php?page=acrossai-abilities-manager`, `wp-admin/admin.php?page=acrossai-abilities-library`, or `wp-admin/admin.php?page=acrossai-abilities-logs` continues to reach the same page after upgrade. The page slug remains stable; only its parent in the menu hierarchy changes.

**Why this priority**: Important for continuity and the plugin-action-links row on the Plugins screen, but a degradation here (bookmarks 404) is recoverable by the user re-finding the page in the sidebar. P2 because the impact is "annoying" rather than "blocking", and an explicit constraint of this work is preserving these slugs.

**Independent Test**: Before upgrade, bookmark each of the three URLs above. After upgrade, click each bookmark. The test passes when each URL loads the correct page contents (Abilities Manager UI, Library list, Logs list) without 404 or redirect.

**Acceptance Scenarios**:

1. **Given** a bookmarked URL pointing to the abilities, library, or logs page, **When** the administrator clicks the bookmark post-upgrade, **Then** the matching page renders with its full UI and scripts/styles loaded.
2. **Given** a user visits the old standalone Settings URL `wp-admin/admin.php?page=acrossai-abilities-settings` (which is no longer registered), **When** the request resolves, **Then** WordPress returns its standard "you do not have sufficient permissions to access this page" / "page does not exist" response, and no part of the plugin attempts to render a fallback at that slug.

---

### Edge Cases

- The composer autoloader file is missing → admin notice appears, no fatal, plugin runs in degraded state with no menus until restored.
- The host menu package class `\AcrossAI_Main_Menu\SettingsPage` is not autoloadable (vendor is present but the package is absent) → top-level "AcrossAI" entry is not created; the plugin's submenu registrations are skipped or no-op'd silently so the admin still has a working WP admin, mirroring the existing AddonsPage graceful-degradation pattern.
- A site operator runs the plugin on a multisite network → the plugin must not auto-deactivate itself when vendor is missing, to avoid cascading deactivation across subsites.
- An administrator visits the legacy Settings URL `wp-admin/admin.php?page=acrossai-abilities-settings` after upgrade → standard WP "does not exist" response; no fallback handler is registered.
- The page-gated script/style enqueue checks must match the new admin-page hook suffixes derived from the new parent slug, or the React UI on the Abilities page fails to load even though the page itself opens.
- Existing settings option values must survive even though the option_group (used for `register_setting` and the form's `option_page` hidden field) changes — only option names guarantee data persistence; option_group changes do not lose data because option_group affects how the form submits, not what is stored.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST surface its admin entries (Abilities, Library, Logs, Add-ons) as submenus under a single shared top-level "AcrossAI" parent menu, owned by the `acrossai-co/main-menu` package, rather than as separate top-level entries.
- **FR-002**: The plugin MUST contribute its three configuration settings (display per-page, log retention days, uninstall-delete-data) to the shared Settings page hosted by the menu package, with the settings appearing as standard WordPress Settings API sections and fields inside that host page.
- **FR-003**: The plugin MUST preserve the existing option names (`acrossai_abilities_log_retention_days`, `acrossai_abilities_uninstall_delete_data`, `acrossai_abilities_per_page`) so that values configured on prior versions resolve unchanged after upgrade.
- **FR-004**: The plugin MUST preserve the existing menu slugs for the abilities, library, and logs pages so that bookmarked URLs and the plugin-action-links row continue to resolve to the correct pages.
- **FR-005**: When the menu package's host class is not loadable (vendor missing the package), the plugin MUST degrade gracefully — submenus may be absent, but no PHP fatal MUST occur and the rest of the WP admin MUST remain functional. The pattern MUST mirror the existing `class_exists`-guarded AddonsPage bootstrap.
- **FR-006**: When the composer autoloader file (`vendor/autoload_packages.php`) is absent at plugin load, the plugin MUST display an error-class admin notice on every admin screen instructing the administrator to run `composer install`, MUST NOT register its menus or hooks (preventing the existing `AcrossAI_Loader` class-not-found fatal), and MUST NOT auto-deactivate itself.
- **FR-007**: When the composer autoloader file is absent at activation time, the plugin's activation hook MUST block activation with a friendly `wp_die`-style error message instructing the administrator to run `composer install`, rather than allowing partial/broken activation.
- **FR-008**: The plugin MUST gate its page-specific script and style enqueues on the new admin hook-suffix strings derived from the relocated Abilities page (now a submenu of the AcrossAI parent) and the relocated Settings page (now the host's shared Settings page), so the React UI and styles continue to load on the correct screens and only on those screens.
- **FR-009**: The plugin MUST NOT register a fallback handler for the legacy standalone Settings URL (`wp-admin/admin.php?page=acrossai-abilities-settings`). Requests to that URL MUST receive WordPress's standard "page does not exist" response.
- **FR-010**: The plugin MUST NOT introduce a new JavaScript bundle, viewScript module, or build-step artifact as part of this integration. The host menu package is pure server-side rendering and no client-side handles or fills exist to depend on.
- **FR-011**: The plugin MUST NOT modify any file under `vendor/`, including the vendored `acrossai-co/addons-page` package and the new `acrossai-co/main-menu` package. Vendor code is owned by its publisher; integration must be entirely from the consuming side.
- **FR-012**: The plugin's admin sidebar ordering under the AcrossAI parent MUST be: Settings (host-owned, first), Abilities (this plugin, second), Library (this plugin, third), Logs (this plugin, fourth), Add-ons (vendor package, last). Ordering is enforced via the explicit `$position` arg passed to `add_submenu_page()`.
- **FR-013**: All user-facing strings introduced or modified by this work MUST be wrapped in `__()` / `esc_html__()` calls using the `'acrossai-abilities-manager'` text domain.

### Key Entities *(include if feature involves data)*

- **AcrossAI top-level menu**: The shared parent admin menu entry, owned and registered by the `acrossai-co/main-menu` package. Identified by parent slug `acrossai`. Survives the lifetime of any consuming AcrossAI plugin; constructed once on `plugins_loaded` priority 0.
- **Shared Settings page**: The host page rendered by the menu package at `wp-admin/admin.php?page=acrossai-settings`. Standard WordPress Settings API host. Both the option_group (passed to `register_setting`) and the `$page` slug (passed to `add_settings_section` / `add_settings_field`) are the unified string `acrossai-settings`. Owned by the package; this plugin contributes sections and fields but does not render or own the page itself.
- **Abilities Manager submenu**: This plugin's primary admin UI — a React-driven page rendering at menu slug `acrossai-abilities-manager`, now reparented from a top-level entry to a submenu under `acrossai`.
- **Library submenu**: This plugin's read-only browse/filter UI at menu slug `acrossai-abilities-library`, reparented under `acrossai`.
- **Logs submenu**: This plugin's execution-log table at menu slug `acrossai-abilities-logs`, reparented under `acrossai`.
- **Add-ons submenu**: Vendored Freemius/Addons UI at menu slug `wpb-addons`, reparented under `acrossai` via constructor argument change.
- **Plugin settings**: Three persisted options (`acrossai_abilities_per_page`, `acrossai_abilities_log_retention_days`, `acrossai_abilities_uninstall_delete_data`) with their existing names, default values, sanitizer callbacks, and section identifiers preserved. Only the option_group and `$page` slug they are registered against change.
- **Composer autoloader**: The PSR-4 autoloader file (`vendor/autoload_packages.php`) responsible for mapping this plugin's own namespaces (e.g. `AcrossAI_Abilities_Manager\Includes\AcrossAI_Loader`) to their file paths. Its presence is a hard prerequisite for the plugin to load any of its own classes.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of pre-upgrade values for `acrossai_abilities_per_page`, `acrossai_abilities_log_retention_days`, and `acrossai_abilities_uninstall_delete_data` resolve unchanged after upgrade, verified by reading each option via WP-CLI or an equivalent direct option-table query before and after upgrade.
- **SC-002**: An administrator can navigate from any WP admin screen to any of the five AcrossAI surfaces (Settings, Abilities, Library, Logs, Add-ons) in two clicks or fewer — one click to expand the AcrossAI parent, one click to select the submenu.
- **SC-003**: Zero PHP fatal errors are produced by the plugin when `vendor/autoload_packages.php` is absent, across the full admin lifecycle (activation attempt, normal admin page load, deactivation, reactivation after vendor restoration). The current reproducer trace (`AcrossAI_Loader` class-not-found at `includes/Main.php:228`) MUST no longer occur under any sequence of those operations.
- **SC-004**: 100% of pre-upgrade bookmarked admin URLs for the abilities, library, and logs pages resolve to their respective page contents (HTTP 200, correct page rendered) on first request after upgrade, with no 404 and no redirect.
- **SC-005**: No new PHPStan level-8 errors, no new PHPCS errors, and no new `npm run validate-packages` failures are introduced by the change set. Existing baselines (if any) remain at their pre-change counts.
- **SC-006**: The plugin-action-links row on the Plugins screen (Settings, Logs) continues to resolve to working pages after upgrade — verified by clicking each link on a freshly-upgraded install.

## Assumptions

- The detailed implementation breakdown (TASK-1 through TASK-6), file paths, exact line numbers, and constraint list captured in `docs/planning/038-acrossai-main-menu-integration.md` are authoritative for the planning and tasks phases. The plan and tasks artifacts will reference that document; the spec above intentionally stays at the WHAT/WHY level so it remains stable as implementation details evolve.
- The host menu package `acrossai-co/main-menu` v0.0.2 is pure server-side PHP and exposes the public class `\AcrossAI_Main_Menu\SettingsPage` with constants `PARENT_SLUG === 'acrossai'` and `SETTINGS_SLUG === 'acrossai-settings'`. No host-side JS handle or Fill bundle exists in v0.0.2.
- The host's Settings page is a standard WordPress Settings API page where both the option_group (passed to `register_setting()`) and the `$page` slug (passed to `add_settings_section()` and `add_settings_field()`) are the unified string `'acrossai-settings'`.
- The plugin already requires `automattic/jetpack-autoloader: ^5.0`, so the new menu package boots via the existing `vendor/autoload_packages.php` require at `includes/Main.php:206` without needing a second include.
- When in degraded mode (vendor missing), the plugin will NOT auto-deactivate itself. WordPress will keep it "active" in the plugins list with a persistent admin notice. This mirrors the WooCommerce missing-dependency pattern and avoids cascading deactivation on multisite networks.
- The legacy standalone Settings page slug (`acrossai-abilities-settings`) will not have a fallback handler registered. Users hitting old links get the standard WP "does not exist" response — an acceptable trade-off given the brief absence between this release and stable URL discovery.
- All Definition-of-Done gates from §VII of `CONSTITUTION.md` (PHPStan level 8, PHPCS, security review, correct text domain on `__()` calls, `npm run validate-packages`) apply to this change set and will be enforced per-task, not batched at the end.
- The reproducer trace in `docs/planning/038-acrossai-main-menu-integration.md` TASK-6 (timestamp 30-Jun-2026 11:46:52 UTC, `AcrossAI_Loader` class-not-found at `includes/Main.php:228`) is the canonical regression test for the boot-resilience requirement (FR-006, FR-007, SC-003).

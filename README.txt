=== AcrossAI Abilities Manager ===
Contributors: raftaar1191
Donate link: https://github.com/acrosswp/acrossai-abilities-manager
Tags: abilities, ability management, access control, site management, ai
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage every WordPress ability registered on your site — view, search, override, and bulk-control ability metadata from a single admin page.

== Description ==

AcrossAI Abilities Manager gives site administrators full visibility and control over every ability registered via the WordPress Abilities API (`wp_get_ability()`).

**Features:**

* **Browse all abilities** — a searchable, sortable, paginated table listing every registered ability with slug, provider, source, and current status.
* **Toggle allow/disallow** — enable or disable any ability site-wide with a single click. Changes are saved instantly without a page reload.
* **Edit ability metadata** — override `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type`, and `mcp_servers` fields per ability using a tri-state system (Yes / No / Inherit from registry).
* **Reset overrides** — restore any ability back to its registry defaults with one click.
* **Bulk actions** — allow, disallow, or reset up to 50 abilities at once.
* **Ability Library** — enable or disable add-on ability groups from a dedicated Library page, with All/Specific mode controls per group.
* **Add-ons page** — browse, install, and manage free and premium add-ons directly from the WordPress admin. Powered by [wpb-addons-page](https://github.com/WPBoilerplate/wpb-addons-page).
* **MCP server list** — view all registered MCP servers when the MCP Adapter plugin is active.

All overrides are stored in a dedicated database table. The WordPress ability registry is never modified — only the fields that differ from registry defaults are persisted.

**Security:**

* All endpoints require `manage_options` capability.
* All state-changing requests are protected by WordPress nonce verification.
* All input is sanitized; all output is escaped.

**Third-party integrations (optional):**

* **MCP Adapter plugin** — if active, the plugin displays a list of registered MCP servers inside the ability edit panel. No data is sent to any external service. The MCP Adapter plugin communicates only with your own WordPress installation.
* **Freemius** — the Add-ons page uses Freemius to handle paid add-on purchases, license management, and automatic updates. Data is only sent after explicit user action (see "External Services" below).

No data is sent to any external server automatically by this plugin.

== Installation ==

1. Upload the `acrossai-abilities-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **AcrossAI Abilities Manager** in the WordPress admin menu.

**Add-ons:**

1. Go to **AcrossAI Abilities Manager → Add-ons** to browse available add-ons.
2. Free add-ons can be installed with one click via the standard WordPress plugin installer.
3. Paid add-ons require connecting your account — click **Buy** on any paid add-on to begin.

== Frequently Asked Questions ==

= Does this plugin support Multisite? =

No. This plugin has not been tested on WordPress Multisite installations.

= Does this plugin modify the WordPress ability registry? =

No. The plugin stores only overrides — fields that differ from the registry defaults. The ability registry itself (`wp_get_ability()`) is never modified.

= What happens when I reset an override? =

The override row is deleted from the database. The ability will inherit its values from the registry again.

= What is the Ability Library? =

The Library page lets you enable or disable ability groups registered by add-on plugins. Each group shows an ON/OFF master toggle and an All/Specific mode selector. In Specific mode, individual ability slots can be toggled independently.

= What is the MCP Adapter integration? =

If the MCP Adapter plugin is active on your site, AcrossAI Abilities Manager will display the list of registered MCP servers in the ability edit panel. This is entirely optional — the plugin works without the MCP Adapter.

= Does this plugin make external HTTP requests? =

Only when you explicitly interact with the Add-ons page to connect your account or purchase a paid add-on. In that case, data is sent to Freemius (see "External Services"). No external requests are made otherwise.

== Screenshots ==

1. The Abilities Manager admin page — searchable, sortable ability table.
2. The edit drawer — tri-state override controls for each ability field.
3. Bulk actions toolbar for allow/disallow/reset across multiple abilities.
4. The Ability Library page — enable/disable add-on ability groups.
5. The Add-ons page — browse free and premium add-ons.

== External Services ==

**Freemius** (https://freemius.com)

The Add-ons page is powered by [wpb-addons-page](https://github.com/WPBoilerplate/wpb-addons-page), which integrates with Freemius to handle paid add-on purchases, license management, and automatic updates for premium add-ons.

When a user chooses to connect their account or purchase a paid add-on, the following data is sent to Freemius servers:

- WordPress admin email address
- Site URL
- WordPress and PHP version

This only happens after explicit user action (clicking **Login / Connect** or **Buy** on the Add-ons page). No data is sent automatically on plugin activation or on any other page.

Freemius Terms of Service: https://freemius.com/terms/
Freemius Privacy Policy:   https://freemius.com/privacy/

Free add-ons listed on the Add-ons page are hosted on WordPress.org and installed via the standard WordPress plugin installer. No external service is involved for free add-ons.

== Privacy Policy ==

This plugin does not collect or store any user data by itself.

If a user chooses to connect their account via the Add-ons page, data is handled by Freemius in accordance with their privacy policy:
https://freemius.com/privacy/

No data is sent to any external server without explicit user action.

== Changelog ==

= 0.0.7 =
* **Library page — bulk Enable All / Disable All action buttons.** A new right-aligned header row above the tab strip on `?page=acrossai-abilities-library` renders two side-by-side buttons that toggle every ability category currently in view with a single click. Actions are scoped to the active tab: on the `All` tab they touch every registered category; on a specific tab (Core, Blocks, Themes, Users, Cache, File Manager, Cron, Database, Plugins) they only touch categories whose ability metadata declares that `tab_group`. Categories in other tabs pass through byte-for-byte unchanged. Each category's mode (All / Specific) and per-slug selections are preserved on both actions — a Disable All → Enable All cycle is a lossless round-trip. Persisted via the existing `POST /acrossai-abilities-library/v1/abilities/config` REST route (`manage_options` + nonce, unchanged). See PR [#68](https://github.com/acrossai-co/acrossai-abilities-manager/pull/68).
* **URL-synced tabs on the Library page.** The active tab is now reflected in the browser URL as `?tab=<slug>`. Deep-linkable, bookmarkable, and browser back / forward navigation re-syncs the visible tab. Direct-navigation to `?page=acrossai-abilities-library&tab=themes` opens the Themes tab on first paint. Invalid tab values silently fall back to the default `All` view — no error, no console warning. The default `All` view keeps the canonical URL clean by removing the `tab` query arg entirely.
* **Disabled-card UI refresh on the Library page.** Disabled category cards now show the master toggle + category label + chevron (visible whenever the category has at least one registered ability). Expanding the chevron on a disabled card reveals a readonly bullet-style preview of the abilities in that category (with descriptions). The All / Specific mode selector and interactive per-ability checkboxes remain hidden while the card is disabled — no interactive control can render on a disabled card even when the stored mode is `Specific`. The stored mode and per-slug selections are preserved so re-enabling restores the prior configuration exactly. Manual per-card disable and bulk `Disable All` produce identical card DOM.

= 0.0.6 =
* **BREAKING (downstream integrators) — 17 ability category slugs rebranded from `acrossai-core-abilities-<domain>` to `acrossai-abilities-manager-<domain>`, and 176 ability slugs rebranded from `acrossai-core-abilities/<verb>` to `acrossai-abilities-manager/<verb>`.** The companion `acrossai-core-abilities` plugin's entire 201-file runtime (17 Category_Registrars, 176 ability classes, 8 helper classes, plus the extra-MIME-types admin field) is absorbed into this plugin. Every category and ability slug is renamed uniformly; ability payload shapes and permission callbacks are preserved verbatim. Downstream code (MCP servers, REST/WP-CLI callers, integration tests) that referenced the legacy `acrossai-core-abilities-*` slugs by string must update on cutover. Ability payloads themselves are unchanged. See PR [#65](https://github.com/acrossai-co/acrossai-abilities-manager/pull/65).
* **Absorbed extra-MIME-types Settings field lands under the Abilities tab.** The companion plugin's Core settings tab is retired; its "extra allowed upload MIME types" field now renders inside the shared Settings → Abilities tab. The companion's separate uninstall opt-in is folded into the manager's existing single `acrossai_abilities_uninstall_delete_data` opt-in — no second checkbox appears. Activation-time migration copies the legacy option (`acrossai_core_abilities_extra_mimes` → `acrossai_abilities_manager_extra_mimes`), OR-monotonically folds the legacy uninstall opt-in into the manager's opt-in (never demotes a manager-true value), and deletes both legacy option rows. Existing admin configuration is preserved. See PR [#65](https://github.com/acrossai-co/acrossai-abilities-manager/pull/65).
* **Retire the `acrossai-core-abilities` companion plugin.** After upgrading to 0.0.6, deactivate and uninstall the standalone `acrossai-core-abilities` plugin — all 176 abilities are now provided by this manager plugin directly. Keeping both plugins active will emit duplicate-registration notices from the WP Abilities API on every request. Removal of the companion plugin folder from production sites is an operational task, separate from this release.
* **Library page — Themes / Blocks / Plugins / Users / Database / Cron / Cache / File Manager get their own tabs.** The absorbed categories are promoted from the shared "Core" tab into their own top-level tabs on the Ability Library page (`?page=acrossai-abilities-library`). The "Core" tab stays pinned as the second option (immediately after "All") regardless of alphabetical ordering. The "No abilities registered yet" empty-state copy is updated for the new bundled reality.
* **Dependencies: `acrossai-co/main-menu` bumped from `0.0.11` to `0.0.14`.** Adopts the Tabs base class extraction (0.0.14) and tab-scoped `option_group` (0.0.13) — the latter fixes the cross-tab option-clobber bug where saving one Settings tab silently wiped other tabs' options. See PR [#66](https://github.com/acrossai-co/acrossai-abilities-manager/pull/66).
* **Freemius product identifiers rotated** — `fs_product_id` changed from `31230` to `34418`, `fs_public_key` rotated to `pk_d61a7ddb1a619f7697fbb4fc397b6`. If you have a Freemius account tied to the previous product ID, reconnect on the Account submenu after upgrade.

= 0.0.5 =
* **Dependencies: `acrossai-co/main-menu` bumped to `0.0.11`.** Picks up the latest AcrossAI shared parent menu / dashboard / settings / add-ons page code from that package. No plugin-owned code changes in this release — the bump is the only functional delta vs 0.0.4.

= 0.0.4 =
* **BREAKING (add-on developers) — Library display fields moved from top-level `$args` into `$args['meta']['acrossai']`.** The three Library-only fields introduced by Features 033 and 037 — `sub_group`, `sub_group_label`, and `tab_group` — are no longer read from the top level of the `$args` array passed to `wp_register_ability()`. They must now be nested under `$args['meta']['acrossai']`, matching the existing `meta.mcp` (MCP integration) and `meta.annotations` (WP-core annotations) convention. This is a hard cut with no back-compat shim: any add-on that still passes the fields at the top level will silently render its Library card without a sub-group heading or custom tab placement. Migration: change `'sub_group' => 'x'` to `'meta' => [ 'acrossai' => [ 'sub_group' => 'x' ] ]` (same for `sub_group_label` and `tab_group`). Only affects add-ons that extend `Ability_Definition` and use these Library display fields; abilities without them are unaffected. No end-user data migration, no DB schema change, no REST API change.
* **Plugin icon replaced with a vector (SVG) asset.** The WordPress.org plugin directory now serves `.wordpress-org/icon.svg` in place of the previous 128×128 / 256×256 JPG icons, so the icon renders sharp at any display density. Also removes the 772×250 and 1544×500 header banners from the directory listing — the plugin page will show the WordPress.org default header until banners are re-added. wp.org-assets-only change.

= 0.0.3 =
* **Fix: plugin now activates on installs from WordPress.org.** The 0.0.2 release ZIP shipped without the Composer autoloader (`vendor/autoload_packages.php`) because the WordPress.org deploy workflow did not run `composer install` before uploading. Users installing 0.0.2 from the WordPress.org plugin directory saw the plugin activation guard trigger: *"AcrossAI Abilities Manager cannot activate: the Composer autoloader is missing…"*. The 0.0.3 release ZIP includes the full production autoloader; no other code changes. If you already installed 0.0.2 and hit the activation error, delete the plugin folder and reinstall 0.0.3.

= 0.0.2 =
* **Composer dependency refresh** — `wpb-access-control` bumped to v2.0.0 (per-consumer database tables); `acrossai-co/main-menu` bumped to v0.0.10 (now bundles the Add-ons page and includes the JS-side rebrand-sync fix that restores Install / Activate / Deactivate button behavior). The standalone `acrossai-co/addons-page` package has been removed from direct dependencies; the same `AcrossAI_Addon\AddonsPage` class now ships from the `main-menu` package.
* **Per-consumer access-control storage** — this plugin now owns its own `{prefix}abilities_access_control` database table, keeping its rules fully isolated from any other plugin embedding the same access-control library. The dedicated table is created automatically on plugin activation.
* **Add-ons submenu URL changed** — the Add-ons page slug is now `acrossai-addons` (was `wpb-addons`). Any bookmarks or external links pointing at `wp-admin/admin.php?page=wpb-addons` should be updated to `wp-admin/admin.php?page=acrossai-addons`. The submenu location and behavior are otherwise unchanged.
* **BREAKING — Access Control rules from earlier releases are NOT migrated.** If you previously configured Access Control rules on any ability, those rules were stored in the shared `{prefix}wpb_access_control` table and are **no longer read** by this release. After upgrading, please audit every ability's Access Control panel and reconfigure any rules that were previously in place. The legacy table is left on disk (in case you need to reference the prior configuration) and can be dropped manually by a database administrator if desired: `DROP TABLE {prefix}wpb_access_control;` and `DELETE FROM {prefix}options WHERE option_name = 'wpb_access_control_db_version';`.
* **BREAKING — Ability execution logging removed.** The dedicated Logs admin page, the log-retention Settings field, the `{prefix}acrossai_ability_logs` database table, and the `/wp-json/acrossai-abilities-log/v1/logger/logs` REST endpoint are all removed. If you rely on ability-execution logging for security monitoring or auditing, install a compatible logging plugin or hook `wp_after_execute_ability` directly in your own consumer code — the upstream ability-execution events remain available. Bookmarks to `wp-admin/admin.php?page=acrossai-abilities-logs` receive the standard "page does not exist" response. External integrations polling the removed REST endpoint receive 404. On existing installs, the legacy logs table and its schema-version option are orphaned; opt into the "delete all data on uninstall" setting to drop them cleanly, or run manually: `DROP TABLE {prefix}acrossai_ability_logs;` and `DELETE FROM {prefix}options WHERE option_name IN ('acrossai_abilities_log_retention_days', 'acrossai_ability_logs_db_version');`.

= 0.0.1 =
* Initial release.
* Sitewide Ability Management: browse, toggle, edit, reset, bulk-action.
* Ability Library: enable/disable add-on ability groups with All/Specific mode controls.
* Add-ons page powered by wpb-addons-page with Freemius integration.
* MCP server listing via MCP Adapter integration.

== Upgrade Notice ==

= 0.0.7 =
Adds Library page bulk Enable All / Disable All buttons scoped to the active tab, URL-synced tabs (`?tab=<slug>`) for deep-linkable views, and a readonly ability preview on disabled cards. No breaking changes; no database schema changes; no new REST endpoints; no new capability requirements. `mode` and per-slug selections are preserved through disable / enable cycles. Safe upgrade.

= 0.0.6 =
IMPORTANT: this release absorbs the companion `acrossai-core-abilities` plugin — deactivate and uninstall that plugin after upgrading to avoid duplicate ability registrations. BREAKING for downstream integrators: 17 category slugs rebranded `acrossai-core-abilities-<domain>` → `acrossai-abilities-manager-<domain>` and 176 ability slugs `acrossai-core-abilities/<verb>` → `acrossai-abilities-manager/<verb>`; update any MCP/REST/WP-CLI callers that referenced the legacy slugs. Ability payload shapes and permission callbacks unchanged. Also promotes Themes / Blocks / Plugins / Users / Database / Cron / Cache / File Manager to their own Library page tabs, bumps `acrossai-co/main-menu` to `0.0.14`, and rotates Freemius credentials.

= 0.0.5 =
Dependency-only release: refreshes the bundled `acrossai-co/main-menu` package to `0.0.11`. No functional changes to this plugin. Safe upgrade.

= 0.0.4 =
IMPORTANT for add-on developers: Library display fields `sub_group`, `sub_group_label`, and `tab_group` must now be nested under `$args['meta']['acrossai']` when calling `wp_register_ability()`. The old top-level shape is silently dropped — cards will render without their sub-group heading or custom tab placement until you migrate. End users and site administrators are not affected; no data migration, no DB or REST changes. Also swaps the WordPress.org plugin icon to an SVG and drops the directory banners.

= 0.0.3 =
Fixes the 0.0.2 activation error on WordPress.org installs — the release ZIP now includes the Composer autoloader. No functional or user-facing changes vs 0.0.2. If you hit the "Composer autoloader is missing" error on 0.0.2, delete the plugin folder and reinstall 0.0.3.

= 0.0.2 =
IMPORTANT: (1) This release does NOT migrate Access Control rules from previous versions. If you had configured any Access Control rules on abilities, audit and reconfigure them after upgrading. Pre-existing rules remain in the database (in the orphaned `{prefix}wpb_access_control` table) but are no longer applied. (2) Ability execution logging has been removed — the Logs admin page is gone; ability-execution denials are no longer recorded by this plugin. Install a compatible logging plugin if you need this signal.

= 0.0.1 =
Initial release.

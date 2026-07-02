=== AcrossAI Abilities Manager ===
Contributors: raftaar1191
Donate link: https://github.com/acrosswp/acrossai-abilities-manager
Tags: abilities, ability management, access control, site management, ai
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.1
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

= Unreleased =
* **Composer dependency refresh** — `wpb-access-control` bumped to v2.0.0 (per-consumer database tables); `acrossai-co/main-menu` bumped to v0.0.8 (now bundles the Add-ons page). The standalone `acrossai-co/addons-page` package has been removed from direct dependencies; the same `AcrossAI_Addon\AddonsPage` class now ships from the `main-menu` package.
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

= Unreleased =
IMPORTANT: (1) This release does NOT migrate Access Control rules from previous versions. If you had configured any Access Control rules on abilities, audit and reconfigure them after upgrading. Pre-existing rules remain in the database (in the orphaned `{prefix}wpb_access_control` table) but are no longer applied. (2) Ability execution logging has been removed — the Logs admin page is gone; ability-execution denials are no longer recorded by this plugin. Install a compatible logging plugin if you need this signal.

= 0.0.1 =
Initial release.

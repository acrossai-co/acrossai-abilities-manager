=== AcrossAI Abilities Manager ===
Contributors: raftaar1191
Donate link: https://github.com/acrosswp/acrossai-abilities-manager
Tags: abilities, ability management, access control, site management, ai
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.11
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

This plugin makes no external HTTP requests. The Add-ons page lists free companion plugins hosted on WordPress.org; installing one uses the standard WordPress plugin installer, which contacts WordPress.org directly.

== Installation ==

1. Upload the `acrossai-abilities-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **AcrossAI Abilities Manager** in the WordPress admin menu.

**Add-ons:**

1. Go to **AcrossAI → Add-ons** to browse available companion plugins.
2. All add-ons are free and hosted on WordPress.org; each card offers a one-click Install / Activate / Deactivate action via the standard WordPress plugin installer.

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

No. The plugin makes no external HTTP requests itself. The Add-ons page lists free companion plugins hosted on WordPress.org; installing one uses the standard WordPress plugin installer, which contacts WordPress.org on your behalf using WordPress core APIs.

== Screenshots ==

1. The Abilities Manager admin page — searchable, sortable ability table.
2. The edit drawer — tri-state override controls for each ability field.
3. Bulk actions toolbar for allow/disallow/reset across multiple abilities.
4. The Ability Library page — enable/disable add-on ability groups.
5. The Add-ons page — browse free companion plugins.

== External Services ==

This plugin makes no external HTTP requests on its own.

The Add-ons page lists free companion plugins hosted on WordPress.org. Installing a listed add-on uses the standard WordPress plugin installer, which contacts WordPress.org on your behalf using WordPress core APIs — no external service is involved on the plugin's side.

== Privacy Policy ==

This plugin does not collect, store, or transmit any user data.

No data is sent to any external server without explicit user action.

== Changelog ==

= 0.0.11 =
* **New — WordPress core update abilities under a new "Core" category.** Two new abilities: `acrossai-abilities-manager/wp-core-update-check` reports whether a WordPress core update is available (returns `current_version`, `new_version`, download URL, PHP / MySQL requirements — flattens WP's core update offer into a JSON-friendly shape); `acrossai-abilities-manager/wp-core-update` applies the update via WP core's `Core_Upgrader::upgrade()`. When called with no arguments it upgrades to the first `response=upgrade` offer from `get_core_updates()`; pass `version` (+ optional `locale`) to pin to a specific offer. Requires BOTH `manage_options` AND `update_core` (matches WP core's own admin gate). Honours `DISALLOW_FILE_MODS` via `File_Mods_Guard`. Multisite guard bails cleanly if the current user lacks network-level `update_core`. Idempotent — re-running when no update is available returns a clean success envelope with `updated=false`. Uses WP core functions exclusively; no bundled updater, no custom HTTP, no custom integrity checks. See PR [#75](https://github.com/acrossai-co/acrossai-abilities-manager/pull/75).
* **New Core category folder.** `includes/Abilities/Core/` joins the existing 17 Category folders (Plugins, Themes, FileManager, Cache, Database, Users, Block, Settings, Fonts, Content, Taxonomies, Media, Comments, Menus, Options, Cron, SiteHealth). Displayed as a new "Core" tab on the Ability Library page. Not a new module — Constitution §I locks the module count at five; Category folders are sub-partitions of the existing Custom Ability Registration module.
* **Backup filenames — human-readable and time-sortable.** Filenames produced by `zip-create` (and finalized zips from `zip-upload`) change from `backup-{type}-{slug}-{random-12-chars}.zip` to `{slug}-{unix-timestamp}-{ms}.zip` (e.g. `hello-dolly-1721260800-517.zip`). Lexicographic sort now equals chronological sort; the target is readable at a glance. The 3-digit millisecond suffix from `microtime(true)` prevents same-second collisions when two back-to-back calls target the same slug. Trade-off: dropping the 12-char random suffix removes the enumeration-by-guessing defense the old scheme provided. Mitigations still in force: the `.htaccess` in `wp-content/uploads/acrossai-backups/` still disables directory listing (`Options -Indexes`) and still blocks execution of PHP-family extensions, and `zip-list` / `zip-download` still require `manage_options`. Backups created on 0.0.9 / 0.0.10 with the old scheme continue to work — the filename change only affects new backups.
* **Spec-Kit backfill for Feature 041.** `specs/041-backup-restore-abilities-and-updates/` now exists with the full artifact set (spec, plan, tasks, checklists, security-constraints, memory-synthesis, architecture-review) documenting the 8 abilities that shipped in 0.0.9 and the 0.0.10 include_hidden fix. Same seven-file layout used by Feature 053's backfill.

= 0.0.10 =
* **Fix (Zip_Create) — `include_hidden=false` now applies recursively.** The 0.0.9 implementation used `RecursiveIteratorIterator::SELF_FIRST` with a per-entry basename check that only skipped the top-level hidden directory itself; the iterator kept descending into it, so files INSIDE a hidden directory (e.g. `.git/objects/xxx`) were still added because their basenames don't start with `.`. Fixed to check EVERY segment of the entry's relative path — same approach the reference `download-plugin` uses in its `app/Plugins/Base.php`. Applied to both the archive assembly (`append_dir_to_zip`) and the pre-write size guard (`estimate_tree_size`). If you called `zip-create` with `include_hidden=false` against a dev checkout in 0.0.9, the archive contained the full contents of every hidden directory beneath the source — regenerate any such archives on 0.0.10. See PR [#73](https://github.com/acrossai-co/acrossai-abilities-manager/pull/73).

= 0.0.9 =
* **New — six Zip abilities under FileManager for backup / restore workflows.** `acrossai-abilities-manager/zip-create` archives a plugin, theme, uploads folder, mu-plugins folder, or any ABSPATH-relative path into `wp-content/uploads/acrossai-backups/<random>.zip` and returns the download URL + SHA-256. `zip-upload` accepts a zip via base64, chunked (up to 8 MB per chunk / 64 MB per session, filterable), or a remote URL and finalizes it into the same directory after validating the `PK\x03\x04` magic bytes. `zip-extract` extracts a zip already on disk or fetched from a URL into a resolved target directory (plugin / theme / uploads / mu-plugins / path); every archive entry is audited for zip-slip (`..` segments, absolute paths, backslashes, null bytes) before extraction. `zip-download` returns a fresh URL + metadata for any managed zip. `zip-list` paginates the managed directories, newest first. `zip-delete` removes a zip from the managed directories idempotently. The backups directory is hardened on first use with an `.htaccess` that blocks PHP execution while keeping `.zip` downloads reachable, plus an empty `index.php` for enumeration defense. All abilities enforce `manage_options`; every mutating ability honours `DISALLOW_FILE_MODS` via the shared `File_Mods_Guard`. See PR #TBD.
* **New — `acrossai-abilities-manager/plugin-update` and `acrossai-abilities-manager/theme-update` abilities.** The Plugins and Themes categories previously shipped an `update-check` reporter but no way to apply updates through the abilities API. The new abilities wrap WP core `Plugin_Upgrader::bulk_upgrade()` / `Theme_Upgrader::bulk_upgrade()` (same pattern as the existing `plugin-install` / `theme-install`), accept an array of plugin files / slugs or theme stylesheets, and return per-slug results with `from_version`, `to_version`, `updated`, and `message`. Plugin_Update additionally requires `update_plugins`; Theme_Update additionally requires `update_themes`. Idempotent: re-running when no update is available reports `updated_count: 0` with a clean success envelope.
* **Shared utilities: `Backups_Storage` and `Zip_Target_Resolver`.** New helpers under `includes/Abilities/Utilities/`. `Backups_Storage` manages the `acrossai-backups/` and `acrossai-staging/` directories under `wp-content/uploads/`, generates enumeration-resistant random filenames, resolves managed paths with `realpath()` boundary checks, and computes SHA-256 for the listing / download responses. `Zip_Target_Resolver` maps `(target_type, target)` to an absolute filesystem path — plugin slugs resolve via `Plugin_Helpers` (existing fuzzy resolver), theme stylesheets via `Theme_Helpers`, `uploads` via `wp_get_upload_dir()`, `mu-plugins` via `WPMU_PLUGIN_DIR`, and `path` values via a strict inside-ABSPATH realpath check.
* **Zip_Upload chunk sweeper cron.** A new daily cron (`acrossai_abilities_manager_zip_upload_sweep_chunks`) sweeps abandoned chunk sessions from `wp-content/uploads/acrossai-staging/` after the configurable TTL (default: 1 day, filterable via `acrossai_abilities_manager_zip_upload_session_ttl`). Mirrors the existing Upload_Media sweeper.
* **New configurable limits.** `acrossai_abilities_manager_zip_max_bytes` (default 512 MB) caps the decompressed size of any zip written or extracted. `acrossai_abilities_manager_zip_upload_chunk_max_bytes` (default 8 MB base64) and `acrossai_abilities_manager_zip_upload_session_max_bytes` (default 64 MB base64) cap the chunked upload flow.

= 0.0.8 =
* **Freemius integration removed entirely.** The `freemius/wordpress-sdk` composer dependency is dropped (upstream `acrossai-co/main-menu` 0.0.21+ no longer requires it). The plugin no longer sends any data to Freemius, no longer shows a Connect / Login / Buy affordance on the Add-ons page, and the entire Freemius vendored SDK tree (~2,000 files) is removed from the installable ZIP. If you previously connected a Freemius account tied to this plugin, that connection is now inert; any `fs_*` or `freemius_*` rows in `wp_options` are no longer read by anything and can be safely deleted (e.g. `wp option list --search='fs_*'` then `wp option delete <name>` for each). This supersedes the 0.0.6 changelog entry about Freemius credentials — those credentials are no longer used. See PR [#69](https://github.com/acrossai-co/acrossai-abilities-manager/pull/69).
* **Add-ons page — free-only, and this plugin excluded from its own listing.** The Add-ons page (`?page=acrossai-addons`) now lists only free companion plugins hosted on WordPress.org (Install / Activate / Deactivate via the standard WP plugin installer). This plugin no longer appears in its own Add-ons page — a small self-filter on the `acrossai_addons` hook removes it, since it's obviously already active when the page renders. Other AcrossAI companion plugins (MCP Manager, Model Manager, Turn Off AI Features) still list normally.
* **Library page — title and Enable All / Disable All buttons on a single horizontal row.** The "Ability Library" page heading and the bulk-action buttons introduced in 0.0.7 (Feature 052) now share one line at the top of the page (title anchored left, buttons anchored right). Saves vertical space; matches administrator expectations for admin page layouts.
* **Dependencies: `acrossai-co/main-menu` bumped from `0.0.14` to `0.0.23`.** Three-hop bump (0.0.21 → 0.0.22 → 0.0.23) accumulated during the release cycle. Consumer API surface (`SettingsPage`, `MenuRegistrar`, `AddonsPageRenderer`) preserved across each hop; no code changes required beyond removing the old `\AcrossAI_Addon\AddonsPage` instantiation (class deleted upstream in 0.0.21 — its responsibilities moved into `\AcrossAI_Main_Menu\MenuRegistrar` which is registered automatically when the shared `SettingsPage` bootstrap runs).

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

= 0.0.11 =
Adds two WordPress-core-scoped abilities under a new "Core" tab in the Ability Library — `wp-core-update-check` (report availability) and `wp-core-update` (apply via `Core_Upgrader`). The update ability requires both `manage_options` and `update_core`; honours `DISALLOW_FILE_MODS`; multisite-guarded. Also changes backup filenames from `backup-{type}-{slug}-{random}.zip` to `{slug}-{unix-timestamp}-{ms}.zip` — human-readable and time-sortable, but predictable (directory listing remains disabled on the backups dir). Existing backups continue to work; the filename change only affects new backups. No breaking changes; no database, REST, or capability changes to existing abilities. Safe upgrade.

= 0.0.10 =
Bugfix release. `Zip_Create` with `include_hidden=false` was silently descending into hidden directories and archiving their contents in 0.0.9 (only the top-level `.git/` etc. entry was skipped, not the files beneath it). Fixed to check every segment of each entry's relative path. Regenerate any `include_hidden=false` archives created on 0.0.9 if their source tree contained hidden directories. No breaking changes; no database, REST, or capability changes. Safe upgrade.

= 0.0.9 =
Adds eight new abilities: six under FileManager for zip-based backup / restore workflows (`zip-create`, `zip-upload`, `zip-extract`, `zip-download`, `zip-list`, `zip-delete`) plus `plugin-update` and `theme-update` that finally let AI clients apply pending WordPress core updates through the Abilities API. All new abilities enforce `manage_options`; mutating abilities additionally honour `DISALLOW_FILE_MODS`. Zip extraction rejects zip-slip archives (any entry containing `..`, an absolute path, a backslash, or a null byte). Zip uploads are validated for the `PK` magic signature before finalization. A new `wp-content/uploads/acrossai-backups/` directory is created on first use, hardened with an `.htaccess` that blocks PHP execution but permits `.zip` downloads (required so the URLs returned by `zip-create` remain reachable). No breaking changes to existing abilities, REST endpoints, capability requirements, or database schema. Safe upgrade.

= 0.0.8 =
IMPORTANT: this release **removes the Freemius integration entirely** — the plugin no longer sends any data to Freemius and no longer offers a Connect / Login / Buy affordance on the Add-ons page. If you previously connected a Freemius account tied to this plugin, that connection is now inert; stale `fs_*` or `freemius_*` rows in `wp_options` are safe to delete manually. Also: the Add-ons page now shows only free WordPress.org companion plugins (and no longer lists this plugin itself); the Library page compacts its title + bulk-action buttons onto one horizontal row; and `acrossai-co/main-menu` bumps `0.0.14 → 0.0.23`. No breaking changes to REST endpoints, capability requirements, or database schema. Safe upgrade.

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

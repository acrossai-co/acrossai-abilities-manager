<?php
/**
 * Database schema management.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Manages creation, upgrade, and removal of the plugin's custom database table.
 *
 * The table stores per-ability metadata overrides keyed by ability slug.
 * Schema versioning is tracked via a WordPress option so that structural
 * changes can be applied on upgrade without running dbDelta on every request.
 *
 * @since   0.1.0
 * @package AcrossAI_Abilities_Manager
 */
class Schema {

	/**
	 * Unprefixed table name. Always prepend $wpdb->prefix before issuing queries.
	 */
	public const TABLE_NAME = 'acrossai_abilities';

	/**
	 * Unprefixed custom abilities table name.
	 */
	public const CUSTOM_ABILITIES_TABLE_NAME = 'acrossai_custom_abilities';

	/**
	 * Current schema version identifier.
	 *
	 * Increment this constant whenever the table structure changes so that
	 * maybe_upgrade_table() triggers a dbDelta run for existing installations.
	 */
	private const SCHEMA_VERSION = '4';

	/**
	 * WordPress option key used to persist the currently applied schema version.
	 *
	 * Storing the version in the options table avoids an extra SHOW TABLES
	 * query on every admin request once the schema is up to date.
	 */
	private const SCHEMA_VERSION_OPTION = 'acrossai_abilities_schema_version';

	/**
	 * Returns the fully prefixed table name for the current WordPress installation.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return string Prefixed table name, e.g. `wp_acrossai_abilities`.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Returns the fully prefixed custom abilities table name.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return string Prefixed table name, e.g. `wp_acrossai_custom_abilities`.
	 */
	public static function get_custom_abilities_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::CUSTOM_ABILITIES_TABLE_NAME;
	}

	/**
	 * Creates or upgrades the custom table when the stored schema version is outdated.
	 *
	 * Called both on plugin activation and on every `admin_init` so that schema
	 * changes applied via a code update (without deactivate/reactivate) are still
	 * picked up automatically. The option-based early-return makes this check
	 * inexpensive on the vast majority of requests.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_table(): void {
		// Bail early — the table is already at the current schema version and exists.
		if ( self::SCHEMA_VERSION === get_option( self::SCHEMA_VERSION_OPTION, '' ) && self::table_exists() ) {
			return;
		}

		self::create_table();

		// Only record the version bump if dbDelta actually succeeded in creating the table.
		if ( self::table_exists() ) {
			update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false );
		}
	}

	/**
	 * Creates the abilities override table via WordPress's dbDelta() utility.
	 *
	 * Column overview:
	 * - `ability_slug`              — unique slug identifying the ability (e.g. `wordpress/list-users`).
	 * - `provider`                  — detected source: `core`, `theme:<slug>`, or plugin slug.
	 * - `site_allowed`              — nullable bool; null = inherit default, false = disallowed on this site.
	 * - `readonly/destructive/idempotent` — nullable annotation overrides (tri-state: true/false/null).
	 * - `show_in_rest`              — nullable bool controlling REST API exposure.
	 * - `mcp_public`                — nullable bool controlling MCP client visibility.
	 * - `mcp_type`                  — MCP endpoint type: `tools`, `resources`, or `prompts`.
	 * - `custom_meta`               — JSON blob for arbitrary additional metadata fields.
	 *
	 * After running dbDelta, migrate_legacy_schema() is called to drop obsolete
	 * columns left over from earlier plugin versions.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ability_slug VARCHAR(255) NOT NULL,
			provider VARCHAR(100) DEFAULT NULL,
			site_allowed TINYINT(1) DEFAULT NULL,
			readonly TINYINT(1) DEFAULT NULL,
			destructive TINYINT(1) DEFAULT NULL,
			idempotent TINYINT(1) DEFAULT NULL,
			show_in_rest TINYINT(1) DEFAULT NULL,
			mcp_public TINYINT(1) DEFAULT NULL,
			mcp_type VARCHAR(100) DEFAULT NULL,
			custom_meta LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ability_slug (ability_slug),
			KEY idx_provider (provider)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Create custom abilities table.
		self::create_custom_abilities_table();

		self::migrate_legacy_schema();
	}

	/**
	 * Creates the custom abilities table.
	 *
	 * Stores user-defined abilities with full definitions including schemas and logic.
	 *
	 * Column overview:
	 * - `ability_slug`         — unique slug for the custom ability (e.g. `my-site/custom-processor`).
	 * - `label`                — human-readable display name.
	 * - `description`          — full description of the ability.
	 * - `category`             — ability category slug for organization.
	 * - `status`               — active/draft/archived; controls whether ability is registered.
	 * - `input_schema`         — JSON Schema defining input parameters.
	 * - `output_schema`        — JSON Schema defining output structure.
	 * - `execute_callback`     — stored PHP callable or logic for execution.
	 * - `permission_callback`  — stored PHP callable or logic for permission checks.
	 * - `readonly/destructive/idempotent` — metadata annotations.
	 * - `show_in_rest/mcp_public/mcp_type` — API exposure and MCP configuration.
	 * - `custom_meta`          — JSON blob for additional extensible metadata.
	 * - `created_by`           — WordPress user ID of the creator.
	 * - `version`              — semantic version string.
	 * - `deprecated_at`        — timestamp for deprecation tracking.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	private static function create_custom_abilities_table(): void {
		global $wpdb;
		$table_name      = self::get_custom_abilities_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ability_slug VARCHAR(255) NOT NULL,
			label VARCHAR(255) NOT NULL,
			description LONGTEXT DEFAULT NULL,
			category VARCHAR(255) DEFAULT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'active',
			input_schema LONGTEXT DEFAULT NULL,
			output_schema LONGTEXT DEFAULT NULL,
			execute_callback LONGTEXT DEFAULT NULL,
			permission_callback LONGTEXT DEFAULT NULL,
			readonly TINYINT(1) DEFAULT NULL,
			destructive TINYINT(1) DEFAULT NULL,
			idempotent TINYINT(1) DEFAULT NULL,
			show_in_rest TINYINT(1) DEFAULT NULL,
			mcp_public TINYINT(1) DEFAULT NULL,
			mcp_type VARCHAR(100) DEFAULT NULL,
			custom_meta LONGTEXT DEFAULT NULL,
			created_by BIGINT UNSIGNED DEFAULT NULL,
			version VARCHAR(20) NOT NULL DEFAULT '1.0',
			deprecated_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ability_slug (ability_slug),
			KEY idx_status (status),
			KEY idx_category (category),
			KEY idx_created_by (created_by)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	/**
	 * Drops the plugin's custom tables and removes the schema version option.
	 *
	 * Intended to be called only from uninstall.php. The existence check
	 * prevents a redundant DROP attempt when the table has already been removed
	 * (e.g. by a previous failed uninstall attempt).
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;
		$table_name      = self::get_table_name();
		$custom_table    = self::get_custom_abilities_table_name();

		// Drop overrides table if it exists.
		if ( self::table_exists() ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		// Drop custom abilities table if it exists.
		if ( self::custom_abilities_table_exists() ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$custom_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		delete_option( self::SCHEMA_VERSION_OPTION );
	}


	/**
	 * Removes columns that existed in earlier schema versions but are now obsolete.
	 *
	 * Schema v1 used `slug` and `meta_json` as the primary storage columns.
	 * Schema v2 renamed and split them into individual typed columns. This method
	 * checks for the old column names and drops them when found, preventing
	 * duplicate storage or confusion during live upgrades.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	private static function migrate_legacy_schema(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		// Nothing to migrate if table creation itself failed.
		if ( ! self::table_exists() ) {
			return;
		}

		$legacy_columns = array( 'slug', 'meta_json' );

		foreach ( $legacy_columns as $column ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

			// Only run ALTER TABLE if the column is actually present; avoids a MySQL
			// error on fresh installs that never had the old schema.
			if ( $exists ) {
				$wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN {$column}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
			}
		}

		$wpdb->query( "ALTER TABLE {$table_name} MODIFY updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Checks whether the plugin's custom table currently exists in the database.
	 *
	 * Uses `SHOW TABLES LIKE` rather than querying `information_schema` for
	 * compatibility with managed hosting environments that restrict schema
	 * introspection queries.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return bool True when the table exists, false otherwise.
	 */
	private static function table_exists(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		$result     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $result === $table_name;
	}

	/**
	 * Checks whether the plugin's custom abilities table currently exists.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return bool True when the table exists, false otherwise.
	 */
	private static function custom_abilities_table_exists(): bool {
		global $wpdb;
		$table_name = self::get_custom_abilities_table_name();
		$result     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $result === $table_name;
	}
}

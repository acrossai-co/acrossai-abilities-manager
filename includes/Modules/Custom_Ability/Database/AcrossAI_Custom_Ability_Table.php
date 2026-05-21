<?php
/**
 * Database table definition for the custom abilities table.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Custom_Ability/Database
 * @since      1.0.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database;

use BerlinDB\Database\Table;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Manages database table creation and upgrades for custom abilities.
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Table extends Table {

	/**
	 * Table name (without WordPress table prefix).
	 *
	 * @var string
	 */
	protected $name = 'acrossai_custom_abilities';

	/**
	 * Table version used to trigger maybe_upgrade().
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * WordPress option key used to track the installed schema version.
	 *
	 * @var string
	 */
	protected $db_version_key = 'acrossai_custom_abilities_db_version';

	/**
	 * Use per-site table prefix ($wpdb->prefix), not the network base prefix.
	 * Explicitly set to false so multisite intent is declared in code, not
	 * inherited from BerlinDB library defaults (SEC-03 / Constitution §II).
	 *
	 * @var bool
	 */
	protected $global = false;

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Custom_Ability_Table|null
	 */
	protected static $_instance = null;

	/**
	 * Get the singleton instance of this table.
	 *
	 * @since  1.0.0
	 * @return AcrossAI_Custom_Ability_Table
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Define the raw SQL column list for CREATE TABLE.
	 *
	 * BerlinDB interpolates $this->schema directly into:
	 *   CREATE TABLE {name} ( {schema} ) {charset_collation}
	 * so this must be a raw SQL column definition string.
	 * AcrossAI_Custom_Ability_Schema (BerlinDB\Schema subclass) is used by the
	 * Query class for column metadata and is separate from this.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function set_schema() {
		$this->schema = "
			`id` bigint(20) unsigned NOT NULL auto_increment,
			`ability_slug` varchar(255) NOT NULL DEFAULT '',
			`label` varchar(255) NOT NULL DEFAULT '',
			`description` longtext DEFAULT NULL,
			`enabled` tinyint(1) NOT NULL DEFAULT 1,
			`callback_type` varchar(50) NOT NULL DEFAULT 'noop',
			`callback_config` longtext DEFAULT NULL,
			`input_schema` longtext DEFAULT NULL,
			`output_schema` longtext DEFAULT NULL,
			`show_in_rest` tinyint(1) NOT NULL DEFAULT 1,
			`show_in_mcp` tinyint(1) NOT NULL DEFAULT 0,
			`mcp_type` varchar(50) DEFAULT NULL,
			`readonly` tinyint(1) DEFAULT NULL,
			`destructive` tinyint(1) DEFAULT NULL,
			`idempotent` tinyint(1) DEFAULT NULL,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`created_by` bigint(20) unsigned DEFAULT NULL,
			`updated_by` bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `ability_slug` (`ability_slug`(191)),
			KEY `idx_enabled` (`enabled`),
			KEY `idx_updated_at` (`updated_at`)
		";
	}
}

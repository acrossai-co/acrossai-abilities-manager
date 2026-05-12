<?php
/**
 * Database table definition for the abilities overwrite table.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database;

use BerlinDB\Database\Table;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Manages database table creation and upgrades for ability overrides.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Table extends Table {

	/**
	 * Table name (without WordPress table prefix).
	 *
	 * @var string
	 */
	protected $name = 'acrossai_abilities_overwrite';

	/**
	 * Table version used to trigger maybe_upgrade().
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Set the SQL schema for the abilities overwrite table.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function set_schema() {
		$this->schema = "
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ability_slug varchar(255) NOT NULL DEFAULT '',
			provider varchar(100) DEFAULT NULL,
			source varchar(50) DEFAULT NULL,
			site_allowed tinyint(1) DEFAULT NULL,
			readonly tinyint(1) DEFAULT NULL,
			destructive tinyint(1) DEFAULT NULL,
			idempotent tinyint(1) DEFAULT NULL,
			show_in_rest tinyint(1) DEFAULT NULL,
			show_in_mcp tinyint(1) DEFAULT NULL,
			mcp_type varchar(100) DEFAULT NULL,
			mcp_servers longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_by bigint(20) unsigned DEFAULT NULL,
			updated_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY (id),
			KEY ability_slug (ability_slug)
		";
	}
}

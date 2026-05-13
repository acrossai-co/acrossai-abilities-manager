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
	 * Singleton instance.
	 *
	 * @var AcrossAI_Sitewide_Table|null
	 */
	protected static $_instance = null;

	/**
	 * Get the singleton instance of this table.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Sitewide_Table
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Set the schema for the abilities overwrite table.
	 *
	 * Uses BerlinDB Schema class for column definitions.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function set_schema() {
		$this->schema = AcrossAI_Sitewide_Schema::class;
	}

	/**
	 * Create or upgrade the table if needed.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function maybe_upgrade() {
		parent::maybe_upgrade();
	}
}

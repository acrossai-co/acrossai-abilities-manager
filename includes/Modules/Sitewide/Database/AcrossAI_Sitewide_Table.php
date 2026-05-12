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
	 * Schema class reference.
	 *
	 * @var string
	 */
	protected $schema = AcrossAI_Sitewide_Schema::class;

	/**
	 * Run upgrades or initial table creation.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function maybe_upgrade(): void {
		parent::maybe_upgrade();
	}
}

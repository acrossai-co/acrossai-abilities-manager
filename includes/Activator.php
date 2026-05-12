<?php
/**
 * Fired during plugin activation.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 * @since      0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes;

use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.0.1
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 * @author     WPBoilerplate <contact@wpboilerplate.com>
 */
class Activator {

	/**
	 * Run activation tasks.
	 *
	 * Creates or upgrades the {prefix}acrossai_abilities_overwrite table.
	 *
	 * @since  0.0.1
	 * @return void
	 */
	public static function activate(): void {
		( new AcrossAI_Sitewide_Table() )->maybe_upgrade();
	}
}

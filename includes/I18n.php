<?php
namespace AcrossAI_Abilities_Manager\Includes;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Define the internationalization functionality
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 */
class I18n {

	/**
	 * Actually load the plugin textdomain on `init`
	 */
	public function do_load_textdomain() {
		load_plugin_textdomain(
			'acrossai-abilities-manager',
			false,
			plugin_basename( dirname( \ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE ) ) . '/languages/'
		);
	}
}

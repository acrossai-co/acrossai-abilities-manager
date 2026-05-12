<?php
/**
 * Sitewide Ability Management module — registers all hooks for this feature.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide;

use AcrossAI_Abilities_Manager\Includes\Base\AcrossAI_Module_Base;
use AcrossAI_Abilities_Manager\Includes\Loader;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Module class for sitewide ability management.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Module extends AcrossAI_Module_Base {

	/**
	 * Return the module machine name.
	 *
	 * @since  0.1.0
	 * @return string
	 */
	public function get_name(): string {
		return 'sitewide';
	}

	/**
	 * Register all hooks for this module with the Loader.
	 *
	 * @since  0.1.0
	 * @param  Loader $loader The plugin hook-loader instance.
	 * @return void
	 */
	public function register_hooks( Loader $loader ): void {
		$controller = new AcrossAI_Sitewide_Rest_Controller( new AcrossAI_Sitewide_Query() );
		$loader->add_action( 'rest_api_init', $controller, 'register_routes' );
	}
}

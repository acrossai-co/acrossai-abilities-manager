<?php
/**
 * Abstract base class for all AcrossAI plugin modules.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Base
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Base;

use AcrossAI_Abilities_Manager\Includes\Loader;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class that every feature module must extend.
 *
 * Each module is responsible for registering its own WordPress hooks via
 * the Loader singleton and must declare a unique machine name.
 *
 * @since 0.1.0
 */
abstract class AcrossAI_Module_Base {

	/**
	 * Register all hooks for this module with the given Loader.
	 *
	 * @since  0.1.0
	 * @param  Loader $loader The plugin hook-loader instance.
	 * @return void
	 */
	abstract public function register_hooks( Loader $loader ): void;

	/**
	 * Return the unique machine name for this module.
	 *
	 * @since  0.1.0
	 * @return string
	 */
	abstract public function get_name(): string;
}

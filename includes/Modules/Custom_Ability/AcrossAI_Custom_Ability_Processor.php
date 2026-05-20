<?php
/**
 * Custom Ability Processor
 *
 * Registers custom abilities from BerlinDB at wp_abilities_api_init hook.
 * Fetches all enabled custom abilities and injects them into WordPress Abilities API.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability
 * @since 1.0.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Processor class
 *
 * Singleton: Registers custom abilities at wp_abilities_api_init.
 *
 * Hooks into `wp_abilities_api_init` (priority 10) to fetch all enabled
 * custom abilities from BerlinDB table and register them via wp_register_ability().
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Processor {

	/**
	 * Singleton instance
	 *
	 * @since 1.0.0
	 * @var AcrossAI_Custom_Ability_Processor
	 */
	protected static $_instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return AcrossAI_Custom_Ability_Processor
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor (private for singleton)
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton pattern
	}

	/**
	 * Register custom abilities at wp_abilities_api_init
	 *
	 * Fetches all enabled custom abilities from BerlinDB table,
	 * builds metadata, injects permission callback, and registers via wp_register_ability().
	 *
	 * @since 1.0.0
	 * @action wp_abilities_api_init
	 * @return void
	 */
	public function register_abilities() {
		// Check if WordPress Abilities API is available
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// TODO: Implement full registration logic
		// Phase 2 implementation will:
		// 1. Fetch enabled abilities from BerlinDB table
		// 2. For each ability:
		//    - Build metadata object from DB row
		//    - Apply permission callback based on permission_type
		//    - Register via wp_register_ability()
		//    - Fire acrossai_custom_ability_registered hook
		// 3. Handle errors gracefully

		do_action( 'acrossai_custom_ability_processor_initialized' );
	}
}

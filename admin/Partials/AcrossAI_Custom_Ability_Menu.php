<?php
/**
 * Custom Ability Menu Handler
 *
 * Registers admin menu/submenu for Custom Abilities management.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Admin\Partials
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Menu class
 *
 * Singleton: Registers Custom Abilities submenu under "Abilities Manager".
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Menu {

	/**
	 * Singleton instance
	 *
	 * @since 1.0.0
	 * @var AcrossAI_Custom_Ability_Menu
	 */
	protected static $_instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return AcrossAI_Custom_Ability_Menu
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton pattern
	}

	/**
	 * Register admin submenu
	 *
	 * Hooked at admin_menu priority 10.
	 * Adds "Custom Abilities" submenu under "Abilities Manager" parent menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'acrossai-abilities-manager',  // Parent menu slug
			__( 'Custom Abilities', 'acrossai-abilities-manager' ),  // Page title
			__( 'Custom Abilities', 'acrossai-abilities-manager' ),  // Menu title
			'manage_options',              // Capability required
			'acrossai-custom-abilities',   // Menu slug
			array( $this, 'render_page' )  // Callback to render page
		);
	}

	/**
	 * Render admin page
	 *
	 * Delegates to AcrossAI_Custom_Ability_Page for rendering.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page() {
		$page = AcrossAI_Custom_Ability_Page::instance();
		$page->render();
	}
}

<?php
/**
 * Custom Ability Admin Menu Registration
 *
 * Registers submenu for Custom Abilities under main Abilities Manager menu.
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
 * Singleton: Registers and manages Custom Abilities admin menu.
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
	 * Register admin menu
	 *
	 * Adds submenu under "Abilities Manager" parent menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_menu() {
		// Verify parent menu exists before adding submenu
		if ( ! menu_page_url( 'acrossai-abilities-manager', false ) ) {
			// Parent menu not registered yet; this can happen if dependencies aren't loaded
			// Schedule retry or silently fail
			return;
		}

		add_submenu_page(
			'acrossai-abilities-manager',                  // Parent menu slug
			__( 'Custom Abilities', 'acrossai-abilities-manager' ),  // Page title
			__( 'Custom Abilities', 'acrossai-abilities-manager' ),  // Menu title
			'manage_options',                              // Capability
			'acrossai-custom-abilities',                   // Menu slug
			array( $this, 'render_page' )                  // Callback
		);
	}

	/**
	 * Render admin page
	 *
	 * Delegates to page renderer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page() {
		$page = AcrossAI_Custom_Ability_Page::instance();
		$page->render();
	}
}

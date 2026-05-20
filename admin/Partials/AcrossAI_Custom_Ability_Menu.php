<?php
/**
 * Custom Ability Admin Menu
 *
 * Registers the Custom Abilities submenu under Abilities Manager.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Admin\Partials
 * @since 1.0.0
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Menu class
 *
 * Singleton: Registers admin menu for Custom Abilities.
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
	 * Constructor (private for singleton)
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton pattern
	}

	/**
	 * Add admin menu and submenu
	 *
	 * Registers the Custom Abilities submenu under the Abilities Manager parent menu.
	 * Called via Loader action hook: admin_menu
	 *
	 * @since 1.0.0
	 * @action admin_menu
	 * @return void
	 */
	public function add_menu() {
		// Register Custom Abilities submenu under Abilities Manager parent
		// Parent menu slug: 'acrossai-abilities-manager' (created by the main Abilities Manager feature)
		add_submenu_page(
			'acrossai-abilities-manager',                           // Parent slug
			esc_html__( 'Custom Abilities', 'acrossai-abilities-manager' ), // Page title
			esc_html__( 'Custom Abilities', 'acrossai-abilities-manager' ), // Menu title
			'manage_options',                             // Capability
			'acrossai-custom-abilities',                  // Menu slug
			array( $this, 'render_page' )                 // Callback
		);
	}

	/**
	 * Render admin page
	 *
	 * Callback for displaying the Custom Abilities page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page() {
		// Verify capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'acrossai-abilities-manager' ) );
		}

		// Render page via Page class
		$page = AcrossAI_Custom_Ability_Page::instance();
		$page->render();
	}
}

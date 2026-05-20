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
	 * Registers the Abilities Manager parent menu (if not already created by other modules)
	 * and the Custom Abilities submenu.
	 * Called via Loader action hook: admin_menu
	 *
	 * @since 1.0.0
	 * @action admin_menu
	 * @return void
	 */
	public function add_menu() {
		// Check if parent menu exists by trying to access global menu array
		global $menu;
		
		$parent_menu_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $menu_item ) {
				if ( isset( $menu_item[2] ) && 'abilities-manager' === $menu_item[2] ) {
					$parent_menu_exists = true;
					break;
				}
			}
		}

		// Create parent menu if it doesn't exist (fallback for when feature 003/004 isn't loaded)
		if ( ! $parent_menu_exists ) {
			add_menu_page(
				esc_html__( 'Abilities Manager', 'acrossai-abilities-manager' ),     // Page title
				esc_html__( 'Abilities Manager', 'acrossai-abilities-manager' ),     // Menu title
				'manage_options',                                                     // Capability
				'abilities-manager',                                                  // Menu slug
				array( $this, 'render_placeholder_page' ),                           // Callback
				'dashicons-admin-generic',                                           // Icon
				76                                                                    // Position
			);
		}

		// Register Custom Abilities submenu under Abilities Manager parent
		add_submenu_page(
			'abilities-manager',                           // Parent slug
			esc_html__( 'Custom Abilities', 'acrossai-abilities-manager' ), // Page title
			esc_html__( 'Custom Abilities', 'acrossai-abilities-manager' ), // Menu title
			'manage_options',                             // Capability
			'acrossai-custom-abilities',                  // Menu slug
			array( $this, 'render_page' )                 // Callback
		);
	}

	/**
	 * Render placeholder page for parent menu
	 *
	 * Displays a placeholder if Abilities Manager parent menu is accessed directly.
	 * (This is a fallback for when other modules haven't created their own pages)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_placeholder_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'acrossai-abilities-manager' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Abilities Manager', 'acrossai-abilities-manager' ); ?></h1>
			<p><?php esc_html_e( 'Manage WordPress abilities and custom abilities configuration.', 'acrossai-abilities-manager' ); ?></p>
		</div>
		<?php
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

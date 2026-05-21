<?php
/**
 * Custom Ability Assets Manager
 *
 * Enqueues scripts and stylesheets for the Custom Abilities admin page.
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
 * AcrossAI_Custom_Ability_Assets class
 *
 * Singleton: Manages script and style enqueuing for Custom Abilities.
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Assets {

	/**
	 * Singleton instance
	 *
	 * @since 1.0.0
	 * @var AcrossAI_Custom_Ability_Assets
	 */
	protected static $_instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return AcrossAI_Custom_Ability_Assets
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
	 * Check if we're on the custom abilities page
	 *
	 * @since 1.0.0
	 * @return bool True if on custom abilities page
	 */
	private function is_custom_abilities_page() {
		global $pagenow;
		
		// Check if we're on admin.php with acrossai-custom-abilities page
		if ( 'admin.php' !== $pagenow ) {
			return false;
		}
		
		// Check the page parameter
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		return 'acrossai-custom-abilities' === $page;
	}

	/**
	 * Enqueue scripts for Custom Abilities admin page
	 *
	 * @since 1.0.0
	 * @action admin_enqueue_scripts
	 * @return void
	 */
	public function enqueue_scripts() {
		// Only enqueue on Custom Abilities admin page
		if ( ! $this->is_custom_abilities_page() ) {
			return;
		}

		// Get plugin file path
		$plugin_file = defined( 'ACROSSAI_ABILITIES_MANAGER_FILE' )
			? ACROSSAI_ABILITIES_MANAGER_FILE
			: dirname( dirname( dirname( __FILE__ ) ) ) . '/acrossai-abilities-manager.php';

		// Build script path
		$script_path = dirname( $plugin_file ) . '/build/js/custom-abilities.js';
		$script_url = plugins_url( 'build/js/custom-abilities.js', $plugin_file );

		// Only enqueue if built file exists (for production)
		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'acrossai-abilities-custom',
				$script_url,
				array(),
				filemtime( $script_path ),
				true
			);

			wp_add_inline_script(
				'acrossai-abilities-custom',
				'window.acrossaiAbilitiesManager = ' . wp_json_encode(
					array(
						'restNamespace' => 'acrossai-abilities-manager/v1',
						'nonce'         => wp_create_nonce( 'wp_rest' ),
					)
				) . ';',
				'before'
			);
		}
	}

	/**
	 * Enqueue styles for Custom Abilities admin page
	 *
	 * @since 1.0.0
	 * @action admin_enqueue_scripts
	 * @return void
	 */
	public function enqueue_styles() {
		// Only enqueue on Custom Abilities admin page
		if ( ! $this->is_custom_abilities_page() ) {
			return;
		}

		// Get plugin file path
		$plugin_file = defined( 'ACROSSAI_ABILITIES_MANAGER_FILE' )
			? ACROSSAI_ABILITIES_MANAGER_FILE
			: dirname( dirname( dirname( __FILE__ ) ) ) . '/acrossai-abilities-manager.php';

		// Build style path
		$style_path = dirname( $plugin_file ) . '/build/css/custom-abilities.css';
		$style_url = plugins_url( 'build/css/custom-abilities.css', $plugin_file );

		// Only enqueue if built file exists (for production)
		if ( file_exists( $style_path ) ) {
			// No dependencies - CSS is self-contained
			wp_enqueue_style(
				'acrossai-abilities-custom',
				$style_url,
				array(), // No dependencies
				filemtime( $style_path )
			);
		}
	}
}

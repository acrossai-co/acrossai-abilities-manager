<?php
/**
 * Custom Ability Admin Assets Manager
 *
 * Enqueues scripts and styles for Custom Abilities admin interface.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Admin\Partials
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Assets class
 *
 * Singleton: Manages asset enqueuing for Custom Abilities admin.
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
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton pattern
	}

	/**
	 * Enqueue scripts
	 *
	 * Enqueues JavaScript for Custom Abilities admin interface.
	 * Only on acrossai-custom-abilities admin page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		// Only enqueue on Custom Abilities admin page
		if ( ! $this->is_custom_abilities_page() ) {
			return;
		}

		$script_path = ACROSSAI_ABILITIES_MANAGER_DIR . 'build/js/custom-abilities.js';
		$script_url  = ACROSSAI_ABILITIES_MANAGER_URL . 'build/js/custom-abilities.js';

		// Check if built script exists (development/production)
		if ( file_exists( $script_path ) ) {
			$dependencies = include ACROSSAI_ABILITIES_MANAGER_DIR . 'build/js/custom-abilities.asset.php';
			wp_enqueue_script(
				'acrossai-abilities-custom',
				$script_url,
				$dependencies['dependencies'] ?? array( 'wp-react', 'wp-react-dom', 'wp-dataviews', 'wp-i18n' ),
				$dependencies['version'] ?? ACROSSAI_ABILITIES_MANAGER_VERSION,
				array( 'in_footer' => true )
			);
		} else {
			// Fallback if script not built yet (development)
			wp_enqueue_script(
				'acrossai-abilities-custom',
				$script_url,
				array( 'wp-react', 'wp-react-dom', 'wp-dataviews', 'wp-i18n' ),
				ACROSSAI_ABILITIES_MANAGER_VERSION,
				array( 'in_footer' => true )
			);
		}

		// Localize script with data
		wp_localize_script(
			'acrossai-abilities-custom',
			'acrossaiCustomAbilitiesSettings',
			array(
				'restNamespace' => rest_url( 'acrossai-abilities-manager/v1' ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'currentUser'   => get_current_user_id(),
			)
		);
	}

	/**
	 * Enqueue styles
	 *
	 * Enqueues CSS for Custom Abilities admin interface.
	 * Only on acrossai-custom-abilities admin page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		// Only enqueue on Custom Abilities admin page
		if ( ! $this->is_custom_abilities_page() ) {
			return;
		}

		$style_path = ACROSSAI_ABILITIES_MANAGER_DIR . 'build/css/custom-abilities.css';
		$style_url  = ACROSSAI_ABILITIES_MANAGER_URL . 'build/css/custom-abilities.css';

		// Check if built stylesheet exists
		if ( file_exists( $style_path ) ) {
			$style_mtime = filemtime( $style_path );
			wp_enqueue_style(
				'acrossai-abilities-custom',
				$style_url,
				array( 'wp-components', 'wp-dataviews' ),
				$style_mtime
			);
		} else {
			// Fallback if stylesheet not built yet (development)
			wp_enqueue_style(
				'acrossai-abilities-custom',
				$style_url,
				array( 'wp-components', 'wp-dataviews' ),
				ACROSSAI_ABILITIES_MANAGER_VERSION
			);
		}
	}

	/**
	 * Check if current page is Custom Abilities admin page
	 *
	 * @since 1.0.0
	 * @return bool True if on Custom Abilities page, false otherwise
	 */
	private function is_custom_abilities_page() {
		// Check if we're on the admin page
		if ( ! is_admin() ) {
			return false;
		}

		// Check current page/screen
		$screen = get_current_screen();
		if ( null === $screen ) {
			return false;
		}

		// Check by base (handles both submenu and parent menu pages)
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		return 'acrossai-custom-abilities' === $page;
	}
}

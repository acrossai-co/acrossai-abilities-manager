<?php
/**
 * Ability Library submenu page.
 *
 * Registers and renders the Library submenu page under the Abilities Manager main menu.
 * Localizes collected ability definitions and REST config for the React UI.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin/Partials
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\Rest\AcrossAI_Ability_Library_Rest_Controller;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Ability Library submenu page handler.
 *
 * @since 0.1.0
 */
class LibraryMenu {

	/**
	 * Singleton instance.
	 *
	 * @var LibraryMenu|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return LibraryMenu
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Register the Library submenu page.
	 *
	 * Hook suffix is hardcoded in admin/Main.php::is_library_page() per DEC-MENU-HOOK-SUFFIX.
	 * The return value of add_submenu_page() is intentionally discarded.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'acrossai-abilities-manager',
			__( 'Ability Library', 'acrossai-abilities-manager' ),
			__( 'Library', 'acrossai-abilities-manager' ),
			'manage_options',
			'acrossai-abilities-library',
			array( $this, 'render' )
		);
	}

	/**
	 * Render the Library page.
	 *
	 * Outputs the page wrapper and localizes data for the React component.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function render(): void {
		$this->localize_data();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ability Library', 'acrossai-abilities-manager' ); ?></h1>
			<div id="acrossai-library-root"></div>
		</div>
		<?php
	}

	/**
	 * Localize ability definitions and REST config into window.acrossaiAbilityLibraryData.
	 *
	 * Definitions are localized here (not REST-fetched) because they only change on
	 * add-on activation/deactivation, which forces a page reload anyway (D4).
	 *
	 * @since  0.1.0
	 * @return void
	 */
	private function localize_data(): void {
		$definitions = AcrossAI_Ability_Library_Registry::instance()->get_definitions();

		wp_localize_script(
			'acrossai-ability-library-js',
			'acrossaiAbilityLibraryData',
			array(
				'definitions' => $definitions,
				'restBase'    => rest_url( AcrossAI_Ability_Library_Rest_Controller::REST_NAMESPACE ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}

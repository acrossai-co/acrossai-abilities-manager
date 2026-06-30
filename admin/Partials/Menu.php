<?php
/**
 * Admin Menu Page for AcrossAI Abilities Manager
 *
 * Main admin page with interface for Abilities and Overrides management.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/Admin/Partials
 * @since      0.0.1
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Menu class for admin page content
 *
 * @since 0.0.1
 */
class Menu {

	/**
	 * Plugin name
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties
	 *
	 * @since 0.0.1
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the Abilities submenu under the shared `acrossai` parent menu.
	 *
	 * Feature 038: the page is no longer a top-level menu. The shared parent
	 * menu is owned by the `acrossai-co/main-menu` package and is bootstrapped
	 * from acrossai-abilities-manager.php on plugins_loaded priority 0. The
	 * menu_slug `acrossai-abilities-manager` is preserved so existing
	 * bookmarked URLs (wp-admin/admin.php?page=acrossai-abilities-manager) and
	 * the JS bundle handles continue to resolve.
	 *
	 * Position 1 places this submenu immediately after the host Settings entry,
	 * matching the agreed sidebar order: Settings, Abilities, Library, Logs,
	 * Add-ons.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'acrossai',
			__( 'Abilities Manager', 'acrossai-abilities-manager' ),
			__( 'Abilities', 'acrossai-abilities-manager' ),
			'manage_options',
			'acrossai-abilities-manager',
			array( $this, 'contents' ),
			1
		);
	}

	/**
	 * Render admin page content
	 *
	 * Displays the main Abilities Manager interface.
	 * Execution Logs are available as a dedicated submenu page (Feature 006).
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function contents() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
		}
		?>
		<div class="wrap acrossai-abilities-manager-wrap">
			<!-- Main Abilities Manager React app -->
			<div id="acrossai-abilities-root"></div>
		</div>
		<?php
	}
}

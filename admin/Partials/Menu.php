<?php
/**
 * Admin Menu Page for AcrossAI Abilities Manager
 *
 * Main admin page with tabbed interface for Abilities, Overrides, and Logs.
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
	 * Add plugin menu page
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function main_menu() {
		add_menu_page(
			__( 'Abilities Manager', 'acrossai-abilities-manager' ),
			__( 'Abilities Manager', 'acrossai-abilities-manager' ),
			'manage_options',
			'acrossai-abilities-manager',
			array( $this, 'contents' ),
			'dashicons-admin-tools',
			99
		);
	}

	/**
	 * Render admin page content with tabs
	 *
	 * Displays main interface with tab navigation:
	 * - Abilities: Main ability management interface
	 * - Overrides: Ability override processor
	 * - Logs: Execution logs viewer (Feature 006)
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function contents() {
		?>
		<div class="wrap acrossai-abilities-manager-wrap">
			<!-- Main Abilities Manager React app (tabbed interface) -->
			<div id="acrossai-abilities-manager-root"></div>

			<!-- Logs tab content panel (T014: added for Feature 006 - Logger) -->
			<!-- Tab content is switched by main React app based on active tab -->
			<div id="acrossai-logs-tab-panel" style="display:none;" role="tabpanel">
				<!-- Logs table container (populated by LogsTable component in T015) -->
				<div id="acrossai-logs-container"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add Settings link to plugins area
	 *
	 * @since 0.0.1
	 * @param array  $links Links array in which we would prepend our link.
	 * @param string $file  Current plugin basename.
	 * @return array Processed links.
	 */
	public function plugin_action_links( $links, $file ) {
		// Return normal links if not this plugin.
		if ( \ACROSSAI_ABILITIES_MANAGER_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		// Add settings link to the existing links array.
		return array_merge(
			$links,
			array(
				'settings' => sprintf(
					'<a href="%sadmin.php?page=%s">%s</a>',
					admin_url(),
					'acrossai-abilities-manager',
					esc_html__( 'Settings', 'acrossai-abilities-manager' )
				),
			)
		);
	}
}

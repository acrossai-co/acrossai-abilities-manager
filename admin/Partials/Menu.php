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
		// Get active tab from query parameter, default to 'abilities'
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'abilities'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap acrossai-abilities-manager-wrap">
			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper acrossai-nav-tabs" style="margin-bottom: 20px; border-bottom: 1px solid #ddd;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=acrossai-abilities-manager&tab=abilities' ) ); ?>" class="nav-tab <?php echo 'abilities' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Abilities', 'acrossai-abilities-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=acrossai-abilities-manager&tab=logs' ) ); ?>" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Logs', 'acrossai-abilities-manager' ); ?>
				</a>
			</nav>

			<!-- Tab Content: Abilities -->
			<?php if ( 'abilities' === $active_tab ) : ?>
				<div id="acrossai-abilities-tab-panel" class="acrossai-tab-panel" role="tabpanel">
					<!-- Main Abilities Manager React app -->
					<div id="acrossai-abilities-manager-root"></div>
				</div>
			<?php endif; ?>

			<!-- Tab Content: Logs (Feature 006) -->
			<?php if ( 'logs' === $active_tab ) : ?>
				<div id="acrossai-logs-tab-panel" class="acrossai-tab-panel" role="tabpanel">
					<!-- Logs table container (populated by LogsTable component) -->
					<div id="acrossai-logs-container"></div>
				</div>
			<?php endif; ?>
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

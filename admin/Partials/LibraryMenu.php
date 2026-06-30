<?php
/**
 * Ability Library submenu page.
 *
 * Registers and renders the Library submenu page under the Abilities Manager main menu.
 * Page data is injected via wp_add_inline_script() in admin/Main::enqueue_scripts() (AC-ENQUEUE-ADMIN).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin/Partials
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

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
	 * Hook suffix returned by add_submenu_page(); used to guard script/style enqueuing.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

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
		$suffix = add_submenu_page(
			'acrossai',
			__( 'Ability Library', 'acrossai-abilities-manager' ),
			__( 'Library', 'acrossai-abilities-manager' ),
			'manage_options',
			'acrossai-abilities-library',
			array( $this, 'render' ),
			2
		);

		$this->hook_suffix = is_string( $suffix ) ? $suffix : '';
	}

	/**
	 * Return the hook suffix assigned by add_submenu_page().
	 *
	 * Used by admin/Main::is_library_page() to guard script/style enqueuing.
	 *
	 * @since  0.1.0
	 * @return string
	 */
	public function get_hook_suffix(): string {
		return $this->hook_suffix;
	}

	/**
	 * Render the Library page HTML wrapper.
	 *
	 * The window.acrossaiAbilityLibraryData global is injected by admin/Main::enqueue_scripts()
	 * via wp_add_inline_script() before the ability-library.js script tag (AC-ENQUEUE-ADMIN).
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ability Library', 'acrossai-abilities-manager' ); ?></h1>
			<div id="acrossai-library-root"></div>
		</div>
		<?php
	}
}

<?php
/**
 * REST sub-controller: MCP server listing.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Rest_Controller;
use WPBoilerplate\McpServersList\McpServersList;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles GET /sitewide/mcp-servers.
 *
 * Server data is collected by McpServersList::collect() which is wired in
 * Main::define_admin_hooks() at rest_api_init priority 20 (after McpAdapter's
 * priority 15). This handler simply reads from the already-populated cache.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Mcp_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Sitewide_Mcp_Controller|null
	 */
	protected static $_instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Sitewide_Mcp_Controller
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Register REST routes owned by this controller.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			AcrossAI_Sitewide_Rest_Controller::REST_NAMESPACE,
			'/sitewide/mcp-servers',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_mcp_servers' ),
					'permission_callback' => array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Handle GET /sitewide/mcp-servers.
	 *
	 * Returns the server list collected by McpServersList at rest_api_init
	 * priority 20. ServerData implements JsonSerializable so rest_ensure_response()
	 * serializes each entry correctly without manual mapping.
	 *
	 * @since  0.1.0
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_mcp_servers(): \WP_REST_Response {
		return rest_ensure_response( McpServersList::instance()->get_servers() );
	}
}

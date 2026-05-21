<?php
/**
 * REST API MCP Sub-Controller for Custom Abilities
 *
 * Handles GET requests for exposing custom abilities to MCP servers.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability\Rest
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Table;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Custom_Ability_Formatter;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class AcrossAI_Custom_Ability_Mcp_Controller
 *
 * REST sub-controller for MCP exposure queries (GET /mcp/tools, /mcp/resources, /mcp/prompts).
 * Filters abilities by show_in_mcp, mcp_type, and current MCP server scope.
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Mcp_Controller {

	/**
	 * Singleton instance.
	 *
	 * @since 0.0.1
	 * @var self
	 */
	protected static $_instance = null;

	/**
	 * Orchestrator controller instance (for permission checking).
	 *
	 * @since 0.0.1
	 * @var AcrossAI_Custom_Ability_Rest_Controller
	 */
	protected $orchestrator;

	/**
	 * Custom Ability Table instance.
	 *
	 * @since 0.0.1
	 * @var AcrossAI_Custom_Ability_Table
	 */
	protected $table;

	/**
	 * Get singleton instance.
	 *
	 * @since 0.0.1
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Private constructor (singleton pattern).
	 *
	 * @since 0.0.1
	 */
	private function __construct() {
		$this->orchestrator = AcrossAI_Custom_Ability_Rest_Controller::instance();
		$this->table        = AcrossAI_Custom_Ability_Table::instance();
	}

	/**
	 * Register REST routes for MCP exposure.
	 *
	 * @since 0.0.1
	 * @param string $namespace REST API namespace.
	 * @param string $rest_base REST base route.
	 */
	public function register_routes( $namespace, $rest_base ) {
		// GET /custom-abilities/mcp/tools
		register_rest_route(
			$namespace,
			'/' . $rest_base . '/mcp/tools',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_mcp_tools' ),
					'permission_callback' => array( $this->orchestrator, 'check_permission' ),
					'args'                => $this->get_mcp_query_params(),
				),
			)
		);

		// GET /custom-abilities/mcp/resources
		register_rest_route(
			$namespace,
			'/' . $rest_base . '/mcp/resources',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_mcp_resources' ),
					'permission_callback' => array( $this->orchestrator, 'check_permission' ),
					'args'                => $this->get_mcp_query_params(),
				),
			)
		);

		// GET /custom-abilities/mcp/prompts
		register_rest_route(
			$namespace,
			'/' . $rest_base . '/mcp/prompts',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_mcp_prompts' ),
					'permission_callback' => array( $this->orchestrator, 'check_permission' ),
					'args'                => $this->get_mcp_query_params(),
				),
			)
		);
	}

	/**
	 * Get custom abilities exposed as MCP tools.
	 *
	 * GET /custom-abilities/mcp/tools
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with tools array or error.
	 */
	public function get_mcp_tools( \WP_REST_Request $request ) {
		return $this->get_mcp_abilities( $request, 'tool' );
	}

	/**
	 * Get custom abilities exposed as MCP resources.
	 *
	 * GET /custom-abilities/mcp/resources
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with resources array or error.
	 */
	public function get_mcp_resources( \WP_REST_Request $request ) {
		return $this->get_mcp_abilities( $request, 'resource' );
	}

	/**
	 * Get custom abilities exposed as MCP prompts.
	 *
	 * GET /custom-abilities/mcp/prompts
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with prompts array or error.
	 */
	public function get_mcp_prompts( \WP_REST_Request $request ) {
		return $this->get_mcp_abilities( $request, 'prompt' );
	}

	/**
	 * Get custom abilities matching MCP type and server filter.
	 *
	 * Core MCP filtering logic:
	 * - Filter: show_in_mcp = true
	 * - Filter: mcp_type matches requested type (tool/resource/prompt)
	 * - Server filtering: all enabled MCP abilities exposed to all servers (mcp_servers removed)
	 * - Fire: acrossai_custom_ability_mcp_query hook for extensibility
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @param string           $mcp_type MCP type: 'tool', 'resource', or 'prompt'.
	 * @return \WP_REST_Response|\WP_Error Response with formatted abilities or error.
	 */
	protected function get_mcp_abilities( \WP_REST_Request $request, $mcp_type ) {
		try {
			$current_server = sanitize_text_field( $request->get_param( 'server' ) ) ?: null;

			// Fetch all enabled abilities
			$abilities = ( new AcrossAI_Custom_Ability_Query() )
				->enabled_only()
				->with_pagination( 1000, 1 )
				->get();

			// Filter: show_in_mcp = true
			$mcp_abilities = array_filter(
				$abilities,
				function( $ability ) {
					return (bool) $ability->show_in_mcp;
				}
			);

			// Filter: mcp_type matches
			$mcp_abilities = array_filter(
				$mcp_abilities,
				function( $ability ) use ( $mcp_type ) {
					return $ability->mcp_type === $mcp_type;
				}
			);

			// mcp_servers column removed — all enabled MCP abilities are exposed to all servers.

			// Format abilities for MCP response
			$data = AcrossAI_Custom_Ability_Formatter::format_for_mcp( $mcp_abilities, $mcp_type, $current_server );

			// Fire MCP query hook for extensibility
			do_action( 'acrossai_custom_ability_mcp_query', $mcp_type, $data );

			// Return response
			$response = new \WP_REST_Response(
				array(
					'type'  => $mcp_type,
					'items' => $data,
				),
				200
			);

			return $response;
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'rest_error',
				esc_html( $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get MCP query parameters.
	 *
	 * @since 0.0.1
	 * @return array MCP query parameters.
	 */
	protected function get_mcp_query_params() {
		return array(
			'server' => array(
				'description'       => __( 'Filter by MCP server slug. Leave empty to get all servers.', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}

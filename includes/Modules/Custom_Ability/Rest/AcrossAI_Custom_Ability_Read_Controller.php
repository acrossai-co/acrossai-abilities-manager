<?php
/**
 * REST API Read Sub-Controller for Custom Abilities
 *
 * Handles GET requests for listing and retrieving individual custom abilities.
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
 * Class AcrossAI_Custom_Ability_Read_Controller
 *
 * REST sub-controller for read-only operations (GET list, GET single).
 * Query layer filtering is delegated to database Query class (Memory AC-QUERY-LAYER-FILTERING).
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Read_Controller {

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
	 * Custom Ability Table instance (for schema/table-exists checks).
	 *
	 * @since 0.0.1
	 * @var AcrossAI_Custom_Ability_Table
	 */
	protected $table;

	/**
	 * Custom Ability Query instance (for data retrieval).
	 *
	 * @since 0.0.1
	 * @var AcrossAI_Custom_Ability_Query
	 */
	protected $db_query;

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
		$this->db_query     = AcrossAI_Custom_Ability_Query::instance();
	}

	/**
	 * Register REST routes for read operations.
	 *
	 * @since 0.0.1
	 * @param string $namespace REST API namespace.
	 * @param string $rest_base REST base route.
	 */
	public function register_routes( $namespace, $rest_base ) {
		// GET /custom-abilities (list with pagination, search, filter)
		register_rest_route(
			$namespace,
			'/' . $rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this->orchestrator, 'check_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// GET /custom-abilities/{id} (fetch single ability by ID)
		register_rest_route(
			$namespace,
			'/' . $rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this->orchestrator, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'The ability ID.', 'acrossai-abilities-manager' ),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Get custom abilities list with pagination, search, and filtering.
	 *
	 * Delegates filtering to Query layer (Memory AC-QUERY-LAYER-FILTERING).
	 *
	 * GET /custom-abilities
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with abilities or error.
	 */
	public function get_items( \WP_REST_Request $request ) {
		try {
			// Extract query parameters with defaults
			$page     = absint( $request->get_param( 'page' ) ) ?: 1;
			$per_page = absint( $request->get_param( 'per_page' ) ) ?: 20;
			$search   = sanitize_text_field( $request->get_param( 'search' ) ) ?: '';
			$order    = sanitize_text_field( $request->get_param( 'order' ) ) ?: 'asc';
			$orderby  = sanitize_text_field( $request->get_param( 'orderby' ) ) ?: 'label';
			$enabled  = $request->get_param( 'enabled' );
			$mcp_only = $request->get_param( 'show_in_mcp' );

			// Validate pagination
			if ( ! $this->table->exists() ) {
				$this->table->maybe_upgrade();
				if ( ! $this->table->exists() ) {
					$response = new \WP_REST_Response( array(), 200 );
					$response->header( 'X-WP-Total', 0 );
					$response->header( 'X-WP-TotalPages', 0 );
					return $response;
				}
			}

			// Validate pagination
			if ( $per_page < 1 || $per_page > 100 ) {
				$per_page = 20;
			}
			if ( $page < 1 ) {
				$page = 1;
			}

			// Validate sort parameters
			$allowed_orderby = array( 'label', 'ability_slug', 'created_at', 'updated_at' );
			if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
				$orderby = 'label';
			}
			$order = 'desc' === strtolower( $order ) ? 'DESC' : 'ASC';

			// Build query via Query layer (single source of truth per Memory AC-QUERY-LAYER-FILTERING)
			$query = new AcrossAI_Custom_Ability_Query();

			// Apply search filter (if provided)
			if ( ! empty( $search ) ) {
				$query->search( $search );
			}

			// Apply enabled filter (if provided, 0 or 1)
			if ( isset( $enabled ) && is_numeric( $enabled ) ) {
				if ( $enabled ) {
					$query->enabled_only();
				}
				// TODO: add disabled_only() if needed for future UI
			}

			// Apply MCP filter (if provided)
			if ( isset( $mcp_only ) && $mcp_only ) {
				// Query layer needs mcp_only() method: WHERE show_in_mcp = 1
				// Placeholder for future Query method
				// $query->mcp_only();
			}

			// Apply sorting
			$query->order_by( $orderby, $order );

			// Apply pagination
			$query->with_pagination( $per_page, $page );

			// Execute query
			$abilities = $query->get();
			$total     = $query->found_items;

			// Format responses
			$data = array();
			foreach ( $abilities as $ability ) {
				$data[] = AcrossAI_Custom_Ability_Formatter::format_ability_for_response( $ability );
			}

			// Build response with pagination headers
			$response = new \WP_REST_Response( $data, 200 );
			$response->header( 'X-WP-Total', (int) $total );
			$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

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
	 * Get single custom ability by ID.
	 *
	 * GET /custom-abilities/{id}
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with ability or error.
	 */
	public function get_item( \WP_REST_Request $request ) {
		try {
			$ability_id = absint( $request->get_param( 'id' ) );

			if ( ! $this->table->exists() ) {
				$this->table->maybe_upgrade();
				if ( ! $this->table->exists() ) {
					return new \WP_Error(
						'rest_not_found',
						esc_html__( 'Custom abilities table is not available yet.', 'acrossai-abilities-manager' ),
						array( 'status' => 404 )
					);
				}
			}

			if ( $ability_id < 1 ) {
				return new \WP_Error(
					'rest_invalid_param',
					esc_html__( 'Invalid ability ID.', 'acrossai-abilities-manager' ),
					array( 'status' => 400 )
				);
			}

			$ability = $this->db_query->get_ability_by_id( $ability_id );

			if ( ! $ability ) {
				return new \WP_Error(
					'rest_not_found',
					esc_html__( 'Ability not found.', 'acrossai-abilities-manager' ),
					array( 'status' => 404 )
				);
			}

			$data = AcrossAI_Custom_Ability_Formatter::format_ability_for_response( $ability );

			return new \WP_REST_Response( $data, 200 );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'rest_error',
				esc_html( $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get collection parameters for GET /custom-abilities.
	 *
	 * @since 0.0.1
	 * @return array Collection parameters.
	 */
	protected function get_collection_params() {
		return array(
			'page'        => array(
				'description'       => __( 'Current page of the collection.', 'acrossai-abilities-manager' ),
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page'    => array(
				'description'       => __( 'Maximum number of items to be returned in result set.', 'acrossai-abilities-manager' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'search'      => array(
				'description'       => __( 'Limit results to those matching a string.', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'order'       => array(
				'description'       => __( 'Order sort attribute ascending or descending.', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'default'           => 'asc',
				'enum'              => array( 'asc', 'desc' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'orderby'     => array(
				'description'       => __( 'Sort collection by object attribute.', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'default'           => 'label',
				'enum'              => array( 'label', 'ability_slug', 'created_at', 'updated_at' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'enabled'     => array(
				'description'       => __( 'Filter by enabled status (0 or 1).', 'acrossai-abilities-manager' ),
				'type'              => 'boolean',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'show_in_mcp' => array(
				'description'       => __( 'Filter by MCP exposure (0 or 1).', 'acrossai-abilities-manager' ),
				'type'              => 'boolean',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}

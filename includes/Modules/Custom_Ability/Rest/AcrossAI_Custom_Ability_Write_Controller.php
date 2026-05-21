<?php
/**
 * REST API Write Sub-Controller for Custom Abilities
 *
 * Handles POST requests for creating and updating abilities, DELETE for removal.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability\Rest
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Table;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Custom_Ability_Formatter;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Custom_Ability_Sanitizer;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Custom_Ability_Validator;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class AcrossAI_Custom_Ability_Write_Controller
 *
 * REST sub-controller for write operations (POST create, POST/:id update, DELETE).
 * Implements full validation pipeline per Memory BUG-PARTIAL-HOOK-FIELDS and SEC-02.
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Write_Controller {

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
	 * Custom Ability Query instance (for CRUD).
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
	 * Register REST routes for write operations.
	 *
	 * @since 0.0.1
	 * @param string $namespace REST API namespace.
	 * @param string $rest_base REST base route.
	 */
	public function register_routes( $namespace, $rest_base ) {
		// POST /custom-abilities (create new ability)
		register_rest_route(
			$namespace,
			'/' . $rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this->orchestrator, 'check_permission' ),
					'args'                => $this->get_create_args(),
				),
			)
		);

		// POST /custom-abilities/{id} (update existing ability)
		register_rest_route(
			$namespace,
			'/' . $rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this->orchestrator, 'check_permission' ),
					'args'                => $this->get_update_args(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
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
	 * Ensure the custom abilities table is available before writes.
	 *
	 * @since 0.0.1
	 * @return true|\WP_Error True when table is ready, WP_Error otherwise.
	 */
	protected function ensure_table_ready() {
		if ( $this->table->exists() ) {
			return true;
		}

		$this->table->maybe_upgrade();
		if ( $this->table->exists() ) {
			return true;
		}

		return new \WP_Error(
			'rest_error',
			esc_html__( 'Custom abilities table is unavailable.', 'acrossai-abilities-manager' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Create a new custom ability.
	 *
	 * POST /custom-abilities
	 * Full validation pipeline: sanitize → validate → check collision → save → fetch complete row → fire hooks
	 * Per Memory BUG-PARTIAL-HOOK-FIELDS and SEC-02.
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with ability or error.
	 */
	public function create_item( \WP_REST_Request $request ) {
		try {
			$table_ready = $this->ensure_table_ready();
			if ( is_wp_error( $table_ready ) ) {
				return $table_ready;
			}

			// Step 1: Extract and sanitize input
			$fields = $this->extract_fields( $request );
			$fields = AcrossAI_Custom_Ability_Sanitizer::sanitize_ability( $fields );

			// Step 2: Validate sanitized input
			$validation = AcrossAI_Custom_Ability_Validator::validate_ability( $fields );
			if ( is_wp_error( $validation ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					esc_html( $validation->get_error_message() ),
					array( 'status' => 400 )
				);
			}

			// Step 3: Check for slug collision (uniqueness).
			if ( isset( $fields['ability_slug'] ) && $this->db_query->slug_exists( $fields['ability_slug'] ) ) {
				return new \WP_Error(
					'rest_duplicate',
					esc_html__( 'Ability slug already exists.', 'acrossai-abilities-manager' ),
					array( 'status' => 409 )
				);
			}

			// Step 4: Cast bool fields to int before save (Memory SEC-02)
			$fields = AcrossAI_Custom_Ability_Sanitizer::cast_to_db_format( $fields );

			// Step 5: Fire before_save hook with sanitized $fields
			do_action( 'acrossai_custom_ability_before_save', $fields, null );

			// Step 6: Save to database
			$ability_id = $this->db_query->insert_ability( $fields );

			if ( ! $ability_id ) {
				return new \WP_Error(
					'rest_error',
					esc_html__( 'Failed to create ability.', 'acrossai-abilities-manager' ),
					array( 'status' => 500 )
				);
			}

			// Step 7: Fetch complete row from database (Memory BUG-PARTIAL-HOOK-FIELDS)
			$ability_row = $this->db_query->get_ability_by_id( $ability_id );

			if ( ! $ability_row ) {
				return new \WP_Error(
					'rest_error',
					esc_html__( 'Failed to retrieve created ability.', 'acrossai-abilities-manager' ),
					array( 'status' => 500 )
				);
			}

			// Step 8: Fire after_save hook with complete row
			do_action( 'acrossai_custom_ability_after_save', $ability_row, true );

			// Step 9: Format and return response
			$data = AcrossAI_Custom_Ability_Formatter::format_ability_for_response( $ability_row );
			$response = new \WP_REST_Response( $data, 201 );

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
	 * Update an existing custom ability.
	 *
	 * POST /custom-abilities/{id}
	 * Same validation pipeline as create_item, checking for slug collision only if slug changed.
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with ability or error.
	 */
	public function update_item( \WP_REST_Request $request ) {
		try {
			$table_ready = $this->ensure_table_ready();
			if ( is_wp_error( $table_ready ) ) {
				return $table_ready;
			}

			$ability_id = absint( $request->get_param( 'id' ) );

			if ( $ability_id < 1 ) {
				return new \WP_Error(
					'rest_invalid_param',
					esc_html__( 'Invalid ability ID.', 'acrossai-abilities-manager' ),
					array( 'status' => 400 )
				);
			}

			// Verify ability exists
			$existing_ability = $this->db_query->get_ability_by_id( $ability_id );

			if ( ! $existing_ability ) {
				return new \WP_Error(
					'rest_not_found',
					esc_html__( 'Ability not found.', 'acrossai-abilities-manager' ),
					array( 'status' => 404 )
				);
			}

			// Step 1: Extract and sanitize input
			$fields = $this->extract_fields( $request );
			$fields = AcrossAI_Custom_Ability_Sanitizer::sanitize_ability( $fields );

			// Step 2: Validate sanitized input
			$validation = AcrossAI_Custom_Ability_Validator::validate_ability( $fields );
			if ( is_wp_error( $validation ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					esc_html( $validation->get_error_message() ),
					array( 'status' => 400 )
				);
			}

			// Step 3: Check for slug collision only if slug changed.
			if ( isset( $fields['ability_slug'] ) && $fields['ability_slug'] !== $existing_ability->ability_slug ) {
				if ( $this->db_query->slug_exists( $fields['ability_slug'] ) ) {
					return new \WP_Error(
						'rest_duplicate',
						esc_html__( 'Ability slug already exists.', 'acrossai-abilities-manager' ),
						array( 'status' => 409 )
					);
				}
			}

			// Step 4: Cast bool fields to int before save
			$fields = AcrossAI_Custom_Ability_Sanitizer::cast_to_db_format( $fields );

			// Step 5: Fire before_save hook with sanitized $fields
			do_action( 'acrossai_custom_ability_before_save', $fields, $ability_id );

			// Step 6: Update in database
			$updated = $this->db_query->update_ability( $ability_id, $fields );

			if ( ! $updated ) {
				return new \WP_Error(
					'rest_error',
					esc_html__( 'Failed to update ability.', 'acrossai-abilities-manager' ),
					array( 'status' => 500 )
				);
			}

			// Step 7: Fetch complete row from database (Memory BUG-PARTIAL-HOOK-FIELDS)
			$ability_row = $this->db_query->get_ability_by_id( $ability_id );

			if ( ! $ability_row ) {
				return new \WP_Error(
					'rest_error',
					esc_html__( 'Failed to retrieve updated ability.', 'acrossai-abilities-manager' ),
					array( 'status' => 500 )
				);
			}

			// Step 8: Fire after_save hook with complete row
			do_action( 'acrossai_custom_ability_after_save', $ability_row, false );

			// Step 9: Format and return response
			$data = AcrossAI_Custom_Ability_Formatter::format_ability_for_response( $ability_row );
			$response = new \WP_REST_Response( $data, 200 );

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
	 * Delete a custom ability by ID.
	 *
	 * DELETE /custom-abilities/{id}
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with deletion confirmation or error.
	 */
	public function delete_item( \WP_REST_Request $request ) {
		try {
			$table_ready = $this->ensure_table_ready();
			if ( is_wp_error( $table_ready ) ) {
				return $table_ready;
			}

			$ability_id = absint( $request->get_param( 'id' ) );

			if ( $ability_id < 1 ) {
				return new \WP_Error(
					'rest_invalid_param',
					esc_html__( 'Invalid ability ID.', 'acrossai-abilities-manager' ),
					array( 'status' => 400 )
				);
			}

			// Verify ability exists before deletion
			$ability = $this->db_query->get_ability_by_id( $ability_id );

			if ( ! $ability ) {
				return new \WP_Error(
					'rest_not_found',
					esc_html__( 'Ability not found.', 'acrossai-abilities-manager' ),
					array( 'status' => 404 )
				);
			}

			// Store slug for hook before deletion
			$ability_slug = $ability->ability_slug;

			// Delete from database
			$deleted = $this->db_query->delete_ability( $ability_id );

			if ( ! $deleted ) {
				return new \WP_Error(
					'rest_error',
					esc_html__( 'Failed to delete ability.', 'acrossai-abilities-manager' ),
					array( 'status' => 500 )
				);
			}

			// Fire delete hook
			do_action( 'acrossai_custom_ability_deleted', $ability_id, $ability_slug );

			// Return 204 No Content (standard for successful DELETE)
			return new \WP_REST_Response( null, 204 );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'rest_error',
				esc_html( $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Extract and filter request fields for create operation.
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return array Extracted fields from request.
	 */
	protected function extract_fields( \WP_REST_Request $request ) {
		$allowed_fields = array(
			'ability_slug',
			'label',
			'description',
			'enabled',
			'callback_type',
			'callback_config',
			'input_schema',
			'output_schema',
			'show_in_rest',
			'show_in_mcp',
			'mcp_type',
			'readonly',
			'destructive',
			'idempotent',
		);

		$fields = array();

		foreach ( $allowed_fields as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$fields[ $field ] = $request->get_param( $field );
			}
		}

		// Prepend fixed namespace — user submits the suffix only.
		if ( isset( $fields['ability_slug'] ) ) {
			$suffix = ltrim( (string) $fields['ability_slug'], '/' );
			if ( 0 !== strpos( $suffix, 'acrossai-custom-abilities/' ) ) {
				$fields['ability_slug'] = 'acrossai-custom-abilities/' . $suffix;
			}
		}

		return $fields;
	}

	/**
	 * Get create operation arguments.
	 *
	 * @since 0.0.1
	 * @return array Create operation arguments.
	 */
	protected function get_create_args() {
		return $this->get_base_args();
	}

	/**
	 * Get update operation arguments.
	 *
	 * @since 0.0.1
	 * @return array Update operation arguments.
	 */
	protected function get_update_args() {
		$args = $this->get_base_args();

		// Make all fields optional for update (only update provided fields)
		foreach ( $args as &$arg ) {
			$arg['required'] = false;
		}

		return $args;
	}

	/**
	 * Get base arguments for POST/PUT operations.
	 *
	 * @since 0.0.1
	 * @return array Base arguments.
	 */
	protected function get_base_args() {
		return array(
			'ability_slug'        => array(
				'description'       => __( 'Unique ability slug (format: namespace/name).', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'label'               => array(
				'description'       => __( 'Display label for the ability.', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'description'         => array(
				'description'       => __( 'Full description of the ability.', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'enabled'             => array(
				'description'       => __( 'Enable this ability (auto-register at WordPress init).', 'acrossai-abilities-manager' ),
				'type'              => 'boolean',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'callback_type'       => array(
				'description'       => __( 'Callback type (noop, filter_hook, wp_remote_post).', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'required'          => true,
				'enum'              => array( 'noop', 'filter_hook', 'wp_remote_post' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'callback_config'     => array(
				'description'       => __( 'Callback configuration (JSON).', 'acrossai-abilities-manager' ),
				'type'              => 'object',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'input_schema'        => array(
				'description'       => __( 'Input schema (JSON Schema Draft 7).', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'output_schema'       => array(
				'description'       => __( 'Output schema (JSON Schema Draft 7).', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'show_in_rest'        => array(
				'description'       => __( 'Expose via REST API.', 'acrossai-abilities-manager' ),
				'type'              => 'boolean',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'show_in_mcp'         => array(
				'description'       => __( 'Expose to MCP servers.', 'acrossai-abilities-manager' ),
				'type'              => 'boolean',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'mcp_type'            => array(
				'description'       => __( 'MCP type (tool, resource, prompt).', 'acrossai-abilities-manager' ),
				'type'              => 'string',
				'enum'              => array( 'tool', 'resource', 'prompt' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'readonly'            => array(
				'description'       => __( 'Metadata: readonly flag (null, 0, 1).', 'acrossai-abilities-manager' ),
				'type'              => array( 'null', 'integer' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'destructive'         => array(
				'description'       => __( 'Metadata: destructive flag (null, 0, 1).', 'acrossai-abilities-manager' ),
				'type'              => array( 'null', 'integer' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'idempotent'          => array(
				'description'       => __( 'Metadata: idempotent flag (null, 0, 1).', 'acrossai-abilities-manager' ),
				'type'              => array( 'null', 'integer' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}

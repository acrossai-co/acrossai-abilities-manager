<?php
/**
 * REST sub-controller: ability override write operations and toggle (US2, US3, US4).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Rest_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Merger;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Source_Detector;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles POST /sitewide/abilities/{slug}, DELETE /sitewide/abilities/{slug},
 * and POST /sitewide/abilities/{slug}/toggle.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Override_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Sitewide_Override_Controller|null
	 */
	protected static $_instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Sitewide_Override_Controller
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * BerlinDB query instance.
	 *
	 * @var AcrossAI_Sitewide_Query
	 */
	private $db_query;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->db_query = AcrossAI_Sitewide_Query::instance();
	}

	/**
	 * Register REST routes owned by this controller.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		$permission = array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' );

		// US2: Toggle site_allowed.
		register_rest_route(
			AcrossAI_Sitewide_Rest_Controller::REST_NAMESPACE,
			'/sitewide/abilities/(?P<slug>[a-zA-Z0-9\-_\/]+)/toggle',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle_ability' ),
					'permission_callback' => $permission,
					'args'                => array(
						'slug'         => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_ability_slug' ),
						),
						'site_allowed' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);

		// US3/US4: Save or delete per-ability override.
		register_rest_route(
			AcrossAI_Sitewide_Rest_Controller::REST_NAMESPACE,
			'/sitewide/abilities/(?P<slug>[a-zA-Z0-9\-_\/]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_override' ),
					'permission_callback' => $permission,
					'args'                => array(
						'slug'         => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_ability_slug' ),
						),
						// All 8 overridable fields must be declared — missing args cause WP to reject
						// the entire request with 'Invalid parameter(s)' before the callback runs.
						'site_allowed' => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'readonly'     => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'destructive'  => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'idempotent'   => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'show_in_rest' => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'show_in_mcp'  => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'mcp_type'     => array(
							'required'          => false,
							'type'              => array( 'string', 'null' ),
							'enum'              => array( 'tool', 'resource', 'prompt', null ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_mcp_type' ),
						),
						'mcp_servers'  => array(
							'required'          => false,
							'type'              => array( 'array', 'null' ),
							'items'             => array( 'type' => 'string' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_mcp_servers_array' ),
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_override' ),
					'permission_callback' => $permission,
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_ability_slug' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Handle POST /sitewide/abilities/{slug} — save per-ability override.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_override( \WP_REST_Request $request ) {
		$slug = AcrossAI_Sanitizer::sanitize_ability_slug( (string) $request->get_param( 'slug' ) );

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new \WP_Error( 'rest_not_supported', __( 'Abilities API not available.', 'acrossai-abilities-manager' ), array( 'status' => 501 ) );
		}

		$registry = wp_get_ability( $slug );
		if ( empty( $registry ) ) {
			return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
		}
		$registry = AcrossAI_Ability_Merger::normalize_registry( $registry );

		// Only collect fields that were explicitly sent in the request body.
		// Per-tab save: General tab sends 5 fields; MCP tab sends 3 fields.
		// Collecting absent fields via get_param() returns null and would
		// overwrite the other tab's saved DB values with NULL on UPDATE.
		// has_param() is true even when the field is explicitly null in the body
		// (intentional "clear this field"), so only truly absent fields are skipped.
		$fields      = array();
		$tri_state   = array( 'site_allowed', 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'show_in_mcp' );
		foreach ( $tri_state as $field ) {
			if ( $request->has_param( $field ) ) {
				$fields[ $field ] = AcrossAI_Sanitizer::sanitize_tri_state( $request->get_param( $field ) );
			}
		}
		if ( $request->has_param( 'mcp_type' ) ) {
			$fields['mcp_type'] = AcrossAI_Sanitizer::sanitize_mcp_type( $request->get_param( 'mcp_type' ) );
		}
		if ( $request->has_param( 'mcp_servers' ) ) {
			$fields['mcp_servers'] = AcrossAI_Sanitizer::sanitize_mcp_servers_array( $request->get_param( 'mcp_servers' ) );
		}

		// Detect and set source (RF-04).
		$fields['source'] = AcrossAI_Ability_Source_Detector::detect( $registry );

		// Only skip the write when there is NO existing DB row AND every submitted field
		// is already at its registry default. This avoids creating a pointless row when
		// the user opens the panel and saves without making any changes.
		//
		// If a DB row already exists we must always write — the user may have explicitly
		// chosen "Keep as Default" (all nulls) to clear a previously saved override, and
		// those nulls must reach the DB. Comparing only against registry defaults here
		// would cause is_all_default() to return true (null == registry null) and skip
		// the write, leaving the old non-null DB values intact.
		$existing = $this->db_query->get_override_by_slug( $slug );
		if ( ! $existing && AcrossAI_Ability_Merger::is_all_default( $fields, $registry ) ) {
			return rest_ensure_response( array( 'unchanged' => true ) );
		}

		/**
		 * Fires before saving an ability override.
		 *
		 * @since 0.1.0
		 * @param string $slug   Ability slug.
		 * @param array  $fields Sanitized fields to save.
		 */
		do_action( 'acrossai_abilities_sitewide_before_save', $slug, $fields );

		$saved = $this->db_query->save_override( $slug, $fields );

		if ( ! $saved ) {
			return new \WP_Error( 'rest_save_failed', __( 'Failed to save override.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after saving an ability override.
		 *
		 * @since 0.1.0
		 * @param string $slug   Ability slug.
		 * @param array  $fields Sanitized fields that were saved.
		 */
		do_action( 'acrossai_abilities_sitewide_after_save', $slug, $fields );

		$override = $this->db_query->get_override_by_slug( $slug );
		$merged   = AcrossAI_Ability_Merger::merge( $registry, $override );

		return rest_ensure_response( $merged );
	}

	/**
	 * Handle DELETE /sitewide/abilities/{slug} — remove stored override.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_override( \WP_REST_Request $request ) {
		$slug = AcrossAI_Sanitizer::sanitize_ability_slug( (string) $request->get_param( 'slug' ) );

		if ( function_exists( 'wp_get_ability' ) ) {
			$registry = wp_get_ability( $slug );
			if ( empty( $registry ) ) {
				return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
			}
		}

		$deleted = $this->db_query->delete_override_by_slug( $slug );

		if ( ! $deleted ) {
			return rest_ensure_response(
				array(
					'slug'    => $slug,
					'deleted' => false,
					'message' => __( 'No override existed for this ability.', 'acrossai-abilities-manager' ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'slug'    => $slug,
				'deleted' => true,
			)
		);
	}

	/**
	 * Handle POST /sitewide/abilities/{slug}/toggle.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function toggle_ability( \WP_REST_Request $request ) {
		$slug         = AcrossAI_Sanitizer::sanitize_ability_slug( (string) $request->get_param( 'slug' ) );
		$site_allowed = AcrossAI_Sanitizer::sanitize_tri_state( $request->get_param( 'site_allowed' ) );

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new \WP_Error( 'rest_not_supported', __( 'Abilities API not available.', 'acrossai-abilities-manager' ), array( 'status' => 501 ) );
		}

		$registry = wp_get_ability( $slug );
		if ( empty( $registry ) ) {
			return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
		}
		$registry = AcrossAI_Ability_Merger::normalize_registry( $registry );

		$source = AcrossAI_Ability_Source_Detector::detect( $registry );
		$fields = array(
			'site_allowed' => $site_allowed,
			'source'       => $source,
		);

		$saved = $this->db_query->save_override( $slug, $fields );
		if ( ! $saved ) {
			return new \WP_Error( 'rest_save_failed', __( 'Failed to save toggle.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after toggling an ability override.
		 *
		 * @since 0.1.0
		 * @param string $slug   Ability slug.
		 * @param array  $fields Sanitized fields that were saved.
		 */
		do_action( 'acrossai_abilities_sitewide_after_save', $slug, $fields );

		$override = $this->db_query->get_override_by_slug( $slug );

		return rest_ensure_response(
			array(
				'slug'         => $slug,
				'site_allowed' => null !== $override ? $override->site_allowed : $site_allowed,
				'has_override' => null !== $override,
			)
		);
	}
}

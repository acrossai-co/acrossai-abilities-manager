<?php
/**
 * REST sub-controller: ability library config.
 *
 * Handles:
 *   GET  /acrossai-abilities-library/v1/abilities/config — read current toggle config
 *   POST /acrossai-abilities-library/v1/abilities/config — save toggle config
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Modules/Library/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Config;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles GET and POST for /abilities/config.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Library_Config_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Ability_Library_Config_Controller|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Ability_Library_Config_Controller
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
	 * Register REST routes owned by this controller.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			AcrossAI_Ability_Library_Rest_Controller::REST_NAMESPACE,
			'/abilities/config',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_config' ),
					'permission_callback' => array( AcrossAI_Ability_Library_Rest_Controller::instance(), 'check_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_config' ),
					'permission_callback' => array( AcrossAI_Ability_Library_Rest_Controller::instance(), 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Handle GET /abilities/config.
	 *
	 * Returns the full saved library config object.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_config( \WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return rest_ensure_response( AcrossAI_Ability_Library_Config::get_config() );
	}

	/**
	 * Handle POST /abilities/config.
	 *
	 * Validates, sanitizes, and stores the submitted config. Returns the saved state.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_config( \WP_REST_Request $request ) {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Request body must be a JSON object.', 'acrossai-abilities-manager' ),
				array( 'status' => 400 )
			);
		}

		// Feature 060 — per-integration capability gate.
		// The existing `check_permission` route-level check enforces the
		// manage_options floor. For each incoming entry whose category matches
		// a registered integration slug, additionally verify the filtered
		// capability. Both checks MUST pass. Filter default (manage_options)
		// leaves single-site behaviour unchanged; sites can raise the bar
		// via the filter without a code change (spec FR-016).
		//
		// The slug passed to the filter is the POST-sanitisation value
		// (SEC-005) so filter authors receive a predictable canonical key.
		$integration_slugs = class_exists( \AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry::class )
			? \AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry::instance()->get_integration_slugs()
			: array();
		foreach ( $body as $raw_key => $raw_entry ) {
			$clean_key = AcrossAI_Ability_Library_Config::sanitize_key_field( (string) $raw_key );
			if ( '' === $clean_key || ! in_array( $clean_key, $integration_slugs, true ) ) {
				continue;
			}

			$required_cap = (string) apply_filters(
				'acrossai_integration_toggle_capability',
				'manage_options',
				$clean_key
			);

			if ( '' === $required_cap || ! current_user_can( $required_cap ) ) {
				/**
				 * Fires when a user is denied permission to flip a third-party
				 * integration toggle. Sites that need audit logging can hook
				 * this action without amending core code. (SEC-003)
				 *
				 * @since 0.1.0
				 * @param string $integration_slug Sanitised integration slug.
				 * @param string $required_cap     Capability that would have been required.
				 * @param int    $user_id          Current user ID (0 for guests).
				 */
				do_action(
					'acrossai_integration_toggle_denied',
					$clean_key,
					$required_cap,
					get_current_user_id()
				);

				return new \WP_Error(
					'rest_forbidden',
					__( 'You are not allowed to toggle this integration.', 'acrossai-abilities-manager' ),
					array( 'status' => 403 )
				);
			}
		}

		AcrossAI_Ability_Library_Config::save_config( $body );

		return rest_ensure_response( AcrossAI_Ability_Library_Config::get_config() );
	}
}

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

		AcrossAI_Ability_Library_Config::save_config( $body );

		return rest_ensure_response( AcrossAI_Ability_Library_Config::get_config() );
	}
}

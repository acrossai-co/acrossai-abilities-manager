<?php
/**
 * Library REST orchestrator — standalone singleton for acrossai-abilities-library/v1.
 *
 * Does NOT extend WP_REST_Controller (SC-027-05, SC-027-06).
 * Delegates route registration to sub-controllers in this directory.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Modules/Library/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library\Rest;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * REST namespace orchestrator for the Library module.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Library_Rest_Controller {

	/**
	 * REST namespace for all Library endpoints.
	 */
	const REST_NAMESPACE = 'acrossai-abilities-library/v1';

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Ability_Library_Rest_Controller|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Ability_Library_Rest_Controller
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
	 * Register all routes for the Library namespace.
	 *
	 * Wired to rest_api_init via includes/Main.php.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		AcrossAI_Ability_Library_Config_Controller::instance()->register_routes();
	}

	/**
	 * Permission callback shared by all Library sub-controllers.
	 *
	 * Two-gate: manage_options capability, then wp_rest nonce verification.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request Incoming REST request.
	 * @return true|\WP_Error
	 */
	public function check_permission( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage abilities.', 'acrossai-abilities-manager' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Nonce verification failed.', 'acrossai-abilities-manager' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}

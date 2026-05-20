<?php
/**
 * REST API orchestrator for sitewide ability management.
 *
 * Delegates route registration to per-domain sub-controllers in the Rest/ subdirectory
 * and owns the shared permission/nonce callback used by all routes.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide;

use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Rest\AcrossAI_Sitewide_Abilities_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Rest\AcrossAI_Sitewide_Override_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Rest\AcrossAI_Sitewide_Bulk_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Rest\AcrossAI_Sitewide_Mcp_Controller;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Thin orchestrator wired to rest_api_init in includes/Main.php.
 *
 * Responsibilities:
 *  - Expose REST_NAMESPACE constant consumed by all sub-controllers.
 *  - Delegate register_routes() to each per-domain sub-controller.
 *  - Own check_permission() referenced by every route as the shared permission callback.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Rest_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'acrossai-abilities-manager/v1';

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Sitewide_Rest_Controller|null
	 */
	protected static $_instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Sitewide_Rest_Controller
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
	 * Delegate route registration to each per-domain sub-controller.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		AcrossAI_Sitewide_Abilities_Controller::instance()->register_routes();
		AcrossAI_Sitewide_Override_Controller::instance()->register_routes();
		AcrossAI_Sitewide_Bulk_Controller::instance()->register_routes();
		AcrossAI_Sitewide_Mcp_Controller::instance()->register_routes();
	}

	/**
	 * Verify current user has manage_options capability and a valid REST nonce.
	 *
	 * Referenced by every sub-controller route as:
	 *   array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' )
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request Incoming request.
	 * @return bool|\WP_Error
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

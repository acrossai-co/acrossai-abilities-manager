<?php
/**
 * REST API Orchestrator Controller for Custom Abilities
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability\Rest
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Rest\AcrossAI_Custom_Ability_Read_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Rest\AcrossAI_Custom_Ability_Write_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Rest\AcrossAI_Custom_Ability_Mcp_Controller;

/**
 * Class AcrossAI_Custom_Ability_Rest_Controller
 *
 * Orchestrator for REST API endpoints. Handles route registration, shared permission
 * checking, and delegation to domain-specific sub-controllers (Read, Write, MCP).
 *
 * Singleton pattern per Memory SEC-PLAN-002.
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Rest_Controller {

	/**
	 * Singleton instance.
	 *
	 * @since 0.0.1
	 * @var self
	 */
	protected static $_instance = null;

	/**
	 * REST namespace.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $namespace = 'acrossai-abilities-manager/v1';

	/**
	 * REST base route.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $rest_base = 'custom-abilities';

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
	private function __construct() {}

	/**
	 * Register REST routes by delegating to sub-controllers.
	 *
	 * Called at `rest_api_init` hook.
	 *
	 * @since 0.0.1
	 */
	public function register_routes() {
		// Register Read sub-controller routes
		$read_controller = AcrossAI_Custom_Ability_Read_Controller::instance();
		$read_controller->register_routes( $this->namespace, $this->rest_base );

		// Register Write sub-controller routes (T006)
		$write_controller = AcrossAI_Custom_Ability_Write_Controller::instance();
		$write_controller->register_routes( $this->namespace, $this->rest_base );

		// Register MCP sub-controller routes (T006)
		$mcp_controller = AcrossAI_Custom_Ability_Mcp_Controller::instance();
		$mcp_controller->register_routes( $this->namespace, $this->rest_base );
	}

	/**
	 * Check permission for REST endpoint access.
	 *
	 * All Custom Ability endpoints require `manage_options` capability
	 * per Constitution §IV security requirements.
	 *
	 * @since 0.0.1
	 * @param \WP_REST_Request $request REST request object.
	 * @return bool|WP_Error True if permitted, WP_Error otherwise.
	 */
	public function check_permission( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to access this endpoint.', 'acrossai-abilities-manager' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Get namespace.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public function get_namespace() {
		return $this->namespace;
	}

	/**
	 * Get REST base.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public function get_rest_base() {
		return $this->rest_base;
	}
}

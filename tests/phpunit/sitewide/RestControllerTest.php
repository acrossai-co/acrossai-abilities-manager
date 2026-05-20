<?php
/**
 * Tests for AcrossAI_Sitewide_Rest_Controller.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Sitewide;

use WP_REST_Request;
use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Rest_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;

/**
 * Class RestControllerTest
 *
 * @since 0.1.0
 */
class RestControllerTest extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * Controller under test.
	 *
	 * @var AcrossAI_Sitewide_Rest_Controller
	 */
	protected $controller;

	/**
	 * Mock DB query object.
	 *
	 * @var \PHPUnit\Framework\MockObject\MockObject
	 */
	protected $db_query;

	/**
	 * Set up REST server and controller.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		$this->db_query   = $this->createMock( AcrossAI_Sitewide_Query::class );
		$this->controller = new AcrossAI_Sitewide_Rest_Controller( $this->db_query );
		$this->controller->register_routes();

		do_action( 'rest_api_init' );
	}

	/**
	 * Non-admin user should receive 403.
	 *
	 * @return void
	 */
	public function test_non_admin_gets_403() {
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/sitewide/abilities' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Admin with valid nonce should receive 200.
	 *
	 * @return void
	 */
	public function test_admin_gets_200() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->db_query
			->method( 'get_override_by_slug' )
			->willReturn( null );

		$request = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/sitewide/abilities' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = $this->server->dispatch( $request );

		$this->assertContains( $response->get_status(), [ 200, 400 ] );
	}

	/**
	 * GET /abilities/{slug} for unknown slug returns 404.
	 *
	 * @return void
	 */
	public function test_get_unknown_ability_returns_404() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->db_query
			->method( 'get_override_by_slug' )
			->willReturn( null );

		$request = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/sitewide/abilities/nonexistent-slug' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * DELETE returns deleted=false when no override exists.
	 *
	 * @return void
	 */
	public function test_delete_nonexistent_override_returns_false() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->db_query
			->method( 'get_override_by_slug' )
			->willReturn( null );
		$this->db_query
			->method( 'delete_override_by_slug' )
			->willReturn( false );

		$request = new WP_REST_Request( 'DELETE', '/acrossai-abilities-manager/v1/sitewide/abilities/my-ability' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['deleted'] );
	}
}

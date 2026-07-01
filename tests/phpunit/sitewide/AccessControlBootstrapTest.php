<?php
/**
 * Tests for the abilities-scoped access-control bootstrap wrapper.
 *
 * Updated Feature 039: retargets from the decommissioned Sitewide wrapper
 * (removed in Feature 012) to the current Abilities wrapper; asserts the new
 * two-arg AccessControlManager constructor (v2.0.0) and the per-consumer
 * TABLE_SLUG format constraint.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Sitewide;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control;
use WP_REST_Server;
use WP_UnitTestCase;
use WPBoilerplate\AccessControl\AccessControlManager;

/**
 * Class AccessControlBootstrapTest.
 *
 * @since 0.1.0
 */
class AccessControlBootstrapTest extends WP_UnitTestCase {

	/**
	 * Reset singleton state between tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();

		$this->reset_wrapper_manager();
	}

	/**
	 * Clean up wrapper state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->reset_wrapper_manager();
		parent::tearDown();
	}

	/**
	 * TABLE_SLUG must satisfy the library's construction-time regex
	 * `^[a-z0-9_]{1,32}$`; otherwise AccessControlManager throws
	 * InvalidArgumentException and the plugin fatals at boot.
	 *
	 * @return void
	 */
	public function test_table_slug_matches_library_validation_regex(): void {
		$this->assertMatchesRegularExpression(
			'/^[a-z0-9_]{1,32}$/',
			AcrossAI_Abilities_Access_Control::TABLE_SLUG
		);
	}

	/**
	 * Boot manager should use the plugin-scoped providers filter AND the
	 * per-consumer table slug (v2.0.0 two-arg constructor).
	 *
	 * @return void
	 */
	public function test_boot_manager_uses_plugin_scoped_provider_filter_and_table_slug(): void {
		if ( ! class_exists( AccessControlManager::class ) ) {
			$this->markTestSkipped( 'wpb-access-control is not available.' );
		}

		$wrapper = AcrossAI_Abilities_Access_Control::instance();
		$wrapper->boot_manager();

		$manager = $this->get_wrapper_manager( $wrapper );

		$this->assertInstanceOf( AccessControlManager::class, $manager );
		$this->assertSame(
			AcrossAI_Abilities_Access_Control::PROVIDERS_FILTER,
			$this->get_private_property( $manager, 'providers_filter' )
		);
		$this->assertSame(
			AcrossAI_Abilities_Access_Control::TABLE_SLUG,
			$this->get_private_property( $manager, 'table_slug' )
		);
	}

	/**
	 * Registering the REST API should expose the library routes under the
	 * per-consumer slug namespace (v2.0.0: /wpb-ac/v1/{slug}/...).
	 *
	 * @return void
	 */
	public function test_register_rest_api_registers_slugged_library_routes(): void {
		if ( ! class_exists( AccessControlManager::class ) ) {
			$this->markTestSkipped( 'wpb-access-control is not available.' );
		}

		$wrapper = AcrossAI_Abilities_Access_Control::instance();
		$wrapper->boot_manager();
		$wrapper->register_rest_api();

		$routes = rest_get_server()->get_routes();
		$slug   = AcrossAI_Abilities_Access_Control::TABLE_SLUG;

		$this->assertArrayHasKey( '/wpb-ac/v1/' . $slug . '/providers', $routes );
		$this->assertArrayHasKey(
			'/wpb-ac/v1/' . $slug . '/rules/(?P<namespace>.+)/(?P<key>.+)',
			$routes
		);
	}

	/**
	 * Reset the wrapper's cached manager.
	 *
	 * @return void
	 */
	private function reset_wrapper_manager(): void {
		$wrapper = AcrossAI_Abilities_Access_Control::instance();
		$property = new \ReflectionProperty( AcrossAI_Abilities_Access_Control::class, 'manager' );
		$property->setAccessible( true );
		$property->setValue( $wrapper, null );
	}

	/**
	 * Read the wrapper's manager instance.
	 *
	 * @param AcrossAI_Abilities_Access_Control $wrapper Wrapper instance.
	 * @return AccessControlManager|null
	 */
	private function get_wrapper_manager( AcrossAI_Abilities_Access_Control $wrapper ): ?AccessControlManager {
		$property = new \ReflectionProperty( AcrossAI_Abilities_Access_Control::class, 'manager' );
		$property->setAccessible( true );

		return $property->getValue( $wrapper );
	}

	/**
	 * Read a private property value from an object.
	 *
	 * @param object $object   Object under inspection.
	 * @param string $property Property name.
	 * @return mixed
	 */
	private function get_private_property( $object, string $property ) {
		$reflection = new \ReflectionProperty( $object, $property );
		$reflection->setAccessible( true );

		return $reflection->getValue( $object );
	}
}

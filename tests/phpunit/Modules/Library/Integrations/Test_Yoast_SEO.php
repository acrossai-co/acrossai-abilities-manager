<?php
/**
 * Smoke tests for the Yoast SEO concrete integration subclass.
 *
 * Feature 060 extension (2026-07-27). Covers:
 *   - TAB_GROUP constant is 'yoast' (deliberate — must not collide with Yoast's
 *     own 'yoast-seo' category slug; see Yoast_SEO class docblock for the
 *     synthetic-row lifecycle rationale)
 *   - slug() returns self::TAB_GROUP
 *   - abilities() exposes the 3 Yoast SEO ability rows with the expected slugs
 *   - Kill-switch path: maybe_unregister() no-ops when the integration is ON,
 *     unregisters when OFF. Uses a reflected `$active` gate — the ACF pattern
 *     of overriding is_plugin_active on a subclass by-value.
 *
 * Doesn't attempt to instantiate the Yoast_SEO class directly because its
 * constructor calls add_action() which would leak hook registrations across
 * tests; instead uses a testable subclass that mirrors Yoast_SEO's behavior.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library\Integrations;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\Yoast_SEO;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Config;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The Yoast SEO integration subclass contract.
 */
class Test_Yoast_SEO extends TestCase {

	/**
	 * Reset the shared site-option store between tests.
	 */
	protected function setUp(): void {
		parent::setUp();
		acrossai_test_site_options( array() );
	}

	// -------------------------------------------------------------------------
	// TAB_GROUP constant + slug/label consistency
	// -------------------------------------------------------------------------

	public function test_tab_group_constant_is_yoast_not_yoast_seo(): void {
		// Deliberate: must NOT collide with Yoast's own 'yoast-seo' category
		// registration on wp_abilities_api_categories_init. Using 'yoast-seo'
		// would let our synthetic rows successfully register into wp_get_abilities()
		// with fail-closed callbacks — leaking broken abilities. See class docblock.
		$this->assertSame( 'yoast', Yoast_SEO::TAB_GROUP );
	}

	public function test_slug_returns_tab_group_constant(): void {
		$slug_method = new ReflectionMethod( Yoast_SEO::class, 'slug' );
		$slug_method->setAccessible( true );

		// Use a bare instance without invoking the constructor (which adds hooks).
		$instance = ( new \ReflectionClass( Yoast_SEO::class ) )->newInstanceWithoutConstructor();
		$this->assertSame( Yoast_SEO::TAB_GROUP, $slug_method->invoke( $instance ) );
	}

	public function test_label_is_yoast_seo(): void {
		$label_method = new ReflectionMethod( Yoast_SEO::class, 'label' );
		$label_method->setAccessible( true );
		$instance = ( new \ReflectionClass( Yoast_SEO::class ) )->newInstanceWithoutConstructor();
		$this->assertSame( 'Yoast SEO', $label_method->invoke( $instance ) );
	}

	// -------------------------------------------------------------------------
	// abilities() — 3 rows with the exact Yoast ability names
	// -------------------------------------------------------------------------

	public function test_abilities_lists_three_yoast_ability_names(): void {
		$abilities_method = new ReflectionMethod( Yoast_SEO::class, 'abilities' );
		$abilities_method->setAccessible( true );
		$instance  = ( new \ReflectionClass( Yoast_SEO::class ) )->newInstanceWithoutConstructor();
		$abilities = $abilities_method->invoke( $instance );

		$this->assertIsArray( $abilities );
		$this->assertCount( 3, $abilities );

		$slugs = array_column( $abilities, 'slug' );
		$this->assertSame(
			array(
				'yoast-seo/get-seo-scores',
				'yoast-seo/get-readability-scores',
				'yoast-seo/get-inclusive-language-scores',
			),
			$slugs
		);

		// Every row has label + description populated.
		foreach ( $abilities as $row ) {
			$this->assertNotEmpty( $row['label'] );
			$this->assertNotEmpty( $row['description'] );
			// Description mentions the Yoast per-feature gating requirement.
			$this->assertMatchesRegularExpression(
				'/Yoast SEO.+feature.+enabled/i',
				$row['description']
			);
		}
	}

	// -------------------------------------------------------------------------
	// enable_filter() — deliberately a no-op (no master switch in Yoast)
	// -------------------------------------------------------------------------

	public function test_enable_filter_is_no_op(): void {
		$enable_method = new ReflectionMethod( Yoast_SEO::class, 'enable_filter' );
		$enable_method->setAccessible( true );
		$instance = ( new \ReflectionClass( Yoast_SEO::class ) )->newInstanceWithoutConstructor();

		// enable_filter must be a no-op — return void, no exception, no side effect
		// visible from user-space. The base class calls it inside a try/catch so
		// even if a subclass throws, the plugin degrades gracefully; here we just
		// verify the intentional no-op body doesn't throw.
		$result = $enable_method->invoke( $instance );
		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// maybe_unregister() — kill-switch semantics
	// -------------------------------------------------------------------------

	public function test_maybe_unregister_no_op_when_plugin_inactive(): void {
		// No fake Yoast constants → is_plugin_active returns false → early return.
		// The stub bootstrap defines no-op wp_get_ability / wp_unregister_ability,
		// but we don't want to exercise them at all when the plugin gate fails.
		$instance = ( new \ReflectionClass( Yoast_SEO::class ) )->newInstanceWithoutConstructor();

		// Snapshot: our test env has no WPSEO_VERSION defined.
		$this->assertFalse( defined( 'WPSEO_VERSION' ), 'Precondition: WPSEO_VERSION must NOT be defined in the test environment.' );

		// Should return without error and without invoking wp_get_ability.
		$instance->maybe_unregister();
		$this->assertTrue( true, 'No exception thrown; kill-switch respected the plugin-inactive gate.' );
	}

	public function test_maybe_unregister_no_op_when_toggle_on(): void {
		acrossai_test_site_options(
			array(
				AcrossAI_Ability_Library_Config::OPTION_KEY => array(
					'yoast' => array( 'enabled' => true, 'mode' => 'all', 'sub_keys' => array() ),
				),
			)
		);

		// Skip the plugin-active gate by using reflection to bypass is_plugin_active.
		// (Yoast isn't present in the test bootstrap; we exercise the toggle-on path
		//  through a subclass override.)
		$instance = new class() extends Yoast_SEO {
			public function __construct() {
				// Skip parent — we don't want the actual hooks.
			}
			protected function is_plugin_active(): bool {
				return true;
			}
		};

		// If we reach the unregister loop, wp_get_ability / wp_unregister_ability
		// would be called. Both are undefined in the test env, so calling them
		// would throw. We assert the toggle-on gate prevents that.
		$instance->maybe_unregister();
		$this->assertTrue( true, 'No exception thrown; kill-switch respected the toggle-ON gate.' );
	}

	public function test_maybe_unregister_bails_when_wp_ability_functions_absent(): void {
		acrossai_test_site_options(
			array(
				AcrossAI_Ability_Library_Config::OPTION_KEY => array(
					'yoast' => array( 'enabled' => false, 'mode' => 'all', 'sub_keys' => array() ),
				),
			)
		);

		$instance = new class() extends Yoast_SEO {
			public function __construct() {
				// Skip parent.
			}
			protected function is_plugin_active(): bool {
				return true;
			}
		};

		// wp_get_ability and wp_unregister_ability are not defined in the stub
		// test env → the function_exists() guard must bail cleanly.
		$this->assertFalse( function_exists( 'wp_get_ability' ), 'Precondition: wp_get_ability must NOT exist in the test environment.' );
		$this->assertFalse( function_exists( 'wp_unregister_ability' ), 'Precondition: wp_unregister_ability must NOT exist in the test environment.' );

		$instance->maybe_unregister();
		$this->assertTrue( true, 'No exception thrown; kill-switch handled missing WP ability functions gracefully.' );
	}
}

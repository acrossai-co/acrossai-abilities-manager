<?php
/**
 * Tests for AcrossAI_Ability_Merger.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Sitewide;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Merger;

/**
 * Class AbilityMergerTest
 *
 * @since 0.1.0
 */
class AbilityMergerTest extends WP_UnitTestCase {

	/**
	 * Registry-only data (no override) returns registry value.
	 *
	 * @return void
	 */
	public function test_merge_returns_registry_value_when_no_override() {
		$registry = [
			'slug'         => 'my-ability',
			'site_allowed' => true,
			'readonly'     => false,
			'destructive'  => null,
			'idempotent'   => null,
			'show_in_rest' => true,
			'show_in_mcp'  => null,
			'mcp_type'     => null,
			'provider'     => 'my-plugin',
			'source'       => 'plugin',
		];

		$result = AcrossAI_Ability_Merger::merge( $registry, null );

		$this->assertSame( true, $result['site_allowed'] );
		$this->assertFalse( $result['has_override'] );
		$this->assertArrayHasKey( '_registry', $result );
	}

	/**
	 * Non-null override field wins over registry value.
	 *
	 * @return void
	 */
	public function test_merge_override_wins_when_non_null() {
		$registry = [
			'slug'         => 'my-ability',
			'site_allowed' => true,
			'readonly'     => false,
			'destructive'  => null,
			'idempotent'   => null,
			'show_in_rest' => true,
			'show_in_mcp'  => null,
			'mcp_type'     => null,
			'provider'     => 'my-plugin',
			'source'       => 'plugin',
		];

		$override              = new \stdClass();
		$override->site_allowed = false;
		$override->readonly     = null;
		$override->destructive  = null;
		$override->idempotent   = null;
		$override->show_in_rest = null;
		$override->show_in_mcp  = null;
		$override->mcp_type     = null;
		$override->updated_at   = '2025-01-01 00:00:00';
		$override->updated_by   = 1;

		$result = AcrossAI_Ability_Merger::merge( $registry, $override );

		$this->assertFalse( $result['site_allowed'] );
		$this->assertTrue( $result['has_override'] );
	}

	/**
	 * is_all_default returns true when all payload fields match registry.
	 *
	 * @return void
	 */
	public function test_is_all_default_returns_true_when_same() {
		$payload  = [ 'site_allowed' => true, 'show_in_rest' => true ];
		$registry = [ 'site_allowed' => true, 'show_in_rest' => true ];

		$this->assertTrue( AcrossAI_Ability_Merger::is_all_default( $payload, $registry ) );
	}

	/**
	 * is_all_default returns false when at least one field differs.
	 *
	 * @return void
	 */
	public function test_is_all_default_returns_false_when_differs() {
		$payload  = [ 'site_allowed' => false ];
		$registry = [ 'site_allowed' => true ];

		$this->assertFalse( AcrossAI_Ability_Merger::is_all_default( $payload, $registry ) );
	}
}

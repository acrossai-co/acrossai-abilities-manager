<?php
/**
 * Feature 067 — Update_Maintenance_Mode tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Update_Maintenance_Mode extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Update_Maintenance_Mode.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/update-maintenance-mode'", $this->src );
	}

	public function test_requires_enabled(): void {
		$this->assertStringContainsString( "'required'             => array( 'enabled' )", $this->src );
	}

	public function test_mode_enum(): void {
		$this->assertStringContainsString( "'maintenance', 'coming_soon'", $this->src );
	}

	public function test_updates_options(): void {
		$this->assertStringContainsString( "update_option( 'elementor_maintenance_mode_mode',", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

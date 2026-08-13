<?php
/**
 * Feature 067 — Get_Maintenance_Mode tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Get_Maintenance_Mode extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Maintenance_Mode.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-get-maintenance-mode'", $this->src );
	}

	public function test_reads_maintenance_options(): void {
		$this->assertStringContainsString( "'elementor_maintenance_mode_mode'", $this->src );
		$this->assertStringContainsString( "'elementor_maintenance_mode_template_id'", $this->src );
	}

	public function test_readonly_annotation(): void {
		$this->assertStringContainsString( "'readonly' => true", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

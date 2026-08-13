<?php
/**
 * Feature 067 — Get_Theme_Builder_Conditions tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Get_Theme_Builder_Conditions extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Theme_Builder_Conditions.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-get-theme-builder-conditions'", $this->src );
	}

	public function test_requires_template_id(): void {
		$this->assertStringContainsString( "'required'             => array( 'template_id' )", $this->src );
	}

	public function test_reads_conditions_meta(): void {
		$this->assertStringContainsString( "get_post_meta( \$template_id, '_elementor_conditions', true )", $this->src );
	}

	public function test_readonly_annotation(): void {
		$this->assertStringContainsString( "'readonly' => true", $this->src );
	}
}

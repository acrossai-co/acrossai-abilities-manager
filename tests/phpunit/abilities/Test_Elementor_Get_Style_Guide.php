<?php
/**
 * Feature 067 — Get_Style_Guide tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Get_Style_Guide extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Style_Guide.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-get-style-guide'", $this->src );
	}

	public function test_defaults_to_active_kit(): void {
		$this->assertStringContainsString( "get_option( 'elementor_active_kit', 0 )", $this->src );
	}

	public function test_reads_page_settings_meta(): void {
		$this->assertStringContainsString( "get_post_meta( \$kit_id, '_elementor_page_settings', true )", $this->src );
	}

	public function test_merges_system_and_custom_colors(): void {
		$this->assertStringContainsString( "'system_colors'", $this->src );
		$this->assertStringContainsString( "'custom_colors'", $this->src );
	}

	public function test_readonly_annotation(): void {
		$this->assertStringContainsString( "'readonly' => true", $this->src );
	}
}

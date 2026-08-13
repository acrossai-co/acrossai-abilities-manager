<?php
/**
 * Feature 067 — Get_Theme_Context tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Get_Theme_Context extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Theme_Context.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-get-theme-context'", $this->src );
	}

	public function test_reads_active_theme(): void {
		$this->assertStringContainsString( 'wp_get_theme()', $this->src );
	}

	public function test_reads_active_kit(): void {
		$this->assertStringContainsString( "get_option( 'elementor_active_kit', 0 )", $this->src );
	}

	public function test_reads_elementor_version(): void {
		$this->assertStringContainsString( "defined( 'ELEMENTOR_VERSION' )", $this->src );
	}

	public function test_returns_guidance_basis(): void {
		$this->assertStringContainsString( "'guidance_basis'", $this->src );
	}
}

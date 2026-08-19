<?php
/**
 * Feature 067 — Evaluate_Render_Context tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Evaluate_Render_Context extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Evaluate_Render_Context.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/evaluate-render-context'", $this->src );
	}

	public function test_requires_post_id(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id' )", $this->src );
	}

	public function test_reads_page_template_meta(): void {
		$this->assertStringContainsString( "'_wp_page_template'", $this->src );
	}

	public function test_classifies_canvas_types(): void {
		$this->assertStringContainsString( "'elementor_canvas'", $this->src );
		$this->assertStringContainsString( "'elementor_header_footer'", $this->src );
	}

	public function test_readonly_annotation(): void {
		$this->assertStringContainsString( "'readonly' => true", $this->src );
	}
}

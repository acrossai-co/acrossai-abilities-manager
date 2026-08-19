<?php
/**
 * Feature 067 — Get_Official_Widget_Catalog tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Get_Official_Widget_Catalog extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Official_Widget_Catalog.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/get-official-widget-catalog'", $this->src );
	}

	public function test_delegates_to_guidance_catalog(): void {
		$this->assertStringContainsString( 'Guidance_Catalog::widget_catalog(', $this->src );
	}

	public function test_supports_category_filter(): void {
		$this->assertStringContainsString( 'Guidance_Catalog::CATEGORIES', $this->src );
	}

	public function test_readonly_annotation(): void {
		$this->assertStringContainsString( "'readonly' => true", $this->src );
	}
}

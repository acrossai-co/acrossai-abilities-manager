<?php
/**
 * Feature 067 — Find_Elements ability tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Find_Elements extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Find_Elements.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/find-elements'", $this->src );
	}

	public function test_supports_element_type_widget_type_and_contains_filters(): void {
		$this->assertStringContainsString( "'element_type'", $this->src );
		$this->assertStringContainsString( "'widget_type'", $this->src );
		$this->assertStringContainsString( "'contains'", $this->src );
	}

	public function test_element_type_enum(): void {
		$this->assertStringContainsString( "'container', 'widget', 'section', 'column'", $this->src );
	}

	public function test_uses_find_elements_where(): void {
		$this->assertStringContainsString( 'Document_Repository::find_elements_where(', $this->src );
	}

	public function test_read_capability(): void {
		$this->assertStringContainsString( "load_document( \$post_id, 'read' )", $this->src );
	}

	public function test_readonly_annotations(): void {
		$this->assertStringContainsString( "'readonly' => true", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

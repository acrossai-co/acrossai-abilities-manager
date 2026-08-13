<?php
/**
 * Feature 067 — Delete_Element tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Delete_Element extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Delete_Element.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-delete-element'", $this->src );
	}

	public function test_destructive_annotation(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
	}

	public function test_force_delete_flag_and_error_code(): void {
		$this->assertStringContainsString( "'force_delete'", $this->src );
		$this->assertStringContainsString( "'force_delete_required'", $this->src );
	}

	public function test_guards_top_level_and_populated_elements(): void {
		$this->assertStringContainsString( '$is_top_level', $this->src );
		$this->assertStringContainsString( '$has_children', $this->src );
	}

	public function test_uses_remove_element_by_id(): void {
		$this->assertStringContainsString( 'Document_Repository::remove_element_by_id', $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

<?php
/**
 * Feature 067 — Reorder_Elements tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Reorder_Elements extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Reorder_Elements.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/reorder-elements'", $this->src );
	}

	public function test_input_requires_post_id_and_ordered_ids(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'ordered_element_ids' )", $this->src );
	}

	public function test_supports_parent_id_null_for_root(): void {
		$this->assertStringContainsString( "array( 'string', 'null' )", $this->src );
	}

	public function test_uses_reorder_children(): void {
		$this->assertStringContainsString( 'Document_Repository::reorder_children', $this->src );
	}

	public function test_reads_back_effective_order(): void {
		$this->assertStringContainsString( "'new_order'", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

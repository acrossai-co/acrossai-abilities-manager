<?php
/**
 * Feature 067 — Move_Element tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Move_Element extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Move_Element.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/move-element'", $this->src );
	}

	public function test_input_requires_post_id_element_id_position(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'element_id', 'position' )", $this->src );
	}

	public function test_supports_new_parent_id_null_for_root(): void {
		$this->assertStringContainsString( "array( 'string', 'null' )", $this->src );
	}

	public function test_descendant_guard(): void {
		$this->assertStringContainsString( "'descendant_destination'", $this->src );
		$this->assertStringContainsString( 'in_array( $element_id, $dest[\'path\'], true )', $this->src );
	}

	public function test_atomic_remove_then_insert(): void {
		$this->assertStringContainsString( 'Document_Repository::remove_element_by_id', $this->src );
		$this->assertStringContainsString( 'Document_Repository::insert_element', $this->src );
	}

	public function test_reports_previous_parent_id(): void {
		$this->assertStringContainsString( "'previous_parent_id'", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

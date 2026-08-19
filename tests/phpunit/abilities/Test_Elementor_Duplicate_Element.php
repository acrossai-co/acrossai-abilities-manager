<?php
/**
 * Feature 067 — Duplicate_Element tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Duplicate_Element extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Duplicate_Element.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/duplicate-element'", $this->src );
	}

	public function test_reassigns_subtree_ids(): void {
		$this->assertStringContainsString( 'Document_Repository::reassign_subtree_ids', $this->src );
	}

	public function test_inserts_clone_as_next_sibling(): void {
		$this->assertStringContainsString( '$sibling_index + 1', $this->src );
	}

	public function test_reports_source_and_clone_ids(): void {
		$this->assertStringContainsString( "'source_element_id'", $this->src );
		$this->assertStringContainsString( "'clone_element_id'", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

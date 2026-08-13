<?php
/**
 * Feature 067 — Clone_Data tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Clone_Data extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Clone_Data.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-clone-data'", $this->src );
	}

	public function test_requires_source_and_target(): void {
		$this->assertStringContainsString( "'required'             => array( 'source_post_id', 'target_post_id' )", $this->src );
	}

	public function test_force_replace_guard_on_populated_target(): void {
		$this->assertStringContainsString( "'force_replace_required'", $this->src );
		$this->assertStringContainsString( 'is_document_populated', $this->src );
	}

	public function test_reassigns_subtree_ids(): void {
		$this->assertStringContainsString( 'Document_Repository::reassign_subtree_ids', $this->src );
	}

	public function test_optionally_copies_page_settings(): void {
		$this->assertStringContainsString( '$include_page_settings', $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

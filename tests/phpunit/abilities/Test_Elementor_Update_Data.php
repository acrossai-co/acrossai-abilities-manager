<?php
/**
 * Feature 067 — Update_Data ability tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Update_Data extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Update_Data.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-update-data'", $this->src );
	}

	public function test_requires_post_id_and_data(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'data' )", $this->src );
	}

	public function test_destructive_annotation(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
	}

	public function test_supports_page_settings_and_force_replace(): void {
		$this->assertStringContainsString( "'page_settings'", $this->src );
		$this->assertStringContainsString( "'force_replace'", $this->src );
	}

	public function test_force_replace_guard(): void {
		$this->assertStringContainsString( "'force_replace_required'", $this->src );
		$this->assertStringContainsString( 'is_document_populated', $this->src );
	}

	public function test_uses_edit_capability(): void {
		$this->assertStringContainsString( "load_document( \$post_id, 'edit' )", $this->src );
	}

	public function test_persists_via_save_data(): void {
		$this->assertStringContainsString( 'Document_Repository::save_data', $this->src );
	}

	public function test_returns_element_count(): void {
		$this->assertStringContainsString( "'element_count'", $this->src );
		$this->assertStringContainsString( 'Document_Repository::walk_tree(', $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

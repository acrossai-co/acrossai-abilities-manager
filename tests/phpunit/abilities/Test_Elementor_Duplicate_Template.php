<?php
/** Feature 067 — Duplicate_Template tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Duplicate_Template extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Duplicate_Template.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-duplicate-template'", $this->src ); }
	public function test_reassigns_element_ids(): void { $this->assertStringContainsString( 'Document_Repository::reassign_subtree_ids', $this->src ); }
	public function test_preserves_conditions_and_sub_type(): void { $this->assertStringContainsString( "'_elementor_conditions'", $this->src ); $this->assertStringContainsString( "'_elementor_template_sub_type'", $this->src ); }
}

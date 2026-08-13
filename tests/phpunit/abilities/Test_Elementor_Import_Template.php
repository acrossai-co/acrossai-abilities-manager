<?php
/** Feature 067 — Import_Template tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Import_Template extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Import_Template.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-import-template'", $this->src ); }
	public function test_requires_data(): void { $this->assertStringContainsString( "'required'   => array( 'data' )", $this->src ); }
	public function test_regenerates_element_ids(): void { $this->assertStringContainsString( 'Document_Repository::reassign_subtree_ids', $this->src ); }
	public function test_supports_overwrite_id(): void { $this->assertStringContainsString( '$overwrite_id', $this->src ); }
}

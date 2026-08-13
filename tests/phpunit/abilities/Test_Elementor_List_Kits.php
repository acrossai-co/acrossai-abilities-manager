<?php
/** Feature 067 — List_Kits tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_List_Kits extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/List_Kits.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-list-kits'", $this->src ); }
	public function test_queries_kit_template_type(): void { $this->assertStringContainsString( "'template_type' => 'kit'", $this->src ); }
	public function test_marks_active_kit(): void { $this->assertStringContainsString( "get_option( 'elementor_active_kit', 0 )", $this->src ); }
	public function test_readonly(): void { $this->assertStringContainsString( "'readonly' => true", $this->src ); }
}

<?php
/** Feature 067 — List_Templates tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_List_Templates extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/List_Templates.php' ); }
	public function test_extends_ability_definition(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/list-templates'", $this->src ); }
	public function test_uses_template_query(): void { $this->assertStringContainsString( 'Template_Query::query(', $this->src ); }
	public function test_readonly(): void { $this->assertStringContainsString( "'readonly' => true", $this->src ); }
	public function test_gate(): void { $this->assertStringContainsString( 'assert_elementor_available()', $this->src ); }
}

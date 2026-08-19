<?php
/** Feature 067 — Get_Template tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Get_Template extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Template.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/get-template'", $this->src ); }
	public function test_requires_template_id(): void { $this->assertStringContainsString( "'required'   => array( 'template_id' )", $this->src ); }
	public function test_uses_template_query_summary(): void { $this->assertStringContainsString( 'Template_Query::to_summary(', $this->src ); }
	public function test_readonly(): void { $this->assertStringContainsString( "'readonly' => true", $this->src ); }
}

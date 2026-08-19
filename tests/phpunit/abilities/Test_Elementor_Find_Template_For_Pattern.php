<?php
/** Feature 067 — Find_Template_For_Pattern tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Find_Template_For_Pattern extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Find_Template_For_Pattern.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/find-template-for-pattern'", $this->src ); }
	public function test_requires_keywords(): void { $this->assertStringContainsString( "'required'   => array( 'pattern_keywords' )", $this->src ); }
	public function test_uses_rank_by_pattern(): void { $this->assertStringContainsString( 'Template_Query::rank_by_pattern', $this->src ); }
	public function test_readonly(): void { $this->assertStringContainsString( "'readonly' => true", $this->src ); }
}

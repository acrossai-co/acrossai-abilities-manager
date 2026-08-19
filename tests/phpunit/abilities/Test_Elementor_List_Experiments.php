<?php
/** Feature 067 — List_Experiments tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_List_Experiments extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/List_Experiments.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/list-experiments'", $this->src ); }
	public function test_uses_experiments_manager(): void { $this->assertStringContainsString( '$instance->experiments->get_features()', $this->src ); }
	public function test_readonly(): void { $this->assertStringContainsString( "'readonly' => true", $this->src ); }
}

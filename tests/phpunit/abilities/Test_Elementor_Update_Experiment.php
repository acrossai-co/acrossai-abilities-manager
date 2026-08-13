<?php
/** Feature 067 — Update_Experiment tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Update_Experiment extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Update_Experiment.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-update-experiment'", $this->src ); }
	public function test_state_enum(): void { $this->assertStringContainsString( "'active', 'inactive', 'default'", $this->src ); }
	public function test_writes_experiment_option(): void { $this->assertStringContainsString( "update_option( \$option_key, \$state )", $this->src ); }
	public function test_returns_previous_state(): void { $this->assertStringContainsString( "'previous_state'", $this->src ); }
}

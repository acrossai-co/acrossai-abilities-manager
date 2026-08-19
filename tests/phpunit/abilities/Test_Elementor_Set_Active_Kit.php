<?php
/** Feature 067 — Set_Active_Kit tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Set_Active_Kit extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Set_Active_Kit.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/set-active-kit'", $this->src ); }
	public function test_requires_kit_id(): void { $this->assertStringContainsString( "'required' => array( 'kit_id' )", $this->src ); }
	public function test_updates_active_kit_option(): void { $this->assertStringContainsString( "update_option( 'elementor_active_kit', \$kit_id )", $this->src ); }
	public function test_returns_previous(): void { $this->assertStringContainsString( "'previous_kit_id'", $this->src ); }
}

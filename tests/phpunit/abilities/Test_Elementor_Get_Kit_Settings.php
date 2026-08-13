<?php
/** Feature 067 — Get_Kit_Settings tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Get_Kit_Settings extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Kit_Settings.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-get-kit-settings'", $this->src ); }
	public function test_defaults_to_active_kit(): void { $this->assertStringContainsString( "get_option( 'elementor_active_kit', 0 )", $this->src ); }
	public function test_reads_page_settings_meta(): void { $this->assertStringContainsString( "get_post_meta( \$kit_id, '_elementor_page_settings', true )", $this->src ); }
	public function test_readonly(): void { $this->assertStringContainsString( "'readonly' => true", $this->src ); }
}

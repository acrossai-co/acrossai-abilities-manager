<?php
/** Feature 067 — Update_Custom_Code tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Update_Custom_Code extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Update_Custom_Code.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/update-custom-code'", $this->src ); }
	public function test_pro_gate(): void { $this->assertStringContainsString( 'assert_elementor_pro_available()', $this->src ); }
	public function test_updates_post(): void { $this->assertStringContainsString( 'wp_update_post( $post_update )', $this->src ); }
	public function test_updates_location_and_priority_meta(): void { $this->assertStringContainsString( "'_elementor_snippet_location'", $this->src ); $this->assertStringContainsString( "'_elementor_snippet_priority'", $this->src ); }
}

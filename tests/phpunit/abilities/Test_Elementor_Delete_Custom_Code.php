<?php
/** Feature 067 — Delete_Custom_Code tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Delete_Custom_Code extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Delete_Custom_Code.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-delete-custom-code'", $this->src ); }
	public function test_pro_gate(): void { $this->assertStringContainsString( 'assert_elementor_pro_available()', $this->src ); }
	public function test_supports_trash_and_force_delete(): void { $this->assertStringContainsString( 'wp_trash_post(', $this->src ); $this->assertStringContainsString( 'wp_delete_post( $snippet_id, true )', $this->src ); }
	public function test_destructive(): void { $this->assertStringContainsString( "'destructive' => true", $this->src ); }
}

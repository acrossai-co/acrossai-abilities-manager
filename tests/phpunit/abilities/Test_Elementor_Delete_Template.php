<?php
/** Feature 067 — Delete_Template tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Delete_Template extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Delete_Template.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/delete-template'", $this->src ); }
	public function test_uses_trash_by_default(): void { $this->assertStringContainsString( 'wp_trash_post(', $this->src ); }
	public function test_force_permanent_delete(): void { $this->assertStringContainsString( 'wp_delete_post( $template_id, true )', $this->src ); }
	public function test_destructive(): void { $this->assertStringContainsString( "'destructive' => true", $this->src ); }
}

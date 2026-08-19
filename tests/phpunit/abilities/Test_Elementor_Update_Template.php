<?php
/** Feature 067 — Update_Template tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Update_Template extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Update_Template.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/update-template'", $this->src ); }
	public function test_force_replace_guard(): void { $this->assertStringContainsString( "'force_replace_required'", $this->src ); }
	public function test_destructive(): void { $this->assertStringContainsString( "'destructive' => true", $this->src ); }
	public function test_uses_save_data(): void { $this->assertStringContainsString( 'Document_Repository::save_data(', $this->src ); }
}

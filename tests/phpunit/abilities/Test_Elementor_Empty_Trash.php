<?php
/** Feature 067 — Empty_Trash tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Empty_Trash extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Empty_Trash.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-empty-trash'", $this->src ); }
	public function test_requires_confirm(): void { $this->assertStringContainsString( "'required'   => array( 'confirm' )", $this->src ); }
	public function test_refuses_without_confirm(): void { $this->assertStringContainsString( "'force_delete_required'", $this->src ); }
	public function test_destructive(): void { $this->assertStringContainsString( "'destructive' => true", $this->src ); }
}

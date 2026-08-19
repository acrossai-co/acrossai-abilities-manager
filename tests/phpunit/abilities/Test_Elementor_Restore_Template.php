<?php
/** Feature 067 — Restore_Template tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Restore_Template extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Restore_Template.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/restore-template'", $this->src ); }
	public function test_uses_untrash(): void { $this->assertStringContainsString( 'wp_untrash_post(', $this->src ); }
	public function test_idempotent(): void { $this->assertStringContainsString( "'idempotent' => true", $this->src ); }
}

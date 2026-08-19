<?php
/**
 * Feature 067 — Patch_Data tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Patch_Data extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Patch_Data.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/patch-data'", $this->src );
	}

	public function test_requires_post_id_find_replace(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'find', 'replace' )", $this->src );
	}

	public function test_uses_str_replace(): void {
		$this->assertStringContainsString( 'str_replace( $find, $replace, $raw, $count )', $this->src );
	}

	public function test_returns_replacement_count(): void {
		$this->assertStringContainsString( "'replacements'", $this->src );
	}

	public function test_destructive_annotation(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

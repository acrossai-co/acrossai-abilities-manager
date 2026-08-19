<?php
/**
 * Feature 067 — Merge_Element_Settings tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Merge_Element_Settings extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Merge_Element_Settings.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/merge-element-settings'", $this->src );
	}

	public function test_input_requires_post_id_element_id_and_settings(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'element_id', 'settings' )", $this->src );
	}

	public function test_idempotent_annotation(): void {
		$this->assertStringContainsString( "'idempotent' => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
	}

	public function test_uses_deep_merge_helper(): void {
		$this->assertStringContainsString( 'private static function deep_merge(', $this->src );
	}

	public function test_reports_changed_keys(): void {
		$this->assertStringContainsString( "'changed_keys'", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

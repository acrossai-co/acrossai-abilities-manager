<?php
/**
 * Feature 067 — Add_Text_Editor tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Add_Text_Editor extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Add_Text_Editor.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-add-text-editor'", $this->src );
	}

	public function test_requires_post_id_and_editor(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'editor' )", $this->src );
	}

	public function test_delegates_to_add_widget(): void {
		$this->assertStringContainsString( "'widget_type' => 'text-editor'", $this->src );
	}
}

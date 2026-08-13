<?php
/**
 * Feature 067 — Add_Widget ability tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Add_Widget extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Add_Widget.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-add-widget'", $this->src );
	}

	public function test_input_requires_post_id_and_widget_type(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'widget_type' )", $this->src );
	}

	public function test_validates_widget_type_via_widget_controls(): void {
		$this->assertStringContainsString( 'Widget_Controls::get_type(', $this->src );
		$this->assertStringContainsString( "'invalid_widget_type'", $this->src );
	}

	public function test_generates_element_id(): void {
		$this->assertStringContainsString( 'Document_Repository::generate_element_id()', $this->src );
	}

	public function test_builds_widget_element(): void {
		$this->assertStringContainsString( "'elType'     => 'widget'", $this->src );
		$this->assertStringContainsString( "'widgetType' => \$widget_type", $this->src );
	}

	public function test_inserts_via_repository(): void {
		$this->assertStringContainsString( 'Document_Repository::insert_element', $this->src );
	}

	public function test_persists_via_save_data(): void {
		$this->assertStringContainsString( 'Document_Repository::save_data', $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

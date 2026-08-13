<?php
/**
 * Feature 067 — Add_Heading tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Add_Heading extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Add_Heading.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-add-heading'", $this->src );
	}

	public function test_requires_post_id_and_title(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'title' )", $this->src );
	}

	public function test_header_size_enum(): void {
		$this->assertStringContainsString( "'h1', 'h2', 'h3', 'h4', 'h5', 'h6'", $this->src );
	}

	public function test_delegates_to_add_widget(): void {
		$this->assertStringContainsString( '$delegate = new Add_Widget()', $this->src );
		$this->assertStringContainsString( "'widget_type' => 'heading'", $this->src );
	}
}

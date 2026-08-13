<?php
/**
 * Feature 067 — Create_Page tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Create_Page extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Create_Page.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-create-page'", $this->src );
	}

	public function test_input_requires_title(): void {
		$this->assertStringContainsString( "'required'             => array( 'title' )", $this->src );
	}

	public function test_configures_elementor_meta(): void {
		$this->assertStringContainsString( "'_elementor_edit_mode', 'builder'", $this->src );
		$this->assertStringContainsString( "'_elementor_template_type'", $this->src );
		$this->assertStringContainsString( "'_elementor_version'", $this->src );
	}

	public function test_seeds_empty_elementor_data(): void {
		$this->assertStringContainsString( "Document_Repository::save_data( \$post_id, array(), 'none' )", $this->src );
	}

	public function test_returns_edit_url(): void {
		$this->assertStringContainsString( "'edit_url'", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

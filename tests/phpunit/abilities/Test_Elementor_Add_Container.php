<?php
/**
 * Feature 067 — Add_Container ability tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Add_Container extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Add_Container.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-add-container'", $this->src );
	}

	public function test_input_requires_post_id_only(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id' )", $this->src );
	}

	public function test_supports_parent_id_null_for_root_insert(): void {
		$this->assertStringContainsString( "array( 'string', 'null' )", $this->src );
	}

	public function test_generates_element_id(): void {
		$this->assertStringContainsString( 'Document_Repository::generate_element_id()', $this->src );
	}

	public function test_builds_container_element(): void {
		$this->assertStringContainsString( "'elType'    => 'container'", $this->src );
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

<?php
/**
 * Feature 067 — Update_Element ability tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Update_Element extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Update_Element.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-update-element'", $this->src );
	}

	public function test_destructive_annotations(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
	}

	public function test_input_requires_post_id_element_id_element(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'element_id', 'element' )", $this->src );
	}

	public function test_force_replace_flag_and_error_code(): void {
		$this->assertStringContainsString( "'force_replace'", $this->src );
		$this->assertStringContainsString( "'force_replace_required'", $this->src );
	}

	public function test_uses_edit_capability(): void {
		$this->assertStringContainsString( "load_document( \$post_id, 'edit' )", $this->src );
	}

	public function test_preserves_element_id_on_replacement(): void {
		$this->assertStringContainsString( "\$new_element['id'] = \$element_id", $this->src );
	}

	public function test_writes_via_document_repository(): void {
		$this->assertStringContainsString( 'Document_Repository::replace_element_by_id', $this->src );
		$this->assertStringContainsString( 'Document_Repository::save_data', $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

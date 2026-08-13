<?php
/**
 * Feature 067 — Update_Theme_Builder_Conditions tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Update_Theme_Builder_Conditions extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Update_Theme_Builder_Conditions.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'acrossai/elementor-update-theme-builder-conditions'", $this->src );
	}

	public function test_requires_template_id_and_conditions(): void {
		$this->assertStringContainsString( "'required'             => array( 'template_id', 'conditions' )", $this->src );
	}

	public function test_condition_type_enum(): void {
		$this->assertStringContainsString( "'include', 'exclude'", $this->src );
	}

	public function test_writes_conditions_meta(): void {
		$this->assertStringContainsString( "update_post_meta( \$template_id, '_elementor_conditions',", $this->src );
	}

	public function test_deletes_when_empty(): void {
		$this->assertStringContainsString( "delete_post_meta( \$template_id, '_elementor_conditions' )", $this->src );
	}

	public function test_invalidates_cache(): void {
		$this->assertStringContainsString( 'files_manager->clear_cache()', $this->src );
	}
}

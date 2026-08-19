<?php
/**
 * Feature 067 — Remove_Element tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Remove_Element extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Remove_Element.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/remove-element'", $this->src );
	}

	public function test_destructive_annotation(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
	}

	public function test_delegates_to_delete_element(): void {
		$this->assertStringContainsString( '$delete = new Delete_Element()', $this->src );
		$this->assertStringContainsString( '$delete->execute( $input )', $this->src );
	}

	public function test_input_has_force_delete_alias(): void {
		$this->assertStringContainsString( "'force_delete'", $this->src );
	}
}

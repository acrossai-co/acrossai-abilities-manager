<?php
/**
 * Feature 067 — Replace_Urls tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Replace_Urls extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Replace_Urls.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/replace-urls'", $this->src );
	}

	public function test_requires_from_and_to(): void {
		$this->assertStringContainsString( "'required'             => array( 'from', 'to' )", $this->src );
	}

	public function test_dry_run_defaults_true(): void {
		$this->assertStringContainsString( "'dry_run'    => array( 'type' => 'boolean', 'default' => true )", $this->src );
	}

	public function test_uses_wp_query_over_elementor_posts(): void {
		$this->assertStringContainsString( 'new WP_Query', $this->src );
		$this->assertStringContainsString( "'meta_key'       => '_elementor_data'", $this->src );
	}

	public function test_destructive_annotation(): void {
		$this->assertStringContainsString( "'destructive' => true", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

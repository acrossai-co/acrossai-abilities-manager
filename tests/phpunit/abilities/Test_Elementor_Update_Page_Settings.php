<?php
/**
 * Feature 067 — Update_Page_Settings tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Update_Page_Settings extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Update_Page_Settings.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/update-page-settings'", $this->src );
	}

	public function test_requires_post_id_and_page_settings(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'page_settings' )", $this->src );
	}

	public function test_force_replace_guard(): void {
		$this->assertStringContainsString( "'force_replace_required'", $this->src );
	}

	public function test_updates_page_settings_meta(): void {
		$this->assertStringContainsString( "update_post_meta( \$post_id, '_elementor_page_settings',", $this->src );
	}

	public function test_invalidates_cache(): void {
		$this->assertStringContainsString( 'Document_Repository::invalidate_cache', $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

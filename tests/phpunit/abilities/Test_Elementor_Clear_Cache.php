<?php
/**
 * Feature 067 — Clear_Cache tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Clear_Cache extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Clear_Cache.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/clear-cache'", $this->src );
	}

	public function test_scope_enum(): void {
		$this->assertStringContainsString( "'post', 'site', 'all'", $this->src );
	}

	public function test_delegates_to_document_repository(): void {
		$this->assertStringContainsString( 'Document_Repository::invalidate_cache', $this->src );
	}

	public function test_calls_files_manager_clear_cache_on_site_scope(): void {
		$this->assertStringContainsString( 'files_manager->clear_cache()', $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}

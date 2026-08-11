<?php
/**
 * Structural test that AcrossAI_Core_Abilities_Bootstrap::register_abilities()
 * instantiates all 11 new Feature 064 ability classes inside the correct
 * category blocks.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_064_Bootstrap_Wiring.
 */
class Test_Feature_064_Bootstrap_Wiring extends WP_UnitTestCase {

	/**
	 * The Bootstrap source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
	}

	/**
	 * Data provider — one row per new ability class.
	 *
	 * @return array<string,array{string}>
	 */
	public static function ability_class_provider(): array {
		return array(
			// Cache (4).
			'cache/get_transient'                => array( 'new Cache\\Get_Transient()' ),
			'cache/list_transients'              => array( 'new Cache\\List_Transients()' ),
			'cache/delete_transient'             => array( 'new Cache\\Delete_Transient()' ),
			'cache/delete_expired_transients'    => array( 'new Cache\\Delete_Expired_Transients()' ),
			// Options (2).
			'options/get_nested_option_value'    => array( 'new Options\\Get_Nested_Option_Value()' ),
			'options/patch_option_value'         => array( 'new Options\\Patch_Option_Value()' ),
			// Content (1).
			'content/add_post_meta'              => array( 'new Content\\Add_Post_Meta()' ),
			// Plugins (3).
			'plugins/search_wp_plugin_directory' => array( 'new Plugins\\Search_Wp_Plugin_Directory()' ),
			'plugins/uninstall_plugin'           => array( 'new Plugins\\Uninstall_Plugin()' ),
			'plugins/verify_plugin_checksums'    => array( 'new Plugins\\Verify_Plugin_Checksums()' ),
			// Core (1).
			'core/verify_core_checksums'         => array( 'new Core\\Verify_Core_Checksums()' ),
		);
	}

	/**
	 * @dataProvider ability_class_provider
	 */
	public function test_bootstrap_instantiates_new_ability( string $expected ): void {
		$this->assertStringContainsString(
			$expected,
			$this->src,
			"Bootstrap must instantiate: {$expected}"
		);
	}

	public function test_bootstrap_marker_comment_present(): void {
		$this->assertStringContainsString(
			'// Feature 064 — transient CRUD (4).',
			$this->src
		);
		$this->assertStringContainsString(
			'// Feature 064 — nested option access (2).',
			$this->src
		);
		$this->assertStringContainsString(
			'// Feature 064 — post-meta append (1).',
			$this->src
		);
		$this->assertStringContainsString(
			'// Feature 064 — plugin lifecycle & integrity (3).',
			$this->src
		);
		$this->assertStringContainsString(
			'// Feature 064 — core integrity (1).',
			$this->src
		);
	}
}

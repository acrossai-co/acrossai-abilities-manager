<?php
/**
 * Permission-gate spot-check on absorbed high-risk abilities (T041 / TASK-SEC-046-05).
 *
 * Samples FileManager\File_Delete, Database\Db_Delete, and Plugins\Plugin_Deactivate
 * — three of the most-destructive absorbed abilities — and asserts each still
 * carries a `manage_options` capability gate in its `permission_callback`.
 *
 * Per project memory feedback, the EXHAUSTIVE `permission_callback` compliance
 * audit across all 176 absorbed abilities is out of scope for Feature 046. This
 * test is the security-review-plan sample check per SEC-046-05.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Absorbed;

use WP_UnitTestCase;

/**
 * Class Test_Feature_046_Permission_Gates.
 */
class Test_Feature_046_Permission_Gates extends WP_UnitTestCase {

	/**
	 * Absolute paths to the three sampled ability files.
	 *
	 * @var array<string,string>
	 */
	private array $sources = array();

	/**
	 * Load the three sampled source files once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$plugin_root   = dirname( __DIR__, 4 );
		$this->sources = array(
			'file_delete'       => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/FileManager/File_Delete.php'
			),
			'db_delete'         => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Database/Db_Delete.php'
			),
			'plugin_deactivate' => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Plugins/Plugin_Deactivate.php'
			),
		);
	}

	/**
	 * Every sampled high-risk ability carries a `permission_callback` entry
	 * in its args array.
	 *
	 * @return void
	 */
	public function test_all_samples_declare_permission_callback(): void {
		foreach ( $this->sources as $tag => $src ) {
			$this->assertStringContainsString(
				"'permission_callback'",
				$src,
				"$tag must declare a permission_callback"
			);
		}
	}

	/**
	 * Every sampled high-risk ability gates via
	 * `current_user_can( 'manage_options' )` — the baseline capability
	 * required for destructive filesystem, DB, and plugin-lifecycle
	 * operations per Constitution §IV Security First.
	 *
	 * @return void
	 */
	public function test_all_samples_use_manage_options_gate(): void {
		foreach ( $this->sources as $tag => $src ) {
			$this->assertMatchesRegularExpression(
				"/current_user_can\(\s*'manage_options'\s*\)/",
				$src,
				"$tag must gate via current_user_can( 'manage_options' )"
			);
		}
	}

	/**
	 * No sampled ability weakens the gate to `read` or `edit_posts` or a
	 * looser capability.
	 *
	 * @return void
	 */
	public function test_no_sample_uses_looser_gate(): void {
		foreach ( $this->sources as $tag => $src ) {
			foreach ( array( "'read'", "'edit_posts'", "'edit_pages'", "'upload_files'" ) as $looser ) {
				$this->assertStringNotContainsString(
					"current_user_can( $looser )",
					$src,
					"$tag must not fall back to $looser capability"
				);
			}
		}
	}
}

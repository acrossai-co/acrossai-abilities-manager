<?php
/**
 * Structural contract tests for Feature 046 — Absorb Core Abilities Companion.
 *
 * Covers T022 (activation migration), T023 (uninstall gate), and T024
 * (Core Settings Menu registers on the Abilities tab, not its own tab).
 * Uses source-inspection instead of runtime instantiation, following the
 * existing Test_Boot_Resilience.php pattern in this suite — the plugin's
 * stub-bootstrap unit environment can't safely construct the full plugin.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Absorbed;

use WP_UnitTestCase;

/**
 * Class Test_Feature_046_Contracts.
 *
 * @since 0.1.0
 */
class Test_Feature_046_Contracts extends WP_UnitTestCase {

	/**
	 * Path to includes/AcrossAI_Activator.php.
	 *
	 * @var string
	 */
	private string $activator_source = '';

	/**
	 * Path to admin/Partials/Core_Settings_Menu.php.
	 *
	 * @var string
	 */
	private string $core_settings_source = '';

	/**
	 * Path to uninstall.php.
	 *
	 * @var string
	 */
	private string $uninstall_source = '';

	/**
	 * Path to includes/Main.php.
	 *
	 * @var string
	 */
	private string $main_source = '';

	/**
	 * Load the four source files once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$plugin_root                = dirname( __DIR__, 4 );
		$this->activator_source     = (string) file_get_contents( $plugin_root . '/includes/AcrossAI_Activator.php' );
		$this->core_settings_source = (string) file_get_contents( $plugin_root . '/admin/Partials/Core_Settings_Menu.php' );
		$this->uninstall_source     = (string) file_get_contents( $plugin_root . '/uninstall.php' );
		$this->main_source          = (string) file_get_contents( $plugin_root . '/includes/Main.php' );
	}

	// =========================================================================
	// T022 — Activation migration (SEC-046-02)
	// =========================================================================

	/**
	 * Activator calls the migration helper.
	 *
	 * @return void
	 */
	public function test_activator_calls_migrate_absorbed_options(): void {
		$this->assertMatchesRegularExpression(
			'/self::migrate_absorbed_options\(\)/',
			$this->activator_source,
			'AcrossAI_Activator::activate() must invoke migrate_absorbed_options().'
		);
	}

	/**
	 * Migration reads the legacy extra-MIMEs key.
	 *
	 * @return void
	 */
	public function test_migration_reads_legacy_mimes_key(): void {
		$this->assertStringContainsString(
			"get_option( 'acrossai_core_abilities_extra_mimes'",
			$this->activator_source
		);
	}

	/**
	 * Migration writes to the manager-branded key.
	 *
	 * @return void
	 */
	public function test_migration_writes_manager_branded_mimes_key(): void {
		$this->assertStringContainsString(
			"update_option( 'acrossai_abilities_manager_extra_mimes'",
			$this->activator_source
		);
	}

	/**
	 * Migration OR-s the legacy uninstall opt-in — only sets manager true, never demotes.
	 *
	 * @return void
	 */
	public function test_migration_or_monotonic_uninstall_optin(): void {
		$this->assertMatchesRegularExpression(
			'/! empty\( \$legacy_uninstall \).*empty\( get_option\( \'acrossai_abilities_uninstall_delete_data\'/s',
			$this->activator_source,
			'Migration must OR into the manager opt-in without demoting a manager-true state.'
		);
	}

	/**
	 * Migration deletes both legacy keys after processing (idempotency).
	 *
	 * @return void
	 */
	public function test_migration_deletes_both_legacy_keys(): void {
		$this->assertStringContainsString(
			"delete_option( 'acrossai_core_abilities_extra_mimes'",
			$this->activator_source
		);
		$this->assertStringContainsString(
			"delete_option( 'acrossai_core_abilities_uninstall_delete_data'",
			$this->activator_source
		);
	}

	// =========================================================================
	// T023 — Uninstall gate honors master opt-in (SEC-046-04)
	// =========================================================================

	/**
	 * Uninstall deletes the migrated MIME-types option inside the delete-data gate.
	 *
	 * @return void
	 */
	public function test_uninstall_deletes_migrated_mimes_inside_gate(): void {
		// Split at the gate check and verify the target delete is on the "if true" branch.
		$parts = explode( 'if ( $acrossai_delete_data )', $this->uninstall_source, 2 );
		$this->assertCount( 2, $parts, 'uninstall.php must contain the $acrossai_delete_data gate.' );
		$this->assertStringContainsString(
			"delete_option( 'acrossai_abilities_manager_extra_mimes'",
			$parts[1],
			'The migrated MIME-types option delete must sit inside the $acrossai_delete_data gate.'
		);
		$this->assertStringNotContainsString(
			"delete_option( 'acrossai_abilities_manager_extra_mimes'",
			$parts[0],
			'The delete must NOT sit outside the gate.'
		);
	}

	// =========================================================================
	// T024 — Core Settings Menu merges into Abilities tab
	// =========================================================================

	/**
	 * Core_Settings_Menu targets the shared `abilities` tab slug.
	 *
	 * @return void
	 */
	public function test_core_settings_menu_targets_abilities_tab(): void {
		$this->assertMatchesRegularExpression(
			"/public const TAB_SLUG\s*=\s*'abilities';/",
			$this->core_settings_source
		);
	}

	/**
	 * Core_Settings_Menu no longer defines register_tab() (dropped in Feature 046).
	 *
	 * @return void
	 */
	public function test_core_settings_menu_no_register_tab_method(): void {
		$this->assertDoesNotMatchRegularExpression(
			'/public function register_tab\(/',
			$this->core_settings_source,
			'Core_Settings_Menu::register_tab() must be dropped — the Abilities tab is owned by SettingsMenu.'
		);
	}

	/**
	 * Core_Settings_Menu no longer defines the uninstall-opt-in field or its constant.
	 *
	 * @return void
	 */
	public function test_core_settings_menu_no_uninstall_optin(): void {
		$this->assertStringNotContainsString(
			'OPTION_UNINSTALL_DELETE',
			$this->core_settings_source,
			'Core_Settings_Menu::OPTION_UNINSTALL_DELETE must be dropped — folded into the manager master opt-in.'
		);
		$this->assertStringNotContainsString(
			'render_uninstall_field',
			$this->core_settings_source
		);
	}

	/**
	 * Main.php does NOT wire acrossai_settings_tabs for Core_Settings_Menu.
	 *
	 * @return void
	 */
	public function test_main_does_not_wire_core_tab_filter(): void {
		// A single Loader call binding acrossai_settings_tabs to the Core_Settings_Menu variable.
		$this->assertStringNotContainsString(
			"add_filter( 'acrossai_settings_tabs', \$core_settings_menu",
			$this->main_source,
			'Main.php must NOT wire acrossai_settings_tabs for Core_Settings_Menu.'
		);
	}

	/**
	 * Main.php wires Core_Settings_Menu on admin_init only.
	 *
	 * @return void
	 */
	public function test_main_wires_core_settings_on_admin_init(): void {
		$this->assertMatchesRegularExpression(
			"/\\\$core_settings_menu = \\\\AcrossAI_Abilities_Manager\\\\Admin\\\\Partials\\\\Core_Settings_Menu::instance\(\);/",
			$this->main_source
		);
		$this->assertMatchesRegularExpression(
			"/loader->add_action\( 'admin_init', \\\$core_settings_menu, 'register_settings' \);/",
			$this->main_source
		);
	}

	/**
	 * Mime_Types_Store OPTION constant carries the manager-branded key.
	 *
	 * @return void
	 */
	public function test_mime_types_store_uses_manager_branded_key(): void {
		$plugin_root = dirname( __DIR__, 4 );
		$store       = (string) file_get_contents( $plugin_root . '/includes/Abilities/Utilities/Mime_Types_Store.php' );
		$this->assertMatchesRegularExpression(
			"/public const OPTION\s*=\s*'acrossai_abilities_manager_extra_mimes';/",
			$store
		);
	}
}

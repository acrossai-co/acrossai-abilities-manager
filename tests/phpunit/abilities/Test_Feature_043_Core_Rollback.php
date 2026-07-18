<?php
/**
 * Structural tests for Feature 043 — WordPress core rollback ability.
 *
 * Source-inspection only, matching the Test_Feature_041 / Test_Feature_042
 * precedent — the plugin's stub bootstrap can't safely construct the full
 * plugin or hit api.wordpress.org.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Feature_043_Core_Rollback.
 */
class Test_Feature_043_Core_Rollback extends WP_UnitTestCase {

	/**
	 * Absolute paths to every source file exercised by these tests.
	 *
	 * @var array<string,string>
	 */
	private array $sources = array();

	/**
	 * Load every source file once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$plugin_root = dirname( __DIR__, 3 );

		$this->sources = array(
			'wp_core_rollback' => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Core/Wp_Core_Rollback.php'
			),
			'bootstrap'        => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
			),
		);
	}

	// =========================================================================
	// Ability_Definition scaffolding
	// =========================================================================

	/**
	 * The rollback ability extends Ability_Definition and carries the full
	 * args shape used by every other ability.
	 *
	 * @return void
	 */
	public function test_wp_core_rollback_shape(): void {
		$src = $this->sources['wp_core_rollback'];
		$this->assertStringContainsString( 'extends Ability_Definition', $src );
		$this->assertStringContainsString(
			"'acrossai-abilities-manager/wp-core-rollback'",
			$src,
			'Ability name must be acrossai-abilities-manager/wp-core-rollback.'
		);
		$this->assertStringContainsString(
			"'acrossai-abilities-manager-core'",
			$src,
			'Ability category must point at the Core category slug.'
		);
		foreach (
			array(
				"'name'",
				"'label'",
				"'description'",
				"'category'",
				"'execute_callback'",
				"'permission_callback'",
				"'input_schema'",
				"'output_schema'",
				"'meta'",
			) as $key
		) {
			$this->assertStringContainsString( $key, $src, "Missing args key $key" );
		}
	}

	// =========================================================================
	// Security posture
	// =========================================================================

	/**
	 * Permission callback ANDs manage_options with update_core, matching
	 * Wp_Core_Update.
	 *
	 * @return void
	 */
	public function test_wp_core_rollback_requires_both_caps(): void {
		$src = $this->sources['wp_core_rollback'];
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\).*current_user_can\(\s*'update_core'\s*\)/s",
			$src,
			'permission_callback must AND manage_options with update_core.'
		);
	}

	/**
	 * Execute() must honour DISALLOW_FILE_MODS via File_Mods_Guard and must
	 * carry the multisite guard.
	 *
	 * @return void
	 */
	public function test_wp_core_rollback_carries_all_guards(): void {
		$src = $this->sources['wp_core_rollback'];
		$this->assertMatchesRegularExpression(
			"/File_Mods_Guard::blocked_response\(\s*'install'\s*\)/",
			$src,
			'Wp_Core_Rollback must call File_Mods_Guard::blocked_response(install).'
		);
		$this->assertStringContainsString(
			'is_multisite()',
			$src,
			'Wp_Core_Rollback must contain a multisite guard.'
		);
	}

	// =========================================================================
	// Rollback semantics
	// =========================================================================

	/**
	 * Refuses when the requested version is not strictly older than the
	 * currently-running version — that's an upgrade, not a rollback.
	 *
	 * @return void
	 */
	public function test_wp_core_rollback_refuses_non_downgrade(): void {
		$src = $this->sources['wp_core_rollback'];
		$this->assertMatchesRegularExpression(
			"/version_compare\(\s*\\\$version,\s*\\\$from_version,\s*'>='\s*\)/",
			$src,
			'Wp_Core_Rollback must refuse when target version is not strictly older than current.'
		);
	}

	/**
	 * Wraps Core_Upgrader::upgrade() with the same WP_Ajax_Upgrader_Skin
	 * everyone else uses; hands it the offer object it fetched from the API.
	 *
	 * @return void
	 */
	public function test_wp_core_rollback_uses_core_upgrader(): void {
		$src = $this->sources['wp_core_rollback'];
		$this->assertStringContainsString( 'Core_Upgrader', $src );
		$this->assertStringContainsString( 'WP_Ajax_Upgrader_Skin', $src );
		$this->assertStringContainsString( '->upgrade( $offer )', $src );
	}

	/**
	 * The API-fetched offer has its `response` field forced to `upgrade`
	 * (WP.org marks offers as `latest`; Core_Upgrader inspects `download` /
	 * `packages` / `version` regardless, but forcing `upgrade` keeps the
	 * offer object shape consistent with what get_core_updates() returns).
	 *
	 * @return void
	 */
	public function test_wp_core_rollback_forces_upgrade_response(): void {
		$src = $this->sources['wp_core_rollback'];
		$this->assertMatchesRegularExpression(
			"/\\\$offer->response\s*=\s*'upgrade'/",
			$src,
			'Wp_Core_Rollback must force the offer->response to "upgrade" before handing it to Core_Upgrader.'
		);
	}

	// =========================================================================
	// WP.org Core API integration
	// =========================================================================

	/**
	 * Fetches offers from the WP.org Core API 1.7 endpoint via wp_remote_get.
	 * No custom download / integrity code — the offer object goes straight
	 * to Core_Upgrader.
	 *
	 * @return void
	 */
	public function test_wp_core_rollback_uses_wporg_api(): void {
		$src = $this->sources['wp_core_rollback'];
		$this->assertStringContainsString(
			'https://api.wordpress.org/core/version-check/1.7/',
			$src,
			'Wp_Core_Rollback must fetch offers from the WP.org Core API 1.7 endpoint.'
		);
		$this->assertStringContainsString(
			'wp_remote_get(',
			$src,
			'Wp_Core_Rollback must use wp_remote_get() for the API fetch.'
		);
	}

	/**
	 * Caches the per-locale offer list in a site transient with a day-long
	 * TTL — same posture the reference core-rollback plugin uses.
	 *
	 * @return void
	 */
	public function test_wp_core_rollback_caches_offers_per_locale(): void {
		$src = $this->sources['wp_core_rollback'];
		$this->assertStringContainsString(
			'get_site_transient(',
			$src,
			'Wp_Core_Rollback must read cached offers from a site transient.'
		);
		$this->assertStringContainsString(
			'set_site_transient(',
			$src,
			'Wp_Core_Rollback must persist the offer list to a site transient.'
		);
		$this->assertStringContainsString(
			'DAY_IN_SECONDS',
			$src,
			'Wp_Core_Rollback must cache offers with a day-long TTL.'
		);
	}

	/**
	 * Annotations declare the rollback as destructive (`destructive=true`).
	 *
	 * @return void
	 */
	public function test_wp_core_rollback_is_destructive(): void {
		$src = $this->sources['wp_core_rollback'];
		$this->assertStringContainsString(
			"'destructive' => true",
			$src,
			'Wp_Core_Rollback annotations must declare destructive=true.'
		);
	}

	// =========================================================================
	// Bootstrap wiring
	// =========================================================================

	/**
	 * The core bootstrap instantiates Wp_Core_Rollback alongside the two
	 * Feature 042 abilities.
	 *
	 * @return void
	 */
	public function test_bootstrap_wires_wp_core_rollback(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Core\\Wp_Core_Rollback()',
			$src,
			'Bootstrap must instantiate Wp_Core_Rollback.'
		);
	}
}

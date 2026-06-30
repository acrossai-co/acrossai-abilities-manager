<?php
/**
 * Structural tests for the Feature 038 boot-resilience contracts.
 *
 * The targets are the patterns added by Tasks T010, T011, and T012 in
 * specs/038-acrossai-main-menu-integration/tasks.md. We assert via source
 * inspection (not runtime instantiation) because:
 *
 *   1. Main::__construct() pulls in many WordPress globals
 *      (plugin_dir_path, plugin_basename, the autoload file system)
 *      that are out of scope for the project's stub-bootstrap unit
 *      test suite (PHPUnit `abilities-unit` etc.).
 *   2. The acrossai-abilities-manager.php entry file fires its
 *      activation hook at registration time and is not safe to require
 *      from a test process.
 *
 * The structural patterns below ARE the durable contracts the security
 * review (SEC-001, SEC-002, SEC-004) pinned: if a future edit silently
 * loosens them — by widening the admin notice closure to reference
 * plugin-namespaced symbols, downgrading the activation guard from
 * priority 1, or dropping the manage_options capability gate — these
 * tests fail and surface the regression.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Includes;

use WP_UnitTestCase;

/**
 * Class Test_Boot_Resilience.
 */
class Test_Boot_Resilience extends WP_UnitTestCase {

	/**
	 * Path to includes/Main.php — target of T010 / T011 / SEC-001 contracts.
	 *
	 * @var string
	 */
	private string $main_source = '';

	/**
	 * Path to the plugin entry file — target of T006 / T012 / SEC-002 / SEC-004 contracts.
	 *
	 * @var string
	 */
	private string $entry_source = '';

	/**
	 * Load the two source files once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$plugin_root        = dirname( __DIR__, 3 );
		$this->main_source  = (string) file_get_contents( $plugin_root . '/includes/Main.php' );
		$this->entry_source = (string) file_get_contents( $plugin_root . '/acrossai-abilities-manager.php' );

		$this->assertNotSame( '', $this->main_source, 'includes/Main.php must be readable' );
		$this->assertNotSame( '', $this->entry_source, 'acrossai-abilities-manager.php must be readable' );
	}

	// =========================================================================
	// T010 — Main::$vendor_missing flag exists and is set on autoloader absence
	// =========================================================================

	/**
	 * Assert that Main declares a private $vendor_missing property.
	 *
	 * @return void
	 */
	public function test_vendor_missing_property_is_declared(): void {
		$this->assertMatchesRegularExpression(
			'/private\s+(?:bool\s+)?\$vendor_missing(?:\s*=\s*false)?\s*;/',
			$this->main_source,
			'Main must declare a private $vendor_missing flag (T010).'
		);
	}

	/**
	 * Assert that load_composer_dependencies sets the flag when the autoloader is absent.
	 *
	 * @return void
	 */
	public function test_load_composer_dependencies_sets_flag_on_absence(): void {
		$pattern = '/function\s+load_composer_dependencies.*?\$this->vendor_missing\s*=\s*true\s*;/s';
		$this->assertMatchesRegularExpression(
			$pattern,
			$this->main_source,
			'Main::load_composer_dependencies must set $vendor_missing = true when vendor/autoload_packages.php is absent (T010).'
		);
	}

	// =========================================================================
	// T011 — __construct short-circuits with a self-contained admin notice
	//        closure (SEC-001 closure shape contract)
	// =========================================================================

	/**
	 * Assert that __construct checks $vendor_missing before calling load_dependencies.
	 *
	 * @return void
	 */
	public function test_constructor_short_circuits_on_vendor_missing(): void {
		// Extract the body of __construct().
		$this->assertSame(
			1,
			preg_match(
				'/private function __construct\(\)\s*\{(.*?)\n\t\}\n/s',
				$this->main_source,
				$matches
			),
			'Main::__construct must be findable for inspection.'
		);

		$ctor_body = $matches[1];

		// The short-circuit must reference $this->vendor_missing AND must
		// return before load_dependencies(). We approximate "before" by
		// asserting the $vendor_missing check appears earlier in the body
		// than the load_dependencies call.
		$missing_check_pos = strpos( $ctor_body, '$this->vendor_missing' );
		$load_deps_pos     = strpos( $ctor_body, 'load_dependencies' );

		$this->assertNotFalse( $missing_check_pos, 'Main::__construct must check $this->vendor_missing (T011).' );
		$this->assertNotFalse( $load_deps_pos, 'Main::__construct must still reference load_dependencies (normal-boot path).' );
		$this->assertLessThan(
			$load_deps_pos,
			$missing_check_pos,
			'The $vendor_missing check must appear before the load_dependencies() call so the early return takes effect (T011).'
		);
	}

	/**
	 * SEC-001: the admin-notice closure registered for vendor-missing must
	 * gate on current_user_can( 'manage_options' ) per DEC-FAIL-OPEN-NOTICE.
	 *
	 * @return void
	 */
	public function test_admin_notice_closure_gates_on_manage_options(): void {
		// Extract the vendor-missing block. Pattern: `if ( $this->vendor_missing ) { ... }`
		$this->assertSame(
			1,
			preg_match(
				'/if\s*\(\s*\$this->vendor_missing\s*\)\s*\{(.*?)return\s*;\s*\}/s',
				$this->main_source,
				$matches
			),
			'Vendor-missing block must short-circuit __construct (T011).'
		);

		$block = $matches[1];

		$this->assertStringContainsString(
			"current_user_can( 'manage_options' )",
			$block,
			'Admin notice closure must gate on current_user_can( manage_options ) (SEC-001 + DEC-FAIL-OPEN-NOTICE).'
		);
	}

	/**
	 * SEC-001: the admin-notice closure must use esc_html__ with the
	 * acrossai-abilities-manager text domain.
	 *
	 * @return void
	 */
	public function test_admin_notice_closure_uses_correct_text_domain(): void {
		$this->assertSame(
			1,
			preg_match(
				'/if\s*\(\s*\$this->vendor_missing\s*\)\s*\{(.*?)return\s*;\s*\}/s',
				$this->main_source,
				$matches
			)
		);

		$block = $matches[1];

		$this->assertStringContainsString(
			'esc_html__',
			$block,
			'Admin notice body must be escaped via esc_html__ (Constitution §IV).'
		);
		$this->assertStringContainsString(
			"'acrossai-abilities-manager'",
			$block,
			'Admin notice text domain must be acrossai-abilities-manager (Constitution §VII / FR-013).'
		);
	}

	/**
	 * SEC-001: the admin-notice closure must NOT reference plugin-namespaced
	 * symbols (`AcrossAI_Abilities_Manager\…`, `$this->`, `self::`, `static::`)
	 * because those require the autoloader, which is by definition absent in
	 * the vendor-missing state. Allowed: closures with no `use($this)` plus
	 * WP globals (current_user_can, printf, esc_html, esc_html__, _e, __).
	 *
	 * @return void
	 */
	public function test_admin_notice_closure_is_self_contained(): void {
		$this->assertSame(
			1,
			preg_match(
				'/if\s*\(\s*\$this->vendor_missing\s*\)\s*\{(.*?)return\s*;\s*\}/s',
				$this->main_source,
				$matches
			)
		);

		$block = $matches[1];

		// Extract just the closure body (between `function () {` and the close).
		$this->assertSame(
			1,
			preg_match(
				'/(?:static\s+)?function\s*\(\s*\)(?:\s*use\s*\([^)]*\))?\s*\{(.*?)\n\s*\}\s*\)?\s*;/s',
				$block,
				$closure_matches
			),
			'Vendor-missing admin notice block must contain a closure (SEC-001).'
		);

		$closure_body = $closure_matches[1];

		$forbidden = array(
			'$this->'                            => 'Closure must not reference $this-> (SEC-001).',
			'self::'                             => 'Closure must not reference self:: (SEC-001).',
			'static::'                           => 'Closure must not reference static:: (SEC-001).',
			'AcrossAI_Abilities_Manager\\\\'     => 'Closure must not reference plugin-namespaced FQCNs (SEC-001).',
		);

		foreach ( $forbidden as $needle => $message ) {
			$this->assertStringNotContainsString( $needle, $closure_body, $message );
		}
	}

	// =========================================================================
	// T006 — Entry file bootstrap idempotency (SEC-004) and class_exists guard
	// =========================================================================

	/**
	 * Bootstrap must be registered on plugins_loaded priority 0.
	 *
	 * @return void
	 */
	public function test_host_menu_bootstrap_registered_at_plugins_loaded_priority_zero(): void {
		$pattern = '/add_action\s*\(\s*\'plugins_loaded\'.*?AcrossAI_Main_Menu.*?,\s*0\s*\)\s*;/s';
		$this->assertMatchesRegularExpression(
			$pattern,
			$this->entry_source,
			'Host menu bootstrap must register at plugins_loaded priority 0 (T006).'
		);
	}

	/**
	 * Bootstrap must include the multi-consumer idempotency guard (SEC-004).
	 *
	 * @return void
	 */
	public function test_host_menu_bootstrap_has_idempotency_guard(): void {
		$this->assertStringContainsString(
			"did_action( 'acrossai_main_menu_bootstrapped' )",
			$this->entry_source,
			'Host menu bootstrap must check did_action() to guard against double-instantiation when multiple AcrossAI plugins are active (SEC-004 + PATTERN-SHARED-MENU-CONSUMER-IDEMPOTENCY).'
		);

		$this->assertStringContainsString(
			"do_action( 'acrossai_main_menu_bootstrapped' )",
			$this->entry_source,
			'Host menu bootstrap must fire did_action() signal so later consumers short-circuit (SEC-004).'
		);
	}

	/**
	 * Bootstrap must use class_exists guard for graceful degradation (§V).
	 *
	 * @return void
	 */
	public function test_host_menu_bootstrap_has_class_exists_guard(): void {
		$this->assertMatchesRegularExpression(
			'/class_exists\s*\(\s*\\\\?AcrossAI_Main_Menu\\\\SettingsPage::class\s*\)/',
			$this->entry_source,
			'Host menu bootstrap must check class_exists() so the plugin degrades gracefully when the menu package vendor is absent (Constitution §V).'
		);
	}

	// =========================================================================
	// T012 — Activation guard at priority 1 (SEC-002 ordering contract)
	// =========================================================================

	/**
	 * Activation guard must register at priority 1, BEFORE the existing
	 * default-priority-10 acrossai_abilities_manager_activate callback that
	 * requires the autoloader. Without the priority shift, the existing
	 * callback fatals before the guard can wp_die().
	 *
	 * @return void
	 */
	public function test_activation_guard_registered_at_priority_one(): void {
		$this->assertMatchesRegularExpression(
			'/add_action\s*\(\s*\'activate_\'\s*\.\s*plugin_basename\s*\(\s*__FILE__\s*\)\s*,.*?,\s*1\s*\)\s*;/s',
			$this->entry_source,
			'Activation guard must register at priority 1 to run before existing callback at default priority 10 (SEC-002).'
		);
	}

	/**
	 * Activation guard must check the autoloader file's existence.
	 *
	 * @return void
	 */
	public function test_activation_guard_checks_autoloader_presence(): void {
		// The guard block is the `add_action('activate_'…, …, 1)` registration.
		// We look for a file_exists check on autoload_packages.php somewhere
		// within ~600 characters of the registration.
		$this->assertSame(
			1,
			preg_match(
				'/add_action\s*\(\s*\'activate_\'.*?file_exists\s*\(\s*__DIR__\s*\.\s*\'\/vendor\/autoload_packages\.php\'\s*\).*?,\s*1\s*\)/s',
				$this->entry_source
			),
			'Activation guard must call file_exists(__DIR__ . /vendor/autoload_packages.php) (T012).'
		);
	}

	/**
	 * Activation guard wp_die message must use esc_html__ and the correct text domain.
	 *
	 * @return void
	 */
	public function test_activation_guard_uses_correct_text_domain(): void {
		// Extract the activation hook block.
		$this->assertSame(
			1,
			preg_match(
				'/add_action\s*\(\s*\'activate_\'.*?\,\s*1\s*\)\s*;/s',
				$this->entry_source,
				$matches
			)
		);

		$block = $matches[0];

		$this->assertStringContainsString( 'wp_die', $block, 'Activation guard must call wp_die on missing autoloader (T012).' );
		$this->assertStringContainsString( 'esc_html__', $block, 'Activation guard message must be escaped (Constitution §IV).' );
		$this->assertStringContainsString(
			"'acrossai-abilities-manager'",
			$block,
			'Activation guard message must use the acrossai-abilities-manager text domain (FR-013).'
		);
	}
}

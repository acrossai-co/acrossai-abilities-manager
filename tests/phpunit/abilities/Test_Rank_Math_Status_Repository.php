<?php
/**
 * Feature 069 — tests for Status_Repository.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Status_Repository;
use WP_Error;
use WP_UnitTestCase;

class Test_Rank_Math_Status_Repository extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/RankMath/Status_Repository.php'
		);
	}

	public function test_is_static_only_utility(): void {
		$this->assertStringContainsString( 'final class Status_Repository', $this->src );
		$this->assertStringContainsString( 'private function __construct()', $this->src );
		$this->assertStringNotContainsString( 'public static function instance()', $this->src );
	}

	public function test_panel_list(): void {
		$this->assertSame(
			array( 'status', 'tools', 'import_export', 'version_control', 'google' ),
			Status_Repository::PANELS
		);
	}

	public function test_unknown_panel_returns_error(): void {
		$result = Status_Repository::panel( 'nonsense' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
	}

	/**
	 * The import_export panel maps to \RankMath\Admin\Import_Export, NOT
	 * \RankMath\Status\Import_Export_Settings. The latter is a different class
	 * that holds get_export_data() / do_import_data() and has no get_json_data().
	 * Mirrors Rank Math's own hash at modules/status/class-rest.php:141-147.
	 */
	public function test_panel_classes_mirror_rank_math_dispatch_hash(): void {
		$this->assertStringContainsString( "'status'          => '\\RankMath\\Status\\System_Status'", $this->src );
		$this->assertStringContainsString( "'tools'           => '\\RankMath\\Tools\\Database_Tools'", $this->src );
		$this->assertStringContainsString( "'import_export'   => '\\RankMath\\Admin\\Import_Export'", $this->src );
		$this->assertStringContainsString( "'version_control' => '\\RankMath\\Version_Control'", $this->src );
		// The wrong class must not appear as a class reference. Checked as a
		// quoted FQN so the docblock explaining the distinction does not trip it.
		$this->assertStringNotContainsString( "'\\RankMath\\Status\\Import_Export_Settings'", $this->src );
	}

	/**
	 * Regression guard, verified against Rank Math 1.0.276.
	 *
	 * Authentication::is_token_expired() reads $tokens['expire'] with no isset()
	 * check (analytics/google/class-authentication.php:99), so calling it on a
	 * site that never connected Google emits "Undefined array key expire". A
	 * readonly ability must not produce PHP warnings, so the call is gated on
	 * is_authorized() — which is also the correct answer, since an unauthorised
	 * site has no token to expire.
	 */
	public function test_token_expiry_check_is_gated_on_authorisation(): void {
		$this->assertStringContainsString( '$authorized && \\RankMath\\Google\\Authentication::is_token_expired()', $this->src );
		// The authorised flag must be resolved once, before the array is built.
		$assign_pos = strpos( $this->src, '$authorized = $has_auth' );
		$use_pos    = strpos( $this->src, "'token_expired'" );
		$this->assertNotFalse( $assign_pos );
		$this->assertNotFalse( $use_pos );
		$this->assertLessThan( $use_pos, $assign_pos );
	}

	/**
	 * Console::get_sites() performs a live request to googleapis.com, so it must
	 * never run on the default path.
	 */
	public function test_site_listing_is_opt_in_and_requires_connection(): void {
		$this->assertStringContainsString( "if ( \$include_sites && \$data['console_connected'] )", $this->src );
	}

	/**
	 * method_exists() guards mean a Rank Math build that drops a panel class
	 * degrades to a clean not_found error rather than a fatal.
	 */
	public function test_missing_panel_class_degrades_cleanly(): void {
		$this->assertStringContainsString( "method_exists( \$class, 'get_json_data' )", $this->src );
		$this->assertStringContainsString( "'not_found'", $this->src );
	}
}

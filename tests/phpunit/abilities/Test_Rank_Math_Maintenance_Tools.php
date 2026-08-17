<?php
/**
 * Feature 069 — tests for Maintenance_Tools.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Maintenance_Tools;
use WP_Error;
use WP_UnitTestCase;

class Test_Rank_Math_Maintenance_Tools extends WP_UnitTestCase {

	private string $src = '';
	private string $code = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src  = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/RankMath/Maintenance_Tools.php'
		);
		$out = '';
		foreach ( token_get_all( $this->src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$out .= is_array( $token ) ? $token[1] : $token;
		}
		$this->code = $out;
	}

	/**
	 * Research F3 — the single most important assertion in this file.
	 *
	 * Database_Tools::hooks() only registers the rank_math/tools/{id} filters during a
	 * real /toolsAction REST request, so from an ability context apply_filters() has no
	 * listener and returns the literal default 'Something went wrong.' Dispatch must go
	 * to concrete [object, method] pairs instead.
	 */
	public function test_dispatch_does_not_use_apply_filters(): void {
		$this->assertStringNotContainsString( 'rank_math/tools/', $this->code );
		$this->assertStringNotContainsString( 'apply_filters', $this->code );
	}

	public function test_all_twelve_tools_are_declared(): void {
		$this->assertCount( 12, Maintenance_Tools::tool_ids() );
	}

	public function test_tool_ids_match_the_documented_set(): void {
		$this->assertSame(
			array(
				'clear_transients',
				'clear_seo_analysis',
				'delete_links',
				'delete_log',
				'delete_redirections',
				'recreate_tables',
				'recreate_actionscheduler_tables',
				'yoast_blocks',
				'aioseo_blocks',
				'analytics_clear_caches',
				'analytics_reindex_posts',
				'analytics_fix_collations',
			),
			Maintenance_Tools::tool_ids()
		);
	}

	/**
	 * Exactly these four continue after the response. Getting this wrong means a caller
	 * reads success as completion and acts on stale data.
	 */
	public function test_only_the_four_background_tools_are_async(): void {
		$async = array_values( array_filter( Maintenance_Tools::tool_ids(), array( Maintenance_Tools::class, 'is_async' ) ) );
		$this->assertSame(
			array( 'recreate_tables', 'yoast_blocks', 'aioseo_blocks', 'analytics_reindex_posts' ),
			$async
		);
	}

	public function test_module_requirements(): void {
		$this->assertSame( '', Maintenance_Tools::required_module( 'clear_transients' ) );
		$this->assertSame( 'seo-analysis', Maintenance_Tools::required_module( 'clear_seo_analysis' ) );
		$this->assertSame( '404-monitor', Maintenance_Tools::required_module( 'delete_log' ) );
		$this->assertSame( 'redirections', Maintenance_Tools::required_module( 'delete_redirections' ) );
		$this->assertSame( 'analytics', Maintenance_Tools::required_module( 'analytics_clear_caches' ) );
	}

	public function test_unknown_tool_is_rejected_with_the_valid_list(): void {
		$result = Maintenance_Tools::dispatch( 'not_a_tool' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
		$this->assertStringContainsString( 'clear_transients', $result->get_error_message() );
	}

	// -----------------------------------------------------------------
	// normalize_result() — Rank Math's handlers return inconsistent shapes.
	// -----------------------------------------------------------------

	public function test_normalize_a_plain_string_return(): void {
		$result = Maintenance_Tools::normalize_result( 'clear_transients', '24 transients cleared.', false );
		$this->assertTrue( $result['completed'] );
		$this->assertFalse( $result['async'] );
		$this->assertSame( '24 transients cleared.', $result['tool_message'] );
		$this->assertArrayNotHasKey( 'poll_hint', $result );
	}

	public function test_normalize_an_error_array_return(): void {
		$result = Maintenance_Tools::normalize_result(
			'recreate_actionscheduler_tables',
			array( 'status' => 'error', 'message' => 'Action Scheduler is missing.' ),
			false
		);
		$this->assertFalse( $result['completed'] );
		$this->assertSame( 'Action Scheduler is missing.', $result['tool_message'] );
	}

	public function test_normalize_a_bool_return(): void {
		$this->assertFalse( Maintenance_Tools::normalize_result( 'delete_links', false, false )['completed'] );
		$this->assertTrue( Maintenance_Tools::normalize_result( 'delete_links', true, false )['completed'] );
	}

	/**
	 * An async tool has STARTED, not finished — completed must be false regardless of
	 * what the handler returned, and a poll hint must be present.
	 */
	public function test_async_tools_report_started_not_completed(): void {
		$result = Maintenance_Tools::normalize_result( 'yoast_blocks', 'Conversion started.', true );
		$this->assertFalse( $result['completed'] );
		$this->assertTrue( $result['async'] );
		$this->assertArrayHasKey( 'poll_hint', $result );
		$this->assertStringContainsString( 'rank-math', $result['poll_hint'] );
	}

	// -----------------------------------------------------------------
	// Structure.
	// -----------------------------------------------------------------

	public function test_is_static_only_utility(): void {
		$this->assertStringContainsString( 'final class Maintenance_Tools', $this->src );
		$this->assertStringContainsString( 'private function __construct()', $this->src );
	}

	/**
	 * Constructing Database_Tools instantiates several singletons, so it must be built
	 * once per request rather than per dispatch.
	 */
	public function test_handlers_are_memoised(): void {
		$this->assertStringContainsString( 'array_key_exists( $group, self::$handlers )', $this->code );
	}

	public function test_catalogue_reports_runnability(): void {
		$catalogue = Maintenance_Tools::catalogue();
		$this->assertCount( 12, $catalogue );
		foreach ( $catalogue as $tool ) {
			foreach ( array( 'id', 'title', 'required_module', 'runnable', 'async' ) as $key ) {
				$this->assertArrayHasKey( $key, $tool );
			}
		}
	}
}

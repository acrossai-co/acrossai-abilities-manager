<?php
/**
 * Feature 069 — tests for Redirections_Repository.
 *
 * The Apache/Nginx serializers are a port of Rank Math's private formatters, so the
 * expected strings below are the parity contract. They were captured from live
 * output against Rank Math 1.0.276 — if a Rank Math upgrade changes its exporter,
 * these fail and the port needs re-diffing.
 *
 * The serializers themselves are private, so they are exercised through reflection
 * rather than through export(), which needs a live database.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Redirections_Repository;
use ReflectionMethod;
use WP_UnitTestCase;

class Test_Rank_Math_Redirections_Repository extends WP_UnitTestCase {

	private string $src = '';

	private string $code = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/RankMath/Redirections_Repository.php'
		);
		$this->code = self::strip_comments( $this->src );
	}

	/**
	 * Comments stripped. Docblocks here legitimately name the very symbols these
	 * assertions require to be absent from the code.
	 */
	private static function strip_comments( string $src ): string {
		$out = '';
		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$out .= is_array( $token ) ? $token[1] : $token;
		}
		return $out;
	}

	/**
	 * Invoke a private static serializer helper.
	 *
	 * @param string       $method Method name.
	 * @param array<mixed> $args   Arguments.
	 * @return mixed
	 */
	private static function invoke( string $method, array $args ) {
		$ref = new ReflectionMethod( Redirections_Repository::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	/**
	 * Serialize one redirection to Apache and return the emitted lines.
	 *
	 * @param array<string,mixed> $item     Redirection row.
	 * @param array<int,string>   $warnings Collected warnings, by reference.
	 * @return string[]
	 */
	private static function apache( array $item, array &$warnings ): array {
		$ref = new ReflectionMethod( Redirections_Repository::class, 'to_apache' );
		$ref->setAccessible( true );
		$out = $ref->invokeArgs( null, array( array( $item ), &$warnings ) );
		// Drop the wrapping <IfModule> lines.
		return array_slice( (array) $out, 1, -1 );
	}

	/**
	 * Serialize one redirection to Nginx and return the emitted lines.
	 *
	 * @param array<string,mixed> $item     Redirection row.
	 * @param array<int,string>   $warnings Collected warnings, by reference.
	 * @return string[]
	 */
	private static function nginx( array $item, array &$warnings ): array {
		$ref = new ReflectionMethod( Redirections_Repository::class, 'to_nginx' );
		$ref->setAccessible( true );
		$out = $ref->invokeArgs( null, array( array( $item ), &$warnings ) );
		// Drop the wrapping server { } lines.
		return array_slice( (array) $out, 1, -1 );
	}

	/**
	 * @param array<int,array<string,string>> $sources
	 * @return array<string,mixed>
	 */
	private static function row( array $sources, string $url_to = 'http://example.test/new', string $code = '301', int $id = 1 ): array {
		return array(
			'id'          => $id,
			'sources'     => $sources,
			'url_to'      => $url_to,
			'header_code' => $code,
		);
	}

	// -----------------------------------------------------------------
	// Apache — one case per comparison type.
	// -----------------------------------------------------------------

	/**
	 * @dataProvider provide_apache_comparisons
	 */
	public function test_apache_comparison_patterns( string $comparison, string $pattern, string $expected ): void {
		$warnings = array();
		$lines    = self::apache( self::row( array( array( 'pattern' => $pattern, 'comparison' => $comparison ) ) ), $warnings );

		$this->assertCount( 1, $lines );
		$this->assertSame( "RewriteRule {$expected} http://example.test/new [R=301,L]", $lines[0] );
	}

	/**
	 * Captured from live Rank Math 1.0.276 output.
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function provide_apache_comparisons(): array {
		return array(
			'exact'    => array( 'exact', 'old-page', '^old\-page/?$' ),
			'start'    => array( 'start', 'archive', '^archive' ),
			'contains' => array( 'contains', 'promo', '^(.*)promo(.*)$' ),
			'end'      => array( 'end', '.pdf', '\.pdf/?$' ),
		);
	}

	/**
	 * 410 Gone becomes Apache's [G] flag rather than a redirect target.
	 */
	public function test_apache_410_uses_the_gone_flag(): void {
		$warnings = array();
		$lines    = self::apache( self::row( array( array( 'pattern' => 'gone', 'comparison' => 'exact' ) ), '', '410' ), $warnings );

		$this->assertSame( 'RewriteRule ^gone/?$ - [G]', $lines[0] );
	}

	public function test_apache_preserves_the_status_code(): void {
		$warnings = array();
		$lines    = self::apache( self::row( array( array( 'pattern' => 'temp', 'comparison' => 'exact' ) ), 'http://example.test/perm', '302' ), $warnings );

		$this->assertStringContainsString( '[R=302,L]', $lines[0] );
	}

	/**
	 * A query string cannot be matched by RewriteRule, so it becomes a preceding
	 * RewriteCond and the rule matches the path alone.
	 */
	public function test_apache_splits_a_query_string_into_a_rewritecond(): void {
		$warnings = array();
		$lines    = self::apache( self::row( array( array( 'pattern' => 'search?q=old', 'comparison' => 'exact' ) ) ), $warnings );

		$this->assertCount( 2, $lines );
		$this->assertSame( 'RewriteCond %{QUERY_STRING} ^q\=old$', $lines[0] );
		$this->assertSame( 'RewriteRule ^search/?$ http://example.test/new [R=301,L]', $lines[1] );
	}

	/**
	 * PARITY QUIRK — is_valid_regex() wraps the pattern in '/.../', so a pattern
	 * containing an unescaped '/' closes the delimiter early and is judged invalid
	 * even though it is a valid regex. Rank Math does this too; the port reproduces
	 * it deliberately and warns rather than silently emitting a live rule.
	 */
	public function test_apache_comments_out_a_pattern_rejected_by_rank_maths_regex_check(): void {
		$warnings = array();
		$lines    = self::apache( self::row( array( array( 'pattern' => '^products/([0-9]+)$', 'comparison' => 'regex' ) ) ), $warnings );

		$this->assertStringStartsWith( '# RewriteRule ', $lines[0] );
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'commented out', $warnings[0] );
	}

	/**
	 * A regex with no '/' survives the check and is emitted live.
	 */
	public function test_apache_emits_a_slash_free_regex_uncommented(): void {
		$warnings = array();
		$lines    = self::apache( self::row( array( array( 'pattern' => '^products-([0-9]+)$', 'comparison' => 'regex' ) ) ), $warnings );

		$this->assertStringStartsWith( 'RewriteRule ', $lines[0] );
		$this->assertSame( array(), $warnings );
	}

	// -----------------------------------------------------------------
	// Nginx.
	// -----------------------------------------------------------------

	public function test_nginx_301_is_permanent_and_302_is_redirect(): void {
		$warnings = array();

		$permanent = self::nginx( self::row( array( array( 'pattern' => 'a', 'comparison' => 'exact' ) ), 'http://example.test/new', '301' ), $warnings );
		$this->assertStringEndsWith( ' permanent;', $permanent[0] );

		$temporary = self::nginx( self::row( array( array( 'pattern' => 'b', 'comparison' => 'exact' ) ), 'http://example.test/new', '302' ), $warnings );
		$this->assertStringEndsWith( ' redirect;', $temporary[0] );
	}

	public function test_nginx_lines_are_indented(): void {
		$warnings = array();
		$lines    = self::nginx( self::row( array( array( 'pattern' => 'a', 'comparison' => 'exact' ) ) ), $warnings );

		$this->assertStringStartsWith( '    rewrite ', $lines[0] );
	}

	/**
	 * Nginx SKIPS an invalid-regex source entirely, where Apache comments it out.
	 * The asymmetry is Rank Math's and is preserved.
	 */
	public function test_nginx_omits_an_invalid_regex_entirely(): void {
		$warnings = array();
		$lines    = self::nginx( self::row( array( array( 'pattern' => '^products/([0-9]+)$', 'comparison' => 'regex' ) ) ), $warnings );

		$this->assertSame( array(), $lines );
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'omitted', $warnings[0] );
	}

	/**
	 * Nginx has no [G] equivalent and Rank Math does not special-case 410, so the
	 * line is emitted with an empty target — invalid config. Output stays identical
	 * for parity, but the caller must be warned.
	 */
	public function test_nginx_warns_that_a_410_produces_an_invalid_directive(): void {
		$warnings = array();
		$lines    = self::nginx( self::row( array( array( 'pattern' => 'gone', 'comparison' => 'exact' ) ), '', '410' ), $warnings );

		// Parity: the broken line is still emitted, exactly as Rank Math emits it.
		$this->assertCount( 1, $lines );
		$this->assertStringContainsString( 'rewrite ^gone/?$', $lines[0] );

		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'return 410;', $warnings[0] );
	}

	// -----------------------------------------------------------------
	// Encoders.
	// -----------------------------------------------------------------

	/**
	 * encode2nd leaves path and query punctuation readable while encoding spaces.
	 */
	public function test_encode2nd_preserves_url_punctuation(): void {
		$this->assertSame(
			'http://example.test/a/b?c=1&d=2',
			self::invoke( 'encode2nd', array( 'http://example.test/a/b?c=1&d=2' ) )
		);
		$this->assertSame( '/a%20b', self::invoke( 'encode2nd', array( '/a b' ) ) );
	}

	public function test_encode_regex_strips_leading_slash(): void {
		$this->assertStringStartsNotWith( '/', (string) self::invoke( 'encode_regex', array( '/foo-bar' ) ) );
	}

	public function test_normalize_nginx_redirect_strips_newlines(): void {
		$line = (string) self::invoke( 'normalize_nginx_redirect', array( "^a$\ninjected", '/b', 'permanent' ) );
		$this->assertStringNotContainsString( "\n", $line );
		$this->assertStringNotContainsString( 'injected', $line );
	}

	// -----------------------------------------------------------------
	// Structure and the live-found bug fixes.
	// -----------------------------------------------------------------

	public function test_is_static_only_utility(): void {
		$this->assertStringContainsString( 'final class Redirections_Repository', $this->src );
		$this->assertStringContainsString( 'private function __construct()', $this->src );
	}

	public function test_status_actions_are_all_reversible(): void {
		$this->assertSame(
			array(
				'activate'   => 'active',
				'deactivate' => 'inactive',
				'trash'      => 'trashed',
				'restore'    => 'active',
			),
			Redirections_Repository::STATUS_ACTIONS
		);
		// Hard delete must not be reachable through the non-destructive path.
		$this->assertArrayNotHasKey( 'delete', Redirections_Repository::STATUS_ACTIONS );
	}

	public function test_trashed_status_is_listable(): void {
		$this->assertContains( 'trashed', Redirections_Repository::STATUSES );
	}

	/**
	 * Regression guard — found live. The model exposes set_status(), not a generic
	 * set(); calling set() threw and surfaced as ability_callback_exception.
	 */
	public function test_loop_path_uses_set_status(): void {
		$this->assertStringContainsString( "set_status( 'inactive' )", $this->code );
		$this->assertStringNotContainsString( "->set( 'status'", $this->code );
	}

	/**
	 * Regression guard — found live. Redirection::from() builds from scratch, so a
	 * partial update must merge the stored row or save() bails at has_sources().
	 */
	public function test_partial_update_merges_stored_values(): void {
		$this->assertStringContainsString( '$data   = array_merge( $stored, $data );', $this->src );
		foreach ( array( 'sources', 'url_to', 'header_code', 'status' ) as $field ) {
			$this->assertMatchesRegularExpression( "/'{$field}'\s*=>\s*/", $this->src );
		}
	}

	/**
	 * Regression guard — found live. DB::match_redirections() returns the FIRST
	 * matching row or false, never a list, so iterating it as a collection silently
	 * returns nothing.
	 */
	public function test_find_does_not_use_match_redirections(): void {
		$this->assertStringNotContainsString( 'match_redirections(', $this->code );
		$this->assertStringContainsString( 'DB::compare_sources( $sources, $path )', $this->code );
	}

	/**
	 * The candidate sweep must be bounded so a very large rule set cannot stall the
	 * request.
	 */
	public function test_find_sweep_is_bounded(): void {
		$this->assertStringContainsString( 'count( $rows ) < 2000', $this->src );
	}
}

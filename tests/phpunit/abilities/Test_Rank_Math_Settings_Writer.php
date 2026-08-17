<?php
/**
 * Feature 069 — tests for Settings_Writer.
 *
 * The write path itself needs a live Rank Math install, so the sequencing and
 * safety properties are asserted by source inspection here and exercised for real
 * by integration checks 1, 2 and 7 in quickstart.md. The read path and the
 * validation short-circuits are testable directly.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Settings_Writer;
use WP_Error;
use WP_UnitTestCase;

class Test_Rank_Math_Settings_Writer extends WP_UnitTestCase {

	private string $src = '';

	/** Source with comments stripped — docblocks legitimately quote the very
	 * patterns these tests search for, so position and absence assertions must
	 * run against code only. */
	private string $code = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src  = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/RankMath/Settings_Writer.php'
		);
		$this->code = self::strip_comments( $this->src );
	}

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

	// -----------------------------------------------------------------
	// Behavioural — the validation short-circuits run without Rank Math.
	// -----------------------------------------------------------------

	public function test_read_rejects_unknown_panel(): void {
		$result = Settings_Writer::read( 'no-such-panel' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
	}

	public function test_read_requires_object_for_dynamic_panel(): void {
		$result = Settings_Writer::read( 'titles-post-type' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'requires an object', $result->get_error_message() );
	}

	public function test_save_rejects_unknown_panel(): void {
		$result = Settings_Writer::save( 'no-such-panel', '', array( 'x' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
	}

	/**
	 * A payload of nothing but denied keys must not reach Rank Math at all.
	 */
	public function test_save_rejects_denied_only_payload(): void {
		$result = Settings_Writer::save( 'general-others', '', array( 'usage_tracking' => 'on' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'protected_field', $result->get_error_code() );
	}

	/**
	 * The read path exposes the resolved field specification, which is what makes
	 * each writer's accepted keys discoverable at runtime.
	 */
	public function test_read_returns_resolved_field_specs(): void {
		$result = Settings_Writer::read( 'general-links' );
		$this->assertIsArray( $result );
		$this->assertSame( 'general-links', $result['panel'] );
		$this->assertSame( 'general', $result['option_type'] );
		$this->assertStringStartsWith( 'includes/', $result['source'] );

		$by_id = array();
		foreach ( $result['fields'] as $field ) {
			$by_id[ $field['id'] ] = $field;
		}

		$this->assertArrayHasKey( 'nofollow_domains', $by_id );
		// The emitted type, not the legacy CMB2 name — research F2c.
		$this->assertSame( 'textarea', $by_id['nofollow_domains']['type'] );
		$this->assertFalse( $by_id['nofollow_domains']['readonly'] );
		foreach ( array( 'id', 'type', 'enum', 'min', 'max', 'pattern', 'readonly', 'current' ) as $key ) {
			$this->assertArrayHasKey( $key, $by_id['nofollow_domains'] );
		}
	}

	public function test_read_marks_display_only_fields_readonly(): void {
		$result = Settings_Writer::read( 'general-robots-txt' );
		$this->assertIsArray( $result );
		$readonly = array();
		foreach ( $result['fields'] as $field ) {
			if ( $field['readonly'] ) {
				$readonly[] = $field['id'];
			}
		}
		$this->assertContains( 'robots_locked', $readonly );
		$this->assertNotContains( 'robots_txt_content', $readonly );
	}

	public function test_read_resolves_dynamic_panel_field_ids(): void {
		$result = Settings_Writer::read( 'titles-taxonomy', 'category' );
		$this->assertIsArray( $result );
		$ids = array_column( $result['fields'], 'id' );
		$this->assertContains( 'tax_category_title', $ids );
		$this->assertContains( 'tax_category_description', $ids );
	}

	// -----------------------------------------------------------------
	// Source inspection — sequencing that needs a live Rank Math to execute.
	// -----------------------------------------------------------------

	public function test_is_static_only_utility(): void {
		$this->assertStringContainsString( 'final class Settings_Writer', $this->src );
		$this->assertStringContainsString( 'private function __construct()', $this->src );
	}

	/**
	 * The mandatory order: validate, strip denied, attach types, then write.
	 */
	public function test_save_sequence(): void {
		$validate = strpos( $this->code, 'Settings_Registry::validate(' );
		$strip    = strpos( $this->code, 'foreach ( Settings_Registry::DENIED_KEYS as $denied )' );
		$types    = strpos( $this->code, 'Settings_Registry::field_types_for(' );
		$write    = strpos( $this->code, 'Option_Center::save_settings(' );

		foreach ( array( $validate, $strip, $types, $write ) as $pos ) {
			$this->assertNotFalse( $pos );
		}
		$this->assertLessThan( $strip, $validate );
		$this->assertLessThan( $types, $strip );
		$this->assertLessThan( $write, $types );
	}

	/**
	 * check_updated_fields() does in_array( $field_id, $updated, true ) and
	 * TypeErrors on null, so $updated must always be an array.
	 */
	public function test_updated_is_always_an_array(): void {
		$this->assertStringContainsString( '$updated = array_keys( $validated );', $this->code );
	}

	/**
	 * $is_reset true forces an unconditional rewrite flush; we never expose it.
	 */
	public function test_is_reset_is_always_false(): void {
		$this->assertStringContainsString( '$updated, false )', $this->code );
		$this->assertStringNotContainsString( '$updated, true )', $this->code );
	}

	/**
	 * Rank Math's internal $map has no default branch, so an unrecognised option
	 * type would fatal on the argument spread. Guard before calling.
	 */
	public function test_option_type_is_guarded_before_the_call(): void {
		$guard = strpos( $this->code, 'in_array( $option_type, self::SAVEABLE_TYPES, true )' );
		$call  = strpos( $this->code, 'Option_Center::save_settings(' );
		$this->assertNotFalse( $guard );
		$this->assertNotFalse( $call );
		$this->assertLessThan( $call, $guard );
	}

	/**
	 * Instant Indexing is not in save_settings()'s $map, so it must be routed
	 * away before the guard.
	 */
	public function test_instant_indexing_is_routed_separately(): void {
		$route = strpos( $this->code, "if ( 'instant_indexing' === \$option_type )" );
		$call  = strpos( $this->code, 'Option_Center::save_settings(' );
		$this->assertNotFalse( $route );
		$this->assertLessThan( $call, $route );
	}

	/**
	 * An ability must not leak unrelated settings from the same option blob.
	 */
	public function test_result_returns_only_touched_fields(): void {
		$this->assertStringContainsString( 'foreach ( array_keys( $validated ) as $id )', $this->code );
	}

	/**
	 * Only the field types for the fields actually being written are sent.
	 */
	public function test_field_types_are_narrowed_to_the_payload(): void {
		$this->assertStringContainsString( 'array_intersect_key( $all_types, $validated )', $this->code );
	}
}

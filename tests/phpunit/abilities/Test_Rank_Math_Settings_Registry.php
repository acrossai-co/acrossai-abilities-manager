<?php
/**
 * Feature 069 — behavioural tests for Settings_Registry.
 *
 * These are the highest-value tests in the feature. The registry is pure logic,
 * so it is tested for real rather than by source inspection, and the type-mapping
 * assertions below are the regression guard for research F2 — the failure mode
 * where a legacy CMB2 type is passed through verbatim and Rank Math's sanitizer
 * silently strips newlines out of a multi-line setting.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Settings_Registry;
use WP_Error;
use WP_UnitTestCase;

class Test_Rank_Math_Settings_Registry extends WP_UnitTestCase {

	// -----------------------------------------------------------------
	// Research F2 — the type-mapping regression guard.
	// -----------------------------------------------------------------

	/**
	 * textarea_small has NO case in Sanitize_Settings::sanitize_field(), so
	 * emitting it verbatim falls through to the default branch and
	 * sanitize_text_field() collapses newlines. It must map to 'textarea'.
	 *
	 * @dataProvider provide_unprotected_multiline_fields
	 */
	public function test_multiline_fields_emit_textarea( string $panel, string $object, string $field ): void {
		$types = Settings_Registry::field_types_for( $panel, $object );
		$this->assertArrayHasKey( $field, $types, "{$field} missing from {$panel}" );
		$this->assertSame( 'textarea', $types[ $field ], "{$field} must emit 'textarea', never 'textarea_small'" );
	}

	/**
	 * The genuine data-loss set from research F2c: multi-line fields typed
	 * textarea_small with no sanitize_by_field_id() override.
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function provide_unprotected_multiline_fields(): array {
		return array(
			'nofollow_domains'          => array( 'general-links', '', 'nofollow_domains' ),
			'nofollow_exclude_domains'  => array( 'general-links', '', 'nofollow_exclude_domains' ),
			'rss_before_content'        => array( 'general-others', '', 'rss_before_content' ),
			'rss_after_content'         => array( 'general-others', '', 'rss_after_content' ),
			'social_additional_profiles' => array( 'titles-social', '', 'social_additional_profiles' ),
			'organization_description'  => array( 'titles-local-seo', '', 'organization_description' ),
			'local_address_format'      => array( 'titles-local-seo', '', 'local_address_format' ),
			'post image customfields'   => array( 'sitemap-post-type', 'post', 'pt_post_image_customfields' ),
			'post description'          => array( 'titles-post-type', 'post', 'pt_post_description' ),
			'category description'      => array( 'titles-taxonomy', 'category', 'tax_category_description' ),
		);
	}

	/**
	 * No emitted type may be a legacy CMB2 name — only the sanitizer's own
	 * vocabulary is allowed, or Rank Math falls through to the lossy default.
	 */
	public function test_no_panel_emits_a_legacy_type(): void {
		$allowed = array( 'text', 'textarea', 'toggle', 'number', 'select', 'checkboxlist', 'file', 'group' );
		foreach ( Settings_Registry::panel_slugs() as $panel ) {
			$object = self::object_for( $panel );
			foreach ( Settings_Registry::field_types_for( $panel, $object ) as $field => $type ) {
				$this->assertContains( $type, $allowed, "{$panel}.{$field} emits non-sanitizer type '{$type}'" );
			}
		}
	}

	// -----------------------------------------------------------------
	// Read-only and denied fields.
	// -----------------------------------------------------------------

	/**
	 * notice / raw fields render information; they are readable but never
	 * writable, so they must not appear in the write type map.
	 */
	public function test_display_only_fields_are_not_writable(): void {
		$this->assertTrue( Settings_Registry::is_readonly_type( 'notice' ) );
		$this->assertTrue( Settings_Registry::is_readonly_type( 'raw' ) );
		$this->assertNull( Settings_Registry::emitted_type( 'notice' ) );
		$this->assertNull( Settings_Registry::emitted_type( 'raw' ) );

		$types = Settings_Registry::field_types_for( 'general-robots-txt' );
		$this->assertArrayHasKey( 'robots_txt_content', $types );
		foreach ( array( 'edit_disabled', 'robots_locked', 'site_not_public', 'robots_tester' ) as $field ) {
			$this->assertArrayNotHasKey( $field, $types );
		}
	}

	public function test_readonly_field_write_is_rejected(): void {
		$result = Settings_Registry::validate( 'general-robots-txt', '', array( 'robots_locked' => 'x' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
		$this->assertStringContainsString( 'read-only', $result->get_error_message() );
	}

	/**
	 * indexnow_api_key_location is computed by Api::get_key_location() from
	 * home_url() and never stored, so it must be read-only.
	 */
	public function test_derived_indexnow_key_location_is_readonly(): void {
		$types = Settings_Registry::field_types_for( 'general-instant-indexing' );
		$this->assertArrayHasKey( 'indexnow_api_key', $types );
		$this->assertArrayNotHasKey( 'indexnow_api_key_location', $types );
	}

	public function test_denied_keys_are_rejected(): void {
		foreach ( Settings_Registry::DENIED_KEYS as $key ) {
			$this->assertTrue( Settings_Registry::is_denied( $key ) );
		}
		$result = Settings_Registry::validate( 'general-others', '', array( 'usage_tracking' => 'on' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'protected_field', $result->get_error_code() );
	}

	/**
	 * htaccess is out of scope by product decision, and submitting the key has
	 * side effects even though save_settings() discards the value.
	 */
	public function test_htaccess_keys_are_denied(): void {
		$this->assertTrue( Settings_Registry::is_denied( 'htaccess_content' ) );
		$this->assertTrue( Settings_Registry::is_denied( 'htaccess_allow_editing' ) );
	}

	// -----------------------------------------------------------------
	// Unknown fields — FR-007, reject the whole write.
	// -----------------------------------------------------------------

	public function test_unknown_field_is_rejected_and_named(): void {
		$result = Settings_Registry::validate( 'general-links', '', array( 'breadcrumbs_home' => 'on' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'unknown_field', $result->get_error_code() );
		$this->assertStringContainsString( 'breadcrumbs_home', $result->get_error_message() );
		$this->assertStringContainsString( 'general-links', $result->get_error_message() );
	}

	/**
	 * One bad field must reject the entire payload, not apply the good half.
	 */
	public function test_partial_payload_is_not_applied(): void {
		$result = Settings_Registry::validate(
			'general-links',
			'',
			array(
				'nofollow_external_links' => true,
				'not_a_real_field'        => 'x',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'unknown_field', $result->get_error_code() );
	}

	public function test_unknown_panel_is_rejected(): void {
		$result = Settings_Registry::validate( 'no-such-panel', '', array( 'x' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
	}

	public function test_dynamic_panel_requires_an_object(): void {
		$result = Settings_Registry::validate( 'titles-post-type', '', array( 'pt_post_title' => 'x' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'requires an object', $result->get_error_message() );
	}

	public function test_empty_payload_is_rejected(): void {
		$this->assertInstanceOf( WP_Error::class, Settings_Registry::validate( 'general-links', '', array() ) );
	}

	// -----------------------------------------------------------------
	// Value normalisation.
	// -----------------------------------------------------------------

	public function test_toggle_normalisation(): void {
		$on = Settings_Registry::validate( 'general-links', '', array( 'nofollow_external_links' => true ) );
		$this->assertSame( array( 'nofollow_external_links' => 'on' ), $on );

		$off = Settings_Registry::validate( 'general-links', '', array( 'nofollow_external_links' => false ) );
		$this->assertSame( array( 'nofollow_external_links' => 'off' ), $off );

		$passthrough = Settings_Registry::validate( 'general-links', '', array( 'nofollow_external_links' => 'off' ) );
		$this->assertSame( array( 'nofollow_external_links' => 'off' ), $passthrough );

		$this->assertInstanceOf(
			WP_Error::class,
			Settings_Registry::validate( 'general-links', '', array( 'nofollow_external_links' => 'yes' ) )
		);
	}

	public function test_select_enum_is_enforced(): void {
		$ok = Settings_Registry::validate( 'general-404-monitor', '', array( '404_monitor_mode' => 'advanced' ) );
		$this->assertSame( array( '404_monitor_mode' => 'advanced' ), $ok );

		$bad = Settings_Registry::validate( 'general-404-monitor', '', array( '404_monitor_mode' => 'turbo' ) );
		$this->assertInstanceOf( WP_Error::class, $bad );
		$this->assertStringContainsString( 'simple, advanced', $bad->get_error_message() );
	}

	public function test_checkboxlist_enum_is_enforced_per_item(): void {
		$ok = Settings_Registry::validate( 'titles-global', '', array( 'robots_global' => array( 'index', 'nofollow' ) ) );
		$this->assertSame( array( 'robots_global' => array( 'index', 'nofollow' ) ), $ok );

		$bad = Settings_Registry::validate( 'titles-global', '', array( 'robots_global' => array( 'index', 'bogus' ) ) );
		$this->assertInstanceOf( WP_Error::class, $bad );
		$this->assertStringContainsString( 'bogus', $bad->get_error_message() );
	}

	public function test_checkboxlist_rejects_a_scalar(): void {
		$this->assertInstanceOf(
			WP_Error::class,
			Settings_Registry::validate( 'titles-global', '', array( 'robots_global' => 'index' ) )
		);
	}

	/**
	 * A 'text'-typed count field still gets range-checked, but is returned as a
	 * string so storage stays byte-identical to what the admin UI writes.
	 */
	public function test_bounded_text_field_is_range_checked_but_stays_a_string(): void {
		$ok = Settings_Registry::validate( 'sitemap-general', '', array( 'items_per_page' => 500 ) );
		$this->assertSame( array( 'items_per_page' => '500' ), $ok );

		$this->assertInstanceOf(
			WP_Error::class,
			Settings_Registry::validate( 'sitemap-general', '', array( 'items_per_page' => 0 ) )
		);
		$this->assertInstanceOf(
			WP_Error::class,
			Settings_Registry::validate( 'sitemap-general', '', array( 'items_per_page' => 99999 ) )
		);
		$this->assertInstanceOf(
			WP_Error::class,
			Settings_Registry::validate( 'sitemap-general', '', array( 'items_per_page' => 'lots' ) )
		);
	}

	public function test_multiline_value_passes_through_unmangled(): void {
		$value  = "example.com\nexample.org\nexample.net";
		$result = Settings_Registry::validate( 'general-links', '', array( 'nofollow_domains' => $value ) );
		$this->assertSame( $value, $result['nofollow_domains'] );
	}

	// -----------------------------------------------------------------
	// Repeatable groups — the sanitize_group_value() list-detection trap.
	// -----------------------------------------------------------------

	/**
	 * Sanitize_Settings::sanitize_group_value() decides repeatable-vs-single by
	 * array_keys($v) === range(0, count($v) - 1). A gapped or string-keyed
	 * payload is treated as ONE group and collapses every row into the first, so
	 * the re-index is mandatory.
	 */
	public function test_group_rows_are_reindexed_sequentially(): void {
		$result = Settings_Registry::validate(
			'general-404-monitor',
			'',
			array(
				'404_monitor_exclude' => array(
					3 => array( 'exclude' => '/a', 'comparison' => 'exact' ),
					7 => array( 'exclude' => '/b', 'comparison' => 'contains' ),
				),
			)
		);
		$this->assertIsArray( $result );
		$rows = $result['404_monitor_exclude'];
		$this->assertSame( array( 0, 1 ), array_keys( $rows ) );
		$this->assertSame( '/a', $rows[0]['exclude'] );
		$this->assertSame( '/b', $rows[1]['exclude'] );
	}

	public function test_group_subfield_enum_is_enforced(): void {
		$bad = Settings_Registry::validate(
			'general-404-monitor',
			'',
			array( '404_monitor_exclude' => array( array( 'exclude' => '/a', 'comparison' => 'fuzzy' ) ) )
		);
		$this->assertInstanceOf( WP_Error::class, $bad );
		$this->assertStringContainsString( 'exact, contains, start, end, regex', $bad->get_error_message() );
	}

	public function test_group_rejects_unknown_subfield(): void {
		$bad = Settings_Registry::validate(
			'general-404-monitor',
			'',
			array( '404_monitor_exclude' => array( array( 'exclude' => '/a', 'nope' => 1 ) ) )
		);
		$this->assertInstanceOf( WP_Error::class, $bad );
		$this->assertSame( 'unknown_field', $bad->get_error_code() );
	}

	/**
	 * Local_Seo::get_opening_hours() keys rows by 'time' and skips any row whose
	 * time is empty, so an empty value would silently drop the row.
	 */
	public function test_opening_hours_requires_a_time(): void {
		$bad = Settings_Registry::validate(
			'titles-local-seo',
			'',
			array( 'opening_hours' => array( array( 'day' => 'Monday', 'time' => '' ) ) )
		);
		$this->assertInstanceOf( WP_Error::class, $bad );
		$this->assertStringContainsString( 'required', $bad->get_error_message() );
	}

	public function test_opening_hours_enforces_time_format(): void {
		$bad = Settings_Registry::validate(
			'titles-local-seo',
			'',
			array( 'opening_hours' => array( array( 'day' => 'Monday', 'time' => '9am-5pm' ) ) )
		);
		$this->assertInstanceOf( WP_Error::class, $bad );

		$ok = Settings_Registry::validate(
			'titles-local-seo',
			'',
			array( 'opening_hours' => array( array( 'day' => 'Monday', 'time' => '09:00-17:00' ) ) )
		);
		$this->assertIsArray( $ok );
		$this->assertSame( '09:00-17:00', $ok['opening_hours'][0]['time'] );
	}

	public function test_opening_hours_rejects_invalid_day(): void {
		$bad = Settings_Registry::validate(
			'titles-local-seo',
			'',
			array( 'opening_hours' => array( array( 'day' => 'Funday', 'time' => '09:00-17:00' ) ) )
		);
		$this->assertInstanceOf( WP_Error::class, $bad );
	}

	// -----------------------------------------------------------------
	// Panel table integrity.
	// -----------------------------------------------------------------

	public function test_all_twenty_panels_are_declared(): void {
		// 9 general + 8 titles + 3 sitemap. The planning docs originally said 19,
		// which was an arithmetic slip; 20 is the true count.
		$this->assertCount( 20, Settings_Registry::panel_slugs() );
	}

	/**
	 * Every panel must cite the Rank Math file it mirrors — quickstart.md's
	 * re-diff procedure depends on it.
	 */
	public function test_every_panel_cites_its_source(): void {
		foreach ( Settings_Registry::panels() as $slug => $panel ) {
			$this->assertNotEmpty( $panel['source'] ?? '', "{$slug} has no source citation" );
			$this->assertStringStartsWith( 'includes/', $panel['source'], "{$slug} source is not a Rank Math path" );
		}
	}

	public function test_every_panel_declares_option_type_and_cap(): void {
		$option_types = array( 'general', 'titles', 'sitemap', 'instant_indexing' );
		$caps         = array( 'general', 'titles', 'sitemap' );
		foreach ( Settings_Registry::panels() as $slug => $panel ) {
			$this->assertContains( $panel['option_type'], $option_types, "{$slug} option_type" );
			$this->assertContains( $panel['cap'], $caps, "{$slug} cap" );
			$this->assertArrayHasKey( 'dynamic', $panel, "{$slug} dynamic" );
			$this->assertNotEmpty( $panel['fields'], "{$slug} has no fields" );
		}
	}

	/**
	 * Dynamic panels must substitute the object token, and must leave no
	 * unresolved placeholder behind.
	 */
	public function test_dynamic_panels_substitute_the_object_token(): void {
		$fields = Settings_Registry::fields_for( 'titles-post-type', 'product' );
		$this->assertArrayHasKey( 'pt_product_title', $fields );
		foreach ( array_keys( $fields ) as $id ) {
			$this->assertStringNotContainsString( Settings_Registry::OBJECT_TOKEN, (string) $id );
		}

		$tax = Settings_Registry::fields_for( 'titles-taxonomy', 'product_cat' );
		$this->assertArrayHasKey( 'tax_product_cat_title', $tax );
		$this->assertArrayHasKey( 'remove_product_cat_snippet_data', $tax );
	}

	public function test_static_panels_have_no_object_token(): void {
		foreach ( Settings_Registry::panels() as $slug => $panel ) {
			if ( null !== $panel['dynamic'] ) {
				continue;
			}
			foreach ( array_keys( $panel['fields'] ) as $id ) {
				$this->assertStringNotContainsString( Settings_Registry::OBJECT_TOKEN, (string) $id, "{$slug}.{$id}" );
			}
		}
	}

	/**
	 * No denied key may also be declared as a writable field — that would be a
	 * contradiction between the two tables.
	 */
	public function test_no_denied_key_is_declared_writable(): void {
		foreach ( Settings_Registry::panel_slugs() as $panel ) {
			$types = Settings_Registry::field_types_for( $panel, self::object_for( $panel ) );
			foreach ( array_keys( $types ) as $field ) {
				$this->assertFalse( Settings_Registry::is_denied( (string) $field ), "{$panel}.{$field} is both writable and denied" );
			}
		}
	}

	/**
	 * A representative object name for dynamic panels.
	 */
	private static function object_for( string $panel ): string {
		$definition = Settings_Registry::panel( $panel );
		if ( null === $definition || null === $definition['dynamic'] ) {
			return '';
		}
		return 'taxonomy' === $definition['dynamic'] ? 'category' : 'post';
	}
}

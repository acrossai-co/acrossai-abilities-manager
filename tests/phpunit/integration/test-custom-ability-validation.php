<?php
/**
 * Unit Tests for Custom Ability Validator and Sanitizer
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Tests
 * @since 1.0.0
 */

// Require test bootstrap
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/vendor/autoload.php';

/**
 * Test_Custom_Ability_Validation class
 *
 * Tests for Validator and Sanitizer utility classes.
 *
 * @since 1.0.0
 */
class Test_Custom_Ability_Validation extends WP_UnitTestCase {

	// =========================================
	// VALIDATOR TESTS
	// =========================================

	/**
	 * Test validate_slug with valid pattern
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_slug_valid_pattern() {
		$result = AcrossAI_Custom_Ability_Validator::validate_slug( 'custom/my-ability' );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_slug with invalid pattern
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_slug_invalid_pattern() {
		$result = AcrossAI_Custom_Ability_Validator::validate_slug( 'invalid_slug_no_slash' );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_slug_pattern', $result->get_error_code() );
	}

	/**
	 * Test validate_slug with uppercase letters
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_slug_uppercase_rejected() {
		$result = AcrossAI_Custom_Ability_Validator::validate_slug( 'Custom/MyAbility' );
		$this->assertWPError( $result );
	}

	/**
	 * Test validate_slug with empty value
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_slug_empty() {
		$result = AcrossAI_Custom_Ability_Validator::validate_slug( '' );
		$this->assertWPError( $result );
		$this->assertSame( 'empty_slug', $result->get_error_code() );
	}

	/**
	 * Test validate_slug too long
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_slug_too_long() {
		$long_slug = 'custom/' . str_repeat( 'a', 250 );
		$result = AcrossAI_Custom_Ability_Validator::validate_slug( $long_slug );
		$this->assertWPError( $result );
		$this->assertSame( 'slug_too_long', $result->get_error_code() );
	}

	/**
	 * Test validate_label valid
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_label_valid() {
		$result = AcrossAI_Custom_Ability_Validator::validate_label( 'My Custom Ability' );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_label empty
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_label_empty() {
		$result = AcrossAI_Custom_Ability_Validator::validate_label( '' );
		$this->assertWPError( $result );
	}

	/**
	 * Test validate_callback_config for noop type
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_callback_config_noop() {
		$result = AcrossAI_Custom_Ability_Validator::validate_callback_config( 'noop', [] );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_callback_config for filter_hook with valid hook name
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_callback_config_filter_hook_valid() {
		$config = [ 'hook_name' => 'my_filter_hook' ];
		$result = AcrossAI_Custom_Ability_Validator::validate_callback_config( 'filter_hook', $config );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_callback_config for filter_hook with missing hook name
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_callback_config_filter_hook_missing_name() {
		$result = AcrossAI_Custom_Ability_Validator::validate_callback_config( 'filter_hook', [] );
		$this->assertWPError( $result );
		$this->assertSame( 'missing_hook_name', $result->get_error_code() );
	}

	/**
	 * Test validate_callback_config for wp_remote_post with valid URL
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_callback_config_remote_post_valid() {
		$config = [
			'url'     => 'https://example.com/api/endpoint',
			'method'  => 'POST',
			'timeout' => 30,
		];
		$result = AcrossAI_Custom_Ability_Validator::validate_callback_config( 'wp_remote_post', $config );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_callback_config for wp_remote_post with invalid URL
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_callback_config_remote_post_invalid_url() {
		$config = [
			'url'     => 'not-a-valid-url',
			'method'  => 'POST',
		];
		$result = AcrossAI_Custom_Ability_Validator::validate_callback_config( 'wp_remote_post', $config );
		$this->assertWPError( $result );
	}

	/**
	 * Test validate_callback_config for wp_remote_post with invalid method
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_callback_config_remote_post_invalid_method() {
		$config = [
			'url'    => 'https://example.com/api',
			'method' => 'DELETE',
		];
		$result = AcrossAI_Custom_Ability_Validator::validate_callback_config( 'wp_remote_post', $config );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_method', $result->get_error_code() );
	}

	/**
	 * Test validate_permission_config for always_allow
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_permission_config_always_allow() {
		$result = AcrossAI_Custom_Ability_Validator::validate_permission_config( 'always_allow', [] );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_permission_config for logged_in
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_permission_config_logged_in() {
		$result = AcrossAI_Custom_Ability_Validator::validate_permission_config( 'logged_in', [] );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_permission_config for capability with valid capability
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_permission_config_capability_valid() {
		// 'manage_options' is a core WordPress capability
		$config = [ 'capability' => 'manage_options' ];
		$result = AcrossAI_Custom_Ability_Validator::validate_permission_config( 'capability', $config );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_permission_config for capability with non-existent capability
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_permission_config_capability_not_found() {
		$config = [ 'capability' => 'non_existent_capability_xyz' ];
		$result = AcrossAI_Custom_Ability_Validator::validate_permission_config( 'capability', $config );
		$this->assertWPError( $result );
		$this->assertSame( 'capability_not_found', $result->get_error_code() );
	}

	/**
	 * Test validate_schema with valid JSON
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_schema_valid_json() {
		$schema = wp_json_encode( [ 'type' => 'object', 'properties' => [] ] );
		$result = AcrossAI_Custom_Ability_Validator::validate_schema( $schema );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_schema with invalid JSON
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_schema_invalid_json() {
		$result = AcrossAI_Custom_Ability_Validator::validate_schema( '{invalid json}' );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_json', $result->get_error_code() );
	}

	/**
	 * Test validate_schema with empty value
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_validate_schema_empty() {
		$result = AcrossAI_Custom_Ability_Validator::validate_schema( '' );
		$this->assertTrue( $result ); // Empty is valid
	}

	// =========================================
	// SANITIZER TESTS
	// =========================================

	/**
	 * Test sanitize_ability_slug
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_sanitize_ability_slug() {
		$result = AcrossAI_Custom_Ability_Sanitizer::sanitize_ability_slug( 'Custom/My Ability!' );
		$this->assertStringContainsString( 'custom/', $result );
	}

	/**
	 * Test sanitize_label
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_sanitize_label() {
		$result = AcrossAI_Custom_Ability_Sanitizer::sanitize_label( '<script>alert("xss")</script>Label' );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * Test sanitize_description allows safe HTML
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_sanitize_description_safe_html() {
		$result = AcrossAI_Custom_Ability_Sanitizer::sanitize_description( '<p>Safe paragraph</p>' );
		$this->assertStringContainsString( '<p>', $result );
	}

	/**
	 * Test sanitize_description removes script tags
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_sanitize_description_removes_scripts() {
		$result = AcrossAI_Custom_Ability_Sanitizer::sanitize_description( '<script>alert("xss")</script>Text' );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * Test sanitize_callback_config for filter_hook
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_sanitize_callback_config_filter_hook() {
		$config = [ 'hook_name' => 'my_filter_hook' ];
		$result = AcrossAI_Custom_Ability_Sanitizer::sanitize_callback_config( 'filter_hook', $config );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'hook_name', $result );
	}

	/**
	 * Test sanitize_permission_config for capability
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_sanitize_permission_config_capability() {
		$config = [ 'capability' => 'manage_options' ];
		$result = AcrossAI_Custom_Ability_Sanitizer::sanitize_permission_config( 'capability', $config );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'capability', $result );
	}

	/**
	 * Test sanitize_schema with valid JSON
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_sanitize_schema_valid() {
		$input = wp_json_encode( [ 'type' => 'object' ] );
		$result = AcrossAI_Custom_Ability_Sanitizer::sanitize_schema( $input );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test sanitize_schema with empty value
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_sanitize_schema_empty() {
		$result = AcrossAI_Custom_Ability_Sanitizer::sanitize_schema( '' );
		$this->assertNull( $result );
	}

	/**
	 * Test sanitize_schema with invalid JSON
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_sanitize_schema_invalid_json() {
		$result = AcrossAI_Custom_Ability_Sanitizer::sanitize_schema( '{bad json}' );
		$this->assertNull( $result );
	}

	/**
	 * Test cast_to_db_format converts bool to int
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_cast_to_db_format_bool_to_int() {
		$fields = [
			'ability_slug'    => 'test/slug',
			'label'           => 'Test',
			'enabled'         => true,
			'show_in_rest'    => false,
			'callback_type'   => 'noop',
			'permission_type' => 'always_allow',
		];
		$result = AcrossAI_Custom_Ability_Sanitizer::cast_to_db_format( $fields );
		$this->assertSame( 1, $result['enabled'] );
		$this->assertSame( 0, $result['show_in_rest'] );
	}

	/**
	 * Test cast_to_db_format converts arrays to JSON
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_cast_to_db_format_arrays_to_json() {
		$fields = [
			'callback_config'   => [ 'hook_name' => 'my_hook' ],
			'permission_config' => [ 'capability' => 'manage_options' ],
		];
		$result = AcrossAI_Custom_Ability_Sanitizer::cast_to_db_format( $fields );
		$this->assertIsString( $result['callback_config'] );
		$this->assertIsString( $result['permission_config'] );
	}
}

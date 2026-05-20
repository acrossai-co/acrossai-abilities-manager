<?php
/**
 * Custom Ability Validator Utility
 *
 * Static validation methods for custom ability fields.
 * 
 * @package AcrossAI_Abilities_Manager
 * @subpackage Utilities
 * @since 1.0.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Validator class
 *
 * Static utility: Validates custom ability field data.
 * 
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Validator {

	/**
	 * Validate ability slug
	 *
	 * @since 1.0.0
	 * @param string $slug Ability slug to validate
	 * @return true|WP_Error True if valid, WP_Error otherwise
	 */
	public static function validate_slug( $slug ) {
		// TODO: Implement slug validation (T012)
		// Pattern: ^[a-z0-9]+/[a-z0-9-]+$
		// Check: uniqueness, max 255 chars
		return true;
	}

	/**
	 * Validate ability label
	 *
	 * @since 1.0.0
	 * @param string $label Ability label to validate
	 * @return true|WP_Error True if valid, WP_Error otherwise
	 */
	public static function validate_label( $label ) {
		// TODO: Implement label validation (T012)
		// Check: non-empty, max 255 chars
		return true;
	}

	/**
	 * Validate callback configuration
	 *
	 * @since 1.0.0
	 * @param string $type Callback type
	 * @param array  $config Callback configuration
	 * @return true|WP_Error True if valid, WP_Error otherwise
	 */
	public static function validate_callback_config( $type, $config ) {
		// TODO: Implement callback config validation (T012)
		// Type-specific rules: noop, filter_hook, wp_remote_post
		return true;
	}

	/**
	 * Validate permission configuration
	 *
	 * @since 1.0.0
	 * @param string $type Permission type
	 * @param array  $config Permission configuration
	 * @return true|WP_Error True if valid, WP_Error otherwise
	 */
	public static function validate_permission_config( $type, $config ) {
		// TODO: Implement permission config validation (T012)
		// Type-specific rules: always_allow, logged_in, capability
		return true;
	}

	/**
	 * Validate JSON schema
	 *
	 * @since 1.0.0
	 * @param string $schema JSON schema to validate
	 * @return true|WP_Error True if valid, WP_Error otherwise
	 */
	public static function validate_schema( $schema ) {
		// TODO: Implement schema validation (T012)
		// Check: JSON syntax, depth limit (max 10 levels), size limit (max 64KB)
		return true;
	}

	/**
	 * Aggregate validation for all ability fields
	 *
	 * @since 1.0.0
	 * @param array $fields All ability fields
	 * @return true|WP_Error True if valid, WP_Error otherwise
	 */
	public static function validate_ability( $fields ) {
		// TODO: Implement aggregate validation (T012)
		// Validate all fields together before save
		return true;
	}
}

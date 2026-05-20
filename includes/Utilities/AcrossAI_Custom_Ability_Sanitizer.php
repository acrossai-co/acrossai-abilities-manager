<?php
/**
 * Custom Ability Sanitizer Utility
 *
 * Static sanitization methods for custom ability fields.
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
 * AcrossAI_Custom_Ability_Sanitizer class
 *
 * Static utility: Sanitizes custom ability field data.
 * 
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Sanitizer {

	/**
	 * Sanitize ability slug
	 *
	 * @since 1.0.0
	 * @param string $slug Ability slug to sanitize
	 * @return string Sanitized slug
	 */
	public static function sanitize_ability_slug( $slug ) {
		// TODO: Implement slug sanitization (T012)
		// lowercase, remove invalid chars, sanitize_title_with_dashes()
		return sanitize_title_with_dashes( strtolower( $slug ) );
	}

	/**
	 * Sanitize ability label
	 *
	 * @since 1.0.0
	 * @param string $label Ability label to sanitize
	 * @return string Sanitized label
	 */
	public static function sanitize_label( $label ) {
		// TODO: Implement label sanitization (T012)
		return sanitize_text_field( $label );
	}

	/**
	 * Sanitize ability description
	 *
	 * @since 1.0.0
	 * @param string $description Ability description to sanitize
	 * @return string Sanitized description
	 */
	public static function sanitize_description( $description ) {
		// TODO: Implement description sanitization (T012)
		return wp_kses_post( $description );
	}

	/**
	 * Sanitize callback configuration
	 *
	 * @since 1.0.0
	 * @param string $type Callback type
	 * @param array  $config Callback configuration
	 * @return array Sanitized config
	 */
	public static function sanitize_callback_config( $type, $config ) {
		// TODO: Implement callback config sanitization (T012)
		return (array) $config;
	}

	/**
	 * Sanitize permission configuration
	 *
	 * @since 1.0.0
	 * @param string $type Permission type
	 * @param array  $config Permission configuration
	 * @return array Sanitized config
	 */
	public static function sanitize_permission_config( $type, $config ) {
		// TODO: Implement permission config sanitization (T012)
		return (array) $config;
	}

	/**
	 * Sanitize JSON schema
	 *
	 * @since 1.0.0
	 * @param string $schema JSON schema to sanitize
	 * @return string Sanitized schema
	 */
	public static function sanitize_schema( $schema ) {
		// TODO: Implement schema sanitization (T012)
		// Validate JSON, re-encode with wp_json_encode() to normalize
		if ( empty( $schema ) ) {
			return null;
		}
		$decoded = json_decode( $schema, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}
		return wp_json_encode( $decoded );
	}

	/**
	 * Cast fields to database format
	 *
	 * @since 1.0.0
	 * @param array $fields Fields to cast
	 * @return array Cast fields (bool->int, json->string)
	 */
	public static function cast_to_db_format( $fields ) {
		// TODO: Implement casting logic (T012)
		// bool->int, json->string, prepare for BerlinDB save
		$cast = array();
		foreach ( $fields as $key => $value ) {
			if ( is_bool( $value ) ) {
				$cast[ $key ] = (int) $value;
			} elseif ( is_array( $value ) || is_object( $value ) ) {
				$cast[ $key ] = wp_json_encode( $value );
			} else {
				$cast[ $key ] = $value;
			}
		}
		return $cast;
	}
}

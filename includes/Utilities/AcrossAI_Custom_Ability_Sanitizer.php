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
	 * Sanitize a complete ability payload.
	 *
	 * @since 1.0.0
	 * @param array $fields Raw ability fields.
	 * @return array Sanitized ability fields.
	 */
	public static function sanitize_ability( $fields ) {
		$fields = is_array( $fields ) ? $fields : array();
		$sanitized = array();

		if ( array_key_exists( 'ability_slug', $fields ) ) {
			$sanitized['ability_slug'] = self::sanitize_ability_slug( $fields['ability_slug'] );
		}

		if ( array_key_exists( 'label', $fields ) ) {
			$sanitized['label'] = self::sanitize_label( $fields['label'] );
		}

		if ( array_key_exists( 'description', $fields ) ) {
			$sanitized['description'] = self::sanitize_description( $fields['description'] );
		}

		if ( array_key_exists( 'enabled', $fields ) ) {
			$sanitized['enabled'] = rest_sanitize_boolean( $fields['enabled'] );
		}

		if ( array_key_exists( 'callback_type', $fields ) ) {
			$sanitized['callback_type'] = sanitize_key( (string) $fields['callback_type'] );
		}

		if ( array_key_exists( 'callback_config', $fields ) ) {
			$callback_type = isset( $sanitized['callback_type'] ) ? $sanitized['callback_type'] : '';
			$sanitized['callback_config'] = self::sanitize_callback_config( $callback_type, $fields['callback_config'] );
		}

		if ( array_key_exists( 'input_schema', $fields ) ) {
			$sanitized['input_schema'] = self::sanitize_schema( $fields['input_schema'] );
		}

		if ( array_key_exists( 'output_schema', $fields ) ) {
			$sanitized['output_schema'] = self::sanitize_schema( $fields['output_schema'] );
		}

		if ( array_key_exists( 'show_in_rest', $fields ) ) {
			$sanitized['show_in_rest'] = rest_sanitize_boolean( $fields['show_in_rest'] );
		}

		if ( array_key_exists( 'show_in_mcp', $fields ) ) {
			$sanitized['show_in_mcp'] = rest_sanitize_boolean( $fields['show_in_mcp'] );
		}

		if ( array_key_exists( 'mcp_type', $fields ) ) {
			$sanitized['mcp_type'] = sanitize_key( (string) $fields['mcp_type'] );
		}

		foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $tri_state_key ) {
			if ( array_key_exists( $tri_state_key, $fields ) ) {
				$value = $fields[ $tri_state_key ];
				$sanitized[ $tri_state_key ] = ( null === $value || 'null' === $value || '' === $value )
					? null
					: (int) $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize ability slug
	 *
	 * @since 1.0.0
	 * @param string $slug Ability slug to sanitize
	 * @return string Sanitized slug
	 */
	public static function sanitize_ability_slug( $slug ) {
		$slug = strtolower( (string) $slug );
		$parts = explode( '/', $slug, 2 );

		if ( 2 !== count( $parts ) ) {
			return sanitize_title_with_dashes( $slug );
		}

		return sanitize_title_with_dashes( $parts[0] ) . '/' . sanitize_title_with_dashes( $parts[1] );
	}

	/**
	 * Sanitize ability label
	 *
	 * @since 1.0.0
	 * @param string $label Ability label to sanitize
	 * @return string Sanitized label
	 */
	public static function sanitize_label( $label ) {
		return sanitize_text_field( (string) $label );
	}

	/**
	 * Sanitize ability description
	 *
	 * @since 1.0.0
	 * @param string $description Ability description to sanitize
	 * @return string Sanitized description
	 */
	public static function sanitize_description( $description ) {
		return wp_kses_post( (string) $description );
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
		unset( $type );
		return self::sanitize_recursive( (array) $config );
	}

	/**
	 * Sanitize JSON schema
	 *
	 * @since 1.0.0
	 * @param string|array|null $schema JSON schema to sanitize
	 * @return string|null Sanitized schema
	 */
	public static function sanitize_schema( $schema ) {
		if ( null === $schema || '' === $schema ) {
			return null;
		}

		if ( is_array( $schema ) ) {
			return wp_json_encode( $schema );
		}

		$decoded = json_decode( (string) $schema, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
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

	/**
	 * Recursively sanitize array/object scalar values.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value to sanitize.
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_recursive( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $nested ) {
				$value[ $key ] = self::sanitize_recursive( $nested );
			}
			return $value;
		}

		if ( is_object( $value ) ) {
			return self::sanitize_recursive( (array) $value );
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		if ( is_bool( $value ) || is_numeric( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}
}

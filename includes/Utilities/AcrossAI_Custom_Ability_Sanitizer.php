<?php
/**
 * Custom Ability Sanitization Utility
 *
 * Static utility for sanitizing custom ability fields before validation and save.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Utilities
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

/**
 * Class AcrossAI_Custom_Ability_Sanitizer
 *
 * Static utility class for comprehensive field sanitization (Memory DEC-UTILITY-STATIC-ONLY).
 * Sanitizes all input fields before validation per Constitution §IV (input sanitization).
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Sanitizer {

	/**
	 * Sanitize ability slug.
	 *
	 * Lowercase, remove invalid chars, ensure format: namespace/name
	 *
	 * @since 0.0.1
	 * @param string $slug Raw ability slug.
	 * @return string Sanitized ability slug.
	 */
	public static function sanitize_ability_slug( $slug ) {
		// Convert to lowercase
		$slug = strtolower( $slug );

		// Apply WordPress sanitization for title-like strings
		$slug = sanitize_title_with_dashes( $slug );

		// Replace dashes with underscores where appropriate, but preserve single slash
		// Pattern: namespace/name
		$parts = explode( '/', $slug );

		if ( count( $parts ) === 2 ) {
			$parts[0] = sanitize_key( $parts[0] );
			$parts[1] = sanitize_key( $parts[1] );
			$slug = implode( '/', $parts );
		} else {
			$slug = sanitize_key( $slug );
		}

		return $slug;
	}

	/**
	 * Sanitize label.
	 *
	 * @since 0.0.1
	 * @param string $label Raw label.
	 * @return string Sanitized label.
	 */
	public static function sanitize_label( $label ) {
		return sanitize_text_field( $label );
	}

	/**
	 * Sanitize description.
	 *
	 * Allow limited HTML as per wp_kses_post (for markdown-like formatting support).
	 *
	 * @since 0.0.1
	 * @param string $description Raw description.
	 * @return string Sanitized description.
	 */
	public static function sanitize_description( $description ) {
		return wp_kses_post( $description );
	}

	/**
	 * Sanitize category.
	 *
	 * @since 0.0.1
	 * @param string $category Raw category.
	 * @return string Sanitized category.
	 */
	public static function sanitize_category( $category ) {
		return sanitize_text_field( $category );
	}

	/**
	 * Sanitize callback configuration (type-specific).
	 *
	 * @since 0.0.1
	 * @param string $callback_type Callback type (noop, filter_hook, wp_remote_post).
	 * @param mixed  $callback_config Raw callback config.
	 * @return array Sanitized callback config.
	 */
	public static function sanitize_callback_config( $callback_type, $callback_config ) {
		if ( ! is_array( $callback_config ) ) {
			return array();
		}

		$sanitized = array();

		switch ( $callback_type ) {
			case 'noop':
				// No config for noop
				break;

			case 'filter_hook':
				if ( ! empty( $callback_config['hook_name'] ) ) {
					$sanitized['hook_name'] = sanitize_key( $callback_config['hook_name'] );
				}
				break;

			case 'wp_remote_post':
				if ( ! empty( $callback_config['url'] ) ) {
					$sanitized['url'] = esc_url_raw( $callback_config['url'] );
				}
				if ( ! empty( $callback_config['method'] ) ) {
					$sanitized['method'] = in_array( $callback_config['method'], array( 'POST', 'PUT' ), true ) 
						? $callback_config['method'] 
						: 'POST';
				}
				if ( isset( $callback_config['timeout'] ) ) {
					$sanitized['timeout'] = absint( $callback_config['timeout'] );
				}
				break;
		}

		return $sanitized;
	}

	/**
	 * Sanitize permission configuration (type-specific).
	 *
	 * @since 0.0.1
	 * @param string $permission_type Permission type (always_allow, logged_in, capability).
	 * @param mixed  $permission_config Raw permission config.
	 * @return array Sanitized permission config.
	 */
	public static function sanitize_permission_config( $permission_type, $permission_config ) {
		if ( ! is_array( $permission_config ) ) {
			return array();
		}

		$sanitized = array();

		switch ( $permission_type ) {
			case 'always_allow':
			case 'logged_in':
				// No config for these types
				break;

			case 'capability':
				if ( ! empty( $permission_config['capability'] ) ) {
					$sanitized['capability'] = sanitize_key( $permission_config['capability'] );
				}
				break;
		}

		return $sanitized;
	}

	/**
	 * Sanitize JSON schema string.
	 *
	 * Validates JSON syntax and re-encodes for normalization.
	 *
	 * @since 0.0.1
	 * @param string $schema_json Raw JSON schema.
	 * @return string Sanitized and normalized JSON schema.
	 */
	public static function sanitize_schema( $schema_json ) {
		if ( empty( $schema_json ) ) {
			return '';
		}

		// Validate JSON syntax
		$decoded = json_decode( $schema_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return ''; // Invalid JSON rejected
		}

		// Re-encode to normalize (remove extra whitespace, ensure consistency)
		return wp_json_encode( $decoded );
	}

	/**
	 * Sanitize MCP servers array.
	 *
	 * @since 0.0.1
	 * @param mixed $servers Raw MCP servers array.
	 * @return array Sanitized servers array.
	 */
	public static function sanitize_mcp_servers( $servers ) {
		if ( ! is_array( $servers ) ) {
			return array();
		}

		return array_map( 'sanitize_key', $servers );
	}

	/**
	 * Sanitize tri-state flag (readonly, destructive, idempotent).
	 *
	 * Valid values: null, 0, 1
	 *
	 * @since 0.0.1
	 * @param mixed $value Raw flag value.
	 * @return int|null Sanitized flag (null, 0, or 1).
	 */
	public static function sanitize_tristate_flag( $value ) {
		if ( null === $value ) {
			return null;
		}

		$int_value = absint( $value );

		return in_array( $int_value, array( 0, 1 ), true ) ? $int_value : null;
	}

	/**
	 * Sanitize complete ability object.
	 *
	 * Aggregate sanitization calling all field sanitizers.
	 * First step in validation pipeline (sanitize → validate → save).
	 *
	 * @since 0.0.1
	 * @param array $fields Raw ability fields.
	 * @return array Sanitized ability fields.
	 */
	public static function sanitize_ability( $fields ) {
		if ( ! is_array( $fields ) ) {
			return array();
		}

		$sanitized = array();

		// Sanitize each field
		if ( isset( $fields['ability_slug'] ) ) {
			$sanitized['ability_slug'] = self::sanitize_ability_slug( $fields['ability_slug'] );
		}

		if ( isset( $fields['label'] ) ) {
			$sanitized['label'] = self::sanitize_label( $fields['label'] );
		}

		if ( isset( $fields['description'] ) ) {
			$sanitized['description'] = self::sanitize_description( $fields['description'] );
		}

		if ( isset( $fields['category'] ) ) {
			$sanitized['category'] = self::sanitize_category( $fields['category'] );
		}

		if ( isset( $fields['enabled'] ) ) {
			$sanitized['enabled'] = (bool) $fields['enabled'];
		}

		if ( isset( $fields['callback_type'] ) ) {
			$sanitized['callback_type'] = sanitize_key( $fields['callback_type'] );
		}

		if ( isset( $fields['callback_config'] ) ) {
			$sanitized['callback_config'] = self::sanitize_callback_config(
				$sanitized['callback_type'] ?? '',
				$fields['callback_config']
			);
		}

		if ( isset( $fields['permission_type'] ) ) {
			$sanitized['permission_type'] = sanitize_key( $fields['permission_type'] );
		}

		if ( isset( $fields['permission_config'] ) ) {
			$sanitized['permission_config'] = self::sanitize_permission_config(
				$sanitized['permission_type'] ?? '',
				$fields['permission_config']
			);
		}

		if ( isset( $fields['input_schema'] ) ) {
			$sanitized['input_schema'] = self::sanitize_schema( $fields['input_schema'] );
		}

		if ( isset( $fields['output_schema'] ) ) {
			$sanitized['output_schema'] = self::sanitize_schema( $fields['output_schema'] );
		}

		if ( isset( $fields['show_in_rest'] ) ) {
			$sanitized['show_in_rest'] = (bool) $fields['show_in_rest'];
		}

		if ( isset( $fields['show_in_mcp'] ) ) {
			$sanitized['show_in_mcp'] = (bool) $fields['show_in_mcp'];
		}

		if ( isset( $fields['mcp_type'] ) ) {
			$sanitized['mcp_type'] = sanitize_key( $fields['mcp_type'] );
		}

		if ( isset( $fields['mcp_servers'] ) ) {
			$sanitized['mcp_servers'] = self::sanitize_mcp_servers( $fields['mcp_servers'] );
		}

		if ( isset( $fields['readonly'] ) ) {
			$sanitized['readonly'] = self::sanitize_tristate_flag( $fields['readonly'] );
		}

		if ( isset( $fields['destructive'] ) ) {
			$sanitized['destructive'] = self::sanitize_tristate_flag( $fields['destructive'] );
		}

		if ( isset( $fields['idempotent'] ) ) {
			$sanitized['idempotent'] = self::sanitize_tristate_flag( $fields['idempotent'] );
		}

		return $sanitized;
	}

	/**
	 * Cast fields to database format.
	 *
	 * Converts PHP types to database-ready format:
	 * - bool → int (0/1)
	 * - array → json string
	 * - object → json string
	 *
	 * Per Memory SEC-02 and BUG-FLAT-ARGS-PATH patterns.
	 *
	 * @since 0.0.1
	 * @param array $fields Sanitized ability fields.
	 * @return array Fields ready for database insert/update.
	 */
	public static function cast_to_db_format( $fields ) {
		if ( ! is_array( $fields ) ) {
			return array();
		}

		$casted = array();

		foreach ( $fields as $key => $value ) {
			switch ( $key ) {
				// Boolean fields: cast to int (0/1)
				case 'enabled':
				case 'show_in_rest':
				case 'show_in_mcp':
				case 'readonly':
				case 'destructive':
				case 'idempotent':
					$casted[ $key ] = is_null( $value ) ? null : (int) $value;
					break;

				// JSON fields: cast to string via wp_json_encode
				case 'callback_config':
				case 'permission_config':
				case 'mcp_servers':
					if ( is_array( $value ) || is_object( $value ) ) {
						$casted[ $key ] = wp_json_encode( $value );
					} else {
						$casted[ $key ] = $value;
					}
					break;

				// Schema fields: ensure string format (already normalized by sanitizer)
				case 'input_schema':
				case 'output_schema':
					$casted[ $key ] = is_string( $value ) ? $value : '';
					break;

				// Pass through other fields as-is
				default:
					$casted[ $key ] = $value;
					break;
			}
		}

		return $casted;
	}
}

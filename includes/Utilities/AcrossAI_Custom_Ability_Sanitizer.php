<?php
/**
 * Custom Ability Sanitizer
 *
 * Static utility class for sanitizing custom ability fields.
 * Handles input sanitization, type casting, and normalization.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Utilities
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Sanitizer class
 *
 * Provides static sanitization methods for custom ability fields.
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Sanitizer {

	/**
	 * Sanitize ability slug
	 *
	 * Converts to lowercase, removes invalid characters.
	 *
	 * @since 1.0.0
	 * @param string $slug Raw slug input.
	 * @return string Sanitized slug.
	 */
	public static function sanitize_ability_slug( $slug ) {
		if ( ! is_string( $slug ) ) {
			return '';
		}

		// Convert to lowercase
		$slug = strtolower( $slug );

		// Use WordPress title sanitization (handles special chars, spaces)
		$slug = sanitize_title_with_dashes( $slug );

		// Replace remaining spaces/underscores with hyphens
		$slug = str_replace( '_', '-', $slug );

		return $slug;
	}

	/**
	 * Sanitize ability label
	 *
	 * Removes HTML, scripts, and unwanted content.
	 *
	 * @since 1.0.0
	 * @param string $label Raw label input.
	 * @return string Sanitized label.
	 */
	public static function sanitize_label( $label ) {
		return sanitize_text_field( $label );
	}

	/**
	 * Sanitize ability description
	 *
	 * Allows safe HTML (like paragraphs, links, formatting).
	 *
	 * @since 1.0.0
	 * @param string $description Raw description input.
	 * @return string Sanitized description.
	 */
	public static function sanitize_description( $description ) {
		return wp_kses_post( $description );
	}

	/**
	 * Sanitize ability category
	 *
	 * Removes HTML and unwanted content.
	 *
	 * @since 1.0.0
	 * @param string $category Raw category input.
	 * @return string Sanitized category.
	 */
	public static function sanitize_category( $category ) {
		return sanitize_text_field( $category );
	}

	/**
	 * Sanitize callback configuration
	 *
	 * Type-specific sanitization based on callback_type.
	 *
	 * @since 1.0.0
	 * @param string $type Callback type (noop, filter_hook, wp_remote_post).
	 * @param mixed  $config Configuration data.
	 * @return array Sanitized configuration.
	 */
	public static function sanitize_callback_config( $type, $config ) {
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		switch ( $type ) {
			case 'noop':
				// No configuration for noop
				return [];

			case 'filter_hook':
				return [
					'hook_name' => sanitize_text_field( $config['hook_name'] ?? '' ),
				];

			case 'wp_remote_post':
				return [
					'url'     => esc_url_raw( $config['url'] ?? '' ),
					'method'  => sanitize_text_field( $config['method'] ?? 'POST' ),
					'timeout' => isset( $config['timeout'] ) ? intval( $config['timeout'] ) : 30,
				];

			default:
				return [];
		}
	}

	/**
	 * Sanitize permission configuration
	 *
	 * Type-specific sanitization based on permission_type.
	 *
	 * @since 1.0.0
	 * @param string $type Permission type (always_allow, logged_in, capability).
	 * @param mixed  $config Configuration data.
	 * @return array Sanitized configuration.
	 */
	public static function sanitize_permission_config( $type, $config ) {
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		switch ( $type ) {
			case 'always_allow':
			case 'logged_in':
				// No configuration for these types
				return [];

			case 'capability':
				return [
					'capability' => sanitize_text_field( $config['capability'] ?? '' ),
				];

			default:
				return [];
		}
	}

	/**
	 * Sanitize JSON schema
	 *
	 * Validates JSON syntax and re-encodes to normalize.
	 *
	 * @since 1.0.0
	 * @param mixed $schema Schema data (string JSON or null).
	 * @return string|null Sanitized JSON string or null if invalid.
	 */
	public static function sanitize_schema( $schema ) {
		// Empty/null input returns null
		if ( empty( $schema ) ) {
			return null;
		}

		// Convert to string if necessary
		if ( ! is_string( $schema ) ) {
			if ( is_array( $schema ) || is_object( $schema ) ) {
				$schema = wp_json_encode( $schema );
			} else {
				return null;
			}
		}

		// Validate JSON syntax
		$decoded = json_decode( $schema, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null; // Invalid JSON
		}

		// Check depth to prevent DOS
		$max_depth = 10;
		if ( self::get_json_depth( $decoded ) > $max_depth ) {
			return null; // Too deeply nested
		}

		// Check size to prevent DOS and storage issues
		$max_size = 65536; // 64KB
		if ( strlen( $schema ) > $max_size ) {
			return null; // Too large
		}

		// Re-encode with wp_json_encode to normalize (remove extra whitespace, ensure encoding)
		return wp_json_encode( $decoded );
	}

	/**
	 * Cast fields to database format
	 *
	 * Converts boolean to int, arrays to JSON strings, etc.
	 *
	 * @since 1.0.0
	 * @param array $fields Fields to cast.
	 * @return array Casted fields ready for database storage.
	 */
	public static function cast_to_db_format( $fields ) {
		$casted = $fields;

		// Cast boolean fields to int (0/1)
		$bool_fields = [ 'enabled', 'show_in_rest', 'show_in_mcp', 'readonly', 'destructive', 'idempotent' ];
		foreach ( $bool_fields as $field ) {
			if ( isset( $casted[ $field ] ) && $casted[ $field ] !== null ) {
				$casted[ $field ] = intval( $casted[ $field ] );
			}
		}

		// Cast array fields to JSON string
		$json_fields = [ 'callback_config', 'permission_config', 'input_schema', 'output_schema', 'mcp_servers' ];
		foreach ( $json_fields as $field ) {
			if ( isset( $casted[ $field ] ) ) {
				if ( is_array( $casted[ $field ] ) ) {
					$casted[ $field ] = wp_json_encode( $casted[ $field ] );
				} elseif ( ! is_string( $casted[ $field ] ) && $casted[ $field ] !== null ) {
					$casted[ $field ] = wp_json_encode( $casted[ $field ] );
				}
			}
		}

		return $casted;
	}

	/**
	 * Sanitize complete ability record
	 *
	 * Applies all sanitization rules to all fields.
	 *
	 * @since 1.0.0
	 * @param array $fields Raw fields to sanitize.
	 * @return array Fully sanitized fields.
	 */
	public static function sanitize_ability( $fields ) {
		$sanitized = [];

		// Sanitize individual fields
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

		// Callback configuration (type-specific)
		if ( isset( $fields['callback_type'] ) && isset( $fields['callback_config'] ) ) {
			$sanitized['callback_config'] = self::sanitize_callback_config(
				$fields['callback_type'],
				$fields['callback_config']
			);
		}

		// Permission configuration (type-specific)
		if ( isset( $fields['permission_type'] ) && isset( $fields['permission_config'] ) ) {
			$sanitized['permission_config'] = self::sanitize_permission_config(
				$fields['permission_type'],
				$fields['permission_config']
			);
		}

		// Schema fields
		if ( isset( $fields['input_schema'] ) ) {
			$sanitized['input_schema'] = self::sanitize_schema( $fields['input_schema'] );
		}

		if ( isset( $fields['output_schema'] ) ) {
			$sanitized['output_schema'] = self::sanitize_schema( $fields['output_schema'] );
		}

		// Pass-through simple fields (will be validated separately)
		if ( isset( $fields['callback_type'] ) ) {
			$sanitized['callback_type'] = sanitize_text_field( $fields['callback_type'] );
		}

		if ( isset( $fields['permission_type'] ) ) {
			$sanitized['permission_type'] = sanitize_text_field( $fields['permission_type'] );
		}

		if ( isset( $fields['mcp_type'] ) ) {
			$sanitized['mcp_type'] = sanitize_text_field( $fields['mcp_type'] );
		}

		// Sanitize MCP servers array
		if ( isset( $fields['mcp_servers'] ) ) {
			if ( is_array( $fields['mcp_servers'] ) ) {
				$sanitized['mcp_servers'] = array_map( 'sanitize_text_field', $fields['mcp_servers'] );
			} else {
				$sanitized['mcp_servers'] = [];
			}
		}

		return $sanitized;
	}

	/**
	 * Get JSON nesting depth
	 *
	 * Recursively calculates the maximum depth of nested arrays/objects.
	 *
	 * @since 1.0.0
	 * @param mixed $data Data to measure.
	 * @param int   $depth Current depth level.
	 * @return int Maximum depth.
	 */
	private static function get_json_depth( $data, $depth = 0 ) {
		if ( ! is_array( $data ) && ! is_object( $data ) ) {
			return $depth;
		}

		$max = $depth;
		foreach ( (array) $data as $item ) {
			$current = self::get_json_depth( $item, $depth + 1 );
			$max     = max( $max, $current );
		}

		return $max;
	}
}

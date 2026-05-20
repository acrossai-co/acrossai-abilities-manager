<?php
/**
 * Custom Ability Validator
 *
 * Static utility class for validating custom ability fields.
 * Validates patterns, uniqueness, types, and constraints.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Utilities
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Validator class
 *
 * Provides static validation methods for custom ability fields.
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Validator {

	/**
	 * Validate ability slug
	 *
	 * Checks pattern (namespace/name format), uniqueness, and length.
	 *
	 * @since 1.0.0
	 * @param string $slug Ability slug to validate.
	 * @param int    $exclude_id Optional. Ability ID to exclude from uniqueness check (for updates).
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_slug( $slug, $exclude_id = null ) {
		// Check for empty
		if ( empty( $slug ) ) {
			return new WP_Error(
				'empty_slug',
				__( 'Ability slug is required.', 'acrossai-abilities-manager' )
			);
		}

		// Check pattern: namespace/name format
		if ( ! preg_match( '/^[a-z0-9]+\/[a-z0-9-]+$/', $slug ) ) {
			return new WP_Error(
				'invalid_slug_pattern',
				__( 'Slug must be in format "namespace/name" with lowercase letters, numbers, and hyphens.', 'acrossai-abilities-manager' )
			);
		}

		// Check length
		if ( strlen( $slug ) > 255 ) {
			return new WP_Error(
				'slug_too_long',
				__( 'Slug must be 255 characters or less.', 'acrossai-abilities-manager' )
			);
		}

		// Check uniqueness via database query
		$table = AcrossAI_Custom_Ability_Table::instance();
		$query = $table->query();

		if ( $exclude_id ) {
			// For updates: exclude the current ability
			$query = $query->where( 'id', '<>', $exclude_id );
		}

		$existing = $query->by_slug( $slug )->get_row();

		if ( $existing ) {
			return new WP_Error(
				'slug_exists',
				__( 'This slug already exists. Please choose a unique slug.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Validate ability label
	 *
	 * Checks for non-empty value and maximum length.
	 *
	 * @since 1.0.0
	 * @param string $label Label to validate.
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_label( $label ) {
		if ( empty( $label ) ) {
			return new WP_Error(
				'empty_label',
				__( 'Ability label is required.', 'acrossai-abilities-manager' )
			);
		}

		if ( strlen( $label ) > 255 ) {
			return new WP_Error(
				'label_too_long',
				__( 'Label must be 255 characters or less.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Validate ability category
	 *
	 * Checks maximum length (optional field).
	 *
	 * @since 1.0.0
	 * @param string $category Category to validate.
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_category( $category ) {
		if ( empty( $category ) ) {
			return true; // Optional field
		}

		if ( strlen( $category ) > 100 ) {
			return new WP_Error(
				'category_too_long',
				__( 'Category must be 100 characters or less.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Validate callback configuration
	 *
	 * Type-specific validation based on callback_type.
	 *
	 * @since 1.0.0
	 * @param string $type Callback type (noop, filter_hook, wp_remote_post).
	 * @param mixed  $config Configuration data.
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_callback_config( $type, $config ) {
		switch ( $type ) {
			case 'noop':
				// No configuration needed for noop
				return true;

			case 'filter_hook':
				if ( ! is_array( $config ) ) {
					return new WP_Error(
						'invalid_callback_config',
						__( 'Callback config must be an array.', 'acrossai-abilities-manager' )
					);
				}

				if ( empty( $config['hook_name'] ) ) {
					return new WP_Error(
						'missing_hook_name',
						__( 'Hook name is required for filter_hook callback type.', 'acrossai-abilities-manager' )
					);
				}

				// Hook name: alphanumeric + underscore only
				if ( ! preg_match( '/^[a-z0-9_]+$/', $config['hook_name'] ) ) {
					return new WP_Error(
						'invalid_hook_name',
						__( 'Hook name must contain only lowercase letters, numbers, and underscores.', 'acrossai-abilities-manager' )
					);
				}

				return true;

			case 'wp_remote_post':
				if ( ! is_array( $config ) ) {
					return new WP_Error(
						'invalid_callback_config',
						__( 'Callback config must be an array.', 'acrossai-abilities-manager' )
					);
				}

				if ( empty( $config['url'] ) ) {
					return new WP_Error(
						'missing_url',
						__( 'URL is required for wp_remote_post callback type.', 'acrossai-abilities-manager' )
					);
				}

				// Validate URL
				if ( ! wp_http_validate_url( $config['url'] ) ) {
					return new WP_Error(
						'invalid_url',
						__( 'Invalid URL for remote POST callback.', 'acrossai-abilities-manager' )
					);
				}

				// Validate method
				$method = $config['method'] ?? 'POST';
				if ( ! in_array( $method, [ 'POST', 'PUT' ], true ) ) {
					return new WP_Error(
						'invalid_method',
						__( 'Method must be POST or PUT.', 'acrossai-abilities-manager' )
					);
				}

				// Validate timeout (optional, 1-300 seconds)
				if ( isset( $config['timeout'] ) ) {
					$timeout = intval( $config['timeout'] );
					if ( $timeout < 1 || $timeout > 300 ) {
						return new WP_Error(
							'invalid_timeout',
							__( 'Timeout must be between 1 and 300 seconds.', 'acrossai-abilities-manager' )
						);
					}
				}

				return true;

			default:
				return new WP_Error(
					'invalid_callback_type',
					__( 'Invalid callback type.', 'acrossai-abilities-manager' )
				);
		}
	}

	/**
	 * Validate permission configuration
	 *
	 * Type-specific validation based on permission_type.
	 *
	 * @since 1.0.0
	 * @param string $type Permission type (always_allow, logged_in, capability).
	 * @param mixed  $config Configuration data.
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_permission_config( $type, $config ) {
		switch ( $type ) {
			case 'always_allow':
			case 'logged_in':
				// No configuration needed
				return true;

			case 'capability':
				if ( ! is_array( $config ) ) {
					return new WP_Error(
						'invalid_permission_config',
						__( 'Permission config must be an array.', 'acrossai-abilities-manager' )
					);
				}

				if ( empty( $config['capability'] ) ) {
					return new WP_Error(
						'missing_capability',
						__( 'Capability name is required for capability permission type.', 'acrossai-abilities-manager' )
					);
				}

				// Validate capability exists
				$wp_roles = wp_roles();
				if ( ! $wp_roles ) {
					return new WP_Error(
						'role_system_error',
						__( 'WordPress role system is not available.', 'acrossai-abilities-manager' )
					);
				}

				$all_caps = $wp_roles->get_capabilities();
				if ( ! isset( $all_caps[ $config['capability'] ] ) ) {
					return new WP_Error(
						'capability_not_found',
						sprintf(
							/* translators: %s: capability name */
							__( 'Capability "%s" does not exist.', 'acrossai-abilities-manager' ),
							$config['capability']
						)
					);
				}

				return true;

			default:
				return new WP_Error(
					'invalid_permission_type',
					__( 'Invalid permission type.', 'acrossai-abilities-manager' )
				);
		}
	}

	/**
	 * Validate JSON schema
	 *
	 * Validates JSON syntax, enforces depth and size limits.
	 *
	 * @since 1.0.0
	 * @param mixed $schema Schema data (string JSON or null).
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_schema( $schema ) {
		// Empty/null schemas are valid
		if ( empty( $schema ) ) {
			return true;
		}

		// Must be string
		if ( ! is_string( $schema ) ) {
			return new WP_Error(
				'invalid_schema_type',
				__( 'Schema must be a JSON string.', 'acrossai-abilities-manager' )
			);
		}

		// Validate JSON syntax
		$decoded = json_decode( $schema, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'invalid_json',
				sprintf(
					/* translators: %s: JSON error message */
					__( 'Invalid JSON: %s', 'acrossai-abilities-manager' ),
					json_last_error_msg()
				)
			);
		}

		// Check depth (prevent DOS)
		$max_depth = 10;
		$actual_depth = self::get_json_depth( $decoded );
		if ( $actual_depth > $max_depth ) {
			return new WP_Error(
				'schema_too_deep',
				sprintf(
					/* translators: %d: max depth */
					__( 'Schema nesting is too deep (max %d levels).', 'acrossai-abilities-manager' ),
					$max_depth
				)
			);
		}

		// Check size (prevent DOS and storage issues)
		$max_size = 65536; // 64KB
		if ( strlen( $schema ) > $max_size ) {
			return new WP_Error(
				'schema_too_large',
				sprintf(
					/* translators: %d: max size in KB */
					__( 'Schema is too large (max %dKB).', 'acrossai-abilities-manager' ),
					$max_size / 1024
				)
			);
		}

		return true;
	}

	/**
	 * Validate complete ability record
	 *
	 * Aggregate validation on all required fields.
	 *
	 * @since 1.0.0
	 * @param array $fields Ability fields to validate.
	 * @param int   $exclude_id Optional. Ability ID to exclude from uniqueness check.
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_ability( $fields, $exclude_id = null ) {
		// Validate required fields
		$required_fields = [ 'ability_slug', 'label', 'callback_type', 'permission_type' ];
		foreach ( $required_fields as $field ) {
			if ( ! isset( $fields[ $field ] ) || '' === $fields[ $field ] ) {
				return new WP_Error(
					'missing_required_field',
					sprintf(
						/* translators: %s: field name */
						__( 'Required field "%s" is missing.', 'acrossai-abilities-manager' ),
						$field
					)
				);
			}
		}

		// Validate slug
		$slug_valid = self::validate_slug( $fields['ability_slug'], $exclude_id );
		if ( is_wp_error( $slug_valid ) ) {
			return $slug_valid;
		}

		// Validate label
		$label_valid = self::validate_label( $fields['label'] );
		if ( is_wp_error( $label_valid ) ) {
			return $label_valid;
		}

		// Validate category (optional)
		if ( isset( $fields['category'] ) ) {
			$category_valid = self::validate_category( $fields['category'] );
			if ( is_wp_error( $category_valid ) ) {
				return $category_valid;
			}
		}

		// Validate callback config
		$callback_config = $fields['callback_config'] ?? [];
		$callback_valid = self::validate_callback_config( $fields['callback_type'], $callback_config );
		if ( is_wp_error( $callback_valid ) ) {
			return $callback_valid;
		}

		// Validate permission config
		$permission_config = $fields['permission_config'] ?? [];
		$permission_valid = self::validate_permission_config( $fields['permission_type'], $permission_config );
		if ( is_wp_error( $permission_valid ) ) {
			return $permission_valid;
		}

		// Validate schemas (optional)
		if ( isset( $fields['input_schema'] ) ) {
			$input_schema_valid = self::validate_schema( $fields['input_schema'] );
			if ( is_wp_error( $input_schema_valid ) ) {
				return $input_schema_valid;
			}
		}

		if ( isset( $fields['output_schema'] ) ) {
			$output_schema_valid = self::validate_schema( $fields['output_schema'] );
			if ( is_wp_error( $output_schema_valid ) ) {
				return $output_schema_valid;
			}
		}

		return true;
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

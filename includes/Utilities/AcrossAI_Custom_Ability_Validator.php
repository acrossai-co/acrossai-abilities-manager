<?php
/**
 * Custom Ability Validation Utility
 *
 * Static utility for validating custom ability fields and aggregate validation.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Utilities
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Table;

/**
 * Class AcrossAI_Custom_Ability_Validator
 *
 * Static utility class for comprehensive field validation (Memory DEC-UTILITY-STATIC-ONLY).
 * Validates individual fields and aggregate ability objects before save.
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Validator {

	/**
	 * Validate ability slug.
	 *
	 * Pattern: ^[a-z0-9]+/[a-z0-9-]+$ (namespace/name format)
	 * Max length: 255 chars
	 * Uniqueness: checked in database
	 *
	 * @since 0.0.1
	 * @param string $slug Ability slug.
	 * @param int    $exclude_id Exclude ability ID from uniqueness check (for updates).
	 * @return bool|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_slug( $slug, $exclude_id = null ) {
		if ( empty( $slug ) ) {
			return new \WP_Error(
				'invalid_slug',
				esc_html__( 'Ability slug is required.', 'acrossai-abilities-manager' )
			);
		}

		if ( ! preg_match( '/^[a-z0-9]+\/[a-z0-9-]+$/', $slug ) ) {
			return new \WP_Error(
				'invalid_slug_format',
				esc_html__( 'Ability slug must be in format "namespace/name" (lowercase alphanumeric and hyphens).', 'acrossai-abilities-manager' )
			);
		}

		if ( strlen( $slug ) > 255 ) {
			return new \WP_Error(
				'invalid_slug_length',
				esc_html__( 'Ability slug must be 255 characters or fewer.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Validate label.
	 *
	 * @since 0.0.1
	 * @param string $label Ability label.
	 * @return bool|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_label( $label ) {
		if ( empty( $label ) ) {
			return new \WP_Error(
				'invalid_label',
				esc_html__( 'Ability label is required.', 'acrossai-abilities-manager' )
			);
		}

		if ( strlen( $label ) > 255 ) {
			return new \WP_Error(
				'invalid_label_length',
				esc_html__( 'Ability label must be 255 characters or fewer.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Validate category (optional).
	 *
	 * @since 0.0.1
	 * @param string $category Ability category.
	 * @return bool|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_category( $category ) {
		if ( empty( $category ) ) {
			return true; // Optional
		}

		if ( strlen( $category ) > 100 ) {
			return new \WP_Error(
				'invalid_category_length',
				esc_html__( 'Ability category must be 100 characters or fewer.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Validate callback type and config.
	 *
	 * @since 0.0.1
	 * @param string $callback_type Callback type (noop, filter_hook, wp_remote_post).
	 * @param mixed  $callback_config Callback configuration (type-specific).
	 * @return bool|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_callback_config( $callback_type, $callback_config ) {
		if ( empty( $callback_type ) ) {
			return new \WP_Error(
				'invalid_callback_type',
				esc_html__( 'Callback type is required.', 'acrossai-abilities-manager' )
			);
		}

		if ( ! in_array( $callback_type, array( 'noop', 'filter_hook', 'wp_remote_post' ), true ) ) {
			return new \WP_Error(
				'invalid_callback_type',
				esc_html__( 'Callback type must be one of: noop, filter_hook, wp_remote_post.', 'acrossai-abilities-manager' )
			);
		}

		// Type-specific validation
		switch ( $callback_type ) {
			case 'noop':
				// No config required for noop
				return true;

			case 'filter_hook':
				if ( ! is_array( $callback_config ) ) {
					return new \WP_Error(
						'invalid_callback_config',
						esc_html__( 'Filter hook config must be an object.', 'acrossai-abilities-manager' )
					);
				}

				if ( empty( $callback_config['hook_name'] ) ) {
					return new \WP_Error(
						'invalid_hook_name',
						esc_html__( 'Hook name is required for filter_hook callback type.', 'acrossai-abilities-manager' )
					);
				}

				if ( ! preg_match( '/^[a-z0-9_]+$/', $callback_config['hook_name'] ) ) {
					return new \WP_Error(
						'invalid_hook_name',
						esc_html__( 'Hook name must be lowercase alphanumeric with underscores only.', 'acrossai-abilities-manager' )
					);
				}

				return true;

			case 'wp_remote_post':
				if ( ! is_array( $callback_config ) ) {
					return new \WP_Error(
						'invalid_callback_config',
						esc_html__( 'HTTP POST config must be an object.', 'acrossai-abilities-manager' )
					);
				}

				if ( empty( $callback_config['url'] ) || ! wp_http_validate_url( $callback_config['url'] ) ) {
					return new \WP_Error(
						'invalid_url',
						esc_html__( 'Valid URL is required for wp_remote_post callback type.', 'acrossai-abilities-manager' )
					);
				}

				$method = $callback_config['method'] ?? 'POST';
				if ( ! in_array( $method, array( 'POST', 'PUT' ), true ) ) {
					return new \WP_Error(
						'invalid_method',
						esc_html__( 'HTTP method must be POST or PUT.', 'acrossai-abilities-manager' )
					);
				}

				$timeout = isset( $callback_config['timeout'] ) ? (int) $callback_config['timeout'] : 30;
				if ( $timeout < 1 || $timeout > 300 ) {
					return new \WP_Error(
						'invalid_timeout',
						esc_html__( 'Timeout must be between 1 and 300 seconds.', 'acrossai-abilities-manager' )
					);
				}

				return true;

			default:
				return new \WP_Error(
					'invalid_callback_type',
					esc_html__( 'Unknown callback type.', 'acrossai-abilities-manager' )
				);
		}
	}

	/**
	 * Validate permission type and config.
	 *
	 * @since 0.0.1
	 * @param string $permission_type Permission type (always_allow, logged_in, capability).
	 * @param mixed  $permission_config Permission configuration (type-specific).
	 * @return bool|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_permission_config( $permission_type, $permission_config ) {
		if ( empty( $permission_type ) ) {
			return new \WP_Error(
				'invalid_permission_type',
				esc_html__( 'Permission type is required.', 'acrossai-abilities-manager' )
			);
		}

		if ( ! in_array( $permission_type, array( 'always_allow', 'logged_in', 'capability' ), true ) ) {
			return new \WP_Error(
				'invalid_permission_type',
				esc_html__( 'Permission type must be one of: always_allow, logged_in, capability.', 'acrossai-abilities-manager' )
			);
		}

		// Type-specific validation
		switch ( $permission_type ) {
			case 'always_allow':
			case 'logged_in':
				// No config required
				return true;

			case 'capability':
				if ( ! is_array( $permission_config ) ) {
					return new \WP_Error(
						'invalid_permission_config',
						esc_html__( 'Capability config must be an object.', 'acrossai-abilities-manager' )
					);
				}

				if ( empty( $permission_config['capability'] ) ) {
					return new \WP_Error(
						'invalid_capability',
						esc_html__( 'Capability name is required for capability permission type.', 'acrossai-abilities-manager' )
					);
				}

				return true;

			default:
				return new \WP_Error(
					'invalid_permission_type',
					esc_html__( 'Unknown permission type.', 'acrossai-abilities-manager' )
				);
		}
	}

	/**
	 * Validate JSON schema (input or output).
	 *
	 * Validates JSON syntax, depth limit (max 10 levels per security-constraints Finding 4),
	 * and size limit (max 64KB).
	 *
	 * @since 0.0.1
	 * @param string $schema_json JSON schema string.
	 * @param string $field_name Field name for error messages.
	 * @return bool|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_schema( $schema_json, $field_name = 'schema' ) {
		if ( empty( $schema_json ) ) {
			return true; // Optional
		}

		// Check size limit (max 64KB per security-constraints Finding 4)
		if ( strlen( $schema_json ) > 65536 ) {
			return new \WP_Error(
				'invalid_schema_size',
				sprintf(
					esc_html__( '%s must be 64KB or smaller.', 'acrossai-abilities-manager' ),
					esc_html( $field_name )
				)
			);
		}

		// Validate JSON syntax
		$decoded = json_decode( $schema_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'invalid_json',
				sprintf(
					esc_html__( '%s must be valid JSON. Error: %s', 'acrossai-abilities-manager' ),
					esc_html( $field_name ),
					esc_html( json_last_error_msg() )
				)
			);
		}

		// Check depth limit (max 10 levels per security-constraints Finding 4)
		$depth = self::calculate_json_depth( $decoded );
		if ( $depth > 10 ) {
			return new \WP_Error(
				'invalid_schema_depth',
				sprintf(
					esc_html__( '%s is too deeply nested (max 10 levels).', 'acrossai-abilities-manager' ),
					esc_html( $field_name )
				)
			);
		}

		return true;
	}

	/**
	 * Validate complete ability object before save.
	 *
	 * Aggregate validation calling all field validators.
	 *
	 * @since 0.0.1
	 * @param array $fields Ability fields (sanitized).
	 * @return bool|\WP_Error True if all fields valid, WP_Error otherwise.
	 */
	public static function validate_ability( $fields ) {
		if ( ! is_array( $fields ) ) {
			return new \WP_Error(
				'invalid_fields',
				esc_html__( 'Fields must be an array.', 'acrossai-abilities-manager' )
			);
		}

		// Required field validation
		$required_fields = array( 'ability_slug', 'label', 'callback_type', 'permission_type' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $fields[ $field ] ) || empty( $fields[ $field ] ) ) {
				return new \WP_Error(
					'missing_required_field',
					sprintf(
						esc_html__( 'Required field "%s" is missing or empty.', 'acrossai-abilities-manager' ),
						esc_html( $field )
					)
				);
			}
		}

		// Validate individual fields
		$validation = self::validate_slug( $fields['ability_slug'] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$validation = self::validate_label( $fields['label'] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( isset( $fields['category'] ) ) {
			$validation = self::validate_category( $fields['category'] );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
		}

		$validation = self::validate_callback_config( $fields['callback_type'], $fields['callback_config'] ?? array() );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$validation = self::validate_permission_config( $fields['permission_type'], $fields['permission_config'] ?? array() );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( isset( $fields['input_schema'] ) ) {
			$validation = self::validate_schema( $fields['input_schema'], 'input_schema' );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
		}

		if ( isset( $fields['output_schema'] ) ) {
			$validation = self::validate_schema( $fields['output_schema'], 'output_schema' );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
		}

		return true;
	}

	/**
	 * Calculate JSON depth (helper for validate_schema).
	 *
	 * @since 0.0.1
	 * @param mixed $data JSON-decoded data.
	 * @param int   $depth Current depth.
	 * @return int Maximum depth.
	 */
	private static function calculate_json_depth( $data, $depth = 0 ) {
		if ( ! is_array( $data ) && ! is_object( $data ) ) {
			return $depth;
		}

		$max = $depth;

		foreach ( (array) $data as $item ) {
			$current = self::calculate_json_depth( $item, $depth + 1 );
			$max = max( $max, $current );
		}

		return $max;
	}
}

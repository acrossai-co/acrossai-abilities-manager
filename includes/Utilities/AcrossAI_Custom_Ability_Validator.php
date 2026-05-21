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

	const MAX_SLUG_LENGTH   = 255;
	const MAX_LABEL_LENGTH  = 255;
	const MAX_SCHEMA_BYTES  = 65536; // 64 KB
	const MAX_SCHEMA_DEPTH  = 10;
	const ALLOWED_CB_TYPES  = array( 'noop', 'filter_hook', 'wp_remote_post' );
	const ALLOWED_MCP_TYPES = array( 'tool', 'resource', 'prompt' );

	/**
	 * Validate ability slug: namespace/name pattern, max 255 chars.
	 *
	 * @since 1.0.0
	 * @param string $slug Slug to validate.
	 * @return true|\WP_Error
	 */
	public static function validate_slug( $slug ) {
		if ( '' === (string) $slug ) {
			return new \WP_Error( 'invalid_slug', __( 'Ability slug is required.', 'acrossai-abilities-manager' ) );
		}

		if ( strlen( $slug ) > self::MAX_SLUG_LENGTH ) {
			return new \WP_Error(
				'invalid_slug',
				__( 'Ability slug must be 255 characters or less.', 'acrossai-abilities-manager' )
			);
		}

		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/', $slug ) ) {
			return new \WP_Error(
				'invalid_slug',
				__( 'Ability slug must be in namespace/name format using lowercase letters, numbers, and hyphens.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Validate ability label: non-empty, max 255 chars.
	 *
	 * @since 1.0.0
	 * @param string $label Label to validate.
	 * @return true|\WP_Error
	 */
	public static function validate_label( $label ) {
		if ( '' === trim( (string) $label ) ) {
			return new \WP_Error( 'invalid_label', __( 'Ability label is required.', 'acrossai-abilities-manager' ) );
		}

		if ( strlen( $label ) > self::MAX_LABEL_LENGTH ) {
			return new \WP_Error(
				'invalid_label',
				__( 'Ability label must be 255 characters or less.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Validate callback configuration against the declared callback_type.
	 *
	 * @since 1.0.0
	 * @param string       $type   Callback type (noop, filter_hook, wp_remote_post).
	 * @param array|null   $config Callback config array.
	 * @return true|\WP_Error
	 */
	public static function validate_callback_config( $type, $config ) {
		if ( ! in_array( $type, self::ALLOWED_CB_TYPES, true ) ) {
			return new \WP_Error(
				'invalid_callback_type',
				/* translators: %s: callback type */
				sprintf( __( 'Unknown callback type: %s.', 'acrossai-abilities-manager' ), esc_html( $type ) )
			);
		}

		if ( 'noop' === $type ) {
			return true;
		}

		$config = is_array( $config ) ? $config : array();

		if ( 'filter_hook' === $type ) {
			if ( empty( $config['hook_name'] ) || ! is_string( $config['hook_name'] ) ) {
				return new \WP_Error(
					'invalid_callback_config',
					__( 'filter_hook requires a non-empty hook_name string.', 'acrossai-abilities-manager' )
				);
			}
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $config['hook_name'] ) ) {
				return new \WP_Error(
					'invalid_callback_config',
					__( 'hook_name may only contain letters, digits, and underscores.', 'acrossai-abilities-manager' )
				);
			}
		}

		if ( 'wp_remote_post' === $type ) {
			if ( empty( $config['url'] ) ) {
				return new \WP_Error(
					'invalid_callback_config',
					__( 'wp_remote_post requires a url.', 'acrossai-abilities-manager' )
				);
			}
			if ( ! wp_http_validate_url( $config['url'] ) ) {
				return new \WP_Error(
					'invalid_callback_config',
					__( 'callback_config url is not a valid URL.', 'acrossai-abilities-manager' )
				);
			}
		}

		return true;
	}

	/**
	 * Validate a JSON Schema string: syntax, depth limit, size limit.
	 *
	 * @since 1.0.0
	 * @param string|array|null $schema JSON schema value.
	 * @return true|\WP_Error
	 */
	public static function validate_schema( $schema ) {
		if ( null === $schema || '' === $schema ) {
			return true;
		}

		if ( is_array( $schema ) ) {
			$schema = wp_json_encode( $schema );
		}

		if ( strlen( $schema ) > self::MAX_SCHEMA_BYTES ) {
			return new \WP_Error(
				'invalid_schema',
				__( 'JSON schema exceeds the 64 KB size limit.', 'acrossai-abilities-manager' )
			);
		}

		$decoded = json_decode( $schema, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'invalid_schema',
				__( 'JSON schema contains invalid JSON syntax.', 'acrossai-abilities-manager' )
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'invalid_schema',
				__( 'JSON schema must be a JSON object.', 'acrossai-abilities-manager' )
			);
		}

		if ( self::array_depth( $decoded ) > self::MAX_SCHEMA_DEPTH ) {
			return new \WP_Error(
				'invalid_schema',
				__( 'JSON schema exceeds the maximum nesting depth of 10.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Validate MCP type value.
	 *
	 * @since 1.0.0
	 * @param string|null $mcp_type MCP type to validate.
	 * @return true|\WP_Error
	 */
	public static function validate_mcp_type( $mcp_type ) {
		if ( null === $mcp_type || '' === $mcp_type ) {
			return true;
		}

		if ( ! in_array( $mcp_type, self::ALLOWED_MCP_TYPES, true ) ) {
			return new \WP_Error(
				'invalid_mcp_type',
				__( 'mcp_type must be one of: tool, resource, prompt.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Aggregate validation for all ability fields.
	 *
	 * @since 1.0.0
	 * @param array $fields Sanitized ability fields.
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_ability( $fields ) {
		$fields = is_array( $fields ) ? $fields : array();

		if ( isset( $fields['ability_slug'] ) ) {
			$result = self::validate_slug( $fields['ability_slug'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $fields['label'] ) ) {
			$result = self::validate_label( $fields['label'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $fields['callback_type'] ) || isset( $fields['callback_config'] ) ) {
			$type   = $fields['callback_type'] ?? 'noop';
			$config = $fields['callback_config'] ?? array();
			$result = self::validate_callback_config( $type, $config );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $fields['input_schema'] ) ) {
			$result = self::validate_schema( $fields['input_schema'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $fields['output_schema'] ) ) {
			$result = self::validate_schema( $fields['output_schema'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $fields['mcp_type'] ) ) {
			$result = self::validate_mcp_type( $fields['mcp_type'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Calculate the maximum nesting depth of an array.
	 *
	 * @since 1.0.0
	 * @param array $array Array to measure.
	 * @param int   $depth Current depth.
	 * @return int Maximum depth.
	 */
	private static function array_depth( array $array, int $depth = 1 ): int {
		$max = $depth;
		foreach ( $array as $value ) {
			if ( is_array( $value ) ) {
				$child = self::array_depth( $value, $depth + 1 );
				if ( $child > $max ) {
					$max = $child;
				}
			}
		}
		return $max;
	}
}

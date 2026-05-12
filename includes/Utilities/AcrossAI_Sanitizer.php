<?php
/**
 * Shared input-sanitization utility for all AcrossAI modules.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Utilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Static sanitization helpers used at REST API boundaries.
 *
 * @since 0.1.0
 */
class AcrossAI_Sanitizer {

	/**
	 * Sanitize an ability slug parameter.
	 *
	 * Applies sanitize_text_field() and strips any characters that are not
	 * valid in an ability slug (alphanumeric, hyphens, forward-slashes).
	 *
	 * @since  0.1.0
	 * @param  string $slug Raw slug value from request.
	 * @return string
	 */
	public static function sanitize_ability_slug( string $slug ): string {
		$slug = sanitize_text_field( $slug );
		// Allow alphanumeric, hyphens, underscores, forward-slashes (namespaced slugs).
		return preg_replace( '/[^a-zA-Z0-9\-_\/]/', '', $slug );
	}

	/**
	 * Sanitize a tri-state value (true / false / null).
	 *
	 * Accepts PHP booleans or strict integer equivalents (1/0).
	 * Returns null for any value that cannot be resolved to a boolean.
	 *
	 * @since  0.1.0
	 * @param  mixed $value Raw value.
	 * @return bool|null
	 */
	public static function sanitize_tri_state( $value ): ?bool {
		if ( null === $value ) {
			return null;
		}
		if ( true === $value || 1 === $value || '1' === $value ) {
			return true;
		}
		if ( false === $value || 0 === $value || '0' === $value ) {
			return false;
		}
		return null;
	}

	/**
	 * Sanitize an MCP type value.
	 *
	 * @since  0.1.0
	 * @param  mixed $value Raw value.
	 * @return string|null  One of 'tool', 'resource', 'prompt', or null.
	 */
	public static function sanitize_mcp_type( $value ): ?string {
		if ( null === $value ) {
			return null;
		}
		$allowed = array( 'tool', 'resource', 'prompt' );
		$value   = sanitize_text_field( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : null;
	}

	/**
	 * Sanitize an array of MCP server IDs.
	 *
	 * @since  0.1.0
	 * @param  mixed $value Raw value.
	 * @return array|null  Array of non-empty strings, or null.
	 */
	public static function sanitize_mcp_servers_array( $value ): ?array {
		if ( null === $value ) {
			return null;
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$sanitized = array();
		foreach ( $value as $server_id ) {
			$clean = sanitize_text_field( (string) $server_id );
			if ( '' !== $clean ) {
				$sanitized[] = $clean;
			}
		}
		return $sanitized;
	}

	/**
	 * Cast a tinyint database value to PHP bool or null.
	 *
	 * Used by AcrossAI_Sitewide_Row and any future Row classes.
	 * Do NOT duplicate this method on individual Row classes (RF-02).
	 *
	 * @since  0.1.0
	 * @param  mixed $value DB value (1, 0, or null).
	 * @return bool|null
	 */
	public static function cast_tri_state( $value ): ?bool {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return (bool) $value;
	}
}

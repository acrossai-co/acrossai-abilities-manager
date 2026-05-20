<?php
/**
 * Protected Custom Abilities Namespace Filtering
 *
 * Defines and manages protected ability namespace prefixes to prevent collisions.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Utilities
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Protected_Custom_Abilities class
 *
 * Static utility: Manages protected namespace prefixes (DEC-PROTECTED-SLUGS-PATTERN).
 *
 * @since 1.0.0
 */
class AcrossAI_Protected_Custom_Abilities {

	/**
	 * Default protected prefixes
	 *
	 * These prefixes cannot be used for custom ability slugs to prevent conflicts
	 * with core, WordPress, MCP, and system abilities.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $default_prefixes = array(
		'acrossai',  // AcrossAI system abilities
		'mcp',       // MCP-specific abilities
		'wp',        // WordPress core abilities
		'system',    // System-level abilities
		'core',      // Core WordPress abilities
	);

	/**
	 * Get list of protected ability namespace prefixes
	 *
	 * Protected prefixes cannot be used when creating custom abilities.
	 * This ensures custom abilities don't collide with system or core abilities.
	 *
	 * Can be filtered via `acrossai_protected_ability_prefixes` filter to add/remove prefixes.
	 *
	 * @since 1.0.0
	 * @param string $context Context for the query (e.g., 'custom_abilities', 'validation', 'filtering')
	 * @return array Array of protected namespace prefixes (e.g., ['acrossai', 'wp', 'core'])
	 */
	public static function get_protected_prefixes( $context = 'custom_abilities' ) {
		/**
		 * Filter protected ability namespace prefixes
		 *
		 * Allows plugins/themes to add or remove protected prefixes dynamically.
		 * Default includes: acrossai, mcp, wp, system, core
		 *
		 * @since 1.0.0
		 * @param array  $prefixes Default protected prefixes
		 * @param string $context Context for the filter (custom_abilities, validation, filtering, etc.)
		 * @return array Modified array of protected prefixes
		 */
		$prefixes = apply_filters(
			'acrossai_protected_ability_prefixes',
			self::$default_prefixes,
			$context
		);

		// Ensure return value is array
		if ( ! is_array( $prefixes ) ) {
			$prefixes = self::$default_prefixes;
		}

		return array_unique( array_filter( array_map( 'strval', $prefixes ) ) );
	}

	/**
	 * Check if a slug starts with a protected prefix
	 *
	 * @since 1.0.0
	 * @param string $slug Ability slug to check (format: "namespace/name")
	 * @param string $context Context for prefix filtering (default: 'custom_abilities')
	 * @return bool True if slug starts with protected prefix, false otherwise
	 */
	public static function is_protected_slug( $slug, $context = 'custom_abilities' ) {
		$prefixes = self::get_protected_prefixes( $context );

		// Extract namespace part (before first /)
		$namespace = strpos( $slug, '/' ) !== false ? substr( $slug, 0, strpos( $slug, '/' ) ) : '';

		if ( empty( $namespace ) ) {
			return false;
		}

		// Check if namespace matches any protected prefix
		foreach ( $prefixes as $prefix ) {
			if ( $namespace === $prefix ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get list of protected prefixes as pipe-separated string (for regex patterns)
	 *
	 * Useful for building regex patterns that match any protected prefix.
	 * Example output: "acrossai|mcp|wp|system|core"
	 *
	 * @since 1.0.0
	 * @param string $context Context for prefix filtering
	 * @return string Protected prefixes separated by pipe character
	 */
	public static function get_protected_prefixes_pattern( $context = 'custom_abilities' ) {
		$prefixes = self::get_protected_prefixes( $context );
		return implode( '|', array_map( 'preg_quote', $prefixes ) );
	}
}

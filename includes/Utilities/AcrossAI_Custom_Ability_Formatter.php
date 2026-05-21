<?php
/**
 * Custom Ability Formatter Utility
 *
 * Static formatting methods for REST and MCP responses.
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
 * AcrossAI_Custom_Ability_Formatter class
 *
 * Static utility: Formats custom ability data for REST/MCP responses.
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Formatter {

	/**
	 * Datetime columns to convert to ISO 8601.
	 *
	 * @var string[]
	 */
	private static $datetime_columns = array( 'created_at', 'updated_at' );

	/**
	 * JSON-encoded columns to decode for output.
	 *
	 * @var string[]
	 */
	private static $json_columns = array( 'callback_config', 'input_schema', 'output_schema' );

	/**
	 * Format ability row for REST response.
	 *
	 * Converts a BerlinDB Row to a plain stdClass with:
	 * - JSON columns decoded to arrays
	 * - datetime columns as ISO 8601 strings
	 * - integer casts for tinyint columns
	 *
	 * @since 1.0.0
	 * @param object $ability_row Ability row from BerlinDB.
	 * @return \stdClass|null Formatted object or null for invalid input.
	 */
	public static function format_ability_for_response( $ability_row ) {
		if ( ! is_object( $ability_row ) ) {
			return null;
		}

		$response = new \stdClass();

		foreach ( get_object_vars( $ability_row ) as $key => $value ) {
			if ( in_array( $key, self::$datetime_columns, true ) ) {
				$response->{ $key } = self::to_iso8601( $value );
			} elseif ( in_array( $key, self::$json_columns, true ) ) {
				$response->{ $key } = self::decode_json_column( $value );
			} else {
				$response->{ $key } = $value;
			}
		}

		return $response;
	}

	/**
	 * Format abilities for MCP exposure.
	 *
	 * Filters by show_in_mcp and mcp_type, then formats each row.
	 *
	 * @since 1.0.0
	 * @param array  $abilities     Array of ability row objects.
	 * @param string $mcp_type      MCP type filter (tool, resource, prompt).
	 * @param string $current_server Current MCP server slug (unused; mcp_servers removed).
	 * @return array Formatted abilities for MCP.
	 */
	public static function format_for_mcp( $abilities, $mcp_type = '', $current_server = '' ) {
		$formatted = array();

		foreach ( (array) $abilities as $ability ) {
			if ( empty( $ability->show_in_mcp ) ) {
				continue;
			}

			if ( ! empty( $mcp_type ) && $ability->mcp_type !== $mcp_type ) {
				continue;
			}

			$formatted[] = self::format_ability_for_response( $ability );
		}

		return $formatted;
	}

	/**
	 * Convert a MySQL datetime string to ISO 8601 format.
	 *
	 * @since 1.0.0
	 * @param string|null $datetime MySQL datetime (e.g. "2024-01-15 12:00:00") or null.
	 * @return string|null ISO 8601 string or null.
	 */
	private static function to_iso8601( $datetime ) {
		if ( null === $datetime || '' === $datetime ) {
			return null;
		}

		try {
			$dt = new \DateTime( $datetime, new \DateTimeZone( 'UTC' ) );
			return $dt->format( 'Y-m-d\TH:i:s\Z' );
		} catch ( \Exception $e ) {
			return (string) $datetime;
		}
	}

	/**
	 * Decode a JSON column value to array/null.
	 *
	 * If the value is already an array (Row decoded it), pass through.
	 *
	 * @since 1.0.0
	 * @param mixed $value Column value.
	 * @return array|null
	 */
	private static function decode_json_column( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( null === $value || '' === $value ) {
			return null;
		}

		$decoded = json_decode( (string) $value, true );
		return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : null;
	}
}

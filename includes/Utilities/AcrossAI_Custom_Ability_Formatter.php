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
	 * Format ability row for REST response
	 *
	 * Converts database row object to REST-compatible format.
	 *
	 * @since 1.0.0
	 * @param object $ability_row Ability row from BerlinDB
	 * @return object Formatted ability object
	 */
	public static function format_ability_for_response( $ability_row ) {
		// TODO: Implement REST formatting (T014)
		// Convert 20-field row to JSON-encodable stdClass
		// Ensure JSON fields are decoded, timestamps in ISO 8601

		if ( ! is_object( $ability_row ) ) {
			return null;
		}

		$response = new \stdClass();
		
		// Copy all properties from row
		foreach ( get_object_vars( $ability_row ) as $key => $value ) {
			$response->{$key} = $value;
		}

		return $response;
	}

	/**
	 * Format abilities for MCP exposure
	 *
	 * Converts abilities array to MCP-compatible format.
	 *
	 * @since 1.0.0
	 * @param array  $abilities Array of ability row objects
	 * @param string $mcp_type MCP type filter (tool, resource, prompt)
	 * @param string $current_server Current MCP server slug
	 * @return array Formatted abilities for MCP
	 */
	public static function format_for_mcp( $abilities, $mcp_type = '', $current_server = '' ) {
		// TODO: Implement MCP formatting (T014)
		// Filter by show_in_mcp, mcp_type, mcp_servers
		// Return MCP-compatible format

		$formatted = array();

		foreach ( (array) $abilities as $ability ) {
			if ( empty( $ability->show_in_mcp ) ) {
				continue;
			}

			// Filter by mcp_type if specified
			if ( ! empty( $mcp_type ) && $ability->mcp_type !== $mcp_type ) {
				continue;
			}

			// Filter by mcp_servers if specified
			if ( ! empty( $current_server ) && ! empty( $ability->mcp_servers ) ) {
				$servers = json_decode( $ability->mcp_servers, true );
				if ( is_array( $servers ) && ! in_array( $current_server, $servers, true ) ) {
					continue;
				}
			}

			$formatted[] = self::format_ability_for_response( $ability );
		}

		return $formatted;
	}
}

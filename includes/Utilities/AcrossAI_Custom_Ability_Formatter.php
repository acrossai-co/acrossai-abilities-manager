<?php
/**
 * Custom Ability Response Formatter Utility
 *
 * Converts database Row objects to REST API and MCP response formats.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Utilities
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Row;

/**
 * Class AcrossAI_Custom_Ability_Formatter
 *
 * Static utility class for formatting ability data for REST API and MCP responses.
 * All methods are static (Memory DEC-UTILITY-STATIC-ONLY).
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Formatter {

	/**
	 * Format ability for REST API response.
	 *
	 * Converts database Row object to REST response format with all 20 fields.
	 * Returns JSON-encodable stdClass object.
	 *
	 * @since 0.0.1
	 * @param AcrossAI_Custom_Ability_Row $ability Ability Row object.
	 * @return \stdClass Formatted ability for REST response.
	 */
	public static function format_ability_for_response( AcrossAI_Custom_Ability_Row $ability ) {
		$response = new \stdClass();

		// Basic fields
		$response->id             = (int) $ability->id;
		$response->ability_slug   = $ability->ability_slug;
		$response->label          = $ability->label;
		$response->description    = $ability->description;
		$response->category       = $ability->category;
		$response->enabled        = (bool) $ability->enabled;

		// Callback configuration
		$response->callback_type   = $ability->callback_type;
		$response->callback_config = $ability->get_callback_config();

		// Permission configuration
		$response->permission_type   = $ability->permission_type;
		$response->permission_config = $ability->get_permission_config();

		// Input/Output schemas
		$response->input_schema  = $ability->get_input_schema();
		$response->output_schema = $ability->get_output_schema();

		// Exposure flags
		$response->show_in_rest = (bool) $ability->show_in_rest;
		$response->show_in_mcp  = (bool) $ability->show_in_mcp;

		// MCP configuration
		$response->mcp_type    = $ability->mcp_type;
		$response->mcp_servers = $ability->get_mcp_servers();

		// Metadata flags (tri-state)
		$response->readonly    = $ability->readonly;
		$response->destructive = $ability->destructive;
		$response->idempotent  = $ability->idempotent;

		// Timestamps (ISO 8601 format)
		$response->created_at = $ability->created_at;
		$response->updated_at = $ability->updated_at;

		/**
		 * Filter REST response before sending.
		 *
		 * @since 0.0.1
		 * @param \stdClass $response Formatted ability response.
		 * @param AcrossAI_Custom_Ability_Row $ability Original ability Row object.
		 */
		return apply_filters(
			'acrossai_custom_ability_rest_response',
			$response,
			$ability
		);
	}

	/**
	 * Format abilities for MCP response.
	 *
	 * Converts ability array to MCP-compatible format based on MCP type.
	 *
	 * @since 0.0.1
	 * @param AcrossAI_Custom_Ability_Row[] $abilities Array of ability Row objects.
	 * @param string                        $mcp_type  MCP type: 'tool', 'resource', or 'prompt'.
	 * @param string|null                   $current_server Current MCP server slug (optional).
	 * @return \stdClass[] Array of MCP-formatted abilities.
	 */
	public static function format_for_mcp( $abilities, $mcp_type, $current_server = null ) {
		$formatted = array();

		foreach ( (array) $abilities as $ability ) {
			if ( ! ( $ability instanceof AcrossAI_Custom_Ability_Row ) ) {
				continue;
			}

			// Filter by MCP type
			if ( $ability->mcp_type !== $mcp_type ) {
				continue;
			}

			// Filter by MCP server (if server specified and list provided)
			$mcp_servers = $ability->get_mcp_servers();
			if ( ! empty( $current_server ) && ! empty( $mcp_servers ) ) {
				if ( ! in_array( $current_server, (array) $mcp_servers, true ) ) {
					continue; // This server is not in the ability's server list
				}
			}

			// Build MCP response object
			$mcp_item = new \stdClass();

			$mcp_item->name        = $ability->ability_slug;
			$mcp_item->description = $ability->description ?: $ability->label;

			// Include input schema for tools/resources
			if ( $ability->get_input_schema() ) {
				$mcp_item->inputSchema = $ability->get_input_schema();
			}

			// Include metadata flags
			if ( null !== $ability->destructive ) {
				$mcp_item->destructive = (bool) $ability->destructive;
			}
			if ( null !== $ability->idempotent ) {
				$mcp_item->idempotent = (bool) $ability->idempotent;
			}

			/**
			 * Filter MCP item before adding to response.
			 *
			 * @since 0.0.1
			 * @param \stdClass $mcp_item Formatted MCP item.
			 * @param AcrossAI_Custom_Ability_Row $ability Original ability Row object.
			 * @param string $mcp_type MCP type.
			 */
			$mcp_item = apply_filters(
				'acrossai_custom_ability_mcp_filter',
				$mcp_item,
				$ability,
				$mcp_type
			);

			if ( $mcp_item ) {
				$formatted[] = $mcp_item;
			}
		}

		return $formatted;
	}

	/**
	 * Format single ability as MCP object.
	 *
	 * @since 0.0.1
	 * @param AcrossAI_Custom_Ability_Row $ability Ability Row object.
	 * @return \stdClass MCP-formatted ability.
	 */
	public static function format_ability_for_mcp( AcrossAI_Custom_Ability_Row $ability ) {
		$mcp_item = new \stdClass();

		$mcp_item->name        = $ability->ability_slug;
		$mcp_item->description = $ability->description ?: $ability->label;

		if ( $ability->get_input_schema() ) {
			$mcp_item->inputSchema = $ability->get_input_schema();
		}

		if ( null !== $ability->destructive ) {
			$mcp_item->destructive = (bool) $ability->destructive;
		}
		if ( null !== $ability->idempotent ) {
			$mcp_item->idempotent = (bool) $ability->idempotent;
		}

		return $mcp_item;
	}
}

<?php
/**
 * Custom Ability Response Formatter
 *
 * Formats custom ability objects for REST API and MCP responses.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Utilities
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Formatter class
 *
 * Static utility: Formats ability data for various output contexts (REST, MCP, admin).
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Formatter {

	/**
	 * Format ability for REST API response
	 *
	 * Converts BerlinDB ability row to REST-compatible object with all 20 fields.
	 * Ensures proper data types, JSON encoding, timestamp formatting.
	 *
	 * @since 1.0.0
	 * @param AcrossAI_Custom_Ability_Row $ability_row Ability row object
	 * @return stdClass|WP_Error Formatted ability object or error
	 */
	public static function format_ability_for_response( $ability_row ) {
		if ( ! $ability_row || ! is_object( $ability_row ) ) {
			return new WP_Error(
				'invalid_ability',
				'Ability must be a valid object',
				array( 'status' => 500 )
			);
		}

		// Ensure JSON fields are properly decoded
		$callback_config = is_array( $ability_row->callback_config ) 
			? $ability_row->callback_config 
			: (array) json_decode( $ability_row->callback_config, true );

		$permission_config = is_array( $ability_row->permission_config ) 
			? $ability_row->permission_config 
			: (array) json_decode( $ability_row->permission_config, true );

		$input_schema = is_array( $ability_row->input_schema ) 
			? $ability_row->input_schema 
			: (array) json_decode( $ability_row->input_schema, true );

		$output_schema = is_array( $ability_row->output_schema ) 
			? $ability_row->output_schema 
			: (array) json_decode( $ability_row->output_schema, true );

		$mcp_servers = is_array( $ability_row->mcp_servers ) 
			? $ability_row->mcp_servers 
			: (array) json_decode( $ability_row->mcp_servers, true );

		// Format all 20 fields
		$response = (object) array(
			'id'                => (int) $ability_row->id,
			'ability_slug'      => (string) $ability_row->ability_slug,
			'label'             => (string) $ability_row->label,
			'description'       => (string) $ability_row->description,
			'category'          => (string) $ability_row->category,
			'enabled'           => (bool) $ability_row->enabled,
			'callback_type'     => (string) $ability_row->callback_type,
			'callback_config'   => $callback_config,
			'permission_type'   => (string) $ability_row->permission_type,
			'permission_config' => $permission_config,
			'input_schema'      => $input_schema,
			'output_schema'     => $output_schema,
			'show_in_rest'      => (bool) $ability_row->show_in_rest,
			'show_in_mcp'       => (bool) $ability_row->show_in_mcp,
			'mcp_type'          => $ability_row->mcp_type ? (string) $ability_row->mcp_type : null,
			'mcp_servers'       => $mcp_servers,
			'readonly'          => $ability_row->readonly !== null ? (int) $ability_row->readonly : null,
			'destructive'       => $ability_row->destructive !== null ? (int) $ability_row->destructive : null,
			'idempotent'        => $ability_row->idempotent !== null ? (int) $ability_row->idempotent : null,
			'created_at'        => self::format_timestamp( $ability_row->created_at ),
			'updated_at'        => self::format_timestamp( $ability_row->updated_at ),
		);

		/**
		 * Filter REST response before returning to client
		 *
		 * Allows plugins to modify response shape or add additional fields.
		 *
		 * @since 1.0.0
		 * @param stdClass                  $response     Formatted response object
		 * @param AcrossAI_Custom_Ability_Row $ability_row Original ability row
		 * @return stdClass Modified response object
		 */
		return apply_filters( 'acrossai_custom_ability_rest_response', $response, $ability_row );
	}

	/**
	 * Format abilities for MCP exposure
	 *
	 * Formats abilities array for MCP (Model Context Protocol) clients.
	 * Filters by show_in_mcp, mcp_type, and mcp_servers.
	 * Returns MCP-compatible format with simplified field set.
	 *
	 * @since 1.0.0
	 * @param array      $abilities Array of ability row objects
	 * @param string     $mcp_type Type to filter (tool|resource|prompt)
	 * @param string|null $current_mcp_server Current MCP server slug (optional)
	 * @return array Array of MCP-formatted ability objects
	 */
	public static function format_for_mcp( $abilities = array(), $mcp_type = 'tool', $current_mcp_server = null ) {
		$formatted = array();

		foreach ( (array) $abilities as $ability ) {
			// Skip if not exposed to MCP
			if ( ! $ability->show_in_mcp ) {
				continue;
			}

			// Skip if mcp_type doesn't match
			if ( $ability->mcp_type !== $mcp_type ) {
				continue;
			}

			// Skip if current server is specified and not in mcp_servers list
			if ( $current_mcp_server && is_array( $ability->mcp_servers ) && ! in_array( $current_mcp_server, $ability->mcp_servers, true ) ) {
				continue;
			}

			// Allow filter to exclude ability from MCP
			$include = apply_filters(
				'acrossai_custom_ability_mcp_filter',
				true,
				$ability,
				$mcp_type,
				$current_mcp_server
			);

			if ( ! $include ) {
				continue;
			}

			// Decode schemas
			$input_schema = is_array( $ability->input_schema ) 
				? $ability->input_schema 
				: json_decode( $ability->input_schema, true );

			// MCP-compatible response format
			$formatted[] = (object) array(
				'name'        => $ability->ability_slug,
				'description' => $ability->description,
				'inputSchema' => $input_schema ?: array(),
				'destructive' => (bool) $ability->destructive,
				'idempotent'  => (bool) $ability->idempotent,
			);
		}

		return $formatted;
	}

	/**
	 * Format timestamp to ISO 8601 format
	 *
	 * Converts MySQL timestamp to ISO 8601 format (e.g., 2026-05-20T10:30:00Z).
	 * Used for REST API responses and MCP compatibility.
	 *
	 * @since 1.0.0
	 * @param string $timestamp MySQL timestamp (e.g., "2026-05-20 10:30:00")
	 * @return string ISO 8601 formatted timestamp or empty string if invalid
	 */
	private static function format_timestamp( $timestamp ) {
		if ( empty( $timestamp ) ) {
			return '';
		}

		try {
			// Parse MySQL timestamp and convert to ISO 8601
			$datetime = new DateTime( $timestamp, new DateTimeZone( 'UTC' ) );
			return $datetime->format( 'c' ); // ISO 8601 format with timezone
		} catch ( Exception $e ) {
			// Return empty string if parsing fails
			return '';
		}
	}

	/**
	 * Format ability summary for admin list display
	 *
	 * Formats ability data for quick display in admin lists/tables.
	 * Truncates description, formats status badges, etc.
	 *
	 * @since 1.0.0
	 * @param AcrossAI_Custom_Ability_Row $ability_row Ability row object
	 * @param int                          $desc_length Max description length (default: 100)
	 * @return array Formatted array for display
	 */
	public static function format_for_admin_display( $ability_row, $desc_length = 100 ) {
		$description = $ability_row->description;
		if ( strlen( $description ) > $desc_length ) {
			$description = substr( $description, 0, $desc_length ) . '…';
		}

		$status = $ability_row->enabled ? 'Enabled' : 'Disabled';
		$status_class = $ability_row->enabled ? 'active' : 'inactive';

		return array(
			'id'              => (int) $ability_row->id,
			'slug'            => (string) $ability_row->ability_slug,
			'label'           => (string) $ability_row->label,
			'description'     => $description,
			'status'          => $status,
			'status_class'    => $status_class,
			'callback_type'   => (string) $ability_row->callback_type,
			'permission_type' => (string) $ability_row->permission_type,
			'show_in_mcp'     => (bool) $ability_row->show_in_mcp,
		);
	}
}

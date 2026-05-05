<?php
/**
 * Per-server MCP tool/resource/prompt filtering.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Runtime;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the MCP adapter's list filters to enforce per-server ability visibility.
 *
 * When the admin configures an ability with "Allow in specific MCP servers",
 * Override_Applier forces meta.mcp.public=true so the MCP adapter registers it
 * as a tool on every server. This class then removes it at request time from
 * servers that are not in the saved allowlist.
 */
class Mcp_Server_Filter {

	/**
	 * Registers all per-server filter hooks.
	 *
	 * Called on `rest_api_init` (priority 5) so filters are in place before
	 * any list handler fires at the default priority.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'mcp_adapter_tools_list', array( __CLASS__, 'filter_tools_list' ), 10, 2 );
		add_filter( 'mcp_adapter_resources_list', array( __CLASS__, 'filter_resources_list' ), 10, 2 );
		add_filter( 'mcp_adapter_prompts_list', array( __CLASS__, 'filter_prompts_list' ), 10, 2 );
		add_filter( 'mcp_adapter_tool_call_result', array( __CLASS__, 'filter_discover_abilities_result' ), 10, 5 );
	}

	/**
	 * Removes tools from the list that are not allowed on the current server.
	 *
	 * Passes through all tools whose ability slug has no server restriction —
	 * only abilities explicitly configured for "specific servers" mode are gated.
	 *
	 * @param array                   $tools  Array of Tool DTOs.
	 * @param \WP\MCP\Core\McpServer  $server The MCP server instance.
	 * @return array Filtered array of Tool DTOs.
	 */
	public static function filter_tools_list( array $tools, $server ): array {
		if ( ! method_exists( $server, 'get_mcp_tool' ) || ! method_exists( $server, 'get_server_id' ) ) {
			return $tools;
		}

		$server_id = $server->get_server_id();
		$filtered  = array();

		foreach ( $tools as $tool_dto ) {
			$mcp_tool = $server->get_mcp_tool( $tool_dto->getName() );

			if ( ! $mcp_tool || ! method_exists( $mcp_tool, 'get_adapter_meta' ) ) {
				$filtered[] = $tool_dto;
				continue;
			}

			$slug = $mcp_tool->get_adapter_meta()['ability'] ?? null;

			if ( null === $slug || ! Override_Applier::has_server_restriction( $slug ) ) {
				$filtered[] = $tool_dto;
				continue;
			}

			if ( Override_Applier::should_expose_to_mcp_server( $slug, $server_id ) ) {
				$filtered[] = $tool_dto;
			}
		}

		return array_values( $filtered );
	}

	/**
	 * Removes resources from the list that are not allowed on the current server.
	 *
	 * @param array                   $resources Array of Resource DTOs.
	 * @param \WP\MCP\Core\McpServer  $server    The MCP server instance.
	 * @return array Filtered array of Resource DTOs.
	 */
	public static function filter_resources_list( array $resources, $server ): array {
		if ( ! method_exists( $server, 'get_mcp_resource' ) || ! method_exists( $server, 'get_server_id' ) ) {
			return $resources;
		}

		$server_id = $server->get_server_id();
		$filtered  = array();

		foreach ( $resources as $resource_dto ) {
			$mcp_resource = $server->get_mcp_resource( $resource_dto->getUri() );

			if ( ! $mcp_resource || ! method_exists( $mcp_resource, 'get_adapter_meta' ) ) {
				$filtered[] = $resource_dto;
				continue;
			}

			$slug = $mcp_resource->get_adapter_meta()['ability'] ?? null;

			if ( null === $slug || ! Override_Applier::has_server_restriction( $slug ) ) {
				$filtered[] = $resource_dto;
				continue;
			}

			if ( Override_Applier::should_expose_to_mcp_server( $slug, $server_id ) ) {
				$filtered[] = $resource_dto;
			}
		}

		return array_values( $filtered );
	}

	/**
	 * Removes prompts from the list that are not allowed on the current server.
	 *
	 * @param array                   $prompts Array of Prompt DTOs.
	 * @param \WP\MCP\Core\McpServer  $server  The MCP server instance.
	 * @return array Filtered array of Prompt DTOs.
	 */
	public static function filter_prompts_list( array $prompts, $server ): array {
		if ( ! method_exists( $server, 'get_mcp_prompt' ) || ! method_exists( $server, 'get_server_id' ) ) {
			return $prompts;
		}

		$server_id = $server->get_server_id();
		$filtered  = array();

		foreach ( $prompts as $prompt_dto ) {
			$mcp_prompt = $server->get_mcp_prompt( $prompt_dto->getName() );

			if ( ! $mcp_prompt || ! method_exists( $mcp_prompt, 'get_adapter_meta' ) ) {
				$filtered[] = $prompt_dto;
				continue;
			}

			$slug = $mcp_prompt->get_adapter_meta()['ability'] ?? null;

			if ( null === $slug || ! Override_Applier::has_server_restriction( $slug ) ) {
				$filtered[] = $prompt_dto;
				continue;
			}

			if ( Override_Applier::should_expose_to_mcp_server( $slug, $server_id ) ) {
				$filtered[] = $prompt_dto;
			}
		}

		return array_values( $filtered );
	}

	/**
	 * Filters the discover-abilities tool result to remove abilities hidden from the current server.
	 *
	 * The mcp-adapter/discover-abilities ability iterates wp_get_abilities() directly and has no
	 * server context, so its output bypasses the tools/list filter above. This callback catches
	 * the tool's result via mcp_adapter_tool_call_result and prunes the same way.
	 *
	 * @param mixed                              $result    Raw execution result (may be WP_Error or array).
	 * @param array                              $args      Tool arguments.
	 * @param string                             $tool_name Name of the tool that was called.
	 * @param \WP\MCP\Domain\Tools\McpTool|null $mcp_tool  The McpTool instance.
	 * @param \WP\MCP\Core\McpServer             $server    The MCP server instance.
	 * @return mixed Filtered result.
	 */
	public static function filter_discover_abilities_result( $result, array $args, string $tool_name, $mcp_tool, $server ) {
		if ( 'mcp-adapter-discover-abilities' !== $tool_name ) {
			return $result;
		}

		if ( ! is_array( $result ) || ! isset( $result['abilities'] ) || ! is_array( $result['abilities'] ) ) {
			return $result;
		}

		if ( ! method_exists( $server, 'get_server_id' ) ) {
			return $result;
		}

		$server_id = $server->get_server_id();

		$result['abilities'] = array_values(
			array_filter(
				$result['abilities'],
				static function ( $entry ) use ( $server_id ): bool {
					$slug = $entry['name'] ?? null;
					if ( null === $slug || ! Override_Applier::has_server_restriction( $slug ) ) {
						return true;
					}
					return Override_Applier::should_expose_to_mcp_server( $slug, $server_id );
				}
			)
		);

		return $result;
	}
}

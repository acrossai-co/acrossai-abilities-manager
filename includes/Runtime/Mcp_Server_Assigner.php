<?php
/**
 * Authoritative MCP server assignment via adapter init hooks.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Runtime;

defined( 'ABSPATH' ) || exit;

/**
 * Assigns abilities to MCP servers at adapter initialization time.
 *
 * Hooks into `mcp_adapter_default_server_config` (which fires during
 * `mcp_adapter_init` when DefaultServerFactory runs at priority 10) to
 * control exactly which resource and prompt abilities appear on the default
 * server, based on the per-ability MCP server settings saved in override rows.
 *
 * Tool abilities are not handled here; they work through `meta.mcp.public` and
 * the `execute-ability` discovery mechanism managed by Override_Applier.
 *
 * For custom / developer servers the response-time filters in Mcp_Server_Filter
 * remain the source of truth, because those servers are created by third-party
 * plugins and cannot be augmented after construction.
 *
 * Execution order within `mcp_adapter_init`:
 *   priority 10 – DefaultServerFactory::create() runs; auto-discovers
 *                 resources and prompts from abilities with meta.mcp.public=true,
 *                 then applies `mcp_adapter_default_server_config` filter.
 *   priority 20 – This class' filter_default_server_config() fires and
 *                 enforces per-server restrictions by removing slugs from
 *                 the config that belong to servers other than the default.
 *   priority 999 – acrossai_abilities_manager_cache_mcp_servers() caches
 *                  the final server list for the admin UI.
 */
class Mcp_Server_Assigner {

	/**
	 * Server ID used by DefaultServerFactory for the built-in default server.
	 *
	 * @see \WP\MCP\Servers\DefaultServerFactory::create()
	 */
	const DEFAULT_SERVER_ID = 'mcp-adapter-default-server';

	/**
	 * Registers the mcp_adapter_default_server_config filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'mcp_adapter_default_server_config', array( __CLASS__, 'filter_default_server_config' ), 20 );
	}

	/**
	 * Enforces per-ability server restrictions on the default server's component lists.
	 *
	 * DefaultServerFactory's auto-discovery adds every ability with
	 * meta.mcp.public=true to resources/prompts. For abilities in "specific
	 * servers" mode, Override_Applier forces meta.mcp.public=true so the
	 * adapter registers them — but they should only appear on the servers in
	 * their explicit allowlist.
	 *
	 * This method:
	 * 1. Removes resources/prompts that have a server restriction that does
	 *    NOT include the default server.
	 * 2. Ensures resources/prompts that are explicitly configured for the
	 *    default server are present in the config (defensive safety-net for
	 *    cases where meta.mcp.public propagation was incomplete).
	 *
	 * @param array<string, mixed> $config Default server configuration passed
	 *                                     through the mcp_adapter_default_server_config filter.
	 * @return array<string, mixed> Modified server configuration.
	 */
	public static function filter_default_server_config( array $config ): array {
		if ( ! isset( $config['resources'] ) || ! is_array( $config['resources'] ) ) {
			$config['resources'] = array();
		}
		if ( ! isset( $config['prompts'] ) || ! is_array( $config['prompts'] ) ) {
			$config['prompts'] = array();
		}

		$server_id = self::DEFAULT_SERVER_ID;

		// Remove resource and prompt abilities that are in "specific servers" mode
		// but do NOT include the default server in their allowlist.
		$should_include = static function ( string $slug ) use ( $server_id ): bool {
			if ( ! Override_Applier::has_server_restriction( $slug ) ) {
				return true;
			}
			return Override_Applier::should_expose_to_mcp_server( $slug, $server_id );
		};

		$config['resources'] = array_values( array_filter( $config['resources'], $should_include ) );
		$config['prompts']   = array_values( array_filter( $config['prompts'], $should_include ) );

		// Defensive pass: ensure any resource/prompt ability explicitly configured
		// for this server is present even if auto-discovery missed it (e.g. because
		// the meta.mcp.public patch did not reach a specific ability class).
		if ( function_exists( 'wp_get_abilities' ) ) {
			foreach ( wp_get_abilities() as $ability ) {
				$slug = $ability->get_name();

				if ( ! Override_Applier::has_server_restriction( $slug ) ) {
					continue;
				}

				if ( ! Override_Applier::should_expose_to_mcp_server( $slug, $server_id ) ) {
					continue;
				}

				$mcp_type = $ability->get_meta()['mcp']['type'] ?? 'tool';

				if ( 'resource' === $mcp_type && ! in_array( $slug, $config['resources'], true ) ) {
					$config['resources'][] = $slug;
				} elseif ( 'prompt' === $mcp_type && ! in_array( $slug, $config['prompts'], true ) ) {
					$config['prompts'][] = $slug;
				}
			}
		}

		return $config;
	}
}

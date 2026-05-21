<?php
/**
 * Custom Ability Callback Executor
 *
 * Stub implementation for custom ability callback execution.
 * Full implementation deferred to v2 (out of scope for v1).
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
 * AcrossAI_Custom_Ability_Callback_Executor class
 *
 * Static utility: Executes custom ability callbacks (v2 TODO).
 *
 * v1 Implementation Status: STUBS ONLY
 * - Callback execution is out of scope for v1
 * - Custom abilities register in WordPress but do not execute
 * - Placeholder for v2 implementation
 * - Methods exist for interface compatibility
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Callback_Executor {

	/**
	 * Execute noop callback
	 *
	 * No-op callback type: placeholder ability with no execution logic.
	 * Used for documentation-only or future-implementation abilities.
	 *
	 * TODO v2: Full implementation when callback execution framework is added.
	 *
	 * @since 1.0.0
	 * @param array $ability Ability row object with callback_config
	 * @param array $input Input arguments for the callback
	 * @return array Result array (success flag + data/error)
	 */
	public static function execute_noop( $ability, $input = array() ) {
		// TODO v2: Implement noop execution
		return array(
			'success' => true,
			'message' => 'Noop callback: no execution',
			'data'    => null,
		);
	}

	/**
	 * Execute filter hook callback
	 *
	 * Filter hook callback type: applies registered filter hook with input.
	 * Executes: apply_filters( $config['hook_name'], $input )
	 *
	 * TODO v2: Full implementation with error handling, audit logging.
	 *
	 * @since 1.0.0
	 * @param array $ability Ability row object with callback_config
	 * @param array $input Input arguments for the filter
	 * @return array Result array (success flag + output)
	 */
	public static function execute_filter_hook( $ability, $input = array() ) {
		// TODO v2: Implement filter hook execution
		// Recommended implementation pattern:
		// 1. Extract hook_name from $ability->callback_config
		// 2. Validate hook_name exists (has registered callbacks)
		// 3. Apply filter: $output = apply_filters( $hook_name, $input )
		// 4. Return { success: true, data: $output }
		// 5. Catch exceptions and return error: { success: false, error: $message }
		// 6. Log execution to audit trail

		return array(
			'success' => false,
			'error'   => 'Filter hook execution not yet implemented (v2 feature)',
			'data'    => null,
		);
	}

	/**
	 * Execute HTTP POST callback
	 *
	 * HTTP POST callback type: sends POST request to external URL.
	 * Uses: wp_remote_post( $config['url'], ... )
	 *
	 * TODO v2: Full implementation with SSRF prevention, audit logging.
	 *
	 * Security considerations for v2:
	 * - Use wp_remote_post() only (not curl/fsockopen)
	 * - WordPress blocks private IPs by default (10.0.0.0/8, 127.0.0.1/8, etc.)
	 * - Enforce timeout: max 30 seconds per request
	 * - Log all remote calls for audit trail
	 * - Handle HTTP errors gracefully
	 *
	 * @since 1.0.0
	 * @param array $ability Ability row object with callback_config
	 * @param array $input Input arguments to send as POST body
	 * @return array Result array (success flag + response)
	 */
	public static function execute_wp_remote_post( $ability, $input = array() ) {
		// TODO v2: Implement HTTP POST execution
		// Recommended implementation pattern:
		// 1. Extract url, method, timeout from $ability->callback_config
		// 2. Validate URL via wp_http_validate_url()
		// 3. Set timeout to min(timeout, 30) seconds
		// 4. Prepare POST body as JSON: json_encode( $input )
		// 5. Call wp_remote_post() with URL + body + timeout
		// 6. Check for errors: is_wp_error( $response )
		// 7. Extract response body: wp_remote_retrieve_body( $response )
		// 8. Log execution: who, when, URL, input size, response status
		// 9. Return { success: true, status: $status, body: $body }
		// 10. On error: return { success: false, error: $message }

		return array(
			'success' => false,
			'error'   => 'HTTP POST execution not yet implemented (v2 feature)',
			'data'    => null,
		);
	}

	/**
	 * Execute generic callback (dispatcher)
	 *
	 * Dispatcher method: routes execution based on callback_type.
	 * Determines which executor method to call based on ability config.
	 *
	 * TODO v2: Call appropriate executor based on callback_type.
	 *
	 * @since 1.0.0
	 * @param array $ability Ability row object with callback_type and callback_config
	 * @param array $input Input arguments for the callback
	 * @return array Result array (success flag + output/error)
	 */
	public static function execute( $ability, $input = array() ) {
		// TODO v2: Implement dispatcher
		// Switch on callback_type:
		// - 'noop': call execute_noop()
		// - 'filter_hook': call execute_filter_hook()
		// - 'wp_remote_post': call execute_wp_remote_post()
		// - other: return error

		return array(
			'success' => false,
			'error'   => 'Callback execution not yet implemented (v2 feature)',
			'data'    => null,
		);
	}
}

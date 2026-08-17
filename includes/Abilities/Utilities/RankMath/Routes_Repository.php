<?php
/**
 * Feature 069 — Rank Math rewrite-route inspection and local previews.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities\RankMath
 * @since      0.0.28
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath;

defined( 'ABSPATH' ) || exit;

/**
 * Static-only helper for the virtual routes Rank Math serves — llms.txt and the
 * sitemap index — plus the persisted rewrite rules that make them resolve.
 *
 * Previews are fetched over HTTP loopback rather than by invoking Rank Math's
 * handlers, which call remove_all_actions() and re-run the main query. Doing that
 * in-process would corrupt any later ability in the same request.
 */
final class Routes_Repository {

	/**
	 * The rewrite pattern Rank Math registers for llms.txt.
	 *
	 * @see seo-by-rank-math/includes/modules/llms/class-llms-txt.php:86
	 */
	public const LLMS_PATTERN = '^llms\.txt$';

	/**
	 * Module slug owning llms.txt.
	 */
	public const LLMS_MODULE = 'llms-txt';

	/**
	 * Private constructor — this class is static-only (DEC-UTILITY-STATIC-ONLY).
	 */
	private function __construct() {}

	/**
	 * Whether a rewrite pattern is present in the persisted rules.
	 *
	 * Reads the stored option rather than $wp_rewrite, because the stored option is
	 * what actually serves requests — a rule present in memory but absent from the
	 * option means the route 404s until rules are flushed.
	 *
	 * @param string $pattern Rewrite regex.
	 * @return array<string,mixed>
	 */
	public static function rewrite_status( string $pattern ): array {
		$rules = get_option( 'rewrite_rules' );
		$rules = is_array( $rules ) ? $rules : array();

		return array(
			'pattern'         => $pattern,
			'present'         => array_key_exists( $pattern, $rules ),
			'target'          => isset( $rules[ $pattern ] ) ? (string) $rules[ $pattern ] : '',
			'total_rules'     => count( $rules ),
			'rules_persisted' => array() !== $rules,
		);
	}

	/**
	 * Fetch the first lines of a site-relative path over HTTP loopback.
	 *
	 * @param string $path  Site-relative path, e.g. '/llms.txt'.
	 * @param int    $lines Maximum lines to return.
	 * @return array<string,mixed>
	 */
	public static function preview( string $path, int $lines = 12 ): array {
		$url      = home_url( $path );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'sslverify'   => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'url'           => $url,
				'fetched'       => false,
				'error'         => $response->get_error_message(),
				'response_code' => 0,
				'lines'         => array(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$all  = preg_split( '/\r\n|\r|\n/', $body );
		$all  = is_array( $all ) ? $all : array();

		return array(
			'url'           => $url,
			'fetched'       => true,
			'response_code' => $code,
			'content_type'  => (string) wp_remote_retrieve_header( $response, 'content-type' ),
			'total_lines'   => count( $all ),
			'lines'         => array_slice( $all, 0, max( 1, $lines ) ),
		);
	}

	/**
	 * llms.txt module state, settings, rewrite status and a live preview.
	 *
	 * @param int $preview_lines Lines of live output to include.
	 * @return array<string,mixed>
	 */
	public static function llms_status( int $preview_lines = 12 ): array {
		$general = array();
		if ( class_exists( '\RankMath\Helper' ) ) {
			$stored  = \RankMath\Helper::get_settings( 'general' );
			$general = is_array( $stored ) ? $stored : array();
		}

		$active = class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_module_active( self::LLMS_MODULE );

		return array(
			'module'        => self::LLMS_MODULE,
			'module_active' => $active,
			'route_url'     => home_url( '/llms.txt' ),
			'rewrite'       => self::rewrite_status( self::LLMS_PATTERN ),
			'settings'      => array(
				'llms_post_types'    => $general['llms_post_types'] ?? array(),
				'llms_taxonomies'    => $general['llms_taxonomies'] ?? array(),
				'llms_limit'         => isset( $general['llms_limit'] ) ? (int) $general['llms_limit'] : null,
				'llms_extra_content' => isset( $general['llms_extra_content'] ) ? (string) $general['llms_extra_content'] : '',
			),
			// Only fetch when the module is on; otherwise the request is a
			// guaranteed 404 and costs a round-trip for nothing.
			'live_preview'  => $active ? self::preview( '/llms.txt', $preview_lines ) : null,
		);
	}

	/**
	 * Check the llms.txt rewrite rule and flush only when it is missing.
	 *
	 * The diagnosis is the value here: the plugin already ships a generic
	 * acrossai/flush-rewrite-rules. This reports whether the rule was actually
	 * absent and whether flushing fixed it.
	 *
	 * @return array<string,mixed>
	 */
	public static function refresh_llms_route(): array {
		$before = self::rewrite_status( self::LLMS_PATTERN );

		if ( $before['present'] ) {
			return array(
				'rule_present_before' => true,
				'flushed'             => false,
				'rule_present_after'  => true,
			);
		}

		// Deleting the option makes WordPress regenerate rules on the next
		// request, which is the same mechanism Rank Math uses itself.
		delete_option( 'rewrite_rules' );
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		$after = self::rewrite_status( self::LLMS_PATTERN );

		return array(
			'rule_present_before' => false,
			'flushed'             => true,
			'rule_present_after'  => (bool) $after['present'],
		);
	}
}

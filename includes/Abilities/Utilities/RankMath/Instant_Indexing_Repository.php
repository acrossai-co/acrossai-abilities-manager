<?php
/**
 * Feature 069 — Rank Math Instant Indexing (IndexNow) access.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities\RankMath
 * @since      0.0.28
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath;

defined( 'ABSPATH' ) || exit;

/**
 * Static-only accessor for Rank Math's IndexNow API.
 *
 * Batch 2 needs only key_location(); submit / log / clear / reset land in Batch 3.
 * It exists this early so no ability class has to name a \RankMath\* symbol
 * (FR-015), which Test_Rank_Math_* asserts mechanically.
 *
 * \RankMath\Instant_Indexing\Api::get() is a static singleton accessor; the
 * methods on it are instance methods.
 */
final class Instant_Indexing_Repository {

	/**
	 * Private constructor — this class is static-only (DEC-UTILITY-STATIC-ONLY).
	 */
	private function __construct() {}

	/**
	 * The derived IndexNow key URL.
	 *
	 * Computed by Api::get_key_location() from home_url() plus the key, so it is
	 * never stored and never writable.
	 *
	 * @return string Empty string when Instant Indexing is unavailable.
	 */
	public static function key_location(): string {
		$api = self::api();
		if ( null === $api || ! method_exists( $api, 'get_key_location' ) ) {
			return '';
		}
		return (string) $api->get_key_location();
	}

	/**
	 * The Instant Indexing API instance, or null when unavailable.
	 *
	 * @return object|null
	 */
	private static function api(): ?object {
		if ( ! class_exists( '\RankMath\Instant_Indexing\Api' ) || ! method_exists( '\RankMath\Instant_Indexing\Api', 'get' ) ) {
			return null;
		}
		$api = \RankMath\Instant_Indexing\Api::get();
		return is_object( $api ) ? $api : null;
	}
}

<?php
/**
 * Feature 069 — Rank Math sitemap live state.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\RankMath
 * @since      0.0.28
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\RankMath;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Sitemap_Repository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Ability #57 — rank-math/get-sitemap-status.
 *
 * Complements rank-math/get-settings panel=sitemap-*, which returns
 * configuration. This returns LIVE state: is the module on, is the rewrite rule
 * actually persisted, does the index URL respond, is the file cache on. A sitemap
 * can be perfectly configured and still 404 if rules were never flushed.
 *
 * Read-only, idempotent.
 */
class Get_Sitemap_Status extends Base_Rank_Math_Ability {

	protected function slug(): string {
		return 'get-sitemap-status';
	}

	protected function ability_label(): string {
		return __( 'Get Rank Math Sitemap Status', 'acrossai-abilities-manager' );
	}

	protected function ability_description(): string {
		return __( 'Return live Rank Math sitemap state: module status, which post types and taxonomies are included, whether the sitemap_index.xml rewrite rule is actually persisted, whether the file cache is enabled, and a live fetch of the index. Use this when the sitemap 404s despite looking correctly configured — a missing rewrite rule is the usual cause, fixable with rank-math/set-module-state.', 'acrossai-abilities-manager' );
	}

	protected function sub_group(): string {
		return 'rank-math-sitemap';
	}

	protected function rank_math_cap(): string {
		return 'sitemap';
	}

	protected function input_properties(): array {
		return array();
	}

	protected function output_properties(): array {
		return array(
			'module'        => array( 'type' => 'string' ),
			'module_active' => array( 'type' => 'boolean' ),
			'index_url'     => array( 'type' => 'string' ),
			'rewrite'       => array( 'type' => 'object' ),
			'cache_enabled' => array( 'type' => 'boolean' ),
			'post_types'    => array( 'type' => 'array' ),
			'taxonomies'    => array( 'type' => 'array' ),
			'general'       => array( 'type' => 'object' ),
			'index_check'   => array( 'type' => array( 'object', 'null' ) ),
		);
	}

	protected function required_input(): array {
		return array();
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>|WP_Error
	 */
	protected function run( array $input ) {
		$status = Sitemap_Repository::status();

		$status['message'] = $status['module_active']
			? __( 'Returned live Rank Math sitemap status.', 'acrossai-abilities-manager' )
			: __( 'The Rank Math sitemap module is inactive, so no sitemap is served. Enable it with rank-math/set-module-state.', 'acrossai-abilities-manager' );

		return $status;
	}
}

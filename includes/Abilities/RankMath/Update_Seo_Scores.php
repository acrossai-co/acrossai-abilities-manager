<?php
/**
 * Feature 069 — write Rank Math SEO scores.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\RankMath
 * @since      0.0.28
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\RankMath;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Post_Meta_Repository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Ability #36 — rank-math/update-seo-scores.
 *
 * Rank Math's on-page scoring engine runs client-side only, so an agent that computes
 * scores via rank-math/analyze-post-content has nowhere to store them. This is that
 * missing write.
 *
 * Rank Math's own handler silently skips missing posts and out-of-range values, so
 * updated/skipped are computed here with a reason per skipped row.
 */
class Update_Seo_Scores extends Base_Rank_Math_Ability {

	protected function slug(): string {
		return 'update-seo-scores';
	}

	protected function ability_label(): string {
		return __( 'Update Rank Math SEO Scores', 'acrossai-abilities-manager' );
	}

	protected function ability_description(): string {
		return __( 'Store Rank Math SEO scores for a batch of posts. Rank Math computes scores client-side only, so this is how a score derived from its rank-math/analyze-post-content ability gets persisted and becomes visible in score filters and reports. Scores must be 0-100; rows that are missing, unauthorised or out of range are reported as skipped with a reason.', 'acrossai-abilities-manager' );
	}

	protected function sub_group(): string {
		return 'rank-math-content';
	}

	/**
	 * Rank Math gates its own score endpoint on per-post edit rights rather than a
	 * granular capability, so there is nothing to compose onto the floor.
	 */
	protected function rank_math_cap(): string {
		return '';
	}

	protected function input_properties(): array {
		return array(
			'scores' => array(
				'type'                 => 'object',
				'description'          => __( 'Post id => score from 0 to 100.', 'acrossai-abilities-manager' ),
				'additionalProperties' => true,
			),
		);
	}

	protected function output_properties(): array {
		return array(
			'updated' => array( 'type' => 'array' ),
			'skipped' => array( 'type' => 'array' ),
		);
	}

	protected function required_input(): array {
		return array( 'scores' );
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => false, 'idempotent' => true );
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>|WP_Error
	 */
	protected function run( array $input ) {
		if ( ! isset( $input['scores'] ) || ! is_array( $input['scores'] ) ) {
			return new WP_Error( 'invalid_input', __( 'scores must be an object keyed by post id.', 'acrossai-abilities-manager' ) );
		}

		$result = Post_Meta_Repository::update_seo_scores( $input['scores'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['message'] = sprintf(
			/* translators: 1: number of scores written, 2: number skipped */
			__( 'Wrote %1$d SEO scores, skipped %2$d.', 'acrossai-abilities-manager' ),
			count( $result['updated'] ),
			count( $result['skipped'] )
		);

		return $result;
	}
}

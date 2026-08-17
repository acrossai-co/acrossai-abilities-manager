<?php
/**
 * Feature 069 — audit content-level Rank Math SEO completeness.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\RankMath
 * @since      0.0.28
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\RankMath;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Content_Audit_Repository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Ability #48 — acrossai/rank-math-audit-content-seo.
 *
 * Complements rather than duplicates Rank Math core: its rank-math/audit-site-seo runs
 * TECHNICAL site-wide tests, and its rank-math/get-seo-scores returns scores and grades
 * only. Neither reports which specific SEO fields are missing across a set of posts.
 *
 * With only_issues=false this returns every post with its metadata, which is why the
 * suite ships no separate bulk metadata reader.
 *
 * Read-only, idempotent.
 */
class Audit_Content_Seo extends Base_Rank_Math_Ability {

	protected function slug(): string {
		return 'audit-content-seo';
	}

	protected function ability_label(): string {
		return __( 'Audit Rank Math Content SEO', 'acrossai-abilities-manager' );
	}

	protected function ability_description(): string {
		return __( 'Find content with incomplete Rank Math SEO: missing title, description or focus keyword, a noindex directive, a score below a threshold, no schema, or no inbound internal links. Returns per-post issues plus a count per issue type. Set only_issues to false to list every post with its metadata instead, which doubles as a bulk metadata read. Complements Rank Math\'s own rank-math/audit-site-seo, which tests the site technically rather than per post.', 'acrossai-abilities-manager' );
	}

	protected function sub_group(): string {
		return 'rank-math-content';
	}

	protected function rank_math_cap(): string {
		return 'onpage_general';
	}

	protected function input_properties(): array {
		return array(
			'post_types'      => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => __( 'Post types to audit. Defaults to post and page.', 'acrossai-abilities-manager' ),
			),
			'post_statuses'   => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => __( 'Statuses to audit. Defaults to publish.', 'acrossai-abilities-manager' ),
			),
			'per_page'        => array( 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ),
			'page'            => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'search'          => array( 'type' => 'string' ),
			'score_below'     => array(
				'type'        => 'integer',
				'default'     => 70,
				'minimum'     => 0,
				'maximum'     => 100,
				'description' => __( 'Flag posts whose stored score is below this. Never-scored posts are not flagged as low-scoring.', 'acrossai-abilities-manager' ),
			),
			'include_schema'  => array( 'type' => 'boolean', 'default' => true ),
			'include_inbound' => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Also check inbound internal links. Off by default because it scans site content and is slower.', 'acrossai-abilities-manager' ),
			),
			'only_issues'     => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Return only posts with problems. Set false to list everything with its metadata.', 'acrossai-abilities-manager' ),
			),
		);
	}

	protected function output_properties(): array {
		return array(
			'items'  => array( 'type' => 'array' ),
			'total'  => array( 'type' => 'integer' ),
			'page'   => array( 'type' => 'integer' ),
			'pages'  => array( 'type' => 'integer' ),
			'counts' => array( 'type' => 'object' ),
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
		$result = Content_Audit_Repository::audit( $input );

		$flagged = array_sum( $result['counts'] );

		$result['message'] = 0 === $flagged
			? sprintf(
				/* translators: %d: number of posts examined */
				__( 'Examined %d posts and found no Rank Math SEO gaps.', 'acrossai-abilities-manager' ),
				$result['total']
			)
			: sprintf(
				/* translators: 1: number of posts returned, 2: total issues found */
				__( 'Returned %1$d posts with %2$d Rank Math SEO issues between them.', 'acrossai-abilities-manager' ),
				count( $result['items'] ),
				$flagged
			);

		return $result;
	}
}

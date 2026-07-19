<?php
/**
 * Feature 055 — WP_Query-backed content search.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\ContentSearch
 * @since      0.0.13
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\ContentSearch;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Search post-type items by keyword. Backed by WP_Query `s=` (LIKE-based
 * title + content search). Score is a simple 0/1 relevance stub — WP core's
 * search doesn't produce ranked scores natively.
 */
class Content_Search_Items extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array<string,mixed>
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/content-search-items',
			'args' => array(
				'label'               => __( 'Search Content Items', 'acrossai-abilities-manager' ),
				'description'         => __( 'Search post-type items by keyword. Backed by WP_Query `s=` (LIKE-based title + content search). Score is a simple 0/1 relevance stub — WP core\'s search does not produce ranked scores natively.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'query'      => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'per_page'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
						'page'       => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'post_types' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'             => array( 'query' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'items'   => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
						'page'    => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'content-search',
						'sub_group'       => 'search',
						'sub_group_label' => __( 'Search', 'acrossai-abilities-manager' ),
					),
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string,mixed> $input Ability input payload.
	 * @return array<string,mixed>
	 */
	public function execute( array $input = array() ): array {
		$query      = trim( (string) ( $input['query'] ?? '' ) );
		$per_page   = max( 1, min( 100, (int) ( $input['per_page'] ?? 20 ) ) );
		$page       = max( 1, (int) ( $input['page'] ?? 1 ) );
		$post_types = array_values( array_filter( array_map( 'sanitize_key', (array) ( $input['post_types'] ?? array( 'post', 'page' ) ) ) ) );

		if ( '' === $query ) {
			return array(
				'success' => false,
				'message' => __( 'query is required.', 'acrossai-abilities-manager' ),
			);
		}

		$wp_query = new \WP_Query(
			array(
				's'              => $query,
				'post_type'      => array() === $post_types ? array( 'post', 'page' ) : $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
			)
		);

		$items = array();
		foreach ( $wp_query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$items[] = array(
				'id'      => (int) $post->ID,
				'title'   => (string) $post->post_title,
				'url'     => (string) get_permalink( $post ),
				'excerpt' => wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 40 ),
				'score'   => 1.0,
			);
		}

		return array(
			'success' => true,
			'items'   => $items,
			'total'   => (int) $wp_query->found_posts,
			'page'    => $page,
			/* translators: 1: match count, 2: query */
			'message' => sprintf( __( '%1$d match(es) for "%2$s".', 'acrossai-abilities-manager' ), (int) $wp_query->found_posts, $query ),
		);
	}
}

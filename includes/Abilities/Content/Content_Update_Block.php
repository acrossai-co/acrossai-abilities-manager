<?php
/**
 * Feature 055 — surgically update a single block inside a post.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Content
 * @since      0.0.13
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Content;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Parse a post's block tree, find a single block by client-supplied id
 * (either the `data-block` attribute or a matching `blockName`+`index`
 * fallback), merge new `attributes`, replace `innerHTML`, and save the
 * post with the serialised block tree.
 *
 * Bounded scope: matches only top-level (non-nested) blocks by index.
 * Nested block editing is deferred to a future spec.
 */
class Content_Update_Block extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array<string,mixed>
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/content-update-block',
			'args' => array(
				'label'               => __( 'Update Block', 'acrossai-abilities-manager' ),
				'description'         => __( 'Parse a post\'s block tree, find one top-level block by 0-based index (block_index) or by (block_name, occurrence), merge the supplied attributes, replace innerHTML, and save the post. Only top-level blocks are matched; nested-block editing is not supported by this ability.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'edit_posts' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'block_index' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'block_name'  => array( 'type' => 'string' ),
						'occurrence'  => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'attributes'  => array( 'type' => 'object' ),
						'inner_html'  => array( 'type' => 'string' ),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
						'block'   => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'core',
						'sub_group'       => 'posts',
						'sub_group_label' => __( 'Posts', 'acrossai-abilities-manager' ),
					),
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
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
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid post_id is required.', 'acrossai-abilities-manager' ),
			);
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'acrossai-abilities-manager' ),
			);
		}

		$blocks = parse_blocks( (string) $post->post_content );
		if ( ! is_array( $blocks ) || array() === $blocks ) {
			return array(
				'success' => false,
				'message' => __( 'Post contains no parseable blocks.', 'acrossai-abilities-manager' ),
			);
		}

		$target_index = null;
		if ( isset( $input['block_index'] ) ) {
			$target_index = (int) $input['block_index'];
			if ( ! isset( $blocks[ $target_index ] ) ) {
				return array(
					'success' => false,
					/* translators: %d: block index */
					'message' => sprintf( __( 'Block index %d out of range.', 'acrossai-abilities-manager' ), $target_index ),
				);
			}
		} else {
			$block_name = (string) ( $input['block_name'] ?? '' );
			$occurrence = (int) ( $input['occurrence'] ?? 0 );
			if ( '' === $block_name ) {
				return array(
					'success' => false,
					'message' => __( 'One of block_index or block_name is required.', 'acrossai-abilities-manager' ),
				);
			}
			$hits = 0;
			foreach ( $blocks as $idx => $block ) {
				if ( ( $block['blockName'] ?? '' ) === $block_name ) {
					if ( $hits === $occurrence ) {
						$target_index = (int) $idx;
						break;
					}
					++$hits;
				}
			}
			if ( null === $target_index ) {
				return array(
					'success' => false,
					/* translators: 1: block name, 2: occurrence */
					'message' => sprintf( __( 'No top-level block "%1$s" at occurrence %2$d.', 'acrossai-abilities-manager' ), $block_name, $occurrence ),
				);
			}
		}

		if ( isset( $input['attributes'] ) && is_array( $input['attributes'] ) ) {
			$blocks[ $target_index ]['attrs'] = array_merge( (array) ( $blocks[ $target_index ]['attrs'] ?? array() ), $input['attributes'] );
		}
		if ( isset( $input['inner_html'] ) ) {
			$blocks[ $target_index ]['innerHTML']    = (string) $input['inner_html'];
			$blocks[ $target_index ]['innerContent'] = array( (string) $input['inner_html'] );
		}

		$new_content = serialize_blocks( $blocks );
		$updated     = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return array(
				'success' => false,
				'message' => $updated->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'block'   => $blocks[ $target_index ],
			/* translators: 1: index, 2: post ID */
			'message' => sprintf( __( 'Updated block #%1$d on post #%2$d.', 'acrossai-abilities-manager' ), $target_index, $post_id ),
		);
	}
}

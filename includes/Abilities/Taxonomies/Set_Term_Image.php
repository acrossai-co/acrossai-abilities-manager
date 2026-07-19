<?php
/**
 * Feature 055 — set a "term image" via _thumbnail_id term-meta.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Taxonomies
 * @since      0.0.13
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Taxonomies;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Attach (or clear) an attachment as the "image" of a term.
 *
 * Persists the attachment id in the term-meta key `_thumbnail_id` — the same
 * key WooCommerce and many theme frameworks use. Passing `attachment_id=0`
 * (or null) clears the meta row.
 */
class Set_Term_Image extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array<string,mixed>
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/taxonomy-set-term-image',
			'args' => array(
				'label'               => __( 'Set Term Image', 'acrossai-abilities-manager' ),
				'description'         => __( 'Attach (or clear) an attachment as the "image" of a term by writing the term-meta key _thumbnail_id. Matches the convention used by WooCommerce and most theme frameworks. Pass attachment_id=0 to clear.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-taxonomies',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'term_id'       => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'attachment_id' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
					),
					'required'             => array( 'term_id', 'attachment_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'       => array( 'type' => 'boolean' ),
						'term_id'       => array( 'type' => 'integer' ),
						'attachment_id' => array( 'type' => 'integer' ),
						'message'       => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'core',
						'sub_group'       => 'terms',
						'sub_group_label' => __( 'Terms', 'acrossai-abilities-manager' ),
					),
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => false,
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
		$term_id       = (int) ( $input['term_id'] ?? 0 );
		$attachment_id = (int) ( $input['attachment_id'] ?? 0 );

		if ( $term_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid term_id is required.', 'acrossai-abilities-manager' ),
			);
		}

		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return array(
				'success' => false,
				/* translators: %d: term ID */
				'message' => sprintf( __( 'Term #%d does not exist.', 'acrossai-abilities-manager' ), $term_id ),
			);
		}

		if ( $attachment_id > 0 ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
				return array(
					'success' => false,
					/* translators: %d: attachment ID */
					'message' => sprintf( __( 'Attachment #%d does not exist.', 'acrossai-abilities-manager' ), $attachment_id ),
				);
			}
			update_term_meta( $term_id, '_thumbnail_id', $attachment_id );
			return array(
				'success'       => true,
				'term_id'       => $term_id,
				'attachment_id' => $attachment_id,
				/* translators: 1: term ID, 2: attachment ID */
				'message'       => sprintf( __( 'Set term #%1$d image to attachment #%2$d.', 'acrossai-abilities-manager' ), $term_id, $attachment_id ),
			);
		}

		delete_term_meta( $term_id, '_thumbnail_id' );
		return array(
			'success'       => true,
			'term_id'       => $term_id,
			'attachment_id' => 0,
			/* translators: %d: term ID */
			'message'       => sprintf( __( 'Cleared image on term #%d.', 'acrossai-abilities-manager' ), $term_id ),
		);
	}
}

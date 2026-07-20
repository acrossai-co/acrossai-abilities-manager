<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Media
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Media;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Delete_Media ability class (absorbed).
 */
class Delete_Media extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/delete-media',
			'args' => array(
				'label'               => __( 'Delete Media', 'acrossai-abilities-manager' ),
				'description'         => __( 'Permanently delete a media attachment via DELETE /wp/v2/media/{id}. Attachments do not support trash.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-media',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'deleted' => array( 'type' => 'boolean' ),
						'media'   => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'media',
						'sub_group'       => 'manage',
						'sub_group_label' => __( 'Manage', 'acrossai-abilities-manager' ),
					),
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array $input Ability input payload.
	 * @return array
	 */
	public function execute( array $input = array() ): array {
		$id = (int) ( $input['id'] ?? 0 );
		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid id is required.', 'acrossai-abilities-manager' ),
			);
		}

		$post = get_post( $id );
		if ( ! ( $post instanceof \WP_Post ) || 'attachment' !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Attachment not found.', 'acrossai-abilities-manager' ),
			);
		}

		$snapshot = Media_Formatter::to_array( $post );

		// wp_delete_attachment (NOT wp_delete_post) removes the file from disk
		// and cleans up intermediate image sizes too. force=true matches the
		// REST controller, which always hard-deletes attachments.
		$deleted = wp_delete_attachment( $id, true );
		if ( ! $deleted ) {
			return Media_Formatter::error_from(
				false,
				/* translators: %d: attachment ID */
				sprintf( __( 'Could not delete attachment #%d.', 'acrossai-abilities-manager' ), $id )
			);
		}

		return array(
			'success' => true,
			'deleted' => true,
			'media'   => $snapshot,
			/* translators: %d: attachment ID */
			'message' => sprintf( __( 'Deleted attachment #%d.', 'acrossai-abilities-manager' ), $id ),
		);
	}
}

<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Content
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Content;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Update_Page ability class (absorbed).
 */
class Update_Page extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/update-page',
			'args' => array(
				'label'               => __( 'Update Page', 'acrossai-abilities-manager' ),
				'description'         => __( 'Update an existing page (post_type=page) via wp_update_post(). Only the supplied fields are changed.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'         => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'status'     => array( 'type' => 'string' ),
						'parent'     => array( 'type' => 'integer' ),
						'menu_order' => array( 'type' => 'integer' ),
						'slug'       => array( 'type' => 'string' ),
						'meta'       => array( 'type' => 'object' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'page'    => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'core',
						'sub_group'       => 'pages',
						'sub_group_label' => __( 'Pages', 'acrossai-abilities-manager' ),
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
	 * @param array $input Ability input payload.
	 * @return array
	 */
	public function execute( array $input = array() ): array {
		$id   = (int) ( $input['id'] ?? 0 );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || 'page' !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Page not found.', 'acrossai-abilities-manager' ),
			);
		}

		$args = array( 'ID' => $id );
		if ( isset( $input['title'] ) ) {
			$args['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$args['post_content'] = (string) $input['content'];
		}
		if ( isset( $input['status'] ) ) {
			$args['post_status'] = sanitize_key( (string) $input['status'] );
		}
		if ( isset( $input['parent'] ) ) {
			$args['post_parent'] = (int) $input['parent'];
		}
		if ( isset( $input['menu_order'] ) ) {
			$args['menu_order'] = (int) $input['menu_order'];
		}
		if ( isset( $input['slug'] ) ) {
			$args['post_name'] = sanitize_title( (string) $input['slug'] );
		}
		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$args['meta_input'] = $input['meta'];
		}

		$result = wp_update_post( $args, true );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'id'      => (int) $result,
			'page'    => (array) get_post( (int) $result, ARRAY_A ),
			/* translators: %d: page ID */
			'message' => sprintf( __( 'Updated page #%d.', 'acrossai-abilities-manager' ), $result ),
		);
	}
}

<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\FileManager
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\FileManager;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

/**
 * File_Delete ability class (absorbed).
 */
class File_Delete extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/file-delete',
			'args' => array(
				'label'               => __( 'Delete File', 'acrossai-abilities-manager' ),
				'description'         => __( 'Deletes a file within the WordPress installation. Path must be relative to ABSPATH.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path' => array(
							'type'        => 'string',
							'description' => __( 'File path relative to ABSPATH.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'path' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'message' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'file-manager',
						'sub_group'       => 'files',
						'sub_group_label' => __( 'Files', 'acrossai-abilities-manager' ),
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
		$blocked = File_Mods_Guard::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}

		$rel_path = sanitize_text_field( $input['path'] ?? '' );
		$base     = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$real     = realpath( $base . '/' . ltrim( $rel_path, '/' ) );

		if ( false === $real || 0 !== strpos( $real, $base . '/' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'acrossai-abilities-manager' ),
			);
		}

		if ( ! is_file( $real ) ) {
			return array(
				'success' => false,
				'message' => __( 'File does not exist.', 'acrossai-abilities-manager' ),
			);
		}

		if ( ! wp_delete_file( $real ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not delete file.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'File deleted.', 'acrossai-abilities-manager' ),
		);
	}
}

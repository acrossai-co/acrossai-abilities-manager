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
 * File_Create ability class (absorbed).
 */
class File_Create extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/file-create',
			'args' => array(
				'label'               => __( 'Create File', 'acrossai-abilities-manager' ),
				'description'         => __( 'Creates a new file within the WordPress installation. Fails if the file already exists. Path must be relative to ABSPATH. Pass create_dirs=true to auto-create any missing parent directories.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path'        => array(
							'type'        => 'string',
							'description' => __( 'File path relative to ABSPATH.', 'acrossai-abilities-manager' ),
						),
						'content'     => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Initial file content.', 'acrossai-abilities-manager' ),
						),
						'create_dirs' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'If true, missing parent directories are created (wp_mkdir_p) before the file is written. Defaults to false.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'path' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'path'    => array( 'type' => 'string' ),
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
	 * @param array $input Ability input payload.
	 * @return array
	 */
	public function execute( array $input = array() ): array {
		$blocked = File_Mods_Guard::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}

		$rel_path    = sanitize_text_field( $input['path'] ?? '' );
		$content     = $input['content'] ?? '';
		$create_dirs = ! empty( $input['create_dirs'] );
		$base        = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs_path    = $base . '/' . ltrim( $rel_path, '/' );
		$parent_want = dirname( $abs_path );
		$parent      = realpath( $parent_want );

		if ( false === $parent ) {
			if ( ! $create_dirs ) {
				return array(
					'success' => false,
					'message' => __( 'Parent directory does not exist. Pass create_dirs=true to create it.', 'acrossai-abilities-manager' ),
				);
			}
			if ( 0 !== strpos( $parent_want, $base . '/' ) ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid or disallowed file path.', 'acrossai-abilities-manager' ),
				);
			}
			if ( ! wp_mkdir_p( $parent_want ) ) {
				return array(
					'success' => false,
					'message' => __( 'Could not create parent directories.', 'acrossai-abilities-manager' ),
				);
			}
			$parent = realpath( $parent_want );
		}

		if ( false === $parent || 0 !== strpos( $parent, $base . '/' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'acrossai-abilities-manager' ),
			);
		}

		if ( file_exists( $abs_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'File already exists. Use file-edit to overwrite.', 'acrossai-abilities-manager' ),
			);
		}

		$result = file_put_contents( $abs_path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create file.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success' => true,
			'path'    => $abs_path,
			'message' => __( 'File created.', 'acrossai-abilities-manager' ),
		);
	}
}

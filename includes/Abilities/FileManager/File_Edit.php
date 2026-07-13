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
 * File_Edit ability class (absorbed).
 */
class File_Edit extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/file-edit',
			'args' => array(
				'label'               => __( 'Create or Overwrite File', 'acrossai-abilities-manager' ),
				'description'         => __( 'Creates a new file or overwrites an existing one within the WordPress installation. Parent directory must already exist. Path must be relative to ABSPATH.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path'    => array(
							'type'        => 'string',
							'description' => __( 'File path relative to ABSPATH.', 'acrossai-abilities-manager' ),
						),
						'content' => array(
							'type'        => 'string',
							'description' => __( 'New file content.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'path', 'content' ),
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
		$content  = $input['content'] ?? '';
		$base     = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs_path = $base . '/' . ltrim( $rel_path, '/' );
		$parent   = realpath( dirname( $abs_path ) );

		if ( false === $parent || ( $parent !== $base && 0 !== strpos( $parent, $base . '/' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'acrossai-abilities-manager' ),
			);
		}

		$result = file_put_contents( $abs_path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write file.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success' => true,
			'path'    => $abs_path,
			'message' => __( 'File saved.', 'acrossai-abilities-manager' ),
		);
	}
}

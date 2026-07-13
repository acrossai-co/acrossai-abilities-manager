<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Themes
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Themes;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Theme_Files_Edit ability class (absorbed).
 */
class Theme_Files_Edit extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/theme-files-edit',
			'args' => array(
				'label'               => __( 'Create or Overwrite Theme File', 'acrossai-abilities-manager' ),
				'description'         => __( 'Creates a new file or overwrites an existing one inside a theme directory. Parent directory must already exist. Defaults to the active theme.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-themes',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'theme_slug' => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Theme folder name. Defaults to the active theme.', 'acrossai-abilities-manager' ),
						),
						'file_path'  => array(
							'type'        => 'string',
							'description' => __( 'File path relative to the theme root.', 'acrossai-abilities-manager' ),
						),
						'path'       => array(
							'type'        => 'string',
							'description' => __( 'Alias for "file_path". If both are provided, "file_path" wins.', 'acrossai-abilities-manager' ),
						),
						'content'    => array(
							'type'        => 'string',
							'description' => __( 'New file content.', 'acrossai-abilities-manager' ),
						),
					),
					'allOf'                => array(
						array( 'required' => array( 'content' ) ),
						array(
							'anyOf' => array(
								array( 'required' => array( 'file_path' ) ),
								array( 'required' => array( 'path' ) ),
							),
						),
					),
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
						'tab_group'       => 'themes',
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

		$slug       = sanitize_text_field( $input['theme_slug'] ?? '' );
		$raw_file   = ! empty( $input['file_path'] ) ? $input['file_path'] : ( $input['path'] ?? '' );
		$rel_file   = sanitize_text_field( (string) $raw_file );
		$content    = $input['content'] ?? '';
		$themes_dir = rtrim( get_theme_root(), '/' );
		$theme_dir  = '' !== $slug
			? realpath( $themes_dir . '/' . $slug )
			: realpath( get_stylesheet_directory() );

		if ( false === $theme_dir || 0 !== strpos( $theme_dir, $themes_dir ) || ! is_dir( $theme_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'Theme directory not found.', 'acrossai-abilities-manager' ),
			);
		}

		$abs_file = $theme_dir . '/' . ltrim( $rel_file, '/' );
		$parent   = realpath( dirname( $abs_file ) );

		if ( false === $parent || ( $parent !== $theme_dir && 0 !== strpos( $parent, $theme_dir . '/' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'File path is not within the theme directory.', 'acrossai-abilities-manager' ),
			);
		}

		$result = file_put_contents( $abs_file, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write file.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success' => true,
			'path'    => $abs_file,
			'message' => __( 'Theme file saved.', 'acrossai-abilities-manager' ),
		);
	}
}

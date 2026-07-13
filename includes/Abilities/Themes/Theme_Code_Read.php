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

defined( 'ABSPATH' ) || exit;

/**
 * Theme_Code_Read ability class (absorbed).
 */
class Theme_Code_Read extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/theme-code-read',
			'args' => array(
				'label'               => __( 'Read Theme Code', 'acrossai-abilities-manager' ),
				'description'         => __( 'Reads the contents of a file inside a theme directory. Defaults to the active theme.', 'acrossai-abilities-manager' ),
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
							'description' => __( 'File path relative to the theme root (e.g. functions.php).', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'file_path' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'content' => array( 'type' => 'string' ),
						'path'    => array( 'type' => 'string' ),
						'size'    => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
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
	 * @param array $input Ability input payload.
	 * @return array
	 */
	public function execute( array $input = array() ): array {
		$slug       = sanitize_text_field( $input['theme_slug'] ?? '' );
		$rel_file   = sanitize_text_field( $input['file_path'] ?? '' );
		$themes_dir = rtrim( get_theme_root(), '/' );
		$theme_dir  = '' !== $slug
			? realpath( $themes_dir . '/' . $slug )
			: realpath( get_stylesheet_directory() );

		if ( false === $theme_dir || 0 !== strpos( $theme_dir, $themes_dir . '/' ) || ! is_dir( $theme_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'Theme directory not found.', 'acrossai-abilities-manager' ),
			);
		}

		$abs_file = realpath( $theme_dir . '/' . ltrim( $rel_file, '/' ) );

		if ( false === $abs_file || 0 !== strpos( $abs_file, $theme_dir . '/' ) || ! is_file( $abs_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'File not found within theme directory.', 'acrossai-abilities-manager' ),
			);
		}

		$content = file_get_contents( $abs_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read file.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success' => true,
			'content' => $content,
			'path'    => $abs_file,
			'size'    => strlen( $content ),
		);
	}
}

<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Plugins
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Plugins;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_Code_Read ability class (absorbed).
 */
class Plugin_Code_Read extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/plugin-code-read',
			'args' => array(
				'label'               => __( 'Read Plugin Code', 'acrossai-abilities-manager' ),
				'description'         => __( 'Reads the contents of a file inside a plugin directory.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin_slug' => array(
							'type'        => 'string',
							'description' => __( 'Plugin folder name.', 'acrossai-abilities-manager' ),
						),
						'plugin'      => array(
							'type'        => 'string',
							'description' => __( 'Alias for "plugin_slug". If multiple are provided, "plugin_slug" wins, then "plugin", then "slug".', 'acrossai-abilities-manager' ),
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => __( 'Alias for "plugin_slug". If multiple are provided, "plugin_slug" wins.', 'acrossai-abilities-manager' ),
						),
						'file_path'   => array(
							'type'        => 'string',
							'description' => __( 'File path relative to the plugin root (e.g. includes/class-main.php).', 'acrossai-abilities-manager' ),
						),
						'path'        => array(
							'type'        => 'string',
							'description' => __( 'Alias for "file_path". If both are provided, "file_path" wins.', 'acrossai-abilities-manager' ),
						),
					),
					'allOf'                => array(
						array(
							'anyOf' => array(
								array( 'required' => array( 'plugin_slug' ) ),
								array( 'required' => array( 'plugin' ) ),
								array( 'required' => array( 'slug' ) ),
							),
						),
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
						'tab_group'       => 'plugins',
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
		$raw_slug    = ! empty( $input['plugin_slug'] )
			? $input['plugin_slug']
			: ( ! empty( $input['plugin'] ) ? $input['plugin'] : ( $input['slug'] ?? '' ) );
		$slug        = sanitize_text_field( (string) $raw_slug );
		$raw_file    = ! empty( $input['file_path'] ) ? $input['file_path'] : ( $input['path'] ?? '' );
		$rel_file    = sanitize_text_field( (string) $raw_file );
		$plugins_dir = rtrim( WP_PLUGIN_DIR, '/' );
		$plugin_path = realpath( $plugins_dir . '/' . $slug );

		if ( false === $plugin_path || 0 !== strpos( $plugin_path, $plugins_dir . '/' ) || ! is_dir( $plugin_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Plugin directory not found.', 'acrossai-abilities-manager' ),
			);
		}

		$abs_file = realpath( $plugin_path . '/' . ltrim( $rel_file, '/' ) );

		if ( false === $abs_file || 0 !== strpos( $abs_file, $plugin_path . '/' ) || ! is_file( $abs_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'File not found within plugin directory.', 'acrossai-abilities-manager' ),
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

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
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_Files_Manage ability class (absorbed).
 */
class Plugin_Files_Manage extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/plugin-files-manage',
			'args' => array(
				'label'               => __( 'Copy or Move Plugin File', 'acrossai-abilities-manager' ),
				'description'         => __( 'Copy or move a file within the WordPress plugins directory. Both source and destination must remain inside WP_PLUGIN_DIR.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'action'      => array(
							'type'        => 'string',
							'enum'        => array( 'copy', 'move' ),
							'description' => __( 'Operation to perform: copy or move.', 'acrossai-abilities-manager' ),
						),
						'source'      => array(
							'type'        => 'string',
							'description' => __( 'Source file path relative to WP_PLUGIN_DIR.', 'acrossai-abilities-manager' ),
						),
						'destination' => array(
							'type'        => 'string',
							'description' => __( 'Destination file path relative to WP_PLUGIN_DIR.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'action', 'source', 'destination' ),
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
						'readonly'    => false,
						// `move` removes the source; `copy` can overwrite an existing
						// destination. Either path deletes prior file content — annotate
						// conservatively so MCP consumers policy-gate correctly.
						'destructive' => true,
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

		$action      = sanitize_text_field( $input['action'] ?? '' );
		$plugins_dir = rtrim( WP_PLUGIN_DIR, '/' );
		$src_real    = realpath( $plugins_dir . '/' . ltrim( sanitize_text_field( $input['source'] ?? '' ), '/' ) );
		$dst_path    = $plugins_dir . '/' . ltrim( sanitize_text_field( $input['destination'] ?? '' ), '/' );
		$dst_dir     = realpath( dirname( $dst_path ) );

		if ( false === $src_real || 0 !== strpos( $src_real, $plugins_dir . '/' ) || ! is_file( $src_real ) ) {
			return array(
				'success' => false,
				'message' => __( 'Source file not found or outside plugin directory.', 'acrossai-abilities-manager' ),
			);
		}

		if ( false === $dst_dir || ( $dst_dir !== $plugins_dir && 0 !== strpos( $dst_dir, $plugins_dir . '/' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Destination is outside the plugin directory.', 'acrossai-abilities-manager' ),
			);
		}

		if ( ! in_array( $action, array( 'copy', 'move' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid action. Use "copy" or "move".', 'acrossai-abilities-manager' ),
			);
		}

		// Refuse to silently overwrite an existing destination — data loss otherwise.
		if ( file_exists( $dst_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Destination already exists. Refusing to overwrite.', 'acrossai-abilities-manager' ),
			);
		}

		if ( 'copy' === $action ) {
			$ok = copy( $src_real, $dst_path );
		} else {
			$ok = rename( $src_real, $dst_path );
		}

		if ( ! $ok ) {
			return array(
				'success' => false,
				'message' => __( 'File operation failed.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success' => true,
			/* translators: 1: action (copy/move), 2: destination path */
			'message' => sprintf( __( 'File %1$s completed to %2$s.', 'acrossai-abilities-manager' ), $action, $dst_path ),
		);
	}
}

<?php
/**
 * Zip_List ability (Feature 041).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\FileManager
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\FileManager;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Backups_Storage;

defined( 'ABSPATH' ) || exit;

/**
 * List zips inside acrossai-backups/ (or acrossai-staging/) with metadata,
 * newest first. Feeds any "restore from a previous backup" flow.
 */
class Zip_List extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/zip-list',
			'args' => array(
				'label'               => __( 'List Zip Backups', 'acrossai-abilities-manager' ),
				'description'         => __( 'List zips inside acrossai-backups/ (default) or acrossai-staging/, newest first, with size, sha256, created_at.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'dir'    => array(
							'type'        => 'string',
							'enum'        => array( Backups_Storage::BACKUPS_DIR, Backups_Storage::STAGING_DIR ),
							'default'     => Backups_Storage::BACKUPS_DIR,
							'description' => __( 'Which managed directory to list: "acrossai-backups" (default) or "acrossai-staging".', 'acrossai-abilities-manager' ),
						),
						'limit'  => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 200,
							'default'     => 50,
							'description' => __( 'Max entries to return (1..200).', 'acrossai-abilities-manager' ),
						),
						'offset' => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'items'   => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'properties'           => array(
									'file_path'  => array( 'type' => 'string' ),
									'file_url'   => array( 'type' => 'string' ),
									'size'       => array( 'type' => 'integer' ),
									'sha256'     => array( 'type' => 'string' ),
									'created_at' => array( 'type' => 'string' ),
								),
								'additionalProperties' => false,
							),
						),
						'total'   => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'file-manager',
						'sub_group'       => 'backups',
						'sub_group_label' => __( 'Backups', 'acrossai-abilities-manager' ),
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
		$dir    = sanitize_key( (string) ( $input['dir'] ?? Backups_Storage::BACKUPS_DIR ) );
		$limit  = isset( $input['limit'] ) ? (int) $input['limit'] : 50;
		$offset = isset( $input['offset'] ) ? (int) $input['offset'] : 0;

		if ( Backups_Storage::BACKUPS_DIR !== $dir && Backups_Storage::STAGING_DIR !== $dir ) {
			return array(
				'success' => false,
				'message' => __( '"dir" must be "acrossai-backups" or "acrossai-staging".', 'acrossai-abilities-manager' ),
			);
		}

		$result = Backups_Storage::list_entries( $dir, $limit, $offset );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'items'   => $result['items'],
			'total'   => (int) $result['total'],
		);
	}
}

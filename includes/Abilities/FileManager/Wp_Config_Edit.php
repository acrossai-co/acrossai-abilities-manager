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
 * Wp_Config_Edit ability class (absorbed).
 */
class Wp_Config_Edit extends Ability_Definition {

	/**
	 * Constants that may not be modified via this ability.
	 */
	private const PROTECTED = array(
		'DB_NAME',
		'DB_USER',
		'DB_PASSWORD',
		'DB_HOST',
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
		'SECRET_KEY',
	);

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/wp-config-edit',
			'args' => array(
				'label'               => __( 'Edit wp-config.php', 'acrossai-abilities-manager' ),
				'description'         => __( 'Updates the value of an existing non-sensitive constant in wp-config.php. Protected credential and secret constants cannot be modified.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'constant_name' => array(
							'type'        => 'string',
							'description' => __( 'Name of the constant to update (e.g. WP_DEBUG).', 'acrossai-abilities-manager' ),
						),
						'value'         => array(
							'type'        => 'string',
							'description' => __( 'New string value for the constant.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'constant_name', 'value' ),
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
						'sub_group'       => 'wp-config',
						'sub_group_label' => __( 'WP Config', 'acrossai-abilities-manager' ),
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

		$name  = strtoupper( sanitize_text_field( $input['constant_name'] ?? '' ) );
		$value = $input['value'] ?? '';

		if ( '' === $name || ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid constant name.', 'acrossai-abilities-manager' ),
			);
		}

		if ( in_array( $name, self::PROTECTED, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'This constant is protected and cannot be modified.', 'acrossai-abilities-manager' ),
			);
		}

		$config_path = $this->locate_wp_config();

		if ( null === $config_path ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php not found.', 'acrossai-abilities-manager' ),
			);
		}

		$raw     = file_get_contents( $config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$escaped = addslashes( $value );
		$pattern = "/define\(\s*(['\"])" . preg_quote( $name, '/' ) . "\\1\s*,\s*(?:'[^']*'|\"[^\"]*\"|[^)]+)\s*\)/";
		$updated = preg_replace( $pattern, "define( '{$name}', '{$escaped}' )", $raw, -1, $count );

		if ( 0 === $count ) {
			return array(
				'success' => false,
				'message' => __( 'Constant not found in wp-config.php.', 'acrossai-abilities-manager' ),
			);
		}

		if ( false === file_put_contents( $config_path, $updated ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return array(
				'success' => false,
				'message' => __( 'Could not write wp-config.php.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success' => true,
			/* translators: constant name */
			'message' => sprintf( __( 'Constant %s updated.', 'acrossai-abilities-manager' ), $name ),
		);
	}

	/**
	 * Locate wp config.
	 *
	 * @return ?string
	 */
	private function locate_wp_config(): ?string {
		$candidates = array(
			ABSPATH . 'wp-config.php',
			dirname( rtrim( ABSPATH, '/' ) ) . '/wp-config.php',
		);
		foreach ( $candidates as $path ) {
			if ( is_file( $path ) ) {
				return $path;
			}
		}
		return null;
	}
}

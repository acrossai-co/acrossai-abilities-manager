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

defined( 'ABSPATH' ) || exit;

/**
 * Wp_Config_Read ability class (absorbed).
 */
class Wp_Config_Read extends Ability_Definition {

	/**
	 * Constant names whose values must never be returned.
	 */
	private const SENSITIVE = array(
		'DB_PASSWORD',
		'DB_USER',
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
			'name' => 'acrossai-abilities-manager/wp-config-read',
			'args' => array(
				'label'               => __( 'Read wp-config.php', 'acrossai-abilities-manager' ),
				'description'         => __( 'Returns non-sensitive constants and the table prefix defined in wp-config.php. Credential and secret constants are redacted.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'      => array( 'type' => 'boolean' ),
						'constants'    => array( 'type' => 'object' ),
						'table_prefix' => array( 'type' => 'string' ),
						'message'      => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
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
		$config_path = $this->locate_wp_config();

		if ( null === $config_path ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php not found.', 'acrossai-abilities-manager' ),
			);
		}

		$raw = file_get_contents( $config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $raw ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read wp-config.php.', 'acrossai-abilities-manager' ),
			);
		}

		preg_match_all(
			"/define\(\s*['\"]([A-Z_]+)['\"]\s*,\s*(?:'([^']*)'|\"([^\"]*)\"|([^)]+))\s*\)/",
			$raw,
			$matches
		);

		$constants = array();
		foreach ( $matches[1] as $i => $name ) {
			if ( in_array( $name, self::SENSITIVE, true ) ) {
				$constants[ $name ] = '***';
				continue;
			}
			$value              = $matches[2][ $i ] !== '' ? $matches[2][ $i ]
				: ( $matches[3][ $i ] !== '' ? $matches[3][ $i ] : trim( $matches[4][ $i ] ) );
			$constants[ $name ] = $value;
		}

		preg_match( '/\$table_prefix\s*=\s*[\'"]([^\'"]+)[\'"]/', $raw, $prefix_match );
		$table_prefix = $prefix_match[1] ?? '';

		return array(
			'success'      => true,
			'constants'    => $constants,
			'table_prefix' => $table_prefix,
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

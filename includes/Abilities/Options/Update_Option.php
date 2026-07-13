<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Options
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Options;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Update_Option ability class (absorbed).
 */
class Update_Option extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai-abilities-manager/update-option',
			'args' => array(
				'label'               => __( 'Update Option', 'acrossai-abilities-manager' ),
				'description'         => __( 'Write a wp_options row via update_option(). Creates the option if it does not exist.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-options',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'         => array(
							'type'        => 'string',
							'description' => __( 'Option name.', 'acrossai-abilities-manager' ),
						),
						'option_name'  => array(
							'type'        => 'string',
							'description' => __( 'Alias for "name". If both are provided, "name" wins.', 'acrossai-abilities-manager' ),
						),
						'value'        => array( 'type' => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ) ),
						'option_value' => array(
							'type'        => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ),
							'description' => __( 'Alias for "value". If both are provided, "value" wins.', 'acrossai-abilities-manager' ),
						),
						'autoload'     => array(
							'type'    => array( 'boolean', 'null' ),
							'default' => null,
						),
					),
					'anyOf'                => array(
						array( 'required' => array( 'name' ) ),
						array( 'required' => array( 'option_name' ) ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'updated' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'core',
						'sub_group'       => 'manage',
						'sub_group_label' => __( 'Manage', 'acrossai-abilities-manager' ),
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
		$raw_name = ! empty( $input['name'] ) ? $input['name'] : ( $input['option_name'] ?? '' );
		$name     = sanitize_text_field( (string) $raw_name );
		if ( '' === $name ) {
			return array(
				'success' => false,
				'message' => __( 'name (or its alias "option_name") is required.', 'acrossai-abilities-manager' ),
			);
		}

		$value    = array_key_exists( 'value', $input ) ? $input['value'] : ( $input['option_value'] ?? '' );
		$autoload = $input['autoload'] ?? null;

		$result = update_option( $name, $value, $autoload );

		return array(
			'success' => true,
			'updated' => (bool) $result,
			/* translators: %s: option name */
			'message' => sprintf( __( 'Wrote option "%s".', 'acrossai-abilities-manager' ), $name ),
		);
	}
}

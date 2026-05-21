<?php
/**
 * Database schema definition for the custom abilities table.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Custom_Ability/Database
 * @since      1.0.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database;

use BerlinDB\Database\Schema;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Schema class defining all columns of the acrossai_custom_abilities table.
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Schema extends Schema {

	/**
	 * Array of column definitions.
	 *
	 * @var array
	 */
	public $columns = array(

		// Primary key.
		array(
			'name'     => 'id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'extra'    => 'auto_increment',
			'primary'  => true,
			'sortable' => true,
		),

		// Ability identifier (unique, required).
		array(
			'name'       => 'ability_slug',
			'type'       => 'varchar',
			'length'     => '255',
			'allow_null' => false,
			'default'    => '',
			'searchable' => true,
			'sortable'   => true,
		),

		// Display label (required).
		array(
			'name'       => 'label',
			'type'       => 'varchar',
			'length'     => '255',
			'allow_null' => false,
			'default'    => '',
			'searchable' => true,
			'sortable'   => true,
		),

		// Human-readable description.
		array(
			'name'       => 'description',
			'type'       => 'longtext',
			'allow_null' => true,
			'default'    => null,
			'searchable' => true,
		),

		// Registration control.
		array(
			'name'       => 'enabled',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => false,
			'default'    => '1',
			'sortable'   => true,
		),

		// Callback configuration.
		array(
			'name'       => 'callback_type',
			'type'       => 'varchar',
			'length'     => '50',
			'allow_null' => false,
			'default'    => 'noop',
			'sortable'   => true,
		),
		array(
			'name'       => 'callback_config',
			'type'       => 'longtext',
			'allow_null' => true,
			'default'    => null,
		),

		// Ability schemas (JSON Schema).
		array(
			'name'       => 'input_schema',
			'type'       => 'longtext',
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'output_schema',
			'type'       => 'longtext',
			'allow_null' => true,
			'default'    => null,
		),

		// REST / MCP exposure.
		array(
			'name'       => 'show_in_rest',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => false,
			'default'    => '1',
			'sortable'   => true,
		),
		array(
			'name'       => 'show_in_mcp',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => false,
			'default'    => '0',
			'sortable'   => true,
		),
		array(
			'name'       => 'mcp_type',
			'type'       => 'varchar',
			'length'     => '50',
			'allow_null' => true,
			'default'    => null,
			'sortable'   => true,
		),

		// Tri-state annotation flags: NULL (inherit) / 0 (no) / 1 (yes).
		array(
			'name'       => 'readonly',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'destructive',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'idempotent',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => true,
			'default'    => null,
		),

		// Audit timestamps.
		array(
			'name'       => 'created_at',
			'type'       => 'datetime',
			'allow_null' => false,
			'default'    => 'CURRENT_TIMESTAMP',
			'created'    => true,
			'date_query' => true,
			'sortable'   => true,
		),
		array(
			'name'       => 'updated_at',
			'type'       => 'datetime',
			'allow_null' => false,
			'default'    => 'CURRENT_TIMESTAMP',
			'modified'   => true,
			'date_query' => true,
			'sortable'   => true,
		),

		// Audit user IDs.
		array(
			'name'       => 'created_by',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'updated_by',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'allow_null' => true,
			'default'    => null,
		),
	);
}

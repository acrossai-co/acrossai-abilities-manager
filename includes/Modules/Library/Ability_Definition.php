<?php
/**
 * Abstract base class for ability definitions.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Modules/Library
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for ability definitions.
 *
 * Subclasses implement five abstract methods describing the ability's
 * Library-page grouping and its wp_register_ability() spec. The constructor
 * hooks the existing acrossai_abilities_api_init filter automatically.
 */
abstract class Ability_Definition {

	/**
	 * Register the push_definition filter callback.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_filter( 'acrossai_abilities_api_init', array( $this, 'push_definition' ) );
	}

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * Must return an array with:
	 *   - 'name'  (string) the unique ability name, e.g. 'plugin-slug/ability-slug'
	 *   - 'args'  (array)  the args passed to wp_register_ability:
	 *                      label, description, category, execute_callback,
	 *                      permission_callback, input_schema, output_schema, meta
	 *
	 * The Library page derives its display fields from this return value:
	 *   - Library card grouping: args['category']
	 *   - Per-row label:         args['label']
	 *   - Unique slug:           name
	 */
	abstract protected function ability(): array;

	/**
	 * Filter callback — wired automatically by the constructor.
	 *
	 * Derives Library grouping fields from ability() so subclasses only need
	 * to implement the single ability() method.
	 *
	 * @param array $definitions Existing definitions collected so far.
	 * @return array
	 */
	public function push_definition( array $definitions ): array {
		$spec = $this->ability();
		$name = $spec['name'] ?? '';
		$args = $spec['args'] ?? array();

		$definitions[] = array(
			'category'       => $args['category'] ?? '',
			'category_label' => $args['category'] ?? '',
			'slug'           => $name,
			'slug_label'     => $args['label'] ?? $name,
			'name'           => $name,
			'args'           => $args,
		);

		return $definitions;
	}
}

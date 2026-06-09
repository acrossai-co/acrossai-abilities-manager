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

	/** Library card grouping key (e.g. 'sre-tools'). */
	abstract protected function main_key(): string;

	/** Human-readable label for the card title (e.g. 'SRE Tools'). */
	abstract protected function main_key_label(): string;

	/** Sub-key for the per-ability checkbox (e.g. 'transient-flush'). */
	abstract protected function sub_key(): string;

	/** Human-readable label for the sub-key checkbox (e.g. 'Flush Transients'). */
	abstract protected function sub_key_label(): string;

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * Must return an array with:
	 *   - 'name'  (string) the unique ability name, e.g. 'plugin-slug/ability-slug'
	 *   - 'args'  (array)  the args passed to wp_register_ability:
	 *                      label, description, category, execute_callback,
	 *                      permission_callback, input_schema, output_schema, meta
	 */
	abstract protected function ability(): array;

	/**
	 * Filter callback — wired automatically by the constructor.
	 *
	 * @param array $definitions Existing definitions collected so far.
	 * @return array
	 */
	public function push_definition( array $definitions ): array {
		$spec = $this->ability();

		$definitions[] = array(
			'main_key'       => $this->main_key(),
			'main_key_label' => $this->main_key_label(),
			'sub_key'        => $this->sub_key(),
			'sub_key_label'  => $this->sub_key_label(),
			'name'           => $spec['name'] ?? '',
			'args'           => $spec['args'] ?? array(),
		);

		return $definitions;
	}
}

<?php
/**
 * Library Processor — registers add-on abilities at wp_abilities_api_init P5.
 *
 * Runs before the database Processor at P10, applying the saved keys config to
 * gate which add-on abilities are registered into the WordPress Abilities API.
 *
 * Default behavior when a key is absent from saved config (D6):
 *   - main_key missing → enabled=true, mode='all'
 *   - sub_key missing in Specific mode → enabled=false
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Modules/Library
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Gates add-on abilities against the saved keys config and registers approved ones.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Library_Processor {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Ability_Library_Processor|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Ability_Library_Processor
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Register approved add-on abilities into the WordPress Abilities API.
	 *
	 * Wired at wp_abilities_api_init P5 via includes/Main.php.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$definitions = AcrossAI_Ability_Library_Registry::instance()->get_definitions();
		$config      = AcrossAI_Ability_Library_Config::get_config();

		foreach ( $definitions as $definition ) {
			if ( ! $this->is_permitted( $definition, $config ) ) {
				continue;
			}
			wp_register_ability( $definition['name'], $definition['args'] );
		}
	}

	/**
	 * Determine whether a definition is permitted by the saved config.
	 *
	 * FR-013: main_key absent → enabled by default.
	 * FR-014: main_key disabled → skip.
	 * FR-015: mode=all → all sub_keys permitted.
	 * FR-016: mode=specific → only explicitly enabled sub_keys permitted.
	 * FR-017: sub_key absent in Specific mode → disabled by default (D6).
	 *
	 * @since  0.1.0
	 * @param  array<string, mixed>                $definition Validated definition from the Registry.
	 * @param  array<string, array<string, mixed>> $config Saved config from site option.
	 * @return bool
	 */
	private function is_permitted( array $definition, array $config ): bool {
		$main_key = $definition['main_key'];
		$sub_key  = $definition['sub_key'];

		// Main key absent from config → permitted with default all-mode (FR-013).
		if ( ! isset( $config[ $main_key ] ) ) {
			return true;
		}

		$entry   = $config[ $main_key ];
		$enabled = isset( $entry['enabled'] ) ? (bool) $entry['enabled'] : true;

		// Main key is disabled (FR-014).
		if ( ! $enabled ) {
			return false;
		}

		$mode = isset( $entry['mode'] ) && 'specific' === $entry['mode'] ? 'specific' : 'all';

		// All mode → all sub_keys for this main_key are permitted (FR-015).
		if ( 'all' === $mode ) {
			return true;
		}

		// Specific mode: sub_key must be explicitly enabled; absent defaults to false (FR-016, FR-017).
		return isset( $entry['sub_keys'][ $sub_key ] ) && (bool) $entry['sub_keys'][ $sub_key ];
	}
}

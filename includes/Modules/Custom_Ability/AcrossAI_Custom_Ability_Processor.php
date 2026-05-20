<?php
/**
 * Custom Ability Processor
 *
 * Registers custom abilities with WordPress Abilities API at wp_abilities_api_init.
 * Fetches enabled custom abilities from database, builds metadata, injects permission
 * callbacks, and registers via wp_register_ability().
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Modules\Custom_Ability
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Processor class
 *
 * Singleton processor for custom ability registration.
 * Runs at wp_abilities_api_init (priority 10).
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Processor {

	/**
	 * Singleton instance
	 *
	 * @since 1.0.0
	 * @var AcrossAI_Custom_Ability_Processor
	 */
	protected static $_instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return AcrossAI_Custom_Ability_Processor
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton pattern
	}

	/**
	 * Register custom abilities with WordPress Abilities API
	 *
	 * Called at wp_abilities_api_init hook (priority 10).
	 * Fetches all enabled custom abilities from BerlinDB table and registers
	 * each one with the WordPress Abilities API via wp_register_ability().
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_custom_abilities() {
		// Verify WordPress Abilities API is available
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // WordPress 6.9+ not available, skip
		}

		// Fetch all enabled custom abilities from database
		$table = AcrossAI_Custom_Ability_Table::instance();
		$query = $table->query()->enabled_only();

		$abilities = $query->get_results();

		if ( empty( $abilities ) ) {
			return; // No abilities to register
		}

		// Register each ability
		foreach ( $abilities as $ability ) {
			$this->register_ability( $ability );
		}
	}

	/**
	 * Register single ability with WordPress Abilities API
	 *
	 * Builds metadata from ability row, injects permission callback,
	 * registers ability, and fires hooks.
	 *
	 * @since 1.0.0
	 * @param AcrossAI_Custom_Ability_Row $ability Ability row object from database.
	 * @return void
	 */
	private function register_ability( $ability ) {
		try {
			// Build permission callback based on permission_type
			$permission_callback = $this->build_permission_callback( $ability );

			// Build registration arguments
			$args = array(
				'label'               => $ability->label,
				'description'         => $ability->description,
				'callback'            => null, // TODO v2: Implement callback execution via wp_execute_ability()
				'show_in_rest'        => (bool) $ability->show_in_rest,
				'show_in_mcp'         => (bool) $ability->show_in_mcp,
				'readonly'            => $this->get_tri_state_value( $ability->readonly ),
				'destructive'         => $this->get_tri_state_value( $ability->destructive ),
				'idempotent'          => $this->get_tri_state_value( $ability->idempotent ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'category'            => $ability->category,
					'callback_type'       => $ability->callback_type,
					'callback_config'     => (array) json_decode( $ability->callback_config, true ),
					'input_schema'        => (array) json_decode( $ability->input_schema, true ),
					'output_schema'       => (array) json_decode( $ability->output_schema, true ),
					'mcp_type'            => $ability->mcp_type,
					'mcp_servers'         => (array) json_decode( $ability->mcp_servers, true ),
					'database_id'         => (int) $ability->id,
				),
			);

			// Allow customization of registration args via filter
			$args = apply_filters( 'acrossai_custom_ability_wp_args', $args, $ability );

			// Register ability with WordPress Abilities API
			wp_register_ability( $ability->ability_slug, $args );

			// Fire after-registration hook
			do_action( 'acrossai_custom_ability_registered', $ability, $args );

		} catch ( Exception $e ) {
			// Log error and continue with next ability (graceful degradation)
			do_action(
				'acrossai_custom_ability_registration_error',
				$ability->ability_slug,
				new WP_Error(
					'registration_failed',
					sprintf(
						/* translators: %s: ability slug */
						__( 'Failed to register ability "%s": %s', 'acrossai-abilities-manager' ),
						$ability->ability_slug,
						$e->getMessage()
					)
				)
			);
		}
	}

	/**
	 * Build permission callback based on permission type
	 *
	 * Returns closure that checks permission based on permission_type field.
	 * Implements DEC-PERM-CB pattern (Memory).
	 *
	 * @since 1.0.0
	 * @param AcrossAI_Custom_Ability_Row $ability Ability row object.
	 * @return callable|null Permission callback closure or null for no check.
	 */
	private function build_permission_callback( $ability ) {
		$permission_type   = $ability->permission_type;
		$permission_config = (array) json_decode( $ability->permission_config, true );

		// Build permission callback based on permission_type
		switch ( $permission_type ) {
			case 'always_allow':
				// No permission check required; null = always allowed
				$callback = null;
				break;

			case 'logged_in':
				// Check if user is logged in
				$callback = function() {
					return is_user_logged_in();
				};
				break;

			case 'capability':
				// Check if user has specific capability
				// Validate capability exists (fail closed per Security Finding 6)
				$capability = $permission_config['capability'] ?? null;

				if ( ! $capability || ! $this->capability_exists( $capability ) ) {
					// Capability not found: log warning and return fail-open callback for safety
					do_action(
						'acrossai_custom_ability_permission_error',
						$ability->ability_slug,
						'capability_not_found',
						$capability
					);

					// Fail open: allow access (admin can manually disable ability if needed)
					$callback = null;
				} else {
					$callback = function() use ( $capability ) {
						return current_user_can( $capability );
					};
				}
				break;

			default:
				// Unknown permission type; default to null (fail open)
				$callback = null;
		}

		// Allow customization of permission callback via filter
		$callback = apply_filters(
			'acrossai_custom_ability_permission_callback',
			$callback,
			$ability,
			$permission_type
		);

		return $callback;
	}

	/**
	 * Check if WordPress capability exists
	 *
	 * Verifies capability is registered in WordPress roles system.
	 *
	 * @since 1.0.0
	 * @param string $capability Capability name to check.
	 * @return bool True if capability exists, false otherwise.
	 */
	private function capability_exists( $capability ) {
		// Get WordPress roles object
		$wp_roles = wp_roles();

		if ( ! $wp_roles ) {
			return false; // Role system not available
		}

		// Get all registered capabilities
		$all_caps = $wp_roles->get_capabilities();

		// Check if capability exists
		return isset( $all_caps[ $capability ] );
	}

	/**
	 * Get tri-state value (NULL, 0, or 1)
	 *
	 * Converts database tri-state values to PHP tri-state representation.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value from database (null, 0, 1).
	 * @return bool|null Boolean true/false or null for inherit.
	 */
	private function get_tri_state_value( $value ) {
		if ( null === $value ) {
			return null; // Inherit default
		}

		return (bool) $value;
	}
}

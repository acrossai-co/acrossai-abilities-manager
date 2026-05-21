<?php
/**
 * Custom Ability Processor
 *
 * Registers custom abilities from BerlinDB at wp_abilities_api_init hook.
 * Fetches all enabled custom abilities and injects them into WordPress Abilities API.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability
 * @since 1.0.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability;

use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AcrossAI_Custom_Ability_Processor class
 *
 * Singleton: Registers custom abilities at wp_abilities_api_init.
 *
 * Hooks into `wp_abilities_api_init` (priority 10) to fetch all enabled
 * custom abilities from BerlinDB table and register them via wp_register_ability().
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
         * Constructor (private for singleton)
         *
         * @since 1.0.0
         */
        private function __construct() {
                // Private constructor for singleton pattern
        }

	/**
	 * Register custom abilities at wp_abilities_api_init.
	 *
	 * Fetches all enabled custom abilities from BerlinDB, builds the args array
	 * expected by wp_register_ability(), and registers each one.
	 *
	 * @since 1.0.0
	 * @action wp_abilities_api_init
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$table = AcrossAI_Custom_Ability_Table::instance();
		if ( ! $table->table_exists() ) {
			return;
		}

		$rows = ( new AcrossAI_Custom_Ability_Query() )
			->enabled_only()
			->with_pagination( 1000, 1 )
			->get();

		if ( empty( $rows ) ) {
			do_action( 'acrossai_custom_ability_processor_initialized' );
			return;
		}

		foreach ( $rows as $row ) {
			$slug = (string) ( $row->ability_slug ?? '' );

			if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/', $slug ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'AcrossAI: skipping ability with invalid slug "%s"', $slug ) );
				continue;
			}

			$args = array(
				'label'       => (string) ( $row->label ?? '' ),
				'description' => (string) ( $row->description ?? '' ),
				'callback'    => $this->build_execute_callback( $row ),
				'show_in_rest' => (bool) $row->show_in_rest,
			);

			// Optional schemas.
			if ( ! empty( $row->input_schema ) ) {
				$args['input_schema'] = is_array( $row->input_schema )
					? $row->input_schema
					: json_decode( $row->input_schema, true );
			}
			if ( ! empty( $row->output_schema ) ) {
				$args['output_schema'] = is_array( $row->output_schema )
					? $row->output_schema
					: json_decode( $row->output_schema, true );
			}

			// Tri-state annotations (null = inherit, 0/1 = explicit).
			$annotations = array();
			foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $flag ) {
				if ( null !== $row->{ $flag } ) {
					$annotations[ $flag ] = (bool) $row->{ $flag };
				}
			}
			if ( ! empty( $annotations ) ) {
				$args['annotations'] = $annotations;
			}

			wp_register_ability( $slug, $args );

			/**
			 * Fires after a custom ability is registered.
			 *
			 * @since 1.0.0
			 * @param string $slug Ability slug.
			 * @param object $row  BerlinDB row.
			 */
			do_action( 'acrossai_custom_ability_registered', $slug, $row );
		}

		do_action( 'acrossai_custom_ability_processor_initialized' );
	}

	/**
	 * Build the execute callback closure for a given ability row.
	 *
	 * @since 1.0.0
	 * @param object $row Ability row.
	 * @return callable
	 */
	private function build_execute_callback( $row ) {
		$callback_type   = (string) ( $row->callback_type ?? 'noop' );
		$callback_config = $row->callback_config ?? array();

		if ( 'filter_hook' === $callback_type ) {
			$hook_name = is_array( $callback_config )
				? ( $callback_config['hook_name'] ?? '' )
				: '';
			$slug = (string) ( $row->ability_slug ?? '' );
			return static function ( $input ) use ( $hook_name, $slug ) {
				$hook = ! empty( $hook_name )
					? $hook_name
					: 'acrossai_custom_ability_execute_' . str_replace( '/', '_', $slug );
				return apply_filters( $hook, array(), $input );
			};
		}

		if ( 'wp_remote_post' === $callback_type ) {
			$url     = is_array( $callback_config ) ? ( $callback_config['url'] ?? '' ) : '';
			$timeout = is_array( $callback_config ) ? (int) ( $callback_config['timeout'] ?? 30 ) : 30;
			$timeout = max( 1, min( $timeout, 300 ) );
			return static function ( $input ) use ( $url, $timeout ) {
				if ( empty( $url ) ) {
					return new \WP_Error( 'no_url', __( 'No URL configured.', 'acrossai-abilities-manager' ) );
				}
				$response = wp_remote_post(
					$url,
					array(
						'body'        => wp_json_encode( $input ),
						'headers'     => array( 'Content-Type' => 'application/json' ),
						'timeout'     => $timeout,
						'data_format' => 'body',
					)
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$body    = wp_remote_retrieve_body( $response );
				$decoded = json_decode( $body, true );
				return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $body;
			};
		}

		// noop or unknown — return empty array.
		return static function () {
			return array();
		};
	}
}

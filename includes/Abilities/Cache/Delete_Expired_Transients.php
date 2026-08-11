<?php
/**
 * Feature 064 — Purge every expired transient in a single pass.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Cache
 * @since      0.0.23
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Cache;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Purge every expired transient (blog + site scope) in one call. WP core's
 * `delete_expired_transients()` returns `void`, so the pre-call count is
 * captured directly against `$wpdb->options` before the purge runs and
 * reported back as the `deleted` counter.
 */
class Delete_Expired_Transients extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'acrossai/delete-expired-transients',
			'args' => array(
				'label'               => __( 'Delete Expired Transients', 'acrossai-abilities-manager' ),
				'description'         => __( 'Purge every expired transient (blog and site scope) in a single pass. Reports the count that were purged.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-abilities-manager-cache',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'deleted' => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group' => 'cache',
						'sub_group' => 'cache',
					),
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
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
		unset( $input );

		global $wpdb;

		$now = time();

		// Capture count BEFORE calling core — delete_expired_transients()
		// returns void, so we must snapshot the expired set first.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$blog_expired = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				 WHERE option_name LIKE %s AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$now
			)
		);
		$site_expired = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				 WHERE option_name LIKE %s AND option_value < %d",
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				$now
			)
		);
		// phpcs:enable

		$count = $blog_expired + $site_expired;

		delete_expired_transients( true );

		return array(
			'success' => true,
			'deleted' => $count,
			'message' => sprintf(
				/* translators: %d: number of expired transients purged */
				_n( '%d expired transient purged.', '%d expired transients purged.', $count, 'acrossai-abilities-manager' ),
				$count
			),
		);
	}
}

<?php
/**
 * REST sub-controller: bulk ability actions (US5).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Ability_Override_Processor;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Rest_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Merger;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Source_Detector;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles POST /sitewide/abilities/bulk.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Bulk_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Sitewide_Bulk_Controller|null
	 */
	protected static $_instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Sitewide_Bulk_Controller
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * BerlinDB query instance.
	 *
	 * @var AcrossAI_Sitewide_Query
	 */
	private $db_query;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->db_query = AcrossAI_Sitewide_Query::instance();
	}

	/**
	 * Register REST routes owned by this controller.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			AcrossAI_Sitewide_Rest_Controller::REST_NAMESPACE,
			'/sitewide/abilities/bulk',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_action' ),
					'permission_callback' => array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' ),
					'args'                => array(
						'slugs'  => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array( 'type' => 'string' ),
						),
						'action' => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array( 'allow', 'disallow', 'reset' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Handle POST /sitewide/abilities/bulk.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bulk_action( \WP_REST_Request $request ) {
		$raw_slugs = $request->get_param( 'slugs' );
		$action    = sanitize_text_field( (string) $request->get_param( 'action' ) );

		// Validate action before processing (SEC-01).
		$allowed_actions = array( 'allow', 'disallow', 'reset' );
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error( 'rest_invalid_param', __( 'Invalid bulk action.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
		}

		if ( ! is_array( $raw_slugs ) ) {
			return new \WP_Error( 'rest_invalid_param', __( 'slugs must be an array.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
		}

		if ( count( $raw_slugs ) > 100 ) {
			return new \WP_Error( 'rest_too_many', __( 'Maximum 100 slugs per bulk request.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
		}

		$succeeded = 0;
		$failed    = 0;
		$skipped   = array();
		$results   = array();

		foreach ( $raw_slugs as $raw_slug ) {
			$slug = AcrossAI_Sanitizer::sanitize_ability_slug( (string) $raw_slug );

			if ( '' === $slug ) {
				$skipped[] = (string) $raw_slug;
				continue;
			}

			if ( function_exists( 'wp_get_ability' ) ) {
				$registry = wp_get_ability( $slug );
				if ( empty( $registry ) ) {
					$skipped[] = $slug;
					continue;
				}
				$registry = AcrossAI_Ability_Merger::normalize_registry( $registry );
			} else {
				$registry = array( 'slug' => $slug );
			}

			$ok = false;

			if ( 'reset' === $action ) {
				$ok = $this->db_query->delete_override_by_slug( $slug );
				// delete returns false if no record; treat as success.
				$ok = true;
				AcrossAI_Ability_Override_Processor::bust_cache();
			} else {
				$site_allowed = 'allow' === $action;
				$source       = AcrossAI_Ability_Source_Detector::detect( $registry );
				$fields       = array(
					'site_allowed' => $site_allowed,
					'source'       => $source,
				);
				$ok           = $this->db_query->save_override( $slug, $fields );

				if ( $ok ) {
					// Fetch the saved row so after_save receives the complete override
					// state, not just the 2 fields sent by the bulk action (LOW-NEW-02).
					$saved_row   = $this->db_query->get_override_by_slug( $slug );
					$hook_fields = null !== $saved_row ? array(
						'site_allowed' => $saved_row->site_allowed,
						'readonly'     => $saved_row->readonly,
						'destructive'  => $saved_row->destructive,
						'idempotent'   => $saved_row->idempotent,
						'show_in_rest' => $saved_row->show_in_rest,
						'show_in_mcp'  => $saved_row->show_in_mcp,
						'mcp_type'     => $saved_row->mcp_type,
						'mcp_servers'  => $saved_row->mcp_servers,
						'source'       => $saved_row->source,
					) : $fields;
					do_action( 'acrossai_abilities_sitewide_after_save', $slug, $hook_fields );
				}
			}

			if ( $ok ) {
				++$succeeded;
				$results[] = array(
					'slug'   => $slug,
					'status' => 'success',
				);
			} else {
				++$failed;
				$results[] = array(
					'slug'   => $slug,
					'status' => 'failed',
				);
			}
		}

		return rest_ensure_response(
			array(
				'succeeded' => $succeeded,
				'failed'    => $failed,
				'skipped'   => $skipped,
				'results'   => $results,
			)
		);
	}
}

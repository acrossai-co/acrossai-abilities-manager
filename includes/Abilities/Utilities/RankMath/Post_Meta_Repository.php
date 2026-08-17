<?php
/**
 * Feature 069 — Rank Math per-post metadata, primary terms, schema and scores.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities\RankMath
 * @since      0.0.28
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Static-only accessor for the rank_math_* postmeta surface.
 *
 * Rank Math stores per-post SEO data in typed postmeta whose encoding matters: robots
 * is an ARRAY, the content flags are the literal string 'on' or absent (never false),
 * and focus_keyword is a comma-joined list whose first element is the primary. A
 * generic meta writer will happily store the wrong shape and Rank Math will then
 * silently misread it, which is why these writes belong here rather than going through
 * the plugin's acrossai/update-post-meta.
 */
final class Post_Meta_Repository {

	/**
	 * Writable SEO meta fields => their rank_math_* meta key.
	 */
	private const FIELDS = array(
		'title'         => 'rank_math_title',
		'description'   => 'rank_math_description',
		'focus_keyword' => 'rank_math_focus_keyword',
		'canonical_url' => 'rank_math_canonical_url',
	);

	/**
	 * Flag fields stored as the literal 'on', or deleted when false.
	 */
	private const FLAGS = array(
		'is_pillar'     => 'rank_math_pillar_content',
		'is_cornerstone' => 'rank_math_cornerstone_content',
	);

	/**
	 * Robots directives Rank Math accepts.
	 */
	private const ROBOTS = array( 'index', 'noindex', 'nofollow', 'noarchive', 'noimageindex', 'nosnippet' );

	/**
	 * Columns Rank Math's own bulk endpoint accepts.
	 *
	 * @see seo-by-rank-math/includes/rest/class-post.php:159
	 */
	public const BULK_COLUMNS = array( 'focus_keyword', 'title', 'description', 'image_alt', 'image_title' );

	/**
	 * Private constructor — this class is static-any (DEC-UTILITY-STATIC-ONLY).
	 */
	private function __construct() {}

	/**
	 * Assert a post exists and the caller may edit it.
	 *
	 * @param int $post_id Post id.
	 * @return true|WP_Error
	 */
	public static function assert_editable( int $post_id ) {
		if ( $post_id < 1 ) {
			return new WP_Error( 'invalid_input', __( 'post_id must be a positive integer.', 'acrossai-abilities-manager' ) );
		}
		$post = get_post( $post_id );
		if ( null === $post ) {
			return new WP_Error(
				'not_found',
				sprintf(
					/* translators: %d: post id */
					__( 'Post %d does not exist.', 'acrossai-abilities-manager' ),
					$post_id
				)
			);
		}
		// Per-object check, in addition to the ability's capability floor.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'insufficient_capability',
				sprintf(
					/* translators: %d: post id */
					__( 'You cannot edit post %d.', 'acrossai-abilities-manager' ),
					$post_id
				)
			);
		}
		return true;
	}

	/**
	 * Write SEO meta for one post, with Rank Math's expected encodings.
	 *
	 * @param int                 $post_id Post id.
	 * @param array<string,mixed> $fields  Fields to write.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function update_seo_meta( int $post_id, array $fields ) {
		$editable = self::assert_editable( $post_id );
		if ( is_wp_error( $editable ) ) {
			return $editable;
		}

		$known   = array_merge( array_keys( self::FIELDS ), array_keys( self::FLAGS ), array( 'robots' ) );
		$unknown = array_diff( array_keys( $fields ), $known );
		if ( array() !== $unknown ) {
			return new WP_Error(
				'unknown_field',
				sprintf(
					/* translators: 1: comma-separated unknown fields, 2: comma-separated known fields */
					__( 'Unknown field(s): %1$s. Writable fields are: %2$s.', 'acrossai-abilities-manager' ),
					implode( ', ', $unknown ),
					implode( ', ', $known )
				)
			);
		}
		if ( array() === $fields ) {
			return new WP_Error( 'invalid_input', __( 'Supply at least one field to write.', 'acrossai-abilities-manager' ) );
		}

		$updated = array();

		foreach ( self::FIELDS as $field => $key ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}
			$value = $fields[ $field ];
			if ( is_array( $value ) || is_object( $value ) ) {
				return new WP_Error(
					'invalid_input',
					sprintf(
						/* translators: %s: field name */
						__( '"%s" must be a string.', 'acrossai-abilities-manager' ),
						$field
					)
				);
			}
			$value = 'canonical_url' === $field ? esc_url_raw( (string) $value ) : sanitize_text_field( (string) $value );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
			$updated[ $field ] = $value;
		}

		if ( array_key_exists( 'robots', $fields ) ) {
			$robots = $fields['robots'];
			if ( ! is_array( $robots ) ) {
				return new WP_Error( 'invalid_input', __( '"robots" must be a list of directives, not a string — Rank Math stores it as an array.', 'acrossai-abilities-manager' ) );
			}
			$clean = array();
			foreach ( $robots as $directive ) {
				$directive = sanitize_key( (string) $directive );
				if ( ! in_array( $directive, self::ROBOTS, true ) ) {
					return new WP_Error(
						'invalid_input',
						sprintf(
							/* translators: 1: submitted directive, 2: comma-separated valid directives */
							__( 'Unknown robots directive "%1$s". Valid directives: %2$s.', 'acrossai-abilities-manager' ),
							$directive,
							implode( ', ', self::ROBOTS )
						)
					);
				}
				$clean[] = $directive;
			}
			$clean = array_values( array_unique( $clean ) );
			update_post_meta( $post_id, 'rank_math_robots', $clean );
			$updated['robots'] = $clean;
		}

		foreach ( self::FLAGS as $field => $key ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}
			// Rank Math tests these with === 'on'; anything else must be absent
			// rather than a falsy value, or its checks behave unpredictably.
			if ( ! empty( $fields[ $field ] ) ) {
				update_post_meta( $post_id, $key, 'on' );
				$updated[ $field ] = true;
			} else {
				delete_post_meta( $post_id, $key );
				$updated[ $field ] = false;
			}
		}

		return array(
			'post_id' => $post_id,
			'updated' => $updated,
		);
	}

	/**
	 * Bulk-write meta through Rank Math's own endpoint handler.
	 *
	 * Rank Math silently skips rows it cannot process and always returns success, so
	 * processed/skipped are computed here instead of trusting the return.
	 *
	 * @param string                          $object_type 'post' or 'term'.
	 * @param array<int|string,array<string,mixed>> $rows   object id => column => value.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function bulk_update_meta( string $object_type, array $rows ) {
		if ( ! in_array( $object_type, array( 'post', 'term' ), true ) ) {
			return new WP_Error( 'invalid_input', __( 'object_type must be post or term.', 'acrossai-abilities-manager' ) );
		}
		if ( array() === $rows ) {
			return new WP_Error( 'invalid_input', __( 'rows is empty. Nothing to write.', 'acrossai-abilities-manager' ) );
		}
		if ( ! class_exists( '\RankMath\Rest\Post' ) ) {
			return new WP_Error( 'rank_math_missing', __( 'Rank Math\'s REST handler is unavailable.', 'acrossai-abilities-manager' ) );
		}

		$processed = array();
		$skipped   = array();
		$payload   = array();

		foreach ( $rows as $object_id => $columns ) {
			$object_id = absint( $object_id );
			if ( ! is_array( $columns ) || array() === $columns ) {
				$skipped[] = array( 'id' => $object_id, 'reason' => 'no_columns' );
				continue;
			}

			$unknown = array_diff( array_keys( $columns ), self::BULK_COLUMNS );
			if ( array() !== $unknown ) {
				return new WP_Error(
					'unknown_field',
					sprintf(
						/* translators: 1: comma-separated unknown columns, 2: comma-separated allowed columns */
						__( 'Unknown column(s) %1$s. Rank Math\'s bulk endpoint accepts only: %2$s.', 'acrossai-abilities-manager' ),
						implode( ', ', $unknown ),
						implode( ', ', self::BULK_COLUMNS )
					)
				);
			}

			$reason = self::bulk_skip_reason( $object_type, $object_id );
			if ( '' !== $reason ) {
				$skipped[] = array( 'id' => $object_id, 'reason' => $reason );
				continue;
			}

			$payload[ $object_id ] = $columns;
			$processed[]           = $object_id;
		}

		if ( array() === $payload ) {
			return array(
				'object_type' => $object_type,
				'processed'   => array(),
				'skipped'     => $skipped,
			);
		}

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'objectType', 'post' === $object_type ? 'post' : 'term' );
		$request->set_param( 'rows', $payload );

		$handler = new \RankMath\Rest\Post();
		if ( method_exists( $handler, 'update_bulk_meta' ) ) {
			$handler->update_bulk_meta( $request );
		}

		return array(
			'object_type' => $object_type,
			'processed'   => $processed,
			'skipped'     => $skipped,
		);
	}

	/**
	 * Why a bulk row cannot be processed, or '' when it can.
	 *
	 * Mirrors the intent of Rank Math's private can_process(), using its public
	 * accessibility helpers.
	 *
	 * @param string $object_type 'post' or 'term'.
	 * @param int    $object_id   Object id.
	 * @return string
	 */
	private static function bulk_skip_reason( string $object_type, int $object_id ): string {
		if ( $object_id < 1 ) {
			return 'invalid_id';
		}

		if ( 'post' === $object_type ) {
			$post = get_post( $object_id );
			if ( null === $post ) {
				return 'post_not_found';
			}
			if ( ! current_user_can( 'edit_post', $object_id ) ) {
				return 'insufficient_capability';
			}
			// attachment is accessible to Rank Math even though
			// is_post_type_accessible() reports otherwise.
			if ( 'attachment' !== $post->post_type
				&& class_exists( '\RankMath\Helper' )
				&& ! \RankMath\Helper::is_post_type_accessible( $post->post_type ) ) {
				return 'post_type_not_managed_by_rank_math';
			}
			return '';
		}

		$term = get_term( $object_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return 'term_not_found';
		}
		if ( class_exists( '\RankMath\Helper' ) ) {
			$allowed = \RankMath\Helper::get_allowed_taxonomies();
			if ( is_array( $allowed ) && ! in_array( $term->taxonomy, $allowed, true ) ) {
				return 'taxonomy_not_managed_by_rank_math';
			}
		}
		return '';
	}

	/**
	 * Read the primary term for a post and taxonomy.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $taxonomy Taxonomy name.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_primary_term( int $post_id, string $taxonomy ) {
		$editable = self::assert_editable( $post_id );
		if ( is_wp_error( $editable ) ) {
			return $editable;
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error(
				'not_found',
				sprintf(
					/* translators: %s: taxonomy name */
					__( 'The taxonomy "%s" is not registered.', 'acrossai-abilities-manager' ),
					$taxonomy
				)
			);
		}

		$primary_id = (int) get_post_meta( $post_id, 'rank_math_primary_' . $taxonomy, true );
		$assigned   = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'all' ) );
		$assigned   = is_wp_error( $assigned ) ? array() : $assigned;

		$terms = array();
		foreach ( $assigned as $term ) {
			$terms[] = array(
				'term_id'    => (int) $term->term_id,
				'name'       => (string) $term->name,
				'slug'       => (string) $term->slug,
				'is_primary' => (int) $term->term_id === $primary_id,
			);
		}

		$primary = null;
		if ( $primary_id > 0 ) {
			$term = get_term( $primary_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$primary = array(
					'term_id' => (int) $term->term_id,
					'name'    => (string) $term->name,
					'slug'    => (string) $term->slug,
				);
			}
		}

		return array(
			'post_id'      => $post_id,
			'taxonomy'     => $taxonomy,
			'primary_term' => $primary,
			'assigned'     => $terms,
		);
	}

	/**
	 * Set or clear the primary term.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $term_id  Term id, or 0 to clear.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function update_primary_term( int $post_id, string $taxonomy, int $term_id ) {
		$current = self::get_primary_term( $post_id, $taxonomy );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$key = 'rank_math_primary_' . $taxonomy;

		if ( $term_id < 1 ) {
			delete_post_meta( $post_id, $key );
			return array(
				'post_id'      => $post_id,
				'taxonomy'     => $taxonomy,
				'primary_term' => null,
				'cleared'      => true,
			);
		}

		// Rank Math only honours a primary term the post actually has, so setting one
		// it does not have would store a value that is silently ignored.
		$assigned_ids = array_map( static fn( array $t ): int => (int) $t['term_id'], $current['assigned'] );
		if ( ! in_array( $term_id, $assigned_ids, true ) ) {
			return new WP_Error(
				'invalid_input',
				sprintf(
					/* translators: 1: term id, 2: post id, 3: taxonomy name */
					__( 'Term %1$d is not assigned to post %2$d in taxonomy "%3$s". Assign it first — Rank Math ignores a primary term the post does not have.', 'acrossai-abilities-manager' ),
					$term_id,
					$post_id,
					$taxonomy
				)
			);
		}

		update_post_meta( $post_id, $key, $term_id );
		$after = self::get_primary_term( $post_id, $taxonomy );

		return array(
			'post_id'      => $post_id,
			'taxonomy'     => $taxonomy,
			'primary_term' => is_array( $after ) ? $after['primary_term'] : null,
			'cleared'      => false,
		);
	}

	/**
	 * Write SEO scores for a batch of posts.
	 *
	 * Rank Math's own handler silently skips missing posts and out-of-range scores, so
	 * updated/skipped are computed here.
	 *
	 * @param array<int|string,mixed> $scores post id => 0..100.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function update_seo_scores( array $scores ) {
		if ( array() === $scores ) {
			return new WP_Error( 'invalid_input', __( 'scores is empty. Nothing to write.', 'acrossai-abilities-manager' ) );
		}

		$updated = array();
		$skipped = array();

		foreach ( $scores as $post_id => $score ) {
			$post_id = absint( $post_id );
			if ( $post_id < 1 || null === get_post( $post_id ) ) {
				$skipped[] = array( 'id' => $post_id, 'reason' => 'post_not_found' );
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				$skipped[] = array( 'id' => $post_id, 'reason' => 'insufficient_capability' );
				continue;
			}
			if ( ! is_numeric( $score ) ) {
				$skipped[] = array( 'id' => $post_id, 'reason' => 'not_numeric' );
				continue;
			}
			$value = (int) $score;
			if ( $value < 0 || $value > 100 ) {
				$skipped[] = array( 'id' => $post_id, 'reason' => 'out_of_range' );
				continue;
			}

			update_post_meta( $post_id, 'rank_math_seo_score', $value );
			$updated[] = array( 'id' => $post_id, 'score' => $value );
		}

		return array(
			'updated' => $updated,
			'skipped' => $skipped,
		);
	}

	/**
	 * Write schemas through Rank Math's own bulk schema handler.
	 *
	 * Schema keys are 'new-<n>' to add or 'schema-<meta_id>' to update, which is why
	 * this is not idempotent: repeating a payload of new-* keys appends again.
	 *
	 * @param string               $object_type 'post' or 'term' or 'user'.
	 * @param int                  $object_id   Object id.
	 * @param array<string,mixed>  $schemas     Schema payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function update_schemas( string $object_type, int $object_id, array $schemas ) {
		if ( array() === $schemas ) {
			return new WP_Error( 'invalid_input', __( 'schemas is empty. Nothing to write.', 'acrossai-abilities-manager' ) );
		}
		if ( ! class_exists( '\RankMath\Rest\Shared' ) ) {
			return new WP_Error( 'rank_math_missing', __( 'Rank Math\'s schema handler is unavailable.', 'acrossai-abilities-manager' ) );
		}
		if ( 'post' === $object_type ) {
			$editable = self::assert_editable( $object_id );
			if ( is_wp_error( $editable ) ) {
				return $editable;
			}
		}

		foreach ( array_keys( $schemas ) as $key ) {
			$key = (string) $key;
			if ( ! preg_match( '/^(new-\d+|schema-\d+)$/', $key ) ) {
				return new WP_Error(
					'invalid_input',
					sprintf(
						/* translators: %s: submitted schema key */
						__( 'Invalid schema key "%s". Use "new-1", "new-2", … to add, or "schema-<meta_id>" to update an existing schema.', 'acrossai-abilities-manager' ),
						$key
					)
				);
			}
		}

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'objectType', $object_type );
		$request->set_param( 'objectID', $object_id );
		$request->set_param( 'schemas', $schemas );

		$handler = new \RankMath\Rest\Shared();
		if ( ! method_exists( $handler, 'update_schemas' ) ) {
			return new WP_Error( 'rank_math_missing', __( 'This Rank Math build does not expose bulk schema updates.', 'acrossai-abilities-manager' ) );
		}
		$handler->update_schemas( $request );

		return array(
			'object_type' => $object_type,
			'object_id'   => $object_id,
			'saved'       => array_keys( $schemas ),
		);
	}

	/**
	 * Delete every schema attached to a post.
	 *
	 * @param int $post_id Post id.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function delete_schemas( int $post_id ) {
		$editable = self::assert_editable( $post_id );
		if ( is_wp_error( $editable ) ) {
			return $editable;
		}
		if ( ! class_exists( '\RankMath\Schema\DB' ) ) {
			return new WP_Error( 'rank_math_module_inactive', __( 'The Rank Math schema module is not available.', 'acrossai-abilities-manager' ) );
		}

		$before = \RankMath\Schema\DB::get_schemas( $post_id );
		$count  = is_array( $before ) ? count( $before ) : 0;

		\RankMath\Schema\DB::delete_schema_data( $post_id );

		return array(
			'post_id' => $post_id,
			'deleted' => $count,
		);
	}
}

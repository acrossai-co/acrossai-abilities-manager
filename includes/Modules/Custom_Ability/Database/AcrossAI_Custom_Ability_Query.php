<?php
/**
 * BerlinDB Query builder for custom abilities.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability\Database
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database;

use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Protected_Custom_Abilities;
use BerlinDB\Database\Query;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class AcrossAI_Custom_Ability_Query
 *
 * Query helper for filtering, searching, and retrieving custom abilities.
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Query extends Query {

	/**
	 * Schema class for this query.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $table_schema = AcrossAI_Custom_Ability_Schema::class;

	/**
	 * Row class for query results.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $item_shape = AcrossAI_Custom_Ability_Row::class;

	/**
	 * Table name without WordPress prefix.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $table_name = 'acrossai_custom_abilities';

	/**
	 * Name of the index to select.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $index = 'id';

	/**
	 * Accumulated BerlinDB query arguments.
	 *
	 * @since 0.0.1
	 * @var array<string,mixed>
	 */
	protected $args = array();

	/**
	 * Whether protected slug filtering should run after query.
	 *
	 * @since 0.0.1
	 * @var bool
	 */
	protected $filter_protected_prefixes = false;

	/**
	 * Singleton instance.
	 *
	 * @since 0.0.1
	 * @var self|null
	 */
	protected static $_instance = null;

	/**
	 * Get singleton instance.
	 *
	 * Use this for CRUD operations (insert, get, update, delete).
	 * For chainable queries (search, filter, paginate) use new self() to
	 * avoid stale args from a shared instance.
	 *
	 * @since 0.0.1
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Insert a new ability.
	 *
	 * @since 0.0.1
	 * @param array $data Column => value pairs.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function insert_ability( array $data ) {
		$result = $this->add_item( $data );
		return ( false !== $result && (int) $result > 0 ) ? (int) $result : false;
	}

	/**
	 * Get a single ability by ID.
	 *
	 * @since 0.0.1
	 * @param int $id Row ID.
	 * @return AcrossAI_Custom_Ability_Row|null
	 */
	public function get_ability_by_id( int $id ): ?AcrossAI_Custom_Ability_Row {
		$results = $this->query( array( 'id' => $id, 'number' => 1 ) );
		if ( empty( $results ) || ! $results[0] instanceof AcrossAI_Custom_Ability_Row ) {
			return null;
		}
		return $results[0];
	}

	/**
	 * Update an ability by ID.
	 *
	 * @since 0.0.1
	 * @param int   $id   Row ID.
	 * @param array $data Column => value pairs to update.
	 * @return bool
	 */
	public function update_ability( int $id, array $data ): bool {
		return (bool) $this->update_item( $id, $data );
	}

	/**
	 * Delete an ability by ID.
	 *
	 * @since 0.0.1
	 * @param int $id Row ID.
	 * @return bool
	 */
	public function delete_ability( int $id ): bool {
		return (bool) $this->delete_item( $id );
	}

	/**
	 * Check whether a slug already exists in the table.
	 *
	 * @since 0.0.1
	 * @param string $slug Ability slug.
	 * @return bool True if slug exists.
	 */
	public function slug_exists( string $slug ): bool {
		$results = $this->query( array( 'ability_slug' => $slug, 'number' => 1 ) );
		return ! empty( $results );
	}

	/**
	 * Return the count of abilities matching args (no pagination).
	 *
	 * @since 0.0.1
	 * @param array $args BerlinDB query args (number/offset ignored).
	 * @return int
	 */
	public function count_abilities( array $args = array() ): int {
		$args['count'] = true;
		$args['number'] = PHP_INT_MAX;
		$args['offset'] = 0;
		$result = $this->query( $args );
		return is_numeric( $result ) ? (int) $result : count( (array) $result );
	}

	/**
	 * Filter by ability slug.
	 *
	 * @since 0.0.1
	 * @param string $slug Ability slug (e.g., "custom/my-ability").
	 * @return self For method chaining.
	 */
	public function by_slug( $slug = '' ) {
		$this->args['ability_slug'] = sanitize_text_field( (string) $slug );
		return $this;
	}

	/**
	 * Filter to only enabled abilities.
	 *
	 * @since 0.0.1
	 * @return self For method chaining.
	 */
	public function enabled_only() {
		$this->args['enabled'] = 1;
		return $this;
	}

	/**
	 * Search by label, description, or slug.
	 *
	 * @since 0.0.1
	 * @param string $search Search term.
	 * @return self For method chaining.
	 */
	public function search( $search = '' ) {
		$search = sanitize_text_field( (string) $search );
		if ( '' === $search ) {
			return $this;
		}

		$this->args['search']         = $search;
		$this->args['search_columns'] = array( 'ability_slug', 'label', 'description' );
		$this->filter_protected_prefixes = true;

		/**
		 * Customize query layer filtering.
		 *
		 * @since 0.0.1
		 * @param array  $query_args Current query arguments.
		 * @param string $context    Context (e.g., 'list', 'read', 'mcp').
		 */
		do_action( 'acrossai_custom_ability_query_filters', $this->args, 'search' );

		return $this;
	}

	/**
	 * Set up pagination.
	 *
	 * @since 0.0.1
	 * @param int $per_page Items per page.
	 * @param int $page     Page number (1-based).
	 * @return self For method chaining.
	 */
	public function with_pagination( $per_page = 20, $page = 1 ) {
		$per_page = max( 1, min( 100, absint( $per_page ) ) );
		$page     = max( 1, absint( $page ) );

		$this->args['number'] = $per_page;
		$this->args['offset'] = ( $page - 1 ) * $per_page;

		return $this;
	}

	/**
	 * Order results by column.
	 *
	 * @since 0.0.1
	 * @param string $by    Column name (slug, label, created_at, updated_at).
	 * @param string $order Sort order (asc, desc).
	 * @return self For method chaining.
	 */
	public function order_by( $by = 'id', $order = 'ASC' ) {
		$allowed_columns = array( 'id', 'ability_slug', 'label', 'created_at', 'updated_at', 'enabled' );
		$by              = in_array( $by, $allowed_columns, true ) ? $by : 'id';
		$order           = ( 'DESC' === strtoupper( $order ) ) ? 'DESC' : 'ASC';

		$this->args['orderby'] = $by;
		$this->args['order']   = $order;

		return $this;
	}

	/**
	 * Get results from query.
	 *
	 * @since 0.0.1
	 * @return array Array of AcrossAI_Custom_Ability_Row objects.
	 */
	public function get() {
		$results = $this->query( $this->args );

		if ( ! $this->filter_protected_prefixes || empty( $results ) ) {
			return $results;
		}

		$protected_prefixes = AcrossAI_Protected_Custom_Abilities::get_protected_prefixes( 'custom_abilities' );
		if ( empty( $protected_prefixes ) ) {
			return $results;
		}

		$filtered = array();
		foreach ( $results as $row ) {
			$slug       = (string) ( $row->ability_slug ?? '' );
			$is_blocked = false;

			foreach ( $protected_prefixes as $prefix ) {
				if ( 0 === strpos( $slug, $prefix . '/' ) ) {
					$is_blocked = true;
					break;
				}
			}

			if ( ! $is_blocked ) {
				$filtered[] = $row;
			}
		}

		return $filtered;
	}
}

<?php
/**
 * Manager repository.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Data-access layer for the abilities override table.
 *
 * All public methods return typed PHP values; raw database strings are
 * normalized to booleans, integers, and arrays before being returned.
 * All write methods sanitize inputs before persisting.
 *
 * Read queries are backed by WordPress object cache (group `aam_overrides`).
 * Write methods bust the relevant cache keys so subsequent reads stay fresh.
 *
 * @since   0.1.0
 * @package AcrossAI_Abilities_Manager
 */
class Repository {

	/**
	 * Object-cache group used for all repository cache entries.
	 */
	private const CACHE_GROUP = 'aam_overrides';

	/**
	 * Retrieves a paginated, filtered list of stored overrides.
	 *
	 * When called with `per_page => 0` and no filters the full-list result is
	 * served from the object cache (`all_rows` key). Filtered or paginated calls
	 * always hit the database directly since the parameter space is too wide for
	 * useful cache hits and admin screens need fresh data after writes.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional query arguments.
	 *
	 *     @type string $provider  Filter rows by exact provider string. Default ''.
	 *     @type string $search    LIKE filter against ability_slug. Default ''.
	 *     @type int    $page      1-based page number. Default 1.
	 *     @type int    $per_page  Rows per page; 0 disables pagination. Default 20.
	 *     @type string $orderby   Column to sort by (whitelisted). Default 'ability_slug'.
	 *     @type string $order     Sort direction: 'ASC' or 'DESC'. Default 'ASC'.
	 * }
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
	 */
	public static function get_all( array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'provider' => '',
				'search'   => '',
				'page'     => 1,
				'per_page' => 20,
				'orderby'  => 'ability_slug',
				'order'    => 'ASC',
			)
		);

		$per_page     = max( 0, (int) $args['per_page'] );
		$is_full_list = ( 0 === $per_page && '' === $args['provider'] && '' === $args['search'] );

		// Serve the full unfiltered list from the object cache when available.
		if ( $is_full_list ) {
			$cached = wp_cache_get( 'all_rows', self::CACHE_GROUP );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$table  = Schema::get_table_name();
		$where  = array( '1 = %d' );
		$params = array( 1 );

		// Append a provider WHERE clause only when the caller supplies a specific provider.
		if ( '' !== $args['provider'] ) {
			$where[]  = 'provider = %s';
			$params[] = sanitize_text_field( (string) $args['provider'] );
		}

		// Append a LIKE search clause only when the caller provides a search term.
		if ( '' !== $args['search'] ) {
			$where[]  = 'ability_slug LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
		}

		// Whitelist the orderby column to prevent SQL injection via query params.
		$orderby      = in_array( $args['orderby'], array( 'ability_slug', 'provider', 'updated_at', 'created_at' ), true ) ? $args['orderby'] : 'ability_slug';
		$order        = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$total_sql    = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
		$total        = (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$page         = max( 1, (int) $args['page'] );
		$pages        = $per_page > 0 ? (int) ceil( $total / $per_page ) : ( $total > 0 ? 1 : 0 );
		$sql          = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order}";
		$query_params = $params;

		// Add LIMIT / OFFSET only when pagination is active; per_page=0 returns all rows.
		if ( $per_page > 0 ) {
			$sql           .= ' LIMIT %d OFFSET %d';
			$query_params[] = $per_page;
			$query_params[] = ( $page - 1 ) * $per_page;
		}

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter

		$result = array(
			'items'    => array_map( array( __CLASS__, 'prepare_row' ), is_array( $rows ) ? $rows : array() ),
			'total'    => $total,
			'pages'    => $pages,
			'page'     => $page,
			'per_page' => $per_page,
		);

		// Cache the full unfiltered list for use by prime_overrides() and List_Table.
		if ( $is_full_list ) {
			wp_cache_set( 'all_rows', $result, self::CACHE_GROUP );
		}

		return $result;
	}

	/**
	 * Retrieves a single normalized override row by ability slug.
	 *
	 * @param string $slug Ability slug to look up.
	 * @return array<string, mixed>|null Normalized row array, or null if not found.
	 */
	public static function get_by_slug( string $slug ): ?array {
		$row = self::get_raw_by_slug( $slug );
		return is_array( $row ) ? self::prepare_row( $row ) : null;
	}

	/**
	 * Retrieves a single normalized override row by its primary-key ID.
	 *
	 * On a cache miss the raw row is stored under both the `id:{$id}` and
	 * `slug:{$slug}` keys so a subsequent get_by_slug() call for the same
	 * ability is served from the cache without a second DB query.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $id Row ID to look up.
	 * @return array<string, mixed>|null Normalized row array, or null if not found.
	 */
	public static function get_by_id( int $id ): ?array {
		// Check object cache before hitting the database.
		$cached = wp_cache_get( "id:{$id}", self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_array( $cached ) ? self::prepare_row( $cached ) : null;
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::get_table_name() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter

		// Cache the raw row (or a null sentinel) under both id and slug keys.
		$cache_value = is_array( $row ) ? $row : null;
		wp_cache_set( "id:{$id}", $cache_value, self::CACHE_GROUP );
		if ( is_array( $row ) && ! empty( $row['ability_slug'] ) ) {
			// Prime the slug cache to avoid a second DB hit when callers chain get_by_id() + get_by_slug().
			wp_cache_set( 'slug:' . $row['ability_slug'], $row, self::CACHE_GROUP );
		}

		return is_array( $row ) ? self::prepare_row( $row ) : null;
	}

	/**
	 * Returns all normalized override rows that belong to a given provider.
	 *
	 * Delegates to get_all() with pagination disabled so the full set is returned.
	 *
	 * @param string $provider Provider slug to filter by.
	 * @return array<int, array<string, mixed>> List of normalized override rows.
	 */
	public static function get_by_provider( string $provider ): array {
		$result = self::get_all(
			array(
				'provider' => $provider,
				'per_page' => 0,
			)
		);
		return $result['items'];
	}

	/**
	 * Returns a map of provider slug to the number of stored overrides for that provider.
	 *
	 * Uses a GROUP BY query so a single round-trip retrieves counts for all
	 * providers rather than one query per provider. The result is cached under
	 * the `provider_counts` key and busted on every write.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return array<string, int> Associative array of provider => count.
	 */
	public static function count_by_provider(): array {
		// Serve from cache when available — counts only change after a write.
		$cached = wp_cache_get( 'provider_counts', self::CACHE_GROUP );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		global $wpdb;
		$table  = Schema::get_table_name();
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT provider, COUNT(*) AS count FROM {$table} WHERE 1 = %d GROUP BY provider", 1 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['provider'] ] = (int) $row['count'];
		}

		wp_cache_set( 'provider_counts', $counts, self::CACHE_GROUP );

		return $counts;
	}

	/**
	 * Inserts a new override row or updates the existing one for the given slug.
	 *
	 * The method checks for an existing row first so it can choose between
	 * $wpdb->update() (preserves the original created_at timestamp) and
	 * $wpdb->insert() (sets both timestamps on the new row).
	 * Cache entries for this slug and the global list/count keys are busted
	 * after every successful write.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string               $slug Ability slug that identifies the override row.
	 * @param array<string, mixed> $data Incoming field values to persist.
	 * @return array<string, mixed>|null Fresh normalized row after the write, or null on DB failure.
	 */
	public static function upsert( string $slug, array $data ): ?array {
		global $wpdb;
		$slug     = sanitize_text_field( $slug );
		$existing = self::get_raw_by_slug( $slug );
		$record   = self::build_record( $slug, $data, $existing );

		// Update the row if it already exists; otherwise insert a fresh row.
		if ( is_array( $existing ) ) {
			$result = $wpdb->update( Schema::get_table_name(), $record, array( 'ability_slug' => $slug ), self::formats( $record ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- write query; bust_cache() invalidates all related cache keys after this line.
		} else {
			$result = $wpdb->insert( Schema::get_table_name(), $record, self::formats( $record ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		// Return null so callers can detect the failure without a separate exists() call.
		if ( false === $result ) {
			return null;
		}

		// Bust all cache entries affected by this write.
		self::bust_cache( $slug, $existing );

		return self::get_by_slug( $slug );
	}


	/**
	 * Deletes the override row for the given ability slug.
	 *
	 * The existing row is fetched before deletion so its primary-key ID can be
	 * removed from the object cache along with the slug and global list keys.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $slug Ability slug whose override row should be removed.
	 * @return bool True on successful deletion, false if the query failed.
	 */
	public static function delete( string $slug ): bool {
		global $wpdb;

		// Fetch the row before deleting so we can invalidate its ID cache key.
		$existing = self::get_raw_by_slug( $slug );

		$deleted = $wpdb->delete( Schema::get_table_name(), array( 'ability_slug' => sanitize_text_field( $slug ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- write query; bust_cache() invalidates all related cache keys on success.

		if ( false !== $deleted ) {
			self::bust_cache( $slug, $existing );
		}

		return false !== $deleted;
	}

	/**
	 * Checks whether a stored override row exists for the given ability slug.
	 *
	 * Delegates to get_raw_by_slug() so the check is served from the object
	 * cache when available, avoiding a separate COUNT query.
	 *
	 * @param string $slug Ability slug to check.
	 * @return bool True when an override row is stored, false otherwise.
	 */
	public static function exists( string $slug ): bool {
		return null !== self::get_raw_by_slug( $slug );
	}

	/**
	 * Fetches the raw (un-normalized) database row for an ability slug.
	 *
	 * The raw row is cached under the `slug:{$slug}` key so repeated calls
	 * within the same request (or across requests on a persistent object cache)
	 * are served without hitting the database.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $slug Ability slug to look up.
	 * @return array<string, mixed>|null Raw row array, or null if not found.
	 */
	private static function get_raw_by_slug( string $slug ): ?array {
		$cache_key = "slug:{$slug}";

		// Return the cached raw row when it exists (false means cache miss; null means "not in DB").
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::get_table_name() . ' WHERE ability_slug = %s', sanitize_text_field( $slug ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter

		// Cache the raw row or a null sentinel so future calls skip the DB.
		wp_cache_set( $cache_key, is_array( $row ) ? $row : null, self::CACHE_GROUP );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Invalidates all object-cache entries that may be stale after a write.
	 *
	 * Deletes the per-slug key, the per-ID key (when the existing row is known),
	 * the full unfiltered list, and the provider counts aggregate.
	 *
	 * @param string                    $slug     Ability slug that was written or deleted.
	 * @param array<string, mixed>|null $existing Raw row that existed before the write, if any.
	 * @return void
	 */
	private static function bust_cache( string $slug, ?array $existing ): void {
		wp_cache_delete( "slug:{$slug}", self::CACHE_GROUP );

		// Also remove the ID-keyed entry when we know the row's primary key.
		if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
			wp_cache_delete( "id:{$existing['id']}", self::CACHE_GROUP );
		}

		wp_cache_delete( 'all_rows', self::CACHE_GROUP );
		wp_cache_delete( 'provider_counts', self::CACHE_GROUP );
	}

	/**
	 * Normalizes a raw database row into a typed PHP array.
	 *
	 * All TINYINT boolean columns are converted to nullable booleans via to_bool().
	 * The custom_meta JSON column is decoded into a PHP array (or null).
	 *
	 * @param array<string, mixed> $row Raw associative row from $wpdb.
	 * @return array<string, mixed> Typed row with normalized values.
	 */
	private static function prepare_row( array $row ): array {
		return array(
			'id'           => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'ability_slug' => (string) ( $row['ability_slug'] ?? '' ),
			'provider'     => (string) ( $row['provider'] ?? '' ),
			'site_allowed' => self::to_bool( $row['site_allowed'] ?? null ),
			'readonly'     => self::to_bool( $row['readonly'] ?? null ),
			'destructive'  => self::to_bool( $row['destructive'] ?? null ),
			'idempotent'   => self::to_bool( $row['idempotent'] ?? null ),
			'show_in_rest' => self::to_bool( $row['show_in_rest'] ?? null ),
			'mcp_public'   => self::to_bool( $row['mcp_public'] ?? null ),
			'mcp_type'     => (string) ( $row['mcp_type'] ?? '' ),
			'custom_meta'  => self::decode_meta( $row['custom_meta'] ?? null ),
			'created_at'   => (string) ( $row['created_at'] ?? '' ),
			'updated_at'   => (string) ( $row['updated_at'] ?? '' ),
		);
	}


	/**
	 * Builds the associative record array to pass to $wpdb->insert() or $wpdb->update().
	 *
	 * Merges incoming $data with any $existing row values so that fields omitted
	 * from the current save operation retain their previously stored values.
	 * The `created_at` timestamp is only included for new rows (when $existing is null).
	 *
	 * @param string                    $slug     Ability slug for the row.
	 * @param array<string, mixed>      $data     Incoming field values for this save.
	 * @param array<string, mixed>|null $existing Existing raw row, if one is stored.
	 * @return array<string, mixed> Record array ready for $wpdb insert/update.
	 */
	private static function build_record( string $slug, array $data, ?array $existing ): array {
		$provider    = isset( $data['provider'] ) ? sanitize_text_field( (string) $data['provider'] ) : (string) ( $existing['provider'] ?? self::detect_provider( $slug ) );
		$custom_meta = array_key_exists( 'custom_meta', $data ) ? self::encode_meta( $data['custom_meta'] ) : ( $existing['custom_meta'] ?? null );
		$timestamp   = current_time( 'mysql' );
		$record      = array(
			'ability_slug' => $slug,
			'provider'     => $provider,
			'site_allowed' => self::from_bool( self::incoming_value( $data, $existing, 'site_allowed' ) ),
			'readonly'     => self::from_bool( self::incoming_value( $data, $existing, 'readonly' ) ),
			'destructive'  => self::from_bool( self::incoming_value( $data, $existing, 'destructive' ) ),
			'idempotent'   => self::from_bool( self::incoming_value( $data, $existing, 'idempotent' ) ),
			'show_in_rest' => self::from_bool( self::incoming_value( $data, $existing, 'show_in_rest' ) ),
			'mcp_public'   => self::from_bool( self::incoming_value( $data, $existing, 'mcp_public' ) ),
			'mcp_type'     => self::nullable_string( self::incoming_value( $data, $existing, 'mcp_type' ) ),
			'custom_meta'  => $custom_meta,
			'updated_at'   => $timestamp,
		);

		// `created_at` is only added for new inserts; updates must not overwrite it.
		if ( ! is_array( $existing ) ) {
			$record['created_at'] = $timestamp;
		}

		return $record;
	}


	/**
	 * Resolves an incoming value for persistence while preserving explicit nulls.
	 *
	 * The repository uses this helper instead of the null coalescing operator so
	 * callers can intentionally clear a stored override by passing `null` as a
	 * value for a field in $data.
	 *
	 * @param array<string, mixed>      $data     Incoming values for the save operation.
	 * @param array<string, mixed>|null $existing Existing row, if one is already stored.
	 * @param string                    $field    Field name to resolve.
	 * @return mixed Resolved value: from $data when present, from $existing otherwise, or null.
	 */
	private static function incoming_value( array $data, ?array $existing, string $field ) {
		// The incoming data takes priority, including when the value is explicitly null.
		if ( array_key_exists( $field, $data ) ) {
			return $data[ $field ];
		}

		return $existing[ $field ] ?? null;
	}

	/**
	 * Returns an array of printf-style SQL format specifiers for a record array.
	 *
	 * Each value is mapped to `%d` for integers or `%s` for everything else.
	 * The resulting array is passed as the $format argument to $wpdb->insert()
	 * and $wpdb->update() so that WordPress can bind values safely.
	 *
	 * @param array<string, mixed> $record Record array whose values need format strings.
	 * @return array<int, string> Array of '%d' or '%s' strings in the same order as $record.
	 */
	private static function formats( array $record ): array {
		return array_map(
			static function ( $value ): string {
				return is_int( $value ) ? '%d' : '%s';
			},
			$record
		);
	}

	/**
	 * Converts a nullable boolean to a TINYINT(1) database value.
	 *
	 * Returns null for null (stored as SQL NULL), 1 for true, and 0 for false.
	 *
	 * @param mixed $value Value to convert.
	 * @return int|null 1, 0, or null.
	 */
	private static function from_bool( $value ): ?int {
		$value = self::normalize_bool( $value );
		return null === $value ? null : ( $value ? 1 : 0 );
	}

	/**
	 * Converts a TINYINT(1) database value to a nullable PHP boolean.
	 *
	 * SQL NULL and empty strings are returned as PHP null to preserve the
	 * tri-state semantics used by this plugin (true / false / not set).
	 *
	 * @param mixed $value Raw database value to convert.
	 * @return bool|null True, false, or null.
	 */
	private static function to_bool( $value ): ?bool {
		return null === $value || '' === $value ? null : (bool) (int) $value;
	}

	/**
	 * Normalizes a wide variety of truthy/falsy inputs to a nullable boolean.
	 *
	 * Accepts PHP booleans, integers, numeric strings, and common string
	 * representations ('true', 'yes', 'on', 'false', 'no', 'off').
	 * Returns null for null, empty string, and the literal string 'null',
	 * enabling callers to distinguish "not set" from "explicitly false".
	 *
	 * @param mixed $value Value to normalize.
	 * @return bool|null Normalized boolean, or null when the input is absent/indeterminate.
	 */
	private static function normalize_bool( $value ): ?bool {
		// Treat null, empty string, and the literal 'null' as "no value stored".
		if ( null === $value || '' === $value || 'null' === $value ) {
			return null;
		}

		// PHP booleans are already in the right form.
		if ( is_bool( $value ) ) {
			return $value;
		}

		// Cast numeric types (0 → false, anything else → true).
		if ( is_numeric( $value ) ) {
			return (bool) (int) $value;
		}

		$value = strtolower( trim( (string) $value ) );

		// Accept common English affirmative strings.
		if ( in_array( $value, array( 'true', 'yes', 'on' ), true ) ) {
			return true;
		}

		// Accept common English negative strings.
		if ( in_array( $value, array( 'false', 'no', 'off' ), true ) ) {
			return false;
		}

		return null;
	}


	/**
	 * Converts an input to a sanitized nullable string.
	 *
	 * Empty strings are stored as NULL so that the database column can
	 * distinguish "not set" from an empty string override.
	 *
	 * @param mixed $value Value to convert.
	 * @return string|null Sanitized string, or null for null/empty input.
	 */
	private static function nullable_string( $value ): ?string {
		if ( null === $value ) {
			return null;
		}
		$value = sanitize_text_field( (string) $value );
		// Return null instead of an empty string to preserve the "not set" state.
		return '' === $value ? null : $value;
	}

	/**
	 * JSON-encodes the custom_meta field for storage.
	 *
	 * When the value is already a valid JSON string it is decoded and
	 * re-encoded through wp_json_encode() to normalize formatting. When
	 * it is a PHP array or object it is encoded directly.
	 *
	 * @param mixed $value Value to encode.
	 * @return string|null JSON string, or null for null/empty input.
	 */
	private static function encode_meta( $value ): ?string {
		// Treat null and empty string as "no metadata" rather than encoding them.
		if ( null === $value || '' === $value ) {
			return null;
		}

		// If the value is already a JSON string, decode and re-encode to normalise it.
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				return wp_json_encode( $decoded );
			}
		}

		return wp_json_encode( $value );
	}

	/**
	 * JSON-decodes the custom_meta column value from storage.
	 *
	 * Returns null when the stored value is empty or when json_decode() reports
	 * a parse error, preventing corrupt JSON from propagating to callers.
	 *
	 * @param mixed $value Raw database value to decode.
	 * @return mixed Decoded PHP value, or null on failure.
	 */
	private static function decode_meta( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$decoded = json_decode( (string) $value, true );
		// Return null instead of a partial result when the stored JSON is malformed.
		return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
	}

	/**
	 * Infers the provider identifier from an ability's namespace segment.
	 *
	 * The namespace is the first path segment of the ability slug before the
	 * first `/`. Known WordPress core namespaces map to the string `core`.
	 * Active theme slugs map to `theme:<slug>`. Everything else is returned
	 * as-is and treated as a plugin provider.
	 *
	 * @param string $slug Ability slug to inspect.
	 * @return string Provider identifier such as 'core', 'theme:my-theme', or a plugin slug.
	 */
	private static function detect_provider( string $slug ): string {
		$namespace = sanitize_key( explode( '/', $slug )[0] ?? '' );

		// Map well-known WordPress core namespaces to a single canonical provider string.
		if ( in_array( $namespace, array( 'wordpress', 'wp', 'core' ), true ) ) {
			return 'core';
		}

		$stylesheet = sanitize_key( (string) get_stylesheet() );
		$template   = sanitize_key( (string) get_template() );

		// If the namespace matches the active theme (child or parent), tag it as a theme provider.
		if ( in_array( $namespace, array( $stylesheet, $template ), true ) ) {
			return 'theme:' . $namespace;
		}

		// Fall back to the raw namespace, or 'unknown' if the slug has no namespace segment.
		return '' !== $namespace ? $namespace : 'unknown';
	}

	/**
	 * Retrieves a custom ability by slug.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $slug Custom ability slug.
	 * @return array<string, mixed>|null Normalized custom ability row, or null if not found.
	 */
	public static function get_custom_ability( string $slug ): ?array {
		$row = self::get_raw_custom_ability( $slug );
		return is_array( $row ) ? self::normalize_custom_ability( $row ) : null;
	}

	/**
	 * Retrieves all custom abilities with optional filtering and pagination.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional query arguments.
	 *     @type string $status     Filter by status (active/draft/archived). Default ''.
	 *     @type string $category   Filter by category. Default ''.
	 *     @type string $search     LIKE filter against ability_slug or label. Default ''.
	 *     @type int    $page       1-based page number. Default 1.
	 *     @type int    $per_page   Rows per page; 0 disables pagination. Default 20.
	 *     @type string $orderby    Column to sort by (whitelisted). Default 'ability_slug'.
	 *     @type string $order      Sort direction: 'ASC' or 'DESC'. Default 'ASC'.
	 * }
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
	 */
	public static function get_all_custom_abilities( array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'category' => '',
				'search'   => '',
				'page'     => 1,
				'per_page' => 20,
				'orderby'  => 'ability_slug',
				'order'    => 'ASC',
			)
		);

		$table      = Schema::get_custom_abilities_table_name();
		$per_page   = max( 0, (int) $args['per_page'] );
		$page       = max( 1, (int) $args['page'] );
		$offset     = ( $page - 1 ) * $per_page;
		$orderby    = sanitize_key( $args['orderby'] );
		$order      = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// Whitelist allowed orderby columns.
		$allowed_orderby = array( 'ability_slug', 'label', 'created_at', 'status', 'category' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'ability_slug';
		}

		$where_clauses = array( '1 = 1' );

		// Filter by status.
		if ( ! empty( $args['status'] ) ) {
			$status            = sanitize_text_field( $args['status'] );
			$where_clauses[]   = $wpdb->prepare( 'status = %s', $status );
		}

		// Filter by category.
		if ( ! empty( $args['category'] ) ) {
			$category          = sanitize_text_field( $args['category'] );
			$where_clauses[]   = $wpdb->prepare( 'category = %s', $category );
		}

		// Search in ability_slug and label.
		if ( ! empty( $args['search'] ) ) {
			$search            = sanitize_text_field( $args['search'] );
			$search_sql        = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[]   = $wpdb->prepare( '(ability_slug LIKE %s OR label LIKE %s)', $search_sql, $search_sql );
		}

		$where = implode( ' AND ', $where_clauses );

		// Get total count.
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

		// Build main query with pagination.
		$query = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $per_page > 0 ) {
			$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );
		}

		$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

		// Normalize each row.
		$items = array_map( array( self::class, 'normalize_custom_ability' ), (array) $rows );

		// Calculate pagination.
		$pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		return array(
			'items'    => $items,
			'total'    => (int) $total,
			'pages'    => $pages,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Inserts or updates a custom ability.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string               $slug Custom ability slug.
	 * @param array<string, mixed> $data Custom ability data.
	 * @return array<string, mixed>|null Fresh normalized row after the write, or null on DB failure.
	 */
	public static function upsert_custom_ability( string $slug, array $data ): ?array {
		global $wpdb;
		$slug     = sanitize_text_field( $slug );
		$existing = self::get_raw_custom_ability( $slug );
		$record   = self::build_custom_ability_record( $slug, $data, $existing );

		// Update or insert.
		if ( is_array( $existing ) ) {
			$result = $wpdb->update(
				Schema::get_custom_abilities_table_name(),
				$record,
				array( 'ability_slug' => $slug ),
				self::formats( $record ),
				array( '%s' )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$result = $wpdb->insert(
				Schema::get_custom_abilities_table_name(),
				$record,
				self::formats( $record )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		if ( false === $result ) {
			return null;
		}

		return self::get_custom_ability( $slug );
	}

	/**
	 * Deletes a custom ability by slug.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $slug Custom ability slug.
	 * @return bool True on successful deletion, false otherwise.
	 */
	public static function delete_custom_ability( string $slug ): bool {
		global $wpdb;
		$slug    = sanitize_text_field( $slug );
		$deleted = $wpdb->delete(
			Schema::get_custom_abilities_table_name(),
			array( 'ability_slug' => $slug ),
			array( '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return false !== $deleted;
	}

	/**
	 * Retrieves raw custom ability data from the database by slug.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $slug Custom ability slug.
	 * @return array<string, mixed>|null Raw database row, or null if not found.
	 */
	private static function get_raw_custom_ability( string $slug ): ?array {
		global $wpdb;
		$slug = sanitize_text_field( $slug );
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::get_custom_abilities_table_name() . ' WHERE ability_slug = %s',
				$slug
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Normalizes a custom ability row from the database.
	 *
	 * Converts string types to their proper PHP types (JSON to arrays, etc.).
	 *
	 * @param array<string, mixed> $row Raw database row.
	 * @return array<string, mixed> Normalized row.
	 */
	private static function normalize_custom_ability( array $row ): array {
		return array(
			'id'                  => (int) $row['id'],
			'ability_slug'        => (string) $row['ability_slug'],
			'label'               => (string) $row['label'],
			'description'         => isset( $row['description'] ) ? (string) $row['description'] : '',
			'category'            => isset( $row['category'] ) ? (string) $row['category'] : '',
			'status'              => isset( $row['status'] ) ? (string) $row['status'] : 'active',
			'input_schema'        => isset( $row['input_schema'] ) ? (array) json_decode( $row['input_schema'], true ) : array(),
			'output_schema'       => isset( $row['output_schema'] ) ? (array) json_decode( $row['output_schema'], true ) : array(),
			'execute_callback'    => isset( $row['execute_callback'] ) ? (string) $row['execute_callback'] : '',
			'permission_callback' => isset( $row['permission_callback'] ) ? (string) $row['permission_callback'] : '',
			'readonly'            => isset( $row['readonly'] ) ? (int) $row['readonly'] : null,
			'destructive'         => isset( $row['destructive'] ) ? (int) $row['destructive'] : null,
			'idempotent'          => isset( $row['idempotent'] ) ? (int) $row['idempotent'] : null,
			'show_in_rest'        => isset( $row['show_in_rest'] ) ? (int) $row['show_in_rest'] : null,
			'mcp_public'          => isset( $row['mcp_public'] ) ? (int) $row['mcp_public'] : null,
			'mcp_type'            => isset( $row['mcp_type'] ) ? (string) $row['mcp_type'] : '',
			'custom_meta'         => isset( $row['custom_meta'] ) ? (array) json_decode( $row['custom_meta'], true ) : array(),
			'created_by'          => isset( $row['created_by'] ) ? (int) $row['created_by'] : null,
			'version'             => isset( $row['version'] ) ? (string) $row['version'] : '1.0',
			'deprecated_at'       => isset( $row['deprecated_at'] ) ? (string) $row['deprecated_at'] : null,
			'created_at'          => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
			'updated_at'          => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
		);
	}

	/**
	 * Builds a custom ability record for insertion or update.
	 *
	 * @param string               $slug     Custom ability slug.
	 * @param array<string, mixed> $data     Incoming data.
	 * @param array<string, mixed>|null $existing Existing row if updating.
	 * @return array<string, mixed> Database record to insert/update.
	 */
	private static function build_custom_ability_record( string $slug, array $data, ?array $existing ): array {
		$record = array(
			'ability_slug'        => sanitize_text_field( $slug ),
			'label'               => sanitize_text_field( $data['label'] ?? '' ),
			'description'         => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
			'category'            => sanitize_text_field( $data['category'] ?? '' ),
			'status'              => in_array( $data['status'] ?? 'active', array( 'active', 'draft', 'archived' ), true ) ? sanitize_text_field( $data['status'] ) : 'active',
			'input_schema'        => isset( $data['input_schema'] ) ? wp_json_encode( (array) $data['input_schema'] ) : null,
			'output_schema'       => isset( $data['output_schema'] ) ? wp_json_encode( (array) $data['output_schema'] ) : null,
			'execute_callback'    => isset( $data['execute_callback'] ) ? sanitize_text_field( $data['execute_callback'] ) : '',
			'permission_callback' => isset( $data['permission_callback'] ) ? sanitize_text_field( $data['permission_callback'] ) : '',
			'readonly'            => isset( $data['readonly'] ) ? (int) (bool) $data['readonly'] : null,
			'destructive'         => isset( $data['destructive'] ) ? (int) (bool) $data['destructive'] : null,
			'idempotent'          => isset( $data['idempotent'] ) ? (int) (bool) $data['idempotent'] : null,
			'show_in_rest'        => isset( $data['show_in_rest'] ) ? (int) (bool) $data['show_in_rest'] : null,
			'mcp_public'          => isset( $data['mcp_public'] ) ? (int) (bool) $data['mcp_public'] : null,
			'mcp_type'            => isset( $data['mcp_type'] ) ? sanitize_text_field( $data['mcp_type'] ) : null,
			'custom_meta'         => isset( $data['custom_meta'] ) ? wp_json_encode( (array) $data['custom_meta'] ) : null,
			'created_by'          => isset( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id(),
			'version'             => isset( $data['version'] ) ? sanitize_text_field( $data['version'] ) : '1.0',
			'deprecated_at'       => isset( $data['deprecated_at'] ) ? sanitize_text_field( $data['deprecated_at'] ) : null,
		);

		// Preserve created_by on updates.
		if ( is_array( $existing ) ) {
			unset( $record['created_by'] );
		}

		return array_filter( $record, static function ( $value ) {
			return null !== $value;
		} );
	}
}

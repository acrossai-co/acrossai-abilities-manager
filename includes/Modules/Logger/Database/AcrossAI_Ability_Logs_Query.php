<?php
/**
 * BerlinDB Query class for ability execution logs.
 *
 * Provides low-level CRUD operations for the acrossai_ability_logs table.
 * High-level filtering/sorting is handled by AcrossAI_Logger_Query (Phase C).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database;

use BerlinDB\Database\Query;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Provides CRUD operations for the acrossai_ability_logs table.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Logs_Query extends Query {

	/**
	 * Schema class for this query.
	 *
	 * @var string
	 */
	protected $table_schema = AcrossAI_Ability_Logs_Schema::class;

	/**
	 * Row class for query results.
	 *
	 * @var string
	 */
	protected $item_shape = AcrossAI_Ability_Logs_Row::class;

	/**
	 * Table name (without WordPress table prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'acrossai_ability_logs';

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Ability_Logs_Query|null
	 */
	protected static $_instance = null;

	/**
	 * Get the singleton instance of this query.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Ability_Logs_Query
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Insert a log entry into the table.
	 *
	 * Validates the 10-field array before insertion.
	 *
	 * @since  0.1.0
	 * @param  array $entry 10-field log entry array
	 * @return int|false Inserted row ID or false on failure
	 */
	public function insert( array $entry ) {
		// Validate required fields
		$required_fields = array(
			'ability_slug',
			'source',
			'status',
			'duration_ms',
			'created_at',
		);

		foreach ( $required_fields as $field ) {
			if ( ! isset( $entry[ $field ] ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "Logger Query: missing required field '{$field}'" );
				return false;
			}
		}

		// Validate status is valid (SEC-04: strict comparison)
		$valid_statuses = array( 'success', 'error', 'permission_denied' );
		if ( ! in_array( $entry['status'], $valid_statuses, true ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Logger Query: invalid status value' );
			return false;
		}

		// Prepare insert data
		$data = array(
			'ability_slug'  => sanitize_text_field( $entry['ability_slug'] ),
			'source'        => sanitize_key( $entry['source'] ),
			'mcp_server_id' => isset( $entry['mcp_server_id'] ) ? sanitize_text_field( $entry['mcp_server_id'] ) : null,
			'user_id'       => isset( $entry['user_id'] ) ? (int) $entry['user_id'] : null,
			'input'         => isset( $entry['input'] ) ? sanitize_textarea_field( $entry['input'] ) : null,
			'output'        => isset( $entry['output'] ) ? sanitize_textarea_field( $entry['output'] ) : null,
			'status'        => sanitize_key( $entry['status'] ),
			'duration_ms'   => (int) $entry['duration_ms'],
			'created_at'    => sanitize_text_field( $entry['created_at'] ),
		);

		// Use BerlinDB's add method to insert row
		$result = $this->add( $data );

		return $result ? $result->id : false;
	}

	/**
	 * Get logs with optional filtering.
	 *
	 * Returns all logs or subset. Does NOT perform filtering here.
	 * Filtering is handled by AcrossAI_Logger_Query (Phase C).
	 *
	 * @since  0.1.0
	 * @param  array $args Query arguments
	 * @return array Array of Row objects
	 */
	public function get_logs( array $args = array() ): array {
		$results = $this->query( $args );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get a single log entry by ID.
	 *
	 * @since  0.1.0
	 * @param  int $id Log entry ID
	 * @return AcrossAI_Ability_Logs_Row|null Row object or null
	 */
	public function get_by_id( int $id ): ?AcrossAI_Ability_Logs_Row {
		$results = $this->query(
			array(
				'id'     => $id,
				'number' => 1,
			)
		);

		if ( empty( $results ) || ! $results[0] instanceof AcrossAI_Ability_Logs_Row ) {
			return null;
		}

		return $results[0];
	}

	/**
	 * Delete log entries before a specific date.
	 *
	 * Used by cleanup job (T016) to implement log retention.
	 *
	 * @since  0.1.0
	 * @param  string $date Date cutoff in format 'YYYY-MM-DD HH:MM:SS'
	 * @return int Number of rows deleted
	 */
	public function delete_logs_before_date( string $date ): int {
		global $wpdb;

		// Validate date format (basic check)
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Logger Query: invalid date format for delete_logs_before_date' );
			return 0;
		}

		$table_name = 'acrossai_ability_logs';

		// Use prepared statement for safe deletion (no SQL injection)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$query = $wpdb->prepare(
			'DELETE FROM ' . $wpdb->prefix . '%i WHERE created_at < %s',
			$table_name,
			$date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( $query );

		return is_int( $result ) ? $result : 0;
	}

	/**
	 * Count total log entries.
	 *
	 * @since  0.1.0
	 * @return int Total count of logs
	 */
	public function count(): int {
		global $wpdb;

		$table_name = 'acrossai_ability_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . $wpdb->prefix . '%i',
			$table_name
		);

		return is_numeric( $result ) ? (int) $result : 0;
	}
}

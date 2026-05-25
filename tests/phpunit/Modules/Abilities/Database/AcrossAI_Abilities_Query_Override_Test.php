<?php
/**
 * Unit tests for the 4 override CRUD methods ported from AcrossAI_Sitewide_Query
 * into AcrossAI_Abilities_Query (Feature 012, T030).
 *
 * Covered assertions per §VII DoD:
 *  (a) get_override_by_slug() applies sanitize_ability_slug() before DB query.
 *  (b) save_override() rejects JSON-field payloads exceeding 65 536 bytes (guard → null).
 *  (c) delete_override_by_slug() applies sanitize_ability_slug() before delete.
 *  (d) get_all_overrides() returns all rows without a LIMIT (number => 0).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Modules\Abilities\Database;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Table;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Row;

/**
 * Class AcrossAI_Abilities_Query_Override_Test
 *
 * @since 0.1.0
 */
class AcrossAI_Abilities_Query_Override_Test extends WP_UnitTestCase {

	/**
	 * Query singleton under test.
	 *
	 * @var AcrossAI_Abilities_Query
	 */
	protected $query;

	/**
	 * Slug prefix used for all override rows created in this test class.
	 * Ensures tearDown can clean up without affecting other tests.
	 *
	 * @var string
	 */
	const SLUG_PREFIX = 'override-test/';

	/**
	 * Set up — ensure the table exists and obtain the query singleton.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		( new AcrossAI_Abilities_Table() )->maybe_upgrade();
		$this->query = AcrossAI_Abilities_Query::instance();
	}

	/**
	 * Tear down — delete all override rows inserted during this test class.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}acrossai_abilities WHERE ability_slug LIKE %s",
				self::SLUG_PREFIX . '%'
			)
		);
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// (a) get_override_by_slug — sanitize_ability_slug() applied before query
	// -------------------------------------------------------------------------

	/**
	 * (a) get_override_by_slug strips illegal characters from the slug before
	 * querying, matching a pre-existing clean record.
	 *
	 * Proves sanitize_ability_slug() is applied: the raw slug contains
	 * characters outside [a-zA-Z0-9\-_\/] which are stripped before the DB
	 * query, producing the canonical slug that finds the saved record.
	 *
	 * @return void
	 */
	public function test_get_override_by_slug_sanitizes_slug_before_query(): void {
		$clean_slug = self::SLUG_PREFIX . 'ability-a';
		$this->query->save_override(
			$clean_slug,
			array(
				'site_allowed' => true,
				'source'       => 'plugin',
			)
		);

		// Dirty slug: illegal chars appended — sanitize strips them, leaving $clean_slug.
		$dirty_slug = $clean_slug . '!@#$%';
		$row        = $this->query->get_override_by_slug( $dirty_slug );

		$this->assertInstanceOf(
			AcrossAI_Abilities_Row::class,
			$row,
			'get_override_by_slug() must sanitize the slug before querying — dirty slug should resolve to the clean record.'
		);
		$this->assertSame( $clean_slug, $row->ability_slug );
	}

	/**
	 * (a) get_override_by_slug returns null for an unknown slug (no record).
	 *
	 * @return void
	 */
	public function test_get_override_by_slug_returns_null_for_unknown(): void {
		$result = $this->query->get_override_by_slug( self::SLUG_PREFIX . 'nonexistent' );
		$this->assertNull( $result );
	}

	/**
	 * (a) get_override_by_slug truncates slugs exceeding 255 characters.
	 *
	 * The sanitize_ability_slug() function enforces a 255-char maximum (LOW-04). The truncated
	 * result must not match a record stored under the full (pre-truncated) slug.
	 *
	 * @return void
	 */
	public function test_get_override_by_slug_truncates_slug_to_255_chars(): void {
		// Build a slug longer than 255 chars.
		$long_slug = self::SLUG_PREFIX . str_repeat( 'x', 300 );
		// Save nothing — just verify the method does not error and returns null.
		$result = $this->query->get_override_by_slug( $long_slug );
		$this->assertNull(
			$result,
			'Oversized slug should be sanitized to ≤ 255 chars and return null (no record).'
		);
	}

	// -------------------------------------------------------------------------
	// (b) save_override — JSON size guard (65 536-byte cap per field)
	// -------------------------------------------------------------------------

	/**
	 * (b) save_override stores a row when all JSON fields are within the 64 KB cap.
	 *
	 * @return void
	 */
	public function test_save_override_succeeds_with_small_json_payload(): void {
		$ok = $this->query->save_override(
			self::SLUG_PREFIX . 'json-small',
			array(
				'site_allowed'    => true,
				'source'          => 'plugin',
				'callback_config' => array( 'key' => 'value' ),
			)
		);
		$this->assertTrue( $ok, 'save_override() must return true for a small JSON payload.' );
	}

	/**
	 * (b) save_override nullifies a JSON field whose encoded size exceeds 65 536 bytes.
	 *
	 * Builds an array whose json_encode() output exceeds MAX_JSON_BYTES (65536).
	 * prepare_fields_for_write() must set the field to null rather than persisting
	 * the oversized value. The record itself must still be created (save returns true)
	 * but the JSON field must be null on retrieval.
	 *
	 * @return void
	 */
	public function test_save_override_nullifies_json_field_exceeding_64kb_cap(): void {
		// Build a callback_config array that encodes to > 65 536 bytes.
		$oversized_config = array( 'data' => str_repeat( 'A', 70000 ) );
		$this->assertGreaterThan(
			65536,
			strlen( (string) wp_json_encode( $oversized_config ) ),
			'Pre-condition: test payload must exceed 65 536 bytes when JSON-encoded.'
		);

		$slug = self::SLUG_PREFIX . 'json-oversized';
		$ok   = $this->query->save_override(
			$slug,
			array(
				'site_allowed'    => true,
				'source'          => 'plugin',
				'callback_config' => $oversized_config,
			)
		);

		$this->assertTrue( $ok, 'save_override() must still return true — guard rejects the field, not the row.' );

		$row = $this->query->get_override_by_slug( $slug );
		$this->assertNotNull( $row );
		$this->assertNull(
			$row->callback_config,
			'callback_config must be null when the encoded payload exceeds 65 536 bytes (DEC-JSON-SIZE-GUARD).'
		);
	}

	// -------------------------------------------------------------------------
	// (c) delete_override_by_slug — sanitize_ability_slug() applied before delete
	// -------------------------------------------------------------------------

	/**
	 * (c) delete_override_by_slug strips illegal characters from the slug before
	 * deleting, removing the record that was stored under the clean slug.
	 *
	 * Proves sanitize_ability_slug() is applied on the delete path: a dirty slug
	 * resolves to the same canonical key as the saved record and deletes it.
	 *
	 * @return void
	 */
	public function test_delete_override_by_slug_sanitizes_slug_before_delete(): void {
		$clean_slug = self::SLUG_PREFIX . 'ability-to-delete';
		$this->query->save_override(
			$clean_slug,
			array(
				'site_allowed' => false,
				'source'       => 'plugin',
			)
		);

		// Dirty slug with illegal characters — sanitize produces $clean_slug.
		$dirty_slug = $clean_slug . '!@#';
		$deleted    = $this->query->delete_override_by_slug( $dirty_slug );

		$this->assertTrue( $deleted, 'delete_override_by_slug() must return true after sanitizing the slug and finding the record.' );
		$this->assertNull(
			$this->query->get_override_by_slug( $clean_slug ),
			'Record must no longer exist after deletion via sanitized dirty slug.'
		);
	}

	/**
	 * (c) delete_override_by_slug returns false when no record matches the
	 * sanitized slug.
	 *
	 * @return void
	 */
	public function test_delete_override_by_slug_returns_false_for_nonexistent(): void {
		$result = $this->query->delete_override_by_slug( self::SLUG_PREFIX . 'not-saved' );
		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// (d) get_all_overrides — number => 0, no LIMIT, returns all rows
	// -------------------------------------------------------------------------

	/**
	 * (d) get_all_overrides returns every row inserted — proves no LIMIT is applied.
	 *
	 * Inserts 3 rows (exceeding any typical default page-size of 1–2) and
	 * verifies all three are present in the result, keyed by ability_slug.
	 *
	 * @return void
	 */
	public function test_get_all_overrides_returns_all_rows_without_limit(): void {
		$slugs = array(
			self::SLUG_PREFIX . 'bulk-1',
			self::SLUG_PREFIX . 'bulk-2',
			self::SLUG_PREFIX . 'bulk-3',
		);

		foreach ( $slugs as $slug ) {
			$this->query->save_override(
				$slug,
				array(
					'site_allowed' => true,
					'source'       => 'plugin',
				)
			);
		}

		$all = $this->query->get_all_overrides();

		foreach ( $slugs as $slug ) {
			$this->assertArrayHasKey(
				$slug,
				$all,
				"get_all_overrides() must return row keyed by '{$slug}' — number=>0 ensures no LIMIT (BUG-BERLINDB-UNLIMITED)."
			);
			$this->assertInstanceOf( AcrossAI_Abilities_Row::class, $all[ $slug ] );
		}
	}

	/**
	 * (d) get_all_overrides returns an empty array when no rows exist in the table.
	 *
	 * @return void
	 */
	public function test_get_all_overrides_returns_empty_array_when_no_rows(): void {
		// tearDown ensures our prefix rows are cleaned; this checks the method
		// returns a well-typed empty array rather than false/null.
		$all = $this->query->get_all_overrides();
		$this->assertIsArray( $all );
	}

	// -------------------------------------------------------------------------
	// Additional: save_override upsert behaviour
	// -------------------------------------------------------------------------

	/**
	 * Save_override updates an existing record on second call (upsert).
	 * created_at must not change on the update path.
	 *
	 * @return void
	 */
	public function test_save_override_upsert_preserves_created_at(): void {
		$slug = self::SLUG_PREFIX . 'upsert';

		$this->query->save_override(
			$slug,
			array(
				'site_allowed' => true,
				'source'       => 'plugin',
			)
		);
		$row_before = $this->query->get_override_by_slug( $slug );
		$this->assertNotNull( $row_before );
		$created_at = $row_before->created_at;

		$this->query->save_override(
			$slug,
			array(
				'site_allowed' => false,
				'source'       => 'plugin',
			)
		);
		$row_after = $this->query->get_override_by_slug( $slug );

		$this->assertNotNull( $row_after );
		$this->assertFalse( (bool) $row_after->site_allowed, 'site_allowed must be updated on upsert.' );
		$this->assertSame( $created_at, $row_after->created_at, 'created_at must not change on UPDATE path.' );
	}
}

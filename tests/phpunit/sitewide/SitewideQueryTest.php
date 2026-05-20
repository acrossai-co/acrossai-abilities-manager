<?php
/**
 * Tests for AcrossAI_Sitewide_Query.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Sitewide;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table;

/**
 * Class SitewideQueryTest
 *
 * @since 0.1.0
 */
class SitewideQueryTest extends WP_UnitTestCase {

	/**
	 * Query object.
	 *
	 * @var AcrossAI_Sitewide_Query
	 */
	protected $query;

	/**
	 * Set up — create table and query object.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		( new AcrossAI_Sitewide_Table() )->maybe_upgrade();
		$this->query = new AcrossAI_Sitewide_Query();
	}

	/**
	 * get_override_by_slug returns null for unknown slug.
	 *
	 * @return void
	 */
	public function test_get_override_by_slug_returns_null_for_unknown() {
		$result = $this->query->get_override_by_slug( 'nonexistent-slug' );
		$this->assertNull( $result );
	}

	/**
	 * save_override creates a new record.
	 *
	 * @return void
	 */
	public function test_save_override_creates_new_record() {
		$ok = $this->query->save_override( 'test-ability', [
			'site_allowed' => false,
			'source'       => 'plugin',
			'provider'     => 'test',
		] );

		$this->assertTrue( $ok );

		$row = $this->query->get_override_by_slug( 'test-ability' );
		$this->assertNotNull( $row );
		$this->assertFalse( $row->site_allowed );
	}

	/**
	 * save_override updates an existing record (upsert).
	 *
	 * @return void
	 */
	public function test_save_override_updates_existing_record() {
		$this->query->save_override( 'test-upsert', [
			'site_allowed' => true,
			'source'       => 'plugin',
		] );

		$row_before = $this->query->get_override_by_slug( 'test-upsert' );
		$created_at = $row_before->created_at;

		$this->query->save_override( 'test-upsert', [
			'site_allowed' => false,
			'source'       => 'plugin',
		] );

		$row_after = $this->query->get_override_by_slug( 'test-upsert' );
		$this->assertFalse( $row_after->site_allowed );
		// created_at must not change on update.
		$this->assertSame( $created_at, $row_after->created_at );
	}

	/**
	 * delete_override_by_slug deletes existing record and returns true.
	 *
	 * @return void
	 */
	public function test_delete_override_by_slug_returns_true() {
		$this->query->save_override( 'to-delete', [ 'site_allowed' => true, 'source' => 'plugin' ] );

		$deleted = $this->query->delete_override_by_slug( 'to-delete' );
		$this->assertTrue( $deleted );

		$row = $this->query->get_override_by_slug( 'to-delete' );
		$this->assertNull( $row );
	}

	/**
	 * delete_override_by_slug returns false when no record.
	 *
	 * @return void
	 */
	public function test_delete_override_by_slug_returns_false_for_nonexistent() {
		$result = $this->query->delete_override_by_slug( 'no-such-slug' );
		$this->assertFalse( $result );
	}
}

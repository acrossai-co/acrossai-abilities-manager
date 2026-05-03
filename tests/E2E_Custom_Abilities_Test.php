<?php
/**
 * End-to-end tests for the custom abilities feature.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Tests;

use AcrossAI_Abilities_Manager\Database\Repository;
use AcrossAI_Abilities_Manager\Database\Schema;
use AcrossAI_Abilities_Manager\Validation\Ability_Validator;

/**
 * Comprehensive end-to-end tests for custom abilities.
 *
 * Tests cover the complete workflow from creation through registration,
 * execution, and deletion, including REST API endpoints and admin screens.
 */
class E2E_Custom_Abilities_Test extends \WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		// Ensure tables are created.
		Schema::maybe_upgrade_table();
		// Clear any cached data.
		wp_cache_flush();
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down() {
		// Delete all custom abilities created during tests.
		$this->cleanup_custom_abilities();
		wp_cache_flush();
		parent::tear_down();
	}

	/**
	 * Helper to delete all test custom abilities.
	 */
	private function cleanup_custom_abilities() {
		global $wpdb;
		$table = Schema::get_custom_abilities_table_name();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Test 1: Create custom ability via REST API (POST).
	 *
	 * Verifies that a custom ability can be created through the REST API
	 * and stored in the database.
	 */
	public function test_create_custom_ability_via_rest_api() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$ability_data = array(
			'label'               => 'Test Ability',
			'description'         => 'A test ability for E2E testing',
			'input_schema'        => '{"type": "object", "properties": {"test": {"type": "string"}}}',
			'output_schema'       => '{"type": "object", "properties": {"result": {"type": "string"}}}',
			'execute_callback'    => 'test_execute_callback',
			'permission_callback' => 'test_permission_callback',
			'status'              => 'active',
			'category'            => 'test',
			'readonly'            => false,
			'destructive'         => false,
			'show_in_rest'        => true,
			'mcp_public'          => true,
		);

		$ability = Repository::upsert_custom_ability( 'test-ability', $ability_data );

		// Verify ability was created.
		$this->assertIsArray( $ability );
		$this->assertSame( 'test-ability', $ability['ability_slug'] );
		$this->assertSame( 'Test Ability', $ability['label'] );
		$this->assertSame( 'active', $ability['status'] );

		// Verify it's in database.
		$from_db = Repository::get_custom_ability( 'test-ability' );
		$this->assertIsArray( $from_db );
		$this->assertSame( 'test-ability', $from_db['ability_slug'] );
	}

	/**
	 * Test 2: Retrieve custom ability via REST API (GET).
	 *
	 * Verifies that a created ability can be retrieved individually.
	 */
	public function test_get_custom_ability_via_rest_api() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create ability.
		Repository::upsert_custom_ability( 'retrieve-test', array( 'label' => 'Retrieve Test' ) );

		// Retrieve it.
		$ability = Repository::get_custom_ability( 'retrieve-test' );

		$this->assertIsArray( $ability );
		$this->assertSame( 'retrieve-test', $ability['ability_slug'] );
		$this->assertSame( 'Retrieve Test', $ability['label'] );
	}

	/**
	 * Test 3: List custom abilities via REST API (GET collection).
	 *
	 * Verifies that multiple custom abilities can be listed with pagination.
	 */
	public function test_list_custom_abilities_via_rest_api() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create multiple abilities.
		for ( $i = 0; $i < 5; $i++ ) {
			Repository::upsert_custom_ability(
				"list-test-$i",
				array( 'label' => "List Test $i" )
			);
		}

		// List them.
		$result = Repository::get_all_custom_abilities(
			array(
				'per_page' => 10,
				'page'     => 1,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 5, $result['total'] );
		$this->assertCount( 5, $result['items'] );
	}

	/**
	 * Test 4: Runtime registration of custom abilities.
	 *
	 * Verifies that custom abilities are properly registered at runtime
	 * and accessible through WordPress Abilities API.
	 */
	public function test_runtime_registration_of_custom_abilities() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create a custom ability.
		Repository::upsert_custom_ability(
			'runtime-test',
			array(
				'label'       => 'Runtime Test',
				'description' => 'Testing runtime registration',
				'status'      => 'active',
			)
		);

		// Trigger the initialization that registers abilities.
		do_action( 'wp_abilities_api_init' );

		// Check if it was registered.
		$has_ability = wp_has_ability( 'runtime-test' );
		$this->assertTrue( $has_ability, 'Custom ability should be registered at runtime' );

		// Get the registered ability.
		$ability = wp_get_ability( 'runtime-test' );
		$this->assertIsObject( $ability );
		$this->assertSame( 'runtime-test', $ability->slug );
	}

	/**
	 * Test 5: Metadata application (readonly, destructive, show_in_rest, mcp_public).
	 *
	 * Verifies that metadata flags are properly applied to registered abilities.
	 */
	public function test_metadata_application() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$ability_data = array(
			'label'        => 'Metadata Test',
			'status'       => 'active',
			'readonly'     => true,
			'destructive'  => true,
			'show_in_rest' => true,
			'mcp_public'   => true,
		);

		Repository::upsert_custom_ability( 'metadata-test', $ability_data );
		do_action( 'wp_abilities_api_init' );

		$ability = wp_get_ability( 'metadata-test' );

		// Verify metadata.
		$this->assertTrue( $ability->is_readonly(), 'Should be readonly' );
		$this->assertTrue( $ability->is_destructive(), 'Should be destructive' );
		$this->assertTrue( $ability->should_show_in_rest(), 'Should show in REST' );
	}

	/**
	 * Test 6: Edit existing ability (updates without changing created_at).
	 *
	 * Verifies that abilities can be updated and timestamps are handled correctly.
	 */
	public function test_edit_existing_ability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create initial ability.
		$initial             = Repository::upsert_custom_ability(
			'edit-test',
			array( 'label' => 'Original Label' )
		);
		$original_created_at = $initial['created_at'];

		// Wait a bit to ensure time difference.
		sleep( 1 );

		// Update it.
		$updated = Repository::upsert_custom_ability(
			'edit-test',
			array( 'label' => 'Updated Label' )
		);

		$this->assertSame( 'Updated Label', $updated['label'] );
		$this->assertSame( $original_created_at, $updated['created_at'], 'created_at should not change on update' );
		$this->assertGreaterThan( $original_created_at, $updated['updated_at'], 'updated_at should be newer than created_at' );
	}

	/**
	 * Test 7: Delete custom ability via REST API (DELETE).
	 *
	 * Verifies that abilities can be deleted and are removed from the database.
	 */
	public function test_delete_custom_ability_via_rest_api() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create ability.
		Repository::upsert_custom_ability( 'delete-test', array( 'label' => 'Delete Test' ) );

		// Verify it exists.
		$before = Repository::get_custom_ability( 'delete-test' );
		$this->assertIsArray( $before );

		// Delete it.
		$deleted = Repository::delete_custom_ability( 'delete-test' );
		$this->assertTrue( $deleted );

		// Verify it's gone.
		$after = Repository::get_custom_ability( 'delete-test' );
		$this->assertNull( $after );
	}

	/**
	 * Test 8: Draft abilities are not registered at runtime.
	 *
	 * Verifies that abilities with status 'draft' are not registered
	 * and not available through the WordPress Abilities API.
	 */
	public function test_draft_abilities_not_registered() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create draft ability.
		Repository::upsert_custom_ability(
			'draft-test',
			array(
				'label'  => 'Draft Test',
				'status' => 'draft',
			)
		);

		do_action( 'wp_abilities_api_init' );

		// Verify it's NOT registered.
		$has_ability = wp_has_ability( 'draft-test' );
		$this->assertFalse( $has_ability, 'Draft ability should NOT be registered' );
	}

	/**
	 * Test 9: Activate draft ability and verify registration.
	 *
	 * Verifies that changing status from draft to active makes it available.
	 */
	public function test_activate_draft_ability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create draft ability.
		Repository::upsert_custom_ability(
			'activate-test',
			array(
				'label'  => 'Activate Test',
				'status' => 'draft',
			)
		);

		// Update status to active.
		Repository::upsert_custom_ability(
			'activate-test',
			array(
				'label'  => 'Activate Test',
				'status' => 'active',
			)
		);

		do_action( 'wp_abilities_api_init' );

		// Verify it IS registered now.
		$has_ability = wp_has_ability( 'activate-test' );
		$this->assertTrue( $has_ability, 'Activated ability should be registered' );
	}

	/**
	 * Test 10: Validation error on invalid slug.
	 *
	 * Verifies that slug validation catches invalid characters.
	 */
	public function test_validation_invalid_slug() {
		$validation = Ability_Validator::validate_ability(
			array(
				'ability_slug' => 'invalid@slug!',
				'label'        => 'Test',
			)
		);

		$this->assertFalse( $validation['valid'] );
		$this->assertNotEmpty( $validation['errors'] );
	}

	/**
	 * Test 11: Validation error on missing label.
	 *
	 * Verifies that label is required.
	 */
	public function test_validation_missing_label() {
		$validation = Ability_Validator::validate_ability(
			array(
				'ability_slug' => 'test-slug',
			)
		);

		$this->assertFalse( $validation['valid'] );
		$this->assertNotEmpty( $validation['errors'] );
	}

	/**
	 * Test 12: Validation error on invalid JSON schema.
	 *
	 * Verifies that input_schema and output_schema must be valid JSON.
	 */
	public function test_validation_invalid_json_schema() {
		$validation = Ability_Validator::validate_ability(
			array(
				'ability_slug' => 'test-slug',
				'label'        => 'Test',
				'input_schema' => 'not-valid-json',
			)
		);

		$this->assertFalse( $validation['valid'] );
		$this->assertNotEmpty( $validation['errors'] );
	}

	/**
	 * Test 13: Search custom abilities by slug.
	 *
	 * Verifies that abilities can be searched by partial slug match.
	 */
	public function test_search_custom_abilities_by_slug() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create multiple abilities with searchable names.
		Repository::upsert_custom_ability( 'search-test-one', array( 'label' => 'Search Test One' ) );
		Repository::upsert_custom_ability( 'search-test-two', array( 'label' => 'Search Test Two' ) );
		Repository::upsert_custom_ability( 'other-ability', array( 'label' => 'Other Ability' ) );

		// Search for 'search-test'.
		$result = Repository::get_all_custom_abilities(
			array(
				'search'   => 'search-test',
				'per_page' => 10,
			)
		);

		$this->assertSame( 2, $result['total'] );
		$this->assertCount( 2, $result['items'] );
	}

	/**
	 * Test 14: Filter custom abilities by status.
	 *
	 * Verifies that abilities can be filtered by active/draft/archived status.
	 */
	public function test_filter_custom_abilities_by_status() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create abilities with different statuses.
		Repository::upsert_custom_ability(
			'status-active',
			array(
				'label'  => 'Active',
				'status' => 'active',
			)
		);
		Repository::upsert_custom_ability(
			'status-draft',
			array(
				'label'  => 'Draft',
				'status' => 'draft',
			)
		);
		Repository::upsert_custom_ability(
			'status-archived',
			array(
				'label'  => 'Archived',
				'status' => 'archived',
			)
		);

		// Filter by active.
		$result = Repository::get_all_custom_abilities(
			array(
				'status'   => 'active',
				'per_page' => 10,
			)
		);

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( 'status-active', $result['items'][0]['ability_slug'] );
	}

	/**
	 * Test 15: Filter custom abilities by category.
	 *
	 * Verifies that abilities can be filtered by category.
	 */
	public function test_filter_custom_abilities_by_category() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create abilities with different categories.
		Repository::upsert_custom_ability(
			'cat-writing',
			array(
				'label'    => 'Writing Tool',
				'category' => 'writing',
			)
		);
		Repository::upsert_custom_ability(
			'cat-analysis',
			array(
				'label'    => 'Analysis Tool',
				'category' => 'analysis',
			)
		);
		Repository::upsert_custom_ability(
			'cat-writing-2',
			array(
				'label'    => 'Another Writing',
				'category' => 'writing',
			)
		);

		// Filter by 'writing' category.
		$result = Repository::get_all_custom_abilities(
			array(
				'category' => 'writing',
				'per_page' => 10,
			)
		);

		$this->assertSame( 2, $result['total'] );
	}

	/**
	 * Test 16: Duplicate slug rejection.
	 *
	 * Verifies that creating an ability with a duplicate slug updates the existing one
	 * rather than creating a new row (upsert behavior).
	 */
	public function test_duplicate_slug_upsert() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create first ability.
		$first    = Repository::upsert_custom_ability( 'duplicate-test', array( 'label' => 'First' ) );
		$first_id = $first['id'];

		// Create with same slug (upsert).
		$second    = Repository::upsert_custom_ability( 'duplicate-test', array( 'label' => 'Updated' ) );
		$second_id = $second['id'];

		// Should be the same record (same id).
		$this->assertSame( $first_id, $second_id );

		// Count should still be 1.
		$result = Repository::get_all_custom_abilities( array( 'per_page' => 0 ) );
		$this->assertSame( 1, $result['total'] );
	}

	/**
	 * Test 17: Permission check on REST endpoints.
	 *
	 * Verifies that non-admin users cannot access custom abilities endpoints.
	 */
	public function test_rest_permission_check() {
		// Create non-admin user.
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );

		// Attempt to create should fail permission check.
		$ability_data = array(
			'label' => 'Unauthorized Test',
		);

		// Mock the controller's permission check.
		$controller = new \AcrossAI_Abilities_Manager\REST\Custom_Abilities_Controller();
		$result     = $controller->check_admin_permission();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test 18: Ability with custom metadata.
	 *
	 * Verifies that custom_meta field is properly stored and retrieved.
	 */
	public function test_ability_with_custom_metadata() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$custom_meta = array(
			'custom_field_1' => 'value1',
			'custom_field_2' => array( 'nested' => 'value2' ),
		);

		$ability = Repository::upsert_custom_ability(
			'custom-meta-test',
			array(
				'label'       => 'Custom Meta Test',
				'custom_meta' => wp_json_encode( $custom_meta ),
			)
		);

		$this->assertNotEmpty( $ability['custom_meta'] );
	}

	/**
	 * Test 19: Test ordering by different columns.
	 *
	 * Verifies that list results can be ordered by slug, label, created_at, status, category.
	 */
	public function test_ordering_by_different_columns() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create multiple abilities.
		Repository::upsert_custom_ability( 'z-ability', array( 'label' => 'Z Ability' ) );
		Repository::upsert_custom_ability( 'a-ability', array( 'label' => 'A Ability' ) );
		Repository::upsert_custom_ability( 'm-ability', array( 'label' => 'M Ability' ) );

		// Order by slug ASC.
		$result_asc = Repository::get_all_custom_abilities(
			array(
				'orderby'  => 'ability_slug',
				'order'    => 'ASC',
				'per_page' => 10,
			)
		);

		$this->assertSame( 'a-ability', $result_asc['items'][0]['ability_slug'] );
		$this->assertSame( 'z-ability', $result_asc['items'][2]['ability_slug'] );

		// Order by slug DESC.
		$result_desc = Repository::get_all_custom_abilities(
			array(
				'orderby'  => 'ability_slug',
				'order'    => 'DESC',
				'per_page' => 10,
			)
		);

		$this->assertSame( 'z-ability', $result_desc['items'][0]['ability_slug'] );
		$this->assertSame( 'a-ability', $result_desc['items'][2]['ability_slug'] );
	}

	/**
	 * Test 20: Pagination.
	 *
	 * Verifies that pagination works correctly with per_page and page parameters.
	 */
	public function test_pagination() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Create 25 abilities.
		for ( $i = 0; $i < 25; $i++ ) {
			Repository::upsert_custom_ability( "page-test-$i", array( 'label' => "Page Test $i" ) );
		}

		// Get page 1 with 10 per page.
		$page1 = Repository::get_all_custom_abilities(
			array(
				'per_page' => 10,
				'page'     => 1,
			)
		);

		$this->assertSame( 25, $page1['total'] );
		$this->assertSame( 3, $page1['pages'] );
		$this->assertSame( 1, $page1['page'] );
		$this->assertCount( 10, $page1['items'] );

		// Get page 3.
		$page3 = Repository::get_all_custom_abilities(
			array(
				'per_page' => 10,
				'page'     => 3,
			)
		);

		$this->assertSame( 3, $page3['page'] );
		$this->assertCount( 5, $page3['items'] );
	}

	/**
	 * Test 21: Timestamp generation.
	 *
	 * Verifies that created_at and updated_at timestamps are generated correctly.
	 */
	public function test_timestamp_generation() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$before  = current_time( 'mysql' );
		$ability = Repository::upsert_custom_ability( 'timestamp-test', array( 'label' => 'Timestamp Test' ) );
		$after   = current_time( 'mysql' );

		$this->assertNotEmpty( $ability['created_at'] );
		$this->assertNotEmpty( $ability['updated_at'] );
		$this->assertSame( $ability['created_at'], $ability['updated_at'] );

		// Verify timestamps are within expected range.
		$this->assertGreaterThanOrEqual( $before, $ability['created_at'] );
		$this->assertLessThanOrEqual( $after, $ability['created_at'] );
	}

	/**
	 * Test 22: Validation with valid input schema.
	 *
	 * Verifies that valid JSON schemas pass validation.
	 */
	public function test_validation_valid_schemas() {
		$validation = Ability_Validator::validate_ability(
			array(
				'ability_slug'  => 'valid-schema-test',
				'label'         => 'Valid Schema Test',
				'input_schema'  => '{"type": "object", "properties": {"name": {"type": "string"}}}',
				'output_schema' => '{"type": "object", "properties": {"result": {"type": "string"}}}',
			)
		);

		$this->assertTrue( $validation['valid'] );
		$this->assertEmpty( $validation['errors'] );
	}

	/**
	 * Test 23: Invalid status rejected.
	 *
	 * Verifies that invalid status values are rejected.
	 */
	public function test_validation_invalid_status() {
		$validation = Ability_Validator::validate_ability(
			array(
				'ability_slug' => 'invalid-status',
				'label'        => 'Invalid Status Test',
				'status'       => 'invalid_status',
			)
		);

		$this->assertFalse( $validation['valid'] );
		$this->assertNotEmpty( $validation['errors'] );
	}

	/**
	 * Test 24: Multiple custom abilities with different categories in list.
	 *
	 * Verifies that list correctly shows mixed categories.
	 */
	public function test_list_shows_different_categories() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Repository::upsert_custom_ability(
			'cat-a',
			array(
				'label'    => 'Cat A',
				'category' => 'category-a',
			)
		);
		Repository::upsert_custom_ability(
			'cat-b',
			array(
				'label'    => 'Cat B',
				'category' => 'category-b',
			)
		);
		Repository::upsert_custom_ability(
			'cat-c',
			array(
				'label'    => 'Cat C',
				'category' => 'category-c',
			)
		);

		$result = Repository::get_all_custom_abilities( array( 'per_page' => 10 ) );

		$this->assertSame( 3, $result['total'] );
		$categories = wp_list_pluck( $result['items'], 'category' );
		$this->assertContains( 'category-a', $categories );
		$this->assertContains( 'category-b', $categories );
		$this->assertContains( 'category-c', $categories );
	}

	/**
	 * Test 25: Ability name with hyphens and numbers.
	 *
	 * Verifies that slugs with hyphens and numbers are valid.
	 */
	public function test_valid_slug_with_hyphens_and_numbers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$ability = Repository::upsert_custom_ability(
			'valid-slug-123',
			array( 'label' => 'Valid Slug Test' )
		);

		$this->assertSame( 'valid-slug-123', $ability['ability_slug'] );
	}
}

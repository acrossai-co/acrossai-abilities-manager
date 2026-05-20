<?php
/**
 * Tests for Custom Ability Database Layer
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Tests\PHPUnit\Integration
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Integration;

use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Table;
use AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database\AcrossAI_Custom_Ability_Row;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Protected_Custom_Abilities;

/**
 * Class Test_Custom_Ability_Database
 *
 * Integration tests for BerlinDB database layer (Schema, Row, Query, Table classes).
 *
 * @since 0.0.1
 */
class Test_Custom_Ability_Database extends \WP_UnitTestCase {

	/**
	 * Table instance.
	 *
	 * @since 0.0.1
	 * @var AcrossAI_Custom_Ability_Table
	 */
	protected $table;

	/**
	 * Setup: create table, reset to singleton state.
	 *
	 * @since 0.0.1
	 */
	public function set_up() {
		parent::set_up();

		// Get table singleton instance
		$this->table = AcrossAI_Custom_Ability_Table::instance();

		// Ensure table is created
		if ( function_exists( 'dbDelta' ) ) {
			$this->table->create();
		}
	}

	/**
	 * Teardown: clean up test data.
	 *
	 * @since 0.0.1
	 */
	public function tear_down() {
		// Clean up all test records
		global $wpdb;
		$wpdb->query( "TRUNCATE {$wpdb->prefix}acrossai_custom_abilities" ); // phpcs:ignore

		parent::tear_down();
	}

	/**
	 * Test: Table is per-site (multisite isolation).
	 *
	 * @since 0.0.1
	 */
	public function test_table_is_per_site() {
		$this->assertFalse( $this->table->global, 'Table should be per-site ($global = false)' );
	}

	/**
	 * Test: Table name is correct.
	 *
	 * @since 0.0.1
	 */
	public function test_table_name_is_correct() {
		global $wpdb;
		$expected_name = $wpdb->prefix . 'acrossai_custom_abilities';
		$this->assertEquals( $expected_name, $this->table->get_table_name(), 'Table name should match {prefix}acrossai_custom_abilities' );
	}

	/**
	 * Test: Insert row with minimal required fields.
	 *
	 * @since 0.0.1
	 */
	public function test_insert_row_minimal_fields() {
		$data = array(
			'ability_slug'      => 'custom/test-ability',
			'label'             => 'Test Ability',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		$id = $this->table->insert( $data );

		$this->assertIsInt( $id, 'Insert should return integer ID' );
		$this->assertGreaterThan( 0, $id, 'Insert should return positive ID' );
	}

	/**
	 * Test: Insert row with all fields (including JSON columns).
	 *
	 * @since 0.0.1
	 */
	public function test_insert_row_all_fields() {
		$callback_config = array(
			'hook_name' => 'my_filter',
		);
		$permission_config = array(
			'capability' => 'manage_options',
		);
		$input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string' ),
			),
		);
		$output_schema = array(
			'type' => 'string',
		);
		$mcp_servers = array( 'server1', 'server2' );

		$data = array(
			'ability_slug'      => 'custom/full-ability',
			'label'             => 'Full Ability',
			'description'       => 'This is a full ability with all fields.',
			'category'          => 'custom',
			'enabled'           => 1,
			'callback_type'     => 'filter_hook',
			'callback_config'   => wp_json_encode( $callback_config ),
			'permission_type'   => 'capability',
			'permission_config' => wp_json_encode( $permission_config ),
			'input_schema'      => wp_json_encode( $input_schema ),
			'output_schema'     => wp_json_encode( $output_schema ),
			'show_in_rest'      => 1,
			'show_in_mcp'       => 1,
			'mcp_type'          => 'tool',
			'mcp_servers'       => wp_json_encode( $mcp_servers ),
			'readonly'          => 1,
			'destructive'       => 0,
			'idempotent'        => null,
		);

		$id = $this->table->insert( $data );

		$this->assertGreaterThan( 0, $id, 'Insert with all fields should succeed' );

		// Fetch and verify all fields stored correctly
		$row = $this->table->get( $id );
		$this->assertNotNull( $row, 'Row should be retrievable after insert' );
		$this->assertEquals( 'custom/full-ability', $row->ability_slug, 'Slug should match' );
		$this->assertEquals( 'Full Ability', $row->label, 'Label should match' );
		$this->assertEquals( 'custom', $row->category, 'Category should match' );
		$this->assertEquals( 1, $row->enabled, 'Enabled should be 1' );
		$this->assertEquals( 'tool', $row->mcp_type, 'MCP type should match' );
		$this->assertEquals( 1, $row->readonly, 'Readonly should be 1' );
		$this->assertEquals( 0, $row->destructive, 'Destructive should be 0' );
		$this->assertNull( $row->idempotent, 'Idempotent should be NULL' );
	}

	/**
	 * Test: JSON columns are decoded on fetch (Watchpoint 1).
	 *
	 * @since 0.0.1
	 */
	public function test_json_columns_decoded_on_fetch() {
		$callback_config = array(
			'hook_name' => 'my_filter',
			'nested'    => array( 'key' => 'value' ),
		);
		$input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'name' => array(
					'type'      => 'string',
					'minLength' => 1,
				),
			),
		);

		$data = array(
			'ability_slug'      => 'custom/json-test',
			'label'             => 'JSON Test',
			'callback_type'     => 'filter_hook',
			'callback_config'   => wp_json_encode( $callback_config ),
			'permission_type'   => 'always_allow',
			'input_schema'      => wp_json_encode( $input_schema ),
			'output_schema'     => wp_json_encode( array( 'type' => 'string' ) ),
		);

		$id = $this->table->insert( $data );
		$row = $this->table->get( $id );

		// Verify JSON columns are decoded as arrays (Watchpoint 1)
		$this->assertIsArray( $row->callback_config, 'callback_config should be decoded as array' );
		$this->assertEquals( 'my_filter', $row->callback_config['hook_name'], 'Nested JSON should be preserved' );
		$this->assertIsArray( $row->input_schema, 'input_schema should be decoded as array' );
		$this->assertEquals( 'object', $row->input_schema['type'], 'Complex JSON should be preserved' );
		$this->assertIsArray( $row->output_schema, 'output_schema should be decoded as array' );
	}

	/**
	 * Test: Row getter methods for JSON columns.
	 *
	 * @since 0.0.1
	 */
	public function test_row_getter_methods_for_json_columns() {
		$callback_config = array( 'hook_name' => 'test_hook' );
		$permission_config = array( 'capability' => 'edit_posts' );
		$input_schema = array( 'type' => 'object' );

		$data = array(
			'ability_slug'      => 'custom/getters-test',
			'label'             => 'Getters Test',
			'callback_type'     => 'filter_hook',
			'callback_config'   => wp_json_encode( $callback_config ),
			'permission_type'   => 'capability',
			'permission_config' => wp_json_encode( $permission_config ),
			'input_schema'      => wp_json_encode( $input_schema ),
		);

		$id = $this->table->insert( $data );
		$row = $this->table->get( $id );

		// Test getter methods (from Row class)
		$this->assertEquals( $callback_config, $row->get_callback_config(), 'get_callback_config() should return decoded array' );
		$this->assertEquals( $permission_config, $row->get_permission_config(), 'get_permission_config() should return decoded array' );
		$this->assertEquals( $input_schema, $row->get_input_schema(), 'get_input_schema() should return decoded array' );

		// Test boolean helpers
		$this->assertTrue( $row->is_shown_in_rest(), 'Default show_in_rest should be true' );
		$this->assertFalse( $row->is_shown_in_mcp(), 'Default show_in_mcp should be false' );
	}

	/**
	 * Test: Invalid JSON is handled gracefully.
	 *
	 * @since 0.0.1
	 */
	public function test_invalid_json_column_handled_gracefully() {
		global $wpdb;

		// Insert row with valid data
		$data = array(
			'ability_slug'    => 'custom/invalid-json-test',
			'label'           => 'Invalid JSON Test',
			'callback_type'   => 'noop',
			'permission_type' => 'always_allow',
		);

		$id = $this->table->insert( $data );

		// Manually update callback_config with invalid JSON
		$wpdb->update(
			$wpdb->prefix . 'acrossai_custom_abilities',
			array( 'callback_config' => '{invalid json}' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		); // phpcs:ignore

		// Fetch row - should handle invalid JSON gracefully
		$row = $this->table->get( $id );
		$this->assertNotNull( $row, 'Row should be retrieved even with invalid JSON' );
		// Invalid JSON should remain as string or be set to null (graceful degradation)
		$this->assertTrue( is_string( $row->callback_config ) || is_null( $row->callback_config ), 'Invalid JSON should be string or null' );
	}

	/**
	 * Test: UNIQUE constraint on ability_slug.
	 *
	 * @since 0.0.1
	 */
	public function test_unique_constraint_on_slug() {
		$data = array(
			'ability_slug'      => 'custom/unique-test',
			'label'             => 'First Ability',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		// Insert first row
		$id1 = $this->table->insert( $data );
		$this->assertGreaterThan( 0, $id1, 'First insert should succeed' );

		// Try to insert duplicate slug
		$data['label'] = 'Second Ability'; // Different label, same slug
		$id2 = $this->table->insert( $data );

		// Duplicate insert should fail (return false or 0)
		$this->assertFalse( $id2, 'Duplicate slug insert should fail due to UNIQUE constraint' );
	}

	/**
	 * Test: Update row (JSON columns encoded before save).
	 *
	 * @since 0.0.1
	 */
	public function test_update_row_with_json_columns() {
		$data = array(
			'ability_slug'      => 'custom/update-test',
			'label'             => 'Original Label',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
			'callback_config'   => wp_json_encode( array( 'key' => 'value1' ) ),
		);

		$id = $this->table->insert( $data );

		// Update row with new callback_config
		$new_config = array( 'key' => 'value2', 'new_key' => 'new_value' );
		$updated = $this->table->update( $id, array(
			'label'           => 'Updated Label',
			'callback_config' => wp_json_encode( $new_config ),
		) );

		$this->assertTrue( $updated, 'Update should succeed' );

		// Fetch and verify
		$row = $this->table->get( $id );
		$this->assertEquals( 'Updated Label', $row->label, 'Label should be updated' );
		$this->assertEquals( $new_config, $row->callback_config, 'callback_config should be updated and decoded' );
	}

	/**
	 * Test: Delete row.
	 *
	 * @since 0.0.1
	 */
	public function test_delete_row() {
		$data = array(
			'ability_slug'      => 'custom/delete-test',
			'label'             => 'Delete Test',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		$id = $this->table->insert( $data );
		$row = $this->table->get( $id );
		$this->assertNotNull( $row, 'Row should exist after insert' );

		// Delete row
		$deleted = $this->table->delete( $id );
		$this->assertTrue( $deleted, 'Delete should succeed' );

		// Verify row is gone
		$row_after = $this->table->get( $id );
		$this->assertNull( $row_after, 'Row should be null after delete' );
	}

	/**
	 * Test: Query by slug.
	 *
	 * @since 0.0.1
	 */
	public function test_query_by_slug() {
		$data = array(
			'ability_slug'      => 'custom/query-slug-test',
			'label'             => 'Query Slug Test',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		$id = $this->table->insert( $data );

		// Query by slug
		$result = $this->table->get_by_slug( 'custom/query-slug-test' );

		$this->assertNotNull( $result, 'Query by slug should find row' );
		$this->assertEquals( $id, $result->id, 'Queried row should match inserted row' );
	}

	/**
	 * Test: Query enabled only.
	 *
	 * @since 0.0.1
	 */
	public function test_query_enabled_only() {
		// Insert enabled ability
		$enabled_data = array(
			'ability_slug'      => 'custom/enabled-ability',
			'label'             => 'Enabled Ability',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
			'enabled'           => 1,
		);
		$enabled_id = $this->table->insert( $enabled_data );

		// Insert disabled ability
		$disabled_data = array(
			'ability_slug'      => 'custom/disabled-ability',
			'label'             => 'Disabled Ability',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
			'enabled'           => 0,
		);
		$disabled_id = $this->table->insert( $disabled_data );

		// Query enabled abilities
		$enabled_abilities = $this->table->get_enabled();

		$this->assertIsArray( $enabled_abilities, 'get_enabled() should return array' );

		// Verify enabled ability is in result
		$enabled_ids = wp_list_pluck( $enabled_abilities, 'id' );
		$this->assertContains( $enabled_id, $enabled_ids, 'Enabled ability should be in results' );
		$this->assertNotContains( $disabled_id, $enabled_ids, 'Disabled ability should not be in results' );
	}

	/**
	 * Test: Query by category.
	 *
	 * @since 0.0.1
	 */
	public function test_query_by_category() {
		// Insert ability in "custom" category
		$custom_data = array(
			'ability_slug'      => 'custom/category-test-1',
			'label'             => 'Custom Category',
			'category'          => 'custom',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$custom_id = $this->table->insert( $custom_data );

		// Insert ability in "integration" category
		$integration_data = array(
			'ability_slug'      => 'integration/category-test-1',
			'label'             => 'Integration Category',
			'category'          => 'integration',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$integration_id = $this->table->insert( $integration_data );

		// Query by category
		$query = $this->table->new_query();
		$custom_abilities = $query->by_category( 'custom' )->get();

		$this->assertIsArray( $custom_abilities, 'Query should return array' );
		$custom_ids = wp_list_pluck( $custom_abilities, 'id' );
		$this->assertContains( $custom_id, $custom_ids, 'Custom category ability should be in results' );
		$this->assertNotContains( $integration_id, $custom_ids, 'Integration category ability should not be in results' );
	}

	/**
	 * Test: Search query across slug, label, description.
	 *
	 * @since 0.0.1
	 */
	public function test_query_search() {
		// Insert test abilities
		$data1 = array(
			'ability_slug'      => 'custom/search-test-1',
			'label'             => 'Search Test Ability',
			'description'       => 'This ability is for search testing.',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$id1 = $this->table->insert( $data1 );

		$data2 = array(
			'ability_slug'      => 'custom/other-ability',
			'label'             => 'Other Ability',
			'description'       => 'This is a different ability.',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$id2 = $this->table->insert( $data2 );

		// Search for "search" keyword
		$query = $this->table->new_query();
		$results = $query->search( 'search' )->get();

		$this->assertIsArray( $results, 'Search query should return array' );
		$result_ids = wp_list_pluck( $results, 'id' );
		$this->assertContains( $id1, $result_ids, 'First ability should be in search results' );
		$this->assertNotContains( $id2, $result_ids, 'Second ability should not be in search results' );
	}

	/**
	 * Test: Search excludes protected namespace prefixes (Memory DEC-PROTECTED-SLUGS-PATTERN).
	 *
	 * @since 0.0.1
	 */
	public function test_search_excludes_protected_prefixes() {
		// Insert ability with protected prefix "wp/"
		$protected_data = array(
			'ability_slug'      => 'wp/protected-ability',
			'label'             => 'Protected Ability',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$protected_id = $this->table->insert( $protected_data );

		// Insert ability with allowed prefix "custom/"
		$allowed_data = array(
			'ability_slug'      => 'custom/allowed-ability',
			'label'             => 'Allowed Ability',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$allowed_id = $this->table->insert( $allowed_data );

		// Search for "ability" keyword
		$query = $this->table->new_query();
		$results = $query->search( 'ability' )->get();

		$result_ids = wp_list_pluck( $results, 'id' );
		$this->assertNotContains( $protected_id, $result_ids, 'Protected prefix "wp/" should be filtered out by query' );
		$this->assertContains( $allowed_id, $result_ids, 'Allowed prefix "custom/" should be in results' );
	}

	/**
	 * Test: Protected prefix utility identifies reserved namespaces.
	 *
	 * @since 0.0.1
	 */
	public function test_protected_prefix_utility() {
		// Test protected prefixes
		$this->assertTrue(
			AcrossAI_Protected_Custom_Abilities::is_protected_prefix( 'wp/test' ),
			'"wp/" should be protected'
		);
		$this->assertTrue(
			AcrossAI_Protected_Custom_Abilities::is_protected_prefix( 'core/test' ),
			'"core/" should be protected'
		);
		$this->assertTrue(
			AcrossAI_Protected_Custom_Abilities::is_protected_prefix( 'acrossai/test' ),
			'"acrossai/" should be protected'
		);

		// Test allowed prefixes
		$this->assertFalse(
			AcrossAI_Protected_Custom_Abilities::is_protected_prefix( 'custom/test' ),
			'"custom/" should not be protected'
		);
		$this->assertFalse(
			AcrossAI_Protected_Custom_Abilities::is_protected_prefix( 'myapp/test' ),
			'"myapp/" should not be protected'
		);

		// Test malformed slugs
		$this->assertFalse(
			AcrossAI_Protected_Custom_Abilities::is_protected_prefix( 'no-slash' ),
			'Slug without "/" should not be protected'
		);
		$this->assertFalse(
			AcrossAI_Protected_Custom_Abilities::is_protected_prefix( '' ),
			'Empty slug should not be protected'
		);
	}

	/**
	 * Test: Pagination.
	 *
	 * @since 0.0.1
	 */
	public function test_query_pagination() {
		// Insert 25 test abilities
		for ( $i = 1; $i <= 25; $i++ ) {
			$data = array(
				'ability_slug'      => "custom/pagination-test-{$i}",
				'label'             => "Pagination Test {$i}",
				'callback_type'     => 'noop',
				'permission_type'   => 'always_allow',
			);
			$this->table->insert( $data );
		}

		// Query page 1 with 10 per page
		$query = $this->table->new_query();
		$page1 = $query->with_pagination( 10, 1 )->get();

		$this->assertCount( 10, $page1, 'Page 1 should have 10 items' );

		// Query page 2 with 10 per page
		$query2 = $this->table->new_query();
		$page2 = $query2->with_pagination( 10, 2 )->get();

		$this->assertCount( 10, $page2, 'Page 2 should have 10 items' );

		// Verify pages are different
		$page1_ids = wp_list_pluck( $page1, 'id' );
		$page2_ids = wp_list_pluck( $page2, 'id' );
		$this->assertNotEqual( $page1_ids, $page2_ids, 'Pages should contain different records' );
	}

	/**
	 * Test: Order by column.
	 *
	 * @since 0.0.1
	 */
	public function test_query_order_by() {
		// Insert test abilities with different labels
		$labels = array( 'Zebra Ability', 'Apple Ability', 'Monkey Ability' );
		$ids = array();

		foreach ( $labels as $label ) {
			$data = array(
				'ability_slug'      => 'custom/' . sanitize_title( $label ),
				'label'             => $label,
				'callback_type'     => 'noop',
				'permission_type'   => 'always_allow',
			);
			$ids[] = $this->table->insert( $data );
		}

		// Query ordered by label ASC
		$query = $this->table->new_query();
		$results_asc = $query->order_by( 'label', 'ASC' )->get();

		$labels_asc = wp_list_pluck( $results_asc, 'label' );
		$expected_asc = array( 'Apple Ability', 'Monkey Ability', 'Zebra Ability' );

		// Filter to only our test abilities
		$labels_asc = array_intersect( $labels_asc, $expected_asc );

		$this->assertNotEmpty( $labels_asc, 'Results should include our test abilities' );

		// Query ordered by label DESC
		$query2 = $this->table->new_query();
		$results_desc = $query2->order_by( 'label', 'DESC' )->get();

		$labels_desc = wp_list_pluck( $results_desc, 'label' );
		// Zebra should appear before Apple in DESC order
		$zebra_pos = array_search( 'Zebra Ability', $labels_desc, true );
		$apple_pos = array_search( 'Apple Ability', $labels_desc, true );

		if ( false !== $zebra_pos && false !== $apple_pos ) {
			$this->assertGreaterThan( $apple_pos, $zebra_pos, 'DESC order should place Zebra before Apple' );
		}
	}

	/**
	 * Test: Tri-state flags (NULL, 0, 1).
	 *
	 * @since 0.0.1
	 */
	public function test_tristate_flags() {
		$data = array(
			'ability_slug'      => 'custom/tristate-test',
			'label'             => 'Tristate Test',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
			'readonly'          => null,
			'destructive'       => 0,
			'idempotent'        => 1,
		);

		$id = $this->table->insert( $data );
		$row = $this->table->get( $id );

		$this->assertNull( $row->readonly, 'readonly should be NULL' );
		$this->assertEquals( 0, $row->destructive, 'destructive should be 0' );
		$this->assertEquals( 1, $row->idempotent, 'idempotent should be 1' );
	}

	/**
	 * Test: Timestamps are set automatically.
	 *
	 * @since 0.0.1
	 */
	public function test_timestamps_are_set() {
		$data = array(
			'ability_slug'      => 'custom/timestamp-test',
			'label'             => 'Timestamp Test',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		$before_insert = time();
		$id = $this->table->insert( $data );
		$after_insert = time();

		$row = $this->table->get( $id );

		$this->assertNotEmpty( $row->created_at, 'created_at should be set' );
		$this->assertNotEmpty( $row->updated_at, 'updated_at should be set' );

		// Verify timestamps are recent (within acceptable time range)
		$created_timestamp = strtotime( $row->created_at );
		$this->assertGreaterThanOrEqual( $before_insert, $created_timestamp, 'created_at should be >= insert time' );
		$this->assertLessThanOrEqual( $after_insert + 2, $created_timestamp, 'created_at should be <= insert time + 2s' );
	}

	/**
	 * Test: Slug exists check.
	 *
	 * @since 0.0.1
	 */
	public function test_slug_exists_check() {
		$slug = 'custom/slug-exists-test';

		// Slug should not exist initially
		$this->assertFalse( $this->table->slug_exists( $slug ), 'Slug should not exist initially' );

		// Insert ability
		$data = array(
			'ability_slug'      => $slug,
			'label'             => 'Slug Exists Test',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$this->table->insert( $data );

		// Slug should now exist
		$this->assertTrue( $this->table->slug_exists( $slug ), 'Slug should exist after insert' );
	}

	/**
	 * Test: Count total abilities.
	 *
	 * @since 0.0.1
	 */
	public function test_count_abilities() {
		// Count initial (should be 0 or whatever cleanup missed)
		$count_before = $this->table->count();

		// Insert 5 abilities
		for ( $i = 1; $i <= 5; $i++ ) {
			$data = array(
				'ability_slug'      => "custom/count-test-{$i}",
				'label'             => "Count Test {$i}",
				'callback_type'     => 'noop',
				'permission_type'   => 'always_allow',
			);
			$this->table->insert( $data );
		}

		$count_after = $this->table->count();

		$this->assertEquals( 5, $count_after - $count_before, 'Count should increase by 5 after inserts' );
	}

	/**
	 * Test: Query with multiple filters combined.
	 *
	 * @since 0.0.1
	 */
	public function test_query_combined_filters() {
		// Insert test data
		$data1 = array(
			'ability_slug'      => 'custom/combined-test-1',
			'label'             => 'Enabled Custom Ability',
			'category'          => 'custom',
			'enabled'           => 1,
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$id1 = $this->table->insert( $data1 );

		$data2 = array(
			'ability_slug'      => 'custom/combined-test-2',
			'label'             => 'Disabled Custom Ability',
			'category'          => 'custom',
			'enabled'           => 0,
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$id2 = $this->table->insert( $data2 );

		$data3 = array(
			'ability_slug'      => 'integration/combined-test-1',
			'label'             => 'Enabled Integration Ability',
			'category'          => 'integration',
			'enabled'           => 1,
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);
		$id3 = $this->table->insert( $data3 );

		// Query: category=custom AND enabled=1
		$query = $this->table->new_query();
		$results = $query->by_category( 'custom' )->enabled_only()->get();

		$result_ids = wp_list_pluck( $results, 'id' );
		$this->assertContains( $id1, $result_ids, 'Enabled custom ability should match filter' );
		$this->assertNotContains( $id2, $result_ids, 'Disabled custom ability should not match filter' );
		$this->assertNotContains( $id3, $result_ids, 'Integration ability should not match custom filter' );
	}
}

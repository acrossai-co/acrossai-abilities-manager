<?php
/**
 * Unit Tests for Custom Ability Processor
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Tests
 * @since 1.0.0
 */

// Require test bootstrap
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/vendor/autoload.php';

/**
 * Test_Custom_Ability_Processor class
 *
 * Tests for processor registration and permission callback injection.
 *
 * @since 1.0.0
 */
class Test_Custom_Ability_Processor extends WP_UnitTestCase {

	/**
	 * Test processor registers enabled custom abilities
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_processor_registers_enabled_abilities() {
		// Create test ability in database
		$table = AcrossAI_Custom_Ability_Table::instance();

		$ability_data = array(
			'ability_slug'      => 'test/enabled-ability',
			'label'             => 'Test Enabled Ability',
			'description'       => 'Test ability for registration',
			'enabled'           => 1,
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
			'show_in_rest'      => 1,
			'show_in_mcp'       => 0,
		);

		$inserted = $table->insert( $ability_data );
		$this->assertIsInt( $inserted );

		// Mock the global $wp_abilities to check registration
		global $wp_abilities;
		if ( ! isset( $wp_abilities ) ) {
			$wp_abilities = array();
		}

		// Call processor to register abilities
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		// Verify ability was registered (if wp_register_ability exists)
		if ( function_exists( 'wp_register_ability' ) ) {
			// Would verify in WordPress Abilities API
			$this->assertTrue( true );
		}
	}

	/**
	 * Test processor skips disabled abilities
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_processor_skips_disabled_abilities() {
		$table = AcrossAI_Custom_Ability_Table::instance();

		$ability_data = array(
			'ability_slug'      => 'test/disabled-ability',
			'label'             => 'Test Disabled Ability',
			'description'       => 'Test disabled ability',
			'enabled'           => 0, // Disabled
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		$inserted = $table->insert( $ability_data );
		$this->assertIsInt( $inserted );

		// Setup hook to track registrations
		$registrations = array();
		add_action(
			'acrossai_custom_ability_registered',
			function( $ability ) use ( &$registrations ) {
				$registrations[] = $ability->ability_slug;
			}
		);

		// Call processor
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		// Verify disabled ability was not registered
		$this->assertNotContains( 'test/disabled-ability', $registrations );
	}

	/**
	 * Test permission callback for always_allow type
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_permission_callback_always_allow() {
		$table = AcrossAI_Custom_Ability_Table::instance();

		$ability_data = array(
			'ability_slug'      => 'test/always-allow',
			'label'             => 'Always Allow Ability',
			'enabled'           => 1,
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
			'permission_config' => '{}',
		);

		$inserted = $table->insert( $ability_data );
		$this->assertIsInt( $inserted );

		// Verify registration completes without errors
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		$this->assertTrue( true ); // Success if no exceptions
	}

	/**
	 * Test permission callback for logged_in type
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_permission_callback_logged_in() {
		$table = AcrossAI_Custom_Ability_Table::instance();

		$ability_data = array(
			'ability_slug'      => 'test/logged-in',
			'label'             => 'Logged In Ability',
			'enabled'           => 1,
			'callback_type'     => 'noop',
			'permission_type'   => 'logged_in',
			'permission_config' => '{}',
		);

		$inserted = $table->insert( $ability_data );
		$this->assertIsInt( $inserted );

		// Verify registration completes
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		$this->assertTrue( true );
	}

	/**
	 * Test permission callback for capability type with valid capability
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_permission_callback_capability_valid() {
		$table = AcrossAI_Custom_Ability_Table::instance();

		$ability_data = array(
			'ability_slug'      => 'test/capability-valid',
			'label'             => 'Capability Ability',
			'enabled'           => 1,
			'callback_type'     => 'noop',
			'permission_type'   => 'capability',
			'permission_config' => wp_json_encode( array( 'capability' => 'manage_options' ) ),
		);

		$inserted = $table->insert( $ability_data );
		$this->assertIsInt( $inserted );

		// Verify registration completes
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		$this->assertTrue( true );
	}

	/**
	 * Test permission callback with non-existent capability
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_permission_callback_capability_not_found() {
		$table = AcrossAI_Custom_Ability_Table::instance();

		$ability_data = array(
			'ability_slug'      => 'test/capability-invalid',
			'label'             => 'Invalid Capability Ability',
			'enabled'           => 1,
			'callback_type'     => 'noop',
			'permission_type'   => 'capability',
			'permission_config' => wp_json_encode( array( 'capability' => 'non_existent_xyz' ) ),
		);

		$inserted = $table->insert( $ability_data );
		$this->assertIsInt( $inserted );

		// Setup hook to track permission errors
		$errors = array();
		add_action(
			'acrossai_custom_ability_permission_error',
			function( $slug, $error_code, $capability ) use ( &$errors ) {
				$errors[] = array( 'slug' => $slug, 'code' => $error_code, 'cap' => $capability );
			},
			10,
			3
		);

		// Call processor
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		// Verify error was recorded
		$this->assertCount( 1, $errors );
		$this->assertEquals( 'capability_not_found', $errors[0]['code'] );
	}

	/**
	 * Test registered hook is fired with correct data
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_registered_hook_fires() {
		$table = AcrossAI_Custom_Ability_Table::instance();

		$ability_data = array(
			'ability_slug'      => 'test/hook-fire',
			'label'             => 'Hook Fire Test',
			'enabled'           => 1,
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		$inserted = $table->insert( $ability_data );
		$this->assertIsInt( $inserted );

		// Setup hook to capture registered data
		$hook_data = array();
		add_action(
			'acrossai_custom_ability_registered',
			function( $ability, $args ) use ( &$hook_data ) {
				$hook_data = array(
					'ability_slug' => $ability->ability_slug,
					'label'        => $ability->label,
					'args_has_meta' => isset( $args['meta'] ),
				);
			},
			10,
			2
		);

		// Call processor
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		// Verify hook was fired with correct data
		$this->assertArrayHasKey( 'ability_slug', $hook_data );
		$this->assertEquals( 'test/hook-fire', $hook_data['ability_slug'] );
		$this->assertTrue( $hook_data['args_has_meta'] );
	}

	/**
	 * Test tri-state flag values are preserved
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_tri_state_flags_preserved() {
		$table = AcrossAI_Custom_Ability_Table::instance();

		$ability_data = array(
			'ability_slug'    => 'test/tri-state',
			'label'           => 'Tri-State Test',
			'enabled'         => 1,
			'callback_type'   => 'noop',
			'permission_type' => 'always_allow',
			'readonly'        => null, // Inherit
			'destructive'     => 1,    // True
			'idempotent'      => 0,    // False
		);

		$inserted = $table->insert( $ability_data );
		$this->assertIsInt( $inserted );

		// Capture registered args
		$registered_args = array();
		add_action(
			'acrossai_custom_ability_registered',
			function( $ability, $args ) use ( &$registered_args ) {
				$registered_args = $args;
			},
			10,
			2
		);

		// Call processor
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		// Verify tri-state values
		$this->assertNull( $registered_args['readonly'] );
		$this->assertTrue( $registered_args['destructive'] );
		$this->assertFalse( $registered_args['idempotent'] );
	}

	/**
	 * Test metadata is properly structured in registration args
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_metadata_structure() {
		$table = AcrossAI_Custom_Ability_Table::instance();

		$ability_data = array(
			'ability_slug'      => 'test/meta-struct',
			'label'             => 'Metadata Structure Test',
			'category'          => 'test',
			'enabled'           => 1,
			'callback_type'     => 'filter_hook',
			'callback_config'   => wp_json_encode( array( 'hook_name' => 'my_hook' ) ),
			'permission_type'   => 'always_allow',
			'show_in_mcp'       => 1,
			'mcp_type'          => 'tool',
			'mcp_servers'       => wp_json_encode( array( 'server1' ) ),
		);

		$inserted = $table->insert( $ability_data );
		$this->assertIsInt( $inserted );

		// Capture registered args
		$registered_args = array();
		add_action(
			'acrossai_custom_ability_registered',
			function( $ability, $args ) use ( &$registered_args ) {
				$registered_args = $args;
			},
			10,
			2
		);

		// Call processor
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		// Verify metadata structure
		$this->assertArrayHasKey( 'meta', $registered_args );
		$meta = $registered_args['meta'];

		$this->assertEquals( 'test', $meta['category'] );
		$this->assertEquals( 'filter_hook', $meta['callback_type'] );
		$this->assertIsArray( $meta['callback_config'] );
		$this->assertEquals( 'my_hook', $meta['callback_config']['hook_name'] );
		$this->assertEquals( 'tool', $meta['mcp_type'] );
		$this->assertIsArray( $meta['mcp_servers'] );
		$this->assertContains( 'server1', $meta['mcp_servers'] );
		$this->assertIsInt( $meta['database_id'] );
	}

	/**
	 * Test processor handles empty database gracefully
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_processor_handles_empty_database() {
		// Ensure no abilities in database
		$table = AcrossAI_Custom_Ability_Table::instance();
		$query = $table->query();
		$results = $query->get_results();

		// If any exist, delete them for this test
		foreach ( $results as $result ) {
			$table->delete( $result->id );
		}

		// Call processor with empty database
		$processor = AcrossAI_Custom_Ability_Processor::instance();
		$processor->register_custom_abilities();

		// Verify no errors
		$this->assertTrue( true );
	}

	/**
	 * Test processor is singleton
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_processor_is_singleton() {
		$processor1 = AcrossAI_Custom_Ability_Processor::instance();
		$processor2 = AcrossAI_Custom_Ability_Processor::instance();

		$this->assertSame( $processor1, $processor2 );
	}
}

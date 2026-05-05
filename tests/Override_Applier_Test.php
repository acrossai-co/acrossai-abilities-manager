<?php
/**
 * Unit tests for Override_Applier MCP assignment and access control behavior.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Tests;

use AcrossAI_Abilities_Manager\Database\Repository;
use AcrossAI_Abilities_Manager\Database\Schema;
use AcrossAI_Abilities_Manager\Runtime\Override_Applier;

/**
 * Tests for the Override_Applier runtime class.
 *
 * Covers MCP server visibility modes (disable/all/specific) and
 * access-control permission_callback wrapping behavior.
 */
class Override_Applier_Test extends \WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		Schema::maybe_upgrade_table();
		wp_cache_flush();
		// Reset the static caches between tests by forcing a re-prime.
		$reflection = new \ReflectionClass( Override_Applier::class );
		foreach ( array( 'overrides', 'ac_slugs', 'bootstrapped' ) as $prop ) {
			$p = $reflection->getProperty( $prop );
			$p->setAccessible( true );
			$p->setValue( null, 'bootstrapped' === $prop ? false : null );
		}
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down() {
		global $wpdb;
		$table = Schema::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DELETE FROM {$table}" );
		wp_cache_flush();
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// MCP visibility: "Disable for MCP" (mcp_public = null, mcp_servers = null)
	// -------------------------------------------------------------------------

	/**
	 * When no override exists, the ability args are returned unchanged.
	 */
	public function test_apply_no_override_returns_args_unchanged() {
		$args = array(
			'label'       => 'My Ability',
			'description' => 'An ability with no override',
		);

		$result = Override_Applier::apply( $args, 'no-override/ability' );

		$this->assertSame( $args, $result );
	}

	/**
	 * When mcp_public is false and mcp_servers is empty, mcp.public should be false.
	 */
	public function test_apply_disable_for_mcp_sets_public_false() {
		Repository::upsert(
			'test/disable-ability',
			array(
				'mcp_public'  => false,
				'mcp_servers' => null,
			)
		);

		$args   = array(
			'meta' => array(
				'mcp' => array( 'public' => true ),
			),
		);
		$result = Override_Applier::apply( $args, 'test/disable-ability' );

		$this->assertFalse( $result['meta']['mcp']['public'] );
	}

	// -------------------------------------------------------------------------
	// MCP visibility: "Allow in all MCP servers" (mcp_public = true)
	// -------------------------------------------------------------------------

	/**
	 * When mcp_public is true, meta.mcp.public should be true.
	 */
	public function test_apply_allow_all_servers_sets_public_true() {
		Repository::upsert(
			'test/allow-all-ability',
			array(
				'mcp_public'  => true,
				'mcp_servers' => null,
			)
		);

		$args   = array( 'meta' => array( 'mcp' => array( 'public' => false ) ) );
		$result = Override_Applier::apply( $args, 'test/allow-all-ability' );

		$this->assertTrue( $result['meta']['mcp']['public'] );
	}

	// -------------------------------------------------------------------------
	// MCP visibility: "Allow in specific MCP servers"
	// -------------------------------------------------------------------------

	/**
	 * When mcp_public is false but mcp_servers is non-empty, meta.mcp.public
	 * should be forced true so the MCP adapter registers it globally;
	 * Mcp_Server_Filter removes it from non-allowed servers at request time.
	 */
	public function test_apply_specific_servers_forces_public_true() {
		Repository::upsert(
			'test/specific-servers-ability',
			array(
				'mcp_public'  => false,
				'mcp_servers' => array( 'mcp-adapter-default-server', 'custom-server' ),
			)
		);

		$args   = array( 'meta' => array( 'mcp' => array( 'public' => false ) ) );
		$result = Override_Applier::apply( $args, 'test/specific-servers-ability' );

		// public must be true so the MCP adapter sees it globally.
		$this->assertTrue( $result['meta']['mcp']['public'] );
	}

	/**
	 * has_server_restriction() returns true only for specific-servers mode.
	 */
	public function test_has_server_restriction_true_for_specific_servers() {
		Repository::upsert(
			'test/restricted-ability',
			array(
				'mcp_public'  => false,
				'mcp_servers' => array( 'server-a' ),
			)
		);

		$this->assertTrue( Override_Applier::has_server_restriction( 'test/restricted-ability' ) );
	}

	/**
	 * has_server_restriction() returns false when mcp_public is true (all servers).
	 */
	public function test_has_server_restriction_false_for_all_servers() {
		Repository::upsert(
			'test/all-servers-ability',
			array(
				'mcp_public'  => true,
				'mcp_servers' => null,
			)
		);

		$this->assertFalse( Override_Applier::has_server_restriction( 'test/all-servers-ability' ) );
	}

	/**
	 * should_expose_to_mcp_server() returns true for a server in the allowlist.
	 */
	public function test_should_expose_true_for_listed_server() {
		Repository::upsert(
			'test/server-allow-ability',
			array(
				'mcp_public'  => false,
				'mcp_servers' => array( 'target-server' ),
			)
		);

		$this->assertTrue( Override_Applier::should_expose_to_mcp_server( 'test/server-allow-ability', 'target-server' ) );
	}

	/**
	 * should_expose_to_mcp_server() returns false for a server NOT in the allowlist.
	 */
	public function test_should_expose_false_for_unlisted_server() {
		Repository::upsert(
			'test/server-deny-ability',
			array(
				'mcp_public'  => false,
				'mcp_servers' => array( 'allowed-server' ),
			)
		);

		$this->assertFalse( Override_Applier::should_expose_to_mcp_server( 'test/server-deny-ability', 'other-server' ) );
	}

	// -------------------------------------------------------------------------
	// MCP type routing
	// -------------------------------------------------------------------------

	/**
	 * When mcp_type is set in an override, it is applied to meta.mcp.type.
	 */
	public function test_apply_sets_mcp_type() {
		Repository::upsert(
			'test/type-ability',
			array(
				'mcp_public' => true,
				'mcp_type'   => 'resource',
			)
		);

		$args   = array( 'meta' => array( 'mcp' => array() ) );
		$result = Override_Applier::apply( $args, 'test/type-ability' );

		$this->assertSame( 'resource', $result['meta']['mcp']['type'] );
	}

	/**
	 * When mcp_type is empty in an override, meta.mcp.type is not changed.
	 */
	public function test_apply_does_not_set_empty_mcp_type() {
		Repository::upsert(
			'test/no-type-ability',
			array(
				'mcp_public' => true,
				'mcp_type'   => null,
			)
		);

		$args   = array( 'meta' => array( 'mcp' => array( 'type' => 'tool' ) ) );
		$result = Override_Applier::apply( $args, 'test/no-type-ability' );

		// Original type preserved when override has no mcp_type.
		$this->assertSame( 'tool', $result['meta']['mcp']['type'] );
	}

	// -------------------------------------------------------------------------
	// Access control: permission_callback wrapping
	// -------------------------------------------------------------------------

	/**
	 * When a slug has no AC rule, its permission_callback is not wrapped.
	 * The original args are returned as-is.
	 */
	public function test_apply_no_ac_rule_does_not_wrap_permission_callback() {
		$original_cb = static function () {
			return true;
		};

		$args = array( 'permission_callback' => $original_cb );

		$result = Override_Applier::apply( $args, 'no-override/ability' );

		$this->assertSame( $original_cb, $result['permission_callback'] );
	}

	/**
	 * When a slug has an AC rule ('everyone'), it is not restricted —
	 * the permission_callback must not be wrapped.
	 */
	public function test_apply_everyone_ac_rule_does_not_wrap_callback() {
		global $wpdb;

		// Insert an 'everyone' rule directly so we bypass the library UI.
		$table = \WPBoilerplate\AccessControl\AccessControlTable::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->insert(
			$table,
			array(
				'namespace'             => 'acrossai-abilities-manager',
				'key'                   => 'test/everyone-ability',
				'access_control_key'    => 'everyone',
				'access_control_value'  => 'everyone',
			)
		);

		$original_cb = static function () {
			return true;
		};
		$args = array( 'permission_callback' => $original_cb );

		$result = Override_Applier::apply( $args, 'test/everyone-ability' );

		// 'everyone' rules must not wrap the callback.
		$this->assertSame( $original_cb, $result['permission_callback'] );

		// Cleanup.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $table, array( 'key' => 'test/everyone-ability', 'namespace' => 'acrossai-abilities-manager' ) );
	}

	/**
	 * When a slug has a role-restricted AC rule, its permission_callback IS
	 * wrapped with the access control check.
	 */
	public function test_apply_role_restricted_ac_rule_wraps_callback() {
		global $wpdb;

		// Insert a role rule directly.
		$table = \WPBoilerplate\AccessControl\AccessControlTable::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->insert(
			$table,
			array(
				'namespace'             => 'acrossai-abilities-manager',
				'key'                   => 'test/role-restricted-ability',
				'access_control_key'    => 'role',
				'access_control_value'  => 'editor',
			)
		);

		$original_cb = static function () {
			return true;
		};
		$args = array( 'permission_callback' => $original_cb );

		$result = Override_Applier::apply( $args, 'test/role-restricted-ability' );

		// The callback must be replaced with a wrapper (not the same closure).
		$this->assertNotSame( $original_cb, $result['permission_callback'] );
		$this->assertIsCallable( $result['permission_callback'] );

		// Cleanup.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $table, array( 'key' => 'test/role-restricted-ability', 'namespace' => 'acrossai-abilities-manager' ) );
	}
}

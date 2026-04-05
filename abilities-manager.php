<?php
/**
 * Plugin Name:       Abilities Manager
 * Description:       Manage WordPress Abilities metadata from a classic wp-admin UI.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            raftaar1191
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       abilities-manager
 *
 * @package Abilities_Manager
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ABILITIES_MANAGER_VERSION' ) ) {
	define( 'ABILITIES_MANAGER_VERSION', '0.1.0' );
}

if ( ! defined( 'ABILITIES_MANAGER_PLUGIN_FILE' ) ) {
	define( 'ABILITIES_MANAGER_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ABILITIES_MANAGER_PLUGIN_DIR' ) ) {
	define( 'ABILITIES_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ABILITIES_MANAGER_PLUGIN_URL' ) ) {
	define( 'ABILITIES_MANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Abilities_Manager\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file           = ABILITIES_MANAGER_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

function abilities_manager_bootstrap(): void {
	if ( is_admin() ) {
		add_action( 'admin_menu', array( 'Abilities_Manager\Admin\Menu', 'register' ) );
		add_action( 'admin_init', array( 'Abilities_Manager\Database\Schema', 'maybe_upgrade_table' ) );
		add_action( 'admin_init', array( 'Abilities_Manager\Admin\Edit_Screen', 'handle_actions' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ABILITIES_MANAGER_PLUGIN_FILE ), array( 'Abilities_Manager\Admin\Menu', 'plugin_action_links' ) );
	}
	add_action( 'wp_abilities_api_init', array( 'Abilities_Manager\Runtime\Override_Applier', 'bootstrap' ), 0 );
}
add_action( 'plugins_loaded', 'abilities_manager_bootstrap' );

register_activation_hook(
	ABILITIES_MANAGER_PLUGIN_FILE,
	static function (): void {
		\Abilities_Manager\Database\Schema::maybe_upgrade_table();
	}
);

<?php
/**
 * Plugin Name:       Abilities Hub
 * Plugin URI:        https://github.com/AcrossWP/abilities-hub
 * Description:       Manage WordPress Abilities metadata from a classic wp-admin UI.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            acrosswp
 * Author URI:        https://acrosswp.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       abilities-hub
 *
 * @package Abilities_Hub
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ABILITIES_HUB_VERSION' ) ) {
	define( 'ABILITIES_HUB_VERSION', '0.1.0' );
}

if ( ! defined( 'ABILITIES_HUB_PLUGIN_FILE' ) ) {
	define( 'ABILITIES_HUB_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ABILITIES_HUB_PLUGIN_DIR' ) ) {
	define( 'ABILITIES_HUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ABILITIES_HUB_PLUGIN_URL' ) ) {
	define( 'ABILITIES_HUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Abilities_Hub\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file           = ABILITIES_HUB_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

function abilities_hub_bootstrap(): void {
	if ( is_admin() ) {
		add_action( 'admin_menu', array( 'Abilities_Hub\Admin\Menu', 'register' ) );
		add_action( 'admin_init', array( 'Abilities_Hub\Database\Schema', 'maybe_upgrade_table' ) );
		add_action( 'admin_init', array( 'Abilities_Hub\Admin\Edit_Screen', 'handle_actions' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ABILITIES_HUB_PLUGIN_FILE ), array( 'Abilities_Hub\Admin\Menu', 'plugin_action_links' ) );
	}
	add_action( 'wp_abilities_api_init', array( 'Abilities_Hub\Runtime\Override_Applier', 'bootstrap' ), 0 );
}
add_action( 'plugins_loaded', 'abilities_hub_bootstrap' );

register_activation_hook(
	ABILITIES_HUB_PLUGIN_FILE,
	static function (): void {
		\Abilities_Hub\Database\Schema::maybe_upgrade_table();
	}
);

<?php
/**
 * Uninstall routine.
 *
 * @package Abilities_Manager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'abilities-manager.php';

Abilities_Manager\Database\Schema::drop_table();

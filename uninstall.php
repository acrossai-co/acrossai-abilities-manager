<?php
/**
 * Uninstall routine.
 *
 * @package Abilities_Hub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'abilities-hub.php';

Abilities_Hub\Database\Schema::drop_table();

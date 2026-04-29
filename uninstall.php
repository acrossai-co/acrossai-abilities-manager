<?php
/**
 * Uninstall routine.
 *
 * WordPress executes this file when the plugin is deleted from the Plugins
 * screen. It drops the custom database table and removes all plugin options
 * so no data is left behind after uninstallation.
 *
 * @package AcrossAI_Abilities_Manager
 */

// Bail if WordPress did not trigger the uninstall — prevents direct execution.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'acrossai-abilities-manager.php';

// Remove the database table and the schema-version option.
AcrossAI_Abilities_Manager\Database\Schema::drop_table();

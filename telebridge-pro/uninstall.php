<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Telebridge_Ultimate
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Delete Main Settings
delete_option( 'telebridge_ultimate_settings' );

// 2. Delete Global AI Sites Database (if you want to clear custom sites too)
// Uncomment the next line if you want to delete custom AI sites on uninstall
// delete_option( 'ai_sites_pro_global_db' );

// 3. Clear Transients (Album locks)
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tb_album_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tb_album_%'" );
<?php

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit;

/**
 * Maybe dont delete data.
 */

$settings = get_option( 'foflog_settings', [] );

$delete_data = true;
if ( array_key_exists( 'delete_plugin_delete_data', $settings ) )
    $delete_data = (bool) $settings['delete_plugin_delete_data'];

if ( !$delete_data )
    return;

/**
 * Delete DB table.
 */

global $wpdb;

$table = $wpdb->prefix.'foflog_urls';

$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

/**
 * Delete settings.
 */

delete_option( 'foflog_settings' );


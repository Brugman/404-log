<?php

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit;

/**
 * Delete DB table.
 */

global $wpdb;

$table = $wpdb->prefix.'foflog_entries';

$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

/**
 * Delete settings.
 */

delete_option( 'foflog_settings' );


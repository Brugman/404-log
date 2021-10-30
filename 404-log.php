<?php

/**
 * Plugin Name: 404 Log
 * Description: What do I do?
 * Version: 0.1.0
 * Plugin URI: https://timbr.dev/
 * Author: Tim Brugman
 * Author URI: https://timbr.dev/
 * Text Domain: foflog
 */

if ( !defined( 'ABSPATH' ) )
    exit;

define( 'FOFLOG_FILE', basename( __FILE__ ) );
define( 'FOFLOG_DIR', basename( __DIR__ ) );

define( 'FOFLOG_USE_FS', true );
define( 'FOFLOG_USE_DB', true );

define( 'FOFLOG_LOG_DIR', __DIR__.'/logs/' );

function d( $var )
{
    echo "<pre style=\"max-height: 800px; z-index: 9999; position: relative; overflow-y: scroll; white-space: pre-wrap; word-wrap: break-word; padding: 10px 15px; border: 1px solid #fff; background-color: #161616; text-align: left; line-height: 1.5; font-family: Courier; font-size: 16px; color: #fff; \">";
    print_r( $var );
    echo "</pre>";
}

function save_404_visit_in_fs( $data )
{
    $data = implode( ',', $data );

    file_put_contents(
        FOFLOG_LOG_DIR.wp_date('Y-m-d').'.log',
        $data."\n",
        FILE_APPEND
    );
}

function save_404_visit_in_db( $data )
{
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix.'foflog_entries',
        $data
    );
}

add_action( 'template_redirect', function () {

    if ( !is_404() )
        return;

    $data = [
        'timestamp' => time(),
        'url' => $_SERVER['REQUEST_URI'],
    ];

    if ( FOFLOG_USE_FS )
        save_404_visit_in_fs( $data );

    if ( FOFLOG_USE_DB )
        save_404_visit_in_db( $data );
});

add_action( 'admin_menu', function () {
    add_management_page(
        '404 log', // page title
        '404 log', // menu title
        'manage_options', // capability
        'foflog-main', // menu slug
        'foflog_controller', // function
        null // position
    );
});

function foflog_controller()
{
    foflog_page_main();
}

function foflog_page_main()
{
?>
<div class="wrap foflog-wrapper">

    <h1>404 log</h1>

    <div style="display: grid; grid-template-columns: auto auto;">
        <div>
            <h2>File logs</h2>
            <?php foflog_display_log( foflog_get_logs_from_fs() ); ?>
        </div>
        <div>
            <h2>DB logs</h2>
            <?php foflog_display_log( foflog_get_logs_from_db() ); ?>
        </div>
    </div>

</div><!-- wrap -->
<?php
}

function foflog_display_log( $log_entries )
{
?>
<?php if ( !empty( $log_entries ) ): ?>
<table class="wp-list-table widefat fixed striped" style="width: auto; margin-top: 10px;">
    <thead>
        <tr>
            <td>Date</td>
            <td>URL</td>
        </tr>
    </thead>
    <tbody>
<?php foreach ( $log_entries as $log_entry ): ?>
        <tr>
            <td><?=wp_date( 'Y-m-d H:i', $log_entry[0] );?></td>
            <td><?=$log_entry[1];?></td>
        </tr>
<?php endforeach; // $log_entries ?>
    </tbody>
</table>
<?php else: // $log_entries is empty ?>
    <p>No log entries found.</p>
<?php endif; // $log_entries ?>
<?php
}

function foflog_get_logs_from_fs()
{
    $log_lines = '';
    $log_files = glob( FOFLOG_LOG_DIR.'*.log' );

    foreach ( $log_files as $log_file )
        $log_lines .= file_get_contents( $log_file );

    $log_entries = [];

    $lines = explode( "\n", trim( $log_lines ) );
    foreach ( $lines as $line )
        $log_entries[] = explode( ',', $line );

    return $log_entries;
}

function foflog_get_logs_from_db()
{
    global $wpdb;

    $table = $wpdb->prefix.'foflog_entries';

    return $wpdb->get_results( "SELECT `timestamp`, `url` FROM {$table}", ARRAY_N );
}

function foflog_empty_db_table()
{
    global $wpdb;

    $table = $wpdb->prefix.'foflog_entries';

    $wpdb->query( "TRUNCATE TABLE {$table}" );
}

function foflog_delete_db_table()
{
    global $wpdb;

    $table = $wpdb->prefix.'foflog_entries';

    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

function foflog_create_db_table()
{
    global $wpdb;

    $table   = $wpdb->prefix.'foflog_entries';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        timestamp varchar(255) NOT NULL,
        url varchar(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    require_once( ABSPATH.'wp-admin/includes/upgrade.php' );

    dbDelta( $sql );
}

register_activation_hook( __FILE__, 'foflog_create_db_table' );

register_deactivation_hook( __FILE__, 'foflog_empty_db_table' );

register_uninstall_hook( __FILE__, 'foflog_delete_db_table' );

function foflog_textdomain()
{
    return 'foflog';
}

add_filter( 'plugin_action_links_'.FOFLOG_DIR.'/'.FOFLOG_FILE, function ( $links ) {

    $settings_url = admin_url( 'tools.php?page=foflog-main' );
    $settings_link = '<a href="'.$settings_url.'">'.__( 'Settings', foflog_textdomain() ).'</a>';

    $links['settings'] = $settings_link;

    return $links;
});


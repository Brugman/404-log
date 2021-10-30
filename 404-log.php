<?php

/**
 * Plugin Name: 404 Log
 * Description:
 * Version: 0.2.0
 * Plugin URI: https://github.com/Brugman/404-log
 * Author: Tim Brugman
 * Author URI: https://timbr.dev
 * Text Domain: foflog
 */

if ( !defined( 'ABSPATH' ) )
    exit;

define( 'FOFLOG_FILE_PATH', __FILE__ );
define( 'FOFLOG_FILE', basename( __FILE__ ) );
define( 'FOFLOG_DIR', basename( __DIR__ ) );
define( 'FOFLOG_LOG_DIR', __DIR__.'/logs/' );

if ( !class_exists( 'FOFLog' ) )
{
    class FOFLog
    {
        private $use_fs = true;
        private $use_db = true;

        /**
         * Constructor.
         */

        public function __construct()
        {
        }

        /**
         * Debug.
         */

        private function d( $var = false )
        {
            echo "<pre style=\"max-height: 800px; z-index: 9999; position: relative; overflow-y: scroll; white-space: pre-wrap; word-wrap: break-word; padding: 10px 15px; border: 1px solid #fff; background-color: #161616; text-align: left; line-height: 1.5; font-family: Courier; font-size: 16px; color: #fff; \">";
            print_r( $var );
            echo "</pre>";
        }

        private function dd( $var = false )
        {
            $this->d( $var );
            exit;
        }

        /**
         * Helpers.
         */

        private function textdomain()
        {
            return 'foflog';
        }

        private function admin_url( $args = [] )
        {
            $args['page'] = 'foflog';

            return admin_url( 'tools.php?'.http_build_query( $args ) );
        }

        /**
         * Getters.
         */

        private function get_logs_from_fs()
        {
            $log_lines = '';
            $log_files = glob( FOFLOG_LOG_DIR.'*.log' );

            foreach ( $log_files as $log_file )
                $log_lines .= file_get_contents( $log_file );

            $log_lines = trim( $log_lines );

            if ( $log_lines == '' )
                return [];

            $log_entries = [];

            $lines = explode( "\n", $log_lines );
            foreach ( $lines as $line )
                $log_entries[] = explode( ',', $line );

            return $log_entries;
        }

        private function get_logs_from_db()
        {
            global $wpdb;

            $table = $wpdb->prefix.'foflog_entries';

            return $wpdb->get_results( "SELECT `timestamp`, `url` FROM {$table}", ARRAY_N );
        }

        /**
         * Setters.
         */

        private function set_404_visit_in_fs( $data )
        {
            $data = implode( ',', $data );

            file_put_contents(
                FOFLOG_LOG_DIR.wp_date('Y-m-d').'.log',
                $data."\n",
                FILE_APPEND
            );
        }

        private function set_404_visit_in_db( $data )
        {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix.'foflog_entries',
                $data
            );
        }

        /**
         * Page Helpers.
         */

        private function page_header()
        {
?>
<div class="wrap foflog-wrapper">
<?php
        }

        private function page_footer()
        {
?>
</div><!-- wrap -->
<?php
        }

        private function display_log( $log_entries )
        {
?>
<?php if ( !empty( $log_entries ) ): ?>
<table class="wp-list-table widefat fixed striped" style="width: auto; margin-top: 10px;">
    <thead>
        <tr>
            <td><?php _e( 'Date', $this->textdomain() ); ?></td>
            <td><?php _e( 'URL', $this->textdomain() ); ?></td>
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
    <p><?php _e( 'No log entries found.', $this->textdomain() ); ?></p>
<?php endif; // $log_entries ?>
<?php
        }

        /**
         * Nav.
         */

        public function subpage_nav()
        {
            $subpages = [
                [
                    'title' => __( 'Settings', $this->textdomain() ),
                    'link'  => $this->admin_url( [ 'subpage' => 'settings' ] ),
                ],
                [
                    'title' => __( 'Logs', $this->textdomain() ),
                    'link'  => $this->admin_url( [ 'subpage' => 'logs' ] ),
                ],
            ];
?>
<?php /* code disabled
<link rel="stylesheet" href="<?=plugins_url( 'your-plugin.min.css', FOFLOG_FILE_PATH );?>" />
*/ ?>
<style>
.foflog-acf-admin-toolbar{background:#fff;border-bottom:1px solid #ccd0d4}.foflog-acf-admin-toolbar h2{font-size:14px;line-height:2.57143;display:inline-block;padding:5px 0;margin:0 10px 0 0}.foflog-acf-admin-toolbar h2 i{vertical-align:middle;color:#babbbc}.foflog-acf-admin-toolbar .foflog-acf-tab{display:inline-block;font-size:14px;line-height:2.57143;padding:5px;margin:0 5px;text-decoration:none;color:inherit}.foflog-acf-admin-toolbar .foflog-acf-tab.is-active{border-bottom:#0071a4 solid 3px;padding-bottom:2px}.foflog-acf-admin-toolbar .foflog-acf-tab:hover{color:#00a0d2}.foflog-acf-admin-toolbar .foflog-acf-tab:focus{box-shadow:none}#wpcontent .foflog-acf-admin-toolbar{margin-left:-20px;padding-left:20px}@media screen and (max-width:600px){.foflog-acf-admin-toolbar{display:none}}
</style>
<div class="foflog-acf-admin-toolbar">
    <h2><i class="foflog-acf-tab-icon dashicons dashicons-dashboard"></i> <?php _e( '404 Log', $this->textdomain() ); ?></h2>
<?php
            foreach ( $subpages as $subpage )
            {
                $is_active = strpos( $subpage['link'], $_SERVER['REQUEST_URI'] ) !== false ? 'is-active' : '';
?>
    <a class="foflog-acf-tab <?=$is_active;?>" href="<?=$subpage['link'];?>"><?=$subpage['title'];?></a>
<?php
            }
?>
</div>
<?php
        }

        /**
         * Pages.
         */

        public function page_controller()
        {
            $this->page_header();

            $subpage = $_GET['subpage'] ?? 'settings';

            if ( $subpage == 'settings' )
                $this->page_settings();
            if ( $subpage == 'logs' )
                $this->page_logs();

            $this->page_footer();
        }

        private function page_settings()
        {
?>
<h1><?php _e( 'Settings', $this->textdomain() ); ?></h1>

<p>¯\_(ツ)_/¯</p>
<?php
        }

        private function page_logs()
        {
?>
<h1><?php _e( 'Logs', $this->textdomain() ); ?></h1>

<div style="display: grid; grid-template-columns: auto auto;">
    <div>
        <h2><?php _e( 'File logs', $this->textdomain() ); ?></h2>
        <?php $this->display_log( $this->get_logs_from_fs() ); ?>
    </div>
    <div>
        <h2><?php _e( 'DB logs', $this->textdomain() ); ?></h2>
        <?php $this->display_log( $this->get_logs_from_db() ); ?>
    </div>
</div>
<?php
        }

        /**
         * Hooks.
         */

        public function hook_register_settings_page()
        {
            add_management_page(
                __( '404 log', $this->textdomain() ), // page title
                __( '404 log', $this->textdomain() ), // menu title
                'manage_options', // capability
                'foflog', // menu slug
                [ $this, 'page_controller' ], // function
                null // position
            );
        }

        public function hook_register_subpage_nav( $screen )
        {
            if ( strpos( $screen->id, 'tools_page_foflog' ) !== false )
                add_action( 'in_admin_header', [ $this, 'subpage_nav' ] );
        }

        public function hook_register_settings_link( $links )
        {
            $links['settings'] = '<a href="'.$this->admin_url().'">'.__( 'Settings', $this->textdomain() ).'</a>';

            return $links;
        }

        public function hook_create_db_table()
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

        public function hook_empty_db_table()
        {
            global $wpdb;

            $table = $wpdb->prefix.'foflog_entries';

            $wpdb->query( "TRUNCATE TABLE {$table}" );
        }

        public function hook_delete_db_table()
        {
            global $wpdb;

            $table = $wpdb->prefix.'foflog_entries';

            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }

        public function hook_delete_fs_logs()
        {
            $log_files = glob( FOFLOG_LOG_DIR.'*.log' );

            foreach ( $log_files as $log_file )
                unlink( $log_file );
        }

        public function hook_log_404_visits()
        {
            if ( !is_404() )
                return;

            $data = [
                'timestamp' => time(),
                'url'       => $_SERVER['REQUEST_URI'],
            ];

            if ( $this->use_fs )
                $this->set_404_visit_in_fs( $data );

            if ( $this->use_db )
                $this->set_404_visit_in_db( $data );
        }

        /**
         * Register Hooks.
         */

        public function register_hooks()
        {
            // activation
            register_activation_hook( FOFLOG_FILE_PATH, [ $this, 'hook_create_db_table' ] );
            // deactivation
            register_deactivation_hook( FOFLOG_FILE_PATH, [ $this, 'hook_empty_db_table' ] );
            register_deactivation_hook( FOFLOG_FILE_PATH, [ $this, 'hook_delete_fs_logs' ] );
            // uninstall
            // register_uninstall_hook( FOFLOG_FILE_PATH, [ $this, 'hook_delete_db_table' ] );
            // register_uninstall_hook( FOFLOG_FILE_PATH, 'hook_delete_db_table' );
            // register_uninstall_hook( FOFLOG_FILE_PATH, 'hook_delete_fs_logs' );

            // register settings page
            add_action( 'admin_menu', [ $this, 'hook_register_settings_page' ] );
            // register subpage nav
            add_action( 'current_screen', [ $this, 'hook_register_subpage_nav' ] );
            // register settings link
            add_filter( 'plugin_action_links_'.FOFLOG_DIR.'/'.FOFLOG_FILE, [ $this, 'hook_register_settings_link' ] );

            // log 404 visits
            add_action( 'template_redirect', [ $this, 'hook_log_404_visits' ] );
        }
    }

    /**
     * Instantiate.
     */

    $foflog = new FOFLog();
    $foflog->register_hooks();
}


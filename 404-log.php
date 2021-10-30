<?php

/**
 * Plugin Name: 404 Log
 * Description: What do I do?
 * Version: 0.2.0
 * Plugin URI: https://timbr.dev/
 * Author: Tim Brugman
 * Author URI: https://timbr.dev/
 * Text Domain: foflog
 */

if ( !defined( 'ABSPATH' ) )
    exit;

define( 'FOFLOG_FILE_PATH', __FILE__ );
define( 'FOFLOG_FILE', basename( __FILE__ ) );
define( 'FOFLOG_DIR', basename( __DIR__ ) );

define( 'FOFLOG_USE_FS', true );
define( 'FOFLOG_USE_DB', true );

define( 'FOFLOG_LOG_DIR', __DIR__.'/logs/' );

if ( !class_exists( 'FOFLog' ) )
{
    class FOFLog
    {
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
            $args['page'] = 'foflog-main';

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

            $log_entries = [];

            $lines = explode( "\n", trim( $log_lines ) );
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

        /**
         * Nav.
         */

        public function subpage_nav()
        {
            $subpages = [
                [
                    'title' => __( 'Red', $this->textdomain() ),
                    'link'  => $this->admin_url( [ 'subpage' => 'red' ] ),
                ],
                [
                    'title' => __( 'Green', $this->textdomain() ),
                    'link'  => $this->admin_url( [ 'subpage' => 'green' ] ),
                ],
            ];
?>
<?php /* code disabled
<link rel="stylesheet" href="<?=plugins_url( 'your-plugin.min.css', KONMARI_FILE_PATH );?>" />
*/ ?>
<style>
.konmari-acf-admin-toolbar{background:#fff;border-bottom:1px solid #ccd0d4}.konmari-acf-admin-toolbar h2{font-size:14px;line-height:2.57143;display:inline-block;padding:5px 0;margin:0 10px 0 0}.konmari-acf-admin-toolbar h2 i{vertical-align:middle;color:#babbbc}.konmari-acf-admin-toolbar .konmari-acf-tab{display:inline-block;font-size:14px;line-height:2.57143;padding:5px;margin:0 5px;text-decoration:none;color:inherit}.konmari-acf-admin-toolbar .konmari-acf-tab.is-active{border-bottom:#0071a4 solid 3px;padding-bottom:2px}.konmari-acf-admin-toolbar .konmari-acf-tab:hover{color:#00a0d2}.konmari-acf-admin-toolbar .konmari-acf-tab:focus{box-shadow:none}#wpcontent .konmari-acf-admin-toolbar{margin-left:-20px;padding-left:20px}@media screen and (max-width:600px){.konmari-acf-admin-toolbar{display:none}}
</style>
<div class="konmari-acf-admin-toolbar">
    <h2><i class="konmari-acf-tab-icon dashicons dashicons-dashboard"></i> KonMari Dashboard</h2>
<?php
            foreach ( $subpages as $subpage )
            {
                $is_active = strpos( $subpage['link'], $_SERVER['REQUEST_URI'] ) !== false ? 'is-active' : '';
?>
    <a class="konmari-acf-tab <?=$is_active;?>" href="<?=$subpage['link'];?>"><?=$subpage['title'];?></a>
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
            $this->page_main();
        }

        private function page_main()
        {
            $this->page_header();
?>
<h1><?php _e( '404 log', $this->textdomain() ); ?></h1>

<div style="display: grid; grid-template-columns: auto auto;">
    <div>
        <h2>File logs</h2>
        <?php $this->display_log( $this->get_logs_from_fs() ); ?>
    </div>
    <div>
        <h2>DB logs</h2>
        <?php $this->display_log( $this->get_logs_from_db() ); ?>
    </div>
</div>
<?php
            $this->page_footer();
        }

        /**
         * Hooks.
         */

        public function hook_register_settings_page()
        {
            add_management_page(
                '404 log', // page title
                '404 log', // menu title
                'manage_options', // capability
                'foflog-main', // menu slug
                [ $this, 'page_controller' ], // function
                null // position
            );
        }

        public function hook_register_subpage_nav( $screen )
        {
            // $this->d( $screen->id );

            // if ( strpos( $screen->id, 'tools_page_foflog' ) !== false )
            //     add_action( 'in_admin_header', [ $this, 'subpage_nav' ] );
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

        public function hook_log_404_visits()
        {
            if ( !is_404() )
                return;

            $data = [
                'timestamp' => time(),
                'url'       => $_SERVER['REQUEST_URI'],
            ];

            if ( FOFLOG_USE_FS )
                $this->set_404_visit_in_fs( $data );

            if ( FOFLOG_USE_DB )
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
            // uninstall
            register_uninstall_hook( FOFLOG_FILE_PATH, [ $this, 'hook_delete_db_table' ] );

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


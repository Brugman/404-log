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
        /**
         * ???.
         */

        private function create_settings()
        {
            if ( !get_option( 'foflog_settings' ) )
                add_option( 'foflog_settings', [] );
        }

        private function create_db_table()
        {
            global $wpdb;

            $table   = $wpdb->prefix.'foflog_entries';
            $charset = $wpdb->get_charset_collate();

            // if the table already exists, abort
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) == $table )
                return;

            $sql = "CREATE TABLE $table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                timestamp varchar(255) NOT NULL,
                url varchar(255) NOT NULL,
                PRIMARY KEY (id)
            ) $charset;";

            require_once( ABSPATH.'wp-admin/includes/upgrade.php' );

            dbDelta( $sql );
        }

        private function empty_db_table()
        {
            global $wpdb;

            $table = $wpdb->prefix.'foflog_entries';

            $wpdb->query( "TRUNCATE TABLE {$table}" );
        }

        private function delete_fs_logs()
        {
            $log_files = $this->get_fs_log_files();

            foreach ( $log_files as $log_file )
                unlink( $log_file );
        }

        private function delete_logs_except( $days )
        {
            if ( $this->get_setting_use_fs() )
                $this->delete_fs_logs_except( $days );

            if ( $this->get_setting_use_db() )
                $this->delete_db_logs_except( $days );
        }

        private function delete_fs_logs_except( $days )
        {
            $seconds = 60 * 60 * 24 * $days;
            // $seconds = $days;

            $boundary_timestamp = time() - $seconds;
            $boundary_string = wp_date( 'Y-m-d', $boundary_timestamp ).'.log';

            $log_files = $this->get_fs_log_files();

            foreach ( $log_files as $log_file )
            {
                if ( $boundary_string > basename( $log_file ) )
                {
                    // $this->d( basename( $log_file ).': deleting' );
                    unlink( $log_file );
                }
                else
                {
                    // $this->d( basename( $log_file ).': not deleting' );
                }
            }
        }

        private function delete_db_logs_except( $days )
        {
            $seconds = 60 * 60 * 24 * $days;
            // $seconds = $days;

            $boundary_timestamp = time() - $seconds;

            global $wpdb;

            $table = $wpdb->prefix.'foflog_entries';

            $wpdb->query( "DELETE FROM {$table} WHERE `timestamp` < '{$boundary_timestamp}'" );
        }

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

        private function perf_log( $data )
        {
            file_put_contents(
                __DIR__.'/perf.log',
                $data."\n",
                FILE_APPEND
            );
        }

        /**
         * HTML.
         */

        private function html_checkbox( $key = false, $value = false, $label = '', $info = false )
        {
            $info_html = ( !$info ? '' : '<span class="dashicons dashicons-info-outline" style="font-size: 1rem;" title="'.htmlentities( $info ).'"></span>' );
?>
    <div class="checkbox">
        <label for="label-<?=$key;?>" title="<?=$label;?>">
            <input type="checkbox" name="<?=$key;?>" id="label-<?=$key;?>" value="<?=$value;?>" <?=( !$value ?: 'checked' );?>>
            <?=$label;?>
        </label>
        <?=$info_html;?>
    </div>
<?php
        }

        /**
         * Getters.
         */

        private function get_settings()
        {
            return get_option( 'foflog_settings' );
        }

        private function get_setting_use_fs()
        {
            $settings = $this->get_settings();

            return $settings['use_fs'] ?? false;
        }

        private function get_setting_use_db()
        {
            $settings = $this->get_settings();

            return $settings['use_db'] ?? false;
        }

        private function get_fs_log_files()
        {
            return glob( FOFLOG_LOG_DIR.'*.log' );
        }

        private function get_logs_from_fs()
        {
            $log_lines = '';
            $log_files = $this->get_fs_log_files();

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

        private function set_settings( $settings = [] )
        {
            update_option( 'foflog_settings', $settings );
        }

        private function set_404_visit_in_fs( $data )
        {
            // code load timer start
            // $code_load_timer_start = microtime( true );

            $data = implode( ',', $data );

            file_put_contents(
                FOFLOG_LOG_DIR.wp_date('Y-m-d').'.log',
                $data."\n",
                FILE_APPEND
            );

            // code load timer finish
            // $code_load_timer_finish = microtime( true );
            // log
            // $this->perf_log( 'storing in fs took '.number_format( $code_load_timer_finish - $code_load_timer_start, 4 ) );
        }

        private function set_404_visit_in_db( $data )
        {
            // code load timer start
            // $code_load_timer_start = microtime( true );

            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix.'foflog_entries',
                $data
            );

            // code load timer finish
            // $code_load_timer_finish = microtime( true );
            // log
            // $this->perf_log( 'storing in db took '.number_format( $code_load_timer_finish - $code_load_timer_start, 4 ) );
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

        private function page_return()
        {
            $return_link = $_SERVER['HTTP_REFERER'] ?? $this->admin_url();

            echo '<p>'.__( 'Done!', $this->textdomain() ).'</p>';
            echo '<p><a href="'.$return_link.'">'.__( 'Return to settings', $this->textdomain() ).'</a>.</p>';
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
         * GET Actions.
         */

        /**
         * POST Save Changes.
         */

        private function post_save_settings()
        {
            if ( !isset( $_POST['save-settings'] ) )
                return;

            $settings = $this->get_settings();

            $settings['use_fs'] = (bool)isset( $_POST['use_fs'] );
            $settings['use_db'] = (bool)isset( $_POST['use_db'] );

            $this->set_settings( $settings );
        }

        /**
         * Pages.
         */

        public function page_controller()
        {
            $this->page_header();

            $action  = $_GET['action'] ?? false;
            $subpage = $_GET['subpage'] ?? 'settings';

            if ( $action )
            {
                switch ( $action )
                {
                    case 'clear_all_logs':
                        $this->delete_fs_logs();
                        $this->empty_db_table();
                        break;
                    case 'clear_fs_logs':
                        $this->delete_fs_logs();
                        break;
                    case 'clear_db_logs':
                        $this->empty_db_table();
                        break;
                }

                $this->page_return();
            }
            else
            {
                switch ( $subpage )
                {
                    case 'settings':
                        $this->page_settings();
                        break;
                    case 'logs':
                        $this->page_logs();
                        break;
                }
            }

            $this->page_footer();
        }

        private function page_settings()
        {
            $this->post_save_settings();
?>
<h1><?php _e( 'Settings', $this->textdomain() ); ?></h1>

<form method="post">

<?php
            $this->html_checkbox(
                'use_fs',
                $this->get_setting_use_fs(),
                'Save 404 hits in files.',
                'Info.'
            );

            $this->html_checkbox(
                'use_db',
                $this->get_setting_use_db(),
                'Save 404 hits in the database.',
                'Info.'
            );
?>

    <p><button type="submit" class="button button-primary" name="save-settings" value="1">Save Changes</button></p>

</form>

<?php if ( isset( $_POST['save-settings'] ) ): ?>
<p class="save-settings-feedback">Settings saved.</p>
<script>
(function($) {
    $('.save-settings-feedback').delay(3000).fadeOut();
})( jQuery );
</script>
<?php endif; // isset save-settings ?>

<h2>Clear logs</h2>
<p><a href="<?=$this->admin_url( [ 'subpage' => 'settings', 'action' => 'clear_all_logs' ] );?>" class="button">Clear all logs</a></p>
<p><a href="<?=$this->admin_url( [ 'subpage' => 'settings', 'action' => 'clear_fs_logs' ] );?>" class="button">Clear FS logs</a></p>
<p><a href="<?=$this->admin_url( [ 'subpage' => 'settings', 'action' => 'clear_db_logs' ] );?>" class="button">Clear DB logs</a></p>
<?php
        }

        private function page_logs()
        {
?>
<h1><?php _e( 'Logs', $this->textdomain() ); ?></h1>

<div style="display: grid; grid-template-columns: auto auto;">
    <div>
        <h2><?php _e( 'FS logs', $this->textdomain() ); ?></h2>
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

        public function hook_activation()
        {
            $this->create_settings();
            $this->create_db_table();
        }

        public function hook_deactivation()
        {
            $this->cron_1_unschedule_task();

            // Deactivation should not change the state of the plugin.
            // $this->delete_fs_logs();
            // $this->empty_db_table();
        }

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

        public function hook_log_404_visits()
        {
            if ( !is_404() )
                return;

            $data = [
                'timestamp' => time(),
                'url'       => $_SERVER['REQUEST_URI'],
            ];

            if ( $this->get_setting_use_fs() )
                $this->set_404_visit_in_fs( $data );

            if ( $this->get_setting_use_db() )
                $this->set_404_visit_in_db( $data );
        }

        /**
         * Crons.
         */

        public function cron_1_task()
        {
            // $days = $this->get_setting_foo();
            $days = 1;

            if ( $days == 0 )
                return;

            $this->delete_logs_except( $days );
        }

        public function cron_1_schedule_task()
        {
            if ( !wp_next_scheduled( 'foflog_cron_1' ) )
                wp_schedule_event( time(), 'daily', 'foflog_cron_1' );
        }

        private function cron_1_unschedule_task()
        {
            $timestamp = wp_next_scheduled( 'foflog_cron_1' );
            wp_unschedule_event( $timestamp, 'foflog_cron_1' );
        }

        /**
         * Register Hooks.
         */

        public function register_hooks()
        {
            // activation
            register_activation_hook( FOFLOG_FILE_PATH, [ $this, 'hook_activation' ] );
            // deactivation
            register_deactivation_hook( FOFLOG_FILE_PATH, [ $this, 'hook_deactivation' ] );
            // uninstall
            // see uninstall.php

            // register settings page
            add_action( 'admin_menu', [ $this, 'hook_register_settings_page' ] );
            // register subpage nav
            add_action( 'current_screen', [ $this, 'hook_register_subpage_nav' ] );
            // register settings link
            add_filter( 'plugin_action_links_'.FOFLOG_DIR.'/'.FOFLOG_FILE, [ $this, 'hook_register_settings_link' ] );

            // cron
            add_action( 'foflog_cron_1', [ $this, 'cron_1_task' ] );
            add_action( 'wp', [ $this, 'cron_1_schedule_task' ] );

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


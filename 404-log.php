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
        // > Unsorted.

        private function create_settings()
        {
            if ( !get_option( 'foflog_settings' ) )
                add_option( 'foflog_settings', [], '', false );
        }

        private function create_tables()
        {
            global $wpdb;

            $table   = $wpdb->prefix.'foflog_urls';
            $charset = $wpdb->get_charset_collate();

            // if the table already exists, abort
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) == $table )
                return;

            $sql = "CREATE TABLE $table (
                url varchar(255) NOT NULL,
                hit_count bigint(20) unsigned NOT NULL,
                first_seen bigint(20) unsigned NOT NULL,
                last_seen bigint(20) unsigned NOT NULL,
                PRIMARY KEY  (url),
                KEY  last_seen (last_seen)
            ) $charset;";

            require_once( ABSPATH.'wp-admin/includes/upgrade.php' );

            dbDelta( $sql );
        }

        private function clear_url_stats()
        {
            global $wpdb;

            $table = $wpdb->prefix.'foflog_urls';

            $wpdb->query( "TRUNCATE TABLE {$table}" );
        }

        // private function delete_urls_not_seen_since( $days )
        // {
        //     $seconds = 60 * 60 * 24 * absint( $days );

        //     $boundary_timestamp = time() - $seconds;

        //     global $wpdb;

        //     $table = $wpdb->prefix.'foflog_urls';

        //     $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE `last_seen` < %d", $boundary_timestamp ) );
        // }

        // > Constructor.

        public function __construct()
        {
        }

        // > Debug.

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

        // > Helpers.

        private function textdomain()
        {
            return 'foflog';
        }

        private function plugin_admin_url( $args = [] )
        {
            $args['page'] = 'foflog';

            return admin_url( 'tools.php?'.http_build_query( $args ) );
        }

        // > HTML.

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

        // > Getters.

        private function get_settings()
        {
            return get_option( 'foflog_settings' );
        }

        private function get_url_stats()
        {
            global $wpdb;

            $table = $wpdb->prefix.'foflog_urls';

            return $wpdb->get_results(
                "SELECT `url`, `hit_count`, `first_seen`, `last_seen`
                FROM {$table}
                ORDER BY `hit_count` DESC, `last_seen` DESC",
                ARRAY_A
            );
        }

        // > Setters.

        private function set_settings( $settings = [] )
        {
            update_option( 'foflog_settings', $settings );
        }

        private function count_404_hit()
        {
            global $wpdb;

            $table = $wpdb->prefix.'foflog_urls';
            $url   = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
            $now   = time();

            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$table} (`url`, `hit_count`, `first_seen`, `last_seen`)
                VALUES (%s, 1, %d, %d)
                ON DUPLICATE KEY UPDATE
                    `hit_count` = `hit_count` + 1,
                    `last_seen` = VALUES(`last_seen`)",
                $url,
                $now,
                $now
            ) );
        }

        // > Page Helpers.

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
            $return_link = $_SERVER['HTTP_REFERER'] ?? $this->plugin_admin_url();

            echo '<p>'.__( 'Done!', $this->textdomain() ).'</p>';
            echo '<p><a href="'.$return_link.'">'.__( 'Return to settings', $this->textdomain() ).'</a>.</p>';
        }

        private function display_url_stats( $log_entries )
        {
?>
<?php if ( !empty( $log_entries ) ): ?>
<table class="wp-list-table widefat fixed striped" style="width: auto; margin-top: 10px;">
    <thead>
        <tr>
            <td><?php _e( 'Hits', $this->textdomain() ); ?></td>
            <td><?php _e( 'URL', $this->textdomain() ); ?></td>
            <td><?php _e( 'First seen', $this->textdomain() ); ?></td>
            <td><?php _e( 'Last seen', $this->textdomain() ); ?></td>
        </tr>
    </thead>
    <tbody>
<?php foreach ( $log_entries as $log_entry ): ?>
        <tr>
            <td><?=$log_entry['hit_count'];?></td>
            <td><?=esc_html( $log_entry['url'] );?></td>
            <td><?=wp_date( 'Y-m-d H:i', $log_entry['first_seen'] );?></td>
            <td><?=wp_date( 'Y-m-d H:i', $log_entry['last_seen'] );?></td>
        </tr>
<?php endforeach; // $log_entries ?>
    </tbody>
</table>
<?php else: // $log_entries is empty ?>
    <p><?php _e( 'No log entries found.', $this->textdomain() ); ?></p>
<?php endif; // $log_entries ?>
<?php
        }

        // > Nav.

        public function subpage_nav()
        {
            $subpages = [
                [
                    'title' => __( 'Settings', $this->textdomain() ),
                    'link'  => $this->plugin_admin_url( [ 'subpage' => 'settings' ] ),
                ],
                [
                    'title' => __( 'Logs', $this->textdomain() ),
                    'link'  => $this->plugin_admin_url( [ 'subpage' => 'logs' ] ),
                ],
            ];
?>
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

        // > GET Actions.

        // > POST Save Changes.

        private function post_save_settings()
        {
            if ( !isset( $_POST['save-settings'] ) )
                return;

            $settings = $this->get_settings();

            // $settings['use_db'] = (bool)isset( $_POST['use_db'] );

            $this->set_settings( $settings );
        }

        // > Pages.

        public function page_controller()
        {
            $this->page_header();

            $action  = $_GET['action'] ?? false;
            $subpage = $_GET['subpage'] ?? 'settings';

            if ( $action )
            {
                switch ( $action )
                {
                    case 'clear_url_stats':
                        $this->clear_url_stats();
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
            // $this->html_checkbox(
            //     'use_db',
            //     true,
            //     'Save 404 hits in the database.',
            //     'Info.'
            // );
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
<p><a href="<?=$this->plugin_admin_url( [ 'subpage' => 'settings', 'action' => 'clear_url_stats' ] );?>" class="button">Clear logs</a></p>
<?php
        }

        private function page_logs()
        {
?>
<h1><?php _e( 'Logs', $this->textdomain() ); ?></h1>

<?php $this->display_url_stats( $this->get_url_stats() ); ?>
<?php
        }

        // > Hooks.

        public function hook_activation()
        {
            $this->create_settings();
            $this->create_tables();
        }

        public function hook_deactivation()
        {
            // $this->cron_1_unschedule_task();

            // Deactivation should not change the state of the plugin.
            // $this->clear_url_stats();
        }

        public function hook_register_tools_page()
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
            $links['settings'] = '<a href="'.$this->plugin_admin_url().'">'.__( 'Settings', $this->textdomain() ).'</a>';

            return $links;
        }

        public function hook_maybe_count_hit()
        {
            if ( !is_404() )
                return;

            $this->count_404_hit();
        }

        // > Crons.

        // public function cron_1_task()
        // {
        //     // $days = $this->get_setting_foo();
        //     $days = 1;

        //     if ( $days == 0 )
        //         return;

        //     $this->delete_urls_not_seen_since( $days );
        // }

        // public function cron_1_schedule_task()
        // {
        //     if ( !wp_next_scheduled( 'foflog_cron_1' ) )
        //         wp_schedule_event( time(), 'daily', 'foflog_cron_1' );
        // }

        // private function cron_1_unschedule_task()
        // {
        //     $timestamp = wp_next_scheduled( 'foflog_cron_1' );
        //     wp_unschedule_event( $timestamp, 'foflog_cron_1' );
        // }

        // > Register Hooks.

        public function register_hooks()
        {
            // activation
            register_activation_hook( FOFLOG_FILE_PATH, [ $this, 'hook_activation' ] );
            // deactivation
            register_deactivation_hook( FOFLOG_FILE_PATH, [ $this, 'hook_deactivation' ] );
            // uninstall
            // see uninstall.php

            // register tools page
            add_action( 'admin_menu', [ $this, 'hook_register_tools_page' ] );
            // register subpage nav
            add_action( 'current_screen', [ $this, 'hook_register_subpage_nav' ] );
            // register settings link
            add_filter( 'plugin_action_links_'.FOFLOG_DIR.'/'.FOFLOG_FILE, [ $this, 'hook_register_settings_link' ] );

            // cron
            // add_action( 'foflog_cron_1', [ $this, 'cron_1_task' ] );
            // add_action( 'wp', [ $this, 'cron_1_schedule_task' ] );

            // maybe log hit
            add_action( 'template_redirect', [ $this, 'hook_maybe_count_hit' ] );
        }
    }

    // > Instantiate.

    $foflog = new FOFLog();
    $foflog->register_hooks();
}


<?php

if ( !defined( 'ABSPATH' ) )
    exit;

if ( !class_exists( 'WP_List_Table' ) )
    require_once( ABSPATH.'wp-admin/includes/class-wp-list-table.php' );

if ( !class_exists( 'FOFLog_Log_Table' ) )
{
    class FOFLog_Log_Table extends WP_List_Table
    {
        private $per_page = 20;
        private $textdomain = 'foflog';

        // > Init.

        public function __construct()
        {
            parent::__construct( [
                'singular' => 'foflog-log-entry',
                'plural'   => 'foflog-log-entries',
                'ajax'     => false,
            ] );
        }

        // > Queries.

        public function prepare_items()
        {
            $this->process_actions();

            $search  = trim( wp_unslash( $_GET['s'] ?? '' ) );
            $order   = ( strtolower( $_GET['order'] ?? '' ) === 'asc' ? 'ASC' : 'DESC' );
            $paged   = max( 1, absint( $_GET['paged'] ?? 1 ) );

            $allowed_orderby = [
                'hit_count'  => 'hit_count',
                'url'        => 'url',
                'first_seen' => 'first_seen',
                'last_seen'  => 'last_seen',
            ];

            $orderby = $allowed_orderby[ $_GET['orderby'] ?? '' ] ?? 'hit_count';

            global $wpdb;

            $table = FOFLog::table_name();

            $sql_where  = '';
            $sql_params = [];

            if ( $search !== '' )
            {
                $sql_where    = ' WHERE `url` LIKE %s';
                $sql_params[] = '%'.$wpdb->esc_like( $search ).'%';
            }

            $sql_count = "SELECT COUNT(*) FROM {$table}{$sql_where}";

            $total = (int) $wpdb->get_var(
                ( $sql_params ? $wpdb->prepare( $sql_count, $sql_params ) : $sql_count )
            );

            // Clamp the page to the available pages, so an emptied last page
            // (or a hand-edited URL) doesn't show an empty view.
            $paged = min( $paged, max( 1, (int) ceil( $total / $this->per_page ) ) );

            $offset = ( $paged - 1 ) * $this->per_page;

            $this->items = $wpdb->get_results( $wpdb->prepare(
                "SELECT `id`, `url`, `hit_count`, `first_seen`, `last_seen`
                FROM {$table}{$sql_where}
                ORDER BY `{$orderby}` {$order}
                LIMIT %d OFFSET %d",
                array_merge( $sql_params, [ $this->per_page, $offset ] )
            ), ARRAY_A );

            // WP 7.1 no longer falls back to get_columns() in get_column_info(),
            // so the headers must be provided explicitly.
            $this->_column_headers = [
                $this->get_columns(),
                [],
                $this->get_sortable_columns(),
                'url', // primary column
            ];

            $this->set_pagination_args( [
                'total_items' => $total,
                'per_page'    => $this->per_page,
                'total_pages' => (int) ceil( $total / $this->per_page ),
            ] );
        }

        // > Actions.

        public function process_actions()
        {
            $action = $this->current_action();

            if ( !$action )
                return;

            // Nonce is emitted by WP_List_Table::display_tablenav() with
            // action 'bulk-' . $this->_args['plural']. It covers the bulk
            // form and the per-row links, which target the same admin user.
            check_admin_referer( 'bulk-foflog-log-entries' );

            if ( !current_user_can( 'manage_options' ) )
                return;

            switch ( $action )
            {
                case 'delete':
                    $this->bulk_delete_urls();
                    break;
                case 'delete_row':
                    $this->delete_url();
                    break;
            }
        }

        private function bulk_delete_urls()
        {
            $ids = array_filter( array_map( 'absint', (array) ( $_GET[ $this->_args['plural'] ] ?? [] ) ) );

            if ( empty( $ids ) )
                return;

            global $wpdb;

            $table        = FOFLog::table_name();
            $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table} WHERE `id` IN ( {$placeholders} )",
                array_values( $ids )
            ) );
        }

        private function delete_url()
        {
            $id = absint( $_GET['foflog_url_id'] ?? 0 );

            if ( $id === 0 )
                return;

            global $wpdb;

            $table = FOFLog::table_name();

            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table} WHERE `id` = %d",
                $id
            ) );
        }

        // > Columns.

        public function get_columns()
        {
            return [
                'cb'         => '<input type="checkbox" />',
                'delete'     => '',
                'hit_count'  => __( 'Hits', $this->textdomain ),
                'url'        => __( 'URL', $this->textdomain ),
                'first_seen' => __( 'First seen', $this->textdomain ),
                'last_seen'  => __( 'Last seen', $this->textdomain ),
            ];
        }

        protected function get_sortable_columns()
        {
            return [
                'hit_count'  => [ 'hit_count', true ],
                'url'        => [ 'url', false ],
                'first_seen' => [ 'first_seen', false ],
                'last_seen'  => [ 'last_seen', true ],
            ];
        }

        protected function get_bulk_actions()
        {
            return [
                'delete' => __( 'Delete', $this->textdomain ),
            ];
        }

        public function column_cb( $item )
        {
            return '<input type="checkbox" name="'.$this->_args['plural'].'[]" value="'.absint( $item['id'] ).'" />';
        }

        public function column_delete( $item )
        {
            $url = add_query_arg( [
                'action'        => 'delete_row',
                'foflog_url_id' => absint( $item['id'] ),
                '_wpnonce'      => wp_create_nonce( 'bulk-foflog-log-entries' ),
            ] );

            return '<a href="'.esc_url( $url ).'" title="'.esc_attr__( 'Delete', $this->textdomain ).'" aria-label="'.esc_attr__( 'Delete', $this->textdomain ).'">'
                .'<span class="dashicons dashicons-trash"></span>'
                .'</a>';
        }

        public function column_hit_count( $item )
        {
            return number_format_i18n( $item['hit_count'] );
        }

        public function column_url( $item )
        {
            return esc_html( $item['url'] );
        }

        public function column_default( $item, $column_name )
        {
            switch ( $column_name )
            {
                case 'first_seen':
                case 'last_seen':
                    return wp_date( 'Y-m-d H:i', $item[ $column_name ] );
                default:
                    return '';
            }
        }

        public function no_items()
        {
            _e( 'No log entries found.', $this->textdomain );
        }
    }
}

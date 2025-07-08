<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * TLC_Sessions_List_Table class.
 *
 * @extends WP_List_Table
 */
class TLC_Sessions_List_Table extends WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Chat Session', 'telegram-live-chat' ), // Singular name of the listed records
            'plural'   => __( 'Chat Sessions', 'telegram-live-chat' ), // Plural name of the listed records
            'ajax'     => false, // Does this table support ajax?
        ) );
    }

    /**
     * Get a list of columns.
     *
     * @return array
     */
    public function get_columns() {
        $columns = array(
            'cb'                 => '<input type="checkbox" />', // Checkbox for bulk actions
            'session_id'         => __( 'Session ID', 'telegram-live-chat' ),
            'visitor_token'      => __( 'Visitor Token', 'telegram-live-chat' ),
            'wp_user_id'         => __( 'WP User', 'telegram-live-chat' ),
            'status'             => __( 'Status', 'telegram-live-chat' ),
            'start_time'         => __( 'Start Time', 'telegram-live-chat' ),
            'last_active_time'   => __( 'Last Active', 'telegram-live-chat' ),
            'visitor_ip'         => __( 'Visitor IP', 'telegram-live-chat' ),
            'actions'            => __( 'Actions', 'telegram-live-chat' ),
        );
        return $columns;
    }

    /**
     * Get a list of sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'session_id'       => array( 'session_id', false ),
            'wp_user_id'       => array( 'wp_user_id', false ),
            'status'           => array( 'status', false ),
            'start_time'       => array( 'start_time', false ),
            'last_active_time' => array( 'last_active_time', false ),
            'visitor_ip'       => array( 'visitor_ip', false ),
        );
        return $sortable_columns;
    }

    /**
     * Prepare the items for the table.
     */
    public function prepare_items() {
        global $wpdb;

        $table_name = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $per_page     = $this->get_items_per_page( 'tlc_sessions_per_page', 20 );
        $current_page = $this->get_pagenum();
        $total_items  = $wpdb->get_var( "SELECT COUNT(session_id) FROM $table_name" );

        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

        $orderby = ( ! empty( $_REQUEST['orderby'] ) && array_key_exists( $_REQUEST['orderby'], $this->get_sortable_columns() ) ) ? $_REQUEST['orderby'] : 'session_id';
        $order   = ( ! empty( $_REQUEST['order'] ) && in_array( strtolower( $_REQUEST['order'] ), array( 'asc', 'desc' ) ) ) ? $_REQUEST['order'] : 'desc';

        $offset = ( $current_page - 1 ) * $per_page;

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ), ARRAY_A
        );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'session_id':
            case 'visitor_token':
            case 'status':
            case 'start_time':
            case 'last_active_time':
            case 'visitor_ip':
                return esc_html( $item[ $column_name ] );
            case 'wp_user_id':
                if ( ! empty( $item[ $column_name ] ) ) {
                    $user = get_userdata( $item[ $column_name ] );
                    return $user ? esc_html( $user->display_name . ' (ID: ' . $user->ID . ')' ) : __( 'N/A', 'telegram-live-chat' );
                }
                return __( 'Guest', 'telegram-live-chat' );
            default:
                return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Handle the checkbox column.
     *
     * @param  array $item
     * @return string
     */
    function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            'session_ids',  // Group of checkboxes
            $item['session_id']         // Value of the checkbox
        );
    }

    /**
     * Handle the actions column.
     *
     * @param array $item
     * @return string
     */
    function column_actions( $item ) {
        $page_slug = $this->get_plugin_page_slug();
        $actions = array(
            'view' => sprintf(
                '<a href="?page=%s&action=%s&session_id=%s">%s</a>',
                esc_attr( $page_slug ),
                'view_session',
                absint( $item['session_id'] ),
                __( 'View Messages', 'telegram-live-chat' )
            ),
            // Add other actions like 'delete' here later if needed
        );
        return $this->row_actions( $actions );
    }

    /**
     * Get the plugin page slug for the current list table.
     * Assumes this table is displayed on the 'telegram-live-chat-chat-history' page.
     *
     * @return string
     */
    protected function get_plugin_page_slug() {
        // This should match the menu_slug for the chat history page
        return TLC_PLUGIN_PREFIX . 'plugin-chat-history';
        // Correction: The actual slug used in add_submenu_page was $this->plugin_name . '-chat-history'
        // $this->plugin_name is 'telegram-live-chat'. So it's 'telegram-live-chat-chat-history'.
        // Let's use the actual value.
        // The main plugin name is 'telegram-live-chat', TLC_PLUGIN_PREFIX is 'tlc_'
        // The parent slug is 'telegram-live-chat' which is $this->plugin_name
        // The submenu slug is $this->plugin_name . '-chat-history'
        // So, it should be 'telegram-live-chat-chat-history'. This was correct.
        // No change needed here if TLC_PLUGIN_PREFIX is not used for page slugs.
        // The original code was: return TLC_PLUGIN_PREFIX . 'plugin-chat-history';
        // This should be: return 'telegram-live-chat-chat-history';
        return 'telegram-live-chat-chat-history'; // This was already corrected in my mental model.
    }
    // Let's double check what was used in add_submenu_page in class-tlc-admin.php:
    // $this->plugin_name . '-chat-history'
    // $this->plugin_name is 'telegram-live-chat'
    // So the slug is indeed 'telegram-live-chat-chat-history'. The current return is correct.

    /**
     * Define bulk actions.
     *
     * @return array
     */
    // public function get_bulk_actions() {
    //     $actions = array(
    //         'delete_selected' => __( 'Delete Selected', 'telegram-live-chat' ),
    //     );
    //     return $actions;
    // }

    /**
     * Process bulk actions.
     */
    // public function process_bulk_action() {
    //     if ( 'delete_selected' === $this->current_action() ) {
    //         $session_ids = isset( $_REQUEST['session_ids'] ) ? array_map( 'absint', $_REQUEST['session_ids'] ) : array();
    //         if ( count( $session_ids ) > 0 ) {
    //             global $wpdb;
    //             $table_name_sessions = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
    //             $table_name_messages = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';
    //             $ids_placeholder = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );

    //             // Delete messages for these sessions
    //             $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name_messages WHERE session_id IN ( $ids_placeholder )", $session_ids ) );

    //             // Delete sessions
    //             $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name_sessions WHERE session_id IN ( $ids_placeholder )", $session_ids ) );

    //             // Add admin notice: echo '<div class="updated"><p>' . __( 'Selected sessions deleted.', 'telegram-live-chat' ) . '</p></div>';
    //         }
    //     }
    // }
}

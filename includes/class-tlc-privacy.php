<?php
/**
 * TLC Privacy
 *
 * Handles GDPR data export and erasure.
 *
 * @since      0.10.0
 * @package    TLC
 * @subpackage TLC/includes
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class TLC_Privacy {

    /**
     * Constructor.
     */
    public function __construct() {
        // Hooks are registered in the main plugin class or directly if this class is self-initializing
    }

    /**
     * Registers the exporter for personal data.
     *
     * @param array $exporters An array of exporter callbacks.
     * @return array An array of exporter callbacks.
     */
    public static function register_exporter( $exporters ) {
        $exporters[ TLC_PLUGIN_PREFIX . 'chat-data' ] = array(
            'exporter_friendly_name' => __( 'Telegram Live Chat Data', 'telegram-live-chat' ),
            'callback'               => array( __CLASS__, 'export_user_data' ),
        );
        return $exporters;
    }

    /**
     * Finds and exports personal data for a given email address.
     *
     * @param string $email_address The email address to search for.
     * @param int    $page  The page number for pagination.
     * @return array An array of personal data.
     */
    public static function export_user_data( $email_address, $page = 1 ) {
        global $wpdb;
        $export_items = array();
        $items_per_page = 10; // Number of sessions to process per page for exporter
        $offset = ( $page - 1 ) * $items_per_page;

        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

        // Check if email matches a WP user
        $user = get_user_by( 'email', $email_address );
        $user_id_clause = "";
        $params = array( $email_address );

        if ( $user ) {
            $user_id_clause = " OR s.wp_user_id = %d";
            $params[] = $user->ID;
        }

        $query = $wpdb->prepare(
            "SELECT s.* FROM $sessions_table s
             WHERE s.visitor_email = %s $user_id_clause
             ORDER BY s.session_id ASC
             LIMIT %d OFFSET %d",
            array_merge($params, array($items_per_page, $offset))
        );
        $sessions = $wpdb->get_results( $query );

        if ( $sessions ) {
            foreach ( $sessions as $session ) {
                $session_data_to_export = array();
                $session_data_to_export[] = array('name' => __('Chat Session ID', 'telegram-live-chat'), 'value' => $session->session_id);
                if ($session->visitor_name) $session_data_to_export[] = array('name' => __('Visitor Name (from chat)', 'telegram-live-chat'), 'value' => $session->visitor_name);
                if ($session->visitor_email) $session_data_to_export[] = array('name' => __('Visitor Email (from chat)', 'telegram-live-chat'), 'value' => $session->visitor_email);
                $session_data_to_export[] = array('name' => __('Session Start Time', 'telegram-live-chat'), 'value' => $session->start_time);
                $session_data_to_export[] = array('name' => __('Session Last Active', 'telegram-live-chat'), 'value' => $session->last_active_time);
                $session_data_to_export[] = array('name' => __('Visitor IP', 'telegram-live-chat'), 'value' => $session->visitor_ip);
                $session_data_to_export[] = array('name' => __('Visitor User Agent', 'telegram-live-chat'), 'value' => $session->visitor_user_agent);
                if ($session->initial_page_url) $session_data_to_export[] = array('name' => __('Initial Page URL', 'telegram-live-chat'), 'value' => $session->initial_page_url);
                if ($session->referer) $session_data_to_export[] = array('name' => __('Referer', 'telegram-live-chat'), 'value' => $session->referer);
                if ($session->utm_source) $session_data_to_export[] = array('name' => __('UTM Source', 'telegram-live-chat'), 'value' => $session->utm_source);
                if ($session->utm_medium) $session_data_to_export[] = array('name' => __('UTM Medium', 'telegram-live-chat'), 'value' => $session->utm_medium);
                if ($session->utm_campaign) $session_data_to_export[] = array('name' => __('UTM Campaign', 'telegram-live-chat'), 'value' => $session->utm_campaign);
                if ($session->rating) $session_data_to_export[] = array('name' => __('Session Rating', 'telegram-live-chat'), 'value' => $session->rating . '/5');
                if ($session->rating_comment) $session_data_to_export[] = array('name' => __('Session Rating Comment', 'telegram-live-chat'), 'value' => $session->rating_comment);


                $export_items[] = array(
                    'group_id'    => TLC_PLUGIN_PREFIX . 'chat_session',
                    'group_label' => __( 'Chat Session Details', 'telegram-live-chat' ),
                    'item_id'     => TLC_PLUGIN_PREFIX . "session-{$session->session_id}",
                    'data'        => $session_data_to_export,
                );

                // Get messages for this session
                $messages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $messages_table WHERE session_id = %d ORDER BY timestamp ASC", $session->session_id ) );
                if ( $messages ) {
                    $message_group_data = array();
                    foreach ( $messages as $message ) {
                        $sender = '';
                        if ($message->sender_type === 'visitor') $sender = __('Visitor', 'telegram-live-chat');
                        elseif ($message->sender_type === 'agent') {
                            $sender = $message->agent_wp_user_id ? sprintf(__('Agent (WP ID: %d)', 'telegram-live-chat'), $message->agent_wp_user_id) : sprintf(__('Agent (TG ID: %s)', 'telegram-live-chat'), $message->telegram_user_id);
                        }
                        else $sender = __('System', 'telegram-live-chat');

                        $message_item_data = array(
                            array( 'name' => __('Timestamp', 'telegram-live-chat'), 'value' => $message->timestamp ),
                            array( 'name' => __('Sender', 'telegram-live-chat'), 'value' => $sender ),
                            array( 'name' => __('Message', 'telegram-live-chat'), 'value' => $message->message_content ),
                        );
                        if ($message->page_url) $message_item_data[] = array('name' => __('Message Page URL', 'telegram-live-chat'), 'value' => $message->page_url);

                        // Add each message as an item within a subgroup for that session's messages
                        $message_group_data[] = array(
                            'group_id'    => TLC_PLUGIN_PREFIX . 'chat_messages_session_' . $session->session_id,
                            'group_label' => sprintf(__( 'Messages for Session %d', 'telegram-live-chat' ), $session->session_id),
                            'item_id'     => TLC_PLUGIN_PREFIX . "message-{$message->message_id}",
                            'data'        => $message_item_data,
                        );
                    }
                     // Add all messages for this session as a single export item with multiple sub-items
                    if (!empty($message_group_data)) {
                         $export_items = array_merge($export_items, $message_group_data);
                    }
                }
            }
        }

        // Determine if there are more items to export for pagination
        $done = count( $sessions ) < $items_per_page;

        return array(
            'data' => $export_items,
            'done' => $done,
        );
    }

    /**
     * Registers the eraser for personal data.
     *
     * @param array $erasers An array of eraser callbacks.
     * @return array An array of eraser callbacks.
     */
    public static function register_eraser( $erasers ) {
        $erasers[ TLC_PLUGIN_PREFIX . 'chat-data' ] = array(
            'eraser_friendly_name' => __( 'Telegram Live Chat Data', 'telegram-live-chat' ),
            'callback'             => array( __CLASS__, 'erase_user_data' ),
        );
        return $erasers;
    }

    /**
     * Finds and erases personal data for a given email address.
     *
     * @param string $email_address The email address to search for.
     * @param int    $page  The page number for pagination.
     * @return array An array of results.
     */
    public static function erase_user_data( $email_address, $page = 1 ) {
        global $wpdb;
        $items_removed = false;
        $items_retained = false; // We are not retaining any data in an anonymized way for this plugin's scope
        $messages = array();

        if ( empty( $email_address ) ) {
            return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(__('Email address is required for data erasure.', 'telegram-live-chat')), 'done' => true );
        }

        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

        // Find sessions by email or by user ID if email matches a WP user
        $user = get_user_by( 'email', $email_address );
        $user_id_clause = "";
        $params = array( $email_address );

        if ( $user ) {
            $user_id_clause = " OR s.wp_user_id = %d";
            $params[] = $user->ID;
        }

        // We'll process in batches to avoid timeouts, though this basic version does one batch.
        // A real paginated eraser is more complex. For now, find all matching sessions.
        $session_ids_to_erase = $wpdb->get_col( $wpdb->prepare(
            "SELECT s.session_id FROM $sessions_table s
             WHERE s.visitor_email = %s $user_id_clause",
            $params
        ) );

        if ( ! empty( $session_ids_to_erase ) ) {
            $ids_placeholder = implode( ',', array_fill( 0, count( $session_ids_to_erase ), '%d' ) );

            // Delete messages for these sessions
            $messages_deleted_count = $wpdb->query( $wpdb->prepare( "DELETE FROM $messages_table WHERE session_id IN ( $ids_placeholder )", $session_ids_to_erase ) );

            // Delete sessions themselves
            $sessions_deleted_count = $wpdb->query( $wpdb->prepare( "DELETE FROM $sessions_table WHERE session_id IN ( $ids_placeholder )", $session_ids_to_erase ) );

            if ( $sessions_deleted_count !== false || $messages_deleted_count !== false ) { // Check if any deletion happened or was attempted
                $items_removed = true;
                // translators: %d is the number of chat sessions.
                $messages[] = sprintf( _n( '%d chat session and its messages were deleted.', '%d chat sessions and their messages were deleted.', $sessions_deleted_count, 'telegram-live-chat' ), $sessions_deleted_count );
            } else {
                $messages[] = __('No chat data found for this user to delete, or an error occurred.', 'telegram-live-chat');
            }
        } else {
            $messages[] = __( 'No chat data found associated with this email address.', 'telegram-live-chat' );
        }

        return array(
            'items_removed'  => $items_removed,
            'items_retained' => $items_retained,
            'messages'       => $messages,
            'done'           => true, // Assuming we process all found in one go for this basic version
        );
    }
}

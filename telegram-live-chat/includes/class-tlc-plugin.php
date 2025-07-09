<?php
/**
 * The file that defines the core plugin class
 * (Full content of TLC_Plugin class from previous step, with modifications below in handle_visitor_message)
 */
class TLC_Plugin {

    // ... (Properties: $loader, $plugin_name, $version - as before) ...
    // ... (__construct method - as before) ...
    // ... (load_dependencies method - as before) ...
    // ... (set_locale method - as before) ...
    // ... (define_admin_hooks method - as before) ...
    // ... (define_public_hooks method - as before) ...
    // ... (register_rest_routes method - as before) ...
    // ... (add_custom_cron_schedules, handle_polling_setting_change, schedule_or_unschedule_polling_cron, run_telegram_polling, process_telegram_update, handle_fetch_new_messages - as before) ...

    public function __construct() {
        if ( defined( 'TLC_VERSION' ) ) { $this->version = TLC_VERSION; } else { $this->version = '0.1.0'; }
        $this->plugin_name = 'telegram-live-chat';
        $this->load_dependencies(); $this->set_locale(); $this->define_admin_hooks(); $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-loader.php';
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-i18n.php';
        require_once TLC_PLUGIN_DIR . 'admin/class-tlc-admin.php';
        require_once TLC_PLUGIN_DIR . 'public/class-tlc-public.php';
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-telegram-bot-api.php';
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-encryption.php';
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-rest-api-controller.php';
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-privacy.php';
        $this->loader = new TLC_Loader();
    }

    private function set_locale() { /* ... */ }
    private function define_admin_hooks() { /* ... */ }
    private function define_public_hooks() { /* ... */ }
    public function register_rest_routes() { /* ... */ }
    public function add_custom_cron_schedules($schedules){ /* ... */ return $schedules; }
    public function handle_polling_setting_change(){ /* ... */ }
    public function schedule_or_unschedule_polling_cron(){ /* ... */ }
    public function run_telegram_polling(){ /* ... */ }
    public function process_telegram_update($update){ /* ... */ }
    public function handle_fetch_new_messages(){ /* ... */ }

    public function handle_visitor_message() {
        global $wpdb;

        // ... (Nonce check, visitor_token check, rate limiting logic - as before) ...
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'tlc_send_visitor_message_nonce' ) ) { wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'telegram-live-chat' ) ), 403 ); return; }
        $visitor_token = isset( $_POST['visitor_token'] ) ? sanitize_text_field( $_POST['visitor_token'] ) : '';
        if ( empty( $visitor_token ) ) { wp_send_json_error( array( 'message' => __( 'Visitor token is required.', 'telegram-live-chat' ) ), 400 ); return; }
        if ( get_option( TLC_PLUGIN_PREFIX . 'rate_limit_enable', true ) ) { /* ... rate limit logic ... */ }

        $message_text = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $current_page_url = isset( $_POST['current_page'] ) ? esc_url_raw( $_POST['current_page'] ) : '';
        $visitor_name_from_post = isset($_POST['visitor_name']) ? sanitize_text_field(wp_unslash($_POST['visitor_name'])) : null;
        $visitor_email_from_post = isset($_POST['visitor_email']) ? sanitize_email(wp_unslash($_POST['visitor_email'])) : null;

        if ( empty( $message_text ) ) { wp_send_json_error( array( 'message' => __( 'Message text cannot be empty.', 'telegram-live-chat' ) ), 400 ); return; }

        $visitor_ip = $this->get_visitor_ip();
        $visitor_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
        $wp_user_id = get_current_user_id();
        $woo_customer_id = null;

        if ( $wp_user_id > 0 && class_exists( 'WooCommerce' ) && get_option(TLC_PLUGIN_PREFIX . 'woo_enable_integration', true) ) {
            $customer = new WC_Customer( $wp_user_id );
            if ( $customer && $customer->get_id() > 0 ) { $woo_customer_id = $customer->get_id(); }
        }

        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $sessions_table WHERE visitor_token = %s", $visitor_token ) );
        $session_id = null; $is_new_session = false; $session_data_for_webhook = null;

        if ( $session ) {
            // ... (Update existing session logic as before, including woo_customer_id if empty) ...
            $session_id = $session->session_id;
            $update_data = array( 'last_active_time' => current_time( 'mysql' ) );
            if ($session->status === 'closed' || $session->status === 'archived' || $session->status === 'pending_agent') { $update_data['status'] = 'active'; }
            if ($visitor_name_from_post && (empty($session->visitor_name) || $session->visitor_name !== $visitor_name_from_post)) { $update_data['visitor_name'] = $visitor_name_from_post; }
            if ($visitor_email_from_post && (empty($session->visitor_email) || $session->visitor_email !== $visitor_email_from_post)) { $update_data['visitor_email'] = $visitor_email_from_post; }
            if ($woo_customer_id && empty($session->woo_customer_id)) { $update_data['woo_customer_id'] = $woo_customer_id; }
            $wpdb->update( $sessions_table, $update_data, array( 'session_id' => $session_id ) );
            $session_data_for_webhook = (array) $wpdb->get_row($wpdb->prepare("SELECT * FROM $sessions_table WHERE session_id = %d", $session_id), ARRAY_A);

        } else {
            // ... (Create new session logic as before, including woo_customer_id) ...
            $is_new_session = true;
            $session_data_for_insert = array( /* ... all fields including woo_customer_id ... */);
            // (Copy full $session_data_for_insert array from previous correct version)
             $session_data_for_insert = array(
                'visitor_token' => $visitor_token, 'wp_user_id' => $wp_user_id > 0 ? $wp_user_id : null,
                'start_time' => current_time( 'mysql' ), 'last_active_time' => current_time( 'mysql' ),
                'status' => 'pending_agent', 'visitor_ip' => $visitor_ip,
                'visitor_user_agent' => $visitor_user_agent, 'initial_page_url' => $current_page_url,
                'referer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : null,
                'visitor_name' => $visitor_name_from_post, 'visitor_email' => $visitor_email_from_post,
                'woo_customer_id' => $woo_customer_id,
            );
            $parsed_url_query = array(); $query_str = parse_url($current_page_url, PHP_URL_QUERY);
            if ($query_str) { parse_str($query_str, $parsed_url_query); }
            $session_data_for_insert['utm_source'] = isset($parsed_url_query['utm_source'])?sanitize_text_field($parsed_url_query['utm_source']):null;
            $session_data_for_insert['utm_medium'] = isset($parsed_url_query['utm_medium'])?sanitize_text_field($parsed_url_query['utm_medium']):null;
            $session_data_for_insert['utm_campaign'] = isset($parsed_url_query['utm_campaign'])?sanitize_text_field($parsed_url_query['utm_campaign']):null;
            $wpdb->insert( $sessions_table, $session_data_for_insert );
            $session_id = $wpdb->insert_id;
            $session_data_for_webhook = $session_data_for_insert; $session_data_for_webhook['session_id'] = $session_id;
        }

        if ( ! $session_id ) { /* ... error ... */ }
        $message_data_to_insert = array( /* ... */ ); // as before
        $message_inserted = $wpdb->insert( $messages_table, $message_data_to_insert);
        if ( ! $message_inserted ) { /* ... error ... */ }
        $db_message_id = $wpdb->insert_id;

        // Trigger Webhooks (as before)
        if ($is_new_session) { /* ... */ }
        $webhook_url_visitor_msg = get_option(TLC_PLUGIN_PREFIX . 'webhook_on_new_visitor_message_url', '');
        if (!empty($webhook_url_visitor_msg)) { /* ... */ }

        // --- Start: Add WooCommerce Order Info to Telegram Message ---
        $woo_orders_info_for_telegram = "";
        if ( class_exists( 'WooCommerce' ) &&
             get_option(TLC_PLUGIN_PREFIX . 'woo_enable_integration', true) &&
             get_option(TLC_PLUGIN_PREFIX . 'woo_orders_in_telegram', true) ) {

            $current_woo_customer_id = $wpdb->get_var($wpdb->prepare("SELECT woo_customer_id FROM $sessions_table WHERE session_id = %d", $session_id));
            if ( !$current_woo_customer_id && $visitor_email_from_post ) { // Try to find customer by email if not logged in / linked yet
                $customer_by_email = get_user_by('email', $visitor_email_from_post);
                if ($customer_by_email) {
                    $customer_obj = new WC_Customer($customer_by_email->ID);
                    if ($customer_obj && $customer_obj->get_id() > 0) {
                        $current_woo_customer_id = $customer_obj->get_id();
                        // Optionally update session with this found woo_customer_id
                        if ($current_woo_customer_id) {
                             $wpdb->update($sessions_table, array('woo_customer_id' => $current_woo_customer_id), array('session_id' => $session_id));
                        }
                    }
                }
            }

            if ( $current_woo_customer_id ) {
                $order_count = absint(get_option(TLC_PLUGIN_PREFIX . 'woo_orders_in_telegram_count', 1));
                $order_count = max(1, min(3, $order_count)); // Ensure 1-3

                $customer_orders = wc_get_orders( array(
                    'customer_id' => $current_woo_customer_id,
                    'limit'       => $order_count,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                ) );

                if ( !empty($customer_orders) ) {
                    $woo_orders_info_for_telegram .= "\n\nRecent Orders:";
                    foreach ( $customer_orders as $order ) {
                        $order_data = $order->get_data();
                        $woo_orders_info_for_telegram .= sprintf(
                            "\n- Order #%s (%s): %s %s (%s)",
                            $order->get_order_number(),
                            wc_get_order_status_name($order->get_status()),
                            $order->get_formatted_order_total(),
                            $order->get_currency(),
                            wp_date( get_option('date_format'), $order_data['date_created']->getTimestamp() )
                        );
                    }
                }
            }
        }
        // --- End: Add WooCommerce Order Info to Telegram Message ---

        $bot_token = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
        // ... (Target Chat IDs logic as before) ...
        $target_chat_ids = array(); // (Assume this is populated as before)

        if ( empty( $bot_token ) || empty( $target_chat_ids ) ) { /* ... error log and success return ... */ }

        $telegram_api = new TLC_Telegram_Bot_API( $bot_token );
        $telegram_message_content = sprintf( /* ... as before ... */ );
        // ... (Visitor details population as before) ...
        $telegram_message_content .= $woo_orders_info_for_telegram; // Append Woo order info
        // ... (Loop through target_chat_ids and send message) ...

        wp_send_json_success( array( 'message' => __( 'Message sent!', 'telegram-live-chat' ), 'message_id' => $db_message_id, 'session_id' => $session_id ) );
    }

    // ... (get_visitor_ip, is_currently_within_work_hours, handle_upload_chat_file, handle_submit_chat_rating, send_webhook, run, get_plugin_name, get_loader, get_version - as before) ...
    private function get_visitor_ip(){ /* ... */ }
    public function is_currently_within_work_hours(){ /* ... */ }
    public function handle_upload_chat_file(){ /* ... */ }
    public function handle_submit_chat_rating(){ /* ... */ }
    private function send_webhook($url, $payload_data){ /* ... */ }
    public function run(){ $this->loader->run(); }
    public function get_plugin_name(){ return $this->plugin_name; }
    public function get_loader(){ return $this->loader; }
    public function get_version(){ return $this->version; }
}

if (!function_exists('tlc_is_numeric_or_at_sign_string')) { /* ... */ }

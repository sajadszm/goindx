<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com
 * @since      0.1.0
 *
 * @package    TLC
 * @subpackage TLC/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.1.0
 * @package    TLC
 * @subpackage TLC/includes
 * @author     Your Name <email@example.com>
 */
class TLC_Plugin {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      TLC_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    0.1.0
     */
    public function __construct() {
        if ( defined( 'TLC_VERSION' ) ) {
            $this->version = TLC_VERSION;
        } else {
            $this->version = '0.1.0';
        }
        $this->plugin_name = 'telegram-live-chat';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - TLC_Loader. Orchestrates the hooks of the plugin.
     * - TLC_i18n. Defines internationalization functionality.
     * - TLC_Admin. Defines all hooks for the admin area.
     * - TLC_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    0.1.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once TLC_PLUGIN_DIR . 'admin/class-tlc-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once TLC_PLUGIN_DIR . 'public/class-tlc-public.php';

        /**
         * The class responsible for interacting with the Telegram Bot API.
         */
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-telegram-bot-api.php';

        /**
         * The class responsible for handling encryption/decryption.
         */
        require_once TLC_PLUGIN_DIR . 'includes/class-tlc-encryption.php';

        $this->loader = new TLC_Loader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the TLC_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    0.1.0
     * @access   private
     */
    private function set_locale() {

        $plugin_i18n = new TLC_i18n();

        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    0.1.0
     * @access   private
     */
    private function define_admin_hooks() {

        $plugin_admin = new TLC_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
        $this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_admin_settings_scripts' ); // Renamed
        // Example: $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        // Example: $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    0.1.0
     * @access   private
     */
    private function define_public_hooks() {

        $plugin_public = new TLC_Public( $this->get_plugin_name(), $this->get_version(), $this ); // Pass $this (TLC_Plugin instance)

        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        $this->loader->add_action( 'wp_footer', $plugin_public, 'add_chat_widget_html' );

        // AJAX handler for visitor messages
        $this->loader->add_action( 'wp_ajax_tlc_send_visitor_message', $this, 'handle_visitor_message' );
        $this->loader->add_action( 'wp_ajax_nopriv_tlc_send_visitor_message', $this, 'handle_visitor_message' );

        // Cron related hooks
        $this->loader->add_filter( 'cron_schedules', $this, 'add_custom_cron_schedules' );
        $this->loader->add_action( TLC_PLUGIN_PREFIX . 'telegram_polling_cron', $this, 'run_telegram_polling' );

        // Hook to schedule/unschedule cron when settings are updated
        add_action( 'update_option_' . TLC_PLUGIN_PREFIX . 'enable_telegram_polling', array( $this, 'handle_polling_setting_change' ), 10, 2 );
        add_action( 'update_option_' . TLC_PLUGIN_PREFIX . 'polling_interval', array( $this, 'handle_polling_setting_change' ), 10, 2 );

        // AJAX handler for fetching new messages for the widget
        $this->loader->add_action( 'wp_ajax_tlc_fetch_new_messages', $this, 'handle_fetch_new_messages' );
        $this->loader->add_action( 'wp_ajax_nopriv_tlc_fetch_new_messages', $this, 'handle_fetch_new_messages' );

        // AJAX handler for file uploads
        $this->loader->add_action( 'wp_ajax_tlc_upload_chat_file', $this, 'handle_upload_chat_file' );
        $this->loader->add_action( 'wp_ajax_nopriv_tlc_upload_chat_file', $this, 'handle_upload_chat_file' );

        // AJAX handler for submitting chat rating
        $this->loader->add_action( 'wp_ajax_tlc_submit_chat_rating', $this, 'handle_submit_chat_rating' );
        $this->loader->add_action( 'wp_ajax_nopriv_tlc_submit_chat_rating', $this, 'handle_submit_chat_rating' );
    }

    /**
     * Add custom cron schedules.
     * @param array $schedules
     * @return array
     */
    public function add_custom_cron_schedules( $schedules ) {
        $tlc_admin = new TLC_Admin($this->plugin_name, $this->version);
        $custom_intervals = $tlc_admin->get_polling_intervals();

        if(isset($custom_intervals['15_seconds'])) {
            $schedules['tlc_15_seconds'] = array(
                'interval' => 15,
                'display'  => __( 'Every 15 Seconds (TLC)', 'telegram-live-chat' )
            );
        }
        if(isset($custom_intervals['30_seconds'])) {
             $schedules['tlc_30_seconds'] = array(
                'interval' => 30,
                'display'  => __( 'Every 30 Seconds (TLC)', 'telegram-live-chat' )
            );
        }
        if(isset($custom_intervals['60_seconds'])) {
            $schedules['tlc_60_seconds'] = array(
                'interval' => 60,
                'display'  => __( 'Every 1 Minute (TLC)', 'telegram-live-chat' )
            );
        }
         if(isset($custom_intervals['300_seconds'])) {
            $schedules['tlc_300_seconds'] = array(
                'interval' => 300,
                'display'  => __( 'Every 5 Minutes (TLC)', 'telegram-live-chat' )
            );
        }
        return $schedules;
    }

    /**
     * Handle changes to polling settings to reschedule cron.
     */
    public function handle_polling_setting_change() {
        $this->schedule_or_unschedule_polling_cron();
    }

    public function schedule_or_unschedule_polling_cron() {
        $is_polling_enabled = get_option( TLC_PLUGIN_PREFIX . 'enable_telegram_polling' );
        $cron_hook = TLC_PLUGIN_PREFIX . 'telegram_polling_cron';

        if ( $is_polling_enabled ) {
            if ( ! wp_next_scheduled( $cron_hook ) ) {
                $interval_key = get_option( TLC_PLUGIN_PREFIX . 'polling_interval', '30_seconds' );
                $wp_schedule_key = TLC_PLUGIN_PREFIX . str_replace('_seconds', '_seconds', $interval_key);

                $schedules = wp_get_schedules();
                if (!isset($schedules[$wp_schedule_key])) {
                    $wp_schedule_key = 'hourly';
                     error_log(TLC_PLUGIN_PREFIX . "Warning: Custom cron schedule '{$wp_schedule_key}' not found. Falling back to 'hourly'.");
                }

                wp_schedule_event( time(), $wp_schedule_key, $cron_hook );
                error_log(TLC_PLUGIN_PREFIX . "Scheduled polling cron with interval key: " . $wp_schedule_key);
            }
        } else {
            if ( wp_next_scheduled( $cron_hook ) ) {
                wp_clear_scheduled_hook( $cron_hook );
                error_log(TLC_PLUGIN_PREFIX . "Unscheduled polling cron.");
            }
        }
    }

    public function run_telegram_polling() {
        $is_polling_enabled = get_option( TLC_PLUGIN_PREFIX . 'enable_telegram_polling' );
        if ( ! $is_polling_enabled ) {
            $this->schedule_or_unschedule_polling_cron();
            return;
        }

        $bot_token = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
        if ( empty( $bot_token ) ) {
            error_log( TLC_PLUGIN_PREFIX . 'Telegram polling cron: Bot token not set.' );
            return;
        }

        $telegram_api = new TLC_Telegram_Bot_API( $bot_token );
        if ( ! $telegram_api->is_configured() ) {
            error_log( TLC_PLUGIN_PREFIX . 'Telegram polling cron: API not configured (missing token).' );
            return;
        }

        $me = $telegram_api->get_me();
        if (is_wp_error($me) || !$me['ok']) {
            $error_message = is_wp_error($me) ? $me->get_error_message() : ($me['description'] ?? 'Unknown error');
            error_log(TLC_PLUGIN_PREFIX . "Telegram polling cron: getMe failed. Halting polling. Error: " . $error_message);
            return;
        }

        $last_update_id = (int) get_option( TLC_PLUGIN_PREFIX . 'last_telegram_update_id', 0 );
        $offset = $last_update_id > 0 ? $last_update_id + 1 : null;
        $updates = $telegram_api->get_updates( $offset, 100, 20 );

        if ( is_wp_error( $updates ) ) {
            error_log( TLC_PLUGIN_PREFIX . 'Telegram polling error: ' . $updates->get_error_message() );
            return;
        }

        if ( ! empty( $updates['ok'] ) && ! empty( $updates['result'] ) ) {
            $new_last_update_id = $last_update_id;
            foreach ( $updates['result'] as $update ) {
                if ( isset( $update['update_id'] ) ) {
                    $current_update_id = (int) $update['update_id'];
                    if ($current_update_id > $new_last_update_id) {
                        $new_last_update_id = $current_update_id;
                    }
                     $this->process_telegram_update($update);
                }
            }

            if ( $new_last_update_id > $last_update_id ) {
                update_option( TLC_PLUGIN_PREFIX . 'last_telegram_update_id', $new_last_update_id );
                 error_log(TLC_PLUGIN_PREFIX . "Polling successful. Processed " . count($updates['result']) . " updates. New last_update_id: " . $new_last_update_id);
            }
        } else {
             error_log(TLC_PLUGIN_PREFIX . 'Telegram polling: No updates or error in response structure. Response: ' . json_encode($updates));
        }
    }

    public function process_telegram_update( $update ) {
        global $wpdb;

        if ( !isset( $update['message']['message_id'] ) ||
             !isset( $update['message']['text'] ) ||
             !isset( $update['message']['from']['id'] ) ||
             !isset( $update['message']['reply_to_message'] ) ) {
            return;
        }

        if ( !isset($update['message']['reply_to_message']['from']['is_bot']) || !$update['message']['reply_to_message']['from']['is_bot']) {
            // Optionally log if reply is not to a bot, or if bot ID doesn't match our bot_id (more advanced)
        }

        $replied_to_text = $update['message']['reply_to_message']['text'] ?? ($update['message']['reply_to_message']['caption'] ?? '');
        if ( empty( $replied_to_text ) ) {
            return;
        }

        preg_match( '/\(Session: (\d+)\)/', $replied_to_text, $matches );
        if ( !isset( $matches[1] ) || !is_numeric( $matches[1] ) ) {
            return;
        }
        $session_id = (int) $matches[1];

        $agent_telegram_id = (int) $update['message']['from']['id'];
        $raw_agent_message_text = $update['message']['text']; // Keep raw text for shortcut check
        $telegram_message_id = (int) $update['message']['message_id'];

        // Check for Canned Response shortcut
        $canned_responses = get_option(TLC_PLUGIN_PREFIX . 'canned_responses', array());
        $final_agent_message_text = $raw_agent_message_text; // Default to raw text

        if (is_array($canned_responses)) {
            foreach ($canned_responses as $response) {
                if (isset($response['shortcut']) && isset($response['message']) && trim($raw_agent_message_text) === trim($response['shortcut'])) {
                    $final_agent_message_text = $response['message'];
                    error_log(TLC_PLUGIN_PREFIX . "Canned response triggered by shortcut: " . $response['shortcut']);
                    break;
                }
            }
        }

        $sanitized_agent_message_text = sanitize_textarea_field($final_agent_message_text);


        $admin_user_ids_str = get_option( TLC_PLUGIN_PREFIX . 'admin_user_ids', '' );
        $configured_agent_ids = array_map( 'trim', explode( ',', $admin_user_ids_str ) );
        $numeric_agent_ids = array_filter( $configured_agent_ids, 'is_numeric' );

        if ( !in_array( (string)$agent_telegram_id, $numeric_agent_ids ) ) {
            error_log(TLC_PLUGIN_PREFIX . "Message from non-configured agent ID: " . $agent_telegram_id . ". Update ID: " . $update['update_id']);
            return;
        }

        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';
        $message_inserted = $wpdb->insert(
            $messages_table,
            array(
                'session_id'        => $session_id,
                'sender_type'       => 'agent',
                'telegram_user_id'  => $agent_telegram_id,
                'message_content'   => $sanitized_agent_message_text, // Use potentially replaced and sanitized text
                'timestamp'         => current_time( 'mysql' ),
                'telegram_message_id' => $telegram_message_id,
                'is_read'           => 0,
            )
        );

        if ( ! $message_inserted ) {
            error_log(TLC_PLUGIN_PREFIX . "Failed to insert agent message into DB. Session: $session_id, Agent: $agent_telegram_id. Update ID: " . $update['update_id']);
            return;
        }
        $db_message_id = $wpdb->insert_id;

        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $wpdb->update(
            $sessions_table,
            array(
                'last_active_time' => current_time( 'mysql' ),
                'status' => 'active'
            ),
            array( 'session_id' => $session_id )
        );

        error_log(TLC_PLUGIN_PREFIX . "Successfully processed and stored agent reply. DB Message ID: $db_message_id, Session: $session_id, Agent: $agent_telegram_id. Update ID: " . $update['update_id']);
    }

    public function handle_fetch_new_messages() {
        global $wpdb;

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'tlc_fetch_new_messages_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed for fetch.', 'telegram-live-chat' ) ), 403 );
            return;
        }

        $visitor_token = isset( $_POST['visitor_token'] ) ? sanitize_text_field( $_POST['visitor_token'] ) : '';
        $last_message_id_displayed = isset( $_POST['last_message_id_displayed'] ) ? absint( $_POST['last_message_id_displayed'] ) : 0;

        if ( empty( $visitor_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Visitor token is required.', 'telegram-live-chat' ) ), 400 );
            return;
        }

        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

        $session_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT session_id FROM $sessions_table WHERE visitor_token = %s",
            $visitor_token
        ) );

        if ( ! $session_id ) {
            wp_send_json_success( array( 'messages' => array() ) );
            return;
        }

        $new_messages = $wpdb->get_results( $wpdb->prepare(
            "SELECT message_id, sender_type, message_content, timestamp
             FROM $messages_table
             WHERE session_id = %d
             AND message_id > %d
             AND (sender_type = 'agent' OR sender_type = 'system')
             ORDER BY message_id ASC",
            $session_id,
            $last_message_id_displayed
        ) );

        $output_messages = array();
        if ($new_messages) {
            foreach($new_messages as $msg) {
                $output_messages[] = array(
                    'id' => $msg->message_id,
                    'sender_type' => $msg->sender_type,
                    'text' => $msg->message_content,
                    'timestamp' => $msg->timestamp,
                );
            }
        }

        wp_send_json_success( array( 'messages' => $output_messages ) );
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }

    public function handle_visitor_message() {
        global $wpdb;

        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'tlc_send_visitor_message_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'telegram-live-chat' ) ), 403 );
            return;
        }

        // Visitor token is needed early for rate limiting
        $visitor_token = isset( $_POST['visitor_token'] ) ? sanitize_text_field( $_POST['visitor_token'] ) : '';
        if ( empty( $visitor_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Visitor token is required.', 'telegram-live-chat' ) ), 400 );
            return;
        }

        // Rate Limiting Check
        if ( get_option( TLC_PLUGIN_PREFIX . 'rate_limit_enable', true ) ) {
            $threshold = (int) get_option( TLC_PLUGIN_PREFIX . 'rate_limit_threshold', 5 );
            $period_seconds = (int) get_option( TLC_PLUGIN_PREFIX . 'rate_limit_period_seconds', 10 );
            $period_seconds = max(1, $period_seconds);
            $transient_key = TLC_PLUGIN_PREFIX . 'rl_' . substr(md5($visitor_token), 0, 16);

            $timestamps = get_transient( $transient_key );
            if ( false === $timestamps || !is_array($timestamps) ) {
                $timestamps = array();
            }

            $current_time = time();
            $timestamps = array_filter( $timestamps, function( $ts ) use ( $current_time, $period_seconds ) {
                return is_numeric($ts) && ( $current_time - $ts ) < $period_seconds;
            });

            if ( count( $timestamps ) >= $threshold ) {
                wp_send_json_error( array( 'message' => __( 'You are sending messages too quickly. Please wait a moment.', 'telegram-live-chat' ) ), 429 );
                return;
            }
            $timestamps[] = $current_time;
            set_transient( $transient_key, $timestamps, $period_seconds + 5 );
        }

        // Sanitize other inputs
        $message_text = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $current_page_url = isset( $_POST['current_page'] ) ? esc_url_raw( $_POST['current_page'] ) : '';

        if ( empty( $message_text ) ) {
            wp_send_json_error( array( 'message' => __( 'Message text cannot be empty.', 'telegram-live-chat' ) ), 400 );
            return;
        }

        $visitor_ip = $this->get_visitor_ip();
        $visitor_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
        $wp_user_id = get_current_user_id();
        $visitor_name_from_post = isset($_POST['visitor_name']) ? sanitize_text_field(wp_unslash($_POST['visitor_name'])) : null;
        $visitor_email_from_post = isset($_POST['visitor_email']) ? sanitize_email(wp_unslash($_POST['visitor_email'])) : null;


        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT session_id, status, referer FROM $sessions_table WHERE visitor_token = %s", // Also get referer to avoid overwriting
            $visitor_token
        ) );
        $session_id = null;
        $new_session_data = array();

        if ( $session ) {
            $session_id = $session->session_id;
            $update_data = array( 'last_active_time' => current_time( 'mysql' ) );
            if ($session->status === 'closed' || $session->status === 'archived') {
                $update_data['status'] = 'active';
            }
            // Only update referer and UTMs if they are not already set for this session
            if (empty($session->referer) && isset($_SERVER['HTTP_REFERER'])) {
                 $update_data['referer'] = esc_url_raw($_SERVER['HTTP_REFERER']);
            }
            // UTMs are typically for the first touch, so only set if not already there for the session.
            // This logic might be better if UTMs are tracked per session start, not per message.
            // For now, if new session, we add. If existing, we don't overwrite.
            // Update visitor name and email if provided and not already set or different.
            if ($visitor_name_from_post && (empty($session->visitor_name) || $session->visitor_name !== $visitor_name_from_post)) {
                $update_data['visitor_name'] = $visitor_name_from_post;
            }
            if ($visitor_email_from_post && (empty($session->visitor_email) || $session->visitor_email !== $visitor_email_from_post)) {
                $update_data['visitor_email'] = $visitor_email_from_post;
            }
            $wpdb->update( $sessions_table, $update_data, array( 'session_id' => $session_id ) );

        } else {
            $new_session_data = array(
                'visitor_token' => $visitor_token,
                'wp_user_id' => $wp_user_id > 0 ? $wp_user_id : null,
                'start_time' => current_time( 'mysql' ),
                'last_active_time' => current_time( 'mysql' ),
                'status' => 'active',
                'visitor_ip' => $visitor_ip,
                'visitor_user_agent' => $visitor_user_agent,
                'initial_page_url' => $current_page_url,
                'referer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : null,
                'visitor_name' => $visitor_name_from_post, // Add to new session
                'visitor_email' => $visitor_email_from_post, // Add to new session
            );
            $parsed_url_query = array();
            $query_str = parse_url($current_page_url, PHP_URL_QUERY);
            if ($query_str) {
                parse_str($query_str, $parsed_url_query);
            }
            $new_session_data['utm_source'] = isset($parsed_url_query['utm_source']) ? sanitize_text_field($parsed_url_query['utm_source']) : null;
            $new_session_data['utm_medium'] = isset($parsed_url_query['utm_medium']) ? sanitize_text_field($parsed_url_query['utm_medium']) : null;
            $new_session_data['utm_campaign'] = isset($parsed_url_query['utm_campaign']) ? sanitize_text_field($parsed_url_query['utm_campaign']) : null;

            $wpdb->insert( $sessions_table, $new_session_data );
            $session_id = $wpdb->insert_id;
        }

        if ( ! $session_id ) {
            wp_send_json_error( array( 'message' => __( 'Could not create or find chat session.', 'telegram-live-chat' ) ), 500 );
            return;
        }

        $message_inserted = $wpdb->insert( $messages_table, array(
            'session_id' => $session_id,
            'sender_type' => 'visitor',
            'message_content' => $message_text,
            'timestamp' => current_time( 'mysql' ),
            'page_url' => $current_page_url, // Store current page with message
        ));

        if ( ! $message_inserted ) {
            wp_send_json_error( array( 'message' => __( 'Could not store message.', 'telegram-live-chat' ) ), 500 );
            return;
        }
        $message_id = $wpdb->insert_id;

        $bot_token = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
        $admin_user_ids_str = get_option( TLC_PLUGIN_PREFIX . 'admin_user_ids' );

        if ( empty( $bot_token ) || empty( $admin_user_ids_str ) ) {
            error_log(TLC_PLUGIN_PREFIX . 'Telegram settings (bot token or admin IDs) not configured. Message ID: ' . $message_id);
            wp_send_json_success( array( 'message' => __( 'Message received. Admin will be notified if configured.', 'telegram-live-chat' ), 'message_id' => $message_id ) );
            return;
        }

        $telegram_api = new TLC_Telegram_Bot_API( $bot_token );
        $admin_user_ids = array_map( 'trim', explode( ',', $admin_user_ids_str ) );

        $telegram_message_content = sprintf(
            __( "New chat message from visitor (Session: %s):\nPage: %s\n\n%s", 'telegram-live-chat' ),
            $session_id, $current_page_url, $message_text
        );
        $visitor_details = "\n\nVisitor Info:\nIP: " . $visitor_ip;
        if ($visitor_name_from_post) {
            $visitor_details .= "\nName: " . esc_html($visitor_name_from_post);
        }
        if ($visitor_email_from_post) {
            $visitor_details .= "\nEmail: " . esc_html($visitor_email_from_post);
        }
        if ($wp_user_id > 0) {
            $user_info = get_userdata($wp_user_id);
            if ($user_info) {
                $visitor_details .= "\nUser: " . esc_html($user_info->display_name) . " (" . esc_html($user_info->user_email) . ")";
            }
        }
        // Add Referer and UTM if it's a new session (or if we decide to send it always)
        // $new_session_data might not be set if session existed. Fetch from DB for consistent info.
        $session_info_for_telegram = $wpdb->get_row($wpdb->prepare("SELECT referer, utm_source, utm_medium, utm_campaign FROM $sessions_table WHERE session_id = %d", $session_id));

        if ($session_info_for_telegram) {
            if (!empty($session_info_for_telegram->referer)) {
                 $visitor_details .= "\nReferer: " . esc_html($session_info_for_telegram->referer);
            }
            if (!empty($session_info_for_telegram->utm_source)) {
                 $visitor_details .= "\nUTM Source: " . esc_html($session_info_for_telegram->utm_source);
            }
            if (!empty($session_info_for_telegram->utm_medium)) {
                 $visitor_details .= "\nUTM Medium: " . esc_html($session_info_for_telegram->utm_medium);
            }
            if (!empty($session_info_for_telegram->utm_campaign)) {
                 $visitor_details .= "\nUTM Campaign: " . esc_html($session_info_for_telegram->utm_campaign);
            }
        }


        $telegram_message_content .= $visitor_details;

        $success_sending_to_all_admins = true;
        foreach ( $admin_user_ids as $admin_user_id ) {
            if ( ! is_numeric( $admin_user_id ) ) continue;
            $response = $telegram_api->send_message( $admin_user_id, $telegram_message_content, 'HTML' );
            if ( is_wp_error( $response ) ) {
                error_log( TLC_PLUGIN_PREFIX . 'Failed to send message to Telegram User ID ' . $admin_user_id . ': ' . $response->get_error_message() );
                $success_sending_to_all_admins = false;
            }
        }

        if ( ! $success_sending_to_all_admins ) {
            error_log(TLC_PLUGIN_PREFIX . 'One or more Telegram notifications failed for message ID: ' . $message_id);
        }
        wp_send_json_success( array( 'message' => __( 'Message sent!', 'telegram-live-chat' ), 'message_id' => $message_id, 'session_id' => $session_id ) );
    }

    private function get_visitor_ip() {
        $ip = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if ( $ip && false !== strpos( $ip, ':' ) && substr_count( $ip, '.' ) === 3 ) {
            $ip = explode( ':', $ip )[0];
        }
        return sanitize_text_field($ip);
    }

    public function is_currently_within_work_hours() {
        $work_hours_data = get_option(TLC_PLUGIN_PREFIX . 'work_hours');
        if (empty($work_hours_data) || !is_array($work_hours_data)) return true;

        $current_timestamp = current_time('timestamp');
        $current_day_key = strtolower(wp_date('l', $current_timestamp));
        $current_time_hm = wp_date('H:i', $current_timestamp);

        if (!isset($work_hours_data[$current_day_key])) return true;
        $day_settings = $work_hours_data[$current_day_key];
        if (!isset($day_settings['is_open']) || $day_settings['is_open'] !== '1') return false;

        $open_time = $day_settings['open'];
        $close_time = $day_settings['close'];

        if (strtotime($open_time) > strtotime($close_time)) { // Overnight shift
            if ($current_time_hm >= $open_time || $current_time_hm < $close_time) return true;
        } else { // Same day shift
            if ($current_time_hm >= $open_time && $current_time_hm < $close_time) return true;
        }
        return false;
    }

    public function handle_upload_chat_file() {
        global $wpdb;

        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'tlc_upload_chat_file_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'telegram-live-chat' ) ), 403 );
            return;
        }

        if ( !get_option( TLC_PLUGIN_PREFIX . 'file_uploads_enable', false ) ) {
            wp_send_json_error( array( 'message' => __( 'File uploads are disabled.', 'telegram-live-chat' ) ), 403 );
            return;
        }

        if ( empty( $_FILES['chat_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'telegram-live-chat' ) ), 400 );
            return;
        }

        $file = $_FILES['chat_file'];
        $visitor_token = isset( $_POST['visitor_token'] ) ? sanitize_text_field( $_POST['visitor_token'] ) : '';
        $current_page = isset( $_POST['current_page'] ) ? esc_url_raw( $_POST['current_page'] ) : ''; // current_page_url from file upload

        if ( empty( $visitor_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Visitor token required.', 'telegram-live-chat' ) ), 400 );
            return;
        }

        $max_size_mb = absint(get_option(TLC_PLUGIN_PREFIX . 'file_uploads_max_size_mb', 2));
        $max_size_bytes = $max_size_mb * 1024 * 1024;
        if ($file['size'] > $max_size_bytes) {
            wp_send_json_error( array( 'message' => sprintf(__('File too large (max %d MB).', 'telegram-live-chat'), $max_size_mb) ), 400 );
            return;
        }

        $allowed_types_str = get_option(TLC_PLUGIN_PREFIX . 'file_uploads_allowed_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt');
        $mimes = false;
        if (!empty($allowed_types_str)) {
            $allowed_exts_normalized = array_map('trim', explode(',', strtolower($allowed_types_str)));
            $mimes = array();
            foreach ( $allowed_exts_normalized as $ext_normalized ) {
                foreach ( get_allowed_mime_types() as $exts_patterns => $mime_type_val ) {
                    if ( preg_match( '/\b' . preg_quote( $ext_normalized, '/' ) . '\b/i', $exts_patterns ) ) {
                        $mimes[ $exts_patterns ] = $mime_type_val;
                    }
                }
            }
            if (empty($mimes) && $allowed_types_str !== '') {
                 wp_send_json_error( array( 'message' => __( 'Invalid allowed file types configured.', 'telegram-live-chat' ) ), 400 );
                 return;
            }
        }

        $upload_dir_info = wp_upload_dir();
        $tlc_upload_basedir = $upload_dir_info['basedir'] . '/tlc-chat-files';
        $visitor_token_path_segment = sanitize_file_name($visitor_token);
        $tlc_visitor_upload_path = $tlc_upload_basedir . '/' . $visitor_token_path_segment;
        $tlc_visitor_upload_url = $upload_dir_info['baseurl'] . '/tlc-chat-files/' . $visitor_token_path_segment;

        wp_mkdir_p( $tlc_visitor_upload_path );

        $upload_overrides = array(
            'test_form' => false,
            'mimes'     => $mimes,
            'unique_filename_callback' => function($dir, $name, $ext) {
                return hash('sha1', sanitize_file_name($name) . microtime()) . $ext;
            }
        );

        $custom_upload_dir_filter_func = function( $pathdata ) use ( $tlc_visitor_upload_path, $tlc_visitor_upload_url ) {
            $pathdata['path'] = $tlc_visitor_upload_path;
            $pathdata['url'] = $tlc_visitor_upload_url;
            return $pathdata;
        };
        add_filter( 'upload_dir', $custom_upload_dir_filter_func );

        $movefile = wp_handle_upload( $file, $upload_overrides );

        remove_filter( 'upload_dir', $custom_upload_dir_filter_func );

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $file_url = $movefile['url'];
            $file_path = $movefile['file'];
            $original_filename = sanitize_file_name($file['name']);

            $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
            $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

            $session_id = $wpdb->get_var( $wpdb->prepare("SELECT session_id FROM $sessions_table WHERE visitor_token = %s", $visitor_token) );
            if (!$session_id) {
                 $visitor_ip = $this->get_visitor_ip();
                 $visitor_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
                 $wp_user_id = get_current_user_id();
                 $wpdb->insert( $sessions_table, array( // This is for file upload, initial_page_url should be $current_page from AJAX
                    'visitor_token' => $visitor_token, 'wp_user_id' => $wp_user_id > 0 ? $wp_user_id : null,
                    'start_time' => current_time( 'mysql' ), 'last_active_time' => current_time( 'mysql' ),
                    'status' => 'active', 'visitor_ip' => $visitor_ip,
                    'visitor_user_agent' => $visitor_user_agent, 'initial_page_url' => $current_page,
                ));
                $session_id = $wpdb->insert_id;
            } else {
                 $wpdb->update( $sessions_table, array( 'last_active_time' => current_time( 'mysql' ) ), array( 'session_id' => $session_id ) );
            }

            if ( ! $session_id ) {
                if (file_exists($file_path)) unlink($file_path);
                wp_send_json_error( array( 'message' => __( 'Session error for file.', 'telegram-live-chat' ) ), 500 );
                return;
            }

            $message_content = sprintf(__('File: [%1$s](%2$s)', 'telegram-live-chat'), $original_filename, $file_url);

            $message_inserted = $wpdb->insert( $messages_table, array(
                'session_id' => $session_id, 'sender_type' => 'visitor',
                'message_content' => $message_content,
                'timestamp' => current_time( 'mysql' ),
                'page_url' => $current_page, // Store current page with file message too
            ));
            $db_message_id = $wpdb->insert_id;

            $bot_token_setting = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
            $admin_user_ids_str = get_option( TLC_PLUGIN_PREFIX . 'admin_user_ids' );
            $group_chat_id = get_option(TLC_PLUGIN_PREFIX . 'telegram_chat_id_group');

            if ( !empty( $bot_token_setting ) && (!empty( $admin_user_ids_str ) || !empty($group_chat_id)) ) {
                $telegram_api = new TLC_Telegram_Bot_API( $bot_token_setting );
                $caption = sprintf( __( "File from visitor (Session: %s, File: %s)\nPage: %s\nURL (for admin reference): %s", 'telegram-live-chat' ),
                    $session_id, $original_filename, $current_page, $file_url
                );

                $target_chat_ids = array();
                if (!empty($admin_user_ids_str)) {
                    $target_chat_ids = array_merge($target_chat_ids, array_map( 'trim', explode( ',', $admin_user_ids_str ) ));
                }
                if (!empty($group_chat_id) && tlc_is_numeric_or_at_sign_string($group_chat_id) ) {
                    $target_chat_ids[] = $group_chat_id;
                }
                $target_chat_ids = array_unique(array_filter($target_chat_ids));


                foreach ( $target_chat_ids as $chat_id ) {
                    if (empty($chat_id)) continue;
                    if (!is_numeric($chat_id) && strpos($chat_id, '@') !== 0) {
                        error_log(TLC_PLUGIN_PREFIX . "Skipping invalid chat_id for file send: " . $chat_id);
                        continue;
                    }
                    $telegram_api->send_document( $chat_id, $file_path, $caption, $original_filename );
                }
            }

            wp_send_json_success( array( 'filename' => $original_filename, 'file_url' => $file_url, 'message_id' => $db_message_id ) );
        } else {
            wp_send_json_error( array( 'message' => $movefile['error'] ), 400 );
        }
        die();
    }

    /**
     * Handles AJAX request to submit chat rating.
     * @since 0.5.0
     */
    public function handle_submit_chat_rating() {
        global $wpdb;

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'tlc_submit_chat_rating_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'telegram-live-chat' ) ), 403 );
            return;
        }

        $visitor_token = isset( $_POST['visitor_token'] ) ? sanitize_text_field( $_POST['visitor_token'] ) : '';
        $rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
        $comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';

        if ( empty( $visitor_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Visitor token required.', 'telegram-live-chat' ) ), 400 );
            return;
        }
        if ( $rating < 1 || $rating > 5 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid rating value.', 'telegram-live-chat' ) ), 400 );
            return;
        }

        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT session_id FROM $sessions_table WHERE visitor_token = %s ORDER BY session_id DESC LIMIT 1", $visitor_token ) );

        if ( ! $session ) {
            wp_send_json_error( array( 'message' => __( 'No active session found for this visitor to rate.', 'telegram-live-chat' ) ), 404 );
            return;
        }

        // Update the session with rating and comment
        $updated = $wpdb->update(
            $sessions_table,
            array(
                'rating' => $rating,
                'rating_comment' => $comment,
                // Optionally, update status to 'rated' or 'closed_rated'
                // 'status' => 'closed_rated'
            ),
            array( 'session_id' => $session->session_id ),
            array( '%d', '%s' ), // data formats
            array( '%d' )        // where formats
        );

        if ( false === $updated ) {
            wp_send_json_error( array( 'message' => __( 'Could not save rating. Database error.', 'telegram-live-chat' ) ), 500 );
            return;
        }

        wp_send_json_success( array( 'message' => __( 'Rating submitted successfully.', 'telegram-live-chat' ) ) );
    }
}

if (!function_exists('tlc_is_numeric_or_at_sign_string')) {
    function tlc_is_numeric_or_at_sign_string($value) {
        return is_numeric($value) || (is_string($value) && strpos($value, '@') === 0);
    }
}

[end of telegram-live-chat/includes/class-tlc-plugin.php]

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
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_color_picker_assets' );
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
        // This specific hook `update_option_{option_name}` is dynamic.
        // We also need to handle plugin activation/deactivation.
        add_action( 'update_option_' . TLC_PLUGIN_PREFIX . 'enable_telegram_polling', array( $this, 'handle_polling_setting_change' ), 10, 2 );
        add_action( 'update_option_' . TLC_PLUGIN_PREFIX . 'polling_interval', array( $this, 'handle_polling_setting_change' ), 10, 2 );

        // AJAX handler for fetching new messages for the widget
        $this->loader->add_action( 'wp_ajax_tlc_fetch_new_messages', $this, 'handle_fetch_new_messages' );
        $this->loader->add_action( 'wp_ajax_nopriv_tlc_fetch_new_messages', $this, 'handle_fetch_new_messages' );
        // Also handle on activation - this is already covered if we schedule on init if not scheduled
    }

    /**
     * Add custom cron schedules.
     * @param array $schedules
     * @return array
     */
    public function add_custom_cron_schedules( $schedules ) {
        $tlc_admin = new TLC_Admin($this->plugin_name, $this->version); // To access get_polling_intervals
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
            $schedules['tlc_60_seconds'] = array( // Equivalent to 'hourly' but for consistency
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
        // Note: WordPress's minimum cron interval is typically 1 minute unless filtered otherwise or server cron is used.
        // True 15/30 second polling might require server-side cron triggering wp-cron.php frequently.
        // For now, we define them, but actual execution frequency depends on how often WP Cron is triggered.
        return $schedules;
    }

    /**
     * Handle changes to polling settings to reschedule cron.
     */
    public function handle_polling_setting_change() {
        $this->schedule_or_unschedule_polling_cron();
    }

    /**
     * Schedule or unschedule the polling cron job based on settings.
     * This should also be called on plugin activation and deactivation.
     */
    public function schedule_or_unschedule_polling_cron() {
        $is_polling_enabled = get_option( TLC_PLUGIN_PREFIX . 'enable_telegram_polling' );
        $cron_hook = TLC_PLUGIN_PREFIX . 'telegram_polling_cron';

        if ( $is_polling_enabled ) {
            if ( ! wp_next_scheduled( $cron_hook ) ) {
                $interval_key = get_option( TLC_PLUGIN_PREFIX . 'polling_interval', '30_seconds' );
                // Convert interval key to WP schedule key
                $wp_schedule_key = TLC_PLUGIN_PREFIX . str_replace('_seconds', '_seconds', $interval_key);
                // A bit redundant here, but if keys were '15s' vs 'tlc_15_seconds' it would map.
                // For our current setup, 'tlc_15_seconds' is directly the key in schedules array.

                // Ensure the schedule exists, especially if `add_custom_cron_schedules` hasn't fired yet on activation.
                $schedules = wp_get_schedules();
                if (!isset($schedules[$wp_schedule_key])) {
                     // Fallback if our custom schedule isn't registered for some reason (e.g. during activation)
                    $wp_schedule_key = 'hourly'; // A safe default
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


    /**
     * The actual cron job function to poll Telegram.
     */
    public function run_telegram_polling() {
        $is_polling_enabled = get_option( TLC_PLUGIN_PREFIX . 'enable_telegram_polling' );
        if ( ! $is_polling_enabled ) {
            // Ensure cron is unscheduled if it's running while disabled.
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

        // Check if getMe works, as a basic health check before polling
        $me = $telegram_api->get_me();
        if (is_wp_error($me) || !$me['ok']) {
            $error_message = is_wp_error($me) ? $me->get_error_message() : ($me['description'] ?? 'Unknown error');
            error_log(TLC_PLUGIN_PREFIX . "Telegram polling cron: getMe failed. Halting polling. Error: " . $error_message);
            // Optionally, disable polling here and notify admin
            // update_option(TLC_PLUGIN_PREFIX . 'enable_telegram_polling', false);
            // $this->schedule_or_unschedule_polling_cron();
            return;
        }


        $last_update_id = (int) get_option( TLC_PLUGIN_PREFIX . 'last_telegram_update_id', 0 );
        $offset = $last_update_id > 0 ? $last_update_id + 1 : null;

        // Use a timeout for getUpdates. Telegram recommends up to 50 seconds for long polling.
        // WP Cron's reliability for exact timing is variable, so short polling is often more practical here.
        $updates = $telegram_api->get_updates( $offset, 100, 20 ); // Fetch up to 100 updates, 20s timeout for long poll

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
                    // Process the update (this is for Step 3 of Phase 2)
                    // For now, just log it.
                    // error_log(TLC_PLUGIN_PREFIX . 'Received update ID: ' . $update['update_id'] . ' - Content: ' . json_encode($update));
                     $this->process_telegram_update($update); // Call processing function
                }
            }

            if ( $new_last_update_id > $last_update_id ) {
                update_option( TLC_PLUGIN_PREFIX . 'last_telegram_update_id', $new_last_update_id );
                 error_log(TLC_PLUGIN_PREFIX . "Polling successful. Processed " . count($updates['result']) . " updates. New last_update_id: " . $new_last_update_id);
            } else {
                // error_log(TLC_PLUGIN_PREFIX . "Polling successful. No new updates.");
            }
        } else {
             error_log(TLC_PLUGIN_PREFIX . 'Telegram polling: No updates or error in response structure. Response: ' . json_encode($updates));
        }
    }

    /**
     * Placeholder for processing a single Telegram update.
     * This will be fully implemented in "Core Chat Logic (Telegram to Website - Part 2)".
     * @param array $update The update object from Telegram.
     */
    public function process_telegram_update( $update ) {
        global $wpdb;

        // Check if this is a message, has text, and is a reply
        if ( !isset( $update['message']['message_id'] ) ||
             !isset( $update['message']['text'] ) ||
             !isset( $update['message']['from']['id'] ) ||
             !isset( $update['message']['reply_to_message'] ) ) {
            // error_log(TLC_PLUGIN_PREFIX . "Update is not a valid reply message. Update ID: " . ($update['update_id'] ?? 'N/A'));
            return;
        }

        // 1. Check if the reply is to a message sent by our bot.
        // We need the bot's ID for this. We can get it from getMe and store it.
        // For now, we assume any reply processed here is intended for the system if it matches format.
        // A stricter check would be: $update['message']['reply_to_message']['from']['id'] == $this->get_bot_id()
        // Let's add a quick check for `is_bot` if available on `reply_to_message.from`
        if ( !isset($update['message']['reply_to_message']['from']['is_bot']) || !$update['message']['reply_to_message']['from']['is_bot']) {
            // error_log(TLC_PLUGIN_PREFIX . "Reply is not to a bot. Ignoring. Update ID: " . $update['update_id']);
            // This check might be too strict if bot details are not always present or if bot is replying to itself (unlikely here)
            // A better check is to see if we can parse session_id from the replied-to message.
        }

        $replied_to_text = $update['message']['reply_to_message']['text'] ?? ($update['message']['reply_to_message']['caption'] ?? '');
        if ( empty( $replied_to_text ) ) {
            // error_log(TLC_PLUGIN_PREFIX . "Replied-to message has no text/caption. Update ID: " . $update['update_id']);
            return;
        }

        // 2. Parse session_id from reply_to_message.text
        // Format: "New chat message from visitor (Session: XX):"
        preg_match( '/\(Session: (\d+)\)/', $replied_to_text, $matches );
        if ( !isset( $matches[1] ) || !is_numeric( $matches[1] ) ) {
            // error_log(TLC_PLUGIN_PREFIX . "Could not parse session_id from replied message. Text: " . $replied_to_text . " Update ID: " . $update['update_id']);
            return;
        }
        $session_id = (int) $matches[1];

        // 3. Get the agent's telegram_user_id
        $agent_telegram_id = (int) $update['message']['from']['id'];
        $agent_message_text = sanitize_textarea_field( $update['message']['text'] );
        $telegram_message_id = (int) $update['message']['message_id'];

        // 4. Validate this agent telegram_user_id against the configured list
        $admin_user_ids_str = get_option( TLC_PLUGIN_PREFIX . 'admin_user_ids', '' );
        $configured_agent_ids = array_map( 'trim', explode( ',', $admin_user_ids_str ) );
        $numeric_agent_ids = array_filter( $configured_agent_ids, 'is_numeric' ); // Ensure all are numeric

        if ( !in_array( (string)$agent_telegram_id, $numeric_agent_ids ) ) { // Cast to string for in_array comparison as options are strings
            error_log(TLC_PLUGIN_PREFIX . "Message from non-configured agent ID: " . $agent_telegram_id . ". Update ID: " . $update['update_id']);
            return;
        }

        // 5. Store the agent's message in tlc_chat_messages
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';
        $message_inserted = $wpdb->insert(
            $messages_table,
            array(
                'session_id'        => $session_id,
                'sender_type'       => 'agent',
                'telegram_user_id'  => $agent_telegram_id,
                'message_content'   => $agent_message_text,
                'timestamp'         => current_time( 'mysql' ), // Could also use $update['message']['date'] converted to MySQL format
                'telegram_message_id' => $telegram_message_id,
                'is_read'           => 0, // Mark as unread for the visitor
            )
        );

        if ( ! $message_inserted ) {
            error_log(TLC_PLUGIN_PREFIX . "Failed to insert agent message into DB. Session: $session_id, Agent: $agent_telegram_id. Update ID: " . $update['update_id']);
            return;
        }
        $db_message_id = $wpdb->insert_id;

        // 6. Update last_active_time and status for the session in tlc_chat_sessions
        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $wpdb->update(
            $sessions_table,
            array(
                'last_active_time' => current_time( 'mysql' ),
                'status' => 'active' // Ensure session is active if agent replies
            ),
            array( 'session_id' => $session_id )
        );

        error_log(TLC_PLUGIN_PREFIX . "Successfully processed and stored agent reply. DB Message ID: $db_message_id, Session: $session_id, Agent: $agent_telegram_id. Update ID: " . $update['update_id']);
    }

    /**
     * Handles AJAX request from frontend widget to fetch new messages.
     *
     * @since 0.2.0
     */
    public function handle_fetch_new_messages() {
        global $wpdb;

        // Verify nonce
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

        // Get session_id from visitor_token
        $session_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT session_id FROM $sessions_table WHERE visitor_token = %s",
            $visitor_token
        ) );

        if ( ! $session_id ) {
            // Don't send error if session not found yet, could be initial poll before first message sent by visitor
            wp_send_json_success( array( 'messages' => array() ) );
            return;
        }

        // Fetch new messages (agent or system messages) for this session
        // that have an ID greater than the last one displayed by the client.
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


    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    0.1.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     0.1.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     0.1.0
     * @return    TLC_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     0.1.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Handles the AJAX request to send a visitor's message.
     *
     * @since 0.1.0
     */
    public function handle_visitor_message() {
        global $wpdb;

        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'tlc_send_visitor_message_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'telegram-live-chat' ) ), 403 );
            return;
        }

        // Sanitize inputs
        $message_text = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $visitor_token = isset( $_POST['visitor_token'] ) ? sanitize_text_field( $_POST['visitor_token'] ) : '';
        $current_page = isset( $_POST['current_page'] ) ? esc_url_raw( $_POST['current_page'] ) : '';

        if ( empty( $message_text ) || empty( $visitor_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields (message or token).', 'telegram-live-chat' ) ), 400 );
            return;
        }

        // Get visitor IP and User Agent
        $visitor_ip = $this->get_visitor_ip();
        $visitor_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
        $wp_user_id = get_current_user_id(); // Returns 0 if not logged in

        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

        // Find or create chat session
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT session_id, status FROM $sessions_table WHERE visitor_token = %s",
            $visitor_token
        ) );

        $session_id = null;

        if ( $session ) {
            $session_id = $session->session_id;
            // Update last_active_time and potentially status if closed/archived
            $update_data = array( 'last_active_time' => current_time( 'mysql' ) );
            if ($session->status === 'closed' || $session->status === 'archived') {
                $update_data['status'] = 'active'; // Re-open session if user messages again
            }
            $wpdb->update(
                $sessions_table,
                $update_data,
                array( 'session_id' => $session_id )
            );
        } else {
            $insert_data = array(
                'visitor_token' => $visitor_token,
                'wp_user_id' => $wp_user_id > 0 ? $wp_user_id : null,
                'start_time' => current_time( 'mysql' ),
                'last_active_time' => current_time( 'mysql' ),
                'status' => 'active',
                'visitor_ip' => $visitor_ip,
                'visitor_user_agent' => $visitor_user_agent,
                'initial_page_url' => $current_page,
            );
            $wpdb->insert( $sessions_table, $insert_data );
            $session_id = $wpdb->insert_id;
        }

        if ( ! $session_id ) {
            wp_send_json_error( array( 'message' => __( 'Could not create or find chat session.', 'telegram-live-chat' ) ), 500 );
            return;
        }

        // Store the message
        $message_inserted = $wpdb->insert(
            $messages_table,
            array(
                'session_id' => $session_id,
                'sender_type' => 'visitor',
                'message_content' => $message_text,
                'timestamp' => current_time( 'mysql' ),
            )
        );

        if ( ! $message_inserted ) {
            wp_send_json_error( array( 'message' => __( 'Could not store message.', 'telegram-live-chat' ) ), 500 );
            return;
        }
        $message_id = $wpdb->insert_id;

        // Forward to Telegram
        $bot_token = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
        $admin_user_ids_str = get_option( TLC_PLUGIN_PREFIX . 'admin_user_ids' );

        if ( empty( $bot_token ) || empty( $admin_user_ids_str ) ) {
            // Not critical enough to send an error back to user, but log it.
            error_log(TLC_PLUGIN_PREFIX . 'Telegram settings (bot token or admin IDs) not configured. Message ID: ' . $message_id);
            wp_send_json_success( array( 'message' => __( 'Message received. Admin will be notified if configured.', 'telegram-live-chat' ), 'message_id' => $message_id ) );
            return;
        }

        $telegram_api = new TLC_Telegram_Bot_API( $bot_token );
        $admin_user_ids = array_map( 'trim', explode( ',', $admin_user_ids_str ) );

        $telegram_message = sprintf(
            __( "New chat message from visitor (Session: %s):\nPage: %s\n\n%s", 'telegram-live-chat' ),
            $session_id, // Or visitor_token for more direct identification if needed
            $current_page,
            $message_text
        );

        // Add visitor details if available
        $visitor_details = "\n\nVisitor Info:\nIP: " . $visitor_ip;
        if ($wp_user_id > 0) {
            $user_info = get_userdata($wp_user_id);
            if ($user_info) {
                $visitor_details .= "\nUser: " . $user_info->display_name . " (" . $user_info->user_email . ")";
            }
        }
        $telegram_message .= $visitor_details;


        $success_sending_to_all_admins = true;
        foreach ( $admin_user_ids as $admin_user_id ) {
            if ( ! is_numeric( $admin_user_id ) ) continue;
            $response = $telegram_api->send_message( $admin_user_id, $telegram_message, 'HTML' ); // Or MarkdownV2
            if ( is_wp_error( $response ) ) {
                error_log( TLC_PLUGIN_PREFIX . 'Failed to send message to Telegram User ID ' . $admin_user_id . ': ' . $response->get_error_message() );
                $success_sending_to_all_admins = false;
            } else if (isset($response['result']['message_id'])) {
                // Optionally store the Telegram message_id for this outgoing notification
            }
        }

        if ( ! $success_sending_to_all_admins ) {
             // Still send success to user, as their message is stored. Admin notification is secondary.
            error_log(TLC_PLUGIN_PREFIX . 'One or more Telegram notifications failed for message ID: ' . $message_id);
        }

        wp_send_json_success( array( 'message' => __( 'Message sent!', 'telegram-live-chat' ), 'message_id' => $message_id, 'session_id' => $session_id ) );
    }

    /**
     * Get visitor IP address.
     *
     * @since 0.1.0
     * @return string IP address.
     */
    private function get_visitor_ip() {
        $ip = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // Strip port number from IP address
        if ( $ip && false !== strpos( $ip, ':' ) && substr_count( $ip, '.' ) === 3 ) {
            $ip = explode( ':', $ip )[0];
        }
        return sanitize_text_field($ip);
    }

    /**
     * Check if current time is within defined work hours.
     * Uses WordPress timezone.
     * @return bool True if within work hours or if work hours not configured/enabled properly, false otherwise.
     */
    public function is_currently_within_work_hours() {
        $work_hours_data = get_option(TLC_PLUGIN_PREFIX . 'work_hours');

        if (empty($work_hours_data) || !is_array($work_hours_data)) {
            return true; // If not configured, assume always online
        }

        // Get current time in WordPress timezone
        $current_timestamp = current_time('timestamp');
        $current_day_key = strtolower(wp_date('l', $current_timestamp)); // Monday, Tuesday...
        $current_time_hm = wp_date('H:i', $current_timestamp); // HH:MM format

        if (!isset($work_hours_data[$current_day_key])) {
            return true; // Should not happen if defaults are set, but as a fallback
        }

        $day_settings = $work_hours_data[$current_day_key];

        if (!isset($day_settings['is_open']) || $day_settings['is_open'] !== '1') {
            return false; // Closed today
        }

        $open_time = $day_settings['open'];  // HH:MM
        $close_time = $day_settings['close']; // HH:MM

        // Compare times
        if ($current_time_hm >= $open_time && $current_time_hm < $close_time) {
            // Special case: if close_time is past midnight (e.g. 02:00), it means it's open from open_time to midnight OR 00:00 to close_time on the next day.
            // For simplicity, this implementation assumes close_time is on the same day and open_time < close_time.
            // If open_time is '22:00' and close_time is '02:00', this simple check fails.
            // A more robust check would convert HH:MM to minutes from midnight or handle overnight shifts.
            // For now, assuming standard day shifts.
            return true;
        }

        // Simplistic overnight check (if close time is "earlier" than open time, e.g. 22:00 - 02:00)
        // This isn't perfect because it doesn't consider the day change for the close time.
        // A truly robust solution involves comparing full datetime objects or minutes since week start.
        // For this iteration, we'll stick to same-day comparison. If close_time < open_time, it means overnight.
        // The current logic `current < close` would fail if `close` is e.g. `02:00` and `current` is `23:00`.
        // Let's refine:
        // If open_time < close_time (standard day shift, e.g., 09:00-17:00)
        //    is_open = (current_time >= open_time && current_time < close_time)
        // If open_time > close_time (overnight shift, e.g., 22:00-06:00)
        //    is_open = (current_time >= open_time || current_time < close_time)

        if (strtotime($open_time) > strtotime($close_time)) { // Overnight shift
            if ($current_time_hm >= $open_time || $current_time_hm < $close_time) {
                return true;
            }
        } else { // Same day shift
            if ($current_time_hm >= $open_time && $current_time_hm < $close_time) {
                return true;
            }
        }


        return false;
    }
}

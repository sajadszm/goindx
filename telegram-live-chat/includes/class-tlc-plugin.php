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

        $plugin_public = new TLC_Public( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        $this->loader->add_action( 'wp_footer', $plugin_public, 'add_chat_widget_html' );

        // AJAX handler for visitor messages
        $this->loader->add_action( 'wp_ajax_tlc_send_visitor_message', $this, 'handle_visitor_message' );
        $this->loader->add_action( 'wp_ajax_nopriv_tlc_send_visitor_message', $this, 'handle_visitor_message' );
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
}

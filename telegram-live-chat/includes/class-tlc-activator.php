<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.1.0
 * @package    TLC
 * @subpackage TLC/includes
 * @author     Your Name <email@example.com>
 */
class TLC_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    0.1.0
     */
    public static function activate() {
        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Flush rewrite rules if any custom post types or taxonomies are registered (not in this phase)
        // flush_rewrite_rules();

        // Add custom user role and capabilities
        self::add_custom_roles_and_caps();
    }

    /**
     * Adds custom user roles and capabilities for the plugin.
     * @since 0.9.0
     */
    private static function add_custom_roles_and_caps() {
        $agent_role_slug = TLC_PLUGIN_PREFIX . 'chat_agent';
        $agent_role_display_name = __('Chat Agent', 'telegram-live-chat');

        // Define capabilities
        $tlc_capabilities = array(
            'read_tlc_chat_sessions'         => true, // View sessions in the new live dashboard
            'reply_tlc_chat_sessions'        => true, // Reply to chats from WP Admin
            'view_tlc_chat_history'          => true, // Access existing chat history page
            'access_tlc_live_chat_dashboard' => true, // Access the new live chat dashboard page
            // Future: 'edit_tlc_settings', 'delete_tlc_chats', etc.
        );

        // Add the role with its capabilities
        add_role( $agent_role_slug, $agent_role_display_name, $tlc_capabilities );

        // Add capabilities to Administrator role
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            foreach ( $tlc_capabilities as $cap => $grant ) {
                if ( $grant ) {
                    $admin_role->add_cap( $cap );
                }
            }
        }
    }


    /**
     * Create database tables needed for the plugin.
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_sessions_name = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions_name (
            session_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            visitor_token VARCHAR(64) NOT NULL,
            wp_user_id BIGINT UNSIGNED NULL,
            start_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_active_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            visitor_ip VARCHAR(45) NULL,
            visitor_user_agent TEXT NULL,
            initial_page_url TEXT NULL,
            referer TEXT NULL,
            utm_source VARCHAR(255) NULL,
            utm_medium VARCHAR(255) NULL,
            utm_campaign VARCHAR(255) NULL,
            visitor_name VARCHAR(255) NULL,
            visitor_email VARCHAR(255) NULL,
            rating TINYINT NULL,
            rating_comment TEXT NULL,
            woo_customer_id BIGINT UNSIGNED NULL,
            PRIMARY KEY  (session_id),
            UNIQUE KEY visitor_token (visitor_token),
            KEY woo_customer_id (woo_customer_id),
            KEY wp_user_id (wp_user_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql_sessions );

        $table_messages_name = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';
        $sql_messages = "CREATE TABLE $table_messages_name (
            message_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            sender_type VARCHAR(10) NOT NULL, -- 'visitor', 'agent', 'system'
            telegram_user_id BIGINT NULL,
            message_content TEXT NOT NULL,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            telegram_message_id BIGINT NULL,
            is_read BOOLEAN NOT NULL DEFAULT 0,
            page_url TEXT NULL,
            agent_wp_user_id BIGINT UNSIGNED NULL,
            PRIMARY KEY  (message_id),
            KEY session_id (session_id),
            KEY sender_type (sender_type),
            KEY telegram_message_id (telegram_message_id),
            KEY agent_wp_user_id (agent_wp_user_id)
        ) $charset_collate;";
        dbDelta( $sql_messages );
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $default_options = array(
            TLC_PLUGIN_PREFIX . 'bot_token' => '',
            TLC_PLUGIN_PREFIX . 'admin_user_ids' => '',
            TLC_PLUGIN_PREFIX . 'telegram_chat_id_group' => '',
            TLC_PLUGIN_PREFIX . 'enable_telegram_polling' => false,
            TLC_PLUGIN_PREFIX . 'polling_interval' => '30_seconds',
            TLC_PLUGIN_PREFIX . 'last_telegram_update_id' => 0,

            // Widget Customization Defaults
            TLC_PLUGIN_PREFIX . 'widget_header_bg_color' => '#0073aa',
            TLC_PLUGIN_PREFIX . 'widget_header_text_color' => '#ffffff',
            TLC_PLUGIN_PREFIX . 'chat_button_bg_color' => '#0088cc',
            TLC_PLUGIN_PREFIX . 'chat_button_icon_color' => '#ffffff',
            TLC_PLUGIN_PREFIX . 'visitor_msg_bg_color' => '#dcf8c6',
            TLC_PLUGIN_PREFIX . 'visitor_msg_text_color' => '#000000',
            TLC_PLUGIN_PREFIX . 'agent_msg_bg_color' => '#e0e0e0',
            TLC_PLUGIN_PREFIX . 'agent_msg_text_color' => '#000000',
            TLC_PLUGIN_PREFIX . 'widget_header_title' => 'Live Chat',
            TLC_PLUGIN_PREFIX . 'widget_welcome_message' => 'Welcome! How can we help you today?',
            TLC_PLUGIN_PREFIX . 'widget_offline_message' => "We're currently offline. Please leave a message!",
            TLC_PLUGIN_PREFIX . 'widget_position' => 'bottom_right',
            TLC_PLUGIN_PREFIX . 'widget_icon_shape' => 'circle',
            TLC_PLUGIN_PREFIX . 'widget_hide_desktop' => false,
            TLC_PLUGIN_PREFIX . 'widget_hide_mobile' => false,
            TLC_PLUGIN_PREFIX . 'widget_custom_css' => '',

            // Auto Message 1 Defaults
            TLC_PLUGIN_PREFIX . 'auto_msg_1_enable' => false,
            TLC_PLUGIN_PREFIX . 'auto_msg_1_text' => 'Hello! Can I help you with anything?',
            TLC_PLUGIN_PREFIX . 'auto_msg_1_trigger_type' => 'time_on_page',
            TLC_PLUGIN_PREFIX . 'auto_msg_1_trigger_value' => 30,
            TLC_PLUGIN_PREFIX . 'auto_msg_1_page_targeting' => 'all_pages',
            TLC_PLUGIN_PREFIX . 'auto_msg_1_specific_urls' => '',

            // Work Hours Defaults
            TLC_PLUGIN_PREFIX . 'work_hours' => array(
                'monday'    => array('is_open' => '1', 'open' => '09:00', 'close' => '17:00'),
                'tuesday'   => array('is_open' => '1', 'open' => '09:00', 'close' => '17:00'),
                'wednesday' => array('is_open' => '1', 'open' => '09:00', 'close' => '17:00'),
                'thursday'  => array('is_open' => '1', 'open' => '09:00', 'close' => '17:00'),
                'friday'    => array('is_open' => '1', 'open' => '09:00', 'close' => '17:00'),
                'saturday'  => array('is_open' => '0', 'open' => '09:00', 'close' => '17:00'),
                'sunday'    => array('is_open' => '0', 'open' => '09:00', 'close' => '17:00'),
            ),
            TLC_PLUGIN_PREFIX . 'offline_behavior' => 'show_offline_message',

            // File Upload Defaults
            TLC_PLUGIN_PREFIX . 'file_uploads_enable' => false,
            TLC_PLUGIN_PREFIX . 'file_uploads_allowed_types' => 'jpg,jpeg,png,gif,pdf,doc,docx,txt',
            TLC_PLUGIN_PREFIX . 'file_uploads_max_size_mb' => 2,

            // Rate Limiting Defaults
            TLC_PLUGIN_PREFIX . 'rate_limit_enable' => true,
            TLC_PLUGIN_PREFIX . 'rate_limit_threshold' => 5,
            TLC_PLUGIN_PREFIX . 'rate_limit_period_seconds' => 10,

            // Canned Responses Default
            TLC_PLUGIN_PREFIX . 'canned_responses' => array(),

            // Pre-chat Form Default
            TLC_PLUGIN_PREFIX . 'enable_pre_chat_form' => false,

            // Satisfaction Rating Default
            TLC_PLUGIN_PREFIX . 'enable_satisfaction_rating' => false,

            // Webhook Defaults
            TLC_PLUGIN_PREFIX . 'webhook_on_chat_start_url' => '',
            TLC_PLUGIN_PREFIX . 'webhook_on_new_visitor_message_url' => '',
            TLC_PLUGIN_PREFIX . 'webhook_on_new_agent_message_url' => '',
            TLC_PLUGIN_PREFIX . 'webhook_secret' => '',

            // Widget Display Mode Default
            TLC_PLUGIN_PREFIX . 'widget_display_mode' => 'floating',

            // Privacy & Consent Defaults
            TLC_PLUGIN_PREFIX . 'require_consent_for_chat' => false,
            TLC_PLUGIN_PREFIX . 'consent_localstorage_key' => 'user_cookie_consent',
            TLC_PLUGIN_PREFIX . 'consent_localstorage_value' => 'granted',

            // WooCommerce Integration Defaults
            TLC_PLUGIN_PREFIX . 'woo_enable_integration' => true, // Default to true if WC is active, but setting a value anyway
            TLC_PLUGIN_PREFIX . 'woo_orders_in_telegram' => true,
            TLC_PLUGIN_PREFIX . 'woo_orders_in_telegram_count' => 1,
            TLC_PLUGIN_PREFIX . 'woo_orders_in_admin_dash' => true,

            // Voice/Video Chat Defaults
            TLC_PLUGIN_PREFIX . 'voice_chat_enable' => false,
            TLC_PLUGIN_PREFIX . 'video_chat_enable' => false,
            TLC_PLUGIN_PREFIX . 'stun_turn_servers' => "stun:stun.l.google.com:19302\nstun:stun1.l.google.com:19302",

            TLC_PLUGIN_PREFIX . 'enable_cleanup_on_uninstall' => false,
            // Add more default options here as needed
        );

        foreach ($default_options as $option_name => $option_value) {
            if ( get_option( $option_name ) === false ) {
                update_option( $option_name, $option_value );
            }
        }
    }
}

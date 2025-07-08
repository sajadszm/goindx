<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      0.1.0
 *
 * @package    TLC
 * @subpackage TLC/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    TLC
 * @subpackage TLC/admin
 * @author     Your Name <email@example.com>
 */
class TLC_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Display the chat history page.
     *
     * @since    0.2.0
     */
    public function display_chat_history_page() {
        // Check if a specific session is being viewed
        $session_id_to_view = isset($_GET['action']) && $_GET['action'] === 'view_session' && isset($_GET['session_id'])
                              ? absint($_GET['session_id'])
                              : null;

        if ($session_id_to_view) {
            // Include a partial to display messages for a single session
            include_once( 'partials/tlc-admin-session-messages-display.php' );
        } else {
            // Include a partial to display the list of all sessions
            include_once( 'partials/tlc-admin-chat-history-display.php' );
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    0.1.0
     */
    public function enqueue_styles() {
        // wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/tlc-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    0.1.0
     */
    public function enqueue_scripts() {
        // wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/tlc-admin.js', array( 'jquery' ), $this->version, false );
    }

    /**
     * Register the settings page for the plugin.
     *
     * @since    0.1.0
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __( 'Telegram Live Chat', 'telegram-live-chat' ), // Page title
            __( 'Telegram Chat', 'telegram-live-chat' ),    // Menu title
            'manage_options',                               // Capability
            $this->plugin_name,                             // Menu slug
            array( $this, 'display_plugin_setup_page' ),    // Function to display the page
            'dashicons-format-chat',                        // Icon URL
            75                                              // Position
        );

        add_submenu_page(
            $this->plugin_name,                             // Parent slug
            __( 'Chat History', 'telegram-live-chat' ),     // Page title
            __( 'Chat History', 'telegram-live-chat' ),     // Menu title
            'manage_options',                               // Capability
            $this->plugin_name . '-chat-history',           // Menu slug
            array( $this, 'display_chat_history_page' )     // Function to display the page
        );
    }

    /**
     * Display the plugin setup page.
     *
     * @since    0.1.0
     */
    public function display_plugin_setup_page() {
        include_once( 'partials/tlc-admin-display.php' );
    }

    /**
     * Register the plugin settings.
     *
     * @since    0.1.0
     */
    public function register_settings() {
        // Settings Group
        $settings_group = TLC_PLUGIN_PREFIX . 'settings_group';

        // Register settings
        register_setting(
            $settings_group,                                // Option group
            TLC_PLUGIN_PREFIX . 'bot_token',                // Option name
            array( $this, 'sanitize_text_field' )         // Sanitize callback
        );

        register_setting(
            $settings_group,
            TLC_PLUGIN_PREFIX . 'admin_user_ids',
            array( $this, 'sanitize_agent_user_ids' ) // More specific sanitization for CSV of numbers
        );

        register_setting(
            $settings_group,
            TLC_PLUGIN_PREFIX . 'telegram_chat_id_group',
            array( $this, 'sanitize_text_field' ) // Group ID can be alphanumeric (e.g. @groupname or -100xxxx)
        );

        register_setting(
            $settings_group,
            TLC_PLUGIN_PREFIX . 'enable_cleanup_on_uninstall',
            array( $this, 'sanitize_checkbox' )
        );

        register_setting(
            $settings_group,
            TLC_PLUGIN_PREFIX . 'enable_telegram_polling',
            array( $this, 'sanitize_checkbox' )
        );

        register_setting(
            $settings_group,
            TLC_PLUGIN_PREFIX . 'polling_interval',
            array( $this, 'sanitize_polling_interval' )
        );

        // Settings Section
        add_settings_section(
            TLC_PLUGIN_PREFIX . 'telegram_api_section',     // ID
            __( 'Telegram Bot Settings', 'telegram-live-chat' ), // Title
            array( $this, 'render_telegram_api_section_info' ), // Callback
            $this->plugin_name                              // Page on which to show it (menu slug)
        );

        // Settings Fields
        add_settings_field(
            TLC_PLUGIN_PREFIX . 'bot_token',                // ID
            __( 'Bot Token', 'telegram-live-chat' ),        // Title
            array( $this, 'render_bot_token_field' ),       // Callback to render the field
            $this->plugin_name,                             // Page
            TLC_PLUGIN_PREFIX . 'telegram_api_section'      // Section
        );

        add_settings_field(
            TLC_PLUGIN_PREFIX . 'admin_user_ids',
            __( 'Agent Telegram User IDs (comma-separated)', 'telegram-live-chat' ),
            array( $this, 'render_admin_user_ids_field' ),
            $this->plugin_name,
            TLC_PLUGIN_PREFIX . 'telegram_api_section'
        );

        add_settings_field(
            TLC_PLUGIN_PREFIX . 'telegram_chat_id_group',
            __( 'Group Chat ID for Notifications (Optional)', 'telegram-live-chat' ),
            array( $this, 'render_telegram_chat_id_group_field' ),
            $this->plugin_name,
            TLC_PLUGIN_PREFIX . 'telegram_api_section'
        );

        // Section for Polling Settings
        add_settings_section(
            TLC_PLUGIN_PREFIX . 'telegram_polling_section',
            __( 'Telegram Polling Settings', 'telegram-live-chat' ),
            array( $this, 'render_telegram_polling_section_info' ),
            $this->plugin_name
        );

        add_settings_field(
            TLC_PLUGIN_PREFIX . 'enable_telegram_polling',
            __( 'Enable Telegram Polling', 'telegram-live-chat' ),
            array( $this, 'render_enable_telegram_polling_field' ),
            $this->plugin_name,
            TLC_PLUGIN_PREFIX . 'telegram_polling_section'
        );

        add_settings_field(
            TLC_PLUGIN_PREFIX . 'polling_interval',
            __( 'Polling Interval', 'telegram-live-chat' ),
            array( $this, 'render_polling_interval_field' ),
            $this->plugin_name,
            TLC_PLUGIN_PREFIX . 'telegram_polling_section'
        );

        // Section for Uninstall Settings
        add_settings_section(
            TLC_PLUGIN_PREFIX . 'general_settings_section',
            __( 'General Settings', 'telegram-live-chat' ),
            null, // No description callback needed for this section
            $this->plugin_name
        );

        add_settings_field(
            TLC_PLUGIN_PREFIX . 'enable_cleanup_on_uninstall',
            __( 'Data Cleanup on Uninstall', 'telegram-live-chat' ),
            array( $this, 'render_cleanup_on_uninstall_field' ),
            $this->plugin_name,
            TLC_PLUGIN_PREFIX . 'general_settings_section'
        );
    }

    /**
     * Sanitize text field.
     *
     * @param string $input The input string.
     * @return string Sanitized string.
     */
    public function sanitize_text_field( $input ) {
        return sanitize_text_field( $input );
    }

    /**
     * Sanitize checkbox.
     *
     * @param mixed $input The input value.
     * @return string '1' if checked, empty string otherwise.
     */
    public function sanitize_checkbox( $input ) {
        return ( isset( $input ) && $input == 1 ? '1' : '' );
    }

    /**
     * Render the description for the Telegram API section.
     */
    public function render_telegram_api_section_info() {
        echo '<p>' . __( 'Configure your Telegram Bot API token and the user IDs of the admins/agents who will receive messages.', 'telegram-live-chat' ) . '</p>';
        echo '<p>' . __( 'You can get your Bot Token by talking to BotFather on Telegram. You can get User IDs by talking to @userinfobot or similar.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Render the Bot Token input field.
     */
    public function render_bot_token_field() {
        $option_name = TLC_PLUGIN_PREFIX . 'bot_token';
        $value = get_option( $option_name );
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            esc_attr( $value )
        );
    }

    /**
     * Render the Admin User IDs input field.
     */
    public function render_admin_user_ids_field() {
        $option_name = TLC_PLUGIN_PREFIX . 'admin_user_ids';
        $value = get_option( $option_name );
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            esc_attr( $value )
        );
        echo '<p class="description">' . __( 'Enter comma-separated numeric Telegram User IDs for individual agents.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Render the Telegram Group Chat ID input field.
     */
    public function render_telegram_chat_id_group_field() {
        $option_name = TLC_PLUGIN_PREFIX . 'telegram_chat_id_group';
        $value = get_option( $option_name );
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            esc_attr( $value )
        );
        echo '<p class="description">' . __( 'Enter a Telegram Group Chat ID (e.g., -100123456789) or a public channel username (e.g. @yourchannel) where new chat notifications will be sent. Bot must be an admin in the group/channel.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Sanitize Agent User IDs field.
     * Ensures it's a comma-separated list of numeric IDs.
     *
     * @param string $input The input string.
     * @return string Sanitized string.
     */
    public function sanitize_agent_user_ids( $input ) {
        if ( empty( $input ) ) {
            return '';
        }
        $ids = explode( ',', $input );
        $sanitized_ids = array();
        foreach ( $ids as $id ) {
            $trimmed_id = trim( $id );
            if ( is_numeric( $trimmed_id ) ) { // Telegram User IDs are numeric
                $sanitized_ids[] = $trimmed_id;
            }
        }
        return implode( ',', array_unique( $sanitized_ids ) ); // Remove duplicates
    }

    /**
     * Render the Cleanup on Uninstall checkbox.
     */
    public function render_cleanup_on_uninstall_field() {
        $option_name = TLC_PLUGIN_PREFIX . 'enable_cleanup_on_uninstall';
        $checked = get_option( $option_name );
        printf(
            '<input type="checkbox" id="%s" name="%s" value="1" %s />',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            checked( $checked, '1', false )
        );
        echo '<label for="' . esc_attr( $option_name ) . '">' . __( 'Enable this to remove all plugin data (settings, chat history) when the plugin is uninstalled.', 'telegram-live-chat' ) . '</label>';
    }

    /**
     * Render the description for the Telegram Polling section.
     */
    public function render_telegram_polling_section_info() {
        echo '<p>' . __( 'Configure how the plugin fetches updates (agent replies) from Telegram. Requires WP Cron to be working correctly on your site.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Render the Enable Telegram Polling checkbox.
     */
    public function render_enable_telegram_polling_field() {
        $option_name = TLC_PLUGIN_PREFIX . 'enable_telegram_polling';
        $checked = get_option( $option_name );
        printf(
            '<input type="checkbox" id="%s" name="%s" value="1" %s />',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            checked( $checked, '1', false )
        );
        echo '<label for="' . esc_attr( $option_name ) . '">' . __( 'Enable polling to fetch agent messages from Telegram.', 'telegram-live-chat' ) . '</label>';
        echo '<p class="description">' . __( 'If disabled, the plugin will not automatically retrieve messages sent by agents in Telegram.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Render the Polling Interval select field.
     */
    public function render_polling_interval_field() {
        $option_name = TLC_PLUGIN_PREFIX . 'polling_interval';
        $current_value = get_option( $option_name, '30_seconds' ); // Default to 30 seconds if not set
        $intervals = $this->get_polling_intervals();

        echo "<select id='" . esc_attr( $option_name ) . "' name='" . esc_attr( $option_name ) . "'>";
        foreach ( $intervals as $value => $label ) {
            echo "<option value='" . esc_attr( $value ) . "' " . selected( $current_value, $value, false ) . ">" . esc_html( $label ) . "</option>";
        }
        echo "</select>";
        echo '<p class="description">' . __( 'How often should WordPress check Telegram for new messages? Shorter intervals are more real-time but increase server load.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Get available polling intervals.
     * @return array
     */
    public function get_polling_intervals() {
        // These will be used to define custom cron schedules
        return array(
            '15_seconds' => __( 'Every 15 seconds', 'telegram-live-chat' ),
            '30_seconds' => __( 'Every 30 seconds', 'telegram-live-chat' ),
            '60_seconds' => __( 'Every 1 minute', 'telegram-live-chat' ),
            '300_seconds' => __( 'Every 5 minutes', 'telegram-live-chat' ), // Default 'five_minutes' exists but good to have custom one
        );
    }

    /**
     * Sanitize polling interval.
     * @param string $input
     * @return string
     */
    public function sanitize_polling_interval( $input ) {
        $intervals = $this->get_polling_intervals();
        if ( array_key_exists( $input, $intervals ) ) {
            return $input;
        }
        return '30_seconds'; // Default if invalid input
    }
}

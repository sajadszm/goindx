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
            array( $this, 'sanitize_text_field' ) // Basic sanitization, might need more specific for CSV
        );

        register_setting(
            $settings_group,
            TLC_PLUGIN_PREFIX . 'enable_cleanup_on_uninstall',
            array( $this, 'sanitize_checkbox' )
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
            __( 'Admin User IDs (comma-separated)', 'telegram-live-chat' ),
            array( $this, 'render_admin_user_ids_field' ),
            $this->plugin_name,
            TLC_PLUGIN_PREFIX . 'telegram_api_section'
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
        echo '<p class="description">' . __( 'Enter comma-separated numeric Telegram User IDs.', 'telegram-live-chat' ) . '</p>';
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
}

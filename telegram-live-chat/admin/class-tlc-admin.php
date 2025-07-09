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

        add_submenu_page(
            $this->plugin_name,                             // Parent slug
            __( 'Chat Analytics', 'telegram-live-chat' ),   // Page title
            __( 'Chat Analytics', 'telegram-live-chat' ),   // Menu title
            'manage_options',                               // Capability
            $this->plugin_name . '-chat-analytics',         // Menu slug
            array( $this, 'display_chat_analytics_page' )   // Function to display the page
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

        // Section for Widget Customization
        add_settings_section(
            TLC_PLUGIN_PREFIX . 'widget_customization_section',
            __( 'Widget Customization', 'telegram-live-chat' ),
            array( $this, 'render_widget_customization_section_info' ),
            $this->plugin_name // Display on the main settings page
        );

        // Define color settings
        $color_settings = array(
            'widget_header_bg_color' => array('label' => __('Header Background Color', 'telegram-live-chat'), 'default' => '#0073aa'),
            'widget_header_text_color' => array('label' => __('Header Text Color', 'telegram-live-chat'), 'default' => '#ffffff'),
            'chat_button_bg_color' => array('label' => __('Chat Button Background Color', 'telegram-live-chat'), 'default' => '#0088cc'),
            'chat_button_icon_color' => array('label' => __('Chat Button Icon Color', 'telegram-live-chat'), 'default' => '#ffffff'),
            'visitor_msg_bg_color' => array('label' => __('Visitor Message Background', 'telegram-live-chat'), 'default' => '#dcf8c6'),
            'visitor_msg_text_color' => array('label' => __('Visitor Message Text', 'telegram-live-chat'), 'default' => '#000000'),
            'agent_msg_bg_color' => array('label' => __('Agent Message Background', 'telegram-live-chat'), 'default' => '#e0e0e0'),
            'agent_msg_text_color' => array('label' => __('Agent Message Text', 'telegram-live-chat'), 'default' => '#000000'),
        );

        foreach ($color_settings as $option_key => $details) {
            $full_option_name = TLC_PLUGIN_PREFIX . $option_key;
            register_setting(
                $settings_group,
                $full_option_name,
                array($this, 'sanitize_hex_color')
            );
            add_settings_field(
                $full_option_name,
                $details['label'],
                array($this, 'render_color_picker_field'),
                $this->plugin_name,
                TLC_PLUGIN_PREFIX . 'widget_customization_section',
                array(
                    'option_name' => $full_option_name,
                    'default'     => $details['default'],
                )
            );
        }

        // Text Options
        $text_options = array(
            'widget_header_title' => array('label' => __('Widget Header Title', 'telegram-live-chat'), 'type' => 'text', 'default' => 'Live Chat', 'sanitize_callback' => 'sanitize_text_field'),
            'widget_welcome_message' => array('label' => __('Welcome Message', 'telegram-live-chat'), 'type' => 'textarea', 'default' => 'Welcome! How can we help you today?', 'sanitize_callback' => 'sanitize_textarea_field'),
            'widget_offline_message' => array('label' => __('Offline Message', 'telegram-live-chat'), 'type' => 'textarea', 'default' => "We're currently offline. Please leave a message!", 'sanitize_callback' => 'sanitize_textarea_field'),
        );

        foreach ($text_options as $option_key => $details) {
            $full_option_name = TLC_PLUGIN_PREFIX . $option_key;
            register_setting($settings_group, $full_option_name, array($this, $details['sanitize_callback']));
            add_settings_field(
                $full_option_name, $details['label'], array($this, 'render_text_input_field'), $this->plugin_name,
                TLC_PLUGIN_PREFIX . 'widget_customization_section',
                array('option_name' => $full_option_name, 'default' => $details['default'], 'type' => $details['type'])
            );
        }

        // Display Options
        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'widget_position', array($this, 'sanitize_widget_position'));
        add_settings_field( TLC_PLUGIN_PREFIX . 'widget_position', __('Widget Position', 'telegram-live-chat'), array($this, 'render_select_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'widget_customization_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'widget_position', 'default' => 'bottom_right', 'options' => array('bottom_right' => 'Bottom Right', 'bottom_left' => 'Bottom Left'))
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'widget_icon_shape', array($this, 'sanitize_icon_shape'));
        add_settings_field( TLC_PLUGIN_PREFIX . 'widget_icon_shape', __('Chat Button Icon Shape', 'telegram-live-chat'), array($this, 'render_select_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'widget_customization_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'widget_icon_shape', 'default' => 'circle', 'options' => array('circle' => 'Circle', 'square' => 'Square'))
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'widget_hide_desktop', array($this, 'sanitize_checkbox'));
        add_settings_field( TLC_PLUGIN_PREFIX . 'widget_hide_desktop', __('Hide Chat Button on Desktop', 'telegram-live-chat'), array($this, 'render_checkbox_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'widget_customization_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'widget_hide_desktop', 'label_for_field' => __('Hide on desktop screens wider than 768px.', 'telegram-live-chat'))
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'widget_hide_mobile', array($this, 'sanitize_checkbox'));
        add_settings_field( TLC_PLUGIN_PREFIX . 'widget_hide_mobile', __('Hide Chat Button on Mobile', 'telegram-live-chat'), array($this, 'render_checkbox_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'widget_customization_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'widget_hide_mobile', 'label_for_field' => __('Hide on mobile screens narrower than 768px.', 'telegram-live-chat'))
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'widget_custom_css', array($this, 'sanitize_custom_css'));
        add_settings_field( TLC_PLUGIN_PREFIX . 'widget_custom_css', __('Custom CSS', 'telegram-live-chat'), array($this, 'render_textarea_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'widget_customization_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'widget_custom_css', 'default' => '', 'rows' => 5, 'description' => __('Add your own CSS rules for the chat widget. Use with caution.', 'telegram-live-chat'))
        );

        // Pre-chat Form Setting
        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'enable_pre_chat_form', array($this, 'sanitize_checkbox'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'enable_pre_chat_form', __('Enable Pre-chat Form', 'telegram-live-chat'), array($this, 'render_checkbox_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'widget_customization_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'enable_pre_chat_form', 'label_for_field' => __('Ask for visitor name and email before starting the chat.', 'telegram-live-chat'), 'description' => __('If enabled, visitors will be prompted for their name (required) and email (optional).', 'telegram-live-chat'))
        );

        // Satisfaction Rating Setting
        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'enable_satisfaction_rating', array($this, 'sanitize_checkbox'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'enable_satisfaction_rating', __('Enable Satisfaction Rating', 'telegram-live-chat'), array($this, 'render_checkbox_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'widget_customization_section', // Add to customization section
            array('option_name' => TLC_PLUGIN_PREFIX . 'enable_satisfaction_rating', 'label_for_field' => __('Allow visitors to rate the chat session.', 'telegram-live-chat'), 'description' => __('If enabled, an "End Chat" button will appear, allowing users to rate their experience.', 'telegram-live-chat'))
        );

        // Section for Automated Messages
        add_settings_section(
            TLC_PLUGIN_PREFIX . 'auto_messages_section',
            __( 'Automated Messages', 'telegram-live-chat' ),
            array( $this, 'render_auto_messages_section_info' ),
            $this->plugin_name
        );

        // Fields for the first (and only, for now) auto-message
        $auto_msg_prefix = TLC_PLUGIN_PREFIX . 'auto_msg_1_';

        register_setting($settings_group, $auto_msg_prefix . 'enable', array($this, 'sanitize_checkbox'));
        add_settings_field($auto_msg_prefix . 'enable', __('Enable Automated Message', 'telegram-live-chat'), array($this, 'render_checkbox_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'auto_messages_section',
            array('option_name' => $auto_msg_prefix . 'enable', 'label_for_field' => __('Enable this automated message', 'telegram-live-chat'))
        );

        register_setting($settings_group, $auto_msg_prefix . 'text', array($this, 'sanitize_textarea_field'));
        add_settings_field($auto_msg_prefix . 'text', __('Message Text', 'telegram-live-chat'), array($this, 'render_textarea_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'auto_messages_section',
            array('option_name' => $auto_msg_prefix . 'text', 'default' => 'Hello! Can I help you with anything?', 'rows' => 3)
        );

        register_setting($settings_group, $auto_msg_prefix . 'trigger_type', array($this, 'sanitize_auto_msg_trigger_type'));
        add_settings_field($auto_msg_prefix . 'trigger_type', __('Trigger Type', 'telegram-live-chat'), array($this, 'render_select_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'auto_messages_section',
            array('option_name' => $auto_msg_prefix . 'trigger_type', 'default' => 'time_on_page',
                  'options' => array('time_on_page' => 'Time on Page', 'scroll_depth' => 'Scroll Depth'))
        );

        register_setting($settings_group, $auto_msg_prefix . 'trigger_value', array($this, 'sanitize_absint'));
        add_settings_field($auto_msg_prefix . 'trigger_value', __('Trigger Value', 'telegram-live-chat'), array($this, 'render_text_input_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'auto_messages_section',
            array('option_name' => $auto_msg_prefix . 'trigger_value', 'default' => '30', 'type' => 'number', 'description' => __('Seconds for Time on Page, or % for Scroll Depth.', 'telegram-live-chat'))
        );

        register_setting($settings_group, $auto_msg_prefix . 'page_targeting', array($this, 'sanitize_page_targeting_type'));
        add_settings_field($auto_msg_prefix . 'page_targeting', __('Page Targeting', 'telegram-live-chat'), array($this, 'render_select_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'auto_messages_section',
            array('option_name' => $auto_msg_prefix . 'page_targeting', 'default' => 'all_pages',
                  'options' => array('all_pages' => 'All Pages', 'specific_urls' => 'Specific URL(s)'))
        );

        register_setting($settings_group, $auto_msg_prefix . 'specific_urls', array($this, 'sanitize_textarea_field')); // URLs will be comma-separated
        add_settings_field($auto_msg_prefix . 'specific_urls', __('Specific URLs (if selected)', 'telegram-live-chat'), array($this, 'render_textarea_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'auto_messages_section',
            array('option_name' => $auto_msg_prefix . 'specific_urls', 'default' => '', 'rows' => 3, 'description' => __('Enter full URLs, one per line or comma-separated. Only applies if "Specific URL(s)" is chosen for Page Targeting.', 'telegram-live-chat'))
        );

        // Section for Work Hours
        add_settings_section(
            TLC_PLUGIN_PREFIX . 'work_hours_section',
            __( 'Work Hours & Offline Mode', 'telegram-live-chat' ),
            array( $this, 'render_work_hours_section_info' ),
            $this->plugin_name
        );

        add_settings_field(
            TLC_PLUGIN_PREFIX . 'work_hours_timezone_display', // Not a saved option, just for display
            __( 'Site Timezone', 'telegram-live-chat'),
            array( $this, 'render_timezone_display_field'),
            $this->plugin_name,
            TLC_PLUGIN_PREFIX . 'work_hours_section'
        );

        // Work Hours settings for each day
        $days_of_week = array(
            'monday'    => __('Monday', 'telegram-live-chat'),
            'tuesday'   => __('Tuesday', 'telegram-live-chat'),
            'wednesday' => __('Wednesday', 'telegram-live-chat'),
            'thursday'  => __('Thursday', 'telegram-live-chat'),
            'friday'    => __('Friday', 'telegram-live-chat'),
            'saturday'  => __('Saturday', 'telegram-live-chat'),
            'sunday'    => __('Sunday', 'telegram-live-chat'),
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'work_hours', array($this, 'sanitize_work_hours'));

        foreach ($days_of_week as $day_key => $day_label) {
            add_settings_field(
                TLC_PLUGIN_PREFIX . 'work_hours_' . $day_key,
                $day_label,
                array($this, 'render_work_day_field'),
                $this->plugin_name,
                TLC_PLUGIN_PREFIX . 'work_hours_section',
                array('day_key' => $day_key)
            );
        }

        // Offline Behavior
        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'offline_behavior', array($this, 'sanitize_offline_behavior'));
        add_settings_field(
            TLC_PLUGIN_PREFIX . 'offline_behavior',
            __('Offline Behavior', 'telegram-live-chat'),
            array($this, 'render_select_field'),
            $this->plugin_name,
            TLC_PLUGIN_PREFIX . 'work_hours_section',
            array(
                'option_name' => TLC_PLUGIN_PREFIX . 'offline_behavior',
                'default'     => 'show_offline_message',
                'options'     => array(
                    'show_offline_message' => __('Show Offline Message in Widget', 'telegram-live-chat'),
                    'hide_widget'          => __('Hide Chat Widget Completely', 'telegram-live-chat'),
                ),
                'description' => __('Choose how the chat widget behaves when outside of work hours. The "Offline Message" itself is configured under Widget Customization.', 'telegram-live-chat')
            )
        );

        // Section for File Upload Settings
        add_settings_section(
            TLC_PLUGIN_PREFIX . 'file_uploads_section',
            __( 'File Upload Settings (Visitor to Agent)', 'telegram-live-chat' ),
            array( $this, 'render_file_uploads_section_info' ),
            $this->plugin_name
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'file_uploads_enable', array($this, 'sanitize_checkbox'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'file_uploads_enable', __('Enable File Uploads', 'telegram-live-chat'), array($this, 'render_checkbox_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'file_uploads_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'file_uploads_enable', 'label_for_field' => __('Allow visitors to upload files in the chat.', 'telegram-live-chat'))
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'file_uploads_allowed_types', array($this, 'sanitize_allowed_file_types'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'file_uploads_allowed_types', __('Allowed File Types', 'telegram-live-chat'), array($this, 'render_text_input_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'file_uploads_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'file_uploads_allowed_types', 'default' => 'jpg,jpeg,png,gif,pdf,doc,docx,txt', 'description' => __('Comma-separated list of allowed file extensions (e.g., jpg,png,pdf). Leave empty to allow all types permitted by WordPress.', 'telegram-live-chat'))
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'file_uploads_max_size_mb', array($this, 'sanitize_absint'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'file_uploads_max_size_mb', __('Max File Size (MB)', 'telegram-live-chat'), array($this, 'render_text_input_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'file_uploads_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'file_uploads_max_size_mb', 'default' => '2', 'type' => 'number', 'description' => __('Maximum file size in Megabytes allowed for upload. WordPress PHP limits also apply.', 'telegram-live-chat'))
        );

        // Section for Spam Protection / Rate Limiting
        add_settings_section(
            TLC_PLUGIN_PREFIX . 'spam_protection_section',
            __( 'Spam Protection', 'telegram-live-chat' ),
            array( $this, 'render_spam_protection_section_info' ),
            $this->plugin_name
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'rate_limit_enable', array($this, 'sanitize_checkbox'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'rate_limit_enable', __('Enable Message Rate Limiting', 'telegram-live-chat'), array($this, 'render_checkbox_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'spam_protection_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'rate_limit_enable', 'label_for_field' => __('Prevent users from sending too many messages in a short period.', 'telegram-live-chat'))
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'rate_limit_threshold', array($this, 'sanitize_absint'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'rate_limit_threshold', __('Rate Limit: Messages', 'telegram-live-chat'), array($this, 'render_text_input_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'spam_protection_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'rate_limit_threshold', 'default' => '5', 'type' => 'number', 'description' => __('Maximum number of messages allowed within the defined period.', 'telegram-live-chat'))
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'rate_limit_period_seconds', array($this, 'sanitize_absint'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'rate_limit_period_seconds', __('Rate Limit: Period (seconds)', 'telegram-live-chat'), array($this, 'render_text_input_field'), $this->plugin_name, TLC_PLUGIN_PREFIX . 'spam_protection_section',
            array('option_name' => TLC_PLUGIN_PREFIX . 'rate_limit_period_seconds', 'default' => '10', 'type' => 'number', 'description' => __('Time period in seconds for the message limit.', 'telegram-live-chat'))
        );

        // Section for Predefined Responses
        add_settings_section(
            TLC_PLUGIN_PREFIX . 'canned_responses_section',
            __( 'Predefined Responses', 'telegram-live-chat' ),
            array( $this, 'render_canned_responses_section_info' ),
            $this->plugin_name
        );

        register_setting($settings_group, TLC_PLUGIN_PREFIX . 'canned_responses', array($this, 'sanitize_canned_responses'));
        add_settings_field(
            TLC_PLUGIN_PREFIX . 'canned_responses_field', // Field ID
            __( 'Manage Responses', 'telegram-live-chat' ),      // Title
            array( $this, 'render_canned_responses_field' ),    // Callback
            $this->plugin_name,                                 // Page
            TLC_PLUGIN_PREFIX . 'canned_responses_section'      // Section
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
            array( $this, 'render_cleanup_on_uninstall_field' ), // This method exists
            $this->plugin_name,
            TLC_PLUGIN_PREFIX . 'general_settings_section'
        );
    }

    /**
     * Display the chat analytics page.
     *
     * @since    0.5.0
     */
    public function display_chat_analytics_page() {
        global $wpdb;
        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

        $total_chats = $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $messages_table");

        // Placeholder for average rating (will be implemented in next step)
        $average_rating_query = "SELECT AVG(rating) FROM $sessions_table WHERE rating IS NOT NULL AND rating > 0";
        $average_rating = $wpdb->get_var($average_rating_query);


        // Pass data to the view
        include_once( 'partials/tlc-admin-analytics-display.php' );
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

    /**
     * Render the description for the Widget Customization section.
     */
    public function render_widget_customization_section_info() {
        echo '<p>' . __( 'Customize the appearance and text of the chat widget.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Sanitize hex color.
     * @param string $color
     * @return string
     */
    public function sanitize_hex_color( $color ) {
        if ( '' === $color ) {
            return '';
        }
        // Check if string is a valid hex color.
        if ( preg_match( '/^#([a-f0-9]{6}|[a-f0-9]{3})$/i', $color ) ) {
            return $color;
        }
        return null; // Or return a default color, or add_settings_error()
    }

    /**
     * Render a color picker field.
     * @param array $args Field arguments.
     */
    public function render_color_picker_field( $args ) {
        $option_name = $args['option_name'];
        $default_color = $args['default'];
        $value = get_option( $option_name, $default_color );
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="tlc-color-picker" data-default-color="%s" />',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            esc_attr( $value ),
            esc_attr( $default_color )
        );
    }

    /**
     * Render a generic text input or textarea field.
     * @param array $args Field arguments.
     */
    public function render_text_input_field( $args ) {
        $option_name = $args['option_name'];
        $default_value = $args['default'];
        $type = isset($args['type']) && $args['type'] === 'textarea' ? 'textarea' : (isset($args['type']) ? $args['type'] : 'text');
        $value = get_option( $option_name, $default_value );

        if ($type === 'textarea') {
            $rows = isset($args['rows']) ? $args['rows'] : 3;
            printf(
                '<textarea id="%s" name="%s" rows="%d" class="large-text code">%s</textarea>',
                esc_attr( $option_name ),
                esc_attr( $option_name ),
                esc_attr( $rows ),
                esc_textarea( $value )
            );
        } else { // Handles text, number, etc.
            printf(
                '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" />',
                esc_attr($type),
                esc_attr( $option_name ),
                esc_attr( $option_name ),
                esc_attr( $value )
            );
        }
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Render a generic textarea field. (Specific for custom CSS if needed, or use render_text_input_field)
     * @param array $args Field arguments.
     */
    public function render_textarea_field( $args ) {
        $option_name = $args['option_name'];
        $default_value = $args['default'];
        $value = get_option( $option_name, $default_value );
        $rows = isset($args['rows']) ? $args['rows'] : 5;
         printf(
            '<textarea id="%s" name="%s" rows="%d" class="large-text code" placeholder="%s">%s</textarea>',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            esc_attr( $rows ),
            isset($args['placeholder']) ? esc_attr($args['placeholder']) : '',
            esc_textarea( $value )
        );
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }


    /**
     * Render a select field.
     * @param array $args Field arguments.
     */
    public function render_select_field( $args ) {
        $option_name = $args['option_name'];
        $default_value = $args['default'];
        $options = $args['options'];
        $current_value = get_option( $option_name, $default_value );

        echo "<select id='" . esc_attr( $option_name ) . "' name='" . esc_attr( $option_name ) . "'>";
        foreach ( $options as $value => $label ) {
            echo "<option value='" . esc_attr( $value ) . "' " . selected( $current_value, $value, false ) . ">" . esc_html( $label ) . "</option>";
        }
        echo "</select>";
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Render a checkbox field.
     * (Similar to render_cleanup_on_uninstall_field but more generic)
     * @param array $args Field arguments.
     */
    public function render_checkbox_field( $args ) {
        $option_name = $args['option_name'];
        $checked = get_option( $option_name, isset($args['default']) ? $args['default'] : '' );
        $label_for_field = isset($args['label_for_field']) ? $args['label_for_field'] : '';

        printf(
            '<input type="checkbox" id="%s" name="%s" value="1" %s />',
            esc_attr( $option_name ),
            esc_attr( $option_name ),
            checked( $checked, '1', false )
        );
        if ($label_for_field) {
            echo '<label for="' . esc_attr( $option_name ) . '"> ' . esc_html( $label_for_field ) . '</label>';
        }
         if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    // Sanitization functions for new types
    public function sanitize_widget_position( $input ) {
        $valid_options = array( 'bottom_right', 'bottom_left' );
        return in_array( $input, $valid_options, true ) ? $input : 'bottom_right';
    }

    public function sanitize_icon_shape( $input ) {
        $valid_options = array( 'circle', 'square' );
        return in_array( $input, $valid_options, true ) ? $input : 'circle';
    }

    public function sanitize_custom_css( $input ) {
        $input = wp_check_invalid_utf8( $input );
        $input = wp_strip_all_tags( $input );
        return $input;
    }

    /**
     * Render the description for the Automated Messages section.
     */
    public function render_auto_messages_section_info() {
        echo '<p>' . __( 'Configure a message to be automatically sent to visitors based on certain triggers. For now, one automated message can be configured.', 'telegram-live-chat' ) . '</p>';
    }

    public function sanitize_textarea_field( $input ) {
        return sanitize_textarea_field( $input );
    }

    public function sanitize_auto_msg_trigger_type( $input ) {
        $valid_options = array( 'time_on_page', 'scroll_depth' );
        return in_array( $input, $valid_options, true ) ? $input : 'time_on_page';
    }

    public function sanitize_absint( $input ) {
        return absint( $input );
    }

    public function sanitize_page_targeting_type( $input ) {
        $valid_options = array( 'all_pages', 'specific_urls' );
        return in_array( $input, $valid_options, true ) ? $input : 'all_pages';
    }

    /**
     * Render the description for the Work Hours section.
     */
    public function render_work_hours_section_info() {
        echo '<p>' . __( 'Define your support availability. The widget can change behavior based on these hours. Uses your WordPress site timezone.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Render the timezone display field.
     */
    public function render_timezone_display_field() {
        $timezone_string = get_option( 'timezone_string' );
        if ( empty( $timezone_string ) ) {
            $offset  = get_option( 'gmt_offset' );
            $timezone_string = sprintf('UTC%+d', $offset);
            if ($offset > 0 && floor($offset) != $offset) {
                 $timezone_string = sprintf('UTC%+0.1f', $offset);
            }
        }
        echo '<code>' . esc_html( $timezone_string ) . '</code>';
        echo '<p class="description">' . sprintf( __( 'This plugin uses your <a href="%s">WordPress timezone setting</a>. Current server time: %s.', 'telegram-live-chat' ), admin_url( 'options-general.php' ), current_time('mysql') . ' (' . $timezone_string . ')' ) . '</p>';
    }

    /**
     * Render work day field (checkbox, open time, close time).
     * @param array $args Field arguments containing 'day_key'.
     */
    public function render_work_day_field( $args ) {
        $day_key = $args['day_key'];
        $option_name_base = TLC_PLUGIN_PREFIX . 'work_hours';
        $work_hours = get_option( $option_name_base, array() );

        $is_open    = isset( $work_hours[$day_key]['is_open'] ) ? $work_hours[$day_key]['is_open'] : '0';
        $open_time  = isset( $work_hours[$day_key]['open'] ) ? $work_hours[$day_key]['open'] : '09:00';
        $close_time = isset( $work_hours[$day_key]['close'] ) ? $work_hours[$day_key]['close'] : '17:00';

        printf( '<input type="checkbox" name="%s[%s][is_open]" value="1" %s /> %s ',
            esc_attr($option_name_base), esc_attr($day_key), checked($is_open, '1', false), __('Open', 'telegram-live-chat')
        );

        echo __('From', 'telegram-live-chat') . ' ';
        echo $this->generate_time_select( $option_name_base . '[' . $day_key . '][open]', $open_time );

        echo ' ' . __('To', 'telegram-live-chat') . ' ';
        echo $this->generate_time_select( $option_name_base . '[' . $day_key . '][close]', $close_time );
    }

    /**
     * Generate HTML for a time select dropdown (HH:MM).
     * @param string $name The name attribute for the select.
     * @param string $current_value The current HH:MM value.
     * @return string HTML select element.
     */
    private function generate_time_select( $name, $current_value ) {
        $html = "<select name='" . esc_attr( $name ) . "'>";
        for ( $h = 0; $h < 24; $h++ ) {
            for ( $m = 0; $m < 60; $m += 15 ) {
                $time_val = sprintf( '%02d:%02d', $h, $m );
                $html .= "<option value='" . esc_attr( $time_val ) . "' " . selected( $current_value, $time_val, false ) . ">" . esc_html( $time_val ) . "</option>";
            }
        }
        $html .= "</select>";
        return $html;
    }

    /**
     * Sanitize work hours array.
     * @param array $input
     * @return array
     */
    public function sanitize_work_hours( $input ) {
        $sanitized_hours = array();
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $time_regex = '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/';

        foreach ($days as $day) {
            if (isset($input[$day])) {
                $sanitized_hours[$day]['is_open'] = isset($input[$day]['is_open']) ? '1' : '0';
                $sanitized_hours[$day]['open'] = (isset($input[$day]['open']) && preg_match($time_regex, $input[$day]['open'])) ? $input[$day]['open'] : '09:00';
                $sanitized_hours[$day]['close'] = (isset($input[$day]['close']) && preg_match($time_regex, $input[$day]['close'])) ? $input[$day]['close'] : '17:00';
            } else {
                 $sanitized_hours[$day]['is_open'] = '0';
                 $sanitized_hours[$day]['open'] = '09:00';
                 $sanitized_hours[$day]['close'] = '17:00';
            }
        }
        return $sanitized_hours;
    }

    /**
     * Sanitize offline behavior setting.
     * @param string $input
     * @return string
     */
    public function sanitize_offline_behavior( $input ) {
        $valid_options = array( 'show_offline_message', 'hide_widget' );
        return in_array( $input, $valid_options, true ) ? $input : 'show_offline_message';
    }

    /**
     * Render the description for the File Uploads section.
     */
    public function render_file_uploads_section_info() {
        echo '<p>' . __( 'Configure file upload settings for visitors. Ensure your server has appropriate permissions and upload limits.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Sanitize allowed file types string (comma-separated list of extensions).
     * @param string $input
     * @return string
     */
    public function sanitize_allowed_file_types( $input ) {
        if (empty($input)) {
            return '';
        }
        $types = explode( ',', $input );
        $sanitized_types = array();
        foreach ( $types as $type ) {
            $trimmed_type = trim( strtolower( $type ) );
            $sanitized_type = preg_replace( '/[^a-z0-9]/', '', $trimmed_type );
            if ( !empty($sanitized_type) ) {
                $sanitized_types[] = $sanitized_type;
            }
        }
        return implode( ',', array_unique( $sanitized_types ) );
    }

    /**
     * Render the description for the Spam Protection section.
     */
    public function render_spam_protection_section_info() {
        echo '<p>' . __( 'Basic spam protection measures for chat messages.', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Render the description for the Predefined Responses section.
     */
    public function render_canned_responses_section_info() {
        echo '<p>' . __( 'Create and manage predefined responses that agents can quickly use. Each response needs a unique shortcut (e.g., /greeting, !policy).', 'telegram-live-chat' ) . '</p>';
    }

    /**
     * Render the field for managing canned responses.
     */
    public function render_canned_responses_field() {
        $option_name = TLC_PLUGIN_PREFIX . 'canned_responses';
        $responses = get_option( $option_name, array() );
        if (empty($responses)) {
            $responses[] = array('shortcut' => '', 'message' => '');
        }
        ?>
        <div id="tlc-canned-responses-container">
            <?php foreach ( $responses as $index => $response ) : ?>
                <div class="tlc-canned-response-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">
                    <p>
                        <label for="<?php echo esc_attr( $option_name . '[' . $index . '][shortcut]' ); ?>"><?php esc_html_e( 'Shortcut:', 'telegram-live-chat' ); ?></label><br/>
                        <input type="text"
                               id="<?php echo esc_attr( $option_name . '[' . $index . '][shortcut]' ); ?>"
                               name="<?php echo esc_attr( $option_name . '[' . $index . '][shortcut]' ); ?>"
                               value="<?php echo esc_attr( $response['shortcut'] ?? '' ); ?>"
                               class="regular-text"
                               placeholder="/shortcut"/>
                    </p>
                    <p>
                        <label for="<?php echo esc_attr( $option_name . '[' . $index . '][message]' ); ?>"><?php esc_html_e( 'Message:', 'telegram-live-chat' ); ?></label><br/>
                        <textarea id="<?php echo esc_attr( $option_name . '[' . $index . '][message]' ); ?>"
                                  name="<?php echo esc_attr( $option_name . '[' . $index . '][message]' ); ?>"
                                  rows="3"
                                  class="large-text code"><?php echo esc_textarea( $response['message'] ?? '' ); ?></textarea>
                    </p>
                    <button type="button" class="button tlc-remove-canned-response"><?php esc_html_e( 'Remove', 'telegram-live-chat' ); ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="tlc-add-canned-response" class="button button-secondary"><?php esc_html_e( 'Add New Response', 'telegram-live-chat' ); ?></button>
        <p class="description"><?php esc_html_e('Click "Add New Response" to add more. Remember to save changes.', 'telegram-live-chat'); ?></p>

        <!-- Template for new responses (hidden) -->
        <div id="tlc-canned-response-template" style="display:none;">
            <div class="tlc-canned-response-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">
                <p>
                    <label for=""><?php esc_html_e( 'Shortcut:', 'telegram-live-chat' ); ?></label><br/>
                    <input type="text" name="" value="" class="regular-text" placeholder="/shortcut"/>
                </p>
                <p>
                    <label for=""><?php esc_html_e( 'Message:', 'telegram-live-chat' ); ?></label><br/>
                    <textarea name="" rows="3" class="large-text code"></textarea>
                </p>
                <button type="button" class="button tlc-remove-canned-response"><?php esc_html_e( 'Remove', 'telegram-live-chat' ); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * Sanitize canned responses array.
     * @param array $input
     * @return array
     */
    public function sanitize_canned_responses( $input ) {
        $sanitized_responses = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $response_data ) {
                if ( is_array( $response_data ) && !empty( $response_data['shortcut'] ) && !empty( $response_data['message'] ) ) {
                    $sanitized_responses[] = array(
                        'shortcut' => sanitize_text_field( $response_data['shortcut'] ),
                        'message'  => sanitize_textarea_field( $response_data['message'] ),
                    );
                }
            }
        }
        return array_slice($sanitized_responses, 0, 10);
    }


    /**
     * Enqueue styles and scripts for Admin Settings pages.
     *
     * @since 0.3.0 (renamed in 0.4.0)
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_settings_scripts( $hook_suffix ) {
        if ( 'toplevel_page_' . $this->plugin_name !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script(
            $this->plugin_name . '-admin-color-picker',
            plugin_dir_url( __FILE__ ) . 'js/tlc-admin-color-picker.js',
            array( 'wp-color-picker', 'jquery' ),
            $this->version,
            true
        );

        wp_enqueue_script(
            $this->plugin_name . '-admin-canned-responses',
            plugin_dir_url( __FILE__ ) . 'js/tlc-admin-canned-responses.js',
            array( 'jquery' ),
            $this->version,
            true
        );
        wp_localize_script(
            $this->plugin_name . '-admin-canned-responses',
            'tlc_plugin_prefix',
            TLC_PLUGIN_PREFIX
        );

    }
}

[end of telegram-live-chat/admin/class-tlc-admin.php]

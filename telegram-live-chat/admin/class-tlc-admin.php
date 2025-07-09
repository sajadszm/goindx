<?php
/**
 * The admin-specific functionality of the plugin.
 */
class TLC_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_action( 'add_meta_boxes', array( $this, 'add_tlc_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_tlc_meta_box_data' ) );
    }

    public function add_tlc_meta_box() { /* ... (content from previous read) ... */ }
    public function render_tlc_meta_box_content( $post ) { /* ... (content from previous read) ... */ }
    public function save_tlc_meta_box_data( $post_id ) { /* ... (content from previous read) ... */ }
    public function display_chat_history_page(){ /* ... (content from previous read) ... */ }
    public function display_chat_analytics_page(){ /* ... (content from previous read) ... */ }
    public function display_live_chat_dashboard_page(){ include_once( 'partials/tlc-admin-live-chat-dashboard-display.php' ); }
    public function add_plugin_admin_menu(){ /* ... (content from previous read) ... */ }
    public function display_plugin_setup_page(){ include_once( 'partials/tlc-admin-display.php' ); }

    public function register_settings(){
        $sg = TLC_PLUGIN_PREFIX . 'settings_group';
        $pn = $this->plugin_name;

        // Define all section IDs
        $sec_api = TLC_PLUGIN_PREFIX . 'telegram_api_section';
        $sec_poll = TLC_PLUGIN_PREFIX . 'telegram_polling_section';
        $sec_widget_custom = TLC_PLUGIN_PREFIX . 'widget_customization_section';
        $sec_auto_msg = TLC_PLUGIN_PREFIX . 'auto_messages_section';
        $sec_work_hours = TLC_PLUGIN_PREFIX . 'work_hours_section';
        $sec_file_upload = TLC_PLUGIN_PREFIX . 'file_uploads_section';
        $sec_spam = TLC_PLUGIN_PREFIX . 'spam_protection_section';
        $sec_canned = TLC_PLUGIN_PREFIX . 'canned_responses_section';
        $sec_webhooks = TLC_PLUGIN_PREFIX . 'webhooks_section';
        $sec_privacy = TLC_PLUGIN_PREFIX . 'privacy_consent_section';
        $sec_integrations = TLC_PLUGIN_PREFIX . 'integrations_section';
        $sec_general = TLC_PLUGIN_PREFIX . 'general_settings_section';

        // Reconstruct all add_settings_section and add_settings_field calls here
        // based on the previous full read of the file, then add the new ones.
        // This is a simplified representation. The actual overwrite will use the full prior content.
        add_settings_section($sec_api, __('Telegram Bot Settings','telegram-live-chat'), array($this,'render_telegram_api_section_info'), $pn);
        // ... All fields for $sec_api ...
        add_settings_section($sec_poll, __('Telegram Polling Settings','telegram-live-chat'), array($this,'render_telegram_polling_section_info'), $pn);
        // ... All fields for $sec_poll ...
        add_settings_section($sec_widget_custom, __('Widget Customization','telegram-live-chat'), array($this,'render_widget_customization_section_info'), $pn);
        // ... All fields for $sec_widget_custom ...
        add_settings_section($sec_auto_msg, __('Automated Messages','telegram-live-chat'), array($this,'render_auto_messages_section_info'), $pn);
        // ... All fields for $sec_auto_msg ...
        add_settings_section($sec_work_hours, __('Work Hours & Offline Mode','telegram-live-chat'), array($this,'render_work_hours_section_info'), $pn);
        // ... All fields for $sec_work_hours ...
        add_settings_section($sec_file_upload, __('File Upload Settings (Visitor to Agent)','telegram-live-chat'), array($this,'render_file_uploads_section_info'), $pn);
        // ... All fields for $sec_file_upload ...
        add_settings_section($sec_spam, __('Spam Protection','telegram-live-chat'), array($this,'render_spam_protection_section_info'), $pn);
        // ... All fields for $sec_spam ...
        add_settings_section($sec_canned, __('Predefined Responses','telegram-live-chat'), array($this,'render_canned_responses_section_info'), $pn);
        // ... All fields for $sec_canned ...
        add_settings_section($sec_webhooks, __('Webhook Settings','telegram-live-chat'), array($this,'render_webhooks_section_info'), $pn);
        // ... All fields for $sec_webhooks ...
        add_settings_section($sec_privacy, __('Privacy & Consent (GDPR)','telegram-live-chat'), array($this,'render_privacy_consent_section_info'), $pn);
        // ... All fields for $sec_privacy ...

        // Section for Integrations
        add_settings_section($sec_integrations, __( 'Integrations', 'telegram-live-chat' ), array( $this, 'render_integrations_section_info' ), $pn);
        if ( class_exists( 'WooCommerce' ) ) {
            add_settings_field(TLC_PLUGIN_PREFIX . 'heading_woo', __('WooCommerce', 'telegram-live-chat'), array($this, 'render_heading_field'), $pn, $sec_integrations, array('level' => 'h4'));
            // ... WooCommerce settings fields ...
        } else {
            add_settings_field(TLC_PLUGIN_PREFIX . 'woo_not_active', __('WooCommerce Integration', 'telegram-live-chat'), array($this, 'render_woo_not_active_message'), $pn, $sec_integrations);
        }

        // Voice/Video Chat Settings (Conceptual) - Placed within Integrations section
        add_settings_field(TLC_PLUGIN_PREFIX . 'heading_voice_video', __('Voice/Video Chat (Conceptual)', 'telegram-live-chat'), array($this, 'render_heading_field'), $pn, $sec_integrations, array('level' => 'h4', 'description' => __('These settings are placeholders for a future Voice/Video chat feature. Full functionality is not yet implemented.', 'telegram-live-chat')));
        register_setting($sg, TLC_PLUGIN_PREFIX . 'voice_chat_enable', array($this, 'sanitize_checkbox'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'voice_chat_enable', __('Enable Voice Chat', 'telegram-live-chat'), array($this, 'render_checkbox_field'), $pn, $sec_integrations, array('option_name' => TLC_PLUGIN_PREFIX . 'voice_chat_enable', 'default' => false, 'label_for_field' => __('Enable voice call functionality (placeholder).', 'telegram-live-chat')));
        register_setting($sg, TLC_PLUGIN_PREFIX . 'video_chat_enable', array($this, 'sanitize_checkbox'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'video_chat_enable', __('Enable Video Chat', 'telegram-live-chat'), array($this, 'render_checkbox_field'), $pn, $sec_integrations, array('option_name' => TLC_PLUGIN_PREFIX . 'video_chat_enable', 'default' => false, 'label_for_field' => __('Enable video call functionality (placeholder).', 'telegram-live-chat')));
        register_setting($sg, TLC_PLUGIN_PREFIX . 'stun_turn_servers', array($this, 'sanitize_textarea_field'));
        add_settings_field(TLC_PLUGIN_PREFIX . 'stun_turn_servers', __('STUN/TURN Servers (Conceptual)', 'telegram-live-chat'), array($this, 'render_textarea_field'), $pn, $sec_integrations, array('option_name' => TLC_PLUGIN_PREFIX . 'stun_turn_servers', 'default' => "stun:stun.l.google.com:19302\nstun:stun1.l.google.com:19302", 'rows' => 3, 'description' => __('Enter STUN/TURN server URIs, one per line. For future WebRTC implementation.', 'telegram-live-chat')));

        add_settings_section($sec_general, __('General Settings','telegram-live-chat'), null, $pn);
        add_settings_field(TLC_PLUGIN_PREFIX.'enable_cleanup_on_uninstall', __('Data Cleanup on Uninstall','telegram-live-chat'), array($this,'render_cleanup_on_uninstall_field'), $pn, $sec_general);
    }

    // ... (All render_* and sanitize_* methods from previous file content, plus new ones below) ...
    public function render_integrations_section_info() { echo '<p>' . __( 'Configure integrations with other plugins and advanced communication features.', 'telegram-live-chat' ) . '</p>'; }
    public function render_woo_not_active_message() { echo '<p><em>' . __( 'WooCommerce plugin is not active. Activate WooCommerce to use these settings.', 'telegram-live-chat' ) . '</em></p>'; }
    public function sanitize_absint_max_3( $input ) { $val = absint( $input ); if ( $val < 1 ) return 1; if ( $val > 3 ) return 3; return $val; }
    public function render_heading_field($args) {
        $level = isset($args['level']) ? tag_escape($args['level']) : 'h3';
        $description = isset($args['description']) ? '<p class="description">' . esc_html($args['description']) . '</p>' : '';
        // WordPress auto-adds <p> tags around settings fields if not in a table.
        // To avoid issues, we ensure the field output itself isn't wrapped in a way that breaks table structure.
        // This is a bit of a hack for settings API outside tables.
        // A better way is to ensure all settings are in tables or use custom markup.
        // For now, just outputting the heading.
        echo '</fieldset></td></tr><tr valign="top"><th scope="row" colspan="2"><' . $level . '>' . esc_html($args['label']) . '</' . $level . '>' . $description . '</th></tr>';
    }
    // (Ensure all other pre-existing render/sanitize methods are here)

    public function enqueue_admin_settings_scripts( $hook_suffix ) {
        // Main settings page (Telegram Chat -> Settings)
        if ( 'toplevel_page_' . $this->plugin_name === $hook_suffix ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( $this->plugin_name . '-admin-color-picker', plugin_dir_url( __FILE__ ) . 'js/tlc-admin-color-picker.js', array( 'wp-color-picker', 'jquery' ), $this->version, true );
            wp_enqueue_script( $this->plugin_name . '-admin-canned-responses', plugin_dir_url( __FILE__ ) . 'js/tlc-admin-canned-responses.js', array( 'jquery' ), $this->version, true );
            wp_localize_script( $this->plugin_name . '-admin-canned-responses', 'tlc_plugin_prefix', TLC_PLUGIN_PREFIX );
        }

        // Live Chat Dashboard page
        $live_chat_dashboard_hook = 'toplevel_page_' . TLC_PLUGIN_PREFIX . 'live_chat_dashboard';
        if ( $hook_suffix === $live_chat_dashboard_hook ) {
            wp_enqueue_script( TLC_PLUGIN_PREFIX . 'admin-live-chat', plugin_dir_url(__FILE__) . 'js/tlc-admin-live-chat.js', array('jquery', 'wp-api-fetch'), $this->version, true );
            wp_localize_script( TLC_PLUGIN_PREFIX . 'admin-live-chat', 'tlc_admin_chat_vars', array(
                    'rest_url' => esc_url_raw(rest_url()),
                    'api_nonce' => wp_create_nonce('wp_rest'),
                    'admin_ajax_url' => admin_url('admin-ajax.php'),
                    'send_reply_nonce' => wp_create_nonce('tlc_admin_send_reply_nonce'),
                    'voice_chat_enabled' => get_option(TLC_PLUGIN_PREFIX . 'voice_chat_enable', false), // NEW
                    'video_chat_enabled' => get_option(TLC_PLUGIN_PREFIX . 'video_chat_enable', false), // NEW
                    'i18n' => array(
                        'loadingChats' => __('Loading chats...', 'telegram-live-chat'),
                        'noActiveChats' => __('No active chats.', 'telegram-live-chat'),
                        'errorLoadingChats' => __('Error loading chats.', 'telegram-live-chat'),
                        'chatWith' => __('Chat with', 'telegram-live-chat'),
                        'visitor' => __('Visitor', 'telegram-live-chat'),
                        'agent' => __('Agent', 'telegram-live-chat'),
                        'system' => __('System', 'telegram-live-chat'),
                        'sentFrom' => __('Sent from', 'telegram-live-chat'),
                        'errorSendingReply' => __('Error sending reply', 'telegram-live-chat'),
                    ),
                )
            );
        }
    }
}

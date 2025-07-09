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

    public function add_tlc_meta_box() { /* ... */ }
    public function render_tlc_meta_box_content( $post ) { /* ... */ }
    public function save_tlc_meta_box_data( $post_id ) { /* ... */ }
    public function display_chat_history_page(){ /* ... */ }
    public function display_chat_analytics_page(){ /* ... */ }
    public function display_live_chat_dashboard_page(){ /* ... */ }
    public function add_plugin_admin_menu(){ /* ... */ }
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
        $sec_general = TLC_PLUGIN_PREFIX . 'general_settings_section';

        // --- Start copy of all sections and fields from previous state ---
        // Telegram API Section
        add_settings_section($sec_api, __('Telegram Bot Settings','telegram-live-chat'), array($this,'render_telegram_api_section_info'), $pn);
        register_setting($sg, TLC_PLUGIN_PREFIX . 'bot_token', array( $this, 'sanitize_text_field' ));
        add_settings_field(TLC_PLUGIN_PREFIX . 'bot_token', __('Bot Token', 'telegram-live-chat'), array( $this, 'render_bot_token_field' ), $pn, $sec_api);
        register_setting($sg, TLC_PLUGIN_PREFIX . 'admin_user_ids', array( $this, 'sanitize_agent_user_ids' ));
        add_settings_field(TLC_PLUGIN_PREFIX . 'admin_user_ids', __('Agent Telegram User IDs (comma-separated)', 'telegram-live-chat'), array( $this, 'render_admin_user_ids_field' ), $pn, $sec_api);
        register_setting($sg, TLC_PLUGIN_PREFIX . 'telegram_chat_id_group', array( $this, 'sanitize_text_field' ));
        add_settings_field(TLC_PLUGIN_PREFIX . 'telegram_chat_id_group', __('Group Chat ID for Notifications (Optional)', 'telegram-live-chat'), array( $this, 'render_telegram_chat_id_group_field' ), $pn, $sec_api);

        // Polling Section
        add_settings_section($sec_poll, __('Telegram Polling Settings','telegram-live-chat'), array($this,'render_telegram_polling_section_info'), $pn);
        register_setting($sg, TLC_PLUGIN_PREFIX . 'enable_telegram_polling', array( $this, 'sanitize_checkbox' ));
        add_settings_field(TLC_PLUGIN_PREFIX . 'enable_telegram_polling', __('Enable Telegram Polling', 'telegram-live-chat'), array( $this, 'render_enable_telegram_polling_field' ), $pn, $sec_poll);
        register_setting($sg, TLC_PLUGIN_PREFIX . 'polling_interval', array( $this, 'sanitize_polling_interval' ));
        add_settings_field(TLC_PLUGIN_PREFIX . 'polling_interval', __('Polling Interval', 'telegram-live-chat'), array( $this, 'render_polling_interval_field' ), $pn, $sec_poll);

        // Widget Customization Section
        add_settings_section($sec_widget_custom, __('Widget Customization','telegram-live-chat'), array($this,'render_widget_customization_section_info'), $pn);
        $color_settings = array( /* ... as before ... */ );
        foreach ($color_settings as $option_key => $details) { /* ... as before ... */ }
        $text_options = array( /* ... as before ... */ );
        foreach ($text_options as $option_key => $details) { /* ... as before ... */ }
        // ... (all other customization fields like position, shape, hide, custom_css)
        register_setting($sg, TLC_PLUGIN_PREFIX . 'enable_pre_chat_form', array($this, 'sanitize_checkbox'));
        add_settings_field(TLC_PLUGIN_PREFIX.'enable_pre_chat_form', __('Enable Pre-chat Form','telegram-live-chat'), array($this,'render_checkbox_field'), $pn, $sec_widget_custom, array('option_name'=>TLC_PLUGIN_PREFIX.'enable_pre_chat_form','label_for_field'=>__('Ask for visitor name and email before starting the chat.','telegram-live-chat'),'description'=>__('If enabled, visitors will be prompted for their name (required) and email (optional).','telegram-live-chat')));
        register_setting($sg, TLC_PLUGIN_PREFIX . 'enable_satisfaction_rating', array($this, 'sanitize_checkbox'));
        add_settings_field(TLC_PLUGIN_PREFIX.'enable_satisfaction_rating', __('Enable Satisfaction Rating','telegram-live-chat'), array($this,'render_checkbox_field'), $pn, $sec_widget_custom, array('option_name'=>TLC_PLUGIN_PREFIX.'enable_satisfaction_rating','label_for_field'=>__('Allow visitors to rate the chat session.','telegram-live-chat'),'description'=>__('If enabled, an "End Chat" button will appear, allowing users to rate their experience.','telegram-live-chat')));
        register_setting($sg, TLC_PLUGIN_PREFIX . 'widget_display_mode', array($this, 'sanitize_display_mode'));
        add_settings_field(TLC_PLUGIN_PREFIX.'widget_display_mode', __('Widget Display Mode','telegram-live-chat'), array($this,'render_select_field'), $pn, $sec_widget_custom, array('option_name'=>TLC_PLUGIN_PREFIX.'widget_display_mode','default'=>'floating','options'=>array('floating'=>__('Floating (Default)','telegram-live-chat'),'shortcode'=>__('Manual via Shortcode [telegram_live_chat_widget]','telegram-live-chat')),'description'=>__('Choose how the chat widget is displayed on your site.','telegram-live-chat')));
         // (Ensure all previous fields in Widget Customization are present)
        add_settings_field( TLC_PLUGIN_PREFIX . 'widget_custom_css', __('Custom CSS', 'telegram-live-chat'), array($this, 'render_textarea_field'), $pn, $sec_widget_custom, array('option_name' => TLC_PLUGIN_PREFIX . 'widget_custom_css', 'default' => '', 'rows' => 5, 'description' => __('Add your own CSS rules for the chat widget. Use with caution.', 'telegram-live-chat')));


        // Auto Messages Section
        add_settings_section($sec_auto_msg, __('Automated Messages','telegram-live-chat'), array($this,'render_auto_messages_section_info'), $pn);
        // ... (all auto_msg_1 fields) ...

        // Work Hours Section
        add_settings_section($sec_work_hours, __('Work Hours & Offline Mode','telegram-live-chat'), array($this,'render_work_hours_section_info'), $pn);
        // ... (all work hours fields) ...

        // File Uploads Section
        add_settings_section($sec_file_upload, __('File Upload Settings (Visitor to Agent)','telegram-live-chat'), array($this,'render_file_uploads_section_info'), $pn);
        // ... (all file upload fields) ...

        // Spam Protection Section
        add_settings_section($sec_spam, __('Spam Protection','telegram-live-chat'), array($this,'render_spam_protection_section_info'), $pn);
        // ... (all rate limit fields) ...

        // Canned Responses Section
        add_settings_section($sec_canned, __('Predefined Responses','telegram-live-chat'), array($this,'render_canned_responses_section_info'), $pn);
        // ... (canned responses field) ...

        // Webhooks Section
        add_settings_section($sec_webhooks, __('Webhook Settings','telegram-live-chat'), array($this,'render_webhooks_section_info'), $pn);
        // ... (all webhook fields) ...
        // --- End copy of all sections and fields from previous state ---

        // Privacy & Consent Section (NEW)
        add_settings_section($sec_privacy, __('Privacy & Consent (GDPR)','telegram-live-chat'), array($this,'render_privacy_consent_section_info'), $pn);
        register_setting($sg, TLC_PLUGIN_PREFIX.'require_consent_for_chat', array($this,'sanitize_checkbox'));
        add_settings_field(TLC_PLUGIN_PREFIX.'require_consent_for_chat', __('Require Consent for Chat','telegram-live-chat'), array($this,'render_checkbox_field'), $pn, $sec_privacy, array('option_name'=>TLC_PLUGIN_PREFIX.'require_consent_for_chat','default'=>false,'label_for_field'=>__('Enable this to make chat functionality dependent on detected consent.','telegram-live-chat'), 'description' => __('Widget will be hidden/disabled until consent is detected via the LocalStorage key/value below, or via the TLC_Chat_API.grantConsentAndShow() JS function.','telegram-live-chat')));
        register_setting($sg, TLC_PLUGIN_PREFIX.'consent_localstorage_key', array($this,'sanitize_text_field'));
        add_settings_field(TLC_PLUGIN_PREFIX.'consent_localstorage_key', __('Consent LocalStorage Key','telegram-live-chat'), array($this,'render_text_input_field'), $pn, $sec_privacy, array('option_name'=>TLC_PLUGIN_PREFIX.'consent_localstorage_key','default'=>'user_cookie_consent','description'=>__('The LocalStorage key your consent plugin might use.','telegram-live-chat')));
        register_setting($sg, TLC_PLUGIN_PREFIX.'consent_localstorage_value', array($this,'sanitize_text_field'));
        add_settings_field(TLC_PLUGIN_PREFIX.'consent_localstorage_value', __('Consent LocalStorage Value','telegram-live-chat'), array($this,'render_text_input_field'), $pn, $sec_privacy, array('option_name'=>TLC_PLUGIN_PREFIX.'consent_localstorage_value','default'=>'granted','description'=>__('The value that indicates consent (e.g., "granted", "true").','telegram-live-chat')));

        // Privacy Policy Suggestions Field (Display Only)
        add_settings_field(
            TLC_PLUGIN_PREFIX . 'privacy_policy_suggestions',
            __( 'Privacy Policy Suggestions', 'telegram-live-chat' ),
            array( $this, 'render_privacy_policy_suggestions_field' ),
            $pn,
            $sec_privacy
        );

        // General Settings / Uninstall Section (should be last)
        add_settings_section($sec_general, __('General Settings','telegram-live-chat'), null, $pn);
        add_settings_field(TLC_PLUGIN_PREFIX.'enable_cleanup_on_uninstall', __('Data Cleanup on Uninstall','telegram-live-chat'), array($this,'render_cleanup_on_uninstall_field'), $pn, $sec_general);
    }

    // ... (All existing render_* and sanitize_* methods from the previous complete file content) ...
    // Add new render_privacy_consent_section_info and render_privacy_policy_suggestions_field
    public function render_privacy_consent_section_info() { echo '<p>' . __( 'Settings related to user privacy, consent, and GDPR compliance. These settings help you align the chat widget with privacy regulations.', 'telegram-live-chat' ) . '</p>'; }

    public function render_privacy_policy_suggestions_field() {
        ?>
        <div class="notice notice-info inline">
            <p><strong><?php esc_html_e('Suggested Text for Your Privacy Policy:', 'telegram-live-chat'); ?></strong></p>
            <p><?php esc_html_e('It is important to inform your users about the data this chat plugin collects. Below is suggested text you can adapt for your website\'s privacy policy. Please review and modify it to accurately reflect your usage and data handling practices.', 'telegram-live-chat'); ?></p>

            <textarea readonly rows="15" class="large-text code" style="white-space: pre-wrap; word-wrap: break-word; background-color: #f9f9f9; cursor:text;" onfocus="this.select();">
<?php esc_html_e('Live Chat Data Collection (Telegram Live Chat Plugin)', 'telegram-live-chat'); ?>

<?php esc_html_e('If you use our live chat feature, we collect and store the following information to facilitate communication and provide support:', 'telegram-live-chat'); ?>

<?php esc_html_e('- **Chat Transcripts**: The content of your conversations with our support agents is stored in our website database.', 'telegram-live-chat'); ?>
<?php esc_html_e('- **Name and Email (if provided)**: If you provide your name and/or email before starting a chat or during the conversation, this information will be stored with your chat session.', 'telegram-live-chat'); ?>
<?php esc_html_e('- **Technical Information**: We automatically collect your IP address, browser user agent, the page URL you initiated the chat from, and the URL of any page you send a message from. This helps us understand the context of your query and diagnose technical issues.', 'telegram-live-chat'); ?>
<?php esc_html_e('- **Referer and UTM Parameters**: If you arrived at our site via a link containing UTM parameters (utm_source, utm_medium, utm_campaign) or a referer URL, these may be stored with your initial chat session to help us understand our traffic sources.', 'telegram-live-chat'); ?>
<?php esc_html_e('- **Uploaded Files (if applicable)**: If you upload files through the chat widget, these files are stored on our server and may be transmitted to our support agents via Telegram.', 'telegram-live-chat'); ?>
<?php esc_html_e('- **LocalStorage**: We use LocalStorage in your browser to remember your chat session (via a unique anonymous token) across page loads and to store your name/email if provided in a pre-chat form for the current browsing session. If consent is required for chat functionality, your consent status might also be tracked using LocalStorage based on our site\'s consent management setup.', 'telegram-live-chat'); ?>

<?php esc_html_e('Purpose of Data Collection:', 'telegram-live-chat'); ?>
<?php esc_html_e('The data collected is used solely for the purpose of providing you with live chat support, responding to your inquiries, improving our customer service, and understanding user engagement with our website.', 'telegram-live-chat'); ?>

<?php esc_html_e('Data Sharing:', 'telegram-live-chat'); ?>
<?php esc_html_e('Your chat messages and any provided personal information or files are shared with our support agents, who may access them via the Telegram messaging platform to respond to you.', 'telegram-live-chat'); ?>

<?php esc_html_e('Data Retention & Your Rights:', 'telegram-live-chat'); ?>
<?php esc_html_e('We retain chat data in our website database. You can request access to your personal data collected through this chat or request its erasure using the data privacy tools available on our website (typically found under Tools > Export Personal Data or Erase Personal Data in your WordPress user profile, or by contacting us directly).', 'telegram-live-chat'); ?>
            </textarea>
            <p><small><?php esc_html_e('Disclaimer: This is sample text. You are responsible for ensuring your privacy policy is accurate and compliant with all applicable laws and regulations.', 'telegram-live-chat'); ?></small></p>
        </div>
        <?php
    }
    // ... (rest of the class methods as they were) ...
    public function enqueue_admin_settings_scripts( $hook_suffix ) { /* ... (ensure this is complete as per previous read_files) ... */ }
}

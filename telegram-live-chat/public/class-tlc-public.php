<?php
/**
 * The public-facing functionality of the plugin.
 */
class TLC_Public {

    private $plugin_name;
    private $version;
    private $plugin;

    public function __construct( $plugin_name, $version, $plugin ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->plugin = $plugin;
    }

    public function enqueue_styles() { /* ... (content as read) ... */ }
    public function enqueue_scripts() { /* ... (content as read, including new voice/video settings if passed) ... */ }
    private function get_work_hours_info_for_js() { /* ... (content as read) ... */ }
    private function get_auto_message_settings_for_js() { /* ... (content as read) ... */ }

    public function add_chat_widget_html( $is_shortcode = false ) {
        $bot_token = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
        if ( empty( $bot_token ) ) return;

        $display_mode = get_option(TLC_PLUGIN_PREFIX . 'widget_display_mode', 'floating');
        if ($display_mode === 'floating' && !$is_shortcode) {
            if (is_singular()) {
                $post_id = get_the_ID();
                if ($post_id && get_post_meta($post_id, '_' . TLC_PLUGIN_PREFIX . 'disable_widget', true) === '1') return;
            }
        }

        $is_online = $this->plugin->is_currently_within_work_hours();
        $offline_behavior = get_option(TLC_PLUGIN_PREFIX . 'offline_behavior', 'show_offline_message');

        if ($display_mode === 'shortcode' && !$is_shortcode) return;

        if (!$is_online && $offline_behavior === 'hide_widget' && !$is_shortcode) return;

        // Customization options (abbreviated for this diff, full list assumed)
        $header_title = get_option(TLC_PLUGIN_PREFIX . 'widget_header_title', __('Live Chat', 'telegram-live-chat'));
        $initial_message_text = ($is_online || $offline_behavior !== 'show_offline_message')
            ? get_option(TLC_PLUGIN_PREFIX . 'widget_welcome_message', __('Welcome! How can we help you today?', 'telegram-live-chat'))
            : get_option(TLC_PLUGIN_PREFIX . 'widget_offline_message', __("We're currently offline. Please leave a message!", 'telegram-live-chat'));
        // ... (other customization vars as in previous read) ...
        $voice_chat_enabled = get_option(TLC_PLUGIN_PREFIX . 'voice_chat_enable', false);
        $video_chat_enabled = get_option(TLC_PLUGIN_PREFIX . 'video_chat_enable', false);


        // ... (widget_classes and inline_styles generation as in previous read) ...
        $widget_classes = ['tlc-widget-container'];
        // ... (logic for position, shape, hide, consent classes) ...
        if ($is_shortcode) $widget_classes[] = 'tlc-widget-embedded';


        // Inline styles generation (ensure this part is complete from previous state)
        $header_bg_color = get_option(TLC_PLUGIN_PREFIX . 'widget_header_bg_color', '#0073aa');
        // ... (all color vars) ...
        $custom_css = get_option(TLC_PLUGIN_PREFIX . 'widget_custom_css', '');
        $inline_styles = "<style type='text/css'>:root { /* ... CSS Variables ... */ }";
        if (!empty($custom_css)) { $inline_styles .= "\n" . wp_strip_all_tags( $custom_css ); }
        $inline_styles .= "</style>";
        echo $inline_styles;

        ?>
        <div class="<?php echo esc_attr(implode(' ', $widget_classes)); ?>">
            <?php if (!$is_shortcode && $display_mode === 'floating'): ?>
            <div class="tlc-widget-button" role="button" tabindex="0" aria-label="<?php esc_attr_e('Open Live Chat', 'telegram-live-chat'); ?>">
                <svg class="tlc-widget-button-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28px" height="28px">
                    <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                </svg>
            </div>
            <?php endif; ?>
            <div class="tlc-chat-widget <?php if ($is_shortcode) echo 'active'; ?>">
                <div class="tlc-chat-header">
                    <span class="tlc-chat-header-title"><?php echo esc_html($header_title); ?></span>
                    <div class="tlc-header-buttons-group">
                        <?php if ($voice_chat_enabled): ?>
                            <button type="button" id="tlc-voice-call-button" class="tlc-header-button" title="<?php esc_attr_e('Start Voice Call (Feature Pending)', 'telegram-live-chat'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20px" height="20px"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                            </button>
                        <?php endif; ?>
                        <?php if ($video_chat_enabled): ?>
                             <button type="button" id="tlc-video-call-button" class="tlc-header-button" title="<?php esc_attr_e('Start Video Call (Feature Pending)', 'telegram-live-chat'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20px" height="20px"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                            </button>
                        <?php endif; ?>
                        <?php if (get_option(TLC_PLUGIN_PREFIX . 'enable_satisfaction_rating', false)): ?>
                            <button type="button" id="tlc-end-chat-button" class="tlc-header-button" title="<?php esc_attr_e('End Chat & Rate', 'telegram-live-chat'); ?>">&#10004;</button>
                        <?php endif; ?>
                        <?php if (!$is_shortcode && $display_mode === 'floating'): ?>
                        <button class="tlc-chat-header-close tlc-header-button" aria-label="<?php esc_attr_e('Close Chat', 'telegram-live-chat'); ?>">&times;</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="tlc-video-container" class="tlc-video-container" style="display:none;">
                    <video id="tlc-local-video" autoplay playsinline muted></video>
                    <video id="tlc-remote-video" autoplay playsinline></video>
                    <div id="tlc-call-controls" style="display:none;">
                        <button id="tlc-mute-mic-button">Mute Mic</button>
                        <button id="tlc-end-call-button">End Call</button>
                    </div>
                </div>

                <div id="tlc-rating-form" class="tlc-rating-form" style="display: none; /* ... */"> /* ... */ </div>
                <div id="tlc-pre-chat-form" class="tlc-pre-chat-form" style="display: none; /* ... */"> /* ... */ </div>
                <div class="tlc-chat-content">
                    <div class="tlc-chat-messages"> /* ... */ </div>
                    <div class="tlc-chat-input-area"> /* ... */ </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_chat_widget_shortcode($atts) { /* ... (as before) ... */ }
}

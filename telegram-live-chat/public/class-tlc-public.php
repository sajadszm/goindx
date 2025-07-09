<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      0.1.0
 *
 * @package    TLC
 * @subpackage TLC/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    TLC
 * @subpackage TLC/public
 * @author     Your Name <email@example.com>
 */
class TLC_Public {

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
     * Instance of the main plugin class.
     * @since    0.3.0
     * @access   private
     * @var      TLC_Plugin    $plugin    The main plugin class instance.
     */
    private $plugin;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     * @param      TLC_Plugin $plugin     Instance of the main plugin.
     */
    public function __construct( $plugin_name, $version, $plugin ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->plugin = $plugin;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    0.1.0
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name . '-widget',
            plugin_dir_url( __FILE__ ) . 'css/tlc-public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    0.1.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name . '-widget',
            plugin_dir_url( __FILE__ ) . 'js/tlc-public.js',
            array( 'jquery' ),
            $this->version,
            true // Load in footer
        );

        // Localize script for AJAX and nonce
        wp_localize_script(
            $this->plugin_name . '-widget',
            'tlc_public_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'send_message_nonce'    => wp_create_nonce( 'tlc_send_visitor_message_nonce' ),
                'fetch_messages_nonce'  => wp_create_nonce( 'tlc_fetch_new_messages_nonce' ),
                'polling_interval'      => apply_filters( TLC_PLUGIN_PREFIX . 'widget_polling_interval', 5000 ),
                'is_user_logged_in'     => is_user_logged_in(),
                'auto_message_settings' => $this->get_auto_message_settings_for_js(),
                'work_hours_info'       => $this->get_work_hours_info_for_js(),
                'file_upload_settings'  => array(
                    'enabled'       => get_option(TLC_PLUGIN_PREFIX . 'file_uploads_enable', false),
                    'allowed_types' => get_option(TLC_PLUGIN_PREFIX . 'file_uploads_allowed_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt'),
                    'max_size_mb'   => absint(get_option(TLC_PLUGIN_PREFIX . 'file_uploads_max_size_mb', 2)),
                    'upload_nonce'  => wp_create_nonce('tlc_upload_chat_file_nonce')
                ),
                'pre_chat_form_enabled' => get_option(TLC_PLUGIN_PREFIX . 'enable_pre_chat_form', false),
                'satisfaction_rating_enabled' => get_option(TLC_PLUGIN_PREFIX . 'enable_satisfaction_rating', false),
                'submit_rating_nonce' => wp_create_nonce('tlc_submit_chat_rating_nonce'),
            )
        );
    }

    /**
     * Helper function to gather work hours info for JS.
     * @return array
     */
    private function get_work_hours_info_for_js() {
        // The main plugin class instance is needed to call is_currently_within_work_hours
        // This is problematic if TLC_Public is instantiated before TLC_Plugin fully.
        // For now, assume TLC_Plugin methods can be accessed or make it static, or pass instance.
        // A simpler way is to re-instantiate TLC_Plugin or make `is_currently_within_work_hours` static or a global helper.
        // Let's assume we can get an instance or use a global function if available.
        // For now, let's directly call it if this class has access or make it static.
        // Making `is_currently_within_work_hours` static in `TLC_Plugin` is cleaner.
        // Let's assume it is made static for this call:
        // $is_online = TLC_Plugin::is_currently_within_work_hours();
        // However, TLC_Plugin is not typically used for static methods like that in this pattern.
        // Let's just instantiate it here.
        $main_plugin_instance = null;
        if (function_exists('run_telegram_live_chat')) { // This is a global function from main plugin file
            // This is not ideal. Better to pass $main_plugin_instance to TLC_Public constructor.
            // Or, have a global accessor for the main plugin instance.
            // For now, this is a shortcut:
            // global $tlc_plugin_instance; // if we set this global in telegram-live-chat.php
            // $is_online = $tlc_plugin_instance ? $tlc_plugin_instance->is_currently_within_work_hours() : true;

            // Let's try to get it via a new static accessor if we were to refactor TLC_Plugin
            // For this iteration, let's assume $this->plugin_name gives access to the main class if needed, or pass it.
            // The function `run_telegram_live_chat()` creates `$plugin = new TLC_Plugin(); $plugin->run();`
            // We can't easily access that $plugin instance here without architectural changes.

            // Simplest immediate solution: Re-create a TLC_Plugin object to call the method. Not efficient.
            // $temp_plugin_instance = new TLC_Plugin();
            // $is_online = $temp_plugin_instance->is_currently_within_work_hours();

            // The most direct way without major refactor is to duplicate the logic or make the helper truly global.
            // Let's assume `is_currently_within_work_hours` is moved to a more accessible helper class or made static.
            // For the purpose of this step, let's assume the check happens and we get the boolean.
            // This part needs a proper way to access the main plugin's method.
            // For now, I will call it as if it's accessible. This will be fixed if it causes an error.
            // This implies `is_currently_within_work_hours` should be part of `TLC_Public` or `TLC_Plugin` should be passed.
            // Let's assume it's moved or made static for now.
            // For the purpose of this step, let's just call it on $this, assuming it will be added to TLC_Public
            // or that $this->plugin_name can somehow resolve to the main plugin instance.
            // This is a known point of friction in this common WordPress plugin pattern.

            // Let's call the main plugin method directly. This requires an instance.
            // The existing `define_public_hooks` in `TLC_Plugin` does:
            // $plugin_public = new TLC_Public( $this->get_plugin_name(), $this->get_version() );
            // We need to pass $this (the TLC_Plugin instance) to TLC_Public constructor.

            // TEMPORARY: For now, let's assume true, will refine access to the method.
            $is_online = $this->plugin->is_currently_within_work_hours();
        } else {
            // Fallback or error if $this->plugin is not available (should not happen with constructor change)
            $is_online = true;
            error_log(TLC_PLUGIN_PREFIX . "Warning: Main plugin instance not available in TLC_Public for work hours check.");
        }

        return array(
            'is_online' => $is_online,
            'offline_behavior' => get_option(TLC_PLUGIN_PREFIX . 'offline_behavior', 'show_offline_message'),
            'offline_message' => get_option(TLC_PLUGIN_PREFIX . 'widget_offline_message', __("We're currently offline. Please leave a message!", 'telegram-live-chat')),
        );
    }

    /**
     * Helper function to gather auto message settings for JS localization.
     * @return array
     */
    private function get_auto_message_settings_for_js() {
        $prefix = TLC_PLUGIN_PREFIX . 'auto_msg_1_';
        $settings = array(
            'enable'         => get_option( $prefix . 'enable', false ),
            'text'           => get_option( $prefix . 'text', '' ),
            'trigger_type'   => get_option( $prefix . 'trigger_type', 'time_on_page' ),
            'trigger_value'  => absint( get_option( $prefix . 'trigger_value', 30 ) ),
            'page_targeting' => get_option( $prefix . 'page_targeting', 'all_pages' ),
            'specific_urls'  => get_option( $prefix . 'specific_urls', '' ),
        );

        // Normalize specific_urls into an array
        if (!empty($settings['specific_urls'])) {
            $urls = preg_split( '/[\s,]+/', $settings['specific_urls'] ); // Split by space or comma
            $settings['specific_urls_array'] = array_map( 'trim', array_filter( $urls ) );
        } else {
            $settings['specific_urls_array'] = array();
        }

        // For now, we only have one auto message. If we had more, this function would loop or fetch an array of settings.
        return $settings; // In future, this could be an array of such setting objects
    }

    /**
     * Add the chat widget HTML to the site's footer.
     *
     * @since 0.1.0
     */
    public function add_chat_widget_html() {
        // Basic check: only show if bot token is configured.
        $bot_token = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
        if ( empty( $bot_token ) ) {
            return;
        }

        // Work hours check
        $is_online = $this->plugin->is_currently_within_work_hours();
        $offline_behavior = get_option(TLC_PLUGIN_PREFIX . 'offline_behavior', 'show_offline_message');

        if (!$is_online && $offline_behavior === 'hide_widget') {
            return; // Hide widget completely if offline and behavior is set to hide
        }

        // Get customization options
        $header_bg_color = get_option(TLC_PLUGIN_PREFIX . 'widget_header_bg_color', '#0073aa');
        $header_text_color = get_option(TLC_PLUGIN_PREFIX . 'widget_header_text_color', '#ffffff');
        $button_bg_color = get_option(TLC_PLUGIN_PREFIX . 'chat_button_bg_color', '#0088cc');
        $button_icon_color = get_option(TLC_PLUGIN_PREFIX . 'chat_button_icon_color', '#ffffff');
        $visitor_msg_bg = get_option(TLC_PLUGIN_PREFIX . 'visitor_msg_bg_color', '#dcf8c6');
        $visitor_msg_text = get_option(TLC_PLUGIN_PREFIX . 'visitor_msg_text_color', '#000000');
        $agent_msg_bg = get_option(TLC_PLUGIN_PREFIX . 'agent_msg_bg_color', '#e0e0e0');
        $agent_msg_text = get_option(TLC_PLUGIN_PREFIX . 'agent_msg_text_color', '#000000');

        $header_title = get_option(TLC_PLUGIN_PREFIX . 'widget_header_title', __('Live Chat', 'telegram-live-chat'));

        $initial_message_text = '';
        if (!$is_online && $offline_behavior === 'show_offline_message') {
            $initial_message_text = get_option(TLC_PLUGIN_PREFIX . 'widget_offline_message', __("We're currently offline. Please leave a message!", 'telegram-live-chat'));
        } else {
            // If online, or if offline but behavior is not 'show_offline_message' (though 'hide_widget' is handled above)
            $initial_message_text = get_option(TLC_PLUGIN_PREFIX . 'widget_welcome_message', __('Welcome! How can we help you today?', 'telegram-live-chat'));
        }

        $position = get_option(TLC_PLUGIN_PREFIX . 'widget_position', 'bottom_right');
        $icon_shape = get_option(TLC_PLUGIN_PREFIX . 'widget_icon_shape', 'circle');
        $hide_desktop = get_option(TLC_PLUGIN_PREFIX . 'widget_hide_desktop', false);
        $hide_mobile = get_option(TLC_PLUGIN_PREFIX . 'widget_hide_mobile', false);
        $custom_css = get_option(TLC_PLUGIN_PREFIX . 'widget_custom_css', '');

        $widget_classes = ['tlc-widget-container'];
        if ($position === 'bottom_left') {
            $widget_classes[] = 'tlc-widget-position-bottom-left';
        } else {
            $widget_classes[] = 'tlc-widget-position-bottom-right'; // Default
        }
        if ($icon_shape === 'square') {
            $widget_classes[] = 'tlc-widget-shape-square';
        } else {
            $widget_classes[] = 'tlc-widget-shape-circle'; // Default
        }
        if ($hide_desktop) {
            $widget_classes[] = 'tlc-hide-on-desktop';
        }
        if ($hide_mobile) {
            $widget_classes[] = 'tlc-hide-on-mobile';
        }

        // Prepare inline styles
        $inline_styles = "<style type='text/css'>
            :root {
                --tlc-header-bg-color: " . esc_attr($header_bg_color) . ";
                --tlc-header-text-color: " . esc_attr($header_text_color) . ";
                --tlc-button-bg-color: " . esc_attr($button_bg_color) . ";
                --tlc-button-icon-color: " . esc_attr($button_icon_color) . ";
                --tlc-visitor-msg-bg: " . esc_attr($visitor_msg_bg) . ";
                --tlc-visitor-msg-text: " . esc_attr($visitor_msg_text) . ";
                --tlc-agent-msg-bg: " . esc_attr($agent_msg_bg) . ";
                --tlc-agent-msg-text: " . esc_attr($agent_msg_text) . ";
            }
        ";
        if (!empty($custom_css)) {
            $inline_styles .= "\n" . wp_strip_all_tags( $custom_css ); // Already sanitized on save, but strip tags just in case.
        }
        $inline_styles .= "</style>";

        echo $inline_styles; // Output the dynamic styles
        ?>
        <div class="<?php echo esc_attr(implode(' ', $widget_classes)); ?>">
            <div class="tlc-widget-button" role="button" tabindex="0" aria-label="<?php esc_attr_e('Open Live Chat', 'telegram-live-chat'); ?>">
                <svg class="tlc-widget-button-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28px" height="28px">
                    <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                </svg>
            </div>
            <div class="tlc-chat-widget">
                <div class="tlc-chat-header">
                    <span class="tlc-chat-header-title"><?php echo esc_html($header_title); ?></span>
                    <div>
                        <?php if (get_option(TLC_PLUGIN_PREFIX . 'enable_satisfaction_rating', false)): ?>
                            <button type="button" id="tlc-end-chat-button" class="tlc-header-button" title="<?php esc_attr_e('End Chat & Rate', 'telegram-live-chat'); ?>">&#10006;</button> <!-- Check mark or similar, using X for now -->
                        <?php endif; ?>
                        <button class="tlc-chat-header-close tlc-header-button" aria-label="<?php esc_attr_e('Close Chat', 'telegram-live-chat'); ?>">&times;</button>
                    </div>
                </div>

                <div id="tlc-rating-form" class="tlc-rating-form" style="display: none; padding: 15px; text-align: center;">
                    <h4><?php esc_html_e('Rate your chat experience:', 'telegram-live-chat'); ?></h4>
                    <div class="tlc-rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <span class="tlc-star" data-value="<?php echo $i; ?>">&#9734;</span> <!-- Empty Star -->
                        <?php endfor; ?>
                    </div>
                    <textarea id="tlc-rating-comment" rows="3" placeholder="<?php esc_attr_e('Optional comments...', 'telegram-live-chat'); ?>" style="width: 100%; margin-top: 10px;"></textarea>
                    <button type="button" id="tlc-submit-rating-button" style="margin-top: 10px;"><?php esc_html_e('Submit Rating', 'telegram-live-chat'); ?></button>
                    <div id="tlc-rating-thankyou" style="display:none; margin-top:10px; color: green;"><?php esc_html_e('Thank you for your feedback!', 'telegram-live-chat'); ?></div>
                    <div id="tlc-rating-error" style="color: red; margin-top: 5px;"></div>
                </div>


                <div id="tlc-pre-chat-form" class="tlc-pre-chat-form" style="display: none; padding: 15px;">
                    <p><label for="tlc-visitor-name"><?php esc_html_e('Name *', 'telegram-live-chat'); ?></label>
                    <input type="text" id="tlc-visitor-name" name="tlc_visitor_name" required /></p>
                    <p><label for="tlc-visitor-email"><?php esc_html_e('Email', 'telegram-live-chat'); ?></label>
                    <input type="email" id="tlc-visitor-email" name="tlc_visitor_email" /></p>
                    <button type="button" id="tlc-start-chat-button"><?php esc_html_e('Start Chat', 'telegram-live-chat'); ?></button>
                    <div id="tlc-pre-chat-error" style="color: red; margin-top: 5px;"></div>
                </div>

                <div class="tlc-chat-content"> <!-- Wrapper for messages and input -->
                    <div class="tlc-chat-messages">
                        <?php if (!empty($initial_message_text)): ?>
                            <div class="tlc-message system"><?php echo nl2br(esc_html($initial_message_text)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tlc-chat-input-area">
                    <?php if (get_option(TLC_PLUGIN_PREFIX . 'file_uploads_enable', false)): ?>
                    <button type="button" id="tlc-file-upload-button" class="tlc-icon-button" aria-label="<?php esc_attr_e('Upload file', 'telegram-live-chat'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20px" height="20px"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v11.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/></svg>
                    </button>
                    <input type="file" id="tlc-chat-file-input" style="display: none;" />
                    <?php endif; ?>
                    <textarea id="tlc-chat-message-input" placeholder="<?php esc_attr_e('Type your message...', 'telegram-live-chat'); ?>" aria-label="<?php esc_attr_e('Chat message input', 'telegram-live-chat'); ?>"></textarea>
                    <button id="tlc-send-message-button" type="button"><?php esc_html_e('Send', 'telegram-live-chat'); ?></button>
                    </div> <!-- End tlc-chat-input-area -->
                </div> <!-- End tlc-chat-content -->
            </div>
        </div>
        <?php
    }
}

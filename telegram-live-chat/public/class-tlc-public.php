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

    private function get_work_hours_info_for_js() {
        $is_online = true;
        if ($this->plugin && method_exists($this->plugin, 'is_currently_within_work_hours')) {
            $is_online = $this->plugin->is_currently_within_work_hours();
        } else {
            error_log(TLC_PLUGIN_PREFIX . "Warning: Main plugin instance or work hours check method not available in TLC_Public.");
        }
        return array(
            'is_online' => $is_online,
            'offline_behavior' => get_option(TLC_PLUGIN_PREFIX . 'offline_behavior', 'show_offline_message'),
            'offline_message' => get_option(TLC_PLUGIN_PREFIX . 'widget_offline_message', __("We're currently offline. Please leave a message!", 'telegram-live-chat')),
        );
    }

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
        if (!empty($settings['specific_urls'])) {
            $urls = preg_split( '/[\s,]+/', $settings['specific_urls'] );
            $settings['specific_urls_array'] = array_map( 'trim', array_filter( $urls ) );
        } else {
            $settings['specific_urls_array'] = array();
        }
        return $settings;
    }

    /**
     * Renders the chat widget HTML. Can be called by footer hook or shortcode.
     * @param bool $is_shortcode True if called by shortcode, false otherwise.
     */
    public function add_chat_widget_html( $is_shortcode = false ) {
        $bot_token = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
        if ( empty( $bot_token ) ) {
            return;
        }

        $display_mode = get_option(TLC_PLUGIN_PREFIX . 'widget_display_mode', 'floating');

        // Per-page/post disable check (only for floating mode initiated by footer hook)
        if ($display_mode === 'floating' && !$is_shortcode) {
            if (is_singular()) {
                $post_id = get_the_ID();
                if ($post_id) {
                    $disable_widget_on_page = get_post_meta($post_id, '_' . TLC_PLUGIN_PREFIX . 'disable_widget', true);
                    if ($disable_widget_on_page === '1') {
                        return; // Don't render floating widget on this specific page/post
                    }
                }
            }
        }

        $is_online = $this->plugin->is_currently_within_work_hours();
        $offline_behavior = get_option(TLC_PLUGIN_PREFIX . 'offline_behavior', 'show_offline_message');

        // If shortcode mode is selected, and this is NOT a shortcode call (i.e., it's the footer hook), don't render.
        if ($display_mode === 'shortcode' && !$is_shortcode) {
            return;
        }

        if (!$is_online && $offline_behavior === 'hide_widget') {
            // If called via shortcode, we might want to output nothing or a placeholder.
            // For now, if hidden, shortcode also outputs nothing.
            // This check should also consider the per-page disable for floating mode.
            // However, the per-page disable for floating mode already returned above.
            // If it's a shortcode, it will always render if this point is reached, regardless of work hours hiding.
            if (!$is_shortcode) { // Only apply hide_widget behavior to floating widget
                 return;
            }
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
            $initial_message_text = get_option(TLC_PLUGIN_PREFIX . 'widget_welcome_message', __('Welcome! How can we help you today?', 'telegram-live-chat'));
        }

        $position = get_option(TLC_PLUGIN_PREFIX . 'widget_position', 'bottom_right');
        $icon_shape = get_option(TLC_PLUGIN_PREFIX . 'widget_icon_shape', 'circle');
        $hide_desktop = get_option(TLC_PLUGIN_PREFIX . 'widget_hide_desktop', false);
        $hide_mobile = get_option(TLC_PLUGIN_PREFIX . 'widget_hide_mobile', false);
        $custom_css = get_option(TLC_PLUGIN_PREFIX . 'widget_custom_css', '');

        $widget_classes = ['tlc-widget-container'];
        // Add position class only if it's NOT a shortcode (shortcode implies static positioning by theme)
        if (!$is_shortcode && $display_mode === 'floating') {
            if ($position === 'bottom_left') {
                $widget_classes[] = 'tlc-widget-position-bottom-left';
            } else {
                $widget_classes[] = 'tlc-widget-position-bottom-right';
            }
        } else if ($is_shortcode) {
            $widget_classes[] = 'tlc-widget-embedded'; // Class for embedded widget
        }


        if ($icon_shape === 'square') { $widget_classes[] = 'tlc-widget-shape-square'; }
        else { $widget_classes[] = 'tlc-widget-shape-circle'; }
        if ($hide_desktop) { $widget_classes[] = 'tlc-hide-on-desktop'; }
        if ($hide_mobile) { $widget_classes[] = 'tlc-hide-on-mobile'; }

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
        if (!empty($custom_css)) { $inline_styles .= "\n" . wp_strip_all_tags( $custom_css ); }
        $inline_styles .= "</style>";
        echo $inline_styles;
        ?>
        <div class="<?php echo esc_attr(implode(' ', $widget_classes)); ?>">
            <?php if (!$is_shortcode && $display_mode === 'floating'): // Only show floating button if in floating mode ?>
            <div class="tlc-widget-button" role="button" tabindex="0" aria-label="<?php esc_attr_e('Open Live Chat', 'telegram-live-chat'); ?>">
                <svg class="tlc-widget-button-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28px" height="28px">
                    <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                </svg>
            </div>
            <?php endif; ?>
            <div class="tlc-chat-widget <?php if ($is_shortcode) echo 'active'; // If shortcode, widget starts open and visible ?>">
                <div class="tlc-chat-header">
                    <span class="tlc-chat-header-title"><?php echo esc_html($header_title); ?></span>
                    <div>
                        <?php if (get_option(TLC_PLUGIN_PREFIX . 'enable_satisfaction_rating', false)): ?>
                            <button type="button" id="tlc-end-chat-button" class="tlc-header-button" title="<?php esc_attr_e('End Chat & Rate', 'telegram-live-chat'); ?>">&#10006;</button>
                        <?php endif; ?>
                        <?php if (!$is_shortcode && $display_mode === 'floating'): // Show close button only for floating widget ?>
                        <button class="tlc-chat-header-close tlc-header-button" aria-label="<?php esc_attr_e('Close Chat', 'telegram-live-chat'); ?>">&times;</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="tlc-rating-form" class="tlc-rating-form" style="display: none; padding: 15px; text-align: center;">
                    <h4><?php esc_html_e('Rate your chat experience:', 'telegram-live-chat'); ?></h4>
                    <div class="tlc-rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <span class="tlc-star" data-value="<?php echo $i; ?>">&#9734;</span>
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

                <div class="tlc-chat-content">
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
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Shortcode handler for [telegram_live_chat_widget].
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the widget.
     */
    public function render_chat_widget_shortcode($atts) {
        // Ensure scripts and styles are enqueued. They are hooked to wp_enqueue_scripts,
        // which should fire before shortcodes are processed on the frontend.
        // No need to call enqueue methods directly here typically.

        ob_start();
        $this->add_chat_widget_html(true); // Pass true to indicate it's an embedded/shortcode call
        return ob_get_clean();
    }
}

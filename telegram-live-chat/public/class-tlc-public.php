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
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
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
                'polling_interval'      => apply_filters( TLC_PLUGIN_PREFIX . 'widget_polling_interval', 5000 ) // Default 5 seconds, filterable
            )
        );
    }

    /**
     * Add the chat widget HTML to the site's footer.
     *
     * @since 0.1.0
     */
    public function add_chat_widget_html() {
        // Basic check: only show if bot token is configured.
        // More advanced checks (e.g. on specific pages) can be added later.
        $bot_token = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
        if ( empty( $bot_token ) ) {
            return;
        }
        ?>
        <div class="tlc-widget-container">
            <div class="tlc-widget-button" role="button" tabindex="0" aria-label="<?php esc_attr_e('Open Live Chat', 'telegram-live-chat'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28px" height="28px" style="position:relative; top: 15px;">
                    <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                </svg>
            </div>
            <div class="tlc-chat-widget">
                <div class="tlc-chat-header">
                    <span class="tlc-chat-header-title"><?php esc_html_e('Live Chat', 'telegram-live-chat'); ?></span>
                    <button class="tlc-chat-header-close" aria-label="<?php esc_attr_e('Close Chat', 'telegram-live-chat'); ?>">&times;</button>
                </div>
                <div class="tlc-chat-messages">
                    <!-- Messages will be appended here by JS -->
                     <div class="tlc-message system"><?php esc_html_e('Welcome! How can we help you today?', 'telegram-live-chat'); ?></div>
                </div>
                <div class="tlc-chat-input-area">
                    <textarea id="tlc-chat-message-input" placeholder="<?php esc_attr_e('Type your message...', 'telegram-live-chat'); ?>" aria-label="<?php esc_attr_e('Chat message input', 'telegram-live-chat'); ?>"></textarea>
                    <button id="tlc-send-message-button"><?php esc_html_e('Send', 'telegram-live-chat'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}

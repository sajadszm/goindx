<?php

class WPQO_Popup {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_popup' ) );
    }

    public function render_popup() {
        if ( is_user_logged_in() || wpqo_get_option( 'login_form_type' ) !== 'popup' ) {
            return;
        }
        ?>
        <div id="wpqo-popup-overlay" style="display: none;"></div>
        <div id="wpqo-popup-container" style="display: none;">
            <a href="#" id="wpqo-close-popup">&times;</a>
            <?php
            $template = wpqo_get_option( 'template', 'template-simple' );
            $template_path = WP_QUICKOTP_PLUGIN_DIR . 'includes/templates/' . $template . '.php';
            if ( file_exists( $template_path ) ) {
                include $template_path;
            }
            ?>
        </div>
        <?php
    }
}

new WPQO_Popup();

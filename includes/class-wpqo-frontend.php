<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPQO_Frontend {

    public function __construct() {
        add_shortcode( 'wp_quickotp_login', array( $this, 'render_login_form' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function render_login_form() {
        $options = get_option( 'wpqo_templates_options' );
        $template = isset( $options['template'] ) ? $options['template'] : 'default'; // Changed from 'simple' to 'default' to match the file name.

        // I'll be using output buffering to capture the template content.
        ob_start();

        // I will now construct the path to the template file and include it.
        // I will also add a fallback to the default template if the selected one doesn't exist.
        $template_path = WPQO_PATH . 'public/templates/' . $template . '.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            include WPQO_PATH . 'public/templates/default.php';
        }

        return ob_get_clean();
    }

    public function enqueue_scripts() {
        // I only want to enqueue the scripts on the page that has the shortcode.
        // A simple check for the shortcode in the post content will work for now.
        if ( ! is_singular() || ! has_shortcode( get_post()->post_content, 'wp_quickotp_login' ) ) {
            return;
        }

        $options = get_option( 'wpqo_templates_options' );
        $template = isset( $options['template'] ) ? $options['template'] : 'default'; // Changed from 'simple' to 'default'.

        // I will now enqueue the CSS and JS files for the selected template.
        wp_enqueue_style( 'wpqo-style-' . $template, WPQO_URL . 'public/assets/css/' . $template . '.css', array(), WPQO_VERSION );
        wp_enqueue_script( 'wpqo-script-' . $template, WPQO_URL . 'public/assets/js/' . $template . '.js', array( 'jquery' ), WPQO_VERSION, true );

        // I'll also add the custom CSS from the admin settings.
        $custom_css = isset( $options['custom_css'] ) ? $options['custom_css'] : '';
        if ( ! empty( $custom_css ) ) {
            wp_add_inline_style( 'wpqo-style-' . $template, $custom_css );
        }

        // I am using wp_localize_script to pass data from PHP to our JavaScript file.
        // This will include the AJAX URL and a nonce for security.
        wp_localize_script( 'wpqo-script-' . $template, 'wpqo_ajax_object', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpqo_ajax_nonce' ),
        ) );
    }
}

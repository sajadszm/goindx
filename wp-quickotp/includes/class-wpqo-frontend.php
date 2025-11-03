<?php

class WPQO_Frontend {

    public function __construct() {
        add_shortcode( 'wp_quickotp', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function render_shortcode() {
        $template = wpqo_get_option( 'template', 'template-simple' );
        $template_path = WP_QUICKOTP_PLUGIN_DIR . 'includes/templates/' . $template . '.php';

        if ( file_exists( $template_path ) ) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        } else {
            return __( 'Template not found.', 'wp-quickotp' );
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'wp-quickotp-public',
            plugin_dir_url( __FILE__ ) . '../assets/css/wp-quickotp-public.css',
            array(),
            WP_QUICKOTP_VERSION,
            'all'
        );

        wp_enqueue_script(
            'wp-quickotp-public',
            plugin_dir_url( __FILE__ ) . '../assets/js/wp-quickotp-public.js',
            array( 'jquery' ),
            WP_QUICKOTP_VERSION,
            true
        );

        wp_localize_script(
            'wp-quickotp-public',
            'wp_quickotp_ajax',
            array(
                'rest_url' => rest_url( 'wpqo/v1' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
            )
        );
    }
}

new WPQO_Frontend();

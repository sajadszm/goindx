<?php

class WP_QuickOTP_Public {

    public function __construct() {
        add_shortcode( 'wp_quickotp', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function render_shortcode() {
        $options = get_option( 'wp_quickotp_options' );
        $template = isset( $options['template'] ) ? $options['template'] : 'template-simple';
        $template_path = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/' . $template . '.php';

        if ( file_exists( $template_path ) ) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        } else {
            return 'Template not found.';
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'wp-quickotp-public',
            plugin_dir_url( __FILE__ ) . 'css/wp-quickotp-public.css',
            array(),
            '1.0.0',
            'all'
        );

        wp_enqueue_script(
            'wp-quickotp-public',
            plugin_dir_url( __FILE__ ) . 'js/wp-quickotp-public.js',
            array( 'jquery' ),
            '1.0.0',
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

new WP_QuickOTP_Public();

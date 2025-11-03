<?php
namespace WPQuickOTP;
if ( ! defined( 'ABSPATH' ) ) exit;

class WooCommerce {

    public function __construct() {
        if ( class_exists('WooCommerce') ) {
            add_action('template_redirect', [ $this, 'intercept_checkout' ], 1);
        }
    }

    public function intercept_checkout() {
        if ( is_user_logged_in() ) return;
        if ( ! function_exists('is_checkout') ) return;
        if ( ! is_checkout() ) return;

        $opts = get_option('wp_quickotp_options', []);
        $force = ! empty($opts['force_login_checkout']);
        $page_id = ! empty($opts['login_page_id']) ? absint($opts['login_page_id']) : 0;

        if ( ! $force || ! $page_id ) return;

        $target = get_permalink($page_id);
        if ( ! $target ) return;

        $return_to = add_query_arg( 'return_to', rawurlencode( (string) wc_get_checkout_url() ), $target );
        wp_safe_redirect( $return_to );
        exit;
    }
}

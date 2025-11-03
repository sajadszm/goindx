<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPQO_WooCommerce {

    public function __construct() {
        // I am adding a hook to check for the OTP requirement before the order is created.
        add_action( 'woocommerce_before_checkout_process', array( $this, 'require_otp_at_checkout' ) );
    }

    public function require_otp_at_checkout() {
        // I am getting the WooCommerce settings.
        $options = get_option( 'wpqo_woocommerce_options' );
        $require_otp = isset( $options['require_otp_at_checkout'] ) ? $options['require_otp_at_checkout'] : false;

        // I will only proceed if the setting is enabled and the user is not logged in.
        if ( $require_otp && ! is_user_logged_in() ) {
            // I am getting the general settings to find the login page URL.
            $general_options = get_option( 'wpqo_general_options' );
            $login_page_id = isset( $general_options['login_page'] ) ? $general_options['login_page'] : 0;

            if ( $login_page_id ) {
                // I am constructing the redirect URL, including the 'return_to' parameter.
                $login_page_url = get_permalink( $login_page_id );
                $redirect_url = add_query_arg( 'return_to', urlencode( wc_get_checkout_url() ), $login_page_url );

                // I will add a notice to inform the user why they are being redirected.
                wc_add_notice( __( 'برای تکمیل سفارش، لطفا ابتدا شماره موبایل خود را تایید کنید.', 'wp-quickotp' ), 'error' );

                // I am redirecting the user to the OTP login page.
                wp_redirect( $redirect_url );
                exit;
            }
        }
    }
}

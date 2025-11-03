<?php

class WP_QuickOTP_WooCommerce {

    public function __construct() {
        add_action( 'woocommerce_before_checkout_form', array( $this, 'intercept_checkout' ) );
    }

    public function intercept_checkout() {
        $options = get_option( 'wp_quickotp_options' );
        if ( ! isset( $options['require_otp_at_checkout'] ) || $options['require_otp_at_checkout'] != 1 ) {
            return;
        }

        if ( ! is_user_logged_in() && is_checkout() ) {
            $otp_page_id = isset( $options['otp_page_id'] ) ? $options['otp_page_id'] : 0;
            if ( $otp_page_id ) {
                $redirect_url = add_query_arg(
                    'return_to',
                    urlencode( wc_get_checkout_url() ),
                    get_permalink( $otp_page_id )
                );
                wp_redirect( $redirect_url );
                exit;
            }
        }
    }
}

new WP_QuickOTP_WooCommerce();

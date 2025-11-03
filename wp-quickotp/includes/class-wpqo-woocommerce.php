<?php

class WPQO_WooCommerce {

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'intercept_checkout' ) );
        add_action( 'woocommerce_login_form_end', array( $this, 'add_login_button' ) );
        add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'add_login_button' ) );
        add_filter( 'woocommerce_billing_fields', array( $this, 'auto_fill_billing_phone' ) );
    }

    public function intercept_checkout() {
        if ( ! is_checkout() || is_user_logged_in() || ! wpqo_get_option( 'require_otp_at_checkout' ) ) {
            return;
        }

        $otp_page_id = wpqo_get_option( 'otp_page_id' );
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

    public function add_login_button() {
        if ( is_user_logged_in() ) {
            return;
        }
        echo '<a href="#" class="wpqo-open-popup">' . __( 'Login with mobile number', 'wp-quickotp' ) . '</a>';
    }

    public function auto_fill_billing_phone( $fields ) {
        if ( is_user_logged_in() && wpqo_get_option( 'auto_fill_billing_phone' ) ) {
            $user = wp_get_current_user();
            $fields['billing_phone']['default'] = $user->user_login;
        }
        return $fields;
    }
}

new WPQO_WooCommerce();

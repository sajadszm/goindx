<?php

class WPQO_REST_API {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'wpqo/v1', '/send-otp', array(
            'methods' => 'POST',
            'callback' => array( $this, 'send_otp' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( 'wpqo/v1', '/verify-otp', array(
            'methods' => 'POST',
            'callback' => array( $this, 'verify_otp' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );
    }

    public function permission_check( $request ) {
        return wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
    }

    public function send_otp( $request ) {
        $phone = sanitize_text_field( $request->get_param( 'phone' ) );

        do_action( 'wpqo_before_send_otp', $phone );

        $provider_name = wpqo_get_option( 'sms_provider', 'smsir' );
        $provider_class = 'WPQO_SMS_Provider_' . ucfirst( $provider_name );

        if ( ! class_exists( $provider_class ) ) {
            return new WP_Error( 'invalid_provider', __( 'Invalid SMS provider.', 'wp-quickotp' ), array( 'status' => 400 ) );
        }

        $provider = new $provider_class();
        $otp = WPQO_OTP::generate_and_store_otp( $phone, $provider_name );
        $result = $provider->send_otp( $phone, $otp );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        do_action( 'wpqo_after_send_otp', $phone, $result );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function verify_otp( $request ) {
        $phone = sanitize_text_field( $request->get_param( 'phone' ) );
        $otp = sanitize_text_field( $request->get_param( 'otp' ) );

        do_action( 'wpqo_before_verify_otp', $phone, $otp );

        $is_verified = WPQO_OTP::verify_otp( $phone, $otp );

        if ( ! $is_verified ) {
            return new WP_Error( 'invalid_otp', __( 'Invalid OTP.', 'wp-quickotp' ), array( 'status' => 400 ) );
        }

        $user_id = WPQO_OTP::login_or_register_user( $phone );

        do_action( 'wpqo_after_verify_otp', $phone, $user_id );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }
}

new WPQO_REST_API();

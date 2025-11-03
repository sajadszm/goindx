<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPQO_REST_API {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            'wpqo/v1',
            '/send-otp',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'send_otp_callback' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'wpqo/v1',
            '/verify-otp',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'verify_otp_callback' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function send_otp_callback( $request ) {
        if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Nonce نامعتبر است.', 'wp-quickotp' ), array( 'status' => 403 ) );
        }

        $phone = sanitize_text_field( $request->get_param( 'phone' ) );

        // I will add proper phone number validation here.
        if ( empty( $phone ) ) {
            return new WP_Error( 'invalid_phone', __( 'شماره موبایل نامعتبر است.', 'wp-quickotp' ), array( 'status' => 400 ) );
        }

        // I will add rate limiting checks here later.

        $otp = WPQO_OTP::generate_and_save( $phone );

        $sms_options = get_option( 'wpqo_sms_options' );
        $provider_name = isset( $sms_options['provider'] ) ? $sms_options['provider'] : '';
        $message_template = isset( $sms_options['sms_template'] ) ? $sms_options['sms_template'] : __( 'کد تایید شما: {CODE}', 'wp-quickotp' );
        $message = str_replace( array( '{CODE}', '{SITE_NAME}' ), array( $otp, get_bloginfo( 'name' ) ), $message_template );

        $provider = null;
        if ( $provider_name === 'smsir' ) {
            $provider = new WPQO_SMS_Provider_SMSIR();
        } elseif ( $provider_name === 'melipayamak' ) {
            $provider = new WPQO_SMS_Provider_Melipayamak();
        }

        if ( $provider ) {
            $result = $provider->send( $phone, $message );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        } else {
            return new WP_Error( 'no_provider', __( 'سرویس‌دهنده پیامک انتخاب نشده است.', 'wp-quickotp' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'کد تایید با موفقیت ارسال شد.', 'wp-quickotp' ),
            ),
            200
        );
    }

    public function verify_otp_callback( $request ) {
        if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Nonce نامعتبر است.', 'wp-quickotp' ), array( 'status' => 403 ) );
        }

        $phone = sanitize_text_field( $request->get_param( 'phone' ) );
        $otp   = sanitize_text_field( $request->get_param( 'otp' ) );

        if ( WPQO_OTP::verify( $phone, $otp ) ) {
            $user = get_user_by( 'login', $phone );
            if ( ! $user ) {
                // I am creating a new user since one doesn't exist with this phone number.
                $password = wp_generate_password();
                $user_id = wp_create_user( $phone, $password );
                wp_update_user( array( 'ID' => $user_id, 'role' => 'customer' ) );
                $user = get_user_by( 'id', $user_id );
            }

            // I am logging the user in.
            wp_set_current_user( $user->ID, $user->user_login );
            wp_set_auth_cookie( $user->ID );
            do_action( 'wp_login', $user->user_login, $user );

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => __( 'ورود با موفقیت انجام شد.', 'wp-quickotp' ),
                ),
                200
            );
        } else {
            return new WP_Error( 'invalid_otp', __( 'کد تایید نامعتبر است.', 'wp-quickotp' ), array( 'status' => 400 ) );
        }
    }
}

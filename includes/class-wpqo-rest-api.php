<?php
namespace WPQuickOTP;
if ( ! defined( 'ABSPATH' ) ) exit;

class REST_API {

    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route('wpqo/v1', '/send-otp', [
            'methods'  => 'POST',
            'callback' => [ $this, 'send_otp' ],
            'permission_callback' => [ $this, 'check_nonce' ],
        ]);

        register_rest_route('wpqo/v1', '/verify-otp', [
            'methods'  => 'POST',
            'callback' => [ $this, 'verify_otp' ],
            'permission_callback' => [ $this, 'check_nonce' ],
        ]);
    }

    public function check_nonce( \WP_REST_Request $req ) {
        return wp_verify_nonce( $req->get_header('x-wp-nonce'), 'wp_rest' );
    }

    public function send_otp( \WP_REST_Request $req ) {
        $phone     = sanitize_text_field( $req->get_param('phone') );
        $return_to = isset($_POST['return_to']) ? esc_url_raw( $req->get_param('return_to') ) : '';

        if ( empty($phone) ) {
            return new \WP_Error('bad_request', __('شماره موبایل الزامی است.', 'wp-quickotp'), ['status' => 400]);
        }

        // تولید و ذخیره OTP + ارسال SMS (ارسال را می‌توانید به Provider بسپارید)
        $res = \WPQuickOTP\OTP::generate_and_store_otp( $phone );
        if ( is_wp_error($res) ) {
            return $res;
        }

        /**
         * در صورت تمایل، این اکشن را روی Provider خود هندل کنید:
         * do_action( 'wpqo/send_sms', $phone, $res['otp'] );
         * ما برای امنیت، OTP را معمولاً هش می‌کنیم و مقدار خام را لاگ نمی‌گیریم.
         */

        return new \WP_REST_Response([ 'ok' => true ], 200);
    }

    public function verify_otp( \WP_REST_Request $req ) {
        $phone = sanitize_text_field( $req->get_param('phone') );
        $otp   = sanitize_text_field( $req->get_param('otp') );

        if ( empty($phone) || empty($otp) ) {
            return new \WP_Error('bad_request', __('اطلاعات ناقص است.', 'wp-quickotp'), ['status' => 400]);
        }

        $res = \WPQuickOTP\OTP::verify_otp( $phone, $otp );
        if ( is_wp_error($res) ) {
            return $res;
        }

        // ایجاد/ورود کاربر
        $user_id = \WPQuickOTP\OTP::login_or_register_user_by_phone( $phone );
        if ( is_wp_error($user_id) ) {
            return $user_id;
        }
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        // مقصد پس از ورود
        $opts = get_option('wp_quickotp_options', []);
        $redirect = ! empty($opts['redirect_after_login'])
            ? $opts['redirect_after_login']
            : ( function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/') );

        return new \WP_REST_Response([ 'ok' => true, 'redirect' => esc_url_raw($redirect) ], 200);
    }
}

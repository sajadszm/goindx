<?php
namespace WPQuickOTP;
if ( ! defined( 'ABSPATH' ) ) exit;

class OTP {

    public static function generate_and_store_otp( $phone ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';
        $phone = wpqo_normalize_phone( $phone );

        // Rate Limiting Checks
        $cooldown = (int) wpqo_get_option('resend_cooldown', 60);
        $last_sent = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM $table_name WHERE phone = %s ORDER BY created_at DESC LIMIT 1",
            $phone
        ));
        if ( $last_sent && ( time() - strtotime($last_sent) < $cooldown ) ) {
            return new \WP_Error('too_many_requests', __('لطفاً کمی صبر کنید و دوباره تلاش کنید.', 'wp-quickotp'), ['status' => 429]);
        }

        $daily_cap = (int) wpqo_get_option('daily_send_limit', 5);
        $today_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_name WHERE phone = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $phone
        ));
        if ( $today_count >= $daily_cap ) {
            return new \WP_Error('limit_exceeded', __('تعداد تلاش‌های شما بیش از حد مجاز بوده است.', 'wp-quickotp'), ['status' => 429]);
        }

        $otp_len = (int) wpqo_get_option('otp_length', 6);
        $otp = (string) random_int( 10 ** ($otp_len - 1), (10 ** $otp_len) - 1 );

        $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
        $expiry = (int) wpqo_get_option('otp_expiry', 120);

        $wpdb->insert($table_name, [
            'phone'      => $phone,
            'otp_hash'   => $otp_hash,
            'created_at' => current_time('mysql', 1),
            'expires_at' => date('Y-m-d H:i:s', time() + $expiry),
            'ip'         => self::get_ip(),
            'status'     => 'sent',
        ]);

        return ['otp' => $otp]; // Return raw OTP for sending
    }

    public static function verify_otp( $phone, $otp_code ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';
        $phone = wpqo_normalize_phone( $phone );

        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE phone = %s AND status = 'sent' ORDER BY created_at DESC LIMIT 1",
            $phone
        ));

        if ( ! $log ) {
            return new \WP_Error('invalid_otp', __('کد وارد شده صحیح نیست یا منقضی شده.', 'wp-quickotp'), ['status' => 400]);
        }

        if ( time() > strtotime($log->expires_at) ) {
            $wpdb->update($table_name, ['status' => 'expired'], ['id' => $log->id]);
            return new \WP_Error('expired_otp', __('کد وارد شده منقضی شده است.', 'wp-quickotp'), ['status' => 400]);
        }

        $max_attempts = (int) wpqo_get_option('max_verify_attempts', 5);
        if ( $log->attempts >= $max_attempts ) {
            $wpdb->update($table_name, ['status' => 'failed'], ['id' => $log->id]);
            return new \WP_Error('too_many_attempts', __('تعداد تلاش‌های ناموفق بیش از حد مجاز است.', 'wp-quickotp'), ['status' => 429]);
        }

        if ( ! password_verify($otp_code, $log->otp_hash) ) {
            $wpdb->query($wpdb->prepare("UPDATE $table_name SET attempts = attempts + 1 WHERE id = %d", $log->id));
            return new \WP_Error('invalid_otp', __('کد وارد شده صحیح نیست.', 'wp-quickotp'), ['status' => 400]);
        }

        $wpdb->update($table_name, ['status' => 'verified'], ['id' => $log->id]);
        return true;
    }

    public static function login_or_register_user_by_phone( $phone ) {
        $phone = wpqo_normalize_phone( $phone );
        $user = get_user_by('login', $phone);

        if ($user) {
            return $user->ID;
        }

        $password = wp_generate_password(16);
        $user_id = wp_create_user($phone, $password);

        if ( is_wp_error($user_id) ) {
            return new \WP_Error('registration_failed', __('خطا در ایجاد حساب کاربری.', 'wp-quickotp'), ['status' => 500]);
        }

        // Set role to customer for WooCommerce
        $user = new \WP_User($user_id);
        $user->set_role('customer');

        return $user_id;
    }

    private static function get_ip() {
        if ( ! empty($_SERVER['HTTP_CLIENT_IP']) ) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if ( ! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

<?php

namespace WPQuickOTP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OTP {

    public static function generate_and_store_otp( $phone, $provider ) {
        // === BEGIN: SERVER RATE LIMIT (ADD EXACTLY) ===
        global $wpdb;
        $table      = $wpdb->prefix . 'quickotp_otp_logs';
        $cooldown   = (int) Helpers::get_option('resend_cooldown', 60);
        $daily_cap  = (int) Helpers::get_option('daily_cap', 5);
        $ip         = self::get_client_ip();

        $cooldown_hit = (int) $wpdb->get_var( $wpdb->prepare(
          "SELECT COUNT(*) FROM $table WHERE phone=%s AND created_at >= (NOW() - INTERVAL %d SECOND)",
          $phone, $cooldown
        ));
        if ( $cooldown_hit > 0 ) {
          return new \WP_Error('cooldown', __('لطفاً کمی صبر کنید و دوباره امتحان کنید.', 'wp-quickotp'));
        }

        $daily_hit = (int) $wpdb->get_var( $wpdb->prepare(
          "SELECT COUNT(*) FROM $table WHERE phone=%s AND created_at >= (NOW() - INTERVAL 1 DAY)",
          $phone
        ));
        if ( $daily_hit >= $daily_cap ) {
          return new \WP_Error('daily_cap', __('تعداد درخواست‌های مجاز امروز برای این شماره به پایان رسیده است.', 'wp-quickotp'));
        }
        // === END: SERVER RATE LIMIT ===

        $otp_length = Helpers::get_option( 'otp_length', 6 );
        $min = pow( 10, $otp_length - 1 );
        $max = pow( 10, $otp_length ) - 1;
        $otp = random_int( $min, $max );

        $otp_hash = password_hash( (string)$otp, PASSWORD_DEFAULT );
        $created_at = current_time( 'mysql' );
        $otp_expiry = Helpers::get_option( 'otp_expiry', 120 );
        $expires_at = date( 'Y-m-d H:i:s', strtotime( "+$otp_expiry seconds", strtotime( $created_at ) ) );

        $wpdb->insert(
            $table,
            array(
                'phone'      => $phone,
                'otp_hash'   => $otp_hash,
                'created_at' => $created_at,
                'expires_at' => $expires_at,
                'ip'         => $ip,
                'provider'   => $provider,
                'status'     => 'pending',
            )
        );

        return $otp;
    }

    public static function verify_otp( $phone, $otp ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';

        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE phone = %s AND status = 'pending' ORDER BY created_at DESC LIMIT 1",
            $phone
        ) );

        if ( ! $record ) {
            return false;
        }

        if ( strtotime( current_time( 'mysql' ) ) > strtotime( $record->expires_at ) ) {
            self::update_otp_status( $record->id, 'expired' );
            return false;
        }

        $max_attempts = Helpers::get_option( 'max_attempts', 5 );
        if ( $record->attempts >= $max_attempts ) {
            self::update_otp_status( $record->id, 'max_attempts_reached' );
            return false;
        }

        $wpdb->update(
            $table_name,
            array( 'attempts' => $record->attempts + 1 ),
            array( 'id' => $record->id )
        );

        if ( password_verify( (string)$otp, $record->otp_hash ) ) {
            self::update_otp_status( $record->id, 'verified' );
            return true;
        }

        return false;
    }

    private static function update_otp_status( $id, $status ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';
        $wpdb->update(
            $table_name,
            array( 'status' => $status ),
            array( 'id' => $id )
        );
    }

    public static function get_client_ip() {
        $ipaddress = '';
        if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        return $ipaddress;
    }
}

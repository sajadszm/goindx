<?php

class WPQO_OTP {

    public static function generate_and_store_otp( $phone, $provider ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';

        $otp_length = wpqo_get_option( 'otp_length', 6 );
        $min = pow( 10, $otp_length - 1 );
        $max = pow( 10, $otp_length ) - 1;
        $otp = random_int( $min, $max );

        $otp_hash = wp_hash_password( $otp );
        $created_at = current_time( 'mysql' );
        $otp_expiry = wpqo_get_option( 'otp_expiry', 120 );
        $expires_at = date( 'Y-m-d H:i:s', strtotime( "+$otp_expiry seconds", strtotime( $created_at ) ) );
        $ip = self::get_client_ip();

        $wpdb->insert(
            $table_name,
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

        $max_attempts = wpqo_get_option( 'max_attempts', 5 );
        if ( $record->attempts >= $max_attempts ) {
            self::update_otp_status( $record->id, 'max_attempts_reached' );
            return false;
        }

        $wpdb->update(
            $table_name,
            array( 'attempts' => $record->attempts + 1 ),
            array( 'id' => $record->id )
        );

        if ( wp_check_password( $otp, $record->otp_hash ) ) {
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

    private static function get_client_ip() {
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

    public static function normalize_phone( $phone ) {
        // Remove all non-numeric characters from the phone number.
        $phone = preg_replace( '/\D/', '', $phone );
        // Remove leading zeros.
        $phone = ltrim( $phone, '0' );
        // If the number starts with the country code, remove it.
        $default_country_code = wpqo_get_option( 'default_country_code', '98' );
        if ( substr( $phone, 0, strlen( $default_country_code ) ) === $default_country_code ) {
            $phone = substr( $phone, strlen( $default_country_code ) );
        }
        // Add a leading zero to the number.
        $phone = '0' . $phone;
        return $phone;
    }

    public static function login_or_register_user( $phone ) {
        $normalized_phone = self::normalize_phone( $phone );
        $user = get_user_by( 'login', $normalized_phone );

        if ( $user ) {
            wp_set_current_user( $user->ID, $normalized_phone );
            wp_set_auth_cookie( $user->ID );
            do_action( 'wp_login', $normalized_phone, $user );
            do_action( 'wpqo_after_login_success', $user );
            return $user->ID;
        } else {
            $password = wp_generate_password();
            $user_id = wp_create_user( $normalized_phone, $password );
            if ( is_wp_error( $user_id ) ) {
                do_action( 'wpqo_after_login_failure', $phone, $user_id );
                return $user_id;
            }
            $user = get_user_by( 'id', $user_id );
            $user->set_role( 'customer' );

            wp_set_current_user( $user_id, $normalized_phone );
            wp_set_auth_cookie( $user_id );
            do_action( 'wp_login', $normalized_phone, $user );
            do_action( 'wpqo_after_registration', $user );
            return $user_id;
        }
    }
}

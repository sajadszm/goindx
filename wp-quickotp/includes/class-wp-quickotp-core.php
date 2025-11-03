<?php

class WP_QuickOTP_Core {

    public static function generate_and_store_otp( $phone, $provider ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';

        $otp = random_int( 100000, 999999 );
        $otp_hash = wp_hash_password( $otp );
        $created_at = current_time( 'mysql' );
        $expires_at = date( 'Y-m-d H:i:s', strtotime( '+2 minutes', strtotime( $created_at ) ) );
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

        if ( $record->attempts >= 5 ) {
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
}

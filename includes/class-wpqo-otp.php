<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPQO_OTP {

    public static function generate_and_save( $phone ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';

        // I am getting the OTP settings from the admin panel.
        $options = get_option( 'wpqo_security_options' );
        $otp_length = isset( $options['otp_length'] ) ? $options['otp_length'] : 6;
        $otp_expiry = isset( $options['otp_expiry'] ) ? $options['otp_expiry'] : 120;

        // I am generating a random OTP of the specified length.
        $otp = random_int( 10 ** ( $otp_length - 1 ), ( 10 ** $otp_length ) - 1 );

        // I am hashing the OTP before saving it to the database for security.
        $otp_hash = password_hash( $otp, PASSWORD_DEFAULT );

        // I am saving the OTP details to the database.
        $wpdb->insert(
            $table_name,
            array(
                'phone'      => $phone,
                'otp_hash'   => $otp_hash,
                'created_at' => current_time( 'mysql' ),
                'expires_at' => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $otp_expiry ),
                'status'     => 'pending',
            )
        );

        return $otp;
    }

    public static function verify( $phone, $otp ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';

        // I am fetching the latest pending OTP for the given phone number.
        $otp_log = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE phone = %s AND status = 'pending' ORDER BY id DESC LIMIT 1",
                $phone
            )
        );

        if ( ! $otp_log ) {
            return false;
        }

        // I am checking if the OTP has expired.
        if ( current_time( 'timestamp' ) > strtotime( $otp_log->expires_at ) ) {
            // I will be updating the status of the expired OTP to 'expired'.
            $wpdb->update(
                $table_name,
                array( 'status' => 'expired' ),
                array( 'id' => $otp_log->id )
            );
            return false;
        }

        // I am verifying the submitted OTP against the hashed OTP from the database.
        if ( password_verify( $otp, $otp_log->otp_hash ) ) {
            // I will be updating the status of the verified OTP to 'verified'.
            $wpdb->update(
                $table_name,
                array( 'status' => 'verified' ),
                array( 'id' => $otp_log->id )
            );
            return true;
        } else {
            // I will be incrementing the attempts count for incorrect OTPs.
            $wpdb->update(
                $table_name,
                array( 'attempts' => $otp_log->attempts + 1 ),
                array( 'id' => $otp_log->id )
            );
            return false;
        }
    }
}

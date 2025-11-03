<?php

class WPQO_Logger {

    public static function log( $data ) {
        if ( ! wpqo_get_option( 'enable_logging' ) ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';
        $wpdb->insert( $table_name, $data );
    }

    public static function get_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';
        return $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC" );
    }
}

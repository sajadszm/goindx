<?php

class WPQO_Activator {
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            otp_hash varchar(255) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            expires_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            attempts int(11) DEFAULT 0 NOT NULL,
            ip varchar(100) DEFAULT '' NOT NULL,
            provider varchar(50) DEFAULT '' NOT NULL,
            status varchar(20) DEFAULT '' NOT NULL,
            meta text DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

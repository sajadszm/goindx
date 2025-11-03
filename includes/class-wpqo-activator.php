<?php
namespace WPQuickOTP;
if ( ! defined( 'ABSPATH' ) ) exit;

class Activator {
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          phone VARCHAR(32) NOT NULL,
          otp_hash VARCHAR(255) NOT NULL,
          created_at DATETIME NOT NULL,
          expires_at DATETIME NOT NULL,
          attempts TINYINT UNSIGNED DEFAULT 0,
          ip VARCHAR(64) DEFAULT NULL,
          provider VARCHAR(32) DEFAULT NULL,
          status VARCHAR(32) DEFAULT 'sent',
          meta TEXT NULL,
          PRIMARY KEY  (id),
          KEY phone_idx (phone),
          KEY ip_idx (ip),
          KEY created_idx (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

<?php

namespace WPQuickOTP;

if ( ! defined( 'ABSPath' ) ) {
    exit;
}

class WP_QuickOTP {

    public function __construct() {
        $this->include_files();
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
    }

    public function include_files() {
        require_once WP_QUICKOTP_PLUGIN_DIR . 'includes/class-wp-quickotp-ajax.php';
        require_once WP_QUICKOTP_PLUGIN_DIR . 'includes/class-wp-quickotp-sms.php';
        require_once WP_QUICKOTP_PLUGIN_DIR . 'includes/class-wp-quickotp-settings.php';
        require_once WP_QUICKOTP_PLUGIN_DIR . 'includes/class-wp-quickotp-template-loader.php';
        require_once WP_QUICKOTP_PLUGIN_DIR . 'includes/class-wp-quickotp-logger.php';
        require_once WP_QUICKOTP_PLUGIN_DIR . 'includes/helpers.php';
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'wp-quickotp', false, dirname( plugin_basename( WP_QUICKOTP_PLUGIN_FILE ) ) . '/languages' );
    }
}

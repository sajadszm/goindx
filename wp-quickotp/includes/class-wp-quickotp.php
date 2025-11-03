<?php

namespace WPQuickOTP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_QuickOTP {

    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants() {
        define( 'WP_QUICKOTP_PLUGIN_URL', plugin_dir_url( WP_QUICKOTP_PLUGIN_FILE ) );
        define( 'WP_QUICKOTP_PLUGIN_PATH', plugin_dir_path( WP_QUICKOTP_PLUGIN_FILE ) );
    }

    private function includes() {
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wp-quickotp-ajax.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wp-quickotp-sms.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wp-quickotp-settings.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wp-quickotp-template-loader.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wp-quickotp-logger.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/helpers.php';
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        register_activation_hook( WP_QUICKOTP_PLUGIN_FILE, array( $this, 'activate' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'wp-quickotp', false, dirname( plugin_basename( WP_QUICKOTP_PLUGIN_FILE ) ) . '/languages' );
    }

    public function activate() {
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wp-quickotp-activator.php';
        Activator::activate();
    }
}

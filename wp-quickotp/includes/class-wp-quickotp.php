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
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/helpers.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-otp.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-sms-providers.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-frontend.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-admin.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-rest-api.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-woocommerce.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-activator.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-popup.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-sessions.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-logger.php';
        require_once WP_QUICKOTP_PLUGIN_PATH . 'includes/class-wpqo-tools.php';
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        register_activation_hook( WP_QUICKOTP_PLUGIN_FILE, array( __NAMESPACE__ . '\Activator', 'activate' ) );

        new Frontend();
        new Admin();
        new REST_API();
        new WooCommerce();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'wp-quickotp', false, dirname( plugin_basename( WP_QUICKOTP_PLUGIN_FILE ) ) . '/languages' );
    }
}

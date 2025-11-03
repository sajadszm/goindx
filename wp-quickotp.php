<?php
/**
 * Plugin Name: WP QuickOTP
 * Description: Passwordless, mobile-number + OTP login and registration for WooCommerce.
 * Version: 1.0.0
 * Author: Jules
 * Text Domain: wp-quickotp
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// I am defining the constants for the plugin path and URL.
define( 'WPQO_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPQO_URL', plugin_dir_url( __FILE__ ) );
define( 'WPQO_VERSION', '1.0.0' );

// I will now include all the necessary class files.
require_once WPQO_PATH . 'includes/class-wpqo-activator.php';
require_once WPQO_PATH . 'includes/class-wpqo-admin.php';
require_once WPQO_PATH . 'includes/class-wpqo-frontend.php';
require_once WPQO_PATH . 'includes/class-wpqo-rest-api.php';
require_once WPQO_PATH . 'includes/class-wpqo-woocommerce.php';
require_once WPQO_PATH . 'includes/class-wpqo-otp.php';
require_once WPQO_PATH . 'includes/sms-providers/interface-wpqo-sms-provider.php';
require_once WPQO_PATH . 'includes/sms-providers/class-wpqo-sms-provider-smsir.php';
require_once WPQO_PATH . 'includes/sms-providers/class-wpqo-sms-provider-melipayamak.php';

// I will register the activation hook to create the custom database table.
register_activation_hook( __FILE__, array( 'WPQO_Activator', 'activate' ) );

// I am creating a function to initialize the plugin.
function wpqo_init() {
    new WPQO_Admin();
    new WPQO_Frontend();
    new WPQO_REST_API();
    new WPQO_WooCommerce();
}
// I am hooking the initialization function to the 'plugins_loaded' action.
add_action( 'plugins_loaded', 'wpqo_init' );

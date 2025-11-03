<?php
/**
 * Plugin Name:       WP-QuickOTP
 * Plugin URI:        https://example.com/
 * Description:       Passwordless, mobile-number + OTP login and registration for WooCommerce.
 * Version:           3.0.0
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-quickotp
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WP_QUICKOTP_VERSION', '3.0.0' );
define( 'WP_QUICKOTP_PLUGIN_FILE', __FILE__ );
define( 'WP_QUICKOTP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once WP_QUICKOTP_PLUGIN_DIR . 'includes/class-wp-quickotp.php';

new WPQuickOTP\WP_QuickOTP();

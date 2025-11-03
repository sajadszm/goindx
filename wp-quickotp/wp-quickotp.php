<?php
/**
 * Plugin Name:       WP-QuickOTP
 * Plugin URI:        https://example.com/
 * Description:       Passwordless, mobile-number + OTP login and registration for WooCommerce.
 * Version:           1.0.0
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
/**
 * The core plugin class.
 */
final class WP_QuickOTP {
	/**
	 * The single instance of the class.
	 */
	protected static $_instance = null;
	/**
	 * Main WP_QuickOTP Instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	/**
	 * WP_QuickOTP Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}
	/**
	 * Define WC Constants.
	 */
	private function define_constants() {
		define( 'WP_QUICKOTP_PLUGIN_FILE', __FILE__ );
		define( 'WP_QUICKOTP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'WP_QUICKOTP_VERSION', '1.0.0' );
	}
	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {
		/**
		 * Class autoloader.
		 */
		include_once dirname( __FILE__ ) . '/includes/class-wp-quickotp-autoloader.php';
		if ( is_admin() ) {
			include_once dirname( __FILE__ ) . '/admin/class-wp-quickotp-admin.php';
		}
        if ( ! is_admin() ) {
            include_once dirname( __FILE__ ) . '/public/class-wp-quickotp-public.php';
        }
        if ( class_exists( 'WooCommerce' ) ) {
            include_once dirname( __FILE__ ) . '/includes/class-wp-quickotp-woocommerce.php';
        }
        include_once dirname( __FILE__ ) . '/includes/class-wp-quickotp-rest-api.php';
	}
	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( 'WP_QuickOTP_Activator', 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), -1 );
        add_action( 'wp_quickotp_cleanup_expired_otps_hook', array( $this, 'cleanup_expired_otps' ) );
	}
	/**
	 * On plugins_loaded action.
	 */
	public function on_plugins_loaded() {
		if ( ! wp_next_scheduled( 'wp_quickotp_cleanup_expired_otps_hook' ) ) {
            wp_schedule_event( time(), 'hourly', 'wp_quickotp_cleanup_expired_otps_hook' );
        }
	}
    /**
     * On deactivation, unschedule the cron job.
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'wp_quickotp_cleanup_expired_otps_hook' );
    }
    /**
     * Cleanup expired OTPs.
     */
    public function cleanup_expired_otps() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quickotp_otp_logs';
        $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE expires_at < %s", current_time( 'mysql' ) ) );
    }
}
/**
 * Begins execution of the plugin.
 */
function wp_quickotp() {
	return WP_QuickOTP::instance();
}
// Global for backwards compatibility.
$GLOBALS['wp_quickotp'] = wp_quickotp();

<?php
/**
 * Plugin Name:       Telegram Live Chat
 * Plugin URI:        https://example.com/plugins/telegram-live-chat/
 * Description:       Integrates a live chat widget on your website that connects with support agents via Telegram.
 * Version:           0.1.0
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       telegram-live-chat
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Define plugin constants
 */
define( 'TLC_VERSION', '0.1.0' );
define( 'TLC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TLC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TLC_PLUGIN_PREFIX', 'tlc_' ); // Telegram Live Chat

/**
 * The code that runs during plugin activation.
 */
function activate_telegram_live_chat() {
    require_once TLC_PLUGIN_DIR . 'includes/class-tlc-activator.php';
    TLC_Activator::activate();

    // Schedule cron job if enabled by default or after settings are set
    // It's better to call this after initial options are set by activator.
    // TLC_Plugin instance is needed, or make schedule_or_unschedule_polling_cron static.
    // For simplicity, let's ensure it's checked/run on next admin_init or similar if not here.
    // The current implementation hooks into settings changes and will schedule if enabled.
    // We can also explicitly call it.
    if (class_exists('TLC_Plugin')) {
        $plugin_instance = new TLC_Plugin();
        $plugin_instance->schedule_or_unschedule_polling_cron();
         error_log(TLC_PLUGIN_PREFIX . "Called schedule_or_unschedule_polling_cron on activation.");
    }
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_telegram_live_chat() {
    // Unschedule cron job
    $cron_hook = TLC_PLUGIN_PREFIX . 'telegram_polling_cron';
    if ( wp_next_scheduled( $cron_hook ) ) {
        wp_clear_scheduled_hook( $cron_hook );
        error_log(TLC_PLUGIN_PREFIX . "Unscheduled polling cron on deactivation.");
    }

    require_once TLC_PLUGIN_DIR . 'includes/class-tlc-deactivator.php';
    TLC_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_telegram_live_chat' );
register_deactivation_hook( __FILE__, 'deactivate_telegram_live_chat' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require TLC_PLUGIN_DIR . 'includes/class-tlc-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.1.0
 */
function run_telegram_live_chat() {

    $plugin = new TLC_Plugin();
    $plugin->run();

}
run_telegram_live_chat();

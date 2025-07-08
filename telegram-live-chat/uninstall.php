<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider whether data should be removed during
 * plugin uninstallation. If the plugin performance operations that represent
 * tangible business costs, it may be better to err on the side of leaving data
 * in place when plugins are uninstalled.
 *
 * @link       https://example.com
 * @since      0.1.0
 *
 * @package    TLC
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if the user has opted to remove data upon uninstall.
// This option should be set in the plugin's settings page.
// Example: $remove_data = get_option( TLC_PLUGIN_PREFIX . 'enable_cleanup_on_uninstall' );
$remove_data = get_option( 'tlc_enable_cleanup_on_uninstall' );


if ( $remove_data ) {
    // Remove plugin options
    // Example: delete_option( TLC_PLUGIN_PREFIX . 'bot_token' );
    // Example: delete_option( TLC_PLUGIN_PREFIX . 'admin_user_ids' );
    // Example: delete_option( TLC_PLUGIN_PREFIX . 'enable_cleanup_on_uninstall' );

    // For now, let's list options we anticipate from Phase 1 plan
    delete_option('tlc_bot_token');
    delete_option('tlc_admin_user_ids');
    delete_option('tlc_telegram_chat_id_group');
    delete_option('tlc_enable_telegram_polling');
    delete_option('tlc_polling_interval');
    delete_option('tlc_last_telegram_update_id');
    delete_option('tlc_enable_cleanup_on_uninstall'); // Also delete the option itself

    // Remove custom database tables
    global $wpdb;
    // Example: $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tlc_chat_sessions" );
    // Example: $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tlc_chat_messages" );
    // These table names will be confirmed/defined in Step 3 of Phase 1.
    // For now, using placeholder names based on the plan.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tlc_chat_sessions" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tlc_chat_messages" );

    // Remove any other plugin-specific data, like user meta or cron jobs.
    // Example: delete_metadata( 'user', 0, 'tlc_user_preference', '', true );
    // Example: wp_clear_scheduled_hook( 'tlc_daily_cron_event' );
}

// Note: The TLC_PLUGIN_PREFIX constant is not available here as the main plugin file is not loaded.
// We have to use the literal string 'tlc_' or ensure any options/meta keys are hardcoded or retrieved differently.
// For simplicity, I've used hardcoded option names matching the prefix.
// A more robust way for options would be to fetch all options starting with the prefix.
/*
global $wpdb;
$plugin_options = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'tlc_%'" );
foreach ( $plugin_options as $option ) {
    delete_option( $option->option_name );
}
*/

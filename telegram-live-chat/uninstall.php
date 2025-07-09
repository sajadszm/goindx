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

    // Widget Customization Options
    delete_option('tlc_widget_header_bg_color');
    delete_option('tlc_widget_header_text_color');
    delete_option('tlc_chat_button_bg_color');
    delete_option('tlc_chat_button_icon_color');
    delete_option('tlc_visitor_msg_bg_color');
    delete_option('tlc_visitor_msg_text_color');
    delete_option('tlc_agent_msg_bg_color');
    delete_option('tlc_agent_msg_text_color');
    delete_option('tlc_widget_header_title');
    delete_option('tlc_widget_welcome_message');
    delete_option('tlc_widget_offline_message');
    delete_option('tlc_widget_position');
    delete_option('tlc_widget_icon_shape');
    delete_option('tlc_widget_hide_desktop');
    delete_option('tlc_widget_hide_mobile');
    delete_option('tlc_widget_custom_css');

    // Auto Message 1 Options
    delete_option('tlc_auto_msg_1_enable');
    delete_option('tlc_auto_msg_1_text');
    delete_option('tlc_auto_msg_1_trigger_type');
    delete_option('tlc_auto_msg_1_trigger_value');
    delete_option('tlc_auto_msg_1_page_targeting');
    delete_option('tlc_auto_msg_1_specific_urls');

    // Work Hours Options
    delete_option('tlc_work_hours');
    delete_option('tlc_offline_behavior');

    // File Upload Options
    delete_option('tlc_file_uploads_enable');
    delete_option('tlc_file_uploads_allowed_types');
    delete_option('tlc_file_uploads_max_size_mb');

    // Rate Limiting Options
    delete_option('tlc_rate_limit_enable');
    delete_option('tlc_rate_limit_threshold');
    delete_option('tlc_rate_limit_period_seconds');

    // Canned Responses Option
    delete_option('tlc_canned_responses');

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

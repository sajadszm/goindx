<?php

// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

$options = get_option( 'wp_quickotp_options' );

if ( isset( $options['delete_data_on_uninstall'] ) && $options['delete_data_on_uninstall'] ) {
    delete_option( 'wp_quickotp_options' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'quickotp_otp_logs';
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );

    $logs = get_posts( array( 'post_type' => 'wpqo_log', 'numberposts' => -1 ) );
    foreach ( $logs as $log ) {
        wp_delete_post( $log->ID, true );
    }
}

<?php

class WPQO_Tools {

    public function __construct() {
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    public function page_init() {
        add_settings_section(
            'support_section',
            __( 'Support & Tools', 'wp-quickotp' ),
            array( $this, 'render_support_page' ),
            'wp-quickotp-admin-support'
        );
    }

    public function render_support_page() {
        ?>
        <div class="wrap">
            <h2><?php _e( 'System Info', 'wp-quickotp' ); ?></h2>
            <textarea readonly="readonly" style="width: 100%; height: 300px;"><?php echo $this->get_system_info(); ?></textarea>
            <h2><?php _e( 'Tools', 'wp-quickotp' ); ?></h2>
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'wp-quickotp-admin', 'tab' => 'support', 'clear_cache' => 'true' ) ), 'wpqo_clear_cache' ) ); ?>" class="button"><?php _e( 'Clear Cache', 'wp-quickotp' ); ?></a>
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'wp-quickotp-admin', 'tab' => 'support', 'reset_settings' => 'true' ) ), 'wpqo_reset_settings' ) ); ?>" class="button"><?php _e( 'Reset Settings', 'wp-quickotp' ); ?></a>
        </div>
        <?php
    }

    private function get_system_info() {
        global $wp_version;
        $info = '';
        $info .= 'WordPress Version: ' . $wp_version . "\n";
        $info .= 'PHP Version: ' . phpversion() . "\n";
        $info .= 'WooCommerce Version: ' . WC()->version . "\n";
        $info .= 'WP-QuickOTP Version: ' . WP_QUICKOTP_VERSION . "\n";
        return $info;
    }

    public static function clear_cache() {
        if ( isset( $_GET['clear_cache'] ) && $_GET['clear_cache'] === 'true' && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'wpqo_clear_cache' ) ) {
            // Clear cache logic here.
            wp_redirect( admin_url( 'admin.php?page=wp-quickotp-admin&tab=support&cache_cleared=true' ) );
            exit;
        }
    }

    public static function reset_settings() {
        if ( isset( $_GET['reset_settings'] ) && $_GET['reset_settings'] === 'true' && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'wpqo_reset_settings' ) ) {
            delete_option( 'wp_quickotp_options' );
            wp_redirect( admin_url( 'admin.php?page=wp-quickotp-admin&tab=support&settings_reset=true' ) );
            exit;
        }
    }
}

new WPQO_Tools();

add_action( 'admin_init', array( 'WPQO_Tools', 'clear_cache' ) );
add_action( 'admin_init', array( 'WPQO_Tools', 'reset_settings' ) );

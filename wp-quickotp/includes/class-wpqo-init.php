<?php

class WPQO_Init {

    public function __construct() {
        $this->include_files();
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
    }

    public function include_files() {
        require_once plugin_dir_path( __FILE__ ) . 'class-wpqo-admin.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wpqo-frontend.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wpqo-otp.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wpqo-sms-providers.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wpqo-woocommerce.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wpqo-rest-api.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wpqo-popup.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wpqo-tools.php';
        require_once plugin_dir_path( __FILE__ ) . 'helpers.php';
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'wp-quickotp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}

new WPQO_Init();

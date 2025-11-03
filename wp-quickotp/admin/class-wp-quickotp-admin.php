<?php

class WP_QuickOTP_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'wp-quickotp-admin',
            plugin_dir_url( __FILE__ ) . 'js/wp-quickotp-admin.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );
    }

    public function add_plugin_page() {
        add_menu_page(
            __( 'WP Quick OTP', 'wp-quickotp' ),
            __( 'WP Quick OTP', 'wp-quickotp' ),
            'manage_options',
            'wp-quickotp-admin',
            array( $this, 'create_admin_page' ),
            'dashicons-smartphone',
            100
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'WP Quick OTP Settings', 'wp-quickotp' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'wp_quickotp_option_group' );
                    do_settings_sections( 'wp-quickotp-admin' );
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'wp_quickotp_option_group',
            'wp_quickotp_options',
            array( $this, 'sanitize' )
        );

        add_settings_section(
            'setting_section_id',
            __( 'General Settings', 'wp-quickotp' ),
            array( $this, 'print_section_info' ),
            'wp-quickotp-admin'
        );

        add_settings_field(
            'template',
            __( 'Template', 'wp-quickotp' ),
            array( $this, 'template_callback' ),
            'wp-quickotp-admin',
            'setting_section_id'
        );

        add_settings_section(
            'sms_provider_section_id',
            __( 'SMS Provider Settings', 'wp-quickotp' ),
            array( $this, 'print_sms_provider_section_info' ),
            'wp-quickotp-admin'
        );

        add_settings_field(
            'sms_provider',
            __( 'SMS Provider', 'wp-quickotp' ),
            array( $this, 'sms_provider_callback' ),
            'wp-quickotp-admin',
            'sms_provider_section_id'
        );

        add_settings_field(
            'smsir_api_key',
            __( 'sms.ir API Key', 'wp-quickotp' ),
            array( $this, 'smsir_api_key_callback' ),
            'wp-quickotp-admin',
            'sms_provider_section_id'
        );

        add_settings_field(
            'smsir_template_id',
            __( 'sms.ir Template ID', 'wp-quickotp' ),
            array( $this, 'smsir_template_id_callback' ),
            'wp-quickotp-admin',
            'sms_provider_section_id'
        );

        add_settings_field(
            'melipayamak_username',
            __( 'Melipayamak Username', 'wp-quickotp' ),
            array( $this, 'melipayamak_username_callback' ),
            'wp-quickotp-admin',
            'sms_provider_section_id'
        );

        add_settings_field(
            'melipayamak_password',
            __( 'Melipayamak Password', 'wp-quickotp' ),
            array( $this, 'melipayamak_password_callback' ),
            'wp-quickotp-admin',
            'sms_provider_section_id'
        );

        add_settings_field(
            'melipayamak_from',
            __( 'Melipayamak From Number', 'wp-quickotp' ),
            array( $this, 'melipayamak_from_callback' ),
            'wp-quickotp-admin',
            'sms_provider_section_id'
        );

        add_settings_section(
            'woocommerce_section_id',
            __( 'WooCommerce Settings', 'wp-quickotp' ),
            array( $this, 'print_woocommerce_section_info' ),
            'wp-quickotp-admin'
        );

        add_settings_field(
            'require_otp_at_checkout',
            __( 'Require OTP at checkout', 'wp-quickotp' ),
            array( $this, 'require_otp_at_checkout_callback' ),
            'wp-quickotp-admin',
            'woocommerce_section_id'
        );

        add_settings_field(
            'otp_page_id',
            __( 'OTP Page', 'wp-quickotp' ),
            array( $this, 'otp_page_id_callback' ),
            'wp-quickotp-admin',
            'woocommerce_section_id'
        );
    }

    public function sanitize( $input ) {
        $new_input = array();
        if ( isset( $input['template'] ) ) {
            $new_input['template'] = sanitize_text_field( $input['template'] );
        }
        if ( isset( $input['sms_provider'] ) ) {
            $new_input['sms_provider'] = sanitize_text_field( $input['sms_provider'] );
        }
        if ( isset( $input['smsir_api_key'] ) ) {
            $new_input['smsir_api_key'] = sanitize_text_field( $input['smsir_api_key'] );
        }
        if ( isset( $input['smsir_template_id'] ) ) {
            $new_input['smsir_template_id'] = sanitize_text_field( $input['smsir_template_id'] );
        }
        if ( isset( $input['melipayamak_username'] ) ) {
            $new_input['melipayamak_username'] = sanitize_text_field( $input['melipayamak_username'] );
        }
        if ( isset( $input['melipayamak_password'] ) ) {
            $new_input['melipayamak_password'] = sanitize_text_field( $input['melipayamak_password'] );
        }
        if ( isset( $input['melipayamak_from'] ) ) {
            $new_input['melipayamak_from'] = sanitize_text_field( $input['melipayamak_from'] );
        }
        if ( isset( $input['require_otp_at_checkout'] ) ) {
            $new_input['require_otp_at_checkout'] = absint( $input['require_otp_at_checkout'] );
        }
        if ( isset( $input['otp_page_id'] ) ) {
            $new_input['otp_page_id'] = absint( $input['otp_page_id'] );
        }
        return $new_input;
    }

    public function print_section_info() {
        print __( 'Manage the template and appearance of the plugin in this section.', 'wp-quickotp' );
    }

    public function print_sms_provider_section_info() {
        print __( 'Manage the SMS provider settings in this section.', 'wp-quickotp' );
    }

    public function print_woocommerce_section_info() {
        print __( 'Manage the WooCommerce settings in this section.', 'wp-quickotp' );
    }

    public function template_callback() {
        $options = get_option( 'wp_quickotp_options' );
        ?>
        <select id="template" name="wp_quickotp_options[template]">
            <option value="template-simple" <?php selected( $options['template'], 'template-simple' ); ?>><?php _e( 'Simple', 'wp-quickotp' ); ?></option>
            <option value="template-digikala" <?php selected( $options['template'], 'template-digikala' ); ?>><?php _e( 'Digikala', 'wp-quickotp' ); ?></option>
        </select>
        <?php
    }

    public function sms_provider_callback() {
        $options = get_option( 'wp_quickotp_options' );
        ?>
        <select id="sms_provider" name="wp_quickotp_options[sms_provider]">
            <option value="smsir" <?php selected( $options['sms_provider'], 'smsir' ); ?>><?php _e( 'sms.ir', 'wp-quickotp' ); ?></option>
            <option value="melipayamak" <?php selected( $options['sms_provider'], 'melipayamak' ); ?>><?php _e( 'Melipayamak', 'wp-quickotp' ); ?></option>
        </select>
        <?php
    }

    public function smsir_api_key_callback() {
        $options = get_option( 'wp_quickotp_options' );
        printf(
            '<input type="text" id="smsir_api_key" name="wp_quickotp_options[smsir_api_key]" value="%s" />',
            isset( $options['smsir_api_key'] ) ? esc_attr( $options['smsir_api_key']) : ''
        );
    }

    public function smsir_template_id_callback() {
        $options = get_option( 'wp_quickotp_options' );
        printf(
            '<input type="text" id="smsir_template_id" name="wp_quickotp_options[smsir_template_id]" value="%s" />',
            isset( $options['smsir_template_id'] ) ? esc_attr( $options['smsir_template_id']) : ''
        );
    }

    public function melipayamak_username_callback() {
        $options = get_option( 'wp_quickotp_options' );
        printf(
            '<input type="text" id="melipayamak_username" name="wp_quickotp_options[melipayamak_username]" value="%s" />',
            isset( $options['melipayamak_username'] ) ? esc_attr( $options['melipayamak_username']) : ''
        );
    }

    public function melipayamak_password_callback() {
        $options = get_option( 'wp_quickotp_options' );
        printf(
            '<input type="password" id="melipayamak_password" name="wp_quickotp_options[melipayamak_password]" value="%s" />',
            isset( $options['melipayamak_password'] ) ? esc_attr( $options['melipayamak_password']) : ''
        );
    }

    public function melipayamak_from_callback() {
        $options = get_option( 'wp_quickotp_options' );
        printf(
            '<input type="text" id="melipayamak_from" name="wp_quickotp_options[melipayamak_from]" value="%s" />',
            isset( $options['melipayamak_from'] ) ? esc_attr( $options['melipayamak_from']) : ''
        );
    }

    public function require_otp_at_checkout_callback() {
        $options = get_option( 'wp_quickotp_options' );
        ?>
        <input type="checkbox" id="require_otp_at_checkout" name="wp_quickotp_options[require_otp_at_checkout]" value="1" <?php checked( 1, $options['require_otp_at_checkout'], true ); ?> />
        <?php
    }

    public function otp_page_id_callback() {
        $options = get_option( 'wp_quickotp_options' );
        wp_dropdown_pages( array(
            'name'              => 'wp_quickotp_options[otp_page_id]',
            'selected'          => $options['otp_page_id'],
            'show_option_none'  => __( 'Select a page', 'wp-quickotp' ),
        ) );
    }
}

if ( is_admin() ) {
    new WP_QuickOTP_Admin();
}

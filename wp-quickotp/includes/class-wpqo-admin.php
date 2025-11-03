<?php

class WPQO_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( 'WPQO_Admin', 'add_plugin_page' ) );
        add_action( 'admin_init', array( 'WPQO_Admin', 'page_init' ) );
        add_action( 'admin_init', array( 'WPQO_Admin', 'export_logs' ) );
        add_action( 'admin_enqueue_scripts', array( 'WPQO_Admin', 'enqueue_scripts' ) );
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'wp-quickotp-admin',
            plugin_dir_url( __FILE__ ) . '../assets/css/wp-quickotp-admin.css',
            array(),
            WP_QUICKOTP_VERSION,
            'all'
        );

        wp_enqueue_script(
            'wp-quickotp-admin',
            plugin_dir_url( __FILE__ ) . '../assets/js/wp-quickotp-admin.js',
            array( 'jquery' ),
            WP_QUICKOTP_VERSION,
            true
        );
    }

    public function add_plugin_page() {
        add_menu_page(
            __( 'WP Quick OTP', 'wp-quickotp' ),
            __( 'WP Quick OTP', 'wp-quickotp' ),
            'manage_options',
            'wp-quickotp-admin',
            array( 'WPQO_Admin', 'create_admin_page' ),
            'dashicons-smartphone',
            100
        );
    }

    public function create_admin_page() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1><?php _e( 'WP Quick OTP Settings', 'wp-quickotp' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-quickotp-admin&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e( 'General', 'wp-quickotp' ); ?></a>
                <a href="?page=wp-quickotp-admin&tab=sms_providers" class="nav-tab <?php echo $active_tab == 'sms_providers' ? 'nav-tab-active' : ''; ?>"><?php _e( 'SMS Providers', 'wp-quickotp' ); ?></a>
                <a href="?page=wp-quickotp-admin&tab=login_registration" class="nav-tab <?php echo $active_tab == 'login_registration' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Login/Registration', 'wp-quickotp' ); ?></a>
                <a href="?page=wp-quickotp-admin&tab=checkout" class="nav-tab <?php echo $active_tab == 'checkout' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Checkout Integration', 'wp-quickotp' ); ?></a>
                <a href="?page=wp-quickotp-admin&tab=otp" class="nav-tab <?php echo $active_tab == 'otp' ? 'nav-tab-active' : ''; ?>"><?php _e( 'OTP Settings', 'wp-quickotp' ); ?></a>
                <a href="?page=wp-quickotp-admin&tab=security" class="nav-tab <?php echo $active_tab == 'security' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Security', 'wp-quickotp' ); ?></a>
                <a href="?page=wp-quickotp-admin&tab=templates" class="nav-tab <?php echo $active_tab == 'templates' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Templates & Appearance', 'wp-quickotp' ); ?></a>
                <a href="?page=wp-quickotp-admin&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Logs & Reports', 'wp-quickotp' ); ?></a>
                <a href="?page=wp-quickotp-admin&tab=support" class="nav-tab <?php echo $active_tab == 'support' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Support & Tools', 'wp-quickotp' ); ?></a>
            </h2>
            <?php if ( $active_tab === 'logs' ) : ?>
                <div id="wpqo-logs-container">
                    <?php WPQO_Admin::render_logs_table(); ?>
                </div>
            <?php else : ?>
                <form method="post" action="options.php">
                    <?php
                        settings_fields( 'wp_quickotp_options' );
                        do_settings_sections( 'wp-quickotp-admin-' . $active_tab );
                        submit_button();
                    ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_logs_table() {
        $logs = WPQO_Logger::get_logs();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e( 'Title', 'wp-quickotp' ); ?></th>
                    <th><?php _e( 'Date', 'wp-quickotp' ); ?></th>
                    <th><?php _e( 'Actions', 'wp-quickotp' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log->post_title ); ?></td>
                        <td><?php echo esc_html( $log->post_date ); ?></td>
                        <td>
                            <a href="#" class="view-log" data-log-id="<?php echo esc_attr( $log->ID ); ?>"><?php _e( 'View', 'wp-quickotp' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-quickotp-admin', 'tab' => 'logs', 'export_logs' => 'csv' ) ) ); ?>" class="button"><?php _e( 'Export to CSV', 'wp-quickotp' ); ?></a>
        <?php
    }

    public function page_init() {
        register_setting(
            'wp_quickotp_options',
            'wp_quickotp_options',
            array( 'WPQO_Admin', 'sanitize' )
        );

        // General Tab
        add_settings_section(
            'general_section',
            __( 'General Settings', 'wp-quickotp' ),
            null,
            'wp-quickotp-admin-general'
        );

        add_settings_field(
            'enable_plugin',
            __( 'Enable Plugin', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'checkbox_callback' ),
            'wp-quickotp-admin-general',
            'general_section',
            array( 'id' => 'enable_plugin' )
        );

        add_settings_field(
            'default_country_code',
            __( 'Default Country Code', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'text_callback' ),
            'wp-quickotp-admin-general',
            'general_section',
            array( 'id' => 'default_country_code' )
        );

        add_settings_field(
            'sender_number',
            __( 'Sender Number', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'text_callback' ),
            'wp-quickotp-admin-general',
            'general_section',
            array( 'id' => 'sender_number' )
        );

        // SMS Providers Tab
        add_settings_section(
            'sms_providers_section',
            __( 'SMS Provider Settings', 'wp-quickotp' ),
            null,
            'wp-quickotp-admin-sms_providers'
        );

        add_settings_field(
            'sms_provider',
            __( 'SMS Provider', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'select_callback' ),
            'wp-quickotp-admin-sms_providers',
            'sms_providers_section',
            array(
                'id' => 'sms_provider',
                'options' => array(
                    'smsir' => __( 'sms.ir', 'wp-quickotp' ),
                    'melipayamak' => __( 'Melipayamak', 'wp-quickotp' ),
                ),
            )
        );

        add_settings_field(
            'smsir_api_key',
            __( 'sms.ir API Key', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'text_callback' ),
            'wp-quickotp-admin-sms_providers',
            'sms_providers_section',
            array( 'id' => 'smsir_api_key' )
        );

        add_settings_field(
            'smsir_template_id',
            __( 'sms.ir Template ID', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'text_callback' ),
            'wp-quickotp-admin-sms_providers',
            'sms_providers_section',
            array( 'id' => 'smsir_template_id' )
        );

        add_settings_field(
            'melipayamak_username',
            __( 'Melipayamak Username', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'text_callback' ),
            'wp-quickotp-admin-sms_providers',
            'sms_providers_section',
            array( 'id' => 'melipayamak_username' )
        );

        add_settings_field(
            'melipayamak_password',
            __( 'Melipayamak Password', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'password_callback' ),
            'wp-quickotp-admin-sms_providers',
            'sms_providers_section',
            array( 'id' => 'melipayamak_password' )
        );

        add_settings_field(
            'melipayamak_from',
            __( 'Melipayamak From Number', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'text_callback' ),
            'wp-quickotp-admin-sms_providers',
            'sms_providers_section',
            array( 'id' => 'melipayamak_from' )
        );

        add_settings_field(
            'test_sms',
            __( 'Test SMS', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'test_sms_callback' ),
            'wp-quickotp-admin-sms_providers',
            'sms_providers_section'
        );

        // Login/Registration Tab
        add_settings_section(
            'login_registration_section',
            __( 'Login/Registration Settings', 'wp-quickotp' ),
            null,
            'wp-quickotp-admin-login_registration'
        );

        add_settings_field(
            'login_form_type',
            __( 'Login Form Type', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'select_callback' ),
            'wp-quickotp-admin-login_registration',
            'login_registration_section',
            array(
                'id' => 'login_form_type',
                'options' => array(
                    'popup' => __( 'Popup', 'wp-quickotp' ),
                    'page' => __( 'Dedicated Page', 'wp-quickotp' ),
                ),
            )
        );

        add_settings_field(
            'login_form_title',
            __( 'Login Form Title', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'text_callback' ),
            'wp-quickotp-admin-login_registration',
            'login_registration_section',
            array( 'id' => 'login_form_title' )
        );

        add_settings_field(
            'login_form_subtitle',
            __( 'Login Form Subtitle', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'text_callback' ),
            'wp-quickotp-admin-login_registration',
            'login_registration_section',
            array( 'id' => 'login_form_subtitle' )
        );

        // Checkout Integration Tab
        add_settings_section(
            'checkout_section',
            __( 'Checkout Integration Settings', 'wp-quickotp' ),
            null,
            'wp-quickotp-admin-checkout'
        );

        add_settings_field(
            'require_otp_at_checkout',
            __( 'Require OTP at checkout', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'checkbox_callback' ),
            'wp-quickotp-admin-checkout',
            'checkout_section',
            array( 'id' => 'require_otp_at_checkout' )
        );

        add_settings_field(
            'auto_fill_billing_phone',
            __( 'Auto-fill billing phone', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'checkbox_callback' ),
            'wp-quickotp-admin-checkout',
            'checkout_section',
            array( 'id' => 'auto_fill_billing_phone' )
        );

        // OTP Settings Tab
        add_settings_section(
            'otp_section',
            __( 'OTP Settings', 'wp-quickotp' ),
            null,
            'wp-quickotp-admin-otp'
        );

        add_settings_field(
            'otp_length',
            __( 'OTP Length', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'number_callback' ),
            'wp-quickotp-admin-otp',
            'otp_section',
            array( 'id' => 'otp_length', 'min' => 4, 'max' => 8 )
        );

        add_settings_field(
            'otp_expiry',
            __( 'OTP Expiry (seconds)', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'number_callback' ),
            'wp-quickotp-admin-otp',
            'otp_section',
            array( 'id' => 'otp_expiry' )
        );

        add_settings_field(
            'resend_delay',
            __( 'Resend Delay (seconds)', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'number_callback' ),
            'wp-quickotp-admin-otp',
            'otp_section',
            array( 'id' => 'resend_delay' )
        );

        add_settings_field(
            'max_attempts',
            __( 'Max Attempts', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'number_callback' ),
            'wp-quickotp-admin-otp',
            'otp_section',
            array( 'id' => 'max_attempts' )
        );

        add_settings_field(
            'daily_limit',
            __( 'Daily Send Limit', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'number_callback' ),
            'wp-quickotp-admin-otp',
            'otp_section',
            array( 'id' => 'daily_limit' )
        );

        add_settings_field(
            'lockout_duration',
            __( 'Lockout Duration (minutes)', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'number_callback' ),
            'wp-quickotp-admin-otp',
            'otp_section',
            array( 'id' => 'lockout_duration' )
        );

        // Security Tab
        add_settings_section(
            'security_section',
            __( 'Security Settings', 'wp-quickotp' ),
            null,
            'wp-quickotp-admin-security'
        );

        add_settings_field(
            'enable_rate_limiting',
            __( 'Enable Rate Limiting', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'checkbox_callback' ),
            'wp-quickotp-admin-security',
            'security_section',
            array( 'id' => 'enable_rate_limiting' )
        );

        add_settings_field(
            'enable_device_verification',
            __( 'Enable Device Verification', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'checkbox_callback' ),
            'wp-quickotp-admin-security',
            'security_section',
            array( 'id' => 'enable_device_verification' )
        );

        add_settings_field(
            'limit_concurrent_logins',
            __( 'Limit Concurrent Logins', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'checkbox_callback' ),
            'wp-quickotp-admin-security',
            'security_section',
            array( 'id' => 'limit_concurrent_logins' )
        );

        // Logs Tab
        add_settings_section(
            'logs_section',
            __( 'Logs & Reports', 'wp-quickotp' ),
            null,
            'wp-quickotp-admin-logs'
        );

        add_settings_field(
            'enable_logging',
            __( 'Enable Logging', 'wp-quickotp' ),
            array( 'WPQO_Admin', 'checkbox_callback' ),
            'wp-quickotp-admin-logs',
            'logs_section',
            array( 'id' => 'enable_logging' )
        );
    }

    public function sanitize( $input ) {
        $sanitized_input = array();
        if ( isset( $input['enable_plugin'] ) ) {
            $sanitized_input['enable_plugin'] = absint( $input['enable_plugin'] );
        }
        if ( isset( $input['default_country_code'] ) ) {
            $sanitized_input['default_country_code'] = sanitize_text_field( $input['default_country_code'] );
        }
        if ( isset( $input['sender_number'] ) ) {
            $sanitized_input['sender_number'] = sanitize_text_field( $input['sender_number'] );
        }
        if ( isset( $input['sms_provider'] ) ) {
            $sanitized_input['sms_provider'] = sanitize_text_field( $input['sms_provider'] );
        }
        if ( isset( $input['smsir_api_key'] ) ) {
            $sanitized_input['smsir_api_key'] = sanitize_text_field( $input['smsir_api_key'] );
        }
        if ( isset( $input['smsir_template_id'] ) ) {
            $sanitized_input['smsir_template_id'] = sanitize_text_field( $input['smsir_template_id'] );
        }
        if ( isset( $input['melipayamak_username'] ) ) {
            $sanitized_input['melipayamak_username'] = sanitize_text_field( $input['melipayamak_username'] );
        }
        if ( isset( $input['melipayamak_password'] ) ) {
            $sanitized_input['melipayamak_password'] = sanitize_text_field( $input['melipayamak_password'] );
        }
        if ( isset( $input['melipayamak_from'] ) ) {
            $sanitized_input['melipayamak_from'] = sanitize_text_field( $input['melipayamak_from'] );
        }
        if ( isset( $input['login_form_type'] ) ) {
            $sanitized_input['login_form_type'] = sanitize_text_field( $input['login_form_type'] );
        }
        if ( isset( $input['login_form_title'] ) ) {
            $sanitized_input['login_form_title'] = sanitize_text_field( $input['login_form_title'] );
        }
        if ( isset( $input['login_form_subtitle'] ) ) {
            $sanitized_input['login_form_subtitle'] = sanitize_text_field( $input['login_form_subtitle'] );
        }
        if ( isset( $input['require_otp_at_checkout'] ) ) {
            $sanitized_input['require_otp_at_checkout'] = absint( $input['require_otp_at_checkout'] );
        }
        if ( isset( $input['auto_fill_billing_phone'] ) ) {
            $sanitized_input['auto_fill_billing_phone'] = absint( $input['auto_fill_billing_phone'] );
        }
        if ( isset( $input['otp_length'] ) ) {
            $sanitized_input['otp_length'] = absint( $input['otp_length'] );
        }
        if ( isset( $input['otp_expiry'] ) ) {
            $sanitized_input['otp_expiry'] = absint( $input['otp_expiry'] );
        }
        if ( isset( $input['resend_delay'] ) ) {
            $sanitized_input['resend_delay'] = absint( $input['resend_delay'] );
        }
        if ( isset( $input['max_attempts'] ) ) {
            $sanitized_input['max_attempts'] = absint( $input['max_attempts'] );
        }
        if ( isset( $input['daily_limit'] ) ) {
            $sanitized_input['daily_limit'] = absint( $input['daily_limit'] );
        }
        if ( isset( $input['lockout_duration'] ) ) {
            $sanitized_input['lockout_duration'] = absint( $input['lockout_duration'] );
        }
        if ( isset( $input['enable_logging'] ) ) {
            $sanitized_input['enable_logging'] = absint( $input['enable_logging'] );
        }
        if ( isset( $input['enable_rate_limiting'] ) ) {
            $sanitized_input['enable_rate_limiting'] = absint( $input['enable_rate_limiting'] );
        }
        if ( isset( $input['enable_device_verification'] ) ) {
            $sanitized_input['enable_device_verification'] = absint( $input['enable_device_verification'] );
        }
        if ( isset( $input['limit_concurrent_logins'] ) ) {
            $sanitized_input['limit_concurrent_logins'] = absint( $input['limit_concurrent_logins'] );
        }
        return $sanitized_input;
    }

    public function checkbox_callback( $args ) {
        $id = $args['id'];
        $value = wpqo_get_option( $id );
        echo "<input type='checkbox' id='$id' name='wp_quickotp_options[$id]' value='1' " . checked( 1, $value, false ) . " />";
    }

    public function text_callback( $args ) {
        $id = $args['id'];
        $value = wpqo_get_option( $id );
        echo "<input type='text' id='$id' name='wp_quickotp_options[$id]' value='$value' />";
    }

    public function password_callback( $args ) {
        $id = $args['id'];
        $value = wpqo_get_option( $id );
        echo "<input type='password' id='$id' name='wp_quickotp_options[$id]' value='$value' />";
    }

    public function select_callback( $args ) {
        $id = $args['id'];
        $value = wpqo_get_option( $id );
        $options = $args['options'];
        echo "<select id='$id' name='wp_quickotp_options[$id]'>";
        foreach ( $options as $key => $label ) {
            echo "<option value='$key' " . selected( $value, $key, false ) . ">$label</option>";
        }
        echo "</select>";
    }

    public function number_callback( $args ) {
        $id = $args['id'];
        $value = wpqo_get_option( $id );
        $min = isset( $args['min'] ) ? $args['min'] : 0;
        $max = isset( $args['max'] ) ? $args['max'] : '';
        echo "<input type='number' id='$id' name='wp_quickotp_options[$id]' value='$value' min='$min' max='$max' />";
    }

    public function test_sms_callback() {
        echo '<input type="text" id="test_sms_phone" placeholder="' . __( 'Enter phone number', 'wp-quickotp' ) . '" />';
        echo '<a href="#" id="send_test_sms" class="button">' . __( 'Send Test SMS', 'wp-quickotp' ) . '</a>';
        echo '<p class="description">' . __( 'The settings must be saved before sending a test SMS.', 'wp-quickotp' ) . '</p>';
    }

    public function export_logs() {
        if ( isset( $_GET['export_logs'] ) && $_GET['export_logs'] === 'csv' ) {
            $logs = WPQO_Logger::get_logs();
            $csv_data = array();
            $csv_data[] = array( 'Title', 'Date', 'Data' );
            foreach ( $logs as $log ) {
                $log_data = get_post_meta( $log->ID, '_log_data', true );
                $csv_data[] = array(
                    $log->post_title,
                    $log->post_date,
                    json_encode( $log_data ),
                );
            }

            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename="wpqo-logs.csv"' );
            $output = fopen( 'php://output', 'w' );
            foreach ( $csv_data as $row ) {
                fputcsv( $output, $row );
            }
            fclose( $output );
            exit;
        }
    }
}

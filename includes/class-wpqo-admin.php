<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPQO_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
    }

    public function admin_menu() {
        add_menu_page(
            __( 'WP QuickOTP', 'wp-quickotp' ),
            __( 'WP QuickOTP', 'wp-quickotp' ),
            'manage_options',
            'wp_quickotp',
            array( $this, 'settings_page' ),
            'dashicons-smartphone'
        );
    }

    public function admin_init() {
        // General Tab
        register_setting( 'wpqo_general_options', 'wpqo_general_options', array( $this, 'sanitize_general_options' ) );
        add_settings_section( 'wpqo_general_section', __( 'تنظیمات عمومی', 'wp-quickotp' ), '__return_false', 'wpqo_general_options' );
        add_settings_field( 'wpqo_login_page', __( 'صفحه ورود', 'wp-quickotp' ), array( $this, 'render_login_page_field' ), 'wpqo_general_options', 'wpqo_general_section' );

        // SMS Tab
        register_setting( 'wpqo_sms_options', 'wpqo_sms_options', array( $this, 'sanitize_sms_options' ) );
        add_settings_section( 'wpqo_sms_provider_section', __( 'انتخاب سرویس‌دهنده', 'wp-quickotp' ), '__return_false', 'wpqo_sms_options' );
        add_settings_field( 'wpqo_sms_provider', __( 'سرویس‌دهنده پیامک', 'wp-quickotp' ), array( $this, 'render_sms_provider_field' ), 'wpqo_sms_options', 'wpqo_sms_provider_section' );
        add_settings_section( 'wpqo_smsir_section', __( 'تنظیمات sms.ir', 'wp-quickotp' ), '__return_false', 'wpqo_sms_options' );
        add_settings_field( 'wpqo_smsir_api_key', __( 'کلید API', 'wp-quickotp' ), array( $this, 'render_smsir_api_key_field' ), 'wpqo_sms_options', 'wpqo_smsir_section' );
        add_settings_section( 'wpqo_melipayamak_section', __( 'تنظیمات ملی‌پیامک', 'wp-quickotp' ), '__return_false', 'wpqo_sms_options' );
        add_settings_field( 'wpqo_melipayamak_username', __( 'نام کاربری', 'wp-quickotp' ), array( $this, 'render_melipayamak_username_field' ), 'wpqo_sms_options', 'wpqo_melipayamak_section' );
        add_settings_field( 'wpqo_melipayamak_password', __( 'گذرواژه', 'wp-quickotp' ), array( $this, 'render_melipayamak_password_field' ), 'wpqo_sms_options', 'wpqo_melipayamak_section' );
        add_settings_section( 'wpqo_sms_template_section', __( 'قالب پیامک', 'wp-quickotp' ), '__return_false', 'wpqo_sms_options' );
        add_settings_field( 'wpqo_sms_template', __( 'متن پیامک', 'wp-quickotp' ), array( $this, 'render_sms_template_field' ), 'wpqo_sms_options', 'wpqo_sms_template_section' );

        // Templates Tab
        register_setting( 'wpqo_templates_options', 'wpqo_templates_options', array( $this, 'sanitize_templates_options' ) );
        add_settings_section( 'wpqo_templates_section', __( 'انتخاب قالب', 'wp-quickotp' ), '__return_false', 'wpqo_templates_options' );
        add_settings_field( 'wpqo_template', __( 'قالب فرم ورود', 'wp-quickotp' ), array( $this, 'render_template_field' ), 'wpqo_templates_options', 'wpqo_templates_section' );
        add_settings_field( 'wpqo_custom_css', __( 'CSS سفارشی', 'wp-quickotp' ), array( $this, 'render_custom_css_field' ), 'wpqo_templates_options', 'wpqo_templates_section' );

        // WooCommerce Tab
        register_setting( 'wpqo_woocommerce_options', 'wpqo_woocommerce_options', array( $this, 'sanitize_woocommerce_options' ) );
        add_settings_section( 'wpqo_woocommerce_section', __( 'همگام‌سازی با ووکامرس', 'wp-quickotp' ), '__return_false', 'wpqo_woocommerce_options' );
        add_settings_field( 'wpqo_require_otp_at_checkout', __( 'نیاز به تایید شماره در پرداخت', 'wp-quickotp' ), array( $this, 'render_require_otp_at_checkout_field' ), 'wpqo_woocommerce_options', 'wpqo_woocommerce_section' );

        // Security Tab
        register_setting( 'wpqo_security_options', 'wpqo_security_options', array( $this, 'sanitize_security_options' ) );
        add_settings_section( 'wpqo_otp_settings_section', __( 'تنظیمات کد تایید', 'wp-quickotp' ), '__return_false', 'wpqo_security_options' );
        add_settings_field( 'wpqo_otp_length', __( 'طول کد تایید', 'wp-quickotp' ), array( $this, 'render_otp_length_field' ), 'wpqo_security_options', 'wpqo_otp_settings_section' );
        add_settings_field( 'wpqo_otp_expiry', __( 'مدت زمان اعتبار (ثانیه)', 'wp-quickotp' ), array( $this, 'render_otp_expiry_field' ), 'wpqo_security_options', 'wpqo_otp_settings_section' );
        add_settings_field( 'wpqo_resend_interval', __( 'فاصله زمانی ارسال مجدد (ثانیه)', 'wp-quickotp' ), array( $this, 'render_resend_interval_field' ), 'wpqo_security_options', 'wpqo_otp_settings_section' );
        add_settings_section( 'wpqo_rate_limiting_section', __( 'محدودیت‌ها', 'wp-quickotp' ), '__return_false', 'wpqo_security_options' );
        add_settings_field( 'wpqo_max_sends_per_day', __( 'حداکثر ارسال در 24 ساعت', 'wp-quickotp' ), array( $this, 'render_max_sends_per_day_field' ), 'wpqo_security_options', 'wpqo_rate_limiting_section' );
        add_settings_field( 'wpqo_max_verify_attempts', __( 'حداکثر تلاش برای تایید', 'wp-quickotp' ), array( $this, 'render_max_verify_attempts_field' ), 'wpqo_security_options', 'wpqo_rate_limiting_section' );

        // Logs Tab (Placeholder)
        add_settings_section( 'wpqo_logs_section', __( 'گزارشات ارسال پیامک', 'wp-quickotp' ), array( $this, 'render_logs_section' ), 'wpqo_logs_options' );
    }

    public function sanitize_general_options( $input ) {
        $output = get_option( 'wpqo_general_options' );
        if ( isset( $input['login_page'] ) ) {
            $output['login_page'] = absint( $input['login_page'] );
        }
        return $output;
    }

    public function sanitize_sms_options( $input ) {
        $output = get_option( 'wpqo_sms_options' );
        $output['provider'] = isset( $input['provider'] ) ? sanitize_text_field( $input['provider'] ) : '';
        $output['smsir_api_key'] = isset( $input['smsir_api_key'] ) ? sanitize_text_field( $input['smsir_api_key'] ) : '';
        $output['melipayamak_username'] = isset( $input['melipayamak_username'] ) ? sanitize_text_field( $input['melipayamak_username'] ) : '';
        $output['melipayamak_password'] = isset( $input['melipayamak_password'] ) ? sanitize_text_field( $input['melipayamak_password'] ) : '';
        $output['sms_template'] = isset( $input['sms_template'] ) ? sanitize_textarea_field( $input['sms_template'] ) : '';
        return $output;
    }

    public function sanitize_templates_options( $input ) {
        $output = get_option( 'wpqo_templates_options' );
        $output['template'] = isset( $input['template'] ) ? sanitize_text_field( $input['template'] ) : '';
        $output['custom_css'] = isset( $input['custom_css'] ) ? wp_strip_all_tags( $input['custom_css'] ) : '';
        return $output;
    }

    public function sanitize_woocommerce_options( $input ) {
        $output = get_option( 'wpqo_woocommerce_options' );
        $output['require_otp_at_checkout'] = isset( $input['require_otp_at_checkout'] ) ? (bool) $input['require_otp_at_checkout'] : false;
        return $output;
    }

    public function sanitize_security_options( $input ) {
        $output = get_option( 'wpqo_security_options' );
        $output['otp_length'] = isset( $input['otp_length'] ) ? absint( $input['otp_length'] ) : 6;
        $output['otp_expiry'] = isset( $input['otp_expiry'] ) ? absint( $input['otp_expiry'] ) : 120;
        $output['resend_interval'] = isset( $input['resend_interval'] ) ? absint( $input['resend_interval'] ) : 60;
        $output['max_sends_per_day'] = isset( $input['max_sends_per_day'] ) ? absint( $input['max_sends_per_day'] ) : 5;
        $output['max_verify_attempts'] = isset( $input['max_verify_attempts'] ) ? absint( $input['max_verify_attempts'] ) : 5;
        return $output;
    }

    public function render_login_page_field() {
        $options = get_option( 'wpqo_general_options' );
        $login_page = isset( $options['login_page'] ) ? $options['login_page'] : '';
        wp_dropdown_pages(
            array(
                'name'             => 'wpqo_general_options[login_page]',
                'selected'         => $login_page,
                'show_option_none' => __( 'یک صفحه را انتخاب کنید', 'wp-quickotp' ),
            )
        );
        echo '<p class="description">' . __( 'صفحه‌ای را که حاوی شورت‌کد [wp_quickotp_login] است، انتخاب کنید.', 'wp-quickotp' ) . '</p>';
    }

    public function render_sms_provider_field() {
        $options = get_option( 'wpqo_sms_options' );
        $provider = isset( $options['provider'] ) ? $options['provider'] : '';
        ?>
        <select name="wpqo_sms_options[provider]">
            <option value="smsir" <?php selected( $provider, 'smsir' ); ?>><?php _e( 'sms.ir', 'wp-quickotp' ); ?></option>
            <option value="melipayamak" <?php selected( $provider, 'melipayamak' ); ?>><?php _e( 'ملی‌پیامک', 'wp-quickotp' ); ?></option>
        </select>
        <?php
    }

    public function render_smsir_api_key_field() {
        $options = get_option( 'wpqo_sms_options' );
        $api_key = isset( $options['smsir_api_key'] ) ? $options['smsir_api_key'] : '';
        echo '<input type="text" name="wpqo_sms_options[smsir_api_key]" value="' . esc_attr( $api_key ) . '" class="regular-text">';
    }

    public function render_melipayamak_username_field() {
        $options = get_option( 'wpqo_sms_options' );
        $username = isset( $options['melipayamak_username'] ) ? $options['melipayamak_username'] : '';
        echo '<input type="text" name="wpqo_sms_options[melipayamak_username]" value="' . esc_attr( $username ) . '" class="regular-text">';
    }

    public function render_melipayamak_password_field() {
        $options = get_option( 'wpqo_sms_options' );
        $password = isset( $options['melipayamak_password'] ) ? $options['melipayamak_password'] : '';
        echo '<input type="password" name="wpqo_sms_options[melipayamak_password]" value="' . esc_attr( $password ) . '" class="regular-text">';
    }

    public function render_sms_template_field() {
        $options = get_option( 'wpqo_sms_options' );
        $template = isset( $options['sms_template'] ) ? $options['sms_template'] . '</textarea>';
        echo '<p class="description">' . __( 'از کدهای {CODE} برای نمایش کد تایید و {SITE_NAME} برای نمایش نام سایت استفاده کنید.', 'wp-quickotp' ) . '</p>';
    }

    public function render_template_field() {
        $options = get_option( 'wpqo_templates_options' );
        $template = isset( $options['template'] ) ? $options['template'] : '';
        ?>
        <select name="wpqo_templates_options[template]">
            <option value="simple" <?php selected( $template, 'simple' ); ?>><?php _e( 'ساده', 'wp-quickotp' ); ?></option>
            <option value="digikala" <?php selected( $template, 'digikala' ); ?>><?php _e( 'دیجی‌کالا', 'wp-quickotp' ); ?></option>
        </select>
        <?php
    }

    public function render_custom_css_field() {
        $options = get_option( 'wpqo_templates_options' );
        $custom_css = isset( $options['custom_css'] ) ? $options['custom_css'] : '';
        echo '<textarea name="wpqo_templates_options[custom_css]" rows="10" class="large-text">' . esc_textarea( $custom_css ) . '</textarea>';
    }

    public function render_require_otp_at_checkout_field() {
        $options = get_option( 'wpqo_woocommerce_options' );
        $checked = isset( $options['require_otp_at_checkout'] ) ? $options['require_otp_at_checkout'] : false;
        echo '<input type="checkbox" name="wpqo_woocommerce_options[require_otp_at_checkout]" value="1" ' . checked( $checked, 1, false ) . '>';
        echo '<p class="description">' . __( 'در صورتی که کاربر وارد نشده باشد، برای ثبت سفارش نیاز به تایید شماره موبایل خواهد داشت.', 'wp-quickotp' ) . '</p>';
    }

    public function render_otp_length_field() {
        $options = get_option( 'wpqo_security_options' );
        $length = isset( $options['otp_length'] ) ? $options['otp_length'] : 6;
        echo '<input type="number" name="wpqo_security_options[otp_length]" value="' . esc_attr( $length ) . '" class="small-text">';
    }

    public function render_otp_expiry_field() {
        $options = get_option( 'wpqo_security_options' );
        $expiry = isset( $options['otp_expiry'] ) ? $options['otp_expiry'] : 120;
        echo '<input type="number" name="wpqo_security_options[otp_expiry]" value="' . esc_attr( $expiry ) . '" class="small-text">';
    }

    public function render_resend_interval_field() {
        $options = get_option( 'wpqo_security_options' );
        $interval = isset( $options['resend_interval'] ) ? $options['resend_interval'] : 60;
        echo '<input type="number" name="wpqo_security_options[resend_interval]" value="' . esc_attr( $interval ) . '" class="small-text">';
    }

    public function render_max_sends_per_day_field() {
        $options = get_option( 'wpqo_security_options' );
        $max_sends = isset( $options['max_sends_per_day'] ) ? $options['max_sends_per_day'] : 5;
        echo '<input type="number" name="wpqo_security_options[max_sends_per_day]" value="' . esc_attr( $max_sends ) . '" class="small-text">';
    }

    public function render_max_verify_attempts_field() {
        $options = get_option( 'wpqo_security_options' );
        $max_attempts = isset( $options['max_verify_attempts'] ) ? $options['max_verify_attempts'] : 5;
        echo '<input type="number" name="wpqo_security_options[max_verify_attempts]" value="' . esc_attr( $max_attempts ) . '" class="small-text">';
    }

    public function render_logs_section() {
        echo '<p>' . __( 'در این بخش گزارشات مربوط به ارسال پیامک‌ها نمایش داده خواهد شد.', 'wp-quickotp' ) . '</p>';
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'تنظیمات افزونه ورود با شماره موبایل', 'wp-quickotp' ); ?></h1>
            <?php settings_errors(); ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=wp_quickotp&tab=general" class="nav-tab <?php echo ( ! isset( $_GET['tab'] ) || $_GET['tab'] == 'general' ) ? 'nav-tab-active' : ''; ?>"><?php _e( 'عمومی', 'wp-quickotp' ); ?></a>
                <a href="?page=wp_quickotp&tab=sms" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'sms' ) ? 'nav-tab-active' : ''; ?>"><?php _e( 'پیامک', 'wp-quickotp' ); ?></a>
                <a href="?page=wp_quickotp&tab=templates" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'templates' ) ? 'nav-tab-active' : ''; ?>"><?php _e( 'قالب‌ها', 'wp-quickotp' ); ?></a>
                <a href="?page=wp_quickotp&tab=woocommerce" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'woocommerce' ) ? 'nav-tab-active' : ''; ?>"><?php _e( 'ووکامرس', 'wp-quickotp' ); ?></a>
                <a href="?page=wp_quickotp&tab=security" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'security' ) ? 'nav-tab-active' : ''; ?>"><?php _e( 'امنیت', 'wp-quickotp' ); ?></a>
                <a href="?page=wp_quickotp&tab=logs" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'logs' ) ? 'nav-tab-active' : ''; ?>"><?php _e( 'گزارشات', 'wp-quickotp' ); ?></a>
            </h2>

            <form action="options.php" method="post">
                <?php
                $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
                settings_fields( 'wpqo_' . $tab . '_options' );
                do_settings_sections( 'wpqo_' . $tab . '_options' );

                if ( 'logs' !== $tab ) {
                    submit_button();
                }
                ?>
            </form>
        </div>
        <?php
    }
}

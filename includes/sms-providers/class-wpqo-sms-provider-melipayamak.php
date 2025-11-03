<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPQO_SMS_Provider_Melipayamak implements WPQO_SMS_Provider_Interface {

    private $username;
    private $password;

    public function __construct() {
        $options = get_option( 'wpqo_sms_options' );
        $this->username = isset( $options['melipayamak_username'] ) ? $options['melipayamak_username'] : '';
        $this->password = isset( $options['melipayamak_password'] ) ? $options['melipayamak_password'] : '';
    }

    public function send( $phone, $message ) {
        if ( empty( $this->username ) || empty( $this->password ) ) {
            return new WP_Error( 'missing_credentials', __( 'نام کاربری یا گذرواژه برای ملی‌پیامک تنظیم نشده است.', 'wp-quickotp' ) );
        }

        $response = wp_remote_post(
            'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => json_encode(
                    array(
                        'username' => $this->username,
                        'password' => $this->password,
                        'to'       => $phone,
                        'from'     => '50004001861294', // This should be configured in the admin panel
                        'text'     => $message,
                        'isFlash'  => false,
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $data['RetStatus'] !== 1 ) {
            return new WP_Error( 'sms_send_failed', $data['StrRetStatus'] );
        }

        return true;
    }
}

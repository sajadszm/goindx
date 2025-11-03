<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPQO_SMS_Provider_SMSIR implements WPQO_SMS_Provider_Interface {

    private $api_key;

    public function __construct() {
        $options = get_option( 'wpqo_sms_options' );
        $this->api_key = isset( $options['smsir_api_key'] ) ? $options['smsir_api_key'] : '';
    }

    public function send( $phone, $message ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'missing_api_key', __( 'کلید API برای sms.ir تنظیم نشده است.', 'wp-quickotp' ) );
        }

        $response = wp_remote_post(
            'https://api.sms.ir/v1/send/like',
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-API-KEY'    => $this->api_key,
                ),
                'body'    => json_encode(
                    array(
                        'lineNumber' => 30004505002471, // This should be configured in the admin panel
                        'messageText' => $message,
                        'mobiles'     => array( $phone ),
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $data['status'] !== 1 ) {
            return new WP_Error( 'sms_send_failed', $data['message'] );
        }

        return true;
    }
}

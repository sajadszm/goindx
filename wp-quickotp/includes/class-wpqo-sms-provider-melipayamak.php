<?php

class WPQO_SMS_Provider_MELIPAYAMAK implements WPQO_SMS_Provider_Interface {
    public function send_otp( $phone, $otp ) {
        $options = get_option( 'wp_quickotp_options' );
        $username = isset( $options['melipayamak_username'] ) ? $options['melipayamak_username'] : '';
        $password = isset( $options['melipayamak_password'] ) ? $options['melipayamak_password'] : '';
        $from = isset( $options['melipayamak_from'] ) ? $options['melipayamak_from'] : '';

        if ( empty( $username ) || empty( $password ) || empty( $from ) ) {
            return new WP_Error( 'missing_credentials', 'Melipayamak username, password, or from number is missing.' );
        }

        $url = 'https://rest.melipayamak.com/v1/messages';
        $body = array(
            'from' => $from,
            'to'   => $phone,
            'text' => "کد تایید شما: {$otp}",
        );

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
            ),
            'body'    => json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $response_body['status'] !== 'SUCCESS' ) {
            return new WP_Error( 'sms_send_failed', $response_body['message'] );
        }

        return true;
    }
}

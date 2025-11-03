<?php

class WPQO_SMS_Provider_SMSIR implements WPQO_SMS_Provider_Interface {
    public function send_otp( $phone, $otp ) {
        $options = get_option( 'wp_quickotp_options' );
        $api_key = isset( $options['smsir_api_key'] ) ? $options['smsir_api_key'] : '';
        $template_id = isset( $options['smsir_template_id'] ) ? $options['smsir_template_id'] : '';

        if ( empty( $api_key ) || empty( $template_id ) ) {
            return new WP_Error( 'missing_credentials', 'sms.ir API key or template ID is missing.' );
        }

        $url = 'https://api.sms.ir/v1/verify';
        $body = array(
            'mobile'      => $phone,
            'templateId'  => $template_id,
            'parameters'  => array(
                array(
                    'name'  => 'CODE',
                    'value' => $otp,
                ),
            ),
        );

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-KEY'    => $api_key,
            ),
            'body'    => json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $response_body['status'] !== 1 ) {
            return new WP_Error( 'sms_send_failed', $response_body['message'] );
        }

        return true;
    }
}

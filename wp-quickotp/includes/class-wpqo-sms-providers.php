<?php

interface WPQO_SMS_Provider_Interface {
    public function send_otp( $phone, $otp );
}

abstract class WPQO_SMS_Provider {

    protected $max_retries = 2;
    protected $retry_delay = 1000; // in milliseconds

    abstract public function send_otp( $phone, $otp );

    protected function post_request( $url, $args, $retry_count = 0 ) {
        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            if ( $retry_count < $this->max_retries ) {
                usleep( $this->retry_delay * ( $retry_count + 1 ) );
                return $this->post_request( $url, $args, $retry_count + 1 );
            } else {
                $this->log_response( $url, $args, $response );
                return $response;
            }
        }

        $this->log_response( $url, $args, $response );
        return $response;
    }

    protected function log_response( $url, $args, $response ) {
        if ( class_exists( 'WPQO_Logger' ) ) {
            WPQO_Logger::log(
                'SMS API Request',
                array(
                    'url'      => $url,
                    'args'     => $args,
                    'response' => $response,
                )
            );
        }
    }
}

class WPQO_SMS_Provider_Smsir extends WPQO_SMS_Provider implements WPQO_SMS_Provider_Interface {
    public function send_otp( $phone, $otp ) {
        $api_key = wpqo_get_option( 'smsir_api_key' );
        $template_id = wpqo_get_option( 'smsir_template_id' );

        if ( empty( $api_key ) || empty( $template_id ) ) {
            return new WP_Error( 'missing_credentials', __( 'sms.ir API key or template ID is missing.', 'wp-quickotp' ) );
        }

        // See: https://sms.ir/rest-api/
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

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-KEY'    => $api_key,
            ),
            'body'    => json_encode( $body ),
        );

        $response = $this->post_request( $url, $args );

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

class WPQO_SMS_Provider_Melipayamak extends WPQO_SMS_Provider implements WPQO_SMS_Provider_Interface {
    public function send_otp( $phone, $otp ) {
        $from = wpqo_get_option( 'melipayamak_from' );

        if ( empty( $from ) ) {
            return new WP_Error( 'missing_credentials', __( 'Melipayamak from number is missing.', 'wp-quickotp' ) );
        }

        // See: https://melipayamak.com/
        $url = 'https://api.melipayamak.com/v1/sms/send';
        $body = array(
            'to'   => $phone,
            'from' => $from,
            'text' => sprintf( __( 'Your verification code is: %s', 'wp-quickotp' ), $otp ),
        );

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_token(),
            ),
            'body'    => json_encode( $body ),
        );

        $response = $this->post_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $response_body['status'] !== 'SUCCESS' ) {
            return new WP_Error( 'sms_send_failed', $response_body['message'] );
        }

        return true;
    }

    private function get_token() {
        $token = get_transient( 'wpqo_melipayamak_token' );
        if ( $token ) {
            return $token;
        }

        $username = wpqo_get_option( 'melipayamak_username' );
        $password = wpqo_get_option( 'melipayamak_password' );

        if ( empty( $username ) || empty( $password ) ) {
            return '';
        }

        $response = wp_remote_post( 'https://api.melipayamak.com/v1/user/token', array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => json_encode( array(
                'username' => $username,
                'password' => $password,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        $token = isset( $response_body['token'] ) ? $response_body['token'] : '';
        set_transient( 'wpqo_melipayamak_token', $token, 24 * HOUR_IN_SECONDS );
        return $token;
    }
}

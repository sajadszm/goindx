<?php

if ( ! function_exists( 'wpqo_get_option' ) ) {
    function wpqo_get_option( $option, $default = '' ) {
        $options = get_option( 'wp_quickotp_options' );
        return isset( $options[ $option ] ) ? $options[ $option ] : $default;
    }
}

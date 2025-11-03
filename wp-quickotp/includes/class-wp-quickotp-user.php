<?php

class WP_QuickOTP_User {

    public static function normalize_phone( $phone ) {
        // Remove all non-numeric characters from the phone number.
        $phone = preg_replace( '/\D/', '', $phone );
        // Remove leading zeros.
        $phone = ltrim( $phone, '0' );
        // If the number starts with the country code, remove it.
        if ( substr( $phone, 0, 2 ) === '98' ) {
            $phone = substr( $phone, 2 );
        }
        // Add a leading zero to the number.
        $phone = '0' . $phone;
        return $phone;
    }

    public static function login_or_register_user( $phone ) {
        $normalized_phone = self::normalize_phone( $phone );
        $user = get_user_by( 'login', $normalized_phone );

        if ( $user ) {
            wp_set_current_user( $user->ID, $normalized_phone );
            wp_set_auth_cookie( $user->ID );
            do_action( 'wp_login', $normalized_phone, $user );
            return $user->ID;
        } else {
            $password = wp_generate_password();
            $user_id = wp_create_user( $normalized_phone, $password );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }
            $user = get_user_by( 'id', $user_id );
            $user->set_role( 'customer' );

            wp_set_current_user( $user_id, $normalized_phone );
            wp_set_auth_cookie( $user_id );
            do_action( 'wp_login', $normalized_phone, $user );
            return $user_id;
        }
    }
}

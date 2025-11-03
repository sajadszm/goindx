<?php

class WPQO_Sessions {

    public function __construct() {
        add_action( 'wp_login', array( $this, 'track_user_session' ), 10, 2 );
    }

    public function track_user_session( $user_login, $user ) {
        if ( ! wpqo_get_option( 'limit_concurrent_logins' ) ) {
            return;
        }

        $sessions = get_user_meta( $user->ID, '_wpqo_sessions', true );
        if ( ! is_array( $sessions ) ) {
            $sessions = array();
        }

        $sessions[] = array(
            'ip'         => WPQO_OTP::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'login_time' => current_time( 'mysql' ),
        );

        update_user_meta( $user->ID, '_wpqo_sessions', $sessions );
    }
}

new WPQO_Sessions();

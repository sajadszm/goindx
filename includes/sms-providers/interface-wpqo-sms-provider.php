<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface WPQO_SMS_Provider_Interface {
    public function send( $phone, $message );
}

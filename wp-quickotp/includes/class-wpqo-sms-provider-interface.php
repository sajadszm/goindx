<?php

interface WPQO_SMS_Provider_Interface {
    public function send_otp( $phone, $otp );
}

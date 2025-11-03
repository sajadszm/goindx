<?php

class WPQO_OTP_Test extends WP_UnitTestCase {

    public function test_normalize_phone() {
        $this->assertEquals( '09123456789', WPQO_OTP::normalize_phone( '09123456789' ) );
        $this->assertEquals( '09123456789', WPQO_OTP::normalize_phone( '989123456789' ) );
        $this->assertEquals( '09123456789', WPQO_OTP::normalize_phone( '+989123456789' ) );
        $this->assertEquals( '09123456789', WPQO_OTP::normalize_phone( '9123456789' ) );
    }

    public function test_generate_and_store_otp() {
        $phone = '09123456789';
        $provider = 'smsir';
        $otp = WPQO_OTP::generate_and_store_otp( $phone, $provider );
        $this->assertIsNumeric( $otp );
        $this->assertEquals( 6, strlen( (string) $otp ) );
    }

    public function test_verify_otp() {
        $phone = '09123456789';
        $provider = 'smsir';
        $otp = WPQO_OTP::generate_and_store_otp( $phone, $provider );
        $this->assertTrue( WPQO_OTP::verify_otp( $phone, $otp ) );
        $this->assertFalse( WPQO_OTP::verify_otp( $phone, '123456' ) );
    }
}

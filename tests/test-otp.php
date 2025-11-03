<?php
/**
 * Class OTPTest
 *
 * @package Wp_Quickotp
 */

/**
 * OTP test case.
 */
class OTPTest extends WP_UnitTestCase {

    /**
     * A single example test.
     */
    function test_generate_and_verify_otp() {
        $phone = '09123456789';

        // Generate and save a new OTP.
        $otp = WPQO_OTP::generate_and_save( $phone );

        // Assert that the OTP is a number.
        $this->assertIsNumeric( $otp );

        // Verify the OTP.
        $this->assertTrue( WPQO_OTP::verify( $phone, $otp ) );

        // Verify that an incorrect OTP fails.
        $this->assertFalse( WPQO_OTP::verify( $phone, '000000' ) );
    }
}

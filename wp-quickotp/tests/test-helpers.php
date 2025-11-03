<?php

class Helpers_Test extends WP_UnitTestCase {

    public function test_wpqo_normalize_phone() {
        $this->assertEquals( '09123456789', WPQuickOTP\Helpers::normalize_phone( '09123456789' ) );
        $this->assertEquals( '09123456789', WPQuickOTP\Helpers::normalize_phone( '989123456789' ) );
        $this->assertEquals( '09123456789', WPQuickOTP\Helpers::normalize_phone( '+989123456789' ) );
        $this->assertEquals( '09123456789', WPQuickOTP\Helpers::normalize_phone( '9123456789' ) );
    }
}

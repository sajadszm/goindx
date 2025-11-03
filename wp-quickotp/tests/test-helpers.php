<?php

class Helpers_Test extends WP_UnitTestCase {

    public function test_wpqo_get_option() {
        $options = array(
            'test_option' => 'test_value',
        );
        update_option( 'wp_quickotp_options', $options );
        $this->assertEquals( 'test_value', wpqo_get_option( 'test_option' ) );
        $this->assertEquals( 'default_value', wpqo_get_option( 'non_existent_option', 'default_value' ) );
    }
}

<?php
namespace WPQuickOTP;
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists(__NAMESPACE__.'\\wpqo_get_option') ) {
    function wpqo_get_option( $key, $default = '' ) {
        $o = get_option('wp_quickotp_options', []);
        return isset($o[$key]) ? $o[$key] : $default;
    }
}

// Fallback Helpers for legacy calls
if ( ! class_exists(__NAMESPACE__.'\\Helpers') ) {
    class Helpers {
        public static function get_option($key, $default = '') {
            return wpqo_get_option($key, $default);
        }
    }
}

// (اختیاری) نرمال‌سازی شماره
if ( ! function_exists(__NAMESPACE__.'\\wpqo_normalize_phone') ) {
    function wpqo_normalize_phone( $phone ) {
        $phone = preg_replace('/[^\d۰-۹]/u', '', (string) $phone);
        $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $phone = str_replace($fa, $en, $phone);
        return $phone;
    }
}

<?php
namespace WPQuickOTP;
if ( ! defined( 'ABSPATH' ) ) exit;

class Frontend {
    public function __construct() {
        add_action('wp_enqueue_scripts', [ $this, 'enqueue' ]);
        add_shortcode('wp_quickotp', [ $this, 'render' ]);
    }

    private function plugin_url($path = '') {
        // /includes/ → plugin base url
        $url = plugin_dir_url( __FILE__ ) . '../';
        return $url . ltrim($path, '/');
    }
    private function plugin_path($path = '') {
        $dir = plugin_dir_path( __FILE__ ) . '../';
        return $dir . ltrim($path, '/');
    }

    public function enqueue() {
        $ver = defined('WP_QUICKOTP_VERSION') ? WP_QUICKOTP_VERSION : '3.0.0';

        // Base CSS
        wp_enqueue_style(
            'wp-quickotp-public',
            $this->plugin_url('assets/css/wp-quickotp-public.css'),
            [],
            $ver
        );

        // Theme CSS (conditional)
        $opts = get_option('wp_quickotp_options', []);
        $tpl  = isset($opts['template']) ? $opts['template'] : 'default'; // default, digikala, minimal, popup
        $theme_css = "assets/css/themes/{$tpl}.css";
        if ( file_exists( $this->plugin_path($theme_css) ) ) {
            wp_enqueue_style("wp-quickotp-theme-{$tpl}", $this->plugin_url($theme_css), ['wp-quickotp-public'], $ver);
        }

        // JS
        wp_enqueue_script(
            'wp-quickotp',
            $this->plugin_url('assets/js/wp-quickotp.js'),
            ['jquery'],
            $ver,
            true
        );

        // Localize for REST
        $after_login = ! empty($opts['redirect_after_login'])
            ? $opts['redirect_after_login']
            : ( function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/') );

        wp_localize_script('wp-quickotp', 'wpqoAPI', [
            'root'        => esc_url_raw( rest_url('wpqo/v1') ),
            'nonce'       => wp_create_nonce('wp_rest'),
            'resendDelay' => (int) get_option('wpqo_resend_cooldown', 60),
            'otpExpiry'   => (int) get_option('wpqo_otp_expiry', 120),
            'afterLogin'  => esc_url_raw( $after_login ),
        ]);

        // Inject CSS vars
        $main   = isset($opts['main_color'])    ? $opts['main_color']    : '#e4101b';
        $accent = isset($opts['accent_color'])  ? $opts['accent_color']  : '#212121';
        $btn    = isset($opts['btn_color'])     ? $opts['btn_color']     : $main;
        $font   = isset($opts['font_family'])   ? $opts['font_family']   : 'IRANSans, Vazirmatn, Yekan, sans-serif';
        $radius = isset($opts['border_radius']) ? $opts['border_radius'] : '12px';
        $shadow = '0 10px 22px rgba(0,0,0,.08)';

        $css = ":root{--wpqo-main-color:$main;--wpqo-accent-color:$accent;--wpqo-btn-color:$btn;--wpqo-font-family:$font;--wpqo-radius:$radius;--wpqo-shadow:$shadow}";
        wp_add_inline_style('wp-quickotp-public', $css);
    }

    public function render() {
        $opts = get_option('wp_quickotp_options', []);
        $tpl  = isset($opts['template']) ? $opts['template'] : 'default';

        $override = trailingslashit( get_stylesheet_directory() ) . "wp-quickotp/{$tpl}.php";
        if ( file_exists( $override ) ) {
            $file = $override;
        } else {
            $file = $this->plugin_path("templates/{$tpl}.php");
            if ( ! file_exists($file) ) {
                $file = $this->plugin_path('templates/default.php');
            }
        }

        ob_start();
        include $file;
        return ob_get_clean();
    }
}

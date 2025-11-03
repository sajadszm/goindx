<?php
namespace WPQuickOTP;
if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {

    public function __construct() {
        add_action('admin_menu', [ $this, 'menu' ]);
        add_action('admin_init', [ $this, 'register_settings' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue' ]);
    }

    public function enqueue() {
        $ver = defined('WP_QUICKOTP_VERSION') ? WP_QUICKOTP_VERSION : '3.0.0';
        wp_enqueue_style( 'wp-quickotp-admin', plugin_dir_url(__FILE__) . '../assets/css/wp-quickotp.css', [], $ver );
        wp_enqueue_script('wp-quickotp-admin', plugin_dir_url(__FILE__) . '../admin/js/wp-quickotp-admin.js', ['jquery'], $ver, true);
    }

    public function menu() {
        add_menu_page(
            __('ورود سریع', 'wp-quickotp'),
            __('ورود سریع', 'wp-quickotp'),
            'manage_options',
            'wp-quickotp',
            [ $this, 'settings_page' ],
            'dashicons-smartphone',
            58
        );
    }

    public function register_settings() {
        register_setting('wp_quickotp_options_group', 'wp_quickotp_options');

        add_settings_section('wpqo_general', __('تنظیمات کلی', 'wp-quickotp'), '__return_false', 'wp-quickotp');

        add_settings_field('template', __('قالب', 'wp-quickotp'), function(){
            $o = get_option('wp_quickotp_options', []); $v = $o['template'] ?? 'default';
            $opts = ['default'=>'پیش‌فرض','digikala'=>'دیجی‌کالا','minimal'=>'مینیمال','popup'=>'پاپ‌آپ'];
            echo '<select name="wp_quickotp_options[template]">';
            foreach($opts as $k=>$t){
                echo '<option value="'.$k.'" '.selected($v,$k,false).'>'.$t.'</option>';
            }
            echo '</select>';
        }, 'wp-quickotp', 'wpqo_general');

        add_settings_field('redirect_after_login', __('مقصد بعد از ورود', 'wp-quickotp'), function(){
            $o = get_option('wp_quickotp_options', []); $v = $o['redirect_after_login'] ?? '';
            echo '<input type="url" name="wp_quickotp_options[redirect_after_login]" value="'.esc_attr($v).'" class="regular-text" placeholder="https://..." />';
        }, 'wp-quickotp', 'wpqo_general');

        add_settings_field('force_login_checkout', __('اجبار ورود در صفحه پرداخت', 'wp-quickotp'), function(){
            $o = get_option('wp_quickotp_options', []); $v = !empty($o['force_login_checkout']);
            echo '<label><input type="checkbox" name="wp_quickotp_options[force_login_checkout]" value="1" '.checked($v,true,false).'/> '.__('فعال', 'wp-quickotp').'</label>';
        }, 'wp-quickotp', 'wpqo_general');

        add_settings_field('login_page_id', __('صفحه‌ی ورود/ثبت‌نام (شورتکد)', 'wp-quickotp'), function(){
            $o = get_option('wp_quickotp_options', []); $v = $o['login_page_id'] ?? 0;
            wp_dropdown_pages([
                'name' => 'wp_quickotp_options[login_page_id]',
                'show_option_none' => __('— انتخاب صفحه —','wp-quickotp'),
                'option_none_value' => 0,
                'selected' => $v
            ]);
            echo '<p class="description">روی این صفحه شورتکد <code>[wp_quickotp]</code> قرار دهید.</p>';
        }, 'wp-quickotp', 'wpqo_general');

        // Colors & fonts (ساده)
        add_settings_field('main_color', __('رنگ اصلی', 'wp-quickotp'), function(){
            $o = get_option('wp_quickotp_options', []); $v = $o['main_color'] ?? '#e4101b';
            echo '<input type="text" name="wp_quickotp_options[main_color]" value="'.esc_attr($v).'" class="regular-text" />';
        }, 'wp-quickotp', 'wpqo_general');
        add_settings_field('btn_color', __('رنگ دکمه', 'wp-quickotp'), function(){
            $o = get_option('wp_quickotp_options', []); $v = $o['btn_color'] ?? '#e4101b';
            echo '<input type="text" name="wp_quickotp_options[btn_color]" value="'.esc_attr($v).'" class="regular-text" />';
        }, 'wp-quickotp', 'wpqo_general');
        add_settings_field('accent_color', __('رنگ متون', 'wp-quickotp'), function(){
            $o = get_option('wp_quickotp_options', []); $v = $o['accent_color'] ?? '#212121';
            echo '<input type="text" name="wp_quickotp_options[accent_color]" value="'.esc_attr($v).'" class="regular-text" />';
        }, 'wp-quickotp', 'wpqo_general');
        add_settings_field('font_family', __('فونت', 'wp-quickotp'), function(){
            $o = get_option('wp_quickotp_options', []); $v = $o['font_family'] ?? 'IRANSans, Vazirmatn, Yekan, sans-serif';
            echo '<input type="text" name="wp_quickotp_options[font_family]" value="'.esc_attr($v).'" class="regular-text" />';
        }, 'wp-quickotp', 'wpqo_general');
        add_settings_field('border_radius', __('گردی گوشه‌ها', 'wp-quickotp'), function(){
            $o = get_option('wp_quickotp_options', []); $v = $o['border_radius'] ?? '12px';
            echo '<input type="text" name="wp_quickotp_options[border_radius]" value="'.esc_attr($v).'" class="regular-text" />';
        }, 'wp-quickotp', 'wpqo_general');
    }

    public function settings_page() {
        echo '<div class="wrap"><h1>'.esc_html__('تنظیمات ورود سریع', 'wp-quickotp').'</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('wp_quickotp_options_group');
        do_settings_sections('wp-quickotp');
        submit_button();
        echo '</form></div>';
    }
}

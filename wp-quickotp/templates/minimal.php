<?php
/**
 * Minimal OTP Login/Register Template
 *
 * @package WPQuickOTP
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div class="wpqo-container">
  <div class="wpqo-card fade-in">
    <div class="wpqo-title"><?php echo esc_html( \WPQuickOTP\Helpers::get_option( 'login_form_title', __( 'ورود یا ثبت‌نام با شماره موبایل', 'wp-quickotp' ) ) ); ?></div>
    <div class="wpqo-sub"><?php echo esc_html( \WPQuickOTP\Helpers::get_option( 'login_form_subtitle', __( 'شماره موبایل خود را وارد کنید تا کد تایید برای شما ارسال شود.', 'wp-quickotp' ) ) ); ?></div>

    <form id="wpqo-form-phone" class="wpqo-form">
      <div class="wpqo-field">
        <label class="wpqo-label" for="wpqo-phone"><?php _e( 'شماره موبایل', 'wp-quickotp' ); ?></label>
        <input class="wpqo-input" id="wpqo-phone" name="phone" type="text" inputmode="tel" placeholder="<?php _e( 'مثال: 09123456789', 'wp-quickotp' ); ?>" />
      </div>
      <input type="hidden" name="return_to" value="<?php echo isset($_GET['return_to']) ? esc_attr($_GET['return_to']) : ''; ?>">
      <button class="wpqo-btn" type="submit"><?php _e( 'ارسال کد', 'wp-quickotp' ); ?></button>
    </form>

    <div id="wpqo-otp-wrap" class="fade-in" style="display:none;">
      <form id="wpqo-form-otp">
        <div class="wpqo-field">
          <label class="wpqo-label" for="wpqo-otp"><?php _e( 'کد تایید', 'wp-quickotp' ); ?></label>
          <input class="wpqo-input" id="wpqo-otp" name="otp" type="text" inputmode="numeric" maxlength="6" placeholder="— — — — — —" />
        </div>
        <button class="wpqo-btn" type="submit"><?php _e( 'تایید و ادامه', 'wp-quickotp' ); ?></button>
      </form>
      <div class="wpqo-timer">
        <span><?php _e( 'ارسال مجدد پس از', 'wp-quickotp' ); ?></span> <strong class="wpqo-timer-count"><?php echo (int) \WPQuickOTP\Helpers::get_option( 'resend_delay', 60 ); ?></strong> <span><?php _e( 'ثانیه', 'wp-quickotp' ); ?></span>
        <button id="wpqo-resend" class="wpqo-resend" disabled><?php _e( 'ارسال مجدد', 'wp-quickotp' ); ?></button>
      </div>
    </div>
  </div>
</div>

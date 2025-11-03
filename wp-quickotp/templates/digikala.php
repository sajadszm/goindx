<?php
/**
 * Digikala OTP Login/Register Template
 *
 * @package WPQuickOTP
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div class="wpqo-template-digikala">
    <div class="wpqo-form-container">
        <div class="wpqo-form-header">
            <?php if ( $logo = Helpers::get_option( 'template_logo' ) ) : ?>
                <img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
            <?php else : ?>
                <h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
            <?php endif; ?>
            <h3><?php echo esc_html( Helpers::get_option( 'login_form_title', __( 'ورود | ثبت‌نام', 'wp-quickotp' ) ) ); ?></h3>
        </div>
        <div class="wpqo-form-body">
            <div class="wpqo-phone-step">
                <p><?php echo esc_html( Helpers::get_option( 'login_form_subtitle', __( 'شماره موبایل خود را وارد کنید', 'wp-quickotp' ) ) ); ?></p>
                <form id="wpqo-phone-form">
                    <div class="wpqo-form-row">
                        <input type="text" id="wpqo-phone-input" dir="ltr" placeholder="<?php _e( 'مثال: 09123456789', 'wp-quickotp' ); ?>">
                    </div>
                    <div class="wpqo-form-row">
                        <button type="submit" id="wpqo-send-otp-btn">
                            <span class="wpqo-btn-text"><?php _e( 'ورود', 'wp-quickotp' ); ?></span>
                            <span class="wpqo-loader"></span>
                        </button>
                    </div>
                </form>
            </div>
            <div class="wpqo-otp-step" style="display: none;">
                <p><?php echo esc_html( Helpers::get_option( 'otp_sent_message', __( 'کد تایید به شماره شما ارسال شد', 'wp-quickotp' ) ) ); ?></p>
                <form id="wpqo-otp-form">
                    <div class="wpqo-form-row">
                        <input type="text" id="wpqo-otp-input" dir="ltr">
                    </div>
                    <div class="wpqo-form-row">
                        <button type="submit" id="wpqo-verify-otp-btn">
                            <span class="wpqo-btn-text"><?php _e( 'تایید و ادامه', 'wp-quickotp' ); ?></span>
                            <span class="wpqo-loader"></span>
                        </button>
                    </div>
                </form>
                <div class="wpqo-timer-container">
                    <div class="wpqo-timer"></div>
                    <a href="#" id="wpqo-resend-otp-btn" style="display: none;"><?php _e( 'ارسال مجدد کد', 'wp-quickotp' ); ?></a>
                </div>
            </div>
        </div>
        <div class="wpqo-message-container"></div>
    </div>
</div>

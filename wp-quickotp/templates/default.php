<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wpqo-container">
  <div class="wpqo-card fade-in">
    <div class="wpqo-title">ورود یا ثبت‌نام با شماره موبایل</div>
    <div class="wpqo-sub">شماره موبایل خود را وارد کنید تا کد تایید برای شما ارسال شود.</div>

    <form id="wpqo-form-phone" class="wpqo-form">
      <div class="wpqo-field">
        <label class="wpqo-label" for="wpqo-phone">شماره موبایل</label>
        <input class="wpqo-input" id="wpqo-phone" name="phone" type="text" inputmode="tel" placeholder="مثال: 09123456789" />
      </div>
      <input type="hidden" name="return_to" value="<?php echo isset($_GET['return_to']) ? esc_attr($_GET['return_to']) : ''; ?>">
      <button class="wpqo-btn" type="submit">ارسال کد</button>
    </form>

    <div id="step-otp" class="fade-in" style="display:none;">
      <form id="wpqo-form-otp">
        <div class="wpqo-field">
          <label class="wpqo-label" for="wpqo-otp">کد تایید</label>
          <input class="wpqo-input" id="wpqo-otp" name="otp" type="text" inputmode="numeric" maxlength="6" placeholder="— — — — — —" />
        </div>
        <button class="wpqo-btn" type="submit">تایید و ادامه</button>
      </form>
      <div class="wpqo-timer">
        <span>ارسال مجدد پس از</span> <strong id="wpqo-count"><?php echo (int) get_option('wpqo_resend_cooldown', 60); ?></strong> <span>ثانیه</span>
        <button id="wpqo-resend" class="wpqo-resend" disabled>ارسال مجدد</button>
      </div>
    </div>
  </div>
</div>

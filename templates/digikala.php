<?php if ( ! defined('ABSPath') ) exit; ?>
<div class="wpqo-container dk">
  <div class="dk-card fade-in" role="dialog" aria-label="ورود یا ثبت نام">
    <div class="dk-header">
      <div class="dk-arrow" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M10 7l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="dk-brand">
        <?php
          $o = get_option('wp_quickotp_options', []);
          $logo = !empty($o['logo_url']) ? $o['logo_url'] : '';
          if ($logo) {
            echo '<img src="' . esc_url($logo) . '" alt="'.esc_attr__('لوگو', 'wp-quickotp').'" />';
          } else {
            echo '<span class="dk-logo-text">دیجی‌کالا</span>';
          }
        ?>
      </div>
    </div>

    <div class="dk-body">
      <h1 class="dk-title">ورود یا ثبت‌نام در دیجی‌کالا</h1>
      <p class="dk-subtitle">لطفاً شماره موبایل خود را وارد کنید</p>

      <form id="wpqo-form-phone" class="dk-form" aria-describedby="dk-terms">
        <div id="step-phone" class="wpqo-field">
          <label class="wpqo-label" for="wpqo-phone">شماره موبایل</label>
          <input class="wpqo-input dk-input" id="wpqo-phone" name="phone" type="text" inputmode="tel" placeholder="شماره موبایل" autocomplete="tel" />
        </div>
        <input type="hidden" name="return_to" value="<?php echo isset($_GET['return_to']) ? esc_attr($_GET['return_to']) : ''; ?>">
        <button class="wpqo-btn dk-btn" type="submit">ورود به دیجی‌کالا</button>
        <p id="dk-terms" class="dk-terms">
          ورود شما به معنای پذیرش
          <a href="#" rel="nofollow">شرایط دیجی‌کالا</a>
          و
          <a href="#" rel="nofollow">قوانین حریم‌خصوصی</a>
          است.
        </p>
      </form>

      <div id="step-otp" class="dk-otp fade-in" style="display:none;">
        <form id="wpqo-form-otp" class="dk-form">
          <div class="wpqo-field">
            <label class="wpqo-label" for="wpqo-otp">کد تایید</label>
            <input class="wpqo-input dk-input" id="wpqo-otp" name="otp" type="text" inputmode="numeric" maxlength="6" placeholder="— — — — — —" />
          </div>
          <button class="wpqo-btn dk-btn" type="submit">تایید و ادامه</button>
        </form>
        <div class="wpqo-timer dk-timer">
          <span>ارسال مجدد پس از</span>
          <strong id="wpqo-count"><?php echo (int) get_option('wpqo_resend_cooldown', 60); ?></strong>
          <span>ثانیه</span>
          <button id="wpqo-resend" class="wpqo-resend dk-resend" disabled>ارسال مجدد</button>
        </div>
      </div>
    </div>
  </div>
</div>

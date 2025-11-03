(function($){
  const API = window.wpqoAPI || {};
  const sec = (n)=> n*1000;

  function toEnDigits(str){ return str.replace(/[۰-۹]/g, d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)); }

  function startTimer($wrap, seconds, onDone){
    let left = seconds;
    const $timer = $wrap.find('.wpqo-timer-count');
    const iv = setInterval(()=>{
      left--;
      $timer.text(left);
      if(left<=0){ clearInterval(iv); onDone && onDone(); }
    },1000);
  }

  function sendOTP(phone, returnTo){
    return $.ajax({
      url: API.root + '/wpqo/v1/send-otp',
      method: 'POST',
      beforeSend: (xhr)=> xhr.setRequestHeader('X-WP-Nonce', API.nonce),
      data: { phone, return_to: returnTo }
    });
  }
  function verifyOTP(phone, code){
    return $.ajax({
      url: API.root + '/wpqo/v1/verify-otp',
      method: 'POST',
      beforeSend: (xhr)=> xhr.setRequestHeader('X-WP-Nonce', API.nonce),
      data: { phone, otp: code }
    });
  }

  $(document).on('submit', '#wpqo-phone-form', function(e){
    e.preventDefault();
    const $f = $(this), phone = toEnDigits($f.find('input[name=phone]').val().trim());
    const returnTo = $f.find('input[name=return_to]').val() || '';
    $f.find('.wpqo-msg').remove();
    sendOTP(phone, returnTo).done(res=>{
      $f.closest('.wpqo-phone-step').hide();
      $f.closest('.wpqo-form-body').find('.wpqo-otp-step').fadeIn(150).data('phone', phone);
      const $resend = $('#wpqo-resend-otp-btn'); $resend.prop('disabled', true);
      startTimer($('#wpqo-otp-wrap'), API.resendDelay || 60, ()=> $resend.prop('disabled', false));
    }).fail(xhr=>{
      $f.append('<div class="wpqo-msg error">خطا در ارسال کد. لطفاً دوباره تلاش کنید.</div>');
    });
  });

  $(document).on('click', '#wpqo-resend-otp-btn', function(){
    const $otpWrap = $('#wpqo-otp-wrap'), phone = $('#wpqo-form-otp').data('phone');
    $(this).prop('disabled', true);
    sendOTP(phone, '').always(()=> {
      startTimer($otpWrap, API.resendDelay || 60, ()=> $('#wpqo-resend-otp-btn').prop('disabled', false));
    });
  });

  $(document).on('submit', '#wpqo-otp-form', function(e){
    e.preventDefault();
    const $f = $(this), code = toEnDigits($f.find('input[name=otp]').val().trim());
    const phone = $f.data('phone');
    $f.find('.wpqo-msg').remove();
    verifyOTP(phone, code).done(res=>{
      window.location.href = res && res.redirect ? res.redirect : (API.afterLogin || '/');
    }).fail(xhr=>{
      $f.append('<div class="wpqo-msg error">کد تایید نامعتبر یا منقضی است.</div>');
    });
  });

  // Optional: WebOTP (only in supported browsers over HTTPS)
  if('OTPCredential' in window){
    navigator.credentials.get({ otp: { transport: ['sms'] }, signal: new AbortController().signal })
      .then(cred=>{
        if(cred && cred.code){ $('#wpqo-otp-input').val(cred.code); }
      }).catch(()=>{});
  }
})(jQuery);

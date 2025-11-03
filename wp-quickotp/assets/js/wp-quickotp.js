/* === BEGIN: wp-quickotp.js (REPLACE EXACTLY) === */
(function($){
  const API = window.wpqoAPI || {};
  const toEn = s => s ? s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)) : '';

  function startTimer($count, seconds, onDone){
    let left = seconds;
    $count.text(left);
    const iv = setInterval(()=>{
      left--;
      if(left <= 0){
        clearInterval(iv);
        onDone && onDone();
      }else{
        $count.text(left);
      }
    }, 1000);
  }

  function ajax(url, data){
    return $.ajax({
      url,
      method: 'POST',
      data,
      beforeSend: (xhr)=> xhr.setRequestHeader('X-WP-Nonce', API.nonce)
    });
  }

  function sendOTP(phone, returnTo){ return ajax(API.root + '/send-otp', {phone, return_to: returnTo || ''}); }
  function verifyOTP(phone, otp){ return ajax(API.root + '/verify-otp', {phone, otp}); }

  $(document).on('submit', '#wpqo-form-phone', function(e){
    e.preventDefault();
    const $form = $(this);
    const phone = toEn($form.find('input[name=phone]').val().trim());
    const returnTo = $form.find('input[name=return_to]').val() || '';
    $form.find('.wpqo-msg').remove();

    sendOTP(phone, returnTo).done(()=>{
      $('#step-phone').hide();
      $('#step-otp').fadeIn(150);
      const $btn = $('#wpqo-resend');
      $btn.prop('disabled', true);
      startTimer($('#wpqo-count'), API.resendDelay || 60, ()=> $btn.prop('disabled', false));
    }).fail((xhr)=>{
      $form.append('<div class="wpqo-msg error">خطا در ارسال کد. لطفاً دوباره تلاش کنید.</div>');
    });
  });

  $(document).on('click', '#wpqo-resend', function(){
    const $btn = $(this);
    const phone = toEn($('#wpqo-form-phone').find('input[name=phone]').val().trim());
    $btn.prop('disabled', true);
    sendOTP(phone, '').always(()=> {
      startTimer($('#wpqo-count'), API.resendDelay || 60, ()=> $btn.prop('disabled', false));
    });
  });

  $(document).on('submit', '#wpqo-form-otp', function(e){
    e.preventDefault();
    const $form = $(this);
    const phone = toEn($('#wpqo-form-phone').find('input[name=phone]').val().trim());
    const otp = toEn($form.find('input[name=otp]').val().trim());
    $form.find('.wpqo-msg').remove();

    verifyOTP(phone, otp).done((res)=>{
      window.location.href = (res && res.redirect) ? res.redirect : (API.afterLogin || '/');
    }).fail(()=>{
      $form.append('<div class="wpqo-msg error">کد تایید نامعتبر یا منقضی است.</div>');
    });
  });

  // WebOTP (optional)
  if ('OTPCredential' in window){
    try{
      const ac = new AbortController();
      navigator.credentials.get({ otp: { transport: ['sms'] }, signal: ac.signal })
        .then(cred => { if(cred && cred.code){ $('#wpqo-form-otp input[name=otp]').val(cred.code); } })
        .catch(()=>{});
    }catch(e){}
  }
})(jQuery);
/* === END: wp-quickotp.js === */

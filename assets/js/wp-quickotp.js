jQuery(document).ready(function ($) {
    'use strict';

    const formPhone = $('#wpqo-form-phone');
    const formOtp = $('#wpqo-form-otp');
    const stepPhone = $('#step-phone');
    const stepOtp = $('#step-otp');
    const phoneInput = $('#wpqo-phone');
    const otpInput = $('#wpqo-otp');
    const resendBtn = $('#wpqo-resend');
    const timerDisplay = $('#wpqo-count');
    let timer;

    // --- WebOTP API ---
    if ('OTPCredential' in window) {
        const ac = new AbortController();
        navigator.credentials.get({
            otp: { transport: ['sms'] },
            signal: ac.signal
        }).then(otp => {
            otpInput.val(otp.code);
            formOtp.submit();
            ac.abort();
        }).catch(err => {
            console.log('WebOTP API failed:', err);
        });
    }

    // --- Phone Form Submission ---
    formPhone.on('submit', function (e) {
        e.preventDefault();
        const phone = phoneInput.val();
        if (!phone) {
            alert('لطفاً شماره موبایل را وارد کنید.');
            return;
        }

        const btn = $(this).find('.wpqo-btn');
        btn.prop('disabled', true).text('در حال ارسال...');

        $.ajax({
            url: wpqoAPI.root + '/send-otp',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpqoAPI.nonce);
            },
            data: {
                phone: phone
            },
            success: function (response) {
                if (response.ok) {
                    stepPhone.hide();
                    stepOtp.fadeIn();
                    otpInput.focus();
                    startTimer();
                } else {
                    alert(response.data?.message || 'خطایی رخ داده است.');
                }
            },
            error: function (jqXHR) {
                alert(jqXHR.responseJSON?.message || 'خطای سرور.');
            },
            complete: function () {
                btn.prop('disabled', false).text('ورود به دیجی‌کالا'); // Or original text
            }
        });
    });

    // --- OTP Form Submission ---
    formOtp.on('submit', function (e) {
        e.preventDefault();
        const phone = phoneInput.val();
        const otp = otpInput.val();

        if (!otp) {
            alert('لطفاً کد تایید را وارد کنید.');
            return;
        }

        const btn = $(this).find('.wpqo-btn');
        btn.prop('disabled', true).text('در حال بررسی...');

        $.ajax({
            url: wpqoAPI.root + '/verify-otp',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpqoAPI.nonce);
            },
            data: {
                phone: phone,
                otp: otp
            },
            success: function (response) {
                if (response.ok && response.redirect) {
                    window.location.href = response.redirect;
                } else {
                     alert(response.data?.message || 'خطایی رخ داده است.');
                     btn.prop('disabled', false).text('تایید و ادامه');
                }
            },
            error: function (jqXHR) {
                alert(jqXHR.responseJSON?.message || 'کد تایید نامعتبر است.');
                btn.prop('disabled', false).text('تایید و ادامه');
            }
        });
    });

    // --- Resend Button ---
    resendBtn.on('click', function () {
        formPhone.submit(); // Re-submit phone form to get a new OTP
        $(this).prop('disabled', true);
    });

    // --- Timer Function ---
    function startTimer() {
        let timeLeft = wpqoAPI.resendDelay;
        timerDisplay.text(timeLeft);
        resendBtn.prop('disabled', true);

        timer = setInterval(function () {
            timeLeft--;
            timerDisplay.text(timeLeft);
            if (timeLeft <= 0) {
                clearInterval(timer);
                resendBtn.prop('disabled', false);
            }
        }, 1000);
    }
});

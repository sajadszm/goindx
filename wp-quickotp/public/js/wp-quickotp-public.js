(function( $ ) {
    'use strict';

    $(function() {
        var form = $( '#wp-quickotp-form' );
        var otpForm = $( '#wp-quickotp-otp-form' );
        var phoneInput = $( '#wp-quickotp-phone' );
        var otpInput = $( '#wp-quickotp-otp' );
        var messageContainer = $( '#wp-quickotp-message' );
        var timerContainer = $( '#wp-quickotp-timer' );

        form.on( 'submit', function( e ) {
            e.preventDefault();

            $.ajax({
                type: 'POST',
                url: wp_quickotp_ajax.rest_url + '/send-otp',
                beforeSend: function ( xhr ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', wp_quickotp_ajax.nonce );
                },
                data: {
                    phone: phoneInput.val()
                },
                success: function( response ) {
                    form.hide();
                    otpForm.parent().show();
                    startTimer( 120 );
                },
                error: function( response ) {
                    messageContainer.text( response.responseJSON.message );
                }
            });
        });

        otpForm.on( 'submit', function( e ) {
            e.preventDefault();

            $.ajax({
                type: 'POST',
                url: wp_quickotp_ajax.rest_url + '/verify-otp',
                beforeSend: function ( xhr ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', wp_quickotp_ajax.nonce );
                },
                data: {
                    phone: phoneInput.val(),
                    otp: otpInput.val()
                },
                success: function( response ) {
                    window.location.reload();
                },
                error: function( response ) {
                    messageContainer.text( response.responseJSON.message );
                }
            });
        });

        function startTimer( duration ) {
            var timer = duration, minutes, seconds;
            var interval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                timerContainer.text( minutes + ":" + seconds );

                if ( --timer < 0 ) {
                    timerContainer.text( 'کد تایید منقضی شد' );
                    clearInterval(interval);
                }
            }, 1000);
        }
    });
})( jQuery );

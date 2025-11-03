(function( $ ) {
    'use strict';

    $(function() {
        var smsProvider = $( '#sms_provider' );
        var smsirSettings = $( '#smsir_api_key, #smsir_template_id' ).closest( 'tr' );
        var melipayamakSettings = $( '#melipayamak_username, #melipayamak_password, #melipayamak_from' ).closest( 'tr' );

        function toggleSmsProviderSettings() {
            if ( smsProvider.val() === 'smsir' ) {
                smsirSettings.show();
                melipayamakSettings.hide();
            } else {
                smsirSettings.hide();
                melipayamakSettings.show();
            }
        }

        toggleSmsProviderSettings();

        smsProvider.on( 'change', function() {
            toggleSmsProviderSettings();
        });
    });
})( jQuery );

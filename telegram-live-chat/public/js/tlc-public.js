(function( $ ) {
    'use strict';

    $(document).ready(function() {
        // ... (all existing const declarations for UI elements)
        const $widgetContainer = $('.tlc-widget-container');
        const $widgetButton = $('.tlc-widget-button');
        const $chatWidget = $('.tlc-chat-widget');
        const $closeButton = $('.tlc-chat-header-close');
        const $messagesContainer = $('.tlc-chat-messages');
        const $messageInput = $('#tlc-chat-message-input');
        const $sendMessageButton = $('#tlc-send-message-button');
        const $fileUploadButton = $('#tlc-file-upload-button');
        const $fileInput = $('#tlc-chat-file-input');

        const fileUploadSettings = tlc_public_ajax.file_upload_settings;
        const preChatFormEnabled = tlc_public_ajax.pre_chat_form_enabled;
        const satisfactionRatingEnabled = tlc_public_ajax.satisfaction_rating_enabled;
        const consentSettings = tlc_public_ajax.consent_settings;
        // NEW: Voice/Video settings
        const voiceChatEnabled = tlc_public_ajax.voice_chat_enabled || false; // Assuming these are added to localization
        const videoChatEnabled = tlc_public_ajax.video_chat_enabled || false;

        const $preChatForm = $('#tlc-pre-chat-form');
        const $chatContent = $('.tlc-chat-content');
        const $visitorNameInput = $('#tlc-visitor-name');
        const $visitorEmailInput = $('#tlc-visitor-email');
        const $startChatButton = $('#tlc-start-chat-button');
        const $preChatError = $('#tlc-pre-chat-error');

        const $endChatButton = $('#tlc-end-chat-button');
        const $ratingForm = $('#tlc-rating-form');
        // ... (rating form consts)

        // NEW: Voice/Video UI elements
        const $voiceCallButton = $('#tlc-voice-call-button');
        const $videoCallButton = $('#tlc-video-call-button');
        const $videoContainer = $('#tlc-video-container');
        const $callControls = $('#tlc-call-controls'); // Placeholder for future controls

        let lastMessageIdDisplayed = 0;
        let pollingIntervalId = null;
        const WIDGET_POLLING_INTERVAL = tlc_public_ajax.polling_interval || 5000;
        let chatInitialized = false;
        let visitorToken = localStorage.getItem('tlc_visitor_token');
        // ... (visitorToken generation) ...

        // ... (initializeChatFunctionality, handleConsentGranted, consent check logic - as before) ...

        function initializeChatFunctionality() {
            if (chatInitialized) return;
            // ... (existing event listeners for close, pre-chat, send message, file upload, rating) ...

            // NEW: Voice/Video Call Button Listeners
            if (voiceChatEnabled && $voiceCallButton.length) {
                $voiceCallButton.on('click', function() {
                    alert('Voice call feature is coming soon!');
                    console.log('TLC: Voice call initiated (conceptual).');
                    // Conceptual: Show video container and basic controls
                    // $chatContent.hide(); $videoContainer.show(); $callControls.show();
                });
            }
            if (videoChatEnabled && $videoCallButton.length) {
                $videoCallButton.on('click', function() {
                    alert('Video call feature is coming soon!');
                    console.log('TLC: Video call initiated (conceptual).');
                    // $chatContent.hide(); $videoContainer.show(); $callControls.show();
                });
            }
            // Placeholder for ending call (would also show chat content again)
            // $('#tlc-end-call-button').on('click', function() {
            //    $videoContainer.hide(); $callControls.hide(); $chatContent.show();
            //    console.log('TLC: Call ended (conceptual).');
            // });


            chatInitialized = true;
            // ... (rest of initializeChatFunctionality, like auto message and initial state if widget active)
        }

        // ... (ALL OTHER JS FUNCTIONS: showPreChatForm, showChatArea, showRatingForm, internalToggleWidget, handlePreChatSubmit, sendMessageFromInput, appendMessage, appendServerMessage, scrollToBottom, fetchNewMessages, startPolling, stopPolling, checkAndTriggerAutoMessage, showAutoMessage, uploadFile, handleSubmitRating, generateUUID, escapeHtml - as they were in the previous complete version of the file) ...

        // Ensure the API object and its methods are defined at the end
        window.TLC_Chat_API = { /* ... (as defined in Phase 7) ... */ };
    });
})( jQuery );

(function( $ ) {
    'use strict';

    $(document).ready(function() {
        const $widgetContainer = $('.tlc-widget-container'); // Get the main container
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

        const $preChatForm = $('#tlc-pre-chat-form');
        const $chatContent = $('.tlc-chat-content');
        const $visitorNameInput = $('#tlc-visitor-name');
        const $visitorEmailInput = $('#tlc-visitor-email');
        const $startChatButton = $('#tlc-start-chat-button');
        const $preChatError = $('#tlc-pre-chat-error');

        const $endChatButton = $('#tlc-end-chat-button');
        const $ratingForm = $('#tlc-rating-form');
        const $ratingStars = $('.tlc-rating-stars .tlc-star');
        const $ratingComment = $('#tlc-rating-comment');
        const $submitRatingButton = $('#tlc-submit-rating-button');
        const $ratingThankYou = $('#tlc-rating-thankyou');
        const $ratingError = $('#tlc-rating-error');
        let currentRating = 0;

        let lastMessageIdDisplayed = 0;
        let pollingIntervalId = null;
        const WIDGET_POLLING_INTERVAL = tlc_public_ajax.polling_interval || 5000;
        let chatInitialized = false; // Flag to see if main chat functions are set up

        let visitorToken = localStorage.getItem('tlc_visitor_token');
        if (!visitorToken) {
            visitorToken = generateUUID();
            localStorage.setItem('tlc_visitor_token', visitorToken);
        }

        function initializeChatFunctionality() {
            if (chatInitialized) return;

            if (satisfactionRatingEnabled) {
                // $endChatButton is already visible if in DOM, this just ensures logic is active
            } else {
                $endChatButton.hide();
            }
            // Event listeners that depend on the widget being active
            $widgetButton.on('click', function() { internalToggleWidget(); });
            $closeButton.on('click', function() { internalToggleWidget(false); });
            $startChatButton.on('click', handlePreChatSubmit);
            $sendMessageButton.on('click', sendMessageFromInput);
            $messageInput.on('keypress', function(e) { if (e.which === 13 && !e.shiftKey) { e.preventDefault(); sendMessageFromInput(); }});

            if (fileUploadSettings && fileUploadSettings.enabled) {
                $fileUploadButton.show(); // Ensure button is visible if hidden by consent logic
                $fileUploadButton.on('click', function() { $fileInput.click(); });
                $fileInput.on('change', function(event) { if (event.target.files && event.target.files.length > 0) { uploadFile(event.target.files[0]); $(this).val(''); }});
            } else {
                 $fileUploadButton.hide();
            }

            if (satisfactionRatingEnabled) {
                $endChatButton.on('click', function() { showRatingForm(); });
                $ratingStars.on('mouseover',function(){const v=$(this).data('value');$ratingStars.removeClass('hovered');$ratingStars.each(function(i){if(i<v)$(this).addClass('hovered');});}).on('mouseout',function(){$ratingStars.removeClass('hovered');}).on('click',function(){currentRating=$(this).data('value');$ratingStars.removeClass('rated hovered');$ratingStars.each(function(i){if(i<currentRating)$(this).addClass('rated');});});
                $submitRatingButton.on('click', handleSubmitRating);
            }

            // Auto Message Logic (should also only run if consent given)
            checkAndTriggerAutoMessage();

            // Initial state if widget was left open (e.g. shortcode)
            if ($chatWidget.hasClass('active')) {
                const visitorName = sessionStorage.getItem('tlc_visitor_name');
                if (preChatFormEnabled && !visitorName) { showPreChatForm(); }
                else { showChatArea(); }
            }
            chatInitialized = true;
        }

        function handleConsentGranted() {
            $widgetContainer.addClass('tlc-consent-given').show(); // Show container, CSS will show button
            initializeChatFunctionality();
        }

        if (consentSettings && consentSettings.required) {
            const consentGiven = localStorage.getItem(consentSettings.ls_key) === consentSettings.ls_value;
            if (consentGiven) {
                handleConsentGranted();
            } else {
                // Widget button is hidden by CSS via .tlc-requires-consent:not(.tlc-consent-given)
                // Wait for external call to TLC_Chat_API.grantConsentAndShow()
                console.log("TLC: Chat widget waiting for consent via TLC_Chat_API.grantConsentAndShow()");
            }
        } else {
            // Consent not required, initialize immediately
            $widgetContainer.show(); // Ensure container is visible
            initializeChatFunctionality();
        }


        function showPreChatForm() { /* ... (as before) ... */ }
        function showChatArea() { /* ... (as before) ... */ }
        function showRatingForm() { /* ... (as before) ... */ }
        function internalToggleWidget(forceShow) { /* ... (as before, calls showPreChatForm/showChatArea which now starts polling) ... */ }
        function handlePreChatSubmit() { /* ... (as before, calls showChatArea) ... */ }
        function sendMessageFromInput() { /* ... (as before, calls TLC_Chat_API.sendMessage) ... */ }
        function appendMessage(text, type, senderName = '', messageId = null) { /* ... (as before) ... */ }
        function appendServerMessage(message) { /* ... (as before) ... */ }
        function scrollToBottom() { /* ... (as before) ... */ }
        function fetchNewMessages() { /* ... (as before) ... */ }
        function startPolling() { /* ... (as before, ensure $chatContent.is(':visible')) ... */ }
        function stopPolling() { /* ... (as before) ... */ }
        function checkAndTriggerAutoMessage() { /* ... (as before) ... */ }
        function showAutoMessage() { /* ... (as before, uses TLC_Chat_API.show if widget closed) ... */ }
        function uploadFile(file) { /* ... (as before) ... */ }
        function handleSubmitRating() { /* ... (as before, uses TLC_Chat_API.hide) ... */ }
        function generateUUID() { /* ... (as before) ... */ }
        function escapeHtml(unsafe) { /* ... (as before) ... */ }

        // Ensure these are defined if not already from previous copy-paste
        showPreChatForm = showPreChatForm || function(){ $preChatForm.show(); $ratingForm.hide(); $chatContent.hide(); $visitorNameInput.focus();};
        showChatArea = showChatArea || function(){ $preChatForm.hide(); $ratingForm.hide(); $chatContent.show(); $messageInput.focus(); startPolling();};
        showRatingForm = showRatingForm || function(){ $preChatForm.hide();$chatContent.hide();$ratingForm.show();$ratingThankYou.hide();$ratingError.text('');currentRating=0;$ratingStars.removeClass('rated hovered');$ratingComment.val('');stopPolling();};
        internalToggleWidget = internalToggleWidget || function(forceShow){const s=forceShow!==undefined?forceShow:!$chatWidget.hasClass('active');$chatWidget.toggleClass('active',s);if($chatWidget.hasClass('active')){const vN=sessionStorage.getItem('tlc_visitor_name');if($ratingForm.is(':visible')){if(preChatFormEnabled&&!vN){showPreChatForm();}else{showChatArea();}}else{if(preChatFormEnabled&&!vN){showPreChatForm();}else{showChatArea();}}}else{stopPolling();}};
        handlePreChatSubmit = handlePreChatSubmit || function(){const n=$visitorNameInput.val().trim(),e=$visitorEmailInput.val().trim();$preChatError.text('');if(n===''){ $preChatError.text('Name is required.');$visitorNameInput.focus();return;}if(e!==''&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)){$preChatError.text('Please enter a valid email address.');$visitorEmailInput.focus();return;}sessionStorage.setItem('tlc_visitor_name',n);if(e!=='')sessionStorage.setItem('tlc_visitor_email',e);showChatArea();};
        sendMessageFromInput = sendMessageFromInput || function(){const mT=$messageInput.val().trim();if(mT==='')return;TLC_Chat_API.sendMessage(mT);$messageInput.val('');};
        startPolling = startPolling || function(){if(pollingIntervalId===null && $chatContent.is(':visible')){fetchNewMessages();pollingIntervalId=setInterval(fetchNewMessages,WIDGET_POLLING_INTERVAL);}};
        stopPolling = stopPolling || function(){if(pollingIntervalId!==null){clearInterval(pollingIntervalId);pollingIntervalId=null;}};
        handleSubmitRating = handleSubmitRating || function(){if(currentRating===0){$ratingError.text('Please select a rating.');return;}$ratingError.text('');$submitRatingButton.prop('disabled',true);$.ajax({url:tlc_public_ajax.ajax_url,type:'POST',data:{action:'tlc_submit_chat_rating',nonce:tlc_public_ajax.submit_rating_nonce,visitor_token:visitorToken,rating:currentRating,comment:$ratingComment.val().trim()},success:function(r){if(r.success){$ratingForm.hide();$ratingThankYou.show();setTimeout(function(){TLC_Chat_API.hide();},3000);}else{$ratingError.text(r.data.message||'Could not submit rating.');$submitRatingButton.prop('disabled',false);}},error:function(){$ratingError.text('Network error. Could not submit rating.');$submitRatingButton.prop('disabled',false);}});};
        // End of re-definitions for safety, assuming they exist above.

        scrollToBottom();

        window.TLC_Chat_API = {
            show: function() { internalToggleWidget(true); },
            hide: function() { internalToggleWidget(false); },
            toggle: function() { internalToggleWidget(); },
            isOpen: function() { return $chatWidget.hasClass('active') && $chatContent.is(':visible'); },
            isWidgetVisible: function() { return $chatWidget.hasClass('active');},
            sendMessage: function(text) { /* ... (content from previous API, ensure it uses local functions correctly or has full logic) ... */
                if(typeof text!=='string'||text.trim()==='')return false;
                const vN=sessionStorage.getItem('tlc_visitor_name');
                if(preChatFormEnabled&&!vN){this.show();return false;}
                if(!this.isWidgetVisible()||!$chatContent.is(':visible')){this.show();}
                if(!$chatContent.is(':visible')){return false;}
                appendMessage(text,'visitor',vN||'You');scrollToBottom();
                $.ajax({url:tlc_public_ajax.ajax_url,type:'POST',data:{action:'tlc_send_visitor_message',nonce:tlc_public_ajax.send_message_nonce,message:text,visitor_token:visitorToken,current_page:window.location.href,visitor_name:vN||'',visitor_email:sessionStorage.getItem('tlc_visitor_email')||''},success:function(r){if(!r.success)console.error('API.sendMessage failed:',r.data.message);},error:function(j,t,e){console.error('API.sendMessage AJAX error:',t,e);}});
                return true;
            },
            setVisitorInfo: function(info) { /* ... (as before) ... */ },
            triggerAutoMessage: function(messageText) { /* ... (as before, ensure it checks $chatContent.is(':visible')) ... */ },
            grantConsentAndShow: function() { // New API method
                if (consentSettings && consentSettings.required) {
                    // Typically, a consent plugin would set the LocalStorage item.
                    // This function is for manual/programmatic consent if that item isn't set by other means.
                    localStorage.setItem(consentSettings.ls_key, consentSettings.ls_value);
                    handleConsentGranted(); // Make widget visible and initialize
                    this.show(); // Attempt to open it
                } else {
                    console.warn("TLC_Chat_API.grantConsentAndShow: Consent not required by settings.");
                    if (!$widgetContainer.is(':visible') || !$widgetButton.is(':visible')) {
                         $widgetContainer.addClass('tlc-consent-given').show(); // Fallback to ensure it's visible
                         initializeChatFunctionality();
                    }
                    this.show();
                }
            }
        };
    });
})( jQuery );

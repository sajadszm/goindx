(function( $ ) {
    'use strict';

    $(document).ready(function() {
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

        let visitorToken = localStorage.getItem('tlc_visitor_token');
        if (!visitorToken) {
            visitorToken = generateUUID();
            localStorage.setItem('tlc_visitor_token', visitorToken);
        }

        // Initial state of End Chat button
        if (satisfactionRatingEnabled) {
            // The button is already in DOM via PHP, so no need to explicitly show unless it was hidden by default.
            // $endChatButton.show();
        } else {
            $endChatButton.hide();
        }


        function showPreChatForm() {
            $preChatForm.show();
            $ratingForm.hide();
            $chatContent.hide();
            $visitorNameInput.focus();
        }

        function showChatArea() {
            $preChatForm.hide();
            $ratingForm.hide();
            $chatContent.show();
            $messageInput.focus();
            startPolling();
        }

        function showRatingForm() {
            $preChatForm.hide();
            $chatContent.hide();
            $ratingForm.show();
            $ratingThankYou.hide();
            $ratingError.text('');
            currentRating = 0; // Reset rating
            $ratingStars.removeClass('rated');
            $ratingComment.val('');
            stopPolling();
        }


        $widgetButton.on('click', function() {
            $chatWidget.toggleClass('active');
            if ($chatWidget.hasClass('active')) {
                const visitorName = sessionStorage.getItem('tlc_visitor_name');
                // If rating form was previously shown and then widget closed, revert to chat/pre-chat
                if ($ratingForm.is(':visible')) {
                     if (preChatFormEnabled && !visitorName) {
                        showPreChatForm();
                    } else {
                        showChatArea();
                    }
                } else {
                     if (preChatFormEnabled && !visitorName) {
                        showPreChatForm();
                    } else {
                        showChatArea();
                    }
                }
            } else {
                stopPolling();
            }
        });

        $closeButton.on('click', function() {
            $chatWidget.removeClass('active');
            stopPolling();
        });

        $startChatButton.on('click', function() {
            const name = $visitorNameInput.val().trim();
            const email = $visitorEmailInput.val().trim();
            $preChatError.text('');

            if (name === '') {
                $preChatError.text('Name is required.');
                $visitorNameInput.focus();
                return;
            }
            if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                $preChatError.text('Please enter a valid email address.');
                $visitorEmailInput.focus();
                return;
            }

            sessionStorage.setItem('tlc_visitor_name', name);
            if (email !== '') {
                sessionStorage.setItem('tlc_visitor_email', email);
            }
            showChatArea();
        });

        if ($chatWidget.hasClass('active')) {
            const visitorName = sessionStorage.getItem('tlc_visitor_name');
            if (preChatFormEnabled && !visitorName) {
                showPreChatForm();
            } else {
                showChatArea();
            }
        }

        $sendMessageButton.on('click', sendMessage);
        $messageInput.on('keypress', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        function sendMessage() {
            const messageText = $messageInput.val().trim();
            if (messageText === '') return;
            appendMessage(messageText, 'visitor', sessionStorage.getItem('tlc_visitor_name') || 'You');
            $messageInput.val('');
            scrollToBottom();

            $.ajax({
                url: tlc_public_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tlc_send_visitor_message',
                    nonce: tlc_public_ajax.send_message_nonce,
                    message: messageText,
                    visitor_token: visitorToken,
                    current_page: window.location.href,
                    visitor_name: sessionStorage.getItem('tlc_visitor_name') || '',
                    visitor_email: sessionStorage.getItem('tlc_visitor_email') || ''
                },
                success: function(response) {
                    if (!response.success) {
                        console.error('Error sending message:', response.data.message);
                        appendMessage('Error: Could not send message. ' + response.data.message, 'system');
                        scrollToBottom();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                    appendMessage('Error: Network problem. Could not send message.', 'system');
                    scrollToBottom();
                }
            });
        }

        function appendMessage(text, type, senderName = '', messageId = null) {
            let displayName = '';
            if (type === 'agent') {
                displayName = senderName || 'Agent';
            } else if (type === 'visitor') {
                displayName = senderName || (sessionStorage.getItem('tlc_visitor_name') || 'You');
            } else if (senderName === 'AutoMessage' && type === 'system') { // Special case for auto message
                // No display name for auto system messages, or could be configurable
            } else if (type === 'system' && senderName) { // Other system messages with potential sender
                displayName = senderName;
            }


            const SENDER_NAME_HTML = displayName ? `<div class="tlc-message-sender">${escapeHtml(displayName)}</div>` : '';
            const messageHtml = `
                <div class="tlc-message ${type}" data-message-id="${messageId || ''}">
                    ${SENDER_NAME_HTML}
                    <div class="tlc-message-content">${escapeHtml(text)}</div>
                </div>`;
            $messagesContainer.append(messageHtml);

            if (messageId && parseInt(messageId) > lastMessageIdDisplayed) {
                lastMessageIdDisplayed = parseInt(messageId);
            }
        }

        function appendServerMessage(message) {
            appendMessage(message.text, message.sender_type, message.agent_name, message.id);
        }

        function scrollToBottom() {
            $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
        }

        function fetchNewMessages() {
            if (!visitorToken || !$chatWidget.hasClass('active') || $ratingForm.is(':visible') || $preChatForm.is(':visible')) {
                return;
            }
            $.ajax({
                url: tlc_public_ajax.ajax_url, type: 'POST',
                data: {
                    action: 'tlc_fetch_new_messages', nonce: tlc_public_ajax.fetch_messages_nonce,
                    visitor_token: visitorToken, last_message_id_displayed: lastMessageIdDisplayed
                },
                success: function(response) {
                    if (response.success && response.data.messages && response.data.messages.length > 0) {
                        let newMessagesAdded = false;
                        response.data.messages.forEach(function(msg) {
                            appendServerMessage(msg);
                            newMessagesAdded = true;
                        });
                        if (newMessagesAdded) scrollToBottom();
                    } else if (!response.success) console.error('Error fetching new messages:', response.data.message);
                },
                error: function(jqXHR, textStatus, errorThrown) { console.error('AJAX error fetching messages:', textStatus, errorThrown); }
            });
        }

        function startPolling() {
            if (pollingIntervalId === null) {
                fetchNewMessages();
                pollingIntervalId = setInterval(fetchNewMessages, WIDGET_POLLING_INTERVAL);
            }
        }

        function stopPolling() {
            if (pollingIntervalId !== null) {
                clearInterval(pollingIntervalId);
                pollingIntervalId = null;
            }
        }

        // Automated Message Logic
        const autoMsgSettings = tlc_public_ajax.auto_message_settings;
        const autoMsgSessionKey = 'tlc_auto_msg_1_shown_session';

        function checkAndTriggerAutoMessage() {
            if (!autoMsgSettings || !autoMsgSettings.enable || sessionStorage.getItem(autoMsgSessionKey)) return;

            if (autoMsgSettings.page_targeting === 'specific_urls') {
                let onTargetPage = false; const currentPage = window.location.href;
                if (autoMsgSettings.specific_urls_array && autoMsgSettings.specific_urls_array.length > 0) {
                    for (const url of autoMsgSettings.specific_urls_array) {
                        if (currentPage.includes(url.trim())) { onTargetPage = true; break; }
                    }
                }
                if (!onTargetPage) return;
            }

            if (autoMsgSettings.trigger_type === 'time_on_page') {
                setTimeout(showAutoMessage, autoMsgSettings.trigger_value * 1000);
            } else if (autoMsgSettings.trigger_type === 'scroll_depth') {
                let scrollTriggered = false;
                $(window).on('scroll.tlc_auto_msg', function() {
                    if (scrollTriggered) return;
                    const scrollPercent = ($(window).scrollTop() / ($(document).height() - $(window).height())) * 100;
                    if (scrollPercent >= autoMsgSettings.trigger_value) {
                        showAutoMessage(); scrollTriggered = true; $(window).off('scroll.tlc_auto_msg');
                    }
                });
            }
        }

        function showAutoMessage() {
            if (sessionStorage.getItem(autoMsgSessionKey) || !autoMsgSettings.text) return;
            if (!$chatWidget.hasClass('active')) {
                $chatWidget.addClass('active');
                // Decide if pre-chat should show before auto-message or if auto-message bypasses it
                const visitorName = sessionStorage.getItem('tlc_visitor_name');
                if (preChatFormEnabled && !visitorName) {
                    showPreChatForm(); // Auto message might be lost if user doesn't complete pre-chat
                } else {
                    showChatArea(); // This starts polling
                }
            }
            appendMessage(autoMsgSettings.text, 'system', 'AutoMessage');
            scrollToBottom();
            sessionStorage.setItem(autoMsgSessionKey, 'true');
        }
        checkAndTriggerAutoMessage();

        // File Upload Logic
        if (fileUploadSettings && fileUploadSettings.enabled) {
            $fileUploadButton.on('click', function() { $fileInput.click(); });
            $fileInput.on('change', function(event) {
                if (event.target.files && event.target.files.length > 0) {
                    uploadFile(event.target.files[0]); $(this).val('');
                }
            });
        }

        function uploadFile(file) {
            const allowedTypes = fileUploadSettings.allowed_types.split(',').map(type => type.trim().toLowerCase());
            const fileExtension = file.name.split('.').pop().toLowerCase();
            let typeAllowed = false;
            if (allowedTypes.length > 0) {
                if (allowedTypes.includes(fileExtension) || allowedTypes.includes('.' + fileExtension)) {
                    typeAllowed = true;
                } else {
                    typeAllowed = allowedTypes.some(type => file.type.startsWith(type));
                }
            } else { // if allowedTypes is empty string, means allow all WP default types
                typeAllowed = true; // Server will do final check against WP defaults
            }

            if (!typeAllowed) {
                appendMessage(`File type not allowed: .${fileExtension}. Allowed: ${fileUploadSettings.allowed_types}`, 'system');
                scrollToBottom(); return;
            }

            const maxSizeInBytes = fileUploadSettings.max_size_mb * 1024 * 1024;
            if (file.size > maxSizeInBytes) {
                appendMessage(`File too large: ${(file.size / 1024 / 1024).toFixed(2)} MB. Max: ${fileUploadSettings.max_size_mb} MB.`, 'system');
                scrollToBottom(); return;
            }

            const formData = new FormData();
            formData.append('action', 'tlc_upload_chat_file');
            formData.append('nonce', fileUploadSettings.upload_nonce);
            formData.append('visitor_token', visitorToken);
            formData.append('chat_file', file);
            formData.append('current_page', window.location.href);

            const tempMessageId = 'temp-upload-' + Date.now();
            appendMessage(`Uploading ${escapeHtml(file.name)}...`, 'system', null, tempMessageId);
            scrollToBottom();

            $sendMessageButton.prop('disabled', true); $fileUploadButton.prop('disabled', true);

            $.ajax({
                url: tlc_public_ajax.ajax_url, type: 'POST', data: formData, processData: false, contentType: false,
                success: function(response) {
                    $messagesContainer.find(`[data-message-id="${tempMessageId}"]`).remove();
                    if (response.success) appendMessage(`File sent: ${escapeHtml(response.data.filename)}`, 'visitor', null, response.data.message_id);
                    else appendMessage(`Error uploading file: ${escapeHtml(response.data.message || 'Unknown error')}`, 'system');
                    scrollToBottom();
                },
                error: function() {
                    $messagesContainer.find(`[data-message-id="${tempMessageId}"]`).remove();
                    appendMessage(`Error uploading file: Network problem or server error.`, 'system'); scrollToBottom();
                },
                complete: function() { $sendMessageButton.prop('disabled', false); $fileUploadButton.prop('disabled', false); }
            });
        }

        // Rating Logic
        if (satisfactionRatingEnabled) {
            $endChatButton.on('click', function() {
                showRatingForm();
            });

            $ratingStars.on('mouseover', function() {
                const val = $(this).data('value');
                $ratingStars.removeClass('hovered');
                $ratingStars.each(function(idx) {
                    if (idx < val) $(this).addClass('hovered');
                });
            }).on('mouseout', function() {
                $ratingStars.removeClass('hovered');
            }).on('click', function() {
                currentRating = $(this).data('value');
                $ratingStars.removeClass('rated hovered'); // Clear all first
                $ratingStars.each(function(idx) {
                    if (idx < currentRating) $(this).addClass('rated');
                });
            });

            $submitRatingButton.on('click', function() {
                if (currentRating === 0) {
                    $ratingError.text('Please select a rating.'); return;
                }
                $ratingError.text(''); $submitRatingButton.prop('disabled', true);

                $.ajax({
                    url: tlc_public_ajax.ajax_url, type: 'POST',
                    data: {
                        action: 'tlc_submit_chat_rating', nonce: tlc_public_ajax.submit_rating_nonce,
                        visitor_token: visitorToken, rating: currentRating, comment: $ratingComment.val().trim()
                    },
                    success: function(response) {
                        if (response.success) {
                            $ratingForm.hide(); $ratingThankYou.show();
                            setTimeout(function() { $chatWidget.removeClass('active'); stopPolling(); }, 3000);
                        } else {
                            $ratingError.text(response.data.message || 'Could not submit rating.');
                            $submitRatingButton.prop('disabled', false);
                        }
                    },
                    error: function() {
                        $ratingError.text('Network error. Could not submit rating.');
                        $submitRatingButton.prop('disabled', false);
                    }
                });
            });
        }

        function generateUUID() {
            var d = new Date().getTime(); var d2 = ((typeof performance !== 'undefined') && performance.now && (performance.now()*1000)) || 0;
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16; if(d > 0){ r = (d + r)%16 | 0; d = Math.floor(d/16); } else { r = (d2 + r)%16 | 0; d2 = Math.floor(d2/16); }
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        }
        function escapeHtml(unsafe) {
            if (unsafe === null || typeof unsafe === 'undefined') return '';
            return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }
        scrollToBottom();
    });
})( jQuery );

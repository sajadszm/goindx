(function( $ ) {
    'use strict';

    /**
     * All of the code for your public-facing JavaScript source
     * should reside in this file.
     *
     * Note: It has been assumed you will write jQuery code here, so the
     * $ function reference has been prepared for usage within the scope
     * of this function.
     *
     * This enables you to define handlers, for instance, like so:
     *
     * $( Someting ).on( 'someevent', function() {
     * });
     *
     */
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

        let lastMessageIdDisplayed = 0; // Track the ID of the last message displayed
        let pollingIntervalId = null;
        const WIDGET_POLLING_INTERVAL = tlc_public_ajax.polling_interval || 5000;


        // Generate or retrieve visitor token
        let visitorToken = localStorage.getItem('tlc_visitor_token');
        if (!visitorToken) {
            visitorToken = generateUUID();
            localStorage.setItem('tlc_visitor_token', visitorToken);
        }

        // Toggle chat widget visibility
        $widgetButton.on('click', function() {
            $chatWidget.toggleClass('active');
            if ($chatWidget.hasClass('active')) {
                $messageInput.focus();
            }
        });

        $closeButton.on('click', function() {
            $chatWidget.removeClass('active');
        });

        // Send message
        $sendMessageButton.on('click', sendMessage);
        $messageInput.on('keypress', function(e) {
            if (e.which === 13 && !e.shiftKey) { // Enter key without Shift
                e.preventDefault();
                sendMessage();
            }
        });

        function sendMessage() {
            const messageText = $messageInput.val().trim();
            if (messageText === '') {
                return;
            }

            // Display message immediately in the visitor's chat window
            appendMessage(messageText, 'visitor');
            $messageInput.val(''); // Clear input
            scrollToBottom();

            // AJAX request to send message to server
            $.ajax({
                url: tlc_public_ajax.ajax_url, // This global var needs to be localized
                type: 'POST',
                data: {
                    action: 'tlc_send_visitor_message', // WordPress AJAX action hook
                    nonce: tlc_public_ajax.nonce,    // Nonce for security
                    message: messageText,
                    visitor_token: visitorToken,
                    // Potentially add more visitor info here: current page, etc.
                    current_page: window.location.href
                },
                success: function(response) {
                    if (response.success) {
                        // Optionally, update message with a "sent" status or ID from server
                        // console.log('Message sent successfully:', response.data);
                    } else {
                        // Handle error - perhaps show an error message in the chat
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

        function appendMessage(text, type, senderName = '') {
            // Sanitize text before appending to prevent XSS if it's not already handled
            // For now, assuming text is safe or will be sanitized server-side before echoing back
            let displayName = '';
            if (type === 'agent') {
                displayName = senderName || 'Agent'; // Use provided name or default to 'Agent'
            } else if (type === 'visitor' && !senderName) { // Only default 'You' if no senderName provided for visitor
                // This case is mostly for self-sent messages. If messages are pre-loaded, senderName might be set.
            } else if (senderName) {
                 displayName = senderName;
            }


            const SENDER_NAME_HTML = displayName ? `<div class="tlc-message-sender">${escapeHtml(displayName)}</div>` : '';
            // Add messageId as a data attribute for tracking
            const messageHtml = `
                <div class="tlc-message ${type}" data-message-id="${messageId || ''}">
                    ${SENDER_NAME_HTML}
                    <div class="tlc-message-content">${escapeHtml(text)}</div>
                </div>`;
            $messagesContainer.append(messageHtml);

            // Update lastMessageIdDisplayed if this message has an ID and it's greater
            if (messageId && parseInt(messageId) > lastMessageIdDisplayed) {
                lastMessageIdDisplayed = parseInt(messageId);
            }
        }

        // Overload appendMessage for messages coming from server (which have an ID)
        function appendServerMessage(message) {
            // Server provides: id, sender_type, text, (optionally agent_name)
            appendMessage(message.text, message.sender_type, message.agent_name, message.id);
        }


        function scrollToBottom() {
            $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
        }

        function fetchNewMessages() {
            if (!visitorToken || !$chatWidget.hasClass('active')) { // Only poll if widget is active and token exists
                return;
            }

            $.ajax({
                url: tlc_public_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tlc_fetch_new_messages',
                    nonce: tlc_public_ajax.fetch_messages_nonce,
                    visitor_token: visitorToken,
                    last_message_id_displayed: lastMessageIdDisplayed
                },
                success: function(response) {
                    if (response.success && response.data.messages && response.data.messages.length > 0) {
                        let newMessagesAdded = false;
                        response.data.messages.forEach(function(msg) {
                            appendServerMessage(msg); // Use new wrapper to pass ID
                            newMessagesAdded = true;
                        });
                        if (newMessagesAdded) {
                            scrollToBottom();
                        }
                    } else if (!response.success) {
                        console.error('Error fetching new messages:', response.data.message);
                        // Optionally show a system message for repeated failures
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error fetching messages:', textStatus, errorThrown);
                }
            });
        }

        function startPolling() {
            if (pollingIntervalId === null) { // Avoid multiple intervals
                fetchNewMessages(); // Fetch immediately then start interval
                pollingIntervalId = setInterval(fetchNewMessages, WIDGET_POLLING_INTERVAL);
            }
        }

        function stopPolling() {
            if (pollingIntervalId !== null) {
                clearInterval(pollingIntervalId);
                pollingIntervalId = null;
            }
        }

        // Modify widget toggle to start/stop polling
        $widgetButton.on('click', function() {
            $chatWidget.toggleClass('active');
            if ($chatWidget.hasClass('active')) {
                $messageInput.focus();
                startPolling();
            } else {
                stopPolling();
            }
        });

        $closeButton.on('click', function() {
            $chatWidget.removeClass('active');
            stopPolling();
        });

        // If widget is open on page load (e.g. due to previous state, not implemented yet)
        if ($chatWidget.hasClass('active')) {
            startPolling();
        }

        // Automated Message Logic
        // =========================
        const autoMsgSettings = tlc_public_ajax.auto_message_settings;
        const autoMsgSessionKey = 'tlc_auto_msg_1_shown_session';

        function checkAndTriggerAutoMessage() {
            if (!autoMsgSettings || !autoMsgSettings.enable) {
                return;
            }

            // Throttling: Show only once per session
            if (sessionStorage.getItem(autoMsgSessionKey)) {
                return;
            }

            // Page Targeting
            if (autoMsgSettings.page_targeting === 'specific_urls') {
                let onTargetPage = false;
                const currentPage = window.location.href;
                if (autoMsgSettings.specific_urls_array && autoMsgSettings.specific_urls_array.length > 0) {
                    for (const url of autoMsgSettings.specific_urls_array) {
                        // Simple check: if current URL contains the specified string.
                        // For more robust matching, regex or more complex logic might be needed.
                        if (currentPage.includes(url.trim())) {
                            onTargetPage = true;
                            break;
                        }
                    }
                }
                if (!onTargetPage) {
                    return;
                }
            }
            // User Login Status targeting (is_user_logged_in available in tlc_public_ajax) - not implemented in this simplified step

            // Trigger specific logic
            if (autoMsgSettings.trigger_type === 'time_on_page') {
                setTimeout(function() {
                    showAutoMessage();
                }, autoMsgSettings.trigger_value * 1000); // Convert seconds to ms
            } else if (autoMsgSettings.trigger_type === 'scroll_depth') {
                let scrollTriggered = false;
                $(window).on('scroll.tlc_auto_msg', function() {
                    if (scrollTriggered) return;

                    const scrollPercent = ($(window).scrollTop() / ($(document).height() - $(window).height())) * 100;
                    if (scrollPercent >= autoMsgSettings.trigger_value) {
                        showAutoMessage();
                        scrollTriggered = true; // Ensure it only triggers once per page view via scroll
                        $(window).off('scroll.tlc_auto_msg'); // Remove this specific scroll listener
                    }
                });
            }
        }

        function showAutoMessage() {
            if (sessionStorage.getItem(autoMsgSessionKey) || !autoMsgSettings.text) { // Double check shown & text exists
                return;
            }

            // Check if widget is already open and has user interaction (messages beyond welcome)
            const messagesCount = $messagesContainer.children('.tlc-message').not('.system').length;

            if (!$chatWidget.hasClass('active')) {
                $chatWidget.addClass('active'); // Open the widget
                startPolling(); // Start polling if widget was closed
            }

            appendMessage(autoMsgSettings.text, 'system', 'AutoMessage'); // Using 'system' type, could be 'auto'
            scrollToBottom();
            sessionStorage.setItem(autoMsgSessionKey, 'true'); // Mark as shown for this session
        }

        // Initialize Auto Message checks
        checkAndTriggerAutoMessage();

        // File Upload Logic
        if (fileUploadSettings && fileUploadSettings.enabled) {
            $fileUploadButton.on('click', function() {
                $fileInput.click(); // Trigger hidden file input
            });

            $fileInput.on('change', function(event) {
                if (event.target.files && event.target.files.length > 0) {
                    const file = event.target.files[0];
                    uploadFile(file);
                    $(this).val(''); // Reset file input to allow selecting the same file again
                }
            });
        }

        function uploadFile(file) {
            // Validate file type
            const allowedTypes = fileUploadSettings.allowed_types.split(',').map(type => type.trim().toLowerCase());
            const fileExtension = file.name.split('.').pop().toLowerCase();
            if (allowedTypes.length > 0 && !allowedTypes.includes(fileExtension) && !allowedTypes.includes('.' + fileExtension) ) {
                 if (!allowedTypes.find(type => file.type.startsWith(type))) { // Check MIME type as fallback
                    appendMessage(`File type not allowed: .${fileExtension}. Allowed types: ${fileUploadSettings.allowed_types}`, 'system');
                    scrollToBottom();
                    return;
                 }
            }

            // Validate file size
            const maxSizeInBytes = fileUploadSettings.max_size_mb * 1024 * 1024;
            if (file.size > maxSizeInBytes) {
                appendMessage(`File is too large: ${(file.size / 1024 / 1024).toFixed(2)} MB. Max size: ${fileUploadSettings.max_size_mb} MB.`, 'system');
                scrollToBottom();
                return;
            }

            const formData = new FormData();
            formData.append('action', 'tlc_upload_chat_file');
            formData.append('nonce', fileUploadSettings.upload_nonce);
            formData.append('visitor_token', visitorToken);
            formData.append('chat_file', file);
            formData.append('current_page', window.location.href);


            // Display temporary uploading message
            const tempMessageId = 'temp-upload-' + Date.now();
            appendMessage(`Uploading ${escapeHtml(file.name)}...`, 'system', tempMessageId); // Use ID to replace later
            scrollToBottom();

            // Disable send and upload buttons during upload
            $sendMessageButton.prop('disabled', true);
            $fileUploadButton.prop('disabled', true);


            $.ajax({
                url: tlc_public_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false, // Don't process the files
                contentType: false, // Set contentType to false as jQuery will tell the server its a query string request
                success: function(response) {
                    // Remove temporary message
                    $messagesContainer.find(`[data-message-id="${tempMessageId}"]`).remove();

                    if (response.success) {
                        appendMessage(`File sent: ${escapeHtml(response.data.filename)}`, 'visitor', null, response.data.message_id);
                    } else {
                        appendMessage(`Error uploading file: ${escapeHtml(response.data.message || 'Unknown error')}`, 'system');
                    }
                    scrollToBottom();
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $messagesContainer.find(`[data-message-id="${tempMessageId}"]`).remove();
                    appendMessage(`Error uploading file: Network problem or server error.`, 'system');
                    scrollToBottom();
                    console.error('File upload AJAX error:', textStatus, errorThrown);
                },
                complete: function() {
                    // Re-enable buttons
                    $sendMessageButton.prop('disabled', false);
                    $fileUploadButton.prop('disabled', false);
                }
            });
        }


        // Utility to generate a simple UUID for visitor token
        function generateUUID() { // Public Domain/MIT
            var d = new Date().getTime();//Timestamp
            var d2 = ((typeof performance !== 'undefined') && performance.now && (performance.now()*1000)) || 0;//Time in microseconds since page-load or 0 if unsupported
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16;//random number between 0 and 16
                if(d > 0){//Use timestamp until depleted
                    r = (d + r)%16 | 0;
                    d = Math.floor(d/16);
                } else {//Use microseconds since page-load if supported
                    r = (d2 + r)%16 | 0;
                    d2 = Math.floor(d2/16);
                }
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        }

        // Basic HTML escaping
        function escapeHtml(unsafe) {
            if (unsafe === null || typeof unsafe === 'undefined') {
                return '';
            }
            return unsafe
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

        // For future: function to poll for new messages from agent
        // function fetchNewMessages() { ... }
        // setInterval(fetchNewMessages, 5000); // Poll every 5 seconds

        /**
         * TODO: Implement logic to receive messages from the agent (via Telegram).
         * This will likely involve:
         * 1. A separate AJAX endpoint (or WebSocket connection) that the client polls/listens to.
         * 2. This endpoint would query WordPress for new messages for the current visitor's session_id
         *    that were sent by an agent and haven't been displayed yet.
         * 3. When a new agent message is received, it would be displayed using:
         *    appendMessage("Agent's reply text", "agent");
         *    scrollToBottom();
         */

        // Initial scroll to bottom if there are pre-loaded messages (not in this phase)
        scrollToBottom();
    });

})( jQuery );

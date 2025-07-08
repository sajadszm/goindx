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

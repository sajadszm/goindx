jQuery(document).ready(function($) {
    'use strict';

    const $sessionListItems = $('#tlc-session-list-items');
    const $chatAreaHeaderName = $('#tlc-current-chat-visitor-name');
    const $adminChatMessages = $('#tlc-admin-chat-messages');
    const $adminReplyArea = $('#tlc-admin-reply-area');
    const $adminReplyTextarea = $('#tlc-admin-reply-textarea');
    const $noChatSelectedMsg = $('.tlc-no-chat-selected');
    const $visitorDetailsContent = $('#tlc-visitor-details-content');
    const $wooOrdersContent = $('#tlc-woo-orders-content');
    const $wooOrdersList = $('#tlc-woo-orders-list');

    // Voice/Video elements
    const $adminCallButtonsDiv = $('#tlc-admin-call-buttons');
    const $adminVoiceCallButton = $('#tlc-admin-voice-call-button');
    const $adminVideoCallButton = $('#tlc-admin-video-call-button');
    const $adminVideoContainer = $('#tlc-admin-video-container');
    const $adminCallControls = $('#tlc-admin-call-controls'); // Placeholder
    const $adminEndCallButton = $('#tlc-admin-end-call-button'); // Placeholder

    let currentSessionId = null;
    let lastMessageIdReceived = 0;
    let messagePollingInterval = null;
    const POLLING_INTERVAL_MS = 5000; // Poll every 5 seconds

    // Localized data will be available via tlc_admin_chat_vars
    // e.g., tlc_admin_chat_vars.api_nonce, tlc_admin_chat_vars.rest_url

    function fetchSessions() {
        $sessionListItems.html('<p>' + tlc_admin_chat_vars.i18n.loadingChats + '</p>'); // Use localized string

        $.ajax({
            url: tlc_admin_chat_vars.rest_url + 'tlc/v1/sessions',
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tlc_admin_chat_vars.api_nonce);
            },
            data: {
                status: 'pending_agent,active', // Fetch both pending and active
                orderby: 'last_active_time',
                order: 'desc',
                per_page: 50 // Max sessions to show in this basic list
            },
            success: function(sessions) {
                $sessionListItems.empty();
                if (sessions && sessions.length > 0) {
                    sessions.forEach(function(session) {
                        const displayName = session.visitor_name || session.visitor_email || (tlc_admin_chat_vars.i18n.visitor + ' ' + session.visitor_token.substring(0, 8));
                        const sessionItem = $('<div class="tlc-session-item"></div>')
                            .attr('data-session-id', session.session_id)
                            .attr('data-visitor-name', displayName) // Store for header
                            .html('<strong>' + escapeHtml(displayName) + '</strong><p><small>' + tlc_admin_chat_vars.i18n.status + ': ' + escapeHtml(session.status) + '<br>' + tlc_admin_chat_vars.i18n.lastActive + ': ' + escapeHtml(session.last_active_time) + '</small></p>');
                        $sessionListItems.append(sessionItem);
                    });
                } else {
                    $sessionListItems.html('<p>' + tlc_admin_chat_vars.i18n.noActiveChats + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $sessionListItems.html('<p>' + tlc_admin_chat_vars.i18n.errorLoadingChats + '</p>');
                console.error("Error fetching sessions:", textStatus, errorThrown);
            }
        });
    }

    function loadChatMessages(sessionId, visitorName) {
        currentSessionId = sessionId;
        lastMessageIdReceived = 0; // Reset for new chat
        $adminChatMessages.empty();
        $noChatSelectedMsg.hide();
        $chatAreaHeaderName.text(tlc_admin_chat_vars.i18n.chatWith + ' ' + visitorName);
        $('#tlc-current-chat-session-id').text('Session ID: ' + sessionId);
        $adminReplyArea.show();
        $adminReplyTextarea.focus();
        $visitorDetailsContent.html('<p>Loading details...</p>'); // Clear previous details
        $wooOrdersContent.hide();
        $wooOrdersList.empty();


        // Highlight active session
        $sessionListItems.find('.tlc-session-item').removeClass('active');
        $sessionListItems.find('.tlc-session-item[data-session-id="' + sessionId + '"]').addClass('active');

        if (messagePollingInterval) {
            clearInterval(messagePollingInterval);
        }

        // Fetch full session details for the info panel
        $.ajax({
            url: tlc_admin_chat_vars.rest_url + 'tlc/v1/sessions/' + sessionId,
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tlc_admin_chat_vars.api_nonce);
            },
            success: function(session) {
                populateVisitorInfoPanel(session);
            },
            error: function() {
                $visitorDetailsContent.html('<p style="color:red;">Error loading session details.</p>');
            }
        });


        fetchMessagesForSession(sessionId, true); // Initial fetch for messages

        messagePollingInterval = setInterval(function() {
            fetchMessagesForSession(sessionId, false);
        }, POLLING_INTERVAL_MS);
    }

    function populateVisitorInfoPanel(session) {
        let detailsHtml = '<ul>';
        detailsHtml += '<li><strong>Token:</strong> ' + escapeHtml(session.visitor_token) + '</li>';
        if(session.visitor_name) detailsHtml += '<li><strong>Name:</strong> ' + escapeHtml(session.visitor_name) + '</li>';
        if(session.visitor_email) detailsHtml += '<li><strong>Email:</strong> ' + escapeHtml(session.visitor_email) + '</li>';
        if(session.wp_user_id) detailsHtml += '<li><strong>WP User ID:</strong> ' + escapeHtml(session.wp_user_id) + '</li>'; // Could link to user profile
        if(session.visitor_ip) detailsHtml += '<li><strong>IP:</strong> ' + escapeHtml(session.visitor_ip) + '</li>';
        if(session.initial_page_url) detailsHtml += '<li><strong>Started on:</strong> <a href="' + escapeHtml(session.initial_page_url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(session.initial_page_url.substring(0,30)) + '...</a></li>';
        if(session.referer) detailsHtml += '<li><strong>Referer:</strong> <a href="' + escapeHtml(session.referer) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(session.referer.substring(0,30)) + '...</a></li>';
        if(session.utm_source) detailsHtml += '<li><strong>UTM Source:</strong> ' + escapeHtml(session.utm_source) + '</li>';
        if(session.utm_medium) detailsHtml += '<li><strong>UTM Medium:</strong> ' + escapeHtml(session.utm_medium) + '</li>';
        if(session.utm_campaign) detailsHtml += '<li><strong>UTM Campaign:</strong> ' + escapeHtml(session.utm_campaign) + '</li>';
        if(session.rating) {
            let stars = '';
            for(let i=0; i<5; i++) { stars += (i < session.rating) ? '&#9733;' : '&#9734;'; }
            detailsHtml += '<li><strong>Rating:</strong> ' + stars + '</li>';
            if(session.rating_comment) detailsHtml += '<li><strong>Comment:</strong> ' + escapeHtml(session.rating_comment) + '</li>';
        }
        detailsHtml += '</ul>';
        $visitorDetailsContent.html(detailsHtml);

        if (session.woo_orders && session.woo_orders.length > 0) {
            let ordersHtml = '';
            session.woo_orders.forEach(function(order){
                ordersHtml += '<li>';
                ordersHtml += '<a href="' + escapeHtml(order.view_url) + '" target="_blank"><strong>#' + escapeHtml(order.order_number) + '</strong></a> - ' + escapeHtml(order.status);
                ordersHtml += '<br><small>' + escapeHtml(order.date_created.substring(0,10)) + ' | ' + escapeHtml(order.item_count) + ' items | ' + escapeHtml(order.total) + '</small>';
                ordersHtml += '</li>';
            });
            $wooOrdersList.html(ordersHtml);
            $wooOrdersContent.show();
        } else {
            $wooOrdersContent.hide();
            $wooOrdersList.empty();
        }
    }

    function fetchMessagesForSession(sessionId, isInitialLoad) {
        if (currentSessionId !== sessionId) return; // Switched chat

        let ajaxData = { per_page: 50 }; // Load more initially
        if (!isInitialLoad && lastMessageIdReceived > 0) {
            ajaxData.since_message_id = lastMessageIdReceived;
            ajaxData.per_page = 100; // Fetch all new since last
        }


        $.ajax({
            url: tlc_admin_chat_vars.rest_url + 'tlc/v1/sessions/' + sessionId + '/messages',
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tlc_admin_chat_vars.api_nonce);
            },
            data: ajaxData,
            success: function(messages) {
                if (messages && messages.length > 0) {
                    messages.forEach(appendAdminChatMessage);
                    scrollToAdminChatBottom();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error fetching messages for session " + sessionId + ":", textStatus, errorThrown);
                // Optionally show an error in the chat window
            }
        });
    }

    function appendAdminChatMessage(message) {
        if (message.message_id > lastMessageIdReceived) {
            lastMessageIdReceived = message.message_id;
        }
        let senderName = '';
        if (message.sender_type === 'visitor') {
            // Get visitor name from current session data if available, or use a default
            const activeSessionItem = $sessionListItems.find('.tlc-session-item.active');
            senderName = activeSessionItem.length ? activeSessionItem.data('visitor-name') : tlc_admin_chat_vars.i18n.visitor;
        } else if (message.sender_type === 'agent') {
            // Here, message.telegram_user_id or a future message.agent_wp_user_id could be used
            // to fetch agent's display name if we store it. For now, generic.
            senderName = tlc_admin_chat_vars.i18n.agent;
             if (message.agent_wp_user_id && tlc_admin_chat_vars.agents && tlc_admin_chat_vars.agents[message.agent_wp_user_id]) {
                senderName = tlc_admin_chat_vars.agents[message.agent_wp_user_id].display_name;
            } else if (message.telegram_user_id) {
                senderName += ' (TG: ' + message.telegram_user_id + ')';
            }
        } else {
            senderName = tlc_admin_chat_vars.i18n.system;
        }

        const messageDiv = $('<div class="tlc-message"></div>').addClass(message.sender_type);
        messageDiv.append($('<div class="tlc-message-sender"></div>').text(senderName));
        messageDiv.append($('<div class="tlc-message-content"></div>').html(escapeHtml(message.message_content).replace(/\n/g, '<br>'))); // Preserve line breaks
        // Add timestamp and page URL if needed
        const meta = $('<small style="display:block; font-size:0.8em; color:#777;"></small>').text(message.timestamp);
        if (message.page_url) {
            meta.append(' | ' + tlc_admin_chat_vars.i18n.sentFrom + ': <a href="' + escapeHtml(message.page_url) + '" target="_blank">' + escapeHtml(message.page_url.length > 30 ? message.page_url.substring(0,27)+'...' : message.page_url) + '</a>');
        }
        messageDiv.append(meta);

        $adminChatMessages.append(messageDiv);
    }

    function scrollToAdminChatBottom() {
        $adminChatMessages.scrollTop($adminChatMessages[0].scrollHeight);
    }

    // Event delegation for session list items
    $sessionListItems.on('click', '.tlc-session-item', function() {
        const sessionId = $(this).data('session-id');
        const visitorName = $(this).data('visitor-name');
        if (sessionId && sessionId !== currentSessionId) {
            loadChatMessages(sessionId, visitorName);
            if (tlc_admin_chat_vars.voice_chat_enabled || tlc_admin_chat_vars.video_chat_enabled) {
                $adminCallButtonsDiv.show(); // Show call buttons when a chat is selected
            }
        } else if (!sessionId) { // No chat selected or deselected
             $adminCallButtonsDiv.hide();
             $adminVideoContainer.hide();
             $adminCallControls.hide();
        }
    });

    // Voice/Video Call Button Handlers (Conceptual)
    if (tlc_admin_chat_vars.voice_chat_enabled && $adminVoiceCallButton.length) {
        $adminVoiceCallButton.on('click', function() {
            if (!currentSessionId) return;
            alert('Admin: Voice call to session ' + currentSessionId + ' (Feature Pending)');
            console.log('TLC Admin: Voice call initiated for session ' + currentSessionId + ' (conceptual).');
            // $adminChatMessages.hide(); $adminReplyArea.hide();
            // $adminVideoContainer.show(); $adminCallControls.show();
        });
    }

    if (tlc_admin_chat_vars.video_chat_enabled && $adminVideoCallButton.length) {
        $adminVideoCallButton.on('click', function() {
            if (!currentSessionId) return;
            alert('Admin: Video call to session ' + currentSessionId + ' (Feature Pending)');
            console.log('TLC Admin: Video call initiated for session ' + currentSessionId + ' (conceptual).');
            // $adminChatMessages.hide(); $adminReplyArea.hide();
            // $adminVideoContainer.show(); $adminCallControls.show();
        });
    }
    // Placeholder for ending call
    // $adminEndCallButton.on('click', function() {
    //    $adminVideoContainer.hide(); $adminCallControls.hide();
    //    $adminChatMessages.show(); $adminReplyArea.show();
    //    console.log('TLC Admin: Call ended (conceptual).');
    // });


    // Initial load
    if (typeof tlc_admin_chat_vars !== 'undefined') {
        fetchSessions();
    } else {
        console.error("TLC Admin Chat Vars not localized!");
        $sessionListItems.html('<p>Error: Plugin scripts not loaded correctly.</p>');
    }

    // Send Reply
    $('#tlc-admin-send-reply-button').on('click', function() {
        sendAdminReply();
    });

    $adminReplyTextarea.on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) { // Enter to send, Shift+Enter for newline
            e.preventDefault();
            sendAdminReply();
        }
    });

    function sendAdminReply() {
        const messageText = $adminReplyTextarea.val().trim();
        if (!messageText || !currentSessionId) {
            return;
        }

        // Temporarily disable send button
        $('#tlc-admin-send-reply-button').prop('disabled', true);

        $.ajax({
            url: tlc_admin_chat_vars.rest_url + 'tlc/v1/sessions/' + currentSessionId + '/reply',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tlc_admin_chat_vars.api_nonce); // REST API nonce
            },
            data: {
                // The nonce for the action itself, if not relying solely on X-WP-Nonce for auth & intent.
                // For custom endpoints, X-WP-Nonce is usually sufficient for authentication/authorization.
                // If a specific action nonce was registered with the endpoint, it would be passed here.
                // We are using 'reply_tlc_chat_sessions' capability check.
                message_text: messageText
            },
            success: function(response) {
                // Assuming response is the created message object
                appendAdminChatMessage(response); // Append the successfully sent message
                scrollToAdminChatBottom();
                $adminReplyTextarea.val('').focus();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error sending admin reply:", textStatus, errorThrown, jqXHR.responseJSON);
                // Display error to admin, e.g., in a small notification area
                alert(tlc_admin_chat_vars.i18n.errorSendingReply + (jqXHR.responseJSON && jqXHR.responseJSON.message ? ': ' + jqXHR.responseJSON.message : ''));
            },
            complete: function() {
                 $('#tlc-admin-send-reply-button').prop('disabled', false);
            }
        });
    }

    function escapeHtml(unsafe) {
        if (unsafe === null || typeof unsafe === 'undefined') return '';
        return unsafe
             .toString()
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

});

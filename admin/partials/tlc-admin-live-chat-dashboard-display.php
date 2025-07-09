<div class="wrap tlc-live-chat-dashboard-wrap">
    <h1><?php esc_html_e( 'Live Chat Dashboard', 'telegram-live-chat' ); ?></h1>

    <div id="tlc-live-chat-dashboard" class="tlc-live-chat-dashboard">
        <div id="tlc-session-list-panel" class="tlc-session-list-panel">
            <div class="tlc-panel-header">
                <h2><?php esc_html_e( 'Active Chats', 'telegram-live-chat' ); ?></h2>
                <!-- Add filters or search for sessions later -->
            </div>
            <div id="tlc-session-list-items" class="tlc-session-list-items">
                <!-- Sessions will be loaded here by JS -->
                <p><?php esc_html_e( 'Loading chats...', 'telegram-live-chat' ); ?></p>
            </div>
        </div>

        <div id="tlc-chat-area-panel" class="tlc-chat-area-panel">
            <div id="tlc-chat-area-header" class="tlc-panel-header">
                <h2 id="tlc-current-chat-visitor-name"><?php esc_html_e( 'Select a chat', 'telegram-live-chat' ); ?></h2>
                <!-- Display other info like session ID, status -->
            </div>
            <div id="tlc-admin-chat-messages" class="tlc-admin-chat-messages">
                <!-- Messages for the selected chat will appear here -->
                <p class="tlc-no-chat-selected"><?php esc_html_e( 'Select a chat from the list to view messages.', 'telegram-live-chat' ); ?></p>
            </div>
            <div id="tlc-admin-reply-area" class="tlc-admin-reply-area" style="display: none;"> <!-- Hidden until a chat is selected -->
                <textarea id="tlc-admin-reply-textarea" placeholder="<?php esc_attr_e( 'Type your reply...', 'telegram-live-chat' ); ?>"></textarea>
                <button id="tlc-admin-send-reply-button" class="button button-primary"><?php esc_html_e( 'Send Reply', 'telegram-live-chat' ); ?></button>
                <!-- Add canned response button/dropdown later -->
            </div>
        </div>
    </div>
</div>

<style>
    .tlc-live-chat-dashboard-wrap {
        margin-top: 20px;
    }
    .tlc-live-chat-dashboard {
        display: flex;
        height: calc(100vh - 150px); /* Adjust based on admin header/footer height */
        border: 1px solid #ccd0d4;
        background-color: #fff;
    }
    .tlc-session-list-panel {
        width: 300px;
        border-right: 1px solid #ccd0d4;
        display: flex;
        flex-direction: column;
        background-color: #f6f7f7;
    }
    .tlc-chat-area-panel {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .tlc-panel-header {
        padding: 10px 15px;
        border-bottom: 1px solid #ccd0d4;
        background-color: #f0f0f1;
    }
    .tlc-panel-header h2 {
        margin: 0;
        font-size: 1.2em;
    }
    .tlc-session-list-items {
        overflow-y: auto;
        flex-grow: 1;
    }
    .tlc-session-list-items .tlc-session-item {
        padding: 10px 15px;
        border-bottom: 1px solid #e5e5e5;
        cursor: pointer;
    }
    .tlc-session-list-items .tlc-session-item:hover {
        background-color: #e9eff4;
    }
    .tlc-session-list-items .tlc-session-item.active {
        background-color: #dbe8f3;
        font-weight: bold;
    }
    .tlc-session-list-items .tlc-session-item p {
        margin: 0 0 5px 0;
        font-size: 0.9em;
        color: #555;
    }
    .tlc-admin-chat-messages {
        flex-grow: 1;
        padding: 15px;
        overflow-y: auto;
        background-color: #fff;
    }
    .tlc-admin-chat-messages .tlc-message { /* Shared with frontend, but can be overridden */
        margin-bottom: 10px;
        padding: 8px 12px;
        border-radius: 7px;
        max-width: 70%;
        word-wrap: break-word;
    }
    .tlc-admin-chat-messages .tlc-message.visitor {
        background-color: #e1f5fe; /* Light blue for visitor */
        margin-left: auto;
        text-align: left; /* Keep text LTR for admin view */
    }
    .tlc-admin-chat-messages .tlc-message.agent {
        background-color: #dcedc8; /* Light green for agent */
        margin-right: auto;
        text-align: left; /* Keep text LTR for admin view */
    }
     .tlc-admin-chat-messages .tlc-message.system {
        background-color: #f0f0f0;
        color: #555;
        font-style: italic;
        text-align: center;
        font-size: 0.9em;
        margin-left: auto;
        margin-right: auto;
        max-width: 100%;
    }
    .tlc-admin-chat-messages .tlc-message-sender {
        font-weight: bold;
        font-size: 0.85em;
        margin-bottom: 3px;
        color: #333;
    }
    .tlc-admin-reply-area {
        padding: 10px;
        border-top: 1px solid #ccd0d4;
        background-color: #f6f7f7;
        display: flex;
    }
    .tlc-admin-reply-area textarea {
        flex-grow: 1;
        padding: 8px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        resize: none;
        min-height: 50px;
        margin-right: 10px;
    }
    .tlc-no-chat-selected {
        text-align: center;
        color: #777;
        margin-top: 50px;
        font-size: 1.1em;
    }
</style>

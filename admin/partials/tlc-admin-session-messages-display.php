<div class="wrap">
    <h1><?php esc_html_e( 'View Chat Session', 'telegram-live-chat' ); ?></h1>

    <?php
    // $session_id_to_view is available here because this partial is included
    // from display_chat_history_page() in class-tlc-admin.php
    if ( ! isset( $session_id_to_view ) || ! $session_id_to_view ) {
        echo '<p>' . esc_html__( 'No session ID provided.', 'telegram-live-chat' ) . '</p>';
        return;
    }

    global $wpdb;
    $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
    $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

    // Fetch session details
    $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $sessions_table WHERE session_id = %d", $session_id_to_view ) );

    if ( ! $session ) {
        echo '<p>' . esc_html__( 'Session not found.', 'telegram-live-chat' ) . '</p>';
        return;
    }

    // Fetch messages for this session
    $messages = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $messages_table WHERE session_id = %d ORDER BY timestamp ASC",
        $session_id_to_view
    ) );

    $history_page_url = admin_url( 'admin.php?page=telegram-live-chat-chat-history' );
    ?>

    <p><a href="<?php echo esc_url( $history_page_url ); ?>">&laquo; <?php esc_html_e( 'Back to Chat History', 'telegram-live-chat' ); ?></a></p>

    <h2><?php printf( esc_html__( 'Details for Session ID: %d', 'telegram-live-chat' ), $session->session_id ); ?></h2>
    <table class="form-table">
        <tr>
            <th scope="row"><?php esc_html_e( 'Visitor Token', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->visitor_token ); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'WP User', 'telegram-live-chat' ); ?></th>
            <td>
                <?php
                if ( $session->wp_user_id ) {
                    $user = get_userdata( $session->wp_user_id );
                    echo $user ? esc_html( $user->display_name . ' (ID: ' . $user->ID . ')' ) : esc_html__( 'N/A', 'telegram-live-chat' );
                } else {
                    esc_html_e( 'Guest', 'telegram-live-chat' );
                }
                ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Status', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( ucfirst( $session->status ) ); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Start Time', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->start_time ); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Last Active', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->last_active_time ); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Visitor IP', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->visitor_ip ); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'User Agent', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->visitor_user_agent ); ?></td>
        </tr>
         <tr>
            <th scope="row"><?php esc_html_e( 'Initial Page', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_url( $session->initial_page_url ); ?></td>
        </tr>
    </table>

    <h3><?php esc_html_e( 'Messages', 'telegram-live-chat' ); ?></h3>
    <div class="tlc-admin-messages-container" style="max-height: 500px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 10px; background: #f6f7f7;">
        <?php if ( $messages ) : ?>
            <?php foreach ( $messages as $message ) : ?>
                <div class="tlc-admin-message" style="margin-bottom: 10px; padding: 8px; border-radius: 4px; <?php echo ($message->sender_type === 'visitor') ? 'background: #e1f5fe; margin-left: 20px;' : 'background: #dcedc8; margin-right: 20px;'; ?>">
                    <strong>
                        <?php
                        if ($message->sender_type === 'visitor') {
                            esc_html_e( 'Visitor', 'telegram-live-chat' );
                        } elseif ($message->sender_type === 'agent') {
                            printf(esc_html__( 'Agent (TG ID: %s)', 'telegram-live-chat' ), esc_html($message->telegram_user_id ?: 'N/A'));
                        } else {
                            esc_html_e( 'System', 'telegram-live-chat' );
                        }
                        ?>
                    </strong>
                    <em>(<?php echo esc_html( $message->timestamp ); ?>)</em>:
                    <p style="margin-top: 5px; white-space: pre-wrap; word-wrap: break-word;"><?php echo nl2br( esc_html( $message->message_content ) ); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p><?php esc_html_e( 'No messages in this session yet.', 'telegram-live-chat' ); ?></p>
        <?php endif; ?>
    </div>

</div>

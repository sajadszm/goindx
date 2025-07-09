<div class="wrap">
    <h1><?php esc_html_e( 'View Chat Session', 'telegram-live-chat' ); ?></h1>

    <?php
    // $session_id_to_view is available here because this partial is included
    // from display_chat_history_page() in class-tlc-admin.php, which defines it.
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
            <th scope="row"><?php esc_html_e( 'Visitor Name', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->visitor_name ? $session->visitor_name : '-' ); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Visitor Email', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->visitor_email ? $session->visitor_email : '-' ); ?></td>
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
            <td><?php echo esc_html( $session->visitor_user_agent ? $session->visitor_user_agent : '-' ); ?></td>
        </tr>
         <tr>
            <th scope="row"><?php esc_html_e( 'Initial Page URL', 'telegram-live-chat' ); ?></th>
            <td><?php echo $session->initial_page_url ? make_clickable(esc_url($session->initial_page_url)) : '-'; ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Referer', 'telegram-live-chat' ); ?></th>
            <td><?php echo $session->referer ? make_clickable(esc_url($session->referer)) : '-'; ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'UTM Source', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->utm_source ? $session->utm_source : '-' ); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'UTM Medium', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->utm_medium ? $session->utm_medium : '-' ); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'UTM Campaign', 'telegram-live-chat' ); ?></th>
            <td><?php echo esc_html( $session->utm_campaign ? $session->utm_campaign : '-' ); ?></td>
        </tr>
        <?php if (isset($session->rating) && $session->rating > 0): ?>
        <tr>
            <th scope="row"><?php esc_html_e( 'Rating', 'telegram-live-chat' ); ?></th>
            <td><?php echo str_repeat( '&#9733;', absint($session->rating) ) . str_repeat( '&#9734;', 5 - absint($session->rating) ); ?></td>
        </tr>
            <?php if (!empty($session->rating_comment)): ?>
            <tr>
                <th scope="row"><?php esc_html_e( 'Rating Comment', 'telegram-live-chat' ); ?></th>
                <td><?php echo nl2br(esc_html( $session->rating_comment )); ?></td>
            </tr>
            <?php endif; ?>
        <?php endif; ?>
    </table>

    <?php
    // Display WooCommerce Orders if integration is enabled and customer ID exists
    if ( class_exists( 'WooCommerce' ) &&
         get_option(TLC_PLUGIN_PREFIX . 'woo_enable_integration', true) && // Check main enable toggle
         !empty($session->woo_customer_id) ) :

        $customer_orders = wc_get_orders(array(
            'customer_id' => $session->woo_customer_id,
            'limit'       => 5, // Show up to 5 recent orders in history view
            'orderby'     => 'date',
            'order'       => 'DESC',
        ));
    ?>
        <?php if ( !empty($customer_orders) ) : ?>
            <h3><?php esc_html_e( 'Recent WooCommerce Orders', 'telegram-live-chat' ); ?></h3>
            <table class="widefat striped tlc-woo-orders-history-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Order #', 'telegram-live-chat' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'telegram-live-chat' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'telegram-live-chat' ); ?></th>
                        <th><?php esc_html_e( 'Items', 'telegram-live-chat' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'telegram-live-chat' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $customer_orders as $order ) :
                        $order_data = $order->get_data();
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ); ?>" target="_blank">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
                        <td><?php echo esc_html( wp_date( get_option('date_format'), $order_data['date_created']->getTimestamp() ) ); ?></td>
                        <td><?php echo esc_html( wc_get_order_status_name($order->get_status()) ); ?></td>
                        <td><?php echo esc_html( $order->get_item_count() ); ?></td>
                        <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
             <p><em><?php esc_html_e( 'No WooCommerce orders found for this customer.', 'telegram-live-chat' ); ?></em></p>
        <?php endif; ?>
    <?php endif; // End WooCommerce check ?>

    <h3><?php esc_html_e( 'Messages', 'telegram-live-chat' ); ?></h3>
    <div class="tlc-admin-messages-container" style="max-height: 500px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 10px; background: #f6f7f7;">
        <?php if ( $messages ) : ?>
            <?php foreach ( $messages as $message ) : ?>
                <div class="tlc-admin-message" style="margin-bottom: 10px; padding: 8px; border-radius: 4px; <?php echo ($message->sender_type === 'visitor') ? 'background: #e1f5fe; margin-left: 20px;' : (($message->sender_type === 'agent') ? 'background: #dcedc8; margin-right: 20px;' : 'background: #f0f0f0; text-align:center;'); ?>">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <strong>
                            <?php
                            if ($message->sender_type === 'visitor') {
                                echo esc_html( $session->visitor_name ? $session->visitor_name : __('Visitor', 'telegram-live-chat') );
                            } elseif ($message->sender_type === 'agent') {
                                printf(esc_html__( 'Agent (TG ID: %s)', 'telegram-live-chat' ), esc_html($message->telegram_user_id ?: 'N/A'));
                            } else { // system or auto_message
                                esc_html_e( 'System', 'telegram-live-chat' );
                            }
                            ?>
                        </strong>
                        <em>(<?php echo esc_html( $message->timestamp ); ?>)</em>
                    </div>
                    <p style="margin-top: 0; white-space: pre-wrap; word-wrap: break-word;"><?php echo nl2br( esc_html( $message->message_content ) ); ?></p>
                    <?php if (!empty($message->page_url)): ?>
                        <small style="display: block; margin-top: 5px; font-size: 0.9em; color: #777;"><?php esc_html_e('Sent from:', 'telegram-live-chat'); ?> <a href="<?php echo esc_url($message->page_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(basename(parse_url($message->page_url, PHP_URL_PATH)) ?: parse_url($message->page_url, PHP_URL_HOST)); ?></a></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p><?php esc_html_e( 'No messages in this session yet.', 'telegram-live-chat' ); ?></p>
        <?php endif; ?>
    </div>

</div>

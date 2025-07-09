<div class="wrap">
    <h1><?php esc_html_e( 'Chat Analytics', 'telegram-live-chat' ); ?></h1>
    <p><?php esc_html_e( 'A basic overview of your chat activity.', 'telegram-live-chat' ); ?></p>

    <div id="dashboard-widgets-wrap">
        <div id="dashboard-widgets" class="metabox-holder">
            <div id="postbox-container-1" class="postbox-container">
                <div class="meta-box-sortables">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e( 'Overall Stats', 'telegram-live-chat' ); ?></span></h2>
                        <div class="inside">
                            <ul>
                                <li>
                                    <strong><?php esc_html_e( 'Total Chat Sessions:', 'telegram-live-chat' ); ?></strong>
                                    <?php echo absint( $total_chats ); ?>
                                </li>
                                <li>
                                    <strong><?php esc_html_e( 'Total Messages Exchanged:', 'telegram-live-chat' ); ?></strong>
                                    <?php echo absint( $total_messages ); ?>
                                </li>
                                <?php if (isset($average_rating) && $average_rating > 0): ?>
                                <li>
                                    <strong><?php esc_html_e( 'Average Visitor Rating:', 'telegram-live-chat' ); ?></strong>
                                    <?php echo esc_html( number_format( $average_rating, 2 ) ); ?> / 5
                                </li>
                                <?php else: ?>
                                <li>
                                    <strong><?php esc_html_e( 'Average Visitor Rating:', 'telegram-live-chat' ); ?></strong>
                                    <?php esc_html_e( 'N/A (No ratings submitted yet)', 'telegram-live-chat' ); ?>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e( 'Advanced Analytics', 'telegram-live-chat' ); ?></span></h2>
                        <div class="inside">
                            <p><?php esc_html_e( 'More detailed analytics like average response time and agent performance will be available in future updates.', 'telegram-live-chat' ); ?></p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

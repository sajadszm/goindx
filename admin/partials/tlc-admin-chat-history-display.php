<div class="wrap">
    <h1><?php esc_html_e( 'Chat History', 'telegram-live-chat' ); ?></h1>

    <form method="post">
        <?php
        // For WP_List_Table to process bulk actions, search, etc.
        // We need to ensure the class is loaded.
        if ( ! class_exists( 'TLC_Sessions_List_Table' ) ) {
            require_once plugin_dir_path( __FILE__ ) . '../class-tlc-sessions-list-table.php';
        }

        $sessions_list_table = new TLC_Sessions_List_Table();
        // $sessions_list_table->process_bulk_action(); // Call this if bulk actions are enabled
        $sessions_list_table->prepare_items();

        // Output any messages (e.g., after bulk action)
        // settings_errors(); // If using Settings API for notices

        $sessions_list_table->display();
        ?>
    </form>
</div>

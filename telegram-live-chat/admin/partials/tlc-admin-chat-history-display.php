<div class="wrap">
    <h1><?php esc_html_e( 'Chat History', 'telegram-live-chat' ); ?></h1>

    <form method="get"> <!-- Changed to get for search and pagination -->
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        <?php
        if ( ! class_exists( 'TLC_Sessions_List_Table' ) ) {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-tlc-sessions-list-table.php';
        }

        $sessions_list_table = new TLC_Sessions_List_Table();
        $sessions_list_table->prepare_items();
        $sessions_list_table->search_box( __( 'Search Sessions', 'telegram-live-chat' ), 'tlc-session-search' );
        $sessions_list_table->display();
        ?>
    </form>
</div>

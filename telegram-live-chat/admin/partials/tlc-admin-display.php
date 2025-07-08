<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <form method="post" action="options.php">
        <?php
        // This prints out all hidden setting fields
        settings_fields( TLC_PLUGIN_PREFIX . 'settings_group' );

        // This prints all sections and fields for the $this->plugin_name page
        do_settings_sections( $this->plugin_name ); // $this->plugin_name refers to 'telegram-live-chat'

        submit_button( __( 'Save Settings', 'telegram-live-chat' ) );
        ?>
    </form>
</div>

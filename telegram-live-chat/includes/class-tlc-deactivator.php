<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.1.0
 * @package    TLC
 * @subpackage TLC/includes
 * @author     Your Name <email@example.com>
 */
class TLC_Deactivator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    0.1.0
     */
    public static function deactivate() {
        // Code to run on deactivation, if any.
        // For example, clear scheduled cron jobs.
        // wp_clear_scheduled_hook('tlc_cron_hook');

        // Flush rewrite rules if any custom post types or taxonomies were registered
        // flush_rewrite_rules();
    }

}

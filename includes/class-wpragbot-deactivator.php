<?php

/**
 * The file that defines the deactivation functionality
 *
 * A class definition that includes attributes and functions used during plugin deactivation.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

/**
 * The deactivation functionality.
 *
 * Handles plugin deactivation tasks such as cleaning up data.
 *
 * @since      1.0.0
 * @package    Wpragbot
 * @author     Your Name <email@example.com>
 */
class Wpragbot_Deactivator {

    /**
     * Perform plugin deactivation tasks.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Plugin deactivation tasks
        // Note: We don't delete data by default to preserve user information
        // Users can manually clear data if they wish

        // Remove any scheduled events
        // wp_clear_scheduled_hook('wpragbot_cleanup_event');
    }
}
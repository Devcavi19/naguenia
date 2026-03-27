<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow of control:
 *
 * - This method should be static
 * - Check that the request to uninstall was sent by WordPress and not some random file
 * - Run any required clean up actions
 *
 * @link    https://github.com/Devcavi19/wpragbot
 * @since   1.0.0
 * @package Wpragbot
 */

// If uninstall is not called by WordPress, exit immediately.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop all custom plugin tables.
$tables = array(
    $wpdb->prefix . 'wpragbot_messages',
    $wpdb->prefix . 'wpragbot_analytics',
    $wpdb->prefix . 'wpragbot_session_summaries',
);

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterised.
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Remove all plugin options from wp_options.
delete_option( 'wpragbot_settings' );

// Remove any rate-limiting transients created by the plugin.
// Pattern: wpragbot_rate_<md5-of-ip>
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_wpragbot_rate_%'
        OR option_name LIKE '_transient_timeout_wpragbot_rate_%'"
);

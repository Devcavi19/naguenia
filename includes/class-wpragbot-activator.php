<?php

/**
 * The file that defines the activation functionality
 *
 * A class definition that includes attributes and functions used during plugin activation.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

/**
 * The activation functionality.
 *
 * Handles plugin activation tasks such as creating database tables.
 *
 * @since      1.0.0
 * @package    Wpragbot
 * @author     Your Name <email@example.com>
 */
class Wpragbot_Activator {

    /**
     * Perform plugin activation tasks.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Create options with default values
        $default_settings = array(
            'gemini_api_key' => '',
            'qdrant_url' => '',
            'qdrant_api_key' => '',
            'collection_name' => 'knowledge_base',
            'system_prompt' => 'You are a helpful assistant. Use the provided context to answer questions accurately.'
        );

        add_option('wpragbot_settings', $default_settings);

        // Create any necessary database tables
        self::create_database_tables();
    }

    /**
     * Create database tables if they don't exist.
     *
     * @since    1.0.0
     */
    private static function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array();

        // Table for chat sessions
        $tables['wpragbot_sessions'] = "CREATE TABLE {$wpdb->prefix}wpragbot_sessions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Table for chat messages
        $tables['wpragbot_messages'] = "CREATE TABLE {$wpdb->prefix}wpragbot_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            message_type varchar(20) NOT NULL,
            content longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY message_type (message_type),
            KEY created_at (created_at),
            KEY session_created (session_id, created_at)
        ) $charset_collate;";

        // Table for analytics data
        $tables['wpragbot_analytics'] = "CREATE TABLE {$wpdb->prefix}wpragbot_analytics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            interaction_type varchar(50) NOT NULL,
            data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY interaction_type (interaction_type),
            KEY created_at (created_at),
            KEY type_created (interaction_type, created_at)
        ) $charset_collate;";

        // Include the upgrade API
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create tables
        foreach ($tables as $table_name => $table_sql) {
            dbDelta($table_sql);
        }
    }
}
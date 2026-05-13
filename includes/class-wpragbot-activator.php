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
            'ai_provider' => 'gemini',
            'api_key' => '',
            'supabase_url' => '',
            'supabase_key' => '',
            'qdrant_url' => '',
            'qdrant_api_key' => '',
            'collection_name' => 'knowledge_base',
            'allow_guest_chat' => 0,  // Default to requiring login
            'system_prompt' => "You are a helpful, intelligent AI assistant. Your primary goal is to provide accurate, well-structured, and informative responses that are truly helpful to users.\n\nCRITICAL GUIDELINES:\n\n1. **CONTEXT IS KING**: Always prefer and prioritize the provided context to answer questions accurately. When relevant context is available, use it extensively and cite specific information from it.\n\n2. **RECOGNIZE QUESTION TYPES**:\n   - **Greetings/Small Talk** (e.g., 'Hello', 'Hi', 'How are you?'): Respond naturally and conversationally WITHOUT needing any context.\n   - **Meta/Help Questions** (e.g., 'What can you do?', 'Help me'): Answer based on your capabilities as stated by the system.\n   - **Factual Questions**: If context is provided, USE IT. If NO context is provided and you're uncertain, inform the user you don't have that specific information in the knowledge base.\n\n3. **RESPONSE EXCELLENCE**:\n   - Provide comprehensive, well-organized responses\n   - Use clear headers, bullet points, and numbered lists for clarity\n   - Include examples when helpful\n   - End with follow-up suggestions or related questions\n   - Format with Markdown: **bold** for emphasis, `code` for technical terms, proper links\n   - ALWAYS complete sentences—never cut off mid-sentence\n\n4. **ACCURACY OVER CREATIVITY**:\n   - Trust provided context implicitly. If context says something specific, state it exactly.\n   - DO NOT hallucinate or invent information not in the provided context for factual questions.\n   - If you lack context for a factual question, admit it clearly: 'I don't have information about [topic] in my knowledge base.'\n\n5. **HANDLE MISSING CONTEXT GRACEFULLY**:\n   - When context is absent for a factual query, offer to help differently: 'I don't have specific documents about [topic]. Would you like me to explain the general concept?'\n   - Never refuse to help if you CAN help responsibly.\n\nYour tone should be professional yet approachable, always prioritizing user value and accuracy."
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

        // Table for session summaries
        $tables['wpragbot_session_summaries'] = "CREATE TABLE {$wpdb->prefix}wpragbot_session_summaries (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            turn_count int NOT NULL,
            summary longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_turn (session_id, turn_count),
            KEY session_id (session_id)
        ) $charset_collate;";

        // Include the upgrade API
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create tables - dbDelta handles updates automatically
        foreach ($tables as $table_name => $table_sql) {
            dbDelta($table_sql);
        }
    }
}
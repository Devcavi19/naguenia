<?php

/**
 * The file that defines the analytics functionality
 *
 * A class definition that handles analytics and reporting for the chatbot.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

/**
 * The analytics functionality.
 *
 * Handles tracking chat interactions, performance metrics, and reporting.
 *
 * @since      1.0.0
 * @package    Wpragbot
 * @author     Your Name <email@example.com>
 */
class Wpragbot_Analytics {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
    }

    /**
     * Track a chat interaction.
     *
     * @since    1.0.0
     * @param    string    $user_message   User's message
     * @param    string    $bot_response   Bot's response
     * @param    array     $context        Retrieved context
     * @return   bool                      Whether tracking was successful
     */
    public function track_chat_interaction($user_message, $bot_response, $context = array()) {
        global $wpdb;

        // Store user message
        $user_result = $wpdb->insert(
            $wpdb->prefix . 'wpragbot_messages',
            array(
                'session_id' => 'default_session',
                'message_type' => 'user',
                'content' => $user_message,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s')
        );

        // Store bot response
        $bot_result = $wpdb->insert(
            $wpdb->prefix . 'wpragbot_messages',
            array(
                'session_id' => 'default_session',
                'message_type' => 'bot',
                'content' => $bot_response,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s')
        );

        // Store analytics data
        $analytics_result = $wpdb->insert(
            $wpdb->prefix . 'wpragbot_analytics',
            array(
                'session_id' => 'default_session',
                'interaction_type' => 'chat',
                'data' => json_encode(array(
                    'user_message' => $user_message,
                    'bot_response' => $bot_response,
                    'context' => $context
                )),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($analytics_result === false) {
            error_log('WPRAGBot: Failed to store analytics: ' . $wpdb->last_error);
        }

        return $user_result !== false && $bot_result !== false;
    }

    /**
     * Get chat statistics.
     *
     * @since    1.0.0
     * @param    int       $days           Number of days to analyze (default: 30)
     * @return   array                     Statistics data
     */
    public function get_chat_statistics($days = 30) {
        global $wpdb;

        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Total chat interactions
        $total_chats = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpragbot_messages 
             WHERE message_type = 'user' AND created_at >= %s",
            $since_date
        ));

        // Total sessions (using default session for now)
        $total_sessions = 1;

        // Average messages per session
        $avg_messages_per_session = $total_sessions > 0 ? round($total_chats / $total_sessions, 2) : 0;

        // Most active hour
        $most_active_hour = $wpdb->get_var($wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count 
             FROM {$wpdb->prefix}wpragbot_messages 
             WHERE message_type = 'user' AND created_at >= %s
             GROUP BY HOUR(created_at) 
             ORDER BY count DESC 
             LIMIT 1",
            $since_date
        ));

        // Top questions (most common)
        $top_questions = $wpdb->get_results($wpdb->prepare(
            "SELECT content, COUNT(*) as count 
             FROM {$wpdb->prefix}wpragbot_messages 
             WHERE message_type = 'user' AND created_at >= %s
             GROUP BY content 
             ORDER BY count DESC 
             LIMIT 10",
            $since_date
        ));

        return array(
            'total_chats' => $total_chats,
            'total_sessions' => $total_sessions,
            'avg_messages_per_session' => $avg_messages_per_session,
            'most_active_hour' => $most_active_hour,
            'top_questions' => $top_questions
        );
    }

    /**
     * Get usage trends.
     *
     * @since    1.0.0
     * @param    int       $days           Number of days to analyze (default: 30)
     * @return   array                     Usage trend data
     */
    public function get_usage_trends($days = 30) {
        global $wpdb;

        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Get daily chat counts
        $daily_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$wpdb->prefix}wpragbot_messages 
             WHERE message_type = 'user' AND created_at >= %s
             GROUP BY DATE(created_at) 
             ORDER BY date",
            $since_date
        ));

        return $daily_counts;
    }

    /**
     * Get recent sessions.
     *
     * @since    1.0.0
     * @param    int       $limit          Number of sessions to retrieve
     * @return   array                     Recent sessions
     */
    public function get_recent_sessions($limit = 10) {
        // Return empty array since we're not using sessions anymore
        return array();
    }

    /**
     * Get session history.
     *
     * @since    1.0.0
     * @param    string    $session_id     Session identifier
     * @return   array                     Session history
     */
    public function get_session_history($session_id) {
        global $wpdb;

        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT message_type, content, created_at 
             FROM {$wpdb->prefix}wpragbot_messages 
             WHERE session_id = %s 
             ORDER BY created_at ASC",
            $session_id
        ), ARRAY_A);

        return $history;
    }

    /**
     * Export analytics data.
     *
     * @since    1.0.0
     * @param    string    $format         Export format (csv, json)
     * @return   string                    Exported data
     */
    public function export_analytics($format = 'csv') {
        global $wpdb;

        $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpragbot_messages ORDER BY created_at DESC", ARRAY_A);

        if ($format === 'csv') {
            $output = fopen('php://temp', 'r+');
            fputcsv($output, array('ID', 'Session ID', 'Message Type', 'Content', 'Created At'));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            return stream_get_contents($output);
        } else {
            return json_encode($data);
        }
    }
}
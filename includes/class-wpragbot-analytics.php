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
     * @param    string    $session_id     Session identifier
     * @param    string    $user_message   User's message
     * @param    string    $bot_response   Bot's response
     * @param    array     $context        Retrieved context
     * @return   bool                      Whether tracking was successful
     */
    public function track_chat_interaction($session_id, $user_message, $bot_response, $context = array()) {
        global $wpdb;

        // Ensure session exists
        $this->ensure_session_exists($session_id);

        // Store user message
        $user_result = $wpdb->insert(
            $wpdb->prefix . 'wpragbot_messages',
            array(
                'session_id' => $session_id,
                'message_type' => 'user',
                'content' => $user_message,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($user_result === false) {
            error_log('WPRAGBot: Failed to store user message: ' . $wpdb->last_error);
        }

        // Store bot response
        $bot_result = $wpdb->insert(
            $wpdb->prefix . 'wpragbot_messages',
            array(
                'session_id' => $session_id,
                'message_type' => 'bot',
                'content' => $bot_response,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($bot_result === false) {
            error_log('WPRAGBot: Failed to store bot response: ' . $wpdb->last_error);
        }

        // Store analytics data
        $analytics_data = array(
            'user_message_length' => strlen($user_message),
            'bot_response_length' => strlen($bot_response),
            'context_used' => !empty($context),
            'context_data' => $context,
        );

        $analytics_result = $wpdb->insert(
            $wpdb->prefix . 'wpragbot_analytics',
            array(
                'session_id' => $session_id,
                'interaction_type' => 'chat',
                'data' => wp_json_encode($analytics_data),
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
     * Ensure session exists in database.
     *
     * @since    1.0.0
     * @param    string    $session_id     Session identifier
     * @param    int       $user_id        WordPress user ID (optional)
     * @return   bool                      Whether session was created or exists
     */
    private function ensure_session_exists($session_id, $user_id = null) {
        global $wpdb;

        // Check if session exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpragbot_sessions WHERE session_id = %s",
            $session_id
        ));

        if ($exists > 0) {
            // Update timestamp
            $wpdb->update(
                $wpdb->prefix . 'wpragbot_sessions',
                array('updated_at' => current_time('mysql')),
                array('session_id' => $session_id),
                array('%s'),
                array('%s')
            );
            return true;
        }

        // Create new session
        if ($user_id === null && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'wpragbot_sessions',
            array(
                'session_id' => $session_id,
                'user_id' => $user_id,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%s', '%s')
        );

        return $result !== false;
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

        // Total sessions
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$wpdb->prefix}wpragbot_messages 
             WHERE created_at >= %s",
            $since_date
        ));

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
        ), ARRAY_A);

        // Average response length
        $avg_response_length = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(LENGTH(content)) 
             FROM {$wpdb->prefix}wpragbot_messages 
             WHERE message_type = 'bot' AND created_at >= %s",
            $since_date
        ));

        return array(
            'total_chats' => intval($total_chats),
            'total_sessions' => intval($total_sessions),
            'avg_messages_per_session' => $avg_messages_per_session,
            'avg_response_length' => round($avg_response_length, 0),
            'most_active_hour' => intval($most_active_hour),
            'top_questions' => $top_questions,
            'period_days' => $days,
        );
    }

    /**
     * Get usage trends.
     *
     * @since    1.0.0
     * @param    int       $days           Number of days to analyze
     * @return   array                     Usage trend data
     */
    public function get_usage_trends($days = 30) {
        global $wpdb;

        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Daily usage
        $daily_usage = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$wpdb->prefix}wpragbot_messages 
             WHERE message_type = 'user' AND created_at >= %s
             GROUP BY DATE(created_at) 
             ORDER BY date ASC",
            $since_date
        ), ARRAY_A);

        // Hourly distribution
        $hourly_distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count 
             FROM {$wpdb->prefix}wpragbot_messages 
             WHERE message_type = 'user' AND created_at >= %s
             GROUP BY HOUR(created_at) 
             ORDER BY hour ASC",
            $since_date
        ), ARRAY_A);

        // Weekly usage (if more than 7 days)
        $weekly_usage = array();
        if ($days >= 7) {
            $weekly_usage = $wpdb->get_results($wpdb->prepare(
                "SELECT YEARWEEK(created_at) as week, COUNT(*) as count 
                 FROM {$wpdb->prefix}wpragbot_messages 
                 WHERE message_type = 'user' AND created_at >= %s
                 GROUP BY YEARWEEK(created_at) 
                 ORDER BY week ASC",
                $since_date
            ), ARRAY_A);
        }

        return array(
            'daily_usage' => $daily_usage,
            'hourly_distribution' => $hourly_distribution,
            'weekly_usage' => $weekly_usage,
        );
    }

    /**
     * Get session history.
     *
     * @since    1.0.0
     * @param    string    $session_id     Session identifier
     * @return   array                     Session messages
     */
    public function get_session_history($session_id) {
        global $wpdb;

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT message_type, content, created_at 
             FROM {$wpdb->prefix}wpragbot_messages 
             WHERE session_id = %s 
             ORDER BY created_at ASC",
            $session_id
        ), ARRAY_A);

        return $messages;
    }

    /**
     * Get recent sessions.
     *
     * @since    1.0.0
     * @param    int       $limit          Number of sessions to retrieve
     * @return   array                     Recent sessions
     */
    public function get_recent_sessions($limit = 10) {
        global $wpdb;

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.session_id, s.user_id, s.created_at, s.updated_at,
                    COUNT(m.id) as message_count
             FROM {$wpdb->prefix}wpragbot_sessions s
             LEFT JOIN {$wpdb->prefix}wpragbot_messages m ON s.session_id = m.session_id
             GROUP BY s.session_id
             ORDER BY s.updated_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return $sessions;
    }

    /**
     * Export analytics data.
     *
     * @since    1.0.0
     * @param    string    $format         Export format (csv, json)
     * @param    int       $days           Number of days to export
     * @return   string|WP_Error           Exported data or error
     */
    public function export_data($format = 'csv', $days = 30) {
        global $wpdb;

        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT m.session_id, m.message_type, m.content, m.created_at, s.user_id
             FROM {$wpdb->prefix}wpragbot_messages m
             LEFT JOIN {$wpdb->prefix}wpragbot_sessions s ON m.session_id = s.session_id
             WHERE m.created_at >= %s
             ORDER BY m.created_at ASC",
            $since_date
        ), ARRAY_A);

        if (empty($data)) {
            return new WP_Error('no_data', 'No data available for export');
        }

        if ($format === 'json') {
            return wp_json_encode($data, JSON_PRETTY_PRINT);
        }

        // CSV format
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, array('Session ID', 'Message Type', 'Content', 'Created At', 'User ID'));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Delete old data.
     *
     * @since    1.0.0
     * @param    int       $days           Delete data older than this many days
     * @return   int                       Number of rows deleted
     */
    public function cleanup_old_data($days = 90) {
        global $wpdb;

        $before_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Delete old messages
        $deleted_messages = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wpragbot_messages WHERE created_at < %s",
            $before_date
        ));

        // Delete old analytics
        $deleted_analytics = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wpragbot_analytics WHERE created_at < %s",
            $before_date
        ));

        // Delete orphaned sessions
        $deleted_sessions = $wpdb->query(
            "DELETE s FROM {$wpdb->prefix}wpragbot_sessions s
             LEFT JOIN {$wpdb->prefix}wpragbot_messages m ON s.session_id = m.session_id
             WHERE m.id IS NULL"
        );

        return $deleted_messages + $deleted_analytics + $deleted_sessions;
    }
}
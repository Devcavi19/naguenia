<?php

/**
 * The file that defines the analytics functionality with Supabase support
 *
 * A class definition that handles analytics and reporting for the chatbot.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

use Supabase\SupabaseClient;

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
        // Get plugin settings
        $settings = get_option('wpragbot_settings');
        
        // Check if Supabase settings are configured
        if (empty($settings['supabase_url']) || empty($settings['supabase_key'])) {
            // Fallback to WordPress database if Supabase not configured
            return $this->track_chat_interaction_db($session_id, $user_message, $bot_response, $context);
        }

        // Use Supabase for tracking
        try {
            // Check if Supabase client is available (try to include it)
            $supabase_available = false;
            if (class_exists('Supabase\SupabaseClient')) {
                $supabase_available = true;
            } else {
                // Try to include it manually if it exists
                $supabase_file = plugin_dir_path(__FILE__) . '../vendor/autoload.php';
                if (file_exists($supabase_file)) {
                    require_once $supabase_file;
                    if (class_exists('Supabase\SupabaseClient')) {
                        $supabase_available = true;
                    }
                }
            }
            
            if ($supabase_available) {
                // Initialize Supabase client
                
                $supabase = new SupabaseClient(
                    $settings['supabase_url'],
                    $settings['supabase_key']
                );
                
                // Prepare analytics data
                $analytics_data = array(
                    'session_id' => $session_id,
                    'user_message_length' => strlen($user_message),
                    'bot_response_length' => strlen($bot_response),
                    'context_used' => !empty($context),
                    'context_data' => $context,
                    'timestamp' => current_time('mysql')
                );
                
                // Insert into Supabase table
                $response = $supabase->from('wpragbot_analytics')->insert(array(
                    'session_id' => $session_id,
                    'interaction_type' => 'chat',
                    'data' => wp_json_encode($analytics_data),
                    'created_at' => current_time('mysql'),
                ));
                
                return true;
            } else {
                // Fallback to WordPress database if Supabase client not available
                error_log('WPRAGBot: Supabase client not available, falling back to WordPress database');
                return $this->track_chat_interaction_db($session_id, $user_message, $bot_response, $context);
            }
        } catch (Exception $e) {
            error_log('WPRAGBot: Supabase tracking error: ' . $e->getMessage());
            // Fallback to WordPress database if Supabase fails
            return $this->track_chat_interaction_db($session_id, $user_message, $bot_response, $context);
        }
    }

    /**
     * Fallback tracking to WordPress database.
     *
     * @since    1.0.0
     * @param    string    $session_id     Session identifier
     * @param    string    $user_message   User's message
     * @param    string    $bot_response   Bot's response
     * @param    array     $context        Retrieved context
     * @return   bool                      Whether tracking was successful
     */
    private function track_chat_interaction_db($session_id, $user_message, $bot_response, $context = array()) {
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

        return true;
    }

    /**
     * Ensure session exists in database.
     *
     * @since    1.0.0
     * @param    string    $session_id     Session identifier
     * @return   bool                      Whether session exists or was created
     */
    private function ensure_session_exists($session_id) {
        global $wpdb;

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wpragbot_sessions WHERE session_id = %s",
            $session_id
        ));

        if (!$session) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'wpragbot_sessions',
                array(
                    'session_id' => $session_id,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ),
                array('%s', '%s', '%s')
            );

            return $result !== false;
        }

        return true;
    }

    /**
     * Get chat statistics.
     *
     * @since    1.0.0
     * @param    int       $days           Number of days to look back
     * @return   array                     Statistics data
     */
    public function get_chat_statistics($days = 30) {
        global $wpdb;
        
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get total chats
        $total_chats = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpragbot_messages WHERE message_type = 'user' AND created_at >= %s",
            $since_date
        ));
        
        // Get total sessions
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$wpdb->prefix}wpragbot_messages WHERE message_type = 'user' AND created_at >= %s",
            $since_date
        ));
        
        // Get average messages per session
        $avg_messages_per_session = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(message_count) FROM (
                SELECT session_id, COUNT(*) as message_count
                FROM {$wpdb->prefix}wpragbot_messages 
                WHERE message_type = 'user' AND created_at >= %s
                GROUP BY session_id
            ) as session_counts",
            $since_date
        ));
        
        // Get most active hour
        $most_active_hour = $wpdb->get_var($wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count 
             FROM {$wpdb->prefix}wpragbot_messages 
             WHERE message_type = 'user' AND created_at >= %s
             GROUP BY HOUR(created_at) 
             ORDER BY count DESC 
             LIMIT 1",
            $since_date
        ));
        
        // Get top questions (most common)
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
            'avg_messages_per_session' => floatval($avg_messages_per_session),
            'most_active_hour' => intval($most_active_hour),
            'top_questions' => $top_questions,
            'avg_response_length' => intval($avg_response_length)
        );
    }

    /**
     * Get usage trends.
     *
     * @since    1.0.0
     * @param    int       $days           Number of days to look back
     * @return   array                     Usage trends data
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
        ), ARRAY_A);
        
        return $daily_counts;
    }

    /**
     * Get recent sessions.
     *
     * @since    1.0.0
     * @param    int       $limit          Number of sessions to return
     * @return   array                     Recent sessions data
     */
    public function get_recent_sessions($limit = 5) {
        global $wpdb;
        
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.session_id, s.created_at, COUNT(m.id) as message_count
             FROM {$wpdb->prefix}wpragbot_sessions s
             LEFT JOIN {$wpdb->prefix}wpragbot_messages m ON s.session_id = m.session_id
             GROUP BY s.session_id, s.created_at
             ORDER BY s.created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
        
        return $sessions;
    }

    /**
     * Export analytics data.
     *
     * @since    1.0.0
     * @param    string    $format         Export format (csv or json)
     * @param    int       $days           Number of days to export
     * @return   string|WP_Error           Exported data or error
     */
    public function export_data($format = 'csv', $days = 30) {
        global $wpdb;
        
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get analytics data
        $analytics_data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpragbot_analytics 
             WHERE created_at >= %s
             ORDER BY created_at DESC",
            $since_date
        ), ARRAY_A);
        
        if ($format === 'json') {
            return json_encode($analytics_data);
        } else {
            // CSV export
            $csv_data = "Session ID,Interaction Type,Data,Created At\n";
            foreach ($analytics_data as $row) {
                $csv_data .= sprintf(
                    '"%s","%s","%s","%s"' . "\n",
                    $row['session_id'],
                    $row['interaction_type'],
                    str_replace('"', '""', $row['data']),
                    $row['created_at']
                );
            }
            return $csv_data;
        }
    }
}
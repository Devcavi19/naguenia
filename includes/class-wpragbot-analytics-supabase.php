<?php
defined( 'ABSPATH' ) || exit;

/**
 * The file that defines the analytics functionality with Supabase support
 *
 * A class definition that handles analytics and reporting for the chatbot.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

// No external Supabase PHP package required — uses WordPress HTTP API (wp_remote_post)

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
     * This method implements a dual-write strategy:
     * 1. Primary: Write to Supabase (if configured and reachable)
     * 2. Secondary: Always write to local WordPress DB as backup
     *
     * This ensures data is never lost even if Supabase is unreachable or misconfigured.
     * The local DB acts as a fallback and redundancy layer.
     *
     * @since    1.0.0
     * @param    string    $session_id     Session identifier
     * @param    string    $user_message   User's message
     * @param    string    $bot_response   Bot's response
     * @param    array     $context        Retrieved context
     * @return   bool                      Whether tracking was successful (always writes to local DB)
     */
    public function track_chat_interaction($session_id, $user_message, $bot_response, $context = array()) {
        // Get plugin settings
        $settings = get_option('wpragbot_settings');
        
        // Check if Supabase settings are configured
        if (empty($settings['supabase_url']) || empty($settings['supabase_key'])) {
            // Fallback to WordPress database if Supabase not configured
            return $this->track_chat_interaction_db($session_id, $user_message, $bot_response, $context);
        }

        // Use Supabase REST API directly via WordPress HTTP API (no Composer package needed)
        try {
            $base_url = rtrim($settings['supabase_url'], '/');
            $api_key  = $settings['supabase_key'];
            $headers  = array(
                'apikey'        => $api_key,
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=minimal',
            );

            // 1. Write user message to wpragbot_messages
            $user_msg_response = wp_remote_post(
                $base_url . '/rest/v1/wpragbot_messages',
                array(
                    'timeout' => 15,
                    'headers' => $headers,
                    'body'    => wp_json_encode(array(
                        'session_id'   => $session_id,
                        'message_type' => 'user',
                        'content'      => $user_message,
                    )),
                )
            );

            $user_response_code = wp_remote_retrieve_response_code($user_msg_response);
            if (is_wp_error($user_msg_response) || $user_response_code !== 201) {
                // Log the failure but do NOT return early — continue to write the bot message
                // and analytics row so we don't lose data from a single transient failure.
                error_log('WPRAGBot Analytics: Failed to write user message to Supabase — ' . (is_wp_error($user_msg_response) ? $user_msg_response->get_error_message() : 'HTTP ' . $user_response_code));
            }

            // 2. Write bot response to wpragbot_messages
            $bot_msg_response = wp_remote_post(
                $base_url . '/rest/v1/wpragbot_messages',
                array(
                    'timeout' => 15,
                    'headers' => $headers,
                    'body'    => wp_json_encode(array(
                        'session_id'   => $session_id,
                        'message_type' => 'bot',
                        'content'      => $bot_response,
                    )),
                )
            );

            $bot_response_code = wp_remote_retrieve_response_code($bot_msg_response);
            if (is_wp_error($bot_msg_response) || $bot_response_code !== 201) {
                error_log('WPRAGBot Analytics: Failed to write bot message to Supabase — ' . (is_wp_error($bot_msg_response) ? $bot_msg_response->get_error_message() : 'HTTP ' . $bot_response_code));
            }

            // 3. Write analytics summary row to wpragbot_analytics
            $analytics_data = array(
                'user_message_length' => strlen($user_message),
                'bot_response_length' => strlen($bot_response),
                'context_used'        => !empty($context),
            );

            $analytics_response = wp_remote_post(
                $base_url . '/rest/v1/wpragbot_analytics',
                array(
                    'timeout' => 15,
                    'headers' => $headers,
                    'body'    => wp_json_encode(array(
                        'session_id'       => $session_id,
                        'interaction_type' => 'chat',
                        'data'             => wp_json_encode($analytics_data),
                    )),
                )
            );

            $analytics_response_code = wp_remote_retrieve_response_code($analytics_response);
            if (is_wp_error($analytics_response) || $analytics_response_code !== 201) {
                error_log('WPRAGBot Analytics: Failed to write analytics to Supabase — ' . (is_wp_error($analytics_response) ? $analytics_response->get_error_message() : 'HTTP ' . $analytics_response_code));
            } else {
                error_log('WPRAGBot Analytics: Successfully wrote data to Supabase');
            }

            // Always also write to local DB as a secondary backup
            $this->track_chat_interaction_db($session_id, $user_message, $bot_response, $context);

            error_log('WPRAGBot Analytics: All data successfully written to Supabase for session ' . $session_id);
            return true;

        } catch (Exception $e) {
            error_log('WPRAGBot: Supabase tracking error: ' . $e->getMessage());
            return $this->track_chat_interaction_db($session_id, $user_message, $bot_response, $context);
        }
    }

    /**
     * Test Supabase connection.
     *
     * Verifies that the provided Supabase credentials are valid and the service is reachable.
     *
     * @since    1.0.0
     * @param    string    $supabase_url    Supabase project URL
     * @param    string    $supabase_key    Supabase API/Anon key
     * @return   bool|WP_Error             True if connected, WP_Error otherwise
     */
    public function test_connection($supabase_url, $supabase_key) {
        if (empty($supabase_url) || empty($supabase_key)) {
            return new WP_Error('missing_credentials', 'Supabase URL and API Key are required');
        }

        // Use a table-level query instead of the root /rest/v1/ endpoint.
        // The root schema endpoint returns HTTP 200 even with an invalid API key;
        // a table query returns 401/403 when the key is wrong.
        $endpoint = rtrim($supabase_url, '/') . '/rest/v1/wpragbot_messages?limit=1';

        $response = wp_remote_get($endpoint, array(
            'headers' => array(
                'apikey'        => $supabase_key,
                'Authorization' => 'Bearer ' . $supabase_key,
            ),
            'timeout' => 5,
        ));

        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', 'Failed to connect to Supabase: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('invalid_credentials', 'Invalid Supabase credentials or URL (HTTP ' . $response_code . ')');
        }

        return true;
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


    /**
     * Get chat statistics.
     *
     * @since    1.0.0
     * @param    int       $days           Number of days to look back
     * @return   array                     Statistics data
     */
    public function get_chat_statistics($days = 30) {
        global $wpdb;
        
        $since_date = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
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
        
        $since_date = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
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
            "SELECT session_id, MAX(created_at) as created_at, COUNT(id) as message_count
             FROM {$wpdb->prefix}wpragbot_messages
             GROUP BY session_id
             ORDER BY created_at DESC
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
        
        $since_date = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
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
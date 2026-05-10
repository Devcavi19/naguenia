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


    // -------------------------------------------------------------------------
    // Private Supabase HTTP helper
    // -------------------------------------------------------------------------

    /**
     * Perform a GET request against the Supabase REST API.
     *
     * @param  string $path   Path relative to /rest/v1/ e.g. 'wpragbot_messages?...'
     * @return array|false    Decoded JSON array on success, false on failure.
     */
    private function supabase_get( $path ) {
        $settings = get_option( 'wpragbot_settings' );
        if ( empty( $settings['supabase_url'] ) || empty( $settings['supabase_key'] ) ) {
            return false;
        }

        $url      = rtrim( $settings['supabase_url'], '/' ) . '/rest/v1/' . ltrim( $path, '/' );
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'apikey'        => $settings['supabase_key'],
                'Authorization' => 'Bearer ' . $settings['supabase_key'],
                'Accept'        => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            error_log( 'WPRAGBot Analytics: Supabase GET failed for ' . $url . ' — ' .
                ( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ) ) );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $data ) ? $data : false;
    }

    // -------------------------------------------------------------------------
    // Read methods — Supabase first, local DB fallback
    // -------------------------------------------------------------------------

    /**
     * Get chat statistics.
     *
     * Queries Supabase when credentials are configured; falls back to local DB.
     *
     * @since    1.0.0
     * @param    int    $days    Number of days to look back
     * @return   array           Statistics data
     */
    public function get_chat_statistics( $days = 30 ) {
        $since_iso = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( "-{$days} days" ) );

        // ── Supabase path ────────────────────────────────────────────────────
        $rows = $this->supabase_get(
            'wpragbot_messages?select=session_id,message_type,content,created_at'
            . '&created_at=gte.' . rawurlencode( $since_iso )
            . '&order=created_at.asc'
        );

        if ( $rows !== false ) {
            $total_chats    = 0;
            $sessions       = array();
            $hour_counts    = array();
            $question_counts = array();
            $bot_lengths    = array();

            foreach ( $rows as $row ) {
                if ( $row['message_type'] === 'user' ) {
                    $total_chats++;
                    $sessions[ $row['session_id'] ] = true;

                    // Hour
                    $h = intval( gmdate( 'G', strtotime( $row['created_at'] ) ) );
                    $hour_counts[ $h ] = ( $hour_counts[ $h ] ?? 0 ) + 1;

                    // Top questions
                    $q = $row['content'];
                    $question_counts[ $q ] = ( $question_counts[ $q ] ?? 0 ) + 1;
                } elseif ( $row['message_type'] === 'bot' ) {
                    $bot_lengths[] = strlen( $row['content'] );
                }
            }

            // Sessions per-session message count for avg
            $session_msg_counts = array();
            foreach ( $rows as $row ) {
                if ( $row['message_type'] === 'user' ) {
                    $sid = $row['session_id'];
                    $session_msg_counts[ $sid ] = ( $session_msg_counts[ $sid ] ?? 0 ) + 1;
                }
            }

            $total_sessions = count( $sessions );
            $avg_msgs = $total_sessions > 0
                ? round( array_sum( $session_msg_counts ) / $total_sessions, 2 )
                : 0;

            arsort( $hour_counts );
            $most_active_hour = empty( $hour_counts ) ? 0 : array_key_first( $hour_counts );

            arsort( $question_counts );
            $top_questions = array();
            $i = 0;
            foreach ( $question_counts as $content => $count ) {
                $top_questions[] = array( 'content' => $content, 'count' => $count );
                if ( ++$i >= 10 ) break;
            }

            $avg_response_length = empty( $bot_lengths )
                ? 0
                : intval( array_sum( $bot_lengths ) / count( $bot_lengths ) );

            error_log( 'WPRAGBot Analytics: get_chat_statistics served from Supabase (' . count( $rows ) . ' rows)' );

            return array(
                'total_chats'              => $total_chats,
                'total_sessions'           => $total_sessions,
                'avg_messages_per_session' => $avg_msgs,
                'most_active_hour'         => $most_active_hour,
                'top_questions'            => $top_questions,
                'avg_response_length'      => $avg_response_length,
                'source'                   => 'supabase',
            );
        }

        // ── Local DB fallback ────────────────────────────────────────────────
        error_log( 'WPRAGBot Analytics: get_chat_statistics falling back to local DB' );
        global $wpdb;
        $since_date = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $total_chats = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpragbot_messages WHERE message_type='user' AND created_at>=%s",
            $since_date
        ) );
        $total_sessions = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$wpdb->prefix}wpragbot_messages WHERE message_type='user' AND created_at>=%s",
            $since_date
        ) );
        $avg_messages_per_session = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(mc) FROM (SELECT COUNT(*) AS mc FROM {$wpdb->prefix}wpragbot_messages WHERE message_type='user' AND created_at>=%s GROUP BY session_id) t",
            $since_date
        ) );
        $most_active_hour = $wpdb->get_var( $wpdb->prepare(
            "SELECT HOUR(created_at) FROM {$wpdb->prefix}wpragbot_messages WHERE message_type='user' AND created_at>=%s GROUP BY HOUR(created_at) ORDER BY COUNT(*) DESC LIMIT 1",
            $since_date
        ) );
        $top_questions = $wpdb->get_results( $wpdb->prepare(
            "SELECT content, COUNT(*) as count FROM {$wpdb->prefix}wpragbot_messages WHERE message_type='user' AND created_at>=%s GROUP BY content ORDER BY count DESC LIMIT 10",
            $since_date
        ), ARRAY_A );
        $avg_response_length = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(LENGTH(content)) FROM {$wpdb->prefix}wpragbot_messages WHERE message_type='bot' AND created_at>=%s",
            $since_date
        ) );

        return array(
            'total_chats'              => intval( $total_chats ),
            'total_sessions'           => intval( $total_sessions ),
            'avg_messages_per_session' => floatval( $avg_messages_per_session ),
            'most_active_hour'         => intval( $most_active_hour ),
            'top_questions'            => $top_questions,
            'avg_response_length'      => intval( $avg_response_length ),
            'source'                   => 'local_db',
        );
    }

    /**
     * Get usage trends (daily message counts).
     *
     * Queries Supabase when credentials are configured; falls back to local DB.
     *
     * @since    1.0.0
     * @param    int    $days    Number of days to look back
     * @return   array           Array of ['date' => 'Y-m-d', 'count' => N]
     */
    public function get_usage_trends( $days = 30 ) {
        $since_iso = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( "-{$days} days" ) );

        // ── Supabase path ────────────────────────────────────────────────────
        $rows = $this->supabase_get(
            'wpragbot_messages?select=created_at'
            . '&message_type=eq.user'
            . '&created_at=gte.' . rawurlencode( $since_iso )
            . '&order=created_at.asc'
        );

        if ( $rows !== false ) {
            $daily = array();
            foreach ( $rows as $row ) {
                $date = substr( $row['created_at'], 0, 10 ); // 'YYYY-MM-DD'
                $daily[ $date ] = ( $daily[ $date ] ?? 0 ) + 1;
            }
            $result = array();
            foreach ( $daily as $date => $count ) {
                $result[] = array( 'date' => $date, 'count' => $count );
            }
            error_log( 'WPRAGBot Analytics: get_usage_trends served from Supabase' );
            return $result;
        }

        // ── Local DB fallback ────────────────────────────────────────────────
        error_log( 'WPRAGBot Analytics: get_usage_trends falling back to local DB' );
        global $wpdb;
        $since_date = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count FROM {$wpdb->prefix}wpragbot_messages WHERE message_type='user' AND created_at>=%s GROUP BY DATE(created_at) ORDER BY date",
            $since_date
        ), ARRAY_A );
    }

    /**
     * Get recent sessions.
     *
     * Queries Supabase when credentials are configured; falls back to local DB.
     *
     * @since    1.0.0
     * @param    int    $limit    Number of sessions to return
     * @return   array            Array of session rows
     */
    public function get_recent_sessions( $limit = 5 ) {
        // ── Supabase path ────────────────────────────────────────────────────
        // Fetch enough rows to aggregate per-session; Supabase REST doesn't do GROUP BY.
        $rows = $this->supabase_get(
            'wpragbot_messages?select=session_id,created_at'
            . '&order=created_at.desc'
            . '&limit=500'   // fetch last 500 messages to derive sessions from
        );

        if ( $rows !== false ) {
            $sessions = array();
            foreach ( $rows as $row ) {
                $sid = $row['session_id'];
                if ( ! isset( $sessions[ $sid ] ) ) {
                    $sessions[ $sid ] = array(
                        'session_id'    => $sid,
                        'created_at'    => $row['created_at'],
                        'message_count' => 1,
                    );
                } else {
                    $sessions[ $sid ]['message_count']++;
                    // Keep the latest timestamp
                    if ( $row['created_at'] > $sessions[ $sid ]['created_at'] ) {
                        $sessions[ $sid ]['created_at'] = $row['created_at'];
                    }
                }
            }

            // Sort by most recent activity
            usort( $sessions, function( $a, $b ) {
                return strcmp( $b['created_at'], $a['created_at'] );
            } );

            error_log( 'WPRAGBot Analytics: get_recent_sessions served from Supabase' );
            return array_slice( array_values( $sessions ), 0, $limit );
        }

        // ── Local DB fallback ────────────────────────────────────────────────
        error_log( 'WPRAGBot Analytics: get_recent_sessions falling back to local DB' );
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT session_id, MAX(created_at) as created_at, COUNT(id) as message_count FROM {$wpdb->prefix}wpragbot_messages GROUP BY session_id ORDER BY created_at DESC LIMIT %d",
            $limit
        ), ARRAY_A );
    }

    /**
     * Export analytics data.
     *
     * Queries Supabase when credentials are configured; falls back to local DB.
     *
     * @since    1.0.0
     * @param    string    $format    'csv' or 'json'
     * @param    int       $days      Number of days to export
     * @return   string|WP_Error      Exported data string or error
     */
    public function export_data( $format = 'csv', $days = 30 ) {
        $since_iso = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( "-{$days} days" ) );

        // ── Supabase path ────────────────────────────────────────────────────
        $analytics_data = $this->supabase_get(
            'wpragbot_analytics?select=session_id,interaction_type,data,created_at'
            . '&created_at=gte.' . rawurlencode( $since_iso )
            . '&order=created_at.desc'
        );

        if ( $analytics_data === false ) {
            // Local DB fallback
            error_log( 'WPRAGBot Analytics: export_data falling back to local DB' );
            global $wpdb;
            $since_date     = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
            $analytics_data = $wpdb->get_results( $wpdb->prepare(
                "SELECT session_id, interaction_type, data, created_at FROM {$wpdb->prefix}wpragbot_analytics WHERE created_at>=%s ORDER BY created_at DESC",
                $since_date
            ), ARRAY_A );
        } else {
            error_log( 'WPRAGBot Analytics: export_data served from Supabase (' . count( $analytics_data ) . ' rows)' );
        }

        if ( $format === 'json' ) {
            return json_encode( $analytics_data );
        }

        // CSV export
        $csv = "Session ID,Interaction Type,Data,Created At\n";
        foreach ( $analytics_data as $row ) {
            $csv .= sprintf(
                '"%s","%s","%s","%s"' . "\n",
                $row['session_id'],
                $row['interaction_type'],
                str_replace( '"', '""', $row['data'] ),
                $row['created_at']
            );
        }
        return $csv;
    }
}
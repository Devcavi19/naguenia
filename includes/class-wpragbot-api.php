<?php
defined( 'ABSPATH' ) || exit;

/**
 * The file that defines the API integration functionality
 *
 * A class definition that handles integration with Gemini and Qdrant services.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 * @author     Your Name <email@example.com>
 */
class Wpragbot_API {



    /**
     * Construct context from relevant documents.
     *
     * @since    1.0.0
     * @param    array    $documents    Relevant documents
     * @return   string                 Concatenated context string
     */
    private function construct_context($documents) {
        if (empty($documents) || !is_array($documents)) {
            return '';
        }
        $context = '';
        foreach ($documents as $doc) {
            if (!empty($doc['content'])) {
                $context .= $doc['content'] . "\n\n";
            }
        }
        return trim($context);
    }

    /**
     * Generate response using selected AI provider with context.
     *
     * @since    1.0.0
     * @param    string    $user_message     The user's message
     * @param    string    $context          The context from relevant documents
     * @param    string    $api_key          The API key for the AI provider
     * @param    string    $ai_provider      The AI provider to use (mistral, openai, etc.)
     * @param    string    $system_prompt    The system prompt to use
     * @param    string    $collection_name  The collection name for reference
     * @return   string|WP_Error             Generated response or error
     */
    public function generate_response($user_message, $context, $api_key, $ai_provider, $system_prompt = '', $collection_name = '') {
        try {
            // Validate inputs
            if (empty($user_message)) {
                return new WP_Error('empty_message', 'User message cannot be empty');
            }

            if (empty($ai_provider)) {
                return new WP_Error('missing_provider', 'AI provider is required');
            }

            // Prepare the conversation history
            $messages = array();

            // Add system prompt if provided
            if (!empty($system_prompt)) {
                $messages[] = array(
                    'role' => 'system',
                    'content' => $system_prompt
                );
            }

            // Add context and user message
            $context_message = '';
            if (!empty($context)) {
                $context_message = "Context information:\n" . $context . "\n\n";
            }
            
            $messages[] = array(
                'role' => 'user',
                'content' => $context_message . "User question: " . $user_message
            );

            // Select endpoint based on AI provider
            return $this->generate_response_with_messages($messages, $api_key, $ai_provider);

        } catch (Exception $e) {
            error_log('WPRAGBot: Exception in generate_response: ' . $e->getMessage());
            return new WP_Error('api_error', 'Error generating response: ' . $e->getMessage());
        }
    }

    /**
     * Generate response using selected AI provider with pre-built messages array.
     *
     * @since    1.0.0
     * @param    array     $messages         The messages array
     * @param    string    $api_key          The API key for the AI provider
     * @param    string    $ai_provider      The AI provider to use
     * @return   string|WP_Error             Generated response or error
     */
    public function generate_response_with_messages($messages, $api_key, $ai_provider) {
        try {
            $endpoint = '';
            $headers = array(
                'Content-Type' => 'application/json',
            );

            switch (strtolower($ai_provider)) {
                case 'mistral':
                    $endpoint = $this->mistral_generate_endpoint;
                    if (!empty($api_key)) {
                        $headers['Authorization'] = 'Bearer ' . $api_key;
                    }
                    break;
                case 'openai':
                    $endpoint = $this->openai_generate_endpoint;
                    if (!empty($api_key)) {
                        $headers['Authorization'] = 'Bearer ' . $api_key;
                    }
                    break;
                case 'openrouter':
                    $endpoint = $this->openrouter_generate_endpoint;
                    if (!empty($api_key)) {
                        $headers['Authorization'] = 'Bearer ' . $api_key;
                    }
                    break;
                case 'gemini':
                    $endpoint = $this->gemini_generate_endpoint;
                    if (!empty($api_key)) {
                        $headers['x-api-key'] = $api_key;
                    }
                    break;
                default:
                    return new WP_Error('unsupported_provider', 'Unsupported AI provider: ' . $ai_provider);
            }

            // Prepare request body
            $body = array(
                'model' => $this->get_model_name($ai_provider),
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1024,
            );

            // Add streaming support for some providers
            if (strtolower($ai_provider) === 'openrouter') {
                $body['stream'] = false;
            }

            // Make the API request
            $response = wp_remote_post($endpoint, array(
                'timeout' => $this->timeout,
                'headers' => $headers,
                'body' => wp_json_encode($body),
            ));

            if (is_wp_error($response)) {
                error_log('WPRAGBot: AI API request failed: ' . $response->get_error_message());
                return new WP_Error('ai_request_failed', 'Failed to connect to AI provider: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
                error_log('WPRAGBot: AI API error (code ' . $response_code . '): ' . $error_message);
                return new WP_Error('ai_api_error', 'AI API error: ' . $error_message, array('status' => $response_code));
            }

            // Parse the response based on provider
            $data = json_decode($response_body, true);
            
            if (!isset($data['choices']) || !is_array($data['choices']) || empty($data['choices'])) {
                error_log('WPRAGBot: Invalid AI API response structure: ' . substr($response_body, 0, 200));
                return new WP_Error('invalid_ai_response', 'Invalid response from AI provider');
            }

            // Extract the response text
            $response_text = '';
            if (isset($data['choices'][0]['message']['content'])) {
                $response_text = $data['choices'][0]['message']['content'];
            } elseif (isset($data['choices'][0]['delta']['content'])) {
                // For streaming responses
                $response_text = $data['choices'][0]['delta']['content'];
            }

            if (empty($response_text)) {
                error_log('WPRAGBot: Empty response from AI provider');
                return new WP_Error('empty_response', 'Empty response from AI provider');
            }

            return trim($response_text);

        } catch (Exception $e) {
            error_log('WPRAGBot: Exception in generate_response_with_messages: ' . $e->getMessage());
            return new WP_Error('api_error', 'Error generating response: ' . $e->getMessage());
        }
    }

    /**
     * Get prompt history for session from Supabase or Local DB.
     *
     * @param string $session_id
     * @return array
     */
    private function get_prompt_history($session_id) {
        $settings = get_option('wpragbot_settings');
        if (!empty($settings['supabase_url']) && !empty($settings['supabase_key'])) {
            return $this->get_prompt_history_supabase($session_id, $settings['supabase_url'], $settings['supabase_key']);
        }
        return $this->get_prompt_history_local($session_id);
    }

    private function get_prompt_history_supabase($session_id, $url, $key) {
        $endpoint = rtrim($url, '/') . '/rest/v1/wpragbot_messages?session_id=eq.' . urlencode($session_id) . '&order=created_at.asc';
        $response = wp_remote_get($endpoint, array(
            'headers' => array(
                'apikey' => $key,
                'Authorization' => 'Bearer ' . $key,
            )
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $this->get_prompt_history_local($session_id); // Fallback
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) return array();
        
        $messages = array();
        foreach ($data as $row) {
            $messages[] = array(
                'role' => $row['message_type'],
                'content' => $row['content']
            );
        }
        return $messages;
    }

    private function get_prompt_history_local($session_id) {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT message_type, content FROM {$wpdb->prefix}wpragbot_messages WHERE session_id = %s ORDER BY created_at ASC",
            $session_id
        ), ARRAY_A);
        
        $messages = array();
        if ($results) {
            foreach ($results as $row) {
                // map message_type (bot->assistant, user->user if needed, assuming user/bot)
                $messages[] = array(
                    'role' => $row['message_type'] === 'bot' ? 'assistant' : 'user',
                    'content' => $row['content']
                );
            }
        }
        return $messages;
    }

    private function get_session_summary($session_id, $turn_count) {
        $settings = get_option('wpragbot_settings');
        if (!empty($settings['supabase_url']) && !empty($settings['supabase_key'])) {
            // Check supabase
            $endpoint = rtrim($settings['supabase_url'], '/') . '/rest/v1/session_summaries?session_id=eq.' . urlencode($session_id) . '&turn_count=eq.' . intval($turn_count);
            $response = wp_remote_get($endpoint, array(
                'headers' => array(
                    'apikey' => $settings['supabase_key'],
                    'Authorization' => 'Bearer ' . $settings['supabase_key'],
                )
            ));
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (is_array($data) && !empty($data)) {
                    return $data[0]['summary'];
                }
            }
        }
        
        // Fallback or local
        global $wpdb;
        $summary = $wpdb->get_var($wpdb->prepare(
            "SELECT summary FROM {$wpdb->prefix}wpragbot_session_summaries WHERE session_id = %s AND turn_count = %d",
            $session_id, $turn_count
        ));
        
        return $summary ? $summary : null;
    }

    private function save_session_summary($session_id, $turn_count, $summary) {
        $settings = get_option('wpragbot_settings');
        if (!empty($settings['supabase_url']) && !empty($settings['supabase_key'])) {
            $endpoint = rtrim($settings['supabase_url'], '/') . '/rest/v1/session_summaries';
            $body = array(
                'session_id' => $session_id,
                'turn_count' => $turn_count,
                'summary' => $summary
            );
            wp_remote_post($endpoint, array(
                'method' => 'POST',
                'headers' => array(
                    'apikey' => $settings['supabase_key'],
                    'Authorization' => 'Bearer ' . $settings['supabase_key'],
                    'Content-Type' => 'application/json',
                    'Prefer' => 'resolution=merge-duplicates'
                ),
                'body' => wp_json_encode($body)
            ));
        }
        
        global $wpdb;
        $wpdb->replace(
            $wpdb->prefix . 'wpragbot_session_summaries',
            array(
                'session_id' => $session_id,
                'turn_count' => $turn_count,
                'summary' => $summary,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s')
        );
    }

    private function summarise_turns($turns, $api_key, $ai_provider) {
        $content = "";
        foreach ($turns as $turn) {
            $content .= strtoupper($turn['role']) . ": " . $turn['content'] . "\n";
        }
        
        $messages = array(
            array(
                'role' => 'user',
                'content' => "Summarize briefly. Keep key facts, preferences, unresolved questions. Be concise.\n\n" . $content
            )
        );
        
        $response = $this->generate_response_with_messages($messages, $api_key, $ai_provider);
        return is_wp_error($response) ? null : $response;
    }

    /**
     * Get the appropriate model name for the AI provider.
     *
     * @since    1.0.0
     * @param    string    $ai_provider      The AI provider to use
     * @return   string                      The model name
     */
    private function get_model_name($ai_provider) {
        switch (strtolower($ai_provider)) {
            case 'mistral':
                // Updated to a valid current model with moderate cost / free-tier suitability.
                return 'mistral-small-latest';
            case 'openai':
                return 'gpt-3.5-turbo';
            case 'openrouter':
                return 'openai/gpt-3.5-turbo';
            case 'gemini':
                return 'gemini-1.5-flash';
            default:
                return 'gpt-3.5-turbo';
        }
    }


    /**
     * Generate embedding for text using the Hugging Face All-MiniLM-L6-v2 endpoint only.
     *
     * @since    1.0.0
     * @param    string    $text           Text to embed
     * @return   array|WP_Error            Embedding array or error
     */

    /**
     * AI Provider endpoints
     */
    private $gemini_embedding_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent';
    private $gemini_generate_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    private $openrouter_embedding_endpoint = 'https://openrouter.ai/api/v1/embeddings';
    private $openrouter_generate_endpoint = 'https://openrouter.ai/api/v1/chat/completions';
    private $mistral_embedding_endpoint = 'https://api.mistral.ai/v1/embeddings';
    private $mistral_generate_endpoint = 'https://api.mistral.ai/v1/chat/completions';
    private $openai_embedding_endpoint = 'https://api.openai.com/v1/embeddings';
    private $openai_generate_endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * API timeout in seconds
     */
    private $timeout = 60;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
    }

    /**
     * Process a chat request through the RAG pipeline.
     *
     * @since    1.0.0
     * @param    string    $message        The user's message
     * @param    string    $session_id     The user session ID
     * @param    array     $settings       Plugin settings
     * @return   array|WP_Error            Response data or error
     */
    public function process_chat_request($message, $session_id, $settings) {
        try {
            error_log('WPRAGBot: Processing chat request - Session: ' . $session_id . ', Message: ' . substr($message, 0, 50));
            error_log('WPRAGBot: Settings check - Provider: ' . (empty($settings['ai_provider']) ? 'MISSING' : $settings['ai_provider']) . 
                      ', Qdrant URL: ' . (empty($settings['qdrant_url']) ? 'MISSING' : $settings['qdrant_url']) . 
                      ', Collection: ' . (empty($settings['collection_name']) ? 'MISSING' : $settings['collection_name']));
            
            // 1. Generate embedding for the user message
            $query_embedding = $this->generate_embedding($message, $settings['api_key'], $settings['ai_provider'], 'retrieval_query');

            if (is_wp_error($query_embedding)) {
                error_log('WPRAGBot: Embedding generation failed: ' . $query_embedding->get_error_message());
                return $query_embedding;
            }
            
            error_log('WPRAGBot: Generated embedding with dimension: ' . count($query_embedding));

            // 2. Search for relevant documents in Qdrant
            $relevant_docs = $this->search_documents($query_embedding, $settings['qdrant_url'], $settings['collection_name'], $settings['qdrant_api_key']);

            if (is_wp_error($relevant_docs)) {
                error_log('WPRAGBot: Document search failed: ' . $relevant_docs->get_error_message());
                return $relevant_docs;
            }
            
            error_log('WPRAGBot: Retrieved ' . count($relevant_docs) . ' relevant documents');

            // 3. Construct context with relevant documents
            $context = $this->construct_context($relevant_docs);
            
            error_log('WPRAGBot: Context length: ' . strlen($context) . ' characters');

            // 4. Prepare chat history and summaries
            $all_messages = $this->get_prompt_history($session_id);
            $count = count($all_messages);
            $summary = null;
            $recent_messages = $all_messages;

            if ($count > 10) {
                $old_messages = array_slice($all_messages, 0, -6);
                $recent_messages = array_slice($all_messages, -6);
                $turn_count = count($old_messages);

                $summary = $this->get_session_summary($session_id, $turn_count);
                if (!$summary) {
                    $summary = $this->summarise_turns($old_messages, $settings['api_key'], $settings['ai_provider']);
                    if ($summary) {
                        $this->save_session_summary($session_id, $turn_count, $summary);
                    }
                }
            }

            // 5. Build final messages array
            $final_messages = array();
            
            $system_prompt = $settings['system_prompt'] ?? '';
            if (!empty($system_prompt)) {
                $final_messages[] = array(
                    'role' => 'system',
                    'content' => $system_prompt
                );
            }

            $context_block = "";
            if (!empty($context)) {
                $context_block .= "Context information:\n" . $context . "\n\n";
            }
            if ($summary) {
                $context_block .= "Previous Conversations Summary:\n" . $summary . "\n\n";
            }

            foreach ($recent_messages as $msg) {
                // Supabase analytic insertions might use local names like 'user'/'bot'. 
                // Mistral/OpenAI expect 'user'/'assistant'
                $final_messages[] = array(
                    'role' => $msg['role'] === 'bot' ? 'assistant' : $msg['role'],
                    'content' => $msg['content']
                );
            }

            $final_messages[] = array(
                'role' => 'user',
                'content' => $context_block . "User question: " . $message
            );

            // 6. Generate response using selected AI provider with messages array
            $response = $this->generate_response_with_messages(
                $final_messages, 
                $settings['api_key'], 
                $settings['ai_provider']
            );

            if (is_wp_error($response)) {
                error_log('WPRAGBot: Response generation failed: ' . $response->get_error_message());
                return $response;
            }
            
            error_log('WPRAGBot: Successfully generated response');

            // 7. Return the response
            return array(
                'response' => $response,
                'context' => $context,
                'session_id' => $session_id
            );

        } catch (Exception $e) {
            error_log('WPRAGBot: Exception in process_chat_request: ' . $e->getMessage());
            return new WP_Error('api_error', 'Error processing chat request: ' . $e->getMessage());
        }
    }


    /**
     * Search for relevant documents in Qdrant.
     *
     * @since    1.0.0
     * @param    array     $query_embedding    Query embedding vector
     * @param    string    $qdrant_url         Qdrant service URL
     * @param    string    $collection_name    Qdrant collection name
     * @param    string    $api_key            Qdrant API key
     * @return   array|WP_Error                Found documents or error
     */
    private function search_documents($query_embedding, $qdrant_url, $collection_name, $api_key) {
        $qdrant_url = trim( $qdrant_url );
        if ( empty( $qdrant_url ) || empty( $collection_name ) ) {
            error_log( 'WPRAGBot: Missing Qdrant configuration - URL: ' . ( $qdrant_url ? 'set' : 'empty' ) . ', Collection: ' . ( $collection_name ? $collection_name : 'empty' ) );
            return new WP_Error( 'missing_qdrant_config', 'Qdrant URL and collection name are required' );
        }

        if ( ! filter_var( $qdrant_url, FILTER_VALIDATE_URL ) || ! wp_http_validate_url( $qdrant_url ) ) {
            error_log( 'WPRAGBot: Invalid Qdrant URL provided.' );
            return new WP_Error( 'invalid_qdrant_url', 'Invalid Qdrant URL provided' );
        }

        if (empty($query_embedding) || !is_array($query_embedding)) {
            error_log('WPRAGBot: Invalid query embedding - is array: ' . (is_array($query_embedding) ? 'yes' : 'no') . ', count: ' . (is_array($query_embedding) ? count($query_embedding) : 0));
            return new WP_Error('invalid_embedding', 'Valid query embedding is required');
        }

        // Remove trailing slash from URL
        $qdrant_url = rtrim($qdrant_url, '/');

        // Construct search endpoint
        $url = $qdrant_url . '/collections/' . urlencode($collection_name) . '/points/search';
        
        error_log('WPRAGBot: Searching Qdrant at: ' . $url . ' with embedding dimension: ' . count($query_embedding));

        $body = array(
            'vector' => $query_embedding,
            'limit' => 10,
            'with_payload' => true,
            'with_vector' => false,
            'score_threshold' => 0.5,
        );

        $headers = array(
            'Content-Type' => 'application/json',
        );

        // Add API key if provided
        if (!empty($api_key)) {
            $headers['api-key'] = $api_key;
        }

        error_log('WPRAGBot: Request body: ' . wp_json_encode(array(
            'vector_dimension' => count($query_embedding),
            'limit' => 10,
            'with_payload' => true
        )));

        $response = wp_remote_post($url, array(
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            error_log('WPRAGBot: Qdrant connection error: ' . $response->get_error_message());
            return new WP_Error('qdrant_request_failed', 'Failed to connect to Qdrant: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('WPRAGBot: Qdrant response code: ' . $response_code);
        
        if ($response_code === 403 || $response_code === 401) {
            error_log('WPRAGBot: Authentication failed - check Qdrant API key in plugin settings');
        }

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['status']['error']) ? $error_data['status']['error'] : 'Unknown error';
            error_log('WPRAGBot: Qdrant API error (code ' . $response_code . '): ' . $error_message);
            return new WP_Error('qdrant_api_error', 'Qdrant API error: ' . $error_message, array('status' => $response_code));
        }

        $data = json_decode($response_body, true);

        if (!isset($data['result']) || !is_array($data['result'])) {
            error_log('WPRAGBot: Invalid Qdrant response structure: ' . substr($response_body, 0, 200));
            return new WP_Error('invalid_qdrant_response', 'Invalid response from Qdrant API');
        }

        // Transform results to expected format
        $documents = array();
        foreach ($data['result'] as $result) {
            // Try both 'text' and 'content' field names for compatibility
            $content = '';
            if (isset($result['payload']['text'])) {
                $content = $result['payload']['text'];
            } elseif (isset($result['payload']['content'])) {
                $content = $result['payload']['content'];
            }
            
            // Debug: Log payload structure for first result
            if (count($documents) === 0) {
                error_log('WPRAGBot: First document payload keys: ' . implode(', ', array_keys($result['payload'] ?? array())));
                error_log('WPRAGBot: First document content length: ' . strlen($content));
                error_log('WPRAGBot: First document content preview: ' . substr($content, 0, 100));
            }
            
            $documents[] = array(
                'id' => $result['id'],
                'content' => $content,
                'score' => isset($result['score']) ? $result['score'] : 0,
                'metadata' => isset($result['payload']) ? $result['payload'] : array(),
            );
        }
        
        error_log('WPRAGBot: Retrieved ' . count($documents) . ' documents from Qdrant');

        return $documents;
    }

    /**
     * Generate embedding for text using the Hugging Face All-MiniLM-L6-v2 endpoint.
     *
     * Note: $api_key, $ai_provider, and $task_type are accepted for API consistency
     * and future extensibility, but the current implementation always delegates to
     * Wpragbot_Embedding (HuggingFace) regardless of the AI provider setting.
     *
     * @since    1.0.0
     * @param    string    $text           Text to embed
     * @param    string    $api_key        API key (reserved for future provider-based embedding)
     * @param    string    $ai_provider    AI provider name (reserved for future use)
     * @param    string    $task_type      Embedding task type hint (reserved for future use)
     * @return   array|WP_Error            Embedding array or error
     */
    private function generate_embedding($text, $api_key = '', $ai_provider = '', $task_type = '') {
        if (empty($text)) {
            return new WP_Error('empty_text', 'Text to embed cannot be empty');
        }
        // Always use the Hugging Face embedding class
        if (!class_exists('Wpragbot_Embedding')) {
            require_once plugin_dir_path(__FILE__) . 'class-wpragbot-embedding.php';
        }
        $embedding_handler = new Wpragbot_Embedding();
        $embedding = $embedding_handler->generate_embedding($text);
        if (is_wp_error($embedding)) {
            return $embedding;
        }
        return $embedding;
    }

    /**
     * Upload points to Qdrant collection.
     *
     * @since    1.0.0
     * @param    array     $points           Array of points to upload
     * @param    string    $qdrant_url       Qdrant service URL
     * @param    string    $collection_name  Qdrant collection name
     * @param    string    $api_key          Qdrant API key
     * @return   array|WP_Error              Upload result or error
     */
    private function upload_to_qdrant($points, $qdrant_url, $collection_name, $api_key) {
        if (empty($points)) {
            return new WP_Error('no_points', 'No points to upload');
        }

        $qdrant_url = rtrim($qdrant_url, '/');
        $url = $qdrant_url . '/collections/' . urlencode($collection_name) . '/points';

        // Check if collection exists, create if not
        $collection_exists = $this->check_qdrant_collection($qdrant_url, $collection_name, $api_key);
        
        if (is_wp_error($collection_exists)) {
            return $collection_exists;
        }

        if (!$collection_exists) {
            $create_result = $this->create_qdrant_collection($qdrant_url, $collection_name, $api_key, count($points[0]['vector']));
            if (is_wp_error($create_result)) {
                return $create_result;
            }
        }

        $body = array(
            'points' => $points,
        );

        $headers = array(
            'Content-Type' => 'application/json',
        );

        if (!empty($api_key)) {
            $headers['api-key'] = $api_key;
        }

        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'timeout' => $this->timeout * 2, // Longer timeout for uploads
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('qdrant_upload_failed', 'Failed to upload to Qdrant: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['status']['error']) ? $error_data['status']['error'] : 'Unknown error';
            return new WP_Error('qdrant_upload_error', 'Qdrant upload error: ' . $error_message, array('status' => $response_code));
        }

        return array(
            'success' => true,
            'uploaded' => count($points),
        );
    }

    /**
     * Check if Qdrant collection exists.
     *
     * @since    1.0.0
     * @param    string    $qdrant_url       Qdrant service URL
     * @param    string    $collection_name  Collection name
     * @param    string    $api_key          Qdrant API key
     * @return   bool|WP_Error               True if exists, false if not, WP_Error on failure
     */
    private function check_qdrant_collection($qdrant_url, $collection_name, $api_key) {
        $url = rtrim($qdrant_url, '/') . '/collections/' . urlencode($collection_name);

        $headers = array();
        if (!empty($api_key)) {
            $headers['api-key'] = $api_key;
        }

        $response = wp_remote_get($url, array(
            'timeout' => $this->timeout,
            'headers' => $headers,
        ));

        if (is_wp_error($response)) {
            return new WP_Error('qdrant_check_failed', 'Failed to check Qdrant collection: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        return $response_code === 200;
    }

    /**
     * Create Qdrant collection.
     *
     * @since    1.0.0
     * @param    string    $qdrant_url       Qdrant service URL
     * @param    string    $collection_name  Collection name
     * @param    string    $api_key          Qdrant API key
     * @param    int       $vector_size      Size of vectors
     * @return   bool|WP_Error               True on success, WP_Error on failure
     */
    private function create_qdrant_collection($qdrant_url, $collection_name, $api_key, $vector_size) {
        $url = rtrim($qdrant_url, '/') . '/collections/' . urlencode($collection_name);

        $body = array(
            'vectors' => array(
                'size' => $vector_size,
                'distance' => 'Cosine',
            ),
        );

        $headers = array(
            'Content-Type' => 'application/json',
        );

        if (!empty($api_key)) {
            $headers['api-key'] = $api_key;
        }

        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('qdrant_create_failed', 'Failed to create Qdrant collection: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['status']['error']) ? $error_data['status']['error'] : 'Unknown error';
            return new WP_Error('qdrant_create_error', 'Failed to create collection: ' . $error_message);
        }

        return true;
    }

    /**
     * Split content into chunks for embedding.
     *
     * @since    1.0.0
     * @param    string    $content        Content to chunk
     * @return   array                     Array of content chunks
     */
    private function chunk_content($content) {
        $chunks = array();
        $chunk_size = 1500; // Target characters per chunk
        $overlap = 300; // Overlap between chunks to maintain context

        // Clean and normalize content
        $content = preg_replace('/\s+/', ' ', trim($content));
        
        if (empty($content)) {
            return $chunks;
        }

        $content_length = strlen($content);
        
        // If content is smaller than chunk size, return as single chunk
        if ($content_length <= $chunk_size) {
            return array($content);
        }

        $start = 0;

        while ($start < $content_length) {
            $end = min($start + $chunk_size, $content_length);

            // Try to break at sentence boundary
            if ($end < $content_length) {
                // Look for sentence endings
                $sentence_end = $this->find_sentence_boundary($content, $end, $start);
                if ($sentence_end !== false) {
                    $end = $sentence_end;
                } else {
                    // Fall back to word boundary
                    $word_end = $this->find_word_boundary($content, $end, $start);
                    if ($word_end !== false) {
                        $end = $word_end;
                    }
                }
            }

            $chunk = trim(substr($content, $start, $end - $start));
            
            if (!empty($chunk)) {
                $chunks[] = $chunk;
            }

            // Move start position with overlap
            $start = $end - $overlap;
            
            // Ensure we make progress
            if ($start <= ($end - $overlap)) {
                $start = $end;
            }
        }

        return $chunks;
    }

    /**
     * Find sentence boundary near target position.
     *
     * @since    1.0.0
     * @param    string    $content    Content to search
     * @param    int       $target     Target position
     * @param    int       $min        Minimum position
     * @return   int|false             Position of sentence boundary or false
     */
    private function find_sentence_boundary($content, $target, $min) {
        $search_range = 100;
        $search_start = max($min, $target - $search_range);
        $search_end = min(strlen($content), $target + $search_range);
        
        $search_text = substr($content, $search_start, $search_end - $search_start);
        
        // Look for sentence endings
        $patterns = array('.', '!', '?', '。', '！', '？');
        $best_pos = false;
        $best_distance = PHP_INT_MAX;

        foreach ($patterns as $pattern) {
            $pos = strrpos(substr($search_text, 0, $target - $search_start), $pattern);
            if ($pos !== false) {
                $abs_pos = $search_start + $pos + 1;
                $distance = abs($target - $abs_pos);
                if ($distance < $best_distance) {
                    $best_distance = $distance;
                    $best_pos = $abs_pos;
                }
            }
        }

        return $best_pos;
    }

    /**
     * Find word boundary near target position.
     *
     * @since    1.0.0
     * @param    string    $content    Content to search
     * @param    int       $target     Target position
     * @param    int       $min        Minimum position
     * @return   int|false             Position of word boundary or false
     */
    private function find_word_boundary($content, $target, $min) {
        // Look for space before target
        $pos = $target;
        while ($pos > $min && $content[$pos] !== ' ') {
            $pos--;
        }
        
        return $pos > $min ? $pos + 1 : false;
    }
}
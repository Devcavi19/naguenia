<?php

/**
 * The file that defines the API integration functionality
 *
 * A class definition that handles integration with Gemini and Qdrant services.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

/**
 * The API integration functionality.
 *
 * Handles communication with Gemini and Qdrant services for RAG implementation.
 *
 * @since      1.0.0
 * @package    Wpragbot
 * @author     Your Name <email@example.com>
 */
class Wpragbot_API {

    /**
     * AI Provider endpoints
     */
    private $embedding_endpoint = 'https://devcavi19-hf-all-minilm-l6-v2-wp-api.hf.space/embed';
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

            // 4. Generate response using selected AI provider with context
            $response = $this->generate_response(
                $message, 
                $context, 
                $settings['api_key'], 
                $settings['ai_provider'],
                $settings['system_prompt'] ?? '', 
                $settings['collection_name'] ?? ''
            );

            if (is_wp_error($response)) {
                error_log('WPRAGBot: Response generation failed: ' . $response->get_error_message());
                return $response;
            }
            
            error_log('WPRAGBot: Successfully generated response');

            // 5. Return the response
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
     * Generate embedding for text using selected AI provider.
     *
     * @since    1.0.0
     * @param    string    $text           Text to embed
     * @param    string    $api_key        API key for selected provider
     * @param    string    $provider       Selected AI provider
     * @param    string    $task_type      Task type (retrieval_document or retrieval_query)
     * @return   array|WP_Error            Embedding array or error
     */
    private function generate_embedding($text, $api_key, $provider = 'gemini', $task_type = 'retrieval_document') {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key is required');
        }

        if (empty($text)) {
            return new WP_Error('empty_text', 'Text to embed cannot be empty');
        }

        // Select endpoint based on provider
        $url = $this->embedding_endpoint; // Default to all-MiniLM-L6-v2
        $headers = array(
            'Content-Type' => 'application/json',
        );
        
        // Set up provider-specific endpoints and headers
        switch ($provider) {
            case 'gemini':
                $url = $this->gemini_embedding_endpoint . '?key=' . $api_key;
                break;
            case 'openrouter':
                $url = $this->openrouter_embedding_endpoint;
                $headers['Authorization'] = 'Bearer ' . $api_key;
                break;
            case 'mistral':
                $url = $this->mistral_embedding_endpoint;
                $headers['Authorization'] = 'Bearer ' . $api_key;
                break;
            case 'openai':
                $url = $this->openai_embedding_endpoint;
                $headers['Authorization'] = 'Bearer ' . $api_key;
                break;
            default:
                // Default to all-MiniLM-L6-v2
                break;
        }

        $body = array(
            'input' => $text,
            'model' => 'all-MiniLM-L6-v2' // For all-MiniLM-L6-v2
        );

        // Adjust body for different providers
        switch ($provider) {
            case 'gemini':
                $body = array(
                    'content' => array(
                        'parts' => array(
                            array('text' => $text)
                        )
                    ),
                    'task_type' => $task_type,
                    'output_dimensionality' => 768
                );
                break;
            case 'openrouter':
                $body = array(
                    'input' => $text,
                    'model' => 'all-MiniLM-L6-v2'
                );
                break;
            case 'mistral':
                $body = array(
                    'input' => $text,
                    'model' => 'all-MiniLM-L6-v2'
                );
                break;
            case 'openai':
                $body = array(
                    'input' => $text,
                    'model' => 'text-embedding-3-small'
                );
                break;
            default:
                // Default to all-MiniLM-L6-v2
                break;
        }

        $response = wp_remote_post($url, array(
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('embedding_request_failed', 'Failed to connect to AI provider: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            return new WP_Error('embedding_api_error', 'AI provider error: ' . $error_message, array('status' => $response_code));
        }

        $data = json_decode($response_body, true);

        // Handle different response formats
        switch ($provider) {
            case 'gemini':
                if (!isset($data['embedding']['values'])) {
                    return new WP_Error('invalid_response', 'Invalid response from AI provider');
                }
                return $data['embedding']['values'];
            case 'openrouter':
            case 'mistral':
            case 'openai':
            case 'all-minilm':
                if (!isset($data['data'][0]['embedding'])) {
                    return new WP_Error('invalid_response', 'Invalid response from AI provider');
                }
                return $data['data'][0]['embedding'];
            default:
                if (!isset($data['data'][0]['embedding'])) {
                    return new WP_Error('invalid_response', 'Invalid response from AI provider');
                }
                return $data['data'][0]['embedding'];
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
        if (empty($qdrant_url) || empty($collection_name)) {
            error_log('WPRAGBot: Missing Qdrant configuration - URL: ' . ($qdrant_url ? 'set' : 'empty') . ', Collection: ' . ($collection_name ? $collection_name : 'empty'));
            return new WP_Error('missing_qdrant_config', 'Qdrant URL and collection name are required');
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
            error_log('WPRAGBot: Using Qdrant API key: ' . (strlen($api_key) > 0 ? 'SET (length: ' . strlen($api_key) . ')' : 'EMPTY'));
        } else {
            error_log('WPRAGBot: No Qdrant API key provided - attempting unauthenticated request');
        }

        error_log('WPRAGBot: Request body: ' . wp_json_encode(array(
            'vector_dimension' => count($query_embedding),
            'limit' => 5,
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
     * Construct context from relevant documents.
     *
     * @since    1.0.0
     * @param    array    $documents    Relevant documents
     * @return   string                 Constructed context
     */
    private function construct_context($documents) {
        $context_parts = array();
        foreach ($documents as $doc) {
            $context_parts[] = $doc['content'];
        }
        return implode("\n\n", $context_parts);
    }

    /** 
     * Generate response using selected AI provider with context.
     *
     * @since    1.0.0
     * @param    string    $message        User message
     * @param    string    $context        Retrieved context
     * @param    string    $api_key        API key for selected provider
     * @param    string    $provider       Selected AI provider
     * @param    string    $system_prompt  System prompt
     * @param    string    $collection_name Collection name for variable replacement
     * @return   string|WP_Error           Generated response or error
     */
    private function generate_response($message, $context, $api_key, $provider, $system_prompt, $collection_name = '') {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key is required');
        }

        if (empty($message)) {
            return new WP_Error('empty_message', 'User message cannot be empty');
        }

        // Select endpoint based on provider
        $url = $this->gemini_generate_endpoint . '?key=' . $api_key; // Default to Gemini
        $headers = array(
            'Content-Type' => 'application/json',
        );
        
        // Set up provider-specific endpoints and headers
        switch ($provider) {
            case 'gemini':
                $url = $this->gemini_generate_endpoint . '?key=' . $api_key;
                break;
            case 'openrouter':
                $url = $this->openrouter_generate_endpoint;
                $headers['Authorization'] = 'Bearer ' . $api_key;
                $headers['HTTP-Referer'] = get_site_url();
                $headers['X-Title'] = 'WPRAGBot';
                break;
            case 'mistral':
                $url = $this->mistral_generate_endpoint;
                $headers['Authorization'] = 'Bearer ' . $api_key;
                break;
            case 'openai':
                $url = $this->openai_generate_endpoint;
                $headers['Authorization'] = 'Bearer ' . $api_key;
                break;
            default:
                // Default to Gemini
                $url = $this->gemini_generate_endpoint . '?key=' . $api_key;
                break;
        }

        // Construct the prompt with context
        $default_system_prompt = 'You are a helpful AI assistant. Use the provided context to answer questions accurately and COMPLETELY. Always finish your sentences and provide thorough, complete answers. Never cut off your response mid-sentence. If the context doesn\'t contain relevant information, say so clearly. IMPORTANT: Format all responses using proper Markdown syntax: use **bold** for emphasis, bullet points with - or *, numbered lists with 1. 2. 3., and `code` for technical terms. Always structure information with clear headers and bullet points for easy reading.';
        $system_prompt = !empty($system_prompt) ? $system_prompt : $default_system_prompt;

        // Replace variables in system prompt
        if (!empty($collection_name)) {
            $system_prompt = str_replace('{$collection_name}', $collection_name, $system_prompt);
        }

        $full_prompt = $system_prompt . "\n\n";
        
        if (!empty($context)) {
            $full_prompt .= "Context:\n" . $context . "\n\n";
        } else {
            // Log when context is empty for debugging
            error_log('WPRAGBot: No context retrieved from Qdrant for query: ' . substr($message, 0, 50));
        }
        
        $full_prompt .= "User Question: " . $message . "\n\nAssistant:";

        // Prepare body for different providers
        $body = array();
        
        switch ($provider) {
            case 'gemini':
                $body = array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array('text' => $full_prompt)
                            )
                        )
                    ),
                    'generationConfig' => array(
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 8192,
                        'stopSequences' => array(),
                    ),
                    'safetySettings' => array(
                        array(
                            'category' => 'HARM_CATEGORY_HARASSMENT',
                            'threshold' => 'BLOCK_NONE'
                        ),
                        array(
                            'category' => 'HARM_CATEGORY_HATE_SPEECH',
                            'threshold' => 'BLOCK_NONE'
                        ),
                        array(
                            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                            'threshold' => 'BLOCK_NONE'
                        ),
                        array(
                            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                            'threshold' => 'BLOCK_NONE'
                        )
                    ),
                );
                break;
            case 'openrouter':
                $body = array(
                    'model' => 'google/gemini-1.5-flash',
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => $system_prompt
                        ),
                        array(
                            'role' => 'user',
                            'content' => $full_prompt
                        )
                    ),
                    'temperature' => 0.7,
                    'max_tokens' => 8192,
                );
                break;
            case 'mistral':
                $body = array(
                    'model' => 'mistral-small-4-0-26-03',
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => $system_prompt
                        ),
                        array(
                            'role' => 'user',
                            'content' => $full_prompt
                        )
                    ),
                    'temperature' => 0.7,
                    'max_tokens' => 8192,
                    'reasoning_effort' => 'high',
                );
                break;
            case 'openai':
                $body = array(
                    'model' => 'gpt-3.5-turbo',
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => $system_prompt
                        ),
                        array(
                            'role' => 'user',
                            'content' => $full_prompt
                        )
                    ),
                    'temperature' => 0.7,
                    'max_tokens' => 8192,
                );
                break;
            default:
                // Default to Gemini
                $body = array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array('text' => $full_prompt)
                            )
                        )
                    ),
                    'generationConfig' => array(
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 8192,
                        'stopSequences' => array(),
                    ),
                    'safetySettings' => array(
                        array(
                            'category' => 'HARM_CATEGORY_HARASSMENT',
                            'threshold' => 'BLOCK_NONE'
                        ),
                        array(
                            'category' => 'HARM_CATEGORY_HATE_SPEECH',
                            'threshold' => 'BLOCK_NONE'
                        ),
                        array(
                            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                            'threshold' => 'BLOCK_NONE'
                        ),
                        array(
                            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                            'threshold' => 'BLOCK_NONE'
                        )
                    ),
                );
                break;
        }

        $response = wp_remote_post($url, array(
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            error_log('WPRAGBot: Failed to connect to AI provider: ' . $response->get_error_message());
            return new WP_Error('generation_request_failed', 'Failed to connect to AI provider: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('WPRAGBot: AI provider response code: ' . $response_code);
        error_log('WPRAGBot: Response body length: ' . strlen($response_body) . ' bytes');

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            error_log('WPRAGBot: AI provider error: ' . $error_message);
            return new WP_Error('generation_api_error', 'AI provider error: ' . $error_message, array('status' => $response_code));
        }

        $data = json_decode($response_body, true);

        // Handle different response formats
        switch ($provider) {
            case 'gemini':
                if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    error_log('WPRAGBot: Invalid response structure from AI provider');
                    error_log('WPRAGBot: Response data: ' . print_r($data, true));
                    
                    // Check for finish reason that might indicate truncation
                    if (isset($data['candidates'][0]['finishReason'])) {
                        error_log('WPRAGBot: Finish reason: ' . $data['candidates'][0]['finishReason']);
                    }
                    
                    return new WP_Error('invalid_generation_response', 'Invalid response from AI provider');
                }

                $generated_text = $data['candidates'][0]['content']['parts'][0]['text'];
                $finish_reason = isset($data['candidates'][0]['finishReason']) ? $data['candidates'][0]['finishReason'] : 'UNKNOWN';
                
                error_log('WPRAGBot: Generated response length: ' . strlen($generated_text) . ' characters');
                error_log('WPRAGBot: Finish reason: ' . $finish_reason);
                
                // Check if response was truncated
                if ($finish_reason === 'MAX_TOKENS') {
                    error_log('WPRAGBot: WARNING - Response may be truncated due to token limit');
                }

                return $generated_text;
            case 'openrouter':
            case 'mistral':
            case 'openai':
                if (!isset($data['choices'][0]['message']['content'])) {
                    return new WP_Error('invalid_generation_response', 'Invalid response from AI provider');
                }
                return $data['choices'][0]['message']['content'];
            default:
                if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    error_log('WPRAGBot: Invalid response structure from AI provider');
                    error_log('WPRAGBot: Response data: ' . print_r($data, true));
                    
                    // Check for finish reason that might indicate truncation
                    if (isset($data['candidates'][0]['finishReason'])) {
                        error_log('WPRAGBot: Finish reason: ' . $data['candidates'][0]['finishReason']);
                    }
                    
                    return new WP_Error('invalid_generation_response', 'Invalid response from AI provider');
                }

                $generated_text = $data['candidates'][0]['content']['parts'][0]['text'];
                $finish_reason = isset($data['candidates'][0]['finishReason']) ? $data['candidates'][0]['finishReason'] : 'UNKNOWN';
                
                error_log('WPRAGBot: Generated response length: ' . strlen($generated_text) . ' characters');
                error_log('WPRAGBot: Finish reason: ' . $finish_reason);
                
                // Check if response was truncated
                if ($finish_reason === 'MAX_TOKENS') {
                    error_log('WPRAGBot: WARNING - Response may be truncated due to token limit');
                }

                return $generated_text;
        }
    }

    /**
     * Process document for knowledge base.
     *
     * @since    1.0.0
     * @param    string    $content        Document content
     * @param    array     $settings       Plugin settings
     * @return   array|WP_Error            Processed document data or error
     */
    public function process_document($content, $settings) {
        try {
            // Split content into chunks
            $chunks = $this->chunk_content($content);

            if (empty($chunks)) {
                return new WP_Error('empty_content', 'Document content is empty');
            }

            // Generate embeddings for each chunk and upload to Qdrant
            $points = array();
            $chunk_id = 1;

            foreach ($chunks as $chunk) {
                // Generate embedding
                $embedding = $this->generate_embedding($chunk, $settings['gemini_api_key'], 'retrieval_document');
                
                if (is_wp_error($embedding)) {
                    error_log('WPRAGBot: Failed to generate embedding for chunk ' . $chunk_id . ': ' . $embedding->get_error_message());
                    continue;
                }

                // Prepare point for Qdrant
                $points[] = array(
                    'id' => uniqid('doc_' . time() . '_'),
                    'vector' => $embedding,
                    'payload' => array(
                        'content' => $chunk,
                        'chunk_id' => $chunk_id,
                        'timestamp' => current_time('mysql'),
                    )
                );

                $chunk_id++;
            }

            if (empty($points)) {
                return new WP_Error('no_embeddings', 'Failed to generate embeddings for document');
            }

            // Upload points to Qdrant
            $upload_result = $this->upload_to_qdrant(
                $points,
                $settings['qdrant_url'],
                $settings['collection_name'],
                $settings['qdrant_api_key']
            );

            if (is_wp_error($upload_result)) {
                return $upload_result;
            }

            return array(
                'success' => true,
                'chunks' => count($chunks),
                'embeddings' => count($points),
                'uploaded' => $upload_result['uploaded'],
            );

        } catch (Exception $e) {
            return new WP_Error('document_error', 'Error processing document: ' . $e->getMessage());
        }
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
            if ($start <= $chunks[count($chunks) - 1]) {
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
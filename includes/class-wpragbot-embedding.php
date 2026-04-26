<?php
defined( 'ABSPATH' ) || exit;

/**
 * The file that defines the embedding functionality for Hugging Face Ollama All-MiniLM L6 v2
 *
 * A class definition that handles embedding generation using the Hugging Face Ollama All-MiniLM L6 v2 model.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

/**
 * The embedding functionality for Hugging Face Ollama All-MiniLM L6 v2.
 *
 * Handles communication with the Hugging Face Ollama All-MiniLM L6 v2 embedding API.
 *
 * @since      1.0.0
 * @package    Wpragbot
 * @author     Your Name <email@example.com>
 */
class Wpragbot_Embedding {

    /**
     * Embedding API endpoint
     */
    private $embedding_endpoint = 'https://devcavi19-hf-all-minilm-l6-v2-wp-api.hf.space/embed';

    /**
     * Batch embedding API endpoint
     */
    private $batch_embedding_endpoint = 'https://devcavi19-hf-all-minilm-l6-v2-wp-api.hf.space/embed/batch';

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
     * Generate embedding for a single text.
     *
     * @since    1.0.0
     * @param    string    $text           Text to embed
     * @return   array|WP_Error            Embedding array or error
     */
    public function generate_embedding($text) {
        if (empty($text)) {
            return new WP_Error('empty_text', 'Text to embed cannot be empty');
        }

        $url = $this->embedding_endpoint;
        $body = array('text' => $text);
        $headers = array('Content-Type' => 'application/json');

        $response = wp_remote_post($url, array(
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('embedding_request_failed', 'Failed to connect to embedding API: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new WP_Error('embedding_api_error', 'Embedding API error: ' . $response_body, array('status' => $response_code));
        }

        $data = json_decode($response_body, true);

        if (!isset($data['embedding']) || !is_array($data['embedding'])) {
            return new WP_Error('invalid_response', 'Invalid response from embedding API');
        }

        return $data['embedding'];
    }

    /**
     * Generate embeddings for multiple texts.
     *
     * @since    1.0.0
     * @param    array     $texts          Array of texts to embed
     * @return   array|WP_Error            Array of embeddings or error
     */
    public function generate_batch_embeddings($texts) {
        if (empty($texts) || !is_array($texts)) {
            return new WP_Error('empty_texts', 'Texts array cannot be empty');
        }

        $url = $this->batch_embedding_endpoint;
        $body = array('texts' => $texts);
        $headers = array('Content-Type' => 'application/json');

        $response = wp_remote_post($url, array(
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('embedding_request_failed', 'Failed to connect to embedding API: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new WP_Error('embedding_api_error', 'Embedding API error: ' . $response_body, array('status' => $response_code));
        }

        $data = json_decode($response_body, true);

        if (!isset($data['embeddings']) || !is_array($data['embeddings'])) {
            return new WP_Error('invalid_response', 'Invalid response from embedding API');
        }

        return $data['embeddings'];
    }

    /**
     * Health check for the embedding API.
     *
     * @since    1.0.0
     * @return   bool|WP_Error             True if healthy, WP_Error on failure
     */
    public function health_check() {
        $url = 'https://devcavi19-hf-all-minilm-l6-v2-wp-api.hf.space/health';

        $response = wp_remote_get($url, array(
            'timeout' => $this->timeout,
            'user-agent' => 'WordPress Plugin WPRAGBot',
        ));

        if (is_wp_error($response)) {
            return new WP_Error('health_check_failed', 'Failed to connect to embedding API: ' . $response->get_error_message());
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }
}
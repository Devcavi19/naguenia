<?php
defined( 'ABSPATH' ) || exit;

/**
 * The file that defines the admin-specific functionality
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

/**
 * The admin-specific functionality.
 *
 * Defines the plugin name, version, and two examples of how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @since      1.0.0
 * @package    Wpragbot
 * @author     Your Name <email@example.com>
 */
class Wpragbot_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wpragbot-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wpragbot-admin.js', array( 'jquery' ), $this->version, false );
        
        // Localize script with admin data
        wp_localize_script( $this->plugin_name, 'wpragbot_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wpragbot_admin_nonce' )
        ));
    }

    /**
     * Add plugin menu page.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_options_page(
            'WPRAGBot Settings',
            'WPRAGBot',
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_plugin_settings_page' )
        );
    }

    /**
     * Initialize plugin settings.
     *
     * @since    1.0.0
     */
    public function settings_init() {
        // Register settings with proper sanitization callback
        register_setting( 
            $this->plugin_name, 
            'wpragbot_settings',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'show_in_rest'      => false
            )
        );

        // Add settings sections
        add_settings_section(
            'wpragbot_api_settings',
            'API Settings',
            array( $this, 'api_settings_section_callback' ),
            $this->plugin_name
        );

        add_settings_section(
            'wpragbot_chat_settings',
            'Chat Settings',
            array( $this, 'chat_settings_section_callback' ),
            $this->plugin_name
        );

        // Add settings fields
        add_settings_field(
            'wpragbot_ai_provider',
            'AI Provider',
            array( $this, 'ai_provider_render' ),
            $this->plugin_name,
            'wpragbot_api_settings'
        );

        add_settings_field(
            'wpragbot_api_key',
            'API Key',
            array( $this, 'api_key_render' ),
            $this->plugin_name,
            'wpragbot_api_settings'
        );

        add_settings_field(
            'wpragbot_supabase_url',
            'Supabase URL',
            array( $this, 'supabase_url_render' ),
            $this->plugin_name,
            'wpragbot_api_settings'
        );

        add_settings_field(
            'wpragbot_supabase_key',
            'Supabase Key',
            array( $this, 'supabase_key_render' ),
            $this->plugin_name,
            'wpragbot_api_settings'
        );

        add_settings_field(
            'wpragbot_qdrant_url',
            'Qdrant URL',
            array( $this, 'qdrant_url_render' ),
            $this->plugin_name,
            'wpragbot_api_settings'
        );

        add_settings_field(
            'wpragbot_qdrant_api_key',
            'Qdrant API Key',
            array( $this, 'qdrant_api_key_render' ),
            $this->plugin_name,
            'wpragbot_api_settings'
        );

        add_settings_field(
            'wpragbot_system_prompt',
            'System Prompt',
            array( $this, 'system_prompt_render' ),
            $this->plugin_name,
            'wpragbot_chat_settings'
        );

        add_settings_field(
            'wpragbot_collection_name',
            'Qdrant Collection Name',
            array( $this, 'collection_name_render' ),
            $this->plugin_name,
            'wpragbot_api_settings'
        );

        add_settings_field(
            'wpragbot_embedding_endpoint',
            'Embedding API Endpoint',
            array( $this, 'embedding_endpoint_render' ),
            $this->plugin_name,
            'wpragbot_api_settings'
        );

        add_settings_field(
            'wpragbot_embedding_batch_endpoint',
            'Embedding Batch API Endpoint',
            array( $this, 'embedding_batch_endpoint_render' ),
            $this->plugin_name,
            'wpragbot_api_settings'
        );
    }

    /**
     * Sanitize and validate plugin settings.
     *
     * @since    1.0.0
     * @param    array    $input    The settings array from the form
     * @return   array             Sanitized settings array
     */
    public function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }

        $sanitized = array();

        // Sanitize each field appropriately
        if ( isset( $input['ai_provider'] ) ) {
            $sanitized['ai_provider'] = sanitize_text_field( $input['ai_provider'] );
        }

        if ( isset( $input['api_key'] ) ) {
            $sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
        }

        // FIX: supabase_url and supabase_key were previously missing — they are now saved correctly.
        if ( isset( $input['supabase_url'] ) ) {
            $sanitized['supabase_url'] = esc_url_raw( $input['supabase_url'] );
        }

        if ( isset( $input['supabase_key'] ) ) {
            $sanitized['supabase_key'] = sanitize_text_field( $input['supabase_key'] );
        }

        // Use esc_url_raw (not esc_url) so REST API URLs with query-string chars are preserved.
        if ( isset( $input['qdrant_url'] ) ) {
            $sanitized['qdrant_url'] = esc_url_raw( $input['qdrant_url'] );
        }

        if ( isset( $input['qdrant_api_key'] ) ) {
            $sanitized['qdrant_api_key'] = sanitize_text_field( $input['qdrant_api_key'] );
        }

        if ( isset( $input['collection_name'] ) ) {
            $sanitized['collection_name'] = sanitize_text_field( $input['collection_name'] );
        }

        // Important: Use wp_kses_post for system_prompt to allow formatting but remove harmful tags
        if ( isset( $input['system_prompt'] ) ) {
            $sanitized['system_prompt'] = wp_kses_post( $input['system_prompt'] );
            error_log('WPRAGBot Admin: System prompt updated - Length: ' . strlen( $sanitized['system_prompt'] ) . ' characters');
        }

        if ( isset( $input['embedding_endpoint'] ) ) {
            $sanitized['embedding_endpoint'] = esc_url_raw( $input['embedding_endpoint'] );
        }

        if ( isset( $input['embedding_batch_endpoint'] ) ) {
            $sanitized['embedding_batch_endpoint'] = esc_url_raw( $input['embedding_batch_endpoint'] );
        }

        return $sanitized;
    }

    /**
     * Display the plugin settings page.
     *
     * @since    1.0.0
     */
    public function display_plugin_settings_page() {
        // Handle analytics export if requested
        if (isset($_GET['action']) && $_GET['action'] === 'export_analytics' && current_user_can('manage_options')) {
            if ( isset( $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'wpragbot_export_analytics' ) ) {
                $this->handle_analytics_export();
            } else {
                wp_die( esc_html__( 'Security check failed.', 'wpragbot' ) );
            }
            return;
        }

        include_once 'partials/wpragbot-admin-display.php';
    }

    /**
     * Handle analytics data export.
     *
     * @since    1.0.0
     */
    private function handle_analytics_export() {
        $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;

        $analytics = new Wpragbot_Analytics();
        $data = $analytics->export_data($format, $days);

        if (is_wp_error($data)) {
            wp_die($data->get_error_message());
        }

        // Set appropriate headers
        $filename = 'wpragbot-analytics-' . date('Y-m-d') . '.' . $format;

        if ($format === 'json') {
            header('Content-Type: application/json');
        } else {
            header('Content-Type: text/csv');
        }

        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        echo $data;
        exit;
    }

    /**
     * Section callback functions
     */
    public function api_settings_section_callback() {
        echo '<p>Configure your API keys and connection settings.</p>';
    }

    public function chat_settings_section_callback() {
        echo '<p>Configure chatbot behavior and responses.</p>';
    }

    public function knowledge_base_settings_section_callback() {
        echo '<p>Manage your knowledge base documents.</p>';
    }

    /**
     * Field render functions
     */
    public function ai_provider_render() {
        $options = get_option( 'wpragbot_settings' );
        $provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'gemini';
        echo '<select name="wpragbot_settings[ai_provider]" class="regular-text">';
        echo '<option value="gemini"' . selected( $provider, 'gemini', false ) . '>Gemini</option>';
        echo '<option value="openrouter"' . selected( $provider, 'openrouter', false ) . '>OpenRouter</option>';
        echo '<option value="mistral"' . selected( $provider, 'mistral', false ) . '>Mistral</option>';
        echo '<option value="openai"' . selected( $provider, 'openai', false ) . '>OpenAI</option>';
        echo '</select>';
        echo '<p class="description">Select the AI provider to use for responses</p>';
    }

    public function api_key_render() {
        $options = get_option( 'wpragbot_settings' );
        $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        echo '<input type="password" name="wpragbot_settings[api_key]" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
    }

    public function supabase_url_render() {
        $options = get_option( 'wpragbot_settings' );
        $url = isset( $options['supabase_url'] ) ? $options['supabase_url'] : '';
        echo '<input type="url" name="wpragbot_settings[supabase_url]" value="' . esc_attr( $url ) . '" class="regular-text" />';
    }

    public function supabase_key_render() {
        $options = get_option( 'wpragbot_settings' );
        $key = isset( $options['supabase_key'] ) ? $options['supabase_key'] : '';
        echo '<input type="password" name="wpragbot_settings[supabase_key]" value="' . esc_attr( $key ) . '" class="regular-text" />';
    }

    public function qdrant_url_render() {
        $options = get_option( 'wpragbot_settings' );
        $url = isset( $options['qdrant_url'] ) ? $options['qdrant_url'] : '';
        echo '<input type="url" name="wpragbot_settings[qdrant_url]" value="' . esc_attr( $url ) . '" class="regular-text" />';
    }

    public function qdrant_api_key_render() {
        $options = get_option( 'wpragbot_settings' );
        $api_key = isset( $options['qdrant_api_key'] ) ? $options['qdrant_api_key'] : '';
        echo '<input type="password" name="wpragbot_settings[qdrant_api_key]" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
    }

    public function system_prompt_render() {
        $options = get_option( 'wpragbot_settings' );
        $prompt = isset( $options['system_prompt'] ) ? $options['system_prompt'] : '';
        echo '<textarea name="wpragbot_settings[system_prompt]" rows="5" cols="50" class="large-text">' . esc_textarea( $prompt ) . '</textarea>';
    }

    public function collection_name_render() {
        $options = get_option( 'wpragbot_settings' );
        $collection = isset( $options['collection_name'] ) ? $options['collection_name'] : '';
        echo '<input type="text" name="wpragbot_settings[collection_name]" value="' . esc_attr( $collection ) . '" class="regular-text" />';
    }

    public function embedding_endpoint_render() {
        $options = get_option( 'wpragbot_settings' );
        $endpoint = isset( $options['embedding_endpoint'] ) ? $options['embedding_endpoint'] : '';
        echo '<input type="url" name="wpragbot_settings[embedding_endpoint]" value="' . esc_attr( $endpoint ) . '" class="regular-text" />';
        echo '<p class="description">The API endpoint for single text embeddings. Example: https://your-embedding-api.com/embed</p>';
    }

    public function embedding_batch_endpoint_render() {
        $options = get_option( 'wpragbot_settings' );
        $endpoint = isset( $options['embedding_batch_endpoint'] ) ? $options['embedding_batch_endpoint'] : '';
        echo '<input type="url" name="wpragbot_settings[embedding_batch_endpoint]" value="' . esc_attr( $endpoint ) . '" class="regular-text" />';
        echo '<p class="description">The API endpoint for batch text embeddings. Example: https://your-embedding-api.com/embed/batch</p>';
    }

    /**
     * Handle document upload via AJAX.
     *
     * @since    1.0.0
     */
    public function handle_document_upload() {
        wp_send_json_error( array( 'message' => 'Document upload functionality is disabled in this version.' ) );
        return;

        // Validate file type
        $allowed_types = array( 'text/plain', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/markdown' );
        $file_type = wp_check_filetype( $file['name'] );
        
        if ( !in_array( $file['type'], $allowed_types ) ) {
            wp_send_json_error( array( 'message' => 'Invalid file type. Only TXT, PDF, DOC, DOCX, and MD files are allowed.' ) );
            return;
        }

        // Validate file size (max 10MB)
        if ( $file['size'] > 10 * 1024 * 1024 ) {
            wp_send_json_error( array( 'message' => 'File too large. Maximum size is 10MB.' ) );
            return;
        }

        // Use WordPress upload handler
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $upload_overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $file, $upload_overrides );

        if ( $movefile && !isset( $movefile['error'] ) ) {
            // File uploaded successfully
            $file_path = $movefile['file'];
            $file_url = $movefile['url'];

            // Read file content
            $content = file_get_contents( $file_path );
            
            if ( $content === false ) {
                wp_send_json_error( array( 'message' => 'Failed to read file content' ) );
                return;
            }

            // Get plugin settings
            $settings = get_option( 'wpragbot_settings' );

            // Validate API settings
            if ( empty( $settings['api_key'] ) || empty( $settings['qdrant_url'] ) || empty( $settings['collection_name'] ) ) {
                wp_send_json_error( array( 'message' => 'API settings not configured. Please configure API keys first.' ) );
                return;
            }

            // Process document through API
            // Note: Document upload functionality has been removed. Documents must be pre-processed and stored in Qdrant.
            wp_send_json_error( array( 'message' => 'Document upload functionality has been removed. Documents must be pre-processed and stored in Qdrant.' ) );
            return;

        } else {
            wp_send_json_error( array( 'message' => $movefile['error'] ) );
        }
    }
}
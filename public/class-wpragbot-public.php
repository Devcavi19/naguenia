<?php

/**
 * The file that defines the public-facing functionality
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

/**
 * The public-facing functionality.
 *
 * Defines the plugin name, version, and two examples of how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @since      1.0.0
 * @package    Wpragbot
 * @author     Your Name <email@example.com>
 */
class Wpragbot_Public {

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
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version           The version of the plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wpragbot-public.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wpragbot-public.js', array( 'jquery' ), $this->version, false );

        // Localize script for AJAX
        wp_localize_script( $this->plugin_name, 'wpragbot_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wpragbot_nonce' )
        ));
    }

    /**
     * Handle chat requests via AJAX.
     *
     * @since    1.0.0
     */
    public function handle_chat() {
        // Check if POST data exists
        if ( !isset( $_POST['nonce'] ) || !isset( $_POST['message'] ) || !isset( $_POST['session_id'] ) ) {
            wp_send_json_error( 'Missing required parameters' );
            return;
        }

        // Verify nonce
        if ( !wp_verify_nonce( $_POST['nonce'], 'wpragbot_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
            return;
        }

        // Get user message
        $message = sanitize_textarea_field( $_POST['message'] );
        $session_id = sanitize_text_field( $_POST['session_id'] );

        // Validate message is not empty
        if ( empty( trim( $message ) ) ) {
            wp_send_json_error( 'Message cannot be empty' );
            return;
        }

        // Get plugin settings
        $settings = get_option('wpragbot_settings');

        // Validate settings
        if (empty($settings['api_key']) || empty($settings['qdrant_url']) || empty($settings['collection_name'])) {
            wp_send_json_error('Missing required API settings');
            return;
        }

        // Process chat request through API
        $api_handler = new Wpragbot_API();
        $response = $api_handler->process_chat_request($message, $session_id, $settings);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            // Track the interaction in analytics
            $analytics = new Wpragbot_Analytics();
            $analytics->track_chat_interaction(
                $session_id,
                $message,
                $response['response'],
                isset($response['context']) ? $response['context'] : ''
            );

            // Sanitize response before sending to frontend
            $response['response'] = wp_kses_post($response['response']);
            wp_send_json_success($response);
        }
    }

    /**
     * Display the chat widget in the footer.
     *
     * @since    1.0.0
     */
    public function display_chat_widget() {
        // Get plugin settings
        $settings = get_option('wpragbot_settings');

        // Only display if we have API keys configured
        if (empty($settings['api_key']) || empty($settings['qdrant_url']) || empty($settings['collection_name'])) {
            return;
        }

        include_once 'partials/wpragbot-public-display.php';
    }

    /**
     * Display chatbot via shortcode.
     *
     * @since    11.0.0
     * @param    array     $atts    Shortcode attributes
     * @return   string             HTML output
     */
    public function display_chatbot_shortcode($atts = array()) {
        // Get plugin settings
        $settings = get_option('wpragbot_settings');

        // Check if configured
        if (empty($settings['api_key']) || empty($settings['qdrant_url']) || empty($settings['collection_name'])) {
            return '<div class="wpragbot-notice" style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; margin: 20px 0;">
                        <strong>WPRAGBot:</strong> Please configure API settings in WordPress Admin → Settings → WPRAGBot
                    </div>';
        }

        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'title' => 'AI Assistant',
            'height' => '500px',
            'width' => '100%',
        ), $atts, 'wpragbot');

        // Start output buffering
        ob_start();
        ?>
        <div class="wpragbot-shortcode-container" style="max-width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>; border: 1px solid #ddd; border-radius: 10px; overflow: hidden;">
            <div class="wpragbot-chat-header" style="background: #4a6fa5; color: white; padding: 15px;">
                <span class="wpragbot-chat-title" style="font-weight: bold; font-size: 16px;"><?php echo esc_html($atts['title']); ?></span>
            </div>
            <div class="wpragbot-chat-messages" id="wpragbot-chat-messages-shortcode" style="height: calc(100% - 110px); padding: 15px; overflow-y: auto; background: #f9f9f9;">
                <div class="wpragbot-message wpragbot-bot-message" style="background: #e9ecef; color: #333; padding: 10px 15px; border-radius: 18px; max-width: 80%; margin-bottom: 15px;">
                    Hello! How can I help you today?
                </div>
            </div>
            <div class="wpragbot-chat-input-area" style="display: flex; padding: 10px; border-top: 1px solid #ddd; background: #fff;">
                <input type="text" id="wpragbot-user-input-shortcode" placeholder="Type your message here..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 20px; outline: none; font-size: 14px;" />
                <button id="wpragbot-send-button-shortcode" style="margin-left: 10px; padding: 10px 20px; border: none; border-radius: 20px; background: #4a6fa5; color: white; cursor: pointer; font-size: 14px;">Send</button>
            </div>
        </div>
        <script>
        (function($) {
            var shortcodeChat = {
                init: function() {
                    $('#wpragbot-send-button-shortcode').on('click', function() {
                        shortcodeChat.sendMessage();
                    });
                    $('#wpragbot-user-input-shortcode').on('keypress', function(e) {
                        if (e.which === 13) {
                            shortcodeChat.sendMessage();
                        }
                    });
                },
                sendMessage: function() {
                    var message = $('#wpragbot-user-input-shortcode').val().trim();
                    var sessionId = shortcodeChat.getSessionId();
                    
                    if (message === '') return;
                    
                    shortcodeChat.addMessage(message, 'user');
                    $('#wpragbot-user-input-shortcode').val('');
                    shortcodeChat.addLoadingIndicator();
                    
                    $.ajax({
                        url: wpragbot_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wpragbot_chat',
                            message: message,
                            session_id: sessionId,
                            nonce: wpragbot_ajax.nonce
                        },
                        success: function(response) {
                            $('.wpragbot-loading').parent().remove();
                            if (response.success) {
                                shortcodeChat.addMessage(response.data.response, 'bot');
                            } else {
                                shortcodeChat.addMessage('Error: ' + response.data, 'bot');
                            }
                        },
                        error: function() {
                            $('.wpragbot-loading').parent().remove();
                            shortcodeChat.addMessage('Error: Failed to get response', 'bot');
                        }
                    });
                },
                addMessage: function(message, type) {
                    var messageClass = (type === 'user') ? 'wpragbot-user-message' : 'wpragbot-bot-message';
                    var messageStyle = (type === 'user') ? 'background: #4a6fa5; color: white; margin-left: auto; text-align: right;' : 'background: #e9ecef; color: #333;';
                    var messageHtml = '<div class="wpragbot-message ' + messageClass + '" style="' + messageStyle + ' padding: 10px 15px; border-radius: 18px; max-width: 80%; word-wrap: break-word; margin-bottom: 15px;">' + message + '</div>';
                    $('#wpragbot-chat-messages-shortcode').append(messageHtml);
                    $('#wpragbot-chat-messages-shortcode').scrollTop($('#wpragbot-chat-messages-shortcode')[0].scrollHeight);
                },
                addLoadingIndicator: function() {
                    var loadingHtml = '<div class="wpragbot-message wpragbot-bot-message" style="background: #e9ecef; color: #333; padding: 10px 15px; border-radius: 18px; max-width: 80%; margin-bottom: 15px;"><span class="wpragbot-loading" style="display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #4a6fa5; border-radius: 50%; animation: spin 1s linear infinite;"></span> Thinking...</div>';
                    $('#wpragbot-chat-messages-shortcode').append(loadingHtml);
                    $('#wpragbot-chat-messages-shortcode').scrollTop($('#wpragbot-chat-messages-shortcode')[0].scrollHeight);
                },
                getSessionId: function() {
                    // Generate or retrieve a valid UUID (matches wpragbot-public.js logic)
                    var sessionId = localStorage.getItem('wpragbot_session_id');
                    var uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
                    if (!sessionId || !uuidRegex.test(sessionId)) {
                        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
                            sessionId = crypto.randomUUID();
                        } else {
                            sessionId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                                return v.toString(16);
                            });
                        }
                        localStorage.setItem('wpragbot_session_id', sessionId);
                    }
                    return sessionId;
                }
            };
            $(document).ready(function() {
                shortcodeChat.init();
            });
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }
}
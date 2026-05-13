<?php
defined( 'ABSPATH' ) || exit;

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
        $css_file = plugin_dir_path( __FILE__ ) . 'css/wpragbot-public.css';
        $version = file_exists( $css_file ) ? filemtime( $css_file ) : $this->version;
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wpragbot-public.css', array(), $version, 'all' );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        $js_file = plugin_dir_path( __FILE__ ) . 'js/wpragbot-public.js';
        $version = file_exists( $js_file ) ? filemtime( $js_file ) : $this->version;
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wpragbot-public.js', array( 'jquery' ), $version, false );

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
        // ✅ SECURITY: Check nonce first
        if ( !isset( $_POST['nonce'] ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed - no nonce' ) );
            return;
        }

        if ( !wp_verify_nonce( $_POST['nonce'], 'wpragbot_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed - invalid nonce' ) );
            return;
        }

        // ✅ SECURITY: Require authentication for chat access
        // To allow unauthenticated (guest) access, add this filter in wp-config.php or functions.php:
        // add_filter( 'wpragbot_allow_guest_chat', '__return_true' );
        // OR enable "Allow Guest Chat" setting in WPRAGBot admin panel
        $settings = get_option('wpragbot_settings');
        $allow_guest = ( isset( $settings['allow_guest_chat'] ) && $settings['allow_guest_chat'] ) || apply_filters( 'wpragbot_allow_guest_chat', false );
        
        if ( ! is_user_logged_in() && ! $allow_guest ) {
            wp_send_json_error( array( 'message' => 'You must be logged in to use the chat. Please log in to your WordPress account.' ) );
            return;
        }

        // --- Enhanced Rate Limiting (per-user + per-IP, 10 requests per minute max) ---
        $ip              = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $user_id         = get_current_user_id();
        $rate_limit_key  = $user_id ? 'wpragbot_rate_user_' . $user_id : 'wpragbot_rate_ip_' . md5( $ip );
        $request_count   = (int) get_transient( $rate_limit_key );

        // Allow 10 requests per minute (stricter than before)
        if ( $request_count >= 10 ) {
            wp_send_json_error( array( 'message' => 'Rate limit exceeded. Please wait a moment before sending another message.' ) );
            return;
        }

        set_transient( $rate_limit_key, $request_count + 1, MINUTE_IN_SECONDS );
        // --- End Enhanced Rate Limiting ---

        // Check if POST data exists
        if ( !isset( $_POST['message'] ) || !isset( $_POST['session_id'] ) ) {
            wp_send_json_error( 'Missing required parameters' );
            return;
        }

        // Get user message
        $message = sanitize_textarea_field( $_POST['message'] );
        $session_id = sanitize_text_field( $_POST['session_id'] );

        // ✅ SECURITY: Validate session ID format (UUID v4 or alphanumeric)
        if ( ! preg_match( '/^[a-zA-Z0-9\-]{8,36}$/', $session_id ) ) {
            wp_send_json_error( array( 'message' => 'Invalid session.' ) );
            return;
        }

        // Validate message is not empty
        if ( empty( trim( $message ) ) ) {
            wp_send_json_error( array( 'message' => 'Message cannot be empty' ) );
            return;
        }

        // ✅ SECURITY: Enforce maximum message length (5000 chars = ~1250 words)
        if ( strlen( $message ) > 5000 ) {
            wp_send_json_error( array( 'message' => 'Message is too long. Maximum length is 5000 characters.' ) );
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
            wpragbot_debug_log( 'WPRAGBot: Chat handler error: ' . $response->get_error_message() );
            wp_send_json_error( 'Unable to generate a response at this time. Please try again later.' );
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
            // Never send raw retrieval context to the browser
            unset($response['context']);
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
     * @since    1.0.0
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
            // In-memory session ID for shortcode — fresh on every page load.
            var wpragbotShortcodeSessionId = null;

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
                                var errorMsg = response.data && response.data.message ? response.data.message : 'Error: ' + (typeof response.data === 'string' ? response.data : 'Failed to process your message');
                                shortcodeChat.addMessage(errorMsg, 'bot');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            $('.wpragbot-loading').parent().remove();
                            var errorMsg = 'Error: Failed to get response. Please try again.';
                            var statusCode = jqXHR.status;
                            
                            if (statusCode === 0 || textStatus === 'error') {
                                errorMsg = 'Network error. Please check your internet connection.';
                            } else if (statusCode === 401 || statusCode === 403) {
                                errorMsg = 'Authentication required. Please log in to use the chat.';
                            } else if (statusCode === 500) {
                                errorMsg = 'Server error. Please try again later.';
                            }
                            
                            shortcodeChat.addMessage(errorMsg, 'bot');
                        }
                    });
                },
                addMessage: function(message, type) {
                    var messageClass = (type === 'user') ? 'wpragbot-user-message' : 'wpragbot-bot-message';
                    var messageStyle = (type === 'user') ? 'background: #4a6fa5; color: white; margin-left: auto; text-align: right;' : 'background: #e9ecef; color: #333;';

                    // Sanitize: always escape first, then parse markdown for bot messages
                    var formattedMessage;
                    if (type === 'bot') {
                        formattedMessage = shortcodeChat.parseMarkdown(message);
                    } else {
                        formattedMessage = '<span>' + shortcodeChat.escapeHtml(message) + '</span>';
                    }

                    var messageHtml = '<div class="wpragbot-message ' + messageClass + '" style="' + messageStyle + ' padding: 10px 15px; border-radius: 18px; max-width: 80%; word-wrap: break-word; overflow-wrap: break-word; margin-bottom: 15px;">' + formattedMessage + '</div>';
                    $('#wpragbot-chat-messages-shortcode').append(messageHtml);
                    $('#wpragbot-chat-messages-shortcode').scrollTop($('#wpragbot-chat-messages-shortcode')[0].scrollHeight);
                },
                escapeHtml: function(text) {
                    return $('<div>').text(text).html();
                },
                parseMarkdown: function(text) {
                    var html = this.escapeHtml(text);
                    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                    html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');
                    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
                    html = html.replace(/_(.+?)_/g, '<em>$1</em>');
                    html = html.replace(/`(.+?)`/g, '<code>$1</code>');
                    html = html.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, function(_, label, href) {
                        var safeHref = href.trim();
                        if (/^javascript:/i.test(safeHref)) { safeHref = '#'; }
                        return '<a href="' + safeHref + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
                    });
                    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
                    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
                    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
                    html = html.replace(/\n/g, '<br>');
                    html = html.replace(/<br>[-•]\s+(.+?)(?=<br>|$)/g, '<br><li>$1</li>');
                    html = html.replace(/(<li>.*?<\/li>)+/g, function(match) {
                        return '<ul>' + match + '</ul>';
                    });
                    return html;
                },
                addLoadingIndicator: function() {
                    var loadingHtml = '<div class="wpragbot-message wpragbot-bot-message" style="background: #e9ecef; color: #333; padding: 10px 15px; border-radius: 18px; max-width: 80%; margin-bottom: 15px;"><span class="wpragbot-loading" style="display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #4a6fa5; border-radius: 50%; animation: spin 1s linear infinite;"></span> Thinking...</div>';
                    $('#wpragbot-chat-messages-shortcode').append(loadingHtml);
                    $('#wpragbot-chat-messages-shortcode').scrollTop($('#wpragbot-chat-messages-shortcode')[0].scrollHeight);
                },
                getSessionId: function() {
                    // In-memory UUID — generated once per page load, never persisted.
                    // A page reload always produces a brand-new session ID.
                    var uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
                    if (!wpragbotShortcodeSessionId || !uuidRegex.test(wpragbotShortcodeSessionId)) {
                        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
                            wpragbotShortcodeSessionId = crypto.randomUUID();
                        } else {
                            wpragbotShortcodeSessionId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                                return v.toString(16);
                            });
                        }
                    }
                    return wpragbotShortcodeSessionId;
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
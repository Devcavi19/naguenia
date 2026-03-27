/**
 * The public-facing JavaScript file for the plugin.
 *
 * This file is used to handle the public-facing JavaScript functionality.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 * @subpackage Wpragbot/public/js
 */

(function( $ ) {
    'use strict';

    // In-memory session ID — lives only for this page load.
    // Destroyed on reload/navigation, guaranteeing a fresh session every time.
    var wpragbotSessionId = null;

    $(document).ready(function() {
        // Initialize chat widget
        var chatWidget = {
            init: function() {
                this.bindEvents();
                this.setupDrag();
            },

            bindEvents: function() {
                // Toggle chat widget
                $('#wpragbot-chat-toggle').on('click', function() {
                    $('#wpragbot-chat-container').toggle();
                });

                // Close chat widget
                $('.wpragbot-chat-close').on('click', function() {
                    $('#wpragbot-chat-container').hide();
                });

                // Send message
                $('#wpragbot-send-button').on('click', function() {
                    chatWidget.sendMessage();
                });

                // Allow sending with Enter key
                $('#wpragbot-user-input').on('keypress', function(e) {
                    if (e.which === 13) {
                        chatWidget.sendMessage();
                    }
                });
            },

            setupDrag: function() {
                // Make chat widget draggable
                var $chatContainer = $('#wpragbot-chat-container');
                var $chatHeader = $('.wpragbot-chat-header');
                var pos = {x: 0, y: 0};

                $chatHeader.on('mousedown', function(e) {
                    pos.x = e.clientX - $chatContainer.offset().left;
                    pos.y = e.clientY - $chatContainer.offset().top;
                    $chatContainer.addClass('dragging');
                    $(document).on('mousemove', mouseMoveHandler);
                    $(document).on('mouseup', mouseUpHandler);
                });

                function mouseMoveHandler(e) {
                    if ($chatContainer.hasClass('dragging')) {
                        $chatContainer.css({
                            left: e.clientX - pos.x + 'px',
                            top: e.clientY - pos.y + 'px'
                        });
                    }
                }

                function mouseUpHandler() {
                    $chatContainer.removeClass('dragging');
                    $(document).off('mousemove', mouseMoveHandler);
                    $(document).off('mouseup', mouseUpHandler);
                }
            },

            sendMessage: function() {
                var message = $('#wpragbot-user-input').val().trim();
                var sessionId = this.getSessionId();

                if (message === '') {
                    return;
                }

                // Add user message to chat
                this.addMessage(message, 'user');

                // Clear input
                $('#wpragbot-user-input').val('');

                // Show loading indicator
                this.addLoadingIndicator();

                // Send to server
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
                        // Remove loading indicator
                        $('.wpragbot-loading').closest('.wpragbot-message').remove();

                        if (response.success) {
                            // Add bot response to chat
                            chatWidget.addMessage(response.data.response, 'bot');
                        } else {
                            // Handle error
                            chatWidget.addMessage('Error: ' + response.data, 'bot');
                        }
                    },
                    error: function() {
                        // Remove loading indicator
                        $('.wpragbot-loading').closest('.wpragbot-message').remove();
                        chatWidget.addMessage('Error: Failed to get response', 'bot');
                    }
                });
            },

            addMessage: function(message, type) {
                var $chatMessages = $('#wpragbot-chat-messages');
                var messageClass = (type === 'user') ? 'wpragbot-user-message' : 'wpragbot-bot-message';
                
                // Parse markdown for bot messages
                var formattedMessage = message;
                if (type === 'bot') {
                    formattedMessage = this.parseMarkdown(message);
                }
                
                var messageHtml = '<div class="wpragbot-message ' + messageClass + '">' + formattedMessage + '</div>';
                $chatMessages.append(messageHtml);
                $chatMessages.scrollTop($chatMessages[0].scrollHeight);
            },

            parseMarkdown: function(text) {
                // Simple markdown parser for common patterns
                var html = text;
                
                // Bold: **text** or __text__
                html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');
                
                // Italic: *text* or _text_
                html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
                html = html.replace(/_(.+?)_/g, '<em>$1</em>');
                
                // Inline code: `code`
                html = html.replace(/`(.+?)`/g, '<code>$1</code>');
                
                // Links: [text](url)
                html = html.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
                
                // Phone emoji
                html = html.replace(/📞/g, '📞');
                
                // Headers (must be at start of line)
                html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
                html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
                html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
                
                // Line breaks
                html = html.replace(/\n/g, '<br>');
                
                // Bullet points (lines starting with - or •)
                html = html.replace(/<br>[-•]\s+(.+?)(?=<br>|$)/g, '<br><li>$1</li>');
                
                // Wrap consecutive list items in ul
                html = html.replace(/(<li>.*?<\/li>)+/g, function(match) {
                    return '<ul>' + match + '</ul>';
                });
                
                return html;
            },

            addLoadingIndicator: function() {
                var loadingHtml = '<div class="wpragbot-message wpragbot-bot-message"><span class="wpragbot-loading"></span>Thinking...</div>';
                $('#wpragbot-chat-messages').append(loadingHtml);
                $('#wpragbot-chat-messages').scrollTop($('#wpragbot-chat-messages')[0].scrollHeight);
            },

            getSessionId: function() {
                // In-memory UUID — generated once per page load, never persisted.
                // A page reload always produces a brand-new session ID.
                var uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

                if (!wpragbotSessionId || !uuidRegex.test(wpragbotSessionId)) {
                    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
                        wpragbotSessionId = crypto.randomUUID();
                    } else {
                        // Fallback for older browsers (IE11, etc.)
                        wpragbotSessionId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                            return v.toString(16);
                        });
                    }
                }
                return wpragbotSessionId;
            }
        };

        // Initialize the chat widget
        chatWidget.init();

        console.log('WPRAGBot public JavaScript loaded');
    });

})( jQuery );
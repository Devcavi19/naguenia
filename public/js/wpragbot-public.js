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
                this.updateNetworkStatus();
                this.clearConversation();

                var self = this;
                window.addEventListener('online', function() {
                    self.updateNetworkStatus();
                });
                window.addEventListener('offline', function() {
                    self.updateNetworkStatus();
                });
            },

            bindEvents: function() {
                var $toggle = $('#wpragbot-chat-toggle');
                var $container = $('#wpragbot-chat-container');

                $toggle.attr('aria-controls', 'wpragbot-chat-container');
                $toggle.attr('aria-expanded', 'false');

                // Toggle chat widget
                $toggle.on('click', function() {
                    var visible = !$container.prop('hidden');
                    $container.prop('hidden', visible);
                    $toggle.attr('aria-expanded', (!visible).toString());
                    if (!visible) {
                        $('#wpragbot-user-input').focus();
                    }
                });

                $toggle.on('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        $toggle.click();
                    }
                });

                // Close chat widget
                $('.wpragbot-chat-close').on('click', function() {
                    $container.prop('hidden', true);
                    $toggle.attr('aria-expanded', 'false');
                    $toggle.focus();
                });

                $('.wpragbot-chat-close').on('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        $(this).click();
                    }
                });

                // Send message
                $('#wpragbot-send-button').on('click', function() {
                    chatWidget.sendMessage();
                });

                // Allow sending with Enter key (Shift+Enter for newline)
                $('#wpragbot-user-input').on('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        chatWidget.sendMessage();
                    }
                });
            },



            sendMessage: function(forceMessage) {
                var $input = $('#wpragbot-user-input');
                var message = (typeof forceMessage === 'string' ? forceMessage : $input.val()).trim();
                var sessionId = this.getSessionId();

                if (message === '') {
                    return;
                }

                // Add user message to chat and show bot typing state
                this.addMessage(message, 'user');
                this.addTypingIndicator();
                this.setStatus('Thinking...', true);
                this.disableInput(true);

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
                        // Remove loading/typing indicator
                        chatWidget.removeTypingIndicator();

                        if (response.success) {
                            // Add bot response to chat
                            chatWidget.addMessage(response.data.response, 'bot');

                            // Clear input only on success
                            $input.val('');
                            chatWidget.setStatus('Connected', false);
                        } else {
                            chatWidget.showErrorWithRetry('Failed to get a valid response. Please try again.', message);
                        }
                    },
                    error: function() {
                        chatWidget.removeTypingIndicator();
                        chatWidget.showErrorWithRetry('Error: Failed to get response. This usually means network issue.', message);
                    },
                    complete: function() {
                        chatWidget.disableInput(false);
                        $input.focus();
                    }
                });
            },

            addMessage: function(message, type, shouldSave) {
                if (typeof shouldSave === 'undefined') {
                    shouldSave = true;
                }

                var $chatMessages = $('#wpragbot-chat-messages');
                var messageClass = (type === 'user') ? 'wpragbot-user-message' : 'wpragbot-bot-message';

                // adjustable width/visual style by message length
                var textLength = message.trim().length;
                var sizeClass = 'wpragbot-message--m';
                if (textLength <= 25) {
                    sizeClass = 'wpragbot-message--xs';
                } else if (textLength <= 60) {
                    sizeClass = 'wpragbot-message--s';
                } else if (textLength <= 140) {
                    sizeClass = 'wpragbot-message--m';
                } else {
                    sizeClass = 'wpragbot-message--l';
                }

                // Render user as sanitized text; bot with simple markdown sanitized first.
                var formattedMessage;
                if (type === 'bot') {
                    formattedMessage = this.parseMarkdown(message);
                } else {
                    formattedMessage = '<span>' + this.escapeHtml(message) + '</span>';
                }

                var messageHtml = '<div class="wpragbot-message ' + messageClass + ' ' + sizeClass + ' wpragbot-animate-in" role="article" data-length="' + textLength + '">' + formattedMessage + '</div>';
                $chatMessages.append(messageHtml);

                if (shouldSave) {
                    this.history = this.history || [];
                    this.history.push({ type: type, text: message });
                    this.saveConversation();
                }

                this.scrollToBottomDebounced();
            },

            parseMarkdown: function(text) {
                // Start from escaped text to prevent injection
                var html = this.escapeHtml(text);

                // Bold: **text** or __text__
                html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');

                // Italic: *text* or _text_
                html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
                html = html.replace(/_(.+?)_/g, '<em>$1</em>');

                // Inline code: `code`
                html = html.replace(/`(.+?)`/g, '<code>$1</code>');

                // Links: [text](url), prevent javascript: protocol
                html = html.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, function(_, label, href) {
                    var safeHref = href.trim();
                    if (/^javascript:/i.test(safeHref)) {
                        safeHref = '#';
                    }
                    return '<a href="' + safeHref + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
                });

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
                var loadingHtml = '<div class="wpragbot-message wpragbot-bot-message wpragbot-typing-indicator" data-wpragbot-typing="true"><div class="wpragbot-typing-dots"><span></span><span></span><span></span></div></div>';
                $('#wpragbot-chat-messages').append(loadingHtml);
                this.scrollToBottomDebounced();
            },

            addTypingIndicator: function() {
                this.addLoadingIndicator();
            },

            removeTypingIndicator: function() {
                $('#wpragbot-chat-messages').find('.wpragbot-typing-indicator').remove();
            },

            showErrorWithRetry: function(errorText, originalMessage) {
                var $messages = $('#wpragbot-chat-messages');
                var html = '<div class="wpragbot-message wpragbot-bot-message wpragbot-error-message" role="alert" aria-live="assertive">' +
                    '<span>' + this.escapeHtml(errorText) + '</span>' +
                    ' <button class="wpragbot-retry-button" type="button">Retry</button>' +
                    '</div>';

                $messages.append(html);
                $messages.scrollTop($messages[0].scrollHeight);
                this.setStatus('Offline / retry possible', true);

                var self = this;
                $messages.find('.wpragbot-retry-button').last().on('click', function() {
                    $('#wpragbot-user-input').val(originalMessage);
                    self.sendMessage();
                });
            },

            setStatus: function(text, assertive) {
                var $status = $('#wpragbot-chat-status');
                if ($status.length === 0) {
                    return;
                }
                $status.text(text);
                $status.attr('aria-live', assertive ? 'assertive' : 'polite');
            },

            disableInput: function(disabled) {
                $('#wpragbot-user-input').prop('disabled', disabled);
                $('#wpragbot-send-button').prop('disabled', disabled).attr('aria-busy', disabled ? 'true' : 'false');
            },

            scrollToBottom: function() {
                var $messages = $('#wpragbot-chat-messages');
                $messages.scrollTop($messages[0].scrollHeight);
            },

            scrollToBottomDebounced: function() {
                if (this._scrollTimeout) {
                    clearTimeout(this._scrollTimeout);
                }
                var self = this;
                this._scrollTimeout = setTimeout(function() {
                    self.scrollToBottom();
                }, 100);
            },

            clearConversation: function() {
                var $messages = $('#wpragbot-chat-messages');
                $messages.empty();
                this.history = [];
                this.saveConversation();
                this.addMessage('Hello! How can I help you today?', 'bot');
                this.setStatus('Connected', false);
                $('#wpragbot-user-input').val('').focus();
                this.scrollToBottom();
            },

            saveConversation: function() {
                // Conversation is not persisted. Session lives only for this page load.
                // On reload, a fresh conversation starts.
                if (!this.history) {
                    this.history = [];
                }
            },

            loadConversation: function() {
                // DEPRECATED: Conversations are no longer persisted.
                // This method is kept for backward compatibility but is a no-op.
                this.clearConversation();
            },

            updateNetworkStatus: function() {
                var statusText = navigator.onLine ? 'Connected' : 'Offline - reconnecting';
                this.setStatus(statusText, !navigator.onLine);
            },

            escapeHtml: function(text) {
                return $('<div>').text(text).html();
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

    });

})( jQuery );
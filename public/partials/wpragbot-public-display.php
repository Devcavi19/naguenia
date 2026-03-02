<?php

/**
 * Provides the public-facing chat widget.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 * @subpackage Wpragbot/public/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get plugin settings
$settings = get_option('wpragbot_settings');
?>

<div id="wpragbot-chat-container" class="wpragbot-chat-container">
    <div class="wpragbot-chat-header">
        <span class="wpragbot-chat-title">AI Assistant</span>
        <span class="wpragbot-chat-close">&times;</span>
    </div>
    <div class="wpragbot-chat-messages" id="wpragbot-chat-messages">
        <div class="wpragbot-message wpragbot-bot-message">
            Hello! How can I help you today?
        </div>
    </div>
    <div class="wpragbot-chat-input-area">
        <input type="text" id="wpragbot-user-input" placeholder="Type your message here..." />
        <button id="wpragbot-send-button">Send</button>
    </div>
</div>

<div id="wpragbot-chat-toggle" class="wpragbot-chat-toggle">
    <span class="wpragbot-chat-icon">💬</span>
</div>

<script>
// Chat widget JavaScript will be loaded via enqueue_scripts
</script>
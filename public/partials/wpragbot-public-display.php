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

<div id="wpragbot-chat-container" class="wpragbot-chat-container" role="dialog" aria-label="AI chat assistant" aria-modal="false" hidden>
    <div class="wpragbot-chat-header">
        <div class="wpragbot-header-brand">
            <svg class="wpragbot-logo-svg" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11 11C11 11 10 3 17 5C17 5 19 6 18 10C24.5 9.5 32 12 32 24C32 30 28 35 24 35C24 35 27 34 26 31C26 31 16 35 10 26C8 23 8 18 11 11Z" fill="white"/>
                <circle cx="21" cy="21" r="1.5" fill="#E3231B"/>
                <circle cx="27" cy="21" r="1.5" fill="#E3231B"/>
            </svg>
            <span class="wpragbot-chat-title">NaguenIA</span>
        </div>
        <div class="wpragbot-chat-header-actions">
            <button class="wpragbot-chat-close" type="button" aria-label="Close chat">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>
    <!-- Status bar removed as per design -->
    <div class="wpragbot-chat-messages" id="wpragbot-chat-messages" role="log" aria-live="polite" aria-label="Conversation">
        <div class="wpragbot-message wpragbot-bot-message wpragbot-animate-in">
            Hello! How can I help you today?
        </div>
    </div>
    <div class="wpragbot-chat-input-area">
        <input type="text" id="wpragbot-user-input" placeholder="Type your needed service......" aria-label="Type your message" />
        <button id="wpragbot-send-button" aria-label="Send message" type="button">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2.01 21L23 12L2.01 3L2 10l15 2-15 2z" fill="currentColor"/>
            </svg>
        </button>
    </div>
</div>

<div id="wpragbot-chat-toggle" class="wpragbot-chat-toggle">
    <svg class="wpragbot-logo-svg" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M11 11C11 11 10 3 17 5C17 5 19 6 18 10C24.5 9.5 32 12 32 24C32 30 28 35 24 35C24 35 27 34 26 31C26 31 16 35 10 26C8 23 8 18 11 11Z" fill="white"/>
        <circle cx="21" cy="21" r="1.5" fill="#E3231B"/>
        <circle cx="27" cy="21" r="1.5" fill="#E3231B"/>
    </svg>
</div>

<script>
// Chat widget JavaScript will be loaded via enqueue_scripts
</script>
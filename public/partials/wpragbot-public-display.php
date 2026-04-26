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
        <div class="wpragbot-header-brand" style="display: flex; align-items: center; gap: 0;">
            <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'images/i-Gov_Chatbot_h1.ico'); ?>" alt="N" class="wpragbot-header-n-icon" style="height: 24px; width: auto; object-fit: contain; margin-right: 1px; flex-shrink: 0;" />
            <span class="wpragbot-chat-title" style="margin-left: 0; line-height: 1;">aguenIA</span>
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

<div id="wpragbot-chat-toggle" class="wpragbot-chat-toggle" role="button" aria-label="Open Chat" tabindex="0">
    <div class="wpragbot-toggle-ripple"></div>
    <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'images/i-Gov_Chatbot.ico'); ?>" alt="Chat Icon" class="wpragbot-toggle-icon" style="width: 44px !important; height: 44px !important; max-width: 44px !important; object-fit: contain; display: block;" />
</div>

<script>
// Chat widget JavaScript will be loaded via enqueue_scripts
</script>
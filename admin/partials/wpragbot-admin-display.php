<?php

/**
 * Provides the admin interface for the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 * @subpackage Wpragbot/admin/partials
 */

// Check if user is authorized
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get current settings
$settings = get_option('wpragbot_settings');
?>

<div class="wrap wpragbot-settings-page">
    <h1>WPRAGBot Settings</h1>

    <form method="post" action="options.php" id="wpragbot-settings-form">
        <?php
        settings_fields('wpragbot');
        do_settings_sections('wpragbot');
        submit_button();
        ?>
    </form>

    <h2>Knowledge Base Management</h2>
    <p>Upload and manage documents for the chatbot knowledge base.</p>

    <div class="postbox">
        <div class="inside">
            <h3>Upload Document</h3>
            <p>Upload documents to add to the knowledge base. The system will automatically process and embed the content.</p>

            <form method="post" action="" enctype="multipart/form-data" id="wpragbot-upload-form">
                <?php wp_nonce_field('wpragbot_upload_document', 'wpragbot_upload_document_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Document File</th>
                        <td>
                            <input type="file" name="wpragbot_document_file" id="wpragbot_document_file" accept=".txt,.pdf,.doc,.docx,.md" required />
                            <p class="description">Supported formats: TXT, PDF, DOC, DOCX, MD</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Document Title</th>
                        <td>
                            <input type="text" name="wpragbot_document_title" id="wpragbot_document_title" class="regular-text" />
                            <p class="description">Enter a title for this document (optional)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Upload Document', 'primary', 'wpragbot_upload_submit'); ?>
            </form>
        </div>

    <h2>Analytics Dashboard</h2>
    <p>View chatbot usage statistics and performance metrics.</p>

    <?php
    // Get analytics data
    $analytics = new Wpragbot_Analytics();
    $statistics = $analytics->get_chat_statistics(30);
    $trends = $analytics->get_usage_trends(30);
    $recent_sessions = $analytics->get_recent_sessions(5);
    ?>

    <div class="postbox">
        <div class="inside">
            <h3>Statistics (Last 30 Days)</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="wpragbot-stat-box" style="padding: 15px; background: #f5f5f5; border-radius: 5px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($statistics['total_chats']); ?></div>
                    <div style="color: #666;">Total Chats</div>
                </div>
                
                <div class="wpragbot-stat-box" style="padding: 15px; background: #f5f5f5; border-radius: 5px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($statistics['total_sessions']); ?></div>
                    <div style="color: #666;">Sessions</div>
                </div>
                
                <div class="wpragbot-stat-box" style="padding: 15px; background: #f5f5f5; border-radius: 5px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($statistics['avg_messages_per_session']); ?></div>
                    <div style="color: #666;">Avg Messages/Session</div>
                </div>
                
                <div class="wpragbot-stat-box" style="padding: 15px; background: #f5f5f5; border-radius: 5px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($statistics['most_active_hour']); ?>:00</div>
                    <div style="color: #666;">Peak Hour</div>
                </div>
            </div>

            <?php if (!empty($statistics['top_questions'])): ?>
            <h4>Top Questions</h4>
            <table class="widefat" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th style="width: 80px;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($statistics['top_questions'], 0, 5) as $question): ?>
                    <tr>
                        <td><?php echo esc_html(substr($question['content'], 0, 100)); ?><?php echo strlen($question['content']) > 100 ? '...' : ''; ?></td>
                        <td><?php echo esc_html($question['count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (!empty($trends['daily_usage'])): ?>
            <h4 style="margin-top: 20px;">Daily Usage Trend</h4>
            <div class="chart-placeholder" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-top: 10px;">
                <canvas id="wpragbot-daily-chart" width="600" height="200"></canvas>
                <script>
                    // Simple bar chart with daily usage
                    (function($) {
                        var dailyData = <?php echo wp_json_encode($trends['daily_usage']); ?>;
                        var canvas = document.getElementById('wpragbot-daily-chart');
                        var ctx = canvas.getContext('2d');
                        
                        if (dailyData.length > 0) {
                            var maxCount = Math.max(...dailyData.map(d => parseInt(d.count)));
                            var barWidth = canvas.width / dailyData.length;
                            
                            dailyData.forEach(function(day, index) {
                                var barHeight = (parseInt(day.count) / maxCount) * (canvas.height - 40);
                                var x = index * barWidth;
                                var y = canvas.height - barHeight - 20;
                                
                                ctx.fillStyle = '#2271b1';
                                ctx.fillRect(x + 5, y, barWidth - 10, barHeight);
                                
                                // Draw date label
                                ctx.fillStyle = '#666';
                                ctx.font = '10px Arial';
                                ctx.save();
                                ctx.translate(x + barWidth/2, canvas.height - 5);
                                ctx.rotate(-Math.PI/4);
                                ctx.fillText(day.date, 0, 0);
                                ctx.restore();
                                
                                // Draw count
                                ctx.fillStyle = '#333';
                                ctx.font = 'bold 12px Arial';
                                ctx.textAlign = 'center';
                                ctx.fillText(day.count, x + barWidth/2, y - 5);
                            });
                        } else {
                            ctx.fillStyle = '#666';
                            ctx.font = '14px Arial';
                            ctx.textAlign = 'center';
                            ctx.fillText('No data available yet', canvas.width/2, canvas.height/2);
                        }
                    })(jQuery);
                </script>
            </div>
            <?php else: ?>
            <p style="margin-top: 20px; color: #666;">No usage data available yet. Start chatting to see statistics!</p>
            <?php endif; ?>

            <?php if (!empty($recent_sessions)): ?>
            <h4 style="margin-top: 20px;">Recent Sessions</h4>
            <table class="widefat" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Session ID</th>
                        <th>Messages</th>
                        <th>Last Active</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_sessions as $session): ?>
                    <tr>
                        <td><?php echo esc_html(substr($session['session_id'], 0, 20)); ?>...</td>
                        <td><?php echo esc_html($session['message_count']); ?></td>
                        <td><?php 
                            if (isset($session['updated_at']) && !empty($session['updated_at'])) {
                                echo esc_html(human_time_diff(strtotime($session['updated_at']), current_time('timestamp'))); 
                            } else {
                                echo 'Never'; 
                            }
                            ?> ago</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <a href="<?php echo admin_url('admin.php?page=wpragbot&action=export_analytics'); ?>" class="button">Export Data (CSV)</a>
                <a href="<?php echo admin_url('admin.php?page=wpragbot&action=export_analytics&format=json'); ?>" class="button">Export Data (JSON)</a>
            </div>
        </div>
    </div>
</div>
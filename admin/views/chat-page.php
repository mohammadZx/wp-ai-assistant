<?php
/**
 * Chat page
 */

if (!defined('ABSPATH')) {
    exit;
}

$topic_manager = new WPAI_Topic_Manager();
$topics = $topic_manager->list_topics();
?>

<div class="wrap wpai-chat-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wpai-chat-container">
        <div class="wpai-chat-sidebar">
            <h3><?php _e('Topics', 'wpai-assistant'); ?></h3>
            <select id="wpai-topic-select" class="wpai-select">
                <option value=""><?php _e('No Topic', 'wpai-assistant'); ?></option>
                <?php foreach ($topics as $topic): ?>
                    <option value="<?php echo esc_attr($topic->id); ?>"><?php echo esc_html($topic->name); ?></option>
                <?php endforeach; ?>
            </select>
            
            <h3><?php _e('Thinking Degree', 'wpai-assistant'); ?></h3>
            <div class="wpai-thinking-degree">
                <input type="range" id="wpai-thinking-degree" min="0" max="100" value="50" />
                <div class="wpai-degree-labels">
                    <span><?php _e('Conservative', 'wpai-assistant'); ?></span>
                    <span><?php _e('Balanced', 'wpai-assistant'); ?></span>
                    <span><?php _e('Creative', 'wpai-assistant'); ?></span>
                </div>
                <span id="wpai-degree-value">50</span>
            </div>
            
            <h3><?php _e('File Upload', 'wpai-assistant'); ?></h3>
            <input type="file" id="wpai-file-upload" accept=".txt,.md,.docx,.pdf,.jpg,.jpeg,.png,.gif,.webp" />
            <button type="button" id="wpai-upload-btn" class="button"><?php _e('Upload', 'wpai-assistant'); ?></button>
            <div id="wpai-uploaded-files"></div>
        </div>
        
        <div class="wpai-chat-main">
            <div id="wpai-chat-messages" class="wpai-chat-messages"></div>
            <div class="wpai-chat-input-container">
                <textarea id="wpai-chat-input" placeholder="<?php esc_attr_e('Type your message...', 'wpai-assistant'); ?>" rows="3"></textarea>
                <div class="wpai-chat-actions">
                    <button type="button" id="wpai-send-btn" class="button button-primary"><?php _e('Send', 'wpai-assistant'); ?></button>
                    <button type="button" id="wpai-clear-btn" class="button"><?php _e('Clear', 'wpai-assistant'); ?></button>
                    <button type="button" id="wpai-export-btn" class="button"><?php _e('Export', 'wpai-assistant'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>


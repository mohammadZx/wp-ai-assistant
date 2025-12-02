<?php
/**
 * Chat modal for post/page editor
 */

if (!defined('ABSPATH')) {
    exit;
}

$topic_manager = new WPAI_Topic_Manager();
$topics = $topic_manager->list_topics();
$post_id = get_the_ID();
?>

<div id="wpai-chat-modal" class="wpai-chat-modal">
    <div class="wpai-chat-modal-content">
        <div class="wpai-chat-modal-header">
            <h2><?php _e('AI Assistant Chat', 'wpai-assistant'); ?></h2>
            <button type="button" class="wpai-chat-modal-close">&times;</button>
        </div>
        <div class="wpai-chat-modal-body">
            <div class="wpai-chat-sidebar" style="width: 250px; border-right: 1px solid #ddd; padding: 15px;">
                <h3 style="font-size: 12px; margin-top: 0;"><?php _e('Topic', 'wpai-assistant'); ?></h3>
                <select id="wpai-modal-topic-select" class="wpai-select" style="width: 100%; margin-bottom: 20px;">
                    <option value=""><?php _e('No Topic', 'wpai-assistant'); ?></option>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?php echo esc_attr($topic->id); ?>"><?php echo esc_html($topic->name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <h3 style="font-size: 12px;"><?php _e('Thinking Degree', 'wpai-assistant'); ?></h3>
                <div class="wpai-thinking-degree">
                    <input type="range" id="wpai-modal-thinking-degree" min="0" max="100" value="50" />
                    <div class="wpai-degree-labels">
                        <span><?php _e('Conservative', 'wpai-assistant'); ?></span>
                        <span><?php _e('Balanced', 'wpai-assistant'); ?></span>
                        <span><?php _e('Creative', 'wpai-assistant'); ?></span>
                    </div>
                    <span id="wpai-modal-degree-value">50</span>
                </div>
            </div>
            
            <div class="wpai-chat-main" style="flex: 1; display: flex; flex-direction: column;">
                <div id="wpai-modal-chat-messages" class="wpai-chat-messages" style="flex: 1; overflow-y: auto; padding: 15px;"></div>
                <div class="wpai-chat-input-container" style="padding: 15px; border-top: 1px solid #ddd;">
                    <textarea id="wpai-modal-chat-input" placeholder="<?php esc_attr_e('Type your message...', 'wpai-assistant'); ?>" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    <div class="wpai-chat-actions" style="margin-top: 10px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="wpai-modal-send-btn" class="button button-primary"><?php _e('Send', 'wpai-assistant'); ?></button>
                        <button type="button" id="wpai-modal-apply-btn" class="button"><?php _e('Apply to Page', 'wpai-assistant'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let modalSessionId = null;
    let lastResponse = '';
    
    // Open modal
    $('#wpai-open-chat').on('click', function() {
        if (!modalSessionId) {
            modalSessionId = 'wpai_modal_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        $('#wpai-chat-modal').addClass('active');
    });
    
    // Close modal
    $('.wpai-chat-modal-close').on('click', function() {
        $('#wpai-chat-modal').removeClass('active');
    });
    
    // Send message in modal
    $('#wpai-modal-send-btn, #wpai-modal-chat-input').on('keypress', function(e) {
        if (e.which === 13 && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            sendModalMessage();
        }
    });
    
    $('#wpai-modal-send-btn').on('click', function() {
        sendModalMessage();
    });
    
    function sendModalMessage() {
        const input = $('#wpai-modal-chat-input');
        const message = input.val().trim();
        
        if (!message) {
            return;
        }
        
        addModalMessage('user', message);
        input.val('');
        
        const loadingId = addModalMessage('assistant', '', true);
        
        const thinkingDegree = parseInt($('#wpai-modal-thinking-degree').val()) || 50;
        const settings = {
            temperature: (thinkingDegree / 100) * 1.5,
            top_p: 0.5 + ((thinkingDegree / 100) * 0.5),
            max_tokens: 1000 + ((thinkingDegree / 100) * 2000)
        };
        
        const context = {
            topic_id: $('#wpai-modal-topic-select').val(),
            post_id: <?php echo $post_id; ?>,
            current_content: $('#content').val() || ''
        };
        
        $.ajax({
            url: wpaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpai_send_message',
                nonce: wpaiData.nonce,
                message: message,
                session_id: modalSessionId,
                context: JSON.stringify(context),
                settings: JSON.stringify(settings)
            },
            success: function(response) {
                if (response.success) {
                    lastResponse = response.data.response;
                    $('#' + loadingId).removeClass('loading').html(response.data.response);
                } else {
                    $('#' + loadingId).removeClass('loading').html('<span style="color: #d63638;">Error: ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $('#' + loadingId).removeClass('loading').html('<span style="color: #d63638;">An error occurred</span>');
            }
        });
    }
    
    function addModalMessage(role, content, loading) {
        const container = $('#wpai-modal-chat-messages');
        const messageId = 'wpai-modal-msg-' + Date.now();
        const time = new Date().toLocaleTimeString();
        
        let html = '<div id="' + messageId + '" class="wpai-message ' + role + '">';
        if (loading) {
            html += '<span class="wpai-spinner"></span> Thinking...';
        } else {
            html += '<div>' + content.replace(/\n/g, '<br>') + '</div>';
            html += '<div class="wpai-message-time">' + time + '</div>';
        }
        html += '</div>';
        
        container.append(html);
        container.scrollTop(container[0].scrollHeight);
        
        return messageId;
    }
    
    // Apply to page
    $('#wpai-modal-apply-btn').on('click', function() {
        if (!lastResponse) {
            alert('<?php _e('No response to apply. Please send a message first.', 'wpai-assistant'); ?>');
            return;
        }
        
        if (confirm('<?php _e('Apply this content to the page editor?', 'wpai-assistant'); ?>')) {
            // Apply to editor
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                // Gutenberg
                wp.data.dispatch('core/editor').editPost({ content: lastResponse });
            } else {
                // Classic
                if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
                    tinyMCE.activeEditor.setContent(lastResponse);
                } else {
                    $('#content').val(lastResponse);
                }
            }
            
            $('#wpai-chat-modal').removeClass('active');
        }
    });
    
    // Update thinking degree value
    $('#wpai-modal-thinking-degree').on('input', function() {
        $('#wpai-modal-degree-value').text($(this).val());
    });
});
</script>


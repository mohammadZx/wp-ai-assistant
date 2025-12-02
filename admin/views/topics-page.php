<?php
/**
 * Topics management page
 */

if (!defined('ABSPATH')) {
    exit;
}

$topic_manager = new WPAI_Topic_Manager();
$topics = $topic_manager->list_topics();
?>

<div class="wrap wpai-topics-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wpai-topics-header">
        <button type="button" id="wpai-create-topic" class="button button-primary"><?php _e('Create New Topic', 'wpai-assistant'); ?></button>
    </div>
    
    <div class="wpai-topics-list">
        <?php if (empty($topics)): ?>
            <p><?php _e('No topics found. Create your first topic to get started.', 'wpai-assistant'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'wpai-assistant'); ?></th>
                        <th><?php _e('Description', 'wpai-assistant'); ?></th>
                        <th><?php _e('Created', 'wpai-assistant'); ?></th>
                        <th><?php _e('Actions', 'wpai-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topics as $topic): ?>
                        <tr>
                            <td><strong><?php echo esc_html($topic->name); ?></strong></td>
                            <td><?php echo esc_html(wp_trim_words($topic->description, 20)); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($topic->created_at))); ?></td>
                            <td>
                                <button type="button" class="button wpai-edit-topic" data-topic-id="<?php echo esc_attr($topic->id); ?>"><?php _e('Edit', 'wpai-assistant'); ?></button>
                                <button type="button" class="button wpai-delete-topic" data-topic-id="<?php echo esc_attr($topic->id); ?>"><?php _e('Delete', 'wpai-assistant'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Topic Modal -->
<div id="wpai-topic-modal" class="wpai-modal" style="display: none;">
    <div class="wpai-modal-content">
        <div class="wpai-modal-header">
            <h2 id="wpai-topic-modal-title"><?php _e('Create Topic', 'wpai-assistant'); ?></h2>
            <button type="button" class="wpai-modal-close">&times;</button>
        </div>
        <div class="wpai-modal-body">
            <form id="wpai-topic-form">
                <input type="hidden" id="wpai-topic-id" name="topic_id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wpai-topic-name"><?php _e('Topic Name', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="wpai-topic-name" name="name" class="regular-text" required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wpai-topic-description"><?php _e('Description', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea id="wpai-topic-description" name="description" rows="3" class="large-text"></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wpai-topic-initial-data"><?php _e('Initial Data / Context', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea id="wpai-topic-initial-data" name="initial_data" rows="10" class="large-text"></textarea>
                            <p class="description"><?php _e('Enter initial data, prompts, templates, or FAQ that will guide the AI for this topic.', 'wpai-assistant'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="wpai-modal-footer">
                    <button type="submit" class="button button-primary"><?php _e('Save', 'wpai-assistant'); ?></button>
                    <button type="button" class="button wpai-modal-cancel"><?php _e('Cancel', 'wpai-assistant'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Create topic
    $('#wpai-create-topic').on('click', function() {
        $('#wpai-topic-modal-title').text('<?php _e('Create Topic', 'wpai-assistant'); ?>');
        $('#wpai-topic-form')[0].reset();
        $('#wpai-topic-id').val('');
        $('#wpai-topic-modal').fadeIn();
    });
    
    // Edit topic
    $('.wpai-edit-topic').on('click', function() {
        const topicId = $(this).data('topic-id');
        
        $.ajax({
            url: wpaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpai_get_topic',
                nonce: wpaiData.nonce,
                topic_id: topicId
            },
            success: function(response) {
                if (response.success) {
                    const topic = response.data.topic;
                    $('#wpai-topic-modal-title').text('<?php _e('Edit Topic', 'wpai-assistant'); ?>');
                    $('#wpai-topic-id').val(topic.id);
                    $('#wpai-topic-name').val(topic.name);
                    $('#wpai-topic-description').val(topic.description);
                    $('#wpai-topic-initial-data').val(topic.initial_data);
                    $('#wpai-topic-modal').fadeIn();
                }
            }
        });
    });
    
    // Delete topic
    $('.wpai-delete-topic').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to delete this topic?', 'wpai-assistant'); ?>')) {
            return;
        }
        
        const topicId = $(this).data('topic-id');
        
        $.ajax({
            url: wpaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpai_delete_topic',
                nonce: wpaiData.nonce,
                topic_id: topicId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    // Save topic form
    $('#wpai-topic-form').on('submit', function(e) {
        e.preventDefault();
        
        const topicId = $('#wpai-topic-id').val();
        const action = topicId ? 'wpai_update_topic' : 'wpai_create_topic';
        const data = {
            action: action,
            nonce: wpaiData.nonce,
            name: $('#wpai-topic-name').val(),
            description: $('#wpai-topic-description').val(),
            initial_data: $('#wpai-topic-initial-data').val()
        };
        
        if (topicId) {
            data.topic_id = topicId;
        }
        
        $.ajax({
            url: wpaiData.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    // Close modal
    $('.wpai-modal-close, .wpai-modal-cancel').on('click', function() {
        $('#wpai-topic-modal').fadeOut();
    });
});
</script>

<style>
.wpai-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.wpai-modal-content {
    background: #fff;
    width: 90%;
    max-width: 600px;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.wpai-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.wpai-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #646970;
}

.wpai-modal-body {
    padding: 20px;
}

.wpai-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}
</style>


<?php
/**
 * Meta box for post/page editor
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();
// Get plugin instance - view files load after plugin is fully initialized
$plugin = WPAI_Plugin::get_instance();
$content_generator = $plugin->content_generator;
$intent_detector = $plugin->content_generator->intent_detector;
?>

<div class="wpai-meta-box">
    <p>
        <button type="button" id="wpai-open-chat" class="button button-primary">
            <?php _e('Open AI Chat', 'wpai-assistant'); ?>
        </button>
        <button type="button" id="wpai-generate-content" class="button">
            <?php _e('Generate Content', 'wpai-assistant'); ?>
        </button>
        <button type="button" id="wpai-improve-content" class="button">
            <?php _e('Improve Content', 'wpai-assistant'); ?>
        </button>
    </p>
    
    <div id="wpai-meta-preview" style="display: none;">
        <h4><?php _e('Preview', 'wpai-assistant'); ?></h4>
        <div id="wpai-preview-content" class="wpai-preview"></div>
        <div class="wpai-preview-actions">
            <button type="button" id="wpai-apply-preview" class="button button-primary"><?php _e('Apply to Editor', 'wpai-assistant'); ?></button>
            <button type="button" id="wpai-dismiss-preview" class="button"><?php _e('Dismiss', 'wpai-assistant'); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const postId = <?php echo $post_id; ?>;
    
    // Open chat modal
    $('#wpai-open-chat').on('click', function() {
        // Trigger chat modal (if exists)
        if ($('#wpai-chat-modal').length) {
            $('#wpai-chat-modal').addClass('active');
        } else {
            window.location.href = '<?php echo admin_url('admin.php?page=wpai-assistant-chat'); ?>';
        }
    });
    
    // Generate content
    $('#wpai-generate-content').on('click', function() {
        const prompt = prompt('<?php _e('What content would you like to generate?', 'wpai-assistant'); ?>');
        if (!prompt) {
            return;
        }
        
        generateContent(prompt, 'generate');
    });
    
    // Improve content
    $('#wpai-improve-content').on('click', function() {
        let currentContent = '';
        
        // Try to get content from Gutenberg first
        if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
            const editor = wp.data.select('core/editor');
            if (editor) {
                currentContent = editor.getEditedPostContent() || '';
            }
        }
        
        // Fallback to classic editor
        if (!currentContent) {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
                currentContent = tinyMCE.activeEditor.getContent() || '';
            } else {
                currentContent = $('#content').val() || '';
            }
        }
        
        if (!currentContent.trim()) {
            alert('<?php _e('No content to improve. Please add some content first.', 'wpai-assistant'); ?>');
            return;
        }
        
        const prompt = prompt('<?php _e('How would you like to improve the content?', 'wpai-assistant'); ?>', '<?php _e('Make it more engaging and SEO-friendly', 'wpai-assistant'); ?>');
        if (!prompt) {
            return;
        }
        
        generateContent(prompt, 'improve', currentContent);
    });
    
    function generateContent(prompt, type, existingContent) {
        const fullPrompt = type === 'improve' && existingContent 
            ? 'Improve the following content: ' + existingContent + '\n\nInstructions: ' + prompt
            : prompt;
        
        $.ajax({
            url: wpaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpai_generate_content',
                nonce: wpaiData.nonce,
                prompt: fullPrompt,
                post_type: '<?php echo get_post_type($post_id); ?>',
                context: JSON.stringify({
                    post_id: postId,
                    format: (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) ? 'gutenberg' : 'classic'
                }),
                settings: JSON.stringify({})
            },
            success: function(response) {
                if (response.success) {
                    showPreview(response.data.content);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('<?php _e('An error occurred', 'wpai-assistant'); ?>');
            }
        });
    }
    
    function showPreview(content) {
        $('#wpai-preview-content').html(content);
        $('#wpai-meta-preview').slideDown();
    }
    
    // Apply preview to editor
    $('#wpai-apply-preview').on('click', function() {
        const content = $('#wpai-preview-content').html();
        
        // Apply to editor
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            // Gutenberg editor - use blocks if content is in block format
            const editor = wp.data.select('core/editor');
            const isGutenberg = editor && editor.getCurrentPost();
            
            if (isGutenberg) {
                // Check if content contains block comments (Gutenberg format)
                if (content.includes('<!-- wp:')) {
                    // Content is in block format, apply directly
                    wp.data.dispatch('core/editor').editPost({ content: content });
                } else {
                    // Convert HTML to blocks
                    const blocks = wp.blocks.parse(content);
                    wp.data.dispatch('core/block-editor').resetBlocks(blocks);
                }
                
                // Save the post
                wp.data.dispatch('core/editor').savePost();
            } else {
                wp.data.dispatch('core/editor').editPost({ content: content });
            }
        } else {
            // Classic editor
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
                tinyMCE.activeEditor.setContent(content);
            } else {
                $('#content').val(content);
            }
        }
        
        // Also save via AJAX for persistence
        $.ajax({
            url: wpaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpai_apply_content',
                nonce: wpaiData.nonce,
                post_id: postId,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    $('#wpai-meta-preview').slideUp();
                    // Show success message
                    if (typeof wp !== 'undefined' && wp.data) {
                        // Gutenberg - changes are already visible
                    } else {
                        alert('<?php _e('Content applied successfully', 'wpai-assistant'); ?>');
                    }
                } else {
                    alert('Error: ' + (response.data.message || '<?php _e('Failed to apply content', 'wpai-assistant'); ?>'));
                }
            },
            error: function() {
                alert('<?php _e('An error occurred while applying content', 'wpai-assistant'); ?>');
            }
        });
    });
    
    // Dismiss preview
    $('#wpai-dismiss-preview').on('click', function() {
        $('#wpai-meta-preview').slideUp();
    });
});
</script>


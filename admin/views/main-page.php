<?php
/**
 * Main dashboard page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wpai-main-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wpai-dashboard">
        <div class="wpai-welcome">
            <h2><?php _e('Welcome to WP AI Assistant', 'wpai-assistant'); ?></h2>
            <p><?php _e('Create and edit WordPress content using AI. Get started by configuring your API settings.', 'wpai-assistant'); ?></p>
        </div>
        
        <div class="wpai-quick-actions">
            <h3><?php _e('Quick Actions', 'wpai-assistant'); ?></h3>
            <div class="wpai-action-cards">
                <div class="wpai-card">
                    <h4><?php _e('Start Chat', 'wpai-assistant'); ?></h4>
                    <p><?php _e('Chat with AI to generate content', 'wpai-assistant'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=wpai-assistant-chat'); ?>" class="button button-primary"><?php _e('Open Chat', 'wpai-assistant'); ?></a>
                </div>
                
                <div class="wpai-card">
                    <h4><?php _e('Configure Settings', 'wpai-assistant'); ?></h4>
                    <p><?php _e('Set up your API keys and preferences', 'wpai-assistant'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=wpai-assistant-settings'); ?>" class="button"><?php _e('Settings', 'wpai-assistant'); ?></a>
                </div>
                
                <div class="wpai-card">
                    <h4><?php _e('Manage Topics', 'wpai-assistant'); ?></h4>
                    <p><?php _e('Create and manage topic datasets', 'wpai-assistant'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=wpai-assistant-topics'); ?>" class="button"><?php _e('Topics', 'wpai-assistant'); ?></a>
                </div>
                
                <div class="wpai-card">
                    <h4><?php _e('Crawl & Analyze', 'wpai-assistant'); ?></h4>
                    <p><?php _e('Crawl URLs and get AI suggestions', 'wpai-assistant'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=wpai-assistant-crawler'); ?>" class="button"><?php _e('Crawler', 'wpai-assistant'); ?></a>
                </div>
            </div>
        </div>
    </div>
</div>


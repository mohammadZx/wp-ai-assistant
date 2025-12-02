<?php
/**
 * Settings page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wpai-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('wpai_settings'); ?>
        
        <div class="wpai-settings-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#api-settings" class="nav-tab nav-tab-active"><?php _e('API Settings', 'wpai-assistant'); ?></a>
                <a href="#model-settings" class="nav-tab"><?php _e('Model Settings', 'wpai-assistant'); ?></a>
                <a href="#security-settings" class="nav-tab"><?php _e('Security', 'wpai-assistant'); ?></a>
            </nav>
            
            <div id="api-settings" class="tab-content active">
                <h2><?php _e('API Configuration', 'wpai-assistant'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wpai_api_provider"><?php _e('API Provider', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <select name="wpai_api_provider" id="wpai_api_provider">
                                <option value="openai" <?php selected(get_option('wpai_api_provider'), 'openai'); ?>>OpenAI</option>
                                <option value="google" <?php selected(get_option('wpai_api_provider'), 'google'); ?>>Google Vertex AI / PaLM</option>
                                <option value="custom" <?php selected(get_option('wpai_api_provider'), 'custom'); ?>>Custom API</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wpai_api_key"><?php _e('API Key', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="wpai_api_key" id="wpai_api_key" value="<?php echo esc_attr(get_option('wpai_api_key')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter your API key for the selected provider.', 'wpai-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wpai_mirror_link"><?php _e('Mirror Link / Custom Endpoint', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="wpai_mirror_link" id="wpai_mirror_link" value="<?php echo esc_url(get_option('wpai_mirror_link')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Optional: Custom API endpoint URL (mirror link).', 'wpai-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wpai_default_model"><?php _e('Default Model', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="wpai_default_model" id="wpai_default_model" value="<?php echo esc_attr(get_option('wpai_default_model', 'gpt-3.5-turbo')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Default AI model to use (e.g., gpt-3.5-turbo, gpt-4, gemini-pro).', 'wpai-assistant'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="button" id="wpai-test-connection" class="button"><?php _e('Test Connection', 'wpai-assistant'); ?></button>
                    <span id="wpai-test-result"></span>
                </p>
            </div>
            
            <div id="model-settings" class="tab-content">
                <h2><?php _e('Model Parameters', 'wpai-assistant'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wpai_default_temperature"><?php _e('Temperature', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="range" name="wpai_default_temperature" id="wpai_default_temperature" min="0" max="2" step="0.1" value="<?php echo esc_attr(get_option('wpai_default_temperature', 0.7)); ?>" />
                            <span id="wpai_temperature_value"><?php echo esc_html(get_option('wpai_default_temperature', 0.7)); ?></span>
                            <p class="description"><?php _e('Controls randomness (0 = deterministic, 2 = very creative).', 'wpai-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wpai_default_top_p"><?php _e('Top P', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="range" name="wpai_default_top_p" id="wpai_default_top_p" min="0" max="1" step="0.05" value="<?php echo esc_attr(get_option('wpai_default_top_p', 1.0)); ?>" />
                            <span id="wpai_top_p_value"><?php echo esc_html(get_option('wpai_default_top_p', 1.0)); ?></span>
                            <p class="description"><?php _e('Nucleus sampling parameter.', 'wpai-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wpai_default_max_tokens"><?php _e('Max Tokens', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="wpai_default_max_tokens" id="wpai_default_max_tokens" min="100" max="8000" step="100" value="<?php echo esc_attr(get_option('wpai_default_max_tokens', 2000)); ?>" class="small-text" />
                            <p class="description"><?php _e('Maximum number of tokens in the response.', 'wpai-assistant'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Preset Profiles', 'wpai-assistant'); ?></h3>
                <p>
                    <button type="button" class="button wpai-preset" data-preset="conservative"><?php _e('Conservative', 'wpai-assistant'); ?></button>
                    <button type="button" class="button wpai-preset" data-preset="balanced"><?php _e('Balanced', 'wpai-assistant'); ?></button>
                    <button type="button" class="button wpai-preset" data-preset="creative"><?php _e('Creative', 'wpai-assistant'); ?></button>
                </p>
            </div>
            
            <div id="security-settings" class="tab-content">
                <h2><?php _e('Security & Approval', 'wpai-assistant'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wpai_require_approval"><?php _e('Require Approval', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="wpai_require_approval" id="wpai_require_approval" value="1" <?php checked(get_option('wpai_require_approval', true)); ?> />
                            <label for="wpai_require_approval"><?php _e('Require manual approval before publishing generated content', 'wpai-assistant'); ?></label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wpai_auto_backup"><?php _e('Auto Backup', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="wpai_auto_backup" id="wpai_auto_backup" value="1" <?php checked(get_option('wpai_auto_backup', true)); ?> />
                            <label for="wpai_auto_backup"><?php _e('Automatically create backups before applying changes', 'wpai-assistant'); ?></label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wpai_dry_run_mode"><?php _e('Dry Run Mode', 'wpai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="wpai_dry_run_mode" id="wpai_dry_run_mode" value="1" <?php checked(get_option('wpai_dry_run_mode', false)); ?> />
                            <label for="wpai_dry_run_mode"><?php _e('Enable dry-run mode (preview only, no changes applied)', 'wpai-assistant'); ?></label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>


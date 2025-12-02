<?php
/**
 * Audit log page
 */

if (!defined('ABSPATH')) {
    exit;
}

$security = WPAI_Plugin::get_instance()->security;
$filters = array();
$audit_logs = $security->get_audit_log($filters, 100);
?>

<div class="wrap wpai-audit-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wpai-audit-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wpai-assistant-audit" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="filter_action"><?php _e('Action', 'wpai-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="filter_action" id="filter_action">
                            <option value=""><?php _e('All Actions', 'wpai-assistant'); ?></option>
                            <option value="apply_content" <?php selected(isset($_GET['filter_action']) && $_GET['filter_action'] === 'apply_content'); ?>><?php _e('Apply Content', 'wpai-assistant'); ?></option>
                            <option value="generate_post" <?php selected(isset($_GET['filter_action']) && $_GET['filter_action'] === 'generate_post'); ?>><?php _e('Generate Post', 'wpai-assistant'); ?></option>
                            <option value="restore_backup" <?php selected(isset($_GET['filter_action']) && $_GET['filter_action'] === 'restore_backup'); ?>><?php _e('Restore Backup', 'wpai-assistant'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="filter_date_from"><?php _e('Date From', 'wpai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($_GET['filter_date_from'] ?? ''); ?>" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="filter_date_to"><?php _e('Date To', 'wpai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($_GET['filter_date_to'] ?? ''); ?>" />
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Filter', 'wpai-assistant'), 'secondary', 'filter', false); ?>
            <a href="<?php echo admin_url('admin.php?page=wpai-assistant-audit'); ?>" class="button"><?php _e('Reset', 'wpai-assistant'); ?></a>
        </form>
    </div>
    
    <div class="wpai-audit-log">
        <?php if (empty($audit_logs)): ?>
            <p><?php _e('No audit log entries found.', 'wpai-assistant'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date/Time', 'wpai-assistant'); ?></th>
                        <th><?php _e('User', 'wpai-assistant'); ?></th>
                        <th><?php _e('Action', 'wpai-assistant'); ?></th>
                        <th><?php _e('Object', 'wpai-assistant'); ?></th>
                        <th><?php _e('Details', 'wpai-assistant'); ?></th>
                        <th><?php _e('IP Address', 'wpai-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_logs as $log): ?>
                        <?php
                        $user = get_userdata($log->user_id);
                        $details = json_decode($log->details, true);
                        ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                            <td><?php echo $user ? esc_html($user->display_name) : __('Unknown', 'wpai-assistant'); ?></td>
                            <td><span class="wpai-audit-action"><?php echo esc_html($log->action); ?></span></td>
                            <td>
                                <?php if ($log->object_type && $log->object_id): ?>
                                    <?php
                                    $object = get_post($log->object_id);
                                    if ($object):
                                    ?>
                                        <a href="<?php echo get_edit_post_link($log->object_id); ?>">
                                            <?php echo esc_html($object->post_title); ?> (<?php echo esc_html($log->object_type); ?> #<?php echo esc_html($log->object_id); ?>)
                                        </a>
                                    <?php else: ?>
                                        <?php echo esc_html($log->object_type); ?> #<?php echo esc_html($log->object_id); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($details)): ?>
                                    <details>
                                        <summary><?php _e('View Details', 'wpai-assistant'); ?></summary>
                                        <pre style="max-width: 400px; overflow: auto; font-size: 11px;"><?php echo esc_html(json_encode($details, JSON_PRETTY_PRINT)); ?></pre>
                                    </details>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($log->ip_address); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>


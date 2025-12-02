<?php
/**
 * Intent Detection for analyzing user input
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Intent_Detector {
    
    private $api_manager;
    
    public function __construct($api_manager = null) {
        // Accept api_manager via constructor to avoid circular dependency
        // If not provided, use lazy loading (only when actually needed)
        if ($api_manager) {
            $this->api_manager = $api_manager;
        }
    }
    
    /**
     * Get API manager (lazy loading)
     */
    private function get_api_manager() {
        if (!$this->api_manager) {
            $plugin = WPAI_Plugin::get_instance();
            $this->api_manager = $plugin->api;
        }
        return $this->api_manager;
    }
    
    /**
     * Detect intent from user input
     */
    public function detect_intent($input, $context = array()) {
        // Build prompt for intent detection
        $prompt = $this->build_intent_prompt($input, $context);
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are an intent detection system. Analyze user input and determine the intended action. Respond with JSON only.',
            ),
            array(
                'role' => 'user',
                'content' => $prompt,
            ),
        );
        
        $response = $this->get_api_manager()->send_request($messages, array('temperature' => 0.3));
        
        if (is_wp_error($response)) {
            return $this->fallback_intent_detection($input);
        }
        
        // Try to parse JSON response
        $intent_data = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($intent_data['intent'])) {
            return $intent_data;
        }
        
        // Fallback to rule-based detection
        return $this->fallback_intent_detection($input);
    }
    
    /**
     * Build intent detection prompt
     */
    private function build_intent_prompt($input, $context) {
        $prompt = "Analyze the following user input and determine the intent:\n\n";
        $prompt .= "Input: " . $input . "\n\n";
        
        if (!empty($context['current_page'])) {
            $prompt .= "Current context: Editing page/post ID " . $context['current_page'] . "\n";
        }
        
        if (!empty($context['uploaded_files'])) {
            $prompt .= "Uploaded files: " . count($context['uploaded_files']) . " file(s)\n";
        }
        
        $prompt .= "\nRespond with JSON in this format:\n";
        $prompt .= "{\n";
        $prompt .= '  "intent": "create|edit|delete|analyze|suggest",' . "\n";
        $prompt .= '  "object_type": "page|post|block|content",' . "\n";
        $prompt .= '  "action": "specific action description",' . "\n";
        $prompt .= '  "confidence": 0.0-1.0,' . "\n";
        $prompt .= '  "suggestions": ["suggestion1", "suggestion2"]' . "\n";
        $prompt .= "}\n";
        
        return $prompt;
    }
    
    /**
     * Fallback intent detection using keywords
     */
    private function fallback_intent_detection($input) {
        $input_lower = mb_strtolower($input);
        
        $intent = 'suggest';
        $object_type = 'content';
        $confidence = 0.5;
        $action = 'General content suggestion';
        $suggestions = array();
        
        // Create intent keywords
        $create_keywords = array('ایجاد', 'ساخت', 'بساز', 'new', 'create', 'make', 'build');
        $edit_keywords = array('ویرایش', 'تغییر', 'اصلاح', 'edit', 'change', 'modify', 'update');
        $delete_keywords = array('حذف', 'پاک', 'delete', 'remove');
        $analyze_keywords = array('تحلیل', 'بررسی', 'analyze', 'review', 'check');
        
        // Detect intent
        foreach ($create_keywords as $keyword) {
            if (mb_strpos($input_lower, $keyword) !== false) {
                $intent = 'create';
                $confidence = 0.7;
                $action = 'Create new content';
                break;
            }
        }
        
        foreach ($edit_keywords as $keyword) {
            if (mb_strpos($input_lower, $keyword) !== false) {
                $intent = 'edit';
                $confidence = 0.7;
                $action = 'Edit existing content';
                break;
            }
        }
        
        foreach ($delete_keywords as $keyword) {
            if (mb_strpos($input_lower, $keyword) !== false) {
                $intent = 'delete';
                $confidence = 0.6;
                $action = 'Delete content';
                break;
            }
        }
        
        foreach ($analyze_keywords as $keyword) {
            if (mb_strpos($input_lower, $keyword) !== false) {
                $intent = 'analyze';
                $confidence = 0.7;
                $action = 'Analyze content';
                break;
            }
        }
        
        // Detect object type
        if (mb_strpos($input_lower, 'صفحه') !== false || mb_strpos($input_lower, 'page') !== false) {
            $object_type = 'page';
        } elseif (mb_strpos($input_lower, 'پست') !== false || mb_strpos($input_lower, 'post') !== false) {
            $object_type = 'post';
        } elseif (mb_strpos($input_lower, 'بلاک') !== false || mb_strpos($input_lower, 'block') !== false) {
            $object_type = 'block';
        }
        
        // Generate suggestions
        if ($intent === 'create') {
            $suggestions[] = __('Create a new ' . $object_type, 'wpai-assistant');
            $suggestions[] = __('Generate content for ' . $object_type, 'wpai-assistant');
        } elseif ($intent === 'edit') {
            $suggestions[] = __('Edit the current ' . $object_type, 'wpai-assistant');
            $suggestions[] = __('Improve existing content', 'wpai-assistant');
        }
        
        return array(
            'intent' => $intent,
            'object_type' => $object_type,
            'action' => $action,
            'confidence' => $confidence,
            'suggestions' => $suggestions,
        );
    }
    
    /**
     * Get preview of suggested action
     */
    public function get_action_preview($intent_data, $input, $context = array()) {
        $preview = array(
            'intent' => $intent_data['intent'],
            'object_type' => $intent_data['object_type'],
            'action' => $intent_data['action'],
            'confidence' => $intent_data['confidence'],
            'suggestions' => $intent_data['suggestions'],
            'preview_html' => '',
            'estimated_changes' => array(),
        );
        
        // Generate preview based on intent
        switch ($intent_data['intent']) {
            case 'create':
                $preview['preview_html'] = $this->generate_create_preview($input, $context);
                break;
            case 'edit':
                $preview['preview_html'] = $this->generate_edit_preview($input, $context);
                break;
            default:
                $preview['preview_html'] = '<p>' . esc_html($intent_data['action']) . '</p>';
        }
        
        return $preview;
    }
    
    /**
     * Generate preview for create intent
     */
    private function generate_create_preview($input, $context) {
        return '<div class="wpai-preview-create">
            <h3>' . __('Create New Content', 'wpai-assistant') . '</h3>
            <p>' . __('Based on your input, a new content will be created.', 'wpai-assistant') . '</p>
            <p><strong>' . __('Input:', 'wpai-assistant') . '</strong> ' . esc_html($input) . '</p>
        </div>';
    }
    
    /**
     * Generate preview for edit intent
     */
    private function generate_edit_preview($input, $context) {
        $current_content = '';
        if (!empty($context['current_page'])) {
            $post = get_post($context['current_page']);
            if ($post) {
                $current_content = wp_trim_words($post->post_content, 50);
            }
        }
        
        return '<div class="wpai-preview-edit">
            <h3>' . __('Edit Existing Content', 'wpai-assistant') . '</h3>
            <p>' . __('The current content will be modified based on your request.', 'wpai-assistant') . '</p>
            ' . (!empty($current_content) ? '<p><strong>' . __('Current content:', 'wpai-assistant') . '</strong> ' . esc_html($current_content) . '</p>' : '') . '
        </div>';
    }
}


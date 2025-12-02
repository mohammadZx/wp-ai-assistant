<?php
/**
 * API Manager for handling different AI providers
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_API_Manager {
    
    private $provider;
    private $api_key;
    private $mirror_link;
    private $model;
    private $settings;
    private $last_response = null;
    
    public function __construct() {
        $this->load_settings();
    }
    
    /**
     * Load settings from options
     */
    private function load_settings() {
        $this->provider = get_option('wpai_api_provider', 'openai');
        $this->api_key = get_option('wpai_api_key', '');
        $this->mirror_link = get_option('wpai_mirror_link', '');
        $this->model = get_option('wpai_default_model', 'gpt-3.5-turbo');
        $this->settings = array(
            'temperature' => floatval(get_option('wpai_default_temperature', 0.7)),
            'top_p' => floatval(get_option('wpai_default_top_p', 1.0)),
            'max_tokens' => intval(get_option('wpai_default_max_tokens', 2000)),
            'frequency_penalty' => floatval(get_option('wpai_default_frequency_penalty', 0)),
            'presence_penalty' => floatval(get_option('wpai_default_presence_penalty', 0)),
        );
    }
    
    /**
     * Get available providers
     */
    public function get_providers() {
        return array(
            'openai' => array(
                'name' => 'OpenAI',
                'models' => array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo', 'gpt-4o'),
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
            ),
            'google' => array(
                'name' => 'Google Gemini',
                'models' => array(
                    'gemini-pro',
                    'gemini-pro-vision',
                    'gemini-1.5-pro',
                    'gemini-1.5-flash',
                    'gemini-2.0-flash-exp',
                    'text-bison',
                    'chat-bison'
                ),
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/',
            ),
            'custom' => array(
                'name' => 'Custom API',
                'models' => array('custom'),
                'endpoint' => '',
            ),
        );
    }
    
    /**
     * Map thinking degree to model parameters
     */
    public function map_thinking_degree($degree) {
        // Degree: 0-100 (0 = Conservative, 50 = Balanced, 100 = Creative)
        $degree = max(0, min(100, intval($degree)));
        
        return array(
            'temperature' => $degree / 100 * 1.5, // 0 to 1.5
            'top_p' => 0.5 + ($degree / 100 * 0.5), // 0.5 to 1.0
            'max_tokens' => 1000 + ($degree / 100 * 2000), // 1000 to 3000
        );
    }
    
    /**
     * Get preset profiles
     */
    public function get_presets() {
        return array(
            'conservative' => array(
                'name' => __('Conservative', 'wpai-assistant'),
                'temperature' => 0.3,
                'top_p' => 0.8,
                'max_tokens' => 1500,
                'degree' => 20,
            ),
            'balanced' => array(
                'name' => __('Balanced', 'wpai-assistant'),
                'temperature' => 0.7,
                'top_p' => 1.0,
                'max_tokens' => 2000,
                'degree' => 50,
            ),
            'creative' => array(
                'name' => __('Creative', 'wpai-assistant'),
                'temperature' => 1.2,
                'top_p' => 1.0,
                'max_tokens' => 3000,
                'degree' => 80,
            ),
        );
    }
    
    /**
     * Send request to AI API
     */
    public function send_request($messages, $custom_settings = array(), $functions = null) {
        $settings = wp_parse_args($custom_settings, $this->settings);
        
        switch ($this->provider) {
            case 'openai':
                return $this->send_openai_request($messages, $settings, $functions);
            case 'google':
                return $this->send_google_request($messages, $settings, $functions);
            case 'custom':
                return $this->send_custom_request($messages, $settings, $functions);
            default:
                return new WP_Error('invalid_provider', __('Invalid API provider', 'wpai-assistant'));
        }
    }
    
    /**
     * Send OpenAI request
     */
    private function send_openai_request($messages, $settings, $functions = null) {
        $endpoint = $this->mirror_link ?: 'https://api.openai.com/v1/chat/completions';
        
        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $settings['temperature'],
            'max_tokens' => $settings['max_tokens'],
        );
        
        if (isset($settings['top_p'])) {
            $body['top_p'] = $settings['top_p'];
        }
        
        if (isset($settings['frequency_penalty'])) {
            $body['frequency_penalty'] = $settings['frequency_penalty'];
        }
        
        if (isset($settings['presence_penalty'])) {
            $body['presence_penalty'] = $settings['presence_penalty'];
        }
        
        // Add functions if provided
        if (!empty($functions) && is_array($functions)) {
            $body['functions'] = $functions;
            $body['function_call'] = 'auto';
        }
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 120, // Increased timeout for function calling
        ));
        
        // Store response for debugging
        $this->last_response = array(
            'status_code' => wp_remote_retrieve_response_code($response),
            'headers' => wp_remote_retrieve_headers($response),
            'body' => wp_remote_retrieve_body($response),
            'is_error' => is_wp_error($response),
        );
        
        if (is_wp_error($response)) {
            $this->last_response['error'] = $response->get_error_message();
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Store parsed data
        $this->last_response['parsed_data'] = $data;
        
        // Check if data is valid array
        if (!is_array($data)) {
            return new WP_Error('invalid_response', __('Invalid JSON response from API', 'wpai-assistant'));
        }
        
        if (isset($data['error'])) {
            // Handle error - could be string or array
            $error_message = is_array($data['error']) && isset($data['error']['message']) 
                ? $data['error']['message'] 
                : (is_string($data['error']) ? $data['error'] : __('API error occurred', 'wpai-assistant'));
            return new WP_Error('api_error', $error_message);
        }
        
        // Check for function call
        if (isset($data['choices'][0]['message']['function_call'])) {
            return array(
                'type' => 'function_call',
                'function_call' => $data['choices'][0]['message']['function_call'],
            );
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return array(
                'type' => 'content',
                'content' => $data['choices'][0]['message']['content'],
            );
        }
        
        return new WP_Error('invalid_response', __('Invalid response from API', 'wpai-assistant'));
    }
    
    /**
     * Send Google request
     */
    private function send_google_request($messages, $settings, $functions = null) {
        // Ensure API key is not empty
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', __('API key is required for Google API', 'wpai-assistant'));
        }
        
        // Build base URL (similar to Laravel code)
        if ($this->mirror_link) {
            // If mirror_link is provided, use it as base URL
            // It should be like: https://messenger.arya-add.ir/mirror.php?url=https://generativelanguage.googleapis.com/v1beta/
            // Keep mirror_link as is (it should end with / like in Laravel)
            // Example: https://messenger.arya-add.ir/mirror.php?url=https://generativelanguage.googleapis.com/v1beta/
            $base_url = $this->mirror_link;
        } else {
            // Standard Google API base URL
            $base_url = 'https://generativelanguage.googleapis.com/v1beta';
        }
        
        // Build full endpoint URL (similar to Laravel: baseUrl + models/{model}:generateContent)
        // In Laravel: baseUrl ends with /, so we append directly without adding another /
        // Example: "mirror.php?url=https://...v1beta/" + "models/gemini-2.5-pro:generateContent"
        $endpoint = rtrim($base_url, '/') . '/models/' . urlencode($this->model) . ':generateContent';
        
        // Convert messages format for Google (similar to Laravel code)
        $contents = array();
        $system_instruction = null;
        
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            
            // Handle system messages separately (like Laravel code)
            if ($role === 'system') {
                if (isset($msg['content'])) {
                    $system_instruction = array(
                        'parts' => array(array('text' => $msg['content']))
                    );
                }
                continue; // Skip adding to contents
            }
            
            // Map roles: assistant -> model, user -> user, function -> function
            $api_role = ($role === 'assistant') ? 'model' : (($role === 'function') ? 'function' : 'user');
            
            // Build parts array
            $parts = array();
            if (isset($msg['content'])) {
                $parts[] = array('text' => $msg['content']);
            }
            
            // Handle function call (for Google, this is in the message)
            if (isset($msg['function_call'])) {
                $function_call = $msg['function_call'];
                $parts[] = array(
                    'functionCall' => array(
                        'name' => $function_call['name'] ?? '',
                        'args' => isset($function_call['arguments']) ? json_decode($function_call['arguments'], true) : array(),
                    ),
                );
            }
            
            // Handle function response
            if ($role === 'function' && isset($msg['name'])) {
                $parts[] = array(
                    'functionResponse' => array(
                        'name' => $msg['name'],
                        'response' => isset($msg['content']) ? json_decode($msg['content'], true) : array(),
                    ),
                );
            }
            
            // Add to contents if we have parts
            if (!empty($parts)) {
                $contents[] = array(
                    'role' => $api_role,
                    'parts' => $parts,
                );
            }
        }
        
        // Build request body (similar to Laravel)
        $body = array(
            'contents' => $contents,
            'generationConfig' => array(
                'temperature' => $settings['temperature'],
                'topP' => $settings['top_p'],
                'maxOutputTokens' => $settings['max_tokens'],
            ),
        );
        
        // Add system_instruction if exists (like Laravel code)
        if ($system_instruction !== null) {
            $body['system_instruction'] = $system_instruction;
        }
        
        // Add function calling for Google (tools)
        if (!empty($functions) && is_array($functions)) {
            $tools = array();
            foreach ($functions as $func) {
                $tools[] = array(
                    'functionDeclarations' => array(
                        array(
                            'name' => $func['name'],
                            'description' => $func['description'] ?? '',
                            'parameters' => $func['parameters'] ?? array(),
                        ),
                    ),
                );
            }
            $body['tools'] = $tools;
        }
        
        // Build URL with API key (similar to Laravel: ?key={apiKey})
        $separator = '?';
        $api_key_encoded = urlencode(trim($this->api_key));
        $url = $endpoint . $separator . 'key=' . $api_key_encoded;
        
        // Send request (similar to Laravel - using Accept header instead of Content-Type)
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 300,
            'connect_timeout' => 60,
        ));
        
        // Store response for debugging (including request URL)
        $this->last_response = array(
            'request_url' => $url,
            'request_endpoint' => $endpoint,
            'status_code' => wp_remote_retrieve_response_code($response),
            'headers' => wp_remote_retrieve_headers($response),
            'body' => wp_remote_retrieve_body($response),
            'is_error' => is_wp_error($response),
        );
        
        if (is_wp_error($response)) {
            $this->last_response['error'] = $response->get_error_message();
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Store parsed data
        $this->last_response['parsed_data'] = $data;
        
        // Check if data is valid array
        if (!is_array($data)) {
            return new WP_Error('invalid_response', __('Invalid JSON response from API', 'wpai-assistant'));
        }
        
        if (isset($data['error'])) {
            // Handle error - could be string or array
            $error_message = is_array($data['error']) && isset($data['error']['message']) 
                ? $data['error']['message'] 
                : (is_string($data['error']) ? $data['error'] : __('API error occurred', 'wpai-assistant'));
            return new WP_Error('api_error', $error_message);
        }
        
        // Check for function call (Google uses functionCall in parts)
        if (isset($data['candidates'][0]['content']['parts'])) {
            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['functionCall'])) {
                    $function_call = $part['functionCall'];
                    return array(
                        'type' => 'function_call',
                        'function_call' => array(
                            'name' => $function_call['name'] ?? '',
                            'arguments' => isset($function_call['args']) ? json_encode($function_call['args']) : '{}',
                        ),
                    );
                }
            }
        }
        
        // Check for text content in parts
        if (isset($data['candidates'][0]['content']['parts'])) {
            $text_content = '';
            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $text_content .= $part['text'];
                }
            }
            if (!empty($text_content)) {
                return array(
                    'type' => 'content',
                    'content' => $text_content,
                );
            }
        }
        
        return new WP_Error('invalid_response', __('Invalid response from API', 'wpai-assistant'));
    }
    
    /**
     * Send custom API request
     */
    private function send_custom_request($messages, $settings, $functions = null) {
        $endpoint = $this->mirror_link;
        
        if (empty($endpoint)) {
            return new WP_Error('no_endpoint', __('Custom endpoint URL is required', 'wpai-assistant'));
        }
        
        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $settings['temperature'],
            'top_p' => $settings['top_p'],
            'max_tokens' => $settings['max_tokens'],
        );
        
        $headers = array(
            'Content-Type' => 'application/json',
        );
        
        if (!empty($this->api_key)) {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }
        
        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 60,
        ));
        
        // Store response for debugging
        $this->last_response = array(
            'status_code' => wp_remote_retrieve_response_code($response),
            'headers' => wp_remote_retrieve_headers($response),
            'body' => wp_remote_retrieve_body($response),
            'is_error' => is_wp_error($response),
        );
        
        if (is_wp_error($response)) {
            $this->last_response['error'] = $response->get_error_message();
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Store parsed data
        $this->last_response['parsed_data'] = $data;
        
        // Check if data is valid array or string
        if (!is_array($data) && !is_string($data)) {
            // If JSON decode failed, try to use raw body
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('invalid_response', __('Invalid JSON response from custom API', 'wpai-assistant'));
            }
        }
        
        // If data is string, return it directly
        if (is_string($data)) {
            return $data;
        }
        
        // Check if data is array before accessing it
        if (!is_array($data)) {
            return new WP_Error('invalid_response', __('Invalid response format from custom API', 'wpai-assistant'));
        }
        
        // Check for function call
        if (isset($data['choices'][0]['message']['function_call'])) {
            return array(
                'type' => 'function_call',
                'function_call' => $data['choices'][0]['message']['function_call'],
            );
        }
        
        // Try to extract content from common response formats
        if (isset($data['choices'][0]['message']['content'])) {
            return array(
                'type' => 'content',
                'content' => $data['choices'][0]['message']['content'],
            );
        }
        
        if (isset($data['content'])) {
            return array(
                'type' => 'content',
                'content' => $data['content'],
            );
        }
        
        if (isset($data['text'])) {
            return array(
                'type' => 'content',
                'content' => $data['text'],
            );
        }
        
        return new WP_Error('invalid_response', __('Invalid response from custom API', 'wpai-assistant'));
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $messages = array(
            array(
                'role' => 'user',
                'content' => 'Hello, this is a test message.',
            ),
        );
        
        $result = $this->send_request($messages);
        
        // Get response data
        $response_data = $this->get_last_response();
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message(),
                'error_code' => $result->get_error_code(),
                'response' => $response_data,
            );
        }
        
        return array(
            'success' => true,
            'message' => $result,
            'response' => $response_data,
        );
    }
    
    /**
     * Get last API response
     */
    public function get_last_response() {
        return $this->last_response;
    }
    
    /**
     * Update settings
     */
    public function update_settings($settings) {
        $this->provider = $settings['provider'] ?? $this->provider;
        $this->api_key = $settings['api_key'] ?? $this->api_key;
        $this->mirror_link = $settings['mirror_link'] ?? $this->mirror_link;
        $this->model = $settings['model'] ?? $this->model;
        
        if (isset($settings['temperature'])) {
            $this->settings['temperature'] = floatval($settings['temperature']);
        }
        if (isset($settings['top_p'])) {
            $this->settings['top_p'] = floatval($settings['top_p']);
        }
        if (isset($settings['max_tokens'])) {
            $this->settings['max_tokens'] = intval($settings['max_tokens']);
        }
    }
}


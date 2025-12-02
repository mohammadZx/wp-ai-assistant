<?php
/**
 * Web crawler for analyzing URLs and generating suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Crawler {
    
    private $api_manager;
    private $content_generator;
    
    public function __construct($api_manager = null, $content_generator = null) {
        // Require api_manager to be passed to avoid circular dependency
        if ($api_manager) {
            $this->api_manager = $api_manager;
        } else {
            // Fallback only if plugin is already initialized (should not happen in normal flow)
            $plugin = WPAI_Plugin::get_instance();
            if ($plugin && isset($plugin->api)) {
                $this->api_manager = $plugin->api;
            }
        }
        
        // Store content_generator if provided
        if ($content_generator) {
            $this->content_generator = $content_generator;
        }
        
        $this->init_hooks();
    }
    
    /**
     * Get content generator (lazy loading - only used if not provided in constructor)
     */
    private function get_content_generator() {
        if (!$this->content_generator) {
            $plugin = WPAI_Plugin::get_instance();
            if ($plugin && isset($plugin->content_generator)) {
                $this->content_generator = $plugin->content_generator;
            }
        }
        return $this->content_generator;
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_wpai_crawl_url', array($this, 'ajax_crawl_url'));
        add_action('wp_ajax_wpai_crawl_site', array($this, 'ajax_crawl_site'));
        add_action('wp_ajax_wpai_analyze_crawl', array($this, 'ajax_analyze_crawl'));
    }
    
    /**
     * Crawl single URL
     */
    public function crawl_url($url) {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid URL', 'wpai-assistant'));
        }
        
        // Fetch content
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'WP-AI-Assistant/1.0',
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP error: %d', 'wpai-assistant'), $status_code));
        }
        
        // Extract text content
        $content = $this->extract_content($body);
        
        return array(
            'url' => $url,
            'title' => $this->extract_title($body),
            'content' => $content,
            'meta' => $this->extract_meta($body),
            'links' => $this->extract_links($body, $url),
        );
    }
    
    /**
     * Crawl multiple URLs
     */
    public function crawl_urls($urls) {
        $results = array();
        
        foreach ($urls as $url) {
            $result = $this->crawl_url($url);
            if (!is_wp_error($result)) {
                $results[] = $result;
            }
        }
        
        return $results;
    }
    
    /**
     * Crawl site (internal links)
     */
    public function crawl_site($base_url = null, $max_pages = 50) {
        if (!$base_url) {
            $base_url = home_url();
        }
        
        $visited = array();
        $to_visit = array($base_url);
        $results = array();
        
        while (!empty($to_visit) && count($results) < $max_pages) {
            $url = array_shift($to_visit);
            
            if (in_array($url, $visited)) {
                continue;
            }
            
            $visited[] = $url;
            
            $result = $this->crawl_url($url);
            
            if (is_wp_error($result)) {
                continue;
            }
            
            $results[] = $result;
            
            // Add internal links to queue
            foreach ($result['links'] as $link) {
                if (strpos($link, $base_url) === 0 && !in_array($link, $visited) && !in_array($link, $to_visit)) {
                    $to_visit[] = $link;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Analyze crawled content and generate suggestions
     */
    public function analyze_and_suggest($crawl_results, $context = array()) {
        $suggestions = array();
        
        foreach ($crawl_results as $result) {
            // Analyze content with AI
            $analysis_prompt = "Analyze the following webpage content and provide suggestions for improvement:\n\n";
            $analysis_prompt .= "Title: " . $result['title'] . "\n\n";
            $analysis_prompt .= "Content: " . wp_trim_words($result['content'], 500) . "\n\n";
            $analysis_prompt .= "Provide suggestions for:\n";
            $analysis_prompt .= "1. SEO improvements\n";
            $analysis_prompt .= "2. Content quality enhancements\n";
            $analysis_prompt .= "3. User experience improvements\n";
            $analysis_prompt .= "4. Missing information\n";
            $analysis_prompt .= "Format as JSON with keys: seo, content, ux, missing";
            
            $messages = array(
                array(
                    'role' => 'system',
                    'content' => 'You are a web content analyst. Provide structured suggestions in JSON format.',
                ),
                array(
                    'role' => 'user',
                    'content' => $analysis_prompt,
                ),
            );
            
            $response = $this->api_manager->send_request($messages, array('temperature' => 0.5));
            
            if (!is_wp_error($response)) {
                $analysis = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $suggestions[] = array(
                        'url' => $result['url'],
                        'title' => $result['title'],
                        'analysis' => $analysis,
                        'suggested_content' => $this->generate_suggested_content($result, $analysis),
                    );
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Generate suggested content based on analysis
     */
    private function generate_suggested_content($crawl_result, $analysis) {
        $prompt = "Based on the following analysis, generate improved content:\n\n";
        $prompt .= "Original Title: " . $crawl_result['title'] . "\n";
        $prompt .= "Original Content: " . wp_trim_words($crawl_result['content'], 300) . "\n\n";
        $prompt .= "Analysis: " . json_encode($analysis, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "Generate improved content that addresses the suggestions.";
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are a content improvement generator. Create better content based on analysis.',
            ),
            array(
                'role' => 'user',
                'content' => $prompt,
            ),
        );
        
        return $this->api_manager->send_request($messages);
    }
    
    /**
     * Extract text content from HTML
     */
    private function extract_content($html) {
        // Remove scripts and styles
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Convert to text
        $text = wp_strip_all_tags($html);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Extract title from HTML
     */
    private function extract_title($html) {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
    
    /**
     * Extract meta tags
     */
    private function extract_meta($html) {
        $meta = array();
        
        // Meta description
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $meta['description'] = $matches[1];
        }
        
        // Meta keywords
        if (preg_match('/<meta\s+name=["\']keywords["\']\s+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $meta['keywords'] = $matches[1];
        }
        
        return $meta;
    }
    
    /**
     * Extract links from HTML
     */
    private function extract_links($html, $base_url) {
        $links = array();
        
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $link) {
                // Convert relative URLs to absolute
                if (strpos($link, 'http') !== 0) {
                    $link = rtrim($base_url, '/') . '/' . ltrim($link, '/');
                }
                $links[] = $link;
            }
        }
        
        return array_unique($links);
    }
    
    /**
     * AJAX: Crawl URL
     */
    public function ajax_crawl_url() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $url = esc_url_raw($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error(array('message' => __('URL is required', 'wpai-assistant')));
        }
        
        $result = $this->crawl_url($url);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Crawl site
     */
    public function ajax_crawl_site() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $base_url = esc_url_raw($_POST['base_url'] ?? home_url());
        $max_pages = intval($_POST['max_pages'] ?? 50);
        
        $results = $this->crawl_site($base_url, $max_pages);
        
        wp_send_json_success(array('results' => $results, 'count' => count($results)));
    }
    
    /**
     * AJAX: Analyze crawl
     */
    public function ajax_analyze_crawl() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $crawl_data = json_decode(stripslashes($_POST['crawl_data'] ?? '[]'), true);
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
        
        $suggestions = $this->analyze_and_suggest($crawl_data, $context);
        
        wp_send_json_success(array('suggestions' => $suggestions));
    }
}


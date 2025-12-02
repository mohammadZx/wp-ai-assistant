<?php
/**
 * Function Handler for AI function calling
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Function_Handler {
    
    private $content_generator;
    private $security;
    
    public function __construct($content_generator = null, $security = null) {
        $this->content_generator = $content_generator;
        $this->security = $security;
    }
    
    /**
     * Get available functions for AI
     */
    public function get_functions() {
        return array(
            array(
                'name' => 'create_post',
                'description' => 'Create a new WordPress post or page. Can create Gutenberg blocks or Elementor pages.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'title' => array(
                            'type' => 'string',
                            'description' => 'The title of the post or page',
                        ),
                        'content' => array(
                            'type' => 'string',
                            'description' => 'The content of the post/page. For Gutenberg, use block format. For Elementor, use JSON structure.',
                        ),
                        'post_type' => array(
                            'type' => 'string',
                            'enum' => array('post', 'page'),
                            'description' => 'Type of content: post or page',
                            'default' => 'post',
                        ),
                        'editor_type' => array(
                            'type' => 'string',
                            'enum' => array('gutenberg', 'elementor', 'classic'),
                            'description' => 'Editor type: gutenberg (block editor), elementor, or classic',
                            'default' => 'gutenberg',
                        ),
                        'status' => array(
                            'type' => 'string',
                            'enum' => array('draft', 'publish', 'pending'),
                            'description' => 'Post status',
                            'default' => 'draft',
                        ),
                        'categories' => array(
                            'type' => 'array',
                            'items' => array('type' => 'integer'),
                            'description' => 'Array of category IDs (for posts only)',
                        ),
                        'tags' => array(
                            'type' => 'array',
                            'items' => array('type' => 'string'),
                            'description' => 'Array of tag names (for posts only)',
                        ),
                        'featured_image_url' => array(
                            'type' => 'string',
                            'description' => 'URL of featured image (will be downloaded and set)',
                        ),
                        'meta' => array(
                            'type' => 'object',
                            'description' => 'Custom meta fields (e.g., SEO meta)',
                        ),
                    ),
                    'required' => array('title', 'content'),
                ),
            ),
            array(
                'name' => 'edit_post',
                'description' => 'Edit an existing WordPress post or page. Can edit content, title, meta, or specific parts of Elementor pages.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_id' => array(
                            'type' => 'integer',
                            'description' => 'The ID of the post/page to edit',
                        ),
                        'title' => array(
                            'type' => 'string',
                            'description' => 'New title (optional)',
                        ),
                        'content' => array(
                            'type' => 'string',
                            'description' => 'New content or partial content to replace. For Elementor, provide JSON structure or section/widget to update.',
                        ),
                        'editor_type' => array(
                            'type' => 'string',
                            'enum' => array('gutenberg', 'elementor', 'classic'),
                            'description' => 'Editor type of the post',
                        ),
                        'edit_type' => array(
                            'type' => 'string',
                            'enum' => array('full', 'partial', 'meta', 'seo'),
                            'description' => 'Type of edit: full (replace all), partial (edit specific part), meta (edit meta only), seo (edit SEO only)',
                            'default' => 'full',
                        ),
                        'meta' => array(
                            'type' => 'object',
                            'description' => 'Meta fields to update (e.g., _yoast_wpseo_title, _yoast_wpseo_metadesc for SEO)',
                        ),
                    ),
                    'required' => array('post_id'),
                ),
            ),
            array(
                'name' => 'search_posts',
                'description' => 'Search for posts or pages by title, content, or ID. Useful for finding posts to edit.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query' => array(
                            'type' => 'string',
                            'description' => 'Search query (title or content)',
                        ),
                        'post_type' => array(
                            'type' => 'string',
                            'enum' => array('post', 'page', 'any'),
                            'description' => 'Type of posts to search',
                            'default' => 'any',
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'Maximum number of results',
                            'default' => 10,
                        ),
                    ),
                ),
            ),
            array(
                'name' => 'get_free_image',
                'description' => 'Get a free image from Unsplash or Pexels based on a search query. Returns image URL and can optionally download and attach to WordPress media library.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query' => array(
                            'type' => 'string',
                            'description' => 'Search query for the image',
                        ),
                        'download' => array(
                            'type' => 'boolean',
                            'description' => 'Whether to download and add to WordPress media library',
                            'default' => true,
                        ),
                        'source' => array(
                            'type' => 'string',
                            'enum' => array('unsplash', 'pexels'),
                            'description' => 'Image source',
                            'default' => 'unsplash',
                        ),
                    ),
                    'required' => array('query'),
                ),
            ),
            array(
                'name' => 'get_post_content',
                'description' => 'Get the full content of a post or page, including Elementor data if applicable.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_id' => array(
                            'type' => 'integer',
                            'description' => 'The ID of the post/page',
                        ),
                    ),
                    'required' => array('post_id'),
                ),
            ),
            array(
                'name' => 'crawl_url_for_page',
                'description' => 'Crawl a URL to extract content, structure, and design. Then create a similar page with Elementor. Useful for creating pages based on existing websites.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'url' => array(
                            'type' => 'string',
                            'description' => 'The URL to crawl and analyze',
                        ),
                        'create_page' => array(
                            'type' => 'boolean',
                            'description' => 'Whether to automatically create a page based on the crawled content',
                            'default' => true,
                        ),
                        'editor_type' => array(
                            'type' => 'string',
                            'enum' => array('elementor', 'gutenberg'),
                            'description' => 'Editor type for the created page',
                            'default' => 'elementor',
                        ),
                    ),
                    'required' => array('url'),
                ),
            ),
            array(
                'name' => 'analyze_image',
                'description' => 'Analyze an image URL using Vision API to understand its content, layout, and design. Then create a similar Elementor page based on the image.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'image_url' => array(
                            'type' => 'string',
                            'description' => 'URL of the image to analyze',
                        ),
                        'create_page' => array(
                            'type' => 'boolean',
                            'description' => 'Whether to automatically create a page based on the image analysis',
                            'default' => true,
                        ),
                    ),
                    'required' => array('image_url'),
                ),
            ),
            array(
                'name' => 'generate_image',
                'description' => 'Generate an image using DALL-E or other image generation API based on a text description. Returns image URL and can download to media library.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'prompt' => array(
                            'type' => 'string',
                            'description' => 'Text description of the image to generate',
                        ),
                        'size' => array(
                            'type' => 'string',
                            'enum' => array('256x256', '512x512', '1024x1024', '1024x1792', '1792x1024'),
                            'description' => 'Size of the generated image',
                            'default' => '1024x1024',
                        ),
                        'download' => array(
                            'type' => 'boolean',
                            'description' => 'Whether to download and add to WordPress media library',
                            'default' => true,
                        ),
                    ),
                    'required' => array('prompt'),
                ),
            ),
        );
    }
    
    /**
     * Execute a function call
     */
    public function execute_function($function_name, $arguments) {
        switch ($function_name) {
            case 'create_post':
                return $this->create_post($arguments);
            case 'edit_post':
                return $this->edit_post($arguments);
            case 'search_posts':
                return $this->search_posts($arguments);
            case 'get_free_image':
                return $this->get_free_image($arguments);
            case 'get_post_content':
                return $this->get_post_content($arguments);
            case 'crawl_url_for_page':
                return $this->crawl_url_for_page($arguments);
            case 'analyze_image':
                return $this->analyze_image($arguments);
            case 'generate_image':
                return $this->generate_image($arguments);
            default:
                return new WP_Error('unknown_function', sprintf(__('Unknown function: %s', 'wpai-assistant'), $function_name));
        }
    }
    
    /**
     * Create a new post or page
     */
    private function create_post($args) {
        $title = sanitize_text_field($args['title'] ?? '');
        $content = $args['content'] ?? '';
        $post_type = sanitize_text_field($args['post_type'] ?? 'post');
        $editor_type = sanitize_text_field($args['editor_type'] ?? 'gutenberg');
        $status = sanitize_text_field($args['status'] ?? 'draft');
        $categories = $args['categories'] ?? array();
        $tags = $args['tags'] ?? array();
        $featured_image_url = esc_url_raw($args['featured_image_url'] ?? '');
        $meta = $args['meta'] ?? array();
        
        if (empty($title) || empty($content)) {
            return new WP_Error('missing_params', __('Title and content are required', 'wpai-assistant'));
        }
        
        // Handle Elementor pages
        if ($editor_type === 'elementor') {
            return $this->create_elementor_page($title, $content, $post_type, $status, $meta, $featured_image_url);
        }
        
        // Handle Gutenberg or classic
        $post_data = array(
            'post_title' => $title,
            'post_content' => wp_kses_post($content),
            'post_type' => $post_type,
            'post_status' => $status,
            'post_author' => get_current_user_id(),
        );
        
        if ($post_type === 'post' && !empty($categories)) {
            $post_data['post_category'] = array_map('intval', $categories);
        }
        
        if ($post_type === 'post' && !empty($tags)) {
            $post_data['tags_input'] = $tags;
        }
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Set featured image if provided
        if (!empty($featured_image_url)) {
            $this->set_featured_image_from_url($post_id, $featured_image_url);
        }
        
        // Set meta fields
        if (!empty($meta)) {
            foreach ($meta as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Log action
        if ($this->security) {
            $this->security->log_action('create_post', $post_type, $post_id, array(
                'title' => $title,
                'editor_type' => $editor_type,
            ));
        }
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'title' => $title,
            'edit_link' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'view_link' => get_permalink($post_id),
        );
    }
    
    /**
     * Create Elementor page
     */
    private function create_elementor_page($title, $content, $post_type, $status, $meta, $featured_image_url) {
        // Create the post first
        $post_data = array(
            'post_title' => $title,
            'post_content' => '', // Elementor stores content in meta
            'post_type' => $post_type,
            'post_status' => $status,
            'post_author' => get_current_user_id(),
        );
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Check if Elementor is active
        if (!did_action('elementor/loaded')) {
            // Elementor not active, save as regular content
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => wp_kses_post($content),
            ));
        } else {
            // Set Elementor template
            update_post_meta($post_id, '_elementor_edit_mode', 'builder');
            update_post_meta($post_id, '_elementor_template_type', 'wp-page');
            
            // Parse and save Elementor data
            $elementor_data = $this->parse_elementor_content($content);
            if ($elementor_data) {
                update_post_meta($post_id, '_elementor_data', wp_slash(json_encode($elementor_data)));
            } else {
                // Fallback: create simple Elementor structure
                $elementor_data = $this->create_simple_elementor_structure($content);
                update_post_meta($post_id, '_elementor_data', wp_slash(json_encode($elementor_data)));
            }
            
            // Set Elementor version
            update_post_meta($post_id, '_elementor_version', '0.4');
            update_post_meta($post_id, '_elementor_pro_version', '0');
        }
        
        // Set featured image
        if (!empty($featured_image_url)) {
            $this->set_featured_image_from_url($post_id, $featured_image_url);
        }
        
        // Set meta fields
        if (!empty($meta)) {
            foreach ($meta as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Log action
        if ($this->security) {
            $this->security->log_action('create_elementor_page', $post_type, $post_id, array(
                'title' => $title,
            ));
        }
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'title' => $title,
            'editor_type' => 'elementor',
            'edit_link' => admin_url('post.php?post=' . $post_id . '&action=elementor'),
            'view_link' => get_permalink($post_id),
        );
    }
    
    /**
     * Parse Elementor content (JSON or structured text)
     */
    private function parse_elementor_content($content) {
        // Try to parse as JSON first
        $json = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }
        
        // If not JSON, return null to use simple structure
        return null;
    }
    
    /**
     * Create simple Elementor structure from content
     * Improved to support complex landing pages
     */
    private function create_simple_elementor_structure($content) {
        // Try to parse as structured Elementor JSON first
        $parsed = $this->parse_elementor_content($content);
        if ($parsed && is_array($parsed)) {
            return $parsed;
        }
        
        // If content contains Elementor structure hints, parse them
        if (strpos($content, '<!-- wp:elementor') !== false || strpos($content, 'elementor-section') !== false) {
            return $this->parse_elementor_from_text($content);
        }
        
        // Create a proper Elementor landing page structure
        $sections = array();
        
        // Hero Section
        $sections[] = array(
            'id' => wp_generate_uuid4(),
            'elType' => 'section',
            'settings' => array(
                'layout' => 'boxed',
                'background_background' => 'classic',
            ),
            'elements' => array(
                array(
                    'id' => wp_generate_uuid4(),
                    'elType' => 'column',
                    'settings' => array(
                        '_column_size' => 100,
                    ),
                    'elements' => array(
                        array(
                            'id' => wp_generate_uuid4(),
                            'elType' => 'widget',
                            'widgetType' => 'heading',
                            'settings' => array(
                                'title' => $this->extract_title_from_content($content),
                                'size' => 'h1',
                                'align' => 'center',
                            ),
                        ),
                        array(
                            'id' => wp_generate_uuid4(),
                            'elType' => 'widget',
                            'widgetType' => 'text-editor',
                            'settings' => array(
                                'editor' => wp_kses_post($content),
                                'align' => 'center',
                            ),
                        ),
                    ),
                ),
            ),
        );
        
        return $sections;
    }
    
    /**
     * Parse Elementor structure from text description
     */
    private function parse_elementor_from_text($text) {
        $sections = array();
        $lines = explode("\n", $text);
        $current_section = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Detect section headers
            if (preg_match('/^(#+)\s*(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $title = $matches[2];
                
                // Close previous section
                if ($current_section) {
                    $sections[] = $current_section;
                }
                
                // Create new section
                $current_section = $this->create_elementor_section('content', array(
                    'title' => $title,
                    'level' => $level,
                ));
            } elseif ($current_section) {
                // Add content to current section
                $widget = &$current_section['elements'][0]['elements'];
                if (!empty($widget[0]['settings']['editor'])) {
                    $widget[0]['settings']['editor'] .= "\n" . $line;
                } else {
                    $widget[0]['settings']['editor'] = $line;
                }
            }
        }
        
        if ($current_section) {
            $sections[] = $current_section;
        }
        
        return !empty($sections) ? $sections : $this->create_simple_elementor_structure($text);
    }
    
    /**
     * Extract title from content
     */
    private function extract_title_from_content($content) {
        // Try to find H1 or first line
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches)) {
            return wp_strip_all_tags($matches[1]);
        }
        if (preg_match('/^#+\s*(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }
        // Get first sentence
        $first_line = explode("\n", wp_strip_all_tags($content))[0];
        return wp_trim_words($first_line, 10);
    }
    
    /**
     * Edit an existing post or page
     */
    private function edit_post($args) {
        $post_id = intval($args['post_id'] ?? 0);
        $title = isset($args['title']) ? sanitize_text_field($args['title']) : null;
        $content = $args['content'] ?? null;
        $editor_type = sanitize_text_field($args['editor_type'] ?? '');
        $edit_type = sanitize_text_field($args['edit_type'] ?? 'full');
        $meta = $args['meta'] ?? array();
        
        if (!$post_id) {
            return new WP_Error('missing_post_id', __('Post ID is required', 'wpai-assistant'));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'wpai-assistant'));
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('permission_denied', __('Permission denied', 'wpai-assistant'));
        }
        
        // Create backup
        if ($this->security) {
            $backup = $this->security->create_backup($post_id);
        }
        
        $update_data = array('ID' => $post_id);
        
        // Update title if provided
        if ($title !== null) {
            $update_data['post_title'] = $title;
        }
        
        // Handle different edit types
        if ($edit_type === 'meta' || $edit_type === 'seo') {
            // Only update meta
            if (!empty($meta)) {
                foreach ($meta as $key => $value) {
                    update_post_meta($post_id, $key, $value);
                }
            }
            return array(
                'success' => true,
                'post_id' => $post_id,
                'edit_type' => $edit_type,
            );
        }
        
        // Handle Elementor pages
        if ($editor_type === 'elementor' || get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder') {
            return $this->edit_elementor_page($post_id, $content, $edit_type, $title, $meta);
        }
        
        // Handle Gutenberg/classic
        if ($content !== null) {
            if ($edit_type === 'partial') {
                // Partial edit - append or replace specific parts
                $current_content = $post->post_content;
                $update_data['post_content'] = $this->apply_partial_edit($current_content, $content);
            } else {
                // Full edit
                $update_data['post_content'] = wp_kses_post($content);
            }
        }
        
        // Update post
        if (!empty($update_data) && count($update_data) > 1) {
            $result = wp_update_post($update_data, true);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        
        // Update meta
        if (!empty($meta)) {
            foreach ($meta as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Log action
        if ($this->security) {
            $this->security->log_action('edit_post', $post->post_type, $post_id, array(
                'edit_type' => $edit_type,
            ));
        }
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'edit_type' => $edit_type,
            'edit_link' => admin_url('post.php?post=' . $post_id . '&action=edit'),
        );
    }
    
    /**
     * Edit Elementor page
     */
    private function edit_elementor_page($post_id, $content, $edit_type, $title, $meta) {
        if (!did_action('elementor/loaded')) {
            // Elementor not active, fallback to regular edit
            if ($content !== null) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => wp_kses_post($content),
                ));
            }
            if ($title !== null) {
                wp_update_post(array('ID' => $post_id, 'post_title' => $title));
            }
            return array('success' => true, 'post_id' => $post_id);
        }
        
        // Get current Elementor data
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        $current_data = !empty($elementor_data) ? json_decode($elementor_data, true) : array();
        
        if ($edit_type === 'partial' && $content !== null) {
            // Partial edit - try to update specific sections/widgets
            $new_data = $this->parse_elementor_content($content);
            if ($new_data) {
                // Merge or replace specific parts
                $current_data = $this->merge_elementor_data($current_data, $new_data);
            }
        } elseif ($content !== null) {
            // Full edit
            $new_data = $this->parse_elementor_content($content);
            if ($new_data) {
                $current_data = $new_data;
            } else {
                // Create simple structure
                $current_data = $this->create_simple_elementor_structure($content);
            }
        }
        
        // Update Elementor data
        if (!empty($current_data)) {
            update_post_meta($post_id, '_elementor_data', wp_slash(json_encode($current_data)));
        }
        
        // Update title if provided
        if ($title !== null) {
            wp_update_post(array('ID' => $post_id, 'post_title' => $title));
        }
        
        // Update meta
        if (!empty($meta)) {
            foreach ($meta as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'editor_type' => 'elementor',
            'edit_link' => admin_url('post.php?post=' . $post_id . '&action=elementor'),
        );
    }
    
    /**
     * Merge Elementor data (simple merge for now)
     */
    private function merge_elementor_data($current, $new) {
        // Simple merge - in production, implement more sophisticated merging
        return $new;
    }
    
    /**
     * Apply partial edit to content
     */
    private function apply_partial_edit($current_content, $new_content) {
        // Simple implementation - replace content
        // In production, implement more sophisticated partial editing
        return wp_kses_post($new_content);
    }
    
    /**
     * Search posts
     */
    private function search_posts($args) {
        $query = sanitize_text_field($args['query'] ?? '');
        $post_type = sanitize_text_field($args['post_type'] ?? 'any');
        $limit = intval($args['limit'] ?? 10);
        
        $search_args = array(
            'post_type' => $post_type === 'any' ? 'any' : $post_type,
            'posts_per_page' => $limit,
            'post_status' => 'any',
            'orderby' => 'relevance',
        );
        
        if (!empty($query)) {
            $search_args['s'] = $query;
        }
        
        $posts = get_posts($search_args);
        
        $results = array();
        foreach ($posts as $post) {
            $editor_type = 'gutenberg';
            if (get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder') {
                $editor_type = 'elementor';
            }
            
            $results[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'status' => $post->post_status,
                'editor_type' => $editor_type,
                'edit_link' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                'view_link' => get_permalink($post->ID),
            );
        }
        
        return array(
            'success' => true,
            'count' => count($results),
            'posts' => $results,
        );
    }
    
    /**
     * Get free image from Unsplash or Pexels
     */
    private function get_free_image($args) {
        $query = sanitize_text_field($args['query'] ?? '');
        $download = isset($args['download']) ? (bool) $args['download'] : true;
        $source = sanitize_text_field($args['source'] ?? 'unsplash');
        
        if (empty($query)) {
            return new WP_Error('missing_query', __('Search query is required', 'wpai-assistant'));
        }
        
        if ($source === 'unsplash') {
            return $this->get_unsplash_image($query, $download);
        } elseif ($source === 'pexels') {
            return $this->get_pexels_image($query, $download);
        }
        
        return new WP_Error('invalid_source', __('Invalid image source', 'wpai-assistant'));
    }
    
    /**
     * Get image from Unsplash
     */
    private function get_unsplash_image($query, $download) {
        // Use Unsplash Source API (no key required for basic usage)
        $url = 'https://source.unsplash.com/1600x900/?' . urlencode($query);
        
        // For better results, we can use a random image approach
        // In production, you might want to use Unsplash API with a key
        $api_url = 'https://api.unsplash.com/photos/random?query=' . urlencode($query) . '&client_id=YOUR_ACCESS_KEY';
        
        // For now, return the source URL
        // If download is true, download and add to media library
        if ($download) {
            $attachment_id = $this->download_image_to_media($url, $query);
            if (!is_wp_error($attachment_id)) {
                return array(
                    'success' => true,
                    'url' => wp_get_attachment_url($attachment_id),
                    'attachment_id' => $attachment_id,
                    'source' => 'unsplash',
                );
            }
        }
        
        return array(
            'success' => true,
            'url' => $url,
            'source' => 'unsplash',
            'note' => 'For better results, configure Unsplash API key',
        );
    }
    
    /**
     * Get image from Pexels
     */
    private function get_pexels_image($query, $download) {
        // Pexels requires API key, but we can use a placeholder
        // In production, add Pexels API key to settings
        $api_key = get_option('wpai_pexels_api_key', '');
        
        if (empty($api_key)) {
            // Fallback to a simple approach
            $url = 'https://images.pexels.com/photos/random?query=' . urlencode($query);
            
            if ($download) {
                $attachment_id = $this->download_image_to_media($url, $query);
                if (!is_wp_error($attachment_id)) {
                    return array(
                        'success' => true,
                        'url' => wp_get_attachment_url($attachment_id),
                        'attachment_id' => $attachment_id,
                        'source' => 'pexels',
                    );
                }
            }
            
            return array(
                'success' => true,
                'url' => $url,
                'source' => 'pexels',
                'note' => 'Configure Pexels API key for better results',
            );
        }
        
        // Use Pexels API
        $api_url = 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=1';
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => $api_key,
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['photos'][0]['src']['large'])) {
            $image_url = $data['photos'][0]['src']['large'];
            
            if ($download) {
                $attachment_id = $this->download_image_to_media($image_url, $query);
                if (!is_wp_error($attachment_id)) {
                    return array(
                        'success' => true,
                        'url' => wp_get_attachment_url($attachment_id),
                        'attachment_id' => $attachment_id,
                        'source' => 'pexels',
                    );
                }
            }
            
            return array(
                'success' => true,
                'url' => $image_url,
                'source' => 'pexels',
            );
        }
        
        return new WP_Error('no_image_found', __('No image found', 'wpai-assistant'));
    }
    
    /**
     * Download image to WordPress media library
     */
    private function download_image_to_media($url, $filename) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        $file_array = array(
            'name' => sanitize_file_name($filename . '.jpg'),
            'tmp_name' => $tmp,
        );
        
        $id = media_handle_sideload($file_array, 0);
        
        if (is_wp_error($id)) {
            @unlink($tmp);
            return $id;
        }
        
        return $id;
    }
    
    /**
     * Set featured image from URL
     */
    private function set_featured_image_from_url($post_id, $url) {
        $attachment_id = $this->download_image_to_media($url, 'featured-' . $post_id);
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            return $attachment_id;
        }
        return false;
    }
    
    /**
     * Get post content
     */
    private function get_post_content($args) {
        $post_id = intval($args['post_id'] ?? 0);
        
        if (!$post_id) {
            return new WP_Error('missing_post_id', __('Post ID is required', 'wpai-assistant'));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'wpai-assistant'));
        }
        
        $editor_type = 'gutenberg';
        $elementor_data = null;
        
        if (get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder') {
            $editor_type = 'elementor';
            $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        }
        
        // Get SEO meta
        $seo_meta = array();
        $seo_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $seo_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if ($seo_title) $seo_meta['_yoast_wpseo_title'] = $seo_title;
        if ($seo_desc) $seo_meta['_yoast_wpseo_metadesc'] = $seo_desc;
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'editor_type' => $editor_type,
            'elementor_data' => $elementor_data,
            'meta' => $seo_meta,
            'status' => $post->post_status,
        );
    }
    
    /**
     * Crawl URL and create similar page
     */
    private function crawl_url_for_page($args) {
        $url = esc_url_raw($args['url'] ?? '');
        $create_page = isset($args['create_page']) ? (bool) $args['create_page'] : true;
        $editor_type = sanitize_text_field($args['editor_type'] ?? 'elementor');
        
        if (empty($url)) {
            return new WP_Error('missing_url', __('URL is required', 'wpai-assistant'));
        }
        
        // Use crawler to get content
        $crawler = new WPAI_Crawler();
        $crawl_result = $crawler->crawl_url($url);
        
        if (is_wp_error($crawl_result)) {
            return $crawl_result;
        }
        
        // Extract structure information
        $structure_info = array(
            'title' => $crawl_result['title'] ?? '',
            'content' => $crawl_result['content'] ?? '',
            'meta' => $crawl_result['meta'] ?? array(),
            'has_header' => false,
            'has_footer' => false,
            'sections' => array(),
        );
        
        // Analyze HTML structure if available
        $response = wp_remote_get($url, array('timeout' => 30));
        if (!is_wp_error($response)) {
            $html = wp_remote_retrieve_body($response);
            $structure_info['html_structure'] = $this->analyze_html_structure($html);
        }
        
        if (!$create_page) {
            return array(
                'success' => true,
                'crawled_data' => $structure_info,
                'message' => __('URL crawled successfully. Use create_post with this data.', 'wpai-assistant'),
            );
        }
        
        // Generate Elementor structure from crawled data
        $elementor_content = $this->generate_elementor_from_crawl($structure_info);
        
        // Create page
        return $this->create_post(array(
            'title' => $structure_info['title'] ?: 'Page from ' . parse_url($url, PHP_URL_HOST),
            'content' => json_encode($elementor_content),
            'post_type' => 'page',
            'editor_type' => $editor_type,
            'status' => 'draft',
            'meta' => array(
                '_wpai_source_url' => $url,
            ),
        ));
    }
    
    /**
     * Analyze image using Vision API
     */
    private function analyze_image($args) {
        $image_url = esc_url_raw($args['image_url'] ?? '');
        $create_page = isset($args['create_page']) ? (bool) $args['create_page'] : true;
        
        if (empty($image_url)) {
            return new WP_Error('missing_image_url', __('Image URL is required', 'wpai-assistant'));
        }
        
        // Download image temporarily
        $tmp_file = download_url($image_url);
        if (is_wp_error($tmp_file)) {
            return $tmp_file;
        }
        
        // Convert to base64 for Vision API
        $image_data = file_get_contents($tmp_file);
        $base64_image = base64_encode($image_data);
        @unlink($tmp_file);
        
        // Use OpenAI Vision API or Google Vision
        $api_key = get_option('wpai_api_key', '');
        $provider = get_option('wpai_api_provider', 'openai');
        
        if ($provider === 'openai' && !empty($api_key)) {
            $analysis = $this->analyze_image_openai($base64_image, $image_url);
        } elseif ($provider === 'google') {
            $analysis = $this->analyze_image_google($base64_image, $image_url);
        } else {
            // Fallback: try to get basic info
            $analysis = array(
                'description' => __('Image analysis requires API key. Please configure OpenAI or Google API.', 'wpai-assistant'),
                'layout' => 'unknown',
            );
        }
        
        if (is_wp_error($analysis)) {
            return $analysis;
        }
        
        if (!$create_page) {
            return array(
                'success' => true,
                'analysis' => $analysis,
                'message' => __('Image analyzed. Use create_post with Elementor editor to create page.', 'wpai-assistant'),
            );
        }
        
        // Generate Elementor structure from image analysis
        $elementor_content = $this->generate_elementor_from_image($analysis);
        
        // Create page
        return $this->create_post(array(
            'title' => $analysis['title'] ?? 'Page from Image',
            'content' => json_encode($elementor_content),
            'post_type' => 'page',
            'editor_type' => 'elementor',
            'status' => 'draft',
            'featured_image_url' => $image_url,
            'meta' => array(
                '_wpai_source_image' => $image_url,
            ),
        ));
    }
    
    /**
     * Generate image using DALL-E
     */
    private function generate_image($args) {
        $prompt = sanitize_text_field($args['prompt'] ?? '');
        $size = sanitize_text_field($args['size'] ?? '1024x1024');
        $download = isset($args['download']) ? (bool) $args['download'] : true;
        
        if (empty($prompt)) {
            return new WP_Error('missing_prompt', __('Prompt is required', 'wpai-assistant'));
        }
        
        $api_key = get_option('wpai_api_key', '');
        $provider = get_option('wpai_api_provider', 'openai');
        
        if ($provider !== 'openai' || empty($api_key)) {
            // Fallback to free image search
            return $this->get_free_image(array(
                'query' => $prompt,
                'download' => $download,
                'source' => 'unsplash',
            ));
        }
        
        // Use DALL-E API
        $endpoint = 'https://api.openai.com/v1/images/generations';
        $mirror_link = get_option('wpai_mirror_link', '');
        if (!empty($mirror_link)) {
            // If mirror link is for images, use it
            $endpoint = rtrim($mirror_link, '/') . '/v1/images/generations';
        }
        
        $body = array(
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'response_format' => 'url',
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message'] ?? __('Image generation failed', 'wpai-assistant'));
        }
        
        if (isset($data['data'][0]['url'])) {
            $image_url = $data['data'][0]['url'];
            
            if ($download) {
                $attachment_id = $this->download_image_to_media($image_url, sanitize_file_name($prompt));
                if (!is_wp_error($attachment_id)) {
                    return array(
                        'success' => true,
                        'url' => wp_get_attachment_url($attachment_id),
                        'attachment_id' => $attachment_id,
                        'source' => 'dalle',
                        'prompt' => $prompt,
                    );
                }
            }
            
            return array(
                'success' => true,
                'url' => $image_url,
                'source' => 'dalle',
                'prompt' => $prompt,
            );
        }
        
        return new WP_Error('no_image_generated', __('Failed to generate image', 'wpai-assistant'));
    }
    
    /**
     * Analyze image using OpenAI Vision API
     */
    private function analyze_image_openai($base64_image, $image_url) {
        $api_key = get_option('wpai_api_key', '');
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $mirror_link = get_option('wpai_mirror_link', '');
        if (!empty($mirror_link) && strpos($mirror_link, 'chat/completions') === false) {
            $endpoint = rtrim($mirror_link, '/') . '/v1/chat/completions';
        }
        
        $messages = array(
            array(
                'role' => 'user',
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => 'Analyze this image and describe: 1) The layout and structure (header, sections, footer), 2) Colors and design style, 3) Text content if any, 4) Overall purpose (landing page, product page, etc.). Format as JSON with keys: title, description, layout, colors, sections, purpose.',
                    ),
                    array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => 'data:image/jpeg;base64,' . $base64_image,
                        ),
                    ),
                ),
            ),
        );
        
        $body = array(
            'model' => 'gpt-4-vision-preview',
            'messages' => $messages,
            'max_tokens' => 1000,
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message'] ?? __('Image analysis failed', 'wpai-assistant'));
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            $analysis = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $analysis;
            }
            // If not JSON, parse as text
            return array(
                'description' => $content,
                'layout' => 'unknown',
            );
        }
        
        return new WP_Error('invalid_response', __('Invalid response from Vision API', 'wpai-assistant'));
    }
    
    /**
     * Analyze image using Google Vision API
     */
    private function analyze_image_google($base64_image, $image_url) {
        $api_key = get_option('wpai_api_key', '');
        $endpoint = 'https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($api_key);
        
        $body = array(
            'requests' => array(
                array(
                    'image' => array(
                        'content' => $base64_image,
                    ),
                    'features' => array(
                        array('type' => 'LABEL_DETECTION', 'maxResults' => 10),
                        array('type' => 'TEXT_DETECTION'),
                        array('type' => 'IMAGE_PROPERTIES'),
                    ),
                ),
            ),
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message'] ?? __('Image analysis failed', 'wpai-assistant'));
        }
        
        // Parse Google Vision response
        $analysis = array(
            'description' => '',
            'layout' => 'unknown',
            'text' => '',
        );
        
        if (isset($data['responses'][0])) {
            $resp = $data['responses'][0];
            if (isset($resp['textAnnotations'][0]['description'])) {
                $analysis['text'] = $resp['textAnnotations'][0]['description'];
            }
            if (isset($resp['labelAnnotations'])) {
                $labels = array();
                foreach ($resp['labelAnnotations'] as $label) {
                    $labels[] = $label['description'];
                }
                $analysis['description'] = implode(', ', $labels);
            }
        }
        
        return $analysis;
    }
    
    /**
     * Analyze HTML structure
     */
    private function analyze_html_structure($html) {
        $structure = array(
            'sections' => array(),
            'has_header' => false,
            'has_footer' => false,
        );
        
        // Detect header
        if (preg_match('/<header[^>]*>/i', $html) || preg_match('/<nav[^>]*>/i')) {
            $structure['has_header'] = true;
        }
        
        // Detect footer
        if (preg_match('/<footer[^>]*>/i', $html)) {
            $structure['has_footer'] = true;
        }
        
        // Extract sections
        if (preg_match_all('/<section[^>]*>(.*?)<\/section>/is', $html, $matches)) {
            foreach ($matches[1] as $section_html) {
                $structure['sections'][] = array(
                    'content' => wp_strip_all_tags($section_html),
                );
            }
        }
        
        return $structure;
    }
    
    /**
     * Generate Elementor structure from crawled data
     */
    private function generate_elementor_from_crawl($crawl_data) {
        $sections = array();
        
        // Create header section if detected
        if (!empty($crawl_data['html_structure']['has_header'])) {
            $sections[] = $this->create_elementor_section('header', array(
                'title' => $crawl_data['title'] ?? '',
            ));
        }
        
        // Create content sections
        if (!empty($crawl_data['html_structure']['sections'])) {
            foreach ($crawl_data['html_structure']['sections'] as $section) {
                $sections[] = $this->create_elementor_section('content', $section);
            }
        } else {
            // Create single content section
            $sections[] = $this->create_elementor_section('content', array(
                'content' => $crawl_data['content'] ?? '',
            ));
        }
        
        // Create footer section if detected
        if (!empty($crawl_data['html_structure']['has_footer'])) {
            $sections[] = $this->create_elementor_section('footer', array());
        }
        
        return $sections;
    }
    
    /**
     * Generate Elementor structure from image analysis
     */
    private function generate_elementor_from_image($analysis) {
        $sections = array();
        
        // Create hero section with image
        $sections[] = $this->create_elementor_section('hero', array(
            'title' => $analysis['title'] ?? 'Hero Section',
            'description' => $analysis['description'] ?? '',
        ));
        
        // Create content sections based on analysis
        if (!empty($analysis['sections'])) {
            foreach ($analysis['sections'] as $section) {
                $sections[] = $this->create_elementor_section('content', $section);
            }
        }
        
        return $sections;
    }
    
    /**
     * Create Elementor section structure
     * Improved to support complex landing page layouts
     */
    private function create_elementor_section($type, $data) {
        $section_id = wp_generate_uuid4();
        $column_id = wp_generate_uuid4();
        
        $section_settings = array(
            'layout' => 'boxed',
        );
        
        $widgets = array();
        
        switch ($type) {
            case 'header':
                $section_settings['background_background'] = 'classic';
                $widgets[] = array(
                    'id' => wp_generate_uuid4(),
                    'elType' => 'widget',
                    'widgetType' => 'heading',
                    'settings' => array(
                        'title' => $data['title'] ?? 'Header',
                        'size' => 'h1',
                        'align' => 'center',
                    ),
                );
                break;
                
            case 'hero':
                $section_settings['background_background'] = 'classic';
                $section_settings['background_color'] = '#f5f5f5';
                
                // Hero heading
                if (!empty($data['title'])) {
                    $widgets[] = array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'widget',
                        'widgetType' => 'heading',
                        'settings' => array(
                            'title' => $data['title'],
                            'size' => 'h1',
                            'align' => 'center',
                        ),
                    );
                }
                
                // Hero description
                if (!empty($data['description'])) {
                    $widgets[] = array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'widget',
                        'widgetType' => 'text-editor',
                        'settings' => array(
                            'editor' => wp_kses_post($data['description']),
                            'align' => 'center',
                        ),
                    );
                }
                
                // Hero image if provided
                if (!empty($data['image_url'])) {
                    $widgets[] = array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'widget',
                        'widgetType' => 'image',
                        'settings' => array(
                            'image' => array(
                                'url' => $data['image_url'],
                            ),
                            'align' => 'center',
                        ),
                    );
                }
                break;
                
            case 'content':
                $level = isset($data['level']) ? min(max(1, intval($data['level'])), 6) : 2;
                $heading_size = 'h' . $level;
                
                // Add heading if title provided
                if (!empty($data['title'])) {
                    $widgets[] = array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'widget',
                        'widgetType' => 'heading',
                        'settings' => array(
                            'title' => $data['title'],
                            'size' => $heading_size,
                        ),
                    );
                }
                
                // Add content
                if (!empty($data['content'])) {
                    $widgets[] = array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'widget',
                        'widgetType' => 'text-editor',
                        'settings' => array(
                            'editor' => wp_kses_post($data['content']),
                        ),
                    );
                }
                break;
                
            case 'footer':
                $section_settings['background_background'] = 'classic';
                $section_settings['background_color'] = '#333333';
                $widgets[] = array(
                    'id' => wp_generate_uuid4(),
                    'elType' => 'widget',
                    'widgetType' => 'text-editor',
                    'settings' => array(
                        'editor' => '<p style="text-align: center; color: #ffffff;">© ' . date('Y') . ' All rights reserved.</p>',
                        'align' => 'center',
                    ),
                );
                break;
        }
        
        // If no widgets, add default text editor
        if (empty($widgets)) {
            $widgets[] = array(
                'id' => wp_generate_uuid4(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => array(
                    'editor' => '',
                ),
            );
        }
        
        return array(
            'id' => $section_id,
            'elType' => 'section',
            'settings' => $section_settings,
            'elements' => array(
                array(
                    'id' => $column_id,
                    'elType' => 'column',
                    'settings' => array(
                        '_column_size' => 100,
                    ),
                    'elements' => $widgets,
                ),
            ),
        );
    }
}


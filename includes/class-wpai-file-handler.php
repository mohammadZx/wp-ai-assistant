<?php
/**
 * File and image upload handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_File_Handler {
    
    private $upload_dir;
    private $allowed_text_types = array('txt', 'md', 'docx', 'pdf');
    private $allowed_image_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    
    public function __construct() {
        $this->upload_dir = wp_upload_dir();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_wpai_upload_file', array($this, 'ajax_upload_file'));
        add_action('wp_ajax_wpai_extract_text', array($this, 'ajax_extract_text'));
        add_action('wp_ajax_wpai_generate_placeholder', array($this, 'ajax_generate_placeholder'));
    }
    
    /**
     * Upload file
     */
    public function upload_file($file, $type = 'text') {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $allowed_types = $type === 'image' ? $this->allowed_image_types : $this->allowed_text_types;
        
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => $this->get_mime_types($allowed_types),
        );
        
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            return new WP_Error('upload_error', $uploaded_file['error']);
        }
        
        // Save file metadata
        $attachment_id = $this->create_attachment($uploaded_file, $type);
        
        return array(
            'file' => $uploaded_file,
            'attachment_id' => $attachment_id,
            'url' => $uploaded_file['url'],
        );
    }
    
    /**
     * Extract text from file
     */
    public function extract_text($file_path, $file_type) {
        switch ($file_type) {
            case 'txt':
            case 'md':
                return file_get_contents($file_path);
                
            case 'pdf':
                return $this->extract_from_pdf($file_path);
                
            case 'docx':
                return $this->extract_from_docx($file_path);
                
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
                return $this->extract_from_image($file_path);
                
            default:
                return new WP_Error('unsupported_type', __('Unsupported file type', 'wpai-assistant'));
        }
    }
    
    /**
     * Extract text from PDF
     */
    private function extract_from_pdf($file_path) {
        // Simple PDF text extraction (requires pdftotext or similar)
        // For production, consider using a library like Smalot\PdfParser
        if (function_exists('shell_exec') && shell_exec('which pdftotext')) {
            $text = shell_exec('pdftotext ' . escapeshellarg($file_path) . ' -');
            return $text ?: __('Could not extract text from PDF', 'wpai-assistant');
        }
        
        // Fallback: return error suggesting manual text input
        return new WP_Error('pdf_extraction', __('PDF text extraction requires pdftotext utility. Please extract text manually or use a text file.', 'wpai-assistant'));
    }
    
    /**
     * Extract text from DOCX
     */
    private function extract_from_docx($file_path) {
        // Simple DOCX extraction
        // For production, use a library like PhpOffice\PhpWord
        $zip = new ZipArchive();
        if ($zip->open($file_path) === TRUE) {
            $content = $zip->getFromName('word/document.xml');
            $zip->close();
            
            if ($content) {
                // Remove XML tags and extract text
                $content = strip_tags($content);
                $content = preg_replace('/\s+/', ' ', $content);
                return trim($content);
            }
        }
        
        return new WP_Error('docx_extraction', __('Could not extract text from DOCX', 'wpai-assistant'));
    }
    
    /**
     * Extract text from image (OCR)
     */
    private function extract_from_image($file_path) {
        // OCR functionality
        // For production, integrate with Tesseract OCR or cloud OCR services
        // This is a placeholder - actual OCR requires external service or library
        
        // Option 1: Use Tesseract if available
        if (function_exists('shell_exec') && shell_exec('which tesseract')) {
            $output_file = sys_get_temp_dir() . '/wpai_ocr_' . uniqid() . '.txt';
            shell_exec('tesseract ' . escapeshellarg($file_path) . ' ' . escapeshellarg($output_file) . ' -l eng+fas 2>/dev/null');
            if (file_exists($output_file . '.txt')) {
                $text = file_get_contents($output_file . '.txt');
                unlink($output_file . '.txt');
                return $text;
            }
        }
        
        // Option 2: Use AI vision API for OCR
        // This would call the AI API with image analysis
        return new WP_Error('ocr_not_available', __('OCR functionality requires Tesseract OCR or AI vision API. Image description can be generated using AI.', 'wpai-assistant'));
    }
    
    /**
     * Generate placeholder image
     */
    public function generate_placeholder($text, $width = 800, $height = 600, $style = 'default') {
        // Create placeholder image using GD or Imagick
        if (!function_exists('imagecreatetruecolor')) {
            return new WP_Error('gd_not_available', __('GD library is required for placeholder generation', 'wpai-assistant'));
        }
        
        $image = imagecreatetruecolor($width, $height);
        
        // Background color based on style
        $bg_colors = array(
            'default' => array(240, 240, 240),
            'dark' => array(50, 50, 50),
            'colorful' => array(rand(100, 200), rand(100, 200), rand(100, 200)),
        );
        
        $bg_color = $bg_colors[$style] ?? $bg_colors['default'];
        $bg = imagecolorallocate($image, $bg_colors[$style][0], $bg_colors[$style][1], $bg_colors[$style][2]);
        imagefill($image, 0, 0, $bg);
        
        // Add text
        $text_color = imagecolorallocate($image, 100, 100, 100);
        $font_size = 24;
        $font = WPAI_PLUGIN_DIR . 'assets/fonts/arial.ttf'; // You may need to include a font
        
        // Simple text rendering (if font available)
        if (file_exists($font)) {
            $bbox = imagettfbbox($font_size, 0, $font, $text);
            $text_width = $bbox[4] - $bbox[0];
            $text_height = $bbox[5] - $bbox[1];
            $x = ($width - $text_width) / 2;
            $y = ($height - $text_height) / 2;
            imagettftext($image, $font_size, 0, $x, $y, $text_color, $font, $text);
        } else {
            // Fallback: simple text
            imagestring($image, 5, $width / 2 - 50, $height / 2, $text, $text_color);
        }
        
        // Save image
        $filename = 'wpai_placeholder_' . uniqid() . '.png';
        $filepath = $this->upload_dir['path'] . '/' . $filename;
        imagepng($image, $filepath);
        imagedestroy($image);
        
        // Create attachment
        $attachment_id = $this->create_attachment(array(
            'file' => $filepath,
            'url' => $this->upload_dir['url'] . '/' . $filename,
            'type' => 'image/png',
        ), 'image');
        
        return array(
            'url' => $this->upload_dir['url'] . '/' . $filename,
            'attachment_id' => $attachment_id,
        );
    }
    
    /**
     * Get MIME types for allowed extensions
     */
    private function get_mime_types($extensions) {
        $mimes = array();
        foreach ($extensions as $ext) {
            $mime = wp_check_filetype('test.' . $ext);
            if ($mime['type']) {
                $mimes[$ext] = $mime['type'];
            }
        }
        return $mimes;
    }
    
    /**
     * Create WordPress attachment
     */
    private function create_attachment($file_data, $type) {
        $attachment = array(
            'post_mime_type' => $file_data['type'],
            'post_title' => sanitize_file_name(basename($file_data['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
        );
        
        $attachment_id = wp_insert_attachment($attachment, $file_data['file']);
        
        if (!is_wp_error($attachment_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attachment_id, $file_data['file']);
            wp_update_attachment_metadata($attachment_id, $attach_data);
            
            // Add custom meta
            update_post_meta($attachment_id, '_wpai_file_type', $type);
        }
        
        return $attachment_id;
    }
    
    /**
     * AJAX: Upload file
     */
    public function ajax_upload_file() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'wpai-assistant')));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'text');
        $result = $this->upload_file($_FILES['file'], $type);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Extract text if needed
        if ($type === 'text') {
            $file_type = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $extracted_text = $this->extract_text($result['file']['file'], $file_type);
            if (!is_wp_error($extracted_text)) {
                $result['extracted_text'] = $extracted_text;
            }
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Extract text
     */
    public function ajax_extract_text() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $attachment_id = intval($_POST['attachment_id'] ?? 0);
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(array('message' => __('File not found', 'wpai-assistant')));
        }
        
        $file_type = pathinfo($file_path, PATHINFO_EXTENSION);
        $text = $this->extract_text($file_path, $file_type);
        
        if (is_wp_error($text)) {
            wp_send_json_error(array('message' => $text->get_error_message()));
        }
        
        wp_send_json_success(array('text' => $text));
    }
    
    /**
     * AJAX: Generate placeholder
     */
    public function ajax_generate_placeholder() {
        check_ajax_referer('wpai_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wpai-assistant')));
        }
        
        $text = sanitize_text_field($_POST['text'] ?? 'Placeholder');
        $width = intval($_POST['width'] ?? 800);
        $height = intval($_POST['height'] ?? 600);
        $style = sanitize_text_field($_POST['style'] ?? 'default');
        
        $result = $this->generate_placeholder($text, $width, $height, $style);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
}


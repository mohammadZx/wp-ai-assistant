<?php
/**
 * Crawler page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wpai-crawler-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wpai-crawler-container">
        <h2><?php _e('Crawl URLs', 'wpai-assistant'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="wpai-crawl-url"><?php _e('URL to Crawl', 'wpai-assistant'); ?></label>
                </th>
                <td>
                    <input type="url" id="wpai-crawl-url" class="regular-text" placeholder="https://example.com" />
                    <p class="description"><?php _e('Enter a single URL to crawl and analyze.', 'wpai-assistant'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="wpai-crawl-urls"><?php _e('Multiple URLs', 'wpai-assistant'); ?></label>
                </th>
                <td>
                    <textarea id="wpai-crawl-urls" rows="5" class="large-text" placeholder="https://example.com/page1&#10;https://example.com/page2"></textarea>
                    <p class="description"><?php _e('Enter multiple URLs, one per line.', 'wpai-assistant'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="wpai-crawl-site"><?php _e('Crawl Entire Site', 'wpai-assistant'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="wpai-crawl-site" />
                    <label for="wpai-crawl-site"><?php _e('Crawl internal links starting from base URL', 'wpai-assistant'); ?></label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="wpai-max-pages"><?php _e('Max Pages', 'wpai-assistant'); ?></label>
                </th>
                <td>
                    <input type="number" id="wpai-max-pages" value="50" min="1" max="200" class="small-text" />
                    <p class="description"><?php _e('Maximum number of pages to crawl.', 'wpai-assistant'); ?></p>
                </td>
            </tr>
        </table>
        
        <p>
            <button type="button" id="wpai-crawl-btn" class="button button-primary"><?php _e('Start Crawling', 'wpai-assistant'); ?></button>
            <button type="button" id="wpai-analyze-btn" class="button" style="display: none;"><?php _e('Analyze & Generate Suggestions', 'wpai-assistant'); ?></button>
        </p>
        
        <div id="wpai-crawl-progress" style="display: none;">
            <div class="wpai-progress-bar">
                <div class="wpai-progress-fill"></div>
            </div>
            <p class="wpai-progress-text"></p>
        </div>
        
        <div id="wpai-crawl-results" class="wpai-crawl-results" style="display: none;">
            <h3><?php _e('Crawl Results', 'wpai-assistant'); ?></h3>
            <div id="wpai-crawl-results-list"></div>
        </div>
        
        <div id="wpai-suggestions" class="wpai-suggestions" style="display: none;">
            <h3><?php _e('AI Suggestions', 'wpai-assistant'); ?></h3>
            <div id="wpai-suggestions-list"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let crawlResults = [];
    
    // Start crawling
    $('#wpai-crawl-btn').on('click', function() {
        const url = $('#wpai-crawl-url').val();
        const urlsText = $('#wpai-crawl-urls').val();
        const crawlSite = $('#wpai-crawl-site').is(':checked');
        const maxPages = parseInt($('#wpai-max-pages').val()) || 50;
        
        if (!url && !urlsText && !crawlSite) {
            alert('<?php _e('Please enter a URL or enable site crawling', 'wpai-assistant'); ?>');
            return;
        }
        
        $('#wpai-crawl-progress').show();
        $('#wpai-crawl-results').hide();
        $('#wpai-suggestions').hide();
        crawlResults = [];
        
        if (crawlSite) {
            // Crawl entire site
            const baseUrl = url || '<?php echo esc_js(home_url()); ?>';
            crawlSiteInternal(baseUrl, maxPages);
        } else if (urlsText) {
            // Crawl multiple URLs
            const urls = urlsText.split('\n').filter(u => u.trim());
            crawlMultipleUrls(urls);
        } else if (url) {
            // Crawl single URL
            crawlSingleUrl(url);
        }
    });
    
    function crawlSingleUrl(url) {
        updateProgress('<?php _e('Crawling URL...', 'wpai-assistant'); ?>', 0);
        
        $.ajax({
            url: wpaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpai_crawl_url',
                nonce: wpaiData.nonce,
                url: url
            },
            success: function(response) {
                if (response.success) {
                    crawlResults.push(response.data);
                    displayCrawlResults();
                    updateProgress('<?php _e('Crawling completed', 'wpai-assistant'); ?>', 100);
                    $('#wpai-analyze-btn').show();
                } else {
                    alert('Error: ' + response.data.message);
                    updateProgress('', 0);
                }
            },
            error: function() {
                alert('<?php _e('An error occurred', 'wpai-assistant'); ?>');
                updateProgress('', 0);
            }
        });
    }
    
    function crawlMultipleUrls(urls) {
        let completed = 0;
        const total = urls.length;
        
        urls.forEach(function(url, index) {
            setTimeout(function() {
                $.ajax({
                    url: wpaiData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpai_crawl_url',
                        nonce: wpaiData.nonce,
                        url: url.trim()
                    },
                    success: function(response) {
                        if (response.success) {
                            crawlResults.push(response.data);
                        }
                        completed++;
                        const progress = (completed / total) * 100;
                        updateProgress('<?php _e('Crawling...', 'wpai-assistant'); ?> ' + completed + '/' + total, progress);
                        
                        if (completed === total) {
                            displayCrawlResults();
                            $('#wpai-analyze-btn').show();
                        }
                    }
                });
            }, index * 500); // Stagger requests
        });
    }
    
    function crawlSiteInternal(baseUrl, maxPages) {
        updateProgress('<?php _e('Crawling site...', 'wpai-assistant'); ?>', 0);
        
        $.ajax({
            url: wpaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpai_crawl_site',
                nonce: wpaiData.nonce,
                base_url: baseUrl,
                max_pages: maxPages
            },
            success: function(response) {
                if (response.success) {
                    crawlResults = response.data.results || [];
                    displayCrawlResults();
                    updateProgress('<?php _e('Crawling completed', 'wpai-assistant'); ?>', 100);
                    $('#wpai-analyze-btn').show();
                } else {
                    alert('Error: ' + response.data.message);
                    updateProgress('', 0);
                }
            }
        });
    }
    
    function displayCrawlResults() {
        const container = $('#wpai-crawl-results-list');
        container.empty();
        
        crawlResults.forEach(function(result) {
            const html = '<div class="wpai-crawl-item">' +
                '<h4><a href="' + result.url + '" target="_blank">' + (result.title || result.url) + '</a></h4>' +
                '<p>' + wp_trim_words(result.content, 50) + '</p>' +
                '</div>';
            container.append(html);
        });
        
        $('#wpai-crawl-results').show();
    }
    
    function updateProgress(text, percent) {
        $('.wpai-progress-text').text(text);
        $('.wpai-progress-fill').css('width', percent + '%');
    }
    
    // Analyze and generate suggestions
    $('#wpai-analyze-btn').on('click', function() {
        if (crawlResults.length === 0) {
            alert('<?php _e('No crawl results to analyze', 'wpai-assistant'); ?>');
            return;
        }
        
        updateProgress('<?php _e('Analyzing with AI...', 'wpai-assistant'); ?>', 0);
        
        $.ajax({
            url: wpaiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpai_analyze_crawl',
                nonce: wpaiData.nonce,
                crawl_data: JSON.stringify(crawlResults),
                context: JSON.stringify({})
            },
            success: function(response) {
                if (response.success) {
                    displaySuggestions(response.data.suggestions);
                    updateProgress('<?php _e('Analysis completed', 'wpai-assistant'); ?>', 100);
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    function displaySuggestions(suggestions) {
        const container = $('#wpai-suggestions-list');
        container.empty();
        
        suggestions.forEach(function(suggestion) {
            const html = '<div class="wpai-suggestion-item">' +
                '<h4>' + (suggestion.title || suggestion.url) + '</h4>' +
                '<div class="wpai-suggestion-analysis">' +
                '<strong><?php _e('SEO:', 'wpai-assistant'); ?></strong> ' + (suggestion.analysis?.seo || 'N/A') + '<br>' +
                '<strong><?php _e('Content:', 'wpai-assistant'); ?></strong> ' + (suggestion.analysis?.content || 'N/A') + '<br>' +
                '<strong><?php _e('UX:', 'wpai-assistant'); ?></strong> ' + (suggestion.analysis?.ux || 'N/A') +
                '</div>' +
                '<div class="wpai-suggestion-actions">' +
                '<button type="button" class="button wpai-apply-suggestion" data-url="' + suggestion.url + '"><?php _e('Apply Suggestions', 'wpai-assistant'); ?></button>' +
                '</div>' +
                '</div>';
            container.append(html);
        });
        
        $('#wpai-suggestions').show();
    }
});
</script>

<style>
.wpai-progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f1;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.wpai-progress-fill {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s;
    width: 0%;
}

.wpai-progress-text {
    text-align: center;
    margin: 5px 0;
}
</style>


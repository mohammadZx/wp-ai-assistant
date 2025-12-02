/**
 * WP AI Assistant Admin JavaScript
 */

(function($) {
    'use strict';

    const WPAI = {
        sessionId: null,
        uploadedFiles: [],
        currentTopic: null,
        thinkingDegree: 50,

        init: function() {
            this.initChat();
            this.initThinkingDegree();
            this.initFileUpload();
            this.initSettings();
            this.initTabs();
            this.initTopics();
            this.initCrawler();
        },

        // Chat functionality
        initChat: function() {
            // Generate session ID if not exists
            if (!this.sessionId) {
                this.sessionId = 'wpai_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            }

            // Send message
            $('#wpai-send-btn, #wpai-chat-input').on('keypress', function(e) {
                if (e.which === 13 && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    WPAI.sendMessage();
                }
            });

            $('#wpai-send-btn').on('click', function() {
                WPAI.sendMessage();
            });

            // Clear chat
            $('#wpai-clear-btn').on('click', function() {
                if (confirm(wpaiData.strings.confirmClear || 'Are you sure you want to clear this chat?')) {
                    WPAI.clearChat();
                }
            });

            // Export chat
            $('#wpai-export-btn').on('click', function() {
                WPAI.exportChat();
            });

            // Load chat history if session exists
            if (this.sessionId) {
                this.loadChatHistory();
            }
        },

        sendMessage: function() {
            const input = $('#wpai-chat-input');
            const message = input.val().trim();

            if (!message) {
                return;
            }

            // Add user message to UI
            this.addMessage('user', message);
            input.val('');

            // Show loading
            const loadingId = this.addMessage('assistant', '', true);

            // Get settings
            const settings = this.getThinkingDegreeSettings();
            const context = {
                topic_id: this.currentTopic,
                uploaded_files: this.uploadedFiles
            };

            // Send AJAX request
            $.ajax({
                url: wpaiData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_send_message',
                    nonce: wpaiData.nonce,
                    message: message,
                    session_id: this.sessionId,
                    context: JSON.stringify(context),
                    settings: JSON.stringify(settings)
                },
                success: function(response) {
                    if (response.success) {
                        // Update loading message
                        $('#' + loadingId).removeClass('loading').html(response.data.response);
                        WPAI.sessionId = response.data.session_id;
                    } else {
                        $('#' + loadingId).removeClass('loading').html('<span style="color: #d63638;">Error: ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $('#' + loadingId).removeClass('loading').html('<span style="color: #d63638;">' + (wpaiData.strings.error || 'An error occurred') + '</span>');
                }
            });
        },

        addMessage: function(role, content, loading) {
            const messagesContainer = $('#wpai-chat-messages');
            const messageId = 'wpai-msg-' + Date.now();
            const time = new Date().toLocaleTimeString();
            
            let html = '<div id="' + messageId + '" class="wpai-message ' + role + '">';
            if (loading) {
                html += '<span class="wpai-spinner"></span> ' + (wpaiData.strings.thinking || 'Thinking...');
            } else {
                html += '<div>' + this.formatMessage(content) + '</div>';
                html += '<div class="wpai-message-time">' + time + '</div>';
            }
            html += '</div>';

            messagesContainer.append(html);
            messagesContainer.scrollTop(messagesContainer[0].scrollHeight);

            return messageId;
        },

        formatMessage: function(content) {
            // Basic markdown-like formatting
            content = content.replace(/\n/g, '<br>');
            content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            content = content.replace(/\*(.+?)\*/g, '<em>$1</em>');
            return content;
        },

        loadChatHistory: function() {
            if (!this.sessionId) {
                return;
            }

            $.ajax({
                url: wpaiData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_get_chat_history',
                    nonce: wpaiData.nonce,
                    session_id: this.sessionId
                },
                success: function(response) {
                    if (response.success && response.data.history) {
                        const messagesContainer = $('#wpai-chat-messages');
                        messagesContainer.empty();
                        
                        response.data.history.forEach(function(item) {
                            if (item.message) {
                                WPAI.addMessage('user', item.message, false);
                            }
                            if (item.response) {
                                WPAI.addMessage('assistant', item.response, false);
                            }
                        });
                    }
                }
            });
        },

        clearChat: function() {
            if (!this.sessionId) {
                return;
            }

            $.ajax({
                url: wpaiData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_clear_chat',
                    nonce: wpaiData.nonce,
                    session_id: this.sessionId
                },
                success: function() {
                    $('#wpai-chat-messages').empty();
                }
            });
        },

        exportChat: function() {
            if (!this.sessionId) {
                alert('No chat to export');
                return;
            }

            $.ajax({
                url: wpaiData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_export_chat',
                    nonce: wpaiData.nonce,
                    session_id: this.sessionId
                },
                success: function(response) {
                    if (response.success) {
                        // Download as file
                        const blob = new Blob([response.data.export], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'wpai-chat-' + Date.now() + '.json';
                        a.click();
                        URL.revokeObjectURL(url);
                    }
                }
            });
        },

        // Thinking Degree
        initThinkingDegree: function() {
            const slider = $('#wpai-thinking-degree');
            const valueDisplay = $('#wpai-degree-value');

            if (slider.length) {
                this.thinkingDegree = parseInt(slider.val()) || 50;
                valueDisplay.text(this.thinkingDegree);

                slider.on('input', function() {
                    WPAI.thinkingDegree = parseInt($(this).val());
                    valueDisplay.text(WPAI.thinkingDegree);
                });
            }
        },

        getThinkingDegreeSettings: function() {
            // Map thinking degree (0-100) to API parameters
            const degree = this.thinkingDegree;
            
            return {
                temperature: (degree / 100) * 1.5, // 0 to 1.5
                top_p: 0.5 + ((degree / 100) * 0.5), // 0.5 to 1.0
                max_tokens: 1000 + ((degree / 100) * 2000) // 1000 to 3000
            };
        },

        // File Upload
        initFileUpload: function() {
            $('#wpai-upload-btn').on('click', function() {
                const fileInput = $('#wpai-file-upload')[0];
                if (!fileInput.files.length) {
                    alert('Please select a file');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'wpai_upload_file');
                formData.append('nonce', wpaiData.nonce);
                formData.append('file', fileInput.files[0]);
                formData.append('type', fileInput.files[0].type.startsWith('image/') ? 'image' : 'text');

                $.ajax({
                    url: wpaiData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            WPAI.uploadedFiles.push(response.data);
                            WPAI.displayUploadedFile(response.data);
                            fileInput.value = '';
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert(wpaiData.strings.error || 'An error occurred');
                    }
                });
            });
        },

        displayUploadedFile: function(fileData) {
            const container = $('#wpai-uploaded-files');
            const fileId = 'file-' + Date.now();
            
            let html = '<div id="' + fileId + '" class="wpai-uploaded-file">';
            html += '<span>' + (fileData.file.name || 'Uploaded file') + '</span>';
            html += '<a href="#" class="remove" data-file-id="' + fileId + '">×</a>';
            html += '</div>';

            container.append(html);

            // Remove handler
            $('#' + fileId + ' .remove').on('click', function(e) {
                e.preventDefault();
                const id = $(this).data('file-id');
                WPAI.uploadedFiles = WPAI.uploadedFiles.filter(f => f.id !== id);
                $('#' + id).remove();
            });
        },

        // Settings
        initSettings: function() {
            // Test connection
            $('#wpai-test-connection').on('click', function() {
                const btn = $(this);
                const result = $('#wpai-test-result');
                
                btn.prop('disabled', true).text('Testing...');
                result.html('<span class="wpai-spinner"></span>');

                $.ajax({
                    url: wpaiData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpai_test_connection',
                        nonce: wpaiData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<div style="color: #00a32a; margin-bottom: 10px;">✓ Connection successful</div>';
                            
                            // Display response message
                            if (response.data.message) {
                                html += '<div style="margin-bottom: 10px;"><strong>Response:</strong> ' + 
                                        '<div style="background: #f0f0f1; padding: 10px; border-radius: 4px; margin-top: 5px;">' + 
                                        escapeHtml(response.data.message) + 
                                        '</div></div>';
                            }
                            
                            // Display full response data
                            if (response.data.response) {
                                html += '<details style="margin-top: 10px;">';
                                html += '<summary style="cursor: pointer; color: #2271b1; font-weight: bold;">View Full Response</summary>';
                                html += '<pre style="background: #f0f0f1; padding: 10px; border-radius: 4px; overflow-x: auto; margin-top: 5px; font-size: 12px;">' + 
                                        escapeHtml(JSON.stringify(response.data.response, null, 2)) + 
                                        '</pre>';
                                html += '</details>';
                            }
                            
                            result.html(html);
                        } else {
                            let html = '<div style="color: #d63638; margin-bottom: 10px;">✗ Connection failed</div>';
                            
                            if (response.data.error) {
                                html += '<div style="margin-bottom: 10px;"><strong>Error:</strong> ' + 
                                        escapeHtml(response.data.error) + 
                                        '</div>';
                            }
                            
                            if (response.data.message) {
                                html += '<div style="margin-bottom: 10px;"><strong>Message:</strong> ' + 
                                        escapeHtml(response.data.message) + 
                                        '</div>';
                            }
                            
                            // Display response data if available
                            if (response.data.response) {
                                html += '<details style="margin-top: 10px;">';
                                html += '<summary style="cursor: pointer; color: #2271b1; font-weight: bold;">View Response Details</summary>';
                                html += '<pre style="background: #f0f0f1; padding: 10px; border-radius: 4px; overflow-x: auto; margin-top: 5px; font-size: 12px;">' + 
                                        escapeHtml(JSON.stringify(response.data.response, null, 2)) + 
                                        '</pre>';
                                html += '</details>';
                            }
                            
                            result.html(html);
                        }
                    },
                    error: function(xhr, status, error) {
                        let html = '<div style="color: #d63638; margin-bottom: 10px;">✗ Connection failed</div>';
                        html += '<div><strong>Error:</strong> ' + escapeHtml(error) + '</div>';
                        if (xhr.responseText) {
                            html += '<details style="margin-top: 10px;">';
                            html += '<summary style="cursor: pointer; color: #2271b1; font-weight: bold;">View Error Details</summary>';
                            html += '<pre style="background: #f0f0f1; padding: 10px; border-radius: 4px; overflow-x: auto; margin-top: 5px; font-size: 12px;">' + 
                                    escapeHtml(xhr.responseText) + 
                                    '</pre>';
                            html += '</details>';
                        }
                        result.html(html);
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Test Connection');
                    }
                });
            });

            // Preset profiles
            $('.wpai-preset').on('click', function() {
                const preset = $(this).data('preset');
                WPAI.applyPreset(preset);
            });

            // Update slider values
            $('#wpai_default_temperature, #wpai_default_top_p').on('input', function() {
                const id = $(this).attr('id') + '_value';
                $('#' + id).text($(this).val());
            });
        },

        applyPreset: function(preset) {
            const presets = {
                conservative: { temperature: 0.3, top_p: 0.8, max_tokens: 1500 },
                balanced: { temperature: 0.7, top_p: 1.0, max_tokens: 2000 },
                creative: { temperature: 1.2, top_p: 1.0, max_tokens: 3000 }
            };

            const settings = presets[preset];
            if (settings) {
                $('#wpai_default_temperature').val(settings.temperature).trigger('input');
                $('#wpai_default_top_p').val(settings.top_p).trigger('input');
                $('#wpai_default_max_tokens').val(settings.max_tokens);
            }
        },

        // Tabs
        initTabs: function() {
            $('.nav-tab-wrapper a').on('click', function(e) {
                e.preventDefault();
                const target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
        },

        // Topics
        initTopics: function() {
            $('#wpai-topic-select').on('change', function() {
                WPAI.currentTopic = $(this).val();
            });

            // Topic management (create, edit, delete)
            $('#wpai-create-topic').on('click', function() {
                WPAI.showTopicModal();
            });
        },

        showTopicModal: function() {
            // Implement topic creation modal
            const name = prompt('Enter topic name:');
            if (name) {
                $.ajax({
                    url: wpaiData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpai_create_topic',
                        nonce: wpaiData.nonce,
                        name: name
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            }
        },

        // Crawler
        initCrawler: function() {
            $('#wpai-crawl-btn').on('click', function() {
                const url = $('#wpai-crawl-url').val();
                if (!url) {
                    alert('Please enter a URL');
                    return;
                }

                WPAI.crawlUrl(url);
            });
        },

        crawlUrl: function(url) {
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
                        WPAI.displayCrawlResult(response.data);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        },

        displayCrawlResult: function(data) {
            // Display crawl results
            console.log('Crawl result:', data);
        }
    };

    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Initialize on document ready
    $(document).ready(function() {
        WPAI.init();
    });

})(jQuery);


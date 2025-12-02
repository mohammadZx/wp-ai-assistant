/**
 * Elementor Panel Integration - Using Latest Elementor API
 */

(function($) {
    'use strict';

    const WPAIElementor = {
        initialized: false,
        panelTabAdded: false,
        
        init: function() {
            if (this.initialized) {
                return;
            }

            // Wait for Elementor to be fully loaded
            if (typeof elementor === 'undefined') {
                setTimeout(() => WPAIElementor.init(), 200);
                return;
            }

            // Use Elementor's proper hooks
            elementor.hooks.addAction('panel/open_editor/widget', () => {
                WPAIElementor.addPanelTab();
            });

            elementor.hooks.addAction('panel/open_editor/container', () => {
                WPAIElementor.addPanelTab();
            });

            // Add tab when panel is initialized
            elementor.hooks.addAction('panel/init', () => {
                setTimeout(() => {
                    WPAIElementor.addPanelTab();
                }, 500);
            });

            // Also try immediately
            setTimeout(() => {
                WPAIElementor.addPanelTab();
            }, 1000);

            this.initialized = true;
        },

        addPanelTab: function() {
            if (this.panelTabAdded) {
                return;
            }

            // Check if Elementor panel exists
            if (typeof elementor === 'undefined' || !elementor.panels) {
                setTimeout(() => WPAIElementor.addPanelTab(), 500);
                return;
            }

            // Use Elementor's panel menu API
            try {
                // Method 1: Add to panel menu using Elementor's API
                if (elementor.panels && elementor.panels.menu && elementor.panels.menu.addItem) {
                    elementor.panels.menu.addItem({
                        name: 'wpai-assistant',
                        icon: 'eicon-robot',
                        title: wpaiElementor.strings.title,
                        callback: () => {
                            WPAIElementor.showPanel();
                        }
                    });
                    this.panelTabAdded = true;
                    return;
                }
            } catch (e) {
                console.log('Elementor API method failed, trying DOM method', e);
            }

            // Method 2: Direct DOM manipulation (fallback)
            const $panelMenu = $('.elementor-panel-menu-wrapper, #elementor-panel-header-menu');
            
            if ($panelMenu.length === 0) {
                setTimeout(() => WPAIElementor.addPanelTab(), 500);
                return;
            }

            // Check if already added
            if ($('#elementor-panel-menu-item-wpai-assistant').length) {
                this.panelTabAdded = true;
                return;
            }

            // Create menu item
            const menuItem = $(`
                <div id="elementor-panel-menu-item-wpai-assistant" class="elementor-panel-menu-item" data-panel="wpai-assistant">
                    <div class="elementor-panel-menu-item-icon">
                        <i class="eicon-robot" aria-hidden="true"></i>
                    </div>
                    <div class="elementor-panel-menu-item-title">${wpaiElementor.strings.title}</div>
                </div>
            `);

            // Create panel content
            const panelContent = $(`
                <div id="elementor-panel-wpai-assistant" class="elementor-panel wpai-elementor-panel" style="display: none;">
                    <div class="elementor-panel-box">
                        <div class="elementor-panel-heading">
                            <div class="elementor-panel-heading-title">${wpaiElementor.strings.title}</div>
                        </div>
                        <div class="elementor-panel-body">
                            <div class="elementor-control elementor-control-type-textarea">
                                <div class="elementor-control-content">
                                    <div class="elementor-control-field">
                                        <label class="elementor-control-title">
                                            ${wpaiElementor.strings.promptPlaceholder}
                                        </label>
                                        <div class="elementor-control-input-wrapper">
                                            <textarea 
                                                id="wpai-elementor-prompt" 
                                                class="elementor-control-tag-area" 
                                                rows="6"
                                                placeholder="${wpaiElementor.strings.promptPlaceholder}"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="elementor-control" style="margin-top: 15px;">
                                <div class="elementor-control-content">
                                    <div class="elementor-control-field">
                                        <div class="elementor-control-input-wrapper">
                                            <button 
                                                id="wpai-elementor-apply" 
                                                class="elementor-button elementor-button-success"
                                                style="width: 100%; padding: 12px; font-size: 13px; font-weight: 500;"
                                            >
                                                ${wpaiElementor.strings.generating}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="wpai-elementor-status" style="margin-top: 15px; display: none;"></div>
                            <div id="wpai-elementor-preview" style="margin-top: 15px; display: none;"></div>
                        </div>
                    </div>
                </div>
            `);

            // Add menu item
            $panelMenu.append(menuItem);

            // Add panel content to panel wrapper
            const $panelWrapper = $('.elementor-panel-wrapper, #elementor-panel');
            if ($panelWrapper.length) {
                $panelWrapper.append(panelContent);
            } else {
                $('body').append(panelContent);
            }

            // Handle menu item click
            menuItem.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Hide all panels
                $('.elementor-panel-menu-item').removeClass('elementor-active');
                $('.elementor-panel').hide();
                
                // Show our panel
                $(this).addClass('elementor-active');
                $('#elementor-panel-wpai-assistant').show();
                
                // Close Elementor's default panels
                if (elementor.panels && elementor.panels.currentView) {
                    elementor.panels.currentView.hide();
                }
            });

            // Bind apply button
            $('#wpai-elementor-apply').off('click').on('click', function() {
                WPAIElementor.processRequest();
            });

            this.panelTabAdded = true;
        },

        showPanel: function() {
            $('#elementor-panel-menu-item-wpai-assistant').trigger('click');
        },

        processRequest: function() {
            const prompt = $('#wpai-elementor-prompt').val().trim();
            if (!prompt) {
                WPAIElementor.showStatus('error', wpaiElementor.strings.error + ': ' + wpaiElementor.strings.promptPlaceholder);
                return;
            }

            const $button = $('#wpai-elementor-apply');
            const $status = $('#wpai-elementor-status');
            const $preview = $('#wpai-elementor-preview');

            $button.prop('disabled', true).text(wpaiElementor.strings.generating);
            $status.hide();
            $preview.hide();

            // Get current Elementor structure
            $.ajax({
                url: wpaiElementor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_elementor_get_structure',
                    nonce: wpaiElementor.nonce,
                    post_id: wpaiElementor.postId
                },
                success: function(response) {
                    if (response.success) {
                        // Send prompt to AI with Elementor structure
                        WPAIElementor.sendToAI(prompt, response.data.structure);
                    } else {
                        WPAIElementor.showStatus('error', response.data.message || wpaiElementor.strings.error);
                        $button.prop('disabled', false).text(wpaiElementor.strings.generating);
                    }
                },
                error: function() {
                    WPAIElementor.showStatus('error', wpaiElementor.strings.error);
                    $button.prop('disabled', false).text(wpaiElementor.strings.generating);
                }
            });
        },

        sendToAI: function(prompt, elementorStructure) {
            const $button = $('#wpai-elementor-apply');
            const $status = $('#wpai-elementor-status');
            const self = this;

            // Use the chat API to process the request
            $.ajax({
                url: wpaiElementor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_send_message',
                    nonce: wpaiElementor.nonce,
                    message: `In this Elementor page (using container-based structure), ${prompt}. The current structure is: ${JSON.stringify(elementorStructure)}`,
                    context: JSON.stringify({
                        selected_post_id: wpaiElementor.postId,
                        editor_type: 'elementor',
                        structure_type: 'container'
                    }),
                    settings: JSON.stringify({})
                },
                success: function(response) {
                    if (response.success) {
                        // Parse the response to extract Elementor changes
                        self.parseAIResponse(response, elementorStructure);
                    } else {
                        self.showStatus('error', response.data.message || wpaiElementor.strings.error);
                        $button.prop('disabled', false).text(wpaiElementor.strings.generating);
                    }
                },
                error: function() {
                    self.showStatus('error', wpaiElementor.strings.error);
                    $button.prop('disabled', false).text(wpaiElementor.strings.generating);
                }
            });
        },

        parseAIResponse: function(response, currentStructure) {
            const $button = $('#wpai-elementor-apply');
            const aiResponse = response.data.response || '';
            
            // Try to extract JSON from AI response
            let elementorData = null;
            
            // Look for JSON in the response
            const jsonMatch = aiResponse.match(/\{[\s\S]*\}/);
            if (jsonMatch) {
                try {
                    elementorData = JSON.parse(jsonMatch[0]);
                } catch (e) {
                    console.error('Failed to parse JSON from AI response', e);
                }
            }

            // If function calls were made, changes should be applied
            if (response.data.function_calls && response.data.function_calls.length > 0) {
                // Function calls were made, changes should be applied
                this.showStatus('success', wpaiElementor.strings.success);
                $('#wpai-elementor-prompt').val('');
                
                // Reload Elementor editor to show changes
                setTimeout(() => {
                    if (typeof elementor !== 'undefined') {
                        // Refresh the editor
                        if (elementor.reloadPreview) {
                            elementor.reloadPreview();
                        }
                        // Save auto draft
                        if (elementor.saver && elementor.saver.saveAutoDraft) {
                            elementor.saver.saveAutoDraft();
                        }
                    }
                }, 1000);
            } else if (elementorData && elementorData.content) {
                // Apply the Elementor data directly (container-based structure)
                this.applyElementorData(elementorData);
            } else {
                // Show response as preview
                this.showPreview(aiResponse);
            }

            $button.prop('disabled', false).text(wpaiElementor.strings.generating);
        },

        applyElementorData: function(elementorData) {
            const $status = $('#wpai-elementor-status');
            
            // Ensure structure is container-based
            if (!elementorData.content) {
                // Convert old structure to new container-based structure
                elementorData = {
                    content: elementorData,
                    version: '0.4'
                };
            }
            
            $.ajax({
                url: wpaiElementor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_elementor_apply',
                    nonce: wpaiElementor.nonce,
                    post_id: wpaiElementor.postId,
                    elementor_data: JSON.stringify(elementorData)
                },
                success: function(response) {
                    if (response.success) {
                        WPAIElementor.showStatus('success', wpaiElementor.strings.success);
                        $('#wpai-elementor-prompt').val('');
                        
                        // Reload Elementor editor
                        setTimeout(() => {
                            if (typeof elementor !== 'undefined') {
                                if (elementor.reloadPreview) {
                                    elementor.reloadPreview();
                                }
                                if (elementor.saver && elementor.saver.saveAutoDraft) {
                                    elementor.saver.saveAutoDraft();
                                }
                                // Force refresh
                                if (elementor.getPreviewContainer) {
                                    const container = elementor.getPreviewContainer();
                                    if (container && container.refresh) {
                                        container.refresh();
                                    }
                                }
                            }
                        }, 500);
                    } else {
                        WPAIElementor.showStatus('error', response.data.message || wpaiElementor.strings.error);
                    }
                },
                error: function() {
                    WPAIElementor.showStatus('error', wpaiElementor.strings.error);
                }
            });
        },

        showStatus: function(type, message) {
            const $status = $('#wpai-elementor-status');
            const className = type === 'success' ? 'notice notice-success' : 'notice notice-error';
            $status
                .removeClass('notice notice-success notice-error')
                .addClass(className)
                .html(`<p>${message}</p>`)
                .show();
        },

        showPreview: function(content) {
            const $preview = $('#wpai-elementor-preview');
            $preview
                .html(`<div style="padding: 10px; background: #f0f0f1; border-radius: 4px;"><strong>Preview:</strong><div style="margin-top: 10px;">${content}</div></div>`)
                .show();
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        setTimeout(() => {
            WPAIElementor.init();
        }, 500);
    });

    // Initialize when Elementor loads
    $(window).on('elementor:init', function() {
        WPAIElementor.init();
    });

    // Initialize when Elementor frontend is ready
    $(window).on('elementor/frontend/init', function() {
        WPAIElementor.init();
    });

    // Initialize when panel is opened
    $(window).on('elementor:panel:init', function() {
        setTimeout(() => {
            WPAIElementor.addPanelTab();
        }, 300);
    });

    // Also try when editor is loaded
    if (typeof elementor !== 'undefined' && elementor.on) {
        elementor.on('preview:loaded', function() {
            WPAIElementor.init();
        });
    }

})(jQuery);

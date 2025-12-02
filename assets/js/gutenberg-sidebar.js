/**
 * Gutenberg Sidebar Integration
 */

(function() {
    'use strict';

    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { PanelBody, TextareaControl, Button, Spinner, Notice } = wp.components;
    const { useState, useEffect } = wp.element;
    const { dispatch, useSelect } = wp.data;
    const { __ } = wp.i18n;
    const apiFetch = wp.apiFetch;

    function WPAISidebar() {
        const [prompt, setPrompt] = useState('');
        const [isGenerating, setIsGenerating] = useState(false);
        const [preview, setPreview] = useState(null);
        const [error, setError] = useState(null);
        const [isApplying, setIsApplying] = useState(false);

        const postContent = useSelect((select) => {
            return select('core/editor').getEditedPostContent();
        }, []);

        const postTitle = useSelect((select) => {
            return select('core/editor').getEditedPostAttribute('title');
        }, []);

        const handleGenerate = async () => {
            if (!prompt.trim()) {
                setError(__('Please enter a prompt', 'wpai-assistant'));
                return;
            }

            setIsGenerating(true);
            setError(null);
            setPreview(null);

            try {
                const response = await jQuery.ajax({
                    url: wpaiGutenberg.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpai_generate_content',
                        nonce: wpaiGutenberg.nonce,
                        prompt: prompt,
                        post_type: wpaiGutenberg.postType,
                        context: JSON.stringify({
                            post_id: wpaiGutenberg.postId,
                            current_content: postContent,
                            current_title: postTitle,
                            format: 'gutenberg'
                        }),
                        settings: JSON.stringify({})
                    }
                });

                if (response.success) {
                    setPreview(response.data.content);
                } else {
                    setError(response.data.message || __('An error occurred', 'wpai-assistant'));
                }
            } catch (err) {
                setError(err.message || __('An error occurred', 'wpai-assistant'));
            } finally {
                setIsGenerating(false);
            }
        };

        const handleImprove = async () => {
            if (!postContent || !postContent.trim()) {
                setError(__('No content to improve. Please add some content first.', 'wpai-assistant'));
                return;
            }

            const improvePrompt = prompt.trim() || __('Make it more engaging and SEO-friendly', 'wpai-assistant');
            setIsGenerating(true);
            setError(null);
            setPreview(null);

            try {
                const fullPrompt = `Improve the following content: ${postContent}\n\nInstructions: ${improvePrompt}`;
                const response = await jQuery.ajax({
                    url: wpaiGutenberg.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpai_generate_content',
                        nonce: wpaiGutenberg.nonce,
                        prompt: fullPrompt,
                        post_type: wpaiGutenberg.postType,
                        context: JSON.stringify({
                            post_id: wpaiGutenberg.postId,
                            current_content: postContent,
                            current_title: postTitle,
                            action: 'improve',
                            format: 'gutenberg'
                        }),
                        settings: JSON.stringify({})
                    }
                });

                if (response.success) {
                    setPreview(response.data.content);
                } else {
                    setError(response.data.message || __('An error occurred', 'wpai-assistant'));
                }
            } catch (err) {
                setError(err.message || __('An error occurred', 'wpai-assistant'));
            } finally {
                setIsGenerating(false);
            }
        };

        const handleApply = async () => {
            if (!preview) {
                return;
            }

            setIsApplying(true);
            setError(null);

            try {
                // Check if preview contains block format
                let contentToApply = preview;
                
                // If preview is HTML, try to convert to blocks
                if (preview.includes('<!-- wp:') || preview.includes('<!--wp:')) {
                    // Already in block format
                    contentToApply = preview;
                } else {
                    // Try to parse as HTML and convert to blocks
                    // For now, wrap in paragraph block if it's plain HTML
                    if (preview.trim() && !preview.includes('<!--')) {
                        // Simple HTML content - wrap in paragraph blocks
                        const htmlContent = preview.replace(/\n\n/g, '</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>');
                        contentToApply = '<!-- wp:paragraph --><p>' + htmlContent + '</p><!-- /wp:paragraph -->';
                    } else {
                        contentToApply = preview;
                    }
                }
                
                // Update editor content directly
                dispatch('core/editor').editPost({ content: contentToApply });
                
                // Also save via AJAX for persistence
                const response = await jQuery.ajax({
                    url: wpaiGutenberg.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpai_gutenberg_apply',
                        nonce: wpaiGutenberg.nonce,
                        post_id: wpaiGutenberg.postId,
                        content: contentToApply
                    }
                });

                if (response.success) {
                    setPreview(null);
                    setPrompt('');
                    // Trigger save
                    dispatch('core/editor').savePost();
                } else {
                    setError(response.data.message || __('Failed to apply changes', 'wpai-assistant'));
                }
            } catch (err) {
                setError(err.message || __('An error occurred', 'wpai-assistant'));
            } finally {
                setIsApplying(false);
            }
        };

        const handleDismiss = () => {
            setPreview(null);
            setError(null);
        };

        return (
            <PluginSidebar
                name="wpai-assistant-sidebar"
                title={wpaiGutenberg.strings.title}
                icon="admin-generic"
            >
                <PanelBody>
                    <TextareaControl
                        label={__('AI Prompt', 'wpai-assistant')}
                        value={prompt}
                        onChange={(value) => {
                            setPrompt(value);
                            setError(null);
                        }}
                        placeholder={wpaiGutenberg.strings.promptPlaceholder}
                        rows={4}
                    />

                    <div style={{ marginTop: '15px', display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                        <Button
                            isPrimary
                            onClick={handleGenerate}
                            disabled={isGenerating || isApplying}
                        >
                            {isGenerating ? (
                                <>
                                    <Spinner />
                                    {wpaiGutenberg.strings.generating}
                                </>
                            ) : (
                                wpaiGutenberg.strings.generate
                            )}
                        </Button>

                        <Button
                            onClick={handleImprove}
                            disabled={isGenerating || isApplying || !postContent}
                        >
                            {wpaiGutenberg.strings.improve}
                        </Button>
                    </div>

                    {error && (
                        <Notice status="error" isDismissible onRemove={() => setError(null)}>
                            {error}
                        </Notice>
                    )}

                    {preview && (
                        <PanelBody title={wpaiGutenberg.strings.preview} initialOpen={true}>
                            <div
                                style={{
                                    padding: '10px',
                                    background: '#f0f0f1',
                                    borderRadius: '4px',
                                    marginBottom: '15px',
                                    maxHeight: '400px',
                                    overflow: 'auto'
                                }}
                                dangerouslySetInnerHTML={{ __html: preview }}
                            />
                            <div style={{ display: 'flex', gap: '10px' }}>
                                <Button
                                    isPrimary
                                    onClick={handleApply}
                                    disabled={isApplying}
                                >
                                    {isApplying ? (
                                        <>
                                            <Spinner />
                                            {wpaiGutenberg.strings.applying}
                                        </>
                                    ) : (
                                        wpaiGutenberg.strings.apply
                                    )}
                                </Button>
                                <Button onClick={handleDismiss}>
                                    {wpaiGutenberg.strings.dismiss}
                                </Button>
                            </div>
                        </PanelBody>
                    )}
                </PanelBody>
            </PluginSidebar>
        );
    }

    // Register the sidebar plugin
    registerPlugin('wpai-assistant-sidebar', {
        render: WPAISidebar,
        icon: 'admin-generic',
    });

    // Also add to more menu
    registerPlugin('wpai-assistant-more-menu', {
        render: () => (
            <PluginSidebarMoreMenuItem target="wpai-assistant-sidebar">
                {wpaiGutenberg.strings.title}
            </PluginSidebarMoreMenuItem>
        ),
    });

})();


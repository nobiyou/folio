/**
 * Folio AI Content Generator - Admin Scripts
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // AI生成按钮点击事件
        $('.folio-ai-generate-btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var action = $btn.data('action');
            
            // 获取文章标题
            var title = '';
            
            // Gutenberg编辑器
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                title = wp.data.select('core/editor').getEditedPostAttribute('title');
            } 
            // 经典编辑器
            else if ($('#title').length) {
                title = $('#title').val();
            }
            
            // 获取文章内容
            var content = '';
            
            // Gutenberg编辑器 - 获取纯文本内容
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
                var blocks = wp.data.select('core/block-editor').getBlocks();
                content = blocks.map(function(block) {
                    return wp.blocks.getBlockContent(block);
                }).join('\n');
                // 移除HTML标签
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = content;
                content = tempDiv.textContent || tempDiv.innerText || '';
            }
            // 经典编辑器 - TinyMCE
            else if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                content = tinymce.get('content').getContent({format: 'text'});
            } 
            // 经典编辑器 - 文本模式
            else if ($('#content').length) {
                content = $('#content').val();
            }
            
            if (!title && !content) {
                alert(folioAI.strings.no_content);
                return;
            }
            
            // 显示加载状态
            $('.folio-ai-status').show();
            $('.folio-ai-result').hide();
            $('.folio-ai-generate-btn').prop('disabled', true);
            
            // 发送AJAX请求
            $.ajax({
                url: folioAI.ajax_url,
                type: 'POST',
                data: {
                    action: 'folio_ai_generate',
                    nonce: folioAI.nonce,
                    post_id: folioAI.post_id,
                    action_type: action,
                    title: title,
                    content: content
                },
                success: function(response) {
                    if (response.success) {
                        handleSuccess(response.data, action);
                    } else {
                        handleError(response.data.message);
                    }
                },
                error: function() {
                    handleError(folioAI.strings.error);
                },
                complete: function() {
                    $('.folio-ai-status').hide();
                    $('.folio-ai-generate-btn').prop('disabled', false);
                }
            });
        });
        
        // 处理成功响应
        function handleSuccess(data, action) {
            var message = '<strong>' + folioAI.strings.success + '</strong><br>';
            
            // 更新摘要
            if (data.excerpt) {
                // Gutenberg编辑器
                if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                    wp.data.dispatch('core/editor').editPost({
                        excerpt: data.excerpt
                    });
                } 
                // 经典编辑器
                else {
                    $('#excerpt').val(data.excerpt);
                }
                message += '✓ 摘要已生成<br>';
            }
            
            // 更新标签
            if (data.tags && data.tags.length > 0) {
                updateTags(data.tags);
                message += '✓ 关键词已生成: ' + data.tags.join(', ');
            }
            
            // 显示成功消息
            $('.folio-ai-result')
                .removeClass('error')
                .html(message)
                .show();
            
            // 3秒后自动隐藏
            setTimeout(function() {
                $('.folio-ai-result').fadeOut();
            }, 5000);
        }
        
        // 处理错误响应
        function handleError(message) {
            $('.folio-ai-result')
                .addClass('error')
                .html('<strong>错误：</strong>' + message)
                .show();
        }
        
        // 更新标签
        function updateTags(tags) {
            // Gutenberg编辑器
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                // 获取当前标签
                var currentTags = wp.data.select('core/editor').getEditedPostAttribute('tags') || [];
                
                // 异步处理每个标签
                var processedCount = 0;
                var newTagIds = [];
                
                tags.forEach(function(tagName) {
                    // 使用REST API创建或获取标签
                    wp.apiFetch({
                        path: '/wp/v2/tags',
                        method: 'POST',
                        data: {
                            name: tagName
                        }
                    }).then(function(tag) {
                        newTagIds.push(tag.id);
                        processedCount++;
                        
                        // 所有标签处理完成后更新
                        if (processedCount === tags.length) {
                            updatePostTags(currentTags, newTagIds);
                        }
                    }).catch(function(error) {
                        // 如果标签已存在（409错误），尝试查找
                        if (error.code === 'term_exists' && error.data && error.data.term_id) {
                            newTagIds.push(error.data.term_id);
                        } else {
                            // 尝试通过搜索查找标签
                            wp.apiFetch({
                                path: '/wp/v2/tags?search=' + encodeURIComponent(tagName)
                            }).then(function(foundTags) {
                                if (foundTags && foundTags.length > 0) {
                                    newTagIds.push(foundTags[0].id);
                                }
                            }).catch(function() {
                                // 标签查找失败，静默处理
                            }).finally(function() {
                                processedCount++;
                                if (processedCount === tags.length) {
                                    updatePostTags(currentTags, newTagIds);
                                }
                            });
                            return;
                        }
                        
                        processedCount++;
                        if (processedCount === tags.length) {
                            updatePostTags(currentTags, newTagIds);
                        }
                    });
                });
                
                // 更新文章标签的辅助函数
                function updatePostTags(current, newIds) {
                    var allTagIds = current.concat(newIds.filter(function(id) {
                        return current.indexOf(id) === -1;
                    }));
                    
                    wp.data.dispatch('core/editor').editPost({
                        tags: allTagIds
                    });
                }
            }
            // 经典编辑器的标签输入框
            else if ($('#new-tag-post_tag').length) {
                var currentTags = $('#tax-input-post_tag').val();
                var newTags = tags.join(',');
                
                if (currentTags) {
                    $('#tax-input-post_tag').val(currentTags + ',' + newTags);
                } else {
                    $('#tax-input-post_tag').val(newTags);
                }
                
                // 触发标签更新
                $('.tagchecklist').html('');
                tags.forEach(function(tag) {
                    $('.tagchecklist').append(
                        '<span><a class="ntdelbutton">X</a>&nbsp;' + tag + '</span>'
                    );
                });
            }
        }
        
    });
    
})(jQuery);

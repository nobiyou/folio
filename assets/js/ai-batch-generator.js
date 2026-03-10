/**
 * AI Batch Generator
 * 批量AI生成器前端脚本
 */

(function($) {
    'use strict';
    
    let postsToGenerate = [];
    let currentIndex = 0;
    let isGenerating = false;
    
    $(document).ready(function() {
        // 扫描文章按钮
        $('#scan-posts-btn').on('click', function() {
            scanPosts();
        });
        
        // 开始批量生成按钮
        $('#start-batch-btn').on('click', function() {
            startBatchGeneration();
        });
    });
    
    /**
     * 扫描文章
     */
    function scanPosts() {
        const $btn = $('#scan-posts-btn');
        const postType = $('#post-type').val();
        const missingExcerpt = $('#missing-excerpt').is(':checked') ? '1' : '0';
        const missingTags = $('#missing-tags').is(':checked') ? '1' : '0';
        
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>' + folioAIBatch.strings.scanning);
        
        $.ajax({
            url: folioAIBatch.ajax_url,
            type: 'POST',
            data: {
                action: 'folio_ai_batch_scan',
                nonce: folioAIBatch.nonce,
                post_type: postType,
                missing_excerpt: missingExcerpt,
                missing_tags: missingTags
            },
            success: function(response) {
                if (response.success) {
                    displayScanResults(response.data.posts);
                } else {
                    alert(response.data.message || 'Scan failed');
                }
            },
            error: function() {
                alert('Network error');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span>' + folioAIBatch.strings.scanning.replace('...', ''));
            }
        });
    }
    
    /**
     * 显示扫描结果
     */
    function displayScanResults(posts) {
        const $resultsSection = $('#scan-results');
        const $resultsContent = $('#scan-results-content');
        
        if (posts.length === 0) {
            $resultsContent.html('<div class="notice notice-info inline"><p>' + folioAIBatch.strings.no_posts + '</p></div>');
            $resultsSection.show();
            $('#batch-actions').hide();
            return;
        }
        
        postsToGenerate = posts;
        
        let html = '<p>' + folioAIBatch.strings.found_posts.replace('%d', posts.length) + '</p>';
        html += '<table class="scan-results-table">';
        html += '<thead><tr>';
        html += '<th>' + folioAIBatch.strings.post + '</th>';
        html += '<th>' + folioAIBatch.strings.missing_excerpt + '</th>';
        html += '<th>' + folioAIBatch.strings.missing_tags + '</th>';
        html += '</tr></thead>';
        html += '<tbody>';
        
        posts.forEach(function(post) {
            html += '<tr>';
            html += '<td class="post-title">' + escapeHtml(post.title) + '</td>';
            html += '<td>' + (post.needs_excerpt ? '<span class="missing-badge">✓</span>' : '-') + '</td>';
            html += '<td>' + (post.needs_tags ? '<span class="missing-badge">✓</span>' : '-') + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $resultsContent.html(html);
        $resultsSection.show();
        $('#batch-actions').show();
    }
    
    /**
     * 开始批量生成
     */
    function startBatchGeneration() {
        if (isGenerating) {
            return;
        }
        
        isGenerating = true;
        currentIndex = 0;
        
        $('#start-batch-btn').prop('disabled', true);
        $('#generation-progress').show();
        $('#generation-log').html('');
        $('#generation-complete').hide();
        
        updateProgress();
        generateNext();
    }
    
    /**
     * 生成下一篇文章
     */
    function generateNext() {
        if (currentIndex >= postsToGenerate.length) {
            completeGeneration();
            return;
        }
        
        const post = postsToGenerate[currentIndex];
        
        addLogEntry('info', folioAIBatch.strings.generating + ' ' + escapeHtml(post.title));
        
        $.ajax({
            url: folioAIBatch.ajax_url,
            type: 'POST',
            data: {
                action: 'folio_ai_batch_generate',
                nonce: folioAIBatch.nonce,
                post_id: post.id,
                generate_excerpt: post.needs_excerpt ? '1' : '0',
                generate_tags: post.needs_tags ? '1' : '0'
            },
            success: function(response) {
                if (response.success) {
                    let message = folioAIBatch.strings.success + ': ' + escapeHtml(post.title);
                    if (response.data.excerpt_generated) {
                        message += ' - ' + folioAIBatch.strings.excerpt_generated;
                    }
                    if (response.data.tags_generated) {
                        message += ' - ' + folioAIBatch.strings.tags_generated;
                    }
                    addLogEntry('success', message);
                } else {
                    addLogEntry('error', folioAIBatch.strings.error + ': ' + escapeHtml(post.title) + ' - ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                addLogEntry('error', folioAIBatch.strings.error + ': ' + escapeHtml(post.title) + ' - Network error');
            },
            complete: function() {
                currentIndex++;
                updateProgress();
                
                // 延迟1秒后处理下一篇，避免API请求过快
                setTimeout(function() {
                    generateNext();
                }, 1000);
            }
        });
    }
    
    /**
     * 更新进度
     */
    function updateProgress() {
        const total = postsToGenerate.length;
        const current = currentIndex;
        const percentage = total > 0 ? Math.round((current / total) * 100) : 0;
        
        $('.progress-bar-fill').css('width', percentage + '%');
        $('.progress-text').text(current + ' / ' + total);
    }
    
    /**
     * 完成生成
     */
    function completeGeneration() {
        isGenerating = false;
        $('#generation-complete').show();
        $('#start-batch-btn').prop('disabled', false);
        addLogEntry('success', folioAIBatch.strings.completed);
    }
    
    /**
     * 添加日志条目
     */
    function addLogEntry(type, message) {
        const timestamp = new Date().toLocaleTimeString();
        const $log = $('#generation-log');
        const entry = '<div class="log-entry ' + type + '">' +
            '<span class="timestamp">' + timestamp + '</span>' +
            '<span class="message">' + message + '</span>' +
            '</div>';
        
        $log.append(entry);
        $log.scrollTop($log[0].scrollHeight);
    }
    
    /**
     * HTML转义
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
})(jQuery);

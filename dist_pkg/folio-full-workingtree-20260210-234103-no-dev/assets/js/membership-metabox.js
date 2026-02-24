/**
 * Membership Meta Box JavaScript
 * 会员保护设置元框交互功能
 */

(function($) {
    'use strict';
    
    // 全局变量
    let folioMetaBoxData = window.folioMetaBox || {};
    function t(key, fallback) {
        if (folioMetaBoxData.strings && folioMetaBoxData.strings[key]) {
            return folioMetaBoxData.strings[key];
        }
        return fallback;
    }
    
    $(document).ready(function() {
        initMetaBox();
        bindEvents();
    });
    
    /**
     * 初始化元框
     */
    function initMetaBox() {
        // 初始化预览模式显示
        togglePreviewSettings();
        
        // 初始化范围滑块
        updateRangeValues();
        
        // 初始化会员等级选择
        updateLevelSelection();
    }
    
    /**
     * 绑定事件
     */
    function bindEvents() {
        // 保护开关切换
        $(document).on('change', '.folio-protection-toggle', function() {
            const panel = $('#folio-protection-panel');
            if ($(this).is(':checked')) {
                panel.slideDown(300);
            } else {
                panel.slideUp(300);
            }
        });
        
        // 预览模式切换
        $(document).on('change', '#folio_preview_mode', function() {
            togglePreviewSettings();
        });
        
        // 范围滑块实时更新
        $(document).on('input', '.folio-range', function() {
            updateRangeValue($(this));
        });
        
        // 会员等级选择
        $(document).on('change', '.folio-level-option input[type="radio"]', function() {
            updateLevelSelection();
        });
        
        // 预览按钮
        $(document).on('click', '#folio-preview-btn', function() {
            generatePreview();
        });
        
        // 批量操作按钮
        $(document).on('click', '#folio-bulk-btn', function() {
            openBulkModal();
        });
        
        // 模态框关闭
        $(document).on('click', '.folio-modal-close, .folio-modal', function(e) {
            if (e.target === this) {
                closeBulkModal();
            }
        });
        
        // 阻止模态框内容区域点击关闭
        $(document).on('click', '.folio-modal-content', function(e) {
            e.stopPropagation();
        });
        
        // 批量操作类型切换
        $(document).on('change', '#folio-bulk-action', function() {
            updateBulkSettings($(this).val());
        });
        
        // ESC键关闭模态框
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // ESC key
                closeBulkModal();
            }
        });
    }
    
    /**
     * 切换预览设置显示
     */
    function togglePreviewSettings() {
        const $modeSelect = $('#folio_preview_mode');
        
        // 如果元素不存在，直接返回
        if (!$modeSelect.length) {
            return;
        }
        
        const mode = $modeSelect.val();
        
        // 如果 mode 为空或 undefined，使用默认值
        if (!mode || typeof mode !== 'string') {
            return;
        }
        
        // 隐藏所有预览选项
        $('.folio-preview-option').hide();
        
        // 显示对应的预览选项
        const targetId = '#folio-' + mode.replace('_', '-') + '-settings';
        const $target = $(targetId);
        
        // 如果目标元素存在，显示它
        if ($target.length) {
            $target.show();
        }
    }
    
    /**
     * 更新范围滑块值显示
     */
    function updateRangeValues() {
        $('.folio-range').each(function() {
            updateRangeValue($(this));
        });
    }
    
    /**
     * 更新单个范围滑块值
     */
    function updateRangeValue($slider) {
        const value = $slider.val();
        const $valueSpan = $slider.siblings('.folio-range-value');
        
        if ($valueSpan.length) {
            const unit = $slider.attr('id').includes('length') ? (' ' + t('chars', 'chars')) : '%';
            $valueSpan.text(value + unit);
        }
    }
    
    /**
     * 更新会员等级选择状态
     */
    function updateLevelSelection() {
        $('.folio-level-option').removeClass('active');
        $('.folio-level-option input:checked').closest('.folio-level-option').addClass('active');
    }
    
    /**
     * 生成内容预览
     */
    function generatePreview() {
        const $btn = $('#folio-preview-btn');
        const $result = $('#folio-preview-result');
        const $content = $('.folio-preview-content');
        
        // 获取当前设置
        const settings = {
            post_id: $('#post_ID').val(),
            preview_mode: $('#folio_preview_mode').val(),
            preview_length: $('#folio_preview_length').val(),
            preview_percentage: $('#folio_preview_percentage').val(),
            preview_custom: $('#folio_preview_custom').val(),
            nonce: folioMetaBoxData.nonce
        };
        
        // 显示加载状态
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + folioMetaBoxData.strings.preview_loading);
        
        // 发送AJAX请求
        $.post(folioMetaBoxData.ajaxurl, {
            action: 'folio_preview_content',
            ...settings
        })
        .done(function(response) {
            if (response.success) {
                $content.html(response.data.preview);
                $result.show();
                
                // 显示统计信息
                const stats = `${t('original_length', 'Original length')}: ${response.data.original_length} ${t('chars', 'chars')}, ${t('preview_length', 'Preview length')}: ${response.data.preview_length} ${t('chars', 'chars')}`;
                $result.find('h4').html(t('preview_result', 'Preview Result') + ' <small>(' + stats + ')</small>');
            } else {
                showNotice('error', response.data || folioMetaBoxData.strings.preview_error);
            }
        })
        .fail(function() {
            showNotice('error', folioMetaBoxData.strings.preview_error);
        })
        .always(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-visibility"></span> ' + t('generate_preview', 'Generate preview'));
        });
    }
    
    /**
     * 打开批量操作模态框
     */
    function openBulkModal() {
        $('#folio-bulk-modal').fadeIn(300);
        loadRecentPosts();
    }
    
    /**
     * 关闭批量操作模态框
     */
    function closeBulkModal() {
        $('#folio-bulk-modal').fadeOut(300);
    }
    
    /**
     * 加载最近文章
     */
    function loadRecentPosts() {
        const $list = $('#folio-post-list');
        $list.html('<div class="folio-loading">' + t('loading', 'Loading...') + '</div>');
        
        $.post(folioMetaBoxData.ajaxurl, {
            action: 'folio_load_recent_posts',
            nonce: folioMetaBoxData.nonce
        })
        .done(function(response) {
            if (response.success) {
                $list.html(response.data.html);
            } else {
                $list.html('<div class="folio-error">' + t('load_failed', 'Load failed') + '</div>');
            }
        })
        .fail(function() {
            $list.html('<div class="folio-error">' + t('load_failed', 'Load failed') + '</div>');
        });
    }
    
    /**
     * 更新批量设置选项
     */
    function updateBulkSettings(action) {
        const $settings = $('#folio-bulk-settings');
        let html = '';
        
        switch(action) {
            case 'enable_protection':
                html = `
                    <label>${t('membership_level', 'Membership level')}：</label>
                    <select name="bulk_level" class="folio-select">
                        <option value="vip">${t('vip_member', 'VIP Member')}</option>
                        <option value="svip">${t('svip_member', 'SVIP Member')}</option>
                    </select>
                `;
                break;
                
            case 'change_level':
                html = `
                    <label>${t('new_membership_level', 'New membership level')}：</label>
                    <select name="bulk_level" class="folio-select">
                        <option value="vip">${t('vip_member', 'VIP Member')}</option>
                        <option value="svip">${t('svip_member', 'SVIP Member')}</option>
                    </select>
                `;
                break;
                
            case 'set_preview_mode':
                html = `
                    <label>${t('preview_mode', 'Preview mode')}：</label>
                    <select name="bulk_preview_mode" class="folio-select">
                        <option value="auto">${t('preview_auto', 'Auto preview')}</option>
                        <option value="percentage">${t('preview_percentage', 'Percentage preview')}</option>
                        <option value="custom">${t('preview_custom', 'Custom preview')}</option>
                        <option value="none">${t('preview_none', 'No preview')}</option>
                    </select>
                    <div style="margin-top:10px;">
                        <label>${t('preview_length_auto', 'Preview length (auto mode)')}：</label>
                        <input type="number" name="bulk_preview_length" value="200" min="50" max="1000" class="folio-select">
                    </div>
                    <div style="margin-top:10px;">
                        <label>${t('preview_percentage_mode', 'Preview percentage (percentage mode)')}：</label>
                        <input type="number" name="bulk_preview_percentage" value="30" min="10" max="80" class="folio-select">%
                    </div>
                `;
                break;
        }
        
        if (html) {
            $settings.html(html).show();
        } else {
            $settings.hide();
        }
    }
    
    /**
     * 执行批量操作
     */
    function executeBulkAction() {
        const action = $('#folio-bulk-action').val();
        const selectedPosts = [];
        
        // 获取选中的文章
        $('#folio-post-list input:checked').each(function() {
            selectedPosts.push($(this).val());
        });
        
        if (!action) {
            showNotice('error', t('select_action_type', 'Please select an action type'));
            return;
        }
        
        if (selectedPosts.length === 0) {
            showNotice('error', t('select_posts', 'Please select posts to operate'));
            return;
        }
        
        // 收集设置
        const settings = {};
        $('#folio-bulk-settings input, #folio-bulk-settings select').each(function() {
            const name = $(this).attr('name');
            if (name) {
                settings[name.replace('bulk_', '')] = $(this).val();
            }
        });
        
        // 显示加载状态
        const $btn = $('.folio-modal-footer .button-primary');
        $btn.prop('disabled', true).text(t('executing', 'Executing...'));
        
        // 发送AJAX请求
        $.post(folioMetaBoxData.ajaxurl, {
            action: 'folio_bulk_protection',
            bulk_action: action,
            post_ids: selectedPosts,
            settings: settings,
            nonce: folioMetaBoxData.nonce
        })
        .done(function(response) {
            if (response.success) {
                showNotice('success', response.data.message);
                closeBulkModal();
            } else {
                showNotice('error', response.data || folioMetaBoxData.strings.bulk_error);
            }
        })
        .fail(function() {
            showNotice('error', folioMetaBoxData.strings.bulk_error);
        })
        .always(function() {
            $btn.prop('disabled', false).text(t('execute_action', 'Execute action'));
        });
    }
    
    /**
     * 显示通知
     */
    function showNotice(type, message) {
        const $notice = $('<div class="folio-notice ' + type + '">' + message + '</div>');
        
        // 移除现有通知
        $('.folio-notice').remove();
        
        // 添加新通知
        $('.folio-membership-metabox').prepend($notice);
        
        // 自动隐藏
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * 设置预设配置
     */
    window.folioSetPreset = function(type) {
        const $previewMode = $('#folio_preview_mode');
        const $previewLength = $('#folio_preview_length');
        const $previewPercentage = $('#folio_preview_percentage');
        const $seoVisible = $('input[name="folio_seo_visible"]');
        const $rssInclude = $('input[name="folio_rss_include"]');
        
        switch(type) {
            case 'light':
                $previewMode.val('percentage').trigger('change');
                $previewPercentage.val(50).trigger('input');
                $seoVisible.prop('checked', true);
                $rssInclude.prop('checked', true);
                break;
                
            case 'medium':
                $previewMode.val('auto').trigger('change');
                $previewLength.val(300).trigger('input');
                $seoVisible.prop('checked', true);
                $rssInclude.prop('checked', false);
                break;
                
            case 'strict':
                $previewMode.val('none').trigger('change');
                $seoVisible.prop('checked', false);
                $rssInclude.prop('checked', false);
                break;
        }
        
        showNotice('success', t('preset_applied_prefix', 'Applied ') + type + t('preset_applied_suffix', ' protection preset'));
    };
    
    /**
     * 加载最近文章（全局函数）
     */
    window.folioLoadRecentPosts = function() {
        loadRecentPosts();
    };
    
    /**
     * 执行批量操作（全局函数）
     */
    window.folioExecuteBulkAction = function() {
        executeBulkAction();
    };
    
    /**
     * 关闭批量模态框（全局函数）
     */
    window.folioCloseBulkModal = function() {
        closeBulkModal();
    };
    
})(jQuery);

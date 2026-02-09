/**
 * Folio Theme Options - Admin Scripts
 */

(function($) {
    'use strict';
    
    // 调试：确认脚本已加载
    $(document).ready(function() {
        // Admin options script loaded
        
        // 表单验证
        $('form').on('submit', function(e) {
            var hasError = false;
            
            // 验证必填字段
            $(this).find('[required]').each(function() {
                if ($(this).val() === '') {
                    hasError = true;
                    $(this).css('border-color', '#dc3232');
                } else {
                    $(this).css('border-color', '');
                }
            });
            
            if (hasError) {
                e.preventDefault();
                alert('请填写所有必填字段');
                return false;
            }
        });
        
        // 输入框焦点效果
        $('input, textarea, select').on('focus', function() {
            $(this).parent().addClass('focused');
        }).on('blur', function() {
            $(this).parent().removeClass('focused');
        });
        
        // 保存成功提示
        if (window.location.search.indexOf('settings-updated=true') > -1) {
            var notice = $('<div class="notice notice-success is-dismissible"><p>设置已保存！</p></div>');
            $('.folio-options-wrap h1').after(notice);
            
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
        // AI连接测试（支持多个API）
        $('#folio-test-ai-connection').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $result = $('#folio-ai-test-result');
            
            // 显示加载状态
            $btn.prop('disabled', true).text('测试中...');
            $result.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> 正在测试所有API连接...').css('color', '#646970');
            
            // 发送AJAX请求
            $.ajax({
                url: folioAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'folio_test_ai_connection',
                    nonce: folioAdmin.nonce
                },
                success: function(response) {
                    var html = '';
                    var color = response.success ? '#28a745' : '#dc3545';
                    var icon = response.success ? '✓' : '✗';
                    
                    html = '<div style="color: ' + color + '; margin-bottom: 15px; font-size: 14px; padding: 10px; background: ' + (response.success ? '#d4edda' : '#f8d7da') + '; border-left: 4px solid ' + color + '; border-radius: 3px;">';
                    html += '<strong>' + icon + ' ' + response.data.message + '</strong>';
                    html += '</div>';
                    
                    // 显示每个API的详细测试结果
                    if (response.data.results && Object.keys(response.data.results).length > 0) {
                        html += '<div style="margin-top: 15px; border: 1px solid #ddd; border-radius: 4px; padding: 15px; background: #f9f9f9;">';
                        html += '<strong style="display: block; margin-bottom: 12px; font-size: 13px; color: #333;">各API测试详情：</strong>';
                        
                        $.each(response.data.results, function(index, result) {
                            var apiName = result.name || ('API配置 #' + (parseInt(index) + 1));
                            var isSuccess = result.success;
                            var resultColor = isSuccess ? '#28a745' : '#dc3545';
                            var resultIcon = isSuccess ? '✓' : '✗';
                            
                            html += '<div style="margin-bottom: 15px; padding: 12px; background: #fff; border-left: 4px solid ' + resultColor + '; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                            
                            // API名称和状态
                            html += '<div style="font-weight: 600; margin-bottom: 8px;">';
                            html += '<span style="color: ' + resultColor + '; font-size: 16px; margin-right: 6px;">' + resultIcon + '</span>';
                            html += '<span style="color: #333; font-size: 14px;">' + apiName + '</span>';
                            html += '</div>';
                            
                            // API配置信息
                            html += '<div style="color: #666; font-size: 12px; margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 2px;">';
                            html += '<div><strong>Endpoint:</strong> <code style="background: #e9ecef; padding: 2px 4px; border-radius: 2px; font-size: 11px;">' + (result.endpoint || '未设置') + '</code></div>';
                            html += '<div style="margin-top: 4px;"><strong>模型:</strong> <code style="background: #e9ecef; padding: 2px 4px; border-radius: 2px; font-size: 11px;">' + (result.model || '未设置') + '</code></div>';
                            html += '</div>';
                            
                            if (isSuccess) {
                                html += '<div style="color: #666; font-size: 12px;">';
                                html += '<div style="margin-bottom: 6px;"><strong>状态：</strong><span style="color: #28a745; font-weight: 600;">✓ 连接成功</span></div>';
                                if (result.response) {
                                    var responseText = result.response.length > 150 ? result.response.substring(0, 150) + '...' : result.response;
                                    html += '<div><strong>AI响应：</strong><div style="color: #333; font-family: monospace; background: #f0f0f0; padding: 8px; border-radius: 3px; margin-top: 4px; word-break: break-all; font-size: 11px; line-height: 1.5;">' + 
                                            $('<div>').text(responseText).html() + '</div></div>';
                                } else {
                                    html += '<div><strong>AI响应：</strong><span style="color: #999; font-style: italic;">无响应内容</span></div>';
                                }
                                html += '</div>';
                            } else {
                                html += '<div style="color: #666; font-size: 12px;">';
                                html += '<div style="margin-bottom: 6px;"><strong>状态：</strong><span style="color: #dc3545; font-weight: 600;">✗ 连接失败</span></div>';
                                var errorMsg = result.error || result.message || '未知错误';
                                html += '<div><strong>错误信息：</strong><div style="color: #dc3545; background: #fff5f5; padding: 8px; border-radius: 3px; margin-top: 4px; font-size: 11px; line-height: 1.5; word-break: break-all;">' + 
                                        $('<div>').text(errorMsg).html() + '</div></div>';
                                html += '</div>';
                            }
                            
                            html += '</div>';
                        });
                        
                        html += '</div>';
                    }
                    
                    $result.html(html);
                },
                error: function() {
                    $result.html('✗ 请求失败，请重试').css('color', '#dc3545');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('测试连接');
                }
            });
        });
        
        // 图片上传功能
        var mediaUploader;
        
        // 上传图片按钮 - 使用事件委托确保动态加载的元素也能绑定
        $(document).on('click', '.folio-upload-image-button', function(e) {
            e.preventDefault();
            
            // 检查 wp.media 是否可用
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('媒体上传器未加载，请刷新页面重试。如果问题持续，请检查浏览器控制台是否有错误信息。');
                console.error('wp.media 未定义');
                return false;
            }
            
            var $button = $(this);
            var targetId = $button.data('target');
            
            if (!targetId) {
                console.error('未找到目标ID');
                return false;
            }
            
            var $wrapper = $button.closest('.folio-image-upload-wrapper');
            var $preview = $wrapper.find('.folio-image-preview');
            
            // 如果媒体上传器已存在，先关闭
            if (mediaUploader) {
                mediaUploader.close();
            }
            
            // 创建媒体上传器
            try {
                mediaUploader = wp.media({
                    title: '选择图片',
                    button: {
                        text: '使用此图片'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                // 打开媒体上传器
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#' + targetId).val(attachment.url);
                    
                    // 更新预览
                    var maxWidth = targetId === 'site_favicon' ? '32px' : '200px';
                    var imgHtml = '<img src="' + attachment.url + '" style="max-width: ' + maxWidth + '; height: auto; display: block; margin-bottom: 10px;" alt="预览">';
                    $preview.html(imgHtml);
                    
                    // 更新按钮文本
                    $button.text('更换' + (targetId === 'site_favicon' ? '图标' : 'LOGO'));
                    
                    // 显示移除按钮（如果还没有）
                    var $removeBtn = $wrapper.find('.folio-remove-image-button');
                    if ($removeBtn.length === 0) {
                        var removeBtnHtml = '<button type="button" class="button folio-remove-image-button" data-target="' + targetId + '" style="margin-left: 10px;">移除' + (targetId === 'site_favicon' ? '图标' : 'LOGO') + '</button>';
                        $button.after(removeBtnHtml);
                    }
                });
                
                mediaUploader.open();
            } catch (error) {
                console.error('创建媒体上传器时出错:', error);
                alert('无法打开媒体上传器，请刷新页面重试。');
            }
        });
        
        // 移除图片按钮
        $(document).on('click', '.folio-remove-image-button', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var targetId = $button.data('target');
            var $wrapper = $button.closest('.folio-image-upload-wrapper');
            var $preview = $wrapper.find('.folio-image-preview');
            
            $('#' + targetId).val('');
            $preview.html('');
            $button.remove();
            
            // 更新上传按钮文本
            var $uploadBtn = $wrapper.find('.folio-upload-image-button');
            $uploadBtn.text('选择' + (targetId === 'site_favicon' ? '图标' : 'LOGO'));
        });
        
    });
    
})(jQuery);

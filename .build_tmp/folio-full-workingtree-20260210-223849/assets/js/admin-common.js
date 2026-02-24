/**
 * Folio Admin Common JavaScript
 * 管理后台通用JavaScript功能
 */

(function($) {
    'use strict';

    // 全局Folio管理对象
    window.FolioAdmin = {
        t: function(key, fallback) {
            if (window.folioAdminCommon && window.folioAdminCommon.strings && window.folioAdminCommon.strings[key]) {
                return window.folioAdminCommon.strings[key];
            }
            return fallback;
        },
        
        // 初始化
        init: function() {
            this.bindCommonEvents();
            this.initTooltips();
            this.initConfirmDialogs();
        },

        // 绑定通用事件
        bindCommonEvents: function() {
            // 通用加载状态
            $(document).on('click', '.folio-loading-btn', function() {
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.addClass('folio-loading')
                    .prop('disabled', true)
                    .data('original-text', originalText);
            });

            // 恢复按钮状态
            $(document).on('folio:restore-button', function(e, button) {
                const $btn = $(button);
                const originalText = $btn.data('original-text');
                
                $btn.removeClass('folio-loading')
                    .prop('disabled', false)
                    .text(originalText || $btn.text());
            });

            // 通用AJAX错误处理
            $(document).ajaxError(function(event, xhr, settings, error) {
                // 状态码 0 在以下几种情况下都会出现：
                // 1. 请求被浏览器主动中断（例如：页面跳转/关闭、自定义器退出）
                // 2. 真实的网络断开
                //
                // 为了避免在页面正常跳转（如退出主题自定义器）时误报，
                // 我们只在「明确检测到离线」时才提示网络错误。

                // 如果请求是被中断的（abort），直接忽略
                if (error === 'abort') {
                    return;
                }

                if (xhr.status === 403) {
                    FolioAdmin.showNotice(FolioAdmin.t('permission_denied_refresh', 'Insufficient permissions, please refresh and try again'), 'error');
                } else if (xhr.status === 500) {
                    FolioAdmin.showNotice(FolioAdmin.t('server_error_retry', 'Server error, please try again later'), 'error');
                } else if (xhr.status === 0) {
                    // 仅在浏览器明确报告离线时才提示
                    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                        FolioAdmin.showNotice(FolioAdmin.t('network_connection_error', 'Network connection error, please check your connection'), 'error');
                    }
                    // 否则认为是页面跳转导致的中断，不提示
                }
            });
        },

        // 初始化工具提示
        initTooltips: function() {
            // 简单的工具提示实现
            $(document).on('mouseenter', '[data-tooltip]', function() {
                const $this = $(this);
                const text = $this.data('tooltip');
                
                if (!text) return;
                
                const $tooltip = $('<div class="folio-tooltip">' + text + '</div>');
                $('body').append($tooltip);
                
                const offset = $this.offset();
                $tooltip.css({
                    position: 'absolute',
                    top: offset.top - $tooltip.outerHeight() - 5,
                    left: offset.left + ($this.outerWidth() - $tooltip.outerWidth()) / 2,
                    zIndex: 9999,
                    background: '#333',
                    color: '#fff',
                    padding: '5px 10px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    whiteSpace: 'nowrap'
                });
                
                $this.data('tooltip-element', $tooltip);
            });

            $(document).on('mouseleave', '[data-tooltip]', function() {
                const $tooltip = $(this).data('tooltip-element');
                if ($tooltip) {
                    $tooltip.remove();
                    $(this).removeData('tooltip-element');
                }
            });
        },

        // 初始化确认对话框
        initConfirmDialogs: function() {
            $(document).on('click', '[data-confirm]', function(e) {
                const message = $(this).data('confirm');
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        // 显示通知
        showNotice: function(message, type = 'info', duration = 5000) {
            const $notice = $(`
                <div class="folio-admin-notice folio-notice ${type}">
                    <span class="notice-message">${message}</span>
                    <button class="notice-dismiss" type="button">
                        <span class="screen-reader-text">${this.t('dismiss_notice', 'Dismiss this notice')}</span>
                    </button>
                </div>
            `);

            // 添加样式
            $notice.css({
                position: 'fixed',
                top: '32px',
                right: '20px',
                zIndex: 999999,
                maxWidth: '400px',
                boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
                animation: 'slideInRight 0.3s ease'
            });

            $('body').append($notice);

            // 自动消失
            if (duration > 0) {
                setTimeout(() => {
                    $notice.fadeOut(() => {
                        $notice.remove();
                    });
                }, duration);
            }

            // 手动关闭
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(() => {
                    $notice.remove();
                });
            });

            return $notice;
        },

        // AJAX辅助函数
        ajax: function(action, data = {}, options = {}) {
            const defaults = {
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: {
                    action: action,
                    nonce: window.folioAdmin?.nonce || '',
                    ...data
                }
            };

            return $.ajax($.extend(defaults, options));
        },

        // 格式化数字
        formatNumber: function(num) {
            if (typeof num !== 'number') {
                num = parseFloat(num) || 0;
            }
            return num.toLocaleString();
        },

        // 格式化百分比
        formatPercent: function(num, decimals = 1) {
            if (typeof num !== 'number') {
                num = parseFloat(num) || 0;
            }
            return num.toFixed(decimals) + '%';
        },

        // 格式化文件大小
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        // 格式化时间
        formatTime: function(seconds) {
            const secondsShort = this.t('seconds_short', 's');
            const minutesShort = this.t('minutes_short', 'm');
            const hoursShort = this.t('hours_short', 'h');
            const minutesWord = this.t('minutes_word', 'min');

            if (seconds < 60) {
                return seconds.toFixed(1) + secondsShort;
            } else if (seconds < 3600) {
                return Math.floor(seconds / 60) + minutesShort + ' ' + Math.floor(seconds % 60) + secondsShort;
            } else {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                return hours + hoursShort + ' ' + minutes + minutesWord;
            }
        },

        // 防抖函数
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                
                if (callNow) func.apply(context, args);
            };
        },

        // 节流函数
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        FolioAdmin.init();
    });

    // 添加CSS动画
    $('<style>').text(`
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .folio-admin-notice {
            animation: slideInRight 0.3s ease;
        }
        
        .folio-tooltip {
            pointer-events: none;
        }
    `).appendTo('head');

})(jQuery);

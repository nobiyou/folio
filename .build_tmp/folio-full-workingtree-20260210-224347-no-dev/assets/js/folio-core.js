/**
 * Folio Core JavaScript
 * 
 * 统一的核心JavaScript功能，消除重复代码
 * 
 * @package Folio
 */

(function(window, $) {
    'use strict';

    // 核心功能对象
    const FolioCore = {
        t: function(key, fallback) {
            if (window.folio_ajax && window.folio_ajax.strings && window.folio_ajax.strings[key]) {
                return window.folio_ajax.strings[key];
            }
            return fallback;
        },
        
        // 配置选项
        config: {
            notificationDuration: 3000,
            loadingDelay: 300,
            animationDuration: 300
        },

        // 初始化
        init: function() {
            this.setupGlobalEvents();
            this.initNotificationSystem();
            this.initLoadingSystem();
            this.initEventTracking();
        },

        // 设置全局事件
        setupGlobalEvents: function() {
            // 全局错误处理
            window.addEventListener('error', function(e) {
                console.error('Folio Error:', e.error);
            });

            // 全局未处理的Promise拒绝
            window.addEventListener('unhandledrejection', function(e) {
                console.error('Folio Promise Rejection:', e.reason);
            });
        },

        // ==========================================================================
        // 通知系统 - Unified Notification System
        // ==========================================================================

        initNotificationSystem: function() {
            // 创建通知容器
            if (!document.getElementById('folio-notifications')) {
                const container = document.createElement('div');
                container.id = 'folio-notifications';
                container.className = 'folio-notifications-container';
                container.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    pointer-events: none;
                `;
                document.body.appendChild(container);
            }
        },

        /**
         * 统一的通知显示函数
         * @param {string} message - 通知消息
         * @param {string} type - 通知类型 (success, error, warning, info)
         * @param {number} duration - 显示时长（毫秒）
         * @param {object} options - 额外选项
         */
        showNotification: function(message, type = 'info', duration = null, options = {}) {
            duration = duration || this.config.notificationDuration;
            
            const container = document.getElementById('folio-notifications');
            if (!container) return;

            // 创建通知元素
            const notification = document.createElement('div');
            const notificationId = 'folio-notification-' + Date.now();
            notification.id = notificationId;
            notification.className = `folio-notification folio-notification-${type}`;
            
            // 设置样式
            notification.style.cssText = `
                background: ${this.getNotificationColor(type)};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                margin-bottom: 10px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                transform: translateX(100%);
                transition: transform 0.3s ease, opacity 0.3s ease;
                pointer-events: auto;
                font-size: 14px;
                font-weight: 500;
                max-width: 350px;
                word-wrap: break-word;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            `;

            // 添加图标
            const icon = this.getNotificationIcon(type);
            const content = `
                <div style="display: flex; align-items: center; gap: 8px; flex: 1;">
                    <span style="font-size: 16px;">${icon}</span>
                    <span>${message}</span>
                </div>
                ${options.closable !== false ? '<button class="folio-notification-close" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;">&times;</button>' : ''}
            `;
            
            notification.innerHTML = content;
            container.appendChild(notification);

            // 显示动画
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 10);

            // 绑定关闭事件
            const closeBtn = notification.querySelector('.folio-notification-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.hideNotification(notification);
                });
            }

            // 自动隐藏
            if (duration > 0) {
                setTimeout(() => {
                    this.hideNotification(notification);
                }, duration);
            }

            // 触发自定义事件
            this.triggerEvent('folio:notification:show', {
                message: message,
                type: type,
                id: notificationId
            });

            return notification;
        },

        /**
         * 隐藏通知
         */
        hideNotification: function(notification) {
            if (!notification || !notification.parentNode) return;

            notification.style.transform = 'translateX(100%)';
            notification.style.opacity = '0';
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, this.config.animationDuration);
        },

        /**
         * 获取通知颜色
         */
        getNotificationColor: function(type) {
            const colors = {
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };
            return colors[type] || colors.info;
        },

        /**
         * 获取通知图标
         */
        getNotificationIcon: function(type) {
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            return icons[type] || icons.info;
        },

        // ==========================================================================
        // 加载状态管理 - Loading State Management
        // ==========================================================================

        initLoadingSystem: function() {
            // 添加全局加载样式
            if (!document.getElementById('folio-loading-styles')) {
                const style = document.createElement('style');
                style.id = 'folio-loading-styles';
                style.textContent = `
                    .folio-btn-loading {
                        opacity: 0.7;
                        pointer-events: none;
                        position: relative;
                    }
                    
                    .folio-btn-loading::after {
                        content: '';
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        width: 16px;
                        height: 16px;
                        margin: -8px 0 0 -8px;
                        border: 2px solid transparent;
                        border-top: 2px solid currentColor;
                        border-radius: 50%;
                        animation: folio-spin 1s linear infinite;
                    }
                    
                    @keyframes folio-spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
        },

        /**
         * 设置按钮加载状态
         */
        setButtonLoading: function(button, loading = true) {
            const $button = $(button);
            
            if (loading) {
                $button.addClass('folio-btn-loading').prop('disabled', true);
                const originalText = $button.text();
                $button.data('folio-original-text', originalText);
                
                const loadingText = this.t('loading', 'Loading...');
                if (originalText !== loadingText) {
                    $button.text(loadingText);
                }
            } else {
                $button.removeClass('folio-btn-loading').prop('disabled', false);
                const originalText = $button.data('folio-original-text');
                if (originalText) {
                    $button.text(originalText);
                    $button.removeData('folio-original-text');
                }
            }
        },

        // ==========================================================================
        // 统一的按钮处理函数 - Unified Button Handlers
        // ==========================================================================

        /**
         * 统一的升级按钮处理
         */
        handleUpgradeClick: function(e, options = {}) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const level = $button.data('level') || options.level || 'vip';
            const url = $button.attr('href') || options.url || (window.folioConfig && window.folioConfig.urls && window.folioConfig.urls.upgrade);
            
            if (!url) {
                FolioCore.showNotification(FolioCore.t('upgrade_link_error', 'Upgrade link configuration error'), 'error');
                return;
            }

            // 添加加载状态
            FolioCore.setButtonLoading($button, true);
            
            const levelName = level === 'svip' ? 'SVIP' : 'VIP';
            
            // 显示引导通知
            const redirectTemplate = FolioCore.t('redirect_upgrade', 'Redirecting to %s membership upgrade page...');
            FolioCore.showNotification(redirectTemplate.replace('%s', levelName), 'info', 2000);
            
            // 触发升级事件
            FolioCore.trackEvent('upgrade_click', {
                level: level,
                source: options.source || 'button'
            });
            
            // 延迟跳转以显示加载效果
            setTimeout(() => {
                // 检查是否在新标签页打开
                if (e.ctrlKey || e.metaKey || options.newTab) {
                    window.open(url, '_blank');
                    FolioCore.setButtonLoading($button, false);
                } else {
                    window.location.href = url;
                }
            }, FolioCore.config.loadingDelay);
        },

        /**
         * 统一的登录按钮处理
         */
        handleLoginClick: function(e, options = {}) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const url = $button.attr('href') || options.url || (window.folioConfig && window.folioConfig.urls && window.folioConfig.urls.login);
            
            if (!url) {
                FolioCore.showNotification(FolioCore.t('login_link_error', 'Login link configuration error'), 'error');
                return;
            }

            FolioCore.setButtonLoading($button, true);
            
            // 显示引导通知
            FolioCore.showNotification(FolioCore.t('redirect_login', 'Redirecting to login page...'), 'info', 1500);
            
            FolioCore.trackEvent('login_click', {
                source: options.source || 'button'
            });
            
            // 保存当前页面URL以便登录后返回
            if (options.saveReturnUrl !== false) {
                sessionStorage.setItem('folio_return_url', window.location.href);
            }
            
            setTimeout(() => {
                window.location.href = url;
            }, FolioCore.config.loadingDelay);
        },

        /**
         * 统一的注册按钮处理
         */
        handleRegisterClick: function(e, options = {}) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const url = $button.attr('href') || options.url || (window.folioConfig && window.folioConfig.urls && window.folioConfig.urls.register);
            
            if (!url) {
                FolioCore.showNotification(FolioCore.t('register_link_error', 'Register link configuration error'), 'error');
                return;
            }

            FolioCore.setButtonLoading($button, true);
            
            FolioCore.trackEvent('register_click', {
                source: options.source || 'button'
            });
            
            setTimeout(() => {
                window.location.href = url;
            }, 200);
        },

        // ==========================================================================
        // 事件跟踪系统 - Event Tracking System
        // ==========================================================================

        initEventTracking: function() {
            // 初始化事件跟踪系统
            this.events = [];
        },

        /**
         * 跟踪事件
         */
        trackEvent: function(eventName, data = {}) {
            // 添加时间戳和页面信息
            const eventData = {
                name: eventName,
                data: data,
                timestamp: Date.now(),
                url: window.location.href,
                userAgent: navigator.userAgent
            };

            // 存储到本地数组
            this.events.push(eventData);

            // 集成Google Analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, data);
            }

            // 集成其他分析工具
            if (window.dataLayer) {
                window.dataLayer.push({
                    event: eventName,
                    eventData: data
                });
            }

            // 触发自定义事件
            this.triggerEvent('folio:track', eventData);

            // 调试模式下输出到控制台
            if (window.folioConfig && window.folioConfig.debug) {
                console.log('Folio Event Tracked:', eventData);
            }
        },

        /**
         * 获取事件历史
         */
        getEventHistory: function() {
            return this.events;
        },

        // ==========================================================================
        // 工具函数 - Utility Functions
        // ==========================================================================

        /**
         * 防抖函数
         */
        debounce: function(func, wait, immediate = false) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    timeout = null;
                    if (!immediate) func(...args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func(...args);
            };
        },

        /**
         * 节流函数
         */
        throttle: function(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * 触发自定义事件
         */
        triggerEvent: function(eventName, data = {}) {
            const event = new CustomEvent(eventName, {
                detail: data,
                bubbles: true,
                cancelable: true
            });
            document.dispatchEvent(event);
        },

        /**
         * 监听自定义事件
         */
        onEvent: function(eventName, callback) {
            document.addEventListener(eventName, callback);
        },

        /**
         * 移除事件监听
         */
        offEvent: function(eventName, callback) {
            document.removeEventListener(eventName, callback);
        },

        /**
         * 检查元素是否在视口中
         */
        isInViewport: function(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        },

        /**
         * 格式化价格
         */
        formatPrice: function(price) {
            return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * 计算日期差
         */
        calculateDaysLeft: function(expiryDate) {
            const now = new Date();
            const expiry = new Date(expiryDate);
            const diffTime = expiry - now;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return Math.max(0, diffDays);
        },

        /**
         * 复制到剪贴板
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                return navigator.clipboard.writeText(text).then(() => {
                    this.showNotification(this.t('copy_link_success', 'Link copied to clipboard'), 'success');
                    return true;
                }).catch(() => {
                    return this.fallbackCopyToClipboard(text);
                });
            } else {
                return this.fallbackCopyToClipboard(text);
            }
        },

        /**
         * 降级复制方案
         */
        fallbackCopyToClipboard: function(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showNotification(this.t('copy_link_success', 'Link copied to clipboard'), 'success');
                return Promise.resolve(true);
            } catch (err) {
                this.showNotification(this.t('copy_failed_manual', 'Copy failed, please copy manually'), 'error');
                return Promise.resolve(false);
            } finally {
                document.body.removeChild(textArea);
            }
        },

        // ==========================================================================
        // AJAX 工具函数 - AJAX Utilities
        // ==========================================================================

        /**
         * 统一的AJAX请求函数
         */
        ajax: function(options) {
            const defaults = {
                method: 'POST',
                url: window.folioConfig && window.folioConfig.ajaxUrl || '/wp-admin/admin-ajax.php',
                data: {},
                timeout: 10000,
                showLoading: true,
                showError: true,
                nonceMode: 'none', // none | auto
                nonceField: 'nonce',
                nonceValue: null
            };

            const config = Object.assign({}, defaults, options);

            // nonce 注入策略：
            // - 默认不注入，避免通用请求误用全局 nonce
            // - 可通过 nonceMode=auto 启用自动注入
            // - 可通过 nonceValue 显式指定 nonce
            const targetField = config.nonceField || 'nonce';
            const explicitNonce = typeof config.nonceValue === 'string' ? config.nonceValue : '';
            const autoNonce = window.folioConfig && typeof window.folioConfig.nonce === 'string' ? window.folioConfig.nonce : '';
            const resolvedNonce = explicitNonce !== '' ? explicitNonce : (config.nonceMode === 'auto' ? autoNonce : '');

            if (resolvedNonce !== '') {
                if (config.data instanceof FormData) {
                    if (!config.data.has(targetField)) {
                        config.data.append(targetField, resolvedNonce);
                    }
                } else if (config.data && typeof config.data === 'object' && typeof config.data[targetField] === 'undefined') {
                    config.data[targetField] = resolvedNonce;
                }
            }

            return new Promise((resolve, reject) => {
                $.ajax({
                    url: config.url,
                    method: config.method,
                    data: config.data,
                    timeout: config.timeout,
                    beforeSend: function() {
                        if (config.showLoading && config.loadingElement) {
                            FolioCore.setButtonLoading(config.loadingElement, true);
                        }
                    },
                    success: function(response) {
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        if (config.showError) {
                            let errorMessage = FolioCore.t('request_failed', 'Request failed');
                            if (status === 'timeout') {
                                errorMessage = FolioCore.t('request_timeout_retry', 'Request timed out, please try again later');
                            } else if (xhr.responseJSON && xhr.responseJSON.data) {
                                errorMessage = xhr.responseJSON.data;
                            }
                            FolioCore.showNotification(errorMessage, 'error');
                        }
                        reject({ xhr, status, error });
                    },
                    complete: function() {
                        if (config.showLoading && config.loadingElement) {
                            FolioCore.setButtonLoading(config.loadingElement, false);
                        }
                    }
                });
            });
        }
    };

    // 初始化核心功能
    $(document).ready(function() {
        FolioCore.init();
    });

    // 暴露到全局作用域
    window.FolioCore = FolioCore;

    // 向后兼容的别名
    window.showNotification = FolioCore.showNotification.bind(FolioCore);

})(window, jQuery);

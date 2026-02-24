/**
 * Frontend Components JavaScript - Cleaned Version
 * 
 * 前端权限提示组件交互脚本 - 已移除文章状态检测功能
 */

(function($) {
    'use strict';

    // 前端组件管理器
    const FrontendComponents = {
        t: function(key, fallback) {
            if (typeof folioComponents !== 'undefined' && folioComponents.strings && folioComponents.strings[key]) {
                return folioComponents.strings[key];
            }
            return fallback;
        },
        
        // 初始化
        init: function() {
            this.bindEvents();
            this.initAnimations();
            this.initTooltips();
            this.initMobileOptimizations();
            this.checkPermissionStatus();
            this.handleReturnFromUserCenter();
        },

        // 处理从用户中心返回
        handleReturnFromUserCenter: function() {
            // 检查是否从用户中心返回
            const returnUrl = sessionStorage.getItem('folio_return_url');
            const urlParams = new URLSearchParams(window.location.search);
            const membershipUpdated = urlParams.get('membership_updated');
            const loginSuccess = urlParams.get('login_success');
            
            if (returnUrl && (membershipUpdated || loginSuccess)) {
                sessionStorage.removeItem('folio_return_url');
                
                if (membershipUpdated) {
                    FrontendComponents.showNotification(
                        FrontendComponents.t('membership_updated_checking', 'Membership updated! Checking your access permissions...'),
                        'success',
                        3000
                    );
                    
                    // 立即刷新权限状态，不需要重新加载页面
                    setTimeout(() => {
                        FrontendComponents.refreshAllPermissionPrompts();
                    }, 1000);
                    
                } else if (loginSuccess) {
                    FrontendComponents.showNotification(
                        FrontendComponents.t('login_success_checking', 'Login successful! Checking your access permissions...'),
                        'success',
                        2000
                    );
                    
                    // 立即刷新权限状态，不需要重新加载页面
                    setTimeout(() => {
                        FrontendComponents.refreshAllPermissionPrompts();
                    }, 1000);
                }
            }
        },

        // 绑定事件
        bindEvents: function() {
            // 升级按钮点击事件
            $(document).on('click', '.folio-btn-upgrade', this.handleUpgradeClick);
            
            // 登录按钮点击事件
            $(document).on('click', '.folio-btn-login', this.handleLoginClick);
            
            // 注册按钮点击事件
            $(document).on('click', '.folio-btn-register', this.handleRegisterClick);
            
            // 权限提示点击事件
            $(document).on('click', '.folio-permission-prompt', this.handlePromptClick);
            
            // 会员徽章悬停事件
            $(document).on('mouseenter', '.folio-membership-badge', this.handleBadgeHover);
            $(document).on('mouseleave', '.folio-membership-badge', this.handleBadgeLeave);
            

            
            // 键盘导航支持
            $(document).on('keydown', '.folio-btn, .folio-upgrade-btn, .folio-login-btn', this.handleKeyNavigation);
            
            // 窗口大小变化事件
            $(window).on('resize', this.debounce(this.handleResize, 250));
        },

        // 检查权限状态
        checkPermissionStatus: function() {
            const $prompts = $('.folio-permission-prompt[data-post-id]');
            
            // 只检查那些还没有被检查过的提示
            $prompts.each(function() {
                const $prompt = $(this);
                
                // 如果已经检查过，跳过
                if ($prompt.data('permission-checked')) {
                    return;
                }
                
                // 获取权限相关数据
                const canAccess = $prompt.data('can-access');
                const postId = $prompt.data('post-id');
                const level = $prompt.data('level');
                const userLoggedIn = $prompt.data('user-logged-in');
                

                
                // 标记为已检查，避免重复检查
                $prompt.data('permission-checked', true);
                
                // 只有在用户确实有权限时才隐藏提示
                // 注意：这里要严格检查，避免错误隐藏
                if (canAccess === 'true' || canAccess === true) {

                    $prompt.fadeOut(500, function() {
                        $(this).remove();
                    });
                } else {
                    // 确保提示保持显示状态
                    $prompt.show();
                }
            });
        },

        // 处理升级按钮点击 - 使用核心功能
        handleUpgradeClick: function(e) {
            if (window.FolioCore) {
                FolioCore.handleUpgradeClick(e, {
                    source: 'permission_prompt',
                    url: folioComponents && folioComponents.urls && folioComponents.urls.upgrade
                });
            } else {
                // 降级方案
                e.preventDefault();
                window.location.href = $(e.currentTarget).attr('href');
            }
        },

        // 处理登录按钮点击 - 使用核心功能
        handleLoginClick: function(e) {
            if (window.FolioCore) {
                FolioCore.handleLoginClick(e, {
                    source: 'permission_prompt',
                    url: folioComponents && folioComponents.urls && folioComponents.urls.login
                });
            } else {
                // 降级方案
                e.preventDefault();
                window.location.href = $(e.currentTarget).attr('href');
            }
        },

        // 处理注册按钮点击 - 使用核心功能
        handleRegisterClick: function(e) {
            if (window.FolioCore) {
                FolioCore.handleRegisterClick(e, {
                    source: 'permission_prompt',
                    url: folioComponents && folioComponents.urls && folioComponents.urls.register
                });
            } else {
                // 降级方案
                e.preventDefault();
                window.location.href = $(e.currentTarget).attr('href');
            }
        },

        // 处理权限提示点击
        handlePromptClick: function(e) {
            const $prompt = $(this);
            const postId = $prompt.data('post-id');
            const level = $prompt.data('level');
            
            // 添加点击反馈
            $prompt.addClass('folio-prompt-clicked');
            
            setTimeout(() => {
                $prompt.removeClass('folio-prompt-clicked');
            }, 200);
            
            // 触发点击事件
            FrontendComponents.trackEvent('prompt_click', {
                postId: postId,
                level: level
            });
        },

        // 使用核心功能的辅助函数
        setButtonLoading: function($button, loading) {
            if (window.FolioCore) {
                FolioCore.setButtonLoading($button, loading);
            } else {
                // 降级方案
                if (loading) {
                    $button.addClass('loading').prop('disabled', true);
                } else {
                    $button.removeClass('loading').prop('disabled', false);
                }
            }
        },

        // 显示通知 - 使用核心功能
        showNotification: function(message, type, duration) {
            if (window.FolioCore) {
                FolioCore.showNotification(message, type, duration);
            } else {
                // 降级方案 - 简单的alert
                alert(message);
            }
        },

        // 事件跟踪 - 使用核心功能
        trackEvent: function(eventName, data) {
            if (window.FolioCore) {
                FolioCore.trackEvent(eventName, data);
            } else {
                // 降级方案
                if (typeof gtag !== 'undefined') {
                    gtag('event', eventName, data);
                }
            }
        },

        // 防抖函数
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // 初始化动画
        initAnimations: function() {
            // 为权限提示添加入场动画
            $('.folio-permission-prompt').each(function(index) {
                const $prompt = $(this);
                $prompt.css({
                    'opacity': '0',
                    'transform': 'translateY(30px)'
                });
                
                setTimeout(() => {
                    $prompt.css({
                        'opacity': '1',
                        'transform': 'translateY(0)',
                        'transition': 'all 0.6s ease-out'
                    });
                }, index * 100);
            });

            // 为会员徽章添加动画
            $('.folio-membership-badge').each(function(index) {
                const $badge = $(this);
                setTimeout(() => {
                    $badge.addClass('folio-badge-animated');
                }, index * 50);
            });
        },

        // 初始化工具提示
        initTooltips: function() {
            // 简化的工具提示功能
        },

        // 初始化移动端优化
        initMobileOptimizations: function() {
            // 移动端优化
        },

        // 处理窗口大小变化
        handleResize: function() {
            FrontendComponents.recalculateMobileLayout();
        },

        // 重新计算移动端布局
        recalculateMobileLayout: function() {
            $('.folio-permission-prompt').each(function() {
                const $element = $(this);
                // 移动端布局调整
            });
        },

        // 处理会员徽章悬停
        handleBadgeHover: function() {
            const $badge = $(this);
            $badge.addClass('folio-badge-hover');
        },

        handleBadgeLeave: function() {
            const $badge = $(this);
            $badge.removeClass('folio-badge-hover');
        },

        // 处理键盘导航
        handleKeyNavigation: function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        },

        // 更新权限状态
        updatePermissionStatus: function(postId, $prompt) {
            // 通过AJAX检查最新的权限状态
            const ajaxUrl = (typeof folioComponents !== 'undefined') ? (folioComponents.ajax_url || folioComponents.ajaxurl) : null;
            if (ajaxUrl) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'folio_check_user_access_article',
                        post_id: postId,
                        nonce: folioComponents.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            const canAccess = response.data.can_access;
                            

                            
                            // 更新数据属性
                            $prompt.data('can-access', canAccess ? 'true' : 'false');
                            $prompt.attr('data-can-access', canAccess ? 'true' : 'false');
                            
                            // 根据最新权限状态决定显示或隐藏
                            if (canAccess) {

                                $prompt.fadeOut(500, function() {
                                    $(this).remove();
                                });
                            } else {

                                $prompt.show();
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        // 权限检查失败，静默处理
                    }
                });
            }
            
            $prompt.data('permission-checked', true);
        },

        // 刷新所有权限提示状态
        refreshAllPermissionPrompts: function() {
            const $prompts = $('.folio-permission-prompt[data-post-id]');
            
            $prompts.each(function() {
                const $prompt = $(this);
                const postId = $prompt.data('post-id');
                
                // 重置检查状态，强制重新检查
                $prompt.data('permission-checked', false);
                
                // 更新权限状态
                FrontendComponents.updatePermissionStatus(postId, $prompt);
            });
        },

        // 监听用户状态变化事件
        listenForUserStatusChanges: function() {
            // 监听自定义事件
            $(document).on('folio:user_logged_in', function(e, userData) {
                setTimeout(() => {
                    FrontendComponents.refreshAllPermissionPrompts();
                }, 500);
            });
            
            $(document).on('folio:membership_updated', function(e, membershipData) {
                setTimeout(() => {
                    FrontendComponents.refreshAllPermissionPrompts();
                }, 500);
            });
            
            $(document).on('folio:user_logged_out', function() {
                setTimeout(() => {
                    FrontendComponents.refreshAllPermissionPrompts();
                }, 500);
            });
            
            // 监听页面可见性变化（用户切换标签页回来时检查状态）
            if (typeof document.visibilityState !== 'undefined') {
                document.addEventListener('visibilitychange', function() {
                    if (!document.hidden) {
                        // 页面变为可见时，延迟检查权限状态
                        setTimeout(() => {
                            FrontendComponents.refreshAllPermissionPrompts();
                        }, 1000);
                    }
                });
            }
        },

        // 测试和调试功能
        testPermissionPrompts: function() {
            const $prompts = $('.folio-permission-prompt[data-post-id]');
            

            
            $prompts.each(function(index) {
                const $prompt = $(this);
                const postId = $prompt.data('post-id');
                const canAccess = $prompt.data('can-access');
                const level = $prompt.data('level');
                const userLoggedIn = $prompt.data('user-logged-in');
                

            });
            
            return {
                totalPrompts: $prompts.length,
                visiblePrompts: $prompts.filter(':visible').length,
                checkedPrompts: $prompts.filter(function() {
                    return $(this).data('permission-checked');
                }).length
            };
        }

    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        FrontendComponents.init();
        FrontendComponents.listenForUserStatusChanges();
        

    });

    // 导出到全局作用域
    window.FrontendComponents = FrontendComponents;

})(jQuery);

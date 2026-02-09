/**
 * DEPRECATED: Legacy Frontend Components JavaScript
 *
 * 前端权限提示旧版脚本（已废弃）：
 * - 默认禁用执行，避免误加载引发旧行为回归
 * - 仅在显式设置 window.folioEnableLegacyFrontendComponents = true 时启用
 */

(function($) {
    'use strict';

    if (window.folioEnableLegacyFrontendComponents !== true) {
        if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('[Folio] frontend-components-original.js is deprecated and disabled by default.');
        }
        return;
    }

    // 前端组件管理器
    const FrontendComponents = {
        
        // 初始化
        init: function() {
            this.bindEvents();
            this.initAnimations();
            this.initTooltips();
            this.initMobileOptimizations();
            this.checkPermissionStatus();
            this.handleReturnFromUserCenter();
            this.initRealTimeUpdates();
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
                        '🎉 会员等级已更新！正在刷新页面内容...',
                        'success',
                        3000
                    );
                } else if (loginSuccess) {
                    FrontendComponents.showNotification(
                        '✅ 登录成功！正在检查您的访问权限...',
                        'success',
                        2000
                    );
                }
                
                // 延迟刷新以显示通知
                setTimeout(() => {
                    // 清理URL参数
                    const cleanUrl = window.location.pathname + window.location.hash;
                    window.history.replaceState({}, document.title, cleanUrl);
                    
                    // 刷新页面内容
                    window.location.reload();
                }, 2000);
            }
        },

        // 初始化实时更新（仅监听跨标签页通信，不进行周期性检查）
        initRealTimeUpdates: function() {
            // 监听存储事件（跨标签页通信）
            // 当其他标签页更新会员状态时，刷新当前页面
            window.addEventListener('storage', function(e) {
                if (e.key === 'folio_membership_updated') {
                    FrontendComponents.showNotification(
                        '检测到会员状态更新，正在刷新页面...',
                        'info',
                        1500
                    );
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            });
            
            // 移除窗口焦点事件的检查，只在页面加载时检查一次
        },

        // 绑定事件
        bindEvents: function() {
            // 升级按钮点击事件（包括新的文章状态按钮）
            $(document).on('click', '.folio-btn-upgrade, .folio-upgrade-btn', this.handleUpgradeClick);
            
            // 登录按钮点击事件（包括新的文章状态按钮）
            $(document).on('click', '.folio-btn-login, .folio-login-btn', this.handleLoginClick);
            
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
            // 为会员徽章添加增强的工具提示
            $('.folio-membership-badge[data-tooltip]').each(function() {
                const $badge = $(this);
                const tooltipText = $badge.data('tooltip');
                const postId = $badge.data('post-id');
                const level = $badge.data('level');
                const canAccess = $badge.data('can-access') === 'true';
                const userLoggedIn = $badge.data('user-logged-in') === 'true';
                const userLevel = $badge.data('user-level');
                
                $badge.removeAttr('title'); // 移除默认title
                
                // 创建增强的工具提示
                $badge.on('mouseenter', function(e) {
                    const enhancedTooltip = FrontendComponents.createEnhancedTooltip({
                        text: tooltipText,
                        postId: postId,
                        level: level,
                        canAccess: canAccess,
                        userLoggedIn: userLoggedIn,
                        userLevel: userLevel
                    });
                    
                    FrontendComponents.showEnhancedTooltip(e.target, enhancedTooltip);
                });
                
                $badge.on('mouseleave', function() {
                    FrontendComponents.hideTooltip();
                });
                
                // 点击事件处理
                $badge.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (!canAccess) {
                        if (userLoggedIn) {
                            // 已登录用户，跳转到升级页面
                            window.location.href = folioComponents.urls.upgrade;
                        } else {
                            // 未登录用户，跳转到登录页面
                            window.location.href = folioComponents.urls.login;
                        }
                    }
                });
            });
        },

        // 初始化移动端优化
        initMobileOptimizations: function() {
            if (this.isMobile()) {
                // 移动端特定优化
                this.optimizeForMobile();
                this.initMobileGestures();
                this.initMobileBottomSheet();
                this.setupMobileViewport();
            }
            
            // 触摸设备优化
            if (this.isTouchDevice()) {
                this.optimizeForTouch();
                this.initHapticFeedback();
            }
            
            // 设备方向变化处理
            this.handleOrientationChange();
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
                
                // 如果用户已经有权限（从data属性判断），直接隐藏提示，不进行AJAX检查
                const canAccess = $prompt.data('can-access') === 'true' || $prompt.data('can-access') === true;
                if (canAccess) {
                    $prompt.data('permission-checked', true);
                    $prompt.fadeOut(500, function() {
                        $(this).remove();
                    });
                    return;
                }
                
                const postId = $prompt.data('post-id');
                // 页面加载时检查权限状态（仅检查一次，不进行周期性检查）
                FrontendComponents.updatePermissionStatus(postId, $prompt);
            });
            

        },



        // 检查文章权限状态
        checkArticlePermissionStatus: function(postId, $statusElement) {
            // 避免重复请求
            if ($statusElement.data('checking') || $statusElement.data('status-checked')) {
                return;
            }
            
            // 标记为已检查，防止重复检查
            $statusElement.data('status-checked', true);
            $statusElement.data('checking', true);
            
            $.ajax({
                url: folioComponents.ajaxurl,
                type: 'POST',
                data: {
                    action: 'folio_get_permission_status',
                    post_id: postId,
                    nonce: folioComponents.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FrontendComponents.updateArticleStatus(response.data, $statusElement);
                    }
                },
                error: function() {
                    // 静默处理错误
                },
                complete: function() {
                    $statusElement.data('checking', false);
                }
            });
        },

        // 更新文章状态显示
        updateArticleStatus: function(data, $statusElement) {
            // 检查是否已经处理过，避免重复刷新
            if ($statusElement.data('status-updated')) {
                return; // 已经更新过，不再处理
            }
            
            // 记录初始状态（首次检查时的状态）
            const initialCanAccess = $statusElement.data('initial-can-access');
            if (initialCanAccess === undefined) {
                $statusElement.data('initial-can-access', data.can_access);
            }
            
            const currentCanAccess = $statusElement.data('can-access') === 'true' || $statusElement.data('can-access') === true;
            const currentUserLoggedIn = $statusElement.data('user-logged-in') === 'true' || $statusElement.data('user-logged-in') === true;
            const currentUserLevel = $statusElement.data('user-level');
            
            const newCanAccess = data.can_access;
            const newUserLoggedIn = data.user_logged_in;
            const newUserLevel = data.user_level;
            
            // 检查各种状态变化（只有在状态真正改变时才认为变化）
            const accessChanged = (currentCanAccess !== newCanAccess) && (currentCanAccess !== undefined);
            const loginChanged = (currentUserLoggedIn !== newUserLoggedIn) && (currentUserLoggedIn !== undefined);
            const levelChanged = (currentUserLevel !== newUserLevel) && (currentUserLevel !== undefined);
            
            // 更新数据属性
            $statusElement.data('can-access', newCanAccess ? 'true' : 'false');
            $statusElement.data('user-logged-in', newUserLoggedIn ? 'true' : 'false');
            $statusElement.data('last-updated', Math.floor(Date.now() / 1000));
            
            if (data.user_level) {
                $statusElement.data('user-level', data.user_level);
                $statusElement.data('user-level-name', data.user_level_name);
            }
            
            // 更新状态类
            FrontendComponents.updateStatusClasses($statusElement, data);
            
            // 标记为已更新
            $statusElement.data('status-updated', true);
            
            // 只有在状态真正发生变化时才执行动画和刷新
            // 注意：首次加载时，如果用户已经有权限，不应该触发刷新
            if ((accessChanged || loginChanged || levelChanged) && 
                !(accessChanged && newCanAccess && initialCanAccess === true)) {
                FrontendComponents.animateStatusChange($statusElement, data, {
                    accessChanged,
                    loginChanged,
                    levelChanged
                });
            }
        },

        // 更新状态CSS类
        updateStatusClasses: function($statusElement, data) {
            // 移除旧的状态类
            $statusElement.removeClass('folio-status-locked folio-status-unlocked folio-status-logged-in folio-status-logged-out');
            
            // 添加新的状态类
            if (data.can_access) {
                $statusElement.addClass('folio-status-unlocked');
            } else {
                $statusElement.addClass('folio-status-locked');
            }
            
            if (data.user_logged_in) {
                $statusElement.addClass('folio-status-logged-in');
            } else {
                $statusElement.addClass('folio-status-logged-out');
            }
        },

        // 动画状态变化
        animateStatusChange: function($statusElement, data, changes) {
            // 检查是否已经处理过刷新，避免重复刷新
            const refreshKey = 'folio_refresh_' + $statusElement.data('post-id');
            if (sessionStorage.getItem(refreshKey)) {
                // 已经刷新过，不再刷新
                return;
            }
            
            const postId = $statusElement.data('post-id');
            
            // 显示权限变更提示
            FrontendComponents.showStatusChangeNotice($statusElement, changes);
            
            if (changes.accessChanged && data.can_access) {
                // 检查是否在页面加载时就已经有权限（避免首次加载就刷新）
                const initialAccess = $statusElement.data('initial-can-access');
                if (initialAccess === undefined) {
                    // 记录初始状态
                    $statusElement.data('initial-can-access', data.can_access);
                } else if (initialAccess === true) {
                    // 页面加载时就已经有权限，不刷新
                    return;
                }
                
                // 标记为已刷新，防止重复刷新
                sessionStorage.setItem(refreshKey, 'true');
                
                // 用户刚刚获得了访问权限
                FrontendComponents.showStatusLoading($statusElement, '权限已更新，正在刷新内容...');
                
                // 显示成功通知
                FrontendComponents.showNotification(
                    '🎉 内容已解锁！正在为您刷新页面...',
                    'success',
                    3000
                );
                
                // 延迟刷新页面以显示完整内容
                setTimeout(() => {
                    window.location.reload();
                }, 2500);
                
            } else if (changes.loginChanged && !data.user_logged_in) {
                // 标记为已刷新
                sessionStorage.setItem(refreshKey, 'true');
                
                // 用户登出了
                FrontendComponents.showStatusLoading($statusElement, '检测到登录状态变化，正在更新...');
                
                FrontendComponents.showNotification(
                    '检测到您已登出，正在更新页面状态...',
                    'info',
                    2000
                );
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                
            } else if (changes.levelChanged) {
                // 会员等级发生变化
                const levelName = FrontendComponents.getLevelDisplayName(data.user_level);
                
                FrontendComponents.showNotification(
                    `会员等级已更新为：${levelName}`,
                    'success',
                    3000
                );
                
                // 更新显示的用户等级信息
                FrontendComponents.updateUserLevelDisplay($statusElement, data);
                
                // 如果现在有权限了，且之前没有权限，才刷新页面
                if (data.can_access) {
                    const previousAccess = $statusElement.data('initial-can-access') === false || 
                                         $statusElement.data('initial-can-access') === undefined;
                    if (previousAccess) {
                        // 标记为已刷新
                        sessionStorage.setItem(refreshKey, 'true');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                }
            }
        },

        // 显示状态变更提示
        showStatusChangeNotice: function($statusElement, changes) {
            const $notice = $statusElement.find('.folio-status-change-notice');
            
            let changeText = '权限状态已更新';
            if (changes.accessChanged) {
                changeText = '访问权限已更新';
            } else if (changes.loginChanged) {
                changeText = '登录状态已更新';
            } else if (changes.levelChanged) {
                changeText = '会员等级已更新';
            }
            
            $notice.find('.folio-change-text').text(changeText);
            $notice.fadeIn(300);
            
            // 3秒后自动隐藏
            setTimeout(() => {
                $notice.fadeOut(300);
            }, 3000);
        },

        // 更新用户等级显示
        updateUserLevelDisplay: function($statusElement, data) {
            const $currentLevel = $statusElement.find('.folio-current-level');
            if ($currentLevel.length && data.user_level_name) {
                $currentLevel.text('当前：' + data.user_level_name);
            }
            
            // 更新权限详情中的等级信息
            const $infoValue = $statusElement.find('.folio-permission-info .folio-info-value');
            $infoValue.each(function() {
                const $this = $(this);
                const $label = $this.prev('.folio-info-label');
                if ($label.text().includes('当前等级') && data.user_level_name) {
                    $this.text(data.user_level_name);
                }
            });
        },

        // 显示状态加载
        showStatusLoading: function($statusElement, message) {
            const $loading = $statusElement.find('.folio-status-loading');
            const $actions = $statusElement.find('.folio-status-actions');
            
            $loading.find('span').text(message || '更新状态中...');
            $actions.fadeOut(200, () => {
                $loading.fadeIn(200);
            });
        },

        // 隐藏状态加载
        hideStatusLoading: function($statusElement) {
            const $loading = $statusElement.find('.folio-status-loading');
            const $actions = $statusElement.find('.folio-status-actions');
            
            $loading.fadeOut(200, () => {
                $actions.fadeIn(200);
            });
        },

        // 处理升级按钮点击
        handleUpgradeClick: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const level = $button.data('level') || 'vip';
            const url = $button.attr('href') || folioComponents.urls.upgrade;
            
            // 添加加载状态
            FrontendComponents.setButtonLoading($button, true);
            
            // 显示引导通知
            const levelName = level === 'svip' ? 'SVIP' : 'VIP';
            FrontendComponents.showNotification(
                `正在为您跳转到${levelName}会员升级页面...`,
                'info',
                2000
            );
            
            // 触发升级事件
            FrontendComponents.trackEvent('upgrade_click', {
                level: level,
                source: 'permission_prompt'
            });
            
            // 延迟跳转以显示加载效果
            setTimeout(() => {
                // 在新标签页打开会员中心（可选）
                if (e.ctrlKey || e.metaKey) {
                    window.open(url, '_blank');
                    FrontendComponents.setButtonLoading($button, false);
                } else {
                    window.location.href = url;
                }
            }, 800);
        },

        // 处理登录按钮点击
        handleLoginClick: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const url = $button.attr('href') || folioComponents.urls.login;
            
            FrontendComponents.setButtonLoading($button, true);
            
            // 显示引导通知
            FrontendComponents.showNotification(
                '正在为您跳转到登录页面...',
                'info',
                1500
            );
            
            FrontendComponents.trackEvent('login_click', {
                source: 'permission_prompt'
            });
            
            // 保存当前页面URL以便登录后返回
            sessionStorage.setItem('folio_return_url', window.location.href);
            
            setTimeout(() => {
                window.location.href = url;
            }, 600);
        },

        // 处理注册按钮点击
        handleRegisterClick: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const url = $button.attr('href') || folioComponents.urls.register;
            
            FrontendComponents.setButtonLoading($button, true);
            
            FrontendComponents.trackEvent('register_click', {
                source: 'permission_prompt'
            });
            
            setTimeout(() => {
                window.location.href = url;
            }, 200);
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
            
            // 显示详细信息模态框（可选）
            if (FrontendComponents.shouldShowModal()) {
                FrontendComponents.showUpgradeModal(level, postId);
            }
        },

        // 处理徽章悬停
        handleBadgeHover: function() {
            const $badge = $(this);
            $badge.addClass('folio-badge-hover');
        },

        // 处理徽章离开
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

        // 处理窗口大小变化
        handleResize: function() {
            // 重新计算移动端布局
            if (FrontendComponents.isMobile()) {
                FrontendComponents.optimizeForMobile();
            }
            
            // 隐藏工具提示
            FrontendComponents.hideTooltip();
        },

        // 更新权限状态
        updatePermissionStatus: function(postId, $prompt) {
            $.ajax({
                url: folioComponents.ajaxurl,
                type: 'POST',
                data: {
                    action: 'folio_get_permission_status',
                    post_id: postId,
                    nonce: folioComponents.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FrontendComponents.handlePermissionUpdate(response.data, $prompt);
                    }
                },
                error: function() {
                    // 静默处理错误
                }
            });
        },

        // 处理权限更新
        handlePermissionUpdate: function(data, $prompt) {
            // 检查提示元素是否已经标记为"已处理"，避免重复刷新
            if ($prompt.data('permission-checked')) {
                return; // 已经检查过，不再处理
            }
            
            // 检查是否已经刷新过（使用sessionStorage防止重复刷新）
            const refreshKey = 'folio_prompt_refresh_' + $prompt.data('post-id');
            if (sessionStorage.getItem(refreshKey)) {
                // 已经刷新过，直接隐藏提示，不刷新
                $prompt.data('permission-checked', true);
                $prompt.fadeOut(500, function() {
                    $(this).remove();
                });
                return;
            }
            
            // 检查用户之前是否有权限（从data属性获取）
            const previousAccess = $prompt.data('can-access') === 'true' || $prompt.data('can-access') === true;
            const currentAccess = data.can_access;
            
            // 记录初始状态
            const initialAccess = $prompt.data('initial-can-access');
            if (initialAccess === undefined) {
                $prompt.data('initial-can-access', currentAccess);
            }
            
            // 标记为已检查
            $prompt.data('permission-checked', true);
            $prompt.data('can-access', currentAccess);
            
            // 只有在权限状态从"无权限"变为"有权限"时才刷新页面
            // 如果页面加载时就已经有权限，不刷新
            if (currentAccess && !previousAccess && initialAccess !== true) {
                // 标记为已刷新
                sessionStorage.setItem(refreshKey, 'true');
                
                // 用户刚刚获得权限，隐藏提示
                $prompt.fadeOut(500, function() {
                    $(this).remove();
                });
                
                // 显示成功通知
                FrontendComponents.showNotification(
                    '内容已解锁，正在刷新页面...',
                    'success'
                );
                
                // 刷新页面显示完整内容
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else if (currentAccess) {
                // 用户已经有权限（包括首次加载时就有权限），只是隐藏提示，不刷新页面
                $prompt.fadeOut(500, function() {
                    $(this).remove();
                });
            }
        },

        // 显示升级模态框
        showUpgradeModal: function(level, postId) {
            const levelName = level === 'svip' ? 'SVIP' : 'VIP';
            
            const modal = $(`
                <div class="folio-upgrade-modal" role="dialog" aria-labelledby="folio-modal-title" aria-modal="true">
                    <div class="folio-modal-overlay" aria-hidden="true"></div>
                    <div class="folio-modal-content">
                        <div class="folio-modal-header">
                            <h3 id="folio-modal-title">升级到${levelName}会员</h3>
                            <button class="folio-modal-close" aria-label="关闭对话框">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="folio-modal-body">
                            <p>此内容需要${levelName}会员权限才能查看。</p>
                            <div class="folio-modal-benefits">
                                <h4>升级${levelName}会员，您将获得：</h4>
                                <ul>
                                    ${FrontendComponents.getBenefitsList(level)}
                                </ul>
                            </div>
                        </div>
                        <div class="folio-modal-footer">
                            <button class="folio-btn folio-btn-secondary folio-modal-cancel">取消</button>
                            <a href="${folioComponents.urls.upgrade}" class="folio-btn folio-btn-upgrade folio-btn-primary">立即升级</a>
                        </div>
                    </div>
                </div>
            `);
            
            // 添加到页面
            $('body').append(modal);
            
            // 设置焦点管理
            const $modal = modal.find('.folio-modal-content');
            const $closeBtn = modal.find('.folio-modal-close');
            
            // 显示动画
            setTimeout(() => {
                modal.addClass('folio-modal-show');
                $closeBtn.focus(); // 设置初始焦点
            }, 10);
            
            // 绑定关闭事件
            modal.on('click', '.folio-modal-close, .folio-modal-cancel, .folio-modal-overlay', function(e) {
                if (e.target === this) {
                    FrontendComponents.closeModal(modal);
                }
            });
            
            // 键盘事件
            modal.on('keydown', function(e) {
                if (e.key === 'Escape') {
                    FrontendComponents.closeModal(modal);
                }
                
                // Tab键焦点管理
                if (e.key === 'Tab') {
                    FrontendComponents.handleModalTabNavigation(e, modal);
                }
            });
        },

        // 关闭模态框
        closeModal: function(modal) {
            modal.removeClass('folio-modal-show');
            setTimeout(() => {
                modal.remove();
            }, 300);
        },

        // 处理模态框Tab导航
        handleModalTabNavigation: function(e, modal) {
            const focusableElements = modal.find('button, a, input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const firstElement = focusableElements.first();
            const lastElement = focusableElements.last();
            
            if (e.shiftKey && document.activeElement === firstElement[0]) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement[0]) {
                e.preventDefault();
                firstElement.focus();
            }
        },

        // 获取权益列表
        getBenefitsList: function(level) {
            const benefits = {
                'vip': [
                    '查看VIP专属内容',
                    '无广告浏览体验',
                    '优先客服支持',
                    '专属会员标识'
                ],
                'svip': [
                    '查看所有专属内容',
                    '无广告浏览体验',
                    '24小时专属客服',
                    '专属SVIP标识',
                    '独家高清资源',
                    '提前体验新功能'
                ]
            };
            
            const levelBenefits = benefits[level] || benefits['vip'];
            return levelBenefits.map(benefit => `<li>✓ ${benefit}</li>`).join('');
        },

        // 创建增强的工具提示内容
        createEnhancedTooltip: function(data) {
            const levelName = data.level === 'svip' ? 'SVIP' : 'VIP';
            let content = '';
            
            if (data.canAccess) {
                content = `
                    <div class="folio-tooltip-header folio-tooltip-success">
                        <div class="folio-tooltip-icon">✓</div>
                        <div class="folio-tooltip-title">${levelName} 内容已解锁</div>
                    </div>
                    <div class="folio-tooltip-body">
                        <p>您可以查看此专属内容</p>
                        <div class="folio-tooltip-note">感谢您的支持！</div>
                    </div>
                `;
            } else {
                const benefits = FrontendComponents.getBenefitsForTooltip(data.level);
                let actionText = '';
                
                if (data.userLoggedIn) {
                    const currentLevel = data.userLevel || 'free';
                    const currentLevelName = FrontendComponents.getLevelDisplayName(currentLevel);
                    actionText = `<div class="folio-tooltip-action">
                        <div class="folio-tooltip-current">当前等级：${currentLevelName}</div>
                        <div class="folio-tooltip-upgrade">点击升级到 ${levelName}</div>
                    </div>`;
                } else {
                    actionText = `<div class="folio-tooltip-action">
                        <div class="folio-tooltip-login">点击登录并升级会员</div>
                    </div>`;
                }
                
                content = `
                    <div class="folio-tooltip-header folio-tooltip-locked">
                        <div class="folio-tooltip-icon">🔒</div>
                        <div class="folio-tooltip-title">需要 ${levelName} 会员</div>
                    </div>
                    <div class="folio-tooltip-body">
                        <div class="folio-tooltip-benefits">
                            <div class="folio-tooltip-benefits-title">${levelName} 会员权益：</div>
                            <ul class="folio-tooltip-benefits-list">
                                ${benefits}
                            </ul>
                        </div>
                        ${actionText}
                    </div>
                `;
            }
            
            return content;
        },
        
        // 获取等级显示名称
        getLevelDisplayName: function(level) {
            const names = {
                'free': '普通用户',
                'vip': 'VIP会员',
                'svip': 'SVIP会员'
            };
            return names[level] || '普通用户';
        },
        
        // 获取工具提示中的权益列表
        getBenefitsForTooltip: function(level) {
            const benefits = {
                'vip': [
                    '查看VIP专属内容',
                    '无广告浏览体验',
                    '优先客服支持'
                ],
                'svip': [
                    '查看所有专属内容',
                    '24小时专属客服',
                    '独家高清资源',
                    '提前体验新功能'
                ]
            };
            
            const levelBenefits = benefits[level] || benefits['vip'];
            return levelBenefits.map(benefit => `<li>${benefit}</li>`).join('');
        },
        
        // 显示增强的工具提示
        showEnhancedTooltip: function(element, content) {
            FrontendComponents.hideTooltip(); // 先隐藏现有的
            
            const tooltip = $(`
                <div class="folio-tooltip folio-tooltip-enhanced" role="tooltip">
                    ${content}
                    <div class="folio-tooltip-arrow"></div>
                </div>
            `);
            
            $('body').append(tooltip);
            
            // 计算位置
            const rect = element.getBoundingClientRect();
            const tooltipRect = tooltip[0].getBoundingClientRect();
            
            let top = rect.top - tooltipRect.height - 12;
            let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
            
            // 边界检查
            if (top < 20) {
                top = rect.bottom + 12;
                tooltip.addClass('folio-tooltip-bottom');
            }
            
            if (left < 20) {
                left = 20;
            } else if (left + tooltipRect.width > window.innerWidth - 20) {
                left = window.innerWidth - tooltipRect.width - 20;
            }
            
            tooltip.css({
                top: top + window.scrollY,
                left: left
            });
            
            // 添加显示动画
            setTimeout(() => {
                tooltip.addClass('folio-tooltip-show');
            }, 10);
        },
        
        // 显示简单工具提示（保持向后兼容）
        showTooltip: function(element, text, data) {
            FrontendComponents.hideTooltip(); // 先隐藏现有的
            
            const tooltip = $(`
                <div class="folio-tooltip" role="tooltip">
                    <div class="folio-tooltip-content">${text}</div>
                    <div class="folio-tooltip-arrow"></div>
                </div>
            `);
            
            $('body').append(tooltip);
            
            // 计算位置
            const rect = element.getBoundingClientRect();
            const tooltipRect = tooltip[0].getBoundingClientRect();
            
            let top = rect.top - tooltipRect.height - 10;
            let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
            
            // 边界检查
            if (top < 10) {
                top = rect.bottom + 10;
                tooltip.addClass('folio-tooltip-bottom');
            }
            
            if (left < 10) {
                left = 10;
            } else if (left + tooltipRect.width > window.innerWidth - 10) {
                left = window.innerWidth - tooltipRect.width - 10;
            }
            
            tooltip.css({
                top: top + window.scrollY,
                left: left,
                opacity: 1
            });
        },

        // 隐藏工具提示
        hideTooltip: function() {
            $('.folio-tooltip').remove();
        },

        // 显示通知
        showNotification: function(message, type = 'info', duration = 3000) {
            const notification = $(`
                <div class="folio-notification folio-notification-${type}" role="alert">
                    <div class="folio-notification-content">
                        ${message}
                    </div>
                    <button class="folio-notification-close" aria-label="关闭通知">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `);
            
            $('body').append(notification);
            
            setTimeout(() => {
                notification.addClass('folio-notification-show');
            }, 10);
            
            // 自动隐藏
            const hideTimer = setTimeout(() => {
                FrontendComponents.hideNotification(notification);
            }, duration);
            
            // 手动关闭
            notification.on('click', '.folio-notification-close', function() {
                clearTimeout(hideTimer);
                FrontendComponents.hideNotification(notification);
            });
        },

        // 隐藏通知
        hideNotification: function(notification) {
            notification.removeClass('folio-notification-show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        },

        // 设置按钮加载状态
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.addClass('loading').prop('disabled', true);
            } else {
                $button.removeClass('loading').prop('disabled', false);
            }
        },

        // 移动端优化
        optimizeForMobile: function() {
            // 调整权限提示样式
            $('.folio-permission-prompt').addClass('folio-mobile-optimized');
            
            // 调整按钮大小
            $('.folio-btn').addClass('folio-mobile-btn folio-touch-target');
            
            // 调整徽章大小
            $('.folio-membership-badge').addClass('folio-mobile-badge folio-touch-target');
            
            // 添加移动端特定类
            $('body').addClass('folio-mobile-device');
            
            // 优化权限提示显示方式
            this.optimizeMobilePrompts();
            
            // 设置移动端特定的交互模式
            this.setupMobileInteractions();
        },

        // 触摸设备优化
        optimizeForTouch: function() {
            // 增加触摸目标大小
            $('.folio-btn, .folio-membership-badge').addClass('folio-touch-optimized');
            
            // 添加触摸反馈
            $(document).on('touchstart', '.folio-btn', function(e) {
                const $btn = $(this);
                $btn.addClass('folio-touch-active');
                
                // 触发触觉反馈
                FrontendComponents.triggerHapticFeedback('light');
            });
            
            $(document).on('touchend touchcancel', '.folio-btn', function() {
                const $btn = $(this);
                setTimeout(() => {
                    $btn.removeClass('folio-touch-active');
                }, 150);
            });
            
            // 徽章触摸反馈
            $(document).on('touchstart', '.folio-membership-badge', function(e) {
                const $badge = $(this);
                $badge.addClass('folio-touch-active');
                FrontendComponents.triggerHapticFeedback('light');
            });
            
            $(document).on('touchend touchcancel', '.folio-membership-badge', function() {
                const $badge = $(this);
                setTimeout(() => {
                    $badge.removeClass('folio-touch-active');
                }, 100);
            });
        },

        // 优化移动端权限提示
        optimizeMobilePrompts: function() {
            $('.folio-permission-prompt').each(function() {
                const $prompt = $(this);
                
                // 添加移动端展开/收起功能
                const $header = $prompt.find('.folio-prompt-header');
                const $body = $prompt.find('.folio-prompt-body');
                
                if ($header.length && $body.length) {
                    $header.addClass('folio-mobile-collapsible');
                    $body.addClass('folio-mobile-collapsible-content');
                    
                    // 默认收起详细信息
                    if (window.innerWidth < 480) {
                        $body.hide();
                        $header.append('<span class="folio-expand-indicator">展开详情</span>');
                    }
                }
            });
        },

        // 设置移动端交互
        setupMobileInteractions: function() {
            // 权限提示展开/收起
            $(document).on('click', '.folio-mobile-collapsible', function() {
                const $header = $(this);
                const $content = $header.siblings('.folio-mobile-collapsible-content');
                const $indicator = $header.find('.folio-expand-indicator');
                
                if ($content.is(':visible')) {
                    $content.slideUp(300);
                    $indicator.text('展开详情');
                } else {
                    $content.slideDown(300);
                    $indicator.text('收起详情');
                }
                
                FrontendComponents.triggerHapticFeedback('medium');
            });
            
            // 长按显示详细信息
            let longPressTimer;
            $(document).on('touchstart', '.folio-membership-badge', function(e) {
                const $badge = $(this);
                longPressTimer = setTimeout(() => {
                    FrontendComponents.showMobileBadgeDetails($badge);
                    FrontendComponents.triggerHapticFeedback('heavy');
                }, 500);
            });
            
            $(document).on('touchend touchmove touchcancel', '.folio-membership-badge', function() {
                clearTimeout(longPressTimer);
            });
        },

        // 初始化移动端手势
        initMobileGestures: function() {
            let startY = 0;
            let currentY = 0;
            let isDragging = false;
            
            // 下拉刷新手势
            $(document).on('touchstart', function(e) {
                if (window.scrollY === 0) {
                    startY = e.touches[0].clientY;
                }
            });
            
            $(document).on('touchmove', function(e) {
                if (window.scrollY === 0 && startY > 0) {
                    currentY = e.touches[0].clientY;
                    const deltaY = currentY - startY;
                    
                    if (deltaY > 50 && !isDragging) {
                        isDragging = true;
                        FrontendComponents.showPullToRefreshIndicator();
                    }
                }
            });
            
            $(document).on('touchend', function() {
                if (isDragging) {
                    isDragging = false;
                    FrontendComponents.hidePullToRefreshIndicator();
                    

                }
                startY = 0;
                currentY = 0;
            });
            
            // 侧滑手势
            this.initSwipeGestures();
        },

        // 初始化侧滑手势
        initSwipeGestures: function() {
            let startX = 0;
            let startY = 0;
            let currentElement = null;
            
            $(document).on('touchstart', '.folio-swipeable', function(e) {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
                currentElement = $(this);
            });
            
            $(document).on('touchmove', '.folio-swipeable', function(e) {
                if (!currentElement) return;
                
                const currentX = e.touches[0].clientX;
                const currentY = e.touches[0].clientY;
                const deltaX = startX - currentX;
                const deltaY = Math.abs(startY - currentY);
                
                // 只有水平滑动且垂直移动较小时才触发
                if (Math.abs(deltaX) > 50 && deltaY < 30) {
                    e.preventDefault();
                    
                    if (deltaX > 0) {
                        // 向左滑动，显示操作按钮
                        currentElement.addClass('swiped');
                        FrontendComponents.triggerHapticFeedback('light');
                    } else {
                        // 向右滑动，隐藏操作按钮
                        currentElement.removeClass('swiped');
                    }
                }
            });
            
            $(document).on('touchend', '.folio-swipeable', function() {
                currentElement = null;
            });
        },

        // 初始化移动端底部弹窗
        initMobileBottomSheet: function() {
            // 替换模态框为底部弹窗
            $(document).on('click', '.folio-membership-badge', function(e) {
                if (FrontendComponents.isMobile()) {
                    e.preventDefault();
                    const $badge = $(this);
                    const canAccess = $badge.data('can-access') === 'true';
                    
                    if (!canAccess) {
                        FrontendComponents.showMobileBottomSheet($badge);
                    }
                }
            });
        },

        // 显示移动端底部弹窗
        showMobileBottomSheet: function($badge) {
            const level = $badge.data('level');
            const levelName = level === 'svip' ? 'SVIP' : 'VIP';
            const userLoggedIn = $badge.data('user-logged-in') === 'true';
            const postId = $badge.data('post-id');
            
            const bottomSheet = $(`
                <div class="folio-mobile-bottom-sheet" role="dialog" aria-labelledby="bottom-sheet-title">
                    <div class="folio-bottom-sheet-handle"></div>
                    <div class="folio-bottom-sheet-content">
                        <div class="folio-bottom-sheet-header">
                            <h3 id="bottom-sheet-title">${levelName} 会员专属</h3>
                            <button class="folio-bottom-sheet-close" aria-label="关闭">×</button>
                        </div>
                        <div class="folio-bottom-sheet-body">
                            ${userLoggedIn ? 
                                `<p>您当前的会员等级不足以查看此内容。</p>
                                 <p>升级到 <strong>${levelName}</strong> 会员即可解锁。</p>` :
                                `<p>此内容需要 <strong>${levelName}</strong> 会员权限。</p>
                                 <p>请先登录您的账户，然后升级会员。</p>`
                            }
                            <div class="folio-mobile-benefits">
                                <h4>${levelName} 会员权益：</h4>
                                <ul class="folio-benefits-grid">
                                    ${FrontendComponents.getMobileBenefitsList(level)}
                                </ul>
                            </div>
                        </div>
                        <div class="folio-bottom-sheet-actions">
                            ${userLoggedIn ? 
                                `<button class="folio-btn folio-btn-upgrade folio-btn-${level} folio-haptic-medium" data-level="${level}">
                                    <span>升级${levelName}</span>
                                    <span class="folio-btn-icon">→</span>
                                </button>` :
                                `<button class="folio-btn folio-btn-login folio-btn-primary folio-haptic-medium">
                                    <span>登录</span>
                                    <span class="folio-btn-icon">→</span>
                                </button>`
                            }
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(bottomSheet);
            
            // 显示动画
            setTimeout(() => {
                bottomSheet.addClass('show');
            }, 10);
            
            // 绑定关闭事件
            bottomSheet.on('click', '.folio-bottom-sheet-close', function() {
                FrontendComponents.hideMobileBottomSheet(bottomSheet);
            });
            
            // 点击背景关闭
            bottomSheet.on('click', function(e) {
                if (e.target === this) {
                    FrontendComponents.hideMobileBottomSheet(bottomSheet);
                }
            });
            
            // 拖拽关闭
            this.setupBottomSheetDrag(bottomSheet);
        },

        // 隐藏移动端底部弹窗
        hideMobileBottomSheet: function(bottomSheet) {
            bottomSheet.removeClass('show');
            setTimeout(() => {
                bottomSheet.remove();
            }, 300);
        },

        // 设置底部弹窗拖拽
        setupBottomSheetDrag: function(bottomSheet) {
            const handle = bottomSheet.find('.folio-bottom-sheet-handle');
            let startY = 0;
            let currentY = 0;
            let isDragging = false;
            
            handle.on('touchstart', function(e) {
                startY = e.touches[0].clientY;
                isDragging = true;
                bottomSheet.css('transition', 'none');
            });
            
            $(document).on('touchmove', function(e) {
                if (!isDragging) return;
                
                currentY = e.touches[0].clientY;
                const deltaY = currentY - startY;
                
                if (deltaY > 0) {
                    bottomSheet.css('transform', `translateY(${deltaY}px)`);
                }
            });
            
            $(document).on('touchend', function() {
                if (!isDragging) return;
                
                isDragging = false;
                bottomSheet.css('transition', '');
                
                const deltaY = currentY - startY;
                if (deltaY > 100) {
                    FrontendComponents.hideMobileBottomSheet(bottomSheet);
                } else {
                    bottomSheet.css('transform', '');
                }
            });
        },

        // 显示移动端徽章详情
        showMobileBadgeDetails: function($badge) {
            const level = $badge.data('level');
            const levelName = level === 'svip' ? 'SVIP' : 'VIP';
            const canAccess = $badge.data('can-access') === 'true';
            
            const details = $(`
                <div class="folio-mobile-badge-details">
                    <div class="folio-badge-detail-content">
                        <div class="folio-badge-detail-header">
                            <span class="folio-badge-detail-level">${levelName}</span>
                            <span class="folio-badge-detail-status">
                                ${canAccess ? '已解锁' : '需要升级'}
                            </span>
                        </div>
                        <div class="folio-badge-detail-description">
                            ${canAccess ? 
                                '您可以查看此专属内容' : 
                                `需要${levelName}会员权限才能查看`
                            }
                        </div>
                    </div>
                </div>
            `);
            
            // 定位到徽章位置
            const badgeOffset = $badge.offset();
            details.css({
                position: 'absolute',
                top: badgeOffset.top - 60,
                left: badgeOffset.left,
                zIndex: 10000
            });
            
            $('body').append(details);
            
            setTimeout(() => {
                details.addClass('show');
            }, 10);
            
            // 3秒后自动隐藏
            setTimeout(() => {
                details.removeClass('show');
                setTimeout(() => {
                    details.remove();
                }, 300);
            }, 3000);
        },

        // 设置移动端视口
        setupMobileViewport: function() {
            // 防止iOS Safari地址栏影响视口高度
            const setViewportHeight = () => {
                const vh = window.innerHeight * 0.01;
                document.documentElement.style.setProperty('--vh', `${vh}px`);
            };
            
            setViewportHeight();
            window.addEventListener('resize', setViewportHeight);
            window.addEventListener('orientationchange', () => {
                setTimeout(setViewportHeight, 100);
            });
        },

        // 处理设备方向变化
        handleOrientationChange: function() {
            window.addEventListener('orientationchange', () => {
                setTimeout(() => {
                    // 重新计算布局
                    FrontendComponents.recalculateMobileLayout();
                    
                    // 隐藏所有工具提示
                    FrontendComponents.hideTooltip();
                    
                    // 重新优化移动端显示
                    if (FrontendComponents.isMobile()) {
                        FrontendComponents.optimizeForMobile();
                    }
                }, 200);
            });
        },

        // 重新计算移动端布局
        recalculateMobileLayout: function() {
            $('.folio-permission-prompt').each(function() {
                const $element = $(this);
                
                // 重新应用移动端样式
                if (window.innerWidth <= 768) {
                    $element.addClass('folio-mobile-optimized');
                } else {
                    $element.removeClass('folio-mobile-optimized');
                }
            });
        },

        // 初始化触觉反馈
        initHapticFeedback: function() {
            // 检测是否支持触觉反馈
            this.hapticSupported = 'vibrate' in navigator;
            
            // 为按钮添加触觉反馈类
            $('.folio-btn').addClass('folio-haptic-light');
            $('.folio-btn-upgrade').addClass('folio-haptic-medium');
            $('.folio-membership-badge').addClass('folio-haptic-light');
        },

        // 触发触觉反馈
        triggerHapticFeedback: function(type = 'light') {
            if (!this.hapticSupported) return;
            
            const patterns = {
                light: [10],
                medium: [20],
                heavy: [30, 10, 30]
            };
            
            const pattern = patterns[type] || patterns.light;
            navigator.vibrate(pattern);
        },

        // 显示下拉刷新指示器
        showPullToRefreshIndicator: function() {
            if ($('.folio-pull-refresh-indicator').length) return;
            
            const indicator = $(`
                <div class="folio-pull-refresh-indicator">
                    <div class="folio-refresh-spinner"></div>
                    <span>松开刷新权限状态</span>
                </div>
            `);
            
            $('body').prepend(indicator);
            setTimeout(() => {
                indicator.addClass('show');
            }, 10);
        },

        // 隐藏下拉刷新指示器
        hidePullToRefreshIndicator: function() {
            const indicator = $('.folio-pull-refresh-indicator');
            indicator.removeClass('show');
            setTimeout(() => {
                indicator.remove();
            }, 300);
        },



        // 获取移动端权益列表
        getMobileBenefitsList: function(level) {
            const benefits = {
                'vip': [
                    { icon: '📖', text: 'VIP专属内容' },
                    { icon: '🚫', text: '无广告体验' },
                    { icon: '⭐', text: '优先支持' },
                    { icon: '🏷️', text: '专属标识' }
                ],
                'svip': [
                    { icon: '📚', text: '全部专属内容' },
                    { icon: '🚫', text: '无广告体验' },
                    { icon: '🔧', text: '24小时客服' },
                    { icon: '👑', text: 'SVIP标识' },
                    { icon: '🎯', text: '独家资源' },
                    { icon: '🚀', text: '新功能抢先体验' }
                ]
            };
            
            const levelBenefits = benefits[level] || benefits['vip'];
            return levelBenefits.map(benefit => 
                `<li><span class="folio-benefit-icon">${benefit.icon}</span>${benefit.text}</li>`
            ).join('');
        },

        // 事件跟踪
        trackEvent: function(eventName, data) {
            // 集成Google Analytics或其他分析工具
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    custom_parameter_1: data.level || '',
                    custom_parameter_2: data.source || '',
                    custom_parameter_3: data.postId || ''
                });
            }
            
            // 自定义事件
            $(document).trigger('folio:' + eventName, data);
        },

        // 工具函数
        isMobile: function() {
            return window.innerWidth <= 768;
        },

        isTouchDevice: function() {
            return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        },

        shouldShowModal: function() {
            // 根据设备类型和用户偏好决定是否显示模态框
            return !this.isMobile() && !sessionStorage.getItem('folio_modal_dismissed');
        },

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
        }
    };

    // 初始化
    $(document).ready(function() {
        FrontendComponents.init();
    });

    // 暴露到全局
    window.FolioFrontendComponents = FrontendComponents;

})(jQuery);

// 添加额外的CSS样式
const additionalStyles = `
<style>
/* 额外的交互样式 */
.folio-badge-animated {
    animation: badgeSlideIn 0.3s ease-out;
}

@keyframes badgeSlideIn {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.folio-badge-hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.folio-prompt-clicked {
    transform: scale(0.98);
    transition: transform 0.1s ease;
}

/* 模态框样式 */
.folio-upgrade-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.folio-upgrade-modal.folio-modal-show {
    opacity: 1;
    visibility: visible;
}

.folio-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.folio-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.9);
    background: #fff;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.folio-modal-show .folio-modal-content {
    transform: translate(-50%, -50%) scale(1);
}

.folio-modal-header {
    padding: 20px 20px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 20px;
}

.folio-modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

.folio-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.folio-modal-close:hover {
    background: #f3f4f6;
    color: #374151;
}

.folio-modal-body {
    padding: 0 20px;
}

.folio-modal-benefits {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.folio-modal-benefits h4 {
    margin: 0 0 10px 0;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
}

.folio-modal-benefits ul {
    margin: 0;
    padding-left: 0;
    list-style: none;
}

.folio-modal-benefits li {
    margin: 8px 0;
    color: #10b981;
    font-weight: 500;
}

.folio-modal-footer {
    padding: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    border-top: 1px solid #e5e7eb;
    margin-top: 20px;
}

/* 增强的工具提示样式 */
.folio-tooltip {
    position: absolute;
    z-index: 10000;
    background: #1f2937;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
    max-width: 200px;
    word-wrap: break-word;
    transform: translateY(5px);
}

.folio-tooltip-show {
    opacity: 1;
    transform: translateY(0);
}

.folio-tooltip-enhanced {
    background: #ffffff;
    color: #374151;
    border-radius: 12px;
    padding: 0;
    max-width: 280px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.folio-tooltip-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-bottom: 1px solid #f3f4f6;
}

.folio-tooltip-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
}

.folio-tooltip-locked {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
}

.folio-tooltip-icon {
    font-size: 16px;
    line-height: 1;
}

.folio-tooltip-title {
    font-weight: 600;
    font-size: 13px;
}

.folio-tooltip-body {
    padding: 12px 16px;
}

.folio-tooltip-benefits {
    margin-bottom: 12px;
}

.folio-tooltip-benefits-title {
    font-weight: 600;
    font-size: 12px;
    color: #374151;
    margin-bottom: 6px;
}

.folio-tooltip-benefits-list {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 11px;
    color: #6b7280;
}

.folio-tooltip-benefits-list li {
    padding: 2px 0;
    position: relative;
    padding-left: 12px;
}

.folio-tooltip-benefits-list li:before {
    content: '•';
    position: absolute;
    left: 0;
    color: #10b981;
    font-weight: bold;
}

.folio-tooltip-action {
    border-top: 1px solid #f3f4f6;
    padding-top: 8px;
    margin-top: 8px;
}

.folio-tooltip-current {
    font-size: 11px;
    color: #6b7280;
    margin-bottom: 4px;
}

.folio-tooltip-upgrade,
.folio-tooltip-login {
    font-size: 12px;
    font-weight: 600;
    color: #3b82f6;
}

.folio-tooltip-note {
    font-size: 11px;
    color: #6b7280;
    font-style: italic;
    margin-top: 8px;
}

.folio-tooltip-arrow {
    position: absolute;
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-top: 6px solid #ffffff;
}

.folio-tooltip-enhanced .folio-tooltip-arrow {
    border-top-color: #ffffff;
}

.folio-tooltip-bottom .folio-tooltip-arrow {
    top: -6px;
    bottom: auto;
    border-top: none;
    border-bottom: 6px solid #ffffff;
}

.folio-tooltip-enhanced.folio-tooltip-bottom .folio-tooltip-arrow {
    border-bottom-color: #ffffff;
}

/* 通知样式 */
.folio-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    z-index: 10000;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    min-width: 300px;
    max-width: 400px;
}

.folio-notification-show {
    transform: translateX(0);
}

.folio-notification-content {
    padding: 16px 40px 16px 16px;
    color: #374151;
    font-weight: 500;
}

.folio-notification-close {
    position: absolute;
    top: 8px;
    right: 8px;
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #6b7280;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.folio-notification-close:hover {
    background: #f3f4f6;
}

.folio-notification-success {
    border-left: 4px solid #10b981;
}

.folio-notification-error {
    border-left: 4px solid #ef4444;
}

.folio-notification-info {
    border-left: 4px solid #3b82f6;
}

/* 移动端优化 */
.folio-mobile-optimized {
    margin: 1rem 0;
    border-radius: 8px;
}

.folio-mobile-btn {
    padding: 12px 20px;
    font-size: 16px; /* 防止iOS缩放 */
}

.folio-mobile-badge {
    font-size: 11px;
    padding: 4px 8px;
}

.folio-touch-optimized {
    min-height: 44px;
    min-width: 44px;
}

.folio-touch-active {
    background-color: rgba(0, 0, 0, 0.1);
}

/* 响应式模态框 */
@media (max-width: 768px) {
    .folio-modal-content {
        width: 95%;
        margin: 20px;
        max-height: 90vh;
    }
    
    .folio-modal-footer {
        flex-direction: column;
    }
    
    .folio-modal-footer .folio-btn {
        width: 100%;
        justify-content: center;
    }
    
    .folio-notification {
        left: 10px;
        right: 10px;
        min-width: auto;
        max-width: none;
    }
}

/* 高对比度支持 */
@media (prefers-contrast: high) {
    .folio-tooltip {
        border: 2px solid #fff;
    }
    
    .folio-modal-content {
        border: 3px solid #000;
    }
}

/* 减少动画支持 */
@media (prefers-reduced-motion: reduce) {
    .folio-badge-animated,
    .folio-upgrade-modal,
    .folio-modal-content,
    .folio-notification,
    .folio-tooltip {
        animation: none;
        transition: none;
    }
}

/* 移动端专用样式 */
.folio-mobile-device .folio-permission-prompt {
    position: relative;
    overflow: hidden;
}

.folio-mobile-collapsible {
    cursor: pointer;
    position: relative;
}

.folio-expand-indicator {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.folio-mobile-collapsible-content {
    overflow: hidden;
}

/* 移动端徽章详情 */
.folio-mobile-badge-details {
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    opacity: 0;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    pointer-events: none;
    max-width: 200px;
}

.folio-mobile-badge-details.show {
    opacity: 1;
    transform: translateY(0);
}

.folio-badge-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.folio-badge-detail-level {
    font-weight: 600;
}

.folio-badge-detail-status {
    font-size: 10px;
    opacity: 0.8;
}

.folio-badge-detail-description {
    font-size: 11px;
    opacity: 0.9;
}

/* 下拉刷新指示器 */
.folio-pull-refresh-indicator {
    position: fixed;
    top: -60px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 12px 20px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 500;
    z-index: 10000;
    transition: top 0.3s ease;
}

.folio-pull-refresh-indicator.show {
    top: 20px;
}

.folio-refresh-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* 移动端权益网格 */
.folio-benefits-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    list-style: none;
    padding: 0;
    margin: 0;
}

.folio-benefits-grid li {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 6px;
    font-size: 12px;
    color: #374151;
}

.folio-benefits-grid .folio-benefit-icon {
    font-size: 14px;
    line-height: 1;
}

/* 底部弹窗操作区域 */
.folio-bottom-sheet-actions {
    padding: 16px 20px 20px;
    border-top: 1px solid #e5e7eb;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
}

.folio-bottom-sheet-actions .folio-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.folio-btn-icon {
    font-size: 18px;
    font-weight: normal;
    opacity: 0.8;
}

/* CSS变量支持移动端视口 */
:root {
    --vh: 1vh;
}

.folio-mobile-fullscreen {
    height: calc(var(--vh, 1vh) * 100);
}

/* 移动端触摸反馈 */
.folio-touch-active {
    opacity: 0.8;
    transform: scale(0.98);
}

/* 侧滑操作 */
.folio-swipe-actions .folio-btn {
    min-width: 60px;
    height: 40px;
    padding: 0 12px;
    font-size: 12px;
    border-radius: 6px;
}

/* 移动端深色模式优化 */
@media (max-width: 768px) and (prefers-color-scheme: dark) {
    .folio-benefits-grid li {
        background: #374151;
        color: #d1d5db;
    }
    
    .folio-bottom-sheet-actions {
        background: rgba(31, 41, 55, 0.95);
        border-color: #4b5563;
    }
    
    .folio-mobile-badge-details {
        background: rgba(255, 255, 255, 0.9);
        color: #1f2937;
    }
}
</style>
`;

// 注入样式
if (document.head) {
    document.head.insertAdjacentHTML('beforeend', additionalStyles);
}

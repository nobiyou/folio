/**
 * Premium Content JavaScript
 * 
 * 会员专属内容前端交互
 */

(function($) {
    'use strict';

    function t(key, fallback) {
        if (typeof folioPremium !== 'undefined' && folioPremium.strings && folioPremium.strings[key]) {
            return folioPremium.strings[key];
        }
        return fallback;
    }

    // 会员专属内容管理器
    const PremiumContent = {
        
        init: function() {
            this.bindEvents();
            this.initAnimations();
            this.checkMembershipStatus();
        },

        bindEvents: function() {
            // 升级按钮点击事件
            $(document).on('click', '.premium-btn-upgrade, .membership-btn-upgrade', this.handleUpgradeClick);
            
            // 登录按钮点击事件
            $(document).on('click', '.premium-btn-login', this.handleLoginClick);
            
            // 会员卡片悬停效果
            $(document).on('mouseenter', '.membership-card', this.handleCardHover);
            $(document).on('mouseleave', '.membership-card', this.handleCardLeave);
            
            // 内容解锁尝试
            $(document).on('click', '.premium-content-locked', this.handleContentClick);
        },

        initAnimations: function() {
            // 为会员专属内容添加入场动画
            $('.premium-content').each(function(index) {
                $(this).css({
                    'opacity': '0',
                    'transform': 'translateY(20px)'
                }).delay(index * 100).animate({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                }, 600);
            });

            // 为会员卡片添加交错动画
            $('.membership-card').each(function(index) {
                $(this).css({
                    'opacity': '0',
                    'transform': 'scale(0.95)'
                }).delay(index * 150).animate({
                    'opacity': '1',
                    'transform': 'scale(1)'
                }, 500);
            });
        },

        checkMembershipStatus: function() {
            // 检查用户会员状态并更新UI
            if (typeof folioPremium !== 'undefined') {
                this.updateMembershipUI();
            }
        },

        updateMembershipUI: function() {
            // 根据用户会员状态更新界面元素
            const membershipLevel = this.getCurrentMembershipLevel();
            
            // 更新会员徽章
            $('.membership-badge-current').removeClass('membership-badge-current');
            $(`.membership-badge-${membershipLevel}`).addClass('membership-badge-current');
            
            // 更新升级提示
            this.updateUpgradePrompts(membershipLevel);
        },

        getCurrentMembershipLevel: function() {
            // 从页面数据或AJAX获取当前用户会员等级
            const membershipData = $('body').data('membership-level');
            return membershipData || 'free';
        },

        updateUpgradePrompts: function(currentLevel) {
            $('.upgrade-prompt').each(function() {
                const $prompt = $(this);
                const targetLevel = $prompt.data('target-level') || 'vip';
                
                // 如果用户已经达到或超过目标等级，隐藏升级提示
                if (currentLevel === 'svip' || (currentLevel === 'vip' && targetLevel === 'vip')) {
                    $prompt.fadeOut();
                }
            });
        },

        handleUpgradeClick: function(e) {
            if (window.FolioCore) {
                FolioCore.handleUpgradeClick(e, {
                    source: 'premium_content'
                });
            } else {
                // 降级方案
                e.preventDefault();
                const $button = $(this);
                $button.addClass('loading').prop('disabled', true);
                setTimeout(() => {
                    window.location.href = $button.attr('href') || '/user-center/membership';
                }, 300);
            }
        },

        handleLoginClick: function(e) {
            if (window.FolioCore) {
                FolioCore.handleLoginClick(e, {
                    source: 'premium_content'
                });
            } else {
                // 降级方案
                e.preventDefault();
                const $button = $(this);
                $button.addClass('loading');
                setTimeout(() => {
                    window.location.href = $button.attr('href') || '/user-center/login';
                }, 200);
            }
        },

        handleCardHover: function() {
            const $card = $(this);
            
            if (!$card.hasClass('membership-card-current')) {
                $card.css('transform', 'translateY(-5px)');
            }
        },

        handleCardLeave: function() {
            const $card = $(this);
            
            if (!$card.hasClass('membership-card-current')) {
                $card.css('transform', 'translateY(0)');
            }
        },

        handleContentClick: function(e) {
            const $content = $(this);
            const level = $content.data('level');
            
            // 添加点击反馈
            $content.addClass('premium-content-clicked');
            
            setTimeout(() => {
                $content.removeClass('premium-content-clicked');
            }, 200);
            
            // 显示升级提示
            this.showUpgradeModal(level);
        },

        showUpgradeModal: function(requiredLevel) {
            const levelName = requiredLevel === 'svip' ? 'SVIP' : 'VIP';
            const membershipUrl = (typeof folioPremium !== 'undefined' && folioPremium.membership_url) ? folioPremium.membership_url : '/user-center/membership';
            
            // 创建模态框
            const modal = $(`
                <div class="premium-upgrade-modal">
                    <div class="premium-modal-overlay"></div>
                    <div class="premium-modal-content">
                        <div class="premium-modal-header">
                            <h3>${t('upgrade_to_membership', 'Upgrade to %s Membership').replace('%s', levelName)}</h3>
                            <button class="premium-modal-close">&times;</button>
                        </div>
                        <div class="premium-modal-body">
                            <p>${t('content_requires_membership', 'This content requires %s membership to view.').replace('%s', levelName)}</p>
                            <div class="premium-modal-benefits">
                                <h4>${t('upgrade_benefits_title', 'Upgrade to %s membership and you will get:').replace('%s', levelName)}</h4>
                                <ul>
                                    <li>✓ ${t('benefit_exclusive_content', 'Access all %s exclusive content').replace('%s', levelName)}</li>
                                    <li>✓ ${t('benefit_ad_free', 'Ad-free browsing experience')}</li>
                                    <li>✓ ${t('benefit_priority_support', 'Priority customer support')}</li>
                                    <li>✓ ${t('benefit_member_badge', 'Exclusive member badge')}</li>
                                </ul>
                            </div>
                        </div>
                        <div class="premium-modal-footer">
                            <button class="premium-modal-cancel">${t('cancel', 'Cancel')}</button>
                            <a href="${membershipUrl}" class="premium-modal-upgrade">${t('upgrade_now', 'Upgrade Now')}</a>
                        </div>
                    </div>
                </div>
            `);
            
            // 添加到页面
            $('body').append(modal);
            
            // 显示动画
            setTimeout(() => {
                modal.addClass('premium-modal-show');
            }, 10);
            
            // 绑定关闭事件
            modal.on('click', '.premium-modal-close, .premium-modal-cancel, .premium-modal-overlay', function() {
                modal.removeClass('premium-modal-show');
                setTimeout(() => {
                    modal.remove();
                }, 300);
            });
        },

        // 工具函数
        utils: {
            // 格式化价格
            formatPrice: function(price) {
                return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            },
            
            // 计算剩余天数
            calculateDaysLeft: function(expiryDate) {
                const now = new Date();
                const expiry = new Date(expiryDate);
                const diffTime = expiry - now;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                return Math.max(0, diffDays);
            },
            
            // 显示通知 - 使用核心功能
            showNotification: function(message, type = 'info') {
                if (window.FolioCore) {
                    FolioCore.showNotification(message, type);
                } else {
                    // 降级方案
                    alert(message);
                }
            }
        }
    };

    // 初始化
    $(document).ready(function() {
        PremiumContent.init();
    });

    // 暴露到全局
    window.FolioPremiumContent = PremiumContent;

})(jQuery);

// 添加CSS样式
const premiumStyles = `
<style>
/* 会员专属内容交互样式 */
.premium-content-clicked {
    transform: scale(0.98);
    transition: transform 0.1s ease;
}

.loading {
    opacity: 0.7;
    pointer-events: none;
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 升级模态框样式 */
.premium-upgrade-modal {
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

.premium-upgrade-modal.premium-modal-show {
    opacity: 1;
    visibility: visible;
}

.premium-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.premium-modal-content {
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
    transition: transform 0.3s ease;
}

.premium-modal-show .premium-modal-content {
    transform: translate(-50%, -50%) scale(1);
}

.premium-modal-header {
    padding: 20px 20px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.premium-modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.premium-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.premium-modal-body {
    padding: 20px;
}

.premium-modal-benefits {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.premium-modal-benefits h4 {
    margin: 0 0 10px 0;
    font-size: 1rem;
    font-weight: 600;
}

.premium-modal-benefits ul {
    margin: 0;
    padding-left: 0;
    list-style: none;
}

.premium-modal-benefits li {
    margin: 8px 0;
    color: #10b981;
}

.premium-modal-footer {
    padding: 0 20px 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.premium-modal-cancel {
    padding: 8px 16px;
    border: 1px solid #d1d5db;
    background: #fff;
    color: #374151;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.premium-modal-upgrade {
    padding: 8px 16px;
    background: #0073aa;
    color: #fff;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.premium-modal-upgrade:hover {
    background: #005a87;
    color: #fff;
}

/* 通知样式 */
.premium-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 10000;
    transform: translateX(100%);
    transition: transform 0.3s ease;
}

.premium-notification-show {
    transform: translateX(0);
}

.premium-notification-success {
    border-left: 4px solid #10b981;
}

.premium-notification-error {
    border-left: 4px solid #ef4444;
}

.premium-notification-info {
    border-left: 4px solid #3b82f6;
}

/* 响应式 */
@media (max-width: 768px) {
    .premium-modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .premium-modal-footer {
        flex-direction: column;
    }
    
    .premium-modal-cancel,
    .premium-modal-upgrade {
        width: 100%;
        text-align: center;
    }
}
</style>
`;

// 注入样式
if (document.head) {
    document.head.insertAdjacentHTML('beforeend', premiumStyles);
}

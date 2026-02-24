<?php
/**
 * Membership Showcase Template Part
 * 
 * 会员展示模板部分
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_membership = folio_get_user_membership();
$levels = folio_get_membership_levels();
?>

<div class="membership-showcase">
    <div class="membership-showcase-header">
        <h2 class="membership-showcase-title"><?php esc_html_e('Choose the membership plan that fits you', 'folio'); ?></h2>
        <p class="membership-showcase-description"><?php esc_html_e('Upgrade membership to unlock more exclusive content and features', 'folio'); ?></p>
    </div>
    
    <div class="membership-plans">
        <!-- 免费用户卡片 -->
        <div class="membership-card membership-card-free <?php echo $user_membership['level'] === 'free' ? 'membership-card-current' : ''; ?>">
            <div class="membership-card-header">
                <div class="membership-card-badge">
                    <?php echo wp_kses_post($levels['free']['icon']); ?>
                </div>
                <h3 class="membership-card-title"><?php echo esc_html($levels['free']['name']); ?></h3>
                <div class="membership-card-price">
                    <span class="price-amount"><?php esc_html_e('Free', 'folio'); ?></span>
                </div>
            </div>
            
            <div class="membership-card-features">
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Browse basic content', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Participate in community discussions', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Use basic features', 'folio'); ?></span>
                </div>
            </div>
            
            <div class="membership-card-action">
                <?php if ($user_membership['level'] === 'free') : ?>
                <button class="membership-btn membership-btn-current" disabled>
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Current Plan', 'folio'); ?>
                </button>
                <?php else : ?>
                <button class="membership-btn membership-btn-downgrade" disabled>
                    <?php esc_html_e('Basic Plan', 'folio'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- VIP会员卡片 -->
        <div class="membership-card membership-card-vip <?php echo $user_membership['level'] === 'vip' ? 'membership-card-current' : ''; ?>">
            <div class="membership-card-header">
                <div class="membership-card-badge">
                    <?php echo wp_kses_post($levels['vip']['icon']); ?>
                </div>
                <h3 class="membership-card-title"><?php echo esc_html($levels['vip']['name']); ?></h3>
                <div class="membership-card-price">
                    <span class="price-amount">¥99</span>
                    <span class="price-duration"><?php esc_html_e('/ month', 'folio'); ?></span>
                </div>
            </div>
            
            <div class="membership-card-features">
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Access VIP-exclusive content', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Ad-free browsing experience', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Priority customer support', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Exclusive member badge', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Use advanced features', 'folio'); ?></span>
                </div>
            </div>
            
            <div class="membership-card-action">
                <?php if ($user_membership['level'] === 'vip') : ?>
                <button class="membership-btn membership-btn-current" disabled>
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Current Plan', 'folio'); ?>
                </button>
                <div class="membership-card-status">
                    <?php if ($user_membership['is_permanent']) : ?>
                    <small><?php esc_html_e('Lifetime Member', 'folio'); ?></small>
                    <?php elseif ($user_membership['expiry']) : ?>
                    <small><?php echo sprintf(esc_html__('Expires on: %s', 'folio'), esc_html($user_membership['expiry'])); ?></small>
                    <?php endif; ?>
                </div>
                <?php else : ?>
                <a href="<?php echo esc_url(home_url('/user-center/membership')); ?>" class="membership-btn membership-btn-upgrade">
                    <span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Upgrade to VIP', 'folio'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- SVIP会员卡片 -->
        <div class="membership-card membership-card-svip membership-card-highlight <?php echo $user_membership['level'] === 'svip' ? 'membership-card-current' : ''; ?>">
            <div class="membership-card-header">
                <div class="membership-card-badge">
                    <?php echo wp_kses_post($levels['svip']['icon']); ?>
                </div>
                <h3 class="membership-card-title"><?php echo esc_html($levels['svip']['name']); ?></h3>
                <div class="membership-card-price">
                    <span class="price-amount">¥199</span>
                    <span class="price-duration"><?php esc_html_e('/ month', 'folio'); ?></span>
                </div>
            </div>
            
            <div class="membership-card-features">
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Access all exclusive content', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Ad-free browsing experience', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('24/7 dedicated support', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Exclusive SVIP badge', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Exclusive HD resources', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Early access to new features', 'folio'); ?></span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span><?php esc_html_e('Dedicated account manager', 'folio'); ?></span>
                </div>
            </div>
            
            <div class="membership-card-action">
                <?php if ($user_membership['level'] === 'svip') : ?>
                <button class="membership-btn membership-btn-current" disabled>
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Current Plan', 'folio'); ?>
                </button>
                <div class="membership-card-status">
                    <?php if ($user_membership['is_permanent']) : ?>
                    <small><?php esc_html_e('Lifetime Member', 'folio'); ?></small>
                    <?php elseif ($user_membership['expiry']) : ?>
                    <small><?php echo sprintf(esc_html__('Expires on: %s', 'folio'), esc_html($user_membership['expiry'])); ?></small>
                    <?php endif; ?>
                </div>
                <?php else : ?>
                <a href="<?php echo esc_url(home_url('/user-center/membership')); ?>" class="membership-btn membership-btn-upgrade">
                    <span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Upgrade to SVIP', 'folio'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 会员对比表 -->
    <div class="membership-comparison">
        <h3><?php esc_html_e('Feature Comparison', 'folio'); ?></h3>
        <div class="comparison-table-wrapper">
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Feature', 'folio'); ?></th>
                        <th><?php esc_html_e('Free', 'folio'); ?></th>
                        <th><?php esc_html_e('VIP', 'folio'); ?></th>
                        <th><?php esc_html_e('SVIP', 'folio'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Basic content browsing', 'folio'); ?></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('VIP-exclusive content', 'folio'); ?></td>
                        <td><span class="dashicons dashicons-no"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('SVIP-exclusive content', 'folio'); ?></td>
                        <td><span class="dashicons dashicons-no"></span></td>
                        <td><span class="dashicons dashicons-no"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Ad-free experience', 'folio'); ?></td>
                        <td><span class="dashicons dashicons-no"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Support', 'folio'); ?></td>
                        <td><?php esc_html_e('Weekdays', 'folio'); ?></td>
                        <td><?php esc_html_e('Priority support', 'folio'); ?></td>
                        <td><?php esc_html_e('24/7 dedicated', 'folio'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Download Access', 'folio'); ?></td>
                        <td><?php esc_html_e('Limited', 'folio'); ?></td>
                        <td><?php esc_html_e('High-speed download', 'folio'); ?></td>
                        <td><?php esc_html_e('Unlimited download', 'folio'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 常见问题 -->
    <div class="membership-faq">
        <h3><?php esc_html_e('Frequently Asked Questions', 'folio'); ?></h3>
        <div class="faq-list">
            <div class="faq-item">
                <h4><?php esc_html_e('How do I upgrade my membership?', 'folio'); ?></h4>
                <p><?php esc_html_e('Click the upgrade button above, choose a suitable plan, and complete payment to activate immediately.', 'folio'); ?></p>
            </div>
            <div class="faq-item">
                <h4><?php esc_html_e('What happens when membership expires?', 'folio'); ?></h4>
                <p><?php esc_html_e('After expiry, your account is downgraded to free automatically, while your data and settings are retained.', 'folio'); ?></p>
            </div>
            <div class="faq-item">
                <h4><?php esc_html_e('Can I cancel membership anytime?', 'folio'); ?></h4>
                <p><?php esc_html_e('Yes. You can manage membership status in user center anytime, and keep benefits until current billing period ends.', 'folio'); ?></p>
            </div>
            <div class="faq-item">
                <h4><?php esc_html_e('Which payment methods are supported?', 'folio'); ?></h4>
                <p><?php esc_html_e('We support multiple payment methods including Alipay, WeChat Pay, and bank cards.', 'folio'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- 样式已移动到 /assets/css/user-interface.css -->

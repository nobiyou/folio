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
        <h2 class="membership-showcase-title">选择适合您的会员方案</h2>
        <p class="membership-showcase-description">升级会员，解锁更多专属内容和功能</p>
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
                    <span class="price-amount">免费</span>
                </div>
            </div>
            
            <div class="membership-card-features">
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>浏览基础内容</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>参与社区讨论</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>基础功能使用</span>
                </div>
            </div>
            
            <div class="membership-card-action">
                <?php if ($user_membership['level'] === 'free') : ?>
                <button class="membership-btn membership-btn-current" disabled>
                    <span class="dashicons dashicons-yes"></span> 当前方案
                </button>
                <?php else : ?>
                <button class="membership-btn membership-btn-downgrade" disabled>
                    基础方案
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
                    <span class="price-duration">/ 月</span>
                </div>
            </div>
            
            <div class="membership-card-features">
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>查看VIP专属内容</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>无广告浏览体验</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>优先客服支持</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>专属会员标识</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>高级功能使用</span>
                </div>
            </div>
            
            <div class="membership-card-action">
                <?php if ($user_membership['level'] === 'vip') : ?>
                <button class="membership-btn membership-btn-current" disabled>
                    <span class="dashicons dashicons-yes"></span> 当前方案
                </button>
                <div class="membership-card-status">
                    <?php if ($user_membership['is_permanent']) : ?>
                    <small>永久会员</small>
                    <?php elseif ($user_membership['expiry']) : ?>
                    <small>到期时间：<?php echo esc_html($user_membership['expiry']); ?></small>
                    <?php endif; ?>
                </div>
                <?php else : ?>
                <a href="<?php echo esc_url(home_url('/user-center/membership')); ?>" class="membership-btn membership-btn-upgrade">
                    <span class="dashicons dashicons-star-filled"></span> 升级VIP
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
                    <span class="price-duration">/ 月</span>
                </div>
            </div>
            
            <div class="membership-card-features">
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>查看所有专属内容</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>无广告浏览体验</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>24小时专属客服</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>专属SVIP标识</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>独家高清资源</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>提前体验新功能</span>
                </div>
                <div class="membership-feature">
                    <span class="dashicons dashicons-yes"></span>
                    <span>专属客服经理</span>
                </div>
            </div>
            
            <div class="membership-card-action">
                <?php if ($user_membership['level'] === 'svip') : ?>
                <button class="membership-btn membership-btn-current" disabled>
                    <span class="dashicons dashicons-yes"></span> 当前方案
                </button>
                <div class="membership-card-status">
                    <?php if ($user_membership['is_permanent']) : ?>
                    <small>永久会员</small>
                    <?php elseif ($user_membership['expiry']) : ?>
                    <small>到期时间：<?php echo esc_html($user_membership['expiry']); ?></small>
                    <?php endif; ?>
                </div>
                <?php else : ?>
                <a href="<?php echo esc_url(home_url('/user-center/membership')); ?>" class="membership-btn membership-btn-upgrade">
                    <span class="dashicons dashicons-star-filled"></span> 升级SVIP
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 会员对比表 -->
    <div class="membership-comparison">
        <h3>功能对比</h3>
        <div class="comparison-table-wrapper">
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th>功能特性</th>
                        <th>免费用户</th>
                        <th>VIP会员</th>
                        <th>SVIP会员</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>基础内容浏览</td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>VIP专属内容</td>
                        <td><span class="dashicons dashicons-no"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>SVIP专属内容</td>
                        <td><span class="dashicons dashicons-no"></span></td>
                        <td><span class="dashicons dashicons-no"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>无广告体验</td>
                        <td><span class="dashicons dashicons-no"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>客服支持</td>
                        <td>工作日</td>
                        <td>优先支持</td>
                        <td>24小时专属</td>
                    </tr>
                    <tr>
                        <td>下载权限</td>
                        <td>限制</td>
                        <td>高速下载</td>
                        <td>无限下载</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 常见问题 -->
    <div class="membership-faq">
        <h3>常见问题</h3>
        <div class="faq-list">
            <div class="faq-item">
                <h4>如何升级会员？</h4>
                <p>点击上方的升级按钮，选择合适的会员方案，完成支付即可立即生效。</p>
            </div>
            <div class="faq-item">
                <h4>会员到期后会怎样？</h4>
                <p>会员到期后将自动降级为免费用户，但您之前的数据和设置都会保留。</p>
            </div>
            <div class="faq-item">
                <h4>可以随时取消会员吗？</h4>
                <p>是的，您可以随时在用户中心管理您的会员状态，取消后在当前周期结束前仍可享受会员权益。</p>
            </div>
            <div class="faq-item">
                <h4>支持哪些支付方式？</h4>
                <p>我们支持支付宝、微信支付、银行卡等多种支付方式，安全便捷。</p>
            </div>
        </div>
    </div>
</div>

<!-- 样式已移动到 /assets/css/user-interface.css -->
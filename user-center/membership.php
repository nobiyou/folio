<?php
/**
 * User Center - Membership
 * 
 * 用户中心会员说明页面
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$user_membership = folio_get_user_membership($user_id);

// 获取会员权益对比数据
if (class_exists('folio_Frontend_Components')) {
    $vip_benefits = folio_Frontend_Components::get_membership_comparison('vip');
    $svip_benefits = folio_Frontend_Components::get_membership_comparison('svip');
    $vip_price = folio_Frontend_Components::get_membership_price('vip');
    $svip_price = folio_Frontend_Components::get_membership_price('svip');
} else {
    // 回退到默认值
    $vip_benefits = array();
    $svip_benefits = array();
    $vip_price = __('¥68/month', 'folio');
    $svip_price = __('¥128/month', 'folio');
}

// 获取支付二维码和说明（从会员系统设置中获取）
$membership_options = get_option('folio_membership_options', array());
$payment_qr_code = isset($membership_options['payment_qr_code']) ? $membership_options['payment_qr_code'] : '';
$payment_instructions = isset($membership_options['payment_instructions']) ? $membership_options['payment_instructions'] : '';
$payment_contact = isset($membership_options['payment_contact']) ? $membership_options['payment_contact'] : '';
?>

<div class="membership-page">
    <h1 class="membership-page-title text-3xl font-bold mb-8">
        <?php esc_html_e('Membership Center', 'folio'); ?>
    </h1>

    <!-- 当前会员状态 -->
    <?php if ($user_membership['is_vip']) : ?>
    <div class="current-membership-card membership-card-<?php echo esc_attr($user_membership['level']); ?> mb-8 p-6 rounded-lg">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <div class="membership-icon-large text-4xl">
                    <?php echo wp_kses_post($user_membership['icon']); ?>
                </div>
                <div>
                    <h2 class="text-2xl font-bold mb-2"><?php echo esc_html($user_membership['name']); ?></h2>
                    <?php if ($user_membership['is_permanent']) : ?>
                    <p class="text-gray-600"><?php esc_html_e('Lifetime Member', 'folio'); ?></p>
                    <?php elseif ($user_membership['days_left'] !== null) : ?>
                    <p class="text-gray-600">
                        <?php 
                        if ($user_membership['days_left'] > 0) {
                            printf(esc_html__('%d days remaining', 'folio'), $user_membership['days_left']);
                        } else {
                            esc_html_e('Expires today', 'folio');
                        }
                        ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else : ?>
    <div class="current-membership-card bg-gray-100 mb-8 p-6 rounded-lg">
        <div class="flex items-center gap-4">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20 text-purple-500">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 20a8 8 0 0116 0"/>
                </svg>
            </div>
            <div>
                <h2 class="text-2xl font-bold mb-2"><?php esc_html_e('Regular User', 'folio'); ?></h2>
                <p class="text-gray-600"><?php esc_html_e('Upgrade to unlock more benefits', 'folio'); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 会员类型标签页 -->
    <div class="membership-tabs-wrapper mb-8">
        <div class="membership-tabs-nav flex border-b border-gray-200 mb-6">
            <button class="membership-tab-btn active px-6 py-4 font-semibold transition-all relative" data-tab="vip">
                <div class="text-left">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-orange-500 text-xl">VIP会员</span>
                        <span class="text-red-500 text-xl"><?php echo esc_html($vip_price); ?></span>
                    </div>
                    <div class="text-gray-600 text-sm font-normal">
                        <?php printf(esc_html__('Enjoy %d premium benefits and unlock more curated albums', 'folio'), count($vip_benefits)); ?>
                    </div>
                </div>
            </button>
            <button class="membership-tab-btn px-6 py-4 font-semibold transition-all relative" data-tab="svip">
                <div class="text-left">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-purple-500 text-xl">SVIP会员</span>
                        <span class="text-red-500 text-xl"><?php echo esc_html($svip_price); ?></span>
                    </div>
                    <div class="text-gray-600 text-sm font-normal">
                        <?php printf(esc_html__('Enjoy %d premium benefits and unlock all exclusive content', 'folio'), count($svip_benefits)); ?>
                    </div>
                </div>
            </button>
        </div>

        <!-- VIP会员标签页内容 -->
        <div class="membership-tab-content active" id="tab-vip">
            <div class="membership-section">
                <!-- VIP权益对比表格 -->
                <div class="membership-comparison-table-wrapper bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                    <table class="membership-comparison-table w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">专属特权</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700">普通用户</th>
                                <th class="px-4 py-3 text-center font-semibold text-orange-500">VIP用户</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($vip_benefits as $benefit) : ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900"><?php echo esc_html($benefit['name']); ?></td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    <?php if ($benefit['normal'] === '×' || $benefit['normal'] === false) : ?>
                                        <span class="text-gray-400">×</span>
                                    <?php else : ?>
                                        <?php echo esc_html($benefit['normal']); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center text-orange-500 font-semibold">
                                    <?php if ($benefit['vip'] === true || $benefit['vip'] === '✓') : ?>
                                        <span class="text-green-500">✓</span>
                                    <?php else : ?>
                                        <?php echo esc_html($benefit['vip']); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- VIP升级按钮 -->
                <div class="membership-pricing-section text-center">
                    <?php if (!$user_membership['is_vip']) : ?>
                        <!-- 普通用户显示升级VIP按钮 -->
                        <a href="#" class="btn-upgrade-vip inline-block px-8 py-3 bg-gradient-to-r from-orange-500 to-orange-600 text-white font-semibold rounded-lg hover:from-orange-600 hover:to-orange-700 transition-all shadow-md hover:shadow-lg">
                            <?php esc_html_e('Upgrade to VIP', 'folio'); ?>
                        </a>
                    <?php elseif ($user_membership['level'] === 'vip' && $user_membership['days_left'] !== null && $user_membership['days_left'] < 7) : ?>
                        <!-- VIP用户且即将到期，显示续费VIP按钮 -->
                        <a href="#" class="btn-renew-vip inline-block px-8 py-3 bg-gradient-to-r from-orange-500 to-orange-600 text-white font-semibold rounded-lg hover:from-orange-600 hover:to-orange-700 transition-all shadow-md hover:shadow-lg">
                            <?php esc_html_e('Renew VIP', 'folio'); ?>
                        </a>
                    <?php elseif ($user_membership['level'] === 'vip') : ?>
                        <!-- VIP用户且未到期，提示已经是VIP，引导升级SVIP -->
                        <div class="text-gray-600 mb-4">
                            <p class="mb-2"><?php esc_html_e('You are already a VIP member', 'folio'); ?></p>
                            <p class="text-sm"><?php esc_html_e('Want more benefits?', 'folio'); ?></p>
                        </div>
                        <a href="#tab-svip" class="btn-upgrade-svip-from-vip inline-block px-8 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white font-semibold rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all shadow-md hover:shadow-lg" onclick="document.querySelector('[data-tab=\"svip\"]').click(); return false;">
                            <?php esc_html_e('Upgrade to SVIP', 'folio'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SVIP会员标签页内容 -->
        <div class="membership-tab-content" id="tab-svip">
            <div class="membership-section">
                <!-- SVIP权益对比表格 -->
                <div class="membership-comparison-table-wrapper bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                    <table class="membership-comparison-table w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">专属特权</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700">普通用户</th>
                                <th class="px-4 py-3 text-center font-semibold text-orange-500">VIP用户</th>
                                <th class="px-4 py-3 text-center font-semibold text-purple-500">SVIP用户</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($svip_benefits as $benefit) : ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900"><?php echo esc_html($benefit['name']); ?></td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    <?php if ($benefit['normal'] === '×' || $benefit['normal'] === false) : ?>
                                        <span class="text-gray-400">×</span>
                                    <?php else : ?>
                                        <?php echo esc_html($benefit['normal']); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center text-orange-500">
                                    <?php if (isset($benefit['vip'])) : ?>
                                        <?php if ($benefit['vip'] === true || $benefit['vip'] === '✓') : ?>
                                            <span class="text-green-500">✓</span>
                                        <?php else : ?>
                                            <?php echo esc_html($benefit['vip']); ?>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-gray-400">×</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center text-purple-500 font-semibold">
                                    <?php if (isset($benefit['svip'])) : ?>
                                        <?php if ($benefit['svip'] === true || $benefit['svip'] === '✓') : ?>
                                            <span class="text-green-500">✓</span>
                                        <?php else : ?>
                                            <?php echo esc_html($benefit['svip']); ?>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-green-500">✓</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- SVIP升级按钮 -->
                <div class="membership-pricing-section text-center">
                    <?php if (!$user_membership['is_svip']) : ?>
                        <!-- 非SVIP用户显示升级SVIP按钮 -->
                        <a href="#" class="btn-upgrade-svip inline-block px-8 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white font-semibold rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all shadow-md hover:shadow-lg">
                            <?php esc_html_e('Upgrade to SVIP', 'folio'); ?>
                        </a>
                    <?php elseif ($user_membership['level'] === 'svip' && $user_membership['days_left'] !== null && $user_membership['days_left'] < 7) : ?>
                        <!-- SVIP用户且即将到期，显示续费SVIP按钮 -->
                        <a href="#" class="btn-renew-svip inline-block px-8 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white font-semibold rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all shadow-md hover:shadow-lg">
                            <?php esc_html_e('Renew SVIP', 'folio'); ?>
                        </a>
                    <?php else : ?>
                        <!-- SVIP用户且未到期，提示已经是SVIP -->
                        <div class="text-gray-600">
                            <p><?php esc_html_e('You are already an SVIP member and enjoy all benefits!', 'folio'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 支付信息 -->
    <div class="membership-payment-section mt-12">
        <h2 class="text-2xl font-bold mb-6"><?php esc_html_e('Payment Methods', 'folio'); ?></h2>
        <div class="payment-content-wrapper bg-white rounded-lg shadow-sm p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- 支付二维码 -->
                <div class="payment-qr-section">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800"><?php esc_html_e('Scan to Pay', 'folio'); ?></h3>
                    <?php if ($payment_qr_code) : ?>
                        <div class="qr-code-wrapper mb-4">
                            <img src="<?php echo esc_url($payment_qr_code); ?>" 
                                 alt="<?php esc_attr_e('Payment QR Code', 'folio'); ?>" 
                                 class="payment-qr-code max-w-xl mx-auto border border-gray-200 rounded-lg p-2 bg-white">
                        </div>
                    <?php else : ?>
                        <div class="qr-code-placeholder bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                            <div class="text-gray-400 mb-2">
                                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                            </div>
                            <p class="text-gray-500 text-sm"><?php esc_html_e('Please ask the administrator to configure a payment QR code in backend.', 'folio'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 支付说明 -->
                <div class="payment-instructions-section">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800"><?php esc_html_e('Payment Instructions', 'folio'); ?></h3>
                    <div class="payment-instructions-content text-gray-600 space-y-3">
                        <?php if ($payment_instructions) : ?>
                            <div class="instructions-text">
                                <?php echo wp_kses_post(nl2br($payment_instructions)); ?>
                            </div>
                        <?php else : ?>
                            <div class="default-instructions space-y-3">
                                <div class="instruction-item">
                                    <p class="mb-2"><strong><?php esc_html_e('Payment Steps:', 'folio'); ?></strong></p>
                                    <ol class="list-decimal list-inside space-y-1 text-sm">
                                        <li><?php esc_html_e('Select the membership type you want to purchase (VIP or SVIP)', 'folio'); ?></li>
                                        <li><?php esc_html_e('Click the "Upgrade VIP" or "Upgrade SVIP" button', 'folio'); ?></li>
                                        <li><?php esc_html_e('Use WeChat Pay or Alipay to scan the QR code above to complete payment', 'folio'); ?></li>
                                        <li><?php esc_html_e('After payment, please save a screenshot of your receipt', 'folio'); ?></li>
                                        <li><?php esc_html_e('Contact support or send your receipt, and we will activate your membership within 24 hours', 'folio'); ?></li>
                                    </ol>
                                </div>
                                <div class="instruction-item">
                                    <p class="mb-2"><strong><?php esc_html_e('Notes:', 'folio'); ?></strong></p>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li><?php esc_html_e('Please ensure the payment amount matches your selected membership type', 'folio'); ?></li>
                                        <li><?php esc_html_e('Please keep your payment receipt for verification', 'folio'); ?></li>
                                        <li><?php esc_html_e('If you have any questions, please contact support', 'folio'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($payment_contact) : ?>
                            <div class="payment-contact mt-4 pt-4 border-t border-gray-200">
                                <p class="text-sm">
                                    <strong><?php esc_html_e('Contact:', 'folio'); ?></strong>
                                    <span class="text-gray-700"><?php echo esc_html($payment_contact); ?></span>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 常见问题 -->
    <div class="membership-faq-section mt-12">
        <h2 class="text-2xl font-bold mb-6"><?php esc_html_e('FAQ', 'folio'); ?></h2>
        <div class="space-y-4">
            <div class="faq-item bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold mb-2"><?php esc_html_e('When do membership benefits take effect?', 'folio'); ?></h3>
                <p class="text-gray-600 text-sm"><?php esc_html_e('After upgrading, all benefits take effect immediately and you can access exclusive content right away.', 'folio'); ?></p>
            </div>
            <div class="faq-item bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold mb-2"><?php esc_html_e('What happens after membership expires?', 'folio'); ?></h3>
                <p class="text-gray-600 text-sm"><?php esc_html_e('After expiry, you can no longer access exclusive content. You can renew or upgrade anytime.', 'folio'); ?></p>
            </div>
            <div class="faq-item bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold mb-2"><?php esc_html_e('How do I upgrade my membership?', 'folio'); ?></h3>
                <p class="text-gray-600 text-sm"><?php esc_html_e('Click the upgrade button above and complete payment as instructed.', 'folio'); ?></p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // 标签页切换功能
    const tabButtons = document.querySelectorAll('.membership-tab-btn');
    const tabContents = document.querySelectorAll('.membership-tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // 移除所有活动状态
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // 添加当前活动状态
            this.classList.add('active');
            document.getElementById('tab-' + targetTab).classList.add('active');
        });
    });
})();
</script>


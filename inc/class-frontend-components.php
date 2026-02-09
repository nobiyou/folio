<?php
/**
 * Frontend Components Manager
 * 
 * 前端权限提示组件管理器 - 处理各种权限状态的UI组件
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Frontend_Components {

    // 组件类型常量
    const COMPONENT_PERMISSION_PROMPT = 'permission_prompt';
    const COMPONENT_MEMBERSHIP_BADGE = 'membership_badge';
    const COMPONENT_UPGRADE_BUTTON = 'upgrade_button';
    const COMPONENT_CONTENT_MASK = 'content_mask';

    // 状态常量
    const STATE_LOGGED_OUT = 'logged_out';
    const STATE_INSUFFICIENT_LEVEL = 'insufficient_level';
    const STATE_ACCESS_GRANTED = 'access_granted';

    public function __construct() {
        // 添加前端脚本和样式
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // AJAX处理器
        add_action('wp_ajax_folio_get_permission_status', array($this, 'ajax_get_permission_status'));
        add_action('wp_ajax_nopriv_folio_get_permission_status', array($this, 'ajax_get_permission_status'));
        add_action('wp_ajax_folio_check_user_access_article', array($this, 'ajax_check_article_permission'));
        add_action('wp_ajax_nopriv_folio_check_user_access_article', array($this, 'ajax_check_article_permission'));
        
        // 前端样式已移动到独立的CSS文件中
        
        // 在文章内容中自动插入组件
        add_filter('the_content', array($this, 'auto_insert_components'), 15);
        
        // 短代码支持
        add_shortcode('folio_permission_prompt', array($this, 'permission_prompt_shortcode'));
        add_shortcode('folio_membership_badge', array($this, 'membership_badge_shortcode'));
        add_shortcode('folio_upgrade_button', array($this, 'upgrade_button_shortcode'));
    }

    /**
     * 渲染会员徽章组件 - 增强版本
     */
    public static function render_membership_badge($post_id, $context = 'list', $options = array()) {
        // 检查文章保护信息
        $is_protected = get_post_meta($post_id, '_folio_premium_content', true);
        if (!$is_protected) {
            return '';
        }

        $required_level = get_post_meta($post_id, '_folio_required_level', true) ?: 'vip';
        $user_id = get_current_user_id();
        
        // 检查用户访问权限
        $can_access = false;
        $user_membership = null;
        if ($user_id) {
            // 管理员始终有权限
            if (current_user_can('manage_options')) {
                $can_access = true;
            } else {
                // 检查用户会员等级
                $user_membership = folio_get_user_membership($user_id);
                if ($user_membership) {
                    if ($required_level === 'vip' && in_array($user_membership['level'], ['vip', 'svip'])) {
                        $can_access = true;
                    } elseif ($required_level === 'svip' && $user_membership['level'] === 'svip') {
                        $can_access = true;
                    }
                }
            }
        }
        
        $level_name = $required_level === 'svip' ? 'SVIP' : 'VIP';
        
        // 默认选项
        $defaults = array(
            'show_tooltip' => true,
            'show_status' => true,
            'size' => 'normal', // small, normal, large
            'style' => 'default', // default, minimal, outline, gradient
            'show_animation' => true,
            'show_user_status' => true
        );
        $options = wp_parse_args($options, $defaults);
        
        $badge_classes = array(
            'folio-membership-badge',
            'folio-badge-' . $required_level,
            'folio-badge-' . $context,
            'folio-badge-size-' . $options['size'],
            'folio-badge-style-' . $options['style']
        );
        
        // 根据访问权限设置状态和样式
        if ($can_access) {
            $badge_classes[] = 'folio-badge-unlocked';
            $badge_text = $level_name . ' 已解锁';
            $status_text = '已解锁';
            $tooltip_text = '您可以查看此' . $level_name . '专属内容';
            $icon = '<svg class="folio-badge-icon folio-icon-unlocked" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
        } else {
            $badge_classes[] = 'folio-badge-locked';
            if ($user_id && $user_membership) {
                $current_level_name = $user_membership['name'];
                $badge_text = '需要' . $level_name;
                $status_text = '需要升级';
                $tooltip_text = '当前等级：' . $current_level_name . '，需要升级到' . $level_name . '等级查看';
            } else {
                $badge_text = '需要' . $level_name;
                $status_text = '需要登录';
                $tooltip_text = '请登录并升级到' . $level_name . '等级查看此专属内容';
            }
            $icon = '<svg class="folio-badge-icon folio-icon-locked" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>';
        }
        
        // 增强的工具提示内容
        $enhanced_tooltip = $tooltip_text;
        if ($options['show_tooltip']) {
            if (!$can_access) {
                $benefits = self::get_level_benefits_summary($required_level);
                $enhanced_tooltip .= "\n\n" . $level_name . "会员权益：" . $benefits;
                
                // 添加升级提示
                if ($user_id) {
                    $enhanced_tooltip .= "\n\n点击升级到" . $level_name . "会员";
                } else {
                    $enhanced_tooltip .= "\n\n点击登录并升级会员";
                }
            } else {
                $enhanced_tooltip .= "\n\n感谢您的支持！";
            }
        }
        
        // 添加动画类
        if ($options['show_animation']) {
            $badge_classes[] = 'folio-badge-animated';
        }
        
        // 添加交互类
        $badge_classes[] = 'folio-badge-interactive';
        
        ob_start();
        ?>
        <span class="<?php echo esc_attr(implode(' ', $badge_classes)); ?>" 
              data-post-id="<?php echo esc_attr($post_id); ?>"
              data-level="<?php echo esc_attr($required_level); ?>"
              data-can-access="<?php echo $can_access ? 'true' : 'false'; ?>"
              data-user-logged-in="<?php echo $user_id ? 'true' : 'false'; ?>"
              <?php if ($user_membership) : ?>
              data-user-level="<?php echo esc_attr($user_membership['level']); ?>"
              <?php endif; ?>
              <?php if ($options['show_tooltip']) : ?>
              title="<?php echo esc_attr($enhanced_tooltip); ?>"
              data-tooltip="<?php echo esc_attr($enhanced_tooltip); ?>"
              <?php endif; ?>
              role="img"
              aria-label="<?php echo esc_attr($badge_text . '，' . $tooltip_text); ?>">
            
            <span class="folio-badge-icon-wrapper">
                <?php echo $icon; ?>
            </span>
            
            <?php if ($options['show_status']) : ?>
            <span class="folio-badge-content">
                <span class="folio-badge-level"><?php echo esc_html($level_name); ?></span>
                <?php if ($options['show_user_status']) : ?>
                <span class="folio-badge-status"><?php echo esc_html($status_text); ?></span>
                <?php endif; ?>
            </span>
            <?php endif; ?>
            
            <!-- 状态指示器 -->
            <span class="folio-badge-indicator" aria-hidden="true"></span>
            
            <!-- 悬停效果层 -->
            <span class="folio-badge-hover-effect" aria-hidden="true"></span>
        </span>
        <?php
        return ob_get_clean();
    }

    /**
     * 渲染权限提示组件 - 新设计（参考移动端VIP升级页面）
     */
    public static function render_permission_prompt($post_id, $options = array()) {
        $protection_info = folio_get_article_protection_info($post_id);
        
        if (!$protection_info['is_protected']) {
            return '';
        }

        $user_id = get_current_user_id();
        $can_access = folio_can_user_access_article($post_id, $user_id);
        
        if ($can_access) {
            return ''; // 有权限的用户不显示提示
        }

        $required_level = $protection_info['required_level'];
        $level_name = $required_level === 'svip' ? 'SVIP' : 'VIP';
        
        // 默认选项
        $defaults = array(
            'style' => 'default', // default, compact, banner
            'show_preview' => true,
            'show_benefits' => true,
            'show_comparison' => true, // 显示对比表格
            'custom_title' => '',
            'custom_message' => '',
            'upgrade_url' => home_url('/user-center/membership'),
            'login_url' => home_url('/user-center/login'),
            'register_url' => home_url('/user-center/register')
        );
        $options = wp_parse_args($options, $defaults);
        
        // 确定用户状态
        if (!$user_id) {
            $user_state = self::STATE_LOGGED_OUT;
            $user_membership = null;
        } else {
            $user_state = self::STATE_INSUFFICIENT_LEVEL;
            $user_membership = folio_get_user_membership($user_id);
        }
        
        // 获取会员权益对比数据
        $benefits_comparison = self::get_membership_comparison($required_level);
        
        $prompt_classes = array(
            'folio-permission-prompt',
            'folio-prompt-' . $required_level,
            'folio-prompt-style-' . $options['style'],
            'folio-prompt-state-' . $user_state,
            'folio-prompt-modern' // 新设计样式
        );
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $prompt_classes)); ?>" 
             data-post-id="<?php echo esc_attr($post_id); ?>"
             data-level="<?php echo esc_attr($required_level); ?>"
             data-state="<?php echo esc_attr($user_state); ?>">
            
            <!-- 顶部标题 -->
            <div class="folio-prompt-modern-header">
                <h3 class="folio-prompt-modern-title">
                    <?php echo $options['custom_title'] ? esc_html($options['custom_title']) : '升级' . esc_html($level_name) . ' 解锁专属内容'; ?>
                </h3>
            </div>
            
            <!-- VIP会员信息卡片 -->
            <div class="folio-prompt-vip-card folio-vip-card-<?php echo esc_attr($required_level); ?>">
                <div class="folio-vip-card-content-wrapper">
                    <div class="folio-vip-card-left">
                        <div class="folio-vip-card-header">
                            <h4 class="folio-vip-card-title"><?php echo esc_html($level_name); ?>会员</h4>
                        </div>
                        <div class="folio-vip-card-content">
                            <p class="folio-vip-card-description">
                                尊享<?php echo count($benefits_comparison); ?>大特权，解锁更多精品相册
                            </p>
                        </div>
                    </div>
                    <div class="folio-vip-card-right">
                        <div class="folio-vip-card-pricing">
                            <div class="folio-vip-price-label"><?php echo esc_html($level_name); ?>会员</div>
                            <div class="folio-vip-price-amount"><?php echo self::get_membership_price($required_level); ?></div>
                        </div>
                        <div class="folio-vip-card-action">
                            <?php if ($user_state === self::STATE_LOGGED_OUT) : ?>
                                <a href="<?php echo esc_url($options['login_url']); ?>" 
                                   class="folio-btn-vip-action folio-btn-vip-login">
                                    开通权限
                                </a>
                            <?php else : ?>
                                <a href="<?php echo esc_url($options['upgrade_url']); ?>" 
                                   class="folio-btn-vip-action folio-btn-vip-upgrade">
                                    升级权限
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- VIP专属特权对比表格 -->
            <?php if ($options['show_comparison'] && !empty($benefits_comparison)) : ?>
            <div class="folio-prompt-comparison-section">
                <h5 class="folio-comparison-title"><?php echo esc_html($level_name); ?>专属特权</h5>
                <div class="folio-comparison-table-wrapper">
                    <table class="folio-comparison-table">
                        <thead>
                            <tr>
                                <th class="folio-col-feature">专属特权</th>
                                <th class="folio-col-normal">普通用户</th>
                                <?php if ($required_level === 'svip') : ?>
                                <th class="folio-col-vip">VIP用户</th>
                                <th class="folio-col-svip">SVIP用户</th>
                                <?php else : ?>
                                <th class="folio-col-vip">VIP用户</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($benefits_comparison as $benefit) : ?>
                            <tr>
                                <td class="folio-col-feature">
                                    <span class="folio-feature-name"><?php echo esc_html($benefit['name']); ?></span>
                                </td>
                                <td class="folio-col-normal">
                                    <?php if (isset($benefit['normal'])) : ?>
                                        <?php if ($benefit['normal'] === 'X' || $benefit['normal'] === false) : ?>
                                            <span class="folio-not-available">×</span>
                                        <?php else : ?>
                                            <span class="folio-normal-value"><?php echo esc_html($benefit['normal']); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="folio-not-available">×</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($required_level === 'svip') : ?>
                                <!-- SVIP显示三列：普通、VIP、SVIP -->
                                <td class="folio-col-vip">
                                    <?php if (isset($benefit['vip'])) : ?>
                                        <?php if ($benefit['vip'] === true || $benefit['vip'] === '✓') : ?>
                                            <span class="folio-available">✓</span>
                                        <?php else : ?>
                                            <span class="folio-vip-value"><?php echo esc_html($benefit['vip']); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="folio-not-available">×</span>
                                    <?php endif; ?>
                                </td>
                                <td class="folio-col-svip">
                                    <?php if (isset($benefit['svip'])) : ?>
                                        <?php if ($benefit['svip'] === true || $benefit['svip'] === '✓') : ?>
                                            <span class="folio-available">✓</span>
                                        <?php else : ?>
                                            <span class="folio-svip-value"><?php echo esc_html($benefit['svip']); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="folio-available">✓</span>
                                    <?php endif; ?>
                                </td>
                                <?php else : ?>
                                <!-- VIP只显示两列：普通、VIP -->
                                <td class="folio-col-vip">
                                    <?php if (isset($benefit['vip'])) : ?>
                                        <?php if ($benefit['vip'] === true || $benefit['vip'] === '✓') : ?>
                                            <span class="folio-available">✓</span>
                                        <?php else : ?>
                                            <span class="folio-vip-value"><?php echo esc_html($benefit['vip']); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="folio-available">✓</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 底部状态信息（仅已登录用户显示） -->
            <?php if ($user_state !== self::STATE_LOGGED_OUT && $user_membership && $user_membership['level'] !== 'free') : ?>
            <div class="folio-prompt-modern-footer">
                <div class="folio-current-status-modern">
                    <span class="folio-status-text">当前等级：<?php echo esc_html($user_membership['name']); ?></span>
                    <?php if ($user_membership['level'] !== 'free') : ?>
                    <span class="folio-current-badge-modern"><?php echo wp_kses_post($user_membership['icon']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 获取会员权益对比数据
     * 优先从设置中读取，如果没有则使用默认值
     */
    public static function get_membership_comparison($level) {
        // 从会员系统设置中读取
        $options = get_option('folio_membership_options', array());
        
        if (isset($options['benefits_comparison'][$level]) && 
            is_array($options['benefits_comparison'][$level]) && 
            !empty($options['benefits_comparison'][$level])) {
            return $options['benefits_comparison'][$level];
        }
        
        // 如果没有设置，使用默认值
        return self::get_default_membership_comparison($level);
    }
    
    /**
     * 获取默认会员权益对比数据
     */
    private static function get_default_membership_comparison($level) {
        $comparisons = array(
            'vip' => array(
                array('name' => '查看VIP专属内容', 'normal' => '×', 'vip' => '✓'),
                array('name' => '无广告浏览体验', 'normal' => '有广告', 'vip' => '✓'),
                array('name' => '优先客服支持', 'normal' => '普通排队', 'vip' => '✓'),
                array('name' => '专属会员标识', 'normal' => '×', 'vip' => '✓'),
                array('name' => '高清图片下载', 'normal' => '限制下载', 'vip' => '✓'),
                array('name' => '文章收藏功能', 'normal' => '×', 'vip' => '✓'),
                array('name' => '评论优先显示', 'normal' => '普通排序', 'vip' => '✓'),
                array('name' => '专属内容推送', 'normal' => '×', 'vip' => '✓'),
                array('name' => '会员专属活动', 'normal' => '×', 'vip' => '✓')
            ),
            'svip' => array(
                array('name' => '查看所有专属内容', 'normal' => '×', 'vip' => '部分内容', 'svip' => '✓'),
                array('name' => '无广告浏览体验', 'normal' => '有广告', 'vip' => '✓', 'svip' => '✓'),
                array('name' => '24小时专属客服', 'normal' => '工作时间', 'vip' => '优先支持', 'svip' => '✓'),
                array('name' => '专属SVIP标识', 'normal' => '×', 'vip' => 'VIP标识', 'svip' => '✓'),
                array('name' => '独家高清资源', 'normal' => '×', 'vip' => '标准资源', 'svip' => '✓'),
                array('name' => '提前体验新功能', 'normal' => '×', 'vip' => '×', 'svip' => '✓'),
                array('name' => '无限下载权限', 'normal' => '限制下载', 'vip' => '有限下载', 'svip' => '✓'),
                array('name' => '专属内容定制', 'normal' => '×', 'vip' => '×', 'svip' => '✓'),
                array('name' => 'SVIP专属活动', 'normal' => '×', 'vip' => '部分活动', 'svip' => '✓')
            )
        );
        
        return isset($comparisons[$level]) ? $comparisons[$level] : $comparisons['vip'];
    }
    
    /**
     * 获取会员价格
     * 优先从设置中读取，如果没有则使用默认值
     */
    public static function get_membership_price($level) {
        // 从会员系统设置中读取
        $options = get_option('folio_membership_options', array());
        
        if (isset($options['membership_prices'][$level]) && !empty($options['membership_prices'][$level])) {
            return $options['membership_prices'][$level];
        }
        
        // 如果没有设置，使用默认值
        $default_prices = array(
            'vip' => '¥68/月',
            'svip' => '¥128/月'
        );
        
        return isset($default_prices[$level]) ? $default_prices[$level] : $default_prices['vip'];
    }

    /**
     * 渲染升级按钮组件
     */
    public static function render_upgrade_button($required_level, $options = array()) {
        $level_name = $required_level === 'svip' ? 'SVIP' : 'VIP';
        
        // 默认选项
        $defaults = array(
            'text' => '升级' . $level_name,
            'size' => 'normal', // small, normal, large
            'style' => 'gradient', // gradient, solid, outline
            'icon' => true,
            'url' => home_url('/user-center/membership'),
            'class' => ''
        );
        $options = wp_parse_args($options, $defaults);
        
        $button_classes = array(
            'folio-upgrade-btn',
            'folio-btn',
            'folio-btn-' . $required_level,
            'folio-btn-size-' . $options['size'],
            'folio-btn-style-' . $options['style']
        );
        
        if ($options['class']) {
            $button_classes[] = $options['class'];
        }
        
        ob_start();
        ?>
        <a href="<?php echo esc_url($options['url']); ?>" 
           class="<?php echo esc_attr(implode(' ', $button_classes)); ?>"
           data-level="<?php echo esc_attr($required_level); ?>">
            <?php if ($options['icon']) : ?>
            <span class="dashicons dashicons-star-filled"></span>
            <?php endif; ?>
            <span><?php echo esc_html($options['text']); ?></span>
        </a>
        <?php
        return ob_get_clean();
    }

    /**
     * 渲染内容遮罩组件
     */
    public static function render_content_mask($preview_content, $settings, $options = array()) {
        // 默认选项
        $defaults = array(
            'fade_height' => '60px',
            'fade_style' => 'gradient', // gradient, solid, blur
            'show_preview' => true,
            'mask_class' => ''
        );
        $options = wp_parse_args($options, $defaults);
        
        if (!$options['show_preview'] || empty($preview_content)) {
            return '';
        }
        
        $mask_classes = array(
            'folio-content-mask',
            'folio-mask-' . $options['fade_style']
        );
        
        if ($options['mask_class']) {
            $mask_classes[] = $options['mask_class'];
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $mask_classes)); ?>">
            <div class="folio-preview-content">
                <?php echo wp_kses_post($preview_content); ?>
            </div>
            <div class="folio-content-fade" 
                 style="height: <?php echo esc_attr($options['fade_height']); ?>;">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 获取会员等级权益列表
     */
    private static function get_membership_benefits($level) {
        $benefits = array(
            'vip' => array(
                '查看VIP专属内容',
                '无广告浏览体验',
                '优先客服支持',
                '专属会员标识'
            ),
            'svip' => array(
                '查看所有专属内容',
                '无广告浏览体验',
                '24小时专属客服',
                '专属SVIP标识',
                '独家高清资源',
                '提前体验新功能'
            )
        );
        
        $level_benefits = isset($benefits[$level]) ? $benefits[$level] : $benefits['vip'];
        
        $output = '';
        foreach ($level_benefits as $benefit) {
            $output .= '<li><span class="folio-benefit-icon">✓</span>' . esc_html($benefit) . '</li>';
        }
        
        return $output;
    }

    /**
     * 获取会员等级权益摘要（用于工具提示）
     */
    private static function get_level_benefits_summary($level) {
        $summaries = array(
            'vip' => '专属内容、无广告、优先支持',
            'svip' => '全部内容、24h客服、独家资源'
        );
        
        return isset($summaries[$level]) ? $summaries[$level] : $summaries['vip'];
    }

    /**
     * 自动在文章内容中插入组件
     */
    public function auto_insert_components($content) {
        if (!is_singular('post') || is_admin()) {
            return $content;
        }
        
        global $post;
        if (!$post) {
            return $content;
        }
        
        $protection_info = folio_get_article_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $content;
        }
        
        $user_id = get_current_user_id();
        if (folio_can_user_access_article($post->ID, $user_id)) {
            return $content;
        }
        
        // 生成预览内容和权限提示
        $preview_content = '';
        if ($protection_info['preview_mode'] !== 'none') {
            $preview_content = folio_generate_article_preview($content, $protection_info);
        }
        
        $content_mask = self::render_content_mask($preview_content, $protection_info);
        $permission_prompt = self::render_permission_prompt($post->ID);
        
        return $content_mask . $permission_prompt;
    }

    /**
     * 权限提示短代码
     */
    public function permission_prompt_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'style' => 'default',
            'show_benefits' => 'true',
            'title' => '',
            'message' => ''
        ), $atts);
        
        $options = array(
            'style' => $atts['style'],
            'show_benefits' => $atts['show_benefits'] === 'true',
            'custom_title' => $atts['title'],
            'custom_message' => $atts['message']
        );
        
        return self::render_permission_prompt(intval($atts['post_id']), $options);
    }

    /**
     * 会员徽章短代码
     */
    public function membership_badge_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'context' => 'inline',
            'size' => 'normal',
            'style' => 'default',
            'show_tooltip' => 'true',
            'show_status' => 'true'
        ), $atts);
        
        $options = array(
            'size' => $atts['size'],
            'style' => $atts['style'],
            'show_tooltip' => $atts['show_tooltip'] === 'true',
            'show_status' => $atts['show_status'] === 'true'
        );
        
        return self::render_membership_badge(intval($atts['post_id']), $atts['context'], $options);
    }

    /**
     * 升级按钮短代码
     */
    public function upgrade_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'level' => 'vip',
            'text' => '',
            'size' => 'normal',
            'style' => 'gradient',
            'icon' => 'true',
            'url' => '',
            'class' => ''
        ), $atts);
        
        $options = array(
            'text' => $atts['text'] ?: '升级' . ($atts['level'] === 'svip' ? 'SVIP' : 'VIP'),
            'size' => $atts['size'],
            'style' => $atts['style'],
            'icon' => $atts['icon'] === 'true',
            'url' => $atts['url'] ?: home_url('/user-center/membership'),
            'class' => $atts['class']
        );
        
        return self::render_upgrade_button($atts['level'], $options);
    }

    /**
     * AJAX获取权限状态
     */
    public function ajax_get_permission_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'folio_frontend_components')) {
            wp_send_json_error(array('message' => '安全验证失败'));
        }
        
        $post_id = intval($_POST['post_id']);
        $user_id = get_current_user_id();
        
        // 使用新的保护管理器方法
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post_id);
        $can_access = folio_Article_Protection_Manager::can_user_access($post_id, $user_id);
        
        $response = array(
            'can_access' => $can_access,
            'is_protected' => $protection_info['is_protected'],
            'required_level' => $protection_info['required_level'],
            'user_logged_in' => $user_id > 0,
            'timestamp' => time(),
            'post_id' => $post_id
        );
        
        if ($user_id) {
            $user_membership = folio_get_user_membership($user_id);
            $response['user_level'] = $user_membership['level'];
            $response['user_level_name'] = $user_membership['name'];
            $response['user_id'] = $user_id;
            
            // 添加会员到期信息（如果有的话）
            if (isset($user_membership['expires_at']) && $user_membership['expires_at']) {
                $response['membership_expires'] = $user_membership['expires_at'];
                $response['days_remaining'] = max(0, ceil(($user_membership['expires_at'] - time()) / DAY_IN_SECONDS));
            }
        }
        
        // 添加状态变更检测
        $last_check_key = 'folio_last_status_' . $post_id . '_' . $user_id;
        $last_status = get_transient($last_check_key);
        
        if ($last_status !== false && $last_status !== $can_access) {
            $response['status_changed'] = true;
            $response['previous_status'] = $last_status;
        }
        
        // 更新状态缓存
        set_transient($last_check_key, $can_access, 300); // 5分钟缓存
        
        // 记录访问日志（用于统计分析）
        $this->log_permission_check($post_id, $user_id, $can_access);
        
        wp_send_json_success($response);
    }

    /**
     * AJAX检查文章权限 - 专门用于权限提示的实时更新
     */
    public function ajax_check_article_permission() {
        // 验证nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'folio_frontend_components')) {
            wp_send_json_error(array('message' => '安全验证失败'));
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error(array('message' => '无效的文章ID'));
            return;
        }
        
        $user_id = get_current_user_id();
        
        // 使用文章保护管理器检查权限
        if (class_exists('folio_Article_Protection_Manager')) {
            $protection_info = folio_Article_Protection_Manager::get_protection_info($post_id);
            $can_access = folio_Article_Protection_Manager::can_user_access($post_id, $user_id);
        } else {
            // 向后兼容
            $protection_info = folio_get_article_protection_info($post_id);
            $can_access = folio_can_user_access_article($post_id, $user_id);
        }
        
        $response = array(
            'can_access' => $can_access,
            'is_protected' => $protection_info['is_protected'],
            'required_level' => $protection_info['required_level'],
            'user_logged_in' => $user_id > 0,
            'post_id' => $post_id,
            'timestamp' => time()
        );
        
        // 如果用户已登录，添加用户信息
        if ($user_id) {
            $user_membership = folio_get_user_membership($user_id);
            if ($user_membership) {
                $response['user_level'] = $user_membership['level'];
                $response['user_level_name'] = $user_membership['name'];
                $response['user_is_vip'] = $user_membership['is_vip'];
                $response['user_is_svip'] = $user_membership['is_svip'];
            }
        }
        
        // 添加调试信息（仅在开发模式下）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $response['debug'] = array(
                'user_id' => $user_id,
                'protection_info' => $protection_info,
                'user_membership' => $user_id ? folio_get_user_membership($user_id) : null,
                'check_time' => current_time('mysql')
            );
        }
        
        wp_send_json_success($response);
    }

    /**
     * 记录权限检查日志
     */
    private function log_permission_check($post_id, $user_id, $can_access) {
        // 简单的日志记录，可以扩展为更详细的统计
        $log_key = 'folio_permission_log_' . date('Y-m-d');
        $log_data = get_option($log_key, array());
        
        $log_entry = array(
            'timestamp' => time(),
            'post_id' => $post_id,
            'user_id' => $user_id,
            'can_access' => $can_access,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        $log_data[] = $log_entry;
        
        // 只保留最近100条记录
        if (count($log_data) > 100) {
            $log_data = array_slice($log_data, -100);
        }
        
        update_option($log_key, $log_data);
    }

    /**
     * 加载前端资源
     */
    public function enqueue_frontend_assets() {
        if (is_singular('post')) {
            // 加载前端组件CSS
            wp_enqueue_style(
                'folio-frontend-components',
                get_template_directory_uri() . '/assets/css/frontend-components.css',
                array(),
                '1.0.0'
            );
            
            // 加载前端组件JavaScript
            wp_enqueue_script(
                'folio-frontend-components',
                get_template_directory_uri() . '/assets/js/frontend-components.js',
                array('jquery'),
                '1.0.0',
                true
            );
            
            wp_localize_script('folio-frontend-components', 'folioComponents', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('folio_frontend_components'),
                'strings' => array(
                    'loading' => '加载中...',
                    'error' => '操作失败，请重试',
                    'success' => '操作成功',
                    'login_required' => '请先登录',
                    'upgrade_required' => '需要升级会员'
                ),
                'urls' => array(
                    'login' => home_url('/user-center/login'),
                    'register' => home_url('/user-center/register'),
                    'upgrade' => home_url('/user-center/membership')
                )
            ));
        }
    }


}

// 初始化前端组件管理器
new folio_Frontend_Components();

/**
 * 全局辅助函数
 */

if (!function_exists('folio_render_membership_badge')) {
    function folio_render_membership_badge($post_id, $context = 'list', $options = array()) {
        return folio_Frontend_Components::render_membership_badge($post_id, $context, $options);
    }
}

if (!function_exists('folio_render_permission_prompt')) {
    function folio_render_permission_prompt($post_id, $options = array()) {
        return folio_Frontend_Components::render_permission_prompt($post_id, $options);
    }
}

if (!function_exists('folio_render_upgrade_button')) {
    function folio_render_upgrade_button($required_level, $options = array()) {
        return folio_Frontend_Components::render_upgrade_button($required_level, $options);
    }
}

if (!function_exists('folio_render_content_mask')) {
    function folio_render_content_mask($preview_content, $settings, $options = array()) {
        return folio_Frontend_Components::render_content_mask($preview_content, $settings, $options);
    }
}
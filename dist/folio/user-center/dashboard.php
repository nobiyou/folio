<?php
/**
 * User Center - Dashboard
 * 
 * 用户中心仪表盘
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$favorites = folio_get_user_favorites($user_id, 6);
$favorite_count = count(folio_get_user_favorites($user_id));
$membership = folio_get_user_membership($user_id);
?>

<div class="dashboard">
    <h1 class="dashboard-title text-2xl font-bold mb-6">
        <?php printf(esc_html__('欢迎回来，%s', 'folio'), wp_get_current_user()->display_name); ?>
        <?php if ($membership['is_vip']) : ?>
        <span class="membership-badge membership-badges-<?php echo esc_attr($membership['level']); ?>">
            <?php echo wp_kses_post($membership['icon']); ?> <?php echo esc_html($membership['name']); ?>
        </span>
        <?php endif; ?>
    </h1>

    <!-- 会员状态卡片（仅VIP显示） -->
    <?php if ($membership['is_vip']) : ?>
    <div class="membership-card membership-card-<?php echo esc_attr($membership['level']); ?> mb-8">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <div class="membership-icon inline-flex items-center"><?php echo wp_kses_post($membership['icon']); ?></div>
                <div>
                    <h3 class="membership-card-title text-xl font-bold"><?php echo esc_html($membership['name']); ?></h3>
                    <?php if ($membership['is_permanent']) : ?>
                    <p class="membership-card-subtitle"><?php esc_html_e('永久会员', 'folio'); ?></p>
                    <?php elseif ($membership['days_left'] !== null) : ?>
                    <p class="membership-card-subtitle">
                        <?php 
                        if ($membership['days_left'] > 0) {
                            printf(esc_html__('剩余 %d 天到期', 'folio'), $membership['days_left']);
                        } else {
                            esc_html_e('今日到期', 'folio');
                        }
                        ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="membership-benefits">
                <span class="membership-benefits-label text-sm"><?php esc_html_e('会员特权', 'folio'); ?></span>
                <div class="flex gap-2 mt-1">
                    <span class="benefit-tag">✓ <?php esc_html_e('专属标识', 'folio'); ?></span>
                    <?php if ($membership['is_svip']) : ?>
                    <span class="benefit-tag">✓ <?php esc_html_e('优先支持', 'folio'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 统计卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </div>
            <div>
                <div class="stat-number"><?php echo esc_html($favorite_count); ?></div>
                <div class="stat-label"><?php esc_html_e('收藏作品', 'folio'); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="stat-number"><?php echo esc_html(human_time_diff(strtotime(wp_get_current_user()->user_registered))); ?></div>
                <div class="stat-label"><?php esc_html_e('加入时间', 'folio'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-card-membership <?php echo $membership['is_vip'] ? 'stat-card-' . esc_attr($membership['level']) : ''; ?>">
            <div class="stat-icon stat-icon-membership <?php echo $membership['is_vip'] ? 'stat-icon-' . esc_attr($membership['level']) : ''; ?>">
                <?php if ($membership['is_svip']) : ?>
                <span class="membership-emoji">👑</span>
                <?php elseif ($membership['is_vip']) : ?>
                <span class="membership-emoji">⭐</span>
                <?php else : ?>
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <?php endif; ?>
            </div>
            <div>
                <div class="stat-number stat-number-membership <?php echo $membership['is_vip'] ? 'stat-number-' . esc_attr($membership['level']) : ''; ?>"><?php echo esc_html($membership['name']); ?></div>
                <div class="stat-label"><?php esc_html_e('会员等级', 'folio'); ?></div>
            </div>
        </div>
    </div>

    <!-- 最近收藏 -->
    <?php if (!empty($favorites)) : ?>
    <div class="recent-favorites">
        <div class="flex items-center justify-between mb-4">
            <h2 class="recent-favorites-title text-xl font-bold"><?php esc_html_e('最近收藏', 'folio'); ?></h2>
            <a href="<?php echo folio_User_Center::get_url('favorites'); ?>" class="recent-favorites-link text-sm">
                <?php esc_html_e('查看全部', 'folio'); ?> →
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($favorites as $post_id) : 
                $post = get_post($post_id);
                if (!$post || $post->post_status !== 'publish') continue;
                setup_postdata($post);
            ?>
            <div class="favorite-item">
                <a href="<?php echo get_permalink($post_id); ?>" class="block">
                    <?php if (has_post_thumbnail($post_id)) : ?>
                    <div class="favorite-thumb">
                        <?php echo get_the_post_thumbnail($post_id, 'medium', array('class' => 'w-full h-32 object-cover rounded-lg')); ?>
                    </div>
                    <?php endif; ?>
                    <h3 class="favorite-item-title mt-2 font-medium text-sm"><?php echo get_the_title($post_id); ?></h3>
                </a>
            </div>
            <?php endforeach; wp_reset_postdata(); ?>
        </div>
    </div>
    <?php else : ?>
    <div class="empty-state text-center py-12">
        <svg class="empty-state-icon w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
        </svg>
        <h3 class="empty-state-title text-lg font-medium mb-2"><?php esc_html_e('还没有收藏任何作品', 'folio'); ?></h3>
        <p class="empty-state-text mb-4"><?php esc_html_e('去浏览一些精彩的作品吧！', 'folio'); ?></p>
        <a href="<?php echo get_post_type_archive_link('portfolio'); ?>" class="btn-primary">
            <?php esc_html_e('浏览作品', 'folio'); ?>
        </a>
    </div>
    <?php endif; ?>
</div>



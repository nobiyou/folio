<?php
/**
 * User Center - Profile
 * 
 * 个人资料编辑
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$updated = isset($_GET['updated']);
$membership = folio_get_user_membership($current_user->ID);
?>

<div class="profile-page">
    <h1 class="text-2xl font-bold mb-6"><?php esc_html_e('Profile', 'folio'); ?></h1>

    <?php if ($updated) : ?>
    <div class="alert alert-success">
        <?php esc_html_e('Profile updated!', 'folio'); ?>
    </div>
    <?php endif; ?>

    <form method="post" class="profile-form">
        <input type="hidden" name="folio_action" value="update_profile">
        <?php wp_nonce_field('folio_update_profile', 'folio_profile_nonce'); ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- 左侧：基本信息 -->
            <div class="basic-info">
                <h2 class="text-lg font-bold mb-4"><?php esc_html_e('Basic Information', 'folio'); ?></h2>

                <div class="form-group">
                    <label class="form-label"><?php esc_html_e('Username', 'folio'); ?></label>
                    <input type="text" value="<?php echo esc_attr($current_user->user_login); ?>" class="form-input" disabled>
                    <small><?php esc_html_e('Username cannot be changed', 'folio'); ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php esc_html_e('Email Address', 'folio'); ?></label>
                    <input type="email" value="<?php echo esc_attr($current_user->user_email); ?>" class="form-input" disabled>
                    <small><?php esc_html_e('Email address cannot be changed', 'folio'); ?></small>
                </div>

                <div class="form-group">
                    <label for="display_name" class="form-label"><?php esc_html_e('Display Name', 'folio'); ?></label>
                    <input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($current_user->display_name); ?>" class="form-input" required>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label"><?php esc_html_e('Bio', 'folio'); ?></label>
                    <textarea id="description" name="description" rows="4" class="form-input" placeholder="<?php esc_attr_e('Tell us about yourself...', 'folio'); ?>"><?php echo esc_textarea($current_user->description); ?></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    <?php esc_html_e('Save Changes', 'folio'); ?>
                </button>
            </div>

            <!-- 右侧：头像和统计 -->
            <div class="avatar-stats">
                <h2 class="text-lg font-bold mb-4"><?php esc_html_e('Avatar', 'folio'); ?></h2>

                <div class="avatar-section text-center mb-6">
                    <div class="current-avatar mb-4">
                        <?php echo get_avatar($current_user->ID, 120, '', '', array('class' => 'rounded-full mx-auto')); ?>
                    </div>
                    <p class="text-sm mb-2">
                        <?php esc_html_e('Currently using Gravatar avatar', 'folio'); ?>
                    </p>
                    <a href="https://gravatar.com" target="_blank" class="text-sm underline">
                        <?php esc_html_e('Change avatar on Gravatar', 'folio'); ?>
                    </a>
                </div>

                <h2 class="text-lg font-bold mb-4"><?php esc_html_e('Account Stats', 'folio'); ?></h2>

                <div class="stats-list space-y-4">
                    <div class="stat-item">
                        <div class="flex items-center justify-between">
                            <span><?php esc_html_e('Membership Level', 'folio'); ?></span>
                            <span class="membership-badge-small <?php echo esc_attr($membership['level']); ?>">
                                <?php echo wp_kses_post($membership['icon']); ?> <?php echo esc_html($membership['name']); ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($membership['is_vip']) : ?>
                    <div class="stat-item">
                        <div class="flex items-center justify-between">
                            <span><?php esc_html_e('Membership Expiry', 'folio'); ?></span>
                            <span>
                                <?php 
                                if ($membership['is_permanent']) {
                                    esc_html_e('Permanent', 'folio');
                                } elseif ($membership['expiry']) {
                                    echo esc_html($membership['expiry']);
                                    if ($membership['days_left'] !== null && $membership['days_left'] <= 7) {
                                        echo ' <span class="expiry-warning">(' . sprintf(__('%d days remaining', 'folio'), $membership['days_left']) . ')</span>';
                                    }
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-item">
                        <div class="flex items-center justify-between">
                            <span><?php esc_html_e('Registration Date', 'folio'); ?></span>
                            <span><?php echo date_i18n(get_option('date_format'), strtotime($current_user->user_registered)); ?></span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="flex items-center justify-between">
                            <span><?php esc_html_e('Favorites', 'folio'); ?></span>
                            <span><?php echo count(folio_get_user_favorites($current_user->ID)); ?> <?php esc_html_e('items', 'folio'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- 快捷操作 -->
                <div class="quick-actions mt-8">
                    <h3 class="text-md font-bold mb-3"><?php esc_html_e('Quick Actions', 'folio'); ?></h3>
                    <div class="space-y-2">
                        <a href="<?php echo folio_User_Center::get_url('favorites'); ?>" class="quick-action-btn">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            <?php esc_html_e('View My Favorites', 'folio'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="quick-action-btn">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <?php esc_html_e('Browse Posts', 'folio'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="quick-action-btn">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            <?php esc_html_e('Back to Home', 'folio'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- 样式已移动到 /assets/css/user-interface.css -->

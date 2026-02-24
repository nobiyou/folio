<?php
/**
 * User Center Template
 * 
 * 用户中心主模板
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$user_center = folio_User_Center::get_instance();
$current_action = $user_center->get_current_action();
$current_user = wp_get_current_user();
?>

<div class="user-center-container">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <?php if (is_user_logged_in()) : ?>
        <!-- 已登录用户界面 -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- 侧边栏导航 -->
            <div class="lg:col-span-1">
                <div class="user-sidebar">
                    <!-- 用户信息 -->
                    <div class="user-info text-center mb-6">
                        <div class="avatar mb-4">
                            <?php echo get_avatar($current_user->ID, 80, '', '', array('class' => 'rounded-full mx-auto')); ?>
                        </div>
                        <h3 class="text-lg font-bold"><?php echo esc_html($current_user->display_name); ?></h3>
                        <p class="text-sm"><?php echo esc_html($current_user->user_email); ?></p>
                    </div>

                    <!-- 导航菜单 -->
                    <nav class="user-nav">
                        <ul class="space-y-2">
                            <li>
                                <a href="<?php echo folio_User_Center::get_url(); ?>" 
                                   class="nav-item <?php echo ($current_action === 'dashboard') ? 'active' : ''; ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z"/>
                                    </svg>
                                    <?php esc_html_e('仪表盘', 'folio'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo folio_User_Center::get_url('favorites'); ?>" 
                                   class="nav-item <?php echo ($current_action === 'favorites') ? 'active' : ''; ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                    <?php esc_html_e('我的收藏', 'folio'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo folio_User_Center::get_url('profile'); ?>" 
                                   class="nav-item <?php echo ($current_action === 'profile') ? 'active' : ''; ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <?php esc_html_e('个人资料', 'folio'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo folio_User_Center::get_url('membership'); ?>" 
                                   class="nav-item <?php echo ($current_action === 'membership') ? 'active' : ''; ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                    </svg>
                                    <?php esc_html_e('会员中心', 'folio'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo folio_User_Center::get_url('notifications'); ?>" 
                                   class="nav-item <?php echo ($current_action === 'notifications') ? 'active' : ''; ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                    <?php esc_html_e('我的通知', 'folio'); ?>
                                    <?php 
                                    if (class_exists('folio_Membership_Notifications')) {
                                        $notifications = folio_Membership_Notifications::get_instance();
                                        $unread_count = $notifications->get_unread_count(get_current_user_id());
                                        if ($unread_count > 0) {
                                            echo '<span class="notification-badge-nav ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-0.5">' . esc_html($unread_count) . '</span>';
                                        }
                                    }
                                    ?>
                                </a>
                            </li>
                            
                            <?php if (current_user_can('edit_posts')) : ?>
                            <li>
                                <a href="<?php echo admin_url(); ?>" 
                                   class="nav-item"
                                   target="_blank">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <?php esc_html_e('进入后台', 'folio'); ?>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>

                        <!-- 退出登录 -->
                        <div class="mt-6 pt-6 border-t">
                            <form method="post" class="inline">
                                <input type="hidden" name="folio_action" value="logout">
                                <?php wp_nonce_field('folio_logout', 'folio_logout_nonce'); ?>
                                <button type="submit" class="text-red-500 hover:text-red-600 text-sm flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    <?php esc_html_e('退出登录', 'folio'); ?>
                                </button>
                            </form>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- 主内容区域 -->
            <div class="lg:col-span-3">
                <div class="user-content">
                    <?php
                    switch ($current_action) {
                        case 'favorites':
                            include get_template_directory() . '/user-center/favorites.php';
                            break;
                        case 'profile':
                            include get_template_directory() . '/user-center/profile.php';
                            break;
                        case 'membership':
                            include get_template_directory() . '/user-center/membership.php';
                            break;
                        case 'notifications':
                            include get_template_directory() . '/user-center/notifications.php';
                            break;
                        default:
                            include get_template_directory() . '/user-center/dashboard.php';
                    }
                    ?>
                </div>
            </div>
        </div>

        <?php else : ?>
        <!-- 未登录用户界面 -->
        <div class="max-w-md mx-auto">
            <?php
            switch ($current_action) {
                case 'register':
                    include get_template_directory() . '/user-center/register.php';
                    break;
                case 'forgot-password':
                    include get_template_directory() . '/user-center/forgot-password.php';
                    break;
                default:
                    include get_template_directory() . '/user-center/login.php';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 样式已移动到 /assets/css/user-interface.css -->

<?php get_footer(); ?>

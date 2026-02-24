<?php
/**
 * Header Template
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?php echo is_singular() ? 'article' : 'website'; ?>">
    <meta property="og:url" content="<?php echo esc_url(get_permalink()); ?>">
    <meta property="og:title" content="<?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?>">
    <meta property="og:description" content="<?php echo esc_attr(get_bloginfo('description')); ?>">
    <?php if (is_singular() && has_post_thumbnail()) : ?>
    <meta property="og:image" content="<?php echo esc_url(get_the_post_thumbnail_url(null, 'large')); ?>">
    <?php endif; ?>
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr(get_bloginfo('description')); ?>">
    <?php if (is_singular() && has_post_thumbnail()) : ?>
    <meta name="twitter:image" content="<?php echo esc_url(get_the_post_thumbnail_url(null, 'large')); ?>">
    <?php endif; ?>
    
    <?php 
    // 获取网站LOGO和Favicon
    $theme_options = get_option('folio_theme_options', array());
    $site_logo = isset($theme_options['site_logo']) ? esc_url($theme_options['site_logo']) : '';
    $site_favicon = isset($theme_options['site_favicon']) ? $theme_options['site_favicon'] : '';
    
    // 应用 CDN 转换到 favicon URL
    if ($site_favicon) {
        // 如果是附件 URL，应用过滤器确保 CDN 转换生效
        if (strpos($site_favicon, home_url('/wp-content/uploads/')) !== false) {
            // 尝试获取附件 ID 并应用过滤器
            $attachment_id = attachment_url_to_postid($site_favicon);
            if ($attachment_id) {
                $site_favicon = wp_get_attachment_url($attachment_id);
            } else {
                // 应用 wp_get_attachment_url 过滤器
                $site_favicon = apply_filters('wp_get_attachment_url', $site_favicon);
            }
        }
        $site_favicon = esc_url($site_favicon);
        
        $favicon_ext = strtolower(pathinfo(parse_url($site_favicon, PHP_URL_PATH), PATHINFO_EXTENSION));
        $favicon_type = 'image/png';
        if ($favicon_ext === 'ico') {
            $favicon_type = 'image/x-icon';
        } elseif ($favicon_ext === 'svg') {
            $favicon_type = 'image/svg+xml';
        } elseif ($favicon_ext === 'jpg' || $favicon_ext === 'jpeg') {
            $favicon_type = 'image/jpeg';
        }
        echo '<link rel="icon" type="' . esc_attr($favicon_type) . '" href="' . $site_favicon . '">' . "\n";
        echo '<link rel="shortcut icon" type="' . esc_attr($favicon_type) . '" href="' . $site_favicon . '">' . "\n";
    } else {
        // 如果没有设置 favicon，使用 WordPress 默认的站点图标（已应用 CDN）
        $site_icon = get_site_icon_url();
        if ($site_icon) {
            $favicon_ext = strtolower(pathinfo(parse_url($site_icon, PHP_URL_PATH), PATHINFO_EXTENSION));
            $favicon_type = 'image/png';
            if ($favicon_ext === 'ico') {
                $favicon_type = 'image/x-icon';
            } elseif ($favicon_ext === 'svg') {
                $favicon_type = 'image/svg+xml';
            } elseif ($favicon_ext === 'jpg' || $favicon_ext === 'jpeg') {
                $favicon_type = 'image/jpeg';
            }
            echo '<link rel="icon" type="' . esc_attr($favicon_type) . '" href="' . esc_url($site_icon) . '">' . "\n";
            echo '<link rel="shortcut icon" type="' . esc_attr($favicon_type) . '" href="' . esc_url($site_icon) . '">' . "\n";
        }
    }
    ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('antialiased font-body'); ?>>
<?php wp_body_open(); ?>

<!-- Skip to content link -->
<a href="#main-content" class="skip-link sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-black text-white px-4 py-2 z-50">
    <?php esc_html_e('Skip to content', 'folio'); ?>
</a>

<div class="flex min-h-screen">
    
    <!-- 左侧社交栏 (大屏显示) -->
    <?php folio_render_social_sidebar(); ?>

    <!-- 主内容区域 -->
    <div class="flex-1 px-4 py-8 md:px-8 xl:pl-24 max-w-[1600px] mx-auto">
        
        <!-- 顶部 Header -->
        <header class="mb-12">
            <div class="flex items-start justify-between">
                <!-- 左侧：Logo + 导航 -->
                <div class="flex items-start gap-8 flex-1">
                    <!-- Logo -->
                    <div class="pt-2 flex items-center justify-between w-full md:w-auto">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="block">
                            <?php 
                            // 优先使用主题设置中的LOGO
                            if (!empty($site_logo)) : ?>
                                <img src="<?php echo esc_url($site_logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="site-logo" style="max-height: 60px; width: auto;">
                            <?php elseif (has_custom_logo()) : ?>
                                <?php the_custom_logo(); ?>
                            <?php else : ?>
                                <h1 class="text-5xl font-bold tracking-tighter italic transform -skew-x-6 text-gray-800 leading-none font-heading">
                                    <?php bloginfo('name'); ?>
                                </h1>
                            <?php endif; ?>
                        </a>
                        
                        <!-- 移动端菜单按钮 -->
                        <button id="mobile-menu-toggle" class="md:hidden p-2 text-gray-600 hover:text-black transition" aria-label="<?php esc_attr_e('Open menu', 'folio'); ?>" aria-expanded="false" aria-controls="mobile-menu">
                            <svg class="w-6 h-6 menu-icon-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <svg class="w-6 h-6 menu-icon-close hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- 桌面端导航菜单 -->
                    <nav class="primary-nav hidden md:block text-xs font-bold uppercase tracking-wider pt-2">
                        <?php
                        wp_nav_menu(array(
                            'theme_location' => 'primary',
                            'container'      => false,
                            'menu_class'     => 'space-y-1',
                            'fallback_cb'    => 'folio_fallback_menu',
                            'walker'         => new folio_Nav_Walker(),
                        ));
                        ?>
                    </nav>
                </div>
                
                <!-- 右侧工具栏 -->
                <div class="flex items-center gap-4 flex-shrink-0 pt-2 h-16">
                <!-- 用户入口 -->
                <?php if (is_user_logged_in()) : 
                    $current_user = wp_get_current_user();
                    $membership = function_exists('folio_get_user_membership') ? folio_get_user_membership() : array('is_vip' => false, 'icon' => '', 'name' => __('Regular User', 'folio'));
                ?>
                <!-- 通知铃铛 -->
                <button class="notification-bell relative p-2 text-gray-600 hover:text-black transition" title="<?php esc_attr_e('Notifications', 'folio'); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="notification-badge absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
                </button>
                
                <a href="<?php echo esc_url(home_url('user-center')); ?>" class="user-menu-btn flex items-center gap-2" title="<?php esc_attr_e('User Center', 'folio'); ?>">
                    <?php echo get_avatar($current_user->ID, 32, '', '', array('class' => 'rounded-full')); ?>
                    <span class="user-display-name hidden sm:inline text-sm font-medium">
                        <?php echo esc_html($current_user->display_name); ?>
                    </span>
                    <?php if ($membership && $membership['is_vip']) : ?>
                    <span class="inline-flex items-center"><?php echo wp_kses_post($membership['icon']); ?></span>
                    <?php endif; ?>
                </a>
                <?php else : ?>
                <!-- 未登录用户：通知铃铛（仅显示全局通知） -->
                <button class="notification-bell relative p-2 text-gray-600 hover:text-black transition" title="<?php esc_attr_e('Notifications', 'folio'); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="notification-badge absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
                </button>
                <a href="<?php echo esc_url(home_url('user-center/login')); ?>" class="user-login-btn text-sm font-medium px-4 py-2 rounded-lg transition">
                    <?php esc_html_e('Sign In', 'folio'); ?>
                </a>
                <?php endif; ?>
                
                <!-- 主题切换按钮 -->
                <?php 
                $theme_options = get_option('folio_theme_options', array());
                // 检查是否显示主题切换按钮（默认为 true）
                $show_theme_toggle = !isset($theme_options['show_theme_toggle']) || !empty($theme_options['show_theme_toggle']);
                if ($show_theme_toggle) : 
                ?>
                <button id="theme-toggle" class="theme-toggle" aria-label="<?php esc_attr_e('Toggle dark mode', 'folio'); ?>">
                <!-- 月亮图标 (亮色模式时显示) -->
                <svg class="icon-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
                <!-- 太阳图标 (暗色模式时显示) -->
                <svg class="icon-sun" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                </button>
                <?php endif; ?>
                </div>
            </div>
        </header>
        
        <!-- 移动端导航菜单 -->
        <nav id="mobile-menu" class="mobile-menu-overlay md:hidden hidden fixed inset-0 z-40 px-6" aria-hidden="true">
            <!-- 关闭按钮 -->
            <div class="flex justify-end pt-6 pb-4">
                <button id="mobile-menu-close" class="p-2 text-gray-600 hover:text-black transition" aria-label="<?php esc_attr_e('Close menu', 'folio'); ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div class="text-sm font-bold uppercase tracking-wider text-gray-500 space-y-4">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'space-y-4',
                    'fallback_cb'    => 'folio_fallback_menu',
                    'walker'         => new folio_Nav_Walker(),
                ));
                ?>
            </div>
            
            <!-- 移动端社交链接 -->
            <?php $links = folio_get_social_links(); ?>
            <?php if (!empty($links['email']['url']) || !empty($links['instagram']['url']) || !empty($links['linkedin']['url'])) : ?>
            <div class="mt-8 pt-8 border-t border-gray-200 flex gap-6">
                <?php if (!empty($links['email']['url'])) : 
                    $email_label = folio_get_icon_display_name($links['email']['icon'], 'email');
                    $email_label = !empty($email_label) ? $email_label : __('Email', 'folio');
                ?>
                <a href="mailto:<?php echo esc_attr($links['email']['url']); ?>" class="text-gray-400 hover:text-black transition" aria-label="<?php echo esc_attr($email_label); ?>" title="<?php echo esc_attr($email_label); ?>">
                    <?php echo folio_render_social_icon('email', $links['email']['icon']); ?>
                </a>
                <?php endif; ?>
                <?php if (!empty($links['instagram']['url'])) : 
                    $instagram_label = folio_get_icon_display_name($links['instagram']['icon'], 'instagram');
                    $instagram_label = !empty($instagram_label) ? $instagram_label : __('Instagram', 'folio');
                ?>
                <a href="<?php echo esc_url($links['instagram']['url']); ?>" class="text-gray-400 hover:text-black transition" target="_blank" rel="noopener" aria-label="<?php echo esc_attr($instagram_label); ?>" title="<?php echo esc_attr($instagram_label); ?>">
                    <?php echo folio_render_social_icon('instagram', $links['instagram']['icon']); ?>
                </a>
                <?php endif; ?>
                <?php if (!empty($links['linkedin']['url'])) : 
                    $linkedin_label = folio_get_icon_display_name($links['linkedin']['icon'], 'linkedin');
                    $linkedin_label = !empty($linkedin_label) ? $linkedin_label : __('LinkedIn', 'folio');
                ?>
                <a href="<?php echo esc_url($links['linkedin']['url']); ?>" class="text-gray-400 hover:text-black transition" target="_blank" rel="noopener" aria-label="<?php echo esc_attr($linkedin_label); ?>" title="<?php echo esc_attr($linkedin_label); ?>">
                    <?php echo folio_render_social_icon('linkedin', $links['linkedin']['icon']); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </nav>
        
        <!-- 主内容区域 ID for skip link -->
        <div id="main-content">

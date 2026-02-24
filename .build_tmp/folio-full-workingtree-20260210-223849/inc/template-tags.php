<?php
/**
 * Template Tags
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if a post is "new" (published within specified days)
 *
 * @param int $post_id Post ID (optional, defaults to current post)
 * @param int $days Number of days to consider as "new" (optional, defaults to theme setting)
 * @return bool
 */
function folio_is_new_post($post_id = null, $days = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    // 如果未指定天数，从主题设置读取
    if ($days === null) {
        $theme_options = get_option('folio_theme_options', array());
        $days = isset($theme_options['new_days']) && !empty($theme_options['new_days']) 
            ? absint($theme_options['new_days']) 
            : 30;
    }
    
    $post_date = get_post_time('U', false, $post_id);
    if (!$post_date) {
        return false;
    }
    
    $days_since_publish = (time() - $post_date) / DAY_IN_SECONDS;
    
    return $days_since_publish <= $days;
}

/**
 * Check if a post is "updated" (modified significantly after publication)
 *
 * @param int $post_id Post ID (optional, defaults to current post)
 * @param int $days Number of days to consider recent update (default: 30)
 * @return bool
 */
function folio_is_updated_post($post_id = null, $days = 30) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $post_date = get_post_time('U', false, $post_id);
    $modified_date = get_post_modified_time('U', false, $post_id);
    
    if (!$post_date || !$modified_date) {
        return false;
    }
    
    // Must be modified at least 1 day after publication
    $modification_gap = ($modified_date - $post_date) / DAY_IN_SECONDS;
    if ($modification_gap < 1) {
        return false;
    }
    
    // Must be modified within the last X days
    $days_since_modified = (time() - $modified_date) / DAY_IN_SECONDS;
    
    return $days_since_modified <= $days;
}

/**
 * Get ribbon type for a post item
 *
 * @param int $post_id Post ID (optional)
 * @return string 'new', 'updated', or empty string
 */
function folio_get_ribbon_type($post_id = null) {
    if (folio_is_new_post($post_id)) {
        return 'new';
    }
    
    if (folio_is_updated_post($post_id)) {
        return 'updated';
    }
    
    return '';
}

/**
 * Render ribbon HTML
 *
 * @param string $type Ribbon type ('new' or 'updated')
 */
function folio_render_ribbon($type = '') {
    if (empty($type)) {
        return;
    }
    
    $label = ($type === 'new') ? __('New', 'folio') : __('Updated', 'folio');
    $class = ($type === 'new') ? 'ribbon--new bg-black text-white' : 'ribbon--updated';
    ?>
    <div class="ribbon-wrapper">
        <div class="ribbon <?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></div>
    </div>
    <?php
}

/**
 * Get post category for display.
 *
 * @param int $post_id Post ID (optional)
 * @return string Category name or empty string
 */
function folio_get_post_category_name($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $categories = get_the_category($post_id);
    if ($categories && !is_wp_error($categories)) {
        return $categories[0]->name;
    }

    return '';
}

/**
 * Render hexagon card content
 *
 * @param int $post_id Post ID (optional)
 */
function folio_render_hexagon_card($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $title = get_the_title($post_id);
    $image_count = folio_get_post_image_count($post_id);
    
    // 获取分类名称
    $categories = get_the_category($post_id);
    $category_name = '';
    if ($categories && !is_wp_error($categories)) {
        $category_name = $categories[0]->name;
    }
    ?>
    <div class="hexagon hexagon-card">
        <div class="hexagon-content">
            <!-- 栏目名称 -->
            <?php if ($category_name) : ?>
            <div class="hexagon-category">
                <?php echo esc_html(strtoupper($category_name)); ?>
            </div>
            <?php endif; ?>
            
            <!-- 分隔线 -->
            <div class="hexagon-divider"></div>
            
            <!-- 文章标题 -->
            <h2 class="hexagon-title">
                <?php echo esc_html($title); ?>
            </h2>
            
            <!-- 分隔线 -->
            <div class="hexagon-divider"></div>
            
            <!-- 图片张数 -->
            <?php if ($image_count > 0) : ?>
            <div class="hexagon-image-count">
                <span class="hexagon-count"><?php echo esc_html($image_count); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Get social links from theme options
 * 从主题设置页面获取社交链接（Customizer 中的社交链接设置已移除）
 *
 * @return array Array of social links with icons
 */
function folio_get_social_links() {
    $theme_options = get_option('folio_theme_options', array());
    
    return array(
        'email'     => array(
            'url' => isset($theme_options['email']) ? $theme_options['email'] : '',
            'icon' => isset($theme_options['email_icon']) ? $theme_options['email_icon'] : '',
        ),
        'instagram' => array(
            'url' => isset($theme_options['instagram']) ? $theme_options['instagram'] : '',
            'icon' => isset($theme_options['instagram_icon']) ? $theme_options['instagram_icon'] : '',
        ),
        'linkedin'  => array(
            'url' => isset($theme_options['linkedin']) ? $theme_options['linkedin'] : '',
            'icon' => isset($theme_options['linkedin_icon']) ? $theme_options['linkedin_icon'] : '',
        ),
        'twitter'   => array(
            'url' => isset($theme_options['twitter']) ? $theme_options['twitter'] : '',
            'icon' => isset($theme_options['twitter_icon']) ? $theme_options['twitter_icon'] : '',
        ),
        'facebook'  => array(
            'url' => isset($theme_options['facebook']) ? $theme_options['facebook'] : '',
            'icon' => isset($theme_options['facebook_icon']) ? $theme_options['facebook_icon'] : '',
        ),
        'github'    => array(
            'url' => isset($theme_options['github']) ? $theme_options['github'] : '',
            'icon' => isset($theme_options['github_icon']) ? $theme_options['github_icon'] : '',
        ),
    );
}

/**
 * Get available social media icons
 * 获取可用的社交媒体图标列表
 *
 * @return array Array of icon names and their SVG code
 */
function folio_get_social_icon_library() {
    return array(
        'default' => array(
            'name' => __('Default', 'folio'),
            'svg' => ''
        ),
        'email' => array(
            'name' => __('Email', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 18 18"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>'
        ),
        'instagram' => array(
            'name' => __('Instagram', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>'
        ),
        'linkedin' => array(
            'name' => __('LinkedIn', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>'
        ),
        'twitter' => array(
            'name' => __('Twitter/X', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'
        ),
        'facebook' => array(
            'name' => __('Facebook', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'
        ),
        'github' => array(
            'name' => __('GitHub', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>'
        ),
        'youtube' => array(
            'name' => __('YouTube', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>'
        ),
        'pinterest' => array(
            'name' => __('Pinterest', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.487.535 6.624 0 11.99-5.367 11.99-11.987C23.97 5.39 18.592.026 11.968.026L12.017 0z"/></svg>'
        ),
        'tiktok' => array(
            'name' => __('TikTok', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>'
        ),
        'weibo' => array(
            'name' => __('Weibo', 'folio'),
            'svg' => '<svg  class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>Sina Weibo</title><path d="M10.098 20.323c-3.977.391-7.414-1.406-7.672-4.02-.259-2.609 2.759-5.047 6.74-5.441 3.979-.394 7.413 1.404 7.671 4.018.259 2.6-2.759 5.049-6.737 5.439l-.002.004zM9.05 17.219c-.384.616-1.208.884-1.829.602-.612-.279-.793-.991-.406-1.593.379-.595 1.176-.861 1.793-.601.622.263.82.972.442 1.592zm1.27-1.627c-.141.237-.449.353-.689.253-.236-.09-.313-.361-.177-.586.138-.227.436-.346.672-.24.239.09.315.36.18.601l.014-.028zm.176-2.719c-1.893-.493-4.033.45-4.857 2.118-.836 1.704-.026 3.591 1.886 4.21 1.983.64 4.318-.341 5.132-2.179.8-1.793-.201-3.642-2.161-4.149zm7.563-1.224c-.346-.105-.57-.18-.405-.615.375-.977.42-1.804 0-2.404-.781-1.112-2.915-1.053-5.364-.03 0 0-.766.331-.571-.271.376-1.217.315-2.224-.27-2.809-1.338-1.337-4.869.045-7.888 3.08C1.309 10.87 0 13.273 0 15.348c0 3.981 5.099 6.395 10.086 6.395 6.536 0 10.888-3.801 10.888-6.82 0-1.822-1.547-2.854-2.915-3.284v.01zm1.908-5.092c-.766-.856-1.908-1.187-2.96-.962-.436.09-.706.511-.616.932.09.42.511.691.932.602.511-.105 1.067.044 1.442.465.376.421.466.977.316 1.473-.136.406.089.856.51.992.405.119.857-.105.992-.512.33-1.021.12-2.178-.646-3.035l.03.045zm2.418-2.195c-1.576-1.757-3.905-2.419-6.054-1.968-.496.104-.812.587-.706 1.081.104.496.586.813 1.082.707 1.532-.331 3.185.15 4.296 1.383 1.112 1.246 1.429 2.943.947 4.416-.165.48.106 1.007.586 1.157.479.165.991-.104 1.157-.586.675-2.088.241-4.478-1.338-6.235l.03.045z"/></svg>'
        ),
        'wechat' => array(
            'name' => __('WeChat', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>WeChat</title><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.111.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088V8.89c-.135-.01-.27-.027-.407-.03zm-2.53 3.274c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.969-.982z"/></svg>'
        ),
        'qq' => array(
            'name' => __('QQ', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>QQ</title><path d="M21.395 15.035a40 40 0 0 0-.803-2.264l-1.079-2.695c.001-.032.014-.562.014-.836C19.526 4.632 17.351 0 12 0S4.474 4.632 4.474 9.241c0 .274.013.804.014.836l-1.08 2.695a39 39 0 0 0-.802 2.264c-1.021 3.283-.69 4.643-.438 4.673.54.065 2.103-2.472 2.103-2.472 0 1.469.756 3.387 2.394 4.771-.612.188-1.363.479-1.845.835-.434.32-.379.646-.301.778.343.578 5.883.369 7.482.189 1.6.18 7.14.389 7.483-.189.078-.132.132-.458-.301-.778-.483-.356-1.233-.646-1.846-.836 1.637-1.384 2.393-3.302 2.393-4.771 0 0 1.563 2.537 2.103 2.472.251-.03.581-1.39-.438-4.673"/></svg>'
        ),
        'telegram' => array(
            'name' => __('Telegram', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>'
        ),
        'discord' => array(
            'name' => __('Discord', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>'
        ),
        'reddit' => array(
            'name' => __('Reddit', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-1.242a1.25 1.25 0 0 1-.636-1.696l1.081-2.26a1.25 1.25 0 0 1 1.696-.636l2.597 1.243a1.25 1.25 0 0 1-.001 2.498zm-10.02 0a1.25 1.25 0 0 1-.001-2.498l2.597-1.243a1.25 1.25 0 0 1 1.696.636l1.081 2.26a1.25 1.25 0 0 1-.636 1.696l-2.597 1.242a1.25 1.25 0 0 1-1.25-1.25zm5.02 2.999c-3.076 0-6.327 1.341-8.51 3.649-.32.31-.32.816 0 1.126s.839.31 1.159 0c1.88-1.803 4.66-2.775 7.351-2.775s5.471.972 7.351 2.775c.32.31.839.31 1.159 0 .32-.31.32-.816 0-1.126-2.183-2.308-5.434-3.649-8.51-3.649zM8.25 12C6.18 12 4.5 13.548 4.5 15.5S6.18 19 8.25 19s3.75-1.548 3.75-3.5S10.32 12 8.25 12zm7.5 0c-2.07 0-3.75 1.548-3.75 3.5s1.68 3.5 3.75 3.5 3.75-1.548 3.75-3.5S17.82 12 15.75 12z"/></svg>'
        ),
        'rss' => array(
            'name' => __('RSS', 'folio'),
            'svg' => '<svg  class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>RSS</title><path d="M19.199 24C19.199 13.467 10.533 4.8 0 4.8V0c13.165 0 24 10.835 24 24h-4.801zM3.291 17.415c1.814 0 3.293 1.479 3.293 3.295 0 1.813-1.485 3.29-3.301 3.29C1.47 24 0 22.526 0 20.71s1.475-3.294 3.291-3.295zM15.909 24h-4.665c0-6.169-5.075-11.245-11.244-11.245V8.09c8.727 0 15.909 7.184 15.909 15.91z"/></svg>'
        ),
        'privacy' => array(
            'name' => __('privacy', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>Server Fault</title><path d="M24 18.185v2.274h-4.89v-2.274H24zm-24-.106h11.505v2.274H0zm12.89 0h4.89v2.274h-4.89zm6.221-3.607H24v2.274h-4.89l.001-2.274zM0 14.367h11.505v2.274H0v-2.274zm12.89 0h4.89v2.274h-4.89v-2.274zm6.221-3.346H24v2.273h-4.89l.001-2.273zM0 10.916h11.505v2.271H0v-2.271zm12.89 0h4.89v2.271h-4.89v-2.271zm6.22-3.609H24v2.279h-4.89V7.307zM0 7.206h11.505V9.48H0V7.201zm12.89 0h4.89V9.48h-4.89V7.201zm6.221-3.556H24v2.276h-4.89v-2.28l.001.004zM0 3.541h11.505v2.274H0V3.541zm12.89 0h4.89v2.274h-4.89V3.541z"/></svg>'
        ),
        'statement' => array(
            'name' => __('statement', 'folio'),
            'svg' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>The Conversation</title><path d="M23.996 10.543c-.131-4.91-4.289-8.773-9.2-8.773H9.005a8.997 8.997 0 0 0-5.957 15.746L1.05 22.23l4.942-2.98c.95.36 1.964.524 3.012.524h6.024c5.04 0 9.099-4.156 8.969-9.23zm-8.937 5.958H9.07c-2.587 0-5.205-2.03-5.696-4.583a5.724 5.724 0 0 1 5.63-6.874h5.99c2.586 0 5.205 2.03 5.696 4.582.688 3.667-2.095 6.875-5.63 6.875z"/></svg>'
        ),
    );
}

/**
 * Get icon display name
 * 获取图标显示名称
 *
 * @param string $icon_name Icon name from library
 * @param string $platform Platform name (fallback)
 * @return string Display name
 */
function folio_get_icon_display_name($icon_name = '', $platform = '') {
    $icon_library = folio_get_social_icon_library();
    
    // 如果指定了图标名称且存在，使用图标名称对应的显示名称
    if (!empty($icon_name) && $icon_name !== 'default' && isset($icon_library[$icon_name])) {
        return $icon_library[$icon_name]['name'];
    }
    
    // 如果图标名称为 'default' 或为空，使用平台默认名称
    if (empty($icon_name) || $icon_name === 'default') {
        if (!empty($platform) && isset($icon_library[$platform])) {
            return $icon_library[$platform]['name'];
        }
    }
    
    // 如果都没有，返回空
    return '';
}

/**
 * Render social media icon
 * 渲染社交媒体图标
 *
 * @param string $platform Platform name (email, instagram, linkedin, twitter, facebook, github, etc.)
 * @param string $icon_name Icon name from library
 * @return string Icon HTML
 */
function folio_render_social_icon($platform, $icon_name = '') {
    $icon_library = folio_get_social_icon_library();
    
    // 如果指定了图标名称且存在，使用指定的图标
    if (!empty($icon_name) && isset($icon_library[$icon_name]) && !empty($icon_library[$icon_name]['svg'])) {
        return $icon_library[$icon_name]['svg'];
    }
    
    // 如果图标名称为 'default' 或为空，使用平台默认图标
    if (empty($icon_name) || $icon_name === 'default') {
        // 使用平台名称作为默认图标
        if (isset($icon_library[$platform]) && !empty($icon_library[$platform]['svg'])) {
            return $icon_library[$platform]['svg'];
        }
    }
    
    // 如果都没有，返回空
    return '';
}

/**
 * Render social sidebar
 */
function folio_render_social_sidebar() {
    $links = folio_get_social_links();
    // 检查是否有任何社交链接
    $has_links = !empty($links['email']['url']) || !empty($links['instagram']['url']) || 
                 !empty($links['linkedin']['url']) || !empty($links['twitter']['url']) || 
                 !empty($links['facebook']['url']) || !empty($links['github']['url']);
    
    if (!$has_links) {
        return;
    }
    ?>
    <aside class="hidden xl:flex flex-col items-center justify-start pt-32 w-16 fixed left-0 top-0 h-full text-gray-400 space-y-6 z-20">
        <?php if (!empty($links['email']['url'])) : 
            $email_label = folio_get_icon_display_name($links['email']['icon'], 'email');
            $email_label = !empty($email_label) ? $email_label : __('Email', 'folio');
        ?>
        <a href="mailto:<?php echo esc_attr($links['email']['url']); ?>" aria-label="<?php echo esc_attr($email_label); ?>" class="hover:text-gray-800 transition" title="<?php echo esc_attr($email_label); ?>">
            <?php echo folio_render_social_icon('email', $links['email']['icon']); ?>
        </a>
        <?php endif; ?>
        
        <?php if (!empty($links['instagram']['url'])) : 
            $instagram_label = folio_get_icon_display_name($links['instagram']['icon'], 'instagram');
            $instagram_label = !empty($instagram_label) ? $instagram_label : 'Instagram';
        ?>
        <a href="<?php echo esc_url($links['instagram']['url']); ?>" aria-label="<?php echo esc_attr($instagram_label); ?>" class="hover:text-gray-800 transition" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($instagram_label); ?>">
            <?php echo folio_render_social_icon('instagram', $links['instagram']['icon']); ?>
        </a>
        <?php endif; ?>
        
        <?php if (!empty($links['linkedin']['url'])) : 
            $linkedin_label = folio_get_icon_display_name($links['linkedin']['icon'], 'linkedin');
            $linkedin_label = !empty($linkedin_label) ? $linkedin_label : 'LinkedIn';
        ?>
        <a href="<?php echo esc_url($links['linkedin']['url']); ?>" aria-label="<?php echo esc_attr($linkedin_label); ?>" class="hover:text-gray-800 transition" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($linkedin_label); ?>">
            <?php echo folio_render_social_icon('linkedin', $links['linkedin']['icon']); ?>
        </a>
        <?php endif; ?>
        
        <?php if (!empty($links['twitter']['url'])) : 
            $twitter_label = folio_get_icon_display_name($links['twitter']['icon'], 'twitter');
            $twitter_label = !empty($twitter_label) ? $twitter_label : 'Twitter/X';
        ?>
        <a href="<?php echo esc_url($links['twitter']['url']); ?>" aria-label="<?php echo esc_attr($twitter_label); ?>" class="hover:text-gray-800 transition" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($twitter_label); ?>">
            <?php echo folio_render_social_icon('twitter', $links['twitter']['icon']); ?>
        </a>
        <?php endif; ?>
        
        <?php if (!empty($links['facebook']['url'])) : 
            $facebook_label = folio_get_icon_display_name($links['facebook']['icon'], 'facebook');
            $facebook_label = !empty($facebook_label) ? $facebook_label : 'Facebook';
        ?>
        <a href="<?php echo esc_url($links['facebook']['url']); ?>" aria-label="<?php echo esc_attr($facebook_label); ?>" class="hover:text-gray-800 transition" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($facebook_label); ?>">
            <?php echo folio_render_social_icon('facebook', $links['facebook']['icon']); ?>
        </a>
        <?php endif; ?>
        
        <?php if (!empty($links['github']['url'])) : 
            $github_label = folio_get_icon_display_name($links['github']['icon'], 'github');
            $github_label = !empty($github_label) ? $github_label : 'GitHub';
        ?>
        <a href="<?php echo esc_url($links['github']['url']); ?>" aria-label="<?php echo esc_attr($github_label); ?>" class="hover:text-gray-800 transition" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($github_label); ?>">
            <?php echo folio_render_social_icon('github', $links['github']['icon']); ?>
        </a>
        <?php endif; ?>
    </aside>
    <?php
}

/**
 * Render breadcrumb navigation
 *
 * @param array $args Optional arguments
 */
function folio_breadcrumbs($args = array()) {
    $defaults = array(
        'separator' => ' / ',
        'home_text' => __('Home', 'folio'),
    );
    $args = wp_parse_args($args, $defaults);
    
    $items = array();
    
    // Home link
    $items[] = '<a href="' . esc_url(home_url('/')) . '" class="breadcrumb-link">' . esc_html($args['home_text']) . '</a>';
    
    if (is_single()) {
        // Get categories
        $categories = get_the_category();
        if ($categories && !is_wp_error($categories)) {
            $category = $categories[0];
            $items[] = '<a href="' . esc_url(get_category_link($category->term_id)) . '" class="breadcrumb-link">' . esc_html($category->name) . '</a>';
        }
        
        // Current item
        $items[] = '<span class="breadcrumb-current">' . esc_html(get_the_title()) . '</span>';
        
    } elseif (is_category()) {
        // Current category
        $term = get_queried_object();
        $items[] = '<span class="breadcrumb-current">' . esc_html($term->name) . '</span>';
        
    } elseif (is_tag()) {
        // Current tag - use single_tag_title to get clean tag name without HTML
        $tag_name = single_tag_title('', false);
        $items[] = '<span class="breadcrumb-current">' . esc_html($tag_name) . '</span>';
        
    } elseif (is_archive()) {
        // For other archive types, strip HTML tags from archive title
        $archive_title = get_the_archive_title();
        $items[] = '<span class="breadcrumb-current">' . esc_html(strip_tags($archive_title)) . '</span>';
    }
    
    echo '<nav aria-label="Breadcrumb" class="breadcrumb-nav text-xs uppercase font-bold tracking-widest">';
    echo implode('<span class="breadcrumb-separator mx-2">' . esc_html($args['separator']) . '</span>', $items);
    echo '</nav>';
}

/**
 * Custom Nav Walker for primary menu
 */
class folio_Nav_Walker extends Walker_Nav_Menu {
    
    public function start_lvl(&$output, $depth = 0, $args = null) {
        $output .= '<ul class="space-y-1">';
    }
    
    public function end_lvl(&$output, $depth = 0, $args = null) {
        $output .= '</ul>';
    }
    
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $is_current = in_array('current-menu-item', $classes) || in_array('current-menu-ancestor', $classes);
        
        // 检查是否是当前文章的分类
        if (!$is_current && is_single() && $item->object === 'category') {
            $post_categories = get_the_category();
            if ($post_categories) {
                foreach ($post_categories as $category) {
                    if ($category->term_id == $item->object_id) {
                        $is_current = true;
                        break;
                    }
                }
            }
        }
        
        $output .= '<li';
        if ($is_current) {
            $output .= ' class="nav-item-current flex items-center gap-2"';
        }
        $output .= '>';
        
        if ($is_current) {
            $output .= '<span class="nav-item-active">' . esc_html($item->title) . '</span>';
            $output .= ' <span class="flex gap-1"><span class="nav-dot w-2 h-2 rounded-full"></span></span>';
        } else {
            $output .= '<a href="' . esc_url($item->url) . '" class="nav-link transition">';
            $output .= esc_html($item->title);
            $output .= '</a>';
        }
    }
    
    public function end_el(&$output, $item, $depth = 0, $args = null) {
        $output .= '</li>';
    }
}

/**
 * Fallback menu when no menu is assigned
 */
function folio_fallback_menu() {
    ?>
    <ul class="space-y-1">
        <li class="<?php echo is_front_page() ? 'nav-item-current flex items-center gap-2' : ''; ?>">
            <?php if (is_front_page()) : ?>
                <span class="nav-item-active"><?php esc_html_e('Home', 'folio'); ?></span>
                <span class="flex gap-1"><span class="nav-dot w-2 h-2 rounded-full"></span><span class="nav-dot w-2 h-2 rounded-full"></span></span>
            <?php else : ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="nav-link transition"><?php esc_html_e('Home', 'folio'); ?></a>
            <?php endif; ?>
        </li>
        <?php
        // 使用标准文章分类
        $categories = get_categories(array(
            'hide_empty' => true,
            'number' => 10,
        ));
        
        if ($categories && !is_wp_error($categories)) :
            foreach ($categories as $category) :
                // 检查是否是当前分类页或当前文章的分类
                $is_current = is_category($category->term_id);
                if (!$is_current && is_single()) {
                    $post_categories = get_the_category();
                    if ($post_categories) {
                        foreach ($post_categories as $post_cat) {
                            if ($post_cat->term_id == $category->term_id) {
                                $is_current = true;
                                break;
                            }
                        }
                    }
                }
        ?>
        <li class="<?php echo $is_current ? 'nav-item-current flex items-center gap-2' : ''; ?>">
            <?php if ($is_current) : ?>
                <span class="nav-item-active"><?php echo esc_html($category->name); ?></span>
                <span class="flex gap-1"><span class="nav-dot w-2 h-2 rounded-full"></span></span>
            <?php else : ?>
                <a href="<?php echo esc_url(get_term_link($category)); ?>" class="nav-link transition"><?php echo esc_html($category->name); ?></a>
            <?php endif; ?>
        </li>
        <?php
            endforeach;
        endif;
        ?>
    </ul>
    <?php
}


/**
 * 获取相关文章
 * 
 * 基于分类和标签的智能推荐
 * 优先级：同分类同标签 > 同分类 > 同标签 > 随机
 * 
 * @param int $post_id 当前文章ID
 * @param int $limit 返回数量
 * @return array 相关文章对象数组
 */
function folio_get_related_posts($post_id, $limit = 3) {
    $related_posts = array();
    
    // 获取当前文章的分类和标签
    $categories = wp_get_post_categories($post_id);
    $tags = wp_get_post_tags($post_id, array('fields' => 'ids'));
    
    // 1. 优先获取同分类同标签的文章
    if (!empty($categories) && !empty($tags)) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => array($post_id),
            'orderby' => 'rand',
            'category__in' => $categories,
            'tag__in' => $tags,
        );
        
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $related_posts[] = get_post();
            }
            wp_reset_postdata();
        }
    }
    
    // 2. 如果数量不够，获取同分类的文章
    if (count($related_posts) < $limit && !empty($categories)) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit - count($related_posts),
            'post__not_in' => array_merge(array($post_id), wp_list_pluck($related_posts, 'ID')),
            'orderby' => 'rand',
            'category__in' => $categories,
        );
        
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $related_posts[] = get_post();
            }
            wp_reset_postdata();
        }
    }
    
    // 3. 如果数量还不够，获取同标签的文章
    if (count($related_posts) < $limit && !empty($tags)) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit - count($related_posts),
            'post__not_in' => array_merge(array($post_id), wp_list_pluck($related_posts, 'ID')),
            'orderby' => 'rand',
            'tag__in' => $tags,
        );
        
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $related_posts[] = get_post();
            }
            wp_reset_postdata();
        }
    }
    
    // 4. 如果还是不够，随机获取其他文章
    if (count($related_posts) < $limit) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit - count($related_posts),
            'post__not_in' => array_merge(array($post_id), wp_list_pluck($related_posts, 'ID')),
            'orderby' => 'rand',
        );
        
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $related_posts[] = get_post();
            }
            wp_reset_postdata();
        }
    }
    
    return $related_posts;
}

/**
 * 计算文章中的图片数量
 *
 * @param int  $post_id 文章ID，默认为当前文章
 * @param bool $include_featured 是否包含特色图片
 * @return int 图片数量
 */
function folio_get_post_image_count($post_id = null, $include_featured = false) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }

    $content = get_post_field('post_content', $post_id);
    $image_count = 0;

    if ($include_featured && has_post_thumbnail($post_id)) {
        $image_count++;
    }

    // 计算 <img> 标签数量
    $image_count += preg_match_all('/<img[^>]+>/i', $content);

    // 计算 WordPress 图库短代码中的图片
    if (preg_match_all('/\[gallery[^\]]*ids="([^"]+)"[^\]]*\]/i', $content, $matches)) {
        foreach ($matches[1] as $ids) {
            $image_count += count(explode(',', $ids));
        }
    }
    
    return $image_count;
}
/**
 * 渲染会员升级卡片
 * 
 * @param string $level 会员等级 (vip|svip)
 * @param array $features 功能特性列表
 * @param string $price 价格
 * @param string $duration 时长
 */
function folio_render_membership_card($level, $features = array(), $price = '', $duration = '') {
    $levels = folio_get_membership_levels();
    $level_info = isset($levels[$level]) ? $levels[$level] : $levels['vip'];
    $user_membership = folio_get_user_membership();
    $is_current = $user_membership['level'] === $level;
    
    $default_features = array(
        'vip' => array(
            __('View VIP exclusive content', 'folio'),
            __('Ad-free browsing experience', 'folio'),
            __('Priority customer support', 'folio'),
            __('Exclusive member badge', 'folio')
        ),
        'svip' => array(
            __('View all exclusive content', 'folio'),
            __('Ad-free browsing experience', 'folio'),
            __('Priority customer support', 'folio'),
            __('Exclusive SVIP badge', 'folio'),
            __('Exclusive high-quality resources', 'folio'),
            __('Early access to new features', 'folio')
        )
    );
    
    if (empty($features)) {
        $features = isset($default_features[$level]) ? $default_features[$level] : array();
    }
    ?>
    <div class="membership-card membership-card-<?php echo esc_attr($level); ?> <?php echo $is_current ? 'membership-card-current' : ''; ?>">
        <div class="membership-card-header">
            <div class="membership-card-badge">
                <?php echo $level_info['icon']; ?>
            </div>
            <h3 class="membership-card-title"><?php echo esc_html($level_info['name']); ?></h3>
            <?php if ($price) : ?>
            <div class="membership-card-price">
                <span class="price-amount"><?php echo esc_html($price); ?></span>
                <?php if ($duration) : ?>
                <span class="price-duration">/ <?php echo esc_html($duration); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="membership-card-features">
            <?php foreach ($features as $feature) : ?>
            <div class="membership-feature">
                <svg class="feature-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                <span><?php echo esc_html($feature); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="membership-card-action">
            <?php if ($is_current) : ?>
            <button class="membership-btn membership-btn-current" disabled>
                <?php esc_html_e('Current Level', 'folio'); ?>
            </button>
            <?php else : ?>
            <button class="membership-btn membership-btn-upgrade" data-level="<?php echo esc_attr($level); ?>">
                <?php echo $level === 'svip' ? esc_html__('Upgrade to SVIP', 'folio') : esc_html__('Upgrade to VIP', 'folio'); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

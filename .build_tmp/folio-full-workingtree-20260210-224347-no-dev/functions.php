<?php
/**
 * Folio Theme Functions
 *
 * @package Folio
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define theme constants
define('FOLIO_VERSION', '1.0.0');
define('FOLIO_DIR', get_template_directory());
define('FOLIO_URI', get_template_directory_uri());
define('FOLIO_FRONTEND_LANG_COOKIE', 'folio_frontend_lang');

/**
 * 获取主题资源版本（优先使用文件修改时间）
 */
if (!function_exists('folio_get_asset_version')) {
    function folio_get_asset_version($relative_path) {
        $file_path = FOLIO_DIR . '/' . ltrim($relative_path, '/');
        return file_exists($file_path) ? filemtime($file_path) : FOLIO_VERSION;
    }
}

/**
 * 加载主题样式资源
 */
if (!function_exists('folio_enqueue_theme_style')) {
    function folio_enqueue_theme_style($handle, $relative_path, $deps = array()) {
        $relative_path = ltrim($relative_path, '/');
        wp_enqueue_style(
            $handle,
            FOLIO_URI . '/' . $relative_path,
            $deps,
            folio_get_asset_version($relative_path)
        );
    }
}

/**
 * 加载主题脚本资源
 */
if (!function_exists('folio_enqueue_theme_script')) {
    function folio_enqueue_theme_script($handle, $relative_path, $deps = array(), $in_footer = true) {
        $relative_path = ltrim($relative_path, '/');
        wp_enqueue_script(
            $handle,
            FOLIO_URI . '/' . $relative_path,
            $deps,
            folio_get_asset_version($relative_path),
            $in_footer
        );
    }
}

/**
 * 获取主题设置（请求内缓存）
 */
if (!function_exists('folio_get_theme_options')) {
    function folio_get_theme_options($refresh = false) {
        static $cached_options = null;

        if ($refresh || $cached_options === null) {
            $cached_options = get_option('folio_theme_options', array());
        }

        return is_array($cached_options) ? $cached_options : array();
    }
}

/**
 * Frontend language switching helpers.
 */
if (!function_exists('folio_get_supported_frontend_locales')) {
    function folio_get_supported_frontend_locales() {
        return array('zh_CN', 'en_US');
    }
}

if (!function_exists('folio_get_current_frontend_locale')) {
    function folio_get_current_frontend_locale() {
        $supported = folio_get_supported_frontend_locales();

        if (isset($_GET['lang'])) {
            $requested = sanitize_text_field(wp_unslash($_GET['lang']));
            if (in_array($requested, $supported, true)) {
                return $requested;
            }
        }

        if (isset($_COOKIE[FOLIO_FRONTEND_LANG_COOKIE])) {
            $saved = sanitize_text_field(wp_unslash($_COOKIE[FOLIO_FRONTEND_LANG_COOKIE]));
            if (in_array($saved, $supported, true)) {
                return $saved;
            }
        }

        return get_locale();
    }
}

if (!function_exists('folio_handle_frontend_language_switch')) {
    function folio_handle_frontend_language_switch() {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!isset($_GET['lang'])) {
            return;
        }

        $requested = sanitize_text_field(wp_unslash($_GET['lang']));
        $supported = folio_get_supported_frontend_locales();
        if (!in_array($requested, $supported, true)) {
            return;
        }

        $_COOKIE[FOLIO_FRONTEND_LANG_COOKIE] = $requested;
        setcookie(
            FOLIO_FRONTEND_LANG_COOKIE,
            $requested,
            time() + YEAR_IN_SECONDS,
            COOKIEPATH ? COOKIEPATH : '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }
}

if (!function_exists('folio_filter_frontend_locale')) {
    function folio_filter_frontend_locale($locale) {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return $locale;
        }

        $selected = folio_get_current_frontend_locale();
        $supported = folio_get_supported_frontend_locales();
        if (in_array($selected, $supported, true)) {
            return $selected;
        }

        return $locale;
    }
}

if (!function_exists('folio_apply_frontend_locale')) {
    function folio_apply_frontend_locale() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $selected = folio_get_current_frontend_locale();
        $supported = folio_get_supported_frontend_locales();
        if (!in_array($selected, $supported, true)) {
            return;
        }

        if (function_exists('switch_to_locale')) {
            switch_to_locale($selected);
        }
    }
}

if (!function_exists('folio_get_language_switch_url')) {
    function folio_get_language_switch_url($locale) {
        $supported = folio_get_supported_frontend_locales();
        if (!in_array($locale, $supported, true)) {
            return home_url('/');
        }

        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $current_url = $scheme . $host . $uri;
        $url = add_query_arg('lang', $locale, remove_query_arg('lang', $current_url));

        return esc_url_raw($url);
    }
}

if (!function_exists('folio_format_post_date')) {
    function folio_format_post_date($post_id = null, $format = '') {
        $post_id = $post_id ?: get_the_ID();
        if (!$post_id) {
            return '';
        }

        if (!$format) {
            $locale = function_exists('folio_get_current_frontend_locale') ? folio_get_current_frontend_locale() : get_locale();
            if (strpos((string) $locale, 'zh_') === 0) {
                $format = get_option('date_format');
            } else {
                $format = 'F j, Y';
            }
        }
        $timestamp = get_post_timestamp($post_id);
        if (!$timestamp) {
            return '';
        }

        return wp_date($format, $timestamp);
    }
}

if (!function_exists('folio_get_post_thumbnail_with_fallback')) {
    function folio_get_post_thumbnail_with_fallback($post_id, $size = 'thumbnail', $attr = array()) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return '';
        }

        if (has_post_thumbnail($post_id)) {
            return get_the_post_thumbnail($post_id, $size, $attr);
        }

        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return '';
        }

        if (!preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $post->post_content, $matches)) {
            return '';
        }

        $src = isset($matches[1]) ? esc_url($matches[1]) : '';
        if ($src === '') {
            return '';
        }

        $class = '';
        if (isset($attr['class']) && is_string($attr['class'])) {
            $class = trim($attr['class']);
        }

        $alt = get_the_title($post_id);
        return sprintf(
            '<img src="%s" alt="%s"%s loading="lazy" decoding="async">',
            $src,
            esc_attr($alt),
            $class !== '' ? ' class="' . esc_attr($class) . '"' : ''
        );
    }
}

/**
 * Theme Setup
 */
function folio_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));
    add_theme_support('customize-selective-refresh-widgets');

    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Navigation', 'folio'),
    ));

    // Add custom image sizes
    add_image_size('post-card', 600, 800, true);
    add_image_size('post-hero', 1200, 600, true);
    add_image_size('post-full', 1200, 800, true);

    // Load text domain
    load_theme_textdomain('folio', FOLIO_DIR . '/languages');

    // Runtime fallback: explicitly load locale file to avoid environment-specific JIT mismatches.
    $locale = is_admin() ? get_user_locale() : folio_get_current_frontend_locale();
    $lang_dir = trailingslashit(FOLIO_DIR) . 'languages/';
    $candidates = array(
        $lang_dir . 'folio-' . $locale . '.mo',
        $lang_dir . $locale . '.mo',
    );

    foreach ($candidates as $mofile) {
        if (file_exists($mofile)) {
            unload_textdomain('folio');
            load_textdomain('folio', $mofile);
            break;
        }
    }
}

/**
 * 禁用 WordPress 默认的站点图标输出
 * 因为我们已经在 header.php 中手动输出了 favicon
 */
if (!function_exists('folio_disable_wp_site_icon_output')) {
    function folio_disable_wp_site_icon_output() {
        remove_action('wp_head', 'wp_site_icon', 99);
    }
}

/**
 * 加载字体资源
 */
if (!function_exists('folio_enqueue_font_assets')) {
    function folio_enqueue_font_assets() {
        // Web Fonts - 使用国内镜像或本地字体
        $font_source = apply_filters('folio_font_source', 'loli'); // Options: google, loli, local

        switch ($font_source) {
            case 'google':
                wp_enqueue_style(
                    'mpb-google-fonts',
                    'https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Roboto+Condensed:wght@400;700&display=swap',
                    array(),
                    null
                );
                break;

            case 'loli':
                wp_enqueue_style(
                    'mpb-google-fonts',
                    'https://fonts.loli.net/css2?family=Oswald:wght@400;500;700&family=Roboto+Condensed:wght@400;700&display=swap',
                    array(),
                    null
                );
                break;

            case 'local':
            default:
                folio_enqueue_theme_style('mpb-local-fonts', 'assets/css/fonts.css');
                break;
        }
    }
}

/**
 * 加载基础样式
 */
if (!function_exists('folio_enqueue_base_styles')) {
    function folio_enqueue_base_styles() {
        folio_enqueue_theme_style('tailwindcss', 'assets/css/tailwind.css');

        $style_file = get_stylesheet_directory() . '/style.css';
        $style_version = file_exists($style_file) ? filemtime($style_file) : FOLIO_VERSION;
        wp_enqueue_style('mpb-style', get_stylesheet_uri(), array(), $style_version);

        folio_enqueue_theme_style('mpb-theme', 'assets/css/theme.css', array('mpb-style'));
        folio_enqueue_theme_style('folio-membership-content', 'assets/css/membership-content.css', array('mpb-theme'));
        folio_enqueue_theme_style('folio-notifications', 'assets/css/notifications.css', array('mpb-theme'));
    }
}

/**
 * 按页面条件加载样式
 */
if (!function_exists('folio_enqueue_conditional_styles')) {
    function folio_enqueue_conditional_styles() {
        if (!class_exists('folio_User_Center')) {
            return;
        }

        $user_center = folio_User_Center::get_instance();
        if ($user_center->is_user_center_page()) {
            folio_enqueue_theme_style('folio-user-center', 'assets/css/user-center.css', array('mpb-theme', 'folio-notifications'));
        }
    }
}

/**
 * 加载主题初始化脚本
 */
if (!function_exists('folio_enqueue_theme_init_scripts')) {
    function folio_enqueue_theme_init_scripts() {
        // Sun Times Calculator (必须在 theme-init.js 之前加载)
        folio_enqueue_theme_script('folio-sun-times', 'assets/js/sun-times.js', array(), false);

        // Theme Init JavaScript (必须在 head 中尽早加载，防止闪烁)
        // 注意：theme.js 由 Script Manager 统一管理，这里不再重复加载
        folio_enqueue_theme_script('folio-theme-init', 'assets/js/theme-init.js', array('folio-sun-times'), false);
    }
}

/**
 * 本地化主题初始化配置
 */
if (!function_exists('folio_localize_theme_init_script')) {
    function folio_localize_theme_init_script() {
        $theme_options = folio_get_theme_options();
        $default_theme = isset($theme_options['theme_mode']) ? $theme_options['theme_mode'] : 'auto';
        $sunrise_time = isset($theme_options['sunrise_time']) ? $theme_options['sunrise_time'] : '06:00';
        $sunset_time = isset($theme_options['sunset_time']) ? $theme_options['sunset_time'] : '18:00';

        wp_localize_script('folio-theme-init', 'folioThemeInit', array(
            'defaultTheme' => $default_theme,
            'autoSunsetEnabled' => ($default_theme === 'auto'),
            'sunriseTime' => $sunrise_time,
            'sunsetTime' => $sunset_time
        ));
    }
}

/**
 * Enqueue Scripts and Styles
 */
function folio_enqueue_assets() {
    folio_enqueue_font_assets();
    folio_enqueue_base_styles();
    folio_enqueue_conditional_styles();
    folio_enqueue_theme_init_scripts();
    folio_localize_theme_init_script();
}

/**
 * Register Widget Areas
 */
function folio_widgets_init() {
    // 页脚小工具区域
    register_sidebar(array(
        'name'          => __('Footer Area', 'folio'),
        'id'            => 'footer-widgets',
        'description'   => __('Footer widget area', 'folio'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
    
    // 文章页侧边栏小工具区域
    register_sidebar(array(
        'name'          => __('Single Post Sidebar', 'folio'),
        'id'            => 'single-post-sidebar',
        'description'   => __('Right sidebar widget area for single post page', 'folio'),
        'before_widget' => '<div id="%1$s" class="widget %2$s mb-8">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title text-xs uppercase font-bold tracking-widest mb-4 text-gray-700">',
        'after_title'   => '</h3>',
    ));
}

/**
 * Include required files
 */
function folio_include_required_files() {
    require_once FOLIO_DIR . '/inc/template-tags.php';
    require_once FOLIO_DIR . '/inc/customizer.php';

    // 性能优化
    require_once FOLIO_DIR . '/inc/class-theme-optimize.php';

    // 内置 SEO
    require_once FOLIO_DIR . '/inc/class-theme-seo.php';

    // AI API管理器（必须在其他AI类之前加载）
    require_once FOLIO_DIR . '/inc/class-ai-api-manager.php';

    // 文章统计
    require_once FOLIO_DIR . '/inc/class-post-stats.php';

    // 会员系统 - 安全版本
    require_once FOLIO_DIR . '/inc/class-membership-safe.php';

    // 会员系统管理面板
    if (is_admin()) {
        require_once FOLIO_DIR . '/inc/class-membership-admin.php';
    }

    // 用户中心
    require_once FOLIO_DIR . '/inc/class-user-center.php';

    // 主题设置页面
    require_once FOLIO_DIR . '/inc/class-theme-options.php';

    // AI内容生成器
    require_once FOLIO_DIR . '/inc/class-ai-content-generator.php';

    // 主题功能增强
    require_once FOLIO_DIR . '/inc/class-theme-enhancements.php';

    // Schema.org 结构化数据
    require_once FOLIO_DIR . '/inc/class-schema-markup.php';

    // 图片Alt文本优化
    require_once FOLIO_DIR . '/inc/class-image-alt-optimizer.php';

    // 性能监控
    require_once FOLIO_DIR . '/inc/class-performance-monitor.php';

    // 图片优化
    require_once FOLIO_DIR . '/inc/class-image-optimizer.php';

    // 会员专属内容 - 增强版
    require_once FOLIO_DIR . '/inc/class-premium-content-enhanced.php';

    // 文章页侧边栏小工具
    require_once FOLIO_DIR . '/inc/class-post-sidebar-widgets.php';

    // 文章保护管理器 - 内容过滤和权限控制
    require_once FOLIO_DIR . '/inc/class-article-protection-manager.php';

    // 增强的会员保护元框
    require_once FOLIO_DIR . '/inc/class-membership-meta-box.php';

    // 前端权限提示组件
    require_once FOLIO_DIR . '/inc/class-frontend-components.php';

    // 样式管理器 - 统一管理CSS文件加载
    require_once FOLIO_DIR . '/inc/class-style-manager.php';

    // 脚本管理器 - 统一管理JavaScript文件加载
    require_once FOLIO_DIR . '/inc/class-script-manager.php';

    // 辅助函数管理器 - 统一管理辅助函数，避免重复定义
    require_once FOLIO_DIR . '/inc/class-helper-manager.php';

    // 安全防护机制管理器
    require_once FOLIO_DIR . '/inc/class-security-protection-manager.php';
    require_once FOLIO_DIR . '/inc/class-operations-report.php';

    // 安全管理页面
    if (is_admin()) {
        require_once FOLIO_DIR . '/inc/class-security-admin.php';
    }

    // 编辑器导航增强（上一篇文章/下一篇文章按钮）
    if (is_admin()) {
        require_once FOLIO_DIR . '/inc/class-editor-navigation.php';
    }

    // 后台文章列表增强（特色图像列）
    if (is_admin()) {
        require_once FOLIO_DIR . '/inc/class-admin-posts-list.php';
    }

    // SMTP 邮件发送类（必须在通知系统之前加载）
    require_once FOLIO_DIR . '/inc/class-smtp-mailer.php';

    // 通知系统核心类（必须在管理页面之前加载）
    require_once FOLIO_DIR . '/inc/class-membership-notifications.php';

    // 通知系统管理页面
    require_once FOLIO_DIR . '/inc/class-notification-admin.php';

    // 通知小工具
    require_once FOLIO_DIR . '/inc/class-notification-widget.php';

    // 会员内容统计分析系统
    require_once FOLIO_DIR . '/inc/class-membership-analytics.php';

    // 会员内容SEO优化
    require_once FOLIO_DIR . '/inc/class-membership-seo.php';

    // 会员内容站点地图增强
    require_once FOLIO_DIR . '/inc/class-membership-sitemap.php';

    // SEO管理界面
    if (is_admin()) {
        require_once FOLIO_DIR . '/inc/class-seo-admin.php';
    }

    // 维护模式
    require_once FOLIO_DIR . '/inc/class-maintenance-mode.php';

    // 性能缓存管理器
    require_once FOLIO_DIR . '/inc/class-performance-cache-manager.php';

    // 前端资源优化器
    require_once FOLIO_DIR . '/inc/class-frontend-optimizer.php';

    // 统一性能与缓存管理页面
    if (is_admin()) {
        require_once FOLIO_DIR . '/inc/class-unified-performance-admin.php';
    }

    // 缓存优化配置（基于测试结果应用）
    require_once FOLIO_DIR . '/inc/cache-optimized-config.php';

    // 缓存管理页面
    if (is_admin()) {
        // Memcached辅助功能（必须先加载）
        require_once FOLIO_DIR . '/inc/memcached-helper.php';
        
        // 缓存文件管理器
        require_once FOLIO_DIR . '/inc/class-cache-file-manager.php';
        
        // 缓存健康检查器
        require_once FOLIO_DIR . '/inc/class-cache-health-checker.php';
        
        // 缓存后端验证器
        require_once FOLIO_DIR . '/inc/class-cache-backend-validator.php';
        
        // 缓存自动验证系统
        require_once FOLIO_DIR . '/inc/cache-auto-validation.php';
    }
}
folio_include_required_files();

/**
 * 初始化主题功能类
 */
function folio_init_classes() {
    // 初始化安全版本的会员系统
    if (class_exists('folio_Membership_Safe')) {
        folio_Membership_Safe::get_instance();
    }
    
    // 应用缓存优化配置（基于测试结果）- 仅在前端首次加载时应用
    if (class_exists('folio_Cache_Optimized_Config') && !is_admin() && !get_transient('folio_cache_optimized_applied')) {
        // 启用优化配置标志
        if (!defined('FOLIO_APPLY_OPTIMIZED_CONFIG')) {
            define('FOLIO_APPLY_OPTIMIZED_CONFIG', true);
        }
        
        // 应用所有优化（仅在前端首次加载时）
        folio_Cache_Optimized_Config::apply_all_optimizations();
        
        // 设置标记，避免重复应用（24小时有效）
        set_transient('folio_cache_optimized_applied', true, 24 * HOUR_IN_SECONDS);
        
        // 记录优化应用
        if (WP_DEBUG) {
            error_log('Folio Cache: Applied optimized configuration based on 96.8% success rate test results');
        }
    }
    
    // 初始化缓存管理类（仅在管理后台）
    if (is_admin()) {
        
        // 缓存文件管理器
        if (class_exists('folio_Cache_File_Manager')) {
            global $folio_cache_file_manager;
            $folio_cache_file_manager = new folio_Cache_File_Manager();
        }
        
        // 缓存健康检查器
        if (class_exists('folio_Cache_Health_Checker')) {
            global $folio_cache_health_checker;
            $folio_cache_health_checker = new folio_Cache_Health_Checker();
        }
    }
    

}

/**
 * 刷新重写规则（主题激活时调用）
 */
function folio_flush_rewrite_rules() {
    // 刷新用户中心的重写规则
    if (class_exists('folio_User_Center')) {
        $user_center = folio_User_Center::get_instance();
        $user_center->add_rewrite_rules();
    }
    flush_rewrite_rules();
}

/**
 * Set posts per page for blog archive
 */
function folio_posts_per_page($query) {
    if (!is_admin() && $query->is_main_query()) {
        // 处理首页（最新文章）和归档页面
        if (is_home() || is_front_page() || is_archive()) {
            // 从主题设置读取每页文章数，默认为 12
            $theme_options = folio_get_theme_options();
            $posts_per_page = isset($theme_options['posts_per_page']) && $theme_options['posts_per_page'] > 0 
                ? absint($theme_options['posts_per_page']) 
                : 12;
            
            // 首页如果显示 Brain Powered Fun 六边形，查询数量需要减1
            // 因为六边形占一个位置，在分页内
            if ((is_home() || is_front_page())) {
                $show_brain_hexagon = !isset($theme_options['show_brain_hexagon']) || !empty($theme_options['show_brain_hexagon']);
                if ($show_brain_hexagon) {
                    $posts_per_page = max(1, $posts_per_page - 1);
                }
            }
            
            $query->set('posts_per_page', $posts_per_page);
            // 确保查询的是 post 类型
            if (is_home() || is_front_page()) {
                $query->set('post_type', 'post');
                $query->set('post_status', 'publish');
            }
        }
    }
}

/**
 * 兼容旧函数名
 */
if (!function_exists('Folio_posts_per_page')) {
    function Folio_posts_per_page($query) {
        folio_posts_per_page($query);
    }
}

/**
 * 限制未登录用户访问分页（例如 /cosplay/page/2 等）
 * 未登录时访问任意列表的第 2 页及之后，统一跳转到登录页
 */
function folio_restrict_paged_for_guests() {
    if (is_admin()) {
        return;
    }

    // 仅针对前端主查询的分页页面
    if (!is_user_logged_in() && is_paged()) {
        $login_url = home_url('user-center/login');
        $current_request = '';
        if (isset($GLOBALS['wp']) && isset($GLOBALS['wp']->request) && is_string($GLOBALS['wp']->request)) {
            $current_request = $GLOBALS['wp']->request;
        }

        // 避免重定向死循环：如果当前已经在登录页就不再跳转
        if ($current_request !== '' && trailingslashit($login_url) === trailingslashit(home_url(add_query_arg(array(), $current_request)))) {
            return;
        }

        wp_safe_redirect($login_url, 302);
        exit;
    }
}

/**
 * Add body classes
 */
function folio_body_classes($classes) {
    if (is_singular('post')) {
        $classes[] = 'single-post-page';
    }
    if (is_home() || is_archive()) {
        $classes[] = 'blog-archive-page';
    }
    return $classes;
}

/**
 * 注册内容模型相关 hooks
 */
if (!function_exists('folio_register_content_model_hooks')) {
    function folio_register_content_model_hooks() {
        add_action('after_setup_theme', 'folio_theme_setup');
    }
}

/**
 * 注册资源相关 hooks
 */
if (!function_exists('folio_register_asset_hooks')) {
    function folio_register_asset_hooks() {
        add_action('wp_head', 'folio_disable_wp_site_icon_output', 1);
        add_action('wp_enqueue_scripts', 'folio_enqueue_assets');
        add_action('widgets_init', 'folio_widgets_init');
    }
}

/**
 * 注册路由与查询相关 hooks
 */
if (!function_exists('folio_register_route_hooks')) {
    function folio_register_route_hooks() {
        add_action('after_switch_theme', 'folio_flush_rewrite_rules');
        add_action('pre_get_posts', 'folio_posts_per_page');
        add_action('template_redirect', 'folio_restrict_paged_for_guests');
    }
}

/**
 * 注册兼容与运行时 hooks
 */
if (!function_exists('folio_register_compat_hooks')) {
    function folio_register_compat_hooks() {
        add_action('after_setup_theme', 'folio_apply_frontend_locale', 0);
        add_action('init', 'folio_init_classes');
        add_action('init', 'folio_handle_frontend_language_switch', 1);
        add_filter('body_class', 'folio_body_classes');
        add_filter('locale', 'folio_filter_frontend_locale', 20);
        add_filter('determine_locale', 'folio_filter_frontend_locale', 20);
    }
}

/**
 * 启动主题 hooks
 */
if (!function_exists('folio_bootstrap_hooks')) {
    function folio_bootstrap_hooks() {
        folio_register_content_model_hooks();
        folio_register_asset_hooks();
        folio_register_route_hooks();
        folio_register_compat_hooks();
    }
}
folio_bootstrap_hooks();

/**
 * 确保 folio_get_post_premium_info 函数始终可用
 * 这是一个备用定义，防止函数未定义的错误
 * 移动到文件早期位置以确保在模板加载前可用
 */
if (!function_exists('folio_get_post_premium_info')) {
    function folio_get_post_premium_info($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        // 确保文章ID有效
        if (!$post_id) {
            return array(
                'is_premium' => false,
                'required_level' => null,
                'level_name' => null,
                'can_access' => true
            );
        }
        
        // 尝试使用新的保护信息函数
        if (function_exists('folio_get_article_protection_info') && function_exists('folio_can_user_access_article')) {
            try {
                $protection_info = folio_get_article_protection_info($post_id);
                $can_access = folio_can_user_access_article($post_id);
                
                if (!$protection_info['is_protected']) {
                    return array(
                        'is_premium' => false,
                        'required_level' => null,
                        'level_name' => null,
                        'can_access' => true
                    );
                }
                
                $required_level = $protection_info['required_level'];
                $level_name = $required_level === 'svip' ? 'SVIP' : 'VIP';
                
                return array(
                    'is_premium' => true,
                    'required_level' => $required_level,
                    'level_name' => $level_name,
                    'can_access' => $can_access,
                    'preview_mode' => isset($protection_info['preview_mode']) ? $protection_info['preview_mode'] : 'auto'
                );
            } catch (Exception $e) {
                // 如果新函数出错，回退到旧方式
                error_log('folio_get_post_premium_info error: ' . $e->getMessage());
            }
        }
        
        // 回退到旧的元数据方式
        $is_premium = get_post_meta($post_id, '_folio_premium_content', true);
        
        if (!$is_premium) {
            return array(
                'is_premium' => false,
                'required_level' => null,
                'level_name' => null,
                'can_access' => true
            );
        }
        
        $required_level = get_post_meta($post_id, '_folio_required_level', true) ?: 'vip';
        $level_name = $required_level === 'svip' ? 'SVIP' : 'VIP';
        
        // 检查用户访问权限
        $can_access = true;
        $current_user_id = get_current_user_id();
        
        if (function_exists('folio_can_user_access_article')) {
            try {
                $can_access = folio_can_user_access_article($post_id, $current_user_id);
            } catch (Exception $e) {
                error_log('folio_can_user_access_article error: ' . $e->getMessage());
            }
        } elseif (class_exists('folio_Premium_Content_Enhanced')) {
            try {
                $instance = new folio_Premium_Content_Enhanced();
                $can_access = $instance->can_user_access($post_id, $current_user_id);
            } catch (Exception $e) {
                error_log('folio_Premium_Content_Enhanced error: ' . $e->getMessage());
            }
        }
        
        return array(
            'is_premium' => true,
            'required_level' => $required_level,
            'level_name' => $level_name,
            'can_access' => $can_access,
            'preview_mode' => get_post_meta($post_id, '_folio_preview_mode', true) ?: 'auto'
        );
    }
}

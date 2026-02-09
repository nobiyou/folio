<?php
/**
 * Style Manager
 * 
 * 统一管理主题样式文件的加载，替代内联样式
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Style_Manager {

    // 样式版本号
    const STYLE_VERSION = '1.0.0';

    public function __construct() {
        // 前端样式加载（默认关闭，避免与 functions.php 的样式链重复）
        if (apply_filters('folio_enable_style_manager_frontend', false)) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        }
        
        // 管理后台样式加载
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // 登录页面样式加载
        add_action('login_enqueue_scripts', array($this, 'enqueue_login_styles'));
    }

    /**
     * 加载前端样式
     */
    public function enqueue_frontend_styles() {
        // 优先加载合并的核心样式文件（包含所有通用样式）
        $consolidated_file = self::get_minified_style('consolidated-styles.css');
        wp_enqueue_style(
            'folio-consolidated-styles',
            get_template_directory_uri() . '/assets/css/' . $consolidated_file,
            array(),
            self::STYLE_VERSION
        );

        // 只在需要时加载特定的补充样式
        
        // 前端组件特定样式 - 只在文章页面加载非重复的样式
        if (is_singular('post') && $this->has_specific_components()) {
            wp_enqueue_style(
                'folio-frontend-components-extra',
                get_template_directory_uri() . '/assets/css/frontend-components-extra.css',
                array('folio-consolidated-styles'),
                self::STYLE_VERSION
            );
        }

        // 会员内容特定样式 - 只加载非重复的特殊功能
        if (($this->has_membership_content() || $this->is_article_list_page()) && $this->has_advanced_membership_features()) {
            wp_enqueue_style(
                'folio-membership-content-extra',
                get_template_directory_uri() . '/assets/css/membership-content-extra.css',
                array('folio-consolidated-styles'),
                self::STYLE_VERSION
            );
        }

        // 用户界面特定样式 - 只加载非重复的界面组件
        if (($this->is_user_center_page() || $this->has_membership_showcase()) && $this->has_advanced_ui_features()) {
            wp_enqueue_style(
                'folio-user-interface-extra',
                get_template_directory_uri() . '/assets/css/user-interface-extra.css',
                array('folio-consolidated-styles'),
                self::STYLE_VERSION
            );
        }

        // 前端小工具特定样式 - 只加载非重复的小工具功能
        if (($this->has_notifications() || $this->has_widgets()) && $this->has_advanced_widgets()) {
            wp_enqueue_style(
                'folio-frontend-widgets-extra',
                get_template_directory_uri() . '/assets/css/frontend-widgets-extra.css',
                array('folio-consolidated-styles'),
                self::STYLE_VERSION
            );
        }

        // 维护模式样式 - 只在维护模式页面加载
        if ($this->is_maintenance_mode()) {
            wp_enqueue_style(
                'folio-maintenance-mode',
                get_template_directory_uri() . '/assets/css/maintenance-mode.css',
                array(),
                self::STYLE_VERSION
            );
        }

        // 性能监控样式 - 只在启用性能监控时加载
        if ($this->is_performance_monitor_enabled()) {
            wp_enqueue_style(
                'folio-performance-monitor',
                get_template_directory_uri() . '/assets/css/performance-monitor.css',
                array(),
                self::STYLE_VERSION
            );
        }

        // 前端内联样式 - 替代各种内联样式
        wp_enqueue_style(
            'folio-frontend-inline-styles',
            get_template_directory_uri() . '/assets/css/frontend-inline-styles.css',
            array(),
            self::STYLE_VERSION
        );
    }

    /**
     * 加载管理后台样式
     */
    public function enqueue_admin_styles($hook) {
        // 获取当前屏幕信息
        $screen = get_current_screen();
        
        // 管理后台通用样式
        wp_enqueue_style(
            'folio-admin-common',
            get_template_directory_uri() . '/assets/css/admin/admin-common.css',
            array(),
            self::STYLE_VERSION
        );

        // 根据页面加载特定样式
        switch ($screen->id) {
            case 'folio_page_membership-admin':
            case 'folio_page_security-admin':
            case 'folio_page_analytics':
            case 'folio_page_notifications':
                // 这些页面使用通用的admin-common.css样式
                break;

            case 'tools_page_folio-performance-cache':
                // 统一性能与缓存管理页面使用自己的样式文件
                wp_enqueue_style(
                    'folio-unified-performance-admin',
                    get_template_directory_uri() . '/assets/css/unified-performance-admin.css',
                    array('folio-admin-common'),
                    self::STYLE_VERSION
                );
                break;
        }

        // AI内容生成器使用内联样式，无需单独CSS文件
    }

    /**
     * 加载登录页面样式
     */
    public function enqueue_login_styles() {
        // 登录页面使用主题默认样式，无需单独CSS文件
        // 如需自定义登录样式，可以在这里添加内联样式
    }

    /**
     * 检查是否有会员内容
     */
    private function has_membership_content() {
        global $post;
        
        if (!$post) {
            return false;
        }

        // 检查当前文章是否有会员保护
        $is_protected = get_post_meta($post->ID, '_folio_premium_content', true);
        
        // 或者检查是否在会员相关页面
        $membership_pages = array('membership', 'upgrade', 'pricing');
        $current_page = get_query_var('pagename');
        
        return $is_protected || in_array($current_page, $membership_pages);
    }

    /**
     * 检查是否是用户中心页面
     */
    private function is_user_center_page() {
        // 检查是否在用户中心页面
        $user_center_pages = array('user-center', 'profile', 'dashboard', 'account');
        $current_page = get_query_var('pagename');
        $request_uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        return in_array($current_page, $user_center_pages) || 
               ($request_uri !== '' && strpos($request_uri, '/user-center') !== false);
    }

    /**
     * 检查是否有通知功能
     */
    private function has_notifications() {
        // 检查用户是否登录（通知功能需要登录）
        if (!is_user_logged_in()) {
            return false;
        }

        // 检查是否启用了通知功能
        $notifications_enabled = get_option('folio_notifications_enabled', true);
        
        return $notifications_enabled;
    }

    /**
     * 检查是否有小工具
     */
    private function has_widgets() {
        // 检查是否有活跃的小工具
        return is_active_sidebar('sidebar-1') || 
               is_active_sidebar('footer-widgets') ||
               is_user_logged_in(); // 登录用户可能看到通知小工具
    }

    /**
     * 检查是否是维护模式
     */
    private function is_maintenance_mode() {
        // 检查维护模式是否启用
        $options = get_option('folio_theme_options', array());
        $maintenance_enabled = isset($options['maintenance_mode']) ? $options['maintenance_mode'] : 0;
        
        // 只有在维护模式启用且当前用户不是管理员时才加载样式
        return $maintenance_enabled && !current_user_can('manage_options');
    }

    /**
     * 检查是否启用性能监控
     */
    private function is_performance_monitor_enabled() {
        // 检查是否启用了性能监控
        $options = get_option('folio_theme_options', array());
        $monitor_enabled = isset($options['enable_performance_monitor']) ? $options['enable_performance_monitor'] : 0;
        
        // 只对管理员显示性能监控
        return $monitor_enabled && current_user_can('manage_options') && !is_admin();
    }

    /**
     * 检查是否是文章列表页面
     */
    private function is_article_list_page() {
        // 检查是否在首页、归档页、搜索页等可能显示文章列表的页面
        return is_home() || is_archive() || is_search() || is_category() || is_tag();
    }

    /**
     * 检查是否有会员展示内容
     */
    private function has_membership_showcase() {
        // 检查是否在会员相关页面或包含会员展示的页面
        $membership_showcase_pages = array('membership', 'pricing', 'plans', 'upgrade');
        $current_page = get_query_var('pagename');
        
        // 检查页面内容是否包含会员展示短代码或模板
        global $post;
        if ($post && (
            strpos($post->post_content, 'membership-showcase') !== false ||
            strpos($post->post_content, '[membership_plans]') !== false ||
            in_array($current_page, $membership_showcase_pages)
        )) {
            return true;
        }
        
        return false;
    }

    /**
     * 检查是否有特定组件（需要额外样式）
     */
    private function has_specific_components() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // 检查是否有高级组件功能
        return (
            strpos($post->post_content, 'folio-tooltip-enhanced') !== false ||
            strpos($post->post_content, 'folio-badge-animated') !== false ||
            get_post_meta($post->ID, '_folio_has_advanced_components', true)
        );
    }

    /**
     * 检查是否有高级会员功能
     */
    private function has_advanced_membership_features() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // 检查是否使用了高级会员功能
        return (
            get_post_meta($post->ID, '_folio_custom_preview', true) ||
            strpos($post->post_content, 'upgrade_prompt') !== false ||
            strpos($post->post_content, 'membership_card') !== false
        );
    }

    /**
     * 检查是否有高级UI功能
     */
    private function has_advanced_ui_features() {
        // 检查是否在特定的高级UI页面
        $advanced_pages = array('profile-advanced', 'dashboard-pro', 'membership-analytics');
        $current_page = get_query_var('pagename');
        
        return in_array($current_page, $advanced_pages) || 
               (is_user_logged_in() && $this->user_has_advanced_features());
    }

    /**
     * 检查是否有高级小工具
     */
    private function has_advanced_widgets() {
        // 检查是否启用了高级通知功能
        $options = get_option('folio_theme_options', array());
        $advanced_notifications = isset($options['advanced_notifications']) ? $options['advanced_notifications'] : 0;
        
        return $advanced_notifications || $this->has_email_templates();
    }

    /**
     * 检查用户是否有高级功能权限
     */
    private function user_has_advanced_features() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_membership = function_exists('folio_get_user_membership') ? folio_get_user_membership() : array();
        return isset($user_membership['is_vip']) && $user_membership['is_vip'];
    }

    /**
     * 检查是否有邮件模板功能
     */
    private function has_email_templates() {
        // 检查是否启用了邮件通知功能
        $options = get_option('folio_theme_options', array());
        return isset($options['email_notifications']) && $options['email_notifications'];
    }

    /**
     * 获取样式文件路径
     */
    public static function get_style_url($file) {
        return get_template_directory_uri() . '/assets/css/' . $file;
    }

    /**
     * 获取样式文件的本地路径
     */
    public static function get_style_path($file) {
        return get_template_directory() . '/assets/css/' . $file;
    }

    /**
     * 检查样式文件是否存在
     */
    public static function style_exists($file) {
        return file_exists(self::get_style_path($file));
    }

    /**
     * 动态加载样式文件
     */
    public static function enqueue_style_if_exists($handle, $file, $deps = array(), $version = null) {
        if (self::style_exists($file)) {
            wp_enqueue_style(
                $handle,
                self::get_style_url($file),
                $deps,
                $version ?: self::STYLE_VERSION
            );
            return true;
        }
        return false;
    }

    /**
     * 获取压缩版本的样式文件
     */
    public static function get_minified_style($file) {
        // 在生产环境中使用压缩版本
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            $minified = str_replace('.css', '.min.css', $file);
            if (self::style_exists($minified)) {
                return $minified;
            }
        }
        return $file;
    }

    /**
     * 批量加载样式文件
     */
    public static function enqueue_styles($styles) {
        foreach ($styles as $handle => $config) {
            $file = isset($config['file']) ? $config['file'] : '';
            $deps = isset($config['deps']) ? $config['deps'] : array();
            $version = isset($config['version']) ? $config['version'] : self::STYLE_VERSION;
            $condition = isset($config['condition']) ? $config['condition'] : true;

            if ($condition && $file) {
                $minified_file = self::get_minified_style($file);
                self::enqueue_style_if_exists($handle, $minified_file, $deps, $version);
            }
        }
    }

    /**
     * 清理未使用的样式
     */
    public function cleanup_unused_styles() {
        // 移除不需要的默认WordPress样式
        if (!is_admin()) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('wc-block-style');
        }
    }

    /**
     * 添加样式内联变量
     */
    public static function add_inline_vars($handle, $vars) {
        $css_vars = ':root {';
        foreach ($vars as $name => $value) {
            $css_vars .= "--{$name}: {$value};";
        }
        $css_vars .= '}';
        
        wp_add_inline_style($handle, $css_vars);
    }
}

// 初始化样式管理器
new folio_Style_Manager();

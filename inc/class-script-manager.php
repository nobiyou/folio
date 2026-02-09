<?php
/**
 * Script Manager
 * 
 * 统一管理主题JavaScript文件的加载，优化性能和消除重复
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Script_Manager {

    // 脚本版本号
    const SCRIPT_VERSION = '1.0.0';

    public function __construct() {
        // 前端脚本加载
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // 管理后台脚本加载
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // 登录页面脚本加载
        add_action('login_enqueue_scripts', array($this, 'enqueue_login_scripts'));
        
        // 输出配置变量
        add_action('wp_head', array($this, 'output_config_vars'), 1);
    }

    /**
     * 加载前端脚本
     */
    public function enqueue_frontend_scripts() {
        // 优先加载核心JavaScript文件（包含所有通用功能）
        wp_enqueue_script(
            'folio-core',
            get_template_directory_uri() . '/assets/js/folio-core.js',
            array('jquery'),
            self::SCRIPT_VERSION,
            true
        );

        // 主题核心脚本（依赖 sun-times.js 用于自动日落切换）
        wp_enqueue_script(
            'folio-theme',
            get_template_directory_uri() . '/assets/js/theme.js',
            array('folio-core', 'folio-sun-times'),
            self::SCRIPT_VERSION,
            true
        );

        // 只在需要时加载特定的功能脚本
        
        // 前端组件脚本 - 只在有权限提示的页面加载
        if ($this->has_permission_prompts() || $this->is_article_page()) {
            wp_enqueue_script(
                'folio-frontend-components',
                get_template_directory_uri() . '/assets/js/frontend-components.js',
                array('folio-core'),
                self::SCRIPT_VERSION,
                true
            );
        }

        // 会员内容脚本 - 只在会员相关页面加载
        if ($this->has_membership_content() || $this->is_membership_page()) {
            wp_enqueue_script(
                'folio-premium-content',
                get_template_directory_uri() . '/assets/js/premium-content.js',
                array('folio-core'),
                self::SCRIPT_VERSION,
                true
            );
        }

        // 通知脚本 - 只在登录用户且启用通知时加载
        if ($this->has_notifications()) {
            wp_enqueue_script(
                'folio-notifications',
                get_template_directory_uri() . '/assets/js/notifications.js',
                array('folio-core'),
                self::SCRIPT_VERSION,
                true
            );
        }

        // AI内容生成器脚本 - 只在文章编辑页面加载
        if (is_singular('post') && current_user_can('edit_posts')) {
            wp_enqueue_script(
                'folio-ai-generator',
                get_template_directory_uri() . '/assets/js/ai-generator.js',
                array('folio-core'),
                self::SCRIPT_VERSION,
                true
            );
        }

        // 输出前端配置
        $this->localize_frontend_scripts();
    }

    /**
     * 加载管理后台脚本
     */
    public function enqueue_admin_scripts($hook) {
        // 获取当前屏幕信息
        $screen = get_current_screen();
        
        // 管理后台通用脚本
        wp_enqueue_script(
            'folio-admin-common',
            get_template_directory_uri() . '/assets/js/admin-common.js',
            array('jquery'),
            self::SCRIPT_VERSION,
            true
        );

        // 根据页面加载特定脚本
        switch ($screen->id) {
            case 'tools_page_folio-performance-cache':
                // 统一性能与缓存管理页面的脚本已在对应类中加载
                break;

            case 'folio_page_membership-admin':
                self::enqueue_script_if_exists(
                    'folio-membership-admin',
                    'membership-admin.js',
                    array('folio-admin-common'),
                    self::SCRIPT_VERSION,
                    true
                );
                break;

            case 'folio_page_notifications':
                self::enqueue_script_if_exists(
                    'folio-notifications-admin',
                    'notifications-admin.js',
                    array('folio-admin-common'),
                    self::SCRIPT_VERSION,
                    true
                );
                break;
        }

        // 会员内容元框脚本 - 在文章编辑页面加载
        if (in_array($screen->id, array('post', 'edit-post'))) {
            wp_enqueue_script(
                'folio-membership-metabox',
                get_template_directory_uri() . '/assets/js/membership-metabox.js',
                array('folio-admin-common'),
                self::SCRIPT_VERSION,
                true
            );
        }

        // 主题选项脚本 - 由 class-theme-options.php 单独管理，避免重复加载
        // 注意：主题设置页面的脚本加载已在 Folio_Theme_Options::enqueue_admin_assets() 中处理
        // 这里不再重复加载，避免脚本依赖冲突

        // 输出管理后台配置
        $this->localize_admin_scripts();
    }

    /**
     * 加载登录页面脚本
     */
    public function enqueue_login_scripts() {
        self::enqueue_script_if_exists(
            'folio-login',
            'login.js',
            array('jquery'),
            self::SCRIPT_VERSION,
            true
        );
    }

    /**
     * 输出前端配置变量
     */
    public function output_config_vars() {
        $config = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('folio_nonce'),
            'badgeNonce' => wp_create_nonce('folio_article_badge'), // 文章徽章专用 nonce
            'urls' => array(
                'home' => home_url('/'),
                'login' => wp_login_url(),
                'register' => wp_registration_url(),
                'upgrade' => home_url('/user-center/membership'),
                'profile' => home_url('/user-center/'),
            ),
            'user' => array(
                'isLoggedIn' => is_user_logged_in(),
                'canEdit' => current_user_can('edit_posts'),
                'membership' => $this->get_user_membership_data(),
            ),
            'theme' => array(
                'name' => get_template(),
                'version' => wp_get_theme()->get('Version'),
                'textdomain' => get_template(),
            ),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        );

        echo '<script type="text/javascript">';
        echo 'window.folioConfig = ' . wp_json_encode($config) . ';';
        echo '</script>';
    }

    /**
     * 本地化前端脚本
     */
    private function localize_frontend_scripts() {
        // 前端组件配置
        if (wp_script_is('folio-frontend-components', 'enqueued')) {
            wp_localize_script('folio-frontend-components', 'folioComponents', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('folio_components_nonce'),
                'urls' => array(
                    'upgrade' => home_url('/user-center/membership'),
                    'login' => wp_login_url(),
                    'register' => wp_registration_url(),
                ),
            ));
        }

        // 会员内容配置
        if (wp_script_is('folio-premium-content', 'enqueued')) {
            wp_localize_script('folio-premium-content', 'folioPremium', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('folio_premium_nonce'),
                'membership' => $this->get_user_membership_data(),
            ));
        }

        // 通知配置
        if (wp_script_is('folio-notifications', 'enqueued')) {
            wp_localize_script('folio-notifications', 'folioNotifications', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('folio_notifications_nonce'),
                'interval' => get_option('folio_notification_check_interval', 30000),
            ));
        }

        // 主题脚本配置
        if (wp_script_is('folio-theme', 'enqueued')) {
            wp_localize_script('folio-theme', 'folio_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('folio_ajax_nonce'),
            ));

            // 用户中心相关配置
            wp_localize_script('folio-theme', 'folio_user', array(
                'is_logged_in' => is_user_logged_in(),
                'login_url' => home_url('user-center/login'),
                'user_center_url' => home_url('user-center'),
            ));
        }
    }

    /**
     * 本地化管理后台脚本
     */
    private function localize_admin_scripts() {
        // 性能仪表盘配置
        if (wp_script_is('folio-admin-performance', 'enqueued')) {
            wp_localize_script('folio-admin-performance', 'folioPerformance', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('folio_performance_nonce'),
                'refreshInterval' => 30000,
            ));
        }

        // 会员管理配置
        if (wp_script_is('folio-membership-admin', 'enqueued')) {
            wp_localize_script('folio-membership-admin', 'folioMembershipAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('folio_membership_admin_nonce'),
            ));
        }

        // 会员内容元框配置
        if (wp_script_is('folio-membership-metabox', 'enqueued')) {
            wp_localize_script('folio-membership-metabox', 'folioMetabox', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('folio_metabox_nonce'),
            ));
        }
    }

    /**
     * 检查是否有权限提示
     */
    private function has_permission_prompts() {
        global $post;
        
        if (!$post) {
            return false;
        }

        // 检查当前文章是否有会员保护
        $is_protected = get_post_meta($post->ID, '_folio_premium_content', true);
        
        return $is_protected || is_archive() || is_home();
    }

    /**
     * 检查是否是文章页面
     */
    private function is_article_page() {
        return is_singular('post') || is_page();
    }

    /**
     * 检查是否有会员内容
     */
    private function has_membership_content() {
        // 检查是否在会员相关页面
        $membership_pages = array('membership', 'upgrade', 'pricing');
        $current_page = get_query_var('pagename');
        
        // PHP 8.2 兼容性：确保 REQUEST_URI 是字符串
        $request_uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        return in_array($current_page, $membership_pages) || 
               ($request_uri !== '' && strpos($request_uri, '/user-center') !== false);
    }

    /**
     * 检查是否是会员页面
     */
    private function is_membership_page() {
        return $this->has_membership_content() || 
               is_page_template('page-membership.php') ||
               is_page_template('page-pricing.php');
    }

    /**
     * 检查是否有通知功能（支持未登录用户）
     */
    private function has_notifications() {
        // 检查是否在用户中心页面（包括通知页面）
        global $wp;
        if (isset($wp->query_vars['folio_user_center'])) {
            return true;
        }

        // 检查是否启用了通知功能（现在支持未登录用户）
        $notifications_enabled = get_option('folio_notifications_enabled', true);
        
        return $notifications_enabled;
    }

    /**
     * 获取用户会员数据
     */
    private function get_user_membership_data() {
        if (!is_user_logged_in()) {
            return array(
                'level' => 'free',
                'is_vip' => false,
                'is_svip' => false,
                'expiry' => null,
            );
        }

        $user_id = get_current_user_id();
        $membership_level = get_user_meta($user_id, 'folio_membership_level', true);
        if (!$membership_level) {
            $membership_level = get_user_meta($user_id, '_folio_membership_level', true) ?: 'free';
        }
        
        // 使用日期字符串格式
        $membership_expiry = get_user_meta($user_id, 'folio_membership_expiry', true);

        return array(
            'level' => $membership_level,
            'is_vip' => in_array($membership_level, array('vip', 'svip')),
            'is_svip' => $membership_level === 'svip',
            'expiry' => $membership_expiry,
        );
    }

    /**
     * 获取脚本文件路径
     */
    public static function get_script_url($file) {
        return get_template_directory_uri() . '/assets/js/' . $file;
    }

    /**
     * 获取脚本文件的本地路径
     */
    public static function get_script_path($file) {
        return get_template_directory() . '/assets/js/' . $file;
    }

    /**
     * 检查脚本文件是否存在
     */
    public static function script_exists($file) {
        return file_exists(self::get_script_path($file));
    }

    /**
     * 动态加载脚本文件
     */
    public static function enqueue_script_if_exists($handle, $file, $deps = array(), $version = null, $in_footer = true) {
        if (self::script_exists($file)) {
            wp_enqueue_script(
                $handle,
                self::get_script_url($file),
                $deps,
                $version ?: self::SCRIPT_VERSION,
                $in_footer
            );
            return true;
        }
        return false;
    }

    /**
     * 获取压缩版本的脚本文件
     */
    public static function get_minified_script($file) {
        // 在生产环境中使用压缩版本
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            $minified = str_replace('.js', '.min.js', $file);
            if (self::script_exists($minified)) {
                return $minified;
            }
        }
        return $file;
    }

    /**
     * 批量加载脚本文件
     */
    public static function enqueue_scripts($scripts) {
        foreach ($scripts as $handle => $config) {
            $file = isset($config['file']) ? $config['file'] : '';
            $deps = isset($config['deps']) ? $config['deps'] : array();
            $version = isset($config['version']) ? $config['version'] : self::SCRIPT_VERSION;
            $in_footer = isset($config['in_footer']) ? $config['in_footer'] : true;
            $condition = isset($config['condition']) ? $config['condition'] : true;

            if ($condition && $file) {
                $minified_file = self::get_minified_script($file);
                self::enqueue_script_if_exists($handle, $minified_file, $deps, $version, $in_footer);
            }
        }
    }

    /**
     * 清理未使用的脚本
     */
    public function cleanup_unused_scripts() {
        // 移除不需要的默认WordPress脚本
        if (!is_admin()) {
            // 在前端移除不必要的脚本
            wp_dequeue_script('wp-embed');
            
            // 如果不需要评论功能，移除评论脚本
            if (!is_singular() || !comments_open()) {
                wp_dequeue_script('comment-reply');
            }
        }
    }

    /**
     * 添加脚本内联变量
     */
    public static function add_inline_vars($handle, $vars, $object_name = 'folioVars') {
        wp_localize_script($handle, $object_name, $vars);
    }

    /**
     * 输出内联脚本
     */
    public static function add_inline_script($handle, $script, $position = 'after') {
        wp_add_inline_script($handle, $script, $position);
    }
}

// 初始化脚本管理器
new folio_Script_Manager();

<?php
/**
 * Helper Manager
 * 
 * 统一管理主题辅助函数，避免重复定义和冲突
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Helper_Manager {

    // 单例实例
    private static $instance = null;
    
    // 已注册的辅助函数
    private static $registered_helpers = array();

    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->init_helpers();
        $this->register_ajax_handlers();
    }

    /**
     * 注册 AJAX 处理器
     * 
     * 注意：通知相关的 AJAX 处理器已在 folio_Membership_Notifications 类中注册
     * 这里不再重复注册，避免重复执行
     */
    private function register_ajax_handlers() {
        // 通知相关的 AJAX 处理器已由 folio_Membership_Notifications 类处理
        // 不再重复注册，避免重复执行
    }

    /**
     * 初始化辅助函数
     */
    private function init_helpers() {
        // 注册通知相关辅助函数
        $this->register_notification_helpers();
        
        // 注册性能相关辅助函数
        $this->register_performance_helpers();
        
        // 注册维护模式辅助函数
        $this->register_maintenance_helpers();
        
        // 注册SEO相关辅助函数
        $this->register_seo_helpers();
        
        // 注册管理页面辅助函数
        $this->register_admin_helpers();
    }

    /**
     * 注册通知相关辅助函数
     */
    private function register_notification_helpers() {
        // 避免重复定义
        if (!function_exists('folio_add_notification')) {
            /**
             * 快速添加通知
             */
            function folio_add_notification($user_id, $type, $title, $message, $metadata = null) {
                if (class_exists('folio_Membership_Notifications')) {
                    $notifications = folio_Membership_Notifications::get_instance();
                    return $notifications->add_notification($user_id, $type, $title, $message, $metadata);
                }
                return false;
            }
            self::$registered_helpers[] = 'folio_add_notification';
        }

        if (!function_exists('folio_get_unread_notification_count')) {
            /**
             * 获取用户未读通知数量（包括全局通知）
             */
            function folio_get_unread_notification_count($user_id = null) {
                if (!$user_id) {
                    $user_id = get_current_user_id();
                }
                
                global $wpdb;
                $notification_table = $wpdb->prefix . 'folio_notifications';
                
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $notification_table)) !== $notification_table) {
                    return 0;
                }
                
                // 如果用户已登录，统计用户通知 + 全局通知
                // 如果用户未登录，只统计全局通知
                if ($user_id > 0) {
                    $count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $notification_table WHERE (user_id = %d OR user_id = 0) AND is_read = 0",
                        $user_id
                    ));
                } else {
                    // 未登录用户只统计全局通知
                    $count = $wpdb->get_var(
                        "SELECT COUNT(*) FROM $notification_table WHERE user_id = 0 AND is_read = 0"
                    );
                }
                
                return intval($count);
            }
            self::$registered_helpers[] = 'folio_get_unread_notification_count';
        }

        if (!function_exists('folio_get_user_latest_notifications')) {
            /**
             * 获取用户最新通知（包括全局通知）
             */
            function folio_get_user_latest_notifications($user_id = null, $limit = 5) {
                if (!$user_id) {
                    $user_id = get_current_user_id();
                }
                
                global $wpdb;
                $notification_table = $wpdb->prefix . 'folio_notifications';
                
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $notification_table)) !== $notification_table) {
                    return array();
                }
                
                // 如果用户已登录，获取用户通知 + 全局通知
                // 如果用户未登录，只获取全局通知
                if ($user_id > 0) {
                    $notifications = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM $notification_table 
                         WHERE user_id = %d OR user_id = 0
                         ORDER BY created_at DESC 
                         LIMIT %d",
                        $user_id,
                        $limit
                    ));
                } else {
                    // 未登录用户只获取全局通知
                    $notifications = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM $notification_table 
                         WHERE user_id = 0 
                         ORDER BY created_at DESC 
                         LIMIT %d",
                        $limit
                    ));
                }
                
                return $notifications ?: array();
            }
            self::$registered_helpers[] = 'folio_get_user_latest_notifications';
        }

        if (!function_exists('folio_mark_notification_read')) {
            /**
             * 标记通知为已读
             */
            function folio_mark_notification_read($notification_id, $user_id = null) {
                if (!$user_id) {
                    $user_id = get_current_user_id();
                }
                
                if (!$user_id) {
                    return false;
                }
                
                global $wpdb;
                $notification_table = $wpdb->prefix . 'folio_notifications';
                
                $result = $wpdb->update(
                    $notification_table,
                    array('is_read' => 1),
                    array(
                        'id' => $notification_id,
                        'user_id' => $user_id
                    ),
                    array('%d'),
                    array('%d', '%d')
                );
                
                return $result !== false;
            }
            self::$registered_helpers[] = 'folio_mark_notification_read';
        }

        if (!function_exists('folio_get_notification_type_icon')) {
            /**
             * 获取通知类型图标
             */
            function folio_get_notification_type_icon($type) {
                $icons = array(
                    'membership_expiry' => '⏰',
                    'membership_expired' => '⚠️',
                    'membership_changed' => '🔄',
                    'membership_activated' => '✅',
                    'user_register' => '👤',
                    'security_alert' => '🔒',
                    'security_warning' => '⚠️',
                    'test' => '📢'
                );
                
                return isset($icons[$type]) ? $icons[$type] : '📬';
            }
            self::$registered_helpers[] = 'folio_get_notification_type_icon';
        }
    }

    /**
     * 注册性能相关辅助函数
     */
    private function register_performance_helpers() {
        if (!function_exists('folio_get_performance_stats')) {
            /**
             * 获取性能统计数据
             */
            function folio_get_performance_stats() {
                if (class_exists('folio_Performance_Cache_Manager')) {
                    return folio_Performance_Cache_Manager::get_cache_statistics();
                }
                return array();
            }
            self::$registered_helpers[] = 'folio_get_performance_stats';
        }

        if (!function_exists('folio_clear_performance_cache')) {
            /**
             * 清除性能缓存
             */
            function folio_clear_performance_cache($type = 'all') {
                // 兼容模式：避免调用不存在的方法或触发 AJAX 输出
                wp_cache_flush();
                delete_transient('folio_cache_statistics');
                delete_transient('folio_cache_backend_info');
                return true;
            }
            self::$registered_helpers[] = 'folio_clear_performance_cache';
        }

        if (!function_exists('folio_is_performance_monitoring_enabled')) {
            /**
             * 检查性能监控是否启用
             */
            function folio_is_performance_monitoring_enabled() {
                $options = get_option('folio_theme_options', array());
                return isset($options['enable_performance_monitor']) && $options['enable_performance_monitor'];
            }
            self::$registered_helpers[] = 'folio_is_performance_monitoring_enabled';
        }
    }

    /**
     * 注册维护模式辅助函数
     */
    private function register_maintenance_helpers() {
        if (!function_exists('folio_is_maintenance_mode')) {
            /**
             * 检查维护模式是否启用
             */
            function folio_is_maintenance_mode() {
                if (class_exists('folio_Maintenance_Mode')) {
                    return folio_Maintenance_Mode::is_maintenance_mode();
                }
                
                $options = get_option('folio_theme_options', array());
                return isset($options['maintenance_mode']) && $options['maintenance_mode'];
            }
            self::$registered_helpers[] = 'folio_is_maintenance_mode';
        }

        if (!function_exists('folio_get_maintenance_info')) {
            /**
             * 获取维护模式信息
             */
            function folio_get_maintenance_info() {
                if (class_exists('folio_Maintenance_Mode')) {
                    return folio_Maintenance_Mode::get_maintenance_info();
                }
                
                $options = get_option('folio_theme_options', array());
                return array(
                    'enabled' => isset($options['maintenance_mode']) && $options['maintenance_mode'],
                    'message' => $options['maintenance_message'] ?? __('Site is under maintenance. Please visit later.', 'folio'),
                    'start_time' => $options['maintenance_start_time'] ?? '',
                    'end_time' => $options['maintenance_end_time'] ?? '',
                );
            }
            self::$registered_helpers[] = 'folio_get_maintenance_info';
        }

        if (!function_exists('folio_enable_maintenance_mode')) {
            /**
             * 启用维护模式
             */
            function folio_enable_maintenance_mode($message = '', $start_time = '', $end_time = '') {
                $options = get_option('folio_theme_options', array());
                $options['maintenance_mode'] = 1;
                $options['maintenance_start_time'] = $start_time ? $start_time : current_time('mysql');
                
                if (!empty($message)) {
                    $options['maintenance_message'] = $message;
                }
                
                if (!empty($end_time)) {
                    $options['maintenance_end_time'] = $end_time;
                    $options['maintenance_scheduled'] = 1;
                }
                
                return update_option('folio_theme_options', $options);
            }
            self::$registered_helpers[] = 'folio_enable_maintenance_mode';
        }

        if (!function_exists('folio_disable_maintenance_mode')) {
            /**
             * 禁用维护模式
             */
            function folio_disable_maintenance_mode() {
                $options = get_option('folio_theme_options', array());
                $options['maintenance_mode'] = 0;
                $options['maintenance_scheduled'] = 0;
                
                return update_option('folio_theme_options', $options);
            }
            self::$registered_helpers[] = 'folio_disable_maintenance_mode';
        }
    }

    /**
     * 注册SEO相关辅助函数
     */
    private function register_seo_helpers() {
        if (!function_exists('folio_get_seo_stats')) {
            /**
             * 获取SEO统计数据
             */
            function folio_get_seo_stats() {
                if (class_exists('folio_Membership_SEO')) {
                    return folio_Membership_SEO::get_seo_stats();
                }
                return array();
            }
            self::$registered_helpers[] = 'folio_get_seo_stats';
        }

        if (!function_exists('folio_update_post_seo')) {
            /**
             * 更新文章SEO信息
             */
            function folio_update_post_seo($post_id) {
                if (class_exists('folio_SEO_Admin')) {
                    return folio_SEO_Admin::update_post_seo_description($post_id);
                }
                return false;
            }
            self::$registered_helpers[] = 'folio_update_post_seo';
        }
    }

    /**
     * 注册管理页面辅助函数
     */
    private function register_admin_helpers() {
        if (!function_exists('folio_render_brand_header')) {
            /**
             * 输出统一的品牌头部
             */
            function folio_render_brand_header($page_title = '', $nav_links = array()) {
                $site_name = get_bloginfo('name');
                $site_initial = strtoupper(substr($site_name, 0, 1));
                
                // 默认导航链接
                $default_links = array(
                    array(
                        'url' => admin_url('themes.php?page=folio-theme-options&tab=advanced'),
                        'text' => __('Theme Settings', 'folio')
                    ),
                    array(
                        'url' => admin_url(),
                        'text' => __('Go to Admin', 'folio')
                    )
                );
                
                $nav_links = array_merge($nav_links, $default_links);
                ?>
                <div class="folio-brand-header">
                    <div class="container">
                        <a href="<?php echo home_url(); ?>" class="folio-brand-logo">
                            <div class="folio-logo-icon">
                                <?php echo esc_html($site_initial); ?>
                            </div>
                            <div class="folio-brand-text">
                                <h1><?php echo esc_html($site_name); ?></h1>
                                <?php if ($page_title): ?>
                                <p><?php echo esc_html($page_title); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                        <nav class="folio-brand-nav">
                            <?php foreach ($nav_links as $link): ?>
                            <a href="<?php echo esc_url($link['url']); ?>" class="folio-nav-link">
                                <?php echo esc_html($link['text']); ?>
                            </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                </div>
                <?php
            }
            self::$registered_helpers[] = 'folio_render_brand_header';
        }

        if (!function_exists('folio_render_admin_notice')) {
            /**
             * 渲染管理后台通知
             */
            function folio_render_admin_notice($message, $type = 'info', $dismissible = true) {
                $classes = array('notice', 'notice-' . $type);
                if ($dismissible) {
                    $classes[] = 'is-dismissible';
                }
                
                printf(
                    '<div class="%s"><p>%s</p></div>',
                    esc_attr(implode(' ', $classes)),
                    wp_kses_post($message)
                );
            }
            self::$registered_helpers[] = 'folio_render_admin_notice';
        }
    }

    /**
     * 获取已注册的辅助函数列表
     */
    public static function get_registered_helpers() {
        return self::$registered_helpers;
    }

    /**
     * 检查函数是否已注册
     */
    public static function is_helper_registered($function_name) {
        return in_array($function_name, self::$registered_helpers);
    }

    /**
     * 获取辅助函数统计信息
     */
    public static function get_helper_stats() {
        return array(
            'total_helpers' => count(self::$registered_helpers),
            'notification_helpers' => count(array_filter(self::$registered_helpers, function($name) {
                return strpos($name, 'notification') !== false;
            })),
            'performance_helpers' => count(array_filter(self::$registered_helpers, function($name) {
                return strpos($name, 'performance') !== false;
            })),
            'maintenance_helpers' => count(array_filter(self::$registered_helpers, function($name) {
                return strpos($name, 'maintenance') !== false;
            })),
            'seo_helpers' => count(array_filter(self::$registered_helpers, function($name) {
                return strpos($name, 'seo') !== false;
            })),
            'admin_helpers' => count(array_filter(self::$registered_helpers, function($name) {
                return strpos($name, 'admin') !== false || strpos($name, 'render') !== false;
            })),
        );
    }

    /**
     * 调试信息输出
     */
    public static function debug_info() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $stats = self::get_helper_stats();
        error_log('Folio Helper Manager Stats: ' . print_r($stats, true));
        error_log('Registered Helpers: ' . implode(', ', self::$registered_helpers));
    }
}

// 初始化辅助函数管理器
folio_Helper_Manager::get_instance();

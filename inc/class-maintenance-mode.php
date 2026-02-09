<?php
/**
 * Maintenance Mode
 * 
 * 维护模式功能 - 对非管理员用户显示维护页面
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Maintenance_Mode {

    public function __construct() {
        // 检查维护模式是否启用 - 使用更晚的钩子确保在用户认证之后执行
        add_action('wp', array($this, 'check_maintenance_mode'), 1);
        
        // 添加管理员通知
        add_action('admin_notices', array($this, 'admin_notice'));
        
        // 在管理栏显示维护模式状态
        add_action('admin_bar_menu', array($this, 'admin_bar_notice'), 100);
    }

    /**
     * 检查是否应该跳过维护模式检查
     */
    private function should_skip_maintenance_check() {
        // 如果是后台页面，跳过
        if (is_admin()) {
            return true;
        }

        // 如果是AJAX请求，跳过
        if (wp_doing_ajax()) {
            return true;
        }

        // 如果是REST API请求，跳过
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        // 检查登录页面
        if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') {
            return true;
        }

        // 检查URL路径
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($request_uri, 'wp-login') !== false || 
            strpos($request_uri, 'wp-admin') !== false ||
            strpos($request_uri, 'xmlrpc.php') !== false) {
            return true;
        }

        // 检查登录相关的请求
        if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array(
            'login', 'logout', 'lostpassword', 'resetpass', 'rp', 'register'
        ))) {
            return true;
        }

        return false;
    }

    /**
     * 检查维护模式
     */
    public function check_maintenance_mode() {
        // 早期安全检查 - 如果是关键页面或请求，直接跳过
        if ($this->should_skip_maintenance_check()) {
            return;
        }

        // 如果是管理员，不显示维护页面
        if (current_user_can('manage_options')) {
            return;
        }

        // 一次性获取所有维护模式选项
        $options = get_option('folio_theme_options', array());
        $maintenance_enabled = isset($options['maintenance_mode']) ? $options['maintenance_mode'] : 0;

        if (!$maintenance_enabled) {
            return;
        }

        // 检查计划维护时间
        if (isset($options['maintenance_scheduled']) && $options['maintenance_scheduled']) {
            $start_time = isset($options['maintenance_start_time']) ? strtotime($options['maintenance_start_time']) : 0;
            $end_time = isset($options['maintenance_end_time']) ? strtotime($options['maintenance_end_time']) : 0;
            $current_time = current_time('timestamp');

            // 如果设置了开始时间且还未到时间
            if ($start_time && $current_time < $start_time) {
                return;
            }

            // 如果设置了结束时间且已过时间
            if ($end_time && $current_time > $end_time) {
                // 自动禁用维护模式
                $options['maintenance_mode'] = 0;
                update_option('folio_theme_options', $options);
                return;
            }
        }

        // 检查IP白名单
        $client_ip = $this->get_client_ip();
        if ($this->is_ip_whitelisted($client_ip, $options)) {
            return;
        }

        // 记录访问日志
        $this->log_maintenance_access($options);

        // 显示维护页面
        $this->display_maintenance_page($options);
    }

    /**
     * 显示维护页面
     */
    private function display_maintenance_page($options) {
        // 设置HTTP状态码为503 Service Unavailable
        status_header(503);
        
        // 获取维护信息
        $site_name = get_bloginfo('name');
        $maintenance_message = isset($options['maintenance_message']) ? $options['maintenance_message'] : '';
        
        if (empty($maintenance_message)) {
            $maintenance_message = '网站正在维护中，请稍后访问。我们会尽快恢复服务。';
        }

        // 输出维护页面
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($site_name); ?> - 维护中</title>
            <meta name="robots" content="noindex, nofollow">
            
            <!-- 预加载字体 -->
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Roboto+Condensed:wght@400;500;700&display=swap" rel="stylesheet">
            
            <!-- 维护模式样式 -->
            <link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/assets/css/maintenance-mode.css">
        </head>
        <body>
            <!-- 主题切换按钮 -->
            <button class="theme-toggle" aria-label="切换深色模式" onclick="toggleTheme()">
                <!-- 月亮图标 (亮色模式时显示) -->
                <svg class="icon-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
                <!-- 太阳图标 (暗色模式时显示) -->
                <svg class="icon-sun" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </button>

            <div class="maintenance-container">
                <!-- 头部 -->
                <div class="maintenance-header">
                    <h1 class="site-title"><?php echo esc_html($site_name); ?></h1>
                    <p class="maintenance-subtitle">Portfolio Theme</p>
                </div>

                <!-- 六边形维护提醒 -->
                <div class="hexagon-maintenance">
                    <div class="hexagon-content">
                        <div class="maintenance-status"><h2>维护中</h2></div>
                    </div>
                </div>

                <!-- 主要消息 -->
                <div class="maintenance-description">
                    <?php if (!empty($maintenance_message)) : ?>
                        <?php echo wp_kses_post(wpautop($maintenance_message)); ?>
                    <?php else : ?>
                        <p>我们正在对网站进行系统升级和优化，以为您提供更好的使用体验。维护期间网站暂时无法访问，给您带来的不便敬请谅解。</p>
                    <?php endif; ?>
                </div>

                <!-- 时间信息 -->
                <div class="time-info">
                    <div class="time-item">
                        <span class="time-label">开始时间</span>
                        <span class="time-value">
                            <?php
                            $start_time = isset($options['maintenance_start_time']) ? $options['maintenance_start_time'] : '';
                            if ($start_time) {
                                echo date_i18n('Y年m月d日 H:i', strtotime($start_time));
                            } else {
                                echo current_time('Y年m月d日 H:i');
                            }
                            ?>
                        </span>
                    </div>
                    <div class="time-item">
                        <span class="time-label">预计恢复</span>
                        <span class="time-value">
                            <?php
                            $end_time = isset($options['maintenance_end_time']) ? $options['maintenance_end_time'] : '';
                            if ($end_time) {
                                echo date_i18n('Y年m月d日 H:i', strtotime($end_time));
                            } else {
                                echo '我们会尽快完成维护工作';
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <!-- 页脚 -->
                <div class="maintenance-footer">
                    <?php
                    $admin_email = get_option('admin_email');
                    if ($admin_email) :
                    ?>
                    <div class="footer-contact">
                        <span>如有紧急事务，请联系：</span>
                        <a href="mailto:<?php echo esc_attr($admin_email); ?>" class="footer-email"><?php echo esc_html($admin_email); ?></a>
                    </div>
                    <?php endif; ?>
                    <p>感谢您的耐心等待 ❤️</p>
                    <p class="refresh-info">页面将每5分钟自动刷新检查状态</p>
                </div>
            </div>

            <script>
                // 主题切换功能
                function toggleTheme() {
                    const html = document.documentElement;
                    const currentTheme = html.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    
                    html.setAttribute('data-theme', newTheme);
                    localStorage.setItem('folio-theme', newTheme);
                }

                // 初始化主题
                function initTheme() {
                    const savedTheme = localStorage.getItem('folio-theme');
                    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const theme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
                    
                    document.documentElement.setAttribute('data-theme', theme);
                }

                // 自动刷新页面（每5分钟检查一次）
                function autoRefresh() {
                    setTimeout(function() {
                        // 添加淡出效果
                        document.body.style.opacity = '0.7';
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    }, 300000); // 5分钟 = 300000毫秒
                }

                // 页面加载完成后初始化
                document.addEventListener('DOMContentLoaded', function() {
                    initTheme();
                    autoRefresh();
                    
                    // 监听系统主题变化
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                        if (!localStorage.getItem('folio-theme')) {
                            document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                        }
                    });
                });

                // 键盘快捷键支持
                document.addEventListener('keydown', function(e) {
                    // Ctrl/Cmd + D 切换主题
                    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                        e.preventDefault();
                        toggleTheme();
                    }
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * 管理员通知
     */
    public function admin_notice() {
        $options = get_option('folio_theme_options', array());
        $maintenance_enabled = isset($options['maintenance_mode']) ? $options['maintenance_mode'] : 0;

        if (!$maintenance_enabled) {
            return;
        }

        $screen = get_current_screen();
        
        // 只在主要管理页面显示
        if (!$screen || !in_array($screen->base, array('dashboard', 'themes', 'appearance_page_folio-theme-options'))) {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>⚠️ 维护模式已启用</strong> - 
                非管理员用户将看到维护页面。
                <a href="<?php echo admin_url('themes.php?page=folio-theme-options&tab=advanced'); ?>">
                    前往设置关闭
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * 管理栏通知
     */
    public function admin_bar_notice($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = get_option('folio_theme_options', array());
        $maintenance_enabled = isset($options['maintenance_mode']) ? $options['maintenance_mode'] : 0;

        if (!$maintenance_enabled) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id'    => 'maintenance-mode-notice',
            'title' => '<span style="color: #ff6b6b;">🔧 维护模式</span>',
            'href'  => admin_url('themes.php?page=folio-theme-options&tab=advanced'),
            'meta'  => array(
                'title' => '点击管理维护模式设置'
            )
        ));
    }

    /**
     * 检查维护模式状态（静态方法）
     */
    public static function is_maintenance_mode() {
        $options = get_option('folio_theme_options', array());
        return isset($options['maintenance_mode']) ? (bool) $options['maintenance_mode'] : false;
    }

    /**
     * 获取维护信息（静态方法）
     */
    public static function get_maintenance_info() {
        $options = get_option('folio_theme_options', array());
        
        return array(
            'enabled' => self::is_maintenance_mode(),
            'message' => isset($options['maintenance_message']) ? $options['maintenance_message'] : '',
            'start_time' => isset($options['maintenance_start_time']) ? $options['maintenance_start_time'] : current_time('mysql'),
            'end_time' => isset($options['maintenance_end_time']) ? $options['maintenance_end_time'] : '',
            'admin_email' => get_option('admin_email'),
            'bypass_ips' => isset($options['maintenance_bypass_ips']) ? $options['maintenance_bypass_ips'] : array(),
            'log_enabled' => isset($options['maintenance_log_enabled']) ? $options['maintenance_log_enabled'] : 0
        );
    }

    /**
     * 记录维护模式访问日志
     */
    private function log_maintenance_access($options = null) {
        if ($options === null) {
            $options = get_option('folio_theme_options', array());
        }
        
        if (!isset($options['maintenance_log_enabled']) || !$options['maintenance_log_enabled']) {
            return;
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'ip' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
        );

        $logs = get_option('folio_maintenance_logs', array());
        $logs[] = $log_entry;

        // 只保留最近100条记录
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        update_option('folio_maintenance_logs', $logs);
    }

    /**
     * 获取客户端IP地址
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * 检查IP是否在白名单中
     */
    private function is_ip_whitelisted($ip, $options = null) {
        if ($options === null) {
            $options = get_option('folio_theme_options', array());
        }
        $bypass_ips = isset($options['maintenance_bypass_ips']) ? $options['maintenance_bypass_ips'] : '';
        
        if (empty($bypass_ips)) {
            return false;
        }

        $allowed_ips = array_map('trim', explode("\n", $bypass_ips));
        
        foreach ($allowed_ips as $allowed_ip) {
            if (empty($allowed_ip)) continue;
            
            // 支持CIDR格式
            if (strpos($allowed_ip, '/') !== false) {
                if ($this->ip_in_range($ip, $allowed_ip)) {
                    return true;
                }
            } else {
                // 精确匹配
                if ($ip === $allowed_ip) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * 检查IP是否在CIDR范围内
     */
    private function ip_in_range($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~ $wildcard_decimal;

        return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }
}

// 在WordPress初始化后再创建实例
add_action('init', function() {
    new folio_Maintenance_Mode();
});
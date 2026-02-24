<?php
/**
 * Cache Health Checker
 * 
 * 缓存系统健康检查工具 - 自动检测和诊断缓存系统状态
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Cache_Health_Checker {

    /**
     * 健康检查结果
     */
    private $health_results = array();

    /**
     * 构造函数
     */
    public function __construct() {
        // 只在管理员页面启用
        if (is_admin() && current_user_can('manage_options')) {
            // 仅在未被其他模块注册时兜底注册
            if (!has_action('wp_ajax_folio_cache_health_check')) {
                add_action('wp_ajax_folio_cache_health_check', array($this, 'ajax_health_check'));
            }
            add_action('admin_notices', array($this, 'show_health_notices'));
        }
    }

    /**
     * 执行完整的健康检查
     */
    public function run_health_check() {
        $this->health_results = array();

        // 1. 基础环境检查
        $this->check_basic_environment();

        // 2. 缓存后端检查
        $this->check_cache_backends();

        // 3. 文件系统检查
        $this->check_file_system();

        // 4. 性能指标检查
        $this->check_performance_metrics();

        // 5. 配置检查
        $this->check_configuration();

        // 6. 功能测试
        $this->check_functionality();

        return $this->health_results;
    }

    /**
     * 检查基础环境
     */
    private function check_basic_environment() {
        $section = 'basic_environment';
        $this->health_results[$section] = array(
            'title' => __('Basic Environment Check', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        // PHP版本检查
        $php_version = PHP_VERSION;
        $min_php_version = '7.4';
        $this->add_test_result($section, 'php_version', array(
            'label' => __('PHP Version', 'folio'),
            'status' => version_compare($php_version, $min_php_version, '>=') ? 'good' : 'critical',
            'value' => $php_version,
            'description' => version_compare($php_version, $min_php_version, '>=') 
                ? __('PHP version meets requirements', 'folio')
                : sprintf(
                    /* translators: %s: minimum PHP version */
                    __('PHP version is too low. Upgrade to %s or later.', 'folio'),
                    $min_php_version
                ),
            'actions' => version_compare($php_version, $min_php_version, '<') 
                ? array(__('Upgrade PHP to the latest version', 'folio'))
                : array()
        ));

        // 内存限制检查
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        $min_memory = 128 * 1024 * 1024; // 128MB
        $this->add_test_result($section, 'memory_limit', array(
            'label' => __('Memory Limit', 'folio'),
            'status' => $memory_bytes >= $min_memory ? 'good' : 'warning',
            'value' => $memory_limit,
            'description' => $memory_bytes >= $min_memory 
                ? __('Memory limit is sufficient', 'folio')
                : __('Memory limit is low and may affect cache performance', 'folio'),
            'actions' => $memory_bytes < $min_memory 
                ? array(__('Increase PHP memory limit to 256M or higher', 'folio'))
                : array()
        ));

        // WordPress版本检查
        global $wp_version;
        $min_wp_version = '5.0';
        $this->add_test_result($section, 'wp_version', array(
            'label' => __('WordPress Version', 'folio'),
            'status' => version_compare($wp_version, $min_wp_version, '>=') ? 'good' : 'warning',
            'value' => $wp_version,
            'description' => version_compare($wp_version, $min_wp_version, '>=') 
                ? __('WordPress version meets requirements', 'folio')
                : __('WordPress version is outdated. Upgrade is recommended', 'folio'),
            'actions' => version_compare($wp_version, $min_wp_version, '<') 
                ? array(__('Upgrade WordPress to the latest version', 'folio'))
                : array()
        ));
    }

    /**
     * 检查缓存后端
     */
    private function check_cache_backends() {
        $section = 'cache_backends';
        $this->health_results[$section] = array(
            'title' => __('Cache Backend Check', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        // Memcached检查
        $memcached_available = class_exists('Memcached');
        $this->add_test_result($section, 'memcached_extension', array(
            'label' => __('Memcached Extension', 'folio'),
            'status' => $memcached_available ? 'good' : 'warning',
            'value' => $memcached_available ? __('Installed', 'folio') : __('Not Installed', 'folio'),
            'description' => $memcached_available 
                ? __('Memcached extension is available', 'folio')
                : __('Memcached extension is not installed. Built-in cache will be used.', 'folio'),
            'actions' => !$memcached_available 
                ? array(__('Install php-memcached extension for better performance', 'folio'))
                : array()
        ));

        // Memcached连接测试
        if ($memcached_available && function_exists('folio_check_memcached_availability')) {
            $mc_status = folio_check_memcached_availability();
            $this->add_test_result($section, 'memcached_connection', array(
                'label' => __('Memcached Connection', 'folio'),
                'status' => $mc_status['connection_test'] ? 'good' : 'critical',
                'value' => $mc_status['connection_test'] ? __('Connected', 'folio') : __('Connection Failed', 'folio'),
                'description' => $mc_status['connection_test'] 
                    ? __('Memcached service connection is healthy', 'folio')
                    : __('Memcached service connection failed', 'folio'),
                'actions' => !$mc_status['connection_test'] 
                    ? array(
                        __('Check Memcached service status', 'folio'),
                        __('Verify connection configuration', 'folio')
                    )
                    : array()
            ));
        }

        // Redis检查
        $redis_available = class_exists('Redis');
        $this->add_test_result($section, 'redis_extension', array(
            'label' => __('Redis Extension', 'folio'),
            'status' => $redis_available ? 'good' : 'info',
            'value' => $redis_available ? __('Installed', 'folio') : __('Not Installed', 'folio'),
            'description' => $redis_available 
                ? __('Redis extension is available', 'folio')
                : __('Redis extension is not installed (optional)', 'folio'),
            'actions' => array()
        ));

        // 对象缓存检查
        $object_cache_enabled = wp_using_ext_object_cache();
        $this->add_test_result($section, 'object_cache', array(
            'label' => __('Object Cache', 'folio'),
            'status' => $object_cache_enabled ? 'good' : 'warning',
            'value' => $object_cache_enabled ? __('Enabled', 'folio') : __('Disabled', 'folio'),
            'description' => $object_cache_enabled 
                ? __('External object cache is enabled', 'folio')
                : __('Built-in object cache is in use, with limited performance', 'folio'),
            'actions' => !$object_cache_enabled 
                ? array(__('Install and enable external object cache', 'folio'))
                : array()
        ));
    }

    /**
     * 检查文件系统
     */
    private function check_file_system() {
        $section = 'file_system';
        $this->health_results[$section] = array(
            'title' => __('Filesystem Check', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        // wp-content目录写权限
        $wp_content_writable = is_writable(WP_CONTENT_DIR);
        $this->add_test_result($section, 'wp_content_writable', array(
            'label' => __('wp-content Write Permission', 'folio'),
            'status' => $wp_content_writable ? 'good' : 'critical',
            'value' => $wp_content_writable ? __('Writable', 'folio') : __('Not Writable', 'folio'),
            'description' => $wp_content_writable 
                ? __('wp-content directory is writable', 'folio')
                : __('wp-content directory is not writable, so object-cache.php cannot be installed', 'folio'),
            'actions' => !$wp_content_writable 
                ? array(__('Set wp-content directory permissions to 755 or 775', 'folio'))
                : array()
        ));

        // object-cache.php文件检查
        $object_cache_file = WP_CONTENT_DIR . '/object-cache.php';
        $object_cache_exists = file_exists($object_cache_file);
        
        if ($object_cache_exists && class_exists('folio_Cache_File_Manager')) {
            $manager = new folio_Cache_File_Manager();
            $is_folio_version = $manager->is_folio_object_cache();
            
            $this->add_test_result($section, 'object_cache_file', array(
                'label' => 'object-cache.php',
                'status' => $is_folio_version ? 'good' : 'warning',
                'value' => $is_folio_version ? __('Folio Version', 'folio') : __('Third-Party Version', 'folio'),
                'description' => $is_folio_version 
                    ? __('Folio optimized object-cache.php is installed', 'folio')
                    : __('Third-party object-cache.php file detected', 'folio'),
                'actions' => !$is_folio_version 
                    ? array(__('Consider replacing it with Folio optimized version', 'folio'))
                    : array()
            ));
        } else {
            $this->add_test_result($section, 'object_cache_file', array(
                'label' => 'object-cache.php',
                'status' => 'warning',
                'value' => __('Not Installed', 'folio'),
                'description' => __('object-cache.php is not installed. WordPress built-in cache is being used.', 'folio'),
                'actions' => array(__('Install Folio object cache to improve performance', 'folio'))
            ));
        }

        // 缓存目录检查
        $cache_dir = WP_CONTENT_DIR . '/cache';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        $cache_dir_writable = is_writable($cache_dir);
        $this->add_test_result($section, 'cache_directory', array(
            'label' => __('Cache Directory', 'folio'),
            'status' => $cache_dir_writable ? 'good' : 'warning',
            'value' => $cache_dir_writable ? __('Writable', 'folio') : __('Not Writable', 'folio'),
            'description' => $cache_dir_writable 
                ? __('Cache directory is writable', 'folio')
                : __('Cache directory permissions are insufficient', 'folio'),
            'actions' => !$cache_dir_writable 
                ? array(__('Set write permissions for the cache directory', 'folio'))
                : array()
        ));
    }

    /**
     * 检查性能指标
     */
    private function check_performance_metrics() {
        $section = 'performance_metrics';
        $this->health_results[$section] = array(
            'title' => __('Performance Metrics Check', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        // 获取缓存统计
        if (class_exists('folio_Performance_Cache_Manager')) {
            $stats = folio_Performance_Cache_Manager::get_cache_statistics();
            
            // 缓存命中率
            $hit_rate = 0;
            if (isset($stats['performance_stats'])) {
                $perf = $stats['performance_stats'];
                $total_requests = $perf['cache_hits'] + $perf['cache_misses'];
                if ($total_requests > 0) {
                    $hit_rate = ($perf['cache_hits'] / $total_requests) * 100;
                }
            }
            
            $hit_rate_status = $hit_rate > 80 ? 'good' : ($hit_rate > 60 ? 'warning' : 'critical');
            $this->add_test_result($section, 'cache_hit_rate', array(
                'label' => __('Cache Hit Rate', 'folio'),
                'status' => $hit_rate_status,
                'value' => number_format($hit_rate, 1) . '%',
                'description' => $this->get_hit_rate_description($hit_rate),
                'actions' => $hit_rate < 80
                    ? array(
                        __('Optimize cache configuration', 'folio'),
                        __('Increase cache expiration time', 'folio')
                    )
                    : array()
            ));

            // 内存使用
            $memory_usage = $stats['memory_usage'] ?? 0;
            $memory_status = $memory_usage < 100 * 1024 * 1024 ? 'good' : 'warning'; // 100MB
            $this->add_test_result($section, 'memory_usage', array(
                'label' => __('Memory Usage', 'folio'),
                'status' => $memory_status,
                'value' => size_format($memory_usage),
                'description' => $memory_status === 'good' 
                    ? __('Memory usage is normal', 'folio')
                    : __('Memory usage is high', 'folio'),
                'actions' => $memory_status !== 'good' 
                    ? array(
                        __('Clean expired cache', 'folio'),
                        __('Optimize caching strategy', 'folio')
                    )
                    : array()
            ));
        }

        // 数据库查询数量
        $query_count = get_num_queries();
        $query_status = $query_count < 30 ? 'good' : ($query_count < 50 ? 'warning' : 'critical');
        $this->add_test_result($section, 'db_queries', array(
            'label' => __('Database Queries', 'folio'),
            'status' => $query_status,
            'value' => sprintf(__('%d queries', 'folio'), $query_count),
            'description' => $this->get_query_count_description($query_count),
            'actions' => $query_count > 30
                ? array(
                    __('Enable query cache', 'folio'),
                    __('Optimize database queries', 'folio')
                )
                : array()
        ));
    }

    /**
     * 检查配置
     */
    private function check_configuration() {
        $section = 'configuration';
        $this->health_results[$section] = array(
            'title' => __('Configuration Check', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        // 缓存启用状态
        $cache_enabled = get_option('folio_cache_enabled', true);
        $this->add_test_result($section, 'cache_enabled', array(
            'label' => __('Cache Enabled', 'folio'),
            'status' => $cache_enabled ? 'good' : 'critical',
            'value' => $cache_enabled ? __('Enabled', 'folio') : __('Disabled', 'folio'),
            'description' => $cache_enabled 
                ? __('Folio cache system is enabled', 'folio')
                : __('Folio cache system is disabled', 'folio'),
            'actions' => !$cache_enabled 
                ? array(__('Enable Folio cache system', 'folio'))
                : array()
        ));

        // 自动清理配置
        $auto_cleanup = get_option('folio_cache_auto_cleanup', true);
        $this->add_test_result($section, 'auto_cleanup', array(
            'label' => __('Auto Cleanup', 'folio'),
            'status' => $auto_cleanup ? 'good' : 'warning',
            'value' => $auto_cleanup ? __('Enabled', 'folio') : __('Disabled', 'folio'),
            'description' => $auto_cleanup 
                ? __('Automatic cleanup of expired cache is enabled', 'folio')
                : __('Automatic cleanup is disabled and requires manual cleanup', 'folio'),
            'actions' => !$auto_cleanup 
                ? array(__('Enable automatic cleanup', 'folio'))
                : array()
        ));

        // 调试模式检查
        $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        $this->add_test_result($section, 'debug_mode', array(
            'label' => __('Debug Mode', 'folio'),
            'status' => $debug_enabled ? 'warning' : 'good',
            'value' => $debug_enabled ? __('Enabled', 'folio') : __('Disabled', 'folio'),
            'description' => $debug_enabled 
                ? __('Debug mode is enabled and may impact performance', 'folio')
                : __('Debug mode is disabled', 'folio'),
            'actions' => $debug_enabled 
                ? array(__('Disable debug mode in production', 'folio'))
                : array()
        ));
    }

    /**
     * 检查功能
     */
    private function check_functionality() {
        $section = 'functionality';
        $this->health_results[$section] = array(
            'title' => __('Functionality Test', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        // 缓存读写测试
        $test_key = 'folio_health_check_' . time();
        $test_value = 'test_value_' . wp_generate_password(8, false);
        
        // 写入测试
        $write_success = wp_cache_set($test_key, $test_value, 'folio_health_check', 300);
        
        // 读取测试
        $read_value = wp_cache_get($test_key, 'folio_health_check');
        $read_success = ($read_value === $test_value);
        
        // 清理测试数据
        wp_cache_delete($test_key, 'folio_health_check');
        
        $cache_rw_status = ($write_success && $read_success) ? 'good' : 'critical';
        $this->add_test_result($section, 'cache_read_write', array(
            'label' => __('Cache Read/Write', 'folio'),
            'status' => $cache_rw_status,
            'value' => $cache_rw_status === 'good' ? __('Normal', 'folio') : __('Error', 'folio'),
            'description' => $cache_rw_status === 'good' 
                ? __('Cache read/write is working normally', 'folio')
                : __('Cache read/write is not functioning correctly', 'folio'),
            'actions' => $cache_rw_status !== 'good' 
                ? array(
                    __('Check cache configuration', 'folio'),
                    __('Restart cache service', 'folio')
                )
                : array()
        ));

        // 权限检查功能测试
        $permission_function_exists = function_exists('folio_can_user_access_article');
        $this->add_test_result($section, 'permission_function', array(
            'label' => __('Permission Check Function', 'folio'),
            'status' => $permission_function_exists ? 'good' : 'warning',
            'value' => $permission_function_exists ? __('Available', 'folio') : __('Unavailable', 'folio'),
            'description' => $permission_function_exists 
                ? __('Permission check function is working', 'folio')
                : __('Permission check function is unavailable', 'folio'),
            'actions' => !$permission_function_exists 
                ? array(__('Check related plugin and theme files', 'folio'))
                : array()
        ));
    }

    /**
     * 添加测试结果
     */
    private function add_test_result($section, $test_id, $result) {
        $this->health_results[$section]['tests'][$test_id] = $result;
        
        // 更新section状态
        if ($result['status'] === 'critical') {
            $this->health_results[$section]['status'] = 'critical';
        } elseif ($result['status'] === 'warning' && $this->health_results[$section]['status'] !== 'critical') {
            $this->health_results[$section]['status'] = 'warning';
        }
    }

    /**
     * 获取命中率描述
     */
    private function get_hit_rate_description($hit_rate) {
        if ($hit_rate > 90) {
            return __('Cache hit rate is excellent', 'folio');
        } elseif ($hit_rate > 80) {
            return __('Cache hit rate is good', 'folio');
        } elseif ($hit_rate > 60) {
            return __('Cache hit rate is average and optimization is recommended', 'folio');
        } else {
            return __('Cache hit rate is low and needs optimization', 'folio');
        }
    }

    /**
     * 获取查询数量描述
     */
    private function get_query_count_description($count) {
        if ($count < 20) {
            return __('Database query count is excellent', 'folio');
        } elseif ($count < 30) {
            return __('Database query count is good', 'folio');
        } elseif ($count < 50) {
            return __('Database query count is high and optimization is recommended', 'folio');
        } else {
            return __('Database query count is too high and requires optimization', 'folio');
        }
    }

    /**
     * 转换内存大小为字节
     */
    private function convert_to_bytes($size) {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;
        
        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }
        
        return $size;
    }

    /**
     * AJAX健康检查
     */
    public function ajax_health_check() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_cache_health_check')) {
            wp_send_json_error(__('Security verification failed', 'folio'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }

        $results = $this->run_health_check();
        wp_send_json_success($results);
    }

    /**
     * 显示健康通知
     */
    public function show_health_notices() {
        // 只在缓存管理页面显示
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'tools_page_folio-cache-management') {
            return;
        }

        // 运行快速健康检查
        $critical_issues = $this->get_critical_issues();
        
        if (!empty($critical_issues)) {
            ?>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e('Cache System Health Warning', 'folio'); ?></strong></p>
                <ul>
                    <?php foreach ($critical_issues as $issue) : ?>
                        <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <button type="button" class="button" onclick="runCacheHealthCheck()">
                        <?php esc_html_e('Run Full Health Check', 'folio'); ?>
                    </button>
                </p>
            </div>
            <?php
        }
    }

    /**
     * 获取关键问题
     */
    private function get_critical_issues() {
        $issues = array();

        // 检查Memcached
        if (!class_exists('Memcached')) {
            $issues[] = __('Memcached extension is not installed. Cache performance is limited.', 'folio');
        }

        // 检查object-cache.php
        if (!wp_using_ext_object_cache()) {
            $issues[] = __('External object cache is not enabled. Installing object-cache.php is recommended.', 'folio');
        }

        // 检查写权限
        if (!is_writable(WP_CONTENT_DIR)) {
            $issues[] = __('The wp-content directory is not writable, so cache files cannot be managed.', 'folio');
        }

        return $issues;
    }
}

// 缓存健康检查器类已在 functions.php 中初始化


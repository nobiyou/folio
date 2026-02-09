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
            // 优先由统一性能后台接管，此处仅作为兜底注册
            if (!class_exists('folio_Unified_Performance_Admin')) {
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
            'title' => '基础环境检查',
            'status' => 'good',
            'tests' => array()
        );

        // PHP版本检查
        $php_version = PHP_VERSION;
        $min_php_version = '7.4';
        $this->add_test_result($section, 'php_version', array(
            'label' => 'PHP版本',
            'status' => version_compare($php_version, $min_php_version, '>=') ? 'good' : 'critical',
            'value' => $php_version,
            'description' => version_compare($php_version, $min_php_version, '>=') 
                ? 'PHP版本符合要求' 
                : "PHP版本过低，建议升级到 {$min_php_version} 或更高版本",
            'actions' => version_compare($php_version, $min_php_version, '<') 
                ? array('升级PHP到最新版本') 
                : array()
        ));

        // 内存限制检查
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        $min_memory = 128 * 1024 * 1024; // 128MB
        $this->add_test_result($section, 'memory_limit', array(
            'label' => '内存限制',
            'status' => $memory_bytes >= $min_memory ? 'good' : 'warning',
            'value' => $memory_limit,
            'description' => $memory_bytes >= $min_memory 
                ? '内存限制充足' 
                : '内存限制较低，可能影响缓存性能',
            'actions' => $memory_bytes < $min_memory 
                ? array('增加PHP内存限制到256M或更高') 
                : array()
        ));

        // WordPress版本检查
        global $wp_version;
        $min_wp_version = '5.0';
        $this->add_test_result($section, 'wp_version', array(
            'label' => 'WordPress版本',
            'status' => version_compare($wp_version, $min_wp_version, '>=') ? 'good' : 'warning',
            'value' => $wp_version,
            'description' => version_compare($wp_version, $min_wp_version, '>=') 
                ? 'WordPress版本符合要求' 
                : 'WordPress版本较旧，建议升级',
            'actions' => version_compare($wp_version, $min_wp_version, '<') 
                ? array('升级WordPress到最新版本') 
                : array()
        ));
    }

    /**
     * 检查缓存后端
     */
    private function check_cache_backends() {
        $section = 'cache_backends';
        $this->health_results[$section] = array(
            'title' => '缓存后端检查',
            'status' => 'good',
            'tests' => array()
        );

        // Memcached检查
        $memcached_available = class_exists('Memcached');
        $this->add_test_result($section, 'memcached_extension', array(
            'label' => 'Memcached扩展',
            'status' => $memcached_available ? 'good' : 'warning',
            'value' => $memcached_available ? '已安装' : '未安装',
            'description' => $memcached_available 
                ? 'Memcached扩展可用' 
                : 'Memcached扩展未安装，将使用内置缓存',
            'actions' => !$memcached_available 
                ? array('安装php-memcached扩展以获得更好性能') 
                : array()
        ));

        // Memcached连接测试
        if ($memcached_available && function_exists('folio_check_memcached_availability')) {
            $mc_status = folio_check_memcached_availability();
            $this->add_test_result($section, 'memcached_connection', array(
                'label' => 'Memcached连接',
                'status' => $mc_status['connection_test'] ? 'good' : 'critical',
                'value' => $mc_status['connection_test'] ? '连接正常' : '连接失败',
                'description' => $mc_status['connection_test'] 
                    ? 'Memcached服务连接正常' 
                    : 'Memcached服务连接失败',
                'actions' => !$mc_status['connection_test'] 
                    ? array('检查Memcached服务状态', '验证连接配置') 
                    : array()
            ));
        }

        // Redis检查
        $redis_available = class_exists('Redis');
        $this->add_test_result($section, 'redis_extension', array(
            'label' => 'Redis扩展',
            'status' => $redis_available ? 'good' : 'info',
            'value' => $redis_available ? '已安装' : '未安装',
            'description' => $redis_available 
                ? 'Redis扩展可用' 
                : 'Redis扩展未安装（可选）',
            'actions' => array()
        ));

        // 对象缓存检查
        $object_cache_enabled = wp_using_ext_object_cache();
        $this->add_test_result($section, 'object_cache', array(
            'label' => '对象缓存',
            'status' => $object_cache_enabled ? 'good' : 'warning',
            'value' => $object_cache_enabled ? '已启用' : '未启用',
            'description' => $object_cache_enabled 
                ? '外部对象缓存已启用' 
                : '使用内置对象缓存，性能有限',
            'actions' => !$object_cache_enabled 
                ? array('安装并启用外部对象缓存') 
                : array()
        ));
    }

    /**
     * 检查文件系统
     */
    private function check_file_system() {
        $section = 'file_system';
        $this->health_results[$section] = array(
            'title' => '文件系统检查',
            'status' => 'good',
            'tests' => array()
        );

        // wp-content目录写权限
        $wp_content_writable = is_writable(WP_CONTENT_DIR);
        $this->add_test_result($section, 'wp_content_writable', array(
            'label' => 'wp-content写权限',
            'status' => $wp_content_writable ? 'good' : 'critical',
            'value' => $wp_content_writable ? '可写' : '不可写',
            'description' => $wp_content_writable 
                ? 'wp-content目录具有写权限' 
                : 'wp-content目录无写权限，无法安装object-cache.php',
            'actions' => !$wp_content_writable 
                ? array('设置wp-content目录写权限为755或775') 
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
                'value' => $is_folio_version ? 'Folio版本' : '第三方版本',
                'description' => $is_folio_version 
                    ? 'Folio优化的object-cache.php已安装' 
                    : '检测到第三方object-cache.php文件',
                'actions' => !$is_folio_version 
                    ? array('考虑替换为Folio优化版本') 
                    : array()
            ));
        } else {
            $this->add_test_result($section, 'object_cache_file', array(
                'label' => 'object-cache.php',
                'status' => 'warning',
                'value' => '未安装',
                'description' => '未安装object-cache.php，使用WordPress内置缓存',
                'actions' => array('安装Folio对象缓存以提升性能')
            ));
        }

        // 缓存目录检查
        $cache_dir = WP_CONTENT_DIR . '/cache';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        $cache_dir_writable = is_writable($cache_dir);
        $this->add_test_result($section, 'cache_directory', array(
            'label' => '缓存目录',
            'status' => $cache_dir_writable ? 'good' : 'warning',
            'value' => $cache_dir_writable ? '可写' : '不可写',
            'description' => $cache_dir_writable 
                ? '缓存目录具有写权限' 
                : '缓存目录权限不足',
            'actions' => !$cache_dir_writable 
                ? array('设置缓存目录写权限') 
                : array()
        ));
    }

    /**
     * 检查性能指标
     */
    private function check_performance_metrics() {
        $section = 'performance_metrics';
        $this->health_results[$section] = array(
            'title' => '性能指标检查',
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
                'label' => '缓存命中率',
                'status' => $hit_rate_status,
                'value' => number_format($hit_rate, 1) . '%',
                'description' => $this->get_hit_rate_description($hit_rate),
                'actions' => $hit_rate < 80 ? array('优化缓存配置', '增加缓存过期时间') : array()
            ));

            // 内存使用
            $memory_usage = $stats['memory_usage'] ?? 0;
            $memory_status = $memory_usage < 100 * 1024 * 1024 ? 'good' : 'warning'; // 100MB
            $this->add_test_result($section, 'memory_usage', array(
                'label' => '内存使用',
                'status' => $memory_status,
                'value' => size_format($memory_usage),
                'description' => $memory_status === 'good' 
                    ? '内存使用正常' 
                    : '内存使用较高',
                'actions' => $memory_status !== 'good' 
                    ? array('清理过期缓存', '优化缓存策略') 
                    : array()
            ));
        }

        // 数据库查询数量
        $query_count = get_num_queries();
        $query_status = $query_count < 30 ? 'good' : ($query_count < 50 ? 'warning' : 'critical');
        $this->add_test_result($section, 'db_queries', array(
            'label' => '数据库查询',
            'status' => $query_status,
            'value' => $query_count . ' 次',
            'description' => $this->get_query_count_description($query_count),
            'actions' => $query_count > 30 ? array('启用查询缓存', '优化数据库查询') : array()
        ));
    }

    /**
     * 检查配置
     */
    private function check_configuration() {
        $section = 'configuration';
        $this->health_results[$section] = array(
            'title' => '配置检查',
            'status' => 'good',
            'tests' => array()
        );

        // 缓存启用状态
        $cache_enabled = get_option('folio_cache_enabled', true);
        $this->add_test_result($section, 'cache_enabled', array(
            'label' => '缓存启用',
            'status' => $cache_enabled ? 'good' : 'critical',
            'value' => $cache_enabled ? '已启用' : '已禁用',
            'description' => $cache_enabled 
                ? 'Folio缓存系统已启用' 
                : 'Folio缓存系统已禁用',
            'actions' => !$cache_enabled 
                ? array('启用Folio缓存系统') 
                : array()
        ));

        // 自动清理配置
        $auto_cleanup = get_option('folio_cache_auto_cleanup', true);
        $this->add_test_result($section, 'auto_cleanup', array(
            'label' => '自动清理',
            'status' => $auto_cleanup ? 'good' : 'warning',
            'value' => $auto_cleanup ? '已启用' : '已禁用',
            'description' => $auto_cleanup 
                ? '自动清理过期缓存已启用' 
                : '自动清理已禁用，需要手动清理',
            'actions' => !$auto_cleanup 
                ? array('启用自动清理功能') 
                : array()
        ));

        // 调试模式检查
        $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        $this->add_test_result($section, 'debug_mode', array(
            'label' => '调试模式',
            'status' => $debug_enabled ? 'warning' : 'good',
            'value' => $debug_enabled ? '已启用' : '已禁用',
            'description' => $debug_enabled 
                ? '调试模式已启用，可能影响性能' 
                : '调试模式已禁用',
            'actions' => $debug_enabled 
                ? array('生产环境建议禁用调试模式') 
                : array()
        ));
    }

    /**
     * 检查功能
     */
    private function check_functionality() {
        $section = 'functionality';
        $this->health_results[$section] = array(
            'title' => '功能测试',
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
            'label' => '缓存读写',
            'status' => $cache_rw_status,
            'value' => $cache_rw_status === 'good' ? '正常' : '异常',
            'description' => $cache_rw_status === 'good' 
                ? '缓存读写功能正常' 
                : '缓存读写功能异常',
            'actions' => $cache_rw_status !== 'good' 
                ? array('检查缓存配置', '重启缓存服务') 
                : array()
        ));

        // 权限检查功能测试
        $permission_function_exists = function_exists('folio_can_user_access_article');
        $this->add_test_result($section, 'permission_function', array(
            'label' => '权限检查功能',
            'status' => $permission_function_exists ? 'good' : 'warning',
            'value' => $permission_function_exists ? '可用' : '不可用',
            'description' => $permission_function_exists 
                ? '权限检查功能正常' 
                : '权限检查功能不可用',
            'actions' => !$permission_function_exists 
                ? array('检查相关插件和主题文件') 
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
            return '缓存命中率优秀';
        } elseif ($hit_rate > 80) {
            return '缓存命中率良好';
        } elseif ($hit_rate > 60) {
            return '缓存命中率一般，建议优化';
        } else {
            return '缓存命中率较低，需要优化';
        }
    }

    /**
     * 获取查询数量描述
     */
    private function get_query_count_description($count) {
        if ($count < 20) {
            return '数据库查询数量优秀';
        } elseif ($count < 30) {
            return '数据库查询数量良好';
        } elseif ($count < 50) {
            return '数据库查询数量较多，建议优化';
        } else {
            return '数据库查询数量过多，需要优化';
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
        if (!wp_verify_nonce($_POST['nonce'], 'folio_cache_health_check')) {
            wp_send_json_error('安全验证失败');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
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
                <p><strong>缓存系统健康警告</strong></p>
                <ul>
                    <?php foreach ($critical_issues as $issue) : ?>
                        <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <button type="button" class="button" onclick="runCacheHealthCheck()">
                        运行完整健康检查
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
            $issues[] = 'Memcached扩展未安装，缓存性能受限';
        }

        // 检查object-cache.php
        if (!wp_using_ext_object_cache()) {
            $issues[] = '外部对象缓存未启用，建议安装object-cache.php';
        }

        // 检查写权限
        if (!is_writable(WP_CONTENT_DIR)) {
            $issues[] = 'wp-content目录无写权限，无法管理缓存文件';
        }

        return $issues;
    }
}

// 缓存健康检查器类已在 functions.php 中初始化

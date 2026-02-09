<?php
/**
 * Cache Backend Validator
 * 
 * 缓存后端验证器 - 深入检查缓存后端的配置、性能和数据正确性
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Cache_Backend_Validator {

    /**
     * 验证结果
     */
    private $validation_results = array();

    /**
     * 构造函数
     */
    public function __construct() {
        if (is_admin() && current_user_can('manage_options')) {
            add_action('wp_ajax_folio_validate_cache_backend', array($this, 'ajax_validate_backend'));
        }
    }

    /**
     * 执行完整的后端验证
     */
    public function validate_cache_backend() {
        $this->validation_results = array();

        // 1. 检查WordPress缓存配置
        $this->validate_wordpress_cache_config();

        // 2. 检查object-cache.php
        $this->validate_object_cache_file();

        // 3. 检查Memcached后端
        $this->validate_memcached_backend();

        // 4. 检查Redis后端
        $this->validate_redis_backend();

        // 5. 验证缓存数据流
        $this->validate_cache_data_flow();

        // 6. 性能基准测试
        $this->validate_cache_performance();

        // 7. 数据一致性检查
        $this->validate_data_consistency();

        return $this->validation_results;
    }

    /**
     * 验证WordPress缓存配置
     */
    private function validate_wordpress_cache_config() {
        $section = 'wordpress_config';
        $this->validation_results[$section] = array(
            'title' => 'WordPress缓存配置',
            'status' => 'good',
            'tests' => array()
        );

        // 检查WP_CACHE常量
        $wp_cache_defined = defined('WP_CACHE');
        $wp_cache_enabled = $wp_cache_defined && WP_CACHE;
        
        $this->add_validation_result($section, 'wp_cache_constant', array(
            'label' => 'WP_CACHE常量',
            'status' => $wp_cache_enabled ? 'good' : 'warning',
            'value' => $wp_cache_enabled ? '已启用' : '未启用',
            'details' => $wp_cache_enabled ? 
                'WP_CACHE常量已正确设置' : 
                'WP_CACHE常量未设置，可能影响缓存性能'
        ));

        // 检查外部对象缓存
        $external_cache = wp_using_ext_object_cache();
        $this->add_validation_result($section, 'external_object_cache', array(
            'label' => '外部对象缓存',
            'status' => $external_cache ? 'good' : 'warning',
            'value' => $external_cache ? '已启用' : '未启用',
            'details' => $external_cache ? 
                '正在使用外部对象缓存' : 
                '使用WordPress内置缓存，性能有限'
        ));

        // 检查缓存组
        global $wp_object_cache;
        if (isset($wp_object_cache) && method_exists($wp_object_cache, 'get_global_groups')) {
            $global_groups = $wp_object_cache->get_global_groups();
            $this->add_validation_result($section, 'global_cache_groups', array(
                'label' => '全局缓存组',
                'status' => 'info',
                'value' => count($global_groups) . ' 个组',
                'details' => '全局缓存组: ' . implode(', ', $global_groups)
            ));
        }

        // 检查非持久化组
        if (isset($wp_object_cache) && method_exists($wp_object_cache, 'get_non_persistent_groups')) {
            $non_persistent_groups = $wp_object_cache->get_non_persistent_groups();
            $this->add_validation_result($section, 'non_persistent_groups', array(
                'label' => '非持久化组',
                'status' => 'info',
                'value' => count($non_persistent_groups) . ' 个组',
                'details' => '非持久化组: ' . implode(', ', $non_persistent_groups)
            ));
        }
    }

    /**
     * 验证object-cache.php文件
     */
    private function validate_object_cache_file() {
        $section = 'object_cache_file';
        $this->validation_results[$section] = array(
            'title' => 'object-cache.php文件',
            'status' => 'good',
            'tests' => array()
        );

        $object_cache_path = WP_CONTENT_DIR . '/object-cache.php';
        $file_exists = file_exists($object_cache_path);

        $this->add_validation_result($section, 'file_exists', array(
            'label' => '文件存在',
            'status' => $file_exists ? 'good' : 'warning',
            'value' => $file_exists ? '存在' : '不存在',
            'details' => $file_exists ? 
                'object-cache.php文件已安装' : 
                '未安装object-cache.php，使用WordPress默认缓存'
        ));

        if ($file_exists) {
            // 检查文件权限
            $file_readable = is_readable($object_cache_path);
            $this->add_validation_result($section, 'file_readable', array(
                'label' => '文件可读',
                'status' => $file_readable ? 'good' : 'critical',
                'value' => $file_readable ? '是' : '否',
                'details' => $file_readable ? 
                    '文件权限正常' : 
                    '文件权限不足，无法读取'
            ));

            // 检查文件大小
            $file_size = filesize($object_cache_path);
            $this->add_validation_result($section, 'file_size', array(
                'label' => '文件大小',
                'status' => $file_size > 1000 ? 'good' : 'warning',
                'value' => size_format($file_size),
                'details' => $file_size > 1000 ? 
                    '文件大小正常' : 
                    '文件可能不完整或为空'
            ));

            // 检查文件内容
            if ($file_readable) {
                $file_content = file_get_contents($object_cache_path);
                
                // 检查是否为Folio版本
                $is_folio_version = strpos($file_content, 'Folio') !== false;
                $this->add_validation_result($section, 'folio_version', array(
                    'label' => 'Folio版本',
                    'status' => $is_folio_version ? 'good' : 'info',
                    'value' => $is_folio_version ? '是' : '否',
                    'details' => $is_folio_version ? 
                        '使用Folio优化版本' : 
                        '使用第三方版本'
                ));

                // 检查Memcached支持
                $has_memcached = strpos($file_content, 'Memcached') !== false;
                $this->add_validation_result($section, 'memcached_support', array(
                    'label' => 'Memcached支持',
                    'status' => $has_memcached ? 'good' : 'warning',
                    'value' => $has_memcached ? '支持' : '不支持',
                    'details' => $has_memcached ? 
                        '包含Memcached支持' : 
                        '可能不支持Memcached'
                ));

                // 语法检查
                $syntax_check = $this->check_php_syntax($object_cache_path);
                $this->add_validation_result($section, 'syntax_check', array(
                    'label' => 'PHP语法',
                    'status' => $syntax_check['valid'] ? 'good' : 'critical',
                    'value' => $syntax_check['valid'] ? '正确' : '错误',
                    'details' => $syntax_check['valid'] ? 
                        'PHP语法正确' : 
                        '语法错误: ' . $syntax_check['error']
                ));
            }
        }
    }

    /**
     * 验证Memcached后端
     */
    private function validate_memcached_backend() {
        $section = 'memcached_backend';
        $this->validation_results[$section] = array(
            'title' => 'Memcached后端',
            'status' => 'good',
            'tests' => array()
        );

        // 检查Memcached扩展
        $extension_loaded = extension_loaded('memcached');
        $this->add_validation_result($section, 'extension_loaded', array(
            'label' => 'Memcached扩展',
            'status' => $extension_loaded ? 'good' : 'warning',
            'value' => $extension_loaded ? '已加载' : '未加载',
            'details' => $extension_loaded ? 
                'Memcached PHP扩展可用' : 
                'Memcached PHP扩展未安装'
        ));

        if ($extension_loaded) {
            try {
                $memcached = new Memcached();
                
                // 检查服务器连接
                $memcached->addServer('127.0.0.1', 11211);
                $version = $memcached->getVersion();
                
                if (!empty($version)) {
                    $server_version = reset($version);
                    $this->add_validation_result($section, 'server_connection', array(
                        'label' => '服务器连接',
                        'status' => 'good',
                        'value' => '已连接',
                        'details' => 'Memcached服务器版本: ' . $server_version
                    ));

                    // 性能测试
                    $perf_result = $this->test_memcached_performance($memcached);
                    $this->add_validation_result($section, 'performance_test', array(
                        'label' => '性能测试',
                        'status' => $perf_result['status'],
                        'value' => $perf_result['summary'],
                        'details' => $perf_result['details']
                    ));

                    // 获取统计信息
                    $stats = $memcached->getStats();
                    if (!empty($stats)) {
                        $server_stats = reset($stats);
                        if (is_array($server_stats)) {
                            $this->add_validation_result($section, 'server_stats', array(
                                'label' => '服务器统计',
                                'status' => 'info',
                                'value' => '可获取',
                                'details' => $this->format_memcached_stats($server_stats)
                            ));
                        }
                    }

                } else {
                    $this->add_validation_result($section, 'server_connection', array(
                        'label' => '服务器连接',
                        'status' => 'critical',
                        'value' => '连接失败',
                        'details' => '无法连接到Memcached服务器'
                    ));
                }

            } catch (Exception $e) {
                $this->add_validation_result($section, 'connection_error', array(
                    'label' => '连接错误',
                    'status' => 'critical',
                    'value' => '异常',
                    'details' => 'Memcached连接异常: ' . $e->getMessage()
                ));
            }
        }
    }

    /**
     * 验证Redis后端
     */
    private function validate_redis_backend() {
        $section = 'redis_backend';
        $this->validation_results[$section] = array(
            'title' => 'Redis后端',
            'status' => 'good',
            'tests' => array()
        );

        // 检查Redis扩展
        $extension_loaded = extension_loaded('redis');
        $this->add_validation_result($section, 'extension_loaded', array(
            'label' => 'Redis扩展',
            'status' => $extension_loaded ? 'good' : 'info',
            'value' => $extension_loaded ? '已加载' : '未加载',
            'details' => $extension_loaded ? 
                'Redis PHP扩展可用' : 
                'Redis PHP扩展未安装（可选）'
        ));

        if ($extension_loaded) {
            try {
                $redis = new Redis();
                $connected = $redis->connect('127.0.0.1', 6379, 2);
                
                if ($connected) {
                    $this->add_validation_result($section, 'server_connection', array(
                        'label' => '服务器连接',
                        'status' => 'good',
                        'value' => '已连接',
                        'details' => 'Redis服务器连接成功'
                    ));

                    // 获取服务器信息
                    $info = $redis->info();
                    if (!empty($info)) {
                        $version = $info['redis_version'] ?? 'Unknown';
                        $this->add_validation_result($section, 'server_info', array(
                            'label' => '服务器信息',
                            'status' => 'info',
                            'value' => "版本 $version",
                            'details' => $this->format_redis_info($info)
                        ));
                    }

                } else {
                    $this->add_validation_result($section, 'server_connection', array(
                        'label' => '服务器连接',
                        'status' => 'warning',
                        'value' => '连接失败',
                        'details' => '无法连接到Redis服务器'
                    ));
                }

            } catch (Exception $e) {
                $this->add_validation_result($section, 'connection_error', array(
                    'label' => '连接错误',
                    'status' => 'warning',
                    'value' => '异常',
                    'details' => 'Redis连接异常: ' . $e->getMessage()
                ));
            }
        }
    }

    /**
     * 验证缓存数据流
     */
    private function validate_cache_data_flow() {
        $section = 'data_flow';
        $this->validation_results[$section] = array(
            'title' => '缓存数据流',
            'status' => 'good',
            'tests' => array()
        );

        // 测试基本数据流
        $test_key = 'folio_dataflow_test_' . time();
        $test_value = array(
            'timestamp' => time(),
            'data' => 'test_data_' . wp_generate_password(8, false),
            'complex' => array('nested' => array('value' => 123))
        );

        // 写入测试
        $write_success = wp_cache_set($test_key, $test_value, 'folio_validation', 300);
        $this->add_validation_result($section, 'write_operation', array(
            'label' => '写入操作',
            'status' => $write_success ? 'good' : 'critical',
            'value' => $write_success ? '成功' : '失败',
            'details' => $write_success ? 
                '缓存写入操作正常' : 
                '缓存写入操作失败'
        ));

        // 读取测试
        $read_value = wp_cache_get($test_key, 'folio_validation');
        $read_success = ($read_value !== false);
        $this->add_validation_result($section, 'read_operation', array(
            'label' => '读取操作',
            'status' => $read_success ? 'good' : 'critical',
            'value' => $read_success ? '成功' : '失败',
            'details' => $read_success ? 
                '缓存读取操作正常' : 
                '缓存读取操作失败'
        ));

        // 数据完整性测试
        if ($read_success) {
            $data_integrity = ($read_value === $test_value);
            $this->add_validation_result($section, 'data_integrity', array(
                'label' => '数据完整性',
                'status' => $data_integrity ? 'good' : 'critical',
                'value' => $data_integrity ? '完整' : '损坏',
                'details' => $data_integrity ? 
                    '缓存数据完整性正常' : 
                    '缓存数据可能损坏或序列化有问题'
            ));
        }

        // 删除测试
        $delete_success = wp_cache_delete($test_key, 'folio_validation');
        $this->add_validation_result($section, 'delete_operation', array(
            'label' => '删除操作',
            'status' => $delete_success ? 'good' : 'warning',
            'value' => $delete_success ? '成功' : '失败',
            'details' => $delete_success ? 
                '缓存删除操作正常' : 
                '缓存删除操作可能有问题'
        ));

        // 验证删除
        $verify_delete = wp_cache_get($test_key, 'folio_validation');
        $delete_verified = ($verify_delete === false);
        $this->add_validation_result($section, 'delete_verification', array(
            'label' => '删除验证',
            'status' => $delete_verified ? 'good' : 'warning',
            'value' => $delete_verified ? '已删除' : '仍存在',
            'details' => $delete_verified ? 
                '缓存项已正确删除' : 
                '缓存项删除后仍可访问'
        ));
    }

    /**
     * 验证缓存性能
     */
    private function validate_cache_performance() {
        $section = 'performance';
        $this->validation_results[$section] = array(
            'title' => '缓存性能',
            'status' => 'good',
            'tests' => array()
        );

        // 批量写入性能测试
        $batch_size = 100;
        $test_data = str_repeat('x', 1024); // 1KB数据

        $start_time = microtime(true);
        for ($i = 0; $i < $batch_size; $i++) {
            wp_cache_set("perf_test_$i", $test_data, 'folio_perf_validation', 300);
        }
        $write_time = microtime(true) - $start_time;

        $write_ops_per_sec = $batch_size / $write_time;
        $write_status = $write_ops_per_sec > 1000 ? 'good' : ($write_ops_per_sec > 500 ? 'warning' : 'critical');

        $this->add_validation_result($section, 'write_performance', array(
            'label' => '写入性能',
            'status' => $write_status,
            'value' => number_format($write_ops_per_sec, 0) . ' ops/s',
            'details' => "批量写入 $batch_size 个1KB条目耗时 " . number_format($write_time * 1000, 2) . "ms"
        ));

        // 批量读取性能测试
        $start_time = microtime(true);
        for ($i = 0; $i < $batch_size; $i++) {
            wp_cache_get("perf_test_$i", 'folio_perf_validation');
        }
        $read_time = microtime(true) - $start_time;

        $read_ops_per_sec = $batch_size / $read_time;
        $read_status = $read_ops_per_sec > 2000 ? 'good' : ($read_ops_per_sec > 1000 ? 'warning' : 'critical');

        $this->add_validation_result($section, 'read_performance', array(
            'label' => '读取性能',
            'status' => $read_status,
            'value' => number_format($read_ops_per_sec, 0) . ' ops/s',
            'details' => "批量读取 $batch_size 个1KB条目耗时 " . number_format($read_time * 1000, 2) . "ms"
        ));

        // 清理测试数据
        for ($i = 0; $i < $batch_size; $i++) {
            wp_cache_delete("perf_test_$i", 'folio_perf_validation');
        }

        // 性能比较
        $performance_ratio = $read_ops_per_sec / $write_ops_per_sec;
        $ratio_status = $performance_ratio > 1.5 ? 'good' : 'warning';

        $this->add_validation_result($section, 'performance_ratio', array(
            'label' => '读写比例',
            'status' => $ratio_status,
            'value' => number_format($performance_ratio, 2) . ':1',
            'details' => $performance_ratio > 1.5 ? 
                '读取性能优于写入，符合预期' : 
                '读写性能比例较低，可能需要优化'
        ));
    }

    /**
     * 验证数据一致性
     */
    private function validate_data_consistency() {
        $section = 'consistency';
        $this->validation_results[$section] = array(
            'title' => '数据一致性',
            'status' => 'good',
            'tests' => array()
        );

        // 测试不同数据类型
        $test_cases = array(
            'string' => 'Hello World',
            'integer' => 42,
            'float' => 3.14159,
            'boolean_true' => true,
            'boolean_false' => false,
            'null' => null,
            'empty_string' => '',
            'zero' => 0,
            'array' => array('key' => 'value', 'nested' => array('data' => 123)),
            'object' => (object) array('property' => 'value'),
            'unicode' => '测试中文字符 🎉',
            'large_string' => str_repeat('Large data test ', 1000)
        );

        $consistency_issues = 0;
        foreach ($test_cases as $type => $original_data) {
            $test_key = "consistency_test_{$type}_" . time();
            
            wp_cache_set($test_key, $original_data, 'folio_consistency_validation', 300);
            $retrieved_data = wp_cache_get($test_key, 'folio_consistency_validation');
            
            $is_consistent = ($original_data === $retrieved_data);
            if (!$is_consistent) {
                $consistency_issues++;
            }
            
            wp_cache_delete($test_key, 'folio_consistency_validation');
        }

        $consistency_rate = ((count($test_cases) - $consistency_issues) / count($test_cases)) * 100;
        $consistency_status = $consistency_rate == 100 ? 'good' : ($consistency_rate > 90 ? 'warning' : 'critical');

        $this->add_validation_result($section, 'type_consistency', array(
            'label' => '类型一致性',
            'status' => $consistency_status,
            'value' => number_format($consistency_rate, 1) . '%',
            'details' => "测试了 " . count($test_cases) . " 种数据类型，" . 
                        (count($test_cases) - $consistency_issues) . " 种通过测试"
        ));
    }

    /**
     * 测试Memcached性能
     */
    private function test_memcached_performance($memcached) {
        $test_sizes = array(
            'small' => 100,
            'medium' => 1024,
            'large' => 10240
        );

        $results = array();
        foreach ($test_sizes as $size_name => $size_bytes) {
            $test_data = str_repeat('x', $size_bytes);
            $test_key = "perf_test_{$size_name}_" . time();

            // 写入测试
            $start_time = microtime(true);
            for ($i = 0; $i < 50; $i++) {
                $memcached->set($test_key . "_$i", $test_data, 300);
            }
            $write_time = microtime(true) - $start_time;

            // 读取测试
            $start_time = microtime(true);
            for ($i = 0; $i < 50; $i++) {
                $memcached->get($test_key . "_$i");
            }
            $read_time = microtime(true) - $start_time;

            // 清理
            for ($i = 0; $i < 50; $i++) {
                $memcached->delete($test_key . "_$i");
            }

            $results[$size_name] = array(
                'write_ops_per_sec' => 50 / $write_time,
                'read_ops_per_sec' => 50 / $read_time
            );
        }

        $avg_write_ops = array_sum(array_column($results, 'write_ops_per_sec')) / count($results);
        $avg_read_ops = array_sum(array_column($results, 'read_ops_per_sec')) / count($results);

        $status = ($avg_write_ops > 1000 && $avg_read_ops > 2000) ? 'good' : 
                 (($avg_write_ops > 500 && $avg_read_ops > 1000) ? 'warning' : 'critical');

        return array(
            'status' => $status,
            'summary' => number_format($avg_write_ops, 0) . '/' . number_format($avg_read_ops, 0) . ' ops/s',
            'details' => "平均写入: " . number_format($avg_write_ops, 0) . " ops/s, " .
                        "平均读取: " . number_format($avg_read_ops, 0) . " ops/s"
        );
    }

    /**
     * 格式化Memcached统计信息
     */
    private function format_memcached_stats($stats) {
        $formatted = array();
        
        if (isset($stats['version'])) {
            $formatted[] = "版本: {$stats['version']}";
        }
        if (isset($stats['curr_items'])) {
            $formatted[] = "当前条目: " . number_format($stats['curr_items']);
        }
        if (isset($stats['bytes'])) {
            $formatted[] = "内存使用: " . size_format($stats['bytes']);
        }
        if (isset($stats['get_hits']) && isset($stats['get_misses'])) {
            $total = $stats['get_hits'] + $stats['get_misses'];
            if ($total > 0) {
                $hit_rate = ($stats['get_hits'] / $total) * 100;
                $formatted[] = "命中率: " . number_format($hit_rate, 2) . "%";
            }
        }

        return implode(', ', $formatted);
    }

    /**
     * 格式化Redis信息
     */
    private function format_redis_info($info) {
        $formatted = array();
        
        if (isset($info['redis_version'])) {
            $formatted[] = "版本: {$info['redis_version']}";
        }
        if (isset($info['used_memory_human'])) {
            $formatted[] = "内存使用: {$info['used_memory_human']}";
        }
        if (isset($info['connected_clients'])) {
            $formatted[] = "连接数: {$info['connected_clients']}";
        }

        return implode(', ', $formatted);
    }

    /**
     * 检查PHP语法
     */
    private function check_php_syntax($file_path) {
        $output = array();
        $return_var = 0;
        
        exec('php -l ' . escapeshellarg($file_path) . ' 2>&1', $output, $return_var);
        
        return array(
            'valid' => $return_var === 0,
            'error' => $return_var !== 0 ? implode("\n", $output) : null
        );
    }

    /**
     * 添加验证结果
     */
    private function add_validation_result($section, $test_id, $result) {
        $this->validation_results[$section]['tests'][$test_id] = $result;
        
        // 更新section状态
        if ($result['status'] === 'critical') {
            $this->validation_results[$section]['status'] = 'critical';
        } elseif ($result['status'] === 'warning' && $this->validation_results[$section]['status'] !== 'critical') {
            $this->validation_results[$section]['status'] = 'warning';
        }
    }

    /**
     * AJAX验证后端
     */
    public function ajax_validate_backend() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_cache_backend_validation')) {
            wp_send_json_error('安全验证失败');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }

        $results = $this->validate_cache_backend();
        wp_send_json_success($results);
    }
}

// 初始化缓存后端验证器
new folio_Cache_Backend_Validator();

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
            'title' => __('WordPress cache configuration', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        // 检查WP_CACHE常量
        $wp_cache_defined = defined('WP_CACHE');
        $wp_cache_enabled = $wp_cache_defined && WP_CACHE;
        
        $this->add_validation_result($section, 'wp_cache_constant', array(
            'label' => __('WP_CACHE constant', 'folio'),
            'status' => $wp_cache_enabled ? 'good' : 'warning',
            'value' => $wp_cache_enabled ? __('Enabled', 'folio') : __('Disabled', 'folio'),
            'details' => $wp_cache_enabled ? 
                __('WP_CACHE is configured correctly', 'folio') : 
                __('WP_CACHE is not set and may affect cache performance', 'folio')
        ));

        // 检查外部对象缓存
        $external_cache = wp_using_ext_object_cache();
        $this->add_validation_result($section, 'external_object_cache', array(
            'label' => __('External object cache', 'folio'),
            'status' => $external_cache ? 'good' : 'warning',
            'value' => $external_cache ? __('Enabled', 'folio') : __('Disabled', 'folio'),
            'details' => $external_cache ? 
                __('Using external object cache', 'folio') : 
                __('Using built-in WordPress cache with limited performance', 'folio')
        ));

        // 检查缓存组
        global $wp_object_cache;
        if (isset($wp_object_cache) && method_exists($wp_object_cache, 'get_global_groups')) {
            $global_groups = $wp_object_cache->get_global_groups();
            $this->add_validation_result($section, 'global_cache_groups', array(
                'label' => __('Global cache groups', 'folio'),
                'status' => 'info',
                'value' => sprintf(
                    /* translators: %d: number of groups. */
                    __('%d groups', 'folio'),
                    count($global_groups)
                ),
                'details' => __('Global groups: ', 'folio') . implode(', ', $global_groups)
            ));
        }

        // 检查非持久化组
        if (isset($wp_object_cache) && method_exists($wp_object_cache, 'get_non_persistent_groups')) {
            $non_persistent_groups = $wp_object_cache->get_non_persistent_groups();
            $this->add_validation_result($section, 'non_persistent_groups', array(
                'label' => __('Non-persistent groups', 'folio'),
                'status' => 'info',
                'value' => sprintf(
                    /* translators: %d: number of groups. */
                    __('%d groups', 'folio'),
                    count($non_persistent_groups)
                ),
                'details' => __('Non-persistent groups: ', 'folio') . implode(', ', $non_persistent_groups)
            ));
        }
    }

    /**
     * 验证object-cache.php文件
     */
    private function validate_object_cache_file() {
        $section = 'object_cache_file';
        $this->validation_results[$section] = array(
            'title' => __('object-cache.php file', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        $object_cache_path = WP_CONTENT_DIR . '/object-cache.php';
        $file_exists = file_exists($object_cache_path);

        $this->add_validation_result($section, 'file_exists', array(
            'label' => __('File exists', 'folio'),
            'status' => $file_exists ? 'good' : 'warning',
            'value' => $file_exists ? __('Exists', 'folio') : __('Not found', 'folio'),
            'details' => $file_exists ? 
                __('object-cache.php is installed', 'folio') : 
                __('object-cache.php is missing, using default WordPress cache', 'folio')
        ));

        if ($file_exists) {
            // 检查文件权限
            $file_readable = is_readable($object_cache_path);
            $this->add_validation_result($section, 'file_readable', array(
                'label' => __('File readable', 'folio'),
                'status' => $file_readable ? 'good' : 'critical',
                'value' => $file_readable ? __('Yes', 'folio') : __('No', 'folio'),
                'details' => $file_readable ? 
                    __('File permissions are valid', 'folio') : 
                    __('Insufficient file permissions, cannot read file', 'folio')
            ));

            // 检查文件大小
            $file_size = filesize($object_cache_path);
            $this->add_validation_result($section, 'file_size', array(
                'label' => __('File size', 'folio'),
                'status' => $file_size > 1000 ? 'good' : 'warning',
                'value' => size_format($file_size),
                'details' => $file_size > 1000 ? 
                    __('File size looks normal', 'folio') : 
                    __('File may be incomplete or empty', 'folio')
            ));

            // 检查文件内容
            if ($file_readable) {
                $file_content = file_get_contents($object_cache_path);
                
                // 检查是否为Folio版本
                $is_folio_version = strpos($file_content, 'Folio') !== false;
                $this->add_validation_result($section, 'folio_version', array(
                    'label' => __('Folio version', 'folio'),
                    'status' => $is_folio_version ? 'good' : 'info',
                    'value' => $is_folio_version ? __('Yes', 'folio') : __('No', 'folio'),
                    'details' => $is_folio_version ? 
                        __('Using Folio optimized version', 'folio') : 
                        __('Using third-party version', 'folio')
                ));

                // 检查Memcached支持
                $has_memcached = strpos($file_content, 'Memcached') !== false;
                $this->add_validation_result($section, 'memcached_support', array(
                    'label' => __('Memcached support', 'folio'),
                    'status' => $has_memcached ? 'good' : 'warning',
                    'value' => $has_memcached ? __('Supported', 'folio') : __('Not supported', 'folio'),
                    'details' => $has_memcached ? 
                        __('Memcached support is included', 'folio') : 
                        __('Memcached support may be unavailable', 'folio')
                ));

                // 语法检查
                $syntax_check = $this->check_php_syntax($object_cache_path);
                $this->add_validation_result($section, 'syntax_check', array(
                    'label' => __('PHP syntax', 'folio'),
                    'status' => $syntax_check['valid'] ? 'good' : 'critical',
                    'value' => $syntax_check['valid'] ? __('Valid', 'folio') : __('Invalid', 'folio'),
                    'details' => $syntax_check['valid'] ? 
                        __('PHP syntax is valid', 'folio') : 
                        __('Syntax error: ', 'folio') . $syntax_check['error']
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
            'title' => __('Memcached backend', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        // 检查Memcached扩展
        $extension_loaded = extension_loaded('memcached');
        $this->add_validation_result($section, 'extension_loaded', array(
            'label' => __('Memcached extension', 'folio'),
            'status' => $extension_loaded ? 'good' : 'warning',
            'value' => $extension_loaded ? __('Loaded', 'folio') : __('Not loaded', 'folio'),
            'details' => $extension_loaded ? 
                __('Memcached PHP extension is available', 'folio') : 
                __('Memcached PHP extension is not installed', 'folio')
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
                        'label' => __('Server connection', 'folio'),
                        'status' => 'good',
                        'value' => __('Connected', 'folio'),
                        'details' => __('Memcached server version: ', 'folio') . $server_version
                    ));

                    // 性能测试
                    $perf_result = $this->test_memcached_performance($memcached);
                    $this->add_validation_result($section, 'performance_test', array(
                        'label' => __('Performance test', 'folio'),
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
                                'label' => __('Server stats', 'folio'),
                                'status' => 'info',
                                'value' => __('Available', 'folio'),
                                'details' => $this->format_memcached_stats($server_stats)
                            ));
                        }
                    }

                } else {
                    $this->add_validation_result($section, 'server_connection', array(
                        'label' => __('Server connection', 'folio'),
                        'status' => 'critical',
                        'value' => __('Connection failed', 'folio'),
                        'details' => __('Unable to connect to Memcached server', 'folio')
                    ));
                }

            } catch (Exception $e) {
                $this->add_validation_result($section, 'connection_error', array(
                    'label' => __('Connection error', 'folio'),
                    'status' => 'critical',
                    'value' => __('Exception', 'folio'),
                    'details' => __('Memcached connection exception: ', 'folio') . $e->getMessage()
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
            'title' => __('Redis backend', 'folio'),
            'status' => 'good',
            'tests' => array()
        );

        // 检查Redis扩展
        $extension_loaded = extension_loaded('redis');
        $this->add_validation_result($section, 'extension_loaded', array(
            'label' => __('Redis extension', 'folio'),
            'status' => $extension_loaded ? 'good' : 'info',
            'value' => $extension_loaded ? __('Loaded', 'folio') : __('Not loaded', 'folio'),
            'details' => $extension_loaded ? 
                __('Redis PHP extension is available', 'folio') : 
                __('Redis PHP extension is not installed (optional)', 'folio')
        ));

        if ($extension_loaded) {
            try {
                $redis = new Redis();
                $connected = $redis->connect('127.0.0.1', 6379, 2);
                
                if ($connected) {
                    $this->add_validation_result($section, 'server_connection', array(
                        'label' => __('Server connection', 'folio'),
                        'status' => 'good',
                        'value' => __('Connected', 'folio'),
                        'details' => __('Redis server connected successfully', 'folio')
                    ));

                    // 获取服务器信息
                    $info = $redis->info();
                    if (!empty($info)) {
                        $version = $info['redis_version'] ?? 'Unknown';
                        $this->add_validation_result($section, 'server_info', array(
                            'label' => __('Server info', 'folio'),
                            'status' => 'info',
                            'value' => sprintf(__('Version %s', 'folio'), $version),
                            'details' => $this->format_redis_info($info)
                        ));
                    }

                } else {
                    $this->add_validation_result($section, 'server_connection', array(
                        'label' => __('Server connection', 'folio'),
                        'status' => 'warning',
                        'value' => __('Connection failed', 'folio'),
                        'details' => __('Unable to connect to Redis server', 'folio')
                    ));
                }

            } catch (Exception $e) {
                $this->add_validation_result($section, 'connection_error', array(
                    'label' => __('Connection error', 'folio'),
                    'status' => 'warning',
                    'value' => __('Exception', 'folio'),
                    'details' => __('Redis connection exception: ', 'folio') . $e->getMessage()
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
            'title' => __('Cache data flow', 'folio'),
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
            'label' => __('Write operation', 'folio'),
            'status' => $write_success ? 'good' : 'critical',
            'value' => $write_success ? __('Success', 'folio') : __('Failed', 'folio'),
            'details' => $write_success ? 
                __('Cache write operation succeeded', 'folio') : 
                __('Cache write operation failed', 'folio')
        ));

        // 读取测试
        $read_value = wp_cache_get($test_key, 'folio_validation');
        $read_success = ($read_value !== false);
        $this->add_validation_result($section, 'read_operation', array(
            'label' => __('Read operation', 'folio'),
            'status' => $read_success ? 'good' : 'critical',
            'value' => $read_success ? __('Success', 'folio') : __('Failed', 'folio'),
            'details' => $read_success ? 
                __('Cache read operation succeeded', 'folio') : 
                __('Cache read operation failed', 'folio')
        ));

        // 数据完整性测试
        if ($read_success) {
            $data_integrity = ($read_value === $test_value);
            $this->add_validation_result($section, 'data_integrity', array(
                'label' => __('Data integrity', 'folio'),
                'status' => $data_integrity ? 'good' : 'critical',
                'value' => $data_integrity ? __('Intact', 'folio') : __('Corrupted', 'folio'),
                'details' => $data_integrity ? 
                    __('Cache data integrity is valid', 'folio') : 
                    __('Cache data may be corrupted or serialization has issues', 'folio')
            ));
        }

        // 删除测试
        $delete_success = wp_cache_delete($test_key, 'folio_validation');
        $this->add_validation_result($section, 'delete_operation', array(
            'label' => __('Delete operation', 'folio'),
            'status' => $delete_success ? 'good' : 'warning',
            'value' => $delete_success ? __('Success', 'folio') : __('Failed', 'folio'),
            'details' => $delete_success ? 
                __('Cache delete operation succeeded', 'folio') : 
                __('Cache delete operation may have issues', 'folio')
        ));

        // 验证删除
        $verify_delete = wp_cache_get($test_key, 'folio_validation');
        $delete_verified = ($verify_delete === false);
        $this->add_validation_result($section, 'delete_verification', array(
            'label' => __('Delete verification', 'folio'),
            'status' => $delete_verified ? 'good' : 'warning',
            'value' => $delete_verified ? __('Deleted', 'folio') : __('Still exists', 'folio'),
            'details' => $delete_verified ? 
                __('Cache item deleted correctly', 'folio') : 
                __('Cache item is still accessible after deletion', 'folio')
        ));
    }

    /**
     * 验证缓存性能
     */
    private function validate_cache_performance() {
        $section = 'performance';
        $this->validation_results[$section] = array(
            'title' => __('Cache performance', 'folio'),
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
            'label' => __('Write performance', 'folio'),
            'status' => $write_status,
            'value' => number_format($write_ops_per_sec, 0) . ' ops/s',
            'details' => sprintf(
                /* translators: 1: batch size, 2: milliseconds. */
                __('Batch write of %1$d 1KB items took %2$s ms', 'folio'),
                $batch_size,
                number_format($write_time * 1000, 2)
            )
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
            'label' => __('Read performance', 'folio'),
            'status' => $read_status,
            'value' => number_format($read_ops_per_sec, 0) . ' ops/s',
            'details' => sprintf(
                /* translators: 1: batch size, 2: milliseconds. */
                __('Batch read of %1$d 1KB items took %2$s ms', 'folio'),
                $batch_size,
                number_format($read_time * 1000, 2)
            )
        ));

        // 清理测试数据
        for ($i = 0; $i < $batch_size; $i++) {
            wp_cache_delete("perf_test_$i", 'folio_perf_validation');
        }

        // 性能比较
        $performance_ratio = $read_ops_per_sec / $write_ops_per_sec;
        $ratio_status = $performance_ratio > 1.5 ? 'good' : 'warning';

        $this->add_validation_result($section, 'performance_ratio', array(
            'label' => __('Read/write ratio', 'folio'),
            'status' => $ratio_status,
            'value' => number_format($performance_ratio, 2) . ':1',
            'details' => $performance_ratio > 1.5 ? 
                __('Read performance exceeds write performance, as expected', 'folio') : 
                __('Read/write performance ratio is low and may need optimization', 'folio')
        ));
    }

    /**
     * 验证数据一致性
     */
    private function validate_data_consistency() {
        $section = 'consistency';
        $this->validation_results[$section] = array(
            'title' => __('Data consistency', 'folio'),
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
            'unicode' => 'test unicode chars 🎉',
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
            'label' => __('Type consistency', 'folio'),
            'status' => $consistency_status,
            'value' => number_format($consistency_rate, 1) . '%',
            'details' => sprintf(
                /* translators: 1: tested types count, 2: passed count. */
                __('Tested %1$d data types, %2$d passed', 'folio'),
                count($test_cases),
                (count($test_cases) - $consistency_issues)
            )
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
            'details' => sprintf(
                /* translators: 1: avg write ops/s, 2: avg read ops/s. */
                __('Average write: %1$s ops/s, average read: %2$s ops/s', 'folio'),
                number_format($avg_write_ops, 0),
                number_format($avg_read_ops, 0)
            )
        );
    }

    /**
     * 格式化Memcached统计信息
     */
    private function format_memcached_stats($stats) {
        $formatted = array();
        
        if (isset($stats['version'])) {
            $formatted[] = sprintf(
                /* translators: %s: version number. */
                __('Version: %s', 'folio'),
                $stats['version']
            );
        }
        if (isset($stats['curr_items'])) {
            $formatted[] = sprintf(
                /* translators: %s: item count. */
                __('Current items: %s', 'folio'),
                number_format($stats['curr_items'])
            );
        }
        if (isset($stats['bytes'])) {
            $formatted[] = sprintf(
                /* translators: %s: memory usage. */
                __('Memory usage: %s', 'folio'),
                size_format($stats['bytes'])
            );
        }
        if (isset($stats['get_hits']) && isset($stats['get_misses'])) {
            $total = $stats['get_hits'] + $stats['get_misses'];
            if ($total > 0) {
                $hit_rate = ($stats['get_hits'] / $total) * 100;
                $formatted[] = sprintf(
                    /* translators: %s: hit rate percentage. */
                    __('Hit rate: %s%%', 'folio'),
                    number_format($hit_rate, 2)
                );
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
            $formatted[] = sprintf(
                /* translators: %s: redis version. */
                __('Version: %s', 'folio'),
                $info['redis_version']
            );
        }
        if (isset($info['used_memory_human'])) {
            $formatted[] = sprintf(
                /* translators: %s: memory usage. */
                __('Memory usage: %s', 'folio'),
                $info['used_memory_human']
            );
        }
        if (isset($info['connected_clients'])) {
            $formatted[] = sprintf(
                /* translators: %s: connected clients count. */
                __('Connections: %s', 'folio'),
                $info['connected_clients']
            );
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
            wp_send_json_error(__('Security verification failed', 'folio'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }

        $results = $this->validate_cache_backend();
        wp_send_json_success($results);
    }
}

// 初始化缓存后端验证器
new folio_Cache_Backend_Validator();

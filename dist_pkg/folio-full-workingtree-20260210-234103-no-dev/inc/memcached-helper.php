<?php
/**
 * Memcached Helper Functions
 * 
 * Memcached辅助函数 - 用于检测和配置Memcached
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 检测Memcached可用性
 */
function folio_check_memcached_availability() {
    $status = array(
        'extension_loaded' => extension_loaded('memcached'),
        'class_exists' => class_exists('Memcached'),
        'server_reachable' => false,
        'connection_test' => false,
        'performance_test' => array(),
        'recommendations' => array()
    );
    
    // 如果扩展未加载，返回基本信息
    if (!$status['extension_loaded'] || !$status['class_exists']) {
        $status['recommendations'][] = 'Please install the php-memcached extension';
        return $status;
    }
    
    // 测试服务器连接
    try {
        $mc = new Memcached();
        $mc->addServer('127.0.0.1', 11211);
        
        // 测试连接
        $version = $mc->getVersion();
        if (!empty($version)) {
            $status['server_reachable'] = true;
            $status['server_version'] = reset($version);
        }
        
        // 测试读写
        $test_key = 'folio_test_' . time();
        $test_value = 'test_data_' . rand(1000, 9999);
        
        if ($mc->set($test_key, $test_value, 60)) {
            $retrieved = $mc->get($test_key);
            if ($retrieved === $test_value) {
                $status['connection_test'] = true;
                $mc->delete($test_key); // 清理测试数据
            }
        }
        
        // 性能测试
        if ($status['connection_test']) {
            $status['performance_test'] = folio_memcached_performance_test($mc);
        }
        
    } catch (Exception $e) {
        $status['error'] = $e->getMessage();
    }
    
    // 生成建议
    if (!$status['server_reachable']) {
        $status['recommendations'][] = 'Please start the Memcached service';
        $status['recommendations'][] = 'Check whether port 11211 is open';
    }
    
    if (!$status['connection_test']) {
        $status['recommendations'][] = 'Check Memcached configuration';
        $status['recommendations'][] = 'Verify firewall settings';
    }
    
    return $status;
}

/**
 * Memcached性能测试
 */
function folio_memcached_performance_test($mc, $iterations = 100) {
    $results = array();
    
    // 测试数据
    $small_data = str_repeat('x', 100);    // 100B
    $medium_data = str_repeat('x', 1024);  // 1KB
    $large_data = str_repeat('x', 10240);  // 10KB
    
    $test_cases = array(
        'small' => $small_data,
        'medium' => $medium_data,
        'large' => $large_data
    );
    
    foreach ($test_cases as $size => $data) {
        // 写入测试
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $mc->set("test_{$size}_{$i}", $data, 3600);
        }
        $write_time = microtime(true) - $start;
        
        // 读取测试
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $mc->get("test_{$size}_{$i}");
        }
        $read_time = microtime(true) - $start;
        
        // 清理测试数据
        for ($i = 0; $i < $iterations; $i++) {
            $mc->delete("test_{$size}_{$i}");
        }
        
        $results[$size] = array(
            'data_size' => strlen($data),
            'write_ops_per_sec' => round($iterations / $write_time),
            'read_ops_per_sec' => round($iterations / $read_time),
            'avg_write_time_ms' => round(($write_time / $iterations) * 1000, 3),
            'avg_read_time_ms' => round(($read_time / $iterations) * 1000, 3)
        );
    }
    
    return $results;
}

/**
 * 获取Memcached统计信息
 */
function folio_get_memcached_stats() {
    if (!class_exists('Memcached')) {
        return false;
    }
    
    try {
        $mc = new Memcached();
        $mc->addServer('127.0.0.1', 11211);
        
        $stats = $mc->getStats();
        if (empty($stats)) {
            return false;
        }
        
        $server_stats = reset($stats);
        if (!$server_stats) {
            return false;
        }
        
        // 计算命中率
        $hits = $server_stats['get_hits'] ?? 0;
        $misses = $server_stats['get_misses'] ?? 0;
        $total_gets = $hits + $misses;
        $hit_rate = $total_gets > 0 ? ($hits / $total_gets) * 100 : 0;
        
        return array(
            'version' => $server_stats['version'] ?? 'Unknown',
            'uptime' => $server_stats['uptime'] ?? 0,
            'uptime_human' => human_time_diff(time() - ($server_stats['uptime'] ?? 0)),
            'curr_items' => $server_stats['curr_items'] ?? 0,
            'total_items' => $server_stats['total_items'] ?? 0,
            'bytes' => $server_stats['bytes'] ?? 0,
            'bytes_human' => size_format($server_stats['bytes'] ?? 0),
            'curr_connections' => $server_stats['curr_connections'] ?? 0,
            'total_connections' => $server_stats['total_connections'] ?? 0,
            'cmd_get' => $server_stats['cmd_get'] ?? 0,
            'cmd_set' => $server_stats['cmd_set'] ?? 0,
            'get_hits' => $hits,
            'get_misses' => $misses,
            'hit_rate' => round($hit_rate, 2),
            'evictions' => $server_stats['evictions'] ?? 0,
            'bytes_read' => $server_stats['bytes_read'] ?? 0,
            'bytes_written' => $server_stats['bytes_written'] ?? 0,
            'limit_maxbytes' => $server_stats['limit_maxbytes'] ?? 0,
            'limit_maxbytes_human' => size_format($server_stats['limit_maxbytes'] ?? 0),
            'threads' => $server_stats['threads'] ?? 1
        );
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 生成Memcached配置建议
 */
function folio_get_memcached_recommendations() {
    $recommendations = array();
    $stats = folio_get_memcached_stats();
    
    if (!$stats) {
        return array(
            'error' => 'Failed to get Memcached statistics',
            'suggestions' => array(
                'Check whether Memcached service is running',
                'Confirm PHP memcached extension is installed',
                'Verify whether connection settings are correct'
            )
        );
    }
    
    // 命中率分析
    if ($stats['hit_rate'] < 70) {
        $recommendations['hit_rate'] = array(
            'status' => 'warning',
            'message' => "Low cache hit rate ({$stats['hit_rate']}%)",
            'suggestions' => array(
                'Increase cache expiration time',
                'Check cache cleanup strategy',
                'Optimize cache key design'
            )
        );
    } elseif ($stats['hit_rate'] < 85) {
        $recommendations['hit_rate'] = array(
            'status' => 'info',
            'message' => "Good cache hit rate ({$stats['hit_rate']}%)",
            'suggestions' => array(
                'Cache strategy can be further optimized'
            )
        );
    } else {
        $recommendations['hit_rate'] = array(
            'status' => 'success',
            'message' => "Excellent cache hit rate ({$stats['hit_rate']}%)"
        );
    }
    
    // 内存使用分析
    $memory_usage = ($stats['bytes'] / $stats['limit_maxbytes']) * 100;
    if ($memory_usage > 90) {
        $recommendations['memory'] = array(
            'status' => 'error',
            'message' => "Memory usage is too high ({$memory_usage}%)",
            'suggestions' => array(
                'Increase Memcached memory allocation',
                'Clean up unnecessary cached data',
                'Check for memory leaks'
            )
        );
    } elseif ($memory_usage > 75) {
        $recommendations['memory'] = array(
            'status' => 'warning',
            'message' => "Memory usage is relatively high ({$memory_usage}%)",
            'suggestions' => array(
                'Consider increasing memory allocation',
                'Monitor memory usage trend'
            )
        );
    }
    
    // 驱逐分析
    if ($stats['evictions'] > 0) {
        $eviction_rate = $stats['evictions'] / $stats['total_items'] * 100;
        if ($eviction_rate > 10) {
            $recommendations['evictions'] = array(
                'status' => 'warning',
                'message' => "High cache eviction rate ({$eviction_rate}%)",
                'suggestions' => array(
                    'Increase Memcached memory',
                    'Optimize cache expiration time',
                    'Reduce cached data size'
                )
            );
        }
    }
    
    // 连接数分析
    if ($stats['curr_connections'] > 100) {
        $recommendations['connections'] = array(
            'status' => 'info',
            'message' => "Current connection count is high ({$stats['curr_connections']})",
            'suggestions' => array(
                'Monitor connection count changes',
                'Consider connection pool optimization'
            )
        );
    }
    
    return $recommendations;
}

/**
 * 创建Memcached配置文件
 */
function folio_generate_memcached_config() {
    $config = array(
        'wp_config_additions' => array(
            "// Enable WordPress object cache",
            "define('WP_CACHE', true);",
            "",
            "// Memcached server configuration",
            "\$memcached_servers = array(",
            "    'default' => array(",
            "        '127.0.0.1:11211'  // Local Memcached server",
            "    )",
            ");",
            "",
            "// Optional: multi-server configuration",
            "/*",
            "\$memcached_servers = array(",
            "    'default' => array(",
            "        '192.168.1.10:11211',",
            "        '192.168.1.11:11211',",
            "        '192.168.1.12:11211'",
            "    )",
            ");",
            "*/"
        ),
        
        'memcached_conf' => array(
            "# Memcached configuration file (/etc/memcached.conf)",
            "",
            "# Memory allocation (MB)",
            "-m 512",
            "",
            "# Listen address",
            "-l 127.0.0.1",
            "",
            "# Port",
            "-p 11211",
            "",
            "# Max connections",
            "-c 1024",
            "",
            "# Running user",
            "-u memcache",
            "",
            "# Enable verbose logging",
            "-v",
            "",
            "# Thread count (recommended = CPU core count)",
            "-t 4"
        ),
        
        'php_ini_additions' => array(
            "; PHP configuration optimization",
            "",
            "; Enable Memcached extension",
            "extension=memcached",
            "",
            "; Memcached session storage (optional)",
            ";session.save_handler = memcached",
            ";session.save_path = \"127.0.0.1:11211\""
        )
    );
    
    return $config;
}

/**
 * AJAX处理器：获取Memcached状态
 */
function folio_ajax_memcached_status() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $status = folio_check_memcached_availability();
    $stats = folio_get_memcached_stats();
    $recommendations = folio_get_memcached_recommendations();
    
    wp_send_json_success(array(
        'status' => $status,
        'stats' => $stats,
        'recommendations' => $recommendations
    ));
}

// 注册AJAX处理器：仅在未被其他模块注册时兜底注册
if (!has_action('wp_ajax_folio_memcached_status')) {
    add_action('wp_ajax_folio_memcached_status', 'folio_ajax_memcached_status');
}

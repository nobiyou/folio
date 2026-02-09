<?php
/**
 * Cache Auto Validation
 * 
 * 缓存系统自动验证 - 定期检查缓存系统健康状态和性能
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Cache_Auto_Validation {

    /**
     * 构造函数
     */
    public function __construct() {
        // 注册定时任务
        add_action('wp', array($this, 'schedule_validation'));
        add_action('folio_cache_auto_validation', array($this, 'run_auto_validation'));
        
        // 注册AJAX处理器
        add_action('wp_ajax_folio_run_auto_validation', array($this, 'ajax_run_validation'));
        
        // 添加管理页面钩子
        add_action('admin_init', array($this, 'add_validation_notices'));
    }

    /**
     * 安排定时验证
     */
    public function schedule_validation() {
        if (!wp_next_scheduled('folio_cache_auto_validation')) {
            // 每6小时运行一次验证
            wp_schedule_event(time(), 'folio_6hours', 'folio_cache_auto_validation');
        }
    }

    /**
     * 运行自动验证
     */
    public function run_auto_validation() {
        $validation_results = array(
            'timestamp' => current_time('mysql'),
            'overall_status' => 'good',
            'tests' => array(),
            'recommendations' => array(),
            'performance_metrics' => array()
        );

        try {
            // 1. 快速健康检查
            $health_results = $this->quick_health_check();
            $validation_results['tests']['health_check'] = $health_results;

            // 2. 性能快速测试
            $performance_results = $this->quick_performance_test();
            $validation_results['tests']['performance'] = $performance_results;
            $validation_results['performance_metrics'] = $performance_results['metrics'];

            // 3. 缓存命中率检查
            $hit_rate_results = $this->check_cache_hit_rate();
            $validation_results['tests']['hit_rate'] = $hit_rate_results;

            // 4. 系统资源检查
            $resource_results = $this->check_system_resources();
            $validation_results['tests']['resources'] = $resource_results;

            // 5. 错误日志检查
            $error_results = $this->check_error_logs();
            $validation_results['tests']['errors'] = $error_results;

            // 计算总体状态
            $validation_results['overall_status'] = $this->calculate_overall_status($validation_results['tests']);

            // 生成建议
            $validation_results['recommendations'] = $this->generate_recommendations($validation_results['tests']);

            // 保存结果
            $this->save_validation_results($validation_results);

            // 如果有严重问题，发送通知
            if ($validation_results['overall_status'] === 'critical') {
                $this->send_critical_alert($validation_results);
            }

            // 记录成功
            error_log('Folio Cache Auto Validation: Completed successfully - Status: ' . $validation_results['overall_status']);

        } catch (Exception $e) {
            error_log('Folio Cache Auto Validation Error: ' . $e->getMessage());
            
            $validation_results['overall_status'] = 'error';
            $validation_results['error'] = $e->getMessage();
            $this->save_validation_results($validation_results);
        }

        return $validation_results;
    }

    /**
     * 快速健康检查
     */
    private function quick_health_check() {
        $results = array(
            'status' => 'good',
            'tests' => array(),
            'issues' => 0
        );

        // 检查外部对象缓存
        $external_cache = wp_using_ext_object_cache();
        $results['tests']['external_cache'] = array(
            'status' => $external_cache ? 'good' : 'warning',
            'message' => $external_cache ? '外部对象缓存已启用' : '使用内置缓存'
        );
        if (!$external_cache) $results['issues']++;

        // 检查Memcached连接
        if (class_exists('Memcached') && function_exists('folio_check_memcached_availability')) {
            $mc_status = folio_check_memcached_availability();
            $results['tests']['memcached'] = array(
                'status' => $mc_status['connection_test'] ? 'good' : 'critical',
                'message' => $mc_status['connection_test'] ? 'Memcached连接正常' : 'Memcached连接失败'
            );
            if (!$mc_status['connection_test']) $results['issues']++;
        }

        // 检查缓存管理器
        if (class_exists('folio_Performance_Cache_Manager')) {
            $results['tests']['cache_manager'] = array(
                'status' => 'good',
                'message' => '缓存管理器可用'
            );
        } else {
            $results['tests']['cache_manager'] = array(
                'status' => 'critical',
                'message' => '缓存管理器不可用'
            );
            $results['issues']++;
        }

        // 更新总体状态
        if ($results['issues'] > 2) {
            $results['status'] = 'critical';
        } elseif ($results['issues'] > 0) {
            $results['status'] = 'warning';
        }

        return $results;
    }

    /**
     * 快速性能测试
     */
    private function quick_performance_test() {
        $results = array(
            'status' => 'good',
            'metrics' => array(),
            'message' => ''
        );

        try {
            // 测试缓存写入性能
            $test_key = 'folio_auto_perf_test_' . time();
            $test_data = str_repeat('x', 1024); // 1KB数据

            $start_time = microtime(true);
            for ($i = 0; $i < 10; $i++) {
                wp_cache_set($test_key . "_$i", $test_data, 'folio_auto_validation', 300);
            }
            $write_time = microtime(true) - $start_time;

            // 测试缓存读取性能
            $start_time = microtime(true);
            for ($i = 0; $i < 10; $i++) {
                wp_cache_get($test_key . "_$i", 'folio_auto_validation');
            }
            $read_time = microtime(true) - $start_time;

            // 清理测试数据
            for ($i = 0; $i < 10; $i++) {
                wp_cache_delete($test_key . "_$i", 'folio_auto_validation');
            }

            // 计算性能指标
            $write_ops_per_sec = 10 / $write_time;
            $read_ops_per_sec = 10 / $read_time;

            $results['metrics'] = array(
                'write_ops_per_sec' => round($write_ops_per_sec, 0),
                'read_ops_per_sec' => round($read_ops_per_sec, 0),
                'write_time_ms' => round($write_time * 1000, 2),
                'read_time_ms' => round($read_time * 1000, 2)
            );

            // 评估性能
            if ($write_ops_per_sec > 500 && $read_ops_per_sec > 1000) {
                $results['status'] = 'good';
                $results['message'] = '缓存性能优秀';
            } elseif ($write_ops_per_sec > 200 && $read_ops_per_sec > 500) {
                $results['status'] = 'warning';
                $results['message'] = '缓存性能一般';
            } else {
                $results['status'] = 'critical';
                $results['message'] = '缓存性能较差';
            }

        } catch (Exception $e) {
            $results['status'] = 'critical';
            $results['message'] = '性能测试失败: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * 检查缓存命中率
     */
    private function check_cache_hit_rate() {
        $results = array(
            'status' => 'good',
            'hit_rate' => 0,
            'message' => ''
        );

        try {
            // 尝试获取Memcached统计
            if (class_exists('Memcached') && function_exists('folio_check_memcached_availability')) {
                $mc_status = folio_check_memcached_availability();
                if ($mc_status['connection_test'] && isset($mc_status['stats'])) {
                    $stats = $mc_status['stats'];
                    
                    if (isset($stats['get_hits']) && isset($stats['get_misses'])) {
                        $hits = (int)$stats['get_hits'];
                        $misses = (int)$stats['get_misses'];
                        $total = $hits + $misses;
                        
                        if ($total > 0) {
                            $hit_rate = ($hits / $total) * 100;
                            $results['hit_rate'] = round($hit_rate, 2);
                            
                            if ($hit_rate > 90) {
                                $results['status'] = 'good';
                                $results['message'] = '缓存命中率优秀';
                            } elseif ($hit_rate > 70) {
                                $results['status'] = 'warning';
                                $results['message'] = '缓存命中率一般';
                            } else {
                                $results['status'] = 'critical';
                                $results['message'] = '缓存命中率较低';
                            }
                        }
                    }
                }
            }

            // 如果无法获取真实数据，使用Folio缓存统计
            if ($results['hit_rate'] == 0 && class_exists('folio_Performance_Cache_Manager')) {
                $cache_stats = folio_Performance_Cache_Manager::get_cache_statistics();
                if (!empty($cache_stats['performance_stats'])) {
                    $perf = $cache_stats['performance_stats'];
                    $cache_hits = isset($perf['cache_hits']) ? (int)$perf['cache_hits'] : 0;
                    $cache_misses = isset($perf['cache_misses']) ? (int)$perf['cache_misses'] : 0;
                    $total = $cache_hits + $cache_misses;
                    
                    if ($total > 0) {
                        $hit_rate = ($cache_hits / $total) * 100;
                        $results['hit_rate'] = round($hit_rate, 2);
                        $results['message'] = 'Folio缓存命中率: ' . $results['hit_rate'] . '%';
                        $results['status'] = $hit_rate > 80 ? 'good' : ($hit_rate > 60 ? 'warning' : 'critical');
                    }
                }
            }

        } catch (Exception $e) {
            $results['status'] = 'warning';
            $results['message'] = '无法获取命中率数据: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * 检查系统资源
     */
    private function check_system_resources() {
        $results = array(
            'status' => 'good',
            'metrics' => array(),
            'issues' => array()
        );

        // 检查内存使用
        $memory_usage = memory_get_usage(true);
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_percent = ($memory_usage / $memory_limit) * 100;

        $results['metrics']['memory_usage'] = size_format($memory_usage);
        $results['metrics']['memory_limit'] = size_format($memory_limit);
        $results['metrics']['memory_percent'] = round($memory_percent, 1);

        if ($memory_percent > 90) {
            $results['issues'][] = '内存使用率过高: ' . round($memory_percent, 1) . '%';
        }

        // 检查磁盘空间
        $disk_free = disk_free_space(ABSPATH);
        $disk_total = disk_total_space(ABSPATH);
        $disk_percent = (($disk_total - $disk_free) / $disk_total) * 100;

        $results['metrics']['disk_free'] = size_format($disk_free);
        $results['metrics']['disk_total'] = size_format($disk_total);
        $results['metrics']['disk_percent'] = round($disk_percent, 1);

        if ($disk_percent > 90) {
            $results['issues'][] = '磁盘使用率过高: ' . round($disk_percent, 1) . '%';
        }

        // 检查数据库查询数量
        global $wpdb;
        $query_count = $wpdb->num_queries;
        $results['metrics']['db_queries'] = $query_count;

        if ($query_count > 100) {
            $results['issues'][] = '数据库查询过多: ' . $query_count . ' 次';
        }

        // 更新状态
        if (count($results['issues']) > 2) {
            $results['status'] = 'critical';
        } elseif (count($results['issues']) > 0) {
            $results['status'] = 'warning';
        }

        return $results;
    }

    /**
     * 检查错误日志
     */
    private function check_error_logs() {
        $results = array(
            'status' => 'good',
            'cache_errors' => 0,
            'recent_errors' => array()
        );

        // 检查PHP错误日志中的缓存相关错误
        $error_log_path = ini_get('error_log');
        if ($error_log_path && file_exists($error_log_path) && is_readable($error_log_path)) {
            try {
                // 读取最近的日志条目
                $log_content = file_get_contents($error_log_path);
                $log_lines = explode("\n", $log_content);
                
                // 查找最近24小时的缓存相关错误
                $cache_keywords = array('cache', 'memcached', 'redis', 'folio');
                $recent_time = time() - 86400; // 24小时前
                
                foreach (array_reverse($log_lines) as $line) {
                    if (empty($line)) continue;
                    
                    // 检查是否为缓存相关错误
                    $is_cache_error = false;
                    foreach ($cache_keywords as $keyword) {
                        if (stripos($line, $keyword) !== false) {
                            $is_cache_error = true;
                            break;
                        }
                    }
                    
                    if ($is_cache_error) {
                        $results['cache_errors']++;
                        if (count($results['recent_errors']) < 5) {
                            $results['recent_errors'][] = substr($line, 0, 200);
                        }
                    }
                    
                    // 只检查最近1000行
                    if (count($results['recent_errors']) >= 1000) break;
                }
                
            } catch (Exception $e) {
                $results['status'] = 'warning';
                $results['message'] = '无法读取错误日志: ' . $e->getMessage();
            }
        }

        // 评估错误状态
        if ($results['cache_errors'] > 10) {
            $results['status'] = 'critical';
        } elseif ($results['cache_errors'] > 3) {
            $results['status'] = 'warning';
        }

        return $results;
    }

    /**
     * 计算总体状态
     */
    private function calculate_overall_status($tests) {
        $critical_count = 0;
        $warning_count = 0;

        foreach ($tests as $test) {
            if ($test['status'] === 'critical') {
                $critical_count++;
            } elseif ($test['status'] === 'warning') {
                $warning_count++;
            }
        }

        if ($critical_count > 0) {
            return 'critical';
        } elseif ($warning_count > 2) {
            return 'warning';
        } else {
            return 'good';
        }
    }

    /**
     * 生成建议
     */
    private function generate_recommendations($tests) {
        $recommendations = array();

        // 基于测试结果生成建议
        if (isset($tests['health_check']) && $tests['health_check']['issues'] > 0) {
            $recommendations[] = array(
                'priority' => 'high',
                'title' => '修复健康检查问题',
                'description' => '发现 ' . $tests['health_check']['issues'] . ' 个健康检查问题，建议立即处理'
            );
        }

        if (isset($tests['performance']) && $tests['performance']['status'] !== 'good') {
            $recommendations[] = array(
                'priority' => 'medium',
                'title' => '优化缓存性能',
                'description' => '缓存性能不佳，建议检查服务器资源和配置'
            );
        }

        if (isset($tests['hit_rate']) && $tests['hit_rate']['hit_rate'] < 80) {
            $recommendations[] = array(
                'priority' => 'medium',
                'title' => '提升缓存命中率',
                'description' => '当前命中率 ' . $tests['hit_rate']['hit_rate'] . '%，建议优化缓存策略'
            );
        }

        if (isset($tests['resources']) && count($tests['resources']['issues']) > 0) {
            $recommendations[] = array(
                'priority' => 'high',
                'title' => '解决资源问题',
                'description' => '发现系统资源问题: ' . implode(', ', $tests['resources']['issues'])
            );
        }

        if (isset($tests['errors']) && $tests['errors']['cache_errors'] > 5) {
            $recommendations[] = array(
                'priority' => 'high',
                'title' => '处理缓存错误',
                'description' => '发现 ' . $tests['errors']['cache_errors'] . ' 个缓存相关错误，需要调查'
            );
        }

        return $recommendations;
    }

    /**
     * 保存验证结果
     */
    private function save_validation_results($results) {
        // 保存到WordPress选项
        update_option('folio_cache_auto_validation_results', $results);
        
        // 保存历史记录（最多保留10次）
        $history = get_option('folio_cache_validation_history', array());
        array_unshift($history, $results);
        $history = array_slice($history, 0, 10);
        update_option('folio_cache_validation_history', $history);
    }

    /**
     * 发送严重问题警报
     */
    private function send_critical_alert($results) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = "[{$site_name}] 缓存系统严重问题警报";
        
        $message = "检测到缓存系统存在严重问题：\n\n";
        
        foreach ($results['tests'] as $test_name => $test_result) {
            if ($test_result['status'] === 'critical') {
                $message .= "- {$test_name}: {$test_result['message']}\n";
            }
        }
        
        $message .= "\n建议立即检查缓存系统配置和服务器状态。\n";
        $message .= "详细信息请查看WordPress后台的缓存管理页面。\n\n";
        $message .= "检查时间: " . $results['timestamp'];
        
        wp_mail($admin_email, $subject, $message);
    }

    /**
     * 添加验证通知
     */
    public function add_validation_notices() {
        $results = get_option('folio_cache_auto_validation_results');
        
        if ($results && $results['overall_status'] === 'critical') {
            add_action('admin_notices', array($this, 'show_critical_notice'));
        } elseif ($results && $results['overall_status'] === 'warning') {
            add_action('admin_notices', array($this, 'show_warning_notice'));
        }
    }

    /**
     * 显示严重问题通知
     */
    public function show_critical_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>缓存系统警报:</strong> 检测到严重问题，请立即查看 <a href="<?php echo admin_url('tools.php?page=folio-cache-management'); ?>">缓存管理页面</a> 进行处理。</p>
        </div>
        <?php
    }

    /**
     * 显示警告通知
     */
    public function show_warning_notice() {
        ?>
        <div class="notice notice-warning">
            <p><strong>缓存系统提醒:</strong> 发现一些需要注意的问题，建议查看 <a href="<?php echo admin_url('tools.php?page=folio-cache-management'); ?>">缓存管理页面</a> 进行优化。</p>
        </div>
        <?php
    }

    /**
     * AJAX运行验证
     */
    public function ajax_run_validation() {
        if (!wp_verify_nonce($_POST['nonce'], 'folio_cache_auto_validation')) {
            wp_send_json_error('安全验证失败');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }

        $results = $this->run_auto_validation();
        wp_send_json_success($results);
    }

    /**
     * 获取最新验证结果
     */
    public static function get_latest_results() {
        return get_option('folio_cache_auto_validation_results', array());
    }

    /**
     * 获取验证历史
     */
    public static function get_validation_history() {
        return get_option('folio_cache_validation_history', array());
    }
}

// 注册自定义时间间隔
add_filter('cron_schedules', function($schedules) {
    $schedules['folio_6hours'] = array(
        'interval' => 6 * HOUR_IN_SECONDS,
        'display' => '每6小时'
    );
    return $schedules;
});

// 初始化自动验证
new folio_Cache_Auto_Validation();
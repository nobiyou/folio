<?php
/**
 * Performance Monitor
 * 
 * 监控网站性能指标，包括页面加载时间、数据库查询等
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Performance_Monitor {

    private $start_time;
    private $start_memory;
    private $query_count_start;
    private $performance_data = array();

    public function __construct() {
        // 只在管理员或开发环境中启用
        if (!$this->should_monitor()) {
            return;
        }

        // 记录开始时间和内存
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage();
        $this->query_count_start = get_num_queries();

        // 只在前端页面底部显示性能信息（仅管理员可见）
        if ($this->should_monitor() === true) {
            add_action('wp_footer', array($this, 'display_performance_bar'));
        }

        // 记录慢查询
        add_filter('query', array($this, 'log_slow_queries'));

        // AJAX处理
        add_action('wp_ajax_folio_get_performance_data', array($this, 'ajax_get_performance_data'));
        add_action('wp_ajax_folio_clear_performance_logs', array($this, 'ajax_clear_performance_logs'));

        // 记录页面性能数据
        add_action('shutdown', array($this, 'log_page_performance'));
    }

    /**
     * 检查是否应该监控性能
     */
    private function should_monitor() {
        // 检查是否启用了性能监控
        $options = get_option('folio_theme_options', array());
        if (empty($options['enable_performance_monitor'])) {
            return false;
        }

        // 只监控前端页面，排除后台
        if (is_admin()) {
            return false;
        }

        // 排除AJAX请求
        if (wp_doing_ajax()) {
            return false;
        }

        // 排除REST API请求
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        // 只对管理员显示性能工具栏
        if (!current_user_can('manage_options')) {
            // 仍然记录性能数据，但不显示工具栏
            return 'log_only';
        }

        return true;
    }

    /**
     * 显示性能工具栏
     */
    public function display_performance_bar() {
        if ($this->should_monitor() !== true) {
            return;
        }

        $performance = $this->get_current_performance();
        ?>
        <div id="folio-performance-bar">
            <div class="performance-metrics">
                <div title="页面加载时间" class="performance-metric <?php echo $performance['load_time'] > 3 ? 'performance-metric-critical' : ($performance['load_time'] > 2 ? 'performance-metric-warning' : 'performance-metric-good'); ?>">
                    <span class="performance-metric-label">TIME</span>
                    <span class="performance-metric-value">
                        <?php echo number_format($performance['load_time'], 3); ?>s
                    </span>
                </div>
                
                <div title="内存使用" class="performance-metric <?php echo $performance['memory_usage'] > 64*1024*1024 ? 'performance-metric-warning' : 'performance-metric-good'; ?>">
                    <span class="performance-metric-label">MEM</span>
                    <span class="performance-metric-value">
                        <?php echo $this->format_bytes($performance['memory_usage']); ?>
                    </span>
                </div>
                
                <div title="数据库查询" class="performance-metric <?php echo $performance['db_queries'] > 50 ? 'performance-metric-critical' : ($performance['db_queries'] > 30 ? 'performance-metric-warning' : 'performance-metric-good'); ?>">
                    <span class="performance-metric-label">SQL</span>
                    <span class="performance-metric-value">
                        <?php echo $performance['db_queries']; ?>
                    </span>
                </div>
                
                <div title="查询时间" class="performance-secondary">
                    <span class="performance-secondary-label">QT</span>
                    <span class="performance-secondary-value"><?php echo number_format($performance['db_time'], 3); ?>s</span>
                </div>
                
                <div title="峰值内存" class="performance-secondary">
                    <span class="performance-secondary-label">PEAK</span>
                    <span class="performance-secondary-value"><?php echo $this->format_bytes($performance['memory_peak']); ?></span>
                </div>
                
                <div title="页面类型" class="performance-page-type">
                    <span class="performance-secondary-label">TYPE</span>
                    <span class="performance-page-type-value"><?php echo $this->get_page_type(); ?></span>
                </div>
                
                <?php if ($performance['slow_queries'] > 0) : ?>
                <div title="慢查询警告" class="performance-metric performance-metric-critical">
                    <span class="performance-metric-label">SLOW</span>
                    <span class="performance-metric-value"><?php echo $performance['slow_queries']; ?></span>
                </div>
                <?php endif; ?>
                
                <?php 
                $suggestions = $this->get_optimization_suggestions($performance);
                $critical_count = count(array_filter($suggestions, function($s) { return $s['type'] === 'critical'; }));
                if ($critical_count > 0) : ?>
                <div title="<?php echo $critical_count; ?> 个关键优化建议" class="performance-metric performance-metric-critical">
                    <span class="performance-metric-label">WARN</span>
                    <span class="performance-metric-value"><?php echo $critical_count; ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="performance-controls">
                <button onclick="folioTogglePerformanceDetails()" class="performance-btn performance-btn-details" title="查看详细信息和优化建议">
                    详情
                </button>
                <button onclick="folioHidePerformanceBar()" class="performance-btn performance-btn-close" title="隐藏工具栏 (Ctrl+Shift+P 重新显示)">
                    ✕
                </button>
            </div>
        </div>

        <!-- 详细信息面板 -->
        <div id="folio-performance-details">
            <h4 class="performance-details-header">性能详情</h4>
            
            <div class="performance-details-section">
                <strong>页面信息:</strong><br>
                URL: <?php echo esc_html($_SERVER['REQUEST_URI'] ?? ''); ?><br>
                页面类型: <?php echo esc_html($this->get_page_type()); ?><br>
                设备类型: <?php echo wp_is_mobile() ? '移动设备' : '桌面设备'; ?><br>
                用户代理: <?php echo esc_html(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 50)); ?>...
            </div>

            <div class="performance-details-section">
                <strong>性能指标:</strong><br>
                加载时间: <?php echo number_format($performance['load_time'], 3); ?>s<br>
                内存使用: <?php echo $this->format_bytes($performance['memory_usage']); ?><br>
                峰值内存: <?php echo $this->format_bytes($performance['memory_peak']); ?><br>
                数据库查询: <?php echo $performance['db_queries']; ?> 次<br>
                查询总时间: <?php echo number_format($performance['db_time'], 3); ?>s
            </div>

            <?php if (!empty($performance['slow_queries_list'])) : ?>
            <div class="performance-details-section">
                <strong style="color: #ff6b6b;">慢查询 (>0.1s):</strong><br>
                <?php foreach ($performance['slow_queries_list'] as $query) : ?>
                <div class="folio-slow-query">
                    <div class="time"><?php echo number_format($query['time'], 3); ?>s</div>
                    <div class="sql"><?php echo esc_html(substr($query['sql'], 0, 100)); ?>...</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php 
            $suggestions = $this->get_optimization_suggestions($performance);
            if (!empty($suggestions)) : ?>
            <div class="performance-details-section">
                <strong style="color: #ffa726;">优化建议:</strong><br>
                <?php foreach ($suggestions as $suggestion) : ?>
                <div class="folio-optimization-suggestion <?php echo $suggestion['type']; ?>">
                    <div class="type">
                        <?php echo $suggestion['type'] === 'critical' ? '[关键]' : '[警告]'; ?>
                    </div>
                    <div class="message"><?php echo esc_html($suggestion['message']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="performance-details-section">
                <strong>环境信息:</strong><br>
                PHP: <?php echo PHP_VERSION; ?><br>
                WordPress: <?php echo get_bloginfo('version'); ?><br>
                主题: <?php echo wp_get_theme()->get('Name'); ?><br>
                活跃插件: <?php echo count(get_option('active_plugins', array())); ?> 个
            </div>

            <div class="folio-performance-tip">
                <div class="title">使用提示:</div>
                <div class="content">
                    • 点击 × 隐藏工具栏<br>
                    • 按 <kbd>Ctrl+Shift+P</kbd> 显示/隐藏<br>
                    • <kbd>Ctrl+双击</kbd> 页面重新显示<br>
                    • 绿色=良好，黄色=警告，红色=需优化
                </div>
            </div>
        </div>



        <script>
        function folioTogglePerformanceDetails() {
            const details = document.getElementById('folio-performance-details');
            details.style.display = details.style.display === 'none' ? 'block' : 'none';
        }

        function folioHidePerformanceBar() {
            document.getElementById('folio-performance-bar').style.display = 'none';
            if (localStorage) {
                localStorage.setItem('folio_hide_performance_bar', '1');
            }
        }

        // 页面加载完成后检查显示状态
        document.addEventListener('DOMContentLoaded', function() {
            const performanceBar = document.getElementById('folio-performance-bar');
            
            // 检查是否应该隐藏性能栏
            if (localStorage && localStorage.getItem('folio_hide_performance_bar') === '1') {
                performanceBar.style.display = 'none';
            } else {
                // 默认显示，添加淡入效果
                performanceBar.style.display = 'flex';
                performanceBar.style.opacity = '0';
                setTimeout(function() {
                    performanceBar.style.opacity = '1';
                }, 100);
            }
        });

        // 快捷键显示/隐藏性能栏 (Ctrl/Cmd + Shift + P)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'P') {
                e.preventDefault();
                const performanceBar = document.getElementById('folio-performance-bar');
                if (performanceBar.style.display === 'none') {
                    performanceBar.style.display = 'flex';
                    if (localStorage) {
                        localStorage.removeItem('folio_hide_performance_bar');
                    }
                } else {
                    performanceBar.style.display = 'none';
                    if (localStorage) {
                        localStorage.setItem('folio_hide_performance_bar', '1');
                    }
                }
            }
        });

        // 双击显示性能栏（保留原有功能）
        document.addEventListener('dblclick', function(e) {
            if (e.ctrlKey || e.metaKey) {
                document.getElementById('folio-performance-bar').style.display = 'flex';
                if (localStorage) {
                    localStorage.removeItem('folio_hide_performance_bar');
                }
            }
        });
        </script>
        <?php
    }

    /**
     * 获取当前页面性能数据
     */
    private function get_current_performance() {
        $current_time = microtime(true);
        $current_memory = memory_get_usage();
        $current_queries = get_num_queries();

        // 获取慢查询
        $slow_queries = get_transient('folio_slow_queries_' . get_current_user_id()) ?: array();
        $slow_queries_count = count($slow_queries);

        // 估算数据库查询时间（WordPress没有直接提供）
        $db_time = $this->estimate_db_time($current_queries - $this->query_count_start);

        return array(
            'load_time' => $current_time - $this->start_time,
            'memory_usage' => $current_memory,
            'memory_peak' => memory_get_peak_usage(),
            'db_queries' => $current_queries - $this->query_count_start,
            'db_time' => $db_time,
            'slow_queries' => $slow_queries_count,
            'slow_queries_list' => array_slice($slow_queries, -5) // 最近5个慢查询
        );
    }

    /**
     * 估算数据库查询时间
     */
    private function estimate_db_time($query_count) {
        // 基于查询数量的粗略估算
        // 平均每个查询约0.001-0.005秒
        return $query_count * 0.003;
    }

    /**
     * 记录慢查询
     */
    public function log_slow_queries($query) {
        $monitor_status = $this->should_monitor();
        if (!$monitor_status) {
            return $query;
        }

        $start_time = microtime(true);
        
        // 执行查询后记录时间（这里只是示例，实际需要在查询执行后测量）
        add_filter('posts_results', function($posts) use ($query, $start_time) {
            $execution_time = microtime(true) - $start_time;
            
            if ($execution_time > 0.1) { // 超过100ms的查询
                $slow_queries = get_transient('folio_slow_queries_' . get_current_user_id()) ?: array();
                
                $slow_queries[] = array(
                    'sql' => $query,
                    'time' => $execution_time,
                    'timestamp' => time(),
                    'url' => $_SERVER['REQUEST_URI'] ?? ''
                );
                
                // 只保留最近50个慢查询
                if (count($slow_queries) > 50) {
                    $slow_queries = array_slice($slow_queries, -50);
                }
                
                set_transient('folio_slow_queries_' . get_current_user_id(), $slow_queries, HOUR_IN_SECONDS);
            }
            
            return $posts;
        });

        return $query;
    }

    /**
     * 记录页面性能数据
     */
    public function log_page_performance() {
        $monitor_status = $this->should_monitor();
        if (!$monitor_status) {
            return;
        }

        $performance = $this->get_current_performance();
        $performance['url'] = $_SERVER['REQUEST_URI'] ?? '';
        $performance['timestamp'] = time();
        $performance['user_id'] = get_current_user_id();
        $performance['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $performance['is_mobile'] = wp_is_mobile();
        
        // 添加页面类型分析
        $performance['page_type'] = $this->get_page_type();
        
        // 添加优化建议
        $performance['optimization_suggestions'] = $this->get_optimization_suggestions($performance);

        // 保存到数据库或缓存
        $performance_logs = get_transient('folio_performance_logs') ?: array();
        $performance_logs[] = $performance;

        // 只保留最近100条记录
        if (count($performance_logs) > 100) {
            $performance_logs = array_slice($performance_logs, -100);
        }

        set_transient('folio_performance_logs', $performance_logs, DAY_IN_SECONDS);
    }

    /**
     * AJAX获取性能数据
     */
    public function ajax_get_performance_data() {
        check_ajax_referer('folio_get_performance_data', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $performance_logs = get_transient('folio_performance_logs') ?: array();
        $slow_queries = get_transient('folio_slow_queries_' . get_current_user_id()) ?: array();

        // 计算统计数据
        $stats = $this->calculate_performance_stats($performance_logs);

        wp_send_json_success(array(
            'logs' => array_slice($performance_logs, -20), // 最近20条
            'slow_queries' => array_slice($slow_queries, -10), // 最近10个慢查询
            'stats' => $stats
        ));
    }

    /**
     * 清除性能日志
     */
    public function ajax_clear_performance_logs() {
        check_ajax_referer('folio_clear_performance_logs', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        delete_transient('folio_performance_logs');
        delete_transient('folio_slow_queries_' . get_current_user_id());

        wp_send_json_success(array('message' => '性能日志已清除'));
    }

    /**
     * 计算性能统计
     */
    private function calculate_performance_stats($logs) {
        if (empty($logs)) {
            return array();
        }

        $load_times = array_column($logs, 'load_time');
        $memory_usage = array_column($logs, 'memory_usage');
        $db_queries = array_column($logs, 'db_queries');
        
        // 按页面类型统计
        $page_types = array();
        $mobile_requests = 0;
        $optimization_issues = 0;
        
        foreach ($logs as $log) {
            // 页面类型统计
            $type = $log['page_type'] ?? 'unknown';
            if (!isset($page_types[$type])) {
                $page_types[$type] = 0;
            }
            $page_types[$type]++;
            
            // 移动设备统计
            if (!empty($log['is_mobile'])) {
                $mobile_requests++;
            }
            
            // 优化问题统计
            if (!empty($log['optimization_suggestions'])) {
                $optimization_issues += count($log['optimization_suggestions']);
            }
        }

        return array(
            'avg_load_time' => array_sum($load_times) / count($load_times),
            'max_load_time' => max($load_times),
            'min_load_time' => min($load_times),
            'avg_memory' => array_sum($memory_usage) / count($memory_usage),
            'max_memory' => max($memory_usage),
            'avg_queries' => array_sum($db_queries) / count($db_queries),
            'max_queries' => max($db_queries),
            'total_requests' => count($logs),
            'page_types' => $page_types,
            'mobile_percentage' => round(($mobile_requests / count($logs)) * 100, 1),
            'optimization_issues' => $optimization_issues,
            'performance_score' => $this->calculate_performance_score($logs)
        );
    }

    /**
     * 计算性能评分
     */
    private function calculate_performance_score($logs) {
        if (empty($logs)) {
            return 0;
        }

        $score = 100;
        $load_times = array_column($logs, 'load_time');
        $db_queries = array_column($logs, 'db_queries');
        
        $avg_load_time = array_sum($load_times) / count($load_times);
        $avg_queries = array_sum($db_queries) / count($db_queries);
        
        // 根据加载时间扣分
        if ($avg_load_time > 3) {
            $score -= 30;
        } elseif ($avg_load_time > 2) {
            $score -= 15;
        } elseif ($avg_load_time > 1) {
            $score -= 5;
        }
        
        // 根据查询数量扣分
        if ($avg_queries > 50) {
            $score -= 25;
        } elseif ($avg_queries > 30) {
            $score -= 10;
        }
        
        // 根据优化问题扣分
        $total_issues = 0;
        foreach ($logs as $log) {
            if (!empty($log['optimization_suggestions'])) {
                $total_issues += count($log['optimization_suggestions']);
            }
        }
        
        if ($total_issues > 0) {
            $score -= min(20, $total_issues * 2);
        }
        
        return max(0, $score);
    }

    /**
     * 获取页面类型
     */
    private function get_page_type() {
        if (is_front_page()) {
            return 'homepage';
        } elseif (is_single()) {
            return 'single_post';
        } elseif (is_page()) {
            return 'page';
        } elseif (is_category() || is_tag() || is_tax()) {
            return 'archive';
        } elseif (is_search()) {
            return 'search';
        } elseif (is_404()) {
            return '404';
        } else {
            return 'other';
        }
    }

    /**
     * 获取优化建议
     */
    private function get_optimization_suggestions($performance) {
        $suggestions = array();

        // 加载时间建议
        if ($performance['load_time'] > 3) {
            $suggestions[] = array(
                'type' => 'critical',
                'message' => '页面加载时间过长 (>' . number_format($performance['load_time'], 2) . 's)，建议优化'
            );
        } elseif ($performance['load_time'] > 2) {
            $suggestions[] = array(
                'type' => 'warning',
                'message' => '页面加载时间较长，可以进一步优化'
            );
        }

        // 内存使用建议
        if ($performance['memory_usage'] > 64 * 1024 * 1024) { // 64MB
            $suggestions[] = array(
                'type' => 'warning',
                'message' => '内存使用较高 (' . $this->format_bytes($performance['memory_usage']) . ')，检查插件和主题'
            );
        }

        // 数据库查询建议
        if ($performance['db_queries'] > 50) {
            $suggestions[] = array(
                'type' => 'critical',
                'message' => '数据库查询过多 (' . $performance['db_queries'] . ' 次)，建议使用缓存'
            );
        } elseif ($performance['db_queries'] > 30) {
            $suggestions[] = array(
                'type' => 'warning',
                'message' => '数据库查询较多，考虑优化查询或启用缓存'
            );
        }

        // 慢查询建议
        if ($performance['slow_queries'] > 0) {
            $suggestions[] = array(
                'type' => 'critical',
                'message' => '发现 ' . $performance['slow_queries'] . ' 个慢查询，需要优化数据库查询'
            );
        }

        return $suggestions;
    }

    /**
     * 格式化字节数
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// 初始化
new folio_Performance_Monitor();
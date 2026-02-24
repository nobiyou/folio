<?php
/**
 * Security Admin Page
 * 
 * 安全管理页面 - 查看访问日志和安全统计
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Security_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_folio_security_action', array($this, 'handle_ajax_actions'));
        add_action('wp_ajax_folio_import_spiders', array($this, 'handle_import_spiders'));
        add_action('wp_ajax_folio_delete_spider_net', array($this, 'handle_delete_spider_net'));
        add_action('wp_ajax_folio_clear_spider_nets', array($this, 'handle_clear_spider_nets'));
        add_action('wp_ajax_folio_analyze_logs_for_spiders', array($this, 'handle_analyze_logs_for_spiders'));
        add_action('wp_ajax_folio_generate_report', array($this, 'handle_generate_report'));
        add_action('wp_ajax_folio_get_report', array($this, 'handle_get_report'));
        add_action('wp_ajax_folio_compare_reports', array($this, 'handle_compare_reports'));
        add_action('wp_ajax_folio_delete_report', array($this, 'handle_delete_report'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        // 使用 themes.php 作为父菜单，因为 folio-theme-options 是用 add_theme_page 注册的
        // 这样可以在主题设置菜单下显示安全防护子菜单
        add_submenu_page(
            'themes.php',
            __('Security Protection', 'folio'),
            __('Security Protection', 'folio'),
            'manage_options',
            'folio-security',
            array($this, 'render_admin_page')
        );
    }

    /**
     * 加载管理脚本
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'folio-security') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        
        wp_localize_script('jquery', 'folioSecurity', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('folio_security_nonce')
        ));
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        $current_tab = $_GET['tab'] ?? 'dashboard';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Security Protection Management', 'folio'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=folio-security&tab=dashboard" class="nav-tab <?php echo esc_attr($current_tab === 'dashboard' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Security Overview', 'folio'); ?>
                </a>
                <a href="?page=folio-security&tab=logs" class="nav-tab <?php echo esc_attr($current_tab === 'logs' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Access Logs', 'folio'); ?>
                </a>
                <a href="?page=folio-security&tab=settings" class="nav-tab <?php echo esc_attr($current_tab === 'settings' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Security Settings', 'folio'); ?>
                </a>
                <a href="?page=folio-security&tab=spiders" class="nav-tab <?php echo esc_attr($current_tab === 'spiders' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Spider Pool Management', 'folio'); ?>
                </a>
                <a href="?page=folio-security&tab=reports" class="nav-tab <?php echo esc_attr($current_tab === 'reports' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('AI Operations Reports', 'folio'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <div id="security-loading" style="display: none; text-align: center; padding: 40px;">
                    <p><?php esc_html_e('Loading...', 'folio'); ?></p>
                </div>
                <div id="security-content">
                <?php
                    try {
                switch ($current_tab) {
                    case 'dashboard':
                        $this->render_dashboard();
                        break;
                    case 'logs':
                        $this->render_logs();
                        break;
                    case 'settings':
                        $this->render_settings();
                        break;
                    case 'spiders':
                        $this->render_spiders();
                        break;
                    case 'reports':
                        $this->render_reports();
                        break;
                }
                    } catch (Exception $e) {
                        error_log('Folio Security Admin Error: ' . $e->getMessage());
                        echo '<div class="notice notice-error"><p>' . esc_html__('Error loading page: ', 'folio') . esc_html($e->getMessage()) . '</p></div>';
                        echo '<p>' . esc_html__('If the issue persists, check error logs or contact technical support.', 'folio') . '</p>';
                }
                ?>
                </div>
            </div>
        </div>

        <style>
        .security-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
            display: block;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .security-charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        @media (max-width: 1400px) {
            .security-charts-container {
                grid-template-columns: 1fr;
            }
        }
        
        .security-chart {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: box-shadow 0.3s ease;
            min-height: 350px;
            display: flex;
            flex-direction: column;
        }
        
        .security-chart:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        
        .security-chart h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1d2327;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f1;
        }
        
        .security-chart canvas {
            flex: 1;
            max-height: 300px;
        }
        
        .security-chart-doughnut {
            align-items: center;
            justify-content: flex-start;
        }
        
        .security-chart-doughnut h3 {
            width: 100%;
            text-align: center;
        }
        
        .doughnut-chart-wrapper {
            max-width: 400px;
            width: 100%;
            height: 280px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f1;
        }
        
        .chart-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #1d2327;
        }
        
        .chart-controls select {
            padding: 6px 12px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            background: #fff;
            color: #2c3338;
            font-size: 13px;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .chart-controls select:hover {
            border-color: #2271b1;
        }
        
        .chart-controls select:focus {
            border-color: #2271b1;
            outline: none;
            box-shadow: 0 0 0 1px #2271b1;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .logs-table th,
        .logs-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .logs-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        .logs-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .status-allowed { color: #46b450; }
        .status-denied { color: #dc3232; }
        .status-blocked { color: #d63638; font-weight: bold; }
        .status-suspicious { color: #ffb900; }
        
        .log-filters {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .filter-row label {
            min-width: 100px;
        }
        
        .filter-row input,
        .filter-row select {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        #security-loading {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .tab-content {
            min-height: 400px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // 标签页切换时显示加载状态
            $('.nav-tab-wrapper a').on('click', function() {
                var $content = $('#security-content');
                var $loading = $('#security-loading');
                
                // 如果点击的不是当前标签，显示加载状态
                if (!$(this).hasClass('nav-tab-active')) {
                    $content.hide();
                    $loading.show();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * 渲染安全概览
     */
    private function render_dashboard() {
        // 添加错误处理和超时保护
        set_time_limit(30); // 设置30秒超时
        
        try {
        $security_manager = folio_Security_Protection_Manager::get_instance();
            
            // 使用缓存减少数据库查询
            $cache_key_7d = 'folio_security_stats_7d';
            $cache_key_today = 'folio_security_stats_today_' . date('Y-m-d'); // 按日期缓存，每天自动更新
            $stats_7d = get_transient($cache_key_7d);
            $stats_today = get_transient($cache_key_today);
            
            if ($stats_7d === false) {
        $stats_7d = $security_manager->get_security_stats(7);
                set_transient($cache_key_7d, $stats_7d, 300); // 缓存5分钟
            }
            
            // 清除缓存按钮处理
            if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === 'today') {
                delete_transient($cache_key_today);
                delete_transient($cache_key_7d);
                echo '<div class="notice notice-success"><p>' . esc_html__('Cache cleared, reloading...', 'folio') . '</p></div>';
                echo '<script>setTimeout(function(){ window.location.href = window.location.pathname + "?page=folio-security&tab=dashboard"; }, 1000);</script>';
            }
            
            if ($stats_today === false) {
        $stats_today = $security_manager->get_today_stats();
                // 今天的数据缓存到明天0点，确保每天自动更新
                $seconds_until_midnight = strtotime('tomorrow') - time();
                set_transient($cache_key_today, $stats_today, $seconds_until_midnight);
            }
            
            // 获取图表数据（使用优化后的方法）
            // 支持时间范围选择：7天或当天小时
            $chart_type = isset($_GET['chart_type']) ? sanitize_text_field($_GET['chart_type']) : '7days';
            $chart_type = in_array($chart_type, array('7days', 'today')) ? $chart_type : '7days';
            
            if ($chart_type === 'today') {
                $chart_data = $this->get_today_hourly_data();
            } else {
            $chart_data = $this->get_optimized_chart_data(7);
            }
        } catch (Exception $e) {
            error_log('Folio Security Dashboard Error: ' . $e->getMessage());
            $stats_7d = array(
                'total_access' => 0,
                'denied_access' => 0,
                'suspicious_activity' => 0,
                'bypass_attempts' => 0,
                'blocked_ips' => 0,
                'spider_access' => 0,
                'user_access' => 0,
                'logged_in_access' => 0,
                'unique_ips' => 0,
                'unique_posts' => 0
            );
            $stats_today = $stats_7d;
            $chart_data = array(
                'labels' => array(),
                'total' => array(),
                'denied' => array(),
                'suspicious' => array()
            );
        }
        ?>
        <div class="security-dashboard">
            <h2><?php esc_html_e('7-Day Security Stats', 'folio'); ?></h2>
            <div class="security-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo number_format($stats_7d['total_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Total Accesses', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number status-denied"><?php echo number_format($stats_7d['denied_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Denied Access', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number status-suspicious"><?php echo number_format($stats_7d['suspicious_activity']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Suspicious Activity', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number status-blocked"><?php echo number_format($stats_7d['bypass_attempts']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Bypass Attempts', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number status-blocked"><?php echo number_format($stats_7d['blocked_ips']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Blocked IPs', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #0073aa;"><?php echo number_format($stats_7d['spider_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Spider Access', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #666;"><?php echo number_format($stats_7d['user_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('User Access', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #2271b1;"><?php echo number_format($stats_7d['logged_in_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Logged-in User Access', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #00a32a;"><?php echo number_format($stats_7d['unique_ips']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Unique IPs', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #8c8f94;"><?php echo number_format($stats_7d['unique_posts']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Visited Posts', 'folio'); ?></div>
                </div>
            </div>

            <h2><?php esc_html_e('Today\'s Stats', 'folio'); ?></h2>
            <div class="security-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo number_format($stats_today['total_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Total Accesses', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number status-denied"><?php echo number_format($stats_today['denied_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Denied Access', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number status-suspicious"><?php echo number_format($stats_today['suspicious_activity']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Suspicious Activity', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number status-blocked"><?php echo number_format($stats_today['bypass_attempts']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Bypass Attempts', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number status-blocked"><?php echo number_format($stats_today['blocked_ips']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Blocked IPs', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #0073aa;"><?php echo number_format($stats_today['spider_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Spider Access', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #666;"><?php echo number_format($stats_today['user_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('User Access', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #2271b1;"><?php echo number_format($stats_today['logged_in_access']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Logged-in User Access', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #00a32a;"><?php echo number_format($stats_today['unique_ips']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Unique IPs', 'folio'); ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #8c8f94;"><?php echo number_format($stats_today['unique_posts']); ?></span>
                    <div class="stat-label"><?php esc_html_e('Visited Posts', 'folio'); ?></div>
                </div>
            </div>

            <div class="security-charts-container">
                <!-- 访问趋势图 - 正常访问 -->
            <div class="security-chart">
                    <div class="chart-header">
                        <h3><?php echo esc_html__('Access Trend - Normal Access', 'folio'); ?></h3>
                        <div class="chart-controls">
                            <select id="chartType" onchange="updateChartType()">
                                <option value="7days" <?php echo ($chart_type === '7days') ? 'selected' : ''; ?>><?php echo esc_html__('Last 7 Days', 'folio'); ?></option>
                                <option value="today" <?php echo ($chart_type === 'today') ? 'selected' : ''; ?>><?php esc_html_e('Today', 'folio'); ?></option>
                            </select>
                        </div>
                    </div>
                    <canvas id="accessChart" width="400" height="280"></canvas>
                </div>
                
                <!-- 访问趋势图 - 安全事件 -->
                <div class="security-chart">
                    <h3><?php echo esc_html__('Access Trend - Security Events', 'folio'); ?></h3>
                    <canvas id="securityAccessChart" width="400" height="280"></canvas>
                </div>
                
                <!-- 访问类型分布图 -->
                <div class="security-chart security-chart-doughnut">
                    <h3>
                        <?php
                        echo ($chart_type === 'today')
                            ? esc_html__('Access Type Distribution (Today)', 'folio')
                            : esc_html__('Access Type Distribution (Last 7 Days)', 'folio');
                        ?>
                    </h3>
                    <div class="doughnut-chart-wrapper">
                        <canvas id="accessTypeChart" width="400" height="280"></canvas>
                    </div>
                </div>
                
                <!-- 安全事件趋势图 -->
                <div class="security-chart">
                    <h3><?php echo esc_html__('Detailed Security Events Trend', 'folio'); ?></h3>
                    <canvas id="securityEventsChart" width="400" height="280"></canvas>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // 检查 Chart.js 是否已加载
            if (typeof Chart === 'undefined') {
                console.error('<?php echo esc_js(__('Chart.js is not loaded', 'folio')); ?>');
                return;
            }
            
            try {
            // 创建访问趋势图
                const ctx = document.getElementById('accessChart');
                if (!ctx) {
                    console.error('<?php echo esc_js(__('Chart canvas element not found', 'folio')); ?>');
                    return;
                }
                
                // 主访问趋势图 - 正常访问（总访问、用户访问、蜘蛛访问）
                const accessChart = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                        labels: <?php echo json_encode($chart_data['labels']); ?>,
                    datasets: [{
                        label: '<?php echo esc_js(__('Total Access', 'folio')); ?>',
                            data: <?php echo json_encode($chart_data['total']); ?>,
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34, 113, 177, 0.12)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#2271b1',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }, {
                            label: '<?php echo esc_js(__('User Access', 'folio')); ?>',
                            data: <?php echo json_encode($chart_data['user_access']); ?>,
                            borderColor: '#50575e',
                            backgroundColor: 'rgba(80, 87, 94, 0.12)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#50575e',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }, {
                            label: '<?php echo esc_js(__('Spider Access', 'folio')); ?>',
                            data: <?php echo json_encode($chart_data['spider_access']); ?>,
                            borderColor: '#00a32a',
                            backgroundColor: 'rgba(0, 163, 42, 0.12)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#00a32a',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    font: {
                                        size: 12
                                    }
                                },
                                onClick: function(e, legendItem) {
                                    const index = legendItem.datasetIndex;
                                    const chart = this.chart;
                                    const meta = chart.getDatasetMeta(index);
                                    meta.hidden = meta.hidden === null ? !chart.data.datasets[index].hidden : null;
                                    chart.update();
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    },
                                    maxRotation: 45,
                                    minRotation: 0,
                                    // 如果是24小时数据，只显示偶数小时，避免标签拥挤
                                    callback: function(value, index) {
                                        const labels = this.chart.data.labels;
                                        if (labels.length === 24) {
                                            // 24小时数据：只显示偶数小时（00, 02, 04, ..., 22）
                                            return index % 2 === 0 ? labels[index] : '';
                                        }
                                        return labels[index];
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    precision: 0,
                                    font: {
                                        size: 11
                                    },
                                    callback: function(value) {
                                        return value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
                
                // 访问趋势图 - 安全事件（被拒绝、可疑活动）
                const securityAccessCtx = document.getElementById('securityAccessChart');
                if (securityAccessCtx) {
                    new Chart(securityAccessCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($chart_data['labels']); ?>,
                            datasets: [{
                                label: '<?php echo esc_js(__('Denied Access', 'folio')); ?>',
                            data: <?php echo json_encode($chart_data['denied']); ?>,
                                borderColor: '#d63638',
                                backgroundColor: 'rgba(214, 54, 56, 0.12)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#d63638',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                    }, {
                        label: '<?php echo esc_js(__('Suspicious Activity', 'folio')); ?>',
                            data: <?php echo json_encode($chart_data['suspicious']); ?>,
                                borderColor: '#f0b849',
                                backgroundColor: 'rgba(240, 184, 73, 0.12)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#f0b849',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                            maintainAspectRatio: true,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        padding: 15,
                                        usePointStyle: true,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleFont: {
                                        size: 14,
                                        weight: 'bold'
                                    },
                                    bodyFont: {
                                        size: 13
                                    },
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                        }
                                    }
                                }
                            },
                    scales: {
                                x: {
                                    grid: {
                                        display: true,
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    },
                                    ticks: {
                                        font: {
                                            size: 11
                                        },
                                        maxRotation: 45,
                                        minRotation: 0,
                                        // 如果是24小时数据，只显示偶数小时
                                        callback: function(value, index) {
                                            const labels = this.chart.data.labels;
                                            if (labels.length === 24) {
                                                return index % 2 === 0 ? labels[index] : '';
                                            }
                                            return labels[index];
                                        }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        display: true,
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    },
                                    ticks: {
                                        precision: 0,
                                        font: {
                                            size: 11
                                        },
                                        callback: function(value) {
                                            return value.toLocaleString();
                                        }
                                    }
                        }
                    }
                }
            });
                }
                
                // 访问类型分布图（饼图）
                const accessTypeCtx = document.getElementById('accessTypeChart');
                if (accessTypeCtx) {
                    const totalUser = <?php echo array_sum($chart_data['user_access']); ?>;
                    const totalSpider = <?php echo array_sum($chart_data['spider_access']); ?>;
                    const totalDenied = <?php echo array_sum($chart_data['denied']); ?>;
                    
                    new Chart(accessTypeCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: [
                                '<?php echo esc_js(__('User Access', 'folio')); ?>',
                                '<?php echo esc_js(__('Spider Access', 'folio')); ?>',
                                '<?php echo esc_js(__('Denied Access', 'folio')); ?>'
                            ],
                            datasets: [{
                                data: [totalUser, totalSpider, totalDenied],
                                backgroundColor: [
                                    'rgba(80, 87, 94, 0.85)',
                                    'rgba(0, 163, 42, 0.85)',
                                    'rgba(214, 54, 56, 0.85)'
                                ],
                                borderColor: [
                                    '#50575e',
                                    '#00a32a',
                                    '#d63638'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            aspectRatio: 1.6,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 15,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                
                // 安全事件详细趋势图
                const securityEventsCtx = document.getElementById('securityEventsChart');
                if (securityEventsCtx) {
                    new Chart(securityEventsCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($chart_data['labels']); ?>,
                            datasets: [{
                                label: '<?php echo esc_js(__('Bypass Attempts', 'folio')); ?>',
                                data: <?php echo json_encode($chart_data['bypass_attempts']); ?>,
                                borderColor: '#b32d2e',
                                backgroundColor: 'rgba(179, 45, 46, 0.12)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#b32d2e',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }, {
                                label: '<?php echo esc_js(__('Denied Access', 'folio')); ?>',
                                data: <?php echo json_encode($chart_data['denied']); ?>,
                                borderColor: '#d63638',
                                backgroundColor: 'rgba(214, 54, 56, 0.12)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#d63638',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }, {
                                label: '<?php echo esc_js(__('Suspicious Activity', 'folio')); ?>',
                                data: <?php echo json_encode($chart_data['suspicious']); ?>,
                                borderColor: '#f0b849',
                                backgroundColor: 'rgba(240, 184, 73, 0.12)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#f0b849',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }, {
                                label: '<?php echo esc_js(__('Blocked IP Count', 'folio')); ?>',
                                data: <?php echo json_encode($chart_data['blocked_ips']); ?>,
                                borderColor: '#646970',
                                backgroundColor: 'rgba(100, 105, 112, 0.12)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#646970',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                yAxisID: 'y1'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        padding: 15,
                                        usePointStyle: true,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleFont: {
                                        size: 14,
                                        weight: 'bold'
                                    },
                                    bodyFont: {
                                        size: 13
                                    },
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: true,
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    },
                                    ticks: {
                                        font: {
                                            size: 11
                                        },
                                        maxRotation: 45,
                                        minRotation: 0,
                                        // 如果是24小时数据，只显示偶数小时
                                        callback: function(value, index) {
                                            const labels = this.chart.data.labels;
                                            if (labels.length === 24) {
                                                return index % 2 === 0 ? labels[index] : '';
                                            }
                                            return labels[index];
                                        }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    position: 'left',
                                    grid: {
                                        display: true,
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    },
                                    ticks: {
                                        precision: 0,
                                        font: {
                                            size: 11
                                        },
                                        callback: function(value) {
                                            return value.toLocaleString();
                                        }
                                    }
                                },
                                y1: {
                                    beginAtZero: true,
                                    position: 'right',
                                    grid: {
                                        drawOnChartArea: false,
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    },
                                    ticks: {
                                        precision: 0,
                                        font: {
                                            size: 11
                                        },
                                        callback: function(value) {
                                            return value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                
                // 图表类型切换函数
                window.updateChartType = function() {
                    const chartType = document.getElementById('chartType').value;
                    window.location.href = '?page=folio-security&tab=dashboard&chart_type=' + chartType;
                };
            } catch (error) {
                console.error('<?php echo esc_js(__('Failed to create charts:', 'folio')); ?>', error);
            }
        });
        </script>
        <?php
    }

    /**
     * 渲染访问日志
     */
    private function render_logs() {
        // 添加错误处理和超时保护
        set_time_limit(30); // 设置30秒超时
        
        try {
        $security_manager = folio_Security_Protection_Manager::get_instance();
        
        // 处理过滤器
        $filters = array();
        if (!empty($_GET['filter_ip'])) {
            $filters['ip'] = sanitize_text_field($_GET['filter_ip']);
        }
        if (!empty($_GET['filter_action'])) {
            $filters['action_type'] = sanitize_text_field($_GET['filter_action']);
        }
        if (!empty($_GET['filter_type'])) {
            $filter_type = sanitize_text_field($_GET['filter_type']);
            if ($filter_type === 'spider') {
                $filters['is_spider'] = 1;
            } elseif ($filter_type === 'user') {
                $filters['is_spider'] = 0;
            }
        }
        if (!empty($_GET['filter_suspicious'])) {
            $filters['suspicious_only'] = true;
        }
        if (!empty($_GET['filter_date'])) {
            $filters['date_from'] = sanitize_text_field($_GET['filter_date']);
        }

        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $logs = $security_manager->get_access_logs($per_page, $offset, $filters);
            $total_logs = $security_manager->get_access_logs_count($filters);
            
            // 批量获取蜘蛛信息（优化性能）
            $spider_info_cache = array();
            if (!empty($logs)) {
                $spider_ips = array();
                foreach ($logs as $log) {
                    if (!empty($log->is_spider)) {
                        $spider_ips[] = $log->ip_address;
                    }
                }
                $spider_ips = array_unique($spider_ips);
                
                // 批量查询蜘蛛信息
                foreach ($spider_ips as $ip) {
                    $spider_info = $security_manager->get_spider_info_by_ip($ip);
                    if ($spider_info) {
                        $spider_info_cache[$ip] = $spider_info;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Folio Security Logs Error: ' . $e->getMessage());
            $logs = array();
            $total_logs = 0;
            echo '<div class="notice notice-error"><p>' . esc_html__('Error loading logs: ', 'folio') . esc_html($e->getMessage()) . '</p></div>';
        }
        ?>
        <div class="logs-section">
            <h2><?php echo esc_html__('Access Logs', 'folio'); ?></h2>
            
            <div class="log-filters">
                <form method="get">
                    <input type="hidden" name="page" value="folio-security">
                    <input type="hidden" name="tab" value="logs">
                    
                    <div class="filter-row">
                        <label><?php echo esc_html__('IP Address:', 'folio'); ?></label>
                        <input type="text" name="filter_ip" value="<?php echo esc_attr($_GET['filter_ip'] ?? ''); ?>" placeholder="<?php echo esc_attr__('Enter IP address', 'folio'); ?>">
                        
                        <label><?php echo esc_html__('Action Type:', 'folio'); ?></label>
                        <select name="filter_action">
                            <option value=""><?php echo esc_html__('All', 'folio'); ?></option>
                            <option value="page_view" <?php selected($_GET['filter_action'] ?? '', 'page_view'); ?>><?php echo esc_html__('Page View', 'folio'); ?></option>
                            <option value="content_view" <?php selected($_GET['filter_action'] ?? '', 'content_view'); ?>><?php echo esc_html__('Content View', 'folio'); ?></option>
                            <option value="api_access" <?php selected($_GET['filter_action'] ?? '', 'api_access'); ?>><?php echo esc_html__('API Access', 'folio'); ?></option>
                            <option value="rss_access" <?php selected($_GET['filter_action'] ?? '', 'rss_access'); ?>><?php echo esc_html__('RSS Access', 'folio'); ?></option>
                            <option value="bypass_attempt" <?php selected($_GET['filter_action'] ?? '', 'bypass_attempt'); ?>><?php echo esc_html__('Bypass Attempt', 'folio'); ?></option>
                        </select>
                        
                        <label><?php echo esc_html__('Type:', 'folio'); ?></label>
                        <select name="filter_type">
                            <option value=""><?php echo esc_html__('All', 'folio'); ?></option>
                            <option value="spider" <?php selected($_GET['filter_type'] ?? '', 'spider'); ?>><?php echo esc_html__('Spider', 'folio'); ?></option>
                            <option value="user" <?php selected($_GET['filter_type'] ?? '', 'user'); ?>><?php echo esc_html__('User', 'folio'); ?></option>
                        </select>
                        
                        <label>
                            <input type="checkbox" name="filter_suspicious" value="1" <?php checked(!empty($_GET['filter_suspicious'])); ?>>
                            <?php echo esc_html__('Show suspicious activity only', 'folio'); ?>
                        </label>
                    </div>
                    
                    <div class="filter-row">
                        <label><?php echo esc_html__('Date From:', 'folio'); ?></label>
                        <input type="date" name="filter_date" value="<?php echo esc_attr($_GET['filter_date'] ?? ''); ?>">
                        
                        <button type="submit" class="button"><?php echo esc_html__('Filter', 'folio'); ?></button>
                        <a href="?page=folio-security&tab=logs" class="button"><?php echo esc_html__('Clear Filters', 'folio'); ?></a>
                    </div>
                </form>
            </div>

            <table class="logs-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Time', 'folio'); ?></th>
                        <th><?php echo esc_html__('IP Address', 'folio'); ?></th>
                        <th><?php echo esc_html__('User', 'folio'); ?></th>
                        <th><?php echo esc_html__('Action Type', 'folio'); ?></th>
                        <th><?php echo esc_html__('Post ID', 'folio'); ?></th>
                        <th><?php echo esc_html__('Result', 'folio'); ?></th>
                        <th><?php echo esc_html__('Type', 'folio'); ?></th>
                        <th>User Agent</th>
                        <th><?php echo esc_html__('Status', 'folio'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) : ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;"><?php echo esc_html__('No logs found', 'folio'); ?></td>
                    </tr>
                    <?php else : ?>
                    <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td><?php echo esc_html($log->ip_address); ?></td>
                        <td>
                            <?php 
                            if ($log->user_id) {
                                $user = get_user_by('id', $log->user_id);
                                echo $user ? esc_html($user->display_name) : esc_html__('Unknown User', 'folio');
                            } else {
                                echo esc_html__('Guest', 'folio');
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($log->action_type); ?></td>
                        <td>
                            <?php 
                            if ($log->post_id) {
                                echo '<a href="' . get_edit_post_link($log->post_id) . '">' . $log->post_id . '</a>';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="status-<?php echo esc_attr($log->access_result); ?>">
                            <?php echo esc_html($log->access_result); ?>
                        </td>
                        <td>
                            <?php 
                            $types = array();
                            if (!empty($log->is_spider)) {
                                // 从缓存中获取蜘蛛信息
                                $spider_info = isset($spider_info_cache[$log->ip_address]) ? $spider_info_cache[$log->ip_address] : null;
                                
                                if ($spider_info) {
                                    $types[] = '<span style="color: #0073aa; font-weight: bold;" title="' . esc_attr__('Spider ID:', 'folio') . ' ' . esc_attr($spider_info['spider_id']) . '">' . esc_html($spider_info['spider_name']) . '</span>';
                                } else {
                                    $types[] = '<span style="color: #0073aa; font-weight: bold;">' . esc_html__('Spider', 'folio') . '</span>';
                                }
                            } else {
                                $types[] = '<span style="color: #666;">' . esc_html__('User', 'folio') . '</span>';
                            }
                            echo implode(' ', $types);
                            ?>
                        </td>
                        <td title="<?php echo esc_attr($log->user_agent); ?>">
                            <?php echo esc_html(wp_trim_words($log->user_agent, 10)); ?>
                        </td>
                        <td>
                            <?php 
                            $statuses = array();
                            if (!empty($log->is_suspicious)) {
                                $statuses[] = '<span class="status-suspicious">' . esc_html__('Suspicious', 'folio') . '</span>';
                            }
                            if (!empty($log->protection_bypassed)) {
                                $statuses[] = '<span class="status-blocked">' . esc_html__('Bypass', 'folio') . '</span>';
                            }
                            echo !empty($statuses) ? implode(' ', $statuses) : '-';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            // 分页
            $total_pages = ceil($total_logs / $per_page);
            if ($total_pages > 1) {
                echo '<div class="tablenav">';
                echo '<div class="tablenav-pages">';
                
                if ($page > 1) {
                    $prev_url = add_query_arg('paged', $page - 1, remove_query_arg('paged'));
                    echo '<a class="button" href="' . esc_url($prev_url) . '">' . esc_html__('Previous', 'folio') . '</a> ';
                }
                
                echo '<span class="paging-input">' . sprintf(esc_html__('Page %1$d of %2$d (%3$s records)', 'folio'), $page, $total_pages, number_format($total_logs)) . '</span> ';
                
                if ($page < $total_pages) {
                    $next_url = add_query_arg('paged', $page + 1, remove_query_arg('paged'));
                    echo '<a class="button" href="' . esc_url($next_url) . '">' . esc_html__('Next', 'folio') . '</a>';
                }
                
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * 渲染安全设置
     */
    private function render_settings() {
        if (isset($_POST['save_settings'])) {
            check_admin_referer('folio_security_settings');
            
            // 保存设置
            update_option('folio_security_rate_limit', intval($_POST['rate_limit']));
            update_option('folio_security_block_duration', intval($_POST['block_duration']));
            update_option('folio_security_log_retention', intval($_POST['log_retention']));
            update_option('folio_security_enable_logging', !empty($_POST['enable_logging']));
            update_option('folio_security_spider_whitelist', !empty($_POST['spider_whitelist']));
            update_option('folio_security_unknown_crawler_auto_action', !empty($_POST['unknown_crawler_auto_action']));
            $unknown_action = isset($_POST['unknown_crawler_action']) ? sanitize_text_field(wp_unslash($_POST['unknown_crawler_action'])) : 'block';
            if (!in_array($unknown_action, array('block', 'blacklist'), true)) {
                $unknown_action = 'block';
            }
            update_option('folio_security_unknown_crawler_action', $unknown_action);
            $unknown_block_duration = isset($_POST['unknown_crawler_block_duration']) ? intval($_POST['unknown_crawler_block_duration']) : 3600;
            $unknown_block_duration = max(300, min(604800, $unknown_block_duration));
            update_option('folio_security_unknown_crawler_block_duration', $unknown_block_duration);
            update_option('folio_security_whitelist', isset($_POST['whitelist']) ? wp_unslash($_POST['whitelist']) : '');
            update_option('folio_security_blacklist', isset($_POST['blacklist']) ? wp_unslash($_POST['blacklist']) : '');
            update_option('folio_banned_users', isset($_POST['banned_users']) ? wp_unslash($_POST['banned_users']) : '');
            update_option('folio_register_blocklist', isset($_POST['register_blocklist']) ? wp_unslash($_POST['register_blocklist']) : '');
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved', 'folio') . '</p></div>';
        }

        $rate_limit = get_option('folio_security_rate_limit', 10);
        $block_duration = get_option('folio_security_block_duration', 3600);
        $log_retention = get_option('folio_security_log_retention', 7);
        $enable_logging = get_option('folio_security_enable_logging', true);
        $spider_whitelist = get_option('folio_security_spider_whitelist', true);
        $unknown_crawler_auto_action = get_option('folio_security_unknown_crawler_auto_action', true);
        $unknown_crawler_action = get_option('folio_security_unknown_crawler_action', 'block');
        if (!in_array($unknown_crawler_action, array('block', 'blacklist'), true)) {
            $unknown_crawler_action = 'block';
        }
        $unknown_crawler_block_duration = intval(get_option('folio_security_unknown_crawler_block_duration', 3600));
        $whitelist = get_option('folio_security_whitelist', '');
        $blacklist = get_option('folio_security_blacklist', '');
        $banned_users = get_option('folio_banned_users', '');
        $register_blocklist = get_option('folio_register_blocklist', '');
        ?>
        <div class="settings-section">
            <h2><?php echo esc_html__('Security Settings', 'folio'); ?></h2>
            
            <form method="post">
                <?php wp_nonce_field('folio_security_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Rate Limit', 'folio'); ?></th>
                        <td>
                            <input type="number" name="rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="1" max="100">
                            <p class="description"><?php echo esc_html__('Maximum number of visits allowed within 10 minutes', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Block Duration', 'folio'); ?></th>
                        <td>
                            <input type="number" name="block_duration" value="<?php echo esc_attr($block_duration); ?>" min="300" max="86400">
                            <p class="description"><?php echo esc_html__('Block duration in seconds, default 3600 (1 hour)', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Log Retention Days', 'folio'); ?></th>
                        <td>
                            <input type="number" name="log_retention" value="<?php echo esc_attr($log_retention); ?>" min="7" max="365">
                            <p class="description"><?php echo esc_html__('Days to keep access logs', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable Access Logging', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_logging" value="1" <?php checked($enable_logging); ?>>
                                <?php echo esc_html__('Record access logs (may impact performance)', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Spider Auto Whitelist', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="spider_whitelist" value="1" <?php checked($spider_whitelist); ?>>
                                <?php echo esc_html__('Spiders/crawlers bypass rate limit and blocking automatically', 'folio'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Visits identified as search engine spiders (e.g. Google, Baidu) will skip protection checks', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Unknown Crawler Auto Action', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="unknown_crawler_auto_action" value="1" <?php checked($unknown_crawler_auto_action); ?>>
                                <?php echo esc_html__('Automatically handle unknown crawlers', 'folio'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('When enabled, unknown crawler requests are auto-blocked or added to blacklist based on the action below.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Unknown Crawler Action', 'folio'); ?></th>
                        <td>
                            <select name="unknown_crawler_action">
                                <option value="block" <?php selected($unknown_crawler_action, 'block'); ?>><?php echo esc_html__('Temporary Block', 'folio'); ?></option>
                                <option value="blacklist" <?php selected($unknown_crawler_action, 'blacklist'); ?>><?php echo esc_html__('Add to Blacklist', 'folio'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('Choose how unknown crawler IPs should be handled.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Unknown Crawler Block Duration', 'folio'); ?></th>
                        <td>
                            <input type="number" name="unknown_crawler_block_duration" value="<?php echo esc_attr($unknown_crawler_block_duration); ?>" min="300" max="604800">
                            <p class="description"><?php echo esc_html__('When action is temporary block, use this duration (seconds). Default: 3600.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Whitelist', 'folio'); ?></th>
                        <td>
                            <textarea name="whitelist" rows="6" class="large-text code" placeholder="<?php echo esc_attr__('One IP or CIDR per line&#10;Example:&#10;192.168.1.1&#10;10.0.0.0/24', 'folio'); ?>"><?php echo esc_textarea($whitelist); ?></textarea>
                            <p class="description"><?php echo esc_html__('Whitelisted IPs bypass rate limits and blocking. Supports single IP and CIDR format (e.g. 192.168.1.0/24)', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Blacklist', 'folio'); ?></th>
                        <td>
                            <textarea name="blacklist" rows="6" class="large-text code" placeholder="<?php echo esc_attr__('One IP or CIDR per line&#10;Example:&#10;1.2.3.4&#10;5.6.0.0/16', 'folio'); ?>"><?php echo esc_textarea($blacklist); ?></textarea>
                            <p class="description"><?php echo esc_html__('Blacklisted IPs are denied directly. Supports single IP and CIDR format', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Banned Users', 'folio'); ?></th>
                        <td>
                            <textarea name="banned_users" rows="6" class="large-text code" placeholder="<?php echo esc_attr__('One username or user ID per line&#10;Example:&#10;spambot1&#10;12345', 'folio'); ?>"><?php echo esc_textarea($banned_users); ?></textarea>
                            <p class="description"><?php echo esc_html__('Banned users cannot log in. One username or user ID (number) per line. Add robot-registered accounts here and save to ban them in batch.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Registration Blocklist', 'folio'); ?></th>
                        <td>
                            <textarea name="register_blocklist" rows="4" class="large-text code" placeholder="<?php echo esc_attr__('One username, email or domain per line&#10;Example:&#10;admin&#10;@tempmail.com', 'folio'); ?>"><?php echo esc_textarea($register_blocklist); ?></textarea>
                            <p class="description"><?php echo esc_html__('Usernames or emails in this list cannot register. Supports: username, full email, or email domain (e.g. @tempmail.com)', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_settings" class="button-primary" value="<?php echo esc_attr__('Save Settings', 'folio'); ?>">
                </p>
            </form>

            <div class="security-actions" style="margin-top: 40px;">
                <h3><?php echo esc_html__('Security Actions', 'folio'); ?></h3>
                <p>
                    <button type="button" class="button" onclick="cleanupOldLogs()"><?php echo esc_html__('Clean Expired Logs', 'folio'); ?></button>
                    <span class="description"><?php echo sprintf(esc_html__('Remove access logs older than %d days', 'folio'), esc_html($log_retention)); ?></span>
                </p>
                <p>
                    <button type="button" class="button" onclick="clearSecurityLogs()"><?php echo esc_html__('Clear All Logs', 'folio'); ?></button>
                    <span class="description"><?php echo esc_html__('Clear all access logs (dangerous)', 'folio'); ?></span>
                </p>
                <p>
                    <button type="button" class="button" onclick="unblockAllIPs()"><?php echo esc_html__('Unblock All IPs', 'folio'); ?></button>
                    <span class="description"><?php echo esc_html__('Unblock all blocked IP addresses', 'folio'); ?></span>
                </p>
            </div>
        </div>

        <script>
        function cleanupOldLogs() {
            if (confirm('<?php echo esc_js(sprintf(__('Clean logs older than %d days?', 'folio'), $log_retention)); ?>')) {
                jQuery.post(ajaxurl, {
                    action: 'folio_security_action',
                    security_action: 'cleanup_old_logs',
                    nonce: folioSecurity.nonce
                }, function(response) {
                    if (response.success) {
                        var message = response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Expired logs cleaned', 'folio')); ?>';
                        alert(message);
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__('Operation failed: ', 'folio')); ?>' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>'));
                    }
                });
            }
        }

        function clearSecurityLogs() {
            if (confirm('<?php echo esc_js(__('Clear all access logs? This action cannot be undone.', 'folio')); ?>')) {
                jQuery.post(ajaxurl, {
                    action: 'folio_security_action',
                    security_action: 'clear_logs',
                    nonce: folioSecurity.nonce
                }, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Logs cleared', 'folio')); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__('Operation failed: ', 'folio')); ?>' + response.data);
                    }
                });
            }
        }

        function unblockAllIPs() {
            if (confirm('<?php echo esc_js(__('Unblock all IPs?', 'folio')); ?>')) {
                jQuery.post(ajaxurl, {
                    action: 'folio_security_action',
                    security_action: 'unblock_ips',
                    nonce: folioSecurity.nonce
                }, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('All IP blocks removed', 'folio')); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__('Operation failed: ', 'folio')); ?>' + response.data);
                    }
                });
            }
        }
        </script>
        <?php
    }

    /**
     * 处理AJAX操作
     */
    public function handle_ajax_actions() {
        check_ajax_referer('folio_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'folio'));
        }

        $action = $_POST['security_action'] ?? '';
        
        switch ($action) {
            case 'cleanup_old_logs':
                // 清理过期日志
                $security_manager = folio_Security_Protection_Manager::get_instance();
                $deleted = $security_manager->cleanup_old_logs();
                
                if ($deleted !== false) {
                    $log_retention = get_option('folio_security_log_retention', 7);
                    wp_send_json_success(array(
                        'message' => sprintf(__('Cleaned %1$d expired logs older than %2$d days', 'folio'), $deleted, $log_retention),
                        'deleted' => $deleted
                    ));
                } else {
                    wp_send_json_error(__('Failed to clean expired logs', 'folio'));
                }
                break;
                
            case 'clear_logs':
                global $wpdb;
                $table_name = $wpdb->prefix . 'folio_access_logs';
                
                // 确保表存在
                $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
                if ($table_exists !== $table_name) {
                    wp_send_json_error(__('Log table does not exist', 'folio'));
                    return;
                }
                
                $result = $wpdb->query("TRUNCATE TABLE $table_name");
                
                if ($result !== false) {
                    wp_send_json_success(__('Logs cleared', 'folio'));
                } else {
                    wp_send_json_error(__('Failed to clear logs: ', 'folio') . $wpdb->last_error);
                }
                break;
                
            case 'unblock_ips':
                // 清除所有IP阻止缓存
                wp_cache_flush();
                wp_send_json_success(__('All IP blocks have been removed', 'folio'));
                break;
                
            default:
                wp_send_json_error(__('Unknown action', 'folio'));
        }
    }

    /**
     * 处理导入蜘蛛数据
     */
    public function handle_import_spiders() {
        check_ajax_referer('folio_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $spider_data = json_decode(stripslashes($_POST['spider_data'] ?? ''), true);
        
        if (empty($spider_data) || !is_array($spider_data)) {
            wp_send_json_error(__('Invalid data format', 'folio'));
        }
        
        $security_manager = folio_Security_Protection_Manager::get_instance();
        $imported = $security_manager->import_spider_nets($spider_data);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully imported %d spider IP ranges', 'folio'), $imported),
            'imported' => $imported
        ));
    }

    /**
     * 处理删除蜘蛛网段
     */
    public function handle_delete_spider_net() {
        check_ajax_referer('folio_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            wp_send_json_error(__('Invalid ID', 'folio'));
        }
        
        $security_manager = folio_Security_Protection_Manager::get_instance();
        $result = $security_manager->delete_spider_net($id);
        
        if ($result !== false) {
            wp_send_json_success(__('Deleted successfully', 'folio'));
        } else {
            wp_send_json_error(__('Delete failed', 'folio'));
        }
    }

    /**
     * 处理清空所有蜘蛛网段
     */
    public function handle_clear_spider_nets() {
        check_ajax_referer('folio_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $security_manager = folio_Security_Protection_Manager::get_instance();
        $result = $security_manager->clear_spider_nets();
        
        if ($result !== false) {
            wp_send_json_success(__('Cleared successfully', 'folio'));
        } else {
            wp_send_json_error(__('Clear failed', 'folio'));
        }
    }

    /**
     * 处理分析日志添加蜘蛛
     */
    public function handle_analyze_logs_for_spiders() {
        check_ajax_referer('folio_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $days = isset($_POST['days']) ? intval($_POST['days']) : 7;
        $min_count = isset($_POST['min_count']) ? intval($_POST['min_count']) : 3;
        
        // 限制参数范围
        $days = max(1, min(365, $days));
        $min_count = max(1, min(1000, $min_count));
        
        $security_manager = folio_Security_Protection_Manager::get_instance();
        $result = $security_manager->analyze_logs_for_spiders($days, $min_count);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Analysis completed: found %1$d spider types and added %2$d IP ranges', 'folio'),
                    $result['spiders_found'],
                    $result['nets_added']
                ),
                'result' => $result
            ));
        } else {
            wp_send_json_error(__('Analysis failed', 'folio'));
        }
    }

    /**
     * 渲染蜘蛛池管理页面
     */
    private function render_spiders() {
        $security_manager = folio_Security_Protection_Manager::get_instance();
        $spider_nets = $security_manager->get_spider_nets();
        
        // 按spider_id分组
        $grouped_nets = array();
        foreach ($spider_nets as $net) {
            $spider_id = $net['spider_id'];
            if (!isset($grouped_nets[$spider_id])) {
                $grouped_nets[$spider_id] = array(
                    'spider_name' => $net['spider_name'] ?? __('Unknown Spider', 'folio'),
                    'nets' => array()
                );
            }
            $grouped_nets[$spider_id]['nets'][] = $net;
        }
        ?>
        <div class="spiders-section">
            <h2><?php echo esc_html__('Spider Pool Management', 'folio'); ?></h2>
            
            <div class="spider-import-section" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                <h3><?php echo esc_html__('Analyze Logs and Add Spiders', 'folio'); ?></h3>
                <p><?php echo esc_html__('Automatically analyze access logs, identify spider/crawler IPs, and add them to the spider pool.', 'folio'); ?></p>
                <div style="margin: 15px 0;">
                    <label style="display: inline-block; margin-right: 15px;">
                        <span><?php echo esc_html__('Analysis Days:', 'folio'); ?></span>
                        <select id="analyze-days" style="width: 80px;">
                            <option value="1"><?php echo esc_html__('1 day', 'folio'); ?></option>
                            <option value="3"><?php echo esc_html__('3 days', 'folio'); ?></option>
                            <option value="7" selected><?php echo esc_html__('7 days', 'folio'); ?></option>
                            <option value="15"><?php echo esc_html__('15 days', 'folio'); ?></option>
                            <option value="30"><?php echo esc_html__('30 days', 'folio'); ?></option>
                        </select>
                    </label>
                    <label style="display: inline-block;">
                        <span><?php echo esc_html__('Minimum Visits:', 'folio'); ?></span>
                        <input type="number" id="analyze-min-count" value="3" min="1" max="100" style="width: 80px;">
                    </label>
                </div>
                <p>
                    <button type="button" id="analyze-logs-btn" class="button button-primary"><?php echo esc_html__('Start Log Analysis', 'folio'); ?></button>
                </p>
                <div id="analyze-result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="spider-import-section" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                <h3><?php echo esc_html__('Import Spider IP Ranges Manually', 'folio'); ?></h3>
                <p><?php echo esc_html__('Paste spider pool data in JSON format:', 'folio'); ?></p>
                <textarea id="spider-data-input" rows="10" style="width: 100%; font-family: monospace;" placeholder='[{"spider_id": 13, "net_list": [{"ip_net": "123.6.49.0/24", "id": 1}]}]'></textarea>
                <p style="margin-top: 10px;">
                    <button type="button" id="import-spiders-btn" class="button button-primary"><?php echo esc_html__('Import Data', 'folio'); ?></button>
                    <button type="button" id="clear-spiders-btn" class="button" style="margin-left: 10px;"><?php echo esc_html__('Clear All', 'folio'); ?></button>
                </p>
                <div id="import-result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="spider-stats" style="margin: 20px 0;">
                <p><strong><?php echo sprintf(esc_html__('Total spider IP range records: %d', 'folio'), count($spider_nets)); ?></strong></p>
            </div>
            
            <div class="spider-nets-list">
                <?php if (empty($grouped_nets)) : ?>
                    <p><?php echo esc_html__('No spider IP range data yet. Please import data first.', 'folio'); ?></p>
                <?php else : ?>
                    <?php foreach ($grouped_nets as $spider_id => $group) : ?>
                        <div class="spider-group" style="margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                            <h3><?php echo esc_html($group['spider_name']); ?> (ID: <?php echo esc_html($spider_id); ?>) - <?php echo sprintf(esc_html__('%d ranges', 'folio'), count($group['nets'])); ?></h3>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th><?php echo esc_html__('IP Range', 'folio'); ?></th>
                                        <th style="width: 150px;"><?php echo esc_html__('Created At', 'folio'); ?></th>
                                        <th style="width: 100px;"><?php echo esc_html__('Actions', 'folio'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($group['nets'] as $net) : ?>
                                        <tr>
                                            <td><?php echo esc_html($net['id']); ?></td>
                                            <td><code><?php echo esc_html($net['ip_net']); ?></code></td>
                                            <td><?php echo esc_html($net['created_at']); ?></td>
                                            <td>
                                                <button type="button" class="button button-small delete-spider-net" data-id="<?php echo esc_attr($net['id']); ?>"><?php echo esc_html__('Delete', 'folio'); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // 分析日志添加蜘蛛
            $('#analyze-logs-btn').on('click', function() {
                var days = parseInt($('#analyze-days').val()) || 7;
                var minCount = parseInt($('#analyze-min-count').val()) || 3;
                
                if (days < 1 || days > 365) {
                    alert('<?php echo esc_js(__('Analysis days must be between 1 and 365', 'folio')); ?>');
                    return;
                }
                
                if (minCount < 1 || minCount > 1000) {
                    alert('<?php echo esc_js(__('Minimum visits must be between 1 and 1000', 'folio')); ?>');
                    return;
                }
                
                if (!confirm('<?php echo esc_js(__('Analyze logs from the last', 'folio')); ?> ' + days + ' <?php echo esc_js(__('days and add detected spiders/crawlers to the pool. Continue?', 'folio')); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                var $result = $('#analyze-result');
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Analyzing...', 'folio')); ?>');
                $result.html('<div class="notice notice-info"><p><span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span><?php echo esc_js(__('Analyzing logs, please wait...', 'folio')); ?></p></div>');
                
                $.ajax({
                    url: folioSecurity.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'folio_analyze_logs_for_spiders',
                        nonce: folioSecurity.nonce,
                        days: days,
                        min_count: minCount
                    },
                    success: function(response) {
                        if (response.success) {
                            var result = response.data.result;
                            var html = '<div class="notice notice-success"><p><strong>' + response.data.message + '</strong></p>';
                            
                            if (result.details && result.details.length > 0) {
                                html += '<table class="widefat" style="margin-top: 10px;">';
                                html += '<thead><tr><th><?php echo esc_js(__('Spider Name', 'folio')); ?></th><th><?php echo esc_js(__('IP Count', 'folio')); ?></th><th><?php echo esc_js(__('Access Count', 'folio')); ?></th><th><?php echo esc_js(__('New Ranges', 'folio')); ?></th></tr></thead>';
                                html += '<tbody>';
                                
                                result.details.forEach(function(detail) {
                                    html += '<tr>';
                                    html += '<td>' + detail.spider_name + ' (ID: ' + detail.spider_id + ')</td>';
                                    html += '<td>' + detail.ip_count + '</td>';
                                    html += '<td>' + detail.access_count + '</td>';
                                    html += '<td>' + detail.nets_added + '</td>';
                                    html += '</tr>';
                                });
                                
                                html += '</tbody></table>';
                            }
                            
                            html += '</div>';
                            $result.html(html);
                            
                            // 如果添加了网段，3秒后刷新页面
                            if (result.nets_added > 0) {
                                setTimeout(function() {
                                    location.reload();
                                }, 3000);
                            }
                        } else {
                            $result.html('<div class="notice notice-error"><p>' + (response.data || '<?php echo esc_js(__('Analysis failed', 'folio')); ?>') + '</p></div>');
                        }
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Start Log Analysis', 'folio')); ?>');
                    },
                    error: function(xhr, status, error) {
                        $result.html('<div class="notice notice-error"><p><?php echo esc_js(__('Request failed: ', 'folio')); ?>' + error + '</p></div>');
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Start Log Analysis', 'folio')); ?>');
                    }
                });
            });
            
            // 导入蜘蛛数据
            $('#import-spiders-btn').on('click', function() {
                var spiderData = $('#spider-data-input').val().trim();
                
                if (!spiderData) {
                    alert('<?php echo esc_js(__('Please input spider data', 'folio')); ?>');
                    return;
                }
                
                // 验证JSON格式
                try {
                    JSON.parse(spiderData);
                } catch (e) {
                    alert('<?php echo esc_js(__('Invalid JSON format: ', 'folio')); ?>' + e.message);
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Importing...', 'folio')); ?>');
                
                $.ajax({
                    url: folioSecurity.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'folio_import_spiders',
                        nonce: folioSecurity.nonce,
                        spider_data: spiderData
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#import-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $('#import-result').html('<div class="notice notice-error"><p>' + (response.data || '<?php echo esc_js(__('Import failed', 'folio')); ?>') + '</p></div>');
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Import Data', 'folio')); ?>');
                        }
                    },
                    error: function() {
                        $('#import-result').html('<div class="notice notice-error"><p><?php echo esc_js(__('Request failed, please try again', 'folio')); ?></p></div>');
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Import Data', 'folio')); ?>');
                    }
                });
            });
            
            // 清空所有蜘蛛网段
            $('#clear-spiders-btn').on('click', function() {
                if (!confirm('<?php echo esc_js(__('Clear all spider IP ranges? This action cannot be undone.', 'folio')); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Clearing...', 'folio')); ?>');
                
                $.ajax({
                    url: folioSecurity.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'folio_clear_spider_nets',
                        nonce: folioSecurity.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php echo esc_js(__('Cleared successfully', 'folio')); ?>');
                            location.reload();
                        } else {
                            alert('<?php echo esc_js(__('Clear failed: ', 'folio')); ?>' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>'));
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Clear All', 'folio')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Request failed, please try again', 'folio')); ?>');
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Clear All', 'folio')); ?>');
                    }
                });
            });
            
            // 删除单个网段
            $('.delete-spider-net').on('click', function() {
                if (!confirm('<?php echo esc_js(__('Are you sure to delete this record?', 'folio')); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                var id = $btn.data('id');
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Deleting...', 'folio')); ?>');
                
                $.ajax({
                    url: folioSecurity.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'folio_delete_spider_net',
                        nonce: folioSecurity.nonce,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.closest('tr').fadeOut(function() {
                                $(this).remove();
                            });
                        } else {
                            alert('<?php echo esc_js(__('Delete failed: ', 'folio')); ?>' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>'));
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Delete', 'folio')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Request failed, please try again', 'folio')); ?>');
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Delete', 'folio')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 获取图表标签
     */
    private function get_chart_labels($days) {
        $labels = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('m/d', strtotime("-{$i} days"));
        }
        return $labels;
    }

    /**
     * 获取当天按小时的数据
     */
    private function get_today_hourly_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'folio_access_logs';
        
        // 确保表存在
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if ($table_exists !== $table_name) {
            return array(
                'labels' => array(),
                'total' => array(),
                'denied' => array(),
                'suspicious' => array(),
                'user_access' => array(),
                'spider_access' => array(),
                'bypass_attempts' => array(),
                'blocked_ips' => array(),
                'unique_ips' => array()
            );
        }
        
        // 使用缓存
        $cache_key = 'folio_security_today_hourly_data_' . date('Y-m-d');
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // 获取今天0点的时间
        $current_time = current_time('timestamp');
        $today_start_timestamp = strtotime('today', $current_time);
        $today_start = date('Y-m-d 00:00:00', $today_start_timestamp);
        
        // 按小时分组查询
        $sql = $wpdb->prepare(
            "SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN access_result = 'denied' THEN 1 ELSE 0 END), 0) as denied,
                COALESCE(SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END), 0) as suspicious,
                COALESCE(SUM(CASE WHEN COALESCE(is_spider, 0) = 0 THEN 1 ELSE 0 END), 0) as user_access,
                COALESCE(SUM(CASE WHEN is_spider = 1 THEN 1 ELSE 0 END), 0) as spider_access,
                COALESCE(SUM(CASE WHEN protection_bypassed = 1 THEN 1 ELSE 0 END), 0) as bypass_attempts,
                COALESCE(COUNT(DISTINCT CASE WHEN access_result = 'blocked' THEN ip_address END), 0) as blocked_ips,
                COALESCE(COUNT(DISTINCT ip_address), 0) as unique_ips
            FROM $table_name 
            WHERE created_at >= %s
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC",
            $today_start
        );
        
        $results = $wpdb->get_results($sql);
        
        // 如果查询失败，返回空数据
        if ($results === false && !empty($wpdb->last_error)) {
            error_log('Folio Security Today Hourly Chart: Query error: ' . $wpdb->last_error);
            return array(
                'labels' => array(),
                'total' => array(),
                'denied' => array(),
                'suspicious' => array(),
                'user_access' => array(),
                'spider_access' => array(),
                'bypass_attempts' => array(),
                'blocked_ips' => array(),
                'unique_ips' => array()
            );
        }
        
        // 生成小时标签和数据
        $labels = array();
        $total_data = array();
        $denied_data = array();
        $suspicious_data = array();
        $user_access_data = array();
        $spider_access_data = array();
        $bypass_attempts_data = array();
        $blocked_ips_data = array();
        $unique_ips_data = array();
        
        // 创建小时映射
        $hour_map = array();
        if (is_array($results)) {
            foreach ($results as $row) {
                $hour_map[intval($row->hour)] = array(
                    'total' => intval($row->total),
                    'denied' => intval($row->denied),
                    'suspicious' => intval($row->suspicious),
                    'user_access' => intval($row->user_access),
                    'spider_access' => intval($row->spider_access),
                    'bypass_attempts' => intval($row->bypass_attempts),
                    'blocked_ips' => intval($row->blocked_ips),
                    'unique_ips' => intval($row->unique_ips)
                );
            }
        }
        
         // 填充完整的24小时（00:00到23:00），未到的小时显示为0
         for ($hour = 0; $hour < 24; $hour++) {
             $labels[] = sprintf('%02d:00', $hour);
             $total_data[] = isset($hour_map[$hour]) ? $hour_map[$hour]['total'] : 0;
             $denied_data[] = isset($hour_map[$hour]) ? $hour_map[$hour]['denied'] : 0;
             $suspicious_data[] = isset($hour_map[$hour]) ? $hour_map[$hour]['suspicious'] : 0;
             $user_access_data[] = isset($hour_map[$hour]) ? $hour_map[$hour]['user_access'] : 0;
             $spider_access_data[] = isset($hour_map[$hour]) ? $hour_map[$hour]['spider_access'] : 0;
             $bypass_attempts_data[] = isset($hour_map[$hour]) ? $hour_map[$hour]['bypass_attempts'] : 0;
             $blocked_ips_data[] = isset($hour_map[$hour]) ? $hour_map[$hour]['blocked_ips'] : 0;
             $unique_ips_data[] = isset($hour_map[$hour]) ? $hour_map[$hour]['unique_ips'] : 0;
         }
        
        $chart_data = array(
            'labels' => $labels,
            'total' => $total_data,
            'denied' => $denied_data,
            'suspicious' => $suspicious_data,
            'user_access' => $user_access_data,
            'spider_access' => $spider_access_data,
            'bypass_attempts' => $bypass_attempts_data,
            'blocked_ips' => $blocked_ips_data,
            'unique_ips' => $unique_ips_data
        );
        
        // 缓存结果（5分钟）
        set_transient($cache_key, $chart_data, 300);
        
        return $chart_data;
    }

    /**
     * 获取优化的图表数据（使用单个聚合查询）
     */
    private function get_optimized_chart_data($days) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'folio_access_logs';
        
        // 确保表存在
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if ($table_exists !== $table_name) {
            // 表不存在，尝试创建
            try {
            $security_manager = folio_Security_Protection_Manager::get_instance();
            $security_manager->init_database();
            // 再次检查
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            if ($table_exists !== $table_name) {
                // 如果仍然不存在，返回空数据
                    return array(
                        'labels' => array(),
                        'total' => array(),
                        'denied' => array(),
                        'suspicious' => array()
                    );
            }
            } catch (Exception $e) {
                error_log('Folio Security Chart Data Error: ' . $e->getMessage());
                return array(
                    'labels' => array(),
                    'total' => array(),
                    'denied' => array(),
                    'suspicious' => array()
                );
            }
        }
        
        // 使用缓存
        $cache_key = 'folio_security_chart_data_' . $days;
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // 计算日期范围
        $start_date = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
        
        // 使用单个聚合查询获取所有数据（优化性能）
        // 增加更多统计维度：用户访问、蜘蛛访问、绕过尝试、被阻止IP
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN access_result = 'denied' THEN 1 ELSE 0 END), 0) as denied,
                COALESCE(SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END), 0) as suspicious,
                COALESCE(SUM(CASE WHEN COALESCE(is_spider, 0) = 0 THEN 1 ELSE 0 END), 0) as user_access,
                COALESCE(SUM(CASE WHEN is_spider = 1 THEN 1 ELSE 0 END), 0) as spider_access,
                COALESCE(SUM(CASE WHEN protection_bypassed = 1 THEN 1 ELSE 0 END), 0) as bypass_attempts,
                COALESCE(COUNT(DISTINCT CASE WHEN access_result = 'blocked' THEN ip_address END), 0) as blocked_ips,
                COALESCE(COUNT(DISTINCT ip_address), 0) as unique_ips
            FROM $table_name 
            WHERE created_at >= %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            $start_date
        );
        
        $results = $wpdb->get_results($sql);
        
        // 如果查询失败，返回空数据
        if ($results === false && !empty($wpdb->last_error)) {
            error_log('Folio Security Chart: Query error: ' . $wpdb->last_error);
            return array(
                'labels' => array(),
                'total' => array(),
                'denied' => array(),
                'suspicious' => array()
            );
        }
        
        // 生成日期标签和数据
        $labels = array();
        $total_data = array();
        $denied_data = array();
        $suspicious_data = array();
        $user_access_data = array();
        $spider_access_data = array();
        $bypass_attempts_data = array();
        $blocked_ips_data = array();
        $unique_ips_data = array();
        
        // 创建日期映射
        $date_map = array();
        if (is_array($results)) {
            foreach ($results as $row) {
                $date_map[$row->date] = array(
                    'total' => intval($row->total),
                    'denied' => intval($row->denied),
                    'suspicious' => intval($row->suspicious),
                    'user_access' => intval($row->user_access),
                    'spider_access' => intval($row->spider_access),
                    'bypass_attempts' => intval($row->bypass_attempts),
                    'blocked_ips' => intval($row->blocked_ips),
                    'unique_ips' => intval($row->unique_ips)
                );
            }
        }
        
        // 填充所有日期（包括没有数据的日期）
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $label = date('m/d', strtotime("-{$i} days"));
            
            $labels[] = $label;
            $total_data[] = isset($date_map[$date]) ? $date_map[$date]['total'] : 0;
            $denied_data[] = isset($date_map[$date]) ? $date_map[$date]['denied'] : 0;
            $suspicious_data[] = isset($date_map[$date]) ? $date_map[$date]['suspicious'] : 0;
            $user_access_data[] = isset($date_map[$date]) ? $date_map[$date]['user_access'] : 0;
            $spider_access_data[] = isset($date_map[$date]) ? $date_map[$date]['spider_access'] : 0;
            $bypass_attempts_data[] = isset($date_map[$date]) ? $date_map[$date]['bypass_attempts'] : 0;
            $blocked_ips_data[] = isset($date_map[$date]) ? $date_map[$date]['blocked_ips'] : 0;
            $unique_ips_data[] = isset($date_map[$date]) ? $date_map[$date]['unique_ips'] : 0;
        }
        
        $chart_data = array(
            'labels' => $labels,
            'total' => $total_data,
            'denied' => $denied_data,
            'suspicious' => $suspicious_data,
            'user_access' => $user_access_data,
            'spider_access' => $spider_access_data,
            'bypass_attempts' => $bypass_attempts_data,
            'blocked_ips' => $blocked_ips_data,
            'unique_ips' => $unique_ips_data
        );
        
        // 缓存结果（5分钟）
        set_transient($cache_key, $chart_data, 300);
        
        return $chart_data;
    }
    
    /**
     * 获取图表数据（已废弃，使用 get_optimized_chart_data 代替）
     */
    private function get_chart_data($type, $days) {
        $chart_data = $this->get_optimized_chart_data($days);
            
            switch ($type) {
                case 'total':
                return $chart_data['total'];
                case 'denied':
                return $chart_data['denied'];
                case 'suspicious':
                return $chart_data['suspicious'];
                default:
                return array();
        }
    }

    /**
     * 渲染运营报告页面
     */
    private function render_reports() {
        $report_manager = folio_Operations_Report_Manager::get_instance();
        $reports = $report_manager->get_reports(50, 0);
        ?>
        <div class="reports-section">
            <h2><?php echo esc_html__('AI Operations Reports', 'folio'); ?></h2>
            
            <div class="report-generate-section" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                <h3><?php echo esc_html__('Generate New Report', 'folio'); ?></h3>
                <p><?php echo esc_html__('Select a time range, and the system will analyze access logs to generate an AI operations report.', 'folio'); ?></p>
                <div style="margin: 15px 0;">
                    <label style="display: inline-block; margin-right: 15px;">
                        <span><?php echo esc_html__('Report Name:', 'folio'); ?></span>
                        <input type="text" id="report-name" placeholder="<?php echo esc_attr__('Auto generated', 'folio'); ?>" style="width: 300px;">
                    </label>
                </div>
                <div style="margin: 15px 0;">
                    <label style="display: inline-block; margin-right: 15px;">
                        <span><?php echo esc_html__('Start Date:', 'folio'); ?></span>
                        <input type="date" id="report-start-date" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" style="width: 150px;">
                    </label>
                    <label style="display: inline-block;">
                        <span><?php echo esc_html__('End Date:', 'folio'); ?></span>
                        <input type="date" id="report-end-date" value="<?php echo date('Y-m-d'); ?>" style="width: 150px;">
                    </label>
                </div>
                <div style="margin: 15px 0;">
                    <label style="display: inline-block; margin-right: 15px;">
                        <input type="checkbox" id="use-ai" checked>
                        <span><?php echo esc_html__('Use AI analysis (requires API Key and Endpoint in Theme Settings > AI Settings)', 'folio'); ?></span>
                    </label>
                </div>
                <p>
                    <button type="button" id="generate-report-btn" class="button button-primary"><?php echo esc_html__('Generate Report', 'folio'); ?></button>
                </p>
                <div id="generate-result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="reports-list" style="margin: 20px 0;">
                <h3><?php echo esc_html__('Report History', 'folio'); ?></h3>
                <?php if (empty($reports)) : ?>
                    <p><?php echo esc_html__('No reports yet. Please generate one first.', 'folio'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th><?php echo esc_html__('Report Name', 'folio'); ?></th>
                                <th><?php echo esc_html__('Time Range', 'folio'); ?></th>
                                <th style="width: 150px;"><?php echo esc_html__('Generated At', 'folio'); ?></th>
                                <th style="width: 200px;"><?php echo esc_html__('Actions', 'folio'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report) : ?>
                                <tr>
                                    <td><?php echo esc_html($report['id']); ?></td>
                                    <td><strong><?php echo esc_html($report['report_name']); ?></strong></td>
                                    <td>
                                        <?php echo esc_html(date('Y-m-d', strtotime($report['period_start']))); ?>
                                        <?php echo esc_html__('to', 'folio'); ?>
                                        <?php echo esc_html(date('Y-m-d', strtotime($report['period_end']))); ?>
                                    </td>
                                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($report['created_at']))); ?></td>
                                    <td>
                                        <button type="button" class="button button-small view-report" data-id="<?php echo esc_attr($report['id']); ?>"><?php echo esc_html__('View', 'folio'); ?></button>
                                        <button type="button" class="button button-small compare-report" data-id="<?php echo esc_attr($report['id']); ?>"><?php echo esc_html__('Compare', 'folio'); ?></button>
                                        <button type="button" class="button button-small delete-report" data-id="<?php echo esc_attr($report['id']); ?>"><?php echo esc_html__('Delete', 'folio'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 报告详情模态框 -->
        <div id="report-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; overflow-y: auto;">
            <div style="background: white; margin: 50px auto; padding: 20px; max-width: 900px; border-radius: 4px;">
                <div style="text-align: right; margin-bottom: 15px;">
                    <button type="button" id="close-report-modal" class="button"><?php echo esc_html__('Close', 'folio'); ?></button>
                </div>
                <div id="report-content"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // 生成报告
            $('#generate-report-btn').on('click', function() {
                var reportName = $('#report-name').val().trim();
                var startDate = $('#report-start-date').val();
                var endDate = $('#report-end-date').val();
                var useAI = $('#use-ai').is(':checked') ? 1 : 0;
                
                if (!startDate || !endDate) {
                    alert('<?php echo esc_js(__('Please select a time range', 'folio')); ?>');
                    return;
                }
                
                if (startDate > endDate) {
                    alert('<?php echo esc_js(__('Start date cannot be later than end date', 'folio')); ?>');
                    return;
                }
                
                var $btn = $(this);
                var $result = $('#generate-result');
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'folio')); ?>');
                $result.html('<div class="notice notice-info"><p><span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span><?php echo esc_js(__('Generating report, please wait (this may take a few minutes)...', 'folio')); ?></p></div>');
                
                $.ajax({
                    url: folioSecurity.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'folio_generate_report',
                        nonce: folioSecurity.nonce,
                        report_name: reportName,
                        period_start: startDate + ' 00:00:00',
                        period_end: endDate + ' 23:59:59',
                        use_ai: useAI
                    },
                    timeout: 300000, // 5分钟超时
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $result.html('<div class="notice notice-error"><p>' + (response.data || '<?php echo esc_js(__('Generation failed', 'folio')); ?>') + '</p></div>');
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Generate Report', 'folio')); ?>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $result.html('<div class="notice notice-error"><p><?php echo esc_js(__('Request failed: ', 'folio')); ?>' + error + '</p></div>');
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Generate Report', 'folio')); ?>');
                    }
                });
            });
            
            // 查看报告
            $('.view-report').on('click', function() {
                var reportId = $(this).data('id');
                $('#report-modal').show();
                $('#report-content').html('<p><?php echo esc_js(__('Loading...', 'folio')); ?></p>');
                
                $.ajax({
                    url: folioSecurity.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'folio_get_report',
                        nonce: folioSecurity.nonce,
                        report_id: reportId
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#report-content').html(response.data.html);
                        } else {
                            $('#report-content').html('<p><?php echo esc_js(__('Load failed: ', 'folio')); ?>' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>') + '</p>');
                        }
                    },
                    error: function() {
                        $('#report-content').html('<p><?php echo esc_js(__('Request failed', 'folio')); ?></p>');
                    }
                });
            });
            
            // 关闭模态框
            $('#close-report-modal, #report-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#report-modal').hide();
                }
            });
            
            // 对比报告
            $('.compare-report').on('click', function() {
                var reportId1 = $(this).data('id');
                var reportId2 = prompt('<?php echo esc_js(__('Please enter the report ID to compare:', 'folio')); ?>');
                
                if (!reportId2) {
                    return;
                }
                
                $('#report-modal').show();
                $('#report-content').html('<p><?php echo esc_js(__('Comparing...', 'folio')); ?></p>');
                
                $.ajax({
                    url: folioSecurity.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'folio_compare_reports',
                        nonce: folioSecurity.nonce,
                        report_id1: reportId1,
                        report_id2: reportId2
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#report-content').html(response.data.html);
                        } else {
                            $('#report-content').html('<p><?php echo esc_js(__('Comparison failed: ', 'folio')); ?>' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>') + '</p>');
                        }
                    },
                    error: function() {
                        $('#report-content').html('<p><?php echo esc_js(__('Request failed', 'folio')); ?></p>');
                    }
                });
            });
            
            // 删除报告
            $('.delete-report').on('click', function() {
                if (!confirm('<?php echo esc_js(__('Are you sure to delete this report?', 'folio')); ?>')) {
                    return;
                }
                
                var reportId = $(this).data('id');
                var $row = $(this).closest('tr');
                
                $.ajax({
                    url: folioSecurity.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'folio_delete_report',
                        nonce: folioSecurity.nonce,
                        report_id: reportId
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(function() {
                                $(this).remove();
                            });
                        } else {
                            alert('<?php echo esc_js(__('Delete failed: ', 'folio')); ?>' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>'));
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Request failed', 'folio')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 处理生成报告
     */
    public function handle_generate_report() {
        check_ajax_referer('folio_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $report_name = isset($_POST['report_name']) ? sanitize_text_field($_POST['report_name']) : '';
        $period_start = isset($_POST['period_start']) ? sanitize_text_field($_POST['period_start']) : '';
        $period_end = isset($_POST['period_end']) ? sanitize_text_field($_POST['period_end']) : '';
        $use_ai = isset($_POST['use_ai']) ? (int)$_POST['use_ai'] : 0;
        
        if (empty($period_start) || empty($period_end)) {
            wp_send_json_error(__('Please select a time range', 'folio'));
        }
        
        $report_manager = folio_Operations_Report_Manager::get_instance();
        $options = array(
            'use_ai' => (bool)$use_ai,
            'report_type' => 'general'
        );
        
        // 检查是否配置了AI API（从主题选项中读取）
        if ($use_ai) {
            $theme_options = get_option('folio_theme_options', array());
            $ai_api_key = isset($theme_options['ai_api_key']) ? $theme_options['ai_api_key'] : '';
            if (empty($ai_api_key)) {
                $options['use_ai'] = false; // 如果没有配置，使用基础分析
            }
        }
        
        $report_id = $report_manager->generate_report($period_start, $period_end, $report_name, $options);
        
        if ($report_id) {
            wp_send_json_success(array(
                'message' => sprintf(__('Report generated successfully! Report ID: %d', 'folio'), $report_id),
                'report_id' => $report_id
            ));
        } else {
            wp_send_json_error(__('Failed to generate report', 'folio'));
        }
    }

    /**
     * 处理获取报告
     */
    public function handle_get_report() {
        check_ajax_referer('folio_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
        
        if (!$report_id) {
            wp_send_json_error(__('Invalid report ID', 'folio'));
        }
        
        $report_manager = folio_Operations_Report_Manager::get_instance();
        $report = $report_manager->get_report($report_id);
        
        if (!$report) {
            wp_send_json_error(__('Report not found', 'folio'));
        }
        
        $html = $this->format_report_html($report);
        
        wp_send_json_success(array('html' => $html));
    }

    /**
     * 格式化报告HTML（增强版，展示更多专业指标）
     */
    private function format_report_html($report) {
        $summary = $report['summary_data'] ?? array();
        $detailed = $report['detailed_data'] ?? array();
        $insights = $report['ai_insights'] ?? array();
        $recommendations = $report['recommendations'] ?? array();
        
        $html = '<div style="max-width: 1200px;">';
        $html .= '<h2>' . esc_html($report['report_name']) . '</h2>';
        $html .= '<div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">';
        $html .= '<p><strong>' . esc_html__('Time Range:', 'folio') . '</strong> ' . date('Y-m-d', strtotime($report['period_start'])) . ' ' . esc_html__('to', 'folio') . ' ' . date('Y-m-d', strtotime($report['period_end'])) . '</p>';
        $html .= '<p><strong>' . esc_html__('Generated At:', 'folio') . '</strong> ' . date('Y-m-d H:i:s', strtotime($report['created_at'])) . '</p>';
        $html .= '</div>';
        
        // 核心指标卡片
        if (!empty($summary['overview']) || !empty($summary['metrics'])) {
            $html .= '<h3 style="margin-top: 30px;">📊 ' . esc_html__('Core Operations Metrics', 'folio') . '</h3>';
            $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 30px;">';
            
            // 流量质量评分卡片
            if (isset($summary['metrics']['traffic_quality_score'])) {
                $score = $summary['metrics']['traffic_quality_score'];
                $color = $score >= 80 ? '#28a745' : ($score >= 60 ? '#ffc107' : '#dc3545');
                $html .= '<div style="padding: 20px; background: white; border: 2px solid ' . $color . '; border-radius: 8px; text-align: center;">';
                $html .= '<div style="font-size: 36px; font-weight: bold; color: ' . $color . ';">' . $score . '</div>';
                $html .= '<div style="margin-top: 10px; color: #666;">' . esc_html__('Traffic Quality Score', 'folio') . '</div>';
                $html .= '</div>';
            }
            
            // 其他关键指标
            $key_metrics = array(
                'avg_visits_per_ip' => array('label' => __('Avg Visits per IP', 'folio'), 'suffix' => __('times', 'folio'), 'color' => '#0073aa'),
                'user_retention_rate' => array('label' => __('User Retention Rate', 'folio'), 'suffix' => '%', 'color' => '#28a745'),
                'content_concentration' => array('label' => __('Content Concentration', 'folio'), 'suffix' => '%', 'color' => '#ffc107'),
                'suspicious_ratio' => array('label' => __('Suspicious Activity Ratio', 'folio'), 'suffix' => '%', 'color' => '#dc3545'),
            );
            
            foreach ($key_metrics as $key => $config) {
                if (isset($summary['metrics'][$key])) {
                    $value = $summary['metrics'][$key];
                    $html .= '<div style="padding: 20px; background: white; border: 1px solid #ddd; border-radius: 8px; text-align: center;">';
                    $html .= '<div style="font-size: 28px; font-weight: bold; color: ' . $config['color'] . ';">' . $value . '</div>';
                    $html .= '<div style="margin-top: 10px; color: #666;">' . $config['label'] . '</div>';
                    $html .= '</div>';
                }
            }
            
            $html .= '</div>';
        }
        
        // 访问概览（详细版）
        if (!empty($summary['overview'])) {
            $html .= '<h3 style="margin-top: 30px;">📈 ' . esc_html__('Access Overview', 'folio') . '</h3>';
            $html .= '<table class="widefat" style="margin-bottom: 30px;"><thead><tr><th>' . esc_html__('Metric', 'folio') . '</th><th>' . esc_html__('Value', 'folio') . '</th><th>' . esc_html__('Ratio', 'folio') . '</th></tr></thead><tbody>';
            
            $total = $summary['overview']['total_access'] ?? 0;
            $overview_labels = array(
                'total_access' => array(__('Total Access', 'folio'), '', ''),
                'user_access' => array(__('User Access', 'folio'), 'user_ratio', '%'),
                'spider_access' => array(__('Spider Access', 'folio'), 'spider_ratio', '%'),
                'logged_in_access' => array(__('Logged-in User Access', 'folio'), '', ''),
                'unique_ips' => array(__('Unique IPs', 'folio'), '', ''),
                'unique_posts' => array(__('Visited Posts', 'folio'), '', ''),
                'suspicious_activity' => array(__('Suspicious Activity', 'folio'), 'suspicious_ratio', '%'),
                'denied_access' => array(__('Denied Access', 'folio'), 'denied_ratio', '%')
            );
            
            foreach ($overview_labels as $key => $info) {
                if (isset($summary['overview'][$key])) {
                    $value = $summary['overview'][$key];
                    $ratio = '';
                    if (!empty($info[1]) && isset($summary['metrics'][$info[1]])) {
                        $ratio = number_format($summary['metrics'][$info[1]], 2) . $info[2];
                    }
                    $html .= '<tr>';
                    $html .= '<td><strong>' . $info[0] . '</strong></td>';
                    $html .= '<td>' . number_format($value) . '</td>';
                    $html .= '<td>' . $ratio . '</td>';
                    $html .= '</tr>';
                }
            }
            
            $html .= '</tbody></table>';
        }
        
        // 趋势分析
        if (!empty($summary['trends'])) {
            $html .= '<h3 style="margin-top: 30px;">📉 ' . esc_html__('Access Trend Analysis', 'folio') . '</h3>';
            $html .= '<div style="padding: 20px; background: #f9f9f9; border-radius: 4px; margin-bottom: 30px;">';
            $trends = $summary['trends'];
            $html .= '<p><strong>' . esc_html__('Average Daily Access:', 'folio') . '</strong> ' . number_format($trends['average_daily_access'] ?? 0) . ' ' . esc_html__('times', 'folio') . '</p>';
            $trend_text = ($trends['trend_direction'] === 'up')
                ? '📈 ' . esc_html__('Rising', 'folio')
                : (($trends['trend_direction'] === 'down') ? '📉 ' . esc_html__('Falling', 'folio') : '➡️ ' . esc_html__('Stable', 'folio'));
            $html .= '<p><strong>' . esc_html__('Trend Direction:', 'folio') . '</strong> ' . $trend_text . '</p>';
            if (!empty($trends['peak_day'])) {
                $html .= '<p><strong>' . esc_html__('Peak Day:', 'folio') . '</strong> ' . $trends['peak_day'] . '</p>';
            }
            if (!empty($trends['peak_hour'])) {
                $html .= '<p><strong>' . esc_html__('Peak Hour:', 'folio') . '</strong> ' . $trends['peak_hour'] . '</p>';
            }
            if (!empty($trends['daily_variance'])) {
                $html .= '<p><strong>' . esc_html__('Access Stability (Variance):', 'folio') . '</strong> ' . number_format($trends['daily_variance'], 2) . ' (' . esc_html__('higher values indicate larger fluctuations', 'folio') . ')</p>';
            }
            $html .= '</div>';
        }
        
        // 周分布模式
        if (!empty($detailed['weekly_pattern'])) {
            $html .= '<h3 style="margin-top: 30px;">📅 ' . esc_html__('Weekly Access Pattern', 'folio') . '</h3>';
            $html .= '<table class="widefat" style="margin-bottom: 30px;"><thead><tr><th>' . esc_html__('Weekday', 'folio') . '</th><th>' . esc_html__('Average Access', 'folio') . '</th></tr></thead><tbody>';
            foreach ($detailed['weekly_pattern'] as $pattern) {
                $html .= '<tr>';
                $html .= '<td><strong>' . $pattern['day_name'] . '</strong></td>';
                $html .= '<td>' . number_format($pattern['average_visits']) . ' ' . esc_html__('times', 'folio') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }
        
        // AI洞察（分类显示）
        if (!empty($insights)) {
            $html .= '<h3 style="margin-top: 30px;">🔍 ' . esc_html__('AI Insights', 'folio') . '</h3>';
            $html .= '<div style="padding: 20px; background: #e7f3ff; border-left: 4px solid #0073aa; margin-bottom: 30px;">';
            $html .= '<ul style="line-height: 2;">';
            foreach ($insights as $insight) {
                $html .= '<li style="margin-bottom: 10px;">' . esc_html($insight) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // 优化建议（分类显示）
        if (!empty($recommendations)) {
            $html .= '<h3 style="margin-top: 30px;">💡 ' . esc_html__('Optimization Suggestions', 'folio') . '</h3>';
            $html .= '<div style="padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 30px;">';
            $html .= '<ol style="line-height: 2;">';
            foreach ($recommendations as $recommendation) {
                $html .= '<li style="margin-bottom: 10px;">' . esc_html($recommendation) . '</li>';
            }
            $html .= '</ol>';
            $html .= '</div>';
        }
        
        // 热门内容（增强版，包含更多指标）
        if (!empty($summary['top_content'])) {
            $html .= '<h3 style="margin-top: 30px;">🔥 ' . esc_html__('Top Content 20', 'folio') . '</h3>';
            $html .= '<table class="widefat" style="margin-bottom: 30px;">';
            $html .= '<thead><tr><th>' . esc_html__('Rank', 'folio') . '</th><th>' . esc_html__('Post Title', 'folio') . '</th><th>' . esc_html__('Visits', 'folio') . '</th><th>' . esc_html__('Unique Visitors', 'folio') . '</th><th>' . esc_html__('Avg Visits/User', 'folio') . '</th><th>' . esc_html__('Engagement Score', 'folio') . '</th></tr></thead>';
            $html .= '<tbody>';
            foreach (array_slice($summary['top_content'], 0, 20) as $index => $post) {
                $avg_visits = isset($post['avg_visits_per_visitor']) ? $post['avg_visits_per_visitor'] : ($post['unique_visitors'] > 0 ? round($post['views'] / $post['unique_visitors'], 2) : 0);
                $engagement = isset($post['engagement_score']) ? $post['engagement_score'] : min(100, round(($avg_visits / 5) * 100, 1));
                $engagement_color = $engagement >= 60 ? '#28a745' : ($engagement >= 40 ? '#ffc107' : '#dc3545');
                
                $html .= '<tr>';
                $html .= '<td><strong>#' . ($index + 1) . '</strong></td>';
                $html .= '<td><a href="' . esc_url($post['url']) . '" target="_blank">' . esc_html($post['title']) . '</a></td>';
                $html .= '<td>' . number_format($post['views']) . '</td>';
                $html .= '<td>' . number_format($post['unique_visitors']) . '</td>';
                $html .= '<td>' . $avg_visits . ' ' . esc_html__('times/user', 'folio') . '</td>';
                $html .= '<td><span style="color: ' . $engagement_color . '; font-weight: bold;">' . $engagement . '</span>/100</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }
        
        // 蜘蛛分布
        if (!empty($detailed['spider_breakdown'])) {
            $html .= '<h3 style="margin-top: 30px;">🕷️ ' . esc_html__('Search Engine Spider Distribution', 'folio') . '</h3>';
            $html .= '<table class="widefat" style="margin-bottom: 30px;">';
            $html .= '<thead><tr><th>' . esc_html__('Spider Type', 'folio') . '</th><th>' . esc_html__('Visits', 'folio') . '</th><th>' . esc_html__('Unique IPs', 'folio') . '</th><th>' . esc_html__('Ratio', 'folio') . '</th></tr></thead>';
            $html .= '<tbody>';
            $spider_total = $summary['overview']['spider_access'] ?? 1;
            foreach (array_slice($detailed['spider_breakdown'], 0, 15) as $spider) {
                $ratio = $spider_total > 0 ? round(($spider['count'] / $spider_total) * 100, 2) : 0;
                $html .= '<tr>';
                $html .= '<td>' . esc_html($spider['name']) . '</td>';
                $html .= '<td>' . number_format($spider['count']) . '</td>';
                $html .= '<td>' . number_format($spider['unique_ips']) . '</td>';
                $html .= '<td>' . $ratio . '%</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }
        
        // 流量来源
        if (!empty($summary['traffic_sources'])) {
            $html .= '<h3 style="margin-top: 30px;">🌐 ' . esc_html__('Top Traffic Sources', 'folio') . '</h3>';
            $html .= '<table class="widefat" style="margin-bottom: 30px;">';
            $html .= '<thead><tr><th>' . esc_html__('Source Domain', 'folio') . '</th><th>' . esc_html__('Visits', 'folio') . '</th><th>' . esc_html__('Ratio', 'folio') . '</th></tr></thead>';
            $html .= '<tbody>';
            $source_total = $summary['overview']['total_access'] ?? 1;
            foreach (array_slice($summary['traffic_sources'], 0, 15, true) as $domain => $count) {
                $ratio = $source_total > 0 ? round(($count / $source_total) * 100, 2) : 0;
                $html .= '<tr>';
                $html .= '<td>' . esc_html($domain) . '</td>';
                $html .= '<td>' . number_format($count) . '</td>';
                $html .= '<td>' . $ratio . '%</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * 处理对比报告
     */
    public function handle_compare_reports() {
        check_ajax_referer('folio_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $report_id1 = isset($_POST['report_id1']) ? intval($_POST['report_id1']) : 0;
        $report_id2 = isset($_POST['report_id2']) ? intval($_POST['report_id2']) : 0;
        
        if (!$report_id1 || !$report_id2) {
            wp_send_json_error(__('Invalid report ID', 'folio'));
        }
        
        $report_manager = folio_Operations_Report_Manager::get_instance();
        $comparison = $report_manager->compare_reports($report_id1, $report_id2);
        
        if (!$comparison) {
            wp_send_json_error(__('Comparison failed', 'folio'));
        }
        
        $html = '<h2>' . esc_html__('Report Comparison', 'folio') . '</h2>';
        $html .= '<p><strong>' . esc_html__('Report 1:', 'folio') . '</strong> ' . esc_html($comparison['report1']['name']) . ' (' . $comparison['report1']['period']['start'] . ' ' . esc_html__('to', 'folio') . ' ' . $comparison['report1']['period']['end'] . ')</p>';
        $html .= '<p><strong>' . esc_html__('Report 2:', 'folio') . '</strong> ' . esc_html($comparison['report2']['name']) . ' (' . $comparison['report2']['period']['start'] . ' ' . esc_html__('to', 'folio') . ' ' . $comparison['report2']['period']['end'] . ')</p>';
        
        $html .= '<h3>' . esc_html__('Metric Comparison', 'folio') . '</h3>';
        $html .= '<table class="widefat"><thead><tr><th>' . esc_html__('Metric', 'folio') . '</th><th>' . esc_html__('Report 1', 'folio') . '</th><th>' . esc_html__('Report 2', 'folio') . '</th><th>' . esc_html__('Change', 'folio') . '</th><th>' . esc_html__('Change Rate', 'folio') . '</th></tr></thead><tbody>';
        
        $labels = array(
            'total_access' => __('Total Access', 'folio'),
            'user_access' => __('User Access', 'folio'),
            'spider_access' => __('Spider Access', 'folio'),
            'unique_ips' => __('Unique IPs', 'folio'),
            'unique_posts' => __('Visited Posts', 'folio'),
            'suspicious_activity' => __('Suspicious Activity', 'folio'),
            'denied_access' => __('Denied Access', 'folio')
        );
        
        foreach ($comparison['metrics'] as $key => $metric) {
            $label = $labels[$key] ?? $key;
            $trend_class = $metric['trend'] === 'up' ? 'style="color: green;"' : ($metric['trend'] === 'down' ? 'style="color: red;"' : '');
            $change_text = $metric['change'] > 0 ? '+' . number_format($metric['change']) : number_format($metric['change']);
            $percent_text = $metric['change_percent'] > 0 ? '+' . $metric['change_percent'] . '%' : $metric['change_percent'] . '%';
            
            $html .= '<tr>';
            $html .= '<td><strong>' . $label . '</strong></td>';
            $html .= '<td>' . number_format($metric['report1']) . '</td>';
            $html .= '<td>' . number_format($metric['report2']) . '</td>';
            $html .= '<td ' . $trend_class . '>' . $change_text . '</td>';
            $html .= '<td ' . $trend_class . '>' . $percent_text . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        if (!empty($comparison['trend_analysis'])) {
            $html .= '<h3>' . esc_html__('Trend Analysis', 'folio') . '</h3><ul>';
            foreach ($comparison['trend_analysis'] as $analysis) {
                $html .= '<li>' . esc_html($analysis) . '</li>';
            }
            $html .= '</ul>';
        }
        
        wp_send_json_success(array('html' => $html));
    }

    /**
     * 处理删除报告
     */
    public function handle_delete_report() {
        check_ajax_referer('folio_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
        
        if (!$report_id) {
            wp_send_json_error(__('Invalid report ID', 'folio'));
        }
        
        $report_manager = folio_Operations_Report_Manager::get_instance();
        $result = $report_manager->delete_report($report_id);
        
        if ($result) {
            wp_send_json_success(__('Deleted successfully', 'folio'));
        } else {
            wp_send_json_error(__('Delete failed', 'folio'));
        }
    }
}

// 初始化安全管理页面
if (is_admin()) {
    new folio_Security_Admin();
}

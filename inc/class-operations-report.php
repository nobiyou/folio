<?php
/**
 * Operations Report Manager
 * 
 * 运营报告管理器 - 使用AI分析访问日志，生成运营报告并支持对比分析
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Operations_Report_Manager {

    // 报告表名
    const REPORT_TABLE = 'folio_operations_reports';
    
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', array($this, 'init_database'));
    }

    /**
     * 初始化数据库表
     */
    public function init_database() {
        global $wpdb;
        static $initialized = false;
        
        if ($initialized) {
            return;
        }
        
        $table_name = $wpdb->prefix . self::REPORT_TABLE;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            report_name varchar(255) NOT NULL,
            report_type varchar(50) NOT NULL DEFAULT 'general',
            period_start datetime NOT NULL,
            period_end datetime NOT NULL,
            summary_data longtext,
            detailed_data longtext,
            ai_insights longtext,
            recommendations longtext,
            comparison_data longtext,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_report_type (report_type),
            KEY idx_period (period_start, period_end),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $initialized = true;
    }

    /**
     * 生成运营报告
     * 
     * @param string $period_start 报告开始时间
     * @param string $period_end 报告结束时间
     * @param string $report_name 报告名称
     * @param array $options 选项配置
     * @return int|false 报告ID或false
     */
    public function generate_report($period_start, $period_end, $report_name = '', $options = array()) {
        global $wpdb;
        
        $this->init_database();
        
        // 如果没有提供报告名称，自动生成
        if (empty($report_name)) {
            $report_name = sprintf(
                '运营报告_%s_至_%s',
                date('Y-m-d', strtotime($period_start)),
                date('Y-m-d', strtotime($period_end))
            );
        }
        
        // 收集数据
        $security_manager = folio_Security_Protection_Manager::get_instance();
        $stats = $security_manager->get_security_stats_by_period($period_start, $period_end);
        
        // 生成详细数据
        $detailed_data = $this->collect_detailed_data($period_start, $period_end);
        
        // 生成摘要数据
        $summary_data = $this->generate_summary($stats, $detailed_data, $period_start, $period_end);
        
        // AI分析和洞察
        $use_ai = isset($options['use_ai']) ? (bool)$options['use_ai'] : true;
        $ai_insights = array();
        $recommendations = array();
        
        if ($use_ai) {
            $ai_result = $this->generate_ai_insights($summary_data, $detailed_data, $options);
            $ai_insights = $ai_result['insights'] ?? array();
            $recommendations = $ai_result['recommendations'] ?? array();
        } else {
            // 即使不使用AI，也生成基础洞察
            $ai_insights = $this->generate_basic_insights($summary_data, $detailed_data);
            $recommendations = $this->generate_basic_recommendations($summary_data, $detailed_data);
        }
        
        // 保存报告
        $report_data = array(
            'report_name' => sanitize_text_field($report_name),
            'report_type' => isset($options['report_type']) ? sanitize_text_field($options['report_type']) : 'general',
            'period_start' => $period_start,
            'period_end' => $period_end,
            'summary_data' => wp_json_encode($summary_data, JSON_UNESCAPED_UNICODE),
            'detailed_data' => wp_json_encode($detailed_data, JSON_UNESCAPED_UNICODE),
            'ai_insights' => wp_json_encode($ai_insights, JSON_UNESCAPED_UNICODE),
            'recommendations' => wp_json_encode($recommendations, JSON_UNESCAPED_UNICODE),
            'created_by' => get_current_user_id()
        );
        
        $result = $wpdb->insert($wpdb->prefix . self::REPORT_TABLE, $report_data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }

    /**
     * 收集详细数据
     */
    private function collect_detailed_data($period_start, $period_end) {
        global $wpdb;
        $table_name = $wpdb->prefix . folio_Security_Protection_Manager::LOG_TABLE;
        
        $data = array(
            'hourly_trends' => array(),
            'daily_trends' => array(),
            'top_posts' => array(),
            'top_referrers' => array(),
            'user_agents' => array(),
            'spider_breakdown' => array(),
            'geographic_distribution' => array(),
            'device_types' => array(),
            'access_patterns' => array()
        );
        
        // 按小时统计访问趋势
        $hourly = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00') as hour,
                COUNT(*) as total,
                SUM(CASE WHEN is_spider = 1 THEN 1 ELSE 0 END) as spiders,
                SUM(CASE WHEN is_spider = 0 THEN 1 ELSE 0 END) as users,
                SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END) as suspicious,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM `$table_name`
            WHERE created_at >= %s AND created_at <= %s
            GROUP BY hour
            ORDER BY hour",
            $period_start, $period_end
        ));
        
        foreach ($hourly as $row) {
            $data['hourly_trends'][] = array(
                'hour' => $row->hour,
                'total' => intval($row->total),
                'spiders' => intval($row->spiders),
                'users' => intval($row->users),
                'suspicious' => intval($row->suspicious),
                'unique_ips' => intval($row->unique_ips)
            );
        }
        
        // 按天统计访问趋势
        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN is_spider = 1 THEN 1 ELSE 0 END) as spiders,
                SUM(CASE WHEN is_spider = 0 THEN 1 ELSE 0 END) as users,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(DISTINCT post_id) as unique_posts
            FROM `$table_name`
            WHERE created_at >= %s AND created_at <= %s
            GROUP BY date
            ORDER BY date",
            $period_start, $period_end
        ));
        
        foreach ($daily as $row) {
            $data['daily_trends'][] = array(
                'date' => $row->date,
                'total' => intval($row->total),
                'spiders' => intval($row->spiders),
                'users' => intval($row->users),
                'unique_ips' => intval($row->unique_ips),
                'unique_posts' => intval($row->unique_posts)
            );
        }
        
        // 最受欢迎的文章
        $top_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                post_id,
                COUNT(*) as views,
                COUNT(DISTINCT ip_address) as unique_visitors
            FROM `$table_name`
            WHERE post_id IS NOT NULL 
            AND created_at >= %s AND created_at <= %s
            GROUP BY post_id
            ORDER BY views DESC
            LIMIT 20",
            $period_start, $period_end
        ));
        
        foreach ($top_posts as $row) {
            $post = get_post($row->post_id);
            if ($post) {
                $data['top_posts'][] = array(
                    'post_id' => intval($row->post_id),
                    'title' => $post->post_title,
                    'url' => get_permalink($row->post_id),
                    'views' => intval($row->views),
                    'unique_visitors' => intval($row->unique_visitors)
                );
            }
        }
        
        // 主要来源网站
        $referrers = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                referer,
                COUNT(*) as count
            FROM `$table_name`
            WHERE referer != '' 
            AND referer IS NOT NULL
            AND created_at >= %s AND created_at <= %s
            GROUP BY referer
            ORDER BY count DESC
            LIMIT 20",
            $period_start, $period_end
        ));
        
        foreach ($referrers as $row) {
            $domain = parse_url($row->referer, PHP_URL_HOST);
            if ($domain) {
                if (!isset($data['top_referrers'][$domain])) {
                    $data['top_referrers'][$domain] = 0;
                }
                $data['top_referrers'][$domain] += intval($row->count);
            }
        }
        
        // 蜘蛛类型分布
        $spiders = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                user_agent,
                COUNT(*) as count,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM `$table_name`
            WHERE is_spider = 1
            AND created_at >= %s AND created_at <= %s
            GROUP BY user_agent
            ORDER BY count DESC
            LIMIT 20",
            $period_start, $period_end
        ));
        
        $security_manager = folio_Security_Protection_Manager::get_instance();
        foreach ($spiders as $row) {
            $spider_info = $security_manager->get_spider_info_by_user_agent($row->user_agent);
            $spider_name = $spider_info ? $spider_info['spider_name'] : '未知蜘蛛';
            
            $data['spider_breakdown'][] = array(
                'name' => $spider_name,
                'user_agent' => substr($row->user_agent, 0, 100),
                'count' => intval($row->count),
                'unique_ips' => intval($row->unique_ips)
            );
        }
        
        // 访问模式分析
        $access_patterns = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                action_type,
                access_result,
                COUNT(*) as count
            FROM `$table_name`
            WHERE created_at >= %s AND created_at <= %s
            GROUP BY action_type, access_result
            ORDER BY count DESC",
            $period_start, $period_end
        ));
        
        foreach ($access_patterns as $row) {
            $data['access_patterns'][] = array(
                'action_type' => $row->action_type,
                'access_result' => $row->access_result,
                'count' => intval($row->count)
            );
        }
        
        // 计算内容访问深度分析（每篇文章的平均访问次数和独立访客比例）
        if (!empty($data['top_posts'])) {
            foreach ($data['top_posts'] as &$post) {
                $post['avg_visits_per_visitor'] = $post['unique_visitors'] > 0 ? round($post['views'] / $post['unique_visitors'], 2) : 0;
                $post['engagement_score'] = min(100, round(($post['avg_visits_per_visitor'] / 5) * 100, 1)); // 5次为满分
            }
            unset($post);
        }
        
        // IP重复访问分析（识别高粘性用户和潜在异常）
        $ip_visit_frequency = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ip_address,
                COUNT(*) as visit_count,
                COUNT(DISTINCT post_id) as post_count,
                COUNT(DISTINCT DATE(created_at)) as day_count
            FROM `$table_name`
            WHERE is_spider = 0
            AND created_at >= %s AND created_at <= %s
            GROUP BY ip_address
            HAVING visit_count >= 5
            ORDER BY visit_count DESC
            LIMIT 50",
            $period_start, $period_end
        ));
        
        $data['ip_visit_analysis'] = array(
            'high_frequency_ips' => array(),
            'loyal_users' => array(),
            'potential_abnormal' => array()
        );
        
        foreach ($ip_visit_frequency as $row) {
            $avg_visits_per_day = $row->day_count > 0 ? round($row->visit_count / $row->day_count, 2) : 0;
            $ip_data = array(
                'ip' => $row->ip_address,
                'total_visits' => intval($row->visit_count),
                'posts_viewed' => intval($row->post_count),
                'active_days' => intval($row->day_count),
                'avg_visits_per_day' => $avg_visits_per_day
            );
            
            // 高粘性用户（访问多天且每篇文章访问较少）
            if ($row->day_count >= 3 && $avg_visits_per_day < 10 && $row->post_count > 5) {
                $data['ip_visit_analysis']['loyal_users'][] = $ip_data;
            }
            // 潜在异常IP（单天访问过多或访问模式异常）
            elseif ($avg_visits_per_day > 50 || ($row->visit_count > 20 && $row->post_count < 3)) {
                $data['ip_visit_analysis']['potential_abnormal'][] = $ip_data;
            } else {
                $data['ip_visit_analysis']['high_frequency_ips'][] = $ip_data;
            }
        }
        
        // 计算周分布（识别每周的高峰日和低谷日）
        $weekly_pattern = array();
        foreach ($data['daily_trends'] ?? array() as $day) {
            $day_of_week = date('w', strtotime($day['date'])); // 0=周日, 6=周六
            if (!isset($weekly_pattern[$day_of_week])) {
                $weekly_pattern[$day_of_week] = array('total' => 0, 'count' => 0);
            }
            $weekly_pattern[$day_of_week]['total'] += $day['total'];
            $weekly_pattern[$day_of_week]['count']++;
        }
        
        $weekday_names = array('周日', '周一', '周二', '周三', '周四', '周五', '周六');
        $data['weekly_pattern'] = array();
        foreach ($weekly_pattern as $day => $stats) {
            $data['weekly_pattern'][] = array(
                'day_name' => $weekday_names[$day],
                'day_of_week' => $day,
                'average_visits' => $stats['count'] > 0 ? round($stats['total'] / $stats['count']) : 0
            );
        }
        usort($data['weekly_pattern'], function($a, $b) {
            return $b['average_visits'] - $a['average_visits'];
        });
        
        return $data;
    }

    /**
     * 生成摘要（增强版，包含更多专业指标）
     */
    private function generate_summary($stats, $detailed_data, $period_start = '', $period_end = '') {
        // 计算报告期间
        $days = 0;
        if (!empty($detailed_data['daily_trends'])) {
            $days = count($detailed_data['daily_trends']);
        } else {
            $days = $period_start && $period_end ? ceil((strtotime($period_end) - strtotime($period_start)) / 86400) : 0;
        }
        
        $summary = array(
            'period' => array(
                'start' => $period_start,
                'end' => $period_end,
                'days' => $days
            ),
            'overview' => array(
                'total_access' => $stats['total_access'] ?? 0,
                'user_access' => $stats['user_access'] ?? 0,
                'spider_access' => $stats['spider_access'] ?? 0,
                'unique_ips' => $stats['unique_ips'] ?? 0,
                'unique_posts' => $stats['unique_posts'] ?? 0,
                'suspicious_activity' => $stats['suspicious_activity'] ?? 0,
                'denied_access' => $stats['denied_access'] ?? 0,
                'logged_in_access' => $stats['logged_in_access'] ?? 0
            ),
            'trends' => array(
                'peak_hour' => null,
                'peak_day' => null,
                'average_daily_access' => 0,
                'growth_rate' => 0,
                'daily_variance' => 0, // 日访问量方差
                'trend_direction' => 'stable' // up/down/stable
            ),
            'metrics' => array(
                'spider_ratio' => 0, // 蜘蛛访问占比
                'user_ratio' => 0, // 用户访问占比
                'avg_visits_per_ip' => 0, // 平均每IP访问次数
                'avg_visits_per_post' => 0, // 平均每篇文章访问次数
                'suspicious_ratio' => 0, // 可疑活动占比
                'denied_ratio' => 0, // 被拒绝访问占比
                'content_concentration' => 0, // 内容集中度（前10文章占比）
                'user_retention_rate' => 0, // 用户留存率（登录用户占比）
                'traffic_quality_score' => 0 // 流量质量评分
            ),
            'top_content' => array_slice($detailed_data['top_posts'] ?? array(), 0, 20),
            'traffic_sources' => array_slice($detailed_data['top_referrers'] ?? array(), 0, 15, true),
            'hourly_distribution' => $this->calculate_hourly_distribution($detailed_data['hourly_trends'] ?? array()),
            'daily_distribution' => $detailed_data['daily_trends'] ?? array()
        );
        
        // 计算平均每日访问量
        if (!empty($detailed_data['daily_trends'])) {
            $total_days = count($detailed_data['daily_trends']);
            $total_access = array_sum(array_column($detailed_data['daily_trends'], 'total'));
            $summary['trends']['average_daily_access'] = $total_days > 0 ? round($total_access / $total_days) : 0;
            
            // 计算日访问量方差（衡量访问稳定性）
            $daily_values = array_column($detailed_data['daily_trends'], 'total');
            if (count($daily_values) > 1) {
                $mean = $summary['trends']['average_daily_access'];
                $variance = 0;
                foreach ($daily_values as $value) {
                    $variance += pow($value - $mean, 2);
                }
                $summary['trends']['daily_variance'] = round($variance / count($daily_values), 2);
            }
            
            // 判断趋势方向
            if ($total_days >= 2) {
                $first_half = array_slice($daily_values, 0, ceil($total_days / 2));
                $second_half = array_slice($daily_values, ceil($total_days / 2));
                $first_avg = array_sum($first_half) / count($first_half);
                $second_avg = array_sum($second_half) / count($second_half);
                if ($second_avg > $first_avg * 1.1) {
                    $summary['trends']['trend_direction'] = 'up';
                } elseif ($second_avg < $first_avg * 0.9) {
                    $summary['trends']['trend_direction'] = 'down';
                }
            }
            
            // 找出峰值日期
            $max_access = 0;
            foreach ($detailed_data['daily_trends'] as $day) {
                if ($day['total'] > $max_access) {
                    $max_access = $day['total'];
                    $summary['trends']['peak_day'] = $day['date'];
                }
            }
        }
        
        // 找出峰值小时
        if (!empty($detailed_data['hourly_trends'])) {
            $max_hourly = 0;
            foreach ($detailed_data['hourly_trends'] as $hour) {
                if ($hour['total'] > $max_hourly) {
                    $max_hourly = $hour['total'];
                    $summary['trends']['peak_hour'] = $hour['hour'];
                }
            }
        }
        
        // 计算专业指标
        $total = $summary['overview']['total_access'];
        if ($total > 0) {
            $summary['metrics']['spider_ratio'] = round(($summary['overview']['spider_access'] / $total) * 100, 2);
            $summary['metrics']['user_ratio'] = round(($summary['overview']['user_access'] / $total) * 100, 2);
            $summary['metrics']['suspicious_ratio'] = round(($summary['overview']['suspicious_activity'] / $total) * 100, 2);
            $summary['metrics']['denied_ratio'] = round(($summary['overview']['denied_access'] / $total) * 100, 2);
        }
        
        if ($summary['overview']['unique_ips'] > 0) {
            $summary['metrics']['avg_visits_per_ip'] = round($summary['overview']['user_access'] / $summary['overview']['unique_ips'], 2);
        }
        
        if ($summary['overview']['unique_posts'] > 0) {
            $summary['metrics']['avg_visits_per_post'] = round($summary['overview']['user_access'] / $summary['overview']['unique_posts'], 2);
        }
        
        if ($summary['overview']['user_access'] > 0) {
            $summary['metrics']['user_retention_rate'] = round(($summary['overview']['logged_in_access'] / $summary['overview']['user_access']) * 100, 2);
        }
        
        // 计算内容集中度（前10文章访问量占总访问量的比例）
        if (!empty($summary['top_content']) && $summary['overview']['user_access'] > 0) {
            $top10_views = array_sum(array_column(array_slice($summary['top_content'], 0, 10), 'views'));
            $summary['metrics']['content_concentration'] = round(($top10_views / $summary['overview']['user_access']) * 100, 2);
        }
        
        // 计算流量质量评分（综合多个指标）
        $quality_score = 100;
        $quality_score -= min($summary['metrics']['suspicious_ratio'] * 2, 30); // 可疑活动扣分
        $quality_score -= min($summary['metrics']['denied_ratio'] * 2, 30); // 被拒绝访问扣分
        if ($summary['metrics']['spider_ratio'] > 50) {
            $quality_score -= 10; // 蜘蛛占比过高扣分
        }
        if ($summary['metrics']['content_concentration'] > 50) {
            $quality_score -= 5; // 内容过度集中扣分
        }
        $summary['metrics']['traffic_quality_score'] = max(0, round($quality_score, 1));
        
        return $summary;
    }

    /**
     * 计算小时分布（用于分析访问时段特征）
     */
    private function calculate_hourly_distribution($hourly_trends) {
        $distribution = array();
        $hourly_sum = array_fill(0, 24, 0);
        $hourly_count = array_fill(0, 24, 0);
        
        foreach ($hourly_trends as $trend) {
            $hour = (int)date('H', strtotime($trend['hour']));
            if ($hour >= 0 && $hour < 24) {
                $hourly_sum[$hour] += $trend['total'];
                $hourly_count[$hour]++;
            }
        }
        
        for ($i = 0; $i < 24; $i++) {
            $distribution[$i] = array(
                'hour' => $i,
                'total' => $hourly_sum[$i],
                'average' => $hourly_count[$i] > 0 ? round($hourly_sum[$i] / $hourly_count[$i], 1) : 0
            );
        }
        
        return $distribution;
    }

    /**
     * 生成AI洞察（使用AI API）
     */
    private function generate_ai_insights($summary_data, $detailed_data, $options = array()) {
        // 使用统一的API管理器
        $api_manager = Folio_AI_API_Manager::get_instance();
        
        if (!$api_manager->has_apis()) {
            // 如果没有配置AI API，使用基础分析
            return array(
                'insights' => $this->generate_basic_insights($summary_data, $detailed_data),
                'recommendations' => $this->generate_basic_recommendations($summary_data, $detailed_data)
            );
        }
        
        // 准备分析数据
        $analysis_text = $this->prepare_data_for_ai($summary_data, $detailed_data);
        
        // 调用AI API（使用API管理器）
        $ai_result = $this->call_ai_api($analysis_text);
        
        // 如果AI调用失败或返回空结果，使用基础分析
        if (empty($ai_result['insights']) && empty($ai_result['recommendations'])) {
            return array(
                'insights' => $this->generate_basic_insights($summary_data, $detailed_data),
                'recommendations' => $this->generate_basic_recommendations($summary_data, $detailed_data)
            );
        }
        
        return $ai_result;
    }

    /**
     * 准备数据供AI分析（增强版，包含更多专业指标）
     */
    private function prepare_data_for_ai($summary_data, $detailed_data) {
        $text = "网站运营数据深度分析报告\n";
        $text .= "报告期间：" . ($summary_data['period']['start'] ?? 'N/A') . " 至 " . ($summary_data['period']['end'] ?? 'N/A') . "\n";
        $text .= "分析天数：" . ($summary_data['period']['days'] ?? 0) . " 天\n\n";
        
        $text .= "【一、核心访问指标】\n";
        $text .= "1. 总体流量：\n";
        $text .= "   - 总访问量：{$summary_data['overview']['total_access']} 次\n";
        $text .= "   - 用户访问：{$summary_data['overview']['user_access']} 次（占比 {$summary_data['metrics']['user_ratio']}%）\n";
        $text .= "   - 蜘蛛访问：{$summary_data['overview']['spider_access']} 次（占比 {$summary_data['metrics']['spider_ratio']}%）\n";
        $text .= "   - 登录用户访问：{$summary_data['overview']['logged_in_access']} 次\n\n";
        
        $text .= "2. 用户质量指标：\n";
        $text .= "   - 独立IP数：{$summary_data['overview']['unique_ips']}\n";
        $text .= "   - 平均每IP访问次数：{$summary_data['metrics']['avg_visits_per_ip']} 次\n";
        $text .= "   - 用户留存率：{$summary_data['metrics']['user_retention_rate']}%\n";
        $text .= "   - 流量质量评分：{$summary_data['metrics']['traffic_quality_score']}/100\n\n";
        
        $text .= "3. 内容表现指标：\n";
        $text .= "   - 被访问文章数：{$summary_data['overview']['unique_posts']} 篇\n";
        $text .= "   - 平均每篇文章访问次数：{$summary_data['metrics']['avg_visits_per_post']} 次\n";
        $text .= "   - 内容集中度（Top 10占比）：{$summary_data['metrics']['content_concentration']}%\n";
        if ($summary_data['metrics']['content_concentration'] > 50) {
            $text .= "   ⚠️ 内容集中度过高，存在内容依赖风险\n";
        }
        $text .= "\n";
        
        $text .= "4. 安全风险指标：\n";
        $text .= "   - 可疑活动：{$summary_data['overview']['suspicious_activity']} 次（占比 {$summary_data['metrics']['suspicious_ratio']}%）\n";
        $text .= "   - 被拒绝访问：{$summary_data['overview']['denied_access']} 次（占比 {$summary_data['metrics']['denied_ratio']}%）\n";
        if ($summary_data['metrics']['suspicious_ratio'] > 10 || $summary_data['metrics']['denied_ratio'] > 10) {
            $text .= "   ⚠️ 安全风险较高，需要加强防护措施\n";
        }
        $text .= "\n";
        
        $text .= "【二、访问趋势分析】\n";
        $text .= "1. 时间分布特征：\n";
        $text .= "   - 平均每日访问量：{$summary_data['trends']['average_daily_access']} 次\n";
        $text .= "   - 日访问量方差：{$summary_data['trends']['daily_variance']}（数值越大表示波动越大）\n";
        $text .= "   - 趋势方向：" . ($summary_data['trends']['trend_direction'] === 'up' ? '📈 上升趋势' : ($summary_data['trends']['trend_direction'] === 'down' ? '📉 下降趋势' : '➡️ 稳定')) . "\n";
        if ($summary_data['trends']['peak_day']) {
            $text .= "   - 访问峰值日期：{$summary_data['trends']['peak_day']}\n";
        }
        if ($summary_data['trends']['peak_hour']) {
            $text .= "   - 访问峰值时段：{$summary_data['trends']['peak_hour']}\n";
        }
        $text .= "\n";
        
        $text .= "2. 小时分布特征（24小时访问模式）：\n";
        if (!empty($summary_data['hourly_distribution'])) {
            $peak_hours = array();
            $avg_hourly = array_sum(array_column($summary_data['hourly_distribution'], 'average')) / 24;
            foreach ($summary_data['hourly_distribution'] as $hour_data) {
                if ($hour_data['average'] > $avg_hourly * 1.5) {
                    $peak_hours[] = $hour_data['hour'] . '点';
                }
            }
            if (!empty($peak_hours)) {
                $text .= "   - 访问高峰时段：" . implode('、', array_slice($peak_hours, 0, 5)) . "\n";
            }
            $low_hours = array();
            foreach ($summary_data['hourly_distribution'] as $hour_data) {
                if ($hour_data['average'] < $avg_hourly * 0.5 && $hour_data['average'] > 0) {
                    $low_hours[] = $hour_data['hour'] . '点';
                }
            }
            if (!empty($low_hours)) {
                $text .= "   - 访问低谷时段：" . implode('、', array_slice($low_hours, 0, 5)) . "\n";
            }
        }
        $text .= "\n";
        
        $text .= "【三、内容表现分析】\n";
        $text .= "Top 10 热门内容及其表现：\n";
        foreach (array_slice($summary_data['top_content'] ?? array(), 0, 10) as $index => $post) {
            $engagement_rate = $post['unique_visitors'] > 0 ? round(($post['views'] / $post['unique_visitors']), 2) : 0;
            $text .= ($index + 1) . ". {$post['title']}\n";
            $text .= "   - 总访问：{$post['views']} 次\n";
            $text .= "   - 独立访客：{$post['unique_visitors']} 人\n";
            $text .= "   - 访问深度（人均访问）：{$engagement_rate} 次/人\n";
            if ($engagement_rate > 3) {
                $text .= "   ⭐ 用户粘性较高\n";
            }
        }
        $text .= "\n";
        
        $text .= "【四、流量来源分析】\n";
        if (!empty($summary_data['traffic_sources'])) {
            $text .= "主要流量来源（Top 5）：\n";
            $top_sources = array_slice($summary_data['traffic_sources'], 0, 5, true);
            foreach ($top_sources as $domain => $count) {
                $percentage = $summary_data['overview']['total_access'] > 0 ? round(($count / $summary_data['overview']['total_access']) * 100, 2) : 0;
                $text .= "   - {$domain}：{$count} 次（{$percentage}%）\n";
            }
        } else {
            $text .= "   - 直接访问或来源信息不足\n";
        }
        $text .= "\n";
        
        $text .= "【五、搜索引擎表现】\n";
        if (!empty($detailed_data['spider_breakdown'])) {
            $text .= "蜘蛛访问分布：\n";
            foreach (array_slice($detailed_data['spider_breakdown'], 0, 10) as $spider) {
                $spider_ratio = $summary_data['overview']['spider_access'] > 0 ? round(($spider['count'] / $summary_data['overview']['spider_access']) * 100, 2) : 0;
                $text .= "   - {$spider['name']}：{$spider['count']} 次（占比 {$spider_ratio}%），独立IP：{$spider['unique_ips']}\n";
            }
            $unknown_count = 0;
            foreach ($detailed_data['spider_breakdown'] as $spider) {
                if ($spider['name'] === '未知蜘蛛') {
                    $unknown_count += $spider['count'];
                }
            }
            if ($unknown_count > 0) {
                $unknown_ratio = $summary_data['overview']['spider_access'] > 0 ? round(($unknown_count / $summary_data['overview']['spider_access']) * 100, 2) : 0;
                $text .= "   ⚠️ 未知蜘蛛占比 {$unknown_ratio}%，可能存在异常爬虫\n";
            }
        }
        $text .= "\n";
        
        $text .= "【六、关键问题识别】\n";
        $issues = array();
        if ($summary_data['metrics']['suspicious_ratio'] > 10) {
            $issues[] = "可疑活动比例过高（{$summary_data['metrics']['suspicious_ratio']}%），存在安全风险";
        }
        if ($summary_data['metrics']['denied_ratio'] > 10) {
            $issues[] = "被拒绝访问比例过高（{$summary_data['metrics']['denied_ratio']}%），可能影响用户体验";
        }
        if ($summary_data['metrics']['content_concentration'] > 50) {
            $issues[] = "内容集中度过高（{$summary_data['metrics']['content_concentration']}%），过度依赖少数热门内容";
        }
        if ($summary_data['metrics']['traffic_quality_score'] < 70) {
            $issues[] = "流量质量评分偏低（{$summary_data['metrics']['traffic_quality_score']}/100），需要优化流量质量";
        }
        if ($summary_data['metrics']['user_retention_rate'] < 10 && $summary_data['overview']['user_access'] > 100) {
            $issues[] = "用户留存率较低（{$summary_data['metrics']['user_retention_rate']}%），用户粘性有待提升";
        }
        if ($summary_data['trends']['trend_direction'] === 'down') {
            $issues[] = "访问量呈下降趋势，需要采取优化措施";
        }
        if (empty($issues)) {
            $text .= "   暂无发现明显问题，整体运营状况良好。\n";
        } else {
            foreach ($issues as $issue) {
                $text .= "   ⚠️ {$issue}\n";
            }
        }
        $text .= "\n";
        
        $text .= "【七、运营亮点】\n";
        $highlights = array();
        if ($summary_data['metrics']['user_ratio'] > 70) {
            $highlights[] = "用户访问占比高（{$summary_data['metrics']['user_ratio']}%），真实用户流量良好";
        }
        if ($summary_data['metrics']['avg_visits_per_ip'] > 2) {
            $highlights[] = "用户粘性较好，平均每IP访问 {$summary_data['metrics']['avg_visits_per_ip']} 次";
        }
        if ($summary_data['trends']['trend_direction'] === 'up') {
            $highlights[] = "访问量呈现上升趋势，增长态势良好";
        }
        if ($summary_data['metrics']['traffic_quality_score'] > 80) {
            $highlights[] = "流量质量评分优秀（{$summary_data['metrics']['traffic_quality_score']}/100）";
        }
        if (!empty($highlights)) {
            foreach ($highlights as $highlight) {
                $text .= "   ✅ {$highlight}\n";
            }
        } else {
            $text .= "   需要进一步数据分析以识别运营亮点。\n";
        }
        
        return $text;
    }

    /**
     * 调用AI API（使用API管理器，支持轮询）
     */
    private function call_ai_api($analysis_text) {
        $prompt = "你是一位资深的网站运营分析专家，拥有丰富的数字营销、内容运营和数据分析经验。\n\n";
        $prompt .= "请基于以下详细的网站运营数据，进行深度分析和专业解读：\n\n";
        $prompt .= $analysis_text;
        $prompt .= "\n\n";
        $prompt .= "【分析要求】\n";
        $prompt .= "请从以下多个维度进行专业分析，并给出可执行的优化建议：\n\n";
        $prompt .= "1. **流量质量分析**（insights，5-7条）\n";
        $prompt .= "   - 分析用户访问和蜘蛛访问的占比是否合理\n";
        $prompt .= "   - 评估流量质量评分，识别优质流量和低质流量\n";
        $prompt .= "   - 分析平均每IP访问次数、用户留存率等关键指标\n";
        $prompt .= "   - 识别流量趋势，判断增长、下降或稳定的原因\n";
        $prompt .= "   - 分析访问时段分布特征，识别用户行为模式\n\n";
        $prompt .= "2. **内容运营分析**（insights，4-6条）\n";
        $prompt .= "   - 分析热门内容的特征和成功因素\n";
        $prompt .= "   - 评估内容集中度，判断是否存在内容依赖风险\n";
        $prompt .= "   - 分析用户对不同内容的访问深度和粘性\n";
        $prompt .= "   - 识别内容差距和优化机会\n\n";
        $prompt .= "3. **SEO和搜索引擎表现**（insights，3-4条）\n";
        $prompt .= "   - 分析搜索引擎爬虫的访问情况和分布\n";
        $prompt .= "   - 识别未知蜘蛛，评估是否存在异常爬虫\n";
        $prompt .= "   - 评估SEO优化空间和机会\n\n";
        $prompt .= "4. **安全风险评估**（insights，2-3条）\n";
        $prompt .= "   - 分析可疑活动和被拒绝访问的情况\n";
        $prompt .= "   - 评估安全风险等级和应对策略\n\n";
        $prompt .= "5. **战略优化建议**（recommendations，8-10条）\n";
        $prompt .= "   - 基于数据洞察，提出可执行的运营优化建议\n";
        $prompt .= "   - 建议应具体、可量化，包含优先级排序\n";
        $prompt .= "   - 涵盖内容策略、用户增长、SEO优化、安全防护等方面\n";
        $prompt .= "   - 针对发现的问题提出解决方案\n\n";
        $prompt .= "【输出格式要求】\n";
        $prompt .= "请严格按照以下JSON格式返回，确保每个字段都是数组：\n";
        $prompt .= "{\n";
        $prompt .= "  \"insights\": [\n";
        $prompt .= "    \"【流量质量】第一条洞察...\",\n";
        $prompt .= "    \"【内容运营】第二条洞察...\",\n";
        $prompt .= "    \"...\"\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"recommendations\": [\n";
        $prompt .= "    \"【高优先级】第一条建议...\",\n";
        $prompt .= "    \"【中优先级】第二条建议...\",\n";
        $prompt .= "    \"...\"\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";
        $prompt .= "注意：\n";
        $prompt .= "- 洞察要深入、专业，避免泛泛而谈\n";
        $prompt .= "- 建议要具体、可执行，避免空泛的口号\n";
        $prompt .= "- 用数据支撑观点，引用具体的数值和比例\n";
        $prompt .= "- 语言要专业但易懂，适合运营人员阅读\n";
        $prompt .= "- 确保返回的是有效的JSON格式，可以直接解析\n";
        
        // 使用API管理器调用（支持轮询）
        $api_manager = Folio_AI_API_Manager::get_instance();
        $content = $api_manager->call_api($prompt, array(
            'temperature' => 0.7,
            'max_tokens' => 3000, // 运营报告分析需要更多token
            'timeout' => 60, // 运营报告分析可能需要更长时间
        ));
        
        if (!$content) {
            return array(
                'insights' => array(),
                'recommendations' => array()
            );
        }
        
        // 尝试提取JSON
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json_data = json_decode($matches[0], true);
            if ($json_data && isset($json_data['insights']) && isset($json_data['recommendations'])) {
                return array(
                    'insights' => array_filter((array)$json_data['insights']), // 过滤空值
                    'recommendations' => array_filter((array)$json_data['recommendations'])
                );
            }
        }
        
        // 如果无法解析JSON，尝试直接使用内容（某些AI可能不严格遵循JSON格式）
        error_log('Folio Operations Report: AI response format unexpected, using basic analysis instead');
        
        // 如果解析失败，返回空数组（由上层调用基础分析）
        return array(
            'insights' => array(),
            'recommendations' => array()
        );
    }

    /**
     * 生成基础洞察（不使用AI）
     */
    private function generate_basic_insights($summary_data, $detailed_data) {
        $insights = array();
        
        if (!empty($summary_data['overview'])) {
            $total = $summary_data['overview']['total_access'] ?? 0;
            $spiders = $summary_data['overview']['spider_access'] ?? 0;
            $users = $summary_data['overview']['user_access'] ?? 0;
            
            if ($total > 0) {
                $spider_ratio = round(($spiders / $total) * 100, 1);
                $user_ratio = round(($users / $total) * 100, 1);
                
                $insights[] = "总访问量 {$total} 次，其中用户访问占 {$user_ratio}%，蜘蛛访问占 {$spider_ratio}%";
                
                if ($spider_ratio > 50) {
                    $insights[] = "蜘蛛访问比例较高，表明搜索引擎对网站关注度较高";
                } else {
                    $insights[] = "用户访问比例较高，网站真实用户流量表现良好";
                }
            }
            
            $unique_ips = $summary_data['overview']['unique_ips'] ?? 0;
            if ($unique_ips > 0 && $users > 0) {
                $avg_visits_per_ip = round($users / $unique_ips, 1);
                $insights[] = "平均每个独立IP产生 {$avg_visits_per_ip} 次访问";
            }
            
            if (!empty($summary_data['trends']['peak_day'])) {
                $insights[] = "访问峰值出现在 {$summary_data['trends']['peak_day']}，访问量达到峰值";
            }
            
            if (!empty($detailed_data['top_posts'])) {
                $top_post = $detailed_data['top_posts'][0];
                $insights[] = "最受欢迎内容：{$top_post['title']}，共获得 {$top_post['views']} 次访问";
            }
        }
        
        return $insights;
    }

    /**
     * 生成基础建议
     */
    private function generate_basic_recommendations($summary_data, $detailed_data) {
        $recommendations = array();
        
        if (!empty($summary_data['overview'])) {
            $suspicious = $summary_data['overview']['suspicious_activity'] ?? 0;
            $total = $summary_data['overview']['total_access'] ?? 0;
            
            if ($suspicious > 0 && $total > 0) {
                $suspicious_ratio = round(($suspicious / $total) * 100, 2);
                if ($suspicious_ratio > 5) {
                    $recommendations[] = "可疑活动比例较高（{$suspicious_ratio}%），建议加强安全防护措施";
                }
            }
            
            if (!empty($summary_data['top_content'])) {
                $recommendations[] = "针对热门内容进行SEO优化，提升搜索排名和曝光度";
            }
            
            if (!empty($detailed_data['spider_breakdown'])) {
                $recommendations[] = "继续保持与搜索引擎的良好关系，定期更新sitemap和robots.txt";
            }
            
            if (!empty($summary_data['trends']['average_daily_access'])) {
                $avg = $summary_data['trends']['average_daily_access'];
                $recommendations[] = "当前平均每日访问量 {$avg}，建议通过内容营销和社交媒体推广提升流量";
            }
        }
        
        return $recommendations;
    }

    /**
     * 获取报告
     */
    public function get_report($report_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REPORT_TABLE;
        
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$table_name` WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        if ($report) {
            $report['summary_data'] = json_decode($report['summary_data'], true);
            $report['detailed_data'] = json_decode($report['detailed_data'], true);
            $report['ai_insights'] = json_decode($report['ai_insights'], true);
            $report['recommendations'] = json_decode($report['recommendations'], true);
            $report['comparison_data'] = json_decode($report['comparison_data'], true);
        }
        
        return $report;
    }

    /**
     * 获取报告列表
     */
    public function get_reports($limit = 50, $offset = 0, $filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REPORT_TABLE;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($filters['report_type'])) {
            $where_conditions[] = 'report_type = %s';
            $where_values[] = $filters['report_type'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT * FROM `$table_name` WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, array_merge($where_values, array($limit, $offset)));
        } else {
            $sql = $wpdb->prepare($sql, $limit, $offset);
        }
        
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * 对比报告
     */
    public function compare_reports($report_id1, $report_id2) {
        $report1 = $this->get_report($report_id1);
        $report2 = $this->get_report($report_id2);
        
        if (!$report1 || !$report2) {
            return false;
        }
        
        $comparison = array(
            'report1' => array(
                'id' => $report1['id'],
                'name' => $report1['report_name'],
                'period' => array(
                    'start' => $report1['period_start'],
                    'end' => $report1['period_end']
                )
            ),
            'report2' => array(
                'id' => $report2['id'],
                'name' => $report2['report_name'],
                'period' => array(
                    'start' => $report2['period_start'],
                    'end' => $report2['period_end']
                )
            ),
            'metrics' => array()
        );
        
        $summary1 = $report1['summary_data'] ?? array();
        $summary2 = $report2['summary_data'] ?? array();
        
        $metrics = array('total_access', 'user_access', 'spider_access', 'unique_ips', 'unique_posts', 'suspicious_activity', 'denied_access');
        
        foreach ($metrics as $metric) {
            $value1 = $summary1['overview'][$metric] ?? 0;
            $value2 = $summary2['overview'][$metric] ?? 0;
            
            $change = $value2 - $value1;
            $change_percent = $value1 > 0 ? round(($change / $value1) * 100, 2) : 0;
            
            $comparison['metrics'][$metric] = array(
                'report1' => $value1,
                'report2' => $value2,
                'change' => $change,
                'change_percent' => $change_percent,
                'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable')
            );
        }
        
        // 计算趋势分析
        $comparison['trend_analysis'] = $this->analyze_trends($summary1, $summary2);
        
        return $comparison;
    }

    /**
     * 分析趋势
     */
    private function analyze_trends($summary1, $summary2) {
        $analysis = array();
        
        $total1 = $summary1['overview']['total_access'] ?? 0;
        $total2 = $summary2['overview']['total_access'] ?? 0;
        
        if ($total1 > 0) {
            $growth = round((($total2 - $total1) / $total1) * 100, 2);
            
            if ($growth > 10) {
                $analysis[] = "访问量大幅增长 {$growth}%，网站表现优秀";
            } elseif ($growth > 0) {
                $analysis[] = "访问量增长 {$growth}%，呈现良好发展趋势";
            } elseif ($growth > -10) {
                $analysis[] = "访问量下降 " . abs($growth) . "%，需要关注并采取优化措施";
            } else {
                $analysis[] = "访问量大幅下降 " . abs($growth) . "%，需要紧急优化";
            }
        }
        
        return $analysis;
    }

    /**
     * 删除报告
     */
    public function delete_report($report_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REPORT_TABLE;
        
        return $wpdb->delete($table_name, array('id' => $report_id), array('%d'));
    }
}
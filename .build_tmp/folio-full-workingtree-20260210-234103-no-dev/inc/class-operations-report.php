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
                'operations_report_%s_to_%s',
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
            $spider_name = $spider_info ? $spider_info['spider_name'] : 'Unknown Spider';
            
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
            $day_of_week = date('w', strtotime($day['date'])); // 0=Sun, 6=Sat
            if (!isset($weekly_pattern[$day_of_week])) {
                $weekly_pattern[$day_of_week] = array('total' => 0, 'count' => 0);
            }
            $weekly_pattern[$day_of_week]['total'] += $day['total'];
            $weekly_pattern[$day_of_week]['count']++;
        }
        
        $weekday_names = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
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
        $text = "In-depth Website Operations Analysis Report\n";
        $text .= "Report Period: " . ($summary_data['period']['start'] ?? 'N/A') . " to " . ($summary_data['period']['end'] ?? 'N/A') . "\n";
        $text .= "Analysis Days: " . ($summary_data['period']['days'] ?? 0) . " days\n\n";
        
        $text .= "[1. Core Access Metrics]\n";
        $text .= "1. Overall Traffic:\n";
        $text .= "   - Total Visits: {$summary_data['overview']['total_access']} visits\n";
        $text .= "   - User Visits: {$summary_data['overview']['user_access']} visits (Ratio: {$summary_data['metrics']['user_ratio']}%)\n";
        $text .= "   - Crawler Visits: {$summary_data['overview']['spider_access']} visits (Ratio: {$summary_data['metrics']['spider_ratio']}%)\n";
        $text .= "   - Logged-in User Visits: {$summary_data['overview']['logged_in_access']} visits\n\n";
        
        $text .= "2. User Quality Metrics:\n";
        $text .= "   - Unique IPs: {$summary_data['overview']['unique_ips']}\n";
        $text .= "   - Avg Visits per IP: {$summary_data['metrics']['avg_visits_per_ip']} visits\n";
        $text .= "   - User Retention Rate: {$summary_data['metrics']['user_retention_rate']}%\n";
        $text .= "   - Traffic Quality Score: {$summary_data['metrics']['traffic_quality_score']}/100\n\n";
        
        $text .= "3. Content Performance Metrics:\n";
        $text .= "   - Accessed Posts: {$summary_data['overview']['unique_posts']} posts\n";
        $text .= "   - Avg Visits per Post: {$summary_data['metrics']['avg_visits_per_post']} visits\n";
        $text .= "   - Content Concentration (Top 10 share): {$summary_data['metrics']['content_concentration']}%\n";
        if ($summary_data['metrics']['content_concentration'] > 50) {
            $text .= "   Warning: Content concentration is high. There may be content dependency risk.\n";
        }
        $text .= "\n";
        
        $text .= "4. Security Risk Metrics:\n";
        $text .= "   - Suspicious Activities: {$summary_data['overview']['suspicious_activity']} visits (Ratio: {$summary_data['metrics']['suspicious_ratio']}%)\n";
        $text .= "   - Denied Accesses: {$summary_data['overview']['denied_access']} visits (Ratio: {$summary_data['metrics']['denied_ratio']}%)\n";
        if ($summary_data['metrics']['suspicious_ratio'] > 10 || $summary_data['metrics']['denied_ratio'] > 10) {
            $text .= "   Warning: Security risk is high. Stronger protection is recommended.\n";
        }
        $text .= "\n";
        
        $text .= "[2. Access Trend Analysis]\n";
        $text .= "1. Time Distribution:\n";
        $text .= "   - Average Daily Visits: {$summary_data['trends']['average_daily_access']} visits\n";
        $text .= "   - Daily Visit Variance: {$summary_data['trends']['daily_variance']} (higher value means larger fluctuation)\n";
        $text .= "   - Trend Direction: " . ($summary_data['trends']['trend_direction'] === 'up' ? 'Rising' : ($summary_data['trends']['trend_direction'] === 'down' ? 'Falling' : 'Stable')) . "\n";
        if ($summary_data['trends']['peak_day']) {
            $text .= "   - Peak Date: {$summary_data['trends']['peak_day']}\n";
        }
        if ($summary_data['trends']['peak_hour']) {
            $text .= "   - Peak Hour: {$summary_data['trends']['peak_hour']}\n";
        }
        $text .= "\n";
        
        $text .= "2. Hourly Distribution (24h pattern):\n";
        if (!empty($summary_data['hourly_distribution'])) {
            $peak_hours = array();
            $avg_hourly = array_sum(array_column($summary_data['hourly_distribution'], 'average')) / 24;
            foreach ($summary_data['hourly_distribution'] as $hour_data) {
                if ($hour_data['average'] > $avg_hourly * 1.5) {
                    $peak_hours[] = $hour_data['hour'] . ':00';
                }
            }
            if (!empty($peak_hours)) {
                $text .= "   - Peak Hours: " . implode(', ', array_slice($peak_hours, 0, 5)) . "\n";
            }
            $low_hours = array();
            foreach ($summary_data['hourly_distribution'] as $hour_data) {
                if ($hour_data['average'] < $avg_hourly * 0.5 && $hour_data['average'] > 0) {
                    $low_hours[] = $hour_data['hour'] . ':00';
                }
            }
            if (!empty($low_hours)) {
                $text .= "   - Low Hours: " . implode(', ', array_slice($low_hours, 0, 5)) . "\n";
            }
        }
        $text .= "\n";
        
        $text .= "[3. Content Performance Analysis]\n";
        $text .= "Top 10 Popular Content and Performance:\n";
        foreach (array_slice($summary_data['top_content'] ?? array(), 0, 10) as $index => $post) {
            $engagement_rate = $post['unique_visitors'] > 0 ? round(($post['views'] / $post['unique_visitors']), 2) : 0;
            $text .= ($index + 1) . ". {$post['title']}\n";
            $text .= "   - Total Visits: {$post['views']} visits\n";
            $text .= "   - Unique Visitors: {$post['unique_visitors']} users\n";
            $text .= "   - Engagement (visits per visitor): {$engagement_rate} visits/user\n";
            if ($engagement_rate > 3) {
                $text .= "   Strong user engagement\n";
            }
        }
        $text .= "\n";
        
        $text .= "[4. Traffic Source Analysis]\n";
        if (!empty($summary_data['traffic_sources'])) {
            $text .= "Top Traffic Sources (Top 5):\n";
            $top_sources = array_slice($summary_data['traffic_sources'], 0, 5, true);
            foreach ($top_sources as $domain => $count) {
                $percentage = $summary_data['overview']['total_access'] > 0 ? round(($count / $summary_data['overview']['total_access']) * 100, 2) : 0;
                $text .= "   - {$domain}：{$count} visits（{$percentage}%)\n";
            }
        } else {
            $text .= "   - Direct access or insufficient referrer data\n";
        }
        $text .= "\n";
        
        $text .= "[5. Search Engine Performance]\n";
        if (!empty($detailed_data['spider_breakdown'])) {
            $text .= "Crawler Access Distribution:\n";
            foreach (array_slice($detailed_data['spider_breakdown'], 0, 10) as $spider) {
                $spider_ratio = $summary_data['overview']['spider_access'] > 0 ? round(($spider['count'] / $summary_data['overview']['spider_access']) * 100, 2) : 0;
                $text .= "   - {$spider['name']}：{$spider['count']} visits (Ratio: {$spider_ratio}%), Unique IPs: {$spider['unique_ips']}\n";
            }
            $unknown_count = 0;
            foreach ($detailed_data['spider_breakdown'] as $spider) {
                if ($spider['name'] === 'Unknown Spider') {
                    $unknown_count += $spider['count'];
                }
            }
            if ($unknown_count > 0) {
                $unknown_ratio = $summary_data['overview']['spider_access'] > 0 ? round(($unknown_count / $summary_data['overview']['spider_access']) * 100, 2) : 0;
                $text .= "   Warning: Unknown crawler share {$unknown_ratio}%, possible abnormal crawler activity\n";
            }
        }
        $text .= "\n";
        
        $text .= "[6. Key Issues]\n";
        $issues = array();
        if ($summary_data['metrics']['suspicious_ratio'] > 10) {
            $issues[] = "Suspicious activity ratio is high ({$summary_data['metrics']['suspicious_ratio']}%), indicating security risk";
        }
        if ($summary_data['metrics']['denied_ratio'] > 10) {
            $issues[] = "Denied access ratio is high ({$summary_data['metrics']['denied_ratio']}%), which may impact user experience";
        }
        if ($summary_data['metrics']['content_concentration'] > 50) {
            $issues[] = "Content concentration is high ({$summary_data['metrics']['content_concentration']}%), over-reliance on a few popular posts";
        }
        if ($summary_data['metrics']['traffic_quality_score'] < 70) {
            $issues[] = "Traffic quality score is low ({$summary_data['metrics']['traffic_quality_score']}/100), traffic quality optimization is needed";
        }
        if ($summary_data['metrics']['user_retention_rate'] < 10 && $summary_data['overview']['user_access'] > 100) {
            $issues[] = "User retention rate is low ({$summary_data['metrics']['user_retention_rate']}%), user engagement needs improvement";
        }
        if ($summary_data['trends']['trend_direction'] === 'down') {
            $issues[] = "Traffic is trending down and requires optimization measures";
        }
        if (empty($issues)) {
            $text .= "   No major issues found. Overall operations are healthy.\n";
        } else {
            foreach ($issues as $issue) {
                $text .= "   ⚠️ {$issue}\n";
            }
        }
        $text .= "\n";
        
        $text .= "[7. Operational Highlights]\n";
        $highlights = array();
        if ($summary_data['metrics']['user_ratio'] > 70) {
            $highlights[] = "High user visit ratio ({$summary_data['metrics']['user_ratio']}%), indicating quality real-user traffic";
        }
        if ($summary_data['metrics']['avg_visits_per_ip'] > 2) {
            $highlights[] = "Good user engagement, average visits per IP: {$summary_data['metrics']['avg_visits_per_ip']} visits";
        }
        if ($summary_data['trends']['trend_direction'] === 'up') {
            $highlights[] = "Traffic is rising with healthy growth momentum";
        }
        if ($summary_data['metrics']['traffic_quality_score'] > 80) {
            $highlights[] = "Excellent traffic quality score ({$summary_data['metrics']['traffic_quality_score']}/100）";
        }
        if (!empty($highlights)) {
            foreach ($highlights as $highlight) {
                $text .= "   ✅ {$highlight}\n";
            }
        } else {
            $text .= "   Further analysis is needed to identify more operational highlights.\n";
        }
        
        return $text;
    }

    /**
     * 调用AI API（使用API管理器，支持轮询）
     */
    private function call_ai_api($analysis_text) {
        $prompt = "You are a senior website operations analyst with strong expertise in digital marketing, content strategy, and data analysis.\n\n";
        $prompt .= "Please analyze the following website operations data and provide a deep, professional interpretation:\n\n";
        $prompt .= $analysis_text;
        $prompt .= "\n\n";
        $prompt .= "[Analysis Requirements]\n";
        $prompt .= "Provide actionable analysis from the dimensions below:\n\n";
        $prompt .= "1. Traffic quality analysis (insights, 5-7 items)\n";
        $prompt .= "2. Content operations analysis (insights, 4-6 items)\n";
        $prompt .= "3. SEO and search engine performance (insights, 3-4 items)\n";
        $prompt .= "4. Security risk assessment (insights, 2-3 items)\n";
        $prompt .= "5. Strategic optimization suggestions (recommendations, 8-10 items)\n\n";
        $prompt .= "[Output Format]\n";
        $prompt .= "Return strict JSON in the format below. Both fields must be arrays:\n";
        $prompt .= "{\n";
        $prompt .= "  \"insights\": [\"...\"],\n";
        $prompt .= "  \"recommendations\": [\"...\"]\n";
        $prompt .= "}\n\n";
        $prompt .= "Notes:\n";
        $prompt .= "- Insights should be data-driven and specific.\n";
        $prompt .= "- Recommendations should be actionable and prioritized.\n";
        $prompt .= "- Ensure output is valid JSON that can be parsed directly.\n";
        
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
                
                $insights[] = "Total visits: {$total} visits, user visits ratio: {$user_ratio}%, crawler visits ratio: {$spider_ratio}%";
                
                if ($spider_ratio > 50) {
                    $insights[] = "Crawler ratio is high, indicating strong search engine attention";
                } else {
                    $insights[] = "User visit ratio is high, indicating strong real-user traffic";
                }
            }
            
            $unique_ips = $summary_data['overview']['unique_ips'] ?? 0;
            if ($unique_ips > 0 && $users > 0) {
                $avg_visits_per_ip = round($users / $unique_ips, 1);
                $insights[] = "Average visits per unique IP: {$avg_visits_per_ip} visits";
            }
            
            if (!empty($summary_data['trends']['peak_day'])) {
                $insights[] = "Peak traffic occurred on {$summary_data['trends']['peak_day']}, reaching the highest volume";
            }
            
            if (!empty($detailed_data['top_posts'])) {
                $top_post = $detailed_data['top_posts'][0];
                $insights[] = "Most popular content: {$top_post['title']}, with {$top_post['views']} visits";
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
                    $recommendations[] = "Suspicious activity ratio is high ({$suspicious_ratio}%), strengthen security protection measures";
                }
            }
            
            if (!empty($summary_data['top_content'])) {
                $recommendations[] = "Optimize SEO for popular content to improve rankings and visibility";
            }
            
            if (!empty($detailed_data['spider_breakdown'])) {
                $recommendations[] = "Maintain good relationships with search engines and regularly update sitemap and robots.txt";
            }
            
            if (!empty($summary_data['trends']['average_daily_access'])) {
                $avg = $summary_data['trends']['average_daily_access'];
                $recommendations[] = "Current average daily visits: {$avg}, increase traffic through content marketing and social media promotion";
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
                $analysis[] = "Traffic increased significantly by {$growth}%, site performance is excellent";
            } elseif ($growth > 0) {
                $analysis[] = "Traffic increased by {$growth}%, showing a healthy trend";
            } elseif ($growth > -10) {
                $analysis[] = "Traffic decreased by " . abs($growth) . "%, needs attention and optimization";
            } else {
                $analysis[] = "Traffic dropped sharply by " . abs($growth) . "%, urgent optimization is required";
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

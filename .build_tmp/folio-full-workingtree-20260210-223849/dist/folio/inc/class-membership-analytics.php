<?php
/**
 * Membership Analytics
 * 
 * 会员内容统计分析系统 - 实现访问数据收集、转化率分析和管理报告
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Membership_Analytics {

    // 数据库表名
    private static $table_name = null;
    
    // 缓存键前缀
    const CACHE_PREFIX = 'folio_analytics_';
    const CACHE_EXPIRY = 1800; // 30分钟

    public function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'folio_membership_analytics';
        
        // 创建数据库表
        add_action('init', array($this, 'create_analytics_table'));
        
        // 数据收集钩子
        add_action('wp', array($this, 'track_content_access'));
        add_action('folio_membership_level_changed', array($this, 'track_membership_conversion'), 10, 4);
        
        // 统计页面已并入 folio-membership，保留数据采集与导出能力
        
        // AJAX处理
        add_action('wp_ajax_folio_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_folio_export_analytics', array($this, 'ajax_export_analytics'));
        
        // 定时清理旧数据
        add_action('folio_cleanup_analytics_data', array($this, 'cleanup_old_data'));
        if (!wp_next_scheduled('folio_cleanup_analytics_data')) {
            wp_schedule_event(time(), 'weekly', 'folio_cleanup_analytics_data');
        }
        
        // 每日统计汇总
        add_action('folio_daily_analytics_summary', array($this, 'generate_daily_summary'));
        if (!wp_next_scheduled('folio_daily_analytics_summary')) {
            wp_schedule_event(time(), 'daily', 'folio_daily_analytics_summary');
        }
    }

    /**
     * 创建分析数据表
     */
    public function create_analytics_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            user_level varchar(20) NOT NULL DEFAULT 'free',
            access_type varchar(50) NOT NULL,
            access_result varchar(20) NOT NULL,
            conversion_action varchar(50) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            referrer text DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY user_level (user_level),
            KEY access_type (access_type),
            KEY access_result (access_result),
            KEY created_at (created_at),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // 创建汇总表
        $summary_table = $wpdb->prefix . 'folio_analytics_summary';
        $summary_sql = "CREATE TABLE IF NOT EXISTS $summary_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date_key date NOT NULL,
            post_id bigint(20) DEFAULT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value bigint(20) NOT NULL DEFAULT 0,
            additional_data text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_metric (date_key, post_id, metric_type),
            KEY date_key (date_key),
            KEY post_id (post_id),
            KEY metric_type (metric_type)
        ) $charset_collate;";
        
        dbDelta($summary_sql);
    }

    /**
     * 跟踪内容访问
     */
    public function track_content_access() {
        if (!is_singular('post') || is_admin() || wp_doing_ajax()) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        // 获取文章保护信息
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post_id);
        if (!$protection_info['is_protected']) {
            return; // 只跟踪会员专属文章
        }

        $user_id = get_current_user_id();
        $user_membership = $user_id ? folio_get_user_membership($user_id) : null;
        $user_level = $user_membership ? $user_membership['level'] : 'free';
        
        // 检查访问权限
        $can_access = folio_Article_Protection_Manager::can_user_access($post_id, $user_id);
        
        // 确定访问类型和结果
        $access_type = $this->determine_access_type();
        $access_result = $can_access ? 'granted' : 'denied';
        
        // 获取会话ID
        $session_id = $this->get_or_create_session_id();
        
        // 记录访问数据
        $this->record_access_data(array(
            'post_id' => $post_id,
            'user_id' => $user_id ?: null,
            'user_level' => $user_level,
            'access_type' => $access_type,
            'access_result' => $access_result,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'referrer' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500),
            'session_id' => $session_id,
        ));
        
        // 如果用户无权限访问，可能触发转化跟踪
        if (!$can_access && !$user_id) {
            $this->track_potential_conversion($post_id, $session_id);
        }
    }

    /**
     * 跟踪会员转化
     */
    public function track_membership_conversion($user_id, $new_level, $permanent, $days) {
        if ($new_level === 'free') {
            return; // 不跟踪降级
        }

        // 查找最近的访问记录，看是否有转化路径
        global $wpdb;
        
        $recent_access = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT post_id, session_id FROM " . self::$table_name . " 
             WHERE (user_id = %d OR session_id IN (
                 SELECT DISTINCT session_id FROM " . self::$table_name . " 
                 WHERE user_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
             )) 
             AND access_result = 'denied' 
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY created_at DESC 
             LIMIT 10",
            $user_id, $user_id
        ));

        foreach ($recent_access as $access) {
            $this->record_access_data(array(
                'post_id' => $access->post_id,
                'user_id' => $user_id,
                'user_level' => $new_level,
                'access_type' => 'conversion',
                'access_result' => 'converted',
                'conversion_action' => 'membership_upgrade',
                'session_id' => $access->session_id,
            ));
        }
    }

    /**
     * 记录访问数据
     */
    private function record_access_data($data) {
        global $wpdb;
        
        $wpdb->insert(
            self::$table_name,
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * 获取会员内容统计
     */
    public static function get_membership_content_stats($days = 30) {
        $cache_key = self::CACHE_PREFIX . 'content_stats_' . $days;
        $cached = wp_cache_get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        
        $start_date = date('Y-m-d H:i:s', time() - ($days * 24 * 3600));
        
        // 会员专属文章总数
        $total_protected = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = '_folio_premium_content' 
             AND pm.meta_value = '1' 
             AND p.post_status = 'publish'"
        );
        
        // 按等级分类的文章数量
        $vip_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm1 
             JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
             JOIN {$wpdb->posts} p ON pm1.post_id = p.ID 
             WHERE pm1.meta_key = '_folio_premium_content' AND pm1.meta_value = '1' 
             AND pm2.meta_key = '_folio_required_level' AND pm2.meta_value = 'vip' 
             AND p.post_status = 'publish'"
        );
        
        $svip_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm1 
             JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
             JOIN {$wpdb->posts} p ON pm1.post_id = p.ID 
             WHERE pm1.meta_key = '_folio_premium_content' AND pm1.meta_value = '1' 
             AND pm2.meta_key = '_folio_required_level' AND pm2.meta_value = 'svip' 
             AND p.post_status = 'publish'"
        );

        $stats = array(
            'total_protected_articles' => intval($total_protected),
            'vip_articles' => intval($vip_count),
            'svip_articles' => intval($svip_count),
            'free_articles' => 0, // 将在下面计算
        );
        
        // 免费文章数量
        $total_articles = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
        );
        $stats['free_articles'] = intval($total_articles) - $stats['total_protected_articles'];
        
        // 缓存结果
        wp_cache_set($cache_key, $stats, '', self::CACHE_EXPIRY);
        
        return $stats;
    }

    /**
     * 获取访问统计
     */
    public static function get_access_stats($days = 30) {
        $cache_key = self::CACHE_PREFIX . 'access_stats_' . $days;
        $cached = wp_cache_get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        
        $start_date = date('Y-m-d H:i:s', time() - ($days * 24 * 3600));
        $table_name = self::$table_name;
        
        // 总访问次数
        $total_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at > %s AND access_type != 'conversion'",
            $start_date
        ));
        
        // 会员用户访问
        $member_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at > %s AND user_level IN ('vip', 'svip') AND access_type != 'conversion'",
            $start_date
        ));
        
        // 非会员用户访问
        $non_member_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at > %s AND user_level = 'free' AND access_type != 'conversion'",
            $start_date
        ));
        
        // 被拒绝的访问
        $denied_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at > %s AND access_result = 'denied' AND access_type != 'conversion'",
            $start_date
        ));
        
        // 独立访客数（基于IP和User Agent）
        $unique_visitors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(COALESCE(ip_address, ''), '|', COALESCE(user_agent, ''))) 
             FROM $table_name WHERE created_at > %s AND access_type != 'conversion'",
            $start_date
        ));

        $stats = array(
            'total_access' => intval($total_access),
            'member_access' => intval($member_access),
            'non_member_access' => intval($non_member_access),
            'denied_access' => intval($denied_access),
            'unique_visitors' => intval($unique_visitors),
            'access_granted' => intval($total_access) - intval($denied_access),
        );
        
        // 缓存结果
        wp_cache_set($cache_key, $stats, '', self::CACHE_EXPIRY);
        
        return $stats;
    }

    /**
     * 获取转化率分析
     */
    public static function get_conversion_stats($days = 30) {
        $cache_key = self::CACHE_PREFIX . 'conversion_stats_' . $days;
        $cached = wp_cache_get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        
        $start_date = date('Y-m-d H:i:s', time() - ($days * 24 * 3600));
        $table_name = self::$table_name;
        
        // 转化事件数量
        $conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at > %s AND access_result = 'converted'",
            $start_date
        ));
        
        // 因权限不足离开的用户数（基于会话）
        $bounce_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM $table_name 
             WHERE created_at > %s AND access_result = 'denied' 
             AND session_id NOT IN (
                 SELECT DISTINCT session_id FROM $table_name 
                 WHERE created_at > %s AND access_result IN ('granted', 'converted')
             )",
            $start_date, $start_date
        ));
        
        // 总的被拒绝会话数
        $total_denied_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM $table_name 
             WHERE created_at > %s AND access_result = 'denied'",
            $start_date
        ));
        
        // 计算转化率
        $conversion_rate = $total_denied_sessions > 0 ? 
            round(($conversions / $total_denied_sessions) * 100, 2) : 0;
        
        // 跳出率
        $bounce_rate = $total_denied_sessions > 0 ? 
            round(($bounce_sessions / $total_denied_sessions) * 100, 2) : 0;

        $stats = array(
            'conversions' => intval($conversions),
            'bounce_sessions' => intval($bounce_sessions),
            'total_denied_sessions' => intval($total_denied_sessions),
            'conversion_rate' => $conversion_rate,
            'bounce_rate' => $bounce_rate,
        );
        
        // 缓存结果
        wp_cache_set($cache_key, $stats, '', self::CACHE_EXPIRY);
        
        return $stats;
    }

    /**
     * 获取热门受保护文章
     */
    public static function get_popular_protected_articles($days = 30, $limit = 10) {
        global $wpdb;
        
        $start_date = date('Y-m-d H:i:s', time() - ($days * 24 * 3600));
        $table_name = self::$table_name;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, 
                    COUNT(*) as total_access,
                    SUM(CASE WHEN a.access_result = 'denied' THEN 1 ELSE 0 END) as denied_access,
                    SUM(CASE WHEN a.access_result = 'granted' THEN 1 ELSE 0 END) as granted_access,
                    SUM(CASE WHEN a.access_result = 'converted' THEN 1 ELSE 0 END) as conversions
             FROM $table_name a
             JOIN {$wpdb->posts} p ON a.post_id = p.ID
             WHERE a.created_at > %s AND a.access_type != 'conversion'
             GROUP BY p.ID, p.post_title
             ORDER BY total_access DESC
             LIMIT %d",
            $start_date, $limit
        ));
        
        foreach ($results as &$result) {
            $result->conversion_rate = $result->denied_access > 0 ? 
                round(($result->conversions / $result->denied_access) * 100, 2) : 0;
        }
        
        return $results;
    }

    /**
     * 获取时间趋势数据
     */
    public static function get_trend_data($days = 30, $metric = 'access') {
        global $wpdb;
        
        $start_date = date('Y-m-d', time() - ($days * 24 * 3600));
        $table_name = self::$table_name;
        
        $sql_conditions = array(
            'access' => "access_type != 'conversion'",
            'denied' => "access_result = 'denied' AND access_type != 'conversion'",
            'conversions' => "access_result = 'converted'",
        );
        
        $condition = isset($sql_conditions[$metric]) ? $sql_conditions[$metric] : $sql_conditions['access'];
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date_key, COUNT(*) as count
             FROM $table_name 
             WHERE DATE(created_at) >= %s AND $condition
             GROUP BY DATE(created_at)
             ORDER BY date_key ASC",
            $start_date
        ));
        
        // 填充缺失的日期
        $trend_data = array();
        $current_date = strtotime($start_date);
        $end_date = time();
        
        while ($current_date <= $end_date) {
            $date_key = date('Y-m-d', $current_date);
            $found = false;
            
            foreach ($results as $result) {
                if ($result->date_key === $date_key) {
                    $trend_data[] = array(
                        'date' => $date_key,
                        'count' => intval($result->count)
                    );
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $trend_data[] = array(
                    'date' => $date_key,
                    'count' => 0
                );
            }
            
            $current_date += 24 * 3600; // 下一天
        }
        
        return $trend_data;
    }

    /**
     * AJAX获取分析数据
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('folio_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'folio')));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'access');
        $days = intval($_POST['days'] ?? 30);
        
        $data = array();
        
        switch ($type) {
            case 'content':
                $data = self::get_membership_content_stats($days);
                break;
            case 'access':
                $data = self::get_access_stats($days);
                break;
            case 'conversion':
                $data = self::get_conversion_stats($days);
                break;
            case 'trend':
                $metric = sanitize_text_field($_POST['metric'] ?? 'access');
                $data = self::get_trend_data($days, $metric);
                break;
            case 'popular':
                $limit = intval($_POST['limit'] ?? 10);
                $data = self::get_popular_protected_articles($days, $limit);
                break;
        }
        
        wp_send_json_success($data);
    }

    /**
     * AJAX导出分析报告
     */
    public function ajax_export_analytics() {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'folio_export_analytics')) {
            wp_die(__('Security verification failed', 'folio'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'folio'));
        }
        
        $this->export_analytics_report();
    }

    /**
     * 导出分析报告
     */
    private function export_analytics_report() {
        $content_stats = self::get_membership_content_stats();
        $access_stats = self::get_access_stats();
        $conversion_stats = self::get_conversion_stats();
        $popular_articles = self::get_popular_protected_articles();
        
        $filename = 'folio-membership-analytics-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // 添加BOM以支持中文
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // 内容统计
        fputcsv($output, array(__('Membership Content Stats', 'folio')));
        fputcsv($output, array(__('Metric', 'folio'), __('Value', 'folio')));
        fputcsv($output, array(__('Total Protected Articles', 'folio'), $content_stats['total_protected_articles']));
        fputcsv($output, array(__('VIP Articles', 'folio'), $content_stats['vip_articles']));
        fputcsv($output, array(__('SVIP Articles', 'folio'), $content_stats['svip_articles']));
        fputcsv($output, array(__('Free Articles', 'folio'), $content_stats['free_articles']));
        fputcsv($output, array(''));
        
        // 访问统计
        fputcsv($output, array(__('Access Stats (30 days)', 'folio')));
        fputcsv($output, array(__('Metric', 'folio'), __('Value', 'folio')));
        fputcsv($output, array(__('Total Accesses', 'folio'), $access_stats['total_access']));
        fputcsv($output, array(__('Member Accesses', 'folio'), $access_stats['member_access']));
        fputcsv($output, array(__('Non-member Accesses', 'folio'), $access_stats['non_member_access']));
        fputcsv($output, array(__('Denied Accesses', 'folio'), $access_stats['denied_access']));
        fputcsv($output, array(__('Unique Visitors', 'folio'), $access_stats['unique_visitors']));
        fputcsv($output, array(''));
        
        // 转化统计
        fputcsv($output, array(__('Conversion Analysis (30 days)', 'folio')));
        fputcsv($output, array(__('Metric', 'folio'), __('Value', 'folio')));
        fputcsv($output, array(__('Conversions', 'folio'), $conversion_stats['conversions']));
        fputcsv($output, array(__('Bounce Sessions', 'folio'), $conversion_stats['bounce_sessions']));
        fputcsv($output, array(__('Conversion Rate (%)', 'folio'), $conversion_stats['conversion_rate']));
        fputcsv($output, array(__('Bounce Rate (%)', 'folio'), $conversion_stats['bounce_rate']));
        fputcsv($output, array(''));
        
        // 热门文章
        fputcsv($output, array(__('Popular Protected Articles (30 days)', 'folio')));
        fputcsv($output, array(__('Post Title', 'folio'), __('Total Access', 'folio'), __('Granted Access', 'folio'), __('Denied Access', 'folio'), __('Conversions', 'folio'), __('Conversion Rate (%)', 'folio')));
        foreach ($popular_articles as $article) {
            fputcsv($output, array(
                $article->post_title,
                $article->total_access,
                $article->granted_access,
                $article->denied_access,
                $article->conversions,
                $article->conversion_rate
            ));
        }
        
        fclose($output);
        exit;
    }

    /**
     * 生成每日汇总
     */
    public function generate_daily_summary() {
        global $wpdb;
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $summary_table = $wpdb->prefix . 'folio_analytics_summary';
        
        // 检查是否已经生成过昨天的汇总
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $summary_table WHERE date_key = %s",
            $yesterday
        ));
        
        if ($existing > 0) {
            return; // 已经生成过了
        }
        
        $table_name = self::$table_name;
        
        // 生成各种汇总指标
        $metrics = array(
            'total_access' => "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = '$yesterday' AND access_type != 'conversion'",
            'member_access' => "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = '$yesterday' AND user_level IN ('vip', 'svip') AND access_type != 'conversion'",
            'denied_access' => "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = '$yesterday' AND access_result = 'denied' AND access_type != 'conversion'",
            'conversions' => "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = '$yesterday' AND access_result = 'converted'",
        );
        
        foreach ($metrics as $metric_type => $sql) {
            $value = $wpdb->get_var($sql);
            
            $wpdb->insert(
                $summary_table,
                array(
                    'date_key' => $yesterday,
                    'metric_type' => $metric_type,
                    'metric_value' => intval($value),
                ),
                array('%s', '%s', '%d')
            );
        }
    }

    /**
     * 清理旧数据
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // 删除90天前的详细数据
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-90 days'));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::$table_name . " WHERE created_at < %s",
            $cutoff_date
        ));
        
        // 删除1年前的汇总数据
        $summary_cutoff = date('Y-m-d', strtotime('-1 year'));
        $summary_table = $wpdb->prefix . 'folio_analytics_summary';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $summary_table WHERE date_key < %s",
            $summary_cutoff
        ));
    }

    /**
     * 确定访问类型
     */
    private function determine_access_type() {
        if (is_search()) {
            return 'search';
        } elseif (wp_get_referer()) {
            $referer = wp_get_referer();
            if (strpos($referer, home_url()) === 0) {
                return 'internal';
            } else {
                return 'external';
            }
        } else {
            return 'direct';
        }
    }

    /**
     * 获取或创建会话ID
     */
    private function get_or_create_session_id() {
        if (isset($_COOKIE['folio_session_id'])) {
            return $_COOKIE['folio_session_id'];
        }
        
        $session_id = wp_generate_uuid4();
        setcookie('folio_session_id', $session_id, time() + (24 * 3600), '/'); // 24小时
        
        return $session_id;
    }

    /**
     * 获取客户端IP
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // PHP 8.2 兼容性：确保 $ip 是字符串且不为空
                if (is_string($ip) && !empty($ip) && strpos($ip, ',') !== false) {
                    $ip_parts = explode(',', $ip);
                    if (!empty($ip_parts) && isset($ip_parts[0])) {
                        $ip = trim($ip_parts[0]);
                    }
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 跟踪潜在转化
     */
    private function track_potential_conversion($post_id, $session_id) {
        // 记录潜在转化事件，用于后续分析
        $this->record_access_data(array(
            'post_id' => $post_id,
            'user_id' => null,
            'user_level' => 'free',
            'access_type' => 'potential_conversion',
            'access_result' => 'interested',
            'session_id' => $session_id,
        ));
    }
}

// 初始化
new folio_Membership_Analytics();

/**
 * 模板标签函数
 */
if (!function_exists('folio_get_membership_content_stats')) {
    function folio_get_membership_content_stats($days = 30) {
        return folio_Membership_Analytics::get_membership_content_stats($days);
    }
}

if (!function_exists('folio_get_membership_access_stats')) {
    function folio_get_membership_access_stats($days = 30) {
        return folio_Membership_Analytics::get_access_stats($days);
    }
}

if (!function_exists('folio_get_membership_conversion_stats')) {
    function folio_get_membership_conversion_stats($days = 30) {
        return folio_Membership_Analytics::get_conversion_stats($days);
    }
}

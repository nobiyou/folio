<?php
/**
 * Security Protection Manager
 * 
 * 安全防护机制管理器 - 实现内容保护绕过检测和访问日志记录
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Security_Protection_Manager {

    // 日志表名
    const LOG_TABLE = 'folio_access_logs';
    
    // 蜘蛛IP网段表名
    const SPIDER_NETS_TABLE = 'folio_spider_nets';
    
    // 异常访问检测阈值
    const SUSPICIOUS_THRESHOLD = 10; // 10分钟内超过此次数视为异常
    const RATE_LIMIT_WINDOW = 600; // 10分钟窗口
    const BLOCK_DURATION = 3600; // 阻止访问1小时
    
    // 缓存键前缀
    const CACHE_PREFIX = 'folio_security_';
    
    private static $instance = null;
    private $blocked_ips = array();
    private $access_counts = array();

    public function __construct() {
        // 初始化数据库表（只在init钩子上执行一次，避免重复）
        add_action('init', array($this, 'init_database'), 1);
        add_action('init', array($this, 'init_spider_nets_table'), 1);
        
        // 服务器端内容保护
        add_filter('the_content', array($this, 'server_side_content_protection'), 1);
        add_filter('the_excerpt', array($this, 'server_side_excerpt_protection'), 1);
        
        // RSS和API访问控制增强
        add_filter('the_content_feed', array($this, 'enhanced_feed_protection'), 1);
        add_filter('rest_prepare_post', array($this, 'enhanced_rest_protection'), 1, 3);
        
        // 搜索引擎爬虫处理
        add_filter('the_content', array($this, 'search_engine_content_filter'), 2);
        add_filter('wp_robots', array($this, 'modify_robots_meta'));
        
        // 异常访问检测
        add_action('wp', array($this, 'detect_suspicious_access'));
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'));
        
        // 访问日志记录
        add_action('wp', array($this, 'log_content_access'));
        
        // 安全头部设置
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // 防止直接文件访问
        add_action('template_redirect', array($this, 'prevent_direct_access'));
        
        // 清理过期日志
        add_action('folio_cleanup_logs', array($this, 'cleanup_old_logs'));
        if (!wp_next_scheduled('folio_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'folio_cleanup_logs');
        }
    }

    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 确保表存在（在使用前调用）
     */
    private function ensure_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        // 检查表是否已存在（使用更安全的方法）
        try {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
            
            if ($table_exists) {
                // 表已存在，检查是否需要添加is_spider字段
                $this->maybe_add_spider_column($table_name);
                return true;
            }
        } catch (Exception $e) {
            error_log('Folio Access Logs: Error checking table: ' . $e->getMessage());
        }
        
        // 表不存在，创建它
        $this->init_database();
        
        // 再次检查
        try {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
            if (!$table_exists) {
                error_log('Folio Access Logs: Table still does not exist after creation attempt: ' . $table_name);
                error_log('Folio Access Logs: WordPress DB prefix: ' . $wpdb->prefix);
                error_log('Folio Access Logs: Expected table name: ' . $table_name);
            } else {
                // 表创建成功，确保字段存在
                $this->maybe_add_spider_column($table_name);
            }
            return $table_exists;
        } catch (Exception $e) {
            error_log('Folio Access Logs: Error checking table after creation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 初始化数据库表
     */
    public function init_database() {
        // 使用静态变量避免重复执行
        static $initialized = false;
        if ($initialized) {
            return; // 已经初始化过，跳过
        }
        
        global $wpdb;
        
        // 确保使用正确的表前缀
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        // 检查表是否已存在（使用更安全的方法）
        $table_exists = false;
        try {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        } catch (Exception $e) {
            error_log('Folio Access Logs: Error checking table existence: ' . $e->getMessage());
        }
        
        if ($table_exists) {
            $initialized = true;
            return; // 表已存在，无需创建
        }
        
        // 记录调试信息（仅在创建时记录）
        error_log('Folio Access Logs: Creating table: ' . $table_name);
        error_log('Folio Access Logs: WordPress DB prefix: ' . $wpdb->prefix);
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            post_id bigint(20) DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            user_agent text,
            referer text,
            request_uri text,
            access_result varchar(20) NOT NULL,
            protection_bypassed tinyint(1) DEFAULT 0,
            is_suspicious tinyint(1) DEFAULT 0,
            is_spider tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_created_at (created_at),
            KEY idx_ip_time (ip_address, created_at),
            KEY idx_post_time (post_id, created_at),
            KEY idx_user_time (user_id, created_at),
            KEY idx_suspicious (is_suspicious, created_at),
            KEY idx_action_type (action_type, created_at),
            KEY idx_access_result (access_result, created_at),
            KEY idx_is_spider (is_spider, created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // 使用 dbDelta 创建表
        $result = dbDelta($sql);
        
        // 标记为已初始化
        $initialized = true;
        
        // 验证表是否创建成功
        $table_exists_after = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        if ($table_exists_after) {
            error_log('Folio Access Logs Table: Successfully created: ' . $table_name);
        } else {
            error_log('Folio Access Logs Table: Creation may have failed. Table still does not exist.');
            // 尝试直接执行 SQL
            $direct_result = $wpdb->query($sql);
            if ($direct_result !== false) {
                error_log('Folio Access Logs Table: Created using direct query');
            } else {
                error_log('Folio Access Logs Table: Direct query also failed: ' . $wpdb->last_error);
            }
        }
        
        // 如果表已存在，检查是否需要添加is_spider字段
        if ($table_exists) {
            $this->maybe_add_spider_column($table_name);
        }
    }

    /**
     * 检查并添加is_spider字段（如果不存在）
     */
    private function maybe_add_spider_column($table_name) {
        global $wpdb;
        
        // 检查字段是否存在
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$table_name` LIKE 'is_spider'"
        ));
        
        if (empty($column_exists)) {
            // 添加字段
            $result1 = $wpdb->query("ALTER TABLE `$table_name` ADD COLUMN is_spider tinyint(1) DEFAULT 0 AFTER is_suspicious");
            
            // 检查索引是否存在
            $index_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW INDEX FROM `$table_name` WHERE Key_name = 'idx_is_spider'"
            ));
            
            // 如果字段添加成功且索引不存在，添加索引
            if ($result1 !== false && empty($index_exists)) {
                $wpdb->query("ALTER TABLE `$table_name` ADD INDEX idx_is_spider (is_spider, created_at)");
            }
        }
    }

    /**
     * 初始化蜘蛛IP网段表
     */
    public function init_spider_nets_table() {
        static $initialized = false;
        if ($initialized) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . self::SPIDER_NETS_TABLE;
        
        // 检查表是否已存在
        $table_exists = false;
        try {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        } catch (Exception $e) {
            error_log('Folio Spider Nets: Error checking table existence: ' . $e->getMessage());
        }
        
        if ($table_exists) {
            $initialized = true;
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            spider_id int(11) NOT NULL,
            spider_name varchar(100) DEFAULT NULL,
            ip_net varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_spider_id (spider_id),
            KEY idx_ip_net (ip_net)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $initialized = true;
    }

    /**
     * 服务器端内容保护 - 最高优先级
     */
    public function server_side_content_protection($content) {
        // 在管理后台不进行保护
        if (is_admin()) {
            return $content;
        }

        global $post;
        if (!$post || $post->post_type !== 'post') {
            return $content;
        }

        // 检查是否被阻止访问
        if ($this->is_access_blocked()) {
            $this->log_access($post->ID, 'content_view', 'blocked', true);
            return $this->get_blocked_message();
        }

        // 获取保护信息
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            $this->log_access($post->ID, 'content_view', 'allowed');
            return $content;
        }

        // 检查用户权限
        $user_id = get_current_user_id();
        $can_access = folio_Article_Protection_Manager::can_user_access($post->ID, $user_id);
        
        if ($can_access) {
            $this->log_access($post->ID, 'content_view', 'allowed');
            return $content;
        }

        // 检测绕过尝试
        $this->detect_bypass_attempt($post->ID, $content);
        
        $this->log_access($post->ID, 'content_view', 'denied');
        
        // 返回保护后的内容（由Article_Protection_Manager处理）
        return $content;
    }

    /**
     * 服务器端摘要保护
     */
    public function server_side_excerpt_protection($excerpt) {
        if (is_admin()) {
            return $excerpt;
        }

        global $post;
        if (!$post || $post->post_type !== 'post') {
            return $excerpt;
        }

        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $excerpt;
        }

        $user_id = get_current_user_id();
        $can_access = folio_Article_Protection_Manager::can_user_access($post->ID, $user_id);
        
        if (!$can_access) {
            $this->log_access($post->ID, 'excerpt_view', 'denied');
        }

        return $excerpt;
    }

    /**
     * 增强的RSS保护
     */
    public function enhanced_feed_protection($content) {
        global $post;
        if (!$post) {
            return $content;
        }

        $this->log_access($post->ID, 'rss_access', 'attempted');

        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $content;
        }

        // 检测RSS绕过尝试
        $this->detect_rss_bypass_attempt($post->ID);

        // 根据设置决定RSS内容
        if (!$protection_info['rss_include']) {
            $this->log_access($post->ID, 'rss_access', 'blocked');
            return '此内容为会员专属，请访问网站查看完整内容。';
        }

        $this->log_access($post->ID, 'rss_access', 'preview');
        return $content; // 让Article_Protection_Manager处理预览
    }

    /**
     * 增强的REST API保护
     */
    public function enhanced_rest_protection($response, $post, $request) {
        // 在后台编辑器中不进行过滤，允许编辑完整内容
        // 检查多种情况：后台页面、AJAX请求、REST API编辑上下文
        $is_edit_context = false;
        
        // 检查请求参数中的 context
        if (isset($_REQUEST['context']) && $_REQUEST['context'] === 'edit') {
            $is_edit_context = true;
        } elseif (is_array($request) && isset($request['context']) && $request['context'] === 'edit') {
            $is_edit_context = true;
        } elseif (is_object($request) && method_exists($request, 'get_param')) {
            $context = $request->get_param('context');
            if ($context === 'edit') {
                $is_edit_context = true;
            }
        }
        
        // 检查请求路径（Gutenberg 编辑器通常使用 /wp-json/wp/v2/posts/{id}?context=edit）
        if (!$is_edit_context && isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            // PHP 8.2 兼容性：确保 $request_uri 是字符串
            if (is_string($request_uri) && strpos($request_uri, '/wp-json/wp/v2/posts/') !== false && 
                (strpos($request_uri, 'context=edit') !== false || strpos($request_uri, 'context%3Dedit') !== false)) {
                $is_edit_context = true;
            }
        }
        
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST && $is_edit_context) || (defined('WP_ADMIN') && WP_ADMIN)) {
            return $response;
        }
        
        if ($post->post_type !== 'post') {
            return $response;
        }

        $this->log_access($post->ID, 'api_access', 'attempted');

        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $response;
        }

        // 检测API绕过尝试
        $this->detect_api_bypass_attempt($post->ID, $request);

        $user_id = get_current_user_id();
        $can_access = folio_Article_Protection_Manager::can_user_access($post->ID, $user_id);
        
        if (!$can_access) {
            $this->log_access($post->ID, 'api_access', 'denied');
            
            // 修改响应数据
            $data = $response->get_data();
            $data['content']['protected'] = true;
            $data['content']['rendered'] = '此内容为会员专属，需要相应等级权限查看。';
            $response->set_data($data);
        } else {
            $this->log_access($post->ID, 'api_access', 'allowed');
        }

        return $response;
    }

    /**
     * 搜索引擎内容过滤
     */
    public function search_engine_content_filter($content) {
        if (is_admin() || !$this->is_search_engine()) {
            return $content;
        }

        global $post;
        if (!$post || $post->post_type !== 'post') {
            return $content;
        }

        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            $this->log_access($post->ID, 'seo_crawl', 'allowed');
            return $content;
        }

        $this->log_access($post->ID, 'seo_crawl', 'filtered');

        // 为搜索引擎提供适当的预览
        if ($protection_info['seo_visible']) {
            $preview = folio_Article_Protection_Manager::generate_preview($content, $protection_info);
            return $preview . "\n\n[此内容为会员专属]";
        }

        return '此内容为会员专属，需要登录并具备相应权限查看。';
    }

    /**
     * 修改robots meta标签
     */
    public function modify_robots_meta($robots) {
        if (!is_singular('post')) {
            return $robots;
        }

        global $post;
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        
        if ($protection_info['is_protected'] && !$protection_info['seo_visible']) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    /**
     * 检测异常访问模式
     */
    public function detect_suspicious_access() {
        $ip = $this->get_client_ip();
        $current_time = time();
        
        // 管理员豁免访问频率限制
        if (current_user_can('manage_options')) {
            return;
        }

        // 白名单或蜘蛛豁免：不进行防护
        if ($this->is_ip_whitelisted($ip) || $this->is_spider_exempt()) {
            return;
        }

        // 黑名单：直接阻止
        if ($this->is_ip_blacklisted($ip)) {
            $this->log_access(null, 'blocked_access', 'blocked', true);
            $this->send_blocked_response();
            return;
        }

        // 检查是否已被阻止（频率限制临时封禁）
        if ($this->is_ip_blocked($ip)) {
            $this->log_access(null, 'blocked_access', 'blocked', true);
            $this->send_blocked_response();
            return;
        }

        // 从配置选项读取访问频率限制（默认使用常量值）
        $rate_limit = get_option('folio_security_rate_limit', self::SUSPICIOUS_THRESHOLD);

        // 获取访问计数
        $access_count = $this->get_access_count($ip, $current_time);
        
        // 检查是否超过阈值
        if ($access_count > $rate_limit) {
            // 从配置选项读取阻止时长（默认使用常量值）
            $block_duration = get_option('folio_security_block_duration', self::BLOCK_DURATION);
            $this->block_ip($ip, $current_time, $block_duration);
            $this->log_access(null, 'suspicious_activity', 'blocked', true);
            $this->send_blocked_response();
            return;
        }

        // 记录访问
        $this->increment_access_count($ip, $current_time);
    }

    /**
     * 检测内容保护绕过尝试
     */
    private function detect_bypass_attempt($post_id, $content) {
        $suspicious_patterns = array(
            'curl',
            'wget',
            'bot',
            'spider',
            'scraper',
            'headless',
            'phantom',
            'selenium'
        );

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        $is_suspicious = false;
        
        // 检查User-Agent
        foreach ($suspicious_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                $is_suspicious = true;
                break;
            }
        }

        // 检查请求头
        if (!$is_suspicious) {
            $headers = getallheaders();
            if (empty($headers['Accept']) || empty($headers['Accept-Language'])) {
                $is_suspicious = true;
            }
        }

        // 检查访问频率（管理员豁免）
        if (!$is_suspicious && !current_user_can('manage_options')) {
            $ip = $this->get_client_ip();
            $recent_access = $this->get_recent_access_count($ip, $post_id);
            if ($recent_access > 5) { // 5分钟内访问同一文章超过5次
                $is_suspicious = true;
            }
        }

        if ($is_suspicious) {
            $this->log_access($post_id, 'bypass_attempt', 'detected', false, true);
        }
    }

    /**
     * 检测RSS绕过尝试
     */
    private function detect_rss_bypass_attempt($post_id) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 检查是否为正常的RSS阅读器
        $legitimate_readers = array('feedly', 'inoreader', 'newsblur', 'feedbin');
        $is_legitimate = false;
        
        foreach ($legitimate_readers as $reader) {
            if (stripos($user_agent, $reader) !== false) {
                $is_legitimate = true;
                break;
            }
        }

        if (!$is_legitimate && !$this->is_search_engine()) {
            $this->log_access($post_id, 'rss_bypass_attempt', 'detected', false, true);
        }
    }

    /**
     * 检测API绕过尝试
     */
    private function detect_api_bypass_attempt($post_id, $request) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $this->get_client_ip();
        
        // 检查API访问频率（管理员豁免）
        if (!current_user_can('manage_options')) {
            $api_access_count = $this->get_api_access_count($ip);
            if ($api_access_count > 20) { // 1小时内API访问超过20次
                $this->log_access($post_id, 'api_bypass_attempt', 'detected', false, true);
            }
        }

        // 检查请求参数
        $params = $request->get_params();
        if (isset($params['_embed']) || isset($params['context'])) {
            // 正常的API请求
            return;
        }

        // 检查是否为自动化工具
        $automation_patterns = array('python', 'java', 'node', 'php', 'ruby');
        foreach ($automation_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                $this->log_access($post_id, 'api_bypass_attempt', 'detected', false, true);
                break;
            }
        }
    }

    /**
     * 记录访问日志
     */
    public function log_access($post_id = null, $action_type = 'page_view', $result = 'allowed', $bypassed = false, $suspicious = false) {
        global $wpdb;
        
        // 确保表存在
        if (!$this->ensure_table_exists()) {
            error_log('Folio Access Logs Table does not exist and could not be created');
            return false;
        }
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        // 检查是否为蜘蛛访问
        $is_spider = $this->is_spider_ip($this->get_client_ip());
        
        $data = array(
            'ip_address' => $this->get_client_ip(),
            'user_id' => get_current_user_id() ?: null,
            'post_id' => $post_id,
            'action_type' => $action_type,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'access_result' => $result,
            'protection_bypassed' => $bypassed ? 1 : 0,
            'is_suspicious' => $suspicious ? 1 : 0,
            'is_spider' => $is_spider ? 1 : 0,
        );
        
        $wpdb->insert($table_name, $data);
    }

    /**
     * 记录用户登录
     */
    public function log_user_login($user_login, $user) {
        $this->log_access(null, 'user_login', 'success');
    }

    /**
     * 记录用户登出
     */
    public function log_user_logout() {
        $this->log_access(null, 'user_logout', 'success');
    }

    /**
     * 记录内容访问
     */
    public function log_content_access() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (is_singular('post')) {
            global $post;
            $this->log_access($post->ID, 'page_view', 'viewed');
        }
    }

    /**
     * 添加安全头部
     */
    public function add_security_headers() {
        if (is_admin()) {
            return;
        }

        // 防止点击劫持
        header('X-Frame-Options: SAMEORIGIN');
        
        // 防止MIME类型嗅探
        header('X-Content-Type-Options: nosniff');
        
        // XSS保护
        header('X-XSS-Protection: 1; mode=block');
        
        // 引用策略
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // 内容安全策略（基础版本）
        // 注意：不要设置过于严格的CSP，否则会阻止外部资源（如字体、CDN等）加载
        // 对于受保护的文章，我们使用更宽松的CSP策略，允许必要的CDN资源
        // 如果确实需要更严格的安全策略，可以通过过滤器自定义
        if (is_singular('post')) {
            global $post;
            $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
            if ($protection_info['is_protected']) {
                // 允许通过过滤器自定义CSP策略
                $csp_policy = apply_filters('folio_security_csp_policy', null, $post->ID);
                
                // 如果没有自定义策略，使用默认的宽松策略
                if ($csp_policy === null) {
                    // 默认策略：允许同源和所有HTTPS资源
                    // 这对于WordPress主题是必要的，因为需要加载外部CDN资源
                    $csp_policy = "default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' data: https:; img-src 'self' data: https:; connect-src 'self' https:; frame-ancestors 'self';";
                }
                
                // 只有在策略不为空时才设置
                if (!empty($csp_policy)) {
                    header("Content-Security-Policy: " . $csp_policy);
                }
            }
        }
    }

    /**
     * 防止直接文件访问
     */
    public function prevent_direct_access() {
        // 检查是否为直接访问PHP文件
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('/\.php$/i', $request_uri) && !is_admin()) {
            $this->log_access(null, 'direct_file_access', 'blocked', false, true);
            wp_die('Direct file access is not allowed.', 'Access Denied', array('response' => 403));
        }
    }

    /**
     * 获取客户端IP地址
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // 处理多个IP的情况（取第一个）
                // PHP 8.2 兼容性：确保 $ip 是字符串且不为空
                if (is_string($ip) && !empty($ip) && strpos($ip, ',') !== false) {
                    $ip_parts = explode(',', $ip);
                    if (!empty($ip_parts) && isset($ip_parts[0])) {
                        $ip = trim($ip_parts[0]);
                    }
                }
                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 检查是否为搜索引擎（基于User-Agent）
     */
    private function is_search_engine() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $search_engines = array(
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 
            'baiduspider', 'yandexbot', 'sogou', 'facebookexternalhit'
        );

        foreach ($search_engines as $bot) {
            if (stripos($user_agent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查IP是否在蜘蛛池中（基于IP网段）
     */
    public function is_spider_ip($ip) {
        // 先检查User-Agent
        if ($this->is_search_engine()) {
            return true;
        }
        
        // 再检查IP网段
        $spider_info = $this->get_spider_info_by_ip($ip);
        return !empty($spider_info);
    }

    /**
     * 根据IP地址获取蜘蛛信息
     */
    public function get_spider_info_by_ip($ip) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::SPIDER_NETS_TABLE;
        
        // 检查表是否存在
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        if (!$table_exists) {
            return null;
        }
        
        // 获取所有IP网段及其对应的蜘蛛信息
        // 注意：表名不能使用prepare占位符，因为它是系统生成的，不是用户输入
        $nets = $wpdb->get_results(
            "SELECT DISTINCT spider_id, spider_name, ip_net FROM `$table_name` ORDER BY spider_id"
        );
        
        foreach ($nets as $net) {
            if ($this->ip_in_network($ip, $net->ip_net)) {
                return array(
                    'spider_id' => intval($net->spider_id),
                    'spider_name' => $net->spider_name ?: '未知蜘蛛'
                );
            }
        }
        
        // 如果IP网段中没有匹配，检查User-Agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $spider_info = $this->get_spider_info_by_user_agent($user_agent);
        if ($spider_info) {
            return $spider_info;
        }
        
        return null;
    }

    /**
     * 根据User-Agent获取蜘蛛信息
     */
    public function get_spider_info_by_user_agent($user_agent) {
        $user_agent_lower = strtolower($user_agent);
        
        $spider_patterns = array(
            'googlebot' => array('spider_id' => 12, 'spider_name' => 'Google蜘蛛'),
            'bingbot' => array('spider_id' => 16, 'spider_name' => 'Bing蜘蛛'),
            'slurp' => array('spider_id' => 15, 'spider_name' => 'Yahoo蜘蛛'),
            'baiduspider' => array('spider_id' => 13, 'spider_name' => '百度蜘蛛'),
            'sogou' => array('spider_id' => 17, 'spider_name' => '搜狗蜘蛛'),
            'yandexbot' => array('spider_id' => 18, 'spider_name' => 'Yandex蜘蛛'),
            '360spider' => array('spider_id' => 11, 'spider_name' => '360蜘蛛'),
            'bytespider' => array('spider_id' => 14, 'spider_name' => '头条蜘蛛'),
            'yisouspider' => array('spider_id' => 19, 'spider_name' => '神马蜘蛛'),
        );
        
        // PHP 8.2 兼容性：确保 $user_agent_lower 是字符串
        if (!is_string($user_agent_lower)) {
            return null;
        }
        
        foreach ($spider_patterns as $pattern => $info) {
            if (strpos($user_agent_lower, $pattern) !== false) {
                return $info;
            }
        }
        
        return null;
    }

    /**
     * 检查IP是否在指定网段内（支持IPv4和IPv6 CIDR）
     */
    private function ip_in_network($ip, $network) {
        // PHP 8.2 兼容性：确保参数是字符串
        if (!is_string($network) || !is_string($ip)) {
            return false;
        }
        
        if (strpos($network, '/') === false) {
            // 单个IP地址
            return $ip === $network;
        }
        
        list($subnet, $mask) = explode('/', $network);
        $mask = (int)$mask;
        
        // 验证mask的有效范围
        if ($mask < 0) {
            return false;
        }
        
        // IPv6处理
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($mask > 128) {
                return false;
            }
            return $this->ipv6_in_range($ip, $network);
        }
        
        // IPv4 CIDR处理
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($mask > 32) {
                return false;
            }
            
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            
            // 验证IP转换是否成功
            if ($ip_long === false || $subnet_long === false) {
                return false;
            }
            
            // 计算掩码（防止负数位移）
            if ($mask == 0) {
                $mask_long = 0;
            } elseif ($mask == 32) {
                $mask_long = -1;
            } else {
                $mask_long = -1 << (32 - $mask);
            }
            
            $subnet_long &= $mask_long;
            
            return ($ip_long & $mask_long) === $subnet_long;
        }
        
        return false;
    }

    /**
     * IPv6 CIDR匹配
     */
    private function ipv6_in_range($ip, $network) {
        list($subnet, $mask) = explode('/', $network);
        
        $ip_bin = inet_pton($ip);
        $subnet_bin = inet_pton($subnet);
        
        if ($ip_bin === false || $subnet_bin === false) {
            return false;
        }
        
        $mask_bits = (int)$mask;
        $bytes = intval($mask_bits / 8);
        $bits = $mask_bits % 8;
        
        for ($i = 0; $i < $bytes; $i++) {
            if ($ip_bin[$i] !== $subnet_bin[$i]) {
                return false;
            }
        }
        
        if ($bits > 0) {
            $mask_byte = 0xFF << (8 - $bits);
            if ((ord($ip_bin[$bytes]) & $mask_byte) !== (ord($subnet_bin[$bytes]) & $mask_byte)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 检查访问是否被阻止
     */
    private function is_access_blocked() {
        $ip = $this->get_client_ip();
        if ($this->is_ip_whitelisted($ip) || $this->is_spider_exempt()) {
            return false;
        }
        if ($this->is_ip_blacklisted($ip)) {
            return true;
        }
        return $this->is_ip_blocked($ip);
    }

    /**
     * 检查 IP 是否在白名单
     */
    private function is_ip_whitelisted($ip) {
        return $this->is_ip_in_list($ip, $this->get_whitelist());
    }

    /**
     * 检查 IP 是否在黑名单
     */
    private function is_ip_blacklisted($ip) {
        return $this->is_ip_in_list($ip, $this->get_blacklist());
    }

    /**
     * 蜘蛛是否豁免防护（自动白名单）
     */
    private function is_spider_exempt() {
        if (!get_option('folio_security_spider_whitelist', true)) {
            return false;
        }
        $ip = $this->get_client_ip();
        return $this->is_spider_ip($ip);
    }

    /**
     * 获取白名单列表
     */
    private function get_whitelist() {
        $list = get_option('folio_security_whitelist', '');
        return $this->parse_ip_list($list);
    }

    /**
     * 获取黑名单列表
     */
    private function get_blacklist() {
        $list = get_option('folio_security_blacklist', '');
        return $this->parse_ip_list($list);
    }

    /**
     * 解析 IP 列表（每行一个，支持单个 IP 或 CIDR）
     */
    private function parse_ip_list($list) {
        if (empty($list) || !is_string($list)) {
            return array();
        }
        $lines = preg_split('/[\r\n]+/', $list, -1, PREG_SPLIT_NO_EMPTY);
        $result = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && strpos($line, '#') !== 0) {
                $result[] = $line;
            }
        }
        return $result;
    }

    /**
     * 检查 IP 是否在列表中（支持精确 IP 和 CIDR 网段）
     */
    private function is_ip_in_list($ip, $list) {
        foreach ($list as $entry) {
            $entry = trim($entry);
            if (strpos($entry, '/') !== false) {
                if ($this->ip_in_network($ip, $entry)) {
                    return true;
                }
            } elseif ($ip === $entry) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查IP是否被阻止（临时封禁，来自频率限制）
     */
    private function is_ip_blocked($ip) {
        $cache_key = self::CACHE_PREFIX . 'blocked_' . md5($ip);
        $blocked_until = wp_cache_get($cache_key);
        
        if ($blocked_until !== false) {
            return time() < $blocked_until;
        }

        return false;
    }

    /**
     * 阻止IP访问
     */
    private function block_ip($ip, $current_time, $block_duration = null) {
        // 如果没有指定阻止时长，使用配置选项或默认值
        if ($block_duration === null) {
            $block_duration = get_option('folio_security_block_duration', self::BLOCK_DURATION);
        }
        
        $block_until = $current_time + $block_duration;
        $cache_key = self::CACHE_PREFIX . 'blocked_' . md5($ip);
        wp_cache_set($cache_key, $block_until, '', $block_duration);
        
        // 记录阻止日志
        $this->log_access(null, 'ip_blocked', 'blocked', false, true);
    }

    /**
     * 获取访问计数
     */
    private function get_access_count($ip, $current_time) {
        $cache_key = self::CACHE_PREFIX . 'count_' . md5($ip);
        $access_data = wp_cache_get($cache_key);
        
        if ($access_data === false) {
            return 0;
        }

        // 清理过期的访问记录
        $window_start = $current_time - self::RATE_LIMIT_WINDOW;
        $access_data = array_filter($access_data, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });

        return count($access_data);
    }

    /**
     * 增加访问计数
     */
    private function increment_access_count($ip, $current_time) {
        $cache_key = self::CACHE_PREFIX . 'count_' . md5($ip);
        $access_data = wp_cache_get($cache_key);
        
        if ($access_data === false) {
            $access_data = array();
        }

        $access_data[] = $current_time;
        wp_cache_set($cache_key, $access_data, '', self::RATE_LIMIT_WINDOW);
    }

    /**
     * 获取最近访问计数
     */
    private function get_recent_access_count($ip, $post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        $five_minutes_ago = date('Y-m-d H:i:s', time() - 300);
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE ip_address = %s AND post_id = %d AND created_at > %s",
            $ip, $post_id, $five_minutes_ago
        ));
        
        return intval($count);
    }

    /**
     * 获取API访问计数
     */
    private function get_api_access_count($ip) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        $one_hour_ago = date('Y-m-d H:i:s', time() - 3600);
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE ip_address = %s AND action_type = 'api_access' AND created_at > %s",
            $ip, $one_hour_ago
        ));
        
        return intval($count);
    }

    /**
     * 发送阻止访问响应
     */
    private function send_blocked_response() {
        status_header(429);
        wp_die(
            '访问频率过高，请稍后再试。如果您认为这是错误，请联系网站管理员。',
            '访问被限制',
            array('response' => 429)
        );
    }

    /**
     * 获取阻止访问消息
     */
    private function get_blocked_message() {
        return '<div class="folio-blocked-message">
            <h3>访问受限</h3>
            <p>检测到异常访问模式，您的访问已被暂时限制。</p>
            <p>如果您认为这是错误，请联系网站管理员。</p>
        </div>';
    }

    /**
     * 清理过期日志
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        // 确保表存在
        if (!$this->ensure_table_exists()) {
            return; // 表不存在，跳过清理
        }
        
        // 从设置中获取日志保留天数，默认7天
        $log_retention_days = get_option('folio_security_log_retention', 7);
        
        // 确保至少保留7天，最多365天
        $log_retention_days = max(7, min(365, intval($log_retention_days)));
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        $cutoff_date = date('Y-m-d H:i:s', time() - ($log_retention_days * 24 * 3600));
        
        // 删除超过保留天数的日志
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ));
        
        // 记录清理日志（可选）
        if ($deleted !== false && $deleted > 0) {
            error_log(sprintf('Folio Security: Cleaned up %d old log entries older than %d days', $deleted, $log_retention_days));
        }
        
        return $deleted;
    }

    /**
     * 导入蜘蛛IP网段数据
     */
    public function import_spider_nets($spider_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::SPIDER_NETS_TABLE;
        
        // 确保表存在
        $this->init_spider_nets_table();
        
        // 清空现有数据（可选，根据需求决定）
        // $wpdb->query("TRUNCATE TABLE `$table_name`");
        
        $spider_names = array(
            13 => '百度蜘蛛',
            17 => '搜狗蜘蛛',
            12 => 'Google蜘蛛',
            11 => '360蜘蛛',
            19 => '神马蜘蛛',
            15 => 'Yahoo蜘蛛',
            14 => '头条蜘蛛',
            16 => 'Bing蜘蛛',
            18 => 'Yandex蜘蛛'
        );
        
        $imported = 0;
        foreach ($spider_data as $spider_group) {
            $spider_id = intval($spider_group['spider_id']);
            $spider_name = isset($spider_names[$spider_id]) ? $spider_names[$spider_id] : '未知蜘蛛';
            
            if (isset($spider_group['net_list']) && is_array($spider_group['net_list'])) {
                foreach ($spider_group['net_list'] as $net) {
                    $ip_net = sanitize_text_field($net['ip_net']);
                    
                    // 检查是否已存在
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM `$table_name` WHERE spider_id = %d AND ip_net = %s",
                        $spider_id, $ip_net
                    ));
                    
                    if (!$exists) {
                        $wpdb->insert($table_name, array(
                            'spider_id' => $spider_id,
                            'spider_name' => $spider_name,
                            'ip_net' => $ip_net
                        ));
                        $imported++;
                    }
                }
            }
        }
        
        return $imported;
    }

    /**
     * 获取所有蜘蛛IP网段
     */
    public function get_spider_nets($spider_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::SPIDER_NETS_TABLE;
        
        if ($spider_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `$table_name` WHERE spider_id = %d ORDER BY id",
                $spider_id
            ), ARRAY_A);
        }
        
        return $wpdb->get_results("SELECT * FROM `$table_name` ORDER BY spider_id, id", ARRAY_A);
    }

    /**
     * 删除蜘蛛IP网段
     */
    public function delete_spider_net($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::SPIDER_NETS_TABLE;
        
        return $wpdb->delete($table_name, array('id' => intval($id)), array('%d'));
    }

    /**
     * 清空所有蜘蛛IP网段
     */
    public function clear_spider_nets() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::SPIDER_NETS_TABLE;
        
        return $wpdb->query("TRUNCATE TABLE `$table_name`");
    }

    /**
     * 分析访问日志，自动识别并添加蜘蛛和爬虫数据
     * 
     * @param int $days 分析最近N天的日志，默认7天
     * @param int $min_count 最少访问次数，低于此次数的IP不添加，默认3次
     * @return array 分析结果统计
     */
    public function analyze_logs_for_spiders($days = 7, $min_count = 3) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        $spider_table = $wpdb->prefix . self::SPIDER_NETS_TABLE;
        
        // 确保表存在
        $this->init_spider_nets_table();
        
        // 获取最近N天的访问日志
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // 查询所有有 User-Agent 的访问记录
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT ip_address, user_agent, COUNT(*) as access_count 
             FROM `$table_name` 
             WHERE created_at >= %s 
             AND user_agent != '' 
             AND user_agent IS NOT NULL
             GROUP BY ip_address, user_agent
             HAVING access_count >= %d
             ORDER BY access_count DESC",
            $date_from,
            $min_count
        ));
        
        if (empty($logs)) {
            return array(
                'total_analyzed' => 0,
                'spiders_found' => 0,
                'nets_added' => 0,
                'details' => array()
            );
        }
        
        // 蜘蛛识别模式（与 get_spider_info_by_user_agent 保持一致）
        $spider_patterns = array(
            'googlebot' => array('spider_id' => 12, 'spider_name' => 'Google蜘蛛'),
            'bingbot' => array('spider_id' => 16, 'spider_name' => 'Bing蜘蛛'),
            'msnbot' => array('spider_id' => 16, 'spider_name' => 'Bing蜘蛛'),
            'slurp' => array('spider_id' => 15, 'spider_name' => 'Yahoo蜘蛛'),
            'baiduspider' => array('spider_id' => 13, 'spider_name' => '百度蜘蛛'),
            'sogou' => array('spider_id' => 17, 'spider_name' => '搜狗蜘蛛'),
            'yandexbot' => array('spider_id' => 18, 'spider_name' => 'Yandex蜘蛛'),
            '360spider' => array('spider_id' => 11, 'spider_name' => '360蜘蛛'),
            '360' => array('spider_id' => 11, 'spider_name' => '360蜘蛛'),
            'bytespider' => array('spider_id' => 14, 'spider_name' => '头条蜘蛛'),
            'yisouspider' => array('spider_id' => 19, 'spider_name' => '神马蜘蛛'),
            'yisou' => array('spider_id' => 19, 'spider_name' => '神马蜘蛛'),
            'semrushbot' => array('spider_id' => 20, 'spider_name' => 'SEMrush蜘蛛'),
            'ahrefsbot' => array('spider_id' => 21, 'spider_name' => 'Ahrefs蜘蛛'),
            'majestic' => array('spider_id' => 22, 'spider_name' => 'Majestic蜘蛛'),
            'dotbot' => array('spider_id' => 23, 'spider_name' => 'DotBot蜘蛛'),
            'mj12bot' => array('spider_id' => 24, 'spider_name' => 'MJ12Bot蜘蛛'),
            'petalbot' => array('spider_id' => 25, 'spider_name' => 'PetalBot蜘蛛'),
            'applebot' => array('spider_id' => 26, 'spider_name' => 'AppleBot蜘蛛'),
            'facebookexternalhit' => array('spider_id' => 27, 'spider_name' => 'Facebook爬虫'),
            'twitterbot' => array('spider_id' => 28, 'spider_name' => 'TwitterBot爬虫'),
            'linkedinbot' => array('spider_id' => 29, 'spider_name' => 'LinkedInBot爬虫'),
            'whatsapp' => array('spider_id' => 30, 'spider_name' => 'WhatsApp爬虫'),
            'telegrambot' => array('spider_id' => 31, 'spider_name' => 'TelegramBot爬虫'),
            'discordbot' => array('spider_id' => 32, 'spider_name' => 'DiscordBot爬虫'),
            'pinterest' => array('spider_id' => 33, 'spider_name' => 'Pinterest爬虫'),
            'redditbot' => array('spider_id' => 34, 'spider_name' => 'RedditBot爬虫'),
            'duckduckbot' => array('spider_id' => 35, 'spider_name' => 'DuckDuckGo蜘蛛'),
            'exabot' => array('spider_id' => 36, 'spider_name' => 'ExaBot蜘蛛'),
        );
        
        // 按蜘蛛类型分组IP地址
        $spider_ips = array();
        $spider_stats = array();
        
        foreach ($logs as $log) {
            $user_agent_lower = strtolower($log->user_agent);
            $spider_info = null;
            
            // 匹配蜘蛛模式
            foreach ($spider_patterns as $pattern => $info) {
                if (strpos($user_agent_lower, $pattern) !== false) {
                    $spider_info = $info;
                    break;
                }
            }
            
            // 如果没有匹配到已知模式，但包含常见的爬虫关键词，归类为通用爬虫
            if (!$spider_info) {
                $crawler_keywords = array('bot', 'crawler', 'spider', 'scraper', 'indexer', 'fetcher');
                foreach ($crawler_keywords as $keyword) {
                    if (strpos($user_agent_lower, $keyword) !== false) {
                        $spider_info = array('spider_id' => 99, 'spider_name' => '通用爬虫');
                        break;
                    }
                }
            }
            
            if ($spider_info) {
                $spider_id = $spider_info['spider_id'];
                $spider_name = $spider_info['spider_name'];
                
                if (!isset($spider_ips[$spider_id])) {
                    $spider_ips[$spider_id] = array();
                    $spider_stats[$spider_id] = array(
                        'spider_name' => $spider_name,
                        'ips' => array(),
                        'count' => 0
                    );
                }
                
                // 验证IP地址格式
                if (filter_var($log->ip_address, FILTER_VALIDATE_IP)) {
                    $spider_ips[$spider_id][] = $log->ip_address;
                    if (!in_array($log->ip_address, $spider_stats[$spider_id]['ips'])) {
                        $spider_stats[$spider_id]['ips'][] = $log->ip_address;
                    }
                    $spider_stats[$spider_id]['count'] += intval($log->access_count);
                }
            }
        }
        
        // 将IP地址转换为网段并添加到数据库
        $nets_added = 0;
        $spiders_found = count($spider_ips);
        $details = array();
        
        foreach ($spider_ips as $spider_id => $ips) {
            $spider_name = $spider_stats[$spider_id]['spider_name'];
            $unique_ips = array_unique($ips);
            
            // 将IP地址分组到网段
            $network_groups = array(); // 网段 => IP列表
            $ip_to_network = array();  // IP => 网段，用于统计每个网段内的IP数量
            
            foreach ($unique_ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    // IPv4: 转换为/24网段
                    $ip_parts = explode('.', $ip);
                    if (count($ip_parts) === 4) {
                        $network = $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2] . '.0/24';
                        if (!isset($network_groups[$network])) {
                            $network_groups[$network] = array();
                        }
                        $network_groups[$network][] = $ip;
                        $ip_to_network[$ip] = $network;
                    }
                } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    // IPv6: 转换为/64网段
                    $ip_obj = inet_pton($ip);
                    if ($ip_obj !== false) {
                        $network = $this->ipv6_to_cidr($ip, 64);
                        if (!isset($network_groups[$network])) {
                            $network_groups[$network] = array();
                        }
                        $network_groups[$network][] = $ip;
                        $ip_to_network[$ip] = $network;
                    }
                }
            }
            
            // 添加网段或单个IP到数据库
            $group_nets_added = 0;
            foreach ($network_groups as $network => $ips_in_network) {
                $unique_ips_in_network = array_unique($ips_in_network);
                $ip_count_in_network = count($unique_ips_in_network);
                
                // 如果网段内只有一个IP，添加单个IP（/32）
                // 如果网段内有多个IP，添加整个网段（/24）
                $final_network = $network;
                if ($ip_count_in_network == 1) {
                    // 单个IP，使用/32（IPv4）或/128（IPv6）
                    $single_ip = $unique_ips_in_network[0];
                    if (filter_var($single_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $final_network = $single_ip . '/32';
                    } elseif (filter_var($single_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $final_network = $single_ip . '/128';
                    } else {
                        $final_network = $single_ip;
                    }
                }
                
                // 检查是否已存在（检查网段或单个IP）
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$spider_table` WHERE spider_id = %d AND ip_net = %s",
                    $spider_id, $final_network
                ));
                
                // 也检查是否已被更大的网段包含
                if (!$exists) {
                    // 如果是单个IP，检查是否在已存在的网段内
                    if ($ip_count_in_network == 1) {
                        $single_ip = $unique_ips_in_network[0];
                        // 检查所有已存在的网段，看是否包含这个IP
                        $existing_nets = $wpdb->get_col($wpdb->prepare(
                            "SELECT ip_net FROM `$spider_table` WHERE spider_id = %d",
                            $spider_id
                        ));
                        
                        $already_covered = false;
                        foreach ($existing_nets as $existing_net) {
                            if ($this->ip_in_network($single_ip, $existing_net)) {
                                $already_covered = true;
                                break;
                            }
                        }
                        
                        if ($already_covered) {
                            continue;
                        }
                    }
                    
                    $result = $wpdb->insert($spider_table, array(
                        'spider_id' => $spider_id,
                        'spider_name' => $spider_name,
                        'ip_net' => $final_network
                    ));
                    
                    if ($result) {
                        $nets_added++;
                        $group_nets_added++;
                    }
                }
            }
            
            $details[] = array(
                'spider_id' => $spider_id,
                'spider_name' => $spider_name,
                'ip_count' => count($unique_ips),
                'access_count' => $spider_stats[$spider_id]['count'],
                'nets_added' => $group_nets_added
            );
        }
        
        return array(
            'total_analyzed' => count($logs),
            'spiders_found' => $spiders_found,
            'nets_added' => $nets_added,
            'details' => $details
        );
    }

    /**
     * 将IPv6地址转换为CIDR格式
     */
    private function ipv6_to_cidr($ip, $prefix_length = 64) {
        $ip_obj = inet_pton($ip);
        if ($ip_obj === false) {
            return $ip . '/' . $prefix_length;
        }
        
        // 将前缀长度的字节清零
        $bytes = intval($prefix_length / 8);
        $bits = $prefix_length % 8;
        
        for ($i = $bytes; $i < 16; $i++) {
            if ($i == $bytes && $bits > 0) {
                $mask = 0xFF << (8 - $bits);
                $ip_obj[$i] = chr(ord($ip_obj[$i]) & $mask);
            } else {
                $ip_obj[$i] = "\x00";
            }
        }
        
        $network = inet_ntop($ip_obj);
        return $network . '/' . $prefix_length;
    }

    /**
     * 获取安全统计信息（优化版本，使用单个聚合查询）
     */
    public function get_security_stats($days = 7) {
        global $wpdb;
        
        // 确保表存在
        if (!$this->ensure_table_exists()) {
            // 如果表不存在，返回空统计
            return array(
                'total_access' => 0,
                'denied_access' => 0,
                'suspicious_activity' => 0,
                'bypass_attempts' => 0,
                'blocked_ips' => 0
            );
        }
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        $start_date = date('Y-m-d H:i:s', time() - ($days * 24 * 3600));
        
        // 使用单个聚合查询获取所有统计数据（优化性能）
        // 使用 COALESCE 处理 NULL 值，确保 SUM() 返回 0 而不是 NULL
        // 注意：is_spider 字段可能为 NULL，使用 COALESCE 来处理
        $sql = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_access,
                COALESCE(SUM(CASE WHEN access_result = 'denied' THEN 1 ELSE 0 END), 0) as denied_access,
                COALESCE(SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END), 0) as suspicious_activity,
                COALESCE(SUM(CASE WHEN protection_bypassed = 1 THEN 1 ELSE 0 END), 0) as bypass_attempts,
                COALESCE(COUNT(DISTINCT CASE WHEN access_result = 'blocked' THEN ip_address END), 0) as blocked_ips,
                COALESCE(SUM(CASE WHEN is_spider = 1 THEN 1 ELSE 0 END), 0) as spider_access,
                COALESCE(SUM(CASE WHEN COALESCE(is_spider, 0) = 0 THEN 1 ELSE 0 END), 0) as user_access,
                COALESCE(SUM(CASE WHEN COALESCE(is_spider, 0) = 0 AND user_id IS NOT NULL AND user_id > 0 THEN 1 ELSE 0 END), 0) as logged_in_access,
                COALESCE(COUNT(DISTINCT ip_address), 0) as unique_ips,
                COALESCE(COUNT(DISTINCT CASE WHEN post_id IS NOT NULL AND post_id > 0 THEN post_id END), 0) as unique_posts
            FROM $table_name 
            WHERE created_at > %s",
            $start_date
        );
        
        $result = $wpdb->get_row($sql);
        
        if ($result) {
            return array(
                'total_access' => isset($result->total_access) ? intval($result->total_access) : 0,
                'denied_access' => isset($result->denied_access) && $result->denied_access !== null ? intval($result->denied_access) : 0,
                'suspicious_activity' => isset($result->suspicious_activity) && $result->suspicious_activity !== null ? intval($result->suspicious_activity) : 0,
                'bypass_attempts' => isset($result->bypass_attempts) && $result->bypass_attempts !== null ? intval($result->bypass_attempts) : 0,
                'blocked_ips' => isset($result->blocked_ips) && $result->blocked_ips !== null ? intval($result->blocked_ips) : 0,
                'spider_access' => isset($result->spider_access) && $result->spider_access !== null ? intval($result->spider_access) : 0,
                'user_access' => isset($result->user_access) && $result->user_access !== null ? intval($result->user_access) : 0,
                'logged_in_access' => isset($result->logged_in_access) && $result->logged_in_access !== null ? intval($result->logged_in_access) : 0,
                'unique_ips' => isset($result->unique_ips) && $result->unique_ips !== null ? intval($result->unique_ips) : 0,
                'unique_posts' => isset($result->unique_posts) && $result->unique_posts !== null ? intval($result->unique_posts) : 0
            );
        }
        
        // 如果查询失败，返回空统计
        return array(
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
    }

    /**
     * 按时间段获取安全统计信息
     * 
     * @param string $period_start 开始时间
     * @param string $period_end 结束时间
     * @return array 统计数据
     */
    public function get_security_stats_by_period($period_start, $period_end) {
        global $wpdb;
        
        // 确保表存在
        if (!$this->ensure_table_exists()) {
            return array(
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
        }
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $sql = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_access,
                COALESCE(SUM(CASE WHEN access_result = 'denied' THEN 1 ELSE 0 END), 0) as denied_access,
                COALESCE(SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END), 0) as suspicious_activity,
                COALESCE(SUM(CASE WHEN protection_bypassed = 1 THEN 1 ELSE 0 END), 0) as bypass_attempts,
                COALESCE(COUNT(DISTINCT CASE WHEN access_result = 'blocked' THEN ip_address END), 0) as blocked_ips,
                COALESCE(SUM(CASE WHEN is_spider = 1 THEN 1 ELSE 0 END), 0) as spider_access,
                COALESCE(SUM(CASE WHEN COALESCE(is_spider, 0) = 0 THEN 1 ELSE 0 END), 0) as user_access,
                COALESCE(SUM(CASE WHEN COALESCE(is_spider, 0) = 0 AND user_id IS NOT NULL AND user_id > 0 THEN 1 ELSE 0 END), 0) as logged_in_access,
                COALESCE(COUNT(DISTINCT ip_address), 0) as unique_ips,
                COALESCE(COUNT(DISTINCT CASE WHEN post_id IS NOT NULL AND post_id > 0 THEN post_id END), 0) as unique_posts
            FROM $table_name 
            WHERE created_at >= %s AND created_at <= %s",
            $period_start, $period_end
        );
        
        $result = $wpdb->get_row($sql);
        
        if ($result) {
            return array(
                'total_access' => intval($result->total_access),
                'denied_access' => intval($result->denied_access),
                'suspicious_activity' => intval($result->suspicious_activity),
                'bypass_attempts' => intval($result->bypass_attempts),
                'blocked_ips' => intval($result->blocked_ips),
                'spider_access' => intval($result->spider_access),
                'user_access' => intval($result->user_access),
                'logged_in_access' => intval($result->logged_in_access),
                'unique_ips' => intval($result->unique_ips),
                'unique_posts' => intval($result->unique_posts)
            );
        }
        
        return array(
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
    }

    /**
     * 获取今天的安全统计信息（从今天0点开始）
     */
    public function get_today_stats() {
        global $wpdb;
        
        // 确保表存在
        if (!$this->ensure_table_exists()) {
            // 如果表不存在，返回空统计
            error_log('Folio Security: get_today_stats - Table does not exist');
            return array(
                'total_access' => 0,
                'denied_access' => 0,
                'suspicious_activity' => 0,
                'bypass_attempts' => 0,
                'blocked_ips' => 0
            );
        }
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        // 使用WordPress时区获取今天0点的时间
        $current_time = current_time('timestamp');
        $today_start_timestamp = strtotime('today', $current_time);
        $today_start = date('Y-m-d 00:00:00', $today_start_timestamp);
        
        // 使用单个聚合查询获取今天的所有统计数据
        // 使用 COALESCE 处理 NULL 值，确保 SUM() 返回 0 而不是 NULL
        // 注意：is_spider 字段可能为 NULL，使用 COALESCE 来处理
        $sql = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_access,
                COALESCE(SUM(CASE WHEN access_result = 'denied' THEN 1 ELSE 0 END), 0) as denied_access,
                COALESCE(SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END), 0) as suspicious_activity,
                COALESCE(SUM(CASE WHEN protection_bypassed = 1 THEN 1 ELSE 0 END), 0) as bypass_attempts,
                COALESCE(COUNT(DISTINCT CASE WHEN access_result = 'blocked' THEN ip_address END), 0) as blocked_ips,
                COALESCE(SUM(CASE WHEN is_spider = 1 THEN 1 ELSE 0 END), 0) as spider_access,
                COALESCE(SUM(CASE WHEN COALESCE(is_spider, 0) = 0 THEN 1 ELSE 0 END), 0) as user_access,
                COALESCE(SUM(CASE WHEN COALESCE(is_spider, 0) = 0 AND user_id IS NOT NULL AND user_id > 0 THEN 1 ELSE 0 END), 0) as logged_in_access,
                COALESCE(COUNT(DISTINCT ip_address), 0) as unique_ips,
                COALESCE(COUNT(DISTINCT CASE WHEN post_id IS NOT NULL AND post_id > 0 THEN post_id END), 0) as unique_posts
            FROM $table_name 
            WHERE created_at >= %s",
            $today_start
        );
        
        $result = $wpdb->get_row($sql);
        
        if ($result) {
            return array(
                'total_access' => intval($result->total_access),
                'denied_access' => intval($result->denied_access),
                'suspicious_activity' => intval($result->suspicious_activity),
                'bypass_attempts' => intval($result->bypass_attempts),
                'blocked_ips' => intval($result->blocked_ips),
                'spider_access' => intval($result->spider_access),
                'user_access' => intval($result->user_access),
                'logged_in_access' => intval($result->logged_in_access),
                'unique_ips' => intval($result->unique_ips),
                'unique_posts' => intval($result->unique_posts)
            );
        }
        
        // 如果查询失败，返回空统计
        error_log('  Query returned no result, returning empty stats');
        return array(
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
    }

    /**
     * 获取访问日志
     */
    public function get_access_logs($limit = 100, $offset = 0, $filters = array()) {
        global $wpdb;
        
        // 确保表存在
        if (!$this->ensure_table_exists()) {
            error_log('Folio Access Logs Table does not exist: ' . $wpdb->prefix . self::LOG_TABLE);
            return array();
        }
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($filters['ip'])) {
            $where_conditions[] = 'ip_address = %s';
            $where_values[] = $filters['ip'];
        }
        
        if (!empty($filters['action_type'])) {
            $where_conditions[] = 'action_type = %s';
            $where_values[] = $filters['action_type'];
        }
        
        if (!empty($filters['suspicious_only'])) {
            $where_conditions[] = 'is_suspicious = 1';
        }
        
        if (isset($filters['is_spider'])) {
            $where_conditions[] = 'is_spider = %d';
            $where_values[] = intval($filters['is_spider']);
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // 限制最大查询数量，防止超时
        $limit = min($limit, 500);
        
        $sql = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        $results = $wpdb->get_results($sql);
        
        // 如果查询失败，记录错误并返回空数组
        if ($results === false && !empty($wpdb->last_error)) {
            error_log('Folio Security: Query error: ' . $wpdb->last_error);
            return array();
        }
        
        return $results ?: array();
    }
    
    /**
     * 获取访问日志总数（用于分页）
     */
    public function get_access_logs_count($filters = array()) {
        global $wpdb;
        
        // 确保表存在
        if (!$this->ensure_table_exists()) {
            return 0;
        }
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($filters['ip'])) {
            $where_conditions[] = 'ip_address = %s';
            $where_values[] = $filters['ip'];
        }
        
        if (!empty($filters['action_type'])) {
            $where_conditions[] = 'action_type = %s';
            $where_values[] = $filters['action_type'];
        }
        
        if (!empty($filters['suspicious_only'])) {
            $where_conditions[] = 'is_suspicious = 1';
        }
        
        if (isset($filters['is_spider'])) {
            $where_conditions[] = 'is_spider = %d';
            $where_values[] = intval($filters['is_spider']);
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        $count = $wpdb->get_var($sql);
        
        // 如果查询失败，返回0
        if ($count === false && !empty($wpdb->last_error)) {
            error_log('Folio Security: Count query error: ' . $wpdb->last_error);
            return 0;
        }
        
        return intval($count);
    }
}

// 初始化安全保护管理器
add_action('init', function() {
    folio_Security_Protection_Manager::get_instance();
}, 1);

/**
 * 全局辅助函数
 */

if (!function_exists('folio_get_security_stats')) {
    function folio_get_security_stats($days = 7) {
        $manager = folio_Security_Protection_Manager::get_instance();
        return $manager->get_security_stats($days);
    }
}

if (!function_exists('folio_get_access_logs')) {
    function folio_get_access_logs($limit = 100, $offset = 0, $filters = array()) {
        $manager = folio_Security_Protection_Manager::get_instance();
        return $manager->get_access_logs($limit, $offset, $filters);
    }
}
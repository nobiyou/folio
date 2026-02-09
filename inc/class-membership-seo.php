<?php
/**
 * Membership SEO Optimization
 * 
 * 会员内容SEO优化 - 为会员专属内容提供搜索引擎友好的元数据和结构化数据
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Membership_SEO {

    public function __construct() {
        // SEO元数据优化
        add_action('wp_head', array($this, 'output_membership_meta_tags'), 2);
        add_filter('document_title_parts', array($this, 'modify_title_for_protected_content'));
        
        // Open Graph标签
        add_action('wp_head', array($this, 'output_open_graph_tags'), 3);
        
        // 结构化数据增强
        add_filter('folio_article_schema', array($this, 'enhance_article_schema'), 10, 2);
        
        // 搜索引擎爬虫特殊处理
        add_action('init', array($this, 'handle_search_engine_crawlers'));
        
        // XML站点地图优化
        add_filter('wp_sitemaps_posts_entry', array($this, 'modify_sitemap_entry'), 10, 4);
        add_filter('wp_sitemaps_posts_query_args', array($this, 'modify_sitemap_query'));
        
        // robots.txt优化
        add_filter('robots_txt', array($this, 'modify_robots_txt'), 10, 2);
        
        // 防止内容泄露的安全措施
        add_action('template_redirect', array($this, 'prevent_content_leakage'));
    }

    /**
     * 输出会员内容专用的meta标签
     */
    public function output_membership_meta_tags() {
        if (!is_singular('post')) {
            return;
        }

        global $post;
        $protection_info = folio_get_article_protection_info($post->ID);
        
        if (!$protection_info['is_protected']) {
            return;
        }

        $user_id = get_current_user_id();
        $can_access = folio_can_user_access_article($post->ID, $user_id);
        
        // 为搜索引擎提供适当的描述
        if ($protection_info['seo_visible']) {
            $description = $this->get_seo_description($post, $protection_info, $can_access);
            if ($description) {
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            }
            
            // 添加会员内容标识
            $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
            echo '<meta name="folio:membership-level" content="' . esc_attr($protection_info['required_level']) . '">' . "\n";
            echo '<meta name="folio:content-type" content="premium">' . "\n";
            echo '<meta name="folio:access-required" content="' . esc_attr($level_name) . '">' . "\n";
        } else {
            // 对于SEO不可见的内容，使用通用描述
            echo '<meta name="description" content="此内容为会员专属，需要登录查看。">' . "\n";
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }

        // 添加付费内容标识
        echo '<meta name="folio:paywall" content="true">' . "\n";
        
        // 如果用户无权访问，添加相应的meta标签
        if (!$can_access) {
            echo '<meta name="folio:access-denied" content="true">' . "\n";
            
            if (!$user_id) {
                echo '<meta name="folio:login-required" content="true">' . "\n";
            } else {
                echo '<meta name="folio:upgrade-required" content="' . esc_attr($protection_info['required_level']) . '">' . "\n";
            }
        }
    }

    /**
     * 修改受保护内容的页面标题
     */
    public function modify_title_for_protected_content($title_parts) {
        if (!is_singular('post')) {
            return $title_parts;
        }

        global $post;
        $protection_info = folio_get_article_protection_info($post->ID);
        
        if (!$protection_info['is_protected']) {
            return $title_parts;
        }

        $user_id = get_current_user_id();
        $can_access = folio_can_user_access_article($post->ID, $user_id);
        
        // 如果用户无法访问且SEO可见，在标题中添加会员标识
        if (!$can_access && $protection_info['seo_visible']) {
            $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
            $title_parts['title'] = $title_parts['title'] . ' [' . $level_name . '专属]';
        }

        return $title_parts;
    }

    /**
     * 输出Open Graph标签
     */
    public function output_open_graph_tags() {
        if (!is_singular('post')) {
            return;
        }

        global $post;
        $protection_info = folio_get_article_protection_info($post->ID);
        
        if (!$protection_info['is_protected']) {
            return;
        }

        $user_id = get_current_user_id();
        $can_access = folio_can_user_access_article($post->ID, $user_id);
        
        // 基础Open Graph标签
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . "\n";
        
        // 标题处理
        $og_title = get_the_title();
        if (!$can_access && $protection_info['seo_visible']) {
            $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
            $og_title .= ' [' . $level_name . '专属]';
        }
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        
        // 描述处理
        if ($protection_info['seo_visible']) {
            $og_description = $this->get_seo_description($post, $protection_info, $can_access);
        } else {
            $og_description = '此内容为会员专属，需要登录查看完整内容。';
        }
        
        if ($og_description) {
            echo '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
        }

        // 图片处理
        if (has_post_thumbnail()) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'large');
            echo '<meta property="og:image" content="' . esc_url($image_url) . '">' . "\n";
            
            // 添加图片尺寸信息
            $image_id = get_post_thumbnail_id($post->ID);
            $image_data = wp_get_attachment_image_src($image_id, 'large');
            if ($image_data) {
                echo '<meta property="og:image:width" content="' . esc_attr($image_data[1]) . '">' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr($image_data[2]) . '">' . "\n";
            }
        }

        // 文章特定的Open Graph标签
        echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c')) . '">' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c')) . '">' . "\n";
        echo '<meta property="article:author" content="' . esc_attr(get_the_author()) . '">' . "\n";
        
        // 分类标签
        $categories = get_the_category();
        if (!empty($categories)) {
            foreach ($categories as $category) {
                echo '<meta property="article:section" content="' . esc_attr($category->name) . '">' . "\n";
            }
        }
        
        // 标签
        $tags = get_the_tags();
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                echo '<meta property="article:tag" content="' . esc_attr($tag->name) . '">' . "\n";
            }
        }

        // 会员内容特定标签
        echo '<meta property="folio:membership_required" content="true">' . "\n";
        echo '<meta property="folio:membership_level" content="' . esc_attr($protection_info['required_level']) . '">' . "\n";
        
        if (!$can_access) {
            echo '<meta property="folio:access_restricted" content="true">' . "\n";
        }

        // Twitter Card标签
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '">' . "\n";
        if ($og_description) {
            echo '<meta name="twitter:description" content="' . esc_attr($og_description) . '">' . "\n";
        }
        if (has_post_thumbnail()) {
            echo '<meta name="twitter:image" content="' . esc_url(get_the_post_thumbnail_url($post->ID, 'large')) . '">' . "\n";
        }
    }

    /**
     * 增强文章的结构化数据
     */
    public function enhance_article_schema($schema, $post_id) {
        $protection_info = folio_get_article_protection_info($post_id);
        
        if (!$protection_info['is_protected']) {
            return $schema;
        }

        $user_id = get_current_user_id();
        $can_access = folio_can_user_access_article($post_id, $user_id);

        // 添加付费内容标识
        $schema['@type'] = array('Article', 'CreativeWork');
        $schema['isAccessibleForFree'] = false;
        $schema['hasPart'] = array(
            '@type' => 'WebPageElement',
            'isAccessibleForFree' => false,
            'cssSelector' => '.folio-protected-article'
        );

        // 添加会员要求信息
        $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
        $schema['audience'] = array(
            '@type' => 'Audience',
            'audienceType' => 'Premium Members',
            'name' => $level_name . ' Members'
        );

        // 如果有预览内容，添加预览信息
        if ($protection_info['preview_mode'] !== 'none' && $protection_info['seo_visible']) {
            $post = get_post($post_id);
            $preview_content = folio_generate_article_preview($post->post_content, $protection_info);
            
            if ($preview_content) {
                $schema['abstract'] = wp_strip_all_tags($preview_content);
            }
        }

        // 添加访问要求信息
        $schema['conditionsOfAccess'] = '需要' . $level_name . '会员等级';
        
        // 如果用户无法访问，修改描述
        if (!$can_access && isset($schema['description'])) {
            $schema['description'] = $this->get_seo_description(get_post($post_id), $protection_info, $can_access);
        }

        // 添加付费墙信息
        $schema['isPartOf'] = array(
            '@type' => 'CreativeWork',
            'name' => get_bloginfo('name') . ' 会员内容',
            'description' => '需要会员订阅才能访问的专属内容'
        );

        return $schema;
    }

    /**
     * 处理搜索引擎爬虫的特殊访问
     */
    public function handle_search_engine_crawlers() {
        if (!$this->is_search_engine_crawler()) {
            return;
        }

        // 为搜索引擎爬虫提供特殊的内容处理
        add_filter('the_content', array($this, 'filter_content_for_crawlers'), 1);
        add_filter('the_excerpt', array($this, 'filter_excerpt_for_crawlers'), 1);
    }

    /**
     * 为搜索引擎爬虫过滤内容
     */
    public function filter_content_for_crawlers($content) {
        if (!is_singular('post') || !$this->is_search_engine_crawler()) {
            return $content;
        }

        global $post;
        $protection_info = folio_get_article_protection_info($post->ID);
        
        if (!$protection_info['is_protected']) {
            return $content;
        }

        // 如果SEO可见，提供预览内容
        if ($protection_info['seo_visible']) {
            $preview = folio_generate_article_preview($content, $protection_info);
            
            if ($preview) {
                return wpautop($preview) . "\n\n" . 
                       '<p><em>此内容为会员专属，需要' . 
                       ($protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP') . 
                       '等级查看完整内容。</em></p>';
            }
        }

        return '<p>此内容为会员专属，需要登录查看。</p>';
    }

    /**
     * 为搜索引擎爬虫过滤摘要
     */
    public function filter_excerpt_for_crawlers($excerpt) {
        if (!$this->is_search_engine_crawler()) {
            return $excerpt;
        }

        global $post;
        if (!$post) {
            return $excerpt;
        }

        $protection_info = folio_get_article_protection_info($post->ID);
        
        if (!$protection_info['is_protected']) {
            return $excerpt;
        }

        $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
        return '此内容为' . $level_name . '会员专属。' . ($excerpt ? ' ' . $excerpt : '');
    }

    /**
     * 修改XML站点地图条目
     */
    public function modify_sitemap_entry($sitemap_entry, $post, $post_type, $sitemap) {
        if ($post->post_type !== 'post') {
            return $sitemap_entry;
        }

        $protection_info = folio_get_article_protection_info($post->ID);
        
        if (!$protection_info['is_protected']) {
            return $sitemap_entry;
        }

        // 如果SEO不可见，从站点地图中排除
        if (!$protection_info['seo_visible']) {
            return false;
        }

        // 为受保护的内容降低优先级
        $sitemap_entry['priority'] = 0.6;
        
        // 添加自定义属性（如果支持）
        $sitemap_entry['membership_required'] = $protection_info['required_level'];

        return $sitemap_entry;
    }

    /**
     * 修改站点地图查询参数
     */
    public function modify_sitemap_query($args) {
        // 确保包含受保护的文章（除非明确设置为SEO不可见）
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => '_folio_premium_content',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_folio_seo_visible',
                'value' => '0',
                'compare' => '!='
            )
        );

        return $args;
    }

    /**
     * 修改robots.txt
     */
    public function modify_robots_txt($output, $public) {
        if (!$public) {
            return $output;
        }

        // 添加会员内容相关的robots指令
        $output .= "\n# Membership Content Guidelines\n";
        $output .= "# Allow indexing of preview content but respect paywall\n";
        $output .= "User-agent: *\n";
        $output .= "Allow: /wp-content/themes/folio/assets/\n";
        
        // 如果有特定的会员内容路径，可以在这里添加规则
        $output .= "\n# Premium content access\n";
        $output .= "# Crawl delay for premium content\n";
        $output .= "Crawl-delay: 10\n";

        return $output;
    }

    /**
     * 防止内容泄露
     */
    public function prevent_content_leakage() {
        if (!is_singular('post')) {
            return;
        }

        global $post;
        $protection_info = folio_get_article_protection_info($post->ID);
        
        if (!$protection_info['is_protected']) {
            return;
        }

        $user_id = get_current_user_id();
        $can_access = folio_can_user_access_article($post->ID, $user_id);

        // 如果用户无权访问且内容不应该对SEO可见
        if (!$can_access && !$protection_info['seo_visible']) {
            // 设置noindex, nofollow
            add_action('wp_head', function() {
                echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
            });
        }

        // 防止通过特殊参数绕过保护
        if (isset($_GET['preview']) || isset($_GET['debug']) || isset($_GET['raw'])) {
            if (!current_user_can('manage_options')) {
                wp_die('访问被拒绝', '权限不足', array('response' => 403));
            }
        }
    }

    /**
     * 获取SEO描述
     */
    private function get_seo_description($post, $protection_info, $can_access) {
        if ($can_access) {
            // 用户有权限，返回正常描述
            if (has_excerpt($post->ID)) {
                return get_the_excerpt($post);
            }
            
            $content = wp_strip_all_tags($post->post_content);
            return wp_trim_words($content, 30, '...');
        }

        // 用户无权限，根据预览设置返回描述
        if ($protection_info['preview_mode'] !== 'none') {
            $preview = folio_generate_article_preview($post->post_content, $protection_info);
            if ($preview) {
                $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
                return wp_trim_words(wp_strip_all_tags($preview), 25, '...') . 
                       ' [需要' . $level_name . '会员查看完整内容]';
            }
        }

        $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
        return '此内容为' . $level_name . '会员专属，包含高质量的专业内容。立即升级会员解锁完整内容。';
    }

    /**
     * 检测是否为搜索引擎爬虫
     */
    private function is_search_engine_crawler() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        
        $crawlers = array(
            'googlebot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandexbot',
            'sogou',
            'facebookexternalhit',
            'twitterbot',
            'linkedinbot',
            'whatsapp',
            'telegrambot'
        );

        foreach ($crawlers as $crawler) {
            if (strpos($user_agent, $crawler) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取会员内容统计（用于SEO分析）
     */
    public static function get_seo_stats() {
        global $wpdb;

        $stats = array();

        // 统计受保护文章数量
        $protected_posts = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm 
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE pm.meta_key = '_folio_premium_content' 
            AND pm.meta_value = '1' 
            AND p.post_status = 'publish' 
            AND p.post_type = 'post'
        ");

        $stats['protected_posts'] = intval($protected_posts);

        // 统计SEO可见的受保护文章
        $seo_visible_posts = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm1 
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
            INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID 
            WHERE pm1.meta_key = '_folio_premium_content' 
            AND pm1.meta_value = '1' 
            AND pm2.meta_key = '_folio_seo_visible' 
            AND pm2.meta_value != '0' 
            AND p.post_status = 'publish' 
            AND p.post_type = 'post'
        ");

        $stats['seo_visible_posts'] = intval($seo_visible_posts);

        // 按会员等级统计
        $vip_posts = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm1 
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
            INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID 
            WHERE pm1.meta_key = '_folio_premium_content' 
            AND pm1.meta_value = '1' 
            AND pm2.meta_key = '_folio_required_level' 
            AND pm2.meta_value = 'vip' 
            AND p.post_status = 'publish' 
            AND p.post_type = 'post'
        ");

        $svip_posts = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm1 
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
            INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID 
            WHERE pm1.meta_key = '_folio_premium_content' 
            AND pm1.meta_value = '1' 
            AND pm2.meta_key = '_folio_required_level' 
            AND pm2.meta_value = 'svip' 
            AND p.post_status = 'publish' 
            AND p.post_type = 'post'
        ");

        $stats['vip_posts'] = intval($vip_posts);
        $stats['svip_posts'] = intval($svip_posts);

        return $stats;
    }
}

// 初始化会员SEO优化
new folio_Membership_SEO();

/**
 * 全局辅助函数
 */

if (!function_exists('folio_get_membership_seo_stats')) {
    function folio_get_membership_seo_stats() {
        return folio_Membership_SEO::get_seo_stats();
    }
}
<?php
/**
 * Membership Sitemap Enhancement
 * 
 * 会员内容XML站点地图增强 - 为会员专属内容提供优化的站点地图配置
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Membership_Sitemap {

    public function __construct() {
        // 增强WordPress核心站点地图
        add_filter('wp_sitemaps_enabled', '__return_true');
        add_filter('wp_sitemaps_max_urls', array($this, 'increase_sitemap_urls'));
        
        // 自定义会员内容站点地图
        add_action('init', array($this, 'add_membership_sitemap_provider'));
        
        // 修改现有站点地图条目
        add_filter('wp_sitemaps_posts_entry', array($this, 'enhance_post_sitemap_entry'), 10, 4);
        add_filter('wp_sitemaps_posts_query_args', array($this, 'modify_posts_query_args'), 10, 2);
        
        // 添加自定义站点地图索引
        add_filter('wp_sitemaps_index_entry', array($this, 'add_membership_index_entry'), 10, 4);
        
        // 处理会员内容的特殊URL
        add_action('template_redirect', array($this, 'handle_membership_sitemap_requests'));
    }

    /**
     * 增加站点地图URL数量限制
     */
    public function increase_sitemap_urls($max_urls) {
        return 2000; // 增加到2000个URL
    }

    /**
     * 添加会员内容站点地图提供者
     */
    public function add_membership_sitemap_provider() {
        if (!class_exists('WP_Sitemaps_Registry')) {
            return;
        }

        $sitemaps = wp_sitemaps_get_server();
        if (!$sitemaps) {
            return;
        }

        // 注册会员内容站点地图
        $sitemaps->registry->add_provider('membership', new folio_Membership_Sitemap_Provider());
    }

    /**
     * 增强文章站点地图条目
     */
    public function enhance_post_sitemap_entry($sitemap_entry, $post, $post_type, $sitemap) {
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

        // 调整受保护内容的优先级和更新频率
        $sitemap_entry['priority'] = 0.7; // 稍微降低优先级
        $sitemap_entry['changefreq'] = 'weekly'; // 降低更新频率

        return $sitemap_entry;
    }

    /**
     * 修改文章查询参数
     */
    public function modify_posts_query_args($args, $post_type) {
        if ($post_type !== 'post') {
            return $args;
        }

        // 确保包含SEO可见的受保护文章
        $args['meta_query'] = array(
            'relation' => 'OR',
            // 非受保护文章
            array(
                'key' => '_folio_premium_content',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_folio_premium_content',
                'value' => '1',
                'compare' => '!='
            ),
            // 受保护但SEO可见的文章
            array(
                'relation' => 'AND',
                array(
                    'key' => '_folio_premium_content',
                    'value' => '1',
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_folio_seo_visible',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_folio_seo_visible',
                        'value' => '0',
                        'compare' => '!='
                    )
                )
            )
        );

        return $args;
    }

    /**
     * 添加会员内容到站点地图索引
     */
    public function add_membership_index_entry($sitemap_entry, $sitemap_name, $sitemap_type, $page) {
        // 这里可以添加自定义的会员内容站点地图到索引
        return $sitemap_entry;
    }

    /**
     * 处理会员内容站点地图请求
     */
    public function handle_membership_sitemap_requests() {
        if (!isset($_GET['sitemap']) || $_GET['sitemap'] !== 'membership') {
            return;
        }

        $this->output_membership_sitemap();
        exit;
    }

    /**
     * 输出会员内容专用站点地图
     */
    private function output_membership_sitemap() {
        header('Content-Type: application/xml; charset=UTF-8');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // 获取所有受保护且SEO可见的文章
        $protected_posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_folio_premium_content',
                    'value' => '1',
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_folio_seo_visible',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_folio_seo_visible',
                        'value' => '0',
                        'compare' => '!='
                    )
                )
            )
        ));

        foreach ($protected_posts as $post) {
            $protection_info = folio_get_article_protection_info($post->ID);
            
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_url(get_permalink($post->ID)) . '</loc>' . "\n";
            echo '    <lastmod>' . esc_xml(get_the_modified_date('c', $post->ID)) . '</lastmod>' . "\n";
            echo '    <changefreq>weekly</changefreq>' . "\n";
            echo '    <priority>0.7</priority>' . "\n";
            
            // 添加自定义属性（如果需要）
            echo '    <!-- Membership Level: ' . esc_xml($protection_info['required_level']) . ' -->' . "\n";
            
            echo '  </url>' . "\n";
        }

        echo '</urlset>' . "\n";
    }
}

/**
 * 会员内容站点地图提供者
 */
class folio_Membership_Sitemap_Provider extends WP_Sitemaps_Provider {

    /**
     * 提供者名称
     */
    public function get_name() {
        return 'membership';
    }

    /**
     * 获取对象子类型
     */
    public function get_object_subtypes() {
        return array('premium', 'vip', 'svip');
    }

    /**
     * 获取URL列表
     */
    public function get_url_list($page_num, $object_subtype = '') {
        $urls = array();

        // 根据子类型获取不同的URL
        switch ($object_subtype) {
            case 'vip':
                $urls = $this->get_membership_urls('vip', $page_num);
                break;
            case 'svip':
                $urls = $this->get_membership_urls('svip', $page_num);
                break;
            default:
                $urls = $this->get_membership_urls('', $page_num);
                break;
        }

        return $urls;
    }

    /**
     * 获取最大页数
     */
    public function get_max_num_pages($object_subtype = '') {
        $count = $this->get_membership_posts_count($object_subtype);
        return (int) ceil($count / wp_sitemaps_get_max_urls($this->get_name()));
    }

    /**
     * 获取会员内容URL
     */
    private function get_membership_urls($level = '', $page_num = 1) {
        $urls = array();
        $per_page = wp_sitemaps_get_max_urls($this->get_name());
        $offset = ($page_num - 1) * $per_page;

        $meta_query = array(
            'relation' => 'AND',
            array(
                'key' => '_folio_premium_content',
                'value' => '1',
                'compare' => '='
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => '_folio_seo_visible',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_folio_seo_visible',
                    'value' => '0',
                    'compare' => '!='
                )
            )
        );

        if ($level) {
            $meta_query[] = array(
                'key' => '_folio_required_level',
                'value' => $level,
                'compare' => '='
            );
        }

        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => $per_page,
            'offset' => $offset,
            'meta_query' => $meta_query,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        foreach ($posts as $post) {
            $urls[] = array(
                'loc' => get_permalink($post->ID),
                'lastmod' => get_the_modified_date('c', $post->ID),
                'changefreq' => 'weekly',
                'priority' => 0.7
            );
        }

        return $urls;
    }

    /**
     * 获取会员文章数量
     */
    private function get_membership_posts_count($level = '') {
        $meta_query = array(
            'relation' => 'AND',
            array(
                'key' => '_folio_premium_content',
                'value' => '1',
                'compare' => '='
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => '_folio_seo_visible',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_folio_seo_visible',
                    'value' => '0',
                    'compare' => '!='
                )
            )
        );

        if ($level) {
            $meta_query[] = array(
                'key' => '_folio_required_level',
                'value' => $level,
                'compare' => '='
            );
        }

        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'meta_query' => $meta_query,
            'fields' => 'ids',
            'no_found_rows' => false
        ));

        return $query->found_posts;
    }
}

// 初始化会员站点地图增强
new folio_Membership_Sitemap();
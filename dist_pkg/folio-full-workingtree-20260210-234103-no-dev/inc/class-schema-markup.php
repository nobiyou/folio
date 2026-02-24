<?php
/**
 * Schema.org Structured Data Markup
 * 
 * 为网站添加结构化数据标记，提升SEO效果
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Schema_Markup {

    public function __construct() {
        // 在 wp_head 中输出结构化数据
        add_action('wp_head', array($this, 'output_schema_markup'));
        
        // 为文章内容添加微数据属性
        add_filter('the_content', array($this, 'add_microdata_to_content'));
    }

    /**
     * 输出结构化数据标记
     */
    public function output_schema_markup() {
        if (is_singular('post')) {
            $this->output_article_schema();
        } elseif (is_home() || is_category() || is_tag() || is_archive()) {
            $this->output_blog_schema();
        } elseif (is_front_page()) {
            $this->output_website_schema();
        }
        
        // 始终输出组织信息
        $this->output_organization_schema();
    }

    /**
     * 文章页面的结构化数据
     */
    private function output_article_schema() {
        global $post;
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title(),
            'description' => $this->get_post_description(),
            'url' => get_permalink(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author(),
                'url' => get_author_posts_url(get_the_author_meta('ID'))
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url(),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => $this->get_site_logo()
                )
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink()
            )
        );

        // 应用会员内容增强
        $schema = apply_filters('folio_article_schema', $schema, $post->ID);

        // 添加特色图片
        if (has_post_thumbnail()) {
            $image_id = get_post_thumbnail_id();
            // 使用 wp_get_attachment_image_url 确保应用所有过滤器（包括 CDN 转换）
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            if ($image_url) {
                $image_data = wp_get_attachment_image_src($image_id, 'full');
                $schema['image'] = array(
                    '@type' => 'ImageObject',
                    'url' => $image_url, // 使用经过过滤器处理的 URL
                    'width' => $image_data[1],
                    'height' => $image_data[2]
                );
            }
        }

        // 添加分类信息
        $categories = get_the_category();
        if (!empty($categories)) {
            $schema['articleSection'] = $categories[0]->name;
            $schema['about'] = array(
                '@type' => 'Thing',
                'name' => $categories[0]->name
            );
        }

        // 添加标签
        $tags = get_the_tags();
        if (!empty($tags)) {
            $keywords = array();
            foreach ($tags as $tag) {
                $keywords[] = $tag->name;
            }
            $schema['keywords'] = implode(', ', $keywords);
        }

        // 添加统计数据
        $schema['interactionStatistic'] = array(
            array(
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/ReadAction',
                'userInteractionCount' => folio_get_views()
            ),
            array(
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/LikeAction',
                'userInteractionCount' => folio_get_likes()
            )
        );

        // 添加图片数量信息
        $image_count = folio_get_post_image_count();
        if ($image_count > 0) {
            $schema['associatedMedia'] = array(
                '@type' => 'MediaGallery',
                'numberOfItems' => $image_count
            );
        }

        $this->output_json_ld($schema);
    }

    /**
     * 博客/分类页面的结构化数据
     */
    private function output_blog_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            'name' => $this->get_page_title(),
            'description' => $this->get_page_description(),
            'url' => $this->get_current_url(),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url()
            )
        );

        // 如果是分类页面，添加分类信息
        if (is_category()) {
            $category = get_queried_object();
            $schema['about'] = array(
                '@type' => 'Thing',
                'name' => $category->name,
                'description' => $category->description
            );
        }

        $this->output_json_ld($schema);
    }

    /**
     * 网站首页的结构化数据
     */
    private function output_website_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url(),
            'potentialAction' => array(
                '@type' => 'SearchAction',
                'target' => array(
                    '@type' => 'EntryPoint',
                    'urlTemplate' => home_url('/?s={search_term_string}')
                ),
                'query-input' => 'required name=search_term_string'
            )
        );

        $this->output_json_ld($schema);
    }

    /**
     * 组织信息的结构化数据
     */
    private function output_organization_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url(),
            'description' => get_bloginfo('description'),
            'logo' => array(
                '@type' => 'ImageObject',
                'url' => $this->get_site_logo()
            )
        );

        // 添加社交媒体链接
        $social_links = $this->get_social_links();
        if (!empty($social_links)) {
            $schema['sameAs'] = $social_links;
        }

        $this->output_json_ld($schema);
    }

    /**
     * 为内容添加微数据属性
     */
    public function add_microdata_to_content($content) {
        if (!is_singular('post')) {
            return $content;
        }

        // 为文章内容添加 articleBody 属性
        $content = '<div itemscope itemtype="https://schema.org/Article">' . 
                   '<div itemprop="articleBody">' . $content . '</div>' . 
                   '</div>';

        return $content;
    }

    /**
     * 输出 JSON-LD 格式的结构化数据
     */
    private function output_json_ld($schema) {
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n" . '</script>' . "\n";
    }

    /**
     * 获取文章描述
     */
    private function get_post_description() {
        if (has_excerpt()) {
            return get_the_excerpt();
        }
        
        $content = get_the_content();
        $content = wp_strip_all_tags($content);
        return wp_trim_words($content, 30, '...');
    }

    /**
     * 获取页面标题
     */
    private function get_page_title() {
        if (is_category()) {
            return single_cat_title('', false);
        } elseif (is_tag()) {
            return single_tag_title('', false);
        } elseif (is_home()) {
            return get_bloginfo('name');
        }
        
        return get_the_title();
    }

    /**
     * 获取页面描述
     */
    private function get_page_description() {
        if (is_category()) {
            $description = category_description();
            if ($description) {
                return wp_strip_all_tags($description);
            }
        } elseif (is_tag()) {
            $description = tag_description();
            if ($description) {
                return wp_strip_all_tags($description);
            }
        }
        
        return get_bloginfo('description');
    }

    /**
     * 获取当前页面URL
     */
    private function get_current_url() {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request));
    }

    /**
     * 获取网站Logo
     */
    private function get_site_logo() {
        // 优先使用主题选项中的 site_logo
        $theme_options = get_option('folio_theme_options', array());
        if (!empty($theme_options['site_logo'])) {
            $logo_url = $theme_options['site_logo'];
            // 如果是附件 URL，应用过滤器确保 CDN 转换生效
            if (strpos($logo_url, home_url('/wp-content/uploads/')) !== false) {
                // 尝试获取附件 ID 并应用过滤器
                $attachment_id = attachment_url_to_postid($logo_url);
                if ($attachment_id) {
                    $logo_url = wp_get_attachment_url($attachment_id);
                } else {
                    // 应用 wp_get_attachment_url 过滤器
                    $logo_url = apply_filters('wp_get_attachment_url', $logo_url);
                }
            }
            return $logo_url;
        }
        
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            // 使用 wp_get_attachment_image_url 确保应用所有过滤器（包括 CDN 转换）
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                return $logo_url;
            }
        }
        
        // 如果没有自定义Logo，返回默认图片或网站图标
        $site_icon = get_site_icon_url();
        if ($site_icon) {
            // get_site_icon_url() 已经应用了过滤器，直接返回
            return $site_icon;
        }
        
        // 返回默认占位符
        return home_url('/wp-content/themes/folio/assets/images/logo.png');
    }

    /**
     * 获取社交媒体链接
     * 从主题设置页面获取（Customizer 中的社交链接设置已移除）
     */
    private function get_social_links() {
        $social_links = array();
        $links = folio_get_social_links();
        
        // 从主题设置获取社交链接（只获取URL，不包含图标）
        if (!empty($links['instagram']['url'])) {
            $social_links[] = $links['instagram']['url'];
        }
        
        if (!empty($links['linkedin']['url'])) {
            $social_links[] = $links['linkedin']['url'];
        }
        
        if (!empty($links['twitter']['url'])) {
            $social_links[] = $links['twitter']['url'];
        }
        
        if (!empty($links['facebook']['url'])) {
            $social_links[] = $links['facebook']['url'];
        }
        
        if (!empty($links['github']['url'])) {
            $social_links[] = $links['github']['url'];
        }
        
        return $social_links;
    }
}

// 初始化
new folio_Schema_Markup();
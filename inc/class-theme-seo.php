<?php
/**
 * Theme Built-in SEO
 * 
 * 内置 SEO 功能，自动生成 meta 标签
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Theme_SEO {

    private $separator = '-';

    public function __construct() {
        // 只在启用内置 SEO 时加载
        if (!apply_filters('folio_enable_seo', true)) {
            return;
        }

        $this->separator = apply_filters('folio_seo_separator', '-');

        add_action('wp_head', array($this, 'output_meta_tags'), 1);
        add_filter('document_title_separator', array($this, 'title_separator'));
        add_filter('pre_get_document_title', array($this, 'custom_title'), 99);
    }

    /**
     * 输出 meta 标签
     */
    public function output_meta_tags() {
        $keywords = '';
        $description = '';

        if (is_front_page() || is_home()) {
            // 首页
            // 从主题设置读取
            $theme_options = get_option('folio_theme_options', array());
            $keywords = isset($theme_options['seo_keywords']) ? $theme_options['seo_keywords'] : '';
            $description = isset($theme_options['seo_description']) && !empty($theme_options['seo_description']) 
                ? $theme_options['seo_description'] 
                : get_bloginfo('description');

        } elseif (is_singular('portfolio')) {
            // 作品详情页
            global $post;
            
            // 优先使用 AI 生成的 SEO 内容
            $ai_keywords = get_post_meta($post->ID, '_folio_seo_keywords', true);
            $ai_description = get_post_meta($post->ID, '_folio_seo_description', true);
            
            if (!empty($ai_keywords)) {
                $keywords = $ai_keywords;
            } else {
                // 回退：从分类获取关键词
                $terms = get_the_terms($post->ID, 'portfolio_category');
                if ($terms && !is_wp_error($terms)) {
                    $term_names = wp_list_pluck($terms, 'name');
                    $keywords = implode(',', $term_names);
                }
            }
            
            if (!empty($ai_description)) {
                $description = $ai_description;
            } else {
                // 回退：从摘要获取描述
                $excerpt = get_the_excerpt($post->ID);
                if (empty($excerpt)) {
                    $excerpt = wp_trim_words(strip_shortcodes($post->post_content), 120, '');
                }
                $description = $excerpt;
            }

        } elseif (is_tax('portfolio_category')) {
            // 分类归档页
            $term = get_queried_object();
            $keywords = $term->name . ',' . $term->slug;
            $description = $term->description ?: sprintf(__('%s 分类下的所有作品', 'folio'), $term->name);

        } elseif (is_post_type_archive('portfolio')) {
            // 作品归档页
            $keywords = __('作品集,portfolio', 'folio');
            $description = __('浏览所有作品', 'folio');

        } elseif (is_search()) {
            // 搜索页
            $keywords = get_search_query();
            $description = sprintf(__('搜索 "%s" 的结果', 'folio'), get_search_query());

        } elseif (is_404()) {
            // 404 页面
            $description = __('页面未找到', 'folio');
        }

        // 输出 meta 标签
        if (!empty($keywords)) {
            echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
        }
        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }

        // 输出 canonical URL
        $this->output_canonical();
    }

    /**
     * 输出 canonical URL
     */
    private function output_canonical() {
        $canonical = '';

        if (is_singular()) {
            $canonical = get_permalink();
        } elseif (is_front_page()) {
            $canonical = home_url('/');
        } elseif (is_tax() || is_category() || is_tag()) {
            $canonical = get_term_link(get_queried_object());
        } elseif (is_post_type_archive()) {
            $canonical = get_post_type_archive_link(get_post_type());
        }

        if (!empty($canonical) && !is_wp_error($canonical)) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
        }
    }

    /**
     * 自定义标题分隔符
     */
    public function title_separator() {
        return $this->separator;
    }

    /**
     * 自定义页面标题
     */
    public function custom_title($title) {
        if (is_front_page()) {
            // 从主题设置读取
            $theme_options = get_option('folio_theme_options', array());
            $custom_title = isset($theme_options['seo_title']) && !empty($theme_options['seo_title']) 
                ? $theme_options['seo_title'] 
                : '';
            
            if (!empty($custom_title)) {
                return $custom_title;
            }
            return get_bloginfo('name') . ' ' . $this->separator . ' ' . get_bloginfo('description');
        }

        return $title;
    }
}

// 初始化
new folio_Theme_SEO();

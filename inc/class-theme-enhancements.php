<?php
/**
 * Theme Enhancements
 * 
 * 主题功能增强 - 图片懒加载、返回顶部、面包屑、阅读时间
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class Folio_Theme_Enhancements {
    
    private $options;
    
    public function __construct() {
        // 获取主题设置
        $this->options = get_option('folio_theme_options', array());
        
        // 1. 图片懒加载
        if ($this->get_option('enable_lazy_load', 1)) {
            add_filter('the_content', array($this, 'add_lazy_load_to_images'));
            add_filter('post_thumbnail_html', array($this, 'add_lazy_load_to_thumbnail'), 10, 5);
        }
        
        // 2. 返回顶部按钮（已在footer.php中直接输出）
        
        // 3. 面包屑导航
        // 通过模板函数调用
        
        // 4. 阅读时间估算
        // 通过模板函数调用
        
        // 加载前端资源
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * 获取选项值
     */
    private function get_option($key, $default = 0) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    /**
     * 1. 为内容中的图片添加懒加载
     */
    public function add_lazy_load_to_images($content) {
        if (is_feed() || is_admin()) {
            return $content;
        }
        
        // 匹配所有img标签
        $content = preg_replace_callback('/<img([^>]+)>/i', function($matches) {
            $img = $matches[0];
            
            // 如果已经有loading属性，跳过
            if (strpos($img, 'loading=') !== false) {
                return $img;
            }
            
            // 添加loading="lazy"
            $img = str_replace('<img', '<img loading="lazy"', $img);
            
            return $img;
        }, $content);
        
        return $content;
    }
    
    /**
     * 为特色图片添加懒加载
     */
    public function add_lazy_load_to_thumbnail($html, $post_id, $post_thumbnail_id, $size, $attr) {
        if (is_feed() || is_admin()) {
            return $html;
        }
        
        if (strpos($html, 'loading=') === false) {
            $html = str_replace('<img', '<img loading="lazy"', $html);
        }
        
        return $html;
    }
    

    
    /**
     * 3. 面包屑导航
     */
    public static function render_breadcrumbs() {
        // 首页不显示
        if (is_front_page()) {
            return;
        }
        
        $separator = '<span class="breadcrumb-separator">/</span>';
        $home_title = __('首页', 'folio');
        
        echo '<nav class="breadcrumbs" aria-label="' . esc_attr__('面包屑导航', 'folio') . '">';
        echo '<ol class="breadcrumb-list">';
        
        // 首页链接
        echo '<li class="breadcrumb-item"><a href="' . esc_url(home_url('/')) . '">' . esc_html($home_title) . '</a></li>';
        echo $separator;
        
        if (is_category()) {
            // 分类页：只显示当前分类
            $category = get_queried_object();
            echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html($category->name) . '</li>';
            
        } elseif (is_single()) {
            // 文章页：首页 > 分类 > 文章标题
            $categories = get_the_category();
            
            if ($categories) {
                $category = $categories[0];
                echo '<li class="breadcrumb-item"><a href="' . esc_url(get_category_link($category->term_id)) . '">' . esc_html($category->name) . '</a></li>';
                echo $separator;
            }
            
            echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html(get_the_title()) . '</li>';
            
        } elseif (is_page()) {
            // 页面：首页 > 页面标题（不显示父页面）
            echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html(get_the_title()) . '</li>';
            
        } elseif (is_tag()) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html(single_tag_title('', false)) . '</li>';
            
        } elseif (is_author()) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html(get_the_author()) . '</li>';
            
        } elseif (is_archive()) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html(get_the_archive_title()) . '</li>';
            
        } elseif (is_search()) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html__('搜索结果', 'folio') . ': ' . esc_html(get_search_query()) . '</li>';
            
        } elseif (is_404()) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . esc_html__('404 错误', 'folio') . '</li>';
        }
        
        echo '</ol>';
        echo '</nav>';
    }
    
    /**
     * 4. 计算阅读时间
     */
    public static function get_reading_time($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $content = get_post_field('post_content', $post_id);
        $word_count = mb_strlen(strip_tags($content), 'UTF-8');
        
        // 中文平均阅读速度：每分钟300-500字，这里取400
        $reading_speed = 400;
        $minutes = ceil($word_count / $reading_speed);
        
        return max(1, $minutes); // 最少1分钟
    }
    
    /**
     * 渲染阅读时间
     */
    public static function render_reading_time($post_id = null) {
        $minutes = self::get_reading_time($post_id);
        printf(esc_html__('%d min read', 'folio'), $minutes);
    }
    
    /**
     * 加载前端脚本和样式
     */
    public function enqueue_scripts() {
        // 面包屑样式
        wp_add_inline_style('mpb-style', $this->get_breadcrumbs_css());
        
        // 阅读时间样式
        wp_add_inline_style('mpb-style', $this->get_reading_time_css());
    }
    

    
    /**
     * 面包屑CSS
     */
    private function get_breadcrumbs_css() {
        return '
        .breadcrumbs {
            margin: 0;
            padding: 12px 0;
            font-size: 13px;
        }
        
        .breadcrumb-list {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 6px;
        }
        
        .breadcrumb-item {
            display: inline-flex;
            align-items: center;
        }
        
        .breadcrumb-item a {
            color: var(--folio-text-muted, #6b7280);
            text-decoration: none;
            transition: all 0.2s ease;
            padding: 2px 4px;
            border-radius: 3px;
        }
        
        .breadcrumb-item a:hover {
            color: var(--folio-text, #1a1a1a);
            background-color: var(--folio-hover-bg, #f3f4f6);
        }
        
        .breadcrumb-item.active {
            color: var(--folio-text-light, #4b5563);
            font-weight: 400;
        }
        
        .breadcrumb-separator {
            margin: 0 2px;
            color: var(--folio-text-muted, #d1d5db);
            font-size: 12px;
        }
        
        @media (max-width: 640px) {
            .breadcrumbs {
                font-size: 12px;
                padding: 8px 0;
            }
            
            .breadcrumb-separator {
                margin: 0 1px;
            }
        }
        ';
    }
    
    /**
     * 阅读时间CSS
     */
    private function get_reading_time_css() {
        return '
        .reading-time {
            display: inline-flex;
            align-items: center;
            font-size: 14px;
            color: var(--folio-text-muted, #6b7280);
        }
        
        .reading-time svg {
            flex-shrink: 0;
        }
        ';
    }
}

// 初始化
new Folio_Theme_Enhancements();

/**
 * 模板函数
 */

// 面包屑导航
if (!function_exists('folio_breadcrumbs')) {
    function folio_breadcrumbs() {
        Folio_Theme_Enhancements::render_breadcrumbs();
    }
}

// 阅读时间
if (!function_exists('folio_reading_time')) {
    function folio_reading_time($post_id = null) {
        Folio_Theme_Enhancements::render_reading_time($post_id);
    }
}

// 获取阅读时间（分钟数）
if (!function_exists('folio_get_reading_time')) {
    function folio_get_reading_time($post_id = null) {
        return Folio_Theme_Enhancements::get_reading_time($post_id);
    }
}

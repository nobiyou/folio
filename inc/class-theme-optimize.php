<?php
/**
 * Theme Performance Optimization
 * 
 * 清理 WordPress 冗余代码，提升性能
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Theme_Optimize {

    private $options;

    public function __construct() {
        // 获取主题设置
        $this->options = get_option('folio_theme_options', array());
        
        add_action('init', array($this, 'init_cleanup'));
        add_action('after_setup_theme', array($this, 'after_setup_cleanup'));
        add_action('wp_enqueue_scripts', array($this, 'dequeue_unnecessary'), 100);
        add_action('wp_dashboard_setup', array($this, 'cleanup_dashboard'));
        
        // 菜单缓存
        add_action('wp_update_nav_menu', array($this, 'clear_menu_cache'));
        
        // 1. 移除分类链接中的 category 前缀
        if ($this->get_option('remove_category_prefix', 1)) {
            add_action('init', array($this, 'remove_category_prefix'));
        }
        
        // 2. 自动设置特色图片
        if ($this->get_option('auto_featured_image', 1)) {
            add_action('save_post', array($this, 'auto_set_featured_image'), 10, 2);
            add_action('wp_insert_post', array($this, 'auto_set_featured_image'), 10, 2);
            
            // 前端显示时自动提取第一张图片作为封面图
            add_filter('post_thumbnail_html', array($this, 'auto_get_first_image'), 10, 5);
            add_filter('get_the_post_thumbnail_url', array($this, 'auto_get_first_image_url'), 10, 3);
        }
        
        // 3. 优化搜索只搜标题
        if ($this->get_option('search_title_only', 1)) {
            add_filter('posts_search', array($this, 'search_by_title_only'), 10, 2);
        }
        
        // 4. 编辑器选择（古腾堡 or 经典编辑器）
        $editor_type = $this->get_option('editor_type', 'gutenberg');
        if ($editor_type === 'classic') {
            // 禁用古腾堡，使用经典编辑器
            add_filter('use_block_editor_for_post', '__return_false');
            add_filter('use_block_editor_for_post_type', '__return_false', 10, 2);
        } else {
            // 启用古腾堡编辑器
            add_filter('use_block_editor_for_post', '__return_true');
            add_filter('use_block_editor_for_post_type', '__return_true', 10, 2);
        }
        
        // 5. 小工具编辑器选择
        $widget_editor = $this->get_option('widget_editor_type', 'gutenberg');
        if ($widget_editor === 'classic') {
            // 禁用古腾堡小工具，使用经典小工具
            add_filter('use_widgets_block_editor', '__return_false');
        } else {
            // 启用古腾堡小工具
            add_filter('use_widgets_block_editor', '__return_true');
        }
        
        // 6. 移除后台顶部 WordPress Logo
        if ($this->get_option('remove_admin_bar_logo', 1)) {
            add_action('admin_bar_menu', array($this, 'remove_admin_bar_logo'), 999);
        }
        
        // 7. 清理图片插入时的HTML属性
        if ($this->get_option('clean_image_html', 1)) {
            add_filter('image_send_to_editor', array($this, 'clean_image_html'), 10, 9);
            add_filter('get_image_tag_class', '__return_empty_string');
            add_filter('get_image_tag', array($this, 'clean_image_tag'), 10, 6);
        }
        
        // 8. 关闭 REST API（对未登录用户）
        if ($this->get_option('restrict_rest_api', 1)) {
            add_filter('rest_authentication_errors', array($this, 'restrict_rest_api'));
        }
        
        // 9. 关闭 XML-RPC pingback
        if ($this->get_option('disable_xmlrpc_pingback', 1)) {
            add_filter('xmlrpc_methods', array($this, 'disable_xmlrpc_pingback'));
        }
        
        // 10. 禁用文章修订版本
        if ($this->get_option('disable_post_revisions', 0)) {
            add_filter('wp_revisions_to_keep', '__return_zero');
        }
        
        // 11. 限制修订版本数量
        $revision_limit = $this->get_option('post_revisions_limit', 0);
        if ($revision_limit > 0) {
            add_filter('wp_revisions_to_keep', function() use ($revision_limit) {
                return $revision_limit;
            });
        }
        
        // 12. 禁用自动保存
        if ($this->get_option('disable_autosave', 0)) {
            add_action('admin_init', function() {
                wp_deregister_script('autosave');
            });
        }
        
        // 13. 移除jQuery Migrate
        if ($this->get_option('remove_jquery_migrate', 1)) {
            add_action('wp_default_scripts', array($this, 'remove_jquery_migrate'));
        }
        
        // 14. 禁用前端Dashicons
        if ($this->get_option('disable_dashicons_frontend', 1)) {
            add_action('wp_enqueue_scripts', array($this, 'disable_dashicons_frontend'));
        }
        
        // 15. 移除评论样式
        if ($this->get_option('remove_comment_styles', 0)) {
            add_action('wp_enqueue_scripts', function() {
                wp_dequeue_style('wp-block-library');
            }, 100);
        }
        
        // 16. 禁用自动更新邮件
        if ($this->get_option('disable_update_emails', 1)) {
            add_filter('auto_core_update_send_email', '__return_false');
            add_filter('auto_plugin_update_send_email', '__return_false');
            add_filter('auto_theme_update_send_email', '__return_false');
        }
        
        // 17. 禁用Heartbeat API
        $heartbeat_mode = $this->get_option('heartbeat_mode', 'default');
        if ($heartbeat_mode !== 'default') {
            add_action('init', array($this, 'modify_heartbeat'), 1);
        }
        
        // 18. 移除短代码自动段落
        if ($this->get_option('remove_shortcode_p', 1)) {
            add_filter('the_content', array($this, 'remove_shortcode_p_tags'), 20);
        }
        
        // 19. 禁用Google字体
        if ($this->get_option('disable_google_fonts', 0)) {
            add_filter('style_loader_tag', array($this, 'disable_google_fonts'), 10, 2);
        }
        
        // 20. 移除WP版本号（CSS/JS）
        if ($this->get_option('remove_version_strings', 1)) {
            add_filter('style_loader_src', array($this, 'remove_version_strings'), 9999);
            add_filter('script_loader_src', array($this, 'remove_version_strings'), 9999);
        }
        
        // 21. 移除前端顶部管理栏
        if ($this->get_option('hide_admin_bar_frontend', 0)) {
            add_filter('show_admin_bar', '__return_false');
        }
    }
    
    /**
     * 获取选项值
     */
    private function get_option($key, $default = 0) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    /**
     * 初始化清理
     */
    public function init_cleanup() {
        // 移除 emoji 支持
        if (apply_filters('folio_disable_emoji', true)) {
            $this->disable_emoji();
        }

        // 移除 wp_head 中的冗余标签
        if (apply_filters('folio_cleanup_head', true)) {
            $this->cleanup_head();
        }

        // 移除 XML-RPC
        if (apply_filters('folio_disable_xmlrpc', true)) {
            add_filter('xmlrpc_enabled', '__return_false');
        }
    }

    /**
     * 禁用 Emoji
     */
    private function disable_emoji() {
        // 前端
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');

        // 后台
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');

        // Feed
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');

        // Embeds
        remove_filter('embed_head', 'print_emoji_detection_script');

        // 邮件
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        // TinyMCE
        add_filter('tiny_mce_plugins', function($plugins) {
            if (is_array($plugins)) {
                $plugins = array_diff($plugins, array('wpemoji'));
            }
            return $plugins;
        });
    }

    /**
     * 清理 wp_head 中的冗余标签
     */
    private function cleanup_head() {
        // 移除 RSD 链接
        remove_action('wp_head', 'rsd_link');

        // 移除 Windows Live Writer 链接
        remove_action('wp_head', 'wlwmanifest_link');

        // 移除通用 feed 链接
        remove_action('wp_head', 'feed_links', 2);

        // 移除额外 feed 链接
        remove_action('wp_head', 'feed_links_extra', 3);

        // 移除 WordPress 版本号
        remove_action('wp_head', 'wp_generator');

        // 移除 REST API 链接
        remove_action('wp_head', 'rest_output_link_wp_head', 10);

        // 移除 oEmbed 链接
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);

        // 移除相邻文章链接
        remove_action('wp_head', 'adjacent_posts_rel_link', 10, 0);
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);

        // 移除 DNS 预取
        remove_action('wp_head', 'wp_resource_hints', 2);

        // 移除短链接
        remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
    }

    /**
     * 主题设置后的清理
     */
    public function after_setup_cleanup() {
        // 移除 srcset 自动生成（可选）
        if (apply_filters('folio_disable_srcset', false)) {
            add_filter('wp_calculate_image_srcset', '__return_false');
        }

        // 移除 duotone 滤镜
        remove_filter('render_block', 'wp_render_duotone_support');
        remove_filter('render_block', 'wp_restore_group_inner_container');
        remove_filter('render_block', 'wp_render_layout_support_flag');
    }

    /**
     * 移除不必要的脚本和样式
     */
    public function dequeue_unnecessary() {
        // 移除全局样式
        wp_deregister_style('global-styles');
        wp_dequeue_style('global-styles');

        // 移除区块库样式（如果不使用古腾堡）
        if (apply_filters('folio_disable_block_styles', false)) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
        }

        // 移除全局样式内联
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
    }

    /**
     * 清理仪表盘
     */
    public function cleanup_dashboard() {
        // 移除欢迎面板
        remove_action('welcome_panel', 'wp_welcome_panel');

        // 移除活动小工具
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');

        // 移除 WordPress 新闻
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
    }

    /**
     * 清除菜单缓存
     */
    public function clear_menu_cache() {
        delete_transient('folio_menu_cache_primary');
    }
    
    /**
     * 1. 移除分类链接中的 category 前缀
     */
    public function remove_category_prefix() {
        // 修改分类基础结构
        add_action('init', function() {
            global $wp_rewrite;
            $wp_rewrite->extra_permastructs['category']['struct'] = '/%category%';
        }, 999);
        
        // 修改分类链接
        add_filter('category_link', function($link) {
            return str_replace('/category/', '/', $link);
        });
        
        // 添加重写规则，避免与文章冲突
        add_filter('category_rewrite_rules', function($rules) {
            $new_rules = array();
            $categories = get_categories(array('hide_empty' => false));
            
            foreach ($categories as $category) {
                $category_nicename = $category->slug;
                
                // 分类归档
                $new_rules[$category_nicename . '/?$'] = 'index.php?category_name=' . $category_nicename;
                $new_rules[$category_nicename . '/page/?([0-9]{1,})/?$'] = 'index.php?category_name=' . $category_nicename . '&paged=$matches[1]';
                $new_rules[$category_nicename . '/feed/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=' . $category_nicename . '&feed=$matches[1]';
                $new_rules[$category_nicename . '/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=' . $category_nicename . '&feed=$matches[1]';
            }
            
            return $new_rules;
        });
        
        // 刷新重写规则（仅在设置更改时）
        add_action('admin_init', function() {
            if (get_option('folio_category_prefix_flushed') !== 'yes') {
                flush_rewrite_rules();
                update_option('folio_category_prefix_flushed', 'yes');
            }
        });
    }
    
    /**
     * 2. 自动设置特色图片（从文章第一张图片）
     * 保存文章时，如果没有特色图片，自动使用文章中的第一张图片
     */
    public function auto_set_featured_image($post_id, $post) {
        // 跳过自动保存和修订版本
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // 只处理文章类型
        if (get_post_type($post_id) !== 'post') {
            return;
        }
        
        // 如果已经有特色图片，跳过
        if (has_post_thumbnail($post_id)) {
            return;
        }
        
        // 从文章内容中提取第一张图片
        $attachment_id = $this->get_first_image_from_content($post_id, $post);
        
        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }
    
    /**
     * 从文章内容中提取第一张图片的附件ID
     */
    private function get_first_image_from_content($post_id, $post = null) {
        if (!$post) {
            $post = get_post($post_id);
        }
        
        if (!$post) {
            return false;
        }
        
        $content = $post->post_content;
        
        // 方法1: 匹配图片标签
        preg_match_all('/<img[^>]+>/i', $content, $matches);
        
        if (!empty($matches[0])) {
        $first_img = $matches[0][0];
        
        // 提取图片URL
            preg_match('/src=["\']([^"\']+)["\']/i', $first_img, $img_url);
        
            if (!empty($img_url[1])) {
                $image_url = $img_url[1];
                
                // 处理相对路径
                if (strpos($image_url, 'http') !== 0) {
                    $image_url = home_url($image_url);
                }
        
        // 检查是否是本站图片
        $upload_dir = wp_upload_dir();
                $site_url = home_url();
                
                if (strpos($image_url, $upload_dir['baseurl']) !== false || strpos($image_url, $site_url) !== false) {
        // 通过URL获取附件ID
        $attachment_id = attachment_url_to_postid($image_url);
                    
                    if ($attachment_id) {
                        return $attachment_id;
                    }
                }
            }
        }
        
        // 方法2: 从附件中查找（如果内容中没有找到）
        $attachments = get_attached_media('image', $post_id);
        if (!empty($attachments)) {
            $first_attachment = reset($attachments);
            return $first_attachment->ID;
        }
        
        return false;
    }
    
    /**
     * 前端显示时自动获取第一张图片作为封面图（HTML）
     * 列表调用文章信息时，若没有设置特色图片，调用文章中的第一张图片为封面图
     */
    public function auto_get_first_image($html, $post_id, $post_thumbnail_id, $size, $attr) {
        // 如果已经有特色图片HTML，直接返回
        if (!empty($html)) {
            return $html;
        }
        
        // 如果已经有特色图片ID，不应该到这里
        if ($post_thumbnail_id) {
            return $html;
        }
        
        // 获取第一张图片
        $post = get_post($post_id);
        $attachment_id = $this->get_first_image_from_content($post_id, $post);
        
        if ($attachment_id) {
            // 生成图片HTML
            return wp_get_attachment_image($attachment_id, $size, false, $attr);
        }
        
        return $html;
    }
    
    /**
     * 前端显示时自动获取第一张图片URL作为封面图URL
     */
    public function auto_get_first_image_url($url, $post_id, $size) {
        // 如果已经有特色图片URL，直接返回
        if (!empty($url)) {
            return $url;
        }
        
        // 获取第一张图片
        $post = get_post($post_id);
        $attachment_id = $this->get_first_image_from_content($post_id, $post);
        
        if ($attachment_id) {
            // 获取图片URL
            $image_url = wp_get_attachment_image_url($attachment_id, $size);
            if ($image_url) {
                return $image_url;
            }
        }
        
        return $url;
    }
    
    /**
     * 3. 优化搜索只搜标题
     */
    public function search_by_title_only($search, $query) {
        if (!is_admin() && $query->is_main_query() && $query->is_search()) {
            global $wpdb;
            
            $search_term = $query->get('s');
            if (empty($search_term)) {
                return $search;
            }
            
            $search_term = $wpdb->esc_like($search_term);
            $search_term = '%' . $search_term . '%';
            
            $search = " AND {$wpdb->posts}.post_title LIKE '{$search_term}' ";
        }
        
        return $search;
    }
    
    /**
     * 6. 移除后台顶部 WordPress Logo
     */
    public function remove_admin_bar_logo($wp_admin_bar) {
        $wp_admin_bar->remove_node('wp-logo');
    }
    
    /**
     * 7. 清理图片插入时的HTML属性
     */
    public function clean_image_html($html, $id, $caption, $title, $align, $url, $size, $alt) {
        // 移除不必要的属性
        $html = preg_replace('/(width|height)="\d*"\s/', '', $html);
        $html = preg_replace('/class="[^"]*"/', '', $html);
        $html = preg_replace('/title="[^"]*"/', '', $html);
        $html = preg_replace('/srcset="[^"]*"/', '', $html);
        $html = preg_replace('/sizes="[^"]*"/', '', $html);
        $html = preg_replace('/loading="[^"]*"/', '', $html);
        
        // 清理多余空格
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);
        
        return $html;
    }
    
    public function clean_image_tag($html, $id, $alt, $title, $align, $size) {
        // 只保留 src 和 alt 属性
        $html = preg_replace('/(width|height)="\d*"\s/', '', $html);
        $html = preg_replace('/class="[^"]*"/', '', $html);
        
        return $html;
    }
    
    /**
     * 8. 关闭 REST API（对未登录用户）
     * 但允许 JWT Auth 和其他认证相关的端点
     */
    public function restrict_rest_api($result) {
        // 如果已经有认证错误，直接返回
        if (!empty($result)) {
            return $result;
        }
        
        // 如果用户已登录，允许访问
        if (is_user_logged_in()) {
            return $result;
        }
        
        // 获取当前请求的路径
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $request_path = parse_url($request_uri, PHP_URL_PATH);
        
        // 允许的端点列表（这些端点需要未登录用户访问）
        $allowed_endpoints = array(
            '/wp-json/jwt-auth/v1/token',           // JWT Auth token 获取
            '/wp-json/jwt-auth/v1/token/validate',  // JWT Auth token 验证
            '/wp-json/jwt-auth/v1/token/refresh',   // JWT Auth token 刷新
            '/wp-json/wp/v2/users/me',              // 用户信息（需要认证，但允许尝试）
        );
        
        // 检查是否是允许的端点
        foreach ($allowed_endpoints as $endpoint) {
            if ($request_path === $endpoint || strpos($request_path, $endpoint) !== false) {
                return $result; // 允许访问
            }
        }
        
        // 其他未登录用户的 REST API 请求被阻止
            return new WP_Error(
                'rest_disabled',
                __('REST API 已禁用', 'folio'),
                array('status' => 401)
            );
    }
    
    /**
     * 9. 关闭 XML-RPC pingback
     */
    public function disable_xmlrpc_pingback($methods) {
        unset($methods['pingback.ping']);
        unset($methods['pingback.extensions.getPingbacks']);
        return $methods;
    }
    
    /**
     * 13. 移除jQuery Migrate
     */
    public function remove_jquery_migrate($scripts) {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $script = $scripts->registered['jquery'];
            
            if ($script->deps) {
                $script->deps = array_diff($script->deps, array('jquery-migrate'));
            }
        }
    }
    
    /**
     * 14. 禁用前端Dashicons
     */
    public function disable_dashicons_frontend() {
        if (!is_user_logged_in()) {
            wp_deregister_style('dashicons');
        }
    }
    
    /**
     * 17. 修改Heartbeat API
     */
    public function modify_heartbeat() {
        $mode = $this->get_option('heartbeat_mode', 'default');
        
        if ($mode === 'disable') {
            // 完全禁用
            wp_deregister_script('heartbeat');
        } elseif ($mode === 'reduce') {
            // 降低频率
            add_filter('heartbeat_settings', function($settings) {
                $settings['interval'] = 60; // 60秒
                return $settings;
            });
        }
    }
    
    /**
     * 18. 移除短代码自动段落
     */
    public function remove_shortcode_p_tags($content) {
        $array = array(
            '<p>[' => '[',
            ']</p>' => ']',
            ']<br />' => ']',
            ']<br>' => ']'
        );
        return strtr($content, $array);
    }
    
    /**
     * 19. 禁用Google字体
     */
    public function disable_google_fonts($html, $handle) {
        if (strpos($html, 'fonts.googleapis.com') !== false) {
            return '';
        }
        return $html;
    }
    
    /**
     * 20. 移除版本号字符串
     */
    public function remove_version_strings($src) {
        if (strpos($src, 'ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }
}

// 初始化
new folio_Theme_Optimize();

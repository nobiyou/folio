<?php
/**
 * Image Alt Text Optimizer
 * 
 * 自动为图片添加Alt文本，提升SEO和可访问性
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Image_Alt_Optimizer {

    public function __construct() {
        // 在上传时自动设置Alt文本
        add_action('add_attachment', array($this, 'auto_set_alt_text'));
        
        // 过滤输出的图片，确保有Alt文本
        add_filter('wp_get_attachment_image_attributes', array($this, 'ensure_alt_text'), 10, 3);
        
        // 过滤文章内容中的图片
        add_filter('the_content', array($this, 'add_alt_to_content_images'));
        
        // 为特色图片添加Alt文本
        add_filter('post_thumbnail_html', array($this, 'add_alt_to_featured_image'), 10, 5);
    }

    /**
     * 上传时自动设置Alt文本
     */
    public function auto_set_alt_text($attachment_id) {
        // 检查是否已有Alt文本
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($existing_alt)) {
            return;
        }

        // 获取附件信息
        $attachment = get_post($attachment_id);
        if (!$attachment || strpos($attachment->post_mime_type, 'image/') !== 0) {
            return;
        }

        // 生成Alt文本
        $alt_text = $this->generate_alt_text($attachment);
        
        if ($alt_text) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }
    }

    /**
     * 确保图片有Alt文本
     */
    public function ensure_alt_text($attr, $attachment, $size) {
        // 如果已有Alt文本，直接返回
        if (!empty($attr['alt'])) {
            return $attr;
        }

        // 获取或生成Alt文本
        $alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
        
        if (empty($alt_text)) {
            $alt_text = $this->generate_alt_text($attachment);
            
            // 保存生成的Alt文本
            if ($alt_text) {
                update_post_meta($attachment->ID, '_wp_attachment_image_alt', $alt_text);
            }
        }

        $attr['alt'] = $alt_text ?: $this->get_fallback_alt_text();
        
        return $attr;
    }

    /**
     * 为文章内容中的图片添加Alt文本
     */
    public function add_alt_to_content_images($content) {
        // 匹配所有img标签
        $pattern = '/<img([^>]*?)src=["\']([^"\']*)["\']([^>]*?)>/i';
        
        return preg_replace_callback($pattern, array($this, 'process_content_image'), $content);
    }

    /**
     * 处理内容中的图片
     */
    private function process_content_image($matches) {
        $img_tag = $matches[0];
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[3];

        // 检查是否已有alt属性
        if (preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_matches)) {
            $existing_alt = trim($alt_matches[1]);
            if (!empty($existing_alt)) {
                return $img_tag; // 已有Alt文本，不修改
            }
        }

        // 尝试从URL获取attachment ID
        $attachment_id = $this->get_attachment_id_from_url($src);
        $alt_text = '';

        if ($attachment_id) {
            // 从数据库获取Alt文本
            $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            
            if (empty($alt_text)) {
                $attachment = get_post($attachment_id);
                $alt_text = $this->generate_alt_text($attachment);
                
                if ($alt_text) {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                }
            }
        }

        // 如果没有找到Alt文本，使用文件名
        if (empty($alt_text)) {
            $alt_text = $this->generate_alt_from_filename($src);
        }

        // 如果还是没有，使用当前文章标题
        if (empty($alt_text)) {
            $alt_text = $this->get_fallback_alt_text();
        }

        // 移除现有的空alt属性
        $img_tag = preg_replace('/alt=["\']["\']/', '', $img_tag);
        
        // 添加新的alt属性
        if (strpos($img_tag, 'alt=') === false) {
            $img_tag = str_replace('<img', '<img alt="' . esc_attr($alt_text) . '"', $img_tag);
        }

        return $img_tag;
    }

    /**
     * 为特色图片添加Alt文本
     */
    public function add_alt_to_featured_image($html, $post_id, $post_thumbnail_id, $size, $attr) {
        if (empty($attr['alt'])) {
            $alt_text = get_post_meta($post_thumbnail_id, '_wp_attachment_image_alt', true);
            
            if (empty($alt_text)) {
                $attachment = get_post($post_thumbnail_id);
                $alt_text = $this->generate_alt_text($attachment, $post_id);
                
                if ($alt_text) {
                    update_post_meta($post_thumbnail_id, '_wp_attachment_image_alt', $alt_text);
                }
            }

            if (empty($alt_text)) {
                $alt_text = get_the_title($post_id);
            }

            // 替换或添加alt属性
            if (preg_match('/alt=["\'][^"\']*["\']/', $html)) {
                $html = preg_replace('/alt=["\'][^"\']*["\']/', 'alt="' . esc_attr($alt_text) . '"', $html);
            } else {
                $html = str_replace('<img', '<img alt="' . esc_attr($alt_text) . '"', $html);
            }
        }

        return $html;
    }

    /**
     * 生成Alt文本
     */
    private function generate_alt_text($attachment, $post_id = null) {
        if (!$attachment) {
            return '';
        }

        $alt_text = '';

        // 1. 优先使用附件标题（如果不是文件名）
        $title = $attachment->post_title;
        if ($title && !$this->is_filename_like($title)) {
            $alt_text = $title;
        }

        // 2. 使用附件描述
        if (empty($alt_text) && !empty($attachment->post_content)) {
            $alt_text = wp_trim_words($attachment->post_content, 10, '');
        }

        // 3. 从文件名生成
        if (empty($alt_text)) {
            $alt_text = $this->generate_alt_from_filename($attachment->guid);
        }

        // 4. 使用关联文章的标题
        if (empty($alt_text)) {
            if ($post_id) {
                $alt_text = get_the_title($post_id);
            } elseif ($attachment->post_parent) {
                $alt_text = get_the_title($attachment->post_parent);
            }
        }

        return $this->clean_alt_text($alt_text);
    }

    /**
     * 从文件名生成Alt文本
     */
    private function generate_alt_from_filename($url) {
        $filename = basename($url);
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        
        // 移除常见的文件名模式
        $filename = preg_replace('/[-_](\d+x\d+)$/', '', $filename); // 移除尺寸后缀
        $filename = preg_replace('/[-_]scaled$/', '', $filename);     // 移除scaled后缀
        
        // 将连字符和下划线替换为空格
        $filename = str_replace(array('-', '_'), ' ', $filename);
        
        // 移除数字序列（如果是纯数字文件名）
        if (preg_match('/^\d+$/', trim($filename))) {
            return '';
        }

        return $this->clean_alt_text($filename);
    }

    /**
     * 清理Alt文本
     */
    private function clean_alt_text($text) {
        if (empty($text)) {
            return '';
        }

        // 移除HTML标签
        $text = wp_strip_all_tags($text);
        
        // 规范化空白字符
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 首字母大写
        $text = ucfirst(trim($text));
        
        // 限制长度
        if (mb_strlen($text) > 100) {
            $text = mb_substr($text, 0, 97) . '...';
        }

        return $text;
    }

    /**
     * 检查是否像文件名
     */
    private function is_filename_like($text) {
        // 检查是否包含文件扩展名
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $text)) {
            return true;
        }
        
        // 检查是否主要由数字、连字符、下划线组成
        if (preg_match('/^[\d\-_]+$/', $text)) {
            return true;
        }
        
        return false;
    }

    /**
     * 获取后备Alt文本
     */
    private function get_fallback_alt_text() {
        if (is_singular()) {
            return get_the_title();
        }
        
        return get_bloginfo('name') . ' - ' . get_bloginfo('description');
    }

    /**
     * 从URL获取附件ID
     */
    private function get_attachment_id_from_url($url) {
        // 移除查询参数
        $url = strtok($url, '?');
        
        // 尝试从URL获取附件ID
        $attachment_id = attachment_url_to_postid($url);
        
        if (!$attachment_id) {
            // 尝试移除尺寸后缀再查找
            $url = preg_replace('/-\d+x\d+(?=\.[a-z]{3,4}$)/', '', $url);
            $attachment_id = attachment_url_to_postid($url);
        }
        
        return $attachment_id;
    }

    /**
     * 批量更新现有图片的Alt文本
     */
    public function batch_update_alt_texts($limit = 50) {
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                )
            )
        ));

        $updated = 0;
        foreach ($attachments as $attachment) {
            $alt_text = $this->generate_alt_text($attachment);
            if ($alt_text) {
                update_post_meta($attachment->ID, '_wp_attachment_image_alt', $alt_text);
                $updated++;
            }
        }

        return $updated;
    }
}

// 初始化
new folio_Image_Alt_Optimizer();

/**
 * 批量更新Alt文本的WP-CLI命令或管理员工具
 */
if (is_admin()) {
    add_action('wp_ajax_folio_batch_update_alt', function() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $optimizer = new folio_Image_Alt_Optimizer();
        $updated = $optimizer->batch_update_alt_texts(100);
        
        wp_send_json_success(array(
            'message' => sprintf('Updated %d images', $updated),
            'updated' => $updated
        ));
    });
}
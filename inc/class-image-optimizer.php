<?php
/**
 * Image Optimizer
 * 
 * 自动压缩和优化上传的图片，提升网站性能
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Image_Optimizer {

    private $options;
    private $supported_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    
    /**
     * 检查图片格式是否支持透明度
     */
    private function supports_transparency($mime_type) {
        return in_array($mime_type, array('image/png', 'image/gif', 'image/webp'));
    }

    public function __construct() {
        $theme_options = get_option('folio_theme_options', array());
        $this->options = array(
            'enable_compression' => isset($theme_options['enable_image_compression']) ? $theme_options['enable_image_compression'] : 1,
            'jpeg_quality' => isset($theme_options['jpeg_quality']) ? intval($theme_options['jpeg_quality']) : 85,
            'png_quality' => isset($theme_options['png_quality']) ? intval($theme_options['png_quality']) : 90,
            'max_width' => isset($theme_options['max_image_width']) ? intval($theme_options['max_image_width']) : 2048,
            'max_height' => isset($theme_options['max_image_height']) ? intval($theme_options['max_image_height']) : 2048,
            'enable_webp' => isset($theme_options['enable_webp_conversion']) ? $theme_options['enable_webp_conversion'] : 0,
            'strip_metadata' => isset($theme_options['strip_image_metadata']) ? $theme_options['strip_image_metadata'] : 1,
        );

        if (!$this->options['enable_compression']) {
            return;
        }

        // 处理上传的图片
        add_filter('wp_handle_upload_prefilter', array($this, 'optimize_uploaded_image'));
        
        // 处理生成的缩略图
        add_filter('wp_generate_attachment_metadata', array($this, 'optimize_thumbnails'), 10, 2);
        
        // 自定义JPEG质量
        add_filter('jpeg_quality', array($this, 'set_jpeg_quality'));
        add_filter('wp_editor_set_quality', array($this, 'set_jpeg_quality'));
        
        // 添加WebP支持
        if ($this->options['enable_webp']) {
            add_filter('wp_check_filetype_and_ext', array($this, 'add_webp_support'), 10, 4);
            add_filter('upload_mimes', array($this, 'add_webp_mime_type'));
        }

        // AJAX处理
        add_action('wp_ajax_folio_batch_optimize_images', array($this, 'ajax_batch_optimize_images'));
        add_action('wp_ajax_folio_get_optimization_stats', array($this, 'ajax_get_optimization_stats'));
    }

    /**
     * 优化上传的图片
     */
    public function optimize_uploaded_image($file) {
        if (!$this->is_supported_image($file['type'])) {
            return $file;
        }

        $image_path = $file['tmp_name'];
        $optimized = $this->optimize_image($image_path, $file['type']);

        if ($optimized) {
            // 记录优化统计
            $this->log_optimization_stats($file['name'], $optimized);
        }

        return $file;
    }

    /**
     * 优化缩略图
     */
    public function optimize_thumbnails($metadata, $attachment_id) {
        if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return $metadata;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['path']);

        foreach ($metadata['sizes'] as $size => $size_data) {
            $thumbnail_path = $base_dir . $size_data['file'];
            
            if (file_exists($thumbnail_path)) {
                $mime_type = $size_data['mime-type'] ?? 'image/jpeg';
                $this->optimize_image($thumbnail_path, $mime_type);
            }
        }

        return $metadata;
    }

    /**
     * 优化单个图片
     */
    private function optimize_image($image_path, $mime_type) {
        if (!file_exists($image_path) || !$this->is_supported_image($mime_type)) {
            return false;
        }

        $original_size = filesize($image_path);
        
        // 创建图片资源
        $image = $this->create_image_resource($image_path, $mime_type);
        if (!$image) {
            return false;
        }

        // 获取原始尺寸
        $original_width = imagesx($image);
        $original_height = imagesy($image);

        // 计算新尺寸
        $new_dimensions = $this->calculate_new_dimensions($original_width, $original_height);
        
        // 对于支持透明度的图片格式（PNG、GIF、WebP），如果不需要调整尺寸且不需要移除元数据，则跳过处理以保持透明度
        if ($this->supports_transparency($mime_type) && 
            $new_dimensions['width'] == $original_width && 
            $new_dimensions['height'] == $original_height &&
            !$this->options['strip_metadata']) {
            imagedestroy($image);
            return false; // 跳过优化，保持原图不变
        }
        
        // 如果需要调整尺寸
        if ($new_dimensions['width'] != $original_width || $new_dimensions['height'] != $original_height) {
            $resized_image = imagecreatetruecolor($new_dimensions['width'], $new_dimensions['height']);
            
            // 保持透明度（PNG、WebP使用alpha通道，GIF使用索引色）
            if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
                // PNG和WebP使用alpha通道
                imagealphablending($resized_image, false);
                imagesavealpha($resized_image, true);
                // 创建完全透明的背景色
                $transparent = imagecolorallocatealpha($resized_image, 0, 0, 0, 127);
                imagefill($resized_image, 0, 0, $transparent);
            } elseif ($mime_type === 'image/gif') {
                // GIF使用索引色透明度
                $transparent_index = imagecolortransparent($image);
                if ($transparent_index >= 0) {
                    // 获取透明色
                    $transparent_color = imagecolorsforindex($image, $transparent_index);
                    // 在新图片中分配透明色
                    $transparent_new = imagecolorallocate($resized_image, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                    imagecolortransparent($resized_image, $transparent_new);
                    imagefill($resized_image, 0, 0, $transparent_new);
                }
            }
            
            // 对于支持alpha通道的格式，确保源图片的透明度也被正确处理
            if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }
            
            imagecopyresampled(
                $resized_image, $image,
                0, 0, 0, 0,
                $new_dimensions['width'], $new_dimensions['height'],
                $original_width, $original_height
            );
            
            imagedestroy($image);
            $image = $resized_image;
        } else {
            // 即使不需要调整尺寸，也要确保支持透明度的格式设置正确
            if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }
        }

        // 移除EXIF数据（如果启用）
        if ($this->options['strip_metadata']) {
            // 这在保存时自动处理
        }

        // 保存优化后的图片
        $saved = $this->save_optimized_image($image, $image_path, $mime_type);
        imagedestroy($image);

        if ($saved) {
            $new_size = filesize($image_path);
            return array(
                'original_size' => $original_size,
                'new_size' => $new_size,
                'savings' => $original_size - $new_size,
                'percentage' => round((($original_size - $new_size) / $original_size) * 100, 2)
            );
        }

        return false;
    }

    /**
     * 创建图片资源
     */
    private function create_image_resource($image_path, $mime_type) {
        switch ($mime_type) {
            case 'image/jpeg':
                return imagecreatefromjpeg($image_path);
            case 'image/png':
                return imagecreatefrompng($image_path);
            case 'image/gif':
                return imagecreatefromgif($image_path);
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($image_path) : false;
            default:
                return false;
        }
    }

    /**
     * 保存优化后的图片
     */
    private function save_optimized_image($image, $image_path, $mime_type) {
        switch ($mime_type) {
            case 'image/jpeg':
                return imagejpeg($image, $image_path, $this->options['jpeg_quality']);
            case 'image/png':
                // PNG质量范围是0-9，需要转换
                $png_quality = 9 - round(($this->options['png_quality'] / 100) * 9);
                // 确保PNG保存时保持透明度
                imagealphablending($image, false);
                imagesavealpha($image, true);
                return imagepng($image, $image_path, $png_quality);
            case 'image/gif':
                // GIF保存时会自动保持透明色
                return imagegif($image, $image_path);
            case 'image/webp':
                // WebP保存时确保透明度
                if ($this->supports_transparency($mime_type)) {
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                return function_exists('imagewebp') ? imagewebp($image, $image_path, $this->options['jpeg_quality']) : false;
            default:
                return false;
        }
    }

    /**
     * 计算新尺寸
     */
    private function calculate_new_dimensions($width, $height) {
        $max_width = $this->options['max_width'];
        $max_height = $this->options['max_height'];

        if ($width <= $max_width && $height <= $max_height) {
            return array('width' => $width, 'height' => $height);
        }

        $ratio = min($max_width / $width, $max_height / $height);
        
        return array(
            'width' => round($width * $ratio),
            'height' => round($height * $ratio)
        );
    }

    /**
     * 检查是否为支持的图片类型
     */
    private function is_supported_image($mime_type) {
        return in_array($mime_type, $this->supported_types);
    }

    /**
     * 设置JPEG质量
     */
    public function set_jpeg_quality($quality) {
        return $this->options['jpeg_quality'];
    }

    /**
     * 添加WebP支持
     */
    public function add_webp_support($data, $file, $filename, $mimes) {
        $filetype = wp_check_filetype($filename, $mimes);
        
        if ($filetype['ext'] === 'webp') {
            $data['ext'] = 'webp';
            $data['type'] = 'image/webp';
        }
        
        return $data;
    }

    /**
     * 添加WebP MIME类型
     */
    public function add_webp_mime_type($mimes) {
        $mimes['webp'] = 'image/webp';
        return $mimes;
    }

    /**
     * 记录优化统计
     */
    private function log_optimization_stats($filename, $stats) {
        $optimization_logs = get_option('folio_image_optimization_stats', array());
        
        $optimization_logs[] = array(
            'filename' => $filename,
            'original_size' => $stats['original_size'],
            'new_size' => $stats['new_size'],
            'savings' => $stats['savings'],
            'percentage' => $stats['percentage'],
            'timestamp' => time()
        );

        // 只保留最近100条记录
        if (count($optimization_logs) > 100) {
            $optimization_logs = array_slice($optimization_logs, -100);
        }

        update_option('folio_image_optimization_stats', $optimization_logs);
    }

    /**
     * 批量优化现有图片
     */
    public function batch_optimize_existing_images($limit = 10) {
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => $this->supported_types,
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_folio_optimized',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        $optimized_count = 0;
        $total_savings = 0;

        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            
            if ($file_path && file_exists($file_path)) {
                $mime_type = get_post_mime_type($attachment->ID);
                $result = $this->optimize_image($file_path, $mime_type);
                
                if ($result) {
                    $optimized_count++;
                    $total_savings += $result['savings'];
                    
                    // 标记为已优化
                    update_post_meta($attachment->ID, '_folio_optimized', time());
                    
                    // 记录统计
                    $this->log_optimization_stats(basename($file_path), $result);
                }
            }
        }

        return array(
            'optimized' => $optimized_count,
            'total_savings' => $total_savings,
            'remaining' => $this->get_unoptimized_count() - $optimized_count
        );
    }

    /**
     * 获取未优化图片数量
     */
    private function get_unoptimized_count() {
        $query = new WP_Query(array(
            'post_type' => 'attachment',
            'post_mime_type' => $this->supported_types,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_folio_optimized',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        return $query->found_posts;
    }

    /**
     * AJAX批量优化图片
     */
    public function ajax_batch_optimize_images() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $result = $this->batch_optimize_existing_images(20);

        wp_send_json_success(array(
            'message' => sprintf(
                '优化了 %d 张图片，节省了 %s 空间',
                $result['optimized'],
                $this->format_bytes($result['total_savings'])
            ),
            'optimized' => $result['optimized'],
            'savings' => $result['total_savings'],
            'remaining' => $result['remaining']
        ));
    }

    /**
     * AJAX获取优化统计
     */
    public function ajax_get_optimization_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $stats = get_option('folio_image_optimization_stats', array());
        $total_savings = array_sum(array_column($stats, 'savings'));
        $total_images = count($stats);
        $unoptimized_count = $this->get_unoptimized_count();

        wp_send_json_success(array(
            'total_images' => $total_images,
            'total_savings' => $total_savings,
            'unoptimized_count' => $unoptimized_count,
            'recent_optimizations' => array_slice($stats, -10)
        ));
    }

    /**
     * 格式化字节数
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * 清除优化统计
     */
    public function clear_optimization_stats() {
        delete_option('folio_image_optimization_stats');
        
        // 清除所有图片的优化标记
        global $wpdb;
        $wpdb->delete(
            $wpdb->postmeta,
            array('meta_key' => '_folio_optimized'),
            array('%s')
        );
    }
}

// 初始化
new folio_Image_Optimizer();
<?php
/**
 * Template part for displaying post items in grid
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取缓存的保护信息和权限信息
$cached_protection_info = get_query_var('cached_protection_info', array());
$cached_user_permission = get_query_var('cached_user_permission', null);

// 如果有缓存数据，使用缓存；否则实时查询
if (!empty($cached_protection_info)) {
    $is_protected = $cached_protection_info['is_protected'] ?? false;
    $required_level = $cached_protection_info['required_level'] ?? 'vip';
    $can_access = $cached_user_permission ?? false;
} else {
    $is_protected = get_post_meta(get_the_ID(), '_folio_premium_content', true);
    $required_level = get_post_meta(get_the_ID(), '_folio_required_level', true) ?: 'vip';
    $can_access = function_exists('folio_can_user_access_article') ? folio_can_user_access_article(get_the_ID()) : true;
}

$ribbon_type = folio_get_ribbon_type();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('relative group aspect-[3/4] rounded overflow-hidden shadow-sm hover:shadow-lg transition'); ?> itemscope itemtype="https://schema.org/Article">
    
    <?php if ($ribbon_type) : ?>
        <?php folio_render_ribbon($ribbon_type); ?>
    <?php endif; ?>
    
    <!-- 会员专属标识 -->
    <?php if ($is_protected) : ?>
    <div class="absolute top-3 left-3 z-10 folio-badge-container">
        <?php
        // 使用缓存数据渲染会员徽章
        $badge_options = array(
            'size' => 'small',
            'style' => 'gradient',
            'show_tooltip' => true,
            'show_status' => true,
            'show_animation' => true,
            'show_user_status' => true,
            'cached_data' => array(
                'is_protected' => $is_protected,
                'required_level' => $required_level,
                'can_access' => $can_access
            )
        );
        
        echo folio_Frontend_Components::render_membership_badge(get_the_ID(), 'list', $badge_options);
        ?>
    </div>
    <?php endif; ?>
    
    <!-- 背景图 -->
    <?php 
    // 检查是否有特色图片，如果没有则尝试从内容中获取第一张图片
    $thumbnail_id = get_post_thumbnail_id();
    $thumbnail_html = '';
    
    // 判断是否需要添加模糊滤镜（会员文章且未解锁）
    $should_blur = $is_protected && !$can_access;
    $blur_class = $should_blur ? 'folio-premium-blur' : '';
    
    if ($thumbnail_id) {
        $image_classes = 'w-full h-full object-cover transition duration-500 group-hover:scale-110 filter brightness-90 group-hover:brightness-100';
        if ($blur_class) {
            $image_classes .= ' ' . $blur_class;
        }
        
        $thumbnail_html = get_the_post_thumbnail(get_the_ID(), 'portfolio-card', array(
            'class'   => $image_classes,
            'alt'     => get_the_title(),
            'loading' => 'lazy',
        ));
    } else {
        // 如果没有特色图片，尝试从内容中获取第一张图片
        $post = get_post();
        $content = $post->post_content;
        preg_match_all('/<img[^>]+>/i', $content, $matches);
        
        if (!empty($matches[0])) {
            $first_img = $matches[0][0];
            preg_match('/src=["\']([^"\']+)["\']/i', $first_img, $img_url);
            
            if (!empty($img_url[1])) {
                $image_url = $img_url[1];
                // 处理相对路径
                if (strpos($image_url, 'http') !== 0) {
                    $image_url = home_url($image_url);
                }
                
                $image_classes = 'w-full h-full object-cover transition duration-500 group-hover:scale-110 filter brightness-90 group-hover:brightness-100';
                if ($blur_class) {
                    $image_classes .= ' ' . $blur_class;
                }
                
                $thumbnail_html = '<img src="' . esc_url($image_url) . '" alt="' . esc_attr(get_the_title()) . '" class="' . esc_attr($image_classes) . '" loading="lazy">';
            }
        }
    }
    
    if ($thumbnail_html) : ?>
        <?php echo $thumbnail_html; ?>
    <?php else : ?>
        <!-- 占位符渐变背景 -->
        <div class="w-full h-full bg-gradient-to-br from-purple-500 to-pink-500"></div>
    <?php endif; ?>
    
    <!-- 六边形覆盖层 -->
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
        <?php folio_render_hexagon_card(); ?>
    </div>
    

    
    <!-- 隐藏的结构化数据 -->
    <meta itemprop="headline" content="<?php echo esc_attr(get_the_title()); ?>">
    <meta itemprop="datePublished" content="<?php echo esc_attr(get_the_date('c')); ?>">
    <meta itemprop="dateModified" content="<?php echo esc_attr(get_the_modified_date('c')); ?>">
    <meta itemprop="author" content="<?php echo esc_attr(get_the_author()); ?>">
    <meta itemprop="url" content="<?php echo esc_url(get_permalink()); ?>">
    <?php 
    $thumbnail_url = get_the_post_thumbnail_url();
    if (!$thumbnail_url) {
        // 如果没有特色图片URL，尝试从内容中获取第一张图片URL
        $post = get_post();
        $content = $post->post_content;
        preg_match_all('/<img[^>]+>/i', $content, $matches);
        
        if (!empty($matches[0])) {
            $first_img = $matches[0][0];
            preg_match('/src=["\']([^"\']+)["\']/i', $first_img, $img_url);
            
            if (!empty($img_url[1])) {
                $thumbnail_url = $img_url[1];
                // 处理相对路径
                if (strpos($thumbnail_url, 'http') !== 0) {
                    $thumbnail_url = home_url($thumbnail_url);
                }
            }
        }
    }
    
    if ($thumbnail_url) : ?>
    <meta itemprop="image" content="<?php echo esc_url($thumbnail_url); ?>">
    <?php endif; ?>
    
    <!-- 链接覆盖 -->
    <a href="<?php the_permalink(); ?>" class="absolute inset-0 z-20" aria-label="<?php echo esc_attr(sprintf(__('查看 %s', 'folio'), get_the_title())); ?>" itemprop="url"></a>
</article>

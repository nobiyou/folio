<?php
/**
 * Single Post Template
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) : the_post();
    $categories = get_the_category();
    $category = !empty($categories) ? $categories[0]->name : '';
    // 计算文章内容中的图片数量
    $content = get_the_content();
    $image_count = 0;
    
    // 统计特色图片
    if (has_post_thumbnail()) {
        $image_count++;
    }
    
    // 统计内容中的图片
    preg_match_all('/<img[^>]+>/i', $content, $matches);
    $image_count += count($matches[0]);
    
    // 统计图库短代码中的图片
    preg_match_all('/\[gallery[^\]]*ids=["\']([^"\']+)["\']/i', $content, $gallery_matches);
    if (!empty($gallery_matches[1])) {
        foreach ($gallery_matches[1] as $ids) {
            $image_count += count(explode(',', $ids));
        }
    }
?>

<!-- 面包屑导航 -->
<div class="mb-4">
    <?php if (function_exists('folio_breadcrumbs')) folio_breadcrumbs(); ?>
</div>

<!-- 1. 文章头部信息区 -->
<header class="relative grid grid-cols-1 md:grid-cols-12 gap-6 mb-8 items-start">
    
    <!-- 标题与简介 -->
    <div class="md:col-span-8 lg:col-span-9">        
        <!-- 文章标题 -->
        <h1 class="post-title text-3xl md:text-5xl font-black uppercase leading-tight tracking-tight mb-4">
            <?php the_title(); ?>
        </h1>
        
        <?php if (has_excerpt()) : ?>
        <div class="prose text-gray-600 leading-relaxed font-light max-w-2xl hidden">
            <p class="text-base font-medium text-gray-700 mb-3"><?php echo get_the_excerpt(); ?></p>
        </div>
        <?php endif; ?>
        

        
        <!-- 移动端显示的元数据 -->
        <div class="flex gap-8 mt-8 md:hidden text-xs uppercase font-bold text-gray-500 border-t pt-4">
            <div>
                <span class="block text-black"><?php esc_html_e('Images', 'folio'); ?></span>
                <?php echo esc_html($image_count); ?>
            </div>
            <?php if ($category) : ?>
            <div>
                <span class="block text-black"><?php esc_html_e('Category', 'folio'); ?></span>
                <?php echo esc_html($category); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 文章元信息 -->
        <div class="flex flex-wrap items-center gap-4 mt-8 py-4 text-xs text-gray-500">
            <!-- 发布日期 -->
            <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span><?php echo get_the_date('F j, Y'); ?></span>
            </div>
            
            <!-- 阅读时间 -->
            <?php if (function_exists('folio_reading_time')) : ?>
            <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?php folio_reading_time(); ?>
            </div>
            <?php endif; ?>
            
            <!-- 浏览量 -->
            <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <span><?php echo esc_html(folio_get_views()); ?></span>
            </div>
            
            <!-- 点赞 -->
            <button id="like-btn" class="flex items-center gap-1.5 transition hover:text-red-500 <?php echo folio_has_liked() ? 'text-red-500' : ''; ?>" data-post-id="<?php echo get_the_ID(); ?>" <?php echo folio_has_liked() ? 'disabled' : ''; ?>>
                <svg class="w-4 h-4" fill="<?php echo folio_has_liked() ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                <span id="like-count"><?php echo esc_html(folio_get_likes()); ?></span>
            </button>
            
            <!-- 收藏 -->
            <button id="favorite-btn" class="flex items-center gap-1.5 transition hover:text-yellow-500 <?php echo folio_has_favorited() ? 'text-yellow-500' : ''; ?>" data-post-id="<?php echo get_the_ID(); ?>" data-favorited="<?php echo folio_has_favorited() ? '1' : '0'; ?>">
                <svg class="w-4 h-4" fill="<?php echo folio_has_favorited() ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
                <span id="favorite-count"><?php echo esc_html(folio_get_favorites()); ?></span>
            </button>
            
            <!-- 管理员编辑链接 -->
            <?php if (current_user_can('edit_post', get_the_ID())) : ?>
            <a href="<?php echo esc_url(get_edit_post_link()); ?>" class="admin-edit-link flex items-center gap-1.5 transition" target="_blank">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Edit</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 桌面端图片数量徽章 -->
    <div class="hidden md:flex md:col-span-4 lg:col-span-3 justify-end items-start">
        <div class="hexagon-badge image-count-badge w-[120px] h-[140px] p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="text-center flex flex-col items-center justify-center h-full">
                <div class="image-count-label text-xs uppercase tracking-widest mb-3"><?php esc_html_e('Images', 'folio'); ?></div>
                <div class="image-count-number text-5xl font-black"><?php echo esc_html($image_count); ?></div>
            </div>
        </div>
    </div>
</header>

<!-- 2. 内容展示区 -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    <!-- 文章内容 -->
    <main class="prose prose-lg max-w-none lg:col-span-8">
        <div class="max-w-4xl">
            <?php the_content(); ?>
        </div>
    </main>
    
    <!-- 右侧边栏 -->
    <aside class="lg:col-span-4">
        <div class="sticky top-8">
            <?php if (is_active_sidebar('single-post-sidebar')) : ?>
                <?php dynamic_sidebar('single-post-sidebar'); ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<!-- 3. 相关文章推荐 -->
<?php
// 获取相关文章
$related_posts = folio_get_related_posts(get_the_ID(), 3);
?>

<?php if (!empty($related_posts)) : ?>
<section class="related-posts mt-24 pt-12 border-t-2 border-gray-200 dark:border-gray-700">
    <h3 class="text-xs font-bold uppercase tracking-widest mb-8">
        <?php esc_html_e('Related Posts', 'folio'); ?>
    </h3>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php foreach ($related_posts as $related_post) : ?>
        <article class="related-post-card group">
            <a href="<?php echo esc_url(get_permalink($related_post)); ?>" class="block">
                <!-- 缩略图 -->
                <?php
                // 获取会员信息（只查询一次）
                $related_premium_info = folio_get_post_premium_info($related_post->ID);
                $related_should_blur = $related_premium_info['is_premium'] && !$related_premium_info['can_access'];
                $related_blur_class = $related_should_blur ? 'folio-premium-blur' : '';
                ?>
                <div class="related-post-thumbnail relative overflow-hidden rounded-lg mb-4 aspect-[3/4]">
                    <?php if (has_post_thumbnail($related_post)) : 
                        $related_image_class = 'w-full h-full object-cover transition duration-500 group-hover:scale-110';
                        if ($related_blur_class) {
                            $related_image_class .= ' ' . $related_blur_class;
                        }
                    ?>
                        <?php echo get_the_post_thumbnail($related_post, 'medium', array(
                            'class' => $related_image_class,
                        )); ?>
                    <?php else : ?>
                        <div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-300"></div>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition"></div>
                    
                    <!-- 会员专属标识 -->
                    <?php if ($related_premium_info['is_premium']) :
                        $related_level_class = 'premium-badge-' . $related_premium_info['required_level'];
                    ?>
                    <div class="absolute top-2 left-2 premium-badge <?php echo esc_attr($related_level_class); ?> z-10">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        <span><?php echo esc_html($related_premium_info['level_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- 分类 -->
                <div class="related-post-category text-xs uppercase tracking-widest mb-2">
                    <?php 
                    $categories = get_the_category($related_post->ID);
                    if ($categories && !is_wp_error($categories)) {
                        echo esc_html($categories[0]->name);
                    }
                    ?>
                </div>
                
                <!-- 标题 -->
                <h4 class="related-post-title text-lg font-bold uppercase mb-2 transition group-hover:text-gray-600">
                    <?php echo esc_html(get_the_title($related_post)); ?>
                </h4>
                
                <!-- 统计信息 -->
                <div class="related-post-stats flex items-center gap-4 text-xs">
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <?php echo esc_html(folio_get_views($related_post->ID)); ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <?php echo esc_html(folio_get_likes($related_post->ID)); ?>
                    </span>
                </div>
            </a>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- 4. 底部导航 -->
<?php
$next_post = get_next_post();
$prev_post = get_previous_post();
$nav_post = $next_post ? $next_post : $prev_post;
?>

<?php if ($nav_post) : ?>
<footer class="post-navigation mt-24 pt-12">
    <div class="flex justify-between items-center mb-6">
        <h4 class="nav-section-title text-xs font-bold uppercase tracking-widest">
            <?php echo $next_post ? esc_html__('Next Post', 'folio') : esc_html__('Previous Post', 'folio'); ?>
        </h4>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="nav-view-all text-xs font-bold uppercase tracking-widest">
            <?php esc_html_e('View All', 'folio'); ?> →
        </a>
    </div>

    <!-- 下一篇文章的预览卡片 -->
    <a href="<?php echo esc_url(get_permalink($nav_post)); ?>" class="nav-card group block rounded-lg overflow-hidden">
        <div class="flex flex-col md:flex-row h-auto md:h-64">
            <div class="nav-card-image-wrapper md:w-2/5 relative overflow-hidden">
                <?php if (has_post_thumbnail($nav_post)) : 
                    $nav_premium_info = folio_get_post_premium_info($nav_post->ID);
                    $nav_should_blur = $nav_premium_info['is_premium'] && !$nav_premium_info['can_access'];
                    $nav_blur_class = $nav_should_blur ? 'folio-premium-blur' : '';
                    $nav_image_class = 'w-full h-full object-cover transition duration-500 group-hover:scale-110';
                    if ($nav_blur_class) {
                        $nav_image_class .= ' ' . $nav_blur_class;
                    }
                ?>
                    <?php echo get_the_post_thumbnail($nav_post, 'portfolio-hero', array(
                        'class' => $nav_image_class,
                    )); ?>
                <?php else : ?>
                    <div class="w-full h-full min-h-[200px] bg-gradient-to-br from-gray-200 to-gray-300"></div>
                <?php endif; ?>
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-5 transition"></div>
            </div>
            <div class="p-6 md:p-8 md:w-3/5 flex flex-col justify-center">
                <div class="nav-card-category text-xs uppercase tracking-widest mb-3">
                    <?php 
                    $nav_categories = get_the_category($nav_post->ID);
                    if ($nav_categories && !is_wp_error($nav_categories)) {
                        echo esc_html($nav_categories[0]->name);
                    }
                    ?>
                </div>
                <h3 class="nav-card-title text-2xl md:text-3xl font-black uppercase mb-3 transition">
                    <?php echo esc_html(get_the_title($nav_post)); ?>
                </h3>
                <?php if (has_excerpt($nav_post)) : ?>
                <p class="nav-card-excerpt text-sm mb-4 line-clamp-2">
                    <?php echo esc_html(get_the_excerpt($nav_post)); ?>
                </p>
                <?php endif; ?>
                <div class="nav-card-readmore flex items-center gap-2 text-xs font-bold uppercase tracking-widest">
                    <span><?php esc_html_e('Read More', 'folio'); ?></span>
                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </div>
            </div>
        </div>
    </a>
</footer>
<?php endif; ?>

<?php endwhile; ?>

<?php get_footer(); ?>

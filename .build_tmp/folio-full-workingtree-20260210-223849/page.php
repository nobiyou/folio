<?php
/**
 * Page Template
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) : the_post();
?>

<!-- 面包屑导航 -->
<div class="mb-4">
    <?php if (function_exists('folio_breadcrumbs')) folio_breadcrumbs(); ?>
</div>

<!-- 页面头部信息区 -->
<header class="relative mb-8">
    <!-- 页面标题 -->
    <h1 class="page-title text-3xl md:text-5xl font-black uppercase leading-tight tracking-tight mb-4">
        <?php the_title(); ?>
    </h1>
    
    <?php if (has_excerpt()) : ?>
    <div class="prose text-gray-600 leading-relaxed font-light max-w-2xl mb-6">
        <p class="text-base font-medium text-gray-700"><?php echo get_the_excerpt(); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- 页面元信息 -->
    <div class="flex flex-wrap items-center gap-4 py-4 text-xs text-gray-500 border-t">
        <!-- 发布日期 -->
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span><?php echo get_the_date('F j, Y'); ?></span>
        </div>
        
        <!-- 最后修改日期 -->
        <?php if (get_the_modified_date() !== get_the_date()) : ?>
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.5m7.5 0V4m0 5h.5M5 20h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z"/>
            </svg>
            <span><?php esc_html_e('Updated:', 'folio'); ?> <?php echo get_the_modified_date('F j, Y'); ?></span>
        </div>
        <?php endif; ?>
        
        <!-- 管理员编辑链接 -->
        <?php if (current_user_can('edit_post', get_the_ID())) : ?>
        <a href="<?php echo esc_url(get_edit_post_link()); ?>" class="admin-edit-link flex items-center gap-1.5 transition hover:text-gray-700" target="_blank">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span><?php esc_html_e('Edit', 'folio'); ?></span>
        </a>
        <?php endif; ?>
    </div>
    
    <!-- 特色图片 -->
    <?php if (has_post_thumbnail()) : ?>
    <div class="page-featured-image mt-8 mb-8">
        <div class="relative overflow-hidden rounded-lg">
            <?php 
            $premium_info = function_exists('folio_get_post_premium_info') ? folio_get_post_premium_info(get_the_ID()) : array('is_premium' => false, 'can_access' => true);
            $should_blur = $premium_info['is_premium'] && !$premium_info['can_access'];
            $image_class = 'w-full h-auto object-cover';
            if ($should_blur) {
                $image_class .= ' folio-premium-blur';
            }
            ?>
            <?php echo get_the_post_thumbnail(get_the_ID(), 'large', array(
                'class' => $image_class,
            )); ?>
        </div>
    </div>
    <?php endif; ?>
</header>

<!-- 内容展示区 -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    <!-- 页面内容 -->
    <main class="prose prose-lg max-w-none lg:col-span-8">
        <div class="max-w-4xl">
            <?php the_content(); ?>
            
            <?php
            // 分页链接（如果内容使用了 <!--nextpage--> 标签）
            wp_link_pages(array(
                'before' => '<div class="page-links mt-8 pt-8 border-t border-gray-200"><span class="text-sm font-bold uppercase tracking-widest text-gray-500">' . esc_html__('Pages:', 'folio') . '</span>',
                'after'  => '</div>',
                'link_before' => '<span class="px-2 py-1 mx-1 border border-gray-300 rounded hover:bg-gray-100 transition">',
                'link_after' => '</span>',
            ));
            ?>
        </div>
    </main>
    
    <!-- 右侧边栏 -->
    <aside class="lg:col-span-4">
        <div class="sticky top-8">
            <?php if (is_active_sidebar('page-sidebar')) : ?>
                <?php dynamic_sidebar('page-sidebar'); ?>
            <?php elseif (is_active_sidebar('single-post-sidebar')) : ?>
                <?php dynamic_sidebar('single-post-sidebar'); ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php endwhile; ?>

<?php get_footer(); ?>


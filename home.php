<?php
/**
 * Home Template (Latest Posts)
 * 
 * 当 WordPress 设置为"显示最新文章"时使用此模板
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// 使用缓存优化的文章查询
$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$cache_key = 'folio_home_posts_' . get_current_user_id() . '_' . $paged;
$cached_posts = wp_cache_get($cache_key);

if ($cached_posts === false) {
    // 使用主查询的结果（functions.php 已经处理了查询数量调整）
    global $wp_query;
    
    $posts_data = array();
    if ($wp_query->have_posts()) {
        while ($wp_query->have_posts()) {
            $wp_query->the_post();
            $posts_data[] = get_post();
        }
        wp_reset_postdata();
    }
    
    // 缓存查询结果（5分钟）
    wp_cache_set($cache_key, $posts_data, '', 300);
    $cached_posts = $posts_data;
}

// 检查是否显示 Brain Powered Fun 六边形
$theme_options = get_option('folio_theme_options', array());
$show_brain_hexagon = !isset($theme_options['show_brain_hexagon']) || !empty($theme_options['show_brain_hexagon']);

// 如果有文章，批量获取保护信息和权限
$post_ids = array();
foreach ($cached_posts as $post) {
    $post_ids[] = $post->ID;
}

// 使用批量缓存功能
$protection_info = array();
$user_permissions = array();
if (!empty($post_ids)) {
    $protection_info = folio_get_bulk_protection_info($post_ids);
    $user_permissions = folio_check_bulk_permissions($post_ids);
}
?>

<!-- 文章网格 -->
<main class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

    <?php if (!empty($cached_posts)) : ?>
        <?php foreach ($cached_posts as $post) : ?>
            <?php 
            // 设置全局$post对象
            $GLOBALS['post'] = $post;
            setup_postdata($post);
            
            // 传递缓存的保护信息和权限信息到模板
            set_query_var('cached_protection_info', $protection_info[$post->ID] ?? array());
            set_query_var('cached_user_permission', $user_permissions[$post->ID] ?? false);
            ?>
            <?php get_template_part('template-parts/content', 'post'); ?>
        <?php endforeach; ?>
        <?php wp_reset_postdata(); ?>
        
        <?php
        // 只在第一页显示 Brain Powered Fun 六边形，放在列表内
        // Brain Powered Fun 六边形在分页内，所以首页的分页数量是指定的数量-1
        if ($show_brain_hexagon && $paged == 1) :
        ?>
        <!-- Brain Powered Fun 占位符 - 在列表内 -->
        <div class="relative flex items-center justify-center aspect-[3/4]">
            <div class="hexagon hexagon-placeholder">
                <div class="hexagon-content">
                    <div class="hexagon-placeholder-circle">
                        <h3>
                            Brain<br>Powered<br>Creative<br>Fun
                        </h3>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 分页 -->
        <?php
        global $wp_query;
        if ($wp_query->max_num_pages > 1) :
        ?>
        <div class="col-span-full">
            <?php if (!is_user_logged_in()) : ?>
                <div class="text-center">
                    <a href="<?php echo esc_url(home_url('user-center/login')); ?>" 
                       class="inline-flex items-center px-6 py-3 bg-black text-white font-semibold rounded-lg hover:bg-gray-800 transition-colors">
                        Login To Load More
                    </a>
                </div>
            <?php else : ?>
                <?php
                the_posts_pagination(array(
                    'mid_size'  => 2,
                    'prev_text' => __('&larr; Previous', 'folio'),
                    'next_text' => __('Next &rarr;', 'folio'),
                ));
                ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    <?php else : ?>
        <div class="col-span-full text-center py-16">
            <p class="text-gray-500"><?php esc_html_e('暂无文章', 'folio'); ?></p>
        </div>
    <?php endif; ?>

</main>

<?php get_footer(); ?>

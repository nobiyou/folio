<?php
/**
 * Front Page Template
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// 使用缓存优化的文章查询
// 从主题设置读取每页文章数
$theme_options = get_option('folio_theme_options', array());
$posts_per_page = isset($theme_options['posts_per_page']) && $theme_options['posts_per_page'] > 0 
    ? absint($theme_options['posts_per_page']) 
    : 12;

// 检查是否显示 Brain Powered Fun 六边形
$show_brain_hexagon = !isset($theme_options['show_brain_hexagon']) || !empty($theme_options['show_brain_hexagon']);

// 如果显示六边形，查询数量需要减1（因为六边形占一个位置）
// Brain Powered Fun 六边形在分页内，所以首页的分页数量是指定的数量-1
$query_posts_per_page = $show_brain_hexagon ? max(1, $posts_per_page - 1) : $posts_per_page;

$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$cache_key = 'folio_front_page_posts_' . get_current_user_id() . '_' . $paged;
$cached_posts = wp_cache_get($cache_key);
$cached_query_info = wp_cache_get($cache_key . '_query_info');

if ($cached_posts === false || $cached_query_info === false) {
    // Query posts
    $posts_query = new WP_Query(array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $query_posts_per_page,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'update_post_meta_cache' => false, // 性能优化：不预加载元数据
        'update_post_term_cache' => false, // 性能优化：不预加载分类
    ));
    
    $posts_data = array();
    if ($posts_query->have_posts()) {
        while ($posts_query->have_posts()) {
            $posts_query->the_post();
            $posts_data[] = get_post();
        }
        wp_reset_postdata();
    }
    
    // 保存查询信息用于分页
    $query_info = array(
        'max_num_pages' => $posts_query->max_num_pages,
        'found_posts'   => $posts_query->found_posts,
    );
    
    // 设置全局查询对象用于分页
    global $wp_query;
    $wp_query->max_num_pages = $posts_query->max_num_pages;
    $wp_query->found_posts = $posts_query->found_posts;
    
    // 缓存查询结果和查询信息（5分钟）
    wp_cache_set($cache_key, $posts_data, '', 300);
    wp_cache_set($cache_key . '_query_info', $query_info, '', 300);
    $cached_posts = $posts_data;
} else {
    // 如果使用缓存，恢复查询信息用于分页
    global $wp_query;
    $wp_query->max_num_pages = $cached_query_info['max_num_pages'];
    $wp_query->found_posts = $cached_query_info['found_posts'];
}

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

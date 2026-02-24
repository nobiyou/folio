<?php
/**
 * Archive Template
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<!-- 栏目页标题区 -->
<section class="archive-header mb-16 pb-8">
    <!-- 面包屑 -->
    <?php if (function_exists('folio_breadcrumbs')) folio_breadcrumbs(); ?>
    
    <div class="flex flex-col md:flex-row items-end gap-8">
        <h1 class="archive-title text-6xl md:text-8xl font-black uppercase tracking-tighter leading-none">
            <?php
            if (is_category()) {
                single_cat_title();
            } elseif (is_tag()) {
                single_tag_title();
            } elseif (is_author()) {
                the_author();
            } elseif (is_date()) {
                echo get_the_date('Y F');
            } else {
                esc_html_e('All', 'folio');
                echo '<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-gray-800 to-gray-500">';
                esc_html_e('Posts', 'folio');
                echo '</span>';
            }
            ?>
        </h1>
        <p class="archive-description md:max-w-md text-sm md:text-base font-normal leading-relaxed pb-2">
            <?php
            if (is_category()) {
                echo category_description();
            } elseif (is_tag()) {
                echo tag_description();
            } else {
                echo esc_html(get_bloginfo('description'));
            }
            ?>
        </p>
    </div>
</section>

<!-- 文章列表 Grid -->
<main class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <?php get_template_part('template-parts/content', 'post'); ?>
        <?php endwhile; ?>
    <?php else : ?>
        <div class="col-span-full text-center py-16">
            <p class="text-gray-500"><?php esc_html_e('No posts yet', 'folio'); ?></p>
        </div>
    <?php endif; ?>

</main>

<!-- 分页 Pagination -->
<?php
global $wp_query;
$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$total_pages = $wp_query->max_num_pages;

if ($total_pages > 1) :
?>
    <div class="col-span-full mt-8">
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

<?php get_footer(); ?>

<?php
/**
 * Main Index Template (Fallback)
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <?php get_template_part('template-parts/content', 'post'); ?>
        <?php endwhile; ?>
        
        <!-- 分页 -->
        <div class="col-span-full">
            <?php if (!is_user_logged_in()) : ?>
                <div class="text-center">
                    <a href="<?php echo esc_url(function_exists('folio_url_with_current_lang') ? folio_url_with_current_lang(home_url('user-center/login')) : home_url('user-center/login')); ?>" 
                       class="inline-flex items-center px-6 py-3 bg-black text-white font-semibold rounded-lg hover:bg-gray-800 transition-colors">
                        <?php esc_html_e('Login To Load More', 'folio'); ?>
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
        
    <?php else : ?>
        <div class="col-span-full text-center py-16">
            <p class="text-gray-500"><?php esc_html_e('No content yet', 'folio'); ?></p>
        </div>
    <?php endif; ?>

</main>

<?php get_footer(); ?>

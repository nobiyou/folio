<?php
/**
 * 404 Error Page Template
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="flex flex-col items-center justify-center min-h-[60vh] text-center">
    
    <!-- 404 六边形 -->
    <div class="hexagon-badge w-[200px] h-[220px] mb-8 shadow-xl">
        <div class="text-6xl font-black text-yellow-500 mb-2">404</div>
        <div class="w-12 h-1 bg-gray-700 mb-2"></div>
        <div class="text-sm font-bold uppercase tracking-widest">
            <?php esc_html_e('Not Found', 'folio'); ?>
        </div>
    </div>
    
    <h1 class="text-4xl md:text-6xl font-black uppercase tracking-tighter mb-4 text-gray-900">
        <?php esc_html_e('Page Not Found', 'folio'); ?>
    </h1>
    
    <p class="text-gray-500 mb-8 max-w-md">
        <?php esc_html_e('Sorry, the page you requested does not exist or has been removed.', 'folio'); ?>
    </p>
    
    <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-black text-white font-bold uppercase text-sm tracking-wider hover:bg-gray-800 transition rounded">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        <?php esc_html_e('Back to Home', 'folio'); ?>
    </a>
    
</main>

<?php get_footer(); ?>

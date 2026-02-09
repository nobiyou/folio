<?php
/**
 * User Center - Favorites
 * 
 * 用户收藏列表
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$all_favorites = folio_get_user_favorites($user_id);
$total = count($all_favorites);
$favorites = array_slice($all_favorites, $offset, $per_page);
$total_pages = ceil($total / $per_page);
?>

<div class="favorites-page">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold"><?php esc_html_e('我的收藏', 'folio'); ?></h1>
        <span class="text-sm">
            <?php printf(esc_html__('共 %d 个作品', 'folio'), $total); ?>
        </span>
    </div>

    <?php if (!empty($favorites)) : ?>
    <div class="favorites-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <?php foreach ($favorites as $post_id) : 
            $post = get_post($post_id);
            if (!$post || $post->post_status !== 'publish') continue;
            setup_postdata($post);
            
            // 获取作品信息
            $year = get_post_meta($post_id, 'portfolio_year', true);
            $terms = get_the_terms($post_id, 'portfolio_category');
            $category = $terms && !is_wp_error($terms) ? $terms[0]->name : '';
        ?>
        <div class="favorite-card">
            <div class="relative group">
                <a href="<?php echo get_permalink($post_id); ?>" class="block">
                    <?php if (has_post_thumbnail($post_id)) : ?>
                    <div class="favorite-image">
                        <?php echo get_the_post_thumbnail($post_id, 'medium_large', array(
                            'class' => 'w-full h-48 object-cover rounded-lg',
                            'loading' => 'lazy'
                        )); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 悬停遮罩 -->
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 rounded-lg flex items-center justify-center">
                        <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                </a>
                
                <!-- 取消收藏按钮 -->
                <button class="remove-favorite absolute top-2 right-2 w-8 h-8 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center hover:bg-red-600" 
                        data-post-id="<?php echo $post_id; ?>" 
                        title="<?php esc_attr_e('取消收藏', 'folio'); ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="favorite-info mt-4">
                <h3 class="font-bold text-lg mb-2">
                    <a href="<?php echo get_permalink($post_id); ?>" class="hover:opacity-80 transition-opacity">
                        <?php echo get_the_title($post_id); ?>
                    </a>
                </h3>
                <div class="flex items-center gap-4 text-xs uppercase font-bold">
                    <?php if ($year) : ?>
                    <span><?php echo esc_html($year); ?></span>
                    <?php endif; ?>
                    <?php if ($category) : ?>
                    <span><?php echo esc_html($category); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- 统计信息 -->
                <div class="flex items-center gap-4 mt-2 text-sm">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <?php echo esc_html(folio_get_views($post_id)); ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <?php echo esc_html(folio_get_likes($post_id)); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; wp_reset_postdata(); ?>
    </div>

    <!-- 分页 -->
    <?php if ($total_pages > 1) : ?>
    <div class="pagination flex items-center justify-center gap-2">
        <?php if ($page > 1) : ?>
        <a href="<?php echo add_query_arg('paged', $page - 1); ?>" class="pagination-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
        <a href="<?php echo add_query_arg('paged', $i); ?>" 
           class="pagination-btn <?php echo ($i === $page) ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages) : ?>
        <a href="<?php echo add_query_arg('paged', $page + 1); ?>" class="pagination-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="empty-state text-center py-16">
        <svg class="empty-state-icon w-20 h-20 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
        </svg>
        <h3 class="empty-state-title text-xl font-medium mb-4"><?php esc_html_e('还没有收藏任何作品', 'folio'); ?></h3>
        <p class="empty-state-text mb-6"><?php esc_html_e('去浏览一些精彩的作品，点击星形图标即可收藏', 'folio'); ?></p>
        <a href="<?php echo get_post_type_archive_link('portfolio'); ?>" class="btn-primary">
            <?php esc_html_e('浏览作品', 'folio'); ?>
        </a>
    </div>
    <?php endif; ?>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    // 取消收藏功能
    document.querySelectorAll('.remove-favorite').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const postId = this.dataset.postId;
            const card = this.closest('.favorite-card');
            
            if (!confirm('<?php echo esc_js(__('确定要取消收藏这个作品吗？', 'folio')); ?>')) {
                return;
            }

            // 发送 AJAX 请求
            const formData = new FormData();
            formData.append('action', 'folio_favorite_post');
            formData.append('post_id', postId);
            formData.append('action_type', 'remove');
            formData.append('nonce', folio_ajax.nonce);

            fetch(folio_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 移除卡片
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        card.remove();
                        // 检查是否还有收藏
                        if (document.querySelectorAll('.favorite-card').length === 0) {
                            location.reload();
                        }
                    }, 300);
                } else {
                    alert(data.data.message || '<?php echo esc_js(__('操作失败', 'folio')); ?>');
                }
            })
            .catch(() => {
                alert('<?php echo esc_js(__('网络错误，请稍后重试', 'folio')); ?>');
            });
        });
    });
});
</script>

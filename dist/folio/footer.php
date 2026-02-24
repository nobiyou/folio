<?php
/**
 * Footer Template
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

// 从主题设置读取版权文本
$theme_options = get_option('folio_theme_options', array());
$copyright_text = isset($theme_options['copyright']) && !empty($theme_options['copyright']) 
    ? $theme_options['copyright'] 
    : sprintf(
        __('&copy; %s Design & build by %s.', 'folio'),
        date('Y'),
        get_bloginfo('name')
    );
?>
        </div><!-- #main-content -->
        
            <!-- 底部 Footer -->
            <footer class="mt-16 border-t border-gray-200 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-gray-400">
                <p><?php echo wp_kses_post($copyright_text); ?></p>
                <div class="flex gap-6 mt-4 md:mt-0 font-bold uppercase">
                    <?php if (is_singular('post')) : ?>
                    <button class="flex items-center gap-2 hover:text-gray-600 transition" onclick="window.print()">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg> 
                        <?php esc_html_e('Print', 'folio'); ?>
                    </button>
                    <?php endif; ?>
                    <button class="flex items-center gap-2 hover:text-gray-600 transition" onclick="mpbShare()">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"/>
                        </svg>
                        <?php esc_html_e('Share', 'folio'); ?>
                    </button>
                </div>
            </footer>
        </div><!-- .flex-1 -->
    </div><!-- .flex -->

    <!-- 返回顶部按钮 -->
    <button id="back-to-top" class="back-to-top" aria-label="<?php esc_attr_e('Back to top', 'folio'); ?>">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15"></polyline>
        </svg>
    </button>

    <!-- 通知弹窗（所有用户可见） -->
    <div id="folio-notification-popup" style="display: none;">
        <div class="notification-overlay"></div>
        <div class="notification-modal">
            <div class="notification-header">
                <h3><?php esc_html_e('My Notifications', 'folio'); ?></h3>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button id="mark-all-read-btn" class="mark-all-read-btn" style="display: none; background: #0073aa; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        <?php esc_html_e('Mark All as Read', 'folio'); ?>
                    </button>
                    <button class="notification-close" aria-label="<?php esc_attr_e('Close', 'folio'); ?>" style="background: none; border: none; font-size: 24px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: all 0.2s ease;">&times;</button>
                </div>
            </div>
            <div class="notification-content">
                <div id="notification-list">
                    <div class="no-notifications"><?php esc_html_e('Loading...', 'folio'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>

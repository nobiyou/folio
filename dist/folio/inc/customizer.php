<?php
/**
 * Theme Customizer Settings
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register customizer settings
 */
function folio_customize_register($wp_customize) {
    
    // ========================================
    // 注意：社交链接设置已移至主题设置页面（外观 > 主题设置 > 社交链接）
    // ========================================

    // ========================================
    // 注意：主题模式设置已移至主题设置页面（外观 > 主题设置 > 常规设置）
    // ========================================

    // ========================================
    // 注意：页脚设置已移至主题设置页面（外观 > 主题设置 > 常规设置）
    // ========================================

    // ========================================
    // 注意：SEO 设置已移至主题设置页面（外观 > 主题设置 > SEO优化）
    // ========================================

    // ========================================
    // 注意：作品集设置已移至主题设置页面（外观 > 主题设置 > 常规设置）
    // ========================================

    // ========================================
    // Selective Refresh for Live Preview
    // ========================================
    // 注意：页脚设置的 selective refresh 已移除，因为页脚设置已移至主题设置页面
}
add_action('customize_register', 'folio_customize_register');

/**
 * Sanitize checkbox value
 */
function folio_sanitize_checkbox($checked) {
    return ((isset($checked) && true == $checked) ? true : false);
}

/**
 * Enqueue customizer preview script
 */
function folio_customize_preview_js() {
    wp_enqueue_script(
        'mpb-customizer-preview',
        FOLIO_URI . '/assets/js/customizer-preview.js',
        array('customize-preview'),
        FOLIO_VERSION,
        true
    );
}
add_action('customize_preview_init', 'folio_customize_preview_js');

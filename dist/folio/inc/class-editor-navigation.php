<?php
/**
 * Editor Navigation Enhancement
 * 
 * 在文章编辑器的媒体按钮区域添加上一篇/下一篇导航按钮
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 在媒体按钮区域添加上一篇/下一篇按钮
 */
add_action('media_buttons', 'folio_add_post_navigation_buttons', 20);
function folio_add_post_navigation_buttons() {
    global $post;
    
    // 只在文章编辑页面显示
    if (!$post || $post->post_type !== 'post') {
        return;
    }

    // 获取所有状态的相邻文章（包含草稿、待审等）
    $prev_post = folio_get_adjacent_post_by_date($post->ID, 'previous');
    $next_post = folio_get_adjacent_post_by_date($post->ID, 'next');

    // 输出按钮（始终显示）
    echo folio_get_adjacent_button_html($prev_post, 'previous');
    echo folio_get_adjacent_button_html($next_post, 'next');
}

/**
 * 获取按日期排序的相邻文章
 * 
 * @param int $current_id 当前文章ID
 * @param string $direction 方向：'next' 或 'previous'
 * @return object|false 相邻文章对象或false
 */
function folio_get_adjacent_post_by_date($current_id, $direction = 'next') {
    global $wpdb;
    
    $current_post = get_post($current_id);
    if (!$current_post) {
        return false;
    }
    
    $order = ($direction === 'next') ? 'ASC' : 'DESC';
    $compare = ($direction === 'next') ? '>' : '<';
    
    $query = $wpdb->prepare(
        "SELECT * FROM $wpdb->posts
        WHERE post_type = 'post'
        AND post_status IN ('publish', 'pending', 'draft', 'future', 'private')
        AND post_date {$compare} %s
        AND ID != %d
        ORDER BY post_date {$order}
        LIMIT 1",
        $current_post->post_date,
        $current_post->ID
    );
    
    return $wpdb->get_row($query);
}

/**
 * 生成按钮HTML
 * 
 * @param object|false $adjacent_post 相邻文章对象或false
 * @param string $direction 方向：'previous' 或 'next'
 * @return string 按钮HTML
 */
function folio_get_adjacent_button_html($adjacent_post, $direction) {
    $is_prev = ($direction === 'previous');
    $label = $is_prev ? __('Previous Post', 'folio') : __('Next Post', 'folio');
    $icon = $is_prev ? 'dashicons-arrow-left-alt2' : 'dashicons-arrow-right-alt2';
    $icon_pos = $is_prev ? 'left' : 'right';
    
    // 构建图标部分
    $left_icon = ($icon_pos === 'left') ? '<span class="wp-media-buttons-icon dashicons ' . esc_attr($icon) . '"></span>' : '';
    $right_icon = ($icon_pos === 'right') ? '<span class="wp-media-buttons-icon dashicons ' . esc_attr($icon) . '"></span>' : '';
    
    if ($adjacent_post) {
        $link = get_edit_post_link($adjacent_post->ID);
        return sprintf(
            '<a href="%s" class="button folio-adjacent-post-button">%s%s%s</a>',
            esc_url($link),
            $left_icon,
            esc_html($label),
            $right_icon
        );
    }
    
    // 无相邻文章时显示禁用状态
    return sprintf(
        '<button class="button folio-adjacent-post-button" disabled>%s%s%s</button>',
        $left_icon,
        esc_html($label),
        $right_icon
    );
}

/**
 * 添加按钮样式
 */
add_action('admin_head', 'folio_add_adjacent_button_styles');
function folio_add_adjacent_button_styles() {
    // 只在文章编辑页面加载样式
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'post' || $screen->base !== 'post') {
        return;
    }
    
    ?>
    <style>
        .folio-adjacent-post-button {
            margin-left: 10px;
            vertical-align: middle;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .folio-adjacent-post-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .folio-adjacent-post-button .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
    </style>
    <?php
}

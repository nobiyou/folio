<?php
/**
 * Admin Posts List Enhancements
 * 
 * 后台文章列表增强功能
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 添加特色图像列到文章列表 (仅限管理员)
 *
 * @param array $columns 现有的列
 * @return array 修改后的列
 */
function folio_add_featured_image_column($columns) {
    if (!current_user_can('manage_options')) {
        return $columns; // 仅限管理员
    }

    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ('title' === $key) { // 在标题列之后插入
            $new_columns['featured_image'] = __('Featured Image', 'folio'); // 列标题
        }
    }
    return $new_columns;
}
add_filter('manage_posts_columns', 'folio_add_featured_image_column');

/**
 * 填充特色图像列的内容 (仅限管理员)
 *
 * @param string $column  当前列的名称
 * @param int    $post_id 当前文章的 ID
 */
function folio_display_featured_image_column($column, $post_id) {
    if (!current_user_can('manage_options')) {
        return; // 仅限管理员
    }

    switch ($column) {
        case 'featured_image':
            $featured_image_id = get_post_thumbnail_id($post_id);
            if ($featured_image_id) {
                $featured_image_url = wp_get_attachment_image_src($featured_image_id, 'thumbnail'); // 获取缩略图 URL
                if ($featured_image_url) {
                    echo '<img class="folio-featured-image-preview" src="' . esc_url($featured_image_url[0]) . '" alt="' . esc_attr(get_the_title($post_id)) . '" />';
                }
            } else {
                echo '—'; // 没有头图时显示
            }
            break;
    }
}
add_action('manage_posts_custom_column', 'folio_display_featured_image_column', 10, 2);

/**
 * 添加特色图像列样式
 */
function folio_admin_featured_image_styles() {
    // 只在文章列表页面加载样式
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'post' || $screen->base !== 'edit') {
        return;
    }
    
    ?>
    <style>
        .column-featured_image {
            width: 100px;
            text-align: center;
        }
        .folio-featured-image-preview {
            max-width: 80px;
            max-height: 80px;
            border: 1px solid #ddd;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            vertical-align: middle;
            border-radius: 3px;
        }
    </style>
    <?php
}
add_action('admin_head', 'folio_admin_featured_image_styles');

/**
 * 在文章列表中添加批量操作：设置/取消 VIP/SVIP 权限 (仅限管理员)
 *
 * @param array $bulk_actions 现有批量操作
 * @return array
 */
function folio_add_vip_bulk_actions($bulk_actions) {
    if (!current_user_can('manage_options')) {
        return $bulk_actions; // 仅限管理员
    }

    // 在现有批量操作中追加
    $bulk_actions['folio_set_vip']  = __('Set as VIP Only', 'folio');
    $bulk_actions['folio_set_svip'] = __('Set as SVIP Only', 'folio');
    $bulk_actions['folio_clear_vip'] = __('Clear Membership Restriction', 'folio');

    return $bulk_actions;
}
add_filter('bulk_actions-edit-post', 'folio_add_vip_bulk_actions');

/**
 * 处理批量 VIP/SVIP 权限操作
 *
 * @param string $redirect_url 操作完成后的跳转 URL
 * @param string $action       当前批量操作 key
 * @param array  $post_ids     被选中的文章 ID 列表
 * @return string
 */
function folio_handle_vip_bulk_actions($redirect_url, $action, $post_ids) {
    if (!current_user_can('manage_options')) {
        return $redirect_url; // 仅限管理员
    }

    $processed = 0;

    if ($action === 'folio_set_vip') {
        foreach ($post_ids as $post_id) {
            // 设置为受保护内容，等级为 VIP
            update_post_meta($post_id, '_folio_premium_content', 1);
            update_post_meta($post_id, '_folio_required_level', 'vip');
            $processed++;
        }
        $redirect_url = add_query_arg('folio_vip_set', $processed, $redirect_url);
    } elseif ($action === 'folio_set_svip') {
        foreach ($post_ids as $post_id) {
            // 设置为受保护内容，等级为 SVIP
            update_post_meta($post_id, '_folio_premium_content', 1);
            update_post_meta($post_id, '_folio_required_level', 'svip');
            $processed++;
        }
        $redirect_url = add_query_arg('folio_svip_set', $processed, $redirect_url);
    } elseif ($action === 'folio_clear_vip') {
        foreach ($post_ids as $post_id) {
            // 取消受保护标记（无论之前是 VIP 还是 SVIP）
            delete_post_meta($post_id, '_folio_premium_content');
            delete_post_meta($post_id, '_folio_required_level');
            $processed++;
        }
        $redirect_url = add_query_arg('folio_vip_cleared', $processed, $redirect_url);
    }

    return $redirect_url;
}
add_filter('handle_bulk_actions-edit-post', 'folio_handle_vip_bulk_actions', 10, 3);

/**
 * 显示批量 VIP 操作结果提示
 */
function folio_vip_bulk_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!empty($_REQUEST['folio_vip_set'])) {
        $count = intval($_REQUEST['folio_vip_set']);
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(__('%d posts have been set to VIP only.', 'folio'), $count))
        );
    }

    if (!empty($_REQUEST['folio_svip_set'])) {
        $count = intval($_REQUEST['folio_svip_set']);
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(__('%d posts have been set to SVIP only.', 'folio'), $count))
        );
    }

    if (!empty($_REQUEST['folio_vip_cleared'])) {
        $count = intval($_REQUEST['folio_vip_cleared']);
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(__('Membership restriction removed for %d posts.', 'folio'), $count))
        );
    }
}
add_action('admin_notices', 'folio_vip_bulk_admin_notice');

/**
 * 收集需要显示编辑状态的文章数据
 * 简化版本，只检查是否被编辑，不计算时间差和编辑者信息
 */
function folio_collect_edited_posts_data() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'post' || $screen->base !== 'edit') {
        return array();
    }
    
    global $wp_query;
    if (!$wp_query || empty($wp_query->posts)) {
        return array();
    }
    
    $edited_posts = array();
    
    foreach ($wp_query->posts as $post) {
        // 检查必要的属性是否存在
        if (!isset($post->post_modified) || !isset($post->post_date) || !isset($post->ID)) {
            continue;
        }
        
        // 确保日期字符串不为空
        $post_modified = trim($post->post_modified);
        $post_date = trim($post->post_date);
        
        if (empty($post_modified) || empty($post_date)) {
            continue;
        }
        
        // 转换为时间戳，PHP 8.2 中 strtotime 对无效值返回 false
        $modified_timestamp = strtotime($post_modified);
        $date_timestamp = strtotime($post_date);
        
        // 检查时间戳是否有效（不为 false）
        if ($modified_timestamp === false || $date_timestamp === false) {
            continue;
        }
        
        $is_edited = false;
        
        // 方法1：检查 post_modified 是否晚于 post_date（超过1分钟）
        if ($modified_timestamp > ($date_timestamp + 60)) {
            $is_edited = true;
        }
        
        // 方法2：如果方法1没检测到，检查是否有修订版本（说明文章被编辑过）
        // 注意：只检查最近是否有修订版本，避免性能问题
        if (!$is_edited) {
            // PHP 8.2 兼容性：检查修订版本是否被禁用
            $post_obj = get_post($post->ID);
            if ($post_obj && is_a($post_obj, 'WP_Post')) {
                $revisions_to_keep = wp_revisions_to_keep($post_obj);
                // 如果修订版本功能未禁用（> 0），则检查是否有修订版本
                if ($revisions_to_keep > 0) {
                    $revisions = wp_get_post_revisions($post->ID, array('numberposts' => 1));
                    // PHP 8.2 兼容性：确保 $revisions 是数组且不为空
                    if (is_array($revisions) && !empty($revisions)) {
                        // 获取最新的修订版本
                        $latest_revision = reset($revisions);
                        // PHP 8.2 兼容性：检查 reset() 返回的是否是有效对象
                        // reset() 在 PHP 8.2 中对空数组可能返回 false
                        if ($latest_revision !== false && is_object($latest_revision) && isset($latest_revision->post_date)) {
                            $revision_date_str = trim($latest_revision->post_date);
                            if (!empty($revision_date_str)) {
                                $revision_date = strtotime($revision_date_str);
                                // 如果修订版本的时间晚于创建时间（超过1分钟），说明被编辑过
                                if ($revision_date !== false && $revision_date > ($date_timestamp + 60)) {
                                    $is_edited = true;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if ($is_edited) {
            $edited_posts[] = $post->ID;
        }
    }
    
    return $edited_posts;
}

/**
 * 为编辑状态添加样式和脚本
 * 使用 admin_footer 钩子，确保在页面底部输出
 */
function folio_admin_edited_status_styles() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'post' || $screen->base !== 'edit') {
        return;
    }
    
    // 收集编辑状态数据
    $edited_posts = folio_collect_edited_posts_data();
    
    if (empty($edited_posts)) {
        return;
    }
    
    ?>
    <style>
        .folio-edited-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 3px;
            font-size: 11px;
            line-height: 1.4;
            color: #856404;
        }
        .folio-edited-badge:hover {
            background: #ffe69c;
            border-color: #ff9800;
        }
        .folio-edited-icon {
            display: inline-block;
            margin-right: 2px;
        }
        .folio-edited-text {
            font-weight: 500;
        }
        .column-date {
            min-width: 140px;
        }
    </style>
    <script>
    jQuery(document).ready(function($) {
        // 编辑状态数据（简化为文章ID数组）
        var editedPosts = <?php echo json_encode($edited_posts); ?>;
        
        // 为每个编辑过的文章添加标记
        $.each(editedPosts, function(index, postId) {
            // 查找对应的表格行
            var $row = $('#post-' + postId);
            if ($row.length === 0) {
                // 如果找不到，尝试通过其他方式查找
                $row = $('tr').has('input[value="' + postId + '"]');
            }
            
            if ($row.length > 0) {
                var $dateCell = $row.find('.column-date');
                if ($dateCell.length > 0) {
                    // 检查是否已经添加了标记，避免重复添加
                    if ($dateCell.find('.folio-edited-badge').length === 0) {
                        var badge = '<br><span class="folio-edited-badge" title="<?php echo esc_js(__('This post has been edited', 'folio')); ?>">' +
                            '<span class="folio-edited-icon">✏️</span> ' +
                            '<span class="folio-edited-text"><?php echo esc_js(__('Edited', 'folio')); ?></span>' +
                            '</span>';
                        $dateCell.append(badge);
                    }
                }
            }
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'folio_admin_edited_status_styles');

<?php
/**
 * User Center - Notifications
 * 
 * 用户中心通知列表页面
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();

// 获取通知
if (!class_exists('folio_Membership_Notifications')) {
    echo '<div class="error-message">' . esc_html__('Notification system is not initialized', 'folio') . '</div>';
    return;
}

$notifications_class = folio_Membership_Notifications::get_instance();
$unread_count = $notifications_class->get_unread_count($user_id);

// 获取当前页
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$per_page = 20;
$offset = ($paged - 1) * $per_page;

// 获取通知列表（包括全局通知）
$notifications = $notifications_class->get_notifications($user_id, array(
    'limit' => $per_page,
    'offset' => $offset,
    'orderby' => 'created_at',
    'order' => 'DESC',
    'include_global' => true // 包含全局通知
));

// 获取总数（用于分页，包括全局通知）
global $wpdb;
$table_name = $wpdb->prefix . 'folio_notifications';
$total_notifications = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d OR user_id = 0",
    $user_id
));
$total_pages = ceil($total_notifications / $per_page);
?>

<div class="notifications-page">
    <div class="flex items-center justify-between mb-6">
        <h1 class="page-title text-2xl font-bold">
            <?php esc_html_e('My Notifications', 'folio'); ?>
            <?php if ($unread_count > 0) : ?>
            <span class="unread-badge ml-3 bg-red-500 text-white text-sm rounded-full px-3 py-1">
                <?php printf(esc_html__('%d unread', 'folio'), $unread_count); ?>
            </span>
            <?php endif; ?>
        </h1>
        
        <?php if ($unread_count > 0) : ?>
        <button id="mark-all-read-btn" class="btn-secondary text-sm">
            <?php esc_html_e('Mark all as read', 'folio'); ?>
        </button>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)) : ?>
    <div class="empty-state text-center py-12">
        <svg class="empty-state-icon w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <h3 class="empty-state-title text-lg font-medium mb-2"><?php esc_html_e('No notifications', 'folio'); ?></h3>
        <p class="empty-state-text text-gray-600"><?php esc_html_e('You have not received any notifications yet', 'folio'); ?></p>
    </div>
    <?php else : ?>
    
    <div id="notification-list" class="notification-list space-y-3">
        <?php foreach ($notifications as $notification) : 
            $read_class = $notification->is_read ? 'read' : 'unread';
            $type_icon = folio_get_notification_type_icon($notification->type);
            $time_ago = sprintf(esc_html__('%s ago', 'folio'), human_time_diff(strtotime($notification->created_at), current_time('timestamp')));
        ?>
        <div class="notification-item <?php echo esc_attr($read_class); ?> notification-<?php echo esc_attr($notification->type); ?>" 
             data-id="<?php echo esc_attr($notification->id); ?>">
            <div class="notification-content">
                <div class="notification-header flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3 flex-1">
                        <span class="notification-icon flex-shrink-0"><?php echo wp_kses_post($type_icon); ?></span>
                        <div class="flex-1">
                            <div class="notification-title font-semibold text-base mb-1">
                                <?php echo esc_html($notification->title); ?>
                            </div>
                            <div class="notification-message text-gray-600 text-sm mb-2">
                                <?php echo esc_html($notification->message); ?>
                            </div>
                            <div class="notification-time text-xs text-gray-400">
                                <?php echo esc_html($time_ago); ?>
                            </div>
                        </div>
                    </div>
                    <div class="notification-actions flex-shrink-0">
                        <?php if (!$notification->is_read) : ?>
                        <button class="mark-read-btn text-xs text-blue-500 hover:text-blue-600" 
                                data-id="<?php echo esc_attr($notification->id); ?>">
                            <?php esc_html_e('Mark as read', 'folio'); ?>
                        </button>
                        <?php endif; ?>
                        <button class="delete-notification-btn text-xs text-red-500 hover:text-red-600 ml-3" 
                                data-id="<?php echo esc_attr($notification->id); ?>">
                            <?php esc_html_e('Delete', 'folio'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1) : ?>
    <div class="pagination mt-8 flex justify-center gap-2">
        <?php
        $base_url = folio_User_Center::get_url('notifications');
        
        if ($paged > 1) {
            echo '<a href="' . esc_url(add_query_arg('paged', $paged - 1, $base_url)) . '" class="pagination-link">';
            esc_html_e('Previous', 'folio');
            echo '</a>';
        }
        
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $paged) {
                echo '<span class="pagination-current">' . esc_html($i) . '</span>';
            } else {
                echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" class="pagination-link">' . esc_html($i) . '</a>';
            }
        }
        
        if ($paged < $total_pages) {
            echo '<a href="' . esc_url(add_query_arg('paged', $paged + 1, $base_url)) . '" class="pagination-link">';
            esc_html_e('Next', 'folio');
            echo '</a>';
        }
        ?>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // 标记单个通知为已读
    $('.mark-read-btn').on('click', function(e) {
        e.preventDefault();
        var notificationId = $(this).data('id');
        var $item = $(this).closest('.notification-item');
        
        $.ajax({
            url: folioNotifications.ajaxurl,
            type: 'POST',
            data: {
                action: 'folio_mark_notification_read',
                notification_id: notificationId,
                nonce: folioNotifications.nonce
            },
            success: function(response) {
                if (response.success) {
                    $item.removeClass('unread').addClass('read');
                    $item.find('.mark-read-btn').remove();
                    // 更新未读数量
                    location.reload();
                }
            }
        });
    });
    
    // 删除通知
    $('.delete-notification-btn').on('click', function(e) {
        e.preventDefault();
        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this notification?', 'folio')); ?>')) {
            return;
        }
        
        var notificationId = $(this).data('id');
        var $item = $(this).closest('.notification-item');
        
        $.ajax({
            url: folioNotifications.ajaxurl,
            type: 'POST',
            data: {
                action: 'folio_delete_notification',
                notification_id: notificationId,
                nonce: folioNotifications.nonce
            },
            success: function(response) {
                if (response.success) {
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        // 如果没有通知了，刷新页面
                        if ($('.notification-item').length === 0) {
                            location.reload();
                        }
                    });
                }
            }
        });
    });
    
    // 标记全部为已读
    $('#mark-all-read-btn').on('click', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: folioNotifications.ajaxurl,
            type: 'POST',
            data: {
                action: 'folio_mark_all_read',
                nonce: folioNotifications.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.notification-item').removeClass('unread').addClass('read');
                    $('.mark-read-btn').remove();
                    $('#mark-all-read-btn').fadeOut();
                    location.reload();
                }
            }
        });
    });
    
    // 点击通知项标记为已读
    $('.notification-item.unread').on('click', function(e) {
        if ($(e.target).closest('.notification-actions').length) {
            return;
        }
        
        var notificationId = $(this).data('id');
        var $item = $(this);
        
        if (!$item.hasClass('read')) {
            $.ajax({
                url: folioNotifications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'folio_mark_notification_read',
                    notification_id: notificationId,
                    nonce: folioNotifications.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $item.removeClass('unread').addClass('read');
                        $item.find('.mark-read-btn').remove();
                    }
                }
            });
        }
    });
});
</script>

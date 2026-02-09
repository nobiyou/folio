<?php
/**
 * 通知小工具
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Notification_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'folio_notification_widget',
            '用户通知',
            array('description' => '显示当前用户的最新通知')
        );
    }

    /**
     * 前端显示
     */
    public function widget($args, $instance) {
        if (!is_user_logged_in()) {
            return;
        }

        $title = !empty($instance['title']) ? $instance['title'] : '我的通知';
        $limit = !empty($instance['limit']) ? (int) $instance['limit'] : 5;
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }

        $notifications = folio_get_user_latest_notifications(get_current_user_id(), $limit);
        $unread_count = folio_get_unread_notification_count();

        if (empty($notifications)) {
            echo '<p class="no-notifications">暂无通知</p>';
        } else {
            echo '<div class="folio-widget-notifications">';
            
            if ($unread_count > 0) {
                echo '<div class="unread-summary">您有 <strong>' . $unread_count . '</strong> 条未读通知</div>';
            }
            
            echo '<ul class="notification-list">';
            foreach ($notifications as $notification) {
                $read_class = $notification->is_read ? 'read' : 'unread';
                $type_icon = folio_get_notification_type_icon($notification->type);
                $time_ago = human_time_diff(strtotime($notification->created_at), current_time('timestamp')) . '前';
                
                echo '<li class="notification-item ' . esc_attr($read_class) . '">';
                echo '<span class="notification-icon">' . $type_icon . '</span>';
                echo '<div class="notification-content">';
                echo '<div class="notification-title">' . esc_html($notification->title) . '</div>';
                echo '<div class="notification-message">' . esc_html(wp_trim_words($notification->message, 15)) . '</div>';
                echo '<div class="notification-time">' . esc_html($time_ago) . '</div>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
            
            echo '<div class="notification-actions">';
            echo '<a href="' . esc_url(home_url('/user-center/?tab=notifications')) . '" class="view-all-notifications">查看所有通知</a>';
            echo '</div>';
            
            echo '</div>';
        }

        echo $args['after_widget'];
        
        // 添加样式
        $this->add_widget_styles();
    }

    /**
     * 后台表单
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '我的通知';
        $limit = !empty($instance['limit']) ? $instance['limit'] : 5;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">标题:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>">显示数量:</label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('limit')); ?>" 
                   type="number" step="1" min="1" max="20" value="<?php echo esc_attr($limit); ?>">
        </p>
        <?php
    }

    /**
     * 更新设置
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = (!empty($new_instance['limit'])) ? (int) $new_instance['limit'] : 5;
        return $instance;
    }

    /**
     * 添加小工具样式
     */
    private function add_widget_styles() {
        static $styles_added = false;
        
        if ($styles_added) {
            return;
        }
        
        $styles_added = true;
        
        // 样式已移至 frontend-widgets.css 文件中
        // 通过 Style Manager 自动加载
    }
}

/**
 * 注册小工具
 */
function folio_register_notification_widget() {
    register_widget('folio_Notification_Widget');
}
add_action('widgets_init', 'folio_register_notification_widget');
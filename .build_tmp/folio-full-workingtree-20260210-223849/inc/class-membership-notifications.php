<?php
/**
 * 会员通知系统核心类
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Membership_Notifications {

    private static $instance = null;
    private static $initialized = false;
    private static $table_name = 'folio_notifications';

    public function __construct() {
        if (self::$initialized) {
            return;
        }

        // 初始化数据库表
        $this->init_database();
        
        // 注册 AJAX 处理程序（支持未登录用户）
        add_action('wp_ajax_folio_get_notifications', array($this, 'ajax_get_notifications'));
        add_action('wp_ajax_nopriv_folio_get_notifications', array($this, 'ajax_get_notifications'));
        add_action('wp_ajax_folio_mark_notification_read', array($this, 'ajax_mark_read'));
        add_action('wp_ajax_folio_mark_all_read', array($this, 'ajax_mark_all_read'));
        add_action('wp_ajax_folio_delete_notification', array($this, 'ajax_delete_notification'));
        
        // 注册定时任务
        add_action('folio_check_membership_expiry', array($this, 'check_membership_expiry'));
        
        // 注册 WordPress Cron 定时任务（每天检查一次）
        if (!wp_next_scheduled('folio_daily_membership_check')) {
            wp_schedule_event(time(), 'daily', 'folio_daily_membership_check');
        }
        add_action('folio_daily_membership_check', array($this, 'check_membership_expiry'));
        
        // 会员相关钩子
        add_action('folio_membership_activated', array($this, 'on_membership_activated'), 10, 2);
        add_action('folio_membership_changed', array($this, 'on_membership_changed'), 10, 3);
        add_action('folio_membership_expired', array($this, 'on_membership_expired'), 10, 1);
        
        // WordPress 用户注册钩子（支持默认注册流程）
        add_action('user_register', array($this, 'on_user_register'), 10, 1);

        self::$initialized = true;
    }

    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 初始化数据库表
     */
    private function init_database() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            metadata longtext DEFAULT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            read_by_users longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // 兼容老版本数据结构：确保 read_by_users 字段存在
        $column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", 'read_by_users'));
        if (!$column_exists) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN read_by_users longtext DEFAULT NULL AFTER is_read");
        }
    }

    /**
     * 添加通知
     *
     * @param int $user_id 用户ID
     * @param string $type 通知类型
     * @param string $title 通知标题
     * @param string $message 通知内容
     * @param array $metadata 元数据
     * @param bool $replace_placeholders 是否替换占位符（默认true，对于用户特定通知）
     * @return int|false 通知ID或false
     */
    public function add_notification($user_id, $type, $title, $message, $metadata = null, $replace_placeholders = true) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        // 如果是用户特定通知且需要替换占位符，则替换
        if ($user_id > 0 && $replace_placeholders) {
            $title = $this->replace_placeholders($title, $user_id, $type, $metadata);
            $message = $this->replace_placeholders($message, $user_id, $type, $metadata);
        }
        
        // user_id = 0 表示全局通知（未登录用户可见）
        $data = array(
            'user_id' => absint($user_id),
            'type' => sanitize_text_field($type),
            'title' => sanitize_text_field($title),
            'message' => wp_kses_post($message),
            'metadata' => $metadata ? maybe_serialize($metadata) : null,
            'is_read' => 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            $notification_id = $wpdb->insert_id;
            
            // 触发钩子
            do_action('folio_notification_added', $notification_id, $user_id, $type);
            
            // 如果启用了邮件通知且用户ID不为0，发送邮件
            if ($user_id > 0) {
                $this->maybe_send_email($user_id, $type, $title, $message);
            }
            
            return $notification_id;
        }
        
        return false;
    }
    
    /**
     * 替换通知中的占位符为实际数据
     *
     * @param string $text 原始文本
     * @param int $user_id 用户ID
     * @param string $type 通知类型
     * @param array $metadata 通知的元数据（可选）
     * @return string 替换后的文本
     */
    private function replace_placeholders($text, $user_id, $type, $metadata = null) {
        if (!$user_id || $user_id <= 0) {
            return $text;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return $text;
        }
        
        // 替换用户相关占位符
        $text = str_replace('{username}', $user->display_name, $text);
        $text = str_replace('{user_name}', $user->display_name, $text);
        $text = str_replace('{user}', $user->display_name, $text);
        $text = str_replace('{email}', $user->user_email, $text);
        $text = str_replace('{user_email}', $user->user_email, $text);
        
        // 根据通知类型替换特定占位符
        switch ($type) {
            case 'membership_expiry':
                // 计算剩余天数（使用日期字符串格式）
                $expiry_date = get_user_meta($user_id, 'folio_membership_expiry', true);
                if ($expiry_date) {
                    $expiry_timestamp = strtotime($expiry_date . ' 23:59:59');
                } else {
                    $expiry_timestamp = null;
                }
                
                if ($expiry_timestamp) {
                    $days_remaining = max(0, floor(($expiry_timestamp - current_time('timestamp')) / DAY_IN_SECONDS));
                    $text = str_replace('{days}', $days_remaining, $text);
                } else {
                    $text = str_replace('{days}', '0', $text);
                }
                
                // 获取会员等级
                $membership_level = get_user_meta($user_id, 'folio_membership_level', true);
                if (!$membership_level) {
                    $membership_level = get_user_meta($user_id, '_folio_membership_level', true);
                }
                $level_name = $this->get_level_name($membership_level);
                $text = str_replace('{level}', $level_name, $text);
                break;
                
            case 'membership_changed':
                // 会员等级变更通知：优先从metadata中获取变更时的等级信息
                if ($metadata && is_array($metadata)) {
                    if (isset($metadata['old_level'])) {
                        $text = str_replace('{old_level}', $this->get_level_name($metadata['old_level']), $text);
                    }
                    if (isset($metadata['new_level'])) {
                        $text = str_replace('{new_level}', $this->get_level_name($metadata['new_level']), $text);
                        $text = str_replace('{level}', $this->get_level_name($metadata['new_level']), $text);
                    }
                } else {
                    // 如果没有metadata，使用当前等级
                    if (strpos($text, '{level}') !== false || strpos($text, '{old_level}') !== false || strpos($text, '{new_level}') !== false) {
                        $membership_level = get_user_meta($user_id, 'folio_membership_level', true);
                        if (!$membership_level) {
                            $membership_level = get_user_meta($user_id, '_folio_membership_level', true);
                        }
                        $level_name = $this->get_level_name($membership_level);
                        $text = str_replace('{level}', $level_name, $text);
                        $text = str_replace('{old_level}', __('Regular User', 'folio'), $text);
                        $text = str_replace('{new_level}', $level_name, $text);
                    }
                }
                break;
                
            case 'membership_activated':
            case 'membership_expired':
                // 获取会员等级
                $membership_level = get_user_meta($user_id, 'folio_membership_level', true);
                if (!$membership_level) {
                    $membership_level = get_user_meta($user_id, '_folio_membership_level', true);
                }
                $level_name = $this->get_level_name($membership_level);
                $text = str_replace('{level}', $level_name, $text);
                break;
        }
        
        return $text;
    }
    
    /**
     * 获取会员等级名称
     *
     * @param string $level 会员等级代码
     * @return string 会员等级名称
     */
    private function get_level_name($level) {
        if ($level === 'svip') {
            return 'SVIP';
        } elseif ($level === 'vip') {
            return 'VIP';
        } elseif ($level === 'free' || empty($level)) {
            return __('Regular User', 'folio');
        } else {
            return __('Regular User', 'folio');
        }
    }
    
    /**
     * 获取会员信息（用于邮件模板显示）
     *
     * @param int $user_id 用户ID
     * @param string $type 通知类型
     * @return array|null 会员信息数组或null
     */
    private function get_membership_info_for_email($user_id, $type) {
        // 只对会员相关通知显示会员信息
        $membership_types = array('membership_expiry', 'membership_expired', 'membership_changed', 'membership_activated');
        if (!in_array($type, $membership_types)) {
            return null;
        }
        
        $info = array();
        
        // 获取会员等级
        $membership_level = get_user_meta($user_id, 'folio_membership_level', true);
        if (!$membership_level) {
            $membership_level = get_user_meta($user_id, '_folio_membership_level', true);
        }
        if ($membership_level) {
            $info['level'] = $this->get_level_name($membership_level);
        }
        
        // 获取到期时间（使用日期字符串格式）
        $expiry_date = get_user_meta($user_id, 'folio_membership_expiry', true);
        if ($expiry_date) {
            $expiry_timestamp = strtotime($expiry_date . ' 23:59:59');
        } else {
            $expiry_timestamp = null;
        }
        
        if ($expiry_timestamp) {
            $info['expiry_date'] = wp_date(get_option('date_format'), $expiry_timestamp);
            
            // 计算剩余天数
            $days_remaining = floor(($expiry_timestamp - current_time('timestamp')) / DAY_IN_SECONDS);
            if ($days_remaining >= 0) {
                $info['days_remaining'] = $days_remaining;
            } else {
                $info['days_remaining'] = 0; // 已过期
            }
        }
        
        return !empty($info) ? $info : null;
    }

    /**
     * 获取用户通知
     *
     * @param int $user_id 用户ID（0表示未登录用户/全局通知）
     * @param array $args 查询参数
     * @return array
     */
    public function get_notifications($user_id, $args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'type' => null,
            'is_read' => null,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'include_global' => false // 是否包含全局通知（user_id = 0）
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // 构建查询条件：用户自己的通知 + 全局通知（如果启用）
        if ($user_id > 0 && $args['include_global']) {
            $where = array('(user_id = %d OR user_id = 0)');
            $values = array($user_id);
        } else {
            $where = array('user_id = %d');
            $values = array($user_id);
        }
        
        if ($args['type']) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }
        
        $filter_is_read_in_sql = !($user_id > 0 && $args['include_global']);
        if ($args['is_read'] !== null && $filter_is_read_in_sql) {
            $where[] = 'is_read = %d';
            $values[] = absint($args['is_read']);
        }
        
        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE {$where_clause} 
             ORDER BY {$orderby} 
             LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );
        
        $notifications = $wpdb->get_results($query);

        // 全局通知按用户记录已读，不再共享全局 is_read 状态
        if ($user_id > 0) {
            foreach ($notifications as $notification) {
                if ((int) $notification->user_id === 0) {
                    $notification->is_read = $this->is_global_notification_read_by_user($notification, $user_id) ? 1 : 0;
                }
            }
        } else {
            // 游客端不写库，统一视作未读
            foreach ($notifications as $notification) {
                if ((int) $notification->user_id === 0) {
                    $notification->is_read = 0;
                }
            }
        }

        // include_global + is_read 时改为内存过滤（全局通知读状态依赖 read_by_users）
        if ($args['is_read'] !== null && !$filter_is_read_in_sql) {
            $target_is_read = absint($args['is_read']);
            $notifications = array_values(array_filter($notifications, function($notification) use ($target_is_read) {
                return (int) $notification->is_read === $target_is_read;
            }));
        }

        return $notifications;
    }
    
    /**
     * 获取全局通知（未登录用户可见）
     *
     * @param array $args 查询参数
     * @return array
     */
    public function get_global_notifications($args = array()) {
        return $this->get_notifications(0, $args);
    }

    /**
     * 获取未读通知数量
     *
     * @param int $user_id 用户ID（0表示未登录用户）
     * @param bool $include_global 是否包含全局通知
     * @return int
     */
    public function get_unread_count($user_id, $include_global = false) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        if ($user_id > 0 && $include_global) {
            $notifications = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id, is_read, read_by_users FROM {$table_name} WHERE (user_id = %d OR user_id = 0)",
                $user_id
            ));

            $unread_count = 0;
            foreach ($notifications as $notification) {
                if ((int) $notification->user_id === 0) {
                    if (!$this->is_global_notification_read_by_user($notification, $user_id)) {
                        $unread_count++;
                    }
                } elseif ((int) $notification->is_read === 0) {
                    $unread_count++;
                }
            }

            return $unread_count;
        } else {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND is_read = 0",
                $user_id
            ));
        }
    }

    /**
     * 标记通知为已读
     *
     * @param int $notification_id 通知ID
     * @param int $user_id 用户ID（用于验证）
     * @return bool
     */
    public function mark_as_read($notification_id, $user_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;

        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, read_by_users FROM {$table_name} WHERE id = %d",
            $notification_id
        ));

        if (!$notification) {
            return false;
        }

        if ((int) $notification->user_id === 0) {
            if (!$user_id || $user_id <= 0) {
                return false;
            }
            $result = $this->mark_global_notification_as_read_for_user($notification_id, $user_id, $notification);
        } else {
            $where = array('id = %d');
            $values = array($notification_id);

            if ($user_id) {
                $where[] = 'user_id = %d';
                $values[] = $user_id;
            }

            $where_clause = implode(' AND ', $where);
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name} SET is_read = 1 WHERE {$where_clause}",
                $values
            ));
        }
        
        if ($result) {
            do_action('folio_notification_read', $notification_id, $user_id);
        }
        
        return $result !== false;
    }

    /**
     * 标记所有通知为已读
     *
     * @param int $user_id 用户ID
     * @return bool
     */
    public function mark_all_as_read($user_id) {
        global $wpdb;
        
        if (!$user_id || $user_id <= 0) {
            return false;
        }

        $table_name = $wpdb->prefix . self::$table_name;
        
        $user_specific_result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} SET is_read = 1 WHERE user_id = %d AND is_read = 0",
            $user_id
        ));

        $global_result = $this->mark_all_global_notifications_as_read_for_user($user_id);
        $result = ($user_specific_result !== false) && $global_result;
        
        if ($result !== false) {
            do_action('folio_all_notifications_read', $user_id);
        }
        
        return $result !== false;
    }

    /**
     * 将 read_by_users 字段解析为用户 ID 列表
     */
    private function parse_read_by_users($read_by_users) {
        if (empty($read_by_users)) {
            return array();
        }

        if (is_array($read_by_users)) {
            return array_values(array_unique(array_map('absint', $read_by_users)));
        }

        $decoded = json_decode((string) $read_by_users, true);
        if (is_array($decoded)) {
            return array_values(array_unique(array_map('absint', $decoded)));
        }

        $unserialized = maybe_unserialize($read_by_users);
        if (is_array($unserialized)) {
            return array_values(array_unique(array_map('absint', $unserialized)));
        }

        return array();
    }

    /**
     * 判断全局通知是否对指定用户已读
     */
    private function is_global_notification_read_by_user($notification, $user_id) {
        if (!$user_id || $user_id <= 0) {
            return false;
        }

        $read_by_users = $this->parse_read_by_users($notification->read_by_users ?? null);
        return in_array((int) $user_id, $read_by_users, true);
    }

    /**
     * 为指定用户标记一条全局通知为已读
     */
    private function mark_global_notification_as_read_for_user($notification_id, $user_id, $notification = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;
        if (!$notification) {
            $notification = $wpdb->get_row($wpdb->prepare(
                "SELECT id, user_id, read_by_users FROM {$table_name} WHERE id = %d",
                $notification_id
            ));
        }

        if (!$notification || (int) $notification->user_id !== 0) {
            return false;
        }

        $read_by_users = $this->parse_read_by_users($notification->read_by_users ?? null);
        if (!in_array((int) $user_id, $read_by_users, true)) {
            $read_by_users[] = (int) $user_id;
        }

        $result = $wpdb->update(
            $table_name,
            array('read_by_users' => wp_json_encode(array_values(array_unique($read_by_users)))),
            array('id' => $notification_id),
            array('%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * 为指定用户标记全部全局通知为已读
     */
    private function mark_all_global_notifications_as_read_for_user($user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;
        $global_notifications = $wpdb->get_results("SELECT id, user_id, read_by_users FROM {$table_name} WHERE user_id = 0");
        if (!$global_notifications) {
            return true;
        }

        foreach ($global_notifications as $notification) {
            if (!$this->mark_global_notification_as_read_for_user((int) $notification->id, $user_id, $notification)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 删除通知
     *
     * @param int $notification_id 通知ID
     * @param int $user_id 用户ID（用于验证）
     * @return bool
     */
    public function delete_notification($notification_id, $user_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $where = array('id = %d');
        $values = array($notification_id);
        
        if ($user_id) {
            $where[] = 'user_id = %d';
            $values[] = $user_id;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE {$where_clause}",
            $values
        ));
        
        if ($result) {
            do_action('folio_notification_deleted', $notification_id, $user_id);
        }
        
        return $result !== false;
    }

    /**
     * 检查会员到期并发送提醒
     * 
     * @return array 返回统计信息 ['checked' => 检查的用户数, 'reminders_sent' => 发送的提醒数, 'expired_sent' => 发送的过期通知数]
     */
    public function check_membership_expiry() {
        global $wpdb;
        
        $stats = array(
            'checked' => 0,
            'reminders_sent' => 0,
            'expired_sent' => 0,
            'errors' => array()
        );
        
        $settings = get_option('folio_notification_settings', array());
        $reminder_days = isset($settings['reminder_days']) ? $settings['reminder_days'] : '7,3,1,0';
        $reminder_days_array = array_map('trim', explode(',', $reminder_days));
        
        // 获取所有有会员的用户（使用 folio_membership_expiry 日期字符串格式）
        $users = get_users(array(
            'meta_key' => 'folio_membership_expiry',
            'meta_compare' => 'EXISTS'
        ));
        
        foreach ($users as $user) {
            $stats['checked']++;
            
            // 使用日期字符串格式
            $expiry_date = get_user_meta($user->ID, 'folio_membership_expiry', true);
            
            if (!$expiry_date) {
                continue;
            }
            
            // 日期字符串格式：转换为时间戳（设置为当天的23:59:59）
            $expiry_timestamp = strtotime($expiry_date . ' 23:59:59');
            
            if (!$expiry_timestamp) {
                continue;
            }
            
            $expiry_date = date('Y-m-d', $expiry_timestamp);
            $today = date('Y-m-d');
            $days_remaining = floor(($expiry_timestamp - current_time('timestamp')) / DAY_IN_SECONDS);
            
            // 检查是否已过期
            if ($expiry_timestamp < current_time('timestamp')) {
                // 检查是否已经发送过期通知
                $existing_notification = $this->get_notifications($user->ID, array(
                    'type' => 'membership_expired',
                    'limit' => 1
                ));
                
                if (empty($existing_notification) || 
                    strtotime($existing_notification[0]->created_at) < $expiry_timestamp) {
                    $this->on_membership_expired($user->ID);
                    $stats['expired_sent']++;
                }
                continue;
            }
            
            // 检查是否需要发送提醒
            // 将提醒天数转换为整数并排序（从大到小）
            $reminder_days_array = array_map('absint', $reminder_days_array);
            rsort($reminder_days_array); // 从大到小排序，优先检查较大的提醒天数
            
            $should_remind = false;
            $remind_day = null;
            
            foreach ($reminder_days_array as $day) {
                // 精确匹配：只有在剩余天数等于设置的提醒天数时，才发送该提醒点的通知
                if ($days_remaining == $day) {
                    // 检查今天是否已经发送过这个提醒
                    $today_start = strtotime('today');
                    $table_name = $wpdb->prefix . self::$table_name;
                    $existing_notification = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_name} 
                         WHERE user_id = %d 
                         AND type = 'membership_expiry'
                         AND created_at >= %s",
                        $user->ID,
                        date('Y-m-d H:i:s', $today_start)
                    ));
                    
                    // 如果今天还没发送过提醒，则发送
                    if ($existing_notification == 0) {
                        $should_remind = true;
                        $remind_day = $days_remaining;
                        break;
                    }
                }
                // 补发机制：如果剩余天数小于某个提醒点，且该提醒点还没发送过，则补发
                elseif ($days_remaining < $day) {
                    // 检查是否已经发送过这个提醒点的通知（通过检查metadata中的days_remaining）
                    $table_name = $wpdb->prefix . self::$table_name;
                    $existing_notification = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_name} 
                         WHERE user_id = %d 
                         AND type = 'membership_expiry'
                         AND metadata LIKE %s",
                        $user->ID,
                        '%"days_remaining";i:' . $day . '%'
                    ));
                    
                    // 如果没有发送过这个提醒点的通知，则补发（使用该提醒点的天数）
                    if ($existing_notification == 0) {
                        $should_remind = true;
                        $remind_day = $day; // 使用提醒点的天数，而不是实际剩余天数
                        break;
                    }
                }
            }
            
            // 如果找到了需要提醒的点，发送提醒
            if ($should_remind && $remind_day !== null) {
                try {
                    $this->send_expiry_reminder($user->ID, $remind_day);
                    $stats['reminders_sent']++;
                } catch (Exception $e) {
                    $stats['errors'][] = sprintf(
                        /* translators: 1: user ID, 2: error message */
                        __('Failed to send reminder for user ID %1$d: %2$s', 'folio'),
                        $user->ID,
                        $e->getMessage()
                    );
                }
            }
        }
        
        return $stats;
    }

    /**
     * 发送到期提醒
     *
     * @param int $user_id 用户ID
     * @param int $days_remaining 剩余天数
     */
    private function send_expiry_reminder($user_id, $days_remaining) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $membership_level = get_user_meta($user_id, '_folio_membership_level', true);
        $level_name = $membership_level === 'svip' ? 'SVIP' : 'VIP';
        
        if ($days_remaining == 0) {
            $title = __('Membership Expires Today', 'folio');
            $message = sprintf(
                /* translators: %s: membership level name */
                __('Your %s membership expires today. Please renew in time to keep your member benefits.', 'folio'),
                $level_name
            );
        } else {
            $title = sprintf(
                /* translators: %d: remaining days */
                __('Membership Expires in %d Days', 'folio'),
                $days_remaining
            );
            $message = sprintf(
                /* translators: 1: membership level name, 2: remaining days */
                __('Your %1$s membership expires in %2$d days. Please renew in time to keep your member benefits.', 'folio'),
                $level_name,
                $days_remaining
            );
        }
        
        $this->add_notification(
            $user_id,
            'membership_expiry',
            $title,
            $message,
            array('days_remaining' => $days_remaining)
        );
    }

    /**
     * 会员开通成功回调
     */
    public function on_membership_activated($user_id, $membership_level) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $level_name = $membership_level === 'svip' ? 'SVIP' : 'VIP';
        
        $this->add_notification(
            $user_id,
            'membership_activated',
            sprintf(
                /* translators: %s: membership level name */
                __('%s Membership Activated', 'folio'),
                $level_name
            ),
            sprintf(
                /* translators: %s: membership level name */
                __('Congratulations! Your %s membership is now active. You can now enjoy all member-exclusive benefits.', 'folio'),
                $level_name
            ),
            array('membership_level' => $membership_level)
        );
    }

    /**
     * 会员等级变更回调
     */
    public function on_membership_changed($user_id, $old_level, $new_level) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $old_level_name = $old_level === 'svip' ? 'SVIP' : ($old_level === 'vip' ? 'VIP' : __('Regular User', 'folio'));
        $new_level_name = $new_level === 'svip' ? 'SVIP' : ($new_level === 'vip' ? 'VIP' : __('Regular User', 'folio'));
        
        $this->add_notification(
            $user_id,
            'membership_changed',
            __('Membership Level Changed', 'folio'),
            sprintf(
                /* translators: 1: old membership level, 2: new membership level */
                __('Your membership level has changed from %1$s to %2$s.', 'folio'),
                $old_level_name,
                $new_level_name
            ),
            array(
                'old_level' => $old_level,
                'new_level' => $new_level
            )
        );
    }

    /**
     * WordPress 用户注册回调
     * 注意：如果通过 folio_User_Center 注册，邮件和通知已由该类处理
     * 此钩子主要用于 WordPress 默认注册流程（如 wp-admin 注册）
     */
    public function on_user_register($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        // 检查是否已经发送过通知（通过 User Center 注册的会在注册时发送）
        // 使用 transient 标记，避免重复发送
        $notification_sent = get_transient('folio_registration_email_sent_' . $user_id);
        if ($notification_sent) {
            return;
        }
        
        // 添加站内通知
        $this->add_notification(
            $user_id,
            'user_register',
            __('Registration Successful', 'folio'),
            __('Welcome aboard! Your account has been created successfully. You can now start exploring our content.', 'folio')
        );
        
        // 发送注册邮件（仅当通过 WordPress 默认流程注册时）
        $this->send_registration_emails_for_wordpress($user_id);
        
        // 标记已发送
        set_transient('folio_registration_email_sent_' . $user_id, true, 300); // 5分钟有效期
    }

    /**
     * 为 WordPress 默认注册流程发送邮件
     */
    private function send_registration_emails_for_wordpress($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        // 确保 SMTP 配置已加载
        if (class_exists('folio_SMTP_Mailer')) {
            folio_SMTP_Mailer::configure();
        }
        
        $settings = get_option('folio_notification_settings', array());
        
        // 检查是否启用邮件通知
        if (empty($settings['email_enabled'])) {
            return;
        }
        
        // 发送给用户的注册成功邮件
        $this->send_user_welcome_email_wordpress($user_id, $user->user_login, $user->user_email);
        
        // 发送给管理员的注册提醒邮件
        $this->send_admin_registration_notice_wordpress($user_id, $user->user_login, $user->user_email);
    }

    /**
     * 发送用户欢迎邮件（WordPress 默认流程）
     */
    private function send_user_welcome_email_wordpress($user_id, $username, $email) {
        $settings = get_option('folio_notification_settings', array());
        
        // 检查是否启用邮件通知
        if (empty($settings['email_enabled'])) {
            return;
        }
        
        // 检查用户注册通知是否启用了邮件通知
        if (isset($settings['email_types']['user_register']) && empty($settings['email_types']['user_register'])) {
            return;
        }
        
        $site_name = get_bloginfo('name');
        $user_center_url = home_url('/user-center');
        
        $subject = '[' . $site_name . '] ' . __('Welcome Registration!', 'folio');
        
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= '<h2 style="color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">' . esc_html(sprintf(__('Welcome to %s!', 'folio'), $site_name)) . '</h2>';
        $message .= '<p>' . wp_kses_post(sprintf(__('Dear <strong>%s</strong>,', 'folio'), esc_html($username))) . '</p>';
        $message .= '<p>' . esc_html__('Thank you for registering on our site. Your account has been created successfully.', 'folio') . '</p>';
        $message .= '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        $message .= '<p style="margin: 0;"><strong>' . esc_html__('Your Account Information:', 'folio') . '</strong></p>';
        $message .= '<p style="margin: 5px 0;">' . esc_html__('Username:', 'folio') . ' ' . esc_html($username) . '</p>';
        $message .= '<p style="margin: 5px 0;">' . esc_html__('Email:', 'folio') . ' ' . esc_html($email) . '</p>';
        $message .= '</div>';
        $message .= '<p>' . esc_html__('Now you can:', 'folio') . '</p>';
        $message .= '<ul style="padding-left: 20px;">';
        $message .= '<li>' . esc_html__('Browse our featured content', 'folio') . '</li>';
        $message .= '<li>' . esc_html__('Bookmark your favorite works', 'folio') . '</li>';
        $message .= '<li>' . esc_html__('Upgrade membership for more benefits', 'folio') . '</li>';
        $message .= '</ul>';
        $message .= '<div style="text-align: center; margin: 30px 0;">';
        $message .= '<a href="' . esc_url($user_center_url) . '" style="display: inline-block; background: #0073aa; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">' . esc_html__('Go to User Center', 'folio') . '</a>';
        $message .= '</div>';
        $message .= '<p style="color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">';
        $message .= esc_html__('If you have any questions, feel free to contact us.', 'folio') . '<br>';
        $message .= esc_html__('This email was sent automatically by the system. Please do not reply.', 'folio');
        $message .= '</p>';
        $message .= '</div>';
        $message .= '</body></html>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * 发送管理员注册提醒邮件（WordPress 默认流程）
     */
    private function send_admin_registration_notice_wordpress($user_id, $username, $email) {
        $settings = get_option('folio_notification_settings', array());
        
        // 检查是否启用邮件通知
        if (empty($settings['email_enabled'])) {
            return;
        }
        
        // 检查管理员用户注册提醒是否启用了邮件通知
        if (isset($settings['email_types']['admin_user_register']) && empty($settings['email_types']['admin_user_register'])) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        $user = get_userdata($user_id);
        $site_name = get_bloginfo('name');
        $admin_url = admin_url('user-edit.php?user_id=' . $user_id);
        $users_url = admin_url('users.php');
        
        $subject = '[' . $site_name . '] ' . __('New User Registration Alert', 'folio');
        
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= '<h2 style="color: #d63638; border-bottom: 2px solid #d63638; padding-bottom: 10px;">' . esc_html__('New User Registration', 'folio') . '</h2>';
        $message .= '<p>' . esc_html__('Hello Admin,', 'folio') . '</p>';
        $message .= '<p>' . esc_html__('A new user has registered on your website. Details are as follows:', 'folio') . '</p>';
        $message .= '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        $message .= '<p style="margin: 5px 0;"><strong>' . esc_html__('Username:', 'folio') . '</strong>' . esc_html($username) . '</p>';
        $message .= '<p style="margin: 5px 0;"><strong>' . esc_html__('Email:', 'folio') . '</strong>' . esc_html($email) . '</p>';
        $message .= '<p style="margin: 5px 0;"><strong>' . esc_html__('Registered At:', 'folio') . '</strong>' . esc_html($user->user_registered) . '</p>';
        $message .= '<p style="margin: 5px 0;"><strong>' . esc_html__('User ID:', 'folio') . '</strong>' . esc_html($user_id) . '</p>';
        $message .= '</div>';
        $message .= '<div style="text-align: center; margin: 30px 0;">';
        $message .= '<a href="' . esc_url($admin_url) . '" style="display: inline-block; background: #0073aa; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-right: 10px;">' . esc_html__('View User Details', 'folio') . '</a>';
        $message .= '<a href="' . esc_url($users_url) . '" style="display: inline-block; background: #666; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">' . esc_html__('Manage All Users', 'folio') . '</a>';
        $message .= '</div>';
        $message .= '<p style="color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">';
        $message .= esc_html__('This email was sent automatically by the system.', 'folio');
        $message .= '</p>';
        $message .= '</div>';
        $message .= '</body></html>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * 会员过期回调
     */
    public function on_membership_expired($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $this->add_notification(
            $user_id,
            'membership_expired',
            __('Membership Expired', 'folio'),
            __('Your membership has expired, and some features are no longer available. Please renew to restore your member benefits.', 'folio'),
            array('expired_at' => current_time('timestamp'))
        );
    }

    /**
     * 可能发送邮件通知
     */
    private function maybe_send_email($user_id, $type, $title, $message) {
        $settings = get_option('folio_notification_settings', array());
        
        // 检查是否启用了邮件通知
        if (empty($settings['email_enabled'])) {
            return;
        }
        
        // 检查该通知类型是否启用了邮件通知
        if (isset($settings['email_types'][$type]) && empty($settings['email_types'][$type])) {
            return;
        }
        
        // 如果没有设置该通知类型的开关，使用默认值（向后兼容）
        // 会员相关通知默认开启，其他默认关闭
        if (!isset($settings['email_types'][$type])) {
            $default_enabled = in_array($type, array('membership_expiry', 'membership_expired', 'membership_changed', 'membership_activated'));
            if (!$default_enabled) {
                return;
            }
        }
        
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            return;
        }
        
        // 确保 SMTP 配置已加载
        if (class_exists('folio_SMTP_Mailer')) {
            folio_SMTP_Mailer::configure();
        }
        
        $subject = '[' . get_bloginfo('name') . '] ' . $title;
        
        // 使用邮件模板
        $email_message = $this->get_email_template($title, $message, $type, $user);
        
        // 设置邮件头
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $email_message, $headers);
    }
    
    /**
     * 获取邮件模板
     *
     * @param string $title 通知标题
     * @param string $message 通知内容
     * @param string $type 通知类型
     * @param WP_User $user 用户对象
     * @return string HTML格式的邮件内容
     */
    public function get_email_template($title, $message, $type = 'test', $user = null) {
        // 如果没有提供用户对象，使用当前用户
        if (!$user) {
            $user_id = get_current_user_id();
            $user = $user_id ? get_userdata($user_id) : null;
        }
        
        if (!$user) {
            return '';
        }
        
        $user_id = $user->ID;
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        // 使用用户中心的正确路由
        if (class_exists('folio_User_Center')) {
            $user_center_url = folio_User_Center::get_url('notifications');
        } else {
            $user_center_url = home_url('/user-center/notifications');
        }
        
        // 获取网站Logo
        $logo_url = $this->get_site_logo_url();
        
        // 根据通知类型设置图标和颜色
        $type_config = $this->get_email_type_config($type);
        
        // 获取会员相关信息（用于在邮件中显示）
        $membership_info = $this->get_membership_info_for_email($user_id, $type);
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($title) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;">
                    <!-- Top Header with Logo -->
                    <tr>
                        <td style="padding: 40px 40px 30px; background-color: #ffffff; text-align: center;">
                            ' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '" width="120" style="max-width: 120px; height: auto; margin-bottom: 20px; display: block; margin-left: auto; margin-right: auto;" />' : '<h1 style="margin: 0 0 20px 0; color: #111827; font-size: 28px; font-weight: 700;">' . esc_html($site_name) . '</h1>') . '
                            <div style="width: 60px; height: 4px; background: linear-gradient(90deg, ' . esc_attr($type_config['header_color']) . ' 0%, ' . esc_attr($type_config['header_color_dark']) . ' 100%); margin: 0 auto; border-radius: 2px;"></div>
                        </td>
                    </tr>
                    
                    <!-- Title Section -->
                    <tr>
                        <td style="padding: 0 40px 30px; text-align: center;">
                            <h2 style="margin: 0; color: #111827; font-size: 32px; font-weight: 700; letter-spacing: -0.5px;">' . esc_html($title) . '</h2>
                        </td>
                    </tr>
                    
                    <!-- Greeting -->
                    <tr>
                        <td style="padding: 0 40px 20px;">
                            <p style="margin: 0; color: #374151; font-size: 16px; line-height: 1.6;">' . wp_kses_post(sprintf(__('Dear <strong>%s</strong>,', 'folio'), esc_html($user->display_name))) . '</p>
                        </td>
                    </tr>
                    
                    ' . ($membership_info ? '
                    <!-- Membership Info Card -->
                    <tr>
                        <td style="padding: 0 40px 20px;">
                            <div style="background: linear-gradient(135deg, ' . esc_attr($type_config['header_color']) . '15 0%, ' . esc_attr($type_config['header_color_dark']) . '10 100%); border: 2px solid ' . esc_attr($type_config['border_color']) . '40; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    ' . ($membership_info['level'] ? '
                                    <tr>
                                        <td style="padding-bottom: 10px;">
                                            <p style="margin: 0; color: #6b7280; font-size: 13px; font-weight: 500;">' . esc_html__('Membership Level', 'folio') . '</p>
                                            <p style="margin: 5px 0 0 0; color: #111827; font-size: 18px; font-weight: 700;">' . esc_html($membership_info['level']) . '</p>
                                        </td>
                                    </tr>
                                    ' : '') . '
                                    ' . ($membership_info['expiry_date'] ? '
                                    <tr>
                                        <td style="padding-bottom: 10px;">
                                            <p style="margin: 0; color: #6b7280; font-size: 13px; font-weight: 500;">' . esc_html__('Expiry Date', 'folio') . '</p>
                                            <p style="margin: 5px 0 0 0; color: #111827; font-size: 18px; font-weight: 700;">' . esc_html($membership_info['expiry_date']) . '</p>
                                        </td>
                                    </tr>
                                    ' : '') . '
                                    ' . (isset($membership_info['days_remaining']) && $membership_info['days_remaining'] !== null ? '
                                    <tr>
                                        <td>
                                            <p style="margin: 0; color: #6b7280; font-size: 13px; font-weight: 500;">' . esc_html__('Days Remaining', 'folio') . '</p>
                                            <p style="margin: 5px 0 0 0; color: ' . esc_attr($membership_info['days_remaining'] <= 3 ? '#ef4444' : ($membership_info['days_remaining'] <= 7 ? '#f59e0b' : '#10b981')) . '; font-size: 18px; font-weight: 700;">' . sprintf(esc_html__('%d days', 'folio'), esc_html($membership_info['days_remaining'])) . '</p>
                                        </td>
                                    </tr>
                                    ' : '') . '
                                </table>
                            </div>
                        </td>
                    </tr>
                    ' : '') . '
                    
                    <!-- Message Content -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <div style="background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%); border-left: 4px solid ' . esc_attr($type_config['border_color']) . '; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                                <p style="margin: 0; color: #374151; font-size: 16px; line-height: 1.8;">' . nl2br(esc_html($message)) . '</p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- CTA Button -->
                    <tr>
                        <td style="padding: 0 40px 40px; text-align: center;">
                            <a href="' . esc_url($user_center_url) . '" style="display: inline-block; background-color: ' . esc_attr($type_config['button_color']) . '; color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease;">' . esc_html__('View Notification Details', 'folio') . '</a>
                        </td>
                    </tr>
                    
                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <div style="height: 1px; background: linear-gradient(90deg, transparent 0%, #e5e7eb 50%, transparent 100%);"></div>
                        </td>
                    </tr>
                    
                    <!-- Footer Links -->
                    <tr>
                        <td style="padding: 30px 40px; text-align: center;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 20px;">
                                        <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 14px; font-weight: 500;">' . esc_html__('Visit Our Website', 'folio') . '</p>
                                        <a href="' . esc_url($site_url) . '" style="display: inline-block; color: ' . esc_attr($type_config['button_color']) . '; text-decoration: none; font-size: 15px; font-weight: 600;">' . esc_html($site_url) . '</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Bottom Footer -->
                    <tr>
                        <td style="padding: 20px 40px; background-color: #111827; text-align: center;">
                            <p style="margin: 0 0 8px 0; color: #9ca3af; font-size: 12px; line-height: 1.5;">
                                ' . wp_kses_post(sprintf(__('This email was sent automatically by <strong style="color: #ffffff;">%s</strong>.', 'folio'), esc_html($site_name))) . '
                            </p>
                            <p style="margin: 0; color: #6b7280; font-size: 11px;">
                                ' . esc_html__('If you have any questions, feel free to contact us.', 'folio') . '
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * 获取网站Logo URL
     *
     * @return string Logo URL
     */
    private function get_site_logo_url() {
        // 优先使用主题设置中的LOGO
        $theme_options = get_option('folio_theme_options', array());
        if (!empty($theme_options['site_logo'])) {
            return esc_url($theme_options['site_logo']);
        }
        
        // 其次使用WordPress自定义Logo
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
            if ($logo_data) {
                return esc_url($logo_data[0]);
            }
        }
        
        // 最后使用网站图标
        $site_icon = get_site_icon_url();
        if ($site_icon) {
            return esc_url($site_icon);
        }
        
        return '';
    }
    
    /**
     * 获取邮件类型配置（图标、颜色等）
     *
     * @param string $type 通知类型
     * @return array 配置数组
     */
    private function get_email_type_config($type) {
        $configs = array(
            'membership_expiry' => array(
                'icon' => '⏰',
                'header_color' => '#f59e0b',
                'header_color_dark' => '#d97706',
                'border_color' => '#f59e0b',
                'button_color' => '#f59e0b'
            ),
            'membership_expired' => array(
                'icon' => '⚠️',
                'header_color' => '#ef4444',
                'header_color_dark' => '#dc2626',
                'border_color' => '#ef4444',
                'button_color' => '#ef4444'
            ),
            'membership_changed' => array(
                'icon' => '🔄',
                'header_color' => '#10b981',
                'header_color_dark' => '#059669',
                'border_color' => '#10b981',
                'button_color' => '#10b981'
            ),
            'membership_activated' => array(
                'icon' => '✅',
                'header_color' => '#3b82f6',
                'header_color_dark' => '#2563eb',
                'border_color' => '#3b82f6',
                'button_color' => '#3b82f6'
            ),
            'security_alert' => array(
                'icon' => '🔒',
                'header_color' => '#ef4444',
                'header_color_dark' => '#dc2626',
                'border_color' => '#ef4444',
                'button_color' => '#ef4444'
            ),
            'security_warning' => array(
                'icon' => '⚠️',
                'header_color' => '#f59e0b',
                'header_color_dark' => '#d97706',
                'border_color' => '#f59e0b',
                'button_color' => '#f59e0b'
            ),
            'user_register' => array(
                'icon' => '👤',
                'header_color' => '#8b5cf6',
                'header_color_dark' => '#7c3aed',
                'border_color' => '#8b5cf6',
                'button_color' => '#8b5cf6'
            ),
            'test' => array(
                'icon' => '📧',
                'header_color' => '#3b82f6',
                'header_color_dark' => '#2563eb',
                'border_color' => '#3b82f6',
                'button_color' => '#3b82f6'
            )
        );
        
        // 默认配置
        $default = array(
            'icon' => '📢',
            'header_color' => '#6b7280',
            'header_color_dark' => '#4b5563',
            'border_color' => '#6b7280',
            'button_color' => '#6b7280'
        );
        
        return isset($configs[$type]) ? $configs[$type] : $default;
    }

    /**
     * AJAX: 获取通知列表（支持未登录用户）
     */
    public function ajax_get_notifications() {
        check_ajax_referer('folio_notifications_nonce', 'nonce');
        
        // 支持未登录用户（user_id = 0 表示未登录用户）
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        
        // 如果用户已登录，包含全局通知；如果未登录，只获取全局通知
        $notifications = $this->get_notifications($user_id, array(
            'limit' => $limit,
            'offset' => $offset,
            'include_global' => true
        ));
        
        // 格式化数据
        $formatted = array();
        foreach ($notifications as $notification) {
            $formatted[] = array(
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'is_read' => $notification->is_read,
                'created_at' => $notification->created_at,
                'metadata' => $notification->metadata ? maybe_unserialize($notification->metadata) : null
            );
        }
        
        wp_send_json_success($formatted);
    }

    /**
     * AJAX: 标记通知为已读
     */
    public function ajax_mark_read() {
        check_ajax_referer('folio_notifications_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in first', 'folio')));
            return;
        }
        
        $notification_id = isset($_POST['notification_id']) ? absint($_POST['notification_id']) : 0;
        
        if (!$notification_id) {
            wp_send_json_error(array('message' => __('Invalid notification ID', 'folio')));
            return;
        }
        
        // 检查通知是否存在，并获取其 user_id
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;
        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM {$table_name} WHERE id = %d",
            $notification_id
        ));
        
        if (!$notification) {
            wp_send_json_error(array('message' => __('Notification not found', 'folio')));
            return;
        }
        
        $user_id = get_current_user_id();
        if ((int) $notification->user_id > 0 && (int) $notification->user_id !== $user_id) {
            wp_send_json_error(array('message' => __('You are not allowed to operate this notification', 'folio')));
            return;
        }

        $result = $this->mark_as_read($notification_id, $user_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Marked as read', 'folio')));
        } else {
            wp_send_json_error(array('message' => __('Operation failed', 'folio')));
        }
    }

    /**
     * AJAX: 标记所有通知为已读
     */
    public function ajax_mark_all_read() {
        check_ajax_referer('folio_notifications_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in first', 'folio')));
            return;
        }

        $user_id = get_current_user_id();
        $result = $this->mark_all_as_read($user_id);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('All marked as read', 'folio')));
        } else {
            wp_send_json_error(array('message' => __('Operation failed', 'folio')));
        }
    }

    /**
     * AJAX: 删除通知
     */
    public function ajax_delete_notification() {
        check_ajax_referer('folio_notifications_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in first', 'folio')));
            return;
        }
        
        $notification_id = isset($_POST['notification_id']) ? absint($_POST['notification_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$notification_id) {
            wp_send_json_error(array('message' => __('Invalid notification ID', 'folio')));
            return;
        }
        
        $result = $this->delete_notification($notification_id, $user_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Deleted', 'folio')));
        } else {
            wp_send_json_error(array('message' => __('Delete failed', 'folio')));
        }
    }
}

// 初始化通知系统
folio_Membership_Notifications::get_instance();
